<?php
session_start();
require_once '../../conf/database.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit();
}

$userId = $_SESSION['user']['id'];
$tipo = $_POST['tipo'] ?? null;
$descricao = $_POST['descricao'] ?? null;

if (!$tipo || !$descricao) {
    echo json_encode(['status' => 'error', 'message' => 'Campos obrigatórios não preenchidos']);
    exit();
}

try {
    $stmt = $conn->prepare("INSERT INTO relatos_usuarios (usuario_externo_id, tipo, descricao) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $userId, $tipo, $descricao);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Relato enviado com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o relato.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
