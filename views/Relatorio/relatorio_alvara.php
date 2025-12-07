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
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y'); // Ano selecionado pelo usuário
$situacao = isset($_GET['situacao']) ? $_GET['situacao'] : 'sem_alvara'; // Situação selecionada pelo usuário

// Construção da cláusula WHERE para a situação do alvará
$whereSituacao = "";
if ($situacao === 'com_alvara') {
    $whereSituacao = "a.processo_id IS NOT NULL";
} else {
    $whereSituacao = "a.processo_id IS NULL";
}

// Paginação
$registrosPorPagina = 10;
$paginaAtual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($paginaAtual - 1) * $registrosPorPagina;

// Consulta para contar o total de registros
$sqlCount = "
    SELECT COUNT(*) as total
    FROM estabelecimentos e
    JOIN processos p ON e.id = p.estabelecimento_id
    LEFT JOIN (
        SELECT DISTINCT processo_id 
        FROM arquivos 
        WHERE tipo_documento = 'ALVARÁ SANITÁRIO'
        AND YEAR(data_upload) = ?
    ) a ON p.id = a.processo_id
    WHERE $whereSituacao
    AND e.municipio = ?
    AND p.tipo_processo = 'LICENCIAMENTO'
    AND YEAR(p.data_abertura) = ?
";

$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param("sss", $ano, $municipio, $ano);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalRegistros = $resultCount->fetch_assoc()['total'];
$stmtCount->close();

$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Consulta para obter estabelecimentos com e sem alvará sanitário (com paginação)
$sql = "
    SELECT e.*, 
           CASE WHEN a.processo_id IS NULL THEN 'Sem Alvará Sanitário' ELSE 'Com Alvará Sanitário' END AS situacao_alvara
    FROM estabelecimentos e
    JOIN processos p ON e.id = p.estabelecimento_id
    LEFT JOIN (
        SELECT DISTINCT processo_id 
        FROM arquivos 
        WHERE tipo_documento = 'ALVARÁ SANITÁRIO'
        AND YEAR(data_upload) = ?
    ) a ON p.id = a.processo_id
    WHERE $whereSituacao
    AND e.municipio = ?
    AND p.tipo_processo = 'LICENCIAMENTO'
    AND YEAR(p.data_abertura) = ?
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $ano, $municipio, $ano, $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();
$estabelecimentos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../footer.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Estabelecimentos com e sem Alvará Sanitário</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Relatório de Estabelecimentos com e sem Alvará Sanitário</h2>
            
            <form method="GET" action="" class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">Selecione o Ano:</label>
                    <select id="ano" name="ano" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <?php
                        $currentYear = date('Y');
                        for ($year = $currentYear; $year >= 2024; $year--) {
                            $selected = $year == $ano ? 'selected' : '';
                            echo "<option value=\"$year\" $selected>$year</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="situacao" class="block text-sm font-medium text-gray-700 mb-2">Selecione a Situação:</label>
                    <select id="situacao" name="situacao" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="sem_alvara" <?php echo $situacao == 'sem_alvara' ? 'selected' : ''; ?>>Sem Alvará Sanitário</option>
                        <option value="com_alvara" <?php echo $situacao == 'com_alvara' ? 'selected' : ''; ?>>Com Alvará Sanitário</option>
                    </select>
                </div>
                
                <div class="col-span-1 md:col-span-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Filtrar
                    </button>
                </div>
            </form>

            <?php if (!empty($estabelecimentos)) : ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">
                            Estabelecimentos no município: <?php echo htmlspecialchars($municipio); ?> - 
                            Situação: <?php echo htmlspecialchars($situacao == 'sem_alvara' ? 'Sem Alvará Sanitário' : 'Com Alvará Sanitário'); ?>
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="relatorioTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CNPJ</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome Fantasia</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Razão Social</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endereço</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação Alvará</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($estabelecimentos as $estabelecimento) : ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?php echo $estabelecimento['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-800 hover:underline">
                                                <?php echo htmlspecialchars($estabelecimento['cnpj']); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($estabelecimento['razao_social']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($estabelecimento['logradouro'] . ', ' . $estabelecimento['numero'] . ' - ' . $estabelecimento['bairro'] . ', ' . $estabelecimento['municipio'] . ' - ' . $estabelecimento['uf'] . ', ' . $estabelecimento['cep']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($estabelecimento['ddd_telefone_1']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($estabelecimento['situacao_alvara']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Total de Estabelecimentos:</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $totalRegistros; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if ($totalPaginas > 1): ?>
                    <div class="px-6 py-4 bg-white border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Mostrando <span class="font-medium"><?php echo $offset + 1; ?></span> a 
                                <span class="font-medium"><?php echo min($offset + $registrosPorPagina, $totalRegistros); ?></span> de 
                                <span class="font-medium"><?php echo $totalRegistros; ?></span> resultados
                            </div>
                            <div class="flex space-x-1">
                                <?php if ($paginaAtual > 1): ?>
                                    <a href="?ano=<?php echo $ano; ?>&situacao=<?php echo $situacao; ?>&pagina=1" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                        <span class="sr-only">Primeira</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <a href="?ano=<?php echo $ano; ?>&situacao=<?php echo $situacao; ?>&pagina=<?php echo $paginaAtual - 1; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                        Anterior
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $inicio = max(1, $paginaAtual - 2);
                                $fim = min($totalPaginas, $paginaAtual + 2);
                                
                                for ($i = $inicio; $i <= $fim; $i++): ?>
                                    <a href="?ano=<?php echo $ano; ?>&situacao=<?php echo $situacao; ?>&pagina=<?php echo $i; ?>" 
                                       class="px-3 py-1 <?php echo $i == $paginaAtual ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'; ?> rounded-md hover:bg-gray-200">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($paginaAtual < $totalPaginas): ?>
                                    <a href="?ano=<?php echo $ano; ?>&situacao=<?php echo $situacao; ?>&pagina=<?php echo $paginaAtual + 1; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                        Próxima
                                    </a>
                                    <a href="?ano=<?php echo $ano; ?>&situacao=<?php echo $situacao; ?>&pagina=<?php echo $totalPaginas; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                        <span class="sr-only">Última</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="px-6 py-4 bg-gray-50">
                        <button onclick="generatePDF()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            <i class="fas fa-file-pdf mr-2"></i> Gerar PDF
                        </button>
                    </div>
                </div>
            <?php else : ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Nenhum estabelecimento encontrado com a situação selecionada no município.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Título do documento
            doc.setFontSize(16);
            doc.text('Relatório de Estabelecimentos - <?php echo htmlspecialchars($situacao == "sem_alvara" ? "Sem Alvará Sanitário" : "Com Alvará Sanitário"); ?>', 14, 15);
            doc.setFontSize(12);
            doc.text('Município: <?php echo htmlspecialchars($municipio); ?> - Ano: <?php echo $ano; ?>', 14, 25);
            
            // Criar tabela com os dados
            doc.autoTable({
                head: [['CNPJ', 'Nome Fantasia', 'Razão Social', 'Endereço', 'Telefone', 'Situação Alvará']],
                body: Array.from(document.querySelectorAll("#relatorioTable tbody tr")).map(row =>
                    Array.from(row.cells).map(cell => cell.textContent.trim())
                ),
                foot: [[{content: 'Total de Estabelecimentos: <?php echo $totalRegistros; ?>', colSpan: 6, styles: {halign: 'right', fontStyle: 'bold'}}]],
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [41, 128, 185], textColor: 255 },
                alternateRowStyles: { fillColor: [240, 240, 240] },
                margin: { top: 30 }
            });

            doc.save('relatorio_alvara_<?php echo $situacao; ?>_<?php echo $ano; ?>.pdf');
        }
    </script>
</body>
</html>
