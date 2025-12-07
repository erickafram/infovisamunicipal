<?php
class Relatorios
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getMunicipios($nivel_acesso, $municipioUsuario)
    {
        if ($nivel_acesso == 1) {
            $stmt = $this->conn->prepare("SELECT DISTINCT municipio FROM estabelecimentos");
        } else {
            $stmt = $this->conn->prepare("SELECT DISTINCT municipio FROM estabelecimentos WHERE municipio = ?");
            $stmt->bind_param("s", $municipioUsuario);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getEstabelecimentosByMunicipio($municipio, $data_inicio, $data_fim)
    {
        $stmt = $this->conn->prepare("SELECT * FROM estabelecimentos WHERE municipio = ? AND data_cadastro BETWEEN ? AND ?");
        $stmt->bind_param("sss", $municipio, $data_inicio, $data_fim);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    public function getAtividades($nivel_acesso, $municipioUsuario)
    {
        $atividades = [];

        // Consulta base para atividades principais e secundárias
        $baseQuery = "SELECT DISTINCT cnae_fiscal, cnae_fiscal_descricao FROM estabelecimentos";
        // AQUI DEVERIA APARECER NO RELATORIO OS DADOS DO ESTABELECIMENTO SE U USARIO ESCOLHER A ATIVIDADE SECUNDARIA 
        //$baseQuery = "SELECT DISTINCT cnae_fiscal, cnae_fiscal_descricao, cnaes_secundarios FROM estabelecimentos";

        // Ajuste da query conforme o nível de acesso
        if ($nivel_acesso == 1) {
            $query = $baseQuery;
            $stmt = $this->conn->prepare($query);
        } else {
            $query = $baseQuery . " WHERE municipio = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $municipioUsuario);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Atividades principais
            $atividades[$row['cnae_fiscal']] = $row['cnae_fiscal_descricao'];

            // Atividades secundárias
            if (!empty($row['cnaes_secundarios'])) {
                $cnaesSecundarios = json_decode($row['cnaes_secundarios'], true);
                foreach ($cnaesSecundarios as $cnae) {
                    $atividades[$cnae['codigo']] = $cnae['descricao'];
                }
            }
        }

        // Remover duplicatas e ordenar as atividades
        $atividades = array_unique($atividades);
        asort($atividades);

        return $atividades;
    }

    public function getAcoesExecutadasComFiltro($data_inicio, $data_fim, $atividade_sia, $tipo_acao)
    {
        $query = "
        SELECT 
            os.id AS ordem_id, 
            os.data_inicio, 
            os.data_fim, 
            os.status, 
            tae.descricao AS acao_descricao, 
            tae.codigo_procedimento, 
            tae.atividade_sia, 
            e.nome_fantasia AS estabelecimento
        FROM 
            ordem_servico os
        INNER JOIN 
            tipos_acoes_executadas tae 
            ON JSON_CONTAINS(os.acoes_executadas, JSON_QUOTE(CAST(tae.id AS CHAR)), '$')
        LEFT JOIN 
            estabelecimentos e 
            ON os.estabelecimento_id = e.id
        WHERE 
            os.status = 'finalizada'
            AND os.data_inicio BETWEEN ? AND ?
    ";

        if ($atividade_sia !== 'todos') {
            $query .= " AND tae.atividade_sia = ?";
        }
        if ($tipo_acao !== 'todos') {
            $query .= " AND tae.id = ?";
        }

        $query .= " ORDER BY os.data_inicio ASC";

        $stmt = $this->conn->prepare($query);

        $params = [$data_inicio, $data_fim];
        $types = "ss";

        if ($atividade_sia !== 'todos') {
            $params[] = $atividade_sia;
            $types .= "i";
        }
        if ($tipo_acao !== 'todos') {
            $params[] = $tipo_acao;
            $types .= "i";
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getTiposAcoes()
    {
        $stmt = $this->conn->prepare("SELECT id, descricao FROM tipos_acoes_executadas");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    public function getEstabelecimentosByAtividades($atividades, $nivel_acesso, $municipioUsuario)
    {
        $placeholders = str_repeat('?,', count($atividades) - 1) . '?';
        $query = "
            SELECT e.id, e.cnpj, e.nome_fantasia, e.razao_social, e.ddd_telefone_1, e.cnae_fiscal_descricao, e.cnaes_secundarios
            FROM estabelecimentos e
            WHERE (e.cnae_fiscal IN ($placeholders)
            OR JSON_CONTAINS(e.cnaes_secundarios, JSON_ARRAY(" . implode(',', array_fill(0, count($atividades), '?')) . ")))";

        // Ajuste da query conforme o nível de acesso
        if ($nivel_acesso != 1) {
            $query .= " AND e.municipio = ?";
        }

        $stmt = $this->conn->prepare($query);

        // Criar uma array com os tipos de dados para bind_param
        $types = str_repeat('s', count($atividades) * 2);
        if ($nivel_acesso != 1) {
            $types .= 's';
        }

        // Criar uma array com os parâmetros para bind_param
        $params = array_merge($atividades, $atividades);
        if ($nivel_acesso != 1) {
            $params[] = $municipioUsuario;
        }

        // Usar a função call_user_func_array para passar os parâmetros para bind_param
        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
