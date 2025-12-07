<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Relatorios.php';
require '../../vendor/autoload.php';

use Dompdf\Dompdf;

// Configurações para evitar timeout
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M');

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$nivel_acesso = $_SESSION['user']['nivel_acesso'];
$municipioUsuario = $_SESSION['user']['municipio'];
$relatoriosModel = new Relatorios($conn);
$municipios = $relatoriosModel->getMunicipios($nivel_acesso, $municipioUsuario);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $municipioSelecionado = $_POST['municipio'];
    $dataInicio = $_POST['data_inicio'];
    $dataFim = $_POST['data_fim'];

    // Validar formato das datas
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
        echo "<script>alert('Formato de data inválido. Use o formato YYYY-MM-DD.'); window.history.back();</script>";
        exit();
    }

    // Converter datas para o formato correto para a consulta
    $dataInicio = $dataInicio . ' 00:00:00';
    $dataFim = $dataFim . ' 23:59:59';

    $estabelecimentos = $relatoriosModel->getEstabelecimentosByMunicipio($municipioSelecionado, $dataInicio, $dataFim);

    if (empty($estabelecimentos)) {
        echo "<script>alert('Nenhum estabelecimento encontrado para o período selecionado.'); window.history.back();</script>";
        exit();
    }

    // Teste simples: mostrar dados em HTML antes de gerar PDF
    if (isset($_POST['debug']) && $_POST['debug'] == '1') {
        echo "<h3>Debug - Dados encontrados:</h3>";
        echo "<p>Total de estabelecimentos: " . count($estabelecimentos) . "</p>";
        echo "<table border='1'>";
        echo "<tr><th>CNPJ</th><th>Nome Fantasia</th><th>Razão Social</th><th>Telefone</th><th>Data Cadastro</th></tr>";
        foreach ($estabelecimentos as $est) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($est['cnpj'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($est['nome_fantasia'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($est['razao_social'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($est['ddd_telefone_1'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($est['data_cadastro'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit();
    }

    gerarPDF($estabelecimentos, $municipioSelecionado, $dataInicio, $dataFim);
    exit(); // Certifique-se de sair após gerar o PDF
}

function gerarPDF($estabelecimentos, $municipio, $dataInicio, $dataFim)
{
    try {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_end_clean();
        }

        $dompdf = new Dompdf();

        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { text-align: center; color: #333; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f2f2f2; }
                .total { text-align: right; margin-top: 20px; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1>Relatório de Estabelecimentos - ' . htmlspecialchars($municipio ?? '') . '</h1>
            <h3>Período: ' . htmlspecialchars($dataInicio ?? '') . ' a ' . htmlspecialchars($dataFim ?? '') . '</h3>
            <table>
                <thead>
                    <tr>
                        <th>CNPJ</th>
                        <th>Nome Fantasia</th>
                        <th>Razão Social</th>
                        <th>Telefone</th>
                        <th>Data de Cadastro</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($estabelecimentos as $estabelecimento) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($estabelecimento['cnpj'] ?? '') . '</td>
                    <td>' . htmlspecialchars($estabelecimento['nome_fantasia'] ?? '') . '</td>
                    <td>' . htmlspecialchars($estabelecimento['razao_social'] ?? '') . '</td>
                    <td>' . htmlspecialchars($estabelecimento['ddd_telefone_1'] ?? '') . '</td>
                    <td>' . htmlspecialchars(date('d/m/Y H:i:s', strtotime($estabelecimento['data_cadastro']))) . '</td>
                </tr>';
        }

        $html .= '
                </tbody>
            </table>
            <div class="total">Total de Registros: ' . count($estabelecimentos) . '</div>
        </body>
        </html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Headers para download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Relatorio_Estabelecimentos_' . date('Y-m-d') . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $dompdf->output();

    } catch (Exception $e) {
        error_log("Erro ao gerar PDF: " . $e->getMessage());
        echo "<script>alert('Erro ao gerar o relatório: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }
}

include '../header.php';
?>

<div class="container mt-5">
    <h4 class="mb-4">Relatório de Estabelecimentos por Município</h4>
    <form method="POST" action="relatorio_estabelecimentos.php" class="mb-4">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="data_inicio">Data de Início:</label>
                    <input type="date" id="data_inicio" name="data_inicio" class="form-control" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="data_fim">Data de Fim:</label>
                    <input type="date" id="data_fim" name="data_fim" class="form-control" required>
                </div>
            </div>
        </div>
        <div class="form-group mt-3">
            <label for="municipio">Escolha o Município:</label>
            <select id="municipio" name="municipio" class="form-control" required>
                <?php foreach ($municipios as $municipio) : ?>
                    <option value="<?php echo htmlspecialchars($municipio['municipio']); ?>"><?php echo htmlspecialchars($municipio['municipio']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Gerar PDF</button>
        <button type="submit" name="debug" value="1" class="btn btn-secondary mt-3 ml-2">Debug (Ver Dados)</button>
    </form>
</div>

<?php include '../footer.php'; ?>