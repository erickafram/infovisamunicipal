<?php
require_once '../../controllers/ArquivoController.php';
class Assinatura
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function createAssinatura($arquivo_id, $usuario_id)
    {
        $stmt = $this->conn->prepare("INSERT INTO assinaturas (arquivo_id, usuario_id, data_assinatura) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $arquivo_id, $usuario_id);
        return $stmt->execute();
    }

    public function getAssinaturasIdsByArquivoId($arquivo_id)
    {
        $query = "SELECT usuario_id FROM assinaturas WHERE arquivo_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $assinantes = [];
        while ($row = $result->fetch_assoc()) {
            $assinantes[] = $row['usuario_id'];
        }

        return $assinantes;
    }

    public function getAssinaturasPendentesByArquivoId($arquivo_id)
    {
        $sql = "SELECT * FROM assinaturas WHERE arquivo_id = ? AND status != 'assinado'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $arquivo_id); // 'i' indica que $arquivo_id é um integer
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    

    public function getAssinaturasPendentesPorArquivo($arquivo_id)
    {
        $stmt = $this->conn->prepare("SELECT a.*, u.nome_completo FROM assinaturas a JOIN usuarios u ON a.usuario_id = u.id WHERE a.arquivo_id = ? AND a.status = 'pendente'");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function addAssinatura($arquivo_id, $usuario_id)
    {
        $sql = "INSERT INTO assinaturas (arquivo_id, usuario_id, data_assinatura, status) VALUES (?, ?, NOW(), 'pendente')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $arquivo_id, $usuario_id);
        $stmt->execute();
        $stmt->close();
    }

    public function isArquivoAssinado($arquivo_id)
    {
        $sql = "SELECT COUNT(*) as count FROM assinaturas WHERE arquivo_id = ? AND status = 'assinado'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }

    public function removeAssinatura($arquivo_id, $usuario_id)
    {
        // Verificar se o status da assinatura é 'assinado'
        $stmt = $this->conn->prepare("SELECT status FROM assinaturas WHERE arquivo_id = ? AND usuario_id = ?");
        $stmt->bind_param('ii', $arquivo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    
        if ($row['status'] == 'assinado') {
            // Não permitir remoção de assinatura se o status for 'assinado'
            return false;
        }
    
        // Remover a assinatura se não estiver 'assinado'
        $sql = "DELETE FROM assinaturas WHERE arquivo_id = ? AND usuario_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $arquivo_id, $usuario_id);
        $stmt->execute();
        $stmt->close();
    
        // Verificar se ainda existem assinaturas pendentes
        $stmt = $this->conn->prepare("SELECT COUNT(*) as pendentes FROM assinaturas WHERE arquivo_id = ? AND status = 'pendente'");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    
        // Se não há mais assinaturas pendentes, gerar o PDF
        if ($row['pendentes'] == 0) {
            $arquivo = new Arquivo($this->conn);
            $arquivo->atualizarStatusAssinado($arquivo_id);
    
            // Chamar o método de geração de PDF
            $arquivoController = new ArquivoController($this->conn);
            $arquivoController->gerarPdf($arquivo_id);
        }
    
        return true;
    }
    

    public function isAssinaturaExistente($arquivo_id, $usuario_id)
    {
        $sql = "SELECT COUNT(*) as count FROM assinaturas WHERE arquivo_id = ? AND usuario_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $arquivo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }

    public function getAssinaturasPorArquivo($arquivo_id)
    {
        $stmt = $this->conn->prepare("SELECT a.*, u.nome_completo FROM assinaturas a JOIN usuarios u ON a.usuario_id = u.id WHERE a.arquivo_id = ?");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getAssinaturasByArquivoId($arquivo_id)
    {
        $query = "SELECT * FROM assinaturas WHERE arquivo_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getAssinaturasPendentes($user_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.*, ar.tipo_documento, ar.processo_id, p.estabelecimento_id 
            FROM assinaturas a 
            JOIN arquivos ar ON a.arquivo_id = ar.id 
            JOIN processos p ON ar.processo_id = p.id 
            WHERE a.usuario_id = ? AND a.status = 'pendente'
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    

    public function addOrUpdateAssinatura($arquivo_id, $usuario_id)
{
    $stmt = $this->conn->prepare("SELECT id FROM assinaturas WHERE arquivo_id = ? AND usuario_id = ? AND status = 'pendente'");
    $stmt->bind_param("ii", $arquivo_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt = $this->conn->prepare("UPDATE assinaturas SET status = 'assinado', data_assinatura = NOW() WHERE arquivo_id = ? AND usuario_id = ? AND status = 'pendente'");
        $stmt->bind_param("ii", $arquivo_id, $usuario_id);
    } else {
        $stmt = $this->conn->prepare("INSERT INTO assinaturas (arquivo_id, usuario_id, data_assinatura, status) VALUES (?, ?, NOW(), 'assinado')");
        $stmt->bind_param("ii", $arquivo_id, $usuario_id);
    }

    $stmt->execute();
    
    // Verificar se todas as assinaturas foram concluídas
    $stmt = $this->conn->prepare("SELECT COUNT(*) as pendentes FROM assinaturas WHERE arquivo_id = ? AND status = 'pendente'");
    $stmt->bind_param("i", $arquivo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['pendentes'] == 0) {
        $arquivo = new Arquivo($this->conn);
        $arquivo->atualizarStatusAssinado($arquivo_id);
    
        // Chamar o método de geração de PDF
        $arquivoController = new ArquivoController($this->conn);
        $arquivoController->gerarPdf($arquivo_id);
    }
}

    

    public function getAssinaturaPorArquivoEUsuario($arquivo_id, $usuario_id)
    {
        $stmt = $this->conn->prepare("SELECT data_assinatura FROM assinaturas WHERE arquivo_id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $arquivo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getAssinaturasRealizadas($usuario_id, $search = '', $limit = 10, $offset = 0)
    {
        $search = "%" . $search . "%";
        $sql = "
            SELECT a.*, ar.tipo_documento, ar.caminho_arquivo, p.numero_processo, e.nome_fantasia
            FROM assinaturas a
            JOIN arquivos ar ON a.arquivo_id = ar.id
            JOIN processos p ON ar.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE a.usuario_id = ? AND a.status = 'assinado'
            AND (ar.tipo_documento LIKE ? OR p.numero_processo LIKE ? OR e.nome_fantasia LIKE ?)
            ORDER BY a.data_assinatura DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isssii", $usuario_id, $search, $search, $search, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalAssinaturasRealizadas($usuario_id, $search = '')
    {
        $search = "%" . $search . "%";
        $sql = "
            SELECT COUNT(*) as total
            FROM assinaturas a
            JOIN arquivos ar ON a.arquivo_id = ar.id
            JOIN processos p ON ar.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE a.usuario_id = ? AND a.status = 'assinado'
            AND (ar.tipo_documento LIKE ? OR p.numero_processo LIKE ? OR e.nome_fantasia LIKE ?)
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isss", $usuario_id, $search, $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'];
    }

    public function isAssinaturaPendente($arquivo_id, $usuario_id)
    {
        $query = "SELECT * FROM assinaturas WHERE arquivo_id = ? AND usuario_id = ? AND status != 'assinado'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $arquivo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }


    public function assinaturaExists($arquivo_id, $usuario_id)
    {
        $query = "SELECT COUNT(*) FROM assinaturas WHERE arquivo_id = ? AND usuario_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $arquivo_id, $usuario_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        return $count > 0;
    }
}
