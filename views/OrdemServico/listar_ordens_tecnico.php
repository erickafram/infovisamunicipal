<?php
session_start();
include '../header.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

$ordemServico = new OrdemServico($conn);
$user_id = $_SESSION['user']['id'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Obter todas as ordens de serviço ativas do técnico
$ordens = $ordemServico->getOrdensAtivasByTecnico($user_id);
$total_ordens = count($ordens);
$total_pages = ceil($total_ordens / $limit);

// Limitar os resultados para a paginação atual
$ordens = array_slice($ordens, $offset, $limit);

function formatDate($date)
{
    if (empty($date)) return 'N/A';
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('d/m/Y');
    } catch (Exception $e) {
        return 'Data inválida';
    }
}

function getStatusBadgeClassTailwind($status) {
    $status = strtolower($status ?? '');
    if ($status === 'ativa' || $status === 'em andamento') {
        return 'bg-green-100 text-green-800';
    } elseif ($status === 'finalizada' || $status === 'concluída') {
        return 'bg-blue-100 text-blue-800';
    } elseif ($status === 'cancelada') {
        return 'bg-red-100 text-red-800';
    } elseif ($status === 'pendente') {
        return 'bg-yellow-100 text-yellow-800';
    }
    return 'bg-gray-100 text-gray-800';
}

function getDetalhesUrl($ordem)
{
    return (empty($ordem['estabelecimento_id']) || empty($ordem['processo_id']))
        ? 'detalhes_ordem_sem_estabelecimento.php?id=' . urlencode($ordem['id'])
        : 'detalhes_ordem.php?id=' . urlencode($ordem['id']);
}

function formatOSNumber($id, $date)
{
    if (empty($id) || empty($date)) return 'N/A';
    try {
        return htmlspecialchars($id . '.' . date('Y', strtotime($date)));
    } catch (Exception $e) {
        return htmlspecialchars($id) . '.????';
    }
}

?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-400 px-4 py-4 text-white">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold flex items-center">
                    <i class="fas fa-clipboard-list mr-2"></i> Minhas Ordens de Serviço Ativas
                </h2>
                <a href="../Dashboard/dashboard.php" class="bg-white text-blue-600 hover:bg-blue-50 px-3 py-1.5 rounded-md text-sm font-medium transition-colors duration-200 flex items-center shadow-sm">
                    <i class="fas fa-arrow-left mr-1.5"></i> Voltar
                </a>
            </div>
        </div>

        <div class="p-4">
            <form class="mb-4" method="GET" action="listar_ordens_tecnico.php">
                <div class="flex">
                    <div class="relative flex-grow">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out" 
                               name="search" 
                               placeholder="Buscar por número, razão social, nome fantasia..." 
                               value="<?= htmlspecialchars($search); ?>" 
                               aria-label="Buscar ordens de serviço">
                    </div>
                    <button class="ml-3 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" 
                            type="submit" 
                            id="button-search">
                        Buscar
                    </button>
                </div>
            </form>

            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Lista de Ordens de Serviço Ativas</h3>
                <span class="text-sm text-gray-500"><?php echo $total_ordens; ?> ordens encontradas</span>
            </div>
            
            <div class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Razão Social</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome Fantasia</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Início</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Fim</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Técnicos</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($ordens)) : ?>
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center">
                                    <div class="text-center text-gray-500">
                                        <i class="fas fa-folder-open text-3xl mb-3 text-gray-400"></i>
                                        <p class="mb-1 font-medium">Nenhuma ordem de serviço ativa encontrada.</p>
                                        <?php if (!empty($search)): ?>
                                            <p class="text-sm">Verifique os termos da sua busca ou tente novamente.</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php 
                            foreach ($ordens as $ordem):
                                $detalhesUrl = getDetalhesUrl($ordem);
                                $statusBadgeClass = getStatusBadgeClassTailwind($ordem['status']);
                            ?>
                                <tr class="hover:bg-blue-50 transition-colors duration-150">
                                    <td class="px-4 py-3 text-sm text-gray-900 font-medium"><?= formatOSNumber($ordem['id'] ?? null, $ordem['data_inicio'] ?? null) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($ordem['razao_social'] ?? 'N/A') ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($ordem['nome_fantasia'] ?? 'N/A') ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= formatDate($ordem['data_inicio'] ?? null) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= formatDate($ordem['data_fim'] ?? null) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= isset($ordem['tecnicos_nomes']) ? htmlspecialchars(implode(', ', $ordem['tecnicos_nomes'])) : 'N/A' ?></td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $statusBadgeClass ?>">
                                            <?= htmlspecialchars($ordem['status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <a href="<?= htmlspecialchars($detalhesUrl); ?>" class="text-blue-600 hover:text-blue-800 transition-colors duration-150 mx-1">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1) : ?>
                <nav aria-label="Paginação da lista de Ordens de Serviço" class="mt-6 flex justify-center">
                    <ul class="flex space-x-1">
                        <?php
                        $queryString = "&search=" . urlencode($search);
                        $currentPageUrl = "listar_ordens_tecnico.php?page=";

                        $maxVisiblePages = 5;
                        $startPage = max(1, $page - floor($maxVisiblePages / 2));
                        $endPage = min($total_pages, $page + floor($maxVisiblePages / 2));

                        if ($endPage - $startPage + 1 < $maxVisiblePages) {
                            if ($page < $total_pages / 2) {
                                $endPage = min($total_pages, $startPage + $maxVisiblePages - 1);
                            } else {
                                $startPage = max(1, $endPage - $maxVisiblePages + 1);
                            }
                        }
                        ?>

                        <li>
                            <a class="<?= ($page <= 1) ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:bg-blue-50' ?> px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 inline-flex items-center justify-center" 
                               href="<?= ($page <= 1) ? '#' : $currentPageUrl . '1' . $queryString ?>"
                               <?= ($page <= 1) ? 'aria-disabled="true"' : '' ?>>
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li>
                            <a class="<?= ($page <= 1) ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:bg-blue-50' ?> px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 inline-flex items-center justify-center" 
                               href="<?= ($page <= 1) ? '#' : $currentPageUrl . ($page - 1) . $queryString ?>"
                               <?= ($page <= 1) ? 'aria-disabled="true"' : '' ?>>
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <?php for ($i = $startPage; $i <= $endPage; $i++) : ?>
                            <li>
                                <a class="<?= ($i == $page) ? 'bg-blue-600 text-white' : 'text-blue-600 hover:bg-blue-50' ?> px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 inline-flex items-center justify-center" 
                                   href="<?= $currentPageUrl . $i . $queryString ?>"
                                   <?= ($i == $page) ? 'aria-current="page"' : '' ?>>
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li>
                            <a class="<?= ($page >= $total_pages) ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:bg-blue-50' ?> px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 inline-flex items-center justify-center" 
                               href="<?= ($page >= $total_pages) ? '#' : $currentPageUrl . ($page + 1) . $queryString ?>"
                               <?= ($page >= $total_pages) ? 'aria-disabled="true"' : '' ?>>
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li>
                            <a class="<?= ($page >= $total_pages) ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:bg-blue-50' ?> px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 inline-flex items-center justify-center" 
                               href="<?= ($page >= $total_pages) ? '#' : $currentPageUrl . $total_pages . $queryString ?>"
                               <?= ($page >= $total_pages) ? 'aria-disabled="true"' : '' ?>>
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
