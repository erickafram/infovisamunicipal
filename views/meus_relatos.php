<?php
session_start();
require_once '../conf/database.php';
include 'includes/header_empresa.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// Verifica se é um usuário externo
if (!isset($_SESSION['user']['tipo']) || $_SESSION['user']['tipo'] !== 'externo') {
    header("Location: ../index.php");
    exit();
}

$usuario_id = $_SESSION['user']['id'];

// Verifica se as colunas de resposta existem
$columnsExist = false;
$checkColumnsStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'resposta'");
$checkColumnsStmt->execute();
$columnsExist = ($checkColumnsStmt->get_result()->num_rows > 0);

// Busca os relatos do usuário com adaptação para considerar se as colunas existem
if ($columnsExist) {
    $stmt = $conn->prepare("SELECT r.id, r.tipo, r.descricao, r.data_criacao, r.resposta, r.data_resposta, 
                                u.nome_completo AS admin_nome
                            FROM relatos_usuarios r 
                            LEFT JOIN usuarios u ON r.admin_id = u.id
                            WHERE r.usuario_externo_id = ?
                            ORDER BY r.data_criacao DESC");
} else {
    $stmt = $conn->prepare("SELECT r.id, r.tipo, r.descricao, r.data_criacao, NULL as resposta, NULL as data_resposta, 
                                NULL AS admin_nome
                            FROM relatos_usuarios r 
                            WHERE r.usuario_externo_id = ?
                            ORDER BY r.data_criacao DESC");
}
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$relatos = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Relatos e Sugestões</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            border-radius: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background-color: #4361ee;
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px #4361ee;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-item.response::before {
            background-color: #20c997;
            box-shadow: 0 0 0 2px #20c997;
        }
        
        .new-response-indicator {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background-color: #dc3545;
            border-radius: 50%;
            border: 2px solid #fff;
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="m-0"><i class="fas fa-comment-alt me-2"></i> Meus Relatos e Sugestões</h5>
                        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="fas fa-plus me-1"></i> Novo Relato
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($relatos)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comment-slash fa-4x mb-3 text-muted"></i>
                                <h5 class="text-muted">Você ainda não possui relatos</h5>
                                <p class="text-muted">Utilize o botão "Novo Relato" para registrar um erro ou sugerir uma melhoria.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($relatos as $relato): ?>
                                    <div class="timeline-item">
                                        <div class="card">
                                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
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
                                                <h6 class="card-title">Seu Relato:</h6>
                                                <div class="texto-relato">
                                                    <?= nl2br(htmlspecialchars($relato['descricao'])) ?>
                                                </div>
                                                
                                                <?php if (isset($relato['resposta']) && !empty($relato['resposta'])): ?>
                                                    <div class="timeline-item response">
                                                        <div class="admin-info">
                                                            <div class="admin-avatar pulse">
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
                                                        
                                                        <h6 class="card-title">Resposta:</h6>
                                                        <div class="texto-resposta">
                                                            <?= nl2br(htmlspecialchars($relato['resposta'])) ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="sem-resposta">
                                                        <i class="fas fa-hourglass-half me-2"></i> 
                                                        Aguardando resposta do administrador...
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script para mostrar ou ocultar relatos respondidos/não respondidos
        document.addEventListener('DOMContentLoaded', function() {
            // Se o URL tiver #novas-respostas, filtra para mostrar apenas as novas respostas
            if (window.location.hash === '#novas-respostas') {
                const newResponses = document.querySelectorAll('.new-response');
                if (newResponses.length > 0) {
                    newResponses[0].scrollIntoView({ behavior: 'smooth' });
                }
            }
            
            // Remove o indicador de "nova resposta" quando o usuário visualiza
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    const indicator = this.querySelector('.new-response-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                });
            });
        });
    </script>
</body>
</html> 