<?php
require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Usuario.php';
require_once '../../models/UsuarioExterno.php';
require_once '../../models/Assinatura.php';
require_once '../../models/ResponsavelLegal.php';
require_once '../../models/ResponsavelTecnico.php';
require_once '../../models/Logomarca.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../vendor/autoload.php';


use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class CustomPDF extends TCPDF
{
    private $qrCodePath;
    private $logoPath;

    public function setQrCodePath($qrCodePath)
    {
        $this->qrCodePath = $qrCodePath;
    }

    public function setLogoPath($logoPath)
    {
        $this->logoPath = $logoPath;
    }

    public function Header()
    {
        if ($this->logoPath && $this->getPage() == 1) { // Apenas na primeira página
            // Obtenha as dimensões originais da imagem
            list($originalWidth, $originalHeight) = getimagesize($this->logoPath);

            // Defina a largura e altura desejadas mantendo a proporção
            $desiredWidth = 40; // Defina a largura desejada
            $desiredHeight = ($originalHeight / $originalWidth) * $desiredWidth; // Calcula a altura proporcional

            // Insira a imagem com as novas dimensões
            $this->Image($this->logoPath, $this->getPageWidth() / 2 - ($desiredWidth / 2), 10, $desiredWidth, $desiredHeight, 'PNG');
            $this->Ln(30); // Ajustar esse valor para criar espaço suficiente para a logomarca
        }
    }


    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        if ($this->qrCodePath) {
            $this->Image($this->qrCodePath, $this->getPageWidth() - 30, $this->getPageHeight() - 30, 20, 20, 'PNG');
        }
    }
}

class ArquivoController
{
    private $conn; // Adicione esta linha
    private $arquivo;
    private $estabelecimento;
    private $usuario;
    private $assinatura;
    private $responsavelLegal;
    private $responsavelTecnico;
    private $logomarca;

    public function __construct($conn)
    {
        $this->conn = $conn; // Salve a conexão na propriedade $conn
        $this->arquivo = new Arquivo($conn);
        $this->estabelecimento = new Estabelecimento($conn);
        $this->usuario = new Usuario($conn);
        $this->assinatura = new Assinatura($conn);
        $this->responsavelLegal = new ResponsavelLegal($conn);
        $this->responsavelTecnico = new ResponsavelTecnico($conn);
        $this->logomarca = new Logomarca($conn);
    }

    private function writeLog($message)
    {
        $logFile = __DIR__ . '/arquivo_controller.log';
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    public function Header()
    {
        if ($this->logoPath) {
            $this->Image($this->logoPath, $this->getPageWidth() / 2 - 20, 10, 40, 20, 'PNG');
            $this->Ln(30); // Adjust this value to create enough space for the logo
        }
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['processo_id']) && isset($_POST['conteudo']) && isset($_POST['tipo_documento'])) {
            $processo_id = $_POST['processo_id'];
            $sigiloso = isset($_POST['sigiloso']) ? intval($_POST['sigiloso']) : 0;
            $conteudo = $_POST['conteudo'];
            $tipo_documento = $_POST['tipo_documento'];
            $ano = date('Y');
            $upload_dir = __DIR__ . '/../uploads';

            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $estabelecimento_id = $_POST['estabelecimento_id'];
            $codigo_verificador = md5(uniqid(rand(), true));
            $arquivo_id = $this->arquivo->createArquivo($processo_id, $tipo_documento, 'uploads/temp', $codigo_verificador, $conteudo, $sigiloso);

            if ($arquivo_id === false) {
                header("Location: ../Processo/documentos.php?processo_id=$processo_id&id=$estabelecimento_id&error=" . urlencode('Erro ao registrar o arquivo no banco de dados.'));
                exit();
            }

            $numero_arquivo = $arquivo_id;
            $nome_arquivo = "{$tipo_documento}_{$numero_arquivo}_{$ano}";
            $caminho_arquivo = $upload_dir . DIRECTORY_SEPARATOR . $nome_arquivo;
            $this->arquivo->updateArquivoPathAndCodigo($arquivo_id, 'uploads/' . $nome_arquivo, $codigo_verificador);

            $assinantes = isset($_POST['assinantes']) ? $_POST['assinantes'] : [];
            foreach ($assinantes as $assinante_id) {
                $this->assinatura->createAssinatura($arquivo_id, $assinante_id);
            }

            header("Location: ../Processo/documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
            exit();
        }
    }

    public function gerarPdf($arquivo_id)
    {
        $this->writeLog("Iniciando a geração do PDF para o arquivo ID: $arquivo_id");

        $arquivo = $this->arquivo->getArquivoById($arquivo_id);
        if (!$arquivo || !isset($arquivo['status']) || $arquivo['status'] != 'assinado') {
            $this->writeLog("Arquivo não encontrado ou não assinado para o ID: $arquivo_id");
            return false;
        }

        $processo_id = $arquivo['processo_id'];
        $tipo_documento = $arquivo['tipo_documento'];
        $ano = date('Y');
        $upload_dir = __DIR__ . '/../uploads';
        $nome_arquivo = "{$tipo_documento}_{$arquivo_id}_{$ano}.pdf";
        $caminho_arquivo = $upload_dir . DIRECTORY_SEPARATOR . $nome_arquivo;
        $codigo_verificador = $arquivo['codigo_verificador'];

        // Obter o ID do estabelecimento a partir do processo
        $processo = $this->arquivo->getProcessoInfo($processo_id);
        if (!$processo) {
            $this->writeLog("Processo não encontrado para ID: $processo_id");
            return false;
        }

        $this->writeLog("Processo encontrado: " . json_encode($processo));

        if (!isset($processo['estabelecimento_id']) || empty($processo['estabelecimento_id'])) {
            $this->writeLog("Processo sem estabelecimento associado para ID: $processo_id");
            return false;
        }

        $estabelecimento_id = $processo['estabelecimento_id'];
        $this->writeLog("Estabelecimento ID: $estabelecimento_id associado ao processo ID: $processo_id");

        $estabelecimento = $this->estabelecimento->findById($estabelecimento_id);
        if (!$estabelecimento) {
            $this->writeLog("Estabelecimento não encontrado para ID: $estabelecimento_id");
            return false;
        }

        $this->writeLog("Estabelecimento encontrado: " . json_encode($estabelecimento));

        // Obter informações do responsável legal
        $responsavel_legal = $this->responsavelLegal->findByEstabelecimentoId($estabelecimento_id);
        if ($responsavel_legal) {
            $responsavel_legal['cpf'] = $this->mask($responsavel_legal['cpf'], '###.###.###-##');
        } else {
            $responsavel_legal = ['nome' => 'N/A', 'cpf' => 'N/A'];
        }

        // Obter informações do responsável técnico
        $responsavel_tecnico = $this->responsavelTecnico->findByEstabelecimentoId($estabelecimento_id);
        if ($responsavel_tecnico) {
            $responsavel_tecnico['cpf'] = $this->mask($responsavel_tecnico['cpf'], '###.###.###-##');
        } else {
            $responsavel_tecnico = ['nome' => 'N/A', 'cpf' => 'N/A'];
        }

        $processo_info = $this->arquivo->getProcessoInfo($processo_id);
        if (!$processo_info) {
            $this->writeLog("Processo não encontrado para ID: $processo_id");
            return false;
        }

        $numero_processo = str_replace("", "", $processo_info['numero_processo']);
        $tipo_processo = $processo_info['tipo_processo'];
        $link_verificacao = "https://infovisa.gurupi.to.gov.br/visamunicipal/views/Arquivos/verificar.php?codigo={$codigo_verificador}";

        // Desativar avisos de depreciação temporariamente
        error_reporting(E_ALL & ~E_DEPRECATED);

        // Código que gera o QR Code
        $qrCode = new QrCode($link_verificacao);
        $qrCode->setSize(150);
        $writer = new PngWriter();
        $qrCodePath = $upload_dir . DIRECTORY_SEPARATOR . 'qrcode_' . time() . '.png';

        // Em vez de writeFile(), faça:
        $result = $writer->write($qrCode);

        // Agora salve o resultado em um arquivo:
        $result->saveToFile($qrCodePath);


        // Restaurar a configuração original de relatórios de erro
        error_reporting(E_ALL);

        // Obter a logomarca do município do estabelecimento
        $logomarca_info = $this->logomarca->getLogomarcaByMunicipio($estabelecimento['municipio']);
        $logoPath = $logomarca_info ? $logomarca_info['caminho_logomarca'] : null;
        $espacamento = $logomarca_info ? $logomarca_info['espacamento'] : 40;

        $numero_arquivo = $arquivo_id;
        $nome_arquivo_header = "{$tipo_documento}: {$numero_arquivo}.{$ano}";

        // Create PDF
        $pdf = new CustomPDF();
        $pdf->setQrCodePath($qrCodePath);
        $pdf->setLogoPath($logoPath);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 8);

        // Add header
        $pdf->Ln($espacamento); // ESPAÇO ENTRE LOGOMARCA E TEXTO
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 0, "{$nome_arquivo_header}", 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 5, "{$tipo_processo}: {$numero_processo}", 0, 1, 'C');
        $pdf->Ln(10);

        // Styles
        $styles = "
        <style>
            h2 {
                font-size: 10pt;
                font-weight: 100;
            }
            table {
                width: 100%;
                font-size: 10pt;
                border-collapse: collapse;
            }
            td {
                padding: 5px;
                border: 1px solid #808080;
            }
            .header {
                font-size: 16pt;
                text-align: center;
                color: #808080;
            }
            .centered {
                text-align: center;
                color: #808080;
                font-size: 10pt;
            }
        </style>
    ";

        // Add content - Modificação para diferenciar entre Pessoa Física e Jurídica
        if ($estabelecimento['tipo_pessoa'] == 'fisica') {
            // Para pessoa física, exibir nome e CPF
            $informacoes_estabelecimento = "
    {$styles}
    <table>
        <tr>
            <td><strong>NOME:</strong> {$estabelecimento['nome']}</td>
            <td><strong>CPF:</strong> {$estabelecimento['cpf']}</td>
        </tr>
        <tr>
             <td><strong>Nome Fantasia:</strong> {$estabelecimento['nome_fantasia']}</td>
            <td><strong>ENDEREÇO:</strong> {$estabelecimento['logradouro']}, {$estabelecimento['numero']}, {$estabelecimento['bairro']}, {$estabelecimento['municipio']}-{$estabelecimento['uf']}</td>
        </tr>
        <tr>
            <td><strong>CEP:</strong> {$estabelecimento['cep']}</td>
            <td><strong>TELEFONE:</strong> {$estabelecimento['ddd_telefone_1']} / {$estabelecimento['ddd_telefone_2']}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td><strong>RESPONSÁVEL LEGAL:</strong> {$responsavel_legal['nome']}</td>
            <td><strong>CPF:</strong> {$responsavel_legal['cpf']}</td>
        </tr>
        <tr>
            <td><strong>RESPONSÁVEL TÉCNICO:</strong> {$responsavel_tecnico['nome']}</td>
            <td><strong>CPF:</strong> {$responsavel_tecnico['cpf']}</td>
        </tr>
    </table>
    ";
        } else {
            // Para pessoa jurídica, manter como estava
            $informacoes_estabelecimento = "
    {$styles}
    <table>
        <tr>
            <td><strong>NOME FANTASIA:</strong> {$estabelecimento['nome_fantasia']}</td>
            <td><strong>RAZÃO SOCIAL:</strong> {$estabelecimento['razao_social']}</td>
        </tr>
        <tr>
            <td><strong>CNPJ:</strong> {$estabelecimento['cnpj']}</td>
            <td><strong>ENDEREÇO:</strong> {$estabelecimento['logradouro']}, {$estabelecimento['numero']}, {$estabelecimento['bairro']}, {$estabelecimento['municipio']}-{$estabelecimento['uf']}</td>
        </tr>
        <tr>
            <td><strong>CEP:</strong> {$estabelecimento['cep']}</td>
            <td><strong>TELEFONE:</strong> {$estabelecimento['ddd_telefone_1']} / {$estabelecimento['ddd_telefone_2']}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td><strong>RESPONSÁVEL LEGAL:</strong> {$responsavel_legal['nome']}</td>
            <td><strong>CPF:</strong> {$responsavel_legal['cpf']}</td>
        </tr>
        <tr>
            <td><strong>RESPONSÁVEL TÉCNICO:</strong> {$responsavel_tecnico['nome']}</td>
            <td><strong>CPF:</strong> {$responsavel_tecnico['cpf']}</td>
        </tr>
    </table>
    ";
        }


        $conteudo_completo = $informacoes_estabelecimento . $arquivo['conteudo'];
        $pdf->writeHTML($conteudo_completo);

        // Add signatures
        $assinaturas = [];
        $assinantes = $this->assinatura->getAssinaturasPorArquivo($arquivo_id);
        foreach ($assinantes as $assinante) {
            if ($assinante['status'] == 'assinado') {
                $data_assinatura = date('d/m/Y H:i:s', strtotime($assinante['data_assinatura']));
                $assinaturas[] = "Documento assinado eletronicamente por {$assinante['nome_completo']} em {$data_assinatura}";
            }
        }

        $assinaturas_html = implode('<br>', $assinaturas);
        $rodape = "
        <div style='font-size: 8pt; text-align: center;'>
            <p>{$assinaturas_html}</p>
        <p>A autenticidade do documento pode ser conferida no link: <a href='{$link_verificacao}'>{$link_verificacao}</a>. Caso necessário, o código do documento é: {$codigo_verificador}</p>
        </div>
    ";

        $pdf->writeHTML($rodape, true, false, true, false, '');

        $pdf->Output($caminho_arquivo, 'F');

        // Verificar se o arquivo foi criado
        if (file_exists($caminho_arquivo)) {
            $this->writeLog("PDF gerado com sucesso: $caminho_arquivo");

            // Update arquivo path and status
            $this->arquivo->updateArquivoPathAndCodigo($arquivo_id, 'uploads/' . $nome_arquivo, $codigo_verificador);

            // Enviar e-mails aos usuários externos
            $this->enviarEmailUsuariosExternos($arquivo_id);

            return true;
        } else {
            $this->writeLog("Falha ao gerar o PDF: $caminho_arquivo");
            return false;
        }
    }

    private function enviarEmailUsuariosExternos($arquivo_id)
    {
        $this->writeLog("Iniciando o envio de e-mails para o arquivo ID: $arquivo_id");

        $arquivo = $this->arquivo->getArquivoById($arquivo_id);
        if (!$arquivo) {
            $this->writeLog("Erro: Arquivo não encontrado para envio de e-mail. ID: $arquivo_id");
            return false;
        }

        // Verifique o sigilo do arquivo
        if ($arquivo['sigiloso'] != 0) {
            $this->writeLog("Envio de e-mails cancelado: Arquivo ID $arquivo_id possui sigilo diferente de 0.");
            return false;
        }

        $processo_id = $arquivo['processo_id'];
        $processo_info = $this->arquivo->getProcessoInfo($processo_id);
        if (!$processo_info) {
            $this->writeLog("Erro: Processo não encontrado para envio de e-mail. ID: $processo_id");
            return false;
        }

        $estabelecimento_id = $processo_info['estabelecimento_id'];
        $this->writeLog("Estabelecimento ID: $estabelecimento_id associado ao processo ID: $processo_id");

        $usuarioExterno = new UsuarioExterno($this->conn);
        $usuariosExternos = $usuarioExterno->getUsuariosByEstabelecimento($estabelecimento_id);

        if (empty($usuariosExternos)) {
            $this->writeLog("Nenhum usuário externo encontrado para o estabelecimento ID: $estabelecimento_id");
            return false;
        }

        $this->writeLog("Usuários externos encontrados: " . json_encode($usuariosExternos));

        //$link_processo = "https://infovisa.gurupi.to.gov.br/visamunicipal/views/Processo/detalhes_processo_empresa.php?id={$processo_id}&id={$estabelecimento_id}";
        $link_processo = "https://infovisa.gurupi.to.gov.br/visamunicipal/views/Company/dashboard_empresa.php";

        $assunto = "Novo documento assinado disponível";
        $mensagem = "
        <html>
        <head>
            <title>Documento Disponível</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 20px auto;
                    background: #ffffff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                }
                h2 {
                    color: #007bff;
                    text-align: center;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 16px;
                    color: #555;
                    line-height: 1.5;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #007bff;
                    color: #ffffff;
                    text-decoration: none;
                    font-size: 16px;
                    border-radius: 5px;
                    margin-top: 20px;
                    text-align: center;
                }
                .button:hover {
                    background-color: #0056b3;
                }
                .footer {
                    margin-top: 20px;
                    font-size: 12px;
                    text-align: center;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Documento Assinado Disponível</h2>
                <p>Olá,</p>
                <p>O documento <strong>{$arquivo['tipo_documento']}</strong> foi gerado e está disponível para consulta. Para visualizá-lo, clique no botão abaixo:</p>
                <a href='$link_processo' class='button'>Visualizar Documento</a>
                <p>Este é um e-mail automático. Por favor, não responda.</p>
                <div class='footer'>
    <p>Infovisa - Desenvolvido por: <a href='https://govnex.site/' target='_blank' style='color: #007bff; text-decoration: none;'>Govnex</a></p>
    <p>&copy; " . date('Y') . " Infovisa. Todos os direitos reservados.</p>
</div>
            </div>
        </body>
        </html>
        ";

        foreach ($usuariosExternos as $usuario) {
            $this->writeLog("Tentando enviar e-mail para: {$usuario['email']}");
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'email-ssl.com.br';
                $mail->SMTPAuth = true;
                $mail->Username = 'ti.saude@gurupi.to.gov.br';
                $mail->Password = 'Dti@2021//';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('ti.saude@gurupi.to.gov.br', 'Infovisa');
                $mail->addAddress($usuario['email'], $usuario['nome_completo']);
                $mail->isHTML(true);
                $mail->Subject = $assunto;
                $mail->Body = $mensagem;

                $mail->send();
                $this->writeLog("E-mail enviado com sucesso para: {$usuario['email']}");
            } catch (Exception $e) {
                $this->writeLog("Erro ao enviar e-mail para {$usuario['email']}: {$mail->ErrorInfo}");
            }
        }

        $this->writeLog("Envio de e-mails finalizado para o arquivo ID: $arquivo_id");
    }


    public function getModeloDocumento($tipo_documento)
    {
        $stmt = $this->conn->prepare("SELECT conteudo FROM modelos_documentos WHERE tipo_documento = ?");
        $stmt->bind_param("s", $tipo_documento);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['conteudo'];
        }
        return "";
    }

    public function createDraft()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['processo_id']) && isset($_POST['conteudo']) && isset($_POST['tipo_documento'])) {
            $processo_id = $_POST['processo_id'];
            $sigiloso = isset($_POST['sigiloso']) ? intval($_POST['sigiloso']) : 0;
            $conteudo = $_POST['conteudo'];
            $tipo_documento = $_POST['tipo_documento'];
            $estabelecimento_id = $_POST['estabelecimento_id'];

            $codigo_verificador = md5(uniqid(rand(), true));
            $arquivo_id = $this->arquivo->createDraftArquivo($processo_id, $tipo_documento, $conteudo, $sigiloso);

            if ($arquivo_id === false) {
                header("Location: ../Processo/documentos.php?processo_id=$processo_id&id=$estabelecimento_id&error=" . urlencode('Erro ao salvar o rascunho.'));
                exit();
            }

            $assinantes = isset($_POST['assinantes']) ? $_POST['assinantes'] : [];
            foreach ($assinantes as $assinante_id) {
                $this->assinatura->createAssinatura($arquivo_id, $assinante_id);
            }

            header("Location: ../Processo/documentos.php?processo_id=$processo_id&id=$estabelecimento_id&success=" . urlencode('Rascunho salvo com sucesso.'));
            exit();
        }
    }

    /**
     * Gera uma prévia do documento sem salvar no banco de dados
     */
    public function previsualizar()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['conteudo']) && isset($_POST['tipo_documento'])) {
            $conteudo = $_POST['conteudo'];
            $tipo_documento = $_POST['tipo_documento'];
            $processo_id = $_POST['processo_id'] ?? null;
            $estabelecimento_id = $_POST['estabelecimento_id'] ?? null;
            
            // Se não tiver processo ou estabelecimento, retorna erro
            if (!$processo_id || !$estabelecimento_id) {
                echo '<div class="alert alert-danger">Dados do processo ou estabelecimento não encontrados.</div>';
                exit();
            }
            
            // Buscar informações do estabelecimento
            $estabelecimento = $this->estabelecimento->findById($estabelecimento_id);
            if (!$estabelecimento) {
                echo '<div class="alert alert-danger">Estabelecimento não encontrado.</div>';
                exit();
            }
            
            // Buscar informações do processo
            $processo_info = $this->arquivo->getProcessoInfo($processo_id);
            if (!$processo_info) {
                echo '<div class="alert alert-danger">Processo não encontrado.</div>';
                exit();
            }
            
            // Obter a logomarca do município do estabelecimento
            $logomarca_info = $this->logomarca->getLogomarcaByMunicipio($estabelecimento['municipio']);
            $logoPath = $logomarca_info ? $logomarca_info['caminho_logomarca'] : null;
            
            // Configurar HTML básico com Bootstrap para visualização
            echo '<!DOCTYPE html>
            <html lang="pt-br">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Pré-visualização: ' . htmlspecialchars($tipo_documento) . '</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        padding: 20px;
                        background-color: #f8f9fa;
                    }
                    .preview-container {
                        background-color: white;
                        padding: 30px;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        border-radius: 5px;
                        margin: 0 auto;
                        max-width: 800px;
                    }
                    .header-info {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    .preview-logo {
                        max-height: 80px;
                        margin-bottom: 15px;
                    }
                    .watermark {
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%) rotate(-45deg);
                        font-size: 100px;
                        color: rgba(200, 200, 200, 0.2);
                        pointer-events: none;
                        z-index: 1000;
                    }
                </style>
            </head>
            <body>
                <div class="watermark">PRÉ-VISUALIZAÇÃO</div>
                <div class="preview-container">
                    <div class="header-info">';
            
            // Adicionar logomarca se disponível
            if ($logoPath) {
                echo '<img src="' . $logoPath . '" alt="Logomarca" class="preview-logo"><br>';
            }
            
            echo '<h3>' . htmlspecialchars($tipo_documento) . '</h3>
                        <p>' . htmlspecialchars($processo_info['tipo_processo']) . ': ' . htmlspecialchars($processo_info['numero_processo']) . '</p>
                    </div>
                    <div class="content">' . $conteudo . '</div>
                </div>
                
                <div class="mt-4 text-center">
                    <p class="text-muted small">Este é apenas um documento de pré-visualização e não possui validade legal.</p>
                </div>
            </body>
            </html>';
            exit();
        } else {
            echo '<div class="alert alert-danger">Parâmetros insuficientes para gerar a pré-visualização.</div>';
            exit();
        }
    }

    private function mask($val, $mask)
    {
        $maskared = '';
        $k = 0;
        for ($i = 0; $i < strlen($mask); $i++) {
            if ($mask[$i] == '#') {
                if (isset($val[$k])) {
                    $maskared .= $val[$k++];
                }
            } else {
                if (isset($mask[$i])) {
                    $maskared .= $mask[$i];
                }
            }
        }
        return $maskared;
    }
}

$arquivoController = new ArquivoController($conn);

if (isset($_GET['acao'])) {
    $acao = $_GET['acao'];
    if ($acao == 'create') {
        $arquivoController->create();
    } elseif ($acao == 'previsualizar') {
        $arquivoController->previsualizar();
    }
}
