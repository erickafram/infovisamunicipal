<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Alerta.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alerta_id'])) {
    $usuarioId = $_SESSION['user']['id']; // Obter o ID do usuário logado
    $alertaId = intval($_POST['alerta_id']); // Converter o ID do alerta para inteiro

    $alertaModel = new Alerta($conn);

    // Chama o método para marcar o alerta como lido
    if ($alertaModel->marcarAlertaComoLido($alertaId, $usuarioId)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha ao marcar alerta como lido.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Requisição inválida.']);
}
