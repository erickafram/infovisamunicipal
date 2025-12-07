<?php
session_start();
include '../header.php'; // Assume que header.php não contém Bootstrap ou conflitos

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php'; // Assume que este modelo não depende de Bootstrap

$ordemServico = new OrdemServico($conn);

$usuarioLogado = $_SESSION['user'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; // Número de itens por página
$offset = ($page - 1) * $limit;

// Busca o total de ordens e as ordens da página atual
$total_ordens = $ordemServico->getOrdensCountByMunicipio($usuarioLogado['municipio'], $search);
$total_pages = ceil($total_ordens / $limit);
$ordens = $ordemServico->getOrdensByMunicipio($usuarioLogado['municipio'], $search, $limit, $offset);

/**
 * Formata uma data para o padrão brasileiro (dd/mm/yyyy).
 *
 * @param string|null $date A data no formato YYYY-MM-DD ou compatível com DateTime.
 * @return string A data formatada ou 'N/A' se a data for inválida ou vazia.
 */
function formatDate($date)
{
    if (empty($date)) return 'N/A';
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('d/m/Y');
    } catch (Exception $e) {
        // Log do erro, se necessário: error_log("Erro ao formatar data: " . $e->getMessage());
        return 'Data inválida';
    }
}

/**
 * Retorna as classes CSS do Tailwind para o badge de status.
 *
 * @param string|null $status O status da ordem de serviço.
 * @return string As classes Tailwind correspondentes.
 */
function getStatusBadgeClassTailwind($status)
{
    $status = strtolower($status ?? ''); // Converte para minúsculas e trata nulo
    switch ($status) {
        case 'ativa':
        case 'em andamento':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'finalizada':
        case 'concluída':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'cancelada':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'pendente':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; // Classe padrão
    }
}

/**
 * Determina a URL de detalhes da ordem com base na existência de estabelecimento_id e processo_id.
 *
 * @param array $ordem Os dados da ordem de serviço.
 * @return string A URL para a página de detalhes.
 */
function getDetalhesUrl($ordem)
{
    // Verifica se os IDs necessários estão presentes e não são vazios/nulos
    $hasEstabelecimento = !empty($ordem['estabelecimento_id']);
    $hasProcesso = !empty($ordem['processo_id']);

    if ($hasEstabelecimento && $hasProcesso) {
        // URL para ordens com estabelecimento e processo
        return 'detalhes_ordem.php?id=' . urlencode($ordem['id']);
    } else {
        // URL para ordens sem estabelecimento ou sem processo
        return 'detalhes_ordem_sem_estabelecimento.php?id=' . urlencode($ordem['id']);
    }
}

/**
 * Formata o número da OS concatenando o ID com o ano da data de início.
 *
 * @param int|string|null $id O ID da ordem de serviço.
 * @param string|null $date A data de início da OS.
 * @return string O número formatado (ID.ANO) ou 'N/A'.
 */
function formatOSNumber($id, $date)
{
    if (empty($id) || empty($date)) return 'N/A';
    try {
        // Extrai o ano da data de início
        $year = date('Y', strtotime($date));
        return htmlspecialchars($id . '.' . $year);
    } catch (Exception $e) {
        // Em caso de erro na data, retorna apenas o ID.
        // Log do erro, se necessário: error_log("Erro ao formatar número da OS: " . $e->getMessage());
        return htmlspecialchars($id) . '.????';
    }
}

?>

<div class="container mx-auto px-4 py-6">
    <div
        class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden border border-gray-200 dark:border-gray-700">

        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="listar_ordens.php" aria-label="Formulário de busca de Ordens de Serviço">
                <div class="flex flex-col sm:flex-row items-stretch gap-2">
                    <div class="relative flex-grow">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 dark:text-gray-500 text-xs"></i>
                        </div>
                        <input type="text"
                            class="block w-full pl-8 pr-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs transition duration-150 ease-in-out"
                            name="search" placeholder="Buscar por número, razão social, nome fantasia..."
                            value="<?= htmlspecialchars($search); ?>" aria-label="Campo de busca">
                    </div>
                    <button type="submit"
                        class="px-3 py-1.5 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200 flex-shrink-0"
                        id="button-search">
                        <i class="fas fa-search mr-1 sm:hidden text-xs"></i> <span
                            class="hidden sm:inline">Buscar</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="p-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 gap-2">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lista de Ordens de Serviço</h3>
                    <span class="text-xs text-gray-600 dark:text-gray-400"><?= $total_ordens; ?> ordem(ns)
                        encontrada(s)</span>
                </div>
                <?php if (in_array($_SESSION['user']['nivel_acesso'], [1, 3])) : ?>
                <a href="../OrdemServico/nova_ordem_servico.php"
                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-md text-xs font-medium transition-colors duration-200 flex items-center shadow-sm whitespace-nowrap">
                    <i class="fas fa-plus mr-1 text-xs"></i> Nova OS (Sem Estab.) </a>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['error'])) : ?>
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?= htmlspecialchars($_GET['error']); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Número</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Razão Social</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Nome Fantasia</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Data Início</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Data Fim</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Técnicos</th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($ordens)) : ?>
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center">
                                <div class="text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="mt-2 text-xs font-medium">Nenhuma ordem de serviço encontrada.</p>
                                    <?php if (!empty($search)): ?>
                                    <p class="mt-1 text-xs">Verifique os termos da sua busca ou tente novamente.</p>
                                    <?php else: ?>
                                    <p class="mt-1 text-xs">Não há ordens de serviço cadastradas para este município.
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php else : ?>
                        <?php
                            // Loop para exibir cada ordem de serviço
                            foreach ($ordens as $ordem):
                                $detalhesUrl = getDetalhesUrl($ordem);
                                $statusBadgeClass = getStatusBadgeClassTailwind($ordem['status'] ?? null); // Usa a função Tailwind
                            ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 cursor-pointer"
                            onclick="window.location.href='<?= htmlspecialchars($detalhesUrl); ?>'"
                            title="Clique para ver detalhes">
                            <td
                                class="px-3 py-2 whitespace-nowrap text-xs text-gray-900 dark:text-gray-100 font-medium">
                                <?= formatOSNumber($ordem['id'] ?? null, $ordem['data_inicio'] ?? null) ?></td>
                            <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <?= htmlspecialchars($ordem['razao_social'] ?? 'N/A') ?></td>
                            <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <?= htmlspecialchars($ordem['nome_fantasia'] ?? 'N/A') ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                                <?= formatDate($ordem['data_inicio'] ?? null) ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                                <?= formatDate($ordem['data_fim'] ?? null) ?></td>
                            <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <?php
                                        // Exibe os nomes dos técnicos ou 'N/A'
                                        $tecnicos = $ordem['tecnicos_nomes'] ?? []; // Garante que é um array
                                        if (is_array($tecnicos) && !empty(array_filter($tecnicos))) {
                                            echo htmlspecialchars(implode(', ', $tecnicos));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full <?= $statusBadgeClass ?>">
                                    <?= htmlspecialchars(ucfirst($ordem['status'] ?? 'N/A')) // Capitaliza a primeira letra ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1) : ?>
            <nav aria-label="Paginação da lista de Ordens de Serviço" class="mt-4 flex justify-center">
                <ul class="inline-flex items-center -space-x-px rounded-md shadow-sm text-xs">
                    <?php
                        $queryString = "&search=" . urlencode($search); // Mantém a busca na paginação
                        $currentPageUrl = "listar_ordens.php?page=";

                        // Lógica para exibir links de paginação (simplificada para clareza)
                        $maxVisiblePages = 5; // Máximo de botões de página visíveis (excluindo anterior/próximo)
                        $startPage = max(1, $page - floor($maxVisiblePages / 2));
                        $endPage = min($total_pages, $startPage + $maxVisiblePages - 1);

                        // Ajusta o início se o fim estiver no limite
                        if ($endPage == $total_pages) {
                            $startPage = max(1, $total_pages - $maxVisiblePages + 1);
                        }
                         // Ajusta o fim se o início for 1
                        if ($startPage == 1) {
                             $endPage = min($total_pages, $maxVisiblePages);
                        }

                        ?>

                    <li>
                        <a href="<?= ($page <= 1) ? '#' : $currentPageUrl . ($page - 1) . $queryString ?>"
                            class="<?= ($page <= 1) ? 'text-gray-400 cursor-not-allowed dark:text-gray-500' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' ?> relative inline-flex items-center px-2 py-1.5 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium transition-colors duration-150"
                            aria-label="Anterior" <?= ($page <= 1) ? 'aria-disabled="true"' : '' ?>>
                            <span class="sr-only">Anterior</span>
                            <i class="fas fa-chevron-left h-4 w-4" aria-hidden="true"></i> </a>
                    </li>

                    <?php if ($startPage > 1): ?>
                    <li>
                        <a href="<?= $currentPageUrl . '1' . $queryString ?>"
                            class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                    <li>
                        <span
                            class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-400">...</span>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++) : ?>
                    <li>
                        <a href="<?= $currentPageUrl . $i . $queryString ?>"
                            class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium transition-colors duration-150 <?= ($i == $page) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600 dark:bg-gray-700 dark:border-blue-500 dark:text-white' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' ?>"
                            <?= ($i == $page) ? 'aria-current="page"' : '' ?>>
                            <?= $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($endPage < $total_pages): ?>
                    <?php if ($endPage < $total_pages - 1): ?>
                    <li>
                        <span
                            class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-400">...</span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?= $currentPageUrl . $total_pages . $queryString ?>"
                            class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"><?= $total_pages ?></a>
                    </li>
                    <?php endif; ?>

                    <li>
                        <a href="<?= ($page >= $total_pages) ? '#' : $currentPageUrl . ($page + 1) . $queryString ?>"
                            class="<?= ($page >= $total_pages) ? 'text-gray-400 cursor-not-allowed dark:text-gray-500' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' ?> relative inline-flex items-center px-2 py-1.5 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium transition-colors duration-150"
                            aria-label="Próxima" <?= ($page >= $total_pages) ? 'aria-disabled="true"' : '' ?>>
                            <span class="sr-only">Próxima</span>
                            <i class="fas fa-chevron-right h-4 w-4" aria-hidden="true"></i> </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        </div>
    </div>
</div> <?php include '../footer.php'; // Assume que footer.php não contém Bootstrap ou conflitos ?>