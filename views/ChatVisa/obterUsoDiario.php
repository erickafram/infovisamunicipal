<?php
session_start();
require_once '../../conf/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Usuário não logado.']);
    exit;
}

$remetente_id = $_SESSION['user']['id'];

// Consulta que soma o número de caracteres de todas as mensagens enviadas hoje pelo usuário
$sql = "SELECT IFNULL(SUM(CHAR_LENGTH(mensagem)), 0) AS total
        FROM mensagens 
        WHERE remetente_id = ? AND DATE(data_envio) = CURDATE()";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Erro na preparação da consulta.']);
    exit;
}
$stmt->bind_param("i", $remetente_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total = (int)$row['total'];

echo json_encode(['success' => true, 'total' => $total]);

$stmt->close();
$conn->close();
exit;
?>
