<?php
class UsuarioEstabelecimento {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Obtém todos os usuários vinculados a um estabelecimento específico
     */
    public function getUsuariosByEstabelecimento($estabelecimentoId) {
        $sql = "SELECT ue.id, ue.usuario_id, ue.tipo_vinculo, ux.nome_completo as nome, ux.cpf, ux.email, ux.telefone 
                FROM usuarios_estabelecimentos ue
                JOIN usuarios_externos ux ON ue.usuario_id = ux.id
                WHERE ue.estabelecimento_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $estabelecimentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        
        return $usuarios;
    }

    /**
     * Obtém todos os usuários externos não vinculados a um estabelecimento específico
     */
    public function getUsuariosNaoVinculados($estabelecimentoId) {
        $sql = "SELECT ux.id, ux.nome_completo as nome, ux.cpf, ux.email, ux.telefone, ux.tipo_vinculo
                FROM usuarios_externos ux
                WHERE ux.id NOT IN (
                    SELECT ue.usuario_id 
                    FROM usuarios_estabelecimentos ue 
                    WHERE ue.estabelecimento_id = ?
                )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $estabelecimentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        
        return $usuarios;
    }

    /**
     * Vincula um usuário a um estabelecimento
     */
    public function vincularUsuario($usuarioId, $estabelecimentoId, $tipoVinculo) {
        // Verificar se o vínculo já existe
        $checkSql = "SELECT id FROM usuarios_estabelecimentos WHERE usuario_id = ? AND estabelecimento_id = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $usuarioId, $estabelecimentoId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            return ['success' => false, 'message' => 'Usuário já está vinculado a este estabelecimento.'];
        }
        
        // Inserir novo vínculo
        $sql = "INSERT INTO usuarios_estabelecimentos (usuario_id, estabelecimento_id, tipo_vinculo) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iis", $usuarioId, $estabelecimentoId, $tipoVinculo);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Usuário vinculado com sucesso.'];
        } else {
            return ['success' => false, 'message' => 'Erro ao vincular usuário: ' . $stmt->error];
        }
    }

    /**
     * Remove o vínculo de um usuário com um estabelecimento
     */
    public function desvincularUsuario($id) {
        $sql = "DELETE FROM usuarios_estabelecimentos WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Vínculo removido com sucesso.'];
        } else {
            return ['success' => false, 'message' => 'Erro ao remover vínculo: ' . $stmt->error];
        }
    }

    /**
     * Atualiza o tipo de vínculo de um usuário com um estabelecimento
     */
    public function atualizarVinculo($id, $tipoVinculo) {
        $sql = "UPDATE usuarios_estabelecimentos SET tipo_vinculo = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $tipoVinculo, $id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Vínculo atualizado com sucesso.'];
        } else {
            return ['success' => false, 'message' => 'Erro ao atualizar vínculo: ' . $stmt->error];
        }
    }
}
?>
