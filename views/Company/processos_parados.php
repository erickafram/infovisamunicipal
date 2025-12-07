<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../../includes/header_empresa.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';

$processoModel = new Processo($conn);

$userId = $_SESSION['user']['id'];

// Obter todos os processos parados das empresas vinculadas ao usuário
$processosParados = $processoModel->getProcessosParadosByUsuario($userId);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processos Parados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }
        .card-title {
            font-weight: bold;
            color: #333;
        }
        .alerta-item {
            background-color: #ffdddd;
            border-left: 6px solid #f44336;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 0.9rem; /* Texto menor */
        }
    </style>
</head>

<body>

<div class="container mt-5" style="max-width: 1320px;">
    <h2>Processos Parados</h2>
    <?php if (!empty($processosParados)) : ?>
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Processos Parados</h5>
                <?php foreach ($processosParados as $processo) : ?>
                    <div class="alerta-item">
                        <strong>Empresa:</strong> <?php echo htmlspecialchars($processo['empresa_nome']); ?><br>
                        <strong>Processo:</strong> <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($processo['id']); ?>"><?php echo htmlspecialchars($processo['numero_processo']); ?></a> - <?php echo htmlspecialchars($processo['tipo_processo']); ?><br>
                        <strong>Status:</strong> <?php echo htmlspecialchars($processo['status']); ?><br>
                        <strong>Motivo:</strong> <?php echo htmlspecialchars($processo['motivo_parado']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else : ?>
        <p>Nenhum processo parado encontrado para estas empresas.</p>
    <?php endif; ?>
</div>

</body>

</html>
