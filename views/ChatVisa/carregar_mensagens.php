<?php
session_start();
require_once '../../conf/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
    exit;
}

$usuarioLogado = $_SESSION['user']['id'];
$destinatarioId = isset($_GET['destinatario_id']) ? (int)$_GET['destinatario_id'] : 0;

if ($destinatarioId <= 0) {
    echo json_encode([]);
    exit;
}

// Marcar mensagens como lidas (apenas recebidas pelo usuário logado)
$sqlUpdate = "
    UPDATE mensagens
    SET visualizada = 1
    WHERE remetente_id = ?
      AND destinatario_id = ?
      AND visualizada = 0
";
$stmt = $conn->prepare($sqlUpdate);
$stmt->bind_param("ii", $destinatarioId, $usuarioLogado);
$stmt->execute();
$stmt->close();

$sqlSelect = "
    SELECT m.*,
           CASE 
               WHEN m.remetente_id = ? THEN m.visualizada
               ELSE NULL
           END AS status_visualizacao
    FROM mensagens m
    WHERE 
      (m.remetente_id = ? AND m.destinatario_id = ?)
      OR
      (m.remetente_id = ? AND m.destinatario_id = ?)
    ORDER BY m.data_envio ASC
";
$stmt = $conn->prepare($sqlSelect);
$stmt->bind_param("iiiii", $usuarioLogado, $usuarioLogado, $destinatarioId, $destinatarioId, $usuarioLogado);
$stmt->execute();
$result = $stmt->get_result();

$mensagens = [];
while ($row = $result->fetch_assoc()) {
    // Apenas as mensagens enviadas pelo usuário logado terão status de visualização
    if ($row['remetente_id'] == $usuarioLogado) {
        $row['status_visualizacao'] = (int) $row['visualizada'];
    } else {
        $row['status_visualizacao'] = null;
    }

    $mensagens[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode($mensagens);
