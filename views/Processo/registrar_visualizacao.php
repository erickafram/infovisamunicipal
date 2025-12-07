<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../conf/database.php';
    require_once '../../models/LogVisualizacao.php';

    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['arquivo_id']) && isset($data['usuario_id'])) {
        $usuario_id = $data['usuario_id'];
        $arquivo_id = $data['arquivo_id'];
        
        // Verificar se o usuário existe (seja interno ou externo)
        $sqlUsuarioInterno = "SELECT id FROM usuarios WHERE id = ?";
        $stmtUsuarioInterno = $conn->prepare($sqlUsuarioInterno);
        $stmtUsuarioInterno->bind_param("i", $usuario_id);
        $stmtUsuarioInterno->execute();
        $resultUsuarioInterno = $stmtUsuarioInterno->get_result();
        
        $sqlUsuarioExterno = "SELECT id FROM usuarios_externos WHERE id = ?";
        $stmtUsuarioExterno = $conn->prepare($sqlUsuarioExterno);
        $stmtUsuarioExterno->bind_param("i", $usuario_id);
        $stmtUsuarioExterno->execute();
        $resultUsuarioExterno = $stmtUsuarioExterno->get_result();
        
        if ($resultUsuarioInterno->num_rows === 0 && $resultUsuarioExterno->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado.']);
            exit();
        }
        
        $logVisualizacaoModel = new LogVisualizacao($conn);
        $result = $logVisualizacaoModel->registrarVisualizacao($data['usuario_id'], $data['arquivo_id']);
        
        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Visualização registrada com sucesso.']);
        } else {
            // Verificar o motivo da falha
            $sqlDocumento = "SELECT a.id, a.status, a.processo_id, a.sigiloso, p.estabelecimento_id 
                            FROM arquivos a 
                            JOIN processos p ON a.processo_id = p.id 
                            WHERE a.id = ?";
            $stmtDocumento = $conn->prepare($sqlDocumento);
            $stmtDocumento->bind_param("i", $data['arquivo_id']);
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
                $stmtVinculo->bind_param("ii", $data['usuario_id'], $estabelecimentoId);
                $stmtVinculo->execute();
                $resultVinculo = $stmtVinculo->get_result();
                
                $sqlResponsavel = "SELECT id FROM estabelecimentos 
                                  WHERE usuario_externo_id = ? AND id = ?";
                $stmtResponsavel = $conn->prepare($sqlResponsavel);
                $stmtResponsavel->bind_param("ii", $data['usuario_id'], $estabelecimentoId);
                $stmtResponsavel->execute();
                $resultResponsavel = $stmtResponsavel->get_result();
                
                if ($resultVinculo->num_rows === 0 && $resultResponsavel->num_rows === 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Você não tem permissão para visualizar documentos deste estabelecimento.']);
                    exit();
                }
            }
            
            echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar visualização. Contate o administrador.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
}
?>
