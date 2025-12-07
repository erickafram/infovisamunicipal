<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php'; // Assume que este modelo não depende de Bootstrap

$estabelecimento = new Estabelecimento($conn);

// Parâmetros de busca e paginação
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10; // Itens por página
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Dados do usuário logado
$userMunicipio = $_SESSION['user']['municipio'];
$nivel_acesso = $_SESSION['user']['nivel_acesso'];

// Busca o total de estabelecimentos e os estabelecimentos da página atual
$totalEstabelecimentos = $estabelecimento->countEstabelecimentos($search, $userMunicipio, $nivel_acesso);
$totalPages = ceil($totalEstabelecimentos / $limit);
$estabelecimentos = $estabelecimento->searchEstabelecimentos($search, $limit, $offset, $userMunicipio, $nivel_acesso);

// Obter IDs de todos os estabelecimentos na página atual
$estabelecimentoIds = array_map(function($e) { return $e['id']; }, $estabelecimentos);

// Buscar grupos de risco para todos os estabelecimentos da página atual
$gruposRiscoPorEstabelecimento = [];
if (!empty($estabelecimentoIds)) {
    try {
        $gruposRiscoPorEstabelecimento = $estabelecimento->getGruposRiscoParaListagem($estabelecimentoIds);
    } catch (Exception $e) {
        error_log("Erro ao obter grupos de risco: " . $e->getMessage());
        // Não exibe o erro para o usuário, apenas segue com array vazio
    }
}

/**
 * Retorna as classes CSS do Tailwind para o badge de situação cadastral.
 *
 * @param string|null $situacao A descrição da situação cadastral.
 * @return string As classes Tailwind correspondentes.
 */
function getSituacaoBadgeClass($situacao)
{
    $situacao_upper = strtoupper($situacao ?? ''); // Converte para maiúsculas e trata nulo

    if ($situacao_upper == 'ATIVA') {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
    } elseif (in_array($situacao_upper, ['SUSPENSA', 'BAIXADA', 'INAPTA', 'NULA'])) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
    } elseif (in_array($situacao_upper, ['PENDENTE', 'EM ANALISE'])) { // Corrigido 'EM ANALISE'
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
    }
    // Classe padrão para situações desconhecidas ou N/A
    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

/**
 * Retorna as classes CSS do Tailwind para o badge de grupo de risco.
 *
 * @param string $grupo Nome do grupo de risco
 * @return string As classes Tailwind correspondentes
 */
function getGrupoRiscoBadgeClass($grupo) 
{
    if (strpos($grupo, '1') !== false) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
    } elseif (strpos($grupo, '2') !== false) {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
    } elseif (strpos($grupo, '3') !== false) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

/**
 * Determina a URL de detalhes com base no tipo de pessoa (física ou jurídica).
 *
 * @param array $estab Os dados do estabelecimento/pessoa.
 * @return string A URL para a página de detalhes.
 */
function getDetailUrl($estab)
{
    if (empty($estab['id'])) {
        return '#'; // Retorna um link morto se o ID não estiver disponível
    }

    return ($estab['tipo_pessoa'] ?? 'juridica') === 'fisica' // Assume jurídica se tipo_pessoa não estiver definido
        ? 'detalhes_pessoa_fisica.php?id=' . urlencode($estab['id'])
        : 'detalhes_estabelecimento.php?id=' . urlencode($estab['id']);
}

?>

<div class="container mx-auto px-4 py-6">

    <div
        class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden border border-gray-200 dark:border-gray-700">

        <div
            class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex justify-between items-center">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Estabelecimentos</h2>
            <?php if (in_array($_SESSION['user']['nivel_acesso'], [1, 3])) : ?>
            <div class="flex space-x-2">
                <a href="../Estabelecimento/cadastro_estabelecimento.php"
                    class="bg-blue-600 text-white hover:bg-blue-700 px-3 py-1.5 rounded-md text-xs font-medium flex items-center shadow-sm transition-colors">
                    <i class="fas fa-plus mr-1 text-xs"></i> Cad. Pessoa Jurídica
                </a>
                <a href="../Company/cadastro_pessoa_fisica.php"
                    class="bg-green-600 text-white hover:bg-green-700 px-3 py-1.5 rounded-md text-xs font-medium flex items-center shadow-sm transition-colors">
                    <i class="fas fa-plus mr-1 text-xs"></i> Cad. Pessoa Física
                </a>
            </div>

            <?php endif; ?>
        </div>

        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="listar_estabelecimentos.php"
                aria-label="Formulário de busca de Estabelecimentos">
                <div class="flex flex-col sm:flex-row items-stretch gap-2">
                    <div class="relative flex-grow">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 dark:text-gray-500 text-xs"></i>
                        </div>
                        <input type="text"
                            class="block w-full pl-8 pr-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs transition duration-150 ease-in-out"
                            name="search"
                            placeholder="Buscar por CNPJ, CPF, Razão Social, Nome Fantasia ou Município..."
                            value="<?= htmlspecialchars($search); ?>" aria-label="Campo de busca">
                    </div>
                    <button type="submit"
                        class="px-3 py-1.5 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200 flex-shrink-0"
                        id="button-search">
                        <i class="fas fa-search mr-1 sm:hidden text-xs"></i>
                        <span class="hidden sm:inline">Buscar</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="p-4">
            <div class="mb-3">
                <span class="text-xs text-gray-600 dark:text-gray-400">
                    <?php if ($totalEstabelecimentos > 0): ?>
                    Mostrando <?= $offset + 1 ?> a <?= min($offset + $limit, $totalEstabelecimentos) ?> de
                    <?= $totalEstabelecimentos ?> estabelecimento(s) encontrado(s).
                    <?php else: ?>
                    <?= $totalEstabelecimentos ?> estabelecimento(s) encontrado(s).
                    <?php endif; ?>
                </span>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                CNPJ/CPF</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Razão Social / Nome</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Nome Fantasia</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Município</th>
                            <th scope="col"
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Grupo(s) de Risco</th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Situação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($estabelecimentos)) : ?>
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center">
                                <div class="text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    <p class="mt-2 text-xs font-medium">Nenhum estabelecimento encontrado.</p>
                                    <?php if (!empty($search)): ?>
                                    <p class="mt-1 text-xs">Verifique os termos da sua busca ou tente novamente.</p>
                                    <?php else: ?>
                                    <p class="mt-1 text-xs">Não há estabelecimentos cadastrados que correspondam aos
                                        critérios.</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php else : ?>
                        <?php
                            // Loop para exibir cada estabelecimento
                            foreach ($estabelecimentos as $estab):
                                $situacao = $estab['descricao_situacao_cadastral'] ?? 'N/A';
                                $badge_class = getSituacaoBadgeClass($situacao); // Usa a função refatorada
                                $detail_url = getDetailUrl($estab); // Usa a função refatorada
                            ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 cursor-pointer"
                            onclick="window.location.href='<?= htmlspecialchars($detail_url); ?>'"
                            title="Clique para ver detalhes">
                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                                <?= htmlspecialchars($estab['tipo_pessoa'] === 'fisica' ? ($estab['cpf'] ?? 'N/A') : ($estab['cnpj'] ?? 'N/A')); ?>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-900 dark:text-gray-100 font-medium">
                                <?= htmlspecialchars($estab['razao_social'] ?? $estab['nome'] ?? 'N/A') // Usa nome se for pessoa física ?>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <?= htmlspecialchars($estab['nome_fantasia'] ?? '-') // Mostra '-' se não houver nome fantasia ?>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <?= htmlspecialchars($estab['municipio'] ?? 'N/A') ?>
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <?php 
                                if (isset($gruposRiscoPorEstabelecimento[$estab['id']]) && !empty($gruposRiscoPorEstabelecimento[$estab['id']])) {
                                    foreach ($gruposRiscoPorEstabelecimento[$estab['id']] as $grupo) {
                                        $badge_class = getGrupoRiscoBadgeClass($grupo);
                                        echo '<span class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full ' . $badge_class . ' mr-1 mb-1">' . 
                                             htmlspecialchars($grupo) . '</span>';
                                    }
                                } else {
                                    echo '<span class="text-gray-400">Não classificado</span>';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full <?= $badge_class ?>">
                                    <?= htmlspecialchars(ucfirst(strtolower($situacao))) // Formata para 'Ativa', 'Baixada', etc. ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1) : ?>
            <nav aria-label="Paginação da lista de estabelecimentos" class="mt-4 flex justify-center">
                <ul class="inline-flex items-center -space-x-px rounded-md shadow-sm text-xs">
                    <?php
                        $queryString = "&search=" . urlencode($search); // Mantém a busca na paginação
                        $currentPageUrl = "listar_estabelecimentos.php?page=";

                        // Lógica para exibir links de paginação
                        $maxVisiblePages = 5;
                        $startPage = max(1, $page - floor($maxVisiblePages / 2));
                        $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);

                        if ($endPage == $totalPages) {
                            $startPage = max(1, $totalPages - $maxVisiblePages + 1);
                        }
                        if ($startPage == 1) {
                             $endPage = min($totalPages, $maxVisiblePages);
                        }
                        ?>

                    <li>
                        <a href="<?= ($page <= 1) ? '#' : $currentPageUrl . ($page - 1) . $queryString ?>"
                            class="<?= ($page <= 1) ? 'text-gray-400 cursor-not-allowed dark:text-gray-500' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' ?> relative inline-flex items-center px-2 py-1.5 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium transition-colors duration-150"
                            aria-label="Anterior" <?= ($page <= 1) ? 'aria-disabled="true"' : '' ?>>
                            <span class="sr-only">Anterior</span>
                            <i class="fas fa-chevron-left h-4 w-4" aria-hidden="true"></i>
                        </a>
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

                    <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                    <li>
                        <span
                            class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-400">...</span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?= $currentPageUrl . $totalPages . $queryString ?>"
                            class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"><?= $totalPages ?></a>
                    </li>
                    <?php endif; ?>

                    <li>
                        <a href="<?= ($page >= $totalPages) ? '#' : $currentPageUrl . ($page + 1) . $queryString ?>"
                            class="<?= ($page >= $totalPages) ? 'text-gray-400 cursor-not-allowed dark:text-gray-500' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' ?> relative inline-flex items-center px-2 py-1.5 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium transition-colors duration-150"
                            aria-label="Próxima" <?= ($page >= $totalPages) ? 'aria-disabled="true"' : '' ?>>
                            <span class="sr-only">Próxima</span>
                            <i class="fas fa-chevron-right h-4 w-4" aria-hidden="true"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        </div>
    </div>
</div> <?php
// Fechar a conexão apenas se ela foi aberta com sucesso
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include '../footer.php'; // Assume que footer.php não contém Bootstrap ou conflitos
?>