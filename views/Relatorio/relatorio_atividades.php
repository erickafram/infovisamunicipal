<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Relatorios.php';
require '../../vendor/autoload.php';

use Dompdf\Dompdf;

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$nivel_acesso = $_SESSION['user']['nivel_acesso'];
$municipioUsuario = $_SESSION['user']['municipio'];
$relatoriosModel = new Relatorios($conn);
$atividades = $relatoriosModel->getAtividades($nivel_acesso, $municipioUsuario);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $atividadesSelecionadas = $_POST['atividades'];
    if (in_array('all', $atividadesSelecionadas)) {
        $atividadesSelecionadas = array_keys($atividades);
    }
    $estabelecimentos = $relatoriosModel->getEstabelecimentosByAtividades($atividadesSelecionadas, $nivel_acesso, $municipioUsuario);
    gerarPDF($estabelecimentos);
    exit(); // Certifique-se de sair após gerar o PDF
}

function gerarPDF($estabelecimentos)
{
    $dompdf = new Dompdf();

    $html = '
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                }
                h1 {
                    text-align: center;
                    color: #333;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th, td {
                    padding: 10px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th {
                    background-color: #f2f2f2;
                    color: #333;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .total-registros {
                    text-align: right;
                    margin-top: 20px;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <h1>Relatório de Estabelecimentos</h1>
            <table>
                <thead>
                    <tr>
                        <th>CNPJ</th>
                        <th>Nome Fantasia</th>
                        <th>Razão Social</th>
                        <th>Telefone</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($estabelecimentos as $estabelecimento) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($estabelecimento['cnpj']) . '</td>
                <td>' . htmlspecialchars($estabelecimento['nome_fantasia']) . '</td>
                <td>' . htmlspecialchars($estabelecimento['razao_social']) . '</td>
                <td>' . htmlspecialchars($estabelecimento['ddd_telefone_1']) . '</td>
            </tr>';
    }

    $html .= '
                </tbody>
            </table>
            <div class="total-registros">Total de Registros: ' . count($estabelecimentos) . '</div>
        </body>
        </html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape'); // Define a orientação da folha como horizontal
    $dompdf->render();
    $dompdf->stream('Relatorio_Estabelecimentos.pdf', array("Attachment" => 1));
}

include '../header.php';
?>

<div class="container mt-5">
    <h4 class="mb-4">Relatório de Estabelecimentos por Atividade</h4>
    <form method="POST" action="relatorio_atividades.php" class="mb-4">
        <div class="form-group">
            <label for="atividades">Escolha as Atividades:</label>
            <select id="atividades" name="atividades[]" class="form-select select2-bootstrap-5" multiple required>
                <option value="all">Selecionar Todas</option>
                <?php foreach ($atividades as $codigo => $descricao) : ?>
                    <option value="<?php echo htmlspecialchars($codigo); ?>"><?php echo htmlspecialchars($descricao); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:8px;">Gerar PDF</button>
    </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#atividades').select2({
            placeholder: 'Selecione as atividades',
            allowClear: true,
            theme: 'bootstrap-5'
        });

        $('#atividades').on('select2:select', function(e) {
            var selectedOption = e.params.data;
            if (selectedOption.id === 'all') {
                $('#atividades').val(['all']).trigger('change');
            } else if ($('#atividades').val().includes('all')) {
                $('#atividades').val([$('#atividades').val().filter(function(option) {
                    return option !== 'all';
                })]).trigger('change');
            }
        });
    });
</script>

<?php include '../footer.php'; ?>