<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit;
}

require_once '../../conf/database.php';
require_once '../../models/AlertaLido.php';

// Verificar se os parâmetros necessários foram enviados
if (!isset($_POST['alerta_id']) || !isset($_POST['acao'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos']);
    exit;
}

$alertaId = intval($_POST['alerta_id']);
$acao = $_POST['acao']; // 'marcar' ou 'desmarcar'
$userId = $_SESSION['user']['id'];

$alertaLido = new AlertaLido($conn);

$resultado = false;

if ($acao === 'marcar') {
    $resultado = $alertaLido->marcarComoResolvido($alertaId, $userId);
} elseif ($acao === 'desmarcar') {
    $resultado = $alertaLido->desmarcarComoResolvido($alertaId, $userId);
}

// Retornar resposta em formato JSON
header('Content-Type: application/json');
if ($resultado) {
    echo json_encode([
        'status' => 'success', 
        'message' => ($acao === 'marcar' ? 'Alerta marcado como resolvido' : 'Alerta desmarcado'),
        'alerta_id' => $alertaId,
        'acao' => $acao
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Erro ao processar a solicitação'
    ]);
} 