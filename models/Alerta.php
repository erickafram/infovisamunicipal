<?php
class Alerta
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getConnection()
    {
        return $this->conn;
    }
    public function getAlertasAtivos()
    {
        $stmt = $this->conn->prepare("
        SELECT * 
        FROM alertas_empresas 
        WHERE status = 'ativo' AND prazo >= CURDATE()
    ");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    public function getAssinaturasPendentes($usuario_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.arquivo_id, ar.tipo_documento, ar.processo_id, ar.data_upload, ar.caminho_arquivo, p.estabelecimento_id
            FROM assinaturas a
            JOIN arquivos ar ON a.arquivo_id = ar.id
            JOIN processos p ON ar.processo_id = p.id
            WHERE a.usuario_id = ? AND a.status = 'pendente'
        ");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getAssinaturasRascunho($usuario_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.arquivo_id, ar.tipo_documento, ar.processo_id, ar.data_upload, ar.caminho_arquivo, p.estabelecimento_id, ar.status
            FROM assinaturas a
            JOIN arquivos ar ON a.arquivo_id = ar.id
            JOIN processos p ON ar.processo_id = p.id
            WHERE a.usuario_id = ? AND a.status AND ar.caminho_arquivo = ''
        ");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function criarAlertaParaEmpresas($descricao, $prazo, $link, $municipio)
    {
        $stmt = $this->conn->prepare("INSERT INTO alertas_empresas (descricao, prazo, link, municipio) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $descricao, $prazo, $link, $municipio);
        return $stmt->execute();
    }

    public function listarAlertasEmpresas()
    {
        $result = $this->conn->query("SELECT * FROM alertas_empresas");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function editarAlertaEmpresa($id, $descricao, $prazo, $status, $link)
    {
        $stmt = $this->conn->prepare("UPDATE alertas_empresas SET descricao = ?, prazo = ?, status = ?, link = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $descricao, $prazo, $status, $link, $id);
        return $stmt->execute();
    }
    public function excluirAlertaEmpresa($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM alertas_empresas WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function marcarAlertaComoLido($alertaId, $usuarioId)
    {
        $stmt = $this->conn->prepare("INSERT INTO alertas_lidos (alerta_id, usuario_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $alertaId, $usuarioId);
        return $stmt->execute();
    }

    public function listarAlertasPorMunicipio($municipio)
    {
        $stmt = $this->conn->prepare("
        SELECT *
        FROM alertas_empresas
        WHERE municipio = ? AND status = 'ativo' AND prazo >= CURDATE()
    ");
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }



    public function listarAlertasNaoLidos($usuarioId, $municipios)
    {
        // Constrói uma string de placeholders para os municípios
        $placeholders = implode(',', array_fill(0, count($municipios), '?'));

        // Cria a query SQL com o operador IN
        $query = "
            SELECT a.*
            FROM alertas_empresas a
            LEFT JOIN alertas_lidos l ON a.id = l.alerta_id AND l.usuario_id = ?
            WHERE l.id IS NULL AND a.status = 'ativo' AND a.prazo >= CURDATE() AND a.municipio IN ($placeholders)
        ";

        $stmt = $this->conn->prepare($query);

        // Junta o ID do usuário com os municípios
        $params = array_merge([$usuarioId], $municipios);

        // Usa call_user_func_array para vincular os parâmetros dinamicamente
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
