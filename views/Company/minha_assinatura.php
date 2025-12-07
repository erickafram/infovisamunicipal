<?php
// minha_assinatura.php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$user_id = $_SESSION['user']['id'];

// Buscar todas as assinaturas do usuário
$sql = "SELECT * FROM assinatura_planos 
        WHERE usuario_id = ? 
        ORDER BY data_inicio DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assinaturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Determinar status para exibição do aviso
$showWarning = false;
$motivo = '';
$alertClass = 'info';
$titulo = $mensagem = $botaoTexto = '';

if (empty($assinaturas)) {
    $showWarning = true;
    $motivo = 'sem_assinatura';
} else {
    $ultima_assinatura = $assinaturas[0];
    $status = $ultima_assinatura['status'];
    $expirado = strtotime($ultima_assinatura['data_expiracao']) < time();

    if ($expirado) {
        $showWarning = true;
        $motivo = 'expirado';
    } elseif (in_array($status, ['pendente', 'cancelado'])) {
        $showWarning = true;
        $motivo = $status;
    }
}

// Configurar mensagens com base no motivo
switch ($motivo) {
    case 'sem_assinatura':
        $alertClass = 'info';
        $titulo = 'Você não possui assinatura ativa';
        $mensagem = 'Assine agora para desbloquear todos os benefícios!';
        $botaoTexto = 'Assinar Agora';
        break;
    case 'expirado':
        $alertClass = 'danger';
        $titulo = 'Sua assinatura expirou';
        $mensagem = 'Renove sua assinatura para continuar aproveitando os benefícios.';
        $botaoTexto = 'Renovar Assinatura';
        break;
    case 'pendente':
        $alertClass = 'warning';
        $titulo = 'Pagamento pendente';
        $mensagem = 'Complete o pagamento para ativar sua assinatura.';
        $botaoTexto = 'Regularizar Pagamento';
        break;
    case 'cancelado':
        $alertClass = 'secondary';
        $titulo = 'Assinatura cancelada';
        $mensagem = 'Reative sua assinatura para voltar a utilizar os recursos premium.';
        $botaoTexto = 'Reativar Assinatura';
        break;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <?php include '../../includes/header_empresa.php'; ?>
    <title>Minha Assinatura</title>
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .status-ativo {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-expirado {
            background-color: #f8d7da;
            color: #721c24;
        }

        .assinatura-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 20px;
            transition: transform 0.2s;
        }

        .assinatura-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .beneficios-list {
            list-style: none;
            padding-left: 0;
        }

        .beneficios-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .beneficios-list li:last-child {
            border-bottom: 0;
        }

        .alert-heading {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .btn-renew {
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
        }

        .btn-renew:hover {
            background: linear-gradient(45deg, #c82333, #bd2130);
            color: white;
        }
    </style>
</head>

<body>
    <div class="container py-5">

        <?php if ($showWarning): ?>
            <!-- Aviso de status problemático -->
            <div class="row justify-content-center mb-5">
                <div class="col-md-8 text-center">
                    <div class="alert alert-<?= $alertClass ?> mb-4">
                        <h4 class="alert-heading"><?= $titulo ?></h4>
                        <p class="mb-0"><?= $mensagem ?></p>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Benefícios do Chat Premium</h5>
                            <ul class="beneficios-list">
                                <li><i class="bi bi-unlock me-2"></i>Mensagens ilimitadas</li>
                                <li><i class="bi bi-clock-history me-2"></i>Histórico completo de conversas</li>
                                <li><i class="bi bi-shield-check me-2"></i>Segurança avançada</li>
                                <li><i class="bi bi-image me-2"></i>Upload de imagens convertidas para PDF</li>
                            </ul>
                            <a href="../ChatVisa/assinatura.php" class="btn btn-primary mt-3 <?= $motivo === 'expirado' ? 'btn-renew' : '' ?>">
                                <i class="bi bi-credit-card me-2"></i><?= $botaoTexto ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($assinaturas)): ?>
            <!-- Listagem de assinaturas -->
            <div class="row">
                <?php foreach ($assinaturas as $assinatura): ?>
                    <?php
                    // Determinar classe do status
                    $statusClass = 'status-' . $assinatura['status'];
                    if (strtotime($assinatura['data_expiracao']) < time()) {
                        $statusClass = 'status-expirado';
                    }
                    ?>
                    <div class="col-md-6">
                        <div class="assinatura-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5>
                                    Assinatura #<?= $assinatura['id'] ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= strtoupper($assinatura['status']) ?>
                                    </span>
                                </h5>
                                <small class="text-muted">
                                    <?= date('d/m/Y', strtotime($assinatura['data_inicio'])) ?>
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <p class="mb-1">
                                        <strong>Valor:</strong><br>
                                        R$ <?= number_format($assinatura['valor'], 2, ',', '.') ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Método:</strong><br>
                                        <?= strtoupper($assinatura['metodo_pagamento']) ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1">
                                        <strong>Início:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($assinatura['data_inicio'])) ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Expira:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($assinatura['data_expiracao'])) ?>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <small class="text-muted">
                                    ID Pagamento: <?= $assinatura['payment_id'] ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</body>

</html>