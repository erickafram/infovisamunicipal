<?php
session_start();
require_once __DIR__ . "/../../conf/database.php";

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Verifica assinaturas ativas
$sql = "
    SELECT 
        a.status,
        a.data_expiracao
    FROM assinatura_planos a 
    WHERE 
        a.usuario_id = ?
        AND a.status = 'ativo'
        AND a.data_expiracao > NOW()
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$assinatura = $result->fetch_assoc();

if ($assinatura) {
    echo json_encode([
        'assinante' => true,
        'plano_ativo' => 'premium',
        'total' => 0
    ]);
    exit;
}

// Calcula uso diário se não for premium
$sql = "SELECT SUM(LENGTH(mensagem)) AS total 
        FROM mensagens 
        WHERE remetente_id = ? 
        AND DATE(data_envio) = CURDATE()";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode([
    'assinante' => false,
    'total' => $row['total'] ?? 0
]);
