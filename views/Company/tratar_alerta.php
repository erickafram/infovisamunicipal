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
require_once '../../models/Processo.php';

// Verificar se os parâmetros necessários foram enviados
if (!isset($_POST['alerta_id']) || !isset($_POST['acao'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos']);
    exit;
}

$alertaId = intval($_POST['alerta_id']);
$acao = $_POST['acao']; // 'marcar' ou 'desmarcar'
$userId = $_SESSION['user']['id'];
$observacao = isset($_POST['observacao']) ? $_POST['observacao'] : null;

$processoModel = new Processo($conn);

$resultado = false;

if ($acao === 'marcar') {
    $resultado = $processoModel->marcarAlertaComoTratado($alertaId, $userId, $observacao);
} elseif ($acao === 'desmarcar') {
    $resultado = $processoModel->desmarcarAlertaComoTratado($alertaId);
}

// Retornar resposta em formato JSON
header('Content-Type: application/json');
if ($resultado) {
    echo json_encode([
        'status' => 'success', 
        'message' => ($acao === 'marcar' ? 'Alerta marcado como tratado' : 'Alerta desmarcado'),
        'alerta_id' => $alertaId,
        'acao' => $acao
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Erro ao processar a solicitação'
    ]);
} 