<?php
session_start();
// Start output buffering to prevent "headers already sent" error
ob_start();

include '../header.php'; // Inclui o header com Bootstrap 5.3 e outras configurações necessárias
require_once '../../conf/database.php';

// Função para extrair nome do arquivo de screenshot da descrição e criar link
function processarDescricaoComScreenshot($descricao) {
    // Procura padrão "Captura de Tela: screenshot_XXXXX.png" (case insensitive)
    if (preg_match('/Captura de Tela:\s*(screenshot_[a-z0-9]+\.png)/i', $descricao, $matches)) {
        $nomeArquivo = $matches[1];
        
        // Em vez de redirecionar para uma nova página, usaremos um modal
        $novaDescricao = preg_replace(
            '/Captura de Tela:\s*(screenshot_[a-z0-9]+\.png)/i',
            "Captura de Tela: <a href=\"javascript:void(0)\" class=\"text-primary fw-bold show-screenshot\" data-screenshot=\"$nomeArquivo\">
                <i class=\"fas fa-image me-1\"></i>Ver imagem</a>",
            $descricao
        );
        
        return $novaDescricao;
    }
    
    return $descricao;
}

// Função para extrair URL da página da descrição
function processarDescricaoComURL($descricao) {
    // Procura padrão "URL da Página: https://..."
    if (preg_match('/URL da Página: (https?:\/\/[^\s\n]+)/i', $descricao, $matches)) {
        $url = $matches[1];
        
        // Substitui a referência por um link clicável
        $novaDescricao = str_replace(
            $matches[0],
            "URL da Página: <a href=\"$url\" target=\"_blank\" class=\"text-primary\">
                <i class=\"fas fa-external-link-alt me-1\"></i>$url</a>",
            $descricao
        );
        
        return $novaDescricao;
    }
    
    return $descricao;
}

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

// Processa o envio de resposta - Move this up before any potential output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_relato'])) {
    $relato_id = $_POST['relato_id'];
    $resposta = $_POST['resposta'];
    $admin_id = $_SESSION['user']['id'];
    
    $updateStmt = $conn->prepare("UPDATE relatos_usuarios SET resposta = ?, data_resposta = NOW(), admin_id = ? WHERE id = ?");
    $updateStmt->bind_param("sii", $resposta, $admin_id, $relato_id);
    
    if ($updateStmt->execute()) {
        $_SESSION['mensagem'] = [
            'tipo' => 'success',
            'texto' => 'Resposta enviada com sucesso!'
        ];
    } else {
        $_SESSION['mensagem'] = [
            'tipo' => 'danger',
            'texto' => 'Erro ao enviar resposta: ' . $conn->error
        ];
    }
    
    // Redireciona para evitar reenvio do formulário
    header("Location: listar_relatos.php");
    exit();
}

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Itens por página
$offset = ($page - 1) * $limit;

// Filtros
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroOrigem = isset($_GET['origem']) ? $_GET['origem'] : '';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';

// Construção da query com filtros
$whereConditions = [];
$params = [];
$types = '';

if (!empty($filtroTipo)) {
    $whereConditions[] = "r.tipo = ?";
    $params[] = $filtroTipo;
    $types .= 's';
}

if (!empty($filtroStatus)) {
    if ($filtroStatus == 'RESPONDIDO') {
        $whereConditions[] = "r.resposta IS NOT NULL";
    } else {
        $whereConditions[] = "r.resposta IS NULL";
    }
}

if (!empty($filtroOrigem)) {
    if ($filtroOrigem == 'INTERNO') {
        $whereConditions[] = "(r.origem = 'INTERNO' OR (r.origem IS NULL AND r.usuario_id IS NOT NULL))";
    } else if ($filtroOrigem == 'EXTERNO') {
        $whereConditions[] = "(r.origem = 'EXTERNO' OR (r.origem IS NULL AND r.usuario_externo_id IS NOT NULL AND r.usuario_id IS NULL))";
    }
}

if (!empty($busca)) {
    $whereConditions[] = "(r.descricao LIKE ? OR u.nome_completo LIKE ? OR ui.nome LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $types .= 'sss';
}

// Verifica se as colunas de resposta existem
$columnsExist = false;
$checkColumnsStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'resposta'");
$checkColumnsStmt->execute();
$columnsExist = ($checkColumnsStmt->get_result()->num_rows > 0);

// Verifica se a coluna origem existe
$origemColumnExist = false;
$checkOrigemColumnStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'origem'");
$checkOrigemColumnStmt->execute();
$origemColumnExist = ($checkOrigemColumnStmt->get_result()->num_rows > 0);

// Verifica se a coluna usuario_id existe
$usuarioIdColumnExist = false;
$checkUsuarioIdColumnStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'usuario_id'");
$checkUsuarioIdColumnStmt->execute();
$usuarioIdColumnExist = ($checkUsuarioIdColumnStmt->get_result()->num_rows > 0);

// Query base adaptada para considerar se as colunas existem
if ($columnsExist) {
    if ($usuarioIdColumnExist) {
        $query = "SELECT r.id, r.tipo, r.descricao, r.data_criacao, r.resposta, r.data_resposta, 
                 CASE
                    WHEN r.usuario_externo_id IS NOT NULL THEN ue.nome_completo
                    WHEN r.usuario_id IS NOT NULL THEN u.nome_completo
                    ELSE 'Usuário Desconhecido'
                 END as nome_usuario,
                 CASE
                    WHEN r.usuario_externo_id IS NOT NULL THEN ue.email
                    WHEN r.usuario_id IS NOT NULL THEN u.email
                    ELSE ''
                 END as email_usuario,
                 CASE
                    WHEN r.origem IS NOT NULL THEN r.origem
                    WHEN r.usuario_id IS NOT NULL THEN 'INTERNO'
                    ELSE 'EXTERNO'
                 END as origem
                 FROM relatos_usuarios r 
                 LEFT JOIN usuarios_externos ue ON r.usuario_externo_id = ue.id
                 LEFT JOIN usuarios u ON r.usuario_id = u.id";
    } else {
        $query = "SELECT r.id, r.tipo, r.descricao, r.data_criacao, r.resposta, r.data_resposta, 
                 ue.nome_completo, ue.email,
                 CASE
                    WHEN r.origem IS NOT NULL THEN r.origem
                    ELSE 'EXTERNO'
                 END as origem
                 FROM relatos_usuarios r 
                 JOIN usuarios_externos ue ON r.usuario_externo_id = ue.id";
    }
} else {
    if ($usuarioIdColumnExist) {
        $query = "SELECT r.id, r.tipo, r.descricao, r.data_criacao, NULL as resposta, NULL as data_resposta, 
                 CASE
                    WHEN r.usuario_externo_id IS NOT NULL THEN ue.nome_completo
                    WHEN r.usuario_id IS NOT NULL THEN u.nome_completo
                    ELSE 'Usuário Desconhecido'
                 END as nome_usuario,
                 CASE
                    WHEN r.usuario_externo_id IS NOT NULL THEN ue.email
                    WHEN r.usuario_id IS NOT NULL THEN u.email
                    ELSE ''
                 END as email_usuario,
                 CASE
                    WHEN r.origem IS NOT NULL THEN r.origem
                    WHEN r.usuario_id IS NOT NULL THEN 'INTERNO'
                    ELSE 'EXTERNO'
                 END as origem
                 FROM relatos_usuarios r 
                 LEFT JOIN usuarios_externos ue ON r.usuario_externo_id = ue.id
                 LEFT JOIN usuarios u ON r.usuario_id = u.id";
    } else {
        $query = "SELECT r.id, r.tipo, r.descricao, r.data_criacao, NULL as resposta, NULL as data_resposta, 
                 ue.nome_completo, ue.email, 'EXTERNO' as origem
                 FROM relatos_usuarios r 
                 JOIN usuarios_externos ue ON r.usuario_externo_id = ue.id";
    }
}

// Adiciona condições WHERE se houver filtros
if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

// Adaptação do filtro de status para quando as colunas não existem
if (!empty($filtroStatus) && !$columnsExist) {
    // Se as colunas não existem, considera todos como não respondidos
    if ($filtroStatus == 'RESPONDIDO') {
        $query .= " AND 1=0"; // Força nenhum resultado para "respondido" se a coluna não existe
    }
}

// Ordenação padrão
$query .= " ORDER BY r.data_criacao DESC";

// Query para contagem total
$queryCount = preg_replace('/r\.id,\s*r\.tipo,\s*r\.descricao,\s*r\.data_criacao,\s*r\.resposta,\s*r\.data_resposta,\s*.*?(?=FROM|WHERE|ORDER|LIMIT|$)/s', 'COUNT(*) as total ', $query);

// Executa query de contagem
$stmtCount = $conn->prepare($queryCount);
if (!empty($types)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalRows = $resultCount->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Adiciona LIMIT à query principal
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= 'ii';

// Executa query principal
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$relatos = $result->fetch_all(MYSQLI_ASSOC);

// Processa as descrições para adicionar links às capturas de tela
foreach ($relatos as &$relato) {
    $relato['descricao'] = processarDescricaoComURL($relato['descricao']);
    $relato['descricao'] = processarDescricaoComScreenshot($relato['descricao']);
}
unset($relato); // Quebra a referência com o último elemento
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatos de Erros e Melhorias</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: linear-gradient(to right, #4361ee, #3f37c9);
            color: white;
            font-weight: 500;
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
        }
        
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .badge-bug {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-melhoria {
            background-color: #0d6efd;
            color: white;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .status-respondido {
            background-color: #198754;
        }
        
        .status-pendente {
            background-color: #ffc107;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #4361ee;
            border-color: #4361ee;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background-color: #4361ee;
            border-color: #4361ee;
        }
        
        .btn-primary:hover {
            background-color: #3f37c9;
            border-color: #3f37c9;
        }
        
        .modal-header {
            background: linear-gradient(to right, #4361ee, #3f37c9);
            color: white;
        }
        
        .texto-relato {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #4361ee;
            margin-bottom: 20px;
        }
        
        /* Responsividade para telas pequenas */
        @media (max-width: 768px) {
            .card-title {
                font-size: 0.9rem;
            }
            
            .card-text {
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <!-- Mensagem de feedback -->
        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['mensagem']['texto'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
            <?php unset($_SESSION['mensagem']); ?>
        <?php endif; ?>

        <?php if (!$columnsExist): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Atualização Necessária</h5>
                <p>O sistema detectou que é necessário atualizar a estrutura do banco de dados para habilitar todas as funcionalidades de resposta aos relatos.</p>
                <form method="POST" action="migrar_relatos.php" class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-database me-2"></i> Executar Atualização
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-comment-alt me-2"></i> Relatos de Erros e Melhorias</h5>
                <span class="badge bg-primary"><?= $totalRows ?> relatos</span>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select name="tipo" id="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="BUG" <?= $filtroTipo == 'BUG' ? 'selected' : '' ?>>Erro/Bug</option>
                            <option value="MELHORIA" <?= $filtroTipo == 'MELHORIA' ? 'selected' : '' ?>>Melhoria</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="PENDENTE" <?= $filtroStatus == 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                            <option value="RESPONDIDO" <?= $filtroStatus == 'RESPONDIDO' ? 'selected' : '' ?>>Respondido</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="origem" class="form-label">Origem</label>
                        <select name="origem" id="origem" class="form-select">
                            <option value="">Todos</option>
                            <option value="INTERNO" <?= $filtroOrigem == 'INTERNO' ? 'selected' : '' ?>>Interno</option>
                            <option value="EXTERNO" <?= $filtroOrigem == 'EXTERNO' ? 'selected' : '' ?>>Externo</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="busca" class="form-label">Buscar</label>
                        <input type="text" name="busca" id="busca" class="form-control" placeholder="Descrição ou usuário" value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </form>

                <!-- Lista de relatos em cards -->
                <div class="row">
                    <?php if (!empty($relatos)): ?>
                        <?php foreach ($relatos as $relato): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <span>
                                            <?php if ($relato['tipo'] == 'BUG'): ?>
                                                <span class="badge badge-bug"><i class="fas fa-bug me-1"></i> Erro/Bug</span>
                                            <?php else: ?>
                                                <span class="badge badge-melhoria"><i class="fas fa-lightbulb me-1"></i> Melhoria</span>
                                            <?php endif; ?>
                                        </span>
                                        <small>
                                            <span class="status-indicator <?= isset($relato['resposta']) ? 'status-respondido' : 'status-pendente' ?>"></span>
                                            <?= isset($relato['resposta']) ? 'Respondido' : 'Pendente' ?>
                                        </small>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-user-circle me-1"></i> 
                                            <?= htmlspecialchars($relato['nome_usuario']) ?>
                                        </h6>
                                        <div class="text-muted small mb-2">
                                            <i class="fas fa-calendar-alt me-1"></i> 
                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($relato['data_criacao']))) ?>
                                        </div>
                                        <div class="texto-relato">
                                            <?= nl2br($relato['descricao']) ?>
                                        </div>
                                        <div class="d-flex justify-content-between mt-3">
                                            <button type="button" class="btn btn-sm btn-outline-primary view-details" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detalheModal"
                                                    data-relato-id="<?= $relato['id'] ?>"
                                                    data-relato-tipo="<?= $relato['tipo'] ?>"
                                                    data-relato-descricao="<?= htmlspecialchars($relato['descricao']) ?>"
                                                    data-relato-data="<?= htmlspecialchars(date('d/m/Y H:i', strtotime($relato['data_criacao']))) ?>"
                                                    data-relato-usuario="<?= htmlspecialchars($relato['nome_usuario']) ?>"
                                                    data-relato-email="<?= htmlspecialchars($relato['email_usuario']) ?>"
                                                    data-relato-resposta="<?= htmlspecialchars($relato['resposta'] ?? '') ?>"
                                                    data-relato-data-resposta="<?= isset($relato['data_resposta']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($relato['data_resposta']))) : '' ?>">
                                                <i class="fas fa-eye me-1"></i> Detalhes
                                            </button>
                                            <?php if (!isset($relato['resposta'])): ?>
                                                <button type="button" class="btn btn-sm btn-success responder-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#responderModal"
                                                        data-relato-id="<?= $relato['id'] ?>"
                                                        data-relato-descricao="<?= htmlspecialchars($relato['descricao']) ?>"
                                                        data-relato-usuario="<?= htmlspecialchars($relato['nome_usuario']) ?>">
                                                    <i class="fas fa-reply me-1"></i> Responder
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" disabled>
                                                    <i class="fas fa-check me-1"></i> Respondido
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-comment-slash fa-4x mb-3"></i>
                                <h5>Nenhum relato encontrado</h5>
                                <p>Ajuste os filtros ou verifique mais tarde.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paginação -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navegação de página" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page-1 ?>&tipo=<?= $filtroTipo ?>&status=<?= $filtroStatus ?>&origem=<?= $filtroOrigem ?>&busca=<?= urlencode($busca) ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&tipo=<?= $filtroTipo ?>&status=<?= $filtroStatus ?>&origem=<?= $filtroOrigem ?>&busca=<?= urlencode($busca) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&tipo=<?= $filtroTipo ?>&status=<?= $filtroStatus ?>&origem=<?= $filtroOrigem ?>&busca=<?= urlencode($busca) ?>" aria-label="Próximo">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalheModal" tabindex="-1" aria-labelledby="detalheModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalheModalLabel">Detalhes do Relato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-user me-2"></i>Usuário:</strong> <span id="modal-usuario"></span></p>
                            <p><strong><i class="fas fa-envelope me-2"></i>Email:</strong> <span id="modal-email"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-calendar me-2"></i>Data:</strong> <span id="modal-data"></span></p>
                            <p><strong><i class="fas fa-tag me-2"></i>Tipo:</strong> <span id="modal-tipo"></span></p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6><i class="fas fa-comment-alt me-2"></i>Relato:</h6>
                        <div class="texto-relato" id="modal-descricao"></div>
                    </div>

                    <div id="resposta-section" class="mb-3">
                        <h6><i class="fas fa-reply me-2"></i>Resposta:</h6>
                        <div class="texto-relato bg-light" id="modal-resposta"></div>
                        <div class="text-muted small text-end" id="modal-data-resposta-container">
                            Respondido em: <span id="modal-data-resposta"></span>
                        </div>
                    </div>

                    <div id="sem-resposta-section" class="text-center mb-3">
                        <p class="text-muted"><i class="fas fa-hourglass-half me-2"></i>Esse relato ainda não foi respondido.</p>
                        <button type="button" class="btn btn-success responder-modal-btn" data-bs-toggle="modal" data-bs-target="#responderModal">
                            <i class="fas fa-reply me-1"></i> Responder Agora
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Resposta -->
    <div class="modal fade" id="responderModal" tabindex="-1" aria-labelledby="responderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responderModalLabel">Responder Relato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Usuário:</label>
                            <p id="resposta-usuario" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Relato original:</label>
                            <div class="texto-relato" id="resposta-descricao"></div>
                        </div>
                        <div class="mb-3">
                            <label for="resposta" class="form-label">Sua resposta:</label>
                            <textarea class="form-control" id="resposta" name="resposta" rows="5" required></textarea>
                        </div>
                        <input type="hidden" name="relato_id" id="resposta-id">
                        <input type="hidden" name="responder_relato" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Enviar Resposta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para visualização de screenshots -->
    <div class="modal fade" id="screenshotModal" tabindex="-1" aria-labelledby="screenshotModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="screenshotModalLabel"><i class="fas fa-image me-2"></i> Visualizar Screenshot</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <div class="screenshot-container p-0">
                        <img src="" id="screenshotImage" class="img-fluid" style="max-height: 70vh;" alt="Screenshot">
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="downloadScreenshot" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i> Baixar Imagem
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manipula o modal de detalhes
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = document.getElementById('detalheModal');
                    
                    // Preenche os dados do modal
                    modal.querySelector('#modal-usuario').textContent = this.getAttribute('data-relato-usuario');
                    modal.querySelector('#modal-email').textContent = this.getAttribute('data-relato-email');
                    modal.querySelector('#modal-data').textContent = this.getAttribute('data-relato-data');
                    modal.querySelector('#modal-tipo').textContent = this.getAttribute('data-relato-tipo') === 'BUG' ? 'Erro/Bug' : 'Melhoria';
                    modal.querySelector('#modal-descricao').innerHTML = this.getAttribute('data-relato-descricao');
                    
                    const resposta = this.getAttribute('data-relato-resposta');
                    const respostaSection = modal.querySelector('#resposta-section');
                    const semRespostaSection = modal.querySelector('#sem-resposta-section');
                    
                    if (resposta) {
                        modal.querySelector('#modal-resposta').textContent = resposta;
                        modal.querySelector('#modal-data-resposta').textContent = this.getAttribute('data-relato-data-resposta');
                        respostaSection.style.display = 'block';
                        semRespostaSection.style.display = 'none';
                    } else {
                        respostaSection.style.display = 'none';
                        semRespostaSection.style.display = 'block';
                        
                        // Configura o botão de responder no modal de detalhes
                        const responderBtn = modal.querySelector('.responder-modal-btn');
                        responderBtn.setAttribute('data-relato-id', this.getAttribute('data-relato-id'));
                        responderBtn.setAttribute('data-relato-descricao', this.getAttribute('data-relato-descricao'));
                        responderBtn.setAttribute('data-relato-usuario', this.getAttribute('data-relato-usuario'));
                    }
                });
            });
            
            // Manipula o modal de resposta
            document.querySelectorAll('.responder-btn, .responder-modal-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = document.getElementById('responderModal');
                    
                    modal.querySelector('#resposta-usuario').textContent = this.getAttribute('data-relato-usuario');
                    modal.querySelector('#resposta-descricao').textContent = this.getAttribute('data-relato-descricao');
                    modal.querySelector('#resposta-id').value = this.getAttribute('data-relato-id');
                    
                    // Fecha o modal de detalhes se estiver aberto
                    const detalheModal = bootstrap.Modal.getInstance(document.getElementById('detalheModal'));
                    if (detalheModal) {
                        detalheModal.hide();
                    }
                });
            });
            
            // Manipula a visualização de screenshots
            document.querySelectorAll('.show-screenshot').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const nomeArquivo = this.getAttribute('data-screenshot');
                    const modal = document.getElementById('screenshotModal');
                    const modalInstance = new bootstrap.Modal(modal);
                    
                    // Define a fonte da imagem direta (sem debug)
                    const imagemSrc = 'direct_image.php?arquivo=' + encodeURIComponent(nomeArquivo);
                    document.getElementById('screenshotImage').src = imagemSrc;
                    
                    // Define o titulo do modal com o nome do arquivo
                    document.getElementById('screenshotModalLabel').innerHTML = 
                        '<i class="fas fa-image me-2"></i> ' + nomeArquivo;
                    
                    // Configura o link de download
                    document.getElementById('downloadScreenshot').href = imagemSrc;
                    document.getElementById('downloadScreenshot').setAttribute('download', nomeArquivo);
                    
                    // Abre o modal
                    modalInstance.show();
                });
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush(); // End output buffering ?>