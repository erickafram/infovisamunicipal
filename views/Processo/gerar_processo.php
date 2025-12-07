<?php
session_start();
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Definição do Ghostscript com base no ambiente
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Ambiente local (Windows)
    $ghostscriptPath = '"C:\\Program Files\\gs\\gs10.04.0\\bin\\gswin64c.exe"';
} else {
    // Ambiente de produção (Linux)
    $ghostscriptPath = '/usr/bin/gs';
}

require_once '../../conf/database.php';
require_once '../../models/Documento.php';
require_once '../../models/Processo.php';
require_once '../../models/OrdemServico.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Logomarca.php';

// Função para registrar logs
function writeLog($message)
{
    $logFile = __DIR__ . '/gerar_processo.log';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function optimizePdf($inputFile, $outputFile)
{
    global $ghostscriptPath;

    if (!file_exists($inputFile)) {
        throw new Exception("Arquivo de entrada não encontrado: $inputFile");
    }

    // Tentar otimizar com Ghostscript se disponível
    try {
    $gsCommand = $ghostscriptPath . " -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen -dNOPAUSE -dBATCH -sOutputFile="
        . escapeshellarg($outputFile) . " " . escapeshellarg($inputFile);

    writeLog("Comando Ghostscript: $gsCommand");

    exec($gsCommand, $output, $returnVar);
        
    if ($returnVar !== 0) {
            writeLog("Ghostscript falhou com código $returnVar: " . implode("\n", $output));
            throw new Exception("Erro ao processar PDF com Ghostscript");
        }
        
        // Verificar se o arquivo foi criado
        if (!file_exists($outputFile)) {
            throw new Exception("Arquivo otimizado não foi criado");
        }
        
        writeLog("PDF otimizado com sucesso: $outputFile");
    } catch (Exception $e) {
        writeLog("Erro na otimização, copiando arquivo original: " . $e->getMessage());
        // Se falhar, copiar o arquivo original
        if (!copy($inputFile, $outputFile)) {
            throw new Exception("Erro ao copiar arquivo original");
        }
    }
}

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    writeLog("Acesso negado: Usuário sem permissão.");
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['processo_id']) || !isset($_GET['id'])) {
    writeLog("Erro: Processo ou Estabelecimento não fornecido.");
    die("Processo ou Estabelecimento não fornecido!");
}

$processo_id = $_GET['processo_id'];
$estabelecimento_id = $_GET['id'];

$documento     = new Documento($conn);
$processo      = new Processo($conn);
$ordemServico  = new OrdemServico($conn);
$arquivo       = new Arquivo($conn);
$logomarca     = new Logomarca($conn);

$processoDados = $processo->findById($processo_id);
if (!$processoDados) {
    writeLog("Erro: Processo não encontrado para ID: $processo_id");
    die("Processo não encontrado.");
}

$municipio_estabelecimento = $processoDados['municipio'];
if ($_SESSION['user']['nivel_acesso'] != 1 && $_SESSION['user']['municipio'] != $municipio_estabelecimento) {
    writeLog("Acesso negado: Usuário não tem permissão para acessar o município $municipio_estabelecimento.");
    die("Você não tem permissão para acessar este processo.");
}

$documentos    = $documento->getDocumentosByProcesso($processo_id);
$arquivos      = $arquivo->getArquivosByProcesso($processo_id);
$ordensServico = $ordemServico->getOrdensByProcesso($processo_id);

writeLog("Documentos encontrados: " . count($documentos));
writeLog("Arquivos encontrados: " . count($arquivos));
writeLog("Ordens de Serviço encontradas: " . count($ordensServico));

function isPdf($path)
{
    return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
}

$itens = [];

// Processar documentos
foreach ($documentos as $doc) {
    $caminho = '../../' . $doc['caminho_arquivo'];
    writeLog("Verificando documento: " . $doc['nome_arquivo'] . " - Caminho: " . $caminho);
    
    if (file_exists($caminho)) {
        if (isPdf($caminho)) {
        $itens[] = ['nome' => $doc['nome_arquivo'], 'data' => $doc['data_upload'], 'caminho' => $caminho];
            writeLog("Documento PDF adicionado: " . $doc['nome_arquivo']);
        } else {
            writeLog("Documento não é PDF: " . $doc['nome_arquivo']);
        }
    } else {
        writeLog("Arquivo não encontrado: " . $caminho);
    }
}

// Processar arquivos
foreach ($arquivos as $arq) {
    if (!empty($arq['caminho_arquivo'])) {
        $caminho = '../../' . $arq['caminho_arquivo'];
            $nome_arq = $arq['tipo_documento'] . " " . $arq['id'] . "." . date('Y', strtotime($arq['data_upload']));
        writeLog("Verificando arquivo: " . $nome_arq . " - Caminho: " . $caminho);
        
        if (file_exists($caminho)) {
            if (isPdf($caminho)) {
            $itens[] = ['nome' => $nome_arq, 'data' => $arq['data_upload'], 'caminho' => $caminho];
                writeLog("Arquivo PDF adicionado: " . $nome_arq);
            } else {
                writeLog("Arquivo não é PDF: " . $nome_arq);
            }
        } else {
            writeLog("Arquivo não encontrado: " . $caminho);
        }
    } else {
        writeLog("Arquivo sem caminho definido: " . $arq['id']);
    }
}

// Processar ordens de serviço
foreach ($ordensServico as $os) {
    if (!empty($os['pdf_path'])) {
        $caminho = '../../' . $os['pdf_path'];
            $nome_os = "Ordem de Serviço " . $os['id'] . "." . date('Y', strtotime($os['data_inicio']));
        writeLog("Verificando ordem de serviço: " . $nome_os . " - Caminho: " . $caminho);
        
        if (file_exists($caminho)) {
            if (isPdf($caminho)) {
            $itens[] = ['nome' => $nome_os, 'data' => $os['data_inicio'], 'caminho' => $caminho];
                writeLog("Ordem de Serviço PDF adicionada: " . $nome_os);
            } else {
                writeLog("Ordem de Serviço não é PDF: " . $nome_os);
            }
        } else {
            writeLog("Ordem de Serviço não encontrada: " . $caminho);
        }
    } else {
        writeLog("Ordem de Serviço sem caminho PDF: " . $os['id']);
    }
}

usort($itens, function ($a, $b) {
    return strtotime($a['data']) - strtotime($b['data']);
});

require '../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Criação de uma classe estendendo FPDI para adicionar rodapé com numeração de páginas
class PDF extends FPDI
{
    // Sobrescreve o método Footer para incluir a numeração de páginas
    public function Footer()
    {
        // Posiciona o rodapé a 15 mm da parte inferior
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        // Exibe "Página X de {nb}"
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }
}

// Instancia a nova classe PDF e define o alias para o total de páginas
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);

function toLatin1($str)
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
}

$logomarca_info = $logomarca->getLogomarcaByMunicipio($municipio_estabelecimento);
if ($logomarca_info) {
    writeLog("Logomarca encontrada no banco para o município: $municipio_estabelecimento. Caminho armazenado: " . $logomarca_info['caminho_logomarca']);
    $logoPath = realpath(__DIR__ . '/' . $logomarca_info['caminho_logomarca']);
    if ($logoPath && file_exists($logoPath)) {
        writeLog("Caminho absoluto resolvido: $logoPath");
    } else {
        writeLog("Arquivo não encontrado no caminho resolvido: " . ($logoPath ?: "não definido"));
        $logoPath = null;
    }
} else {
    writeLog("Nenhuma logomarca encontrada no banco para o município: $municipio_estabelecimento");
    $logoPath = null;
}

$pdf->SetFont('Helvetica', '', 12);
$pdf->AddPage();

if ($logoPath && file_exists($logoPath)) {
    list($lw, $lh) = getimagesize($logoPath);
    $desiredWidth = 40;
    $desiredHeight = ($lh / $lw) * $desiredWidth;
    $pdf->Image($logoPath, ($pdf->GetPageWidth() / 2 - $desiredWidth / 2), 10, $desiredWidth, $desiredHeight, 'PNG');
    $pdf->Ln($desiredHeight + 20);
} else {
    $pdf->Ln(20);
}

$pdf->SetFont('Helvetica', 'B', 14);
$pdf->Cell(0, 10, toLatin1($processoDados['tipo_processo']), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Helvetica', '', 12);
$pdf->Cell(0, 10, toLatin1("Número do Processo: " . $processoDados['numero_processo']), 0, 1, 'C');

if ($processoDados['tipo_pessoa'] == 'fisica') {
    $pdf->Cell(0, 10, toLatin1("CPF: " . $processoDados['cpf']), 0, 1, 'C');
    $pdf->Cell(0, 10, toLatin1("Nome: " . $processoDados['nome']), 0, 1, 'C');
} else {
    $pdf->Cell(0, 10, toLatin1("CNPJ: " . $processoDados['cnpj']), 0, 1, 'C');
    $pdf->Cell(0, 10, toLatin1("Nome do Estabelecimento: " . $processoDados['nome_fantasia']), 0, 1, 'C');
}

$endereco = $processoDados['logradouro'] . ', ' . $processoDados['numero'] . ', ' . $processoDados['bairro'] . ', ' . $processoDados['municipio'];
$pdf->MultiCell(0, 10, toLatin1("Endereço: " . $endereco), 0, 'C');
$pdf->Ln(10);

$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Ln(5);

writeLog("Total de itens encontrados: " . count($itens));

$tempDir = __DIR__ . '/temp/';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$documentosProcessados = 0;

foreach ($itens as $index => $item) {
    writeLog("Processando item " . ($index + 1) . ": " . $item['nome'] . " - Caminho: " . $item['caminho']);
    
    // Verificar se o arquivo existe
    if (!file_exists($item['caminho'])) {
        writeLog("Arquivo não encontrado: " . $item['caminho']);
        continue;
    }
    
    // Verificar se é um PDF válido
    if (!isPdf($item['caminho'])) {
        writeLog("Arquivo não é PDF: " . $item['caminho']);
        continue;
    }
    
    $tempPath = $tempDir . 'temp_' . $index . '_' . basename($item['caminho']);
    
    try {
        // Tentar primeiro com otimização
        $arquivoParaUsar = $item['caminho'];
        $usarTemporario = false;
        
        try {
            writeLog("Tentando otimizar PDF: " . $item['caminho']);
        optimizePdf($item['caminho'], $tempPath);
            $arquivoParaUsar = $tempPath;
            $usarTemporario = true;
            writeLog("PDF otimizado com sucesso: " . $tempPath);
        } catch (Exception $e) {
            writeLog("Otimização falhou, usando arquivo original: " . $e->getMessage());
            $arquivoParaUsar = $item['caminho'];
            $usarTemporario = false;
        }
        
        // Não adicionar separadores entre documentos
        
        writeLog("Iniciando importação do PDF: " . $arquivoParaUsar);
        $pageCount = $pdf->setSourceFile($arquivoParaUsar);
        writeLog("Número de páginas do PDF: " . $pageCount);
        
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            try {
            $tplIdx = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplIdx);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplIdx);
                writeLog("Página $pageNo importada com sucesso");
            } catch (Exception $e) {
                writeLog("Erro ao importar página $pageNo: " . $e->getMessage());
                continue;
            }
        }
        
        $documentosProcessados++;
        writeLog("Documento processado com sucesso: " . $item['nome']);
        
        // Limpar arquivo temporário se foi usado
        if ($usarTemporario && file_exists($tempPath)) {
        unlink($tempPath);
        }
        
    } catch (Exception $e) {
        writeLog("Erro fatal ao processar PDF '" . $item['nome'] . "': " . $e->getMessage());
        
        // Limpar arquivo temporário em caso de erro
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
        
        // Tentar uma última vez com o arquivo original diretamente
        try {
            writeLog("Tentativa final com arquivo original: " . $item['caminho']);
            $pageCount = $pdf->setSourceFile($item['caminho']);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplIdx = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($tplIdx);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplIdx);
            }
            
            $documentosProcessados++;
            writeLog("Documento processado com sucesso na tentativa final: " . $item['nome']);
            
        } catch (Exception $e2) {
            writeLog("Tentativa final falhou para '" . $item['nome'] . "': " . $e2->getMessage());
        continue;
        }
    }
}

writeLog("Total de documentos processados: " . $documentosProcessados);

$pdf->Output('I', 'processo_integral.pdf');
exit;
