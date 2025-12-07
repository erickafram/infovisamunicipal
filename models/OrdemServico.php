<?php
class OrdemServico
{
    private $conn;
    private $lastError;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }
    public function create($estabelecimento_id, $processo_id, $data_inicio, $data_fim, $acoes_executadas, $tecnicos, $pdf_path, $municipio, $status = 'ativa', $observacao = null, $pdf_upload = null)
    {
        $acoes_executadas_json = json_encode($acoes_executadas, JSON_UNESCAPED_UNICODE);
        $stmt = $this->conn->prepare(
            "INSERT INTO ordem_servico (estabelecimento_id, processo_id, data_inicio, data_fim, acoes_executadas, tecnicos, pdf_path, status, observacao, pdf_upload) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }

        $stmt->bind_param(
            "iissssssss",
            $estabelecimento_id,
            $processo_id,
            $data_inicio,
            $data_fim,
            $acoes_executadas_json,
            $tecnicos,
            $pdf_path,
            $status,
            $observacao,
            $pdf_upload
        );

        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }


    public function getPontuacaoMensal($tecnico_id, $mes, $ano)
    {
        $query = "
            SELECT SUM(pontuacao) AS pontuacao_total
            FROM pontuacao_tecnicos
            WHERE tecnico_id = ? AND MONTH(data) = ? AND YEAR(data) = ?
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            // Exibe a mensagem de erro se a preparação da consulta falhar
            die("Erro na preparação da consulta: " . $this->conn->error);
        }

        $stmt->bind_param("iii", $tecnico_id, $mes, $ano);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            // Exibe a mensagem de erro se a execução da consulta falhar
            die("Erro na execução da consulta: " . $this->conn->error);
        }

        $row = $result->fetch_assoc();
        return $row['pontuacao_total'] ?: 0;
    }


    public function calcularPontuacao($estabelecimento_id, $acoes_executadas, $municipio)
    {
        $pontuacoes = []; // Guardará a pontuação de cada ação

        // Obter informações básicas do estabelecimento para identificar seu tipo (físico ou jurídico)
        $stmt = $this->conn->prepare("SELECT tipo_pessoa, cnae_fiscal, cnaes_secundarios FROM estabelecimentos WHERE id = ?");
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $estab = $result->fetch_assoc();
        
        $todos_cnaes = [];
        
        // Verificar o tipo de pessoa (física ou jurídica)
        $tipo_pessoa = $estab['tipo_pessoa'] ?? 'juridica';
        
        if ($tipo_pessoa == 'fisica') {
            // Para pessoa física, buscar CNAEs na tabela estabelecimento_cnaes
            $stmt = $this->conn->prepare("SELECT cnae FROM estabelecimento_cnaes WHERE estabelecimento_id = ?");
            $stmt->bind_param("i", $estabelecimento_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $todos_cnaes[] = $row['cnae'];
            }
        } else {
            // Para pessoa jurídica, usar o método original
            $cnae_principal = $estab['cnae_fiscal'] ?? '';
            $cnaes_secundarios = json_decode($estab['cnaes_secundarios'] ?? '[]', true);

            // Montar array com todos os CNAEs (principal e secundários)
            $todos_cnaes = [$cnae_principal];
            if (is_array($cnaes_secundarios)) {
                foreach ($cnaes_secundarios as $atividade) {
                    // Certifique-se de que existe a chave 'codigo'
                    if (isset($atividade['codigo'])) {
                        $todos_cnaes[] = (string)$atividade['codigo'];
                    }
                }
            }
        }
        
        // Se não houver CNAEs, retornar pontuação zero para todas as ações
        if (empty($todos_cnaes)) {
            foreach ($acoes_executadas as $acao_id) {
                $pontuacoes[$acao_id] = 0;
            }
            return $pontuacoes;
        }

        // Agora que $todos_cnaes está definido, podemos usá-lo na query
        foreach ($acoes_executadas as $acao_id) {
            // Criar placeholders para os CNAEs
            $placeholders = implode(',', array_fill(0, count($todos_cnaes), '?'));

            $query = "
                SELECT MAX(ap.pontuacao) AS pontuacao
                FROM acoes_pontuacao ap
                INNER JOIN grupo_risco gr ON ap.grupo_risco_id = gr.id
                WHERE ap.acao_id = ?
                  AND ap.municipio = ?
                  AND gr.id IN (
                    SELECT agr.grupo_risco_id
                    FROM atividade_grupo_risco agr
                    WHERE agr.cnae IN ($placeholders)
                      AND agr.municipio = ?
                )
            ";

            // Monta a string de tipos para bind_param
            // 'i' para acao_id, 's' para municipio, então 's' para cada CNAE e mais um 's' para o municipio no final
            $types = 'is' . str_repeat('s', count($todos_cnaes)) . 's';
            // Parâmetros: acao_id, municipio, todos_cnaes..., municipio
            $params = array_merge([$acao_id, $municipio], $todos_cnaes, [$municipio]);

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                $this->lastError = $this->conn->error;
                return $pontuacoes;
            }

            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $pontuacoes[$acao_id] = $row['pontuacao'] ?: 0;
            } else {
                $pontuacoes[$acao_id] = 0;
            }
        }

        return $pontuacoes; // Retorna um array com as pontuações por ação
    }


    public function calcularPontuacaoSemEstabelecimento($acoes_executadas, $municipio)
    {
        $pontuacoes = [];

        foreach ($acoes_executadas as $acao_id) {
            $query = "
                SELECT MAX(ap.pontuacao) AS pontuacao
                FROM acoes_pontuacao ap
                WHERE ap.acao_id = ? AND ap.municipio = ?
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("is", $acao_id, $municipio);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $pontuacoes[$acao_id] = $row['pontuacao'] ?: 0;
            } else {
                // Caso não haja nenhum resultado, definir pontuação como 0
                $pontuacoes[$acao_id] = 0;
            }
        }

        return $pontuacoes;
    }


    public function deleteOrdem($id)
    {
        // Obter informações da ordem de serviço antes de excluir
        $ordem = $this->getOrdemById($id);
        if ($ordem) {
            $tecnicos_ids = json_decode($ordem['tecnicos'], true);

            // Remover a pontuação dos técnicos associados à ordem de serviço
            if (is_array($tecnicos_ids)) {
                foreach ($tecnicos_ids as $tecnico_id) {
                    $this->removerPontuacaoTecnicoPorOrdem($tecnico_id, $id);
                }
            }

            // Excluir a ordem de serviço
            $query = "DELETE FROM ordem_servico WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                return true;
            } else {
                $this->lastError = $stmt->error;
                return false;
            }
        } else {
            $this->lastError = "Ordem de serviço não encontrada.";
            return false;
        }
    }



    public function salvarPontuacaoTecnico($tecnico_id, $pontuacao, $ordem_id, $acao_id)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO pontuacao_tecnicos (tecnico_id, pontuacao, data, ordem_id, acao_id) VALUES (?, ?, NOW(), ?, ?)"
        );
        if ($stmt === false) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("iiii", $tecnico_id, $pontuacao, $ordem_id, $acao_id);
        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }
    public function reiniciarOrdem($id)
    {
        // Obter informações da ordem de serviço antes de reiniciar
        $ordem = $this->getOrdemById($id);
        if ($ordem) {
            $tecnicos_ids = json_decode($ordem['tecnicos'], true);

            // Remover a pontuação dos técnicos associados à ordem de serviço
            if (is_array($tecnicos_ids)) {
                foreach ($tecnicos_ids as $tecnico_id) {
                    $this->removerPontuacaoTecnicoPorOrdem($tecnico_id, $id);
                }
            }

            // Atualizar o status da ordem de serviço para 'ativa' e limpar a descrição de encerramento
            $query = "UPDATE ordem_servico SET status = 'ativa', descricao_encerramento = NULL WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                return true;
            } else {
                $this->lastError = $stmt->error;
                return false;
            }
        } else {
            $this->lastError = "Ordem de serviço não encontrada.";
            return false;
        }
    }



    public function removerPontuacaoTecnicoPorOrdem($tecnico_id, $ordem_id)
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM pontuacao_tecnicos WHERE tecnico_id = ? AND ordem_id = ?"
        );
        if ($stmt === false) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("ii", $tecnico_id, $ordem_id);
        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }

    public function removerPontuacaoTecnico($tecnico_id)
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM pontuacao_tecnicos WHERE tecnico_id = ?"
        );
        if ($stmt === false) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("i", $tecnico_id);
        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }




    public function getOrdensByProcesso($processo_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM ordem_servico WHERE processo_id = ?");
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getOrdensByTecnico($tecnico_id)
    {
        $stmt = $this->conn->prepare(
            "SELECT os.*, e.razao_social, e.nome_fantasia 
             FROM ordem_servico os 
             JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
             WHERE JSON_CONTAINS(os.tecnicos, JSON_QUOTE(?), '$') AND os.status = 'ativa'"
        );
        $stmt->bind_param("s", $tecnico_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllOrdens($search = '', $limit = 10, $offset = 0)
    {
        $search_query = '';
        if (!empty($search)) {
            $search_query = "WHERE os.id LIKE ? OR e.razao_social LIKE ? OR e.nome_fantasia LIKE ?";
            $search_param = '%' . $search . '%';
        }

        $query = "SELECT os.*, e.razao_social, e.nome_fantasia 
                  FROM ordem_servico os 
                  LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
                  $search_query
                  LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);

        if (!empty($search)) {
            $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $limit, $offset);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $ordens = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($ordens as &$ordem) {
            $tecnicos_ids = json_decode($ordem['tecnicos']);
            $ordem['tecnicos_nomes'] = $this->getTecnicosNomes($tecnicos_ids);
        }

        return $ordens;
    }

    public function getOrdensCount($search = '')
    {
        $search_query = '';
        if (!empty($search)) {
            $search_query = "WHERE os.id LIKE ? OR e.razao_social LIKE ? OR e.nome_fantasia LIKE ?";
            $search_param = '%' . $search . '%';
        }

        $query = "SELECT COUNT(*) AS total 
                  FROM ordem_servico os 
                  LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
                  $search_query";
        $stmt = $this->conn->prepare($query);

        if (!empty($search)) {
            $stmt->bind_param("sss", $search_param, $search_param, $search_param);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['total'];
    }

    public function getOrdensByMunicipio($municipio, $search = '', $limit = 10, $offset = 0)
    {
        $search_query = '';
        $params = [];
        $types = '';

        if (!empty($search)) {
            // Adicionar a busca pelo formato id.ano
            $search_query = "AND (
                os.id LIKE ? 
                OR CONCAT(os.id, '.', YEAR(os.data_inicio)) LIKE ? 
                OR e.razao_social LIKE ? 
                OR e.nome_fantasia LIKE ?
            )";
            $search_param = '%' . $search . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ssss';
        }

        $params = array_merge([$municipio, $municipio], $params, [$limit, $offset]);
        $types = 'ss' . $types . 'ii';

        $query = "SELECT os.*, e.razao_social, e.nome_fantasia 
                  FROM ordem_servico os 
                  LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
                  WHERE (
                    e.municipio = ? 
                    OR (
                      os.estabelecimento_id IS NULL 
                      AND EXISTS (
                        SELECT 1 FROM usuarios u 
                        WHERE JSON_CONTAINS(os.tecnicos, JSON_QUOTE(CAST(u.id AS CHAR)), '$') 
                        AND u.municipio = ?
                      )
                    )
                  ) $search_query
                  ORDER BY os.id DESC
                  LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);

        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();
        $ordens = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($ordens as &$ordem) {
            $tecnicos_ids = json_decode($ordem['tecnicos']);
            $ordem['tecnicos_nomes'] = $this->getTecnicosNomes($tecnicos_ids);
        }

        return $ordens;
    }

    public function getOrdensCountByMunicipio($municipio, $search = '')
    {
        $search_query = '';
        $params = [];
        $types = 'ss';

        if (!empty($search)) {
            $search_query = "AND (
                os.id LIKE ? 
                OR CONCAT(os.id, '.', YEAR(os.data_inicio)) LIKE ? 
                OR e.razao_social LIKE ? 
                OR e.nome_fantasia LIKE ?
            )";
            $search_param = '%' . $search . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ssss';
        }

        $params = array_merge([$municipio, $municipio], $params);

        $query = "SELECT COUNT(*) AS total 
                  FROM ordem_servico os 
                  LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
                  WHERE (
                    e.municipio = ? 
                    OR (
                      os.estabelecimento_id IS NULL 
                      AND EXISTS (
                        SELECT 1 FROM usuarios u 
                        WHERE JSON_CONTAINS(os.tecnicos, JSON_QUOTE(CAST(u.id AS CHAR)), '$') 
                        AND u.municipio = ?
                      )
                    )
                  ) $search_query";
        $stmt = $this->conn->prepare($query);

        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['total'];
    }


    public function getDescricoesAcoesExecutadas($ids_acoes)
    {
        if (empty($ids_acoes)) {
            return [];
        }

        $ids_acoes_str = implode(',', array_map('intval', $ids_acoes));
        $query = "SELECT descricao FROM tipos_acoes_executadas WHERE id IN ($ids_acoes_str)";
        $result = $this->conn->query($query);

        $descricoes_acoes = [];
        while ($row = $result->fetch_assoc()) {
            $descricoes_acoes[] = $row['descricao'];
        }

        return $descricoes_acoes;
    }

    public function getOrdemById($id)
    {
        $query = "SELECT os.*, 
                      e.razao_social, 
                      e.nome_fantasia, 
                      e.nome,
                      e.tipo_pessoa,
                      p.numero_processo, 
                      CONCAT(
                         IFNULL(e.descricao_tipo_de_logradouro, ''),
                         ' ',
                         IFNULL(e.logradouro, ''),
                         ', ',
                         IFNULL(e.numero, ''),
                         ' ',
                         IFNULL(e.complemento, ''),
                         ' - ',
                         IFNULL(e.bairro, ''),
                         ', ',
                         IFNULL(e.municipio, ''),
                         ' - ',
                         IFNULL(e.uf, ''),
                         ', CEP: ',
                         IFNULL(e.cep, '')
                      ) AS endereco,
                      u.nome_completo AS nome_usuario_encerramento 
               FROM ordem_servico os 
               LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
               LEFT JOIN processos p ON os.processo_id = p.id
               LEFT JOIN usuarios u ON os.usuario_encerramento_id = u.id
               WHERE os.id = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ordem = $result->fetch_assoc();

        if (!$ordem) {
            $this->lastError = "Ordem de serviço não encontrada.";
            return false;
        }

        // Processar os nomes dos técnicos
        $tecnicos_ids = json_decode($ordem['tecnicos']);
        $ordem['tecnicos_nomes'] = $this->getTecnicosNomes($tecnicos_ids);

        // Processar os nomes das ações executadas
        $acoes_ids = json_decode($ordem['acoes_executadas'], true);
        $ordem['acoes_executadas_nomes'] = $this->getAcoesNomes($acoes_ids);

        return $ordem;
    }


    public function getOrdensByTecnicoIncludingNoEstabelecimento($tecnico_id)
    {
        // Consulta para buscar ordens de serviço com ou sem estabelecimento
        $query = "
            SELECT os.*, e.nome_fantasia 
            FROM ordem_servico os 
            LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
            WHERE JSON_CONTAINS(os.tecnicos, JSON_QUOTE(?), '$')
            OR os.estabelecimento_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $tecnico_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ordens = $result->fetch_all(MYSQLI_ASSOC);

        // Transformar os IDs das ações executadas em nomes
        foreach ($ordens as &$ordem) {
            $ids_acoes = json_decode($ordem['acoes_executadas'], true);
            if (is_array($ids_acoes)) {
                $descricoes_acoes = $this->getDescricoesAcoesExecutadas($ids_acoes);
                $ordem['acoes_executadas_nomes'] = implode(", ", $descricoes_acoes);
            } else {
                $ordem['acoes_executadas_nomes'] = '';
            }
        }

        return $ordens;
    }

    /**
     * Obtém as ordens de serviço ativas para um técnico específico
     * @param string $tecnico_id ID do técnico
     * @param int $limit Limite de registros (opcional)
     * @return array Array com as ordens de serviço ativas
     */
    public function getOrdensAtivasByTecnico($tecnico_id, $limit = null)
    {
        // Consulta para buscar ordens de serviço ativas
        $query = "
            SELECT os.*, e.nome_fantasia, e.razao_social
            FROM ordem_servico os 
            LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
            WHERE JSON_CONTAINS(os.tecnicos, JSON_QUOTE(?), '$')
            AND os.status = 'ativa'
            ORDER BY os.id DESC";
            
        // Adicionar limite se especificado
        if ($limit !== null) {
            $query .= " LIMIT ?";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if ($limit !== null) {
            $stmt->bind_param("si", $tecnico_id, $limit);
        } else {
            $stmt->bind_param("s", $tecnico_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $ordens = $result->fetch_all(MYSQLI_ASSOC);

        // Transformar os IDs das ações executadas em nomes
        foreach ($ordens as &$ordem) {
            $ids_acoes = json_decode($ordem['acoes_executadas'], true);
            if (is_array($ids_acoes)) {
                $descricoes_acoes = $this->getDescricoesAcoesExecutadas($ids_acoes);
                $ordem['acoes_executadas_nomes'] = implode(", ", $descricoes_acoes);
            } else {
                $ordem['acoes_executadas_nomes'] = '';
            }
            
            // Adicionar nomes dos técnicos
            $tecnicos_ids = json_decode($ordem['tecnicos'], true);
            $ordem['tecnicos_nomes'] = $this->getTecnicosNomes($tecnicos_ids);
        }

        return $ordens;
    }

    /**
     * Conta o número total de ordens de serviço ativas para um técnico
     * @param string $tecnico_id ID do técnico
     * @return int Número total de ordens ativas
     */
    public function countOrdensAtivasByTecnico($tecnico_id)
    {
        $query = "
            SELECT COUNT(*) as total
            FROM ordem_servico 
            WHERE JSON_CONTAINS(tecnicos, JSON_QUOTE(?), '$')
            AND status = 'ativa'";
            
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $tecnico_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['total'];
    }

    public function update($ordem_id, $data_inicio, $data_fim, $acoes_executadas, $tecnicos, $pdf_path, $estabelecimento_id = null, $processo_id = null, $observacao = null)
    {
        if (is_null($acoes_executadas)) {
            $query = "SELECT acoes_executadas FROM ordem_servico WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $ordem_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $acoes_executadas = json_decode($row['acoes_executadas'], true);
        }

        $acoes_executadas_json = json_encode($acoes_executadas, JSON_UNESCAPED_UNICODE);

        $query = "UPDATE ordem_servico SET data_inicio = ?, data_fim = ?, acoes_executadas = ?, tecnicos = ?, pdf_path = ?, estabelecimento_id = ?, processo_id = ?, observacao = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssssssssi", $data_inicio, $data_fim, $acoes_executadas_json, $tecnicos, $pdf_path, $estabelecimento_id, $processo_id, $observacao, $ordem_id);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $this->lastError = $stmt->error;
            $stmt->close();
            return false;
        }
    }


    public function getAcoesNomes($acoes_ids)
    {
        if (empty($acoes_ids)) {
            return [];
        }

        $ids_acoes_str = implode(',', array_map('intval', $acoes_ids));
        $query = "SELECT id, descricao FROM tipos_acoes_executadas WHERE id IN ($ids_acoes_str)";
        $result = $this->conn->query($query);

        $descricoes_acoes = [];
        while ($row = $result->fetch_assoc()) {
            $descricoes_acoes[$row['id']] = $row['descricao'];
        }

        return $descricoes_acoes;
    }


    public function finalizarOrdem($id, $descricao_encerramento)
    {
        // Obter ID do usuário atual que está encerrando a ordem
        $usuario_id = $_SESSION['user']['id'];
        
        // Usar a data e hora atual para o encerramento
        $query = "UPDATE ordem_servico SET status = 'finalizada', 
                                         descricao_encerramento = ?, 
                                         usuario_encerramento_id = ?, 
                                         data_encerramento = NOW() 
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sii", $descricao_encerramento, $usuario_id, $id);

        if ($stmt->execute()) {
            $ordem = $this->getOrdemById($id);
            if ($ordem) {
                $estabelecimento_id = $ordem['estabelecimento_id'];
                $acoes_executadas = json_decode($ordem['acoes_executadas'], true);
                if (!is_array($acoes_executadas)) {
                    $acoes_executadas = [];
                }
                
                // Garantir que todos os IDs de ação sejam válidos
                $acoes_executadas = array_filter($acoes_executadas, function($acao_id) {
                    return !empty($acao_id) && is_numeric($acao_id);
                });
                
                $municipio = $_SESSION['user']['municipio'];
                $pontuacoes = $estabelecimento_id
                    ? $this->calcularPontuacao($estabelecimento_id, $acoes_executadas, $municipio)
                    : $this->calcularPontuacaoSemEstabelecimento($acoes_executadas, $municipio);

                $tecnicos_ids = json_decode($ordem['tecnicos'], true);
                if (is_array($tecnicos_ids)) {
                    foreach ($tecnicos_ids as $tecnico_id) {
                        // Agora que temos acao_id no array $acoes_executadas e pontuacoes associadas a cada acao_id
                        foreach ($pontuacoes as $acao_id => $pontuacao) {
                            // Passamos também o acao_id para salvarPontuacaoTecnico
                            $this->salvarPontuacaoTecnico($tecnico_id, $pontuacao, $id, $acao_id);
                        }
                    }
                }
            }
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }

    public function updateStatus($id, $status)
    {
        $stmt = $this->conn->prepare("UPDATE ordem_servico SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM ordem_servico WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getTecnicosNomes($ids_tecnicos)
    {
        if (empty($ids_tecnicos)) {
            return [];
        }

        $ids_tecnicos_str = implode(',', array_map('intval', $ids_tecnicos));
        $query = "SELECT nome_completo FROM usuarios WHERE id IN ($ids_tecnicos_str)";
        $result = $this->conn->query($query);

        $nomes_tecnicos = [];
        while ($row = $result->fetch_assoc()) {
            $nomes_tecnicos[] = $row['nome_completo'];
        }

        return $nomes_tecnicos;
    }

    public function getTiposAcoesExecutadas()
    {
        $query = "SELECT * FROM tipos_acoes_executadas";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Verifica se o usuário tem permissão para acessar uma ordem de serviço
     * baseado no município do usuário
     */
    public function podeAcessarOrdem($ordemId, $municipioUsuario)
    {
        $query = "SELECT os.id, os.estabelecimento_id, os.tecnicos, e.municipio
                  FROM ordem_servico os 
                  LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id 
                  WHERE os.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $ordemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ordem = $result->fetch_assoc();

        if (!$ordem) {
            return false; // Ordem não existe
        }

        // Se a ordem tem estabelecimento, verificar se é do mesmo município
        if (!is_null($ordem['estabelecimento_id']) && !empty($ordem['municipio'])) {
            return $ordem['municipio'] === $municipioUsuario;
        }

        // Se a ordem não tem estabelecimento, verificar se pelo menos um técnico é do município
        if (is_null($ordem['estabelecimento_id'])) {
            $tecnicos_ids = json_decode($ordem['tecnicos'], true);
            if (!empty($tecnicos_ids)) {
                $placeholders = implode(',', array_fill(0, count($tecnicos_ids), '?'));
                $queryTecnicos = "SELECT COUNT(*) as total FROM usuarios WHERE id IN ($placeholders) AND municipio = ?";
                $stmtTecnicos = $this->conn->prepare($queryTecnicos);
                
                // Preparar parâmetros: IDs dos técnicos + município
                $types = str_repeat('i', count($tecnicos_ids)) . 's';
                $params = array_merge($tecnicos_ids, [$municipioUsuario]);
                $stmtTecnicos->bind_param($types, ...$params);
                $stmtTecnicos->execute();
                $resultTecnicos = $stmtTecnicos->get_result();
                $rowTecnicos = $resultTecnicos->fetch_assoc();
                
                return $rowTecnicos['total'] > 0; // Se há pelo menos um técnico do município
            }
        }

        return false; // Caso padrão: não permitir acesso
    }
}
