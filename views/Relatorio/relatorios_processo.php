<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Parâmetros de filtro
$municipio = $_SESSION['user']['municipio']; // Município do usuário logado
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y'); // Ano selecionado pelo usuário
$tipo_processo = isset($_GET['tipo_processo']) ? $_GET['tipo_processo'] : 'LICENCIAMENTO'; // Tipo de processo selecionado
$situacao = isset($_GET['situacao']) ? $_GET['situacao'] : 'sem_processo'; // Situação selecionada

// Parâmetros de paginação
$registrosPorPagina = 10; // Quantidade de itens por página
$paginaAtual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($paginaAtual - 1) * $registrosPorPagina;

// Definição dos tipos de processo disponíveis
$tipos_processo = [
    'ADMINISTRATIVO',
    'DENÚNCIA',
    'LICENCIAMENTO',
    'PROJETO ARQUITETÔNICO'
];

// Construção da cláusula WHERE para a situação do processo
$whereSituacao = "";
if ($situacao === 'com_processo') {
    $whereSituacao = "p.tipo_processo IS NOT NULL";
} else {
    $whereSituacao = "p.tipo_processo IS NULL";
}

// Consulta para contar o total de registros para paginação
$sqlCount = "
    SELECT COUNT(*) as total
    FROM estabelecimentos e
    LEFT JOIN (
        SELECT *
        FROM processos 
        WHERE tipo_processo = ?
        AND YEAR(data_abertura) = ?
    ) p ON e.id = p.estabelecimento_id
    WHERE $whereSituacao
    AND e.municipio = ?
";

$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param("sss", $tipo_processo, $ano, $municipio);
$stmtCount->execute();
$totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Consulta para obter estabelecimentos com e sem o tipo de processo especificado
$sql = "
    SELECT e.*, 
           CASE WHEN p.tipo_processo IS NULL THEN 'Sem Processo' ELSE 'Com Processo' END AS situacao_processo
    FROM estabelecimentos e
    LEFT JOIN (
        SELECT *
        FROM processos 
        WHERE tipo_processo = ?
        AND YEAR(data_abertura) = ?
    ) p ON e.id = p.estabelecimento_id
    WHERE $whereSituacao
    AND e.municipio = ?
    ORDER BY e.nome_fantasia ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $tipo_processo, $ano, $municipio, $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();
$estabelecimentos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../header.php';
?>

<div class="container mx-auto px-4 py-6 mt-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Relatório de Estabelecimentos por Processo</h1>
    </div>
    
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-6">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="ano" class="block text-sm font-medium text-gray-700 mb-1">Selecione o Ano</label>
                        <select id="ano" name="ano" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                            <?php
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= 2024; $year--) {
                                echo "<option value=\"$year\"" . ($ano == $year ? ' selected' : '') . ">$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="tipo_processo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Processo</label>
                        <select id="tipo_processo" name="tipo_processo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                            <?php foreach ($tipos_processo as $tipo): ?>
                                <option value="<?php echo $tipo; ?>" <?php echo ($tipo_processo == $tipo ? 'selected' : ''); ?>>
                                    <?php echo $tipo; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="situacao" class="block text-sm font-medium text-gray-700 mb-1">Situação</label>
                        <select id="situacao" name="situacao" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                            <option value="sem_processo" <?php echo ($situacao == 'sem_processo' ? 'selected' : ''); ?>>Sem Processo</option>
                            <option value="com_processo" <?php echo ($situacao == 'com_processo' ? 'selected' : ''); ?>>Com Processo</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-sm">
                        Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resultados -->
    <?php if (!empty($estabelecimentos)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <div class="mb-4">
                <h2 class="text-lg font-medium text-gray-900">
                    Estabelecimentos para o Ano: <?php echo htmlspecialchars($ano); ?>
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    <?php echo count($estabelecimentos); ?> estabelecimento(s) encontrado(s) para o tipo de processo "<?php echo htmlspecialchars($tipo_processo); ?>" 
                    com situação: <?php echo ($situacao == 'com_processo') ? 'Com Processo' : 'Sem Processo'; ?>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="estabelecimentosTable">
                    <thead>
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">CNPJ</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome Fantasia</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Razão Social</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endereço</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Telefone</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($estabelecimentos as $estab) : ?>
                            <tr>
                            <td class="px-6 py-4 text-sm text-gray-500 overflow-hidden whitespace-nowrap text-ellipsis max-w-[70px]" title="<?php echo htmlspecialchars($estab['cnpj'] ?? ''); ?>"><?php echo htmlspecialchars($estab['cnpj'] ?? ''); ?></td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                <a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?php echo $estab['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                    <?php echo htmlspecialchars($estab['nome_fantasia'] ?? ''); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($estab['razao_social'] ?? ''); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500 max-w-sm truncate" title="<?php echo htmlspecialchars(($estab['logradouro'] ?? '') . ', ' . ($estab['numero'] ?? '') . ' - ' . ($estab['bairro'] ?? '') . ', ' . ($estab['municipio'] ?? '') . ' - ' . ($estab['uf'] ?? '') . ', ' . ($estab['cep'] ?? '')); ?>">
                                <?php echo htmlspecialchars(($estab['logradouro'] ?? '') . ', ' . ($estab['numero'] ?? '') . ' - ' . ($estab['bairro'] ?? '')); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php 
                                $telefone = '';
                                if (!empty($estab['ddd_telefone_1']) || !empty($estab['telefone_1'])) {
                                    $telefone = (!empty($estab['ddd_telefone_1']) ? '(' . $estab['ddd_telefone_1'] . ') ' : '') . 
                                              (!empty($estab['telefone_1']) ? $estab['telefone_1'] : '');
                                }
                                echo htmlspecialchars($telefone);
                            ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($estab['situacao_processo'] == 'Com Processo'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Com Processo
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Sem Processo
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Controles de Paginação -->
            <?php if ($totalPaginas > 1): ?>
            <div class="mt-6 flex justify-between items-center border-t border-gray-200 px-4 py-3 sm:px-6">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Mostrando <span class="font-medium"><?php echo ($offset + 1); ?></span> a 
                            <span class="font-medium"><?php echo min($offset + $registrosPorPagina, $totalRegistros); ?></span> de
                            <span class="font-medium"><?php echo $totalRegistros; ?></span> resultados
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <!-- Botão Anterior -->
                            <?php if ($paginaAtual > 1): ?>
                            <a href="?ano=<?php echo $ano; ?>&tipo_processo=<?php echo urlencode($tipo_processo); ?>&situacao=<?php echo $situacao; ?>&pagina=<?php echo ($paginaAtual - 1); ?>" 
                               class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Anterior</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <?php else: ?>
                            <span class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-300 ring-1 ring-inset ring-gray-300 cursor-not-allowed">
                                <span class="sr-only">Anterior</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <?php endif; ?>
                            
                            <!-- Páginas -->
                            <?php 
                            $inicio = max(1, $paginaAtual - 2);
                            $fim = min($inicio + 4, $totalPaginas);
                            if ($fim - $inicio < 4 && $inicio > 1) {
                                $inicio = max(1, $fim - 4);
                            }
                            
                            for ($i = $inicio; $i <= $fim; $i++): 
                            ?>
                                <?php if ($i == $paginaAtual): ?>
                                <span class="relative z-10 inline-flex items-center bg-blue-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                    <?php echo $i; ?>
                                </span>
                                <?php else: ?>
                                <a href="?ano=<?php echo $ano; ?>&tipo_processo=<?php echo urlencode($tipo_processo); ?>&situacao=<?php echo $situacao; ?>&pagina=<?php echo $i; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <?php echo $i; ?>
                                </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <!-- Botão Próximo -->
                            <?php if ($paginaAtual < $totalPaginas): ?>
                            <a href="?ano=<?php echo $ano; ?>&tipo_processo=<?php echo urlencode($tipo_processo); ?>&situacao=<?php echo $situacao; ?>&pagina=<?php echo ($paginaAtual + 1); ?>" 
                               class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Próximo</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <?php else: ?>
                            <span class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-300 ring-1 ring-inset ring-gray-300 cursor-not-allowed">
                                <span class="sr-only">Próximo</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 flex justify-end">
                <button onclick="generatePDF()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 text-sm flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd" />
                    </svg>
                    Gerar PDF
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 text-center">
            <div class="flex flex-col items-center justify-center py-10">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum estabelecimento encontrado</h3>
                <p class="mt-1 text-sm text-gray-500">Não foram encontrados estabelecimentos para os filtros selecionados.</p>
                <div class="mt-6">
                    <button type="button" onclick="window.location.href='relatorios_processo.php'" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Limpar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Script para gerar PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
<script>
    function generatePDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l'); // Paisagem para acomodar mais colunas
        
        // Adicionar título
        doc.setFontSize(16);
        doc.text('Relatório de Estabelecimentos por Processo', 14, 15);
        doc.setFontSize(12);
        
        // Obter os valores selecionados
        const tipoProcessoSelect = document.getElementById('tipo_processo');
        const situacaoSelect = document.getElementById('situacao');
        const anoSelect = document.getElementById('ano');
        
        const tipoProcessoText = tipoProcessoSelect ? 
            tipoProcessoSelect.options[tipoProcessoSelect.selectedIndex].text : '<?php echo $tipo_processo; ?>';
        const situacaoText = situacaoSelect ? 
            situacaoSelect.options[situacaoSelect.selectedIndex].text : '<?php echo ($situacao == "com_processo") ? "Com Processo" : "Sem Processo"; ?>';
        const anoText = anoSelect ? anoSelect.value : '<?php echo $ano; ?>';
        
        doc.text(`Tipo de Processo: ${tipoProcessoText}`, 14, 25);
        doc.text(`Situação: ${situacaoText}`, 14, 30);
        doc.text(`Ano: ${anoText}`, 14, 35);
        
        try {
            doc.autoTable({
                startY: 40,
                head: [['CNPJ', 'Nome Fantasia', 'Razão Social', 'Endereço', 'Telefone', 'Situação']],
                body: Array.from(document.querySelectorAll('#estabelecimentosTable tbody tr')).map(row => {
                    // Garantir que não há valores nulos
                    return [
                        (row.cells[0] && row.cells[0].textContent) ? row.cells[0].textContent.trim() : '',
                        (row.cells[1] && row.cells[1].textContent) ? row.cells[1].textContent.trim() : '',
                        (row.cells[2] && row.cells[2].textContent) ? row.cells[2].textContent.trim() : '',
                        (row.cells[3] && row.cells[3].textContent) ? row.cells[3].textContent.trim() : '',
                        (row.cells[4] && row.cells[4].textContent) ? row.cells[4].textContent.trim() : '',
                        (row.cells[5] && row.cells[5].textContent && row.cells[5].textContent.trim().includes('Com Processo')) ? 'Com Processo' : 'Sem Processo'
                    ];
                }),
                theme: 'striped',
                headStyles: { fillColor: [41, 128, 185] },
                footStyles: { fillColor: [41, 128, 185] },
                margin: { top: 40 },
                foot: [['Total de Estabelecimentos:', '', '', '', '', '<?php echo count($estabelecimentos); ?>']]
            });
            
            doc.save('relatorio_estabelecimentos_<?php echo htmlspecialchars($situacao); ?>_<?php echo htmlspecialchars($ano); ?>.pdf');
        } catch (error) {
            console.error('Erro ao gerar PDF:', error);
            alert('Ocorreu um erro ao gerar o PDF. Por favor, tente novamente.');
        }
    }
</script>

<?php include '../footer.php'; ?>