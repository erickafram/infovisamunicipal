<?php
session_start();
require_once '../../conf/database.php';

// Obtém o ID do usuário via REQUEST ou pela sessão
$user_id = $_REQUEST['usuario_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$user_id) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

$status = $_REQUEST['status'] ?? null;

// Inicia transação para garantir a consistência das operações
$conn->begin_transaction();

try {
    // 1. Housekeeping: marca como offline todos os usuários cuja última atividade foi há mais de 5 minutos
    $conn->query("
        UPDATE usuarios_online 
        SET logout_time = NOW() 
        WHERE logout_time IS NULL 
          AND last_activity < (NOW() - INTERVAL 5 MINUTE)
    ");

    // 2. Remove registros fantasmas (opcional)
    $conn->query("
        DELETE FROM usuarios_online 
        WHERE usuario_id = $user_id 
          AND logout_time IS NULL 
          AND last_activity IS NULL
    ");

    // 3. Verifica se já existe um registro para o usuário que não esteja finalizado
    $stmt_check = $conn->prepare("
        SELECT * FROM usuarios_online 
        WHERE usuario_id = ? 
          AND (logout_time IS NULL OR last_activity IS NULL)
        ORDER BY login_time DESC 
        LIMIT 1
    ");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $registroExistente = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($status === 'online') {
        if (!$registroExistente) {
            // Insere novo registro com last_activity preenchido
            $stmt = $conn->prepare("
                INSERT INTO usuarios_online (usuario_id, login_time, last_activity) 
                VALUES (?, NOW(), NOW())
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Atualiza last_activity para NOW() e reseta logout_time
            $stmt = $conn->prepare("
                UPDATE usuarios_online 
                SET last_activity = NOW(), logout_time = NULL 
                WHERE usuario_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->query("INSERT INTO status_log (usuario_id, status) VALUES ($user_id, 'online')");
    } elseif ($status === 'offline') {
        if ($registroExistente) {
            // Ao enviar offline, atualiza tanto logout_time quanto last_activity para NOW()
            $stmt = $conn->prepare("
                UPDATE usuarios_online 
                SET logout_time = NOW(), last_activity = NOW() 
                WHERE usuario_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $conn->query("INSERT INTO status_log (usuario_id, status) VALUES ($user_id, 'offline')");
        }
    }


    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro na atualização de status: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

// Fecha a conexão de forma segura
if ($conn->ping()) {
    $conn->close();
}

header("Content-Type: application/json");
echo json_encode(['status' => 'success']);
exit;
