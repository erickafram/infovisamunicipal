<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';

// Verificar autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$usuario_id = $_SESSION['user']['id'];

// Verificar se a tabela tem as colunas necessárias
$columnsExist = false;
$checkColumnsStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'resposta'");
$checkColumnsStmt->execute();
$columnsExist = ($checkColumnsStmt->get_result()->num_rows > 0);

// Busca os relatos do usuário com adaptação para considerar se as colunas existem
if ($columnsExist) {
    $stmt = $conn->prepare("SELECT r.id, r.tipo, r.descricao, r.data_criacao, r.resposta, r.data_resposta, 
                            IFNULL(u.nome_completo, 'Administrador') AS admin_nome
                            FROM relatos_usuarios r 
                            LEFT JOIN usuarios u ON r.admin_id = u.id
                            WHERE r.usuario_id = ?
                            ORDER BY r.data_criacao DESC");
} else {
    $stmt = $conn->prepare("SELECT r.id, r.tipo, r.descricao, r.data_criacao, NULL as resposta, NULL as data_resposta, 
                            NULL AS admin_nome
                            FROM relatos_usuarios r 
                            WHERE r.usuario_id = ?
                            ORDER BY r.data_criacao DESC");
}
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$relatos = $result->fetch_all(MYSQLI_ASSOC);

// Verifica se existem novas respostas (nos últimos 7 dias)
$novasRespostas = false;
if ($columnsExist) {
    foreach ($relatos as $relato) {
        if (isset($relato['data_resposta']) && !empty($relato['data_resposta'])) {
            $dataResposta = new DateTime($relato['data_resposta']);
            $hoje = new DateTime();
            $diferenca = $dataResposta->diff($hoje);
            if ($diferenca->days <= 7) {
                $novasRespostas = true;
                break;
            }
        }
    }
}

// Verifica se há uma mensagem na sessão
$mensagem = null;
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Relatos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos modernos e minimalistas */
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #edf2f7;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            margin-bottom: 1.25rem;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #edf2f7;
            color: #333;
            padding: 0.75rem 1.25rem;
            font-weight: 500;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Badges mais modernos e sutis */
        .badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 500;
            border-radius: 0.375rem;
        }
        
        .badge-bug {
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        
        .badge-melhoria {
            background-color: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }
        
        /* Blocos de texto mais limpos */
        .texto-relato {
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
            border-left: 3px solid #6366f1;
            margin-bottom: 1rem;
            font-size: 0.9375rem;
            color: #4b5563;
        }
        
        .texto-resposta {
            background-color: #f0f9ff;
            padding: 1rem;
            border-radius: 0.5rem;
            border-left: 3px solid #0ea5e9;
            margin-bottom: 1rem;
            font-size: 0.9375rem;
            color: #4b5563;
        }
        
        .sem-resposta {
            padding: 0.75rem;
            border-radius: 0.5rem;
            background-color: #fffbeb;
            border-left: 3px solid #f59e0b;
            color: #92400e;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }
        
        /* Avatar do admin mais moderno */
        .admin-info {
            display: flex;
            align-items: center;
            background-color: rgba(14, 165, 233, 0.05);
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .admin-avatar {
            width: 2.25rem;
            height: 2.25rem;
            background-color: #0ea5e9;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }
        
        /* Pulsação mais sutil */
        @keyframes pulse-subtle {
            0% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(14, 165, 233, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0);
            }
        }
        
        .pulse {
            animation: pulse-subtle 2s infinite;
        }
        
        /* Timeline mais limpa */
        .relato-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        
        /* Indicador de nova resposta mais sutil */
        .new-response-indicator {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 0.75rem;
            height: 0.75rem;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
            z-index: 2;
        }
        
        /* Estado vazio mais agradável */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6b7280;
        }
        
        .empty-state i {
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        .empty-state h5 {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #4b5563;
        }
        
        .empty-state p {
            font-size: 0.9375rem;
            max-width: 24rem;
            margin: 0 auto;
        }
        
        /* Informações de data sutis */
        .date-info {
            font-size: 0.75rem;
            color: #6b7280;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <!-- Alertas -->
        <?php if ($mensagem): ?>
        <div class="alert alert-<?= $mensagem['tipo'] ?> alert-dismissible fade show shadow-sm rounded-lg" role="alert">
            <?= $mensagem['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php endif; ?>

        <!-- Título da página -->
        <div class="d-flex justify-content-between align-items-center page-title">
            <h1 class="m-0">Meus Relatos</h1>
            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#reportarErroModal">
                <i class="fas fa-plus me-1"></i> Novo Relato
            </button>
        </div>

        <!-- Conteúdo principal -->
        <div class="card">
            <div class="card-body p-3 p-md-4">
                <?php if (empty($relatos)): ?>
                    <!-- Estado vazio -->
                    <div class="empty-state">
                        <i class="fas fa-comment-slash fa-3x"></i>
                        <h5>Nenhum relato encontrado</h5>
                        <p>Você ainda não registrou nenhum relato. Utilize o botão "Novo Relato" para reportar problemas ou sugerir melhorias.</p>
                    </div>
                <?php else: ?>
                    <!-- Lista de relatos -->
                    <div class="relato-list">
                        <?php foreach ($relatos as $relato): 
                            $isNew = false;
                            if (isset($relato['data_resposta']) && !empty($relato['data_resposta'])) {
                                $dataResposta = new DateTime($relato['data_resposta']);
                                $hoje = new DateTime();
                                $diferenca = $dataResposta->diff($hoje);
                                $isNew = ($diferenca->days <= 7);
                            }
                        ?>
                            <div class="card position-relative shadow-sm">
                                <?php if ($isNew): ?>
                                    <div class="new-response-indicator" title="Nova resposta"></div>
                                <?php endif; ?>
                                
                                <div class="card-header d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex align-items-center">
                                        <?php if ($relato['tipo'] == 'BUG'): ?>
                                            <span class="badge badge-bug me-2"><i class="fas fa-bug me-1"></i>Erro</span>
                                        <?php else: ?>
                                            <span class="badge badge-melhoria me-2"><i class="fas fa-lightbulb me-1"></i>Melhoria</span>
                                        <?php endif; ?>
                                        
                                        <span class="date-info">
                                            <i class="far fa-calendar-alt me-1 opacity-70"></i>
                                            <?= date('d/m/Y', strtotime($relato['data_criacao'])) ?>
                                            <span class="mx-1">·</span>
                                            <?= date('H:i', strtotime($relato['data_criacao'])) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (isset($relato['resposta']) && !empty($relato['resposta'])): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1">
                                            <i class="fas fa-check-circle me-1"></i>Respondido
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2 py-1">
                                            <i class="fas fa-clock me-1"></i>Aguardando
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <!-- Descrição do relato -->
                                    <div class="texto-relato">
                                        <?php echo nl2br(htmlspecialchars($relato['descricao'])); ?>
                                    </div>
                                    
                                    <!-- Resposta do administrador -->
                                    <?php if (isset($relato['resposta']) && !empty($relato['resposta'])): ?>
                                        <div class="admin-info">
                                            <div class="admin-avatar <?= $isNew ? 'pulse' : '' ?>">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars($relato['admin_nome'] ?? 'Administrador') ?></div>
                                                <div class="date-info">
                                                    <i class="far fa-clock me-1"></i> 
                                                    <?= date('d/m/Y', strtotime($relato['data_resposta'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="texto-resposta">
                                            <?php echo nl2br(htmlspecialchars($relato['resposta'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="sem-resposta">
                                            <i class="fas fa-hourglass-half me-2"></i> 
                                            Aguardando resposta do administrador
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Destacar relatos com respostas novas
            const newResponses = document.querySelectorAll('.new-response-indicator');
            if (window.location.hash === '#novas-respostas' && newResponses.length > 0) {
                newResponses[0].closest('.card').scrollIntoView({ behavior: 'smooth' });
            }
        });
    </script>
</body>
</html> 