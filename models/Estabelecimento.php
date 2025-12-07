<?php
class Estabelecimento
{
    private $conn;
    private $lastError;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function create($data)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO estabelecimentos (
                cnpj, descricao_identificador_matriz_filial, nome_fantasia, 
                descricao_situacao_cadastral, data_situacao_cadastral, data_inicio_atividade, 
                cnae_fiscal, cnae_fiscal_descricao, descricao_tipo_de_logradouro, 
                logradouro, numero, complemento, bairro, cep, uf, municipio, 
                ddd_telefone_1, ddd_telefone_2, razao_social, natureza_juridica, 
                qsa, cnaes_secundarios, nome_socio_1, qualificacao_socio_1, 
                nome_socio_2, qualificacao_socio_2, nome_socio_3, qualificacao_socio_3, status, usuario_externo_id, data_cadastro
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )"
        );

        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }

        $qsa_json = json_encode($data['qsa']);
        $cnaes_secundarios_json = json_encode($data['cnaes_secundarios']);

        // Extrair até três sócios
        $nome_socio_1 = isset($data['qsa'][0]['nome_socio']) ? $data['qsa'][0]['nome_socio'] : null;
        $qualificacao_socio_1 = isset($data['qsa'][0]['qualificacao_socio']) ? $data['qsa'][0]['qualificacao_socio'] : null;

        $nome_socio_2 = isset($data['qsa'][1]['nome_socio']) ? $data['qsa'][1]['nome_socio'] : null;
        $qualificacao_socio_2 = isset($data['qsa'][1]['qualificacao_socio']) ? $data['qsa'][1]['qualificacao_socio'] : null;

        $nome_socio_3 = isset($data['qsa'][2]['nome_socio']) ? $data['qsa'][2]['nome_socio'] : null;
        $qualificacao_socio_3 = isset($data['qsa'][2]['qualificacao_socio']) ? $data['qsa'][2]['qualificacao_socio'] : null;

        $stmt->bind_param(
            "sssssssssssssssssssssssssssssi",
            $data['cnpj'],
            $data['descricao_identificador_matriz_filial'],
            $data['nome_fantasia'],
            $data['descricao_situacao_cadastral'],
            $data['data_situacao_cadastral'],
            $data['data_inicio_atividade'],
            $data['cnae_fiscal'],
            $data['cnae_fiscal_descricao'],
            $data['descricao_tipo_de_logradouro'],
            $data['logradouro'],
            $data['numero'],
            $data['complemento'],
            $data['bairro'],
            $data['cep'],
            $data['uf'],
            $data['municipio'],
            $data['ddd_telefone_1'],
            $data['ddd_telefone_2'],
            $data['razao_social'],
            $data['natureza_juridica'],
            $qsa_json,
            $cnaes_secundarios_json,
            $nome_socio_1,
            $qualificacao_socio_1,
            $nome_socio_2,
            $qualificacao_socio_2,
            $nome_socio_3,
            $qualificacao_socio_3,
            $data['status'],
            $data['usuario_externo_id']
        );

        if ($stmt->execute()) {
            return $stmt->insert_id; // Retorna o ID do estabelecimento criado
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }

    public function delete($id)
    {
        $query = "DELETE FROM estabelecimentos WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function vincularUsuarioEstabelecimento($usuarioId, $estabelecimentoId, $tipoVinculo)
    {
        // Verificar se o usuario_id existe em `usuarios_externos`
        $queryCheckUser = "SELECT id FROM usuarios_externos WHERE id = ?";
        $stmtCheckUser = $this->conn->prepare($queryCheckUser);
        $stmtCheckUser->bind_param("i", $usuarioId);
        $stmtCheckUser->execute();
        $resultUser = $stmtCheckUser->get_result();

        // Verificar se o estabelecimento_id existe em `estabelecimentos`
        $queryCheckEstablishment = "SELECT id FROM estabelecimentos WHERE id = ?";
        $stmtCheckEstablishment = $this->conn->prepare($queryCheckEstablishment);
        $stmtCheckEstablishment->bind_param("i", $estabelecimentoId);
        $stmtCheckEstablishment->execute();
        $resultEstablishment = $stmtCheckEstablishment->get_result();

        // Se ambos os IDs existirem, prossiga com a inserção
        if ($resultUser->num_rows > 0 && $resultEstablishment->num_rows > 0) {
            $query = "INSERT INTO usuarios_estabelecimentos (usuario_id, estabelecimento_id, tipo_vinculo) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iis", $usuarioId, $estabelecimentoId, $tipoVinculo);
            return $stmt->execute();
        } else {
            $this->lastError = "Erro: Usuário ou estabelecimento não existe.";
            return false;
        }
    }

    public function createPessoaFisica($data)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO estabelecimentos (
                tipo_pessoa, cpf, nome, nome_fantasia, rg, orgao_emissor, 
                descricao_tipo_de_logradouro, logradouro, numero, complemento, bairro, 
                cep, uf, municipio, ddd_telefone_1, ddd_telefone_2, 
                email, inicio_funcionamento, ramo_atividade, descricao_situacao_cadastral, status, 
                usuario_externo_id, data_cadastro
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )"
        );

        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }

        // Assign array elements to variables
        $tipo_pessoa = $data['tipo_pessoa'];
        $cpf = $data['cpf'];
        $nome = $data['nome'];
        $nome_fantasia = $data['nome_fantasia'];
        $rg = $data['rg'];
        $orgao_emissor = $data['orgao_emissor'];
        $descricao_tipo_de_logradouro = $data['descricao_tipo_de_logradouro'];
        $logradouro = $data['logradouro'];
        $numero = $data['numero'];
        $complemento = $data['complemento'];
        $bairro = $data['bairro'];
        $cep = $data['cep'];
        $uf = $data['uf'];
        $municipio = $data['municipio'];
        $ddd_telefone_1 = $data['ddd_telefone_1'];
        $ddd_telefone_2 = $data['ddd_telefone_2'];
        $email = $data['email'];
        $inicio_funcionamento = $data['inicio_funcionamento'];
        $ramo_atividade = $data['ramo_atividade'];
        $descricao_situacao_cadastral = $data['descricao_situacao_cadastral'];
        $status = $data['status'];
        $usuario_externo_id = $data['usuario_externo_id'];

        // Corrected bind_param call
        $stmt->bind_param(
            "sssssssssssssssssssssi", // 22 characters
            $tipo_pessoa,            // 1
            $cpf,                    // 2
            $nome,                   // 3
            $nome_fantasia,          // 4
            $rg,                     // 5
            $orgao_emissor,          // 6
            $descricao_tipo_de_logradouro, // 7
            $logradouro,             // 8
            $numero,                 // 9
            $complemento,            // 10
            $bairro,                 // 11
            $cep,                    // 12
            $uf,                     // 13
            $municipio,              // 14
            $ddd_telefone_1,         // 15
            $ddd_telefone_2,         // 16
            $email,                  // 17
            $inicio_funcionamento,   // 18
            $ramo_atividade,         // 19
            $descricao_situacao_cadastral, // 20
            $status,                 // 21
            $usuario_externo_id      // 22
        );

        if ($stmt->execute()) {
            return $stmt->insert_id; // Return the ID of the created establishment
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }




    public function searchAprovados($usuarioId, $search = '', $limit = 10, $offset = 0)
    {
        $query = "
            SELECT e.*
            FROM estabelecimentos e
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND e.status = 'aprovado'
        ";

        if ($search) {
            $query .= " AND (e.nome_fantasia LIKE ? OR e.cnpj LIKE ?)";
            $search = "%$search%";
        }

        $query .= " LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);

        if ($search) {
            $stmt->bind_param("issii", $usuarioId, $search, $search, $limit, $offset);
        } else {
            $stmt->bind_param("iii", $usuarioId, $limit, $offset);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function countAprovados($usuarioId, $search = '')
    {
        $query = "
            SELECT COUNT(*) as total
            FROM estabelecimentos e
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND e.status = 'aprovado'
        ";

        if ($search) {
            $query .= " AND (e.nome_fantasia LIKE ? OR e.cnpj LIKE ?)";
            $search = "%$search%";
        }

        $stmt = $this->conn->prepare($query);

        if ($search) {
            $stmt->bind_param("iss", $usuarioId, $search, $search);
        } else {
            $stmt->bind_param("i", $usuarioId);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['total'];
    }

    public function countPendentes($usuarioId, $search = '')
    {
        $query = "
        SELECT COUNT(*) as total
        FROM estabelecimentos e
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
        WHERE ue.usuario_id = ? AND e.status = 'pendente'
    ";

        if ($search) {
            $query .= " AND (e.nome_fantasia LIKE ? OR e.cnpj LIKE ?)";
            $search = "%$search%";
        }

        $stmt = $this->conn->prepare($query);

        if ($search) {
            $stmt->bind_param("iss", $usuarioId, $search, $search);
        } else {
            $stmt->bind_param("i", $usuarioId);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['total'] ?? 0;
    }



    public function update($id, $data)
    {
        $stmt = $this->conn->prepare(
            "UPDATE estabelecimentos SET 
                descricao_identificador_matriz_filial = ?, 
                nome_fantasia = ?, 
                descricao_situacao_cadastral = ?, 
                data_situacao_cadastral = ?, 
                data_inicio_atividade = ?, 
                descricao_tipo_de_logradouro = ?, 
                logradouro = ?, 
                numero = ?, 
                complemento = ?, 
                bairro = ?, 
                cep = ?, 
                uf = ?, 
                municipio = ?, 
                ddd_telefone_1 = ?, 
                ddd_telefone_2 = ?, 
                razao_social = ?, 
                natureza_juridica = ?
            WHERE id = ?"
        );

        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }

        $stmt->bind_param(
            "sssssssssssssssssi",
            $data['descricao_identificador_matriz_filial'],
            $data['nome_fantasia'],
            $data['descricao_situacao_cadastral'],
            $data['data_situacao_cadastral'],
            $data['data_inicio_atividade'],
            $data['descricao_tipo_de_logradouro'],
            $data['logradouro'],
            $data['numero'],
            $data['complemento'],
            $data['bairro'],
            $data['cep'],
            $data['uf'],
            $data['municipio'],
            $data['ddd_telefone_1'],
            $data['ddd_telefone_2'],
            $data['razao_social'],
            $data['natureza_juridica'],
            $id
        );

        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }

    public function approve($id)
    {
        // Garantir que o ID é um número inteiro
        $id = intval($id);
        
        // Verificar status atual para depuração
        $stmt_check = $this->conn->prepare("SELECT status FROM estabelecimentos WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows == 0) {
            $this->lastError = "Estabelecimento não encontrado";
            return false;
        }
        
        // Atualizar o status para aprovado
        $stmt = $this->conn->prepare("UPDATE estabelecimentos SET status = 'aprovado' WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        
        if (!$result) {
            $this->lastError = $stmt->error;
            return false;
        }
        
        return true;
    }

    public function getEstabelecimentosByUsuarioExterno($usuarioExternoId)
    {
        $sql = "
        SELECT e.id, e.nome_fantasia, e.cnpj 
        FROM estabelecimentos e
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
        WHERE ue.usuario_id = ?
    ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $usuarioExternoId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    public function findById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Definir o método updatePessoaFisica()
    public function updatePessoaFisica($id, $cpf, $nome, $rg, $orgao_emissor, $nome_fantasia, $cep, $logradouro, $numero, $bairro, $complemento, $municipio, $uf, $email, $ddd_telefone_1, $inicio_funcionamento, $ramo_atividade)
    {
        $sql = "UPDATE estabelecimentos SET cpf = ?, nome = ?, rg = ?, orgao_emissor = ?, nome_fantasia = ?, cep = ?, logradouro = ?, numero = ?, bairro = ?, complemento = ?, municipio = ?, uf = ?, email = ?, ddd_telefone_1 = ?, inicio_funcionamento = ?, ramo_atividade = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$cpf, $nome, $rg, $orgao_emissor, $nome_fantasia, $cep, $logradouro, $numero, $bairro, $complemento, $municipio, $uf, $email, $ddd_telefone_1, $inicio_funcionamento, $ramo_atividade, $id]);
    }

    public function salvarCnae($estabelecimentoId, $cnaeId, $descricao)
    {
        try {
            // Prepara a consulta para inserir o CNAE
            $query = "INSERT INTO estabelecimento_cnaes (estabelecimento_id, cnae, descricao) 
                      VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);

            // Verifica se a preparação da consulta foi bem-sucedida
            if (!$stmt) {
                $this->lastError = $this->conn->error;
                return false;
            }

            // Faz o bind dos parâmetros com MySQLi (utilizando "s" para string e "i" para inteiros)
            $stmt->bind_param('iss', $estabelecimentoId, $cnaeId, $descricao);

            // Executa a query e verifica se foi bem-sucedida
            if ($stmt->execute()) {
                return true; // Retorna true se a inserção for bem-sucedida
            } else {
                $this->lastError = $stmt->error;
                return false; // Retorna false se algo der errado
            }
        } catch (Exception $e) {
            // Captura qualquer outro erro e armazena a mensagem de erro
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function getEstabelecimentosPendentes($municipio, $limit = null)
    {
        $sql = "
            SELECT id, 
                   tipo_pessoa, 
                   IF(tipo_pessoa = 'juridica', nome_fantasia, nome) AS nome_fantasia, 
                   logradouro, numero, bairro, municipio, uf, cep, status
            FROM estabelecimentos 
            WHERE status = 'pendente' AND municipio = ?
        ";

        if ($limit !== null) {
            $sql .= " LIMIT ?";
        }

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            die('Erro na preparação da consulta: ' . $this->conn->error);
        }

        if ($limit !== null) {
            $stmt->bind_param("si", $municipio, $limit);
        } else {
            $stmt->bind_param("s", $municipio);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllEstabelecimentos()
    {
        $result = $this->conn->query("SELECT * FROM estabelecimentos");
        if ($result->num_rows > 0) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            return [];
        }
    }

    public function searchEstabelecimentos($search, $limit, $offset, $municipio, $nivel_acesso)
    {
        $sql = "SELECT * FROM estabelecimentos WHERE status = 'aprovado'";
        $params = [];
        $types = "";

        // Adicionar filtro de município para não administradores
        if ($nivel_acesso != 1) {
            $sql .= " AND municipio = ?";
            $params[] = $municipio;
            $types .= "s";
        }

        if ($search) {
            $sql .= " AND (cnpj LIKE ? OR cpf LIKE ? OR razao_social LIKE ? OR nome_fantasia LIKE ? OR municipio LIKE ?)";
            $search = "%$search%";
            $params[] = $search;
            $params[] = $search; // CPF
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= "sssss";
        }

        $sql .= " ORDER BY razao_social ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->conn->prepare($sql);

        if ($types) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }



    public function getEstabelecimentosByUsuario($usuarioId)
    {
        $query = "
            SELECT e.*
            FROM estabelecimentos e
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND e.status = 'aprovado'
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getEstabelecimentosRejeitadosByUsuario($usuarioId)
    {
        $sql = "SELECT * FROM estabelecimentos 
                WHERE usuario_externo_id = ? 
                AND status = 'rejeitado' 
                AND lido = 0"; // Adicione esta linha

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getEstabelecimentosPendentesLimit($municipio, $limite)
    {
        $stmt = $this->conn->prepare("
        SELECT id, 
               tipo_pessoa, 
               IF(tipo_pessoa = 'juridica', nome_fantasia, nome) AS nome_fantasia, 
               logradouro, numero, bairro, municipio, uf, cep, status
        FROM estabelecimentos 
        WHERE status = 'pendente' AND municipio = ?
        LIMIT ?
    ");
        $stmt->bind_param("si", $municipio, $limite);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    // Contar o total de estabelecimentos pendentes com base em busca e município
    public function countEstabelecimentosPendentes($municipio, $search = '')
    {
        $query = "
          SELECT COUNT(*) AS total 
          FROM estabelecimentos 
          WHERE status = 'pendente' 
          AND municipio = ?
      ";

        if (!empty($search)) {
            $query .= " AND (nome_fantasia LIKE ? OR tipo_pessoa LIKE ?)";
        }

        $stmt = $this->conn->prepare($query);
        if (!empty($search)) {

            $searchTerm = "%" . $search . "%";
            $stmt->bind_param("sss", $municipio, $searchTerm, $searchTerm);
        } else {
            $stmt->bind_param("s", $municipio);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    public function getEstabelecimentosPorMes($municipio)
    {
        $sql = "SELECT 
                    DATE_FORMAT(data_cadastro, '%Y-%m') AS mes,
                    COUNT(*) AS total
                FROM estabelecimentos
                WHERE municipio = ?
                GROUP BY DATE_FORMAT(data_cadastro, '%Y-%m')
                ORDER BY mes DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Obter estabelecimentos pendentes com paginação e busca
    public function getEstabelecimentosPendentesPaginated($municipio, $search = '', $limit = 10, $offset = 0)
    {
        $query = "
          SELECT id, tipo_pessoa, 
                 IF(tipo_pessoa = 'juridica', nome_fantasia, nome) AS nome_fantasia, 
                 logradouro, numero, bairro, municipio, uf 
          FROM estabelecimentos 
          WHERE status = 'pendente' 
          AND municipio = ?
      ";

        if (!empty($search)) {
            $query .= " AND (nome_fantasia LIKE ? OR tipo_pessoa LIKE ?)";
        }

        $query .= " LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($query);
        if (!empty($search)) {
            $searchTerm = "%" . $search . "%";
            $stmt->bind_param("sssii", $municipio, $searchTerm, $searchTerm, $limit, $offset);
        } else {
            $stmt->bind_param("sii", $municipio, $limit, $offset);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function reject($id, $motivo)
    {
        $stmt = $this->conn->prepare("UPDATE estabelecimentos SET status = 'rejeitado', motivo_negacao = ? WHERE id = ?");
        $stmt->bind_param("si", $motivo, $id);
        return $stmt->execute();
    }

    public function marcarComoLido($estabelecimentoId, $userId)
    {
        $sql = "UPDATE estabelecimentos_rejeitados SET lido = 1 WHERE id = ? AND usuario_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$estabelecimentoId, $userId]);
    }


    public function getEstabelecimentosPendentesByUsuario($usuarioId)
    {
        $query = "
            SELECT e.*
            FROM estabelecimentos e
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND e.status = 'pendente'
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getProcessosByEstabelecimento($estabelecimentoId)
    {
        $query = "
            SELECT p.*
            FROM processos p
            WHERE p.estabelecimento_id = ? AND p.tipo_processo != 'DENÚNCIA'
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $estabelecimentoId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function getDocumentosNegadosByUsuario($userId)
    {
        $query = "SELECT d.*, p.numero_processo, e.nome_fantasia
                  FROM documentos d
                  JOIN processos p ON d.processo_id = p.id
                  JOIN estabelecimentos e ON p.estabelecimento_id = e.id
                  JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
                  WHERE ue.usuario_id = ? AND d.status = 'negado' AND (d.alerta_ativo IS NULL OR d.alerta_ativo = TRUE)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $documentosNegados = [];
        while ($row = $result->fetch_assoc()) {
            $documentosNegados[] = $row;
        }

        return $documentosNegados;
    }

    /**
     * Marca o alerta de um documento negado como resolvido
     * @param int $documentoId ID do documento
     * @param int $usuarioId ID do usuário que está resolvendo
     * @return bool True se sucesso, false se erro
     */
    public function marcarAlertaDocumentoComoResolvido($documentoId, $usuarioId)
    {
        $query = "UPDATE documentos 
                  SET alerta_ativo = FALSE, 
                      data_resolucao_alerta = NOW(), 
                      resolvido_por_usuario_id = ? 
                  WHERE id = ? AND status = 'negado'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        
        $stmt->bind_param("ii", $usuarioId, $documentoId);
        $result = $stmt->execute();
        
        if (!$result) {
            $this->lastError = $stmt->error;
            return false;
        }
        
        // Verificar se alguma linha foi afetada
        if ($stmt->affected_rows === 0) {
            $this->lastError = "Documento não encontrado ou não está negado";
            return false;
        }
        
        return true;
    }

    /**
     * Reativa um alerta de documento (caso o usuário precise ver novamente)
     * @param int $documentoId ID do documento
     * @param int $usuarioId ID do usuário que está reativando
     * @return bool True se sucesso, false se erro
     */
    public function reativarAlertaDocumento($documentoId, $usuarioId)
    {
        $query = "UPDATE documentos 
                  SET alerta_ativo = TRUE, 
                      data_resolucao_alerta = NULL, 
                      resolvido_por_usuario_id = NULL 
                  WHERE id = ? AND status = 'negado'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        
        $stmt->bind_param("i", $documentoId);
        $result = $stmt->execute();
        
        if (!$result) {
            $this->lastError = $stmt->error;
            return false;
        }
        
        return true;
    }

    public function getDocumentosPendentesByUsuario($userId)
    {
        $query = "SELECT d.*, p.numero_processo, e.nome_fantasia
                  FROM documentos d
                  JOIN processos p ON d.processo_id = p.id
                  JOIN estabelecimentos e ON p.estabelecimento_id = e.id
                  JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
                  WHERE ue.usuario_id = ? AND d.status = 'pendente'";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $documentosPendentes = [];
        while ($row = $result->fetch_assoc()) {
            $documentosPendentes[] = $row;
        }

        return $documentosPendentes;
    }



    public function countEstabelecimentos($search, $municipio, $nivel_acesso)
    {
        $sql = "SELECT COUNT(*) as total FROM estabelecimentos WHERE status = 'aprovado'";
        $params = [];
        $types = "";

        // Adicionar filtro de município para não administradores
        if ($nivel_acesso != 1) {
            $sql .= " AND municipio = ?";
            $params[] = $municipio;
            $types .= "s";
        }

        if ($search) {
            $sql .= " AND (cnpj LIKE ? OR nome_fantasia LIKE ? OR municipio LIKE ?)";
            $search = "%$search%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= "sss";
        }

        $stmt = $this->conn->prepare($sql);

        if ($types) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['total'];
    }


    public function searchEstabelecimentosRejeitados($search, $limit, $offset, $municipio, $nivel_acesso)
    {
        $sql = "SELECT * FROM estabelecimentos WHERE status = 'rejeitado'";
        $params = [];
        $types = "";

        // Adicionar filtro de município para não administradores
        if ($nivel_acesso != 1) {
            $sql .= " AND municipio = ?";
            $params[] = $municipio;
            $types .= "s";
        }

        if ($search) {
            $sql .= " AND (cnpj LIKE ? OR nome_fantasia LIKE ? OR municipio LIKE ?)";
            $search = "%$search%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= "sss";
        }

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->conn->prepare($sql);

        if ($types) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function reiniciarEstabelecimento($id)
    {
        $stmt = $this->conn->prepare("UPDATE estabelecimentos SET status = 'pendente', motivo_negacao = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function countEstabelecimentosRejeitados($search, $municipio, $nivel_acesso)
    {
        $sql = "SELECT COUNT(*) as total FROM estabelecimentos WHERE status = 'rejeitado'";
        $params = [];
        $types = "";

        // Adicionar filtro de município para não administradores
        if ($nivel_acesso != 1) {
            $sql .= " AND municipio = ?";
            $params[] = $municipio;
            $types .= "s";
        }

        if ($search) {
            $sql .= " AND (cnpj LIKE ? OR nome_fantasia LIKE ? OR municipio LIKE ?)";
            $search = "%$search%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= "sss";
        }

        $stmt = $this->conn->prepare($sql);

        if ($types) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['total'];
    }

    public function getMunicipioByCnpj($cnpj)
    {
        $stmt = $this->conn->prepare("SELECT municipio FROM estabelecimentos WHERE cnpj = ?");
        $stmt->bind_param("s", $cnpj);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['municipio'];
    }

    public function searchPendentes($usuarioId, $search = '', $limit = 10, $offset = 0)
    {
        $query = "
        SELECT e.*
        FROM estabelecimentos e
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
        WHERE ue.usuario_id = ? AND e.status = 'pendente'
    ";

        if ($search) {
            $query .= " AND (e.nome_fantasia LIKE ? OR e.cnpj LIKE ?)";
            $search = "%$search%";
        }

        $query .= " LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);

        if ($search) {
            $stmt->bind_param("issii", $usuarioId, $search, $search, $limit, $offset);
        } else {
            $stmt->bind_param("iii", $usuarioId, $limit, $offset);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }




    public function checkCnpjExists($cnpj)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM estabelecimentos WHERE cnpj = ?");
        $stmt->bind_param("s", $cnpj);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count > 0;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function searchEstabelecimento($searchTerm)
    {
        $query = "SELECT * FROM estabelecimentos WHERE nome_fantasia LIKE ? OR razao_social LIKE ? OR cnpj LIKE ?";
        $stmt = $this->conn->prepare($query);
        $searchTerm = '%' . $searchTerm . '%';
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function searchByNameOrRazaoSocial($search, $userId)
    {
        $search = "%$search%";
        $stmt = $this->conn->prepare("
            SELECT e.*
            FROM estabelecimentos e
            JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
            WHERE ue.usuario_id = ? AND (e.nome_fantasia LIKE ? OR e.razao_social LIKE ?)
        ");
        $stmt->bind_param("iss", $userId, $search, $search);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getCnaesByEstabelecimentoId($estabelecimentoId)
    {
        $query = "SELECT cnae, descricao FROM estabelecimento_cnaes WHERE estabelecimento_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $estabelecimentoId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtém os CNAEs com seus respectivos grupos de risco para um estabelecimento
     * 
     * @param int $estabelecimentoId ID do estabelecimento
     * @return array Array associativo com CNAEs e seus grupos de risco
     */
    public function getCnaesByEstabelecimentoIdWithRisco($estabelecimentoId)
    {
        // Primeiro obtém o estabelecimento para ter o município
        $estabelecimento = $this->findById($estabelecimentoId);
        if (!$estabelecimento) {
            return [];
        }
        
        $municipio = $estabelecimento['municipio'];
        
        // Primeiro buscar todos os CNAEs
        $query = "
            SELECT cnae, descricao
            FROM estabelecimento_cnaes 
            WHERE estabelecimento_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $estabelecimentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $cnaes = $result->fetch_all(MYSQLI_ASSOC);
        
        // Para cada CNAE, buscar todos os grupos de risco
        foreach ($cnaes as $key => $cnae) {
            $query = "
                SELECT gr.descricao as grupo_risco
                FROM atividade_grupo_risco agr
                JOIN grupo_risco gr ON agr.grupo_risco_id = gr.id
                WHERE agr.cnae = ? AND agr.municipio = ?
                ORDER BY gr.id
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('ss', $cnae['cnae'], $municipio);
            $stmt->execute();
            $grResult = $stmt->get_result();
            
            $grupos = [];
            while ($row = $grResult->fetch_assoc()) {
                $grupos[] = $row['grupo_risco'];
            }
            
            // Combinar os grupos de risco em uma string
            if (!empty($grupos)) {
                $cnaes[$key]['grupo_risco'] = implode(' E ', $grupos);
            } else {
                $cnaes[$key]['grupo_risco'] = null;
            }
        }
        
        return $cnaes;
    }

    /**
     * Obtém os grupos de risco aos quais um estabelecimento pertence com base em seus CNAEs
     */
    public function getGruposRiscoByEstabelecimento($estabelecimentoId)
    {
        // Consulta para buscar os grupos de risco com base nos CNAEs
        $query = "
            SELECT DISTINCT gr.id, gr.descricao AS grupo_risco
            FROM estabelecimentos e
            LEFT JOIN estabelecimento_cnaes ec ON e.id = ec.estabelecimento_id
            LEFT JOIN atividade_grupo_risco agr ON ec.cnae = agr.cnae AND e.municipio = agr.municipio
            LEFT JOIN grupo_risco gr ON agr.grupo_risco_id = gr.id
            WHERE e.id = ? AND gr.descricao IS NOT NULL
            ORDER BY gr.id
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $estabelecimentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $gruposRisco = [];
        while ($row = $result->fetch_assoc()) {
            $gruposRisco[] = $row;
        }
        
        return $gruposRisco;
    }

    public function vincularCnaes($estabelecimentoId, $cnaes)
    {
        $query = "INSERT INTO estabelecimento_cnaes (estabelecimento_id, cnae, descricao) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        foreach ($cnaes as $cnae) {
            // Substitua $cnae['cnae'] por $cnae['id']
            $stmt->bind_param("iss", $estabelecimentoId, $cnae['id'], $cnae['descricao']);
            if (!$stmt->execute()) {
                return false; // Retorna false se algum erro ocorrer
            }
        }

        return true; // Retorna true se todos os CNAEs forem inseridos corretamente
    }


    public function removeAllCnaes($estabelecimentoId)
    {
        try {
            $query = "DELETE FROM estabelecimento_cnaes WHERE estabelecimento_id = ?";
            $stmt = $this->conn->prepare($query);

            if (!$stmt) {
                $this->lastError = $this->conn->error;
                return false;
            }

            $stmt->bind_param('i', $estabelecimentoId);
            $stmt->execute();
            return true;
        } catch (mysqli_sql_exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function checkCpfExists($cpf, $municipio)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM estabelecimentos WHERE cpf = ? AND municipio = ?");
        $stmt->bind_param("ss", $cpf, $municipio);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count > 0;
    }

    public function findByCnpj($cnpj)
    {
        $stmt = $this->conn->prepare("SELECT * FROM estabelecimentos WHERE cnpj = ?");
        $stmt->bind_param("s", $cnpj);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findByCnpjAndUsuario($cnpj, $usuarioId)
    {
        $stmt = $this->conn->prepare("
        SELECT e.*
        FROM estabelecimentos e
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
        WHERE e.cnpj = ? AND ue.usuario_id = ?
    ");
        $stmt->bind_param("si", $cnpj, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Verificação temporária sem necessidade de ID de estabelecimento
     */
    public function temCompetenciaVigilanciaTemporaria($cnaeFiscal, $cnaesSecundariosJson, $municipio)
    {
        $cnaes = [];

        // Adicionar CNAE principal
        if (!empty($cnaeFiscal)) {
            $cnaes[] = $cnaeFiscal;
        }

        // Adicionar CNAEs secundários (se existirem)
        if (!empty($cnaesSecundariosJson)) {
            $cnaesSecundarios = json_decode($cnaesSecundariosJson, true);
            foreach ($cnaesSecundarios as $cnae) {
                if (!empty($cnae['codigo'])) {
                    $cnaes[] = $cnae['codigo'];
                }
            }
        }

        if (empty($cnaes)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($cnaes), '?'));
        $types = str_repeat('s', count($cnaes));

        $query = "SELECT COUNT(*) as total 
              FROM atividade_grupo_risco 
              WHERE cnae IN ($placeholders) 
              AND (municipio IS NULL OR municipio = ?)";

        $stmt = $this->conn->prepare($query);
        $params = array_merge($cnaes, [$municipio]);
        $stmt->bind_param($types . 's', ...$params);

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return ($row['total'] > 0);
    }

    /**
     * Verifica se o estabelecimento tem atividades de competência da vigilância sanitária municipal
     * @param int $estabelecimentoId ID do estabelecimento
     * @param string $municipio Município do estabelecimento
     * @return bool Retorna true se tiver pelo menos uma atividade de competência
     */
    public function temCompetenciaVigilancia($estabelecimentoId, $municipio)
    {
        // 1. Obter CNAE principal e secundários do estabelecimento
        $estabelecimento = $this->findById($estabelecimentoId);
        if (!$estabelecimento) {
            return false;
        }

        $cnaes = [];

        // Adicionar CNAE principal
        if (!empty($estabelecimento['cnae_fiscal'])) {
            $cnaes[] = $estabelecimento['cnae_fiscal'];
        }

        // Adicionar CNAEs secundários (se existirem)
        if (!empty($estabelecimento['cnaes_secundarios'])) {
            $cnaesSecundarios = json_decode($estabelecimento['cnaes_secundarios'], true);
            foreach ($cnaesSecundarios as $cnae) {
                if (!empty($cnae['codigo'])) {
                    $cnaes[] = $cnae['codigo'];
                }
            }
        }

        // 2. Verificar se algum CNAE está na tabela atividade_grupo_risco
        if (empty($cnaes)) {
            return false;
        }

        // Criar placeholders para a consulta SQL
        $placeholders = implode(',', array_fill(0, count($cnaes), '?'));
        $types = str_repeat('s', count($cnaes));

        // Consulta verificando os CNAEs e o município
        $query = "SELECT COUNT(*) as total 
              FROM atividade_grupo_risco 
              WHERE cnae IN ($placeholders) 
              AND (municipio IS NULL OR municipio = ?)";

        $stmt = $this->conn->prepare($query);

        // Bind dos parâmetros
        $params = array_merge($cnaes, [$municipio]);
        $stmt->bind_param($types . 's', ...$params);

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return ($row['total'] > 0);
    }

    public function searchByNameAndUsuario($name, $usuarioId)
    {
        $name = "%$name%";
        $stmt = $this->conn->prepare("
        SELECT e.*
        FROM estabelecimentos e
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
        WHERE e.nome_fantasia LIKE ? AND ue.usuario_id = ?
    ");
        $stmt->bind_param("si", $name, $usuarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function searchEstabelecimentosByName($name, $usuarioId)
    {
        $name = "%$name%";
        $stmt = $this->conn->prepare("
        SELECT e.*
        FROM estabelecimentos e
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id
        WHERE e.nome_fantasia LIKE ? AND ue.usuario_id = ?
    ");
        $stmt->bind_param("si", $name, $usuarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtém o grupo de risco associado a um CNAE específico
     * 
     * @param string $cnae Código CNAE
     * @param string $municipio Município para filtrar
     * @return string|null Nome do grupo de risco ou null se não encontrado
     */
    public function getRiscoByCnae($cnae, $municipio)
    {
        $query = "
            SELECT gr.descricao AS grupo_risco
            FROM atividade_grupo_risco agr
            JOIN grupo_risco gr ON agr.grupo_risco_id = gr.id
            WHERE agr.cnae = ? AND agr.municipio = ?
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $cnae, $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['grupo_risco'];
        }
        
        return null;
    }
    
    /**
     * Obtém os grupos de risco para um conjunto de estabelecimentos 
     * para exibição na listagem
     * 
     * @param array $estabelecimentoIds Array com os IDs dos estabelecimentos
     * @return array Associativo com ID do estabelecimento como chave e array de grupos de risco como valor
     */
    public function getGruposRiscoParaListagem($estabelecimentoIds) 
    {
        if (empty($estabelecimentoIds)) {
            return [];
        }
        
        $resultados = [];
        $placeholders = implode(',', array_fill(0, count($estabelecimentoIds), '?'));
        
        // Consulta para estabelecimentos pessoa física (usando a tabela estabelecimento_cnaes)
        $queryFisica = "
            SELECT e.id, gr.descricao AS grupo_risco
            FROM estabelecimentos e
            JOIN estabelecimento_cnaes ec ON e.id = ec.estabelecimento_id
            JOIN atividade_grupo_risco agr ON ec.cnae = agr.cnae AND e.municipio = agr.municipio
            JOIN grupo_risco gr ON agr.grupo_risco_id = gr.id
            WHERE e.id IN ($placeholders) AND e.tipo_pessoa = 'fisica'
            GROUP BY e.id, gr.descricao
            ORDER BY e.id
        ";
        
        // Preparar parâmetros para a consulta
        $stmt = $this->conn->prepare($queryFisica);
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($estabelecimentoIds)), ...$estabelecimentoIds);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Processar resultados para pessoa física
            while ($row = $result->fetch_assoc()) {
                $id = $row['id'];
                if (!isset($resultados[$id])) {
                    $resultados[$id] = [];
                }
                $resultados[$id][] = $row['grupo_risco'];
            }
        }
        
        // Consulta para estabelecimentos pessoa jurídica (usando cnae_fiscal e cnaes_secundarios)
        $queryJuridica = "
            SELECT e.id, gr.descricao AS grupo_risco
            FROM estabelecimentos e
            LEFT JOIN atividade_grupo_risco agr_fiscal
                ON REPLACE(REPLACE(REPLACE(e.cnae_fiscal, '.', ''), '-', ''), '/', '') = 
                   REPLACE(REPLACE(REPLACE(agr_fiscal.cnae, '.', ''), '-', ''), '/', '')
                AND e.municipio = agr_fiscal.municipio
            LEFT JOIN grupo_risco gr_fiscal ON agr_fiscal.grupo_risco_id = gr_fiscal.id
            LEFT JOIN LATERAL (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(sec.value, '$.codigo')) AS cnae_sec
                FROM JSON_TABLE(e.cnaes_secundarios, '$[*]' COLUMNS (value JSON PATH '$')) AS sec
            ) cnaes_secundarios ON 1=1
            LEFT JOIN atividade_grupo_risco agr_secundario
                ON REPLACE(REPLACE(REPLACE(cnaes_secundarios.cnae_sec, '.', ''), '-', ''), '/', '') = 
                   REPLACE(REPLACE(REPLACE(agr_secundario.cnae, '.', ''), '-', ''), '/', '')
                AND e.municipio = agr_secundario.municipio
            LEFT JOIN grupo_risco gr_secundario ON agr_secundario.grupo_risco_id = gr_secundario.id
            LEFT JOIN grupo_risco gr ON gr.id = COALESCE(gr_fiscal.id, gr_secundario.id)
            WHERE e.id IN ($placeholders) AND e.tipo_pessoa = 'juridica' AND gr.descricao IS NOT NULL
            GROUP BY e.id, gr.descricao
            ORDER BY e.id
        ";
        
        // Preparar parâmetros para a consulta
        $stmt = $this->conn->prepare($queryJuridica);
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($estabelecimentoIds)), ...$estabelecimentoIds);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Processar resultados para pessoa jurídica
            while ($row = $result->fetch_assoc()) {
                $id = $row['id'];
                if (!isset($resultados[$id])) {
                    $resultados[$id] = [];
                }
                $resultados[$id][] = $row['grupo_risco'];
            }
        }
        
        return $resultados;
    }
}
