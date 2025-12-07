<?php
class Documento
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function createDocumento($processo_id, $nome_arquivo, $caminho_arquivo, $doc_name = null)
    {
        // Se doc_name for fornecido, use-o como nome do documento
        $display_name = $doc_name ?: $nome_arquivo;

        $stmt = $this->conn->prepare("INSERT INTO documentos (processo_id, nome_arquivo, caminho_arquivo, data_upload, status) VALUES (?, ?, ?, NOW(), 'pendente')");
        $stmt->bind_param("iss", $processo_id, $display_name, $caminho_arquivo);
        return $stmt->execute();
    }

    public function approveDocumento($documento_id, $usuario_id)
    {
        $query = "UPDATE documentos SET status = 'aprovado', aprovado_por = ?, data_aprovacao = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $usuario_id, $documento_id);
        return $stmt->execute();
    }

    public function revertDocumento($documento_id)
    {
        $sql = "UPDATE documentos SET status = 'pendente', aprovado_por = NULL, data_aprovacao = NULL WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $documento_id);
        return $stmt->execute();
    }


    public function denyDocumento($documento_id, $motivo)
    {
        // Salva o histórico de negação
        $usuario_id = $_SESSION['user']['id']; // Usuário logado
        $queryHistorico = "INSERT INTO historico_negacoes (documento_id, motivo_negacao, usuario_id) VALUES (?, ?, ?)";
        $stmtHistorico = $this->conn->prepare($queryHistorico);
        $stmtHistorico->bind_param('isi', $documento_id, $motivo, $usuario_id);
        $stmtHistorico->execute();

        // Atualiza o status do documento
        $query = "UPDATE documentos SET status = 'negado', motivo_negacao = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $motivo, $documento_id);
        return $stmt->execute();
    }

    public function updateDocumentoNegado($documento_id, $nome_arquivo, $caminho_arquivo)
    {
        $stmt = $this->conn->prepare("UPDATE documentos SET nome_arquivo = ?, caminho_arquivo = ?, status = 'pendente', motivo_negacao = NULL, data_upload = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $nome_arquivo, $caminho_arquivo, $documento_id);
        return $stmt->execute();
    }

    public function getDocumentosByProcesso($processo_id)
    {
        $query = "SELECT * FROM documentos WHERE processo_id = ? ORDER BY data_upload DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $processo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getDocumentosByProcessoAndStatus($processo_id, $status)
    {
        $sql = "SELECT * FROM documentos WHERE processo_id = ? AND status = ? ORDER BY data_upload DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $processo_id, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function updateNomeDocumento($documento_id, $novo_nome)
    {
        $stmt = $this->conn->prepare("UPDATE documentos SET nome_arquivo = ? WHERE id = ?");
        $stmt->bind_param("si", $novo_nome, $documento_id);
        return $stmt->execute();
    }

    public function deleteDocumento($documento_id, $usuario_id)
    {
        // Query para obter os dados do documento e do estabelecimento via JOIN com processos
        $stmt = $this->conn->prepare("
            SELECT d.id, d.nome_arquivo, d.processo_id, p.estabelecimento_id, d.caminho_arquivo
            FROM documentos d
            INNER JOIN processos p ON d.processo_id = p.id
            WHERE d.id = ?
        ");
        $stmt->bind_param("i", $documento_id);
        $stmt->execute();
        $documento = $stmt->get_result()->fetch_assoc();

        if ($documento) {
            // Registra a exclusão
            $query = "INSERT INTO logs (tipo, id_referencia, nome, processo_id, estabelecimento_id, usuario_id, data_exclusao)
                      VALUES ('documento', ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "isiii",
                $documento['id'],
                $documento['nome_arquivo'],
                $documento['processo_id'],
                $documento['estabelecimento_id'],
                $usuario_id
            );
            $stmt->execute();

            // Deleta o arquivo físico
            $caminhoCompleto = "../../" . $documento['caminho_arquivo'];
            if (file_exists($caminhoCompleto)) {
                unlink($caminhoCompleto);
            }

            // Remove o item das pastas antes de excluir o documento
            $stmt = $this->conn->prepare("DELETE FROM documentos_pastas WHERE tipo_item = 'documento' AND item_id = ?");
            $stmt->bind_param("i", $documento_id);
            $stmt->execute();

            // Remove o registro da tabela documentos
            $stmt = $this->conn->prepare("DELETE FROM documentos WHERE id = ?");
            $stmt->bind_param("i", $documento_id);
            return $stmt->execute();
        }
        return false;
    }


    public function deleteDocumentosByProcesso($processo_id)
    {
        $stmt = $this->conn->prepare("SELECT caminho_arquivo FROM documentos WHERE processo_id = ?");
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($doc = $result->fetch_assoc()) {
            $caminhoCompleto = "../../" . $doc['caminho_arquivo']; // Ajuste o caminho conforme necessário
            if (file_exists($caminhoCompleto)) {
                unlink($caminhoCompleto);
            }
        }
        $stmt = $this->conn->prepare("DELETE FROM documentos WHERE processo_id = ?");
        $stmt->bind_param("i", $processo_id);
        return $stmt->execute();
    }

    public function findById($documento_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->bind_param("i", $documento_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
