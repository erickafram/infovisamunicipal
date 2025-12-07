<?php
session_start();
header('Content-Type: application/json');

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Verificar se o documento_id foi enviado
if (!isset($_POST['documento_id']) || empty($_POST['documento_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do documento não informado']);
    exit();
}

require_once '../conf/database.php';

$documentoId = intval($_POST['documento_id']);
$usuarioId = $_SESSION['user']['id'];

try {
    // Verificar se o documento pertence a um estabelecimento vinculado ao usuário
    $queryVerifica = "SELECT d.id 
                      FROM documentos d
                      JOIN processos p ON d.processo_id = p.id
                      JOIN estabelecimentos e ON p.estabelecimento_id = e.id
                      JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
                      WHERE d.id = ? AND ue.usuario_id = ? AND d.status = 'negado'";
    
    $stmtVerifica = $conn->prepare($queryVerifica);
    $stmtVerifica->bind_param("ii", $documentoId, $usuarioId);
    $stmtVerifica->execute();
    $result = $stmtVerifica->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Documento não encontrado ou você não tem permissão para acessá-lo']);
        exit();
    }
    
    // Atualizar o documento para marcar alerta como resolvido
    $queryUpdate = "UPDATE documentos 
                    SET alerta_ativo = 0, 
                        data_resolucao_alerta = NOW(), 
                        resolvido_por_usuario_id = ? 
                    WHERE id = ?";
    
    $stmtUpdate = $conn->prepare($queryUpdate);
    $stmtUpdate->bind_param("ii", $usuarioId, $documentoId);
    
    if ($stmtUpdate->execute()) {
        echo json_encode(['success' => true, 'message' => 'Alerta marcado como resolvido com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o documento']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
