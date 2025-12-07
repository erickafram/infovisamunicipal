<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';

// Set response header as JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit();
}

$user_id = $_SESSION['user']['id'];
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'interno'; // Default para compatibilidade

// Check if arquivo_id was provided
if (!isset($_POST['arquivo_id']) || empty($_POST['arquivo_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID do arquivo não fornecido']);
    exit();
}

$arquivo_id = intval($_POST['arquivo_id']);

// Verificar se o usuário existe (seja interno ou externo)
$sqlUsuarioInterno = "SELECT id FROM usuarios WHERE id = ?";
$stmtUsuarioInterno = $conn->prepare($sqlUsuarioInterno);
$stmtUsuarioInterno->bind_param("i", $user_id);
$stmtUsuarioInterno->execute();
$resultUsuarioInterno = $stmtUsuarioInterno->get_result();

$sqlUsuarioExterno = "SELECT id FROM usuarios_externos WHERE id = ?";
$stmtUsuarioExterno = $conn->prepare($sqlUsuarioExterno);
$stmtUsuarioExterno->bind_param("i", $user_id);
$stmtUsuarioExterno->execute();
$resultUsuarioExterno = $stmtUsuarioExterno->get_result();

if ($resultUsuarioInterno->num_rows === 0 && $resultUsuarioExterno->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado no sistema']);
    exit();
}

// Initialize Arquivo model
$arquivoModel = new Arquivo($conn);

// Register the visualization
$result = $arquivoModel->registrarVisualizacao($arquivo_id, $user_id);

if ($result) {
    echo json_encode(['status' => 'success', 'message' => 'Visualização registrada com sucesso']);
} else {
    // Verificar o motivo da falha
    $sqlDocumento = "SELECT a.id, a.status, a.processo_id, a.sigiloso, p.estabelecimento_id 
                    FROM arquivos a 
                    JOIN processos p ON a.processo_id = p.id 
                    WHERE a.id = ?";
    $stmtDocumento = $conn->prepare($sqlDocumento);
    $stmtDocumento->bind_param("i", $arquivo_id);
    $stmtDocumento->execute();
    $resultDocumento = $stmtDocumento->get_result();
    
    if ($resultDocumento->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Documento não encontrado']);
        exit();
    }
    
    $documento = $resultDocumento->fetch_assoc();
    
    // Verificar se o usuário é interno
    $isUsuarioInterno = $resultUsuarioInterno->num_rows > 0;
    
    if (!$isUsuarioInterno) {
        // Se for usuário externo, verificar os motivos possíveis de falha
        if ($documento['status'] !== 'assinado') {
            echo json_encode(['status' => 'error', 'message' => 'Documento não está assinado. Apenas documentos assinados podem ser visualizados.']);
            exit();
        }
        
        if ($documento['sigiloso'] == 1) {
            echo json_encode(['status' => 'error', 'message' => 'Este documento é sigiloso e não pode ser visualizado.']);
            exit();
        }
        
        $estabelecimentoId = $documento['estabelecimento_id'];
        $sqlVinculo = "SELECT id FROM usuarios_estabelecimentos 
                      WHERE usuario_id = ? AND estabelecimento_id = ?";
        $stmtVinculo = $conn->prepare($sqlVinculo);
        $stmtVinculo->bind_param("ii", $user_id, $estabelecimentoId);
        $stmtVinculo->execute();
        $resultVinculo = $stmtVinculo->get_result();
        
        $sqlResponsavel = "SELECT id FROM estabelecimentos 
                          WHERE usuario_externo_id = ? AND id = ?";
        $stmtResponsavel = $conn->prepare($sqlResponsavel);
        $stmtResponsavel->bind_param("ii", $user_id, $estabelecimentoId);
        $stmtResponsavel->execute();
        $resultResponsavel = $stmtResponsavel->get_result();
        
        if ($resultVinculo->num_rows === 0 && $resultResponsavel->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Você não tem permissão para visualizar documentos deste estabelecimento.']);
            exit();
        }
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar visualização. Contate o administrador.']);
}
?>
