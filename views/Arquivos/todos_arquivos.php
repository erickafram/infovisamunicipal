<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$municipio = $_SESSION['user']['municipio']; // Município do usuário logado

$perPage = 10; // Número de resultados por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Consulta para obter a contagem total de registros (sem filtrar por status)
$countSql = "
    SELECT COUNT(*) AS total
    FROM arquivos a
    JOIN processos p ON a.processo_id = p.id
    JOIN estabelecimentos e ON p.estabelecimento_id = e.id
    WHERE 
        (p.numero_processo LIKE '%$searchTerm%' OR 
         a.tipo_documento LIKE '%$searchTerm%' OR 
         a.numero_arquivo LIKE '%$searchTerm%' OR 
         e.nome_fantasia LIKE '%$searchTerm%') AND
         e.municipio = '$municipio'
";

$countResult = $conn->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Consulta para obter todos os documentos e informações relacionadas com pesquisa e paginação (incluindo caminho_arquivo)
$sql = "
    SELECT 
        a.id, 
        a.tipo_documento, 
        a.data_upload, 
        a.numero_arquivo, 
        a.sigiloso, 
        a.caminho_arquivo,
        p.id AS processo_id, 
        p.numero_processo, 
        e.nome_fantasia AS estabelecimento_nome,
        e.id AS estabelecimento_id
    FROM 
        arquivos a
    JOIN 
        processos p ON a.processo_id = p.id
    JOIN 
        estabelecimentos e ON p.estabelecimento_id = e.id
    WHERE 
        (p.numero_processo LIKE '%$searchTerm%' OR 
         a.tipo_documento LIKE '%$searchTerm%' OR 
         a.numero_arquivo LIKE '%$searchTerm%' OR 
         e.nome_fantasia LIKE '%$searchTerm%') AND
         e.municipio = '$municipio'
    ORDER BY 
        a.data_upload DESC
    LIMIT $perPage OFFSET $offset
";

$result = $conn->query($sql);

// Debug: Verifique os dados retornados pela consulta
$debugData = [];
while ($row = $result->fetch_assoc()) {
    $debugData[] = $row;
}
?>

<?php include '../header.php'; ?>

<div class="container mx-auto px-4 py-6 mt-4">
    <div class="flex items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Todos os Documentos</h1>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <form method="GET" action="todos_arquivos.php" class="mb-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-grow">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text" name="search" placeholder="Pesquisar por documento, processo ou estabelecimento..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Pesquisar
                        </button>
                    </div>
                </div>
            </form>

            <?php if (empty($debugData)): ?>
                <div class="flex items-center justify-center py-8">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum documento encontrado</h3>
                        <p class="mt-1 text-sm text-gray-500">Tente modificar sua pesquisa ou criar um novo documento.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de Documento</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sigiloso</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processo</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estabelecimento</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visualizar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($debugData as $row) : ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['tipo_documento'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['data_upload']))); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['numero_arquivo'] . '.' . date('Y')); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($row['sigiloso']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Sim</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="../Processo/documentos.php?processo_id=<?php echo htmlspecialchars($row['processo_id']); ?>&id=<?php echo htmlspecialchars($row['estabelecimento_id']); ?>" class="text-blue-600 hover:text-blue-900 hover:underline">
                                            <?php echo htmlspecialchars($row['numero_processo'] ?? ''); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['estabelecimento_nome'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if (!empty($row['caminho_arquivo'])): ?>
                                            <a href="../../<?php echo htmlspecialchars($row['caminho_arquivo']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900" title="Visualizar Documento">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">Não disponível</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação Melhorada -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6">
                    <nav class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-0">
                        <div class="hidden md:flex w-0 flex-1">
                            <?php if ($page > 1): ?>
                            <a href="todos_arquivos.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>" class="border-t-2 border-transparent pt-4 pr-1 inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
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
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            // Garantir que pelo menos 5 páginas sejam mostradas (se existirem)
                            if ($endPage - $startPage + 1 < 5) {
                                if ($startPage == 1) {
                                    $endPage = min($totalPages, $startPage + 4);
                                } elseif ($endPage == $totalPages) {
                                    $startPage = max(1, $endPage - 4);
                                }
                            }
                            
                            // Mostrar link para a primeira página se necessário
                            if ($startPage > 1): ?>
                                <a href="todos_arquivos.php?page=1&search=<?php echo urlencode($searchTerm); ?>" class="border-t-2 <?php echo 1 == $page ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> pt-4 px-4 inline-flex items-center text-sm font-medium">
                                    1
                                </a>
                                <?php if ($startPage > 2): ?>
                                    <span class="border-transparent text-gray-500 border-t-2 pt-4 px-4 inline-flex items-center text-sm font-medium">
                                        ...
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="todos_arquivos.php?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>" class="border-t-2 <?php echo $i == $page ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> pt-4 px-4 inline-flex items-center text-sm font-medium">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <!-- Mostrar link para a última página se necessário -->
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="border-transparent text-gray-500 border-t-2 pt-4 px-4 inline-flex items-center text-sm font-medium">
                                        ...
                                    </span>
                                <?php endif; ?>
                                <a href="todos_arquivos.php?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($searchTerm); ?>" class="border-t-2 <?php echo $totalPages == $page ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> pt-4 px-4 inline-flex items-center text-sm font-medium">
                                    <?php echo $totalPages; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hidden md:flex w-0 flex-1 justify-end">
                            <?php if ($page < $totalPages): ?>
                            <a href="todos_arquivos.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>" class="border-t-2 border-transparent pt-4 pl-1 inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>