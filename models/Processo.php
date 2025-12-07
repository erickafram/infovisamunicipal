<?php
class Processo
{
    private $conn;
    private $lastError;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function createProcesso($estabelecimento_id, $tipo_processo, $ano_licenciamento = null)
    {
        $anoAtual = date('Y');

        // Busca o maior número de processo existente para o ano atual
        $stmt = $this->conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero_processo, '/', -1) AS UNSIGNED)) as max_numero FROM processos WHERE YEAR(data_abertura) = ?");
        $stmt->bind_param("i", $anoAtual);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        // Incrementa o maior número de processo existente
        $maxNumero = $row['max_numero'] ? $row['max_numero'] : 0;
        $proximoNumero = $maxNumero + 1;

        // Formata o número do processo com o ano e o próximo número
        $numero_processo = sprintf("%s/%05d", $anoAtual, $proximoNumero);

        // Se for um processo de licenciamento e não foi especificado o ano de licenciamento, usa o ano atual
        if (strtoupper($tipo_processo) == 'LICENCIAMENTO' && $ano_licenciamento === null) {
            $ano_licenciamento = $anoAtual;
        }

        // Insere o novo processo no banco de dados
        if (strtoupper($tipo_processo) == 'LICENCIAMENTO') {
            $stmt = $this->conn->prepare("INSERT INTO processos (estabelecimento_id, tipo_processo, data_abertura, numero_processo, status, ano_licenciamento) VALUES (?, ?, NOW(), ?, 'ATIVO', ?)");
            $stmt->bind_param("issi", $estabelecimento_id, $tipo_processo, $numero_processo, $ano_licenciamento);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO processos (estabelecimento_id, tipo_processo, data_abertura, numero_processo, status) VALUES (?, ?, NOW(), ?, 'ATIVO')");
            $stmt->bind_param("iss", $estabelecimento_id, $tipo_processo, $numero_processo);
        }
        return $stmt->execute();
    }


    public function getAlertasCountByUsuario($usuario_id)
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND a.status != 'FINALIZADO' AND a.status_estabelecimento = 'pendente'
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }


    public function getAlertasByUsuario($usuario_id)
    {
        $sql = "
            SELECT a.*, p.numero_processo, p.tipo_processo, e.nome_fantasia AS empresa_nome
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND a.status != 'FINALIZADO' AND a.status_estabelecimento = 'pendente'
            ORDER BY a.prazo ASC
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtém alertas pendentes (não tratados pelo estabelecimento)
     *
     * @param int $usuario_id ID do usuário externo
     * @return array Lista de alertas pendentes
     */
    public function getAlertasPendentesByUsuarioExterno($usuario_id)
    {
        $sql = "
            SELECT a.*, p.numero_processo, p.tipo_processo, e.nome_fantasia AS empresa_nome
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND a.status != 'FINALIZADO' AND a.status_estabelecimento = 'pendente'
            ORDER BY a.prazo ASC
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtém alertas tratados pelo estabelecimento
     *
     * @param int $usuario_id ID do usuário externo
     * @return array Lista de alertas tratados
     */
    public function getAlertasTratadosByUsuarioExterno($usuario_id)
    {
        $sql = "
            SELECT a.*, p.numero_processo, p.tipo_processo, e.nome_fantasia AS empresa_nome,
                   ue_tratado.nome_completo AS tratado_por_nome
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            LEFT JOIN usuarios_externos ue_tratado ON a.tratado_por = ue_tratado.id
            WHERE ue.usuario_id = ? AND a.status_estabelecimento = 'tratado'
            ORDER BY a.data_tratamento DESC
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Marca um alerta como tratado pelo estabelecimento
     *
     * @param int $alerta_id ID do alerta
     * @param int $usuario_id ID do usuário externo
     * @param string $observacao Observação opcional do estabelecimento
     * @return bool Resultado da operação
     */
    public function marcarAlertaComoTratado($alerta_id, $usuario_id, $observacao = null)
    {
        $sql = "UPDATE alertas_processo 
                SET status_estabelecimento = 'tratado', 
                    data_tratamento = NOW(), 
                    tratado_por = ?, 
                    observacao_estabelecimento = ? 
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isi", $usuario_id, $observacao, $alerta_id);
        return $stmt->execute();
    }
    
    /**
     * Desmarca um alerta como tratado (volta para pendente)
     *
     * @param int $alerta_id ID do alerta
     * @return bool Resultado da operação
     */
    public function desmarcarAlertaComoTratado($alerta_id)
    {
        $sql = "UPDATE alertas_processo 
                SET status_estabelecimento = 'pendente', 
                    data_tratamento = NULL, 
                    tratado_por = NULL, 
                    observacao_estabelecimento = NULL 
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $alerta_id);
        return $stmt->execute();
    }
    
    /**
     * Marca o status de um alerta (usado pela vigilância sanitária)
     *
     * @param int $alerta_id ID do alerta
     * @param string $status Novo status ('ativo' ou 'finalizado')
     * @return bool Resultado da operação
     */
    public function atualizarStatusAlerta($alerta_id, $status)
    {
        // Se o status for ativo, também redefine o status do estabelecimento para pendente
        if ($status === 'ativo') {
            $sql = "UPDATE alertas_processo 
                    SET status = ?, 
                        status_estabelecimento = 'pendente', 
                        data_tratamento = NULL, 
                        tratado_por = NULL, 
                        observacao_estabelecimento = NULL 
                    WHERE id = ?";
        } else {
            $sql = "UPDATE alertas_processo SET status = ? WHERE id = ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $alerta_id);
        return $stmt->execute();
    }
    
    /**
     * Obtém a contagem de alertas pendentes para um usuário externo
     *
     * @param int $usuario_id ID do usuário externo
     * @return int Contagem de alertas pendentes
     */
    public function getAlertasPendentesCountByUsuarioExterno($usuario_id)
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND a.status != 'FINALIZADO' AND a.status_estabelecimento = 'pendente'
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    /**
     * Obtém todos os alertas (pendentes e tratados) para um usuário externo
     *
     * @param int $usuario_id ID do usuário externo
     * @return array Lista de alertas
     */
    public function getTodosAlertasComStatusEstabelecimento($usuario_id)
    {
        $sql = "
            SELECT a.*, 
                   p.numero_processo, 
                   p.tipo_processo, 
                   e.nome_fantasia AS empresa_nome,
                   ue_tratado.nome_completo AS tratado_por_nome
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            LEFT JOIN usuarios_externos ue_tratado ON a.tratado_por = ue_tratado.id
            WHERE ue.usuario_id = ? AND a.status != 'FINALIZADO'
            ORDER BY a.status_estabelecimento ASC, a.prazo ASC
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtém alertas para a Vigilância Sanitária que foram tratados pelo estabelecimento
     * mas ainda estão ativos (precisam ser verificados)
     * 
     * @param string $municipio Município para filtrar
     * @return array Lista de alertas tratados
     */
    public function getAlertasTratadosPendentesVerificacao($municipio)
    {
        $sql = "
            SELECT a.*, 
                   p.numero_processo, 
                   p.tipo_processo, 
                   e.nome_fantasia,
                   ue_tratado.nome_completo AS tratado_por_nome
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            LEFT JOIN usuarios_externos ue_tratado ON a.tratado_por = ue_tratado.id
            WHERE e.municipio = ? 
              AND a.status = 'ativo' 
              AND a.status_estabelecimento = 'tratado'
            ORDER BY a.data_tratamento ASC
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('s', $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtém a contagem de alertas tratados pendentes de verificação
     *
     * @param string $municipio Município para filtrar
     * @return int Contagem de alertas
     */
    public function getAlertasTratadosPendentesVerificacaoCount($municipio)
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE e.municipio = ? 
              AND a.status = 'ativo' 
              AND a.status_estabelecimento = 'tratado'
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('s', $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }

    public function getProcessosParadosByUsuario($usuario_id)
    {
        $sql = "
            SELECT p.*, e.nome_fantasia AS empresa_nome
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND p.status = 'PARADO' AND p.status != 'FINALIZADO'
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getProcessosParadosCountByUsuario($usuario_id)
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND p.status = 'PARADO'
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }

    public function getProcessosComDocumentacaoPendente($municipioUsuario)
    {
        $query = "
        SELECT 
        p.id AS processo_id, 
        p.numero_processo, 
        e.id AS estabelecimento_id, 
        e.nome_fantasia, 
        MIN(d.data_upload) AS data_upload_pendente, 
        d.status
    FROM processos p
    JOIN documentos d ON p.id = d.processo_id
    JOIN estabelecimentos e ON p.estabelecimento_id = e.id
    WHERE d.status = 'pendente' AND e.municipio = ?
    GROUP BY p.id, p.numero_processo, e.id, e.nome_fantasia, d.status
    ORDER BY data_upload_pendente ASC
";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $municipioUsuario);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    public function createProcessoLicenciamento($estabelecimento_id)
    {
        $anoAtual = date('Y');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM processos WHERE YEAR(data_abertura) = ?");
        $stmt->bind_param("i", $anoAtual);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = $row['total'] + 1;

        $numero_processo = sprintf("%s/%05d", $anoAtual, $total);

        $stmt = $this->conn->prepare("INSERT INTO processos (estabelecimento_id, tipo_processo, data_abertura, numero_processo, status) VALUES (?, 'LICENCIAMENTO', NOW(), ?, 'ATIVO')");
        $stmt->bind_param("is", $estabelecimento_id, $numero_processo);
        return $stmt->execute();
    }


    public function createProcessoProjetoArquitetonico($estabelecimento_id)
    {
        $anoAtual = date('Y');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM processos WHERE YEAR(data_abertura) = ?");
        $stmt->bind_param("i", $anoAtual);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = $row['total'] + 1;

        $numero_processo = sprintf("%s/%05d", $anoAtual, $total);

        $stmt = $this->conn->prepare("INSERT INTO processos (estabelecimento_id, tipo_processo, data_abertura, numero_processo, status) VALUES (?, 'PROJETO ARQUITETÔNICO', NOW(), ?, 'ATIVO')");
        $stmt->bind_param("is", $estabelecimento_id, $numero_processo);
        return $stmt->execute();
    }



    public function checkProcessoExistente($estabelecimento_id, $anoAtual, $anoLicenciamento = null)
    {
        // Se não for especificado o ano de licenciamento, usa o ano atual
        if ($anoLicenciamento === null) {
            $anoLicenciamento = $anoAtual;
        }

        // Verifica apenas se existe um processo para o mesmo ano de licenciamento,
        // independente do ano de criação do processo
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM processos WHERE estabelecimento_id = ? AND tipo_processo = 'LICENCIAMENTO' AND ano_licenciamento = ?");
        $stmt->bind_param("ii", $estabelecimento_id, $anoLicenciamento);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] > 0;
    }

    public function searchProcessosPorMunicipio($search, $municipio, $isAdmin)
    {
        if ($isAdmin) {
            $query = "SELECT p.*, e.nome_fantasia, e.cnpj 
                      FROM processos p 
                      JOIN estabelecimentos e ON p.estabelecimento_id = e.id 
                      WHERE p.numero_processo LIKE ? OR e.nome_fantasia LIKE ? OR e.cnpj LIKE ?";

            $stmt = $this->conn->prepare($query);
            $searchParam = "%" . $search . "%";
            $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
        } else {
            $query = "SELECT p.*, e.nome_fantasia, e.cnpj 
                      FROM processos p 
                      JOIN estabelecimentos e ON p.estabelecimento_id = e.id 
                      WHERE e.municipio = ? AND (p.numero_processo LIKE ? OR e.nome_fantasia LIKE ? OR e.cnpj LIKE ?)";

            $stmt = $this->conn->prepare($query);
            $searchParam = "%" . $search . "%";
            $stmt->bind_param("ssss", $municipio, $searchParam, $searchParam, $searchParam);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $processos = [];
        while ($row = $result->fetch_assoc()) {
            $processos[] = $row;
        }

        return $processos;
    }



    public function getProcessosByEstabelecimento($estabelecimento_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM processos WHERE estabelecimento_id = ?");
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getProcessosAcompanhados($usuario_id)
    {
        $sql = "
        SELECT p.*, e.nome_fantasia, e.cnpj
        FROM processos_acompanhados pa
        JOIN processos p ON pa.processo_id = p.id
        JOIN estabelecimentos e ON p.estabelecimento_id = e.id
        WHERE pa.usuario_id = ?
    ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getProcessosResponsaveisPorUsuario($usuario_id)
    {
        $sql = "SELECT p.id, p.numero_processo, e.nome_fantasia, pr.descricao, pr.status, p.estabelecimento_id
            FROM processos_responsaveis pr
            JOIN processos p ON pr.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE pr.usuario_id = ? AND pr.status = 'pendente'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllProcessos()
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nome_fantasia 
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            ORDER BY p.data_abertura DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getProcessosParados($municipio)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nome_fantasia 
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE p.status = 'PARADO' AND e.municipio = ?
        ");
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function findById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nome_fantasia, e.nome, e.ramo_atividade, e.cnpj, e.tipo_pessoa, e.cpf, e.logradouro, e.numero, e.complemento, e.bairro, e.ddd_telefone_1, e.ddd_telefone_2, e.municipio 
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }



    public function deleteProcesso($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM processos WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function archiveProcesso($id)
    {
        $stmt = $this->conn->prepare("UPDATE processos SET status = 'ARQUIVADO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function searchProcessosPorMunicipioPaginacao($search, $municipio, $isAdmin, $limit, $offset, $pendentes = false, $status = '', $tipo_processo = '', $grupo_risco = '')
    {
        $sql = "
            WITH GruposRisco AS (
                SELECT DISTINCT e.id AS estabelecimento_id, 
                GROUP_CONCAT(DISTINCT gr.descricao ORDER BY gr.descricao SEPARATOR ', ') as grupos_risco
                FROM estabelecimentos e
                LEFT JOIN atividade_grupo_risco agr_fiscal
                    ON REPLACE(REPLACE(REPLACE(e.cnae_fiscal, '.', ''), '-', ''), '/', '') = REPLACE(REPLACE(REPLACE(agr_fiscal.cnae, '.', ''), '-', ''), '/', '')
                    AND e.municipio = agr_fiscal.municipio
                LEFT JOIN grupo_risco gr_fiscal ON agr_fiscal.grupo_risco_id = gr_fiscal.id
                LEFT JOIN JSON_TABLE(e.cnaes_secundarios, '$[*]' COLUMNS (
                    cnae_sec JSON PATH '$.codigo'
                )) cnaes_secundarios ON 1=1
                LEFT JOIN atividade_grupo_risco agr_secundario
                    ON REPLACE(REPLACE(REPLACE(JSON_UNQUOTE(cnaes_secundarios.cnae_sec), '.', ''), '-', ''), '/', '') = REPLACE(REPLACE(REPLACE(agr_secundario.cnae, '.', ''), '-', ''), '/', '')
                    AND e.municipio = agr_secundario.municipio
                LEFT JOIN grupo_risco gr_secundario ON agr_secundario.grupo_risco_id = gr_secundario.id
                LEFT JOIN grupo_risco gr ON gr.id = COALESCE(gr_fiscal.id, gr_secundario.id)
                WHERE gr.descricao IS NOT NULL
                GROUP BY e.id
            )
            SELECT p.*, e.nome_fantasia, e.cnpj, e.cpf, e.municipio,
                   (SELECT COUNT(*) FROM documentos d WHERE d.processo_id = p.id AND d.status = 'pendente') as documentos_pendentes,
                   COALESCE(gr.grupos_risco, '') as grupos_risco
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            LEFT JOIN GruposRisco gr ON e.id = gr.estabelecimento_id
            WHERE 1=1";

        $params = [];
        $types = "";

        if (!$isAdmin) {
            $sql .= " AND e.municipio = ?";
            $params[] = $municipio;
            $types .= "s";
        }

        if (!empty($search)) {
            $sql .= " AND (p.numero_processo LIKE ? OR e.nome_fantasia LIKE ? OR e.cnpj LIKE ? OR e.cpf LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            $types .= "ssss";
        }

        if ($pendentes) {
            $sql .= " AND EXISTS (SELECT 1 FROM documentos d WHERE d.processo_id = p.id AND d.status = 'pendente')";
        }

        if (!empty($status)) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if (!empty($tipo_processo)) {
            $sql .= " AND p.tipo_processo = ?";
            $params[] = $tipo_processo;
            $types .= "s";
        }

        if (!empty($grupo_risco)) {
            if ($grupo_risco === 'sem') {
                $sql .= " AND (gr.grupos_risco IS NULL OR gr.grupos_risco = '')";
            } else {
                $sql .= " AND gr.grupos_risco LIKE ?";
                $params[] = "%Grupo $grupo_risco%";
                $types .= "s";
            }
        }

        $sql .= " ORDER BY 
            CASE 
                WHEN grupos_risco LIKE '%Grupo 3%' THEN 1
                WHEN grupos_risco LIKE '%Grupo 2%' THEN 2
                WHEN grupos_risco LIKE '%Grupo 1%' THEN 3
                ELSE 4
            END,
            p.data_abertura DESC 
            LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    public function countProcessosPorMunicipio($search, $municipio, $isAdmin, $pendentes = false, $status = '', $tipo_processo = '', $grupo_risco = '')
    {
        $sql = "
            WITH GruposRisco AS (
                SELECT DISTINCT e.id AS estabelecimento_id, 
                GROUP_CONCAT(DISTINCT gr.descricao ORDER BY gr.descricao SEPARATOR ', ') as grupos_risco
                FROM estabelecimentos e
                LEFT JOIN atividade_grupo_risco agr_fiscal
                    ON REPLACE(REPLACE(REPLACE(e.cnae_fiscal, '.', ''), '-', ''), '/', '') = REPLACE(REPLACE(REPLACE(agr_fiscal.cnae, '.', ''), '-', ''), '/', '')
                    AND e.municipio = agr_fiscal.municipio
                LEFT JOIN grupo_risco gr_fiscal ON agr_fiscal.grupo_risco_id = gr_fiscal.id
                LEFT JOIN JSON_TABLE(e.cnaes_secundarios, '$[*]' COLUMNS (
                    cnae_sec JSON PATH '$.codigo'
                )) cnaes_secundarios ON 1=1
                LEFT JOIN atividade_grupo_risco agr_secundario
                    ON REPLACE(REPLACE(REPLACE(JSON_UNQUOTE(cnaes_secundarios.cnae_sec), '.', ''), '-', ''), '/', '') = REPLACE(REPLACE(REPLACE(agr_secundario.cnae, '.', ''), '-', ''), '/', '')
                    AND e.municipio = agr_secundario.municipio
                LEFT JOIN grupo_risco gr_secundario ON agr_secundario.grupo_risco_id = gr_secundario.id
                LEFT JOIN grupo_risco gr ON gr.id = COALESCE(gr_fiscal.id, gr_secundario.id)
                WHERE gr.descricao IS NOT NULL
                GROUP BY e.id
            )
            SELECT COUNT(*) as total
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            LEFT JOIN GruposRisco gr ON e.id = gr.estabelecimento_id
            WHERE 1=1";

        $params = [];
        $types = "";

        if (!$isAdmin) {
            $sql .= " AND e.municipio = ?";
            $params[] = $municipio;
            $types .= "s";
        }

        if (!empty($search)) {
            $sql .= " AND (p.numero_processo LIKE ? OR e.nome_fantasia LIKE ? OR e.cnpj LIKE ? OR e.cpf LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            $types .= "ssss";
        }

        if ($pendentes) {
            $sql .= " AND EXISTS (SELECT 1 FROM documentos d WHERE d.processo_id = p.id AND d.status = 'pendente')";
        }

        if (!empty($status)) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if (!empty($tipo_processo)) {
            $sql .= " AND p.tipo_processo = ?";
            $params[] = $tipo_processo;
            $types .= "s";
        }

        if (!empty($grupo_risco)) {
            if ($grupo_risco === 'sem') {
                $sql .= " AND (gr.grupos_risco IS NULL OR gr.grupos_risco = '')";
            } else {
                $sql .= " AND gr.grupos_risco LIKE ?";
                $params[] = "%Grupo $grupo_risco%";
                $types .= "s";
            }
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['total'];
    }



    public function unarchiveProcesso($id)
    {
        $stmt = $this->conn->prepare("UPDATE processos SET status = 'ATIVO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function stopProcesso($id, $motivo)
    {
        $stmt = $this->conn->prepare("UPDATE processos SET status = 'PARADO', motivo_parado = ? WHERE id = ?");
        $stmt->bind_param("si", $motivo, $id);
        return $stmt->execute();
    }

    public function getEstabelecimentoIdByProcessoId($processo_id)
    {
        $stmt = $this->conn->prepare("SELECT estabelecimento_id FROM processos WHERE id = ?");
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['estabelecimento_id'] : null;
    }


    public function restartProcesso($id)
    {
        $stmt = $this->conn->prepare("UPDATE processos SET status = 'ATIVO', motivo_parado = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getProcessosByUsuario($usuario_id)
    {
        $sql = "
        SELECT p.*, e.nome_fantasia
        FROM processos p
        JOIN estabelecimentos e ON p.estabelecimento_id = e.id
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
        WHERE ue.usuario_id = ?
        ORDER BY p.data_abertura DESC
    ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function searchProcessos($search)
    {
        $search = "%$search%";
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nome_fantasia, e.cnpj 
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE p.numero_processo LIKE ? OR e.nome_fantasia LIKE ? OR e.cnpj LIKE ?
            ORDER BY p.data_abertura DESC
        ");
        $stmt->bind_param("sss", $search, $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getProcessoByNumero($numeroProcesso)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nome_fantasia, e.cnpj 
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE p.numero_processo = ?
        ");
        $stmt->bind_param("s", $numeroProcesso);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getProcessoByCnpj($cnpj)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nome_fantasia, e.cnpj 
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE e.cnpj = ? AND p.tipo_processo != 'DENÚNCIA'
        ");
        $stmt->bind_param("s", $cnpj);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }


    public function getLastError()
    {
        return $this->lastError;
    }

    public function createAlerta($processo_id, $descricao, $prazo)
    {
        $sql = "INSERT INTO alertas_processo (processo_id, descricao, prazo) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iss", $processo_id, $descricao, $prazo);
        return $stmt->execute();
    }

    public function getAlertasByProcesso($processo_id)
    {
        $sql = "SELECT * FROM alertas_processo WHERE processo_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getAlertasVencidos($municipioUsuario)
    {
        $sql = "
            SELECT a.*, p.numero_processo, e.nome_fantasia
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE a.prazo < NOW() AND a.status != 'finalizado' AND e.municipio = ?
        ";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }

        $stmt->bind_param('s', $municipioUsuario);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    public function getAlertasProximosAVencer($municipioUsuario, $limit = null)
    {
        $sql = "
            SELECT a.*, p.numero_processo, e.nome_fantasia, e.id AS estabelecimento_id, DATEDIFF(a.prazo, NOW()) AS dias_restantes
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE a.prazo BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_ADD(NOW(), INTERVAL 5 DAY) 
            AND a.status != 'finalizado' AND e.municipio = ?
        ";

        if ($limit !== null) {
            $sql .= " LIMIT ?";
        }

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }

        if ($limit !== null) {
            $stmt->bind_param('si', $municipioUsuario, $limit);
        } else {
            $stmt->bind_param('s', $municipioUsuario);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalAlertasProximosAVencer($municipioUsuario)
    {
        $sql = "
        SELECT COUNT(*) AS total
        FROM alertas_processo a
        JOIN processos p ON a.processo_id = p.id
        JOIN estabelecimentos e ON p.estabelecimento_id = e.id
        WHERE a.prazo BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_ADD(NOW(), INTERVAL 5 DAY) 
        AND a.status != 'finalizado' AND e.municipio = ?
    ";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }

        $stmt->bind_param('s', $municipioUsuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    public function getProcessosDesignadosPendentes($usuario_id)
    {
        $sql = "
        SELECT pr.*, p.numero_processo, e.nome_fantasia, e.id as estabelecimento_id
        FROM processos_responsaveis pr
        JOIN processos p ON pr.processo_id = p.id
        JOIN estabelecimentos e ON p.estabelecimento_id = e.id
        WHERE pr.usuario_id = ? AND pr.status = 'pendente'
    ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function marcarComoResolvido($processo_id, $usuario_id)
    {
        $sql = "UPDATE processos_responsaveis SET status = 'resolvido' WHERE processo_id = ? AND usuario_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $processo_id, $usuario_id);
        return $stmt->execute();
    }



    public function getTodosAlertas($municipioUsuario)
    {
        $sql = "
            SELECT a.*, p.numero_processo, e.nome_fantasia, e.id AS estabelecimento_id
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE e.municipio = ? AND a.status = 'ativo'
        ";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }

        $stmt->bind_param('s', $municipioUsuario);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // A função getAlertasVencidos já existe na linha 642


    public function getAlertaById($id)
    {
        $sql = "SELECT a.*, p.numero_processo, p.tipo_processo, e.nome_fantasia, e.id AS estabelecimento_id
                FROM alertas_processo a
                JOIN processos p ON a.processo_id = p.id
                JOIN estabelecimentos e ON p.estabelecimento_id = e.id
                WHERE a.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function updateAlerta($id, $descricao = null, $prazo = null, $status = null)
    {
        $fields = [];
        $params = [];
        $types = '';

        if ($descricao !== null) {
            $fields[] = 'descricao = ?';
            $params[] = $descricao;
            $types .= 's';
        }

        if ($prazo !== null) {
            $fields[] = 'prazo = ?';
            $params[] = $prazo;
            $types .= 's';
        }

        if ($status !== null) {
            $fields[] = 'status = ?';
            $params[] = $status;
            $types .= 's';
        }

        if (empty($fields)) {
            return false; // Nothing to update
        }

        $params[] = $id;
        $types .= 'i';

        $sql = 'UPDATE alertas_processo SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    public function getNumeroProcesso($processo_id)
    {
        $stmt = $this->conn->prepare("SELECT numero_processo FROM processos WHERE id = ?");
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $processo = $result->fetch_assoc();
        return $processo ? $processo['numero_processo'] : null;
    }


    public function getProcessoById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nome_fantasia, e.cnpj, e.logradouro, e.numero, e.complemento, e.bairro, e.ddd_telefone_1, e.ddd_telefone_2, e.municipio 
            FROM processos p
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getDocumentosByProcesso($processoId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM documentos WHERE processo_id = ?");
        $stmt->bind_param("i", $processoId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function getEstabelecimentosByUsuario($usuarioId)
    {
        $query = "
        SELECT ue.estabelecimento_id
        FROM usuarios_estabelecimentos ue
        WHERE ue.usuario_id = ?
    ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function deleteAlerta($id)
    {
        $sql = "DELETE FROM alertas_processo WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Obtém alertas marcados como resolvidos pelo usuário
     *
     * @param int $usuario_id ID do usuário
     * @return array Lista de alertas resolvidos
     */
    public function getAlertasResolvidosByUsuario($usuario_id)
    {
        $sql = "
            SELECT a.*, p.numero_processo, p.tipo_processo, e.nome_fantasia AS empresa_nome, ar.data_resolucao
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            JOIN alertas_resolvidos ar ON a.id = ar.alerta_id
            WHERE ue.usuario_id = ? AND ar.usuario_id = ?
            ORDER BY ar.data_resolucao DESC
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('ii', $usuario_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtém todos os alertas (resolvidos e não resolvidos) para o usuário
     * com indicação de quais estão resolvidos
     *
     * @param int $usuario_id ID do usuário
     * @return array Lista de alertas
     */
    public function getTodosAlertasComStatus($usuario_id)
    {
        $sql = "
            SELECT 
                a.*, 
                p.numero_processo, 
                p.tipo_processo, 
                e.nome_fantasia AS empresa_nome,
                CASE WHEN ar.alerta_id IS NOT NULL THEN 1 ELSE 0 END as resolvido,
                ar.data_resolucao
            FROM alertas_processo a
            JOIN processos p ON a.processo_id = p.id
            JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            LEFT JOIN alertas_resolvidos ar ON a.id = ar.alerta_id AND ar.usuario_id = ?
            WHERE ue.usuario_id = ? AND a.status != 'FINALIZADO'
            ORDER BY resolvido ASC, a.prazo ASC
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }
        $stmt->bind_param('ii', $usuario_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
