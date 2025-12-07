<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php'; // Conexão com o banco de dados
require_once '../../models/Documento.php';
require_once '../../models/Processo.php';
require_once '../../models/Arquivo.php';

$documento = new Documento($conn);

// Inicializa os filtros
$pesquisa = isset($_GET['pesquisa']) ? htmlspecialchars($_GET['pesquisa']) : '';
$data_inicio = isset($_GET['data_inicio']) ? htmlspecialchars($_GET['data_inicio']) : '';
$data_fim = isset($_GET['data_fim']) ? htmlspecialchars($_GET['data_fim']) : '';

// Configuração da paginação
$itensPorPagina = 10; // Número de itens por página
$paginaAtual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Monta a parte comum da consulta (WHERE)
$whereClause = "WHERE 1 = 1";
$params = [];

if (!empty($pesquisa)) {
    $whereClause .= " AND (e.nome LIKE ? 
                    OR est.nome_fantasia LIKE ? 
                    OR u.nome_completo LIKE ?)";
    $params[] = '%' . $pesquisa . '%';
    $params[] = '%' . $pesquisa . '%';
    $params[] = '%' . $pesquisa . '%';
}

if (!empty($data_inicio) && !empty($data_fim)) {
    $whereClause .= " AND DATE(e.data_exclusao) BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

// Consulta para contar o total de registros
$countQuery = "SELECT COUNT(*) as total 
               FROM logs e
               JOIN estabelecimentos est ON e.estabelecimento_id = est.id
               JOIN usuarios u ON e.usuario_id = u.id
               LEFT JOIN processos p ON e.processo_id = p.id
               $whereClause";

$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRegistros = $countResult->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $itensPorPagina);
$countStmt->close(); // Fechar o statement de contagem imediatamente

// Consulta principal com paginação
$query = "SELECT 
            e.nome AS nome_arquivo, 
            e.tipo, 
            e.data_exclusao,
            est.nome_fantasia, 
            est.cnpj, 
            u.nome_completo, 
            est.id AS estabelecimento_id,
            p.numero_processo, 
            p.id AS processo_id
          FROM logs e
          JOIN estabelecimentos est ON e.estabelecimento_id = est.id
          JOIN usuarios u ON e.usuario_id = u.id
          LEFT JOIN processos p ON e.processo_id = p.id
          $whereClause
          ORDER BY e.data_exclusao DESC
          LIMIT ?, ?";

// Prepara e executa a consulta principal
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $allParams = $params;
    $allParams[] = $offset;
    $allParams[] = $itensPorPagina;
    
    $types = str_repeat('s', count($params)) . 'ii';
    $stmt->bind_param($types, ...$allParams);
} else {
    $stmt->bind_param('ii', $offset, $itensPorPagina);
}
$stmt->execute();
$result = $stmt->get_result();
// Guardar o resultado em um array e fechar o statement imediatamente para evitar problemas
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close(); // Fechamos o statement logo após usar para evitar problemas
?>

<?php include '../header.php'; // Adiciona o header.php ?>

<div class="container mx-auto px-4 py-6 mt-4">
    <div class="flex items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Logs de Exclusões</h1>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-6">
            <!-- Formulário de Filtros -->
            <form method="GET" action="exclusoes.php" class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="col-span-1 md:col-span-1">
                        <label for="pesquisa" class="block text-sm font-medium text-gray-700 mb-1">Pesquisar</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text" id="pesquisa" name="pesquisa" placeholder="Arquivo, Estabelecimento ou Usuário" value="<?= $pesquisa; ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <label for="data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<?= $data_inicio; ?>" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <label for="data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                        <input type="date" id="data_fim" name="data_fim" value="<?= $data_fim; ?>" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div class="col-span-1 md:col-span-1 flex items-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Filtrar
                        </button>
                        <!-- Campo oculto para manter a página atual ao filtrar -->
                        <input type="hidden" name="pagina" value="1">
                    </div>
                </div>
            </form>

            <!-- Resumo dos resultados -->
            <div class="flex justify-between items-center mb-4">
                <p class="text-sm text-gray-600">
                    Mostrando <?= min(($paginaAtual - 1) * $itensPorPagina + 1, $totalRegistros) ?> a <?= min($paginaAtual * $itensPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?> registros
                </p>
            </div>

            <!-- Tabela -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-52 max-w-xs">Nome</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Tipo</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processo</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Data de Exclusão</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estabelecimento</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">CNPJ</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Excluído Por</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum log de exclusão encontrado</h3>
                                        <p class="mt-1 text-sm text-gray-500">Tente modificar os filtros de busca.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 truncate max-w-xs" title="<?= htmlspecialchars($row['nome_arquivo']); ?>">
                                        <div class="truncate"><?= htmlspecialchars($row['nome_arquivo']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($row['tipo'] === 'arquivo'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Documento</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Arquivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($row['processo_id']) : ?>
                                            <a href="../Processo/documentos.php?processo_id=<?= htmlspecialchars($row['processo_id']); ?>&id=<?= htmlspecialchars($row['estabelecimento_id']); ?>" class="text-blue-600 hover:text-blue-900 hover:underline">
                                                <?= htmlspecialchars($row['numero_processo']); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="text-gray-400">Não Vinculado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($row['data_exclusao'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?= htmlspecialchars($row['estabelecimento_id']); ?>" class="text-blue-600 hover:text-blue-900 hover:underline">
                                            <?= htmlspecialchars($row['nome_fantasia']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['cnpj']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['nome_completo']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($totalPaginas > 1): ?>
            <div class="mt-6">
                <nav class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-0">
                    <div class="hidden md:flex w-0 flex-1">
                        <?php if ($paginaAtual > 1): ?>
                        <a href="?pagina=<?= $paginaAtual - 1 ?>&pesquisa=<?= urlencode($pesquisa) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>" class="border-t-2 border-transparent pt-4 pr-1 inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            <svg class="mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                            Anterior
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex -mt-px">
                        <?php 
                        // Determinar um intervalo razoável de páginas para mostrar
                        $startPage = max(1, $paginaAtual - 2);
                        $endPage = min($totalPaginas, $paginaAtual + 2);
                        
                        // Garantir que pelo menos 5 páginas sejam mostradas (se existirem)
                        if ($endPage - $startPage + 1 < 5) {
                            if ($startPage == 1) {
                                $endPage = min($totalPaginas, $startPage + 4);
                            } elseif ($endPage == $totalPaginas) {
                                $startPage = max(1, $endPage - 4);
                            }
                        }
                        
                        // Mostrar link para a primeira página se necessário
                        if ($startPage > 1): ?>
                            <a href="?pagina=1&pesquisa=<?= urlencode($pesquisa) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>" class="border-t-2 <?= 1 == $paginaAtual ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> pt-4 px-4 inline-flex items-center text-sm font-medium">
                                1
                            </a>
                            <?php if ($startPage > 2): ?>
                                <span class="border-transparent text-gray-500 border-t-2 pt-4 px-4 inline-flex items-center text-sm font-medium">
                                    ...
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?pagina=<?= $i ?>&pesquisa=<?= urlencode($pesquisa) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>" class="border-t-2 <?= $i == $paginaAtual ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> pt-4 px-4 inline-flex items-center text-sm font-medium">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <!-- Mostrar link para a última página se necessário -->
                        <?php if ($endPage < $totalPaginas): ?>
                            <?php if ($endPage < $totalPaginas - 1): ?>
                                <span class="border-transparent text-gray-500 border-t-2 pt-4 px-4 inline-flex items-center text-sm font-medium">
                                    ...
                                </span>
                            <?php endif; ?>
                            <a href="?pagina=<?= $totalPaginas ?>&pesquisa=<?= urlencode($pesquisa) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>" class="border-t-2 <?= $totalPaginas == $paginaAtual ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> pt-4 px-4 inline-flex items-center text-sm font-medium">
                                <?= $totalPaginas ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hidden md:flex w-0 flex-1 justify-end">
                        <?php if ($paginaAtual < $totalPaginas): ?>
                        <a href="?pagina=<?= $paginaAtual + 1 ?>&pesquisa=<?= urlencode($pesquisa) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>" class="border-t-2 border-transparent pt-4 pl-1 inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            Próximo
                            <svg class="ml-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Fechamos a conexão apenas, pois os statements já foram fechados
$conn->close();
include '../footer.php'; // Adiciona o footer.php
?>