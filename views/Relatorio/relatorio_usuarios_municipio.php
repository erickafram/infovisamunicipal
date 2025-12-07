<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1])) {
    header("Location: ../../login.php");
    exit();
}

// Filtros
$municipio = isset($_GET['municipio']) ? $_GET['municipio'] : '';
$cargo = isset($_GET['cargo']) ? $_GET['cargo'] : '';
$escolaridade = isset($_GET['escolaridade']) ? $_GET['escolaridade'] : '';
$tipo_vinculo = isset($_GET['tipo_vinculo']) ? $_GET['tipo_vinculo'] : '';
$tempo_vinculo = isset($_GET['tempo_vinculo']) ? $_GET['tempo_vinculo'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Função para obter valores distintos de uma coluna
function getDistinctValues($conn, $column)
{
    $stmt = $conn->prepare("SELECT DISTINCT $column FROM usuarios WHERE $column IS NOT NULL AND $column != '' ORDER BY $column");
    $stmt->execute();
    $result = $stmt->get_result();
    $values = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $values;
}

$municipios = getDistinctValues($conn, 'municipio');
$cargos = getDistinctValues($conn, 'cargo');
$escolaridades = getDistinctValues($conn, 'escolaridade');
$tipos_vinculo = getDistinctValues($conn, 'tipo_vinculo');

// Construção da consulta com base nos filtros
$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];

if ($municipio) {
    $sql .= " AND municipio = ?";
    $params[] = $municipio;
}

if ($cargo) {
    $sql .= " AND cargo = ?";
    $params[] = $cargo;
}

if ($escolaridade) {
    $sql .= " AND escolaridade = ?";
    $params[] = $escolaridade;
}

if ($tipo_vinculo) {
    $sql .= " AND tipo_vinculo = ?";
    $params[] = $tipo_vinculo;
}

if ($tempo_vinculo !== '') {
    if ($tempo_vinculo == 0) {
        $sql .= " AND tempo_vinculo = 0";
    } else {
        $sql .= " AND tempo_vinculo = ?";
        $params[] = $tempo_vinculo;
    }
}

if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$usuarios = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Usuários por Município</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <style>
        .navbar-expand-lg>.container,
        .navbar-expand-lg>.container-fluid {
            max-width: 1320px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .card-title {
            font-weight: bold;
            color: #333;
        }

        .list-group-item {
            border: none;
            padding: 10px 15px;
        }

        .list-group-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container mt-5" style="max-width: 1320px;">
        <h4>Relatório de Usuários por Município</h4>
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="municipio">Selecione o Município:</label>
                        <select class="form-control" id="municipio" name="municipio">
                            <option value="">Todos os Municípios</option>
                            <?php foreach ($municipios as $row) : ?>
                                <option value="<?php echo htmlspecialchars($row['municipio']); ?>" <?php if ($municipio == $row['municipio']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($row['municipio']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="cargo">Selecione o Cargo:</label>
                        <select class="form-control" id="cargo" name="cargo">
                            <option value="">Todos os Cargos</option>
                            <?php foreach ($cargos as $row) : ?>
                                <option value="<?php echo htmlspecialchars($row['cargo']); ?>" <?php if ($cargo == $row['cargo']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($row['cargo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="escolaridade">Escolaridade:</label>
                        <select class="form-control" id="escolaridade" name="escolaridade">
                            <option value="">Todas as Escolaridades</option>
                            <?php foreach ($escolaridades as $row) : ?>
                                <option value="<?php echo htmlspecialchars($row['escolaridade']); ?>" <?php if ($escolaridade == $row['escolaridade']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($row['escolaridade']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tipo_vinculo">Tipo de Vínculo:</label>
                        <select class="form-control" id="tipo_vinculo" name="tipo_vinculo">
                            <option value="">Todos os Tipos de Vínculo</option>
                            <?php foreach ($tipos_vinculo as $row) : ?>
                                <option value="<?php echo htmlspecialchars($row['tipo_vinculo']); ?>" <?php if ($tipo_vinculo == $row['tipo_vinculo']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($row['tipo_vinculo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tempo_vinculo">Tempo de Vínculo:</label>
                        <select class="form-control" id="tempo_vinculo" name="tempo_vinculo">
                            <option value="">Todos os Tempos de Vínculo</option>
                            <option value="0" <?php if ($tempo_vinculo === '0') echo 'selected'; ?>>Menos de 1 Ano</option>
                            <?php for ($i = 1; $i <= 30; $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php if ($tempo_vinculo == $i) echo 'selected'; ?>><?php echo $i . ' anos'; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="status">Status do Usuário:</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">Todos os Status</option>
                            <option value="ativo" <?php if ($status == 'ativo') echo 'selected'; ?>>Ativo</option>
                            <option value="inativo" <?php if ($status == 'inativo') echo 'selected'; ?>>Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary" style="margin-top: 32px;">Filtrar</button>
                </div>
            </div>
        </form>

        <?php if (!empty($usuarios)) : ?>
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Usuários</h5>
                    <table class="table table-bordered mt-3" id="relatorioTable" style="font-size:12px;">
                        <thead>
                            <tr>
                                <th>Nome Completo</th>
                                <th>CPF</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Município</th>
                                <th>Cargo</th>
                                <th>Tempo Vínculo</th>
                                <th>Escolaridade</th>
                                <th>Vínculo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['nome_completo'] ?? 'Não Informado'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['cpf'] ?? 'Não Informado'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email'] ?? 'Não Informado'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['telefone'] ?? 'Não Informado'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['municipio'] ?? 'Não Informado'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['cargo'] ?? 'Não Informado'); ?></td>
                                    <td><?php
                                        if (isset($usuario['tempo_vinculo'])) {
                                            echo htmlspecialchars($usuario['tempo_vinculo']) == 0 ? 'Menos de 1 Ano' : htmlspecialchars($usuario['tempo_vinculo']) . ' anos';
                                        } else {
                                            echo 'Não Informado';
                                        }
                                        ?></td>
                                    <td><?php echo htmlspecialchars($usuario['escolaridade'] ?? 'Não Informado'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['tipo_vinculo'] ?? 'Não Informado'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['status'] ?? 'Não Informado'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="10"><strong>Total de Usuários:</strong></td>
                                <td><?php echo count($usuarios); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    <button onclick="generatePDF()" class="btn btn-danger mt-3">Gerar PDF</button>
                </div>
            </div>
        <?php else : ?>
            <p>Nenhum usuário encontrado com os filtros selecionados.</p>
        <?php endif; ?>
    </div>

    <!-- Adicionando jsPDF e jsPDF-AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
    <script>
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');

            doc.autoTable({
                head: [
                    ['Nome Completo', 'CPF', 'Email', 'Telefone', 'Município', 'Cargo', 'Tempo de Vínculo', 'Escolaridade', 'Tipo de Vínculo', 'Status']
                ],
                body: Array.from(document.querySelectorAll("#relatorioTable tbody tr")).map(row =>
                    Array.from(row.cells).map(cell => cell.textContent)
                ),
                foot: [
                    [{
                        content: 'Total de Usuários: ' + <?php echo count($usuarios); ?>,
                        colSpan: 10,
                        styles: { halign: 'right' }
                    }]
                ],
                theme: 'striped',
                headStyles: { fillColor: [52, 152, 219] },
                margin: { top: 20 },
                styles: { fontSize: 8 }
            });

            doc.save('relatorio_usuarios.pdf');
        }
    </script>
</body>
</html>

<?php
$conn->close();
include '../footer.php';
?>
