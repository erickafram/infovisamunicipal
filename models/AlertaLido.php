<?php
/**
 * Classe para gerenciar alertas lidos/resolvidos
 */
class AlertaLido {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Marca um alerta como lido/resolvido
     * 
     * @param int $alertaId ID do alerta
     * @param int $userId ID do usuário
     * @return bool
     */
    public function marcarComoResolvido($alertaId, $userId) {
        $sql = "INSERT INTO alertas_resolvidos (alerta_id, usuario_id, data_resolucao) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE data_resolucao = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $alertaId, $userId);
        
        return $stmt->execute();
    }

    /**
     * Desmarca um alerta como resolvido (marca como não lido)
     * 
     * @param int $alertaId ID do alerta
     * @param int $userId ID do usuário
     * @return bool
     */
    public function desmarcarComoResolvido($alertaId, $userId) {
        $sql = "DELETE FROM alertas_resolvidos WHERE alerta_id = ? AND usuario_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $alertaId, $userId);
        
        return $stmt->execute();
    }

    /**
     * Verifica se um alerta está marcado como resolvido
     * 
     * @param int $alertaId ID do alerta
     * @param int $userId ID do usuário
     * @return bool
     */
    public function estaResolvido($alertaId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM alertas_resolvidos 
                WHERE alerta_id = ? AND usuario_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $alertaId, $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] > 0;
    }

    /**
     * Obtém a lista de IDs de alertas resolvidos para um usuário
     * 
     * @param int $userId ID do usuário
     * @return array
     */
    public function getAlertasResolvidosIds($userId) {
        $sql = "SELECT alerta_id FROM alertas_resolvidos WHERE usuario_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $ids = [];
        
        while ($row = $result->fetch_assoc()) {
            $ids[] = $row['alerta_id'];
        }
        
        return $ids;
    }
} 