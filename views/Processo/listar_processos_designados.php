<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1,3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/ProcessoResponsavel.php';

$processoResponsavel = new ProcessoResponsavel($conn);

// Variáveis de busca
$searchUser = isset($_GET['search_user']) ? $_GET['search_user'] : '';
$searchStatus = isset($_GET['search_status']) ? $_GET['search_status'] : '';

// Variáveis de paginação
$limit = 10; // Número de registros por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Buscar processos com paginação
$processosDesignados = $processoResponsavel->getProcessosDesignados($searchUser, $searchStatus, $limit, $offset);

// Contar total de registros para paginação
$totalProcessos = $processoResponsavel->countProcessosDesignados($searchUser, $searchStatus);
$totalPages = ceil($totalProcessos / $limit);

include '../header.php';

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pendente':
            return 'badge bg-warning text-dark';
        case 'resolvido':
            return 'badge bg-success text-light';
        default:
            return 'badge bg-secondary';
    }
}

function formatarData($data) {
    if (empty($data)) return '-';
    $dt = new DateTime($data);
    return $dt->format('d/m/Y');
}
?>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Processos Designados</h5>
                        <span class="badge bg-light text-primary"><?php echo $totalProcessos; ?> processo(s) encontrado(s)</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="listar_processos_designados.php" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="search_user" placeholder="Pesquisar por usuário" value="<?php echo htmlspecialchars($searchUser); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-filter"></i></span>
                                    <select class="form-select" name="search_status">
                                        <option value="">Todos os Status</option>
                                        <option value="pendente" <?php echo $searchStatus == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="resolvido" <?php echo $searchStatus == 'resolvido' ? 'selected' : ''; ?>>Resolvido</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="fas fa-search me-1"></i> Pesquisar
                                    </button>
                                    <?php if (!empty($searchUser) || !empty($searchStatus)): ?>
                                        <a href="listar_processos_designados.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-eraser"></i> Limpar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($processosDesignados)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>Nenhum processo designado encontrado com os filtros atuais.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col"><i class="fas fa-hashtag me-1"></i>Número do Processo</th>
                                        <th scope="col"><i class="fas fa-user me-1"></i>Usuário</th>
                                        <th scope="col"><i class="fas fa-align-left me-1"></i>Descrição</th>
                                        <th scope="col"><i class="fas fa-info-circle me-1"></i>Status</th>
                                        <th scope="col" class="text-center"><i class="fas fa-cog me-1"></i>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($processosDesignados as $processo) : ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($processo['numero_processo']); ?></td>
                                            <td><?php echo htmlspecialchars($processo['nome_completo']); ?></td>
                                            <td>
                                                <?php 
                                                $descricao = htmlspecialchars($processo['descricao']); 
                                                echo (strlen($descricao) > 50) ? substr($descricao, 0, 50) . '...' : $descricao;
                                                ?>
                                            </td>
                                            <td>
                                                <span class="<?php echo getStatusBadgeClass($processo['status']); ?> px-2 py-1">
                                                    <i class="fas <?php echo ($processo['status'] == 'resolvido') ? 'fa-check-circle' : 'fa-clock'; ?> me-1"></i>
                                                    <?php echo ucfirst(htmlspecialchars($processo['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="documentos.php?processo_id=<?php echo $processo['processo_id']; ?>&id=<?php echo $processo['estabelecimento_id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver Processo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Navegação de página" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1&search_user=<?php echo urlencode($searchUser); ?>&search_status=<?php echo urlencode($searchStatus); ?>" aria-label="Primeira">
                                                <span aria-hidden="true">&laquo;&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search_user=<?php echo urlencode($searchUser); ?>&search_status=<?php echo urlencode($searchStatus); ?>" aria-label="Anterior">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">&laquo;&laquo;</span>
                                        </li>
                                        <li class="page-item disabled">
                                            <span class="page-link">&laquo;</span>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $startPage + 4);
                                    if ($endPage - $startPage < 4 && $startPage > 1) {
                                        $startPage = max(1, $endPage - 4);
                                    }

                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search_user=<?php echo urlencode($searchUser); ?>&search_status=<?php echo urlencode($searchStatus); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search_user=<?php echo urlencode($searchUser); ?>&search_status=<?php echo urlencode($searchStatus); ?>" aria-label="Próxima">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?>&search_user=<?php echo urlencode($searchUser); ?>&search_status=<?php echo urlencode($searchStatus); ?>" aria-label="Última">
                                                <span aria-hidden="true">&raquo;&raquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">&raquo;</span>
                                        </li>
                                        <li class="page-item disabled">
                                            <span class="page-link">&raquo;&raquo;</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center text-muted small mt-2">
                                Página <?php echo $page; ?> de <?php echo $totalPages; ?> (<?php echo $totalProcessos; ?> registros no total)
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
    .table th {
        font-size: 0.85rem;
        font-weight: 600;
    }
    .table td {
        vertical-align: middle;
        font-size: 0.9rem;
    }
    .badge {
        font-size: 0.8rem;
        font-weight: 500;
    }
    .pagination .page-link {
        padding: 0.375rem 0.75rem;
    }
    .pagination .active .page-link {
        background-color: #007bff;
        border-color: #007bff;
    }
</style>

<?php include '../footer.php'; ?>