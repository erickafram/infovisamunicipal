<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Relatorios.php';
require '../../vendor/autoload.php'; // Para FPDF

// Ativa buffer de saída para evitar erros
ob_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

// Inclui o header
include '../header.php';

// Instanciando o model Relatorios
$relatorios = new Relatorios($conn);

// Parâmetros de filtro
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t');
$atividade_sia = isset($_GET['atividade_sia']) ? $_GET['atividade_sia'] : 'todos';
$tipo_acao = isset($_GET['tipo_acao']) ? $_GET['tipo_acao'] : 'todos';

// Obtendo as ações executadas
$acoes_executadas = $relatorios->getAcoesExecutadasComFiltro($data_inicio, $data_fim, $atividade_sia, $tipo_acao);

// Geração de PDF
// Geração de PDF
if (isset($_GET['gerar_pdf'])) {
    $pdf = new FPDF('L', 'mm', 'A4'); // Configuração para folha horizontal
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);

    // Título ajustado
    $pdf->Cell(0, 10, utf8_decode('Relatório de Ações Executadas'), 0, 1, 'C');
    $pdf->Ln(5); // Espaçamento após o título

    // Ajuste das larguras das colunas
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 10, 'Data', 1, 0, 'C', true);
    $pdf->Cell(90, 10, utf8_decode('Estabelecimento'), 1, 0, 'C', true); // Aumentei a largura para 90mm
    $pdf->Cell(120, 10, utf8_decode('Ação'), 1, 0, 'C', true); // Aumentei a largura para 120mm
    $pdf->Cell(30, 10, 'Atividade SIA', 1, 1, 'C', true);

    // Conteúdo da tabela
    $pdf->SetFont('Arial', '', 9);
    foreach ($acoes_executadas as $acao) {
        if ($pdf->GetY() + 10 > $pdf->GetPageHeight() - 10) {
            $pdf->AddPage('L'); // Adiciona uma nova página se necessário
        }

        $pdf->Cell(30, 10, date('d/m/Y', strtotime($acao['data_inicio'])), 1, 0, 'C');

        // Quebra de linha para Estabelecimento
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell(90, 5, utf8_decode($acao['estabelecimento'] ?? 'Sem Estabelecimento'), 1, 'L');
        $pdf->SetXY($x + 90, $y); // Ajusta a posição após MultiCell

        // Quebra de linha para Ação
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell(120, 5, utf8_decode($acao['acao_descricao']), 1, 'L');
        $pdf->SetXY($x + 120, $y); // Ajusta a posição após MultiCell

        $pdf->Cell(30, 10, $acao['atividade_sia'] ? 'Sim' : 'Não', 1, 1, 'C');
    }

    // Saída do PDF
    ob_end_clean(); // Limpa o buffer antes de enviar o PDF
    $pdf->Output();
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Ações Executadas</title>
</head>

<body>
    <div class="container mt-5">
        <h4>Relatório de Ações Executadas</h4>
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="data_inicio">Data:</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="data_fim">Data Fim:</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="atividade_sia">Atividade SIA:</label>
                        <select class="form-control" id="atividade_sia" name="atividade_sia">
                            <option value="todos" <?php echo $atividade_sia === 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <option value="1" <?php echo $atividade_sia === '1' ? 'selected' : ''; ?>>Sim</option>
                            <option value="0" <?php echo $atividade_sia === '0' ? 'selected' : ''; ?>>Não</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="tipo_acao">Tipo de Ação:</label>
                        <select class="form-control" id="tipo_acao" name="tipo_acao">
                            <option value="todos" <?php echo $tipo_acao === 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <?php
                            $tiposAcoes = $relatorios->getTiposAcoes();
                            foreach ($tiposAcoes as $acao) {
                                echo "<option value=\"{$acao['id']}\"" . ($tipo_acao == $acao['id'] ? ' selected' : '') . ">{$acao['descricao']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <button type="submit" name="gerar_pdf" class="btn btn-success">Gerar PDF</button>
                </div>
            </div>
        </form>

        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Ações Executadas</h5>
                <p>Total de Ações: <strong><?php echo count($acoes_executadas); ?></strong></p>
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <!-- <th>Número</th> -->
                            <th>Data</th>
                            <th>Estabelecimento</th>
                            <th>Ação</th>
                            <th>Atividade SIA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acoes_executadas as $acao) : ?>
                            <tr>
                                <!-- <td><?php echo htmlspecialchars($acao['ordem_id']); ?></td> -->
                                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($acao['data_inicio']))); ?></td>
                                <td><?php echo htmlspecialchars($acao['estabelecimento'] ?? 'Sem Estabelecimento'); ?></td>
                                <td><?php echo htmlspecialchars($acao['acao_descricao']); ?></td>
                                <td><?php echo $acao['atividade_sia'] ? 'Sim' : 'Não'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>