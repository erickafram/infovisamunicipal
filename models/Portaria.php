<?php

class Portaria {
    private $conn;
    private $table = 'portarias';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todas as portarias ativas para o site público
    public function getPortariasAtivas() {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE status = 'ativo' 
                  ORDER BY ordem_exibicao ASC, data_publicacao DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Listar todas as portarias para administração
    public function getAllPortarias() {
        $query = "SELECT p.*, u.nome_completo as usuario_nome 
                  FROM " . $this->table . " p
                  LEFT JOIN usuarios u ON p.usuario_criacao = u.id
                  ORDER BY p.ordem_exibicao ASC, p.data_criacao DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Buscar portaria por ID
    public function getPortariaById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Criar nova portaria
    public function createPortaria($dados) {
        $query = "INSERT INTO " . $this->table . " 
                  (titulo, subtitulo, numero_portaria, arquivo_pdf, nome_arquivo_original, 
                   status, ordem_exibicao, data_publicacao, usuario_criacao) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssssssisi", 
            $dados['titulo'],
            $dados['subtitulo'],
            $dados['numero_portaria'],
            $dados['arquivo_pdf'],
            $dados['nome_arquivo_original'],
            $dados['status'],
            $dados['ordem_exibicao'],
            $dados['data_publicacao'],
            $dados['usuario_criacao']
        );
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        
        return false;
    }

    // Atualizar portaria
    public function updatePortaria($id, $dados) {
        // Se houver novo arquivo, incluir na atualização
        if (isset($dados['arquivo_pdf'])) {
            $query = "UPDATE " . $this->table . " 
                      SET titulo = ?, subtitulo = ?, numero_portaria = ?, 
                          arquivo_pdf = ?, nome_arquivo_original = ?, 
                          status = ?, ordem_exibicao = ?, data_publicacao = ?
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ssssssssi", 
                $dados['titulo'],
                $dados['subtitulo'],
                $dados['numero_portaria'],
                $dados['arquivo_pdf'],
                $dados['nome_arquivo_original'],
                $dados['status'],
                $dados['ordem_exibicao'],
                $dados['data_publicacao'],
                $id
            );
        } else {
            // Atualizar sem modificar o arquivo
            $query = "UPDATE " . $this->table . " 
                      SET titulo = ?, subtitulo = ?, numero_portaria = ?, 
                          status = ?, ordem_exibicao = ?, data_publicacao = ?
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ssssisi", 
                $dados['titulo'],
                $dados['subtitulo'],
                $dados['numero_portaria'],
                $dados['status'],
                $dados['ordem_exibicao'],
                $dados['data_publicacao'],
                $id
            );
        }
        
        return $stmt->execute();
    }

    // Deletar portaria
    public function deletePortaria($id) {
        // Primeiro, buscar o arquivo para deletar
        $portaria = $this->getPortariaById($id);
        
        if ($portaria && !empty($portaria['arquivo_pdf'])) {
            $arquivo_path = $_SERVER['DOCUMENT_ROOT'] . $portaria['arquivo_pdf'];
            if (file_exists($arquivo_path)) {
                unlink($arquivo_path);
            }
        }
        
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        return $stmt->execute();
    }

    // Alterar status da portaria
    public function alterarStatus($id, $status) {
        $query = "UPDATE " . $this->table . " SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $status, $id);
        
        return $stmt->execute();
    }

    // Atualizar ordem de exibição
    public function atualizarOrdem($id, $ordem) {
        $query = "UPDATE " . $this->table . " SET ordem_exibicao = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $ordem, $id);
        
        return $stmt->execute();
    }

    // Verificar se usuário tem permissão (níveis 1, 2, 3)
    public function verificarPermissao($nivel_acesso) {
        return in_array($nivel_acesso, [1, 2, 3]);
    }
} 