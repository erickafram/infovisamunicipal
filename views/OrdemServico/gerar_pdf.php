<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_POST['ordem_id'])) {
    echo "ID da ordem de serviço não fornecido!";
    exit();
}

$ordem_id = $_POST['ordem_id'];

$ordemServico = new OrdemServico($conn);

// Verificar se o usuário tem permissão para acessar esta ordem baseado no município
$municipioUsuario = $_SESSION['user']['municipio'];
if (!$ordemServico->podeAcessarOrdem($ordem_id, $municipioUsuario)) {
    echo "Acesso negado. Você não tem permissão para gerar PDF desta ordem de serviço.";
    exit();
}

$ordem = $ordemServico->getOrdemById($ordem_id);

if (!$ordem) {
    echo "Ordem de serviço não encontrada!";
    exit();
}

$tecnicos_ids = json_decode($ordem['tecnicos'], true);
$nomes_tecnicos = $ordemServico->getTecnicosNomes($tecnicos_ids);

// Buscar os nomes das ações executadas
$acoes_ids = json_decode($ordem['acoes_executadas'], true);
$acoes_nomes = $ordemServico->getAcoesNomes($acoes_ids);

$acoes_executadas_nomes = [];
if (is_array($acoes_ids)) {
    foreach ($acoes_ids as $acao_id) {
        $acoes_executadas_nomes[] = $acoes_nomes[$acao_id];
    }
}

$acoes_executadas_str = implode(", ", $acoes_executadas_nomes);

class OrdemServicoController
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->conn->set_charset("utf8");
    }

    public function gerarPDF($data_inicio, $data_fim, $acoes_executadas, $observacao, $tecnicos_ids, $estabelecimento_id)
    {
        $dados_estabelecimento = $this->obterDadosEstabelecimento($estabelecimento_id);

        // Obter nomes dos técnicos a partir dos IDs
        $nomes_tecnicos = $this->obterNomesTecnicos($tecnicos_ids);

        // Formatar as datas no formato D/M/Y
        $data_inicio_formatada = date('d/m/Y', strtotime($data_inicio));
        $data_fim_formatada = date('d/m/Y', strtotime($data_fim));

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, mb_convert_encoding('Ordem de Serviço', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $pdf->Ln(10);

        // Portaria no topo
        $pdf->SetFont('Arial', '', 8); // Fonte menor
        $pdf->MultiCell(0, 5, mb_convert_encoding(
            'O COORDENADOR DA VIGILÂNCIA SANITÁRIA, no uso de suas atribuições legais, tendo em vista a necessidade de cumprir todas as atribuições desta Coordenação, designa os fiscais abaixo, para no período de validade desta, exercer fiscalização e atendimentos, conforme o disposto na Lei nº 1.085/94, assessorando e cumprindo as determinações da Coordenação, exercendo todos os deveres e prerrogativas fiscais.',
            'ISO-8859-1',
            'UTF-8'
        ), 0, 'C');
        $pdf->Ln(10);

        // Nome do Coordenador com a data de início
        $data_inicio_formatada = date('d/m/Y', strtotime($data_inicio)); // Formatar a data de início
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, mb_convert_encoding("Coordenador: RONALDO VALADARES VERAS", 'ISO-8859-1', 'UTF-8'), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        //$pdf->Cell(0, 10, mb_convert_encoding("Data: $data_inicio_formatada", 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
        $pdf->Ln(10);


        // Verificar o tipo de pessoa
        if ($dados_estabelecimento['tipo_pessoa'] == 'juridica') {
            // Exibir Razão Social e Nome Fantasia
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(50, 10, mb_convert_encoding('Razão Social:', 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, mb_convert_encoding($dados_estabelecimento['razao_social'] ?? '', 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Ln();

            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(50, 10, mb_convert_encoding('Nome Fantasia:', 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, mb_convert_encoding($dados_estabelecimento['nome_fantasia'] ?? '', 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Ln();
        } else {
            // Exibir Nome
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(50, 10, mb_convert_encoding('Nome:', 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, mb_convert_encoding($dados_estabelecimento['nome'] ?? '', 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Ln();
        }

        // Endereço
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, mb_convert_encoding('Endereço:', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, mb_convert_encoding($dados_estabelecimento['endereco'] ?? '', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Ln();

        // Data Início
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, mb_convert_encoding('Data Início:', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, mb_convert_encoding($data_inicio_formatada ?? '', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Ln();

        // Data Fim
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, mb_convert_encoding('Data Fim:', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, mb_convert_encoding($data_fim_formatada ?? '', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Ln();

        // Ações Executadas
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, mb_convert_encoding('Ações Executadas:', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, mb_convert_encoding($acoes_executadas ?? '', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Ln();

        // Observações
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, mb_convert_encoding('Observações:', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, mb_convert_encoding($observacao ?? '', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Ln();

        // Técnicos
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, mb_convert_encoding('Técnicos:', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, mb_convert_encoding($nomes_tecnicos ?? '', 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Ln();

        // Assinatura digital
        $pdf->Ln(15);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, mb_convert_encoding(
            "Ordem de Serviço assinada digitalmente por RONALDO VALADARES VERAS na data $data_inicio_formatada.",
            'ISO-8859-1',
            'UTF-8'
        ), 0, 'C');


        // Retornar o PDF gerado como string
        return $pdf->Output('S');
    }

    private function obterDadosEstabelecimento($estabelecimento_id)
    {
        $query = "SELECT 
                    razao_social, 
                    nome_fantasia, 
                    nome, 
                    tipo_pessoa, 
                    CONCAT(
                        IFNULL(descricao_tipo_de_logradouro, ''),
                        ' ',
                        IFNULL(logradouro, ''),
                        ', ',
                        IFNULL(numero, ''),
                        ' - ',
                        IFNULL(bairro, ''),
                        ', ',
                        IFNULL(municipio, ''),
                        ' - ',
                        IFNULL(uf, ''),
                        ', CEP: ',
                        IFNULL(cep, '')
                    ) AS endereco 
                  FROM estabelecimentos 
                  WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        $stmt->bind_result($razao_social, $nome_fantasia, $nome, $tipo_pessoa, $endereco);
        $stmt->fetch();
        $stmt->close();

        return [
            'razao_social' => htmlspecialchars_decode($razao_social ?? '', ENT_QUOTES),
            'nome_fantasia' => htmlspecialchars_decode($nome_fantasia ?? '', ENT_QUOTES),
            'nome' => htmlspecialchars_decode($nome ?? '', ENT_QUOTES),
            'tipo_pessoa' => $tipo_pessoa ?? '', // Não precisa de decode
            'endereco' => htmlspecialchars_decode($endereco ?? '', ENT_QUOTES)
        ];
    }

    private function obterNomesTecnicos($ids_tecnicos)
    {
        if (empty($ids_tecnicos)) {
            return '';
        }

        $ids_tecnicos_str = implode(',', array_map('intval', $ids_tecnicos));
        $query = "SELECT nome_completo FROM usuarios WHERE id IN ($ids_tecnicos_str)";
        $result = $this->conn->query($query);

        $nomes_tecnicos = [];
        while ($row = $result->fetch_assoc()) {
            $nomes_tecnicos[] = htmlspecialchars_decode($row['nome_completo'], ENT_QUOTES);
        }

        return implode(', ', $nomes_tecnicos);
    }
}

// Geração do PDF
$controller = new OrdemServicoController($conn);
$pdf_content = $controller->gerarPDF(
    $ordem['data_inicio'],
    $ordem['data_fim'],
    $acoes_executadas_str,
    $ordem['observacao'] ?? '', // Usando o operador ?? para evitar valores nulos
    $tecnicos_ids,
    $ordem['estabelecimento_id']
);

// Configurar o cabeçalho para download do PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="ordem_servico.pdf"');
echo $pdf_content;
