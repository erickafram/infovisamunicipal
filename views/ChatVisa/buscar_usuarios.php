<?php
session_start();
require_once '../../conf/database.php';

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user']['id'];

$sql_usuarios = "
    SELECT u.id, u.nome_completo, 
           CASE 
               WHEN EXISTS (
                   SELECT 1 
                   FROM usuarios_online 
                   WHERE usuario_id = u.id AND (logout_time IS NULL OR logout_time > NOW() - INTERVAL 5 MINUTE)
               ) THEN 1
               ELSE 0
           END AS online,
           (SELECT COUNT(*) 
            FROM mensagens 
            WHERE destinatario_id = ? AND remetente_id = u.id AND visualizada = 0
           ) AS mensagens_nao_lidas,
           (SELECT MAX(data_envio)
            FROM mensagens
            WHERE (remetente_id = u.id AND destinatario_id = ?)
               OR (remetente_id = ? AND destinatario_id = u.id)
           ) AS ultima_mensagem
    FROM usuarios u
    WHERE u.id != ?
    ORDER BY mensagens_nao_lidas DESC, ultima_mensagem DESC, online DESC
";

$stmt_usuarios = $conn->prepare($sql_usuarios);
$stmt_usuarios->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt_usuarios->execute();
$result = $stmt_usuarios->get_result();
$usuarios = $result->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($usuarios);

?>
