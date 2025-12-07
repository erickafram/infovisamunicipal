<?php
require_once __DIR__ . "/conf/database.php";

$logFile = __DIR__ . '/logs/digitopay_webhook.log';
file_put_contents($logFile, "\n" . date('[Y-m-d H:i:s]') . " Nova requisição recebida\n", FILE_APPEND);

try {
    // Logar cabeçalhos para debug
    file_put_contents($logFile, "Cabeçalhos recebidos:\n" . print_r(getallheaders(), true) . "\n", FILE_APPEND);

    // Verificar método HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    file_put_contents($logFile, "Método HTTP: $method\n", FILE_APPEND);

    // Ler payload
    $rawPayload = file_get_contents('php://input');
    file_put_contents($logFile, "Raw payload (hex): " . bin2hex($rawPayload) . "\n", FILE_APPEND);

    if (empty($rawPayload)) {
        // Resposta para validação de webhook (GET)
        if ($method === 'GET') {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Webhook operacional']);
            exit;
        }
        throw new Exception("Payload vazio recebido via $method");
    }

    // Decodificar JSON
    $payload = json_decode($rawPayload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro JSON: " . json_last_error_msg() . " | Conteúdo: " . $rawPayload);
    }

    // ========== INÍCIO DO PROCESSAMENTO DO PAYLOAD ==========
    file_put_contents($logFile, "Payload decodificado:\n" . print_r($payload, true) . "\n", FILE_APPEND);

    // Validar campos obrigatórios
    $requiredFields = ['id', 'status'];
    foreach ($requiredFields as $field) {
        if (!isset($payload[$field])) {
            throw new Exception("Campo obrigatório faltando: $field");
        }
    }

    // Mapear status da Digitopay para status interno
    $statusMap = [
        'REALIZADO' => 'ativo',
        'APROVADO' => 'ativo',
        'CONCLUIDO' => 'ativo',
        'approved' => 'ativo'
    ];

    $statusDigitopay = strtoupper(trim($payload['status']));
    $novoStatus = $statusMap[$statusDigitopay] ?? 'pendente';
    file_put_contents($logFile, "Status convertido: $statusDigitopay => $novoStatus\n", FILE_APPEND);

    // Iniciar transação
    $conn->begin_transaction();

    try {
        // 1. Atualizar assinatura_planos
        $stmt = $conn->prepare("
            UPDATE assinatura_planos 
            SET 
                status = ?, 
                data_expiracao = IF(? = 'ativo', DATE_ADD(NOW(), INTERVAL 1 MONTH), data_expiracao),
                data_atualizacao = NOW()
            WHERE payment_id = ?
        ");
        $stmt->bind_param("sss", $novoStatus, $novoStatus, $payload['id']);

        if (!$stmt->execute()) {
            throw new Exception("Erro ao atualizar assinatura: " . $conn->error);
        }

        $affectedRows = $stmt->affected_rows;
        file_put_contents($logFile, "Linhas afetadas na assinatura: $affectedRows\n", FILE_APPEND);

        if ($affectedRows === 0) {
            throw new Exception("Nenhuma assinatura encontrada para payment_id: " . $payload['id']);
        }

        // 2. Se status for ativo, atualizar usuário
        if ($novoStatus === 'ativo') {
            // Buscar usuario_id
            $stmt = $conn->prepare("
                SELECT usuario_id 
                FROM assinatura_planos 
                WHERE payment_id = ?
            ");
            $stmt->bind_param("s", $payload['id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Usuário não encontrado para payment_id: " . $payload['id']);
            }

            $assinatura = $result->fetch_assoc();
            $userId = $assinatura['usuario_id'];
            file_put_contents($logFile, "ID do usuário encontrado: $userId\n", FILE_APPEND);

            // Atualizar usuário
            $stmt = $conn->prepare("
                UPDATE usuarios_externos 
                SET 
                    assinante = 1,
                    plano_ativo = 'premium',
                    data_expiracao_plano = DATE_ADD(NOW(), INTERVAL 1 MONTH)
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);

            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar usuário: " . $conn->error);
            }

            $userAffected = $stmt->affected_rows;
            file_put_contents($logFile, "Linhas afetadas no usuário: $userAffected\n", FILE_APPEND);

            if ($userAffected === 0) {
                throw new Exception("Nenhum usuário atualizado para ID: $userId");
            }
        }

        // Commit das alterações
        $conn->commit();
        file_put_contents($logFile, "Transação concluída com sucesso!\n", FILE_APPEND);

        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-lança para o catch externo
    }
} catch (Exception $e) {
    file_put_contents($logFile, "ERRO CRÍTICO: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'php_version' => phpversion(),
        'db_status' => isset($conn) ? 'connected' : 'disconnected'
    ]);
}
