<?php
require_once '../conf/database.php';
require_once '../models/OrdemServico.php';
require_once '../vendor/autoload.php'; // Certifique-se de que o autoload do Composer está correto

class OrdemServicoController
{
    private $ordemServico;
    private $conn;

    public function __construct($conn)
    {
        $this->ordemServico = new OrdemServico($conn);
        $this->conn = $conn;

        // Configurar charset da conexão com o banco de dados para UTF-8
        $this->conn->set_charset("utf8");
    }

    public function criarOrdemServico()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $data_inicio = $_POST['data_inicio'];
            $data_fim = $_POST['data_fim'];
            $acoes_executadas_ids = $_POST['acoes_executadas']; // Note que isso agora é um array
            $tecnicos_ids = $_POST['tecnicos'];
            $tecnicos = json_encode($tecnicos_ids); // Salvar os IDs dos técnicos como JSON
            $estabelecimento_id = $_POST['estabelecimento_id'];
            $processo_id = $_POST['processo_id'];
            $observacao = isset($_POST['observacao']) ? $_POST['observacao'] : null; // Campo de observação opcional

            // Verificar se a data de fim é maior ou igual à data de início
            if (strtotime($data_fim) < strtotime($data_inicio)) {
                header("Location: ../views/OrdemServico/ordem_servico.php?id=$estabelecimento_id&processo_id=$processo_id&error=" . urlencode('A data de fim não pode ser anterior à data de início.'));
                exit();
            }

            // Obter descrições das ações executadas
            $acoes_executadas = array_map([$this, 'obterDescricaoAcao'], $acoes_executadas_ids);
            $acoes_executadas_str = implode(", ", $acoes_executadas); // Concatenar todas as descrições

            // Log de diagnóstico
            error_log("Data Início: $data_inicio");
            error_log("Data Fim: $data_fim");
            error_log("Ações Executadas: " . print_r($acoes_executadas_ids, true));
            error_log("Técnicos IDs: $tecnicos");

            $municipio = $this->obterMunicipioEstabelecimento($estabelecimento_id);

            $pdf_path = $this->gerarPDF($data_inicio, $data_fim, $acoes_executadas_str, $observacao, $tecnicos_ids, $estabelecimento_id); // Passar IDs dos técnicos

            if ($this->ordemServico->create($estabelecimento_id, $processo_id, $data_inicio, $data_fim, $acoes_executadas_ids, $tecnicos, $pdf_path, $municipio, 'ativa', $observacao)) {
                header("Location: ../views/Processo/documentos.php?processo_id=$processo_id&id=$estabelecimento_id&success=Ordem de Serviço criada com sucesso.");
                exit();
            } else {
                header("Location: ../views/OrdemServico/ordem_servico.php?id=$estabelecimento_id&processo_id=$processo_id&error=" . urlencode($this->ordemServico->getLastError()));
                exit();
            }
        }
    }

    private function obterMunicipioEstabelecimento($estabelecimento_id)
    {
        $query = "SELECT municipio FROM estabelecimentos WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        $stmt->bind_result($municipio);
        $stmt->fetch();
        $stmt->close();

        return $municipio;
    }


    public function editarOrdemServico()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $ordem_id = $_POST['ordem_id'];
            $data_inicio = $_POST['data_inicio'];
            $data_fim = $_POST['data_fim'];
            $acoes_executadas_ids = $_POST['acoes_executadas']; // Note que isso agora é um array
            $tecnicos_ids = $_POST['tecnicos'];
            $tecnicos = json_encode($tecnicos_ids); // Salvar os IDs dos técnicos como JSON
            $estabelecimento_id = $_POST['estabelecimento_id'];
            $processo_id = $_POST['processo_id'];
            $observacao = isset($_POST['observacao']) ? $_POST['observacao'] : null; // Campo de observação opcional

            // Verificar se a data de fim é maior ou igual à data de início
            if (strtotime($data_fim) < strtotime($data_inicio)) {
                header("Location: ../views/OrdemServico/editar_ordem.php?id=$ordem_id&error=" . urlencode('A data de fim não pode ser anterior à data de início.'));
                exit();
            }

            // Obter descrições das ações executadas
            $acoes_executadas = array_map([$this, 'obterDescricaoAcao'], $acoes_executadas_ids);
            $acoes_executadas_str = implode(", ", $acoes_executadas); // Concatenar todas as descrições

            // Log de diagnóstico
            error_log("Data Início: $data_inicio");
            error_log("Data Fim: $data_fim");
            error_log("Ações Executadas: $acoes_executadas_str");
            error_log("Técnicos IDs: $tecnicos");

            $pdf_path = $this->gerarPDF($data_inicio, $data_fim, $acoes_executadas_str, $observacao, $tecnicos_ids, $estabelecimento_id); // Passar IDs dos técnicos

            if ($this->ordemServico->update($ordem_id, $data_inicio, $data_fim, $acoes_executadas_str, $tecnicos, $pdf_path, $estabelecimento_id, $processo_id, $observacao)) {
                header("Location: ../views/Processo/documentos.php?processo_id=$processo_id&id=$estabelecimento_id&success=Ordem de Serviço atualizada com sucesso.");
                exit();
            } else {
                header("Location: ../views/OrdemServico/editar_ordem.php?id=$ordem_id&error=" . urlencode($this->ordemServico->getLastError()));
                exit();
            }
        }
    }

    private function obterDescricaoAcao($id_acao)
    {
        $query = "SELECT descricao FROM tipos_acoes_executadas WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id_acao);
        $stmt->execute();
        $stmt->bind_result($descricao);
        $stmt->fetch();
        $stmt->close();

        // Verificar se $descricao não é null antes de chamar htmlspecialchars_decode
        return $descricao !== null ? htmlspecialchars_decode($descricao, ENT_QUOTES) : '';
    }
    private function obterDadosEstabelecimento($estabelecimento_id)
    {
        $query = "SELECT razao_social, nome_fantasia, CONCAT(descricao_tipo_de_logradouro, ' ', logradouro, ', ', numero, ' - ', bairro, ', ', municipio, ' - ', uf, ', CEP: ', cep) AS endereco FROM estabelecimentos WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        $stmt->bind_result($razao_social, $nome_fantasia, $endereco);
        $stmt->fetch();
        $stmt->close();

        // Verificar se as variáveis não são null antes de chamar htmlspecialchars_decode
        return [
            'razao_social' => $razao_social !== null ? htmlspecialchars_decode($razao_social, ENT_QUOTES) : '',
            'nome_fantasia' => $nome_fantasia !== null ? htmlspecialchars_decode($nome_fantasia, ENT_QUOTES) : '',
            'endereco' => $endereco !== null ? htmlspecialchars_decode($endereco, ENT_QUOTES) : ''
        ];
    }

    private function gerarPDF($data_inicio, $data_fim, $acoes_executadas, $observacao, $tecnicos_ids, $estabelecimento_id)
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
        $pdf->Cell(0, 10, 'Ordem de Serviço', 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Razão Social:', 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $dados_estabelecimento['razao_social'], 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Nome Fantasia:', 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $dados_estabelecimento['nome_fantasia'], 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Endereço:', 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, $dados_estabelecimento['endereco'], 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Data Início:', 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $data_inicio_formatada, 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Data Fim:', 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $data_fim_formatada, 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Ações Executadas:', 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, $acoes_executadas, 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Observação:', 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, $observacao, 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 10, 'Técnicos:', 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, $nomes_tecnicos, 1);
        $pdf->Ln();

        // Salvar o PDF em um caminho específico
        $upload_dir = '../../uploads/ordem_servico/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = 'ordem_servico_' . time() . '.pdf';
        $pdf_path = $upload_dir . $file_name;
        $pdf->Output($pdf_path, 'F');

        return $pdf_path;
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
            // Verificar se $row['nome_completo'] não é null antes de chamar htmlspecialchars_decode
            $nomes_tecnicos[] = $row['nome_completo'] !== null ? htmlspecialchars_decode($row['nome_completo'], ENT_QUOTES) : '';
        }

        return implode(', ', $nomes_tecnicos);
    }
}

// Processa a ação com base no parâmetro de URL
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Verificar conexão com o banco de dados
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $conn->set_charset("utf8"); // Configurar charset da conexão com o banco de dados para UTF-8
    $controller = new OrdemServicoController($conn);

    if ($action == "criar") {
        $controller->criarOrdemServico();
    } elseif ($action == "editar") {
        $controller->editarOrdemServico();
    }

    $conn->close();
}
