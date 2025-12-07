<?php
class PastaDocumento {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Cria uma nova pasta para um processo
     */
    public function createPasta($processo_id, $nome, $descricao, $usuario_id) {
        $sql = "INSERT INTO pastas_documentos (processo_id, nome, descricao, data_criacao, criado_por) 
                VALUES (?, ?, ?, NOW(), ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("issi", $processo_id, $nome, $descricao, $usuario_id);
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    /**
     * Atualiza os dados de uma pasta
     */
    public function updatePasta($pasta_id, $nome, $descricao) {
        $sql = "UPDATE pastas_documentos SET nome = ?, descricao = ? WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssi", $nome, $descricao, $pasta_id);
        
        return $stmt->execute();
    }
    
    /**
     * Exclui uma pasta
     */
    public function deletePasta($pasta_id) {
        $sql = "DELETE FROM pastas_documentos WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $pasta_id);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém todas as pastas de um processo
     */
    public function getPastasByProcesso($processo_id) {
        $sql = "SELECT p.*, u.nome_completo as criador_nome, 
                (SELECT COUNT(*) FROM documentos_pastas WHERE pasta_id = p.id) as total_itens,
                (SELECT COUNT(*) FROM documentos_pastas dp 
                 JOIN documentos d ON dp.item_id = d.id AND dp.tipo_item = 'documento' 
                 WHERE dp.pasta_id = p.id AND d.status = 'pendente') as documentos_pendentes
                FROM pastas_documentos p 
                LEFT JOIN usuarios u ON p.criado_por = u.id 
                WHERE p.processo_id = ? 
                ORDER BY p.nome ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $pastas = [];
        
        while ($row = $result->fetch_assoc()) {
            $pastas[] = $row;
        }
        
        return $pastas;
    }
    
    /**
     * Obtém os detalhes de uma pasta específica
     */
    public function getPastaById($pasta_id) {
        $sql = "SELECT p.*, u.nome_completo as criador_nome 
                FROM pastas_documentos p 
                LEFT JOIN usuarios u ON p.criado_por = u.id 
                WHERE p.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $pasta_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Adiciona um documento/arquivo a uma pasta
     */
    public function addItemToPasta($pasta_id, $tipo_item, $item_id) {
        // Verificar se o item já está em alguma pasta
        $check_sql = "SELECT id FROM documentos_pastas WHERE tipo_item = ? AND item_id = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("si", $tipo_item, $item_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        // Se o item já estiver em uma pasta, remova-o primeiro
        if ($check_result->num_rows > 0) {
            $delete_sql = "DELETE FROM documentos_pastas WHERE tipo_item = ? AND item_id = ?";
            $delete_stmt = $this->conn->prepare($delete_sql);
            $delete_stmt->bind_param("si", $tipo_item, $item_id);
            $delete_stmt->execute();
        }
        
        // Adicionar o item à nova pasta
        $sql = "INSERT INTO documentos_pastas (pasta_id, tipo_item, item_id, data_adicionado) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isi", $pasta_id, $tipo_item, $item_id);
        
        return $stmt->execute();
    }
    
    /**
     * Remove um documento/arquivo de uma pasta
     */
    public function removeItemFromPasta($pasta_id, $tipo_item, $item_id) {
        $sql = "DELETE FROM documentos_pastas WHERE pasta_id = ? AND tipo_item = ? AND item_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isi", $pasta_id, $tipo_item, $item_id);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém todos os itens (documentos e arquivos) de uma pasta
     */
    public function getItensByPasta($pasta_id) {
        $sql = "SELECT dp.*, dp.tipo_item, dp.item_id 
                FROM documentos_pastas dp 
                WHERE dp.pasta_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $pasta_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $itens_ids = [
            'documento' => [],
            'arquivo' => []
        ];
        
        while ($row = $result->fetch_assoc()) {
            $itens_ids[$row['tipo_item']][] = $row['item_id'];
        }
        
        // Buscar detalhes dos documentos
        $documentos = [];
        if (!empty($itens_ids['documento'])) {
            $doc_ids = implode(',', $itens_ids['documento']);
            $doc_sql = "SELECT d.*, 'documento' as tipo FROM documentos d WHERE d.id IN ($doc_ids)";
            $doc_result = $this->conn->query($doc_sql);
            
            while ($doc = $doc_result->fetch_assoc()) {
                $documentos[] = $doc;
            }
        }
        
        // Buscar detalhes dos arquivos
        $arquivos = [];
        if (!empty($itens_ids['arquivo'])) {
            $arq_ids = implode(',', $itens_ids['arquivo']);
            $arq_sql = "SELECT a.*, 'arquivo' as tipo FROM arquivos a WHERE a.id IN ($arq_ids)";
            $arq_result = $this->conn->query($arq_sql);
            
            while ($arq = $arq_result->fetch_assoc()) {
                $arquivos[] = $arq;
            }
        }
        
        // Combinar e ordenar por data de upload
        $itens = array_merge($documentos, $arquivos);
        usort($itens, function($a, $b) {
            return strtotime($b['data_upload']) - strtotime($a['data_upload']);
        });
        
        return $itens;
    }
    
    /**
     * Verifica se um item está em alguma pasta
     */
    public function getItemPasta($tipo_item, $item_id) {
        $sql = "SELECT p.* 
                FROM documentos_pastas dp 
                JOIN pastas_documentos p ON dp.pasta_id = p.id 
                WHERE dp.tipo_item = ? AND dp.item_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $tipo_item, $item_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }

    /**
     * Conta documentos pendentes que não estão em nenhuma pasta (Documentos Gerais)
     */
    public function countDocumentosPendentesGerais($processo_id) {
        $sql = "SELECT COUNT(*) as total 
                FROM documentos d 
                WHERE d.processo_id = ? 
                AND d.status = 'pendente' 
                AND d.id NOT IN (
                    SELECT dp.item_id 
                    FROM documentos_pastas dp 
                    WHERE dp.tipo_item = 'documento'
                )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['total'];
    }
} 