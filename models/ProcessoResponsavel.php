<?php
class ProcessoResponsavel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getProcessosDesignados($searchUser = '', $searchStatus = '', $limit = null, $offset = null)
    {
        $sql = "
            SELECT pr.id, p.numero_processo, u.nome_completo, pr.descricao, pr.status, p.id as processo_id, e.id as estabelecimento_id
            FROM processos_responsaveis pr
            JOIN processos p ON pr.processo_id = p.id
            JOIN usuarios u ON pr.usuario_id = u.id
            LEFT JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE (u.nome_completo LIKE ? OR ? = '')
            AND (pr.status LIKE ? OR ? = '')
            ORDER BY CASE WHEN pr.status = 'pendente' THEN 0 ELSE 1 END, p.data_abertura DESC
        ";

        // Add pagination if limit and offset are provided
        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT ? OFFSET ?";
        }

        $stmt = $this->conn->prepare($sql);

        if ($limit !== null && $offset !== null) {
            $searchUserParam = '%' . $searchUser . '%';
            $searchStatusParam = '%' . $searchStatus . '%';
            $stmt->bind_param('ssssii', $searchUserParam, $searchUser, $searchStatusParam, $searchStatus, $limit, $offset);
        } else {
            $searchUserParam = '%' . $searchUser . '%';
            $searchStatusParam = '%' . $searchStatus . '%';
            $stmt->bind_param('ssss', $searchUserParam, $searchUser, $searchStatusParam, $searchStatus);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function countProcessosDesignados($searchUser = '', $searchStatus = '')
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM processos_responsaveis pr
            JOIN processos p ON pr.processo_id = p.id
            JOIN usuarios u ON pr.usuario_id = u.id
            WHERE (u.nome_completo LIKE ? OR ? = '')
            AND (pr.status LIKE ? OR ? = '')
        ";
        
        $stmt = $this->conn->prepare($sql);
        $searchUserParam = '%' . $searchUser . '%';
        $searchStatusParam = '%' . $searchStatus . '%';
        $stmt->bind_param('ssss', $searchUserParam, $searchUser, $searchStatusParam, $searchStatus);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['total'];
    }
}
?>
