<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';

$processo = new Processo($conn);

$search = isset($_GET['search']) ? $_GET['search'] : '';
$municipioUsuario = $_SESSION['user']['municipio'];
$isAdmin = $_SESSION['user']['nivel_acesso'] == 1;
$pendentes = isset($_GET['pendentes']) && $_GET['pendentes'] == '1';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$tipo_processo = isset($_GET['tipo_processo']) ? $_GET['tipo_processo'] : '';
$grupo_risco = isset($_GET['grupo_risco']) ? $_GET['grupo_risco'] : '';

// Consulta para buscar tipos distintos de processo
$queryTipos = "SELECT DISTINCT tipo_processo FROM processos WHERE tipo_processo IS NOT NULL ORDER BY tipo_processo ASC";
$resultTipos = $conn->query($queryTipos);

// Variáveis de paginação
$limit = 10; // Número de processos por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Buscar processos com base na busca, no município do usuário (se não for admin), pendentes, status e tipo_processo
$processos = $processo->searchProcessosPorMunicipioPaginacao($search, $municipioUsuario, $isAdmin, $limit, $offset, $pendentes, $status, $tipo_processo, $grupo_risco);

// Obter o total de processos para a paginação com todos os filtros
$totalProcessos = $processo->countProcessosPorMunicipio($search, $municipioUsuario, $isAdmin, $pendentes, $status, $tipo_processo, $grupo_risco);

function formatDate($date)
{
    $dateTime = new DateTime($date);
    return $dateTime->format('d/m/Y');
}
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">

        <div class="p-4">
            <form method="GET" action="listar_processos.php" class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
                    <!-- Campo de Busca -->
                    <div>
                        <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="search" name="search"
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-xs transition duration-150 ease-in-out"
                                placeholder="N° do Processo, Nome ou CNPJ"
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Campo de Status -->
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status"
                            class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                            <option value="">Todos os Status</option>
                            <option value="ATIVO" <?php echo ($status == 'ATIVO') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="ARQUIVADO" <?php echo ($status == 'ARQUIVADO') ? 'selected' : ''; ?>>
                                Arquivado</option>
                            <option value="PARADO" <?php echo ($status == 'PARADO') ? 'selected' : ''; ?>>Parado
                            </option>
                        </select>
                    </div>

                    <!-- Tipo de Processo -->
                    <div>
                        <label for="tipo_processo" class="block text-xs font-medium text-gray-700 mb-1">Tipo de
                            Processo</label>
                        <select id="tipo_processo" name="tipo_processo"
                            class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                            <option value="">Todos os Tipos</option>
                            <?php while ($row = $resultTipos->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($row['tipo_processo']); ?>"
                                <?php echo ($tipo_processo == $row['tipo_processo']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['tipo_processo']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Grupo de Risco -->
                    <div>
                        <label for="grupo_risco" class="block text-xs font-medium text-gray-700 mb-1">Grupo de
                            Risco</label>
                        <select id="grupo_risco" name="grupo_risco"
                            class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                            <option value="">Todos os Grupos</option>
                            <option value="3" <?php echo ($grupo_risco == '3') ? 'selected' : ''; ?>>Grupo 3 (Alto
                                Risco)</option>
                            <option value="2" <?php echo ($grupo_risco == '2') ? 'selected' : ''; ?>>Grupo 2 (Médio
                                Risco)</option>
                            <option value="1" <?php echo ($grupo_risco == '1') ? 'selected' : ''; ?>>Grupo 1 (Baixo
                                Risco)</option>
                            <option value="sem" <?php echo ($grupo_risco == 'sem') ? 'selected' : ''; ?>>Sem Grupo
                            </option>
                        </select>
                    </div>

                    <!-- Botão de Busca -->
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                    </div>
                </div>

                <!-- Botão para Processos Pendentes -->
                <div class="flex">
                    <button type="submit" name="pendentes" value="1"
                        style="background-color: #e74c3c; color: white; border: 1px solid #c0392b;"
                        class="inline-flex items-center px-3 py-1.5 text-xs leading-4 font-medium rounded-md transition-colors duration-200">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Processos com Documentação Pendente
                    </button>
                </div>
            </form>

            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-gray-900">Lista de Processos</h3>
                <span class="text-xs text-gray-500"><?php echo $totalProcessos; ?> processos encontrados</span>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                N° do Processo</th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo</th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Data de Abertura</th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estabelecimento</th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                CNPJ/CPF</th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Grupos de Risco</th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Arquivos</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($processos)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center">
                                <div class="text-center text-gray-500">
                                    <i class="fas fa-info-circle text-3xl mb-3 text-gray-400"></i>
                                    <p class="mb-1 font-medium">Nenhum processo encontrado.</p>
                                    <?php if (!empty($search) || !empty($status) || !empty($tipo_processo) || !empty($grupo_risco) || $pendentes): ?>
                                    <p class="text-xs">Tente modificar os filtros de busca.</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($processos as $proc) : ?>
                        <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            onclick="window.location='documentos.php?processo_id=<?php echo $proc['id']; ?>&id=<?php echo $proc['estabelecimento_id']; ?>';">
                            <td class="px-4 py-2 text-xs text-gray-900 font-medium">
                                <?php echo htmlspecialchars($proc['numero_processo']); ?></td>
                            <td class="px-4 py-2 text-xs text-gray-900">
                                <?php echo htmlspecialchars($proc['tipo_processo']); ?></td>
                            <td class="px-4 py-2 text-xs text-gray-900">
                                <?php echo htmlspecialchars(formatDate($proc['data_abertura'])); ?></td>
                            <td class="px-4 py-2 text-xs text-gray-900">
                                <?php echo htmlspecialchars($proc['nome_fantasia']); ?></td>
                            <td class="px-4 py-2 text-xs text-gray-900">
                                <?php
                                        if (!empty($proc['cpf'])) {
                                            echo htmlspecialchars($proc['cpf']);
                                        } else {
                                            echo htmlspecialchars($proc['cnpj']);
                                        }
                                        ?>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <?php 
                                        $statusClass = "bg-gray-100 text-gray-800";
                                        if (strtoupper($proc['status']) == 'ATIVO') {
                                            $statusClass = "bg-green-100 text-green-800";
                                        } elseif (strtoupper($proc['status']) == 'ARQUIVADO') {
                                            $statusClass = "bg-blue-100 text-blue-800";
                                        } elseif (strtoupper($proc['status']) == 'PARADO') {
                                            $statusClass = "bg-red-100 text-red-800";
                                        }
                                        ?>
                                <span
                                    class="px-2 inline-flex text-2xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($proc['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <?php if (!empty($proc['grupos_risco'])) : ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                                $grupos = explode(', ', $proc['grupos_risco']);
                                                foreach ($grupos as $grupo) : 
                                                    $badgeClass = "bg-gray-100 text-gray-800";
                                                    if (stripos($grupo, "Grupo 3") !== false) {
                                                        $badgeClass = "bg-red-100 text-red-800";
                                                    } elseif (stripos($grupo, "Grupo 2") !== false) {
                                                        $badgeClass = "bg-yellow-100 text-yellow-800";
                                                    } elseif (stripos($grupo, "Grupo 1") !== false) {
                                                        $badgeClass = "bg-blue-100 text-blue-800";
                                                    }
                                                ?>
                                    <span
                                        class="px-2 py-0.5 text-2xs font-medium rounded-full <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($grupo); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else : ?>
                                <span class="text-gray-500 text-2xs italic">Não classificado</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <?php if ($proc['documentos_pendentes'] > 0) : ?>
                                <span class="px-2 py-0.5 text-2xs font-medium rounded-full bg-red-100 text-amber-800">
                                    <?php echo $proc['documentos_pendentes']; ?> Pend.
                                </span>
                                <?php else : ?>
                                <span class="px-2 py-0.5 text-2xs font-medium rounded-full bg-green-100 text-green-800">
                                    0 Pend.
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Links de paginação -->
            <?php
            $totalPages = ceil($totalProcessos / $limit);
            if ($totalPages > 1):
                // Define a quantidade de links antes e depois da página atual
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($totalPages, $page + $range);
                
                // Preparando a query string para os links
                $queryParams = http_build_query([
                    'search' => $search,
                    'pendentes' => $pendentes ? '1' : '0',
                    'status' => $status,
                    'tipo_processo' => $tipo_processo,
                    'grupo_risco' => $grupo_risco
                ]);
            ?>
            <nav aria-label="Paginação da lista de processos" class="mt-6 flex justify-center">
                <ul class="flex space-x-1">
                    <!-- Link para a página anterior -->
                    <li>
                        <a class="<?= ($page <= 1) ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:bg-blue-50' ?> px-3 py-1.5 rounded-md text-xs font-medium transition-colors duration-150 inline-flex items-center justify-center"
                            href="<?= ($page <= 1) ? '#' : "?page=" . ($page - 1) . "&" . $queryParams ?>"
                            aria-label="Anterior">
                            <i class="fas fa-chevron-left text-xs"></i>
                        </a>
                    </li>

                    <!-- Primeiro link se o início não for 1 -->
                    <?php if ($start > 1): ?>
                    <li>
                        <a class="px-3 py-1.5 rounded-md text-xs font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors duration-150"
                            href="?page=1&<?= $queryParams ?>">
                            1
                        </a>
                    </li>
                    <?php if ($start > 2): ?>
                    <li>
                        <span class="px-3 py-1.5 text-xs text-gray-500">...</span>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>

                    <!-- Links do intervalo -->
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li>
                        <a class="px-3 py-1.5 rounded-md text-xs font-medium <?= ($i == $page) ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600' ?> transition-colors duration-150"
                            href="?page=<?= $i ?>&<?= $queryParams ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <!-- Último link se o final não for o total de páginas -->
                    <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                    <li>
                        <span class="px-3 py-1.5 text-xs text-gray-500">...</span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a class="px-3 py-1.5 rounded-md text-xs font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors duration-150"
                            href="?page=<?= $totalPages ?>&<?= $queryParams ?>">
                            <?= $totalPages ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Link para a próxima página -->
                    <li>
                        <a class="<?= ($page >= $totalPages) ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:bg-blue-50' ?> px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 inline-flex items-center justify-center"
                            href="<?= ($page >= $totalPages) ? '#' : "?page=" . ($page + 1) . "&" . $queryParams ?>"
                            aria-label="Próxima">
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>