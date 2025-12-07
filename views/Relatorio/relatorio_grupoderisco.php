<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

$municipio = $_SESSION['user']['municipio']; // Município do usuário logado

// Consulta para obter os grupos de risco distintos
$gruposRiscoSql = "
    SELECT DISTINCT descricao 
    FROM grupo_risco
";
$gruposRiscoResult = $conn->query($gruposRiscoSql);

$selectedGroup = isset($_GET['grupo_risco']) ? $_GET['grupo_risco'] : '';

// Consulta para obter os estabelecimentos pelo grupo de risco selecionado
$estabelecimentos = [];
if ($selectedGroup) {
    $estabelecimentosSql = "
    SELECT DISTINCT 
        e.*,
        IF(gr_fiscal.descricao = ?, gr_fiscal.descricao, gr_secundario.descricao) AS grupo_risco
    FROM 
        estabelecimentos e
    LEFT JOIN 
        atividade_grupo_risco agr_fiscal ON e.cnae_fiscal = agr_fiscal.cnae AND e.municipio = agr_fiscal.municipio
    LEFT JOIN 
        grupo_risco gr_fiscal ON agr_fiscal.grupo_risco_id = gr_fiscal.id
    LEFT JOIN (
        SELECT 
            e.id AS estabelecimento_id, 
            JSON_UNQUOTE(JSON_EXTRACT(cnaes.codigo, '$')) AS cnae_secundario
        FROM 
            estabelecimentos e,
            JSON_TABLE(e.cnaes_secundarios, '$[*]' COLUMNS (codigo VARCHAR(20) PATH '$.codigo')) cnaes
    ) cnae_secundario ON e.id = cnae_secundario.estabelecimento_id
    LEFT JOIN 
        atividade_grupo_risco agr_secundario ON cnae_secundario.cnae_secundario = agr_secundario.cnae AND e.municipio = agr_secundario.municipio
    LEFT JOIN 
        grupo_risco gr_secundario ON agr_secundario.grupo_risco_id = gr_secundario.id
    WHERE 
        (gr_fiscal.descricao = ? OR gr_secundario.descricao = ?) AND e.municipio = ?
";

    $stmt = $conn->prepare($estabelecimentosSql);
    $stmt->bind_param("ssss", $selectedGroup, $selectedGroup, $selectedGroup, $municipio);
    $stmt->execute();
    $result = $stmt->get_result();
    $estabelecimentos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

include '../footer.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Estabelecimentos por Grupo de Risco</title>
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
        <h4>Relatório de Estabelecimentos por Grupo de Risco</h4>
        <form method="GET" action="relatorio_grupoderisco.php" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <label for="grupo_risco" class="form-label">Grupo de Risco</label>
                    <select name="grupo_risco" id="grupo_risco" class="form-control">
                        <option value="">Selecione um grupo de risco</option>
                        <?php while ($row = $gruposRiscoResult->fetch_assoc()) : ?>
                            <option value="<?php echo htmlspecialchars($row['descricao']); ?>" <?php echo $selectedGroup === $row['descricao'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['descricao']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary mt-4" style="margin-top:32px !important;">Gerar Relatório</button>
                </div>
            </div>
        </form>

        <?php if ($selectedGroup && !empty($estabelecimentos)) : ?>
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Estabelecimentos do Grupo de Risco: <?php echo htmlspecialchars($selectedGroup); ?></h5>
                    <table class="table table-bordered mt-3" id="relatorioTable">
                        <thead>
                            <tr>
                                <th>CNPJ</th>
                                <th>Nome Fantasia</th>
                                <th>Razão Social</th>
                                <th>Endereço</th>
                                <th>Telefone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estabelecimentos as $estabelecimento) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($estabelecimento['cnpj']); ?></td>
                                    <td><?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?></td>
                                    <td><?php echo htmlspecialchars($estabelecimento['razao_social']); ?></td>
                                    <td><?php echo htmlspecialchars($estabelecimento['logradouro'] . ', ' . $estabelecimento['numero'] . ' - ' . $estabelecimento['bairro'] . ', ' . $estabelecimento['municipio'] . ' - ' . $estabelecimento['uf'] . ', ' . $estabelecimento['cep']); ?></td>
                                    <td><?php echo htmlspecialchars($estabelecimento['ddd_telefone_1']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p>Total de Estabelecimentos: <?php echo count($estabelecimentos); ?></p>
                    <button onclick="generatePDF()" class="btn btn-danger mt-3">Gerar PDF</button>
                </div>

            </div>
        <?php elseif ($selectedGroup) : ?>
            <p>Nenhum estabelecimento encontrado para o grupo de risco selecionado.</p>
        <?php endif; ?>
    </div>

    <!-- Adicionando jsPDF e jsPDF-AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
    <script>
        function generatePDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            doc.autoTable({
                head: [
                    ['CNPJ', 'Nome Fantasia', 'Razão Social', 'Endereço', 'Telefone']
                ],
                body: Array.from(document.querySelectorAll("#relatorioTable tbody tr")).map(row =>
                    Array.from(row.cells).map(cell => cell.textContent)
                ),
                theme: 'striped',
                headStyles: {
                    fillColor: [52, 152, 219]
                },
                margin: {
                    top: 20
                }
            });

            doc.text(`Total de Estabelecimentos: ${document.querySelectorAll("#relatorioTable tbody tr").length}`, 14, doc.autoTable.previous.finalY + 10);

            doc.save('relatorio_grupo_de_risco.pdf');
        }
    </script>
</body>

</html>