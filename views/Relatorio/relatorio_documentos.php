<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Inicializa variáveis de filtro
$tipoDocumento = isset($_GET['tipo_documento']) ? $_GET['tipo_documento'] : '';
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$municipio = $_SESSION['user']['municipio']; // Município do usuário logado

// Consulta para obter os tipos de documento distintos
$tiposDocumentosSql = "SELECT DISTINCT tipo_documento FROM arquivos";
$tiposDocumentosResult = $conn->query($tiposDocumentosSql);

include '../header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Relatório Documentos</h2>
    <div class="card">
        <div class="card-body">
            <form method="GET" action="relatorio_documentos.php" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                        <select name="tipo_documento" id="tipo_documento" class="form-control">
                            <option value="">Todos</option>
                            <?php while ($row = $tiposDocumentosResult->fetch_assoc()) : ?>
                                <option value="<?php echo htmlspecialchars($row['tipo_documento']); ?>" <?php echo $tipoDocumento === $row['tipo_documento'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['tipo_documento']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="data_inicio" class="form-label">Data Início</label>
                        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?php echo htmlspecialchars($dataInicio); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="data_fim" class="form-label">Data Fim</label>
                        <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?php echo htmlspecialchars($dataFim); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Gerar Relatório</button>
            </form>

            <?php
            if (!empty($dataInicio) && !empty($dataFim)) {
                // Consulta para obter os registros filtrados
                $sql = "
                SELECT a.*, e.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ' - ', e.bairro, ', ', e.municipio, ' - ', e.uf, ', ', e.cep) AS endereco_estabelecimento
                FROM arquivos a
                JOIN processos p ON a.processo_id = p.id
                JOIN estabelecimentos e ON p.estabelecimento_id = e.id
                WHERE a.data_upload BETWEEN ? AND ?
                AND e.municipio = ?
            ";

                if (!empty($tipoDocumento)) {
                    $sql .= " AND a.tipo_documento = ?";
                }

                $stmt = $conn->prepare($sql);

                if (!empty($tipoDocumento)) {
                    $stmt->bind_param("ssss", $dataInicio, $dataFim, $municipio, $tipoDocumento);
                } else {
                    $stmt->bind_param("sss", $dataInicio, $dataFim, $municipio);
                }

                $stmt->execute();
                $result = $stmt->get_result();
                $totalRegistros = $result->num_rows;
            ?>

                <table class="table table-bordered mt-4" id="relatorioTable">
                    <thead>
                        <tr style="font-size:13px;">
                            <th>Tipo de Documento</th>
                            <th>Data</th>
                            <th>Número</th>
                            <th>Sigiloso</th>
                            <th>Status</th>
                            <th>Nome do Estabelecimento</th>
                            <th>Endereço do Estabelecimento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <tr style="font-size:13px;">
                                <td><?php echo htmlspecialchars($row['tipo_documento']); ?></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($row['data_upload']))); ?></td>
                                <td><?php echo htmlspecialchars($row['numero_arquivo']); ?></td>
                                <td><?php echo $row['sigiloso'] ? 'Sim' : 'Não'; ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><?php echo htmlspecialchars($row['nome_fantasia']); ?></td>
                                <td><?php echo htmlspecialchars($row['endereco_estabelecimento']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="1"><strong style="font-size:13px;">Total de Registros: <?php echo $totalRegistros; ?></strong></td>
                        </tr>
                    </tfoot>
                </table>


                <button onclick="generatePDF()" class="btn btn-danger mt-3">Gerar PDF</button>

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
                                ['Tipo de Documento', 'Data', 'Número', 'Sigiloso', 'Status', 'Nome do Estabelecimento', 'Endereço do Estabelecimento']
                            ],
                            body: Array.from(document.querySelectorAll("#relatorioTable tbody tr")).map(row =>
                                Array.from(row.cells).map(cell => cell.textContent)
                            ),
                            foot: [
                                [{
                                    content: `Total de Registros: ${<?php echo $totalRegistros; ?>}`,
                                    colSpan: 7,
                                    styles: {
                                        halign: 'right'
                                    }
                                }]
                            ],
                            theme: 'striped',
                            headStyles: {
                                fillColor: [52, 152, 219]
                            },
                            margin: {
                                top: 20
                            }
                        });
                        doc.save('relatorio_documentos.pdf');
                    }
                </script>

            <?php
            } else {
                echo "<p>Por favor, selecione um período para o relatório.</p>";
            }
            ?>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>