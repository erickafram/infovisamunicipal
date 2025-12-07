<?php
// listar_status_online.php
session_start();
require_once '../../conf/database.php';

if (!isset($_SESSION['user'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

$user_id = $_SESSION['user']['id'];

// Consulta para obter o status dos usuários (exceto o usuário logado)
$sql = "
    SELECT 
        u.id,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM usuarios_online 
                WHERE usuario_id = u.id 
                  AND last_activity > (NOW() - INTERVAL 5 MINUTE)
            ) THEN 1 
            ELSE 0 
        END AS online
    FROM usuarios u
    WHERE u.id != ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode(['data' => $data]);
