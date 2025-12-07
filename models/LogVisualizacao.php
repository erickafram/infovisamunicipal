<?php
class LogVisualizacao
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function registrarVisualizacao($usuario_id, $arquivo_id)
    {
        // Verificar se o documento existe e seu status
        $sqlDocumento = "SELECT a.id, a.status, a.processo_id, a.sigiloso, p.estabelecimento_id 
                        FROM arquivos a 
                        JOIN processos p ON a.processo_id = p.id 
                        WHERE a.id = ?";
        $stmtDocumento = $this->conn->prepare($sqlDocumento);
        $stmtDocumento->bind_param("i", $arquivo_id);
        $stmtDocumento->execute();
        $resultDocumento = $stmtDocumento->get_result();
        
        if ($resultDocumento->num_rows === 0) {
            return false; // Documento não encontrado
        }
        
        $documento = $resultDocumento->fetch_assoc();
        $estabelecimentoId = $documento['estabelecimento_id'];
        
        // Verificar se o usuário é interno (funcionário) ou externo (empresa)
        $sqlUsuarioInterno = "SELECT id FROM usuarios WHERE id = ?";
        $stmtUsuarioInterno = $this->conn->prepare($sqlUsuarioInterno);
        $stmtUsuarioInterno->bind_param("i", $usuario_id);
        $stmtUsuarioInterno->execute();
        $resultUsuarioInterno = $stmtUsuarioInterno->get_result();
        
        $isUsuarioInterno = $resultUsuarioInterno->num_rows > 0;
        
        // Verificar se existe um usuário externo com o mesmo ID
        $sqlUsuarioExterno = "SELECT id FROM usuarios_externos WHERE id = ?";
        $stmtUsuarioExterno = $this->conn->prepare($sqlUsuarioExterno);
        $stmtUsuarioExterno->bind_param("i", $usuario_id);
        $stmtUsuarioExterno->execute();
        $resultUsuarioExterno = $stmtUsuarioExterno->get_result();
        
        $isUsuarioExterno = $resultUsuarioExterno->num_rows > 0;
        
        // Se não for nem interno nem externo, retorna falso
        if (!$isUsuarioInterno && !$isUsuarioExterno) {
            return false;
        }
        
        // Se for usuário interno, pode visualizar qualquer documento
        if ($isUsuarioInterno) {
            // Verificar se já visualizou
            $checkSql = "SELECT id FROM log_visualizacoes WHERE arquivo_id = ? AND usuario_id = ?";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("ii", $arquivo_id, $usuario_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            // Se já visualizou, retorna sucesso
            if ($result->num_rows > 0) {
                return true;
            }
            
            // Inserir novo registro de visualização
            $sql = "INSERT INTO log_visualizacoes (usuario_id, arquivo_id, data_visualizacao) VALUES (?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $usuario_id, $arquivo_id);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
        } else if ($isUsuarioExterno) {
            // Se for usuário externo, verificar se:
            // 1. O documento está assinado
            // 2. O usuário está vinculado ao estabelecimento
            // 3. O documento não é sigiloso
            
            // Verificar se o documento está assinado
            if ($documento['status'] !== 'assinado') {
                return false; // Usuário externo não pode visualizar documentos não assinados
            }
            
            // Verificar se o documento é sigiloso
            if ($documento['sigiloso'] == 1) {
                return false; // Usuário externo não pode visualizar documentos sigilosos
            }
            
            // Verificar se o usuário está vinculado ao estabelecimento
            $sqlVinculo = "SELECT id FROM usuarios_estabelecimentos 
                          WHERE usuario_id = ? AND estabelecimento_id = ?";
            $stmtVinculo = $this->conn->prepare($sqlVinculo);
            $stmtVinculo->bind_param("ii", $usuario_id, $estabelecimentoId);
            $stmtVinculo->execute();
            $resultVinculo = $stmtVinculo->get_result();
            
            // Verificar também se é responsável do estabelecimento
            $sqlResponsavel = "SELECT id FROM estabelecimentos 
                              WHERE usuario_externo_id = ? AND id = ?";
            $stmtResponsavel = $this->conn->prepare($sqlResponsavel);
            $stmtResponsavel->bind_param("ii", $usuario_id, $estabelecimentoId);
            $stmtResponsavel->execute();
            $resultResponsavel = $stmtResponsavel->get_result();
            
            // Se não tiver nenhum vínculo com o estabelecimento, retorna falso
            if ($resultVinculo->num_rows === 0 && $resultResponsavel->num_rows === 0) {
                return false; // Usuário não está vinculado ao estabelecimento
            }
            
            // Verificar se já visualizou
            $checkSql = "SELECT id FROM log_visualizacoes WHERE arquivo_id = ? AND usuario_id = ?";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("ii", $arquivo_id, $usuario_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            // Se já visualizou, retorna sucesso
            if ($result->num_rows > 0) {
                return true;
            }
            
            // Inserir novo registro de visualização
            $sql = "INSERT INTO log_visualizacoes (usuario_id, arquivo_id, data_visualizacao) VALUES (?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $usuario_id, $arquivo_id);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
        }
        
        return false;
    }
}
?>
