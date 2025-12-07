<?php
session_start();
require_once '../../conf/database.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    $estabelecimentoId = $data['estabelecimento_id'] ?? null;
    $userId = $_SESSION['user']['id'];

    if (!$estabelecimentoId) {
        echo json_encode(['status' => 'error', 'message' => 'ID do estabelecimento não informado']);
        exit();
    }

    // Verifica se o estabelecimento pertence ao usuário
    $query = "UPDATE estabelecimentos 
              SET lido = 1 
              WHERE id = ? 
              AND usuario_externo_id = ? 
              AND status = 'rejeitado'";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $estabelecimentoId, $userId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Nenhum registro afetado']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro de execução']);
    }
    exit();
}
