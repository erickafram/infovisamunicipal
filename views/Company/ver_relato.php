<?php
ob_start(); // Iniciar output buffering
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$usuario_id = $_SESSION['user']['id'];

// Verifica se o ID do relato foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensagem'] = [
        'tipo' => 'danger',
        'texto' => 'ID do relato não fornecido.'
    ];
    header("Location: meus_relatos.php");
    exit();
}

$relato_id = intval($_GET['id']);
$usuario_id = $_SESSION['user']['id'];

// Verifica se as colunas de resposta existem
$columnsExist = false;
$checkColumnsStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'resposta'");
$checkColumnsStmt->execute();
$columnsExist = ($checkColumnsStmt->get_result()->num_rows > 0);

// Busca o relato específico do usuário
if ($columnsExist) {
    $stmt = $conn->prepare("SELECT r.id, r.tipo, r.descricao, r.data_criacao, r.resposta, r.data_resposta, 
                             u.nome_completo AS admin_nome
                         FROM relatos_usuarios r 
                         LEFT JOIN usuarios u ON r.admin_id = u.id
                         WHERE r.id = ? AND r.usuario_externo_id = ?");
} else {
    $stmt = $conn->prepare("SELECT r.id, r.tipo, r.descricao, r.data_criacao, NULL as resposta, NULL as data_resposta, 
                             NULL AS admin_nome
                         FROM relatos_usuarios r 
                         WHERE r.id = ? AND r.usuario_externo_id = ?");
}

$stmt->bind_param("ii", $relato_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['mensagem'] = [
        'tipo' => 'danger',
        'texto' => 'Relato não encontrado ou você não tem permissão para acessá-lo.'
    ];
    header("Location: meus_relatos.php");
    exit();
}

$relato = $result->fetch_assoc();

// Incluir o header depois de todas as verificações que necessitam de redirecionamento
include '../../includes/header_empresa.php';

// Determina se é uma nova resposta (nos últimos 7 dias)
$isNew = false;
if (isset($relato['data_resposta']) && !empty($relato['data_resposta'])) {
    $dataResposta = new DateTime($relato['data_resposta']);
    $hoje = new DateTime();
    $diferenca = $dataResposta->diff($hoje);
    $isNew = ($diferenca->days <= 7);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Relato</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(to right, #4361ee, #3f37c9);
            color: white;
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
        }
        
        .badge-bug {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-melhoria {
            background-color: #0d6efd;
            color: white;
        }
        
        .texto-relato {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #4361ee;
            margin-bottom: 20px;
        }
        
        .texto-resposta {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #20c997;
            margin-bottom: 20px;
        }
        
        .sem-resposta {
            padding: 15px;
            border-radius: 5px;
            background-color: #fff8e8;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            background-color: rgba(32, 201, 151, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background-color: #20c997;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(32, 201, 151, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(32, 201, 151, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(32, 201, 151, 0);
            }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="meus_relatos.php"><i class="fas fa-arrow-left me-1"></i>Voltar para Meus Relatos</a></li>
                <li class="breadcrumb-item active" aria-current="page">Visualizar Relato</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card position-relative">
                    <?php if ($isNew): ?>
                        <div class="new-response-indicator" title="Nova resposta" style="position: absolute; top: -8px; right: -8px; width: 20px; height: 20px; background-color: #dc3545; border-radius: 50%; border: 2px solid #fff; animation: pulse 1.5s infinite;"></div>
                    <?php endif; ?>
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($relato['tipo'] == 'BUG'): ?>
                                <span class="badge badge-bug"><i class="fas fa-bug me-1"></i> Erro/Bug</span>
                            <?php else: ?>
                                <span class="badge badge-melhoria"><i class="fas fa-lightbulb me-1"></i> Melhoria</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-white">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            <?= date('d/m/Y H:i', strtotime($relato['data_criacao'])) ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Seu Relato:</h5>
                        <div class="texto-relato">
                            <?= nl2br(htmlspecialchars($relato['descricao'])) ?>
                        </div>
                        
                        <?php if (isset($relato['resposta']) && !empty($relato['resposta'])): ?>
                            <h5 class="card-title mt-4">Resposta:</h5>
                            <div class="admin-info">
                                <div class="admin-avatar <?= $isNew ? 'pulse' : '' ?>">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($relato['admin_nome'] ?? 'Administrador') ?></strong>
                                    <div class="small text-muted">
                                        <i class="fas fa-clock me-1"></i> 
                                        <?= date('d/m/Y H:i', strtotime($relato['data_resposta'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="texto-resposta">
                                <?= nl2br(htmlspecialchars($relato['resposta'])) ?>
                            </div>
                        <?php else: ?>
                            <div class="sem-resposta mt-4">
                                <i class="fas fa-hourglass-half me-2"></i> 
                                Aguardando resposta do administrador...
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="meus_relatos.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> 
                                Voltar para Meus Relatos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); // End output buffering ?>
