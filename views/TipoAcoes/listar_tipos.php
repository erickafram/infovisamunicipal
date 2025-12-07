<?php
session_start();
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Variáveis de paginação
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $results_per_page;

// Variável de pesquisa
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Consulta para contagem total de registros
$total_query = "SELECT COUNT(*) FROM tipos_acoes_executadas WHERE descricao LIKE '%$search%'";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_row();
$total_records = $total_row[0];
$total_pages = ceil($total_records / $results_per_page);

// Consulta com limite para paginação
$query = "SELECT * FROM tipos_acoes_executadas WHERE descricao LIKE '%$search%' LIMIT $start_from, $results_per_page";
$result = $conn->query($query);
?>

<body class="bg-gray-50 min-h-screen">
    <!-- Header com informações -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white py-6 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Tipos de Ações</h1>
                    <p class="text-blue-100 mt-1">Gerencie os tipos de ações executadas no sistema</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-500 bg-opacity-50 px-3 py-2 rounded-lg">
                        <span class="text-sm font-medium"><?php echo $total_records; ?> registros</span>
                    </div>
                    <a href="adicionar_tipo.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-blue-50 transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>Adicionar Tipo
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Card de Informações -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Informações sobre Tipos de Ações</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Gerencie os tipos de ações que podem ser executadas no sistema</li>
                            <li>Configure códigos de procedimento e atividades do SIA</li>
                            <li>Use a pesquisa para encontrar tipos específicos rapidamente</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensagens de Sucesso -->
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong>Sucesso:</strong> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Barra de Pesquisa e Filtros -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="flex-1 w-full md:max-w-md">
                    <form method="GET" action="" class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="search" name="search" 
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" 
                               placeholder="Pesquisar tipos de ações..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-blue-600 hover:text-blue-800">
                            <span class="sr-only">Pesquisar</span>
                        </button>
                    </form>
                </div>
                <div class="flex items-center space-x-3">
                    <?php if ($search): ?>
                        <a href="listar_tipos.php" 
                           class="bg-gray-500 text-white px-4 py-2 rounded-lg font-medium hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-times mr-2"></i>Limpar
                        </a>
                    <?php endif; ?>
                    <span class="text-sm text-gray-600">
                        Mostrando <?php echo min($start_from + 1, $total_records); ?>-<?php echo min($start_from + $results_per_page, $total_records); ?> de <?php echo $total_records; ?> registros
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabela de Tipos de Ações -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-list text-blue-600 mr-3"></i>
                    Lista de Tipos de Ações
                </h2>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código Procedimento</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade SIA</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold">
                                            #<?php echo htmlspecialchars($row['id']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['descricao']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm font-mono">
                                            <?php echo htmlspecialchars($row['codigo_procedimento']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($row['atividade_sia']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>Sim
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i>Não
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="editar_tipo.php?id=<?php echo htmlspecialchars($row['id']); ?>" 
                                               class="bg-blue-500 text-white px-3 py-1 rounded-lg text-xs font-medium hover:bg-blue-600 transition-colors duration-200 flex items-center">
                                                <i class="fas fa-edit mr-1"></i>Editar
                                            </a>
                                            <button onclick="confirmDelete(<?php echo $row['id']; ?>)" 
                                                    class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs font-medium hover:bg-red-600 transition-colors duration-200 flex items-center">
                                                <i class="fas fa-trash mr-1"></i>Deletar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum tipo de ação encontrado</h3>
                    <p class="text-gray-500 mb-4">
                        <?php if ($search): ?>
                            Não foram encontrados resultados para "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            Ainda não há tipos de ações cadastrados no sistema.
                        <?php endif; ?>
                    </p>
                    <a href="adicionar_tipo.php" 
                       class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>Adicionar Primeiro Tipo
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Mostrando <span class="font-medium"><?php echo min($start_from + 1, $total_records); ?></span> a 
                        <span class="font-medium"><?php echo min($start_from + $results_per_page, $total_records); ?></span> de 
                        <span class="font-medium"><?php echo $total_records; ?></span> resultados
                    </div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="listar_tipos.php?page=<?php echo $page - 1; ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="listar_tipos.php?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo ($i == $page) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="listar_tipos.php?page=<?php echo $page + 1; ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-4">Confirmar Exclusão</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Tem certeza que deseja deletar este tipo de ação? Esta ação não pode ser desfeita.
                    </p>
                </div>
                <div class="flex gap-4 px-4 py-3">
                    <button id="confirmDeleteBtn" 
                            class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition-colors duration-200">
                        Deletar
                    </button>
                    <button onclick="closeDeleteModal()" 
                            class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-400 transition-colors duration-200">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        let deleteId = null;

        function confirmDelete(id) {
            deleteId = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            deleteId = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (deleteId) {
                window.location.href = `deletar_tipo.php?id=${deleteId}`;
            }
        });

        // Fechar modal ao clicar fora
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Função para mostrar toast
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            
            toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full opacity-0`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.getElementById('toast-container').appendChild(toast);
            
            // Animar entrada
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 100);
            
            // Remover após 5 segundos
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000);
        }

        // Auto-submit do formulário de pesquisa com delay
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>

    <?php include '../footer.php'; ?>
</body>