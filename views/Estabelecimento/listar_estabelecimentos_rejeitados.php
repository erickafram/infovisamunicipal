<?php
session_start();


// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado ou não for administrador
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

$estabelecimento = new Estabelecimento($conn);

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Configurações de paginação
$limit = 10; // Número de registros por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$userMunicipio = $_SESSION['user']['municipio'];
$nivel_acesso = $_SESSION['user']['nivel_acesso'];

// Obter estabelecimentos rejeitados filtrados pelo município do usuário
$totalEstabelecimentos = $estabelecimento->countEstabelecimentosRejeitados($search, $userMunicipio, $nivel_acesso);
$totalPages = ceil($totalEstabelecimentos / $limit);

$estabelecimentos = $estabelecimento->searchEstabelecimentosRejeitados($search, $limit, $offset, $userMunicipio, $nivel_acesso);

// Reiniciar estabelecimento para status pendente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reiniciar_estabelecimento_id'])) {
    $estabelecimentoId = $_POST['reiniciar_estabelecimento_id'];
    if ($estabelecimento->reiniciarEstabelecimento($estabelecimentoId)) {
        header("Location: listar_estabelecimentos_rejeitados.php?success=1");
        exit();
    } else {
        header("Location: listar_estabelecimentos_rejeitados.php?error=1");
        exit();
    }
}
include '../header.php';

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estabelecimentos Rejeitados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        Estabelecimento reiniciado com sucesso!
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">
                        Erro ao reiniciar estabelecimento. Tente novamente.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Estabelecimentos Rejeitados</h1>
                    <p class="text-base text-gray-600">Gerencie os estabelecimentos que foram negados e permita nova análise</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-red-100 text-red-800 px-4 py-2 rounded-full text-base font-semibold">
                        <?php echo $totalEstabelecimentos; ?> rejeitado(s)
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-ban text-red-400 text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Form -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 mb-8">
            <form method="GET" action="listar_estabelecimentos_rejeitados.php" class="space-y-4 md:space-y-0 md:flex md:items-end md:space-x-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        Buscar estabelecimento
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               name="search" 
                               id="search"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Digite CNPJ, nome fantasia ou município..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <button type="submit" 
                        class="w-full md:w-auto inline-flex items-center justify-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>
                    Buscar
                </button>
                <?php if (!empty($search)): ?>
                <a href="listar_estabelecimentos_rejeitados.php" 
                   class="w-full md:w-auto inline-flex items-center justify-center px-6 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>
                    Limpar
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th scope="col" class="px-8 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                ID
                            </th>
                            <th scope="col" class="px-8 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                CNPJ
                            </th>
                            <th scope="col" class="px-8 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                Nome Fantasia
                            </th>
                            <th scope="col" class="px-8 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                Município
                            </th>
                            <th scope="col" class="px-8 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                Motivo da Rejeição
                            </th>
                            <th scope="col" class="px-8 py-4 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($estabelecimentos): ?>
                            <?php foreach ($estabelecimentos as $estab): ?>
                                <tr class="hover:bg-blue-50 transition-all duration-200 border-b border-gray-100">
                                    <td class="px-8 py-6 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 shadow-sm">
                                            #<?php echo htmlspecialchars($estab['id']); ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 whitespace-nowrap text-sm font-mono text-gray-900 font-medium">
                                        <?php echo htmlspecialchars($estab['cnpj']); ?>
                                    </td>
                                    <td class="px-8 py-6 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($estab['nome_fantasia']); ?>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 whitespace-nowrap text-sm text-gray-600">
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i>
                                            <span class="font-medium"><?php echo htmlspecialchars($estab['municipio']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="max-w-md">
                                            <div class="text-sm text-gray-900 truncate bg-gray-50 px-3 py-2 rounded-md" title="<?php echo htmlspecialchars($estab['motivo_negacao']); ?>">
                                                <?php 
                                                $motivo = htmlspecialchars($estab['motivo_negacao']);
                                                echo strlen($motivo) > 80 ? substr($motivo, 0, 80) . '...' : $motivo;
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 whitespace-nowrap text-center">
                                        <button type="button"
                                                onclick="openReinicirarModal(<?php echo htmlspecialchars($estab['id']); ?>, '<?php echo htmlspecialchars(addslashes($estab['nome_fantasia'])); ?>')"
                                                class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm hover:shadow-md"
                                                title="Reiniciar estabelecimento">
                                            <i class="fas fa-redo mr-2"></i>
                                            Reiniciar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-8 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="bg-green-100 rounded-full p-4 mb-4">
                                            <i class="fas fa-check-circle text-green-500 text-5xl"></i>
                                        </div>
                                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Nenhum estabelecimento rejeitado</h3>
                                        <p class="text-gray-600 text-base max-w-md">Não há estabelecimentos rejeitados no momento ou com os filtros aplicados. Isso é uma boa notícia!</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-white px-8 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <!-- Mobile pagination -->
                        <?php if ($page > 1): ?>
                        <a href="listar_estabelecimentos_rejeitados.php?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Anterior
                        </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                        <a href="listar_estabelecimentos_rejeitados.php?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Próxima
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando 
                                <span class="font-medium"><?php echo ($offset + 1); ?></span>
                                até 
                                <span class="font-medium"><?php echo min($offset + $limit, $totalEstabelecimentos); ?></span>
                                de 
                                <span class="font-medium"><?php echo $totalEstabelecimentos; ?></span>
                                resultados
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                                    <?php if ($i == $page): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                                            <?php echo $i; ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="listar_estabelecimentos_rejeitados.php?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors duration-200">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Confirmação para Reiniciar -->
    <div id="reiniciarModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-6 border w-11/12 md:w-1/2 lg:w-2/5 shadow-2xl rounded-xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Confirmar Reinicialização</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('reiniciarModal')">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Atenção</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>Você está prestes a reiniciar o estabelecimento <strong id="nomeEstabelecimento"></strong>.</p>
                                <p class="mt-1">Isso mudará o status de "Rejeitado" para "Pendente" e permitirá uma nova análise.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="listar_estabelecimentos_rejeitados.php">
                    <input type="hidden" name="reiniciar_estabelecimento_id" id="reiniciarEstabelecimentoId">
                    
                    <div class="flex items-center justify-end space-x-3 pt-4">
                        <button type="button" 
                                onclick="closeModal('reiniciarModal')"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm hover:shadow-md">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-6 py-3 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 hover:shadow-lg">
                            <i class="fas fa-redo mr-2"></i>
                            Reiniciar Estabelecimento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funções para controle dos modais
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openReinicirarModal(estabelecimentoId, nomeFantasia) {
            document.getElementById('reiniciarEstabelecimentoId').value = estabelecimentoId;
            document.getElementById('nomeEstabelecimento').textContent = nomeFantasia;
            openModal('reiniciarModal');
        }

        // Fechar modal clicando fora dele
        window.addEventListener('click', function(event) {
            const reiniciarModal = document.getElementById('reiniciarModal');
            
            if (event.target === reiniciarModal) {
                closeModal('reiniciarModal');
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal('reiniciarModal');
            }
        });

        // Auto-hide success/error messages
        setTimeout(function() {
            const alerts = document.querySelectorAll('[class*="bg-green-50"], [class*="bg-red-50"]');
            alerts.forEach(function(alert) {
                if (alert.parentElement) {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        if (alert.parentElement) {
                            alert.remove();
                        }
                    }, 500);
                }
            });
        }, 5000);
    </script>
</body>

</html>

<?php
$conn->close();
include '../footer.php';
?>

