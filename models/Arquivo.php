<?php
class Arquivo
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
    public function createArquivo($processo_id, $tipo_documento, $caminho_arquivo, $codigo_verificador, $conteudo, $sigiloso)
    {
        // Primeira inserção sem o número do arquivo
        $query = "INSERT INTO arquivos (processo_id, tipo_documento, caminho_arquivo, codigo_verificador, data_upload, numero_arquivo, conteudo, status, sigiloso) VALUES (?, ?, ?, ?, NOW(), 0, ?, 'rascunho', ?)";
        $stmt = $this->conn->prepare($query);

        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("issssi", $processo_id, $tipo_documento, $caminho_arquivo, $codigo_verificador, $conteudo, $sigiloso);
        error_log("Query: $query");
        error_log("Parameters: " . json_encode([$processo_id, $tipo_documento, $caminho_arquivo, $codigo_verificador, $conteudo]));

        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return false;
        }

        $arquivo_id = $stmt->insert_id;

        // Atualiza o numero_arquivo para ser igual ao id do arquivo
        $update_query = "UPDATE arquivos SET numero_arquivo = ? WHERE id = ?";
        $update_stmt = $this->conn->prepare($update_query);

        if ($update_stmt === false) {
            error_log("Prepare failed (update): " . $this->conn->error);
            return false;
        }

        $update_stmt->bind_param("ii", $arquivo_id, $arquivo_id);

        if (!$update_stmt->execute()) {
            error_log("Execute failed (update): " . $update_stmt->error);
            return false;
        }

        return $arquivo_id;
    }

    public function createDraftArquivo($processo_id, $tipo_documento, $conteudo, $sigiloso)
    {
        // Gera o código verificador único
        $codigo_verificador = md5(uniqid(rand(), true));

        // Insere o arquivo com o código verificador gerado e número_arquivo inicial como 0
        $stmt = $this->conn->prepare("INSERT INTO arquivos (processo_id, tipo_documento, caminho_arquivo, codigo_verificador, data_upload, numero_arquivo, conteudo, status, sigiloso) VALUES (?, ?, '', ?, NOW(), 0, ?, 'rascunho', ?)");
        $stmt->bind_param("isssi", $processo_id, $tipo_documento, $codigo_verificador, $conteudo, $sigiloso);

        // Executa a inserção
        $stmt->execute();
        $arquivo_id = $stmt->insert_id; // Obtém o ID do arquivo recém-criado
        $stmt->close();

        // Atualiza o numero_arquivo para ser igual ao id do arquivo
        $update_query = "UPDATE arquivos SET numero_arquivo = ? WHERE id = ?";
        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->bind_param("ii", $arquivo_id, $arquivo_id);

        if ($update_stmt->execute()) {
            $update_stmt->close();
            return $arquivo_id;
        } else {
            echo "Erro ao atualizar o número do arquivo.";
            $update_stmt->close();
            return false;
        }
    }


    public function getArquivosByProcessoRascunho($processoId)
    {
        $sql = "SELECT * FROM arquivos WHERE processo_id = ? AND status != 'rascunho' AND sigiloso = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $processoId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    public function getVisualizacoes($arquivoId)
    {
        // Primeiro, verificar o status do documento
        $sqlDocumento = "SELECT status FROM arquivos WHERE id = ?";
        $stmtDocumento = $this->conn->prepare($sqlDocumento);
        $stmtDocumento->bind_param("i", $arquivoId);
        $stmtDocumento->execute();
        $resultDocumento = $stmtDocumento->get_result();
        
        if ($resultDocumento->num_rows === 0) {
            return []; // Documento não encontrado
        }
        
        $documento = $resultDocumento->fetch_assoc();
        $statusDocumento = $documento['status'];
        
        // Se o documento estiver assinado, mostrar todas as visualizações
       // Se o documento estiver assinado, mostrar todas as visualizações
if ($statusDocumento === 'assinado') {
    // Primeiro, obter o ID do estabelecimento
    $sqlEstabelecimento = "SELECT p.estabelecimento_id 
                          FROM arquivos a
                          JOIN processos p ON a.processo_id = p.id
                          WHERE a.id = ?";
    $stmtEstabelecimento = $this->conn->prepare($sqlEstabelecimento);
    $stmtEstabelecimento->bind_param("i", $arquivoId);
    $stmtEstabelecimento->execute();
    $resultEstabelecimento = $stmtEstabelecimento->get_result();
    $estabelecimentoData = $resultEstabelecimento->fetch_assoc();
    $estabelecimentoId = $estabelecimentoData['estabelecimento_id'];
    
    $sql = "SELECT MAX(lv.data_visualizacao) as data_visualizacao, 
                  CASE 
                      WHEN u_ext.id IS NOT NULL THEN u_ext.nome_completo
                      WHEN u_int.id IS NOT NULL THEN u_int.nome_completo
                      ELSE 'Usuário Desconhecido'
                  END as nome_completo,
                  CASE 
                      WHEN u_ext.id IS NOT NULL THEN u_ext.cpf
                      WHEN u_int.id IS NOT NULL THEN u_int.cpf
                      ELSE NULL
                  END as cpf
            FROM log_visualizacoes lv 
            LEFT JOIN usuarios_externos u_ext ON lv.usuario_id = u_ext.id
            LEFT JOIN usuarios u_int ON lv.usuario_id = u_int.id
            LEFT JOIN usuarios_estabelecimentos ue ON u_ext.id = ue.usuario_id AND ue.estabelecimento_id = ?
            WHERE lv.arquivo_id = ? 
            AND (u_int.id IS NOT NULL OR (u_ext.id IS NOT NULL AND ue.id IS NOT NULL))
            GROUP BY lv.usuario_id, nome_completo, cpf";
} else {
    // Se o documento estiver em rascunho, mostrar apenas visualizações de usuários internos
    $sql = "SELECT MAX(lv.data_visualizacao) as data_visualizacao, 
                   u.nome_completo, 
                   u.cpf
            FROM log_visualizacoes lv 
            JOIN usuarios u ON lv.usuario_id = u.id
            WHERE lv.arquivo_id = ?
            GROUP BY lv.usuario_id, u.nome_completo, u.cpf";
}
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            if ($statusDocumento === 'assinado') {
                $stmt->bind_param("ii", $estabelecimentoId, $arquivoId);
            } else {
                $stmt->bind_param("i", $arquivoId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                return $result->fetch_all(MYSQLI_ASSOC);
            } else {
                error_log("Erro ao obter resultado: " . $this->conn->error);
            }
        } else {
            error_log("Erro ao preparar consulta: " . $this->conn->error);
        }
        return [];
    }

    public function getArquivosNaoVisualizados($usuarioId)
{
    $sql = "
        SELECT DISTINCT d.id, d.tipo_documento AS nome_arquivo, d.caminho_arquivo, p.numero_processo, e.nome_fantasia, p.id as processo_id
        FROM arquivos d
        JOIN processos p ON d.processo_id = p.id
        JOIN estabelecimentos e ON p.estabelecimento_id = e.id
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id AND ue.usuario_id = ?
        LEFT JOIN log_visualizacoes lv ON d.id = lv.arquivo_id AND lv.usuario_id = ?
        WHERE lv.id IS NULL 
          AND d.sigiloso = 0 
          AND d.status = 'assinado' 
          AND NOT EXISTS (
              SELECT 1
              FROM assinaturas a
              WHERE a.arquivo_id = d.id
              AND a.status != 'assinado'
          )
    ";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("ii", $usuarioId, $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}



    public function isVisualizadoPorUsuarioExterno($arquivoId)
    {
        // Primeiro, verificar o status do documento
        $sqlDocumento = "SELECT status FROM arquivos WHERE id = ?";
        $stmtDocumento = $this->conn->prepare($sqlDocumento);
        $stmtDocumento->bind_param("i", $arquivoId);
        $stmtDocumento->execute();
        $resultDocumento = $stmtDocumento->get_result();
        
        if ($resultDocumento->num_rows === 0) {
            return false; // Documento não encontrado
        }
        
        $documento = $resultDocumento->fetch_assoc();
        $statusDocumento = $documento['status'];
        
        // Se o documento estiver em rascunho, verificar apenas visualizações de usuários internos
        if ($statusDocumento !== 'assinado') {
            $sql = "SELECT COUNT(*) AS count 
                    FROM log_visualizacoes lv
                    JOIN usuarios u ON lv.usuario_id = u.id
                    WHERE lv.arquivo_id = ?";
        } else {
            // Se o documento estiver assinado, verificar todas as visualizações
            $sql = "SELECT COUNT(*) AS count FROM log_visualizacoes WHERE arquivo_id = ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $arquivoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }


    public function getArquivoByCodigo($codigo_verificador)
    {
        $stmt = $this->conn->prepare("SELECT * FROM arquivos WHERE codigo_verificador = ?");
        $stmt->bind_param("s", $codigo_verificador);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getArquivosByProcesso($processo_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM arquivos WHERE processo_id = ? ORDER BY data_upload DESC");
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function todasAssinaturasConcluidas($arquivo_id)
    {
        // Primeiro, verificar o status do arquivo
        $stmtStatus = $this->conn->prepare("SELECT status FROM arquivos WHERE id = ?");
        $stmtStatus->bind_param("i", $arquivo_id);
        $stmtStatus->execute();
        $resultStatus = $stmtStatus->get_result();
        $rowStatus = $resultStatus->fetch_assoc();
        
        // Se o arquivo não estiver assinado, retornar false
        if (!$rowStatus || $rowStatus['status'] !== 'assinado') {
            return false;
        }
        
        // Verificar se há assinaturas pendentes
        $stmt = $this->conn->prepare("SELECT COUNT(*) as pendentes FROM assinaturas WHERE arquivo_id = ? AND status = 'pendente'");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['pendentes'] == 0;
    }

    public function getArquivosComAssinaturasCompletas($processoId)
    {
        $query = "
        SELECT a.*
        FROM arquivos a
        LEFT JOIN assinaturas ass ON a.id = ass.arquivo_id
        WHERE a.processo_id = ? AND ass.status = 'assinado' AND a.sigiloso = 0 AND a.status != 'rascunho'
        GROUP BY a.id
        HAVING COUNT(ass.id) = (
            SELECT COUNT(*)
            FROM assinaturas
            WHERE arquivo_id = a.id
        )
    ";


        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $processoId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function todasAssinaturasPendentes($arquivo_id)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM assinaturas WHERE arquivo_id = ? AND status = 'pendente'");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] > 0;
    }

    public function arquivoFinalizadoComAssinaturasPendentes($arquivo_id)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM assinaturas WHERE arquivo_id = ? AND status = 'pendente'");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt = $this->conn->prepare("SELECT status FROM arquivos WHERE id = ?");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result_status = $stmt->get_result();
        $row_status = $result_status->fetch_assoc();

        return $row_status['status'] == 'finalizado' && $row['total'] > 0;
    }



    public function getArquivoById($arquivo_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM arquivos WHERE id = ?");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    public function getProcessoIdByArquivoId($arquivo_id)
    {
        $stmt = $this->conn->prepare("SELECT processo_id FROM arquivos WHERE id = ?");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['processo_id'];
    }

    public function getArquivosNaoSigilososByProcesso($processoId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM arquivos WHERE processo_id = ? AND sigiloso = 0");
        $stmt->bind_param("i", $processoId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function updateArquivo($arquivo_id, $tipo_documento, $conteudo, $sigiloso)
    {
        $stmt = $this->conn->prepare("UPDATE arquivos SET tipo_documento = ?, conteudo = ?, sigiloso = ? WHERE id = ?");
        $stmt->bind_param("ssii", $tipo_documento, $conteudo, $sigiloso, $arquivo_id);
        return $stmt->execute();
    }

    public function updateArquivoPathAndCodigo($arquivo_id, $caminho_arquivo, $codigo_verificador)
    {
        $stmt = $this->conn->prepare("UPDATE arquivos SET caminho_arquivo = ?, codigo_verificador = ? WHERE id = ?");
        $stmt->bind_param("ssi", $caminho_arquivo, $codigo_verificador, $arquivo_id);
        $stmt->execute();
    }

    public function atualizarStatusAssinado($arquivo_id)
    {
        $sql = "UPDATE arquivos SET status = 'assinado' WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $arquivo_id);
        $stmt->execute();
        $stmt->close();
    }



    public function deleteArquivo($arquivo_id, $usuario_id)
    {
        // Query para obter os dados do arquivo e do estabelecimento via JOIN com processos
        $stmt = $this->conn->prepare("
            SELECT a.id, a.tipo_documento, a.processo_id, p.estabelecimento_id, a.caminho_arquivo
            FROM arquivos a
            INNER JOIN processos p ON a.processo_id = p.id
            WHERE a.id = ?
        ");
        $stmt->bind_param("i", $arquivo_id);
        $stmt->execute();
        $arquivo = $stmt->get_result()->fetch_assoc();

        if ($arquivo) {
            // Registra a exclusão
            $query = "INSERT INTO logs (tipo, id_referencia, nome, processo_id, estabelecimento_id, usuario_id, data_exclusao)
                      VALUES ('arquivo', ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "isiii",
                $arquivo['id'],
                $arquivo['tipo_documento'],
                $arquivo['processo_id'],
                $arquivo['estabelecimento_id'],
                $usuario_id
            );
            $stmt->execute();

            // Deleta o arquivo físico
            $caminhoCompleto = "../../" . $arquivo['caminho_arquivo'];
            if (file_exists($caminhoCompleto)) {
                unlink($caminhoCompleto);
            }

            // Remove o item das pastas antes de excluir o arquivo
            $stmt = $this->conn->prepare("DELETE FROM documentos_pastas WHERE tipo_item = 'arquivo' AND item_id = ?");
            $stmt->bind_param("i", $arquivo_id);
            $stmt->execute();

            // Remove o registro da tabela arquivos
            $stmt = $this->conn->prepare("DELETE FROM arquivos WHERE id = ?");
            $stmt->bind_param("i", $arquivo_id);
            return $stmt->execute();
        }

        return false; // Se o arquivo não for encontrado
    }


    public function getArquivosRascunho($limit = 10, $offset = 0, $search = '')
    {
        $query = "
        SELECT a.*, p.numero_processo
        FROM arquivos a
        JOIN processos p ON a.processo_id = p.id
        WHERE a.status = 'rascunho'
        AND (a.tipo_documento LIKE ? OR p.numero_processo LIKE ?)
        LIMIT ? OFFSET ?
    ";
        $stmt = $this->conn->prepare($query);
        $searchParam = "%" . $search . "%";
        $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function countArquivosRascunho($search = '')
    {
        $query = "
        SELECT COUNT(*) as total
        FROM arquivos a
        JOIN processos p ON a.processo_id = p.id
        WHERE a.status = 'rascunho'
        AND (a.tipo_documento LIKE ? OR p.numero_processo LIKE ?)
    ";
        $stmt = $this->conn->prepare($query);
        $searchParam = "%" . $search . "%";
        $stmt->bind_param("ss", $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'];
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

    public function getDocumentosParaFinalizar($search, $limit, $offset)
    {
        $searchParam = "%" . $search . "%";
        $sql = "SELECT a.*, p.numero_processo, e.id AS estabelecimento_id
                FROM arquivos a
                JOIN processos p ON a.processo_id = p.id
                JOIN estabelecimentos e ON p.estabelecimento_id = e.id
                WHERE a.caminho_arquivo = '' AND (a.tipo_documento LIKE ? OR p.numero_processo LIKE ?)
                LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalDocumentosParaFinalizar($search)
    {
        $searchParam = "%" . $search . "%";
        $sql = "SELECT COUNT(*) AS total
                FROM arquivos a
                JOIN processos p ON a.processo_id = p.id
                WHERE a.caminho_arquivo = '' AND (a.tipo_documento LIKE ? OR p.numero_processo LIKE ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'];
    }

    public function getProcessoInfo($processo_id)
    {
        $stmt = $this->conn->prepare("SELECT estabelecimento_id, numero_processo, tipo_processo FROM processos WHERE id = ?");
        $stmt->bind_param("i", $processo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get all documents for a specific user (via their company)
     */
    public function getArquivosByUsuario($usuarioId)
{
    $sql = "
        SELECT DISTINCT
            d.id, 
            d.tipo_documento AS nome_arquivo, 
            d.caminho_arquivo AS url_arquivo, 
            d.data_upload, 
            p.numero_processo, 
            e.nome_fantasia,
            p.id as processo_id,
            CASE 
                WHEN lv.id IS NOT NULL THEN 1 
                ELSE 0 
            END as visualizado
        FROM arquivos d
        JOIN processos p ON d.processo_id = p.id
        JOIN estabelecimentos e ON p.estabelecimento_id = e.id
        JOIN usuarios_estabelecimentos ue ON e.id = ue.estabelecimento_id AND ue.usuario_id = ?
        LEFT JOIN log_visualizacoes lv ON d.id = lv.arquivo_id AND lv.usuario_id = ?
        WHERE d.sigiloso = 0 
          AND d.status = 'assinado' 
          AND NOT EXISTS (
              SELECT 1
              FROM assinaturas a
              WHERE a.arquivo_id = d.id
              AND a.status != 'assinado'
          )
        ORDER BY d.data_upload DESC
    ";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("ii", $usuarioId, $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

    /**
     * Register document visualization by user
     * Verifica se o usuário tem permissão para visualizar o documento antes de registrar
     */
    public function registrarVisualizacao($arquivoId, $usuarioId)
{
    // Primeiro, verificar se o documento existe e seu status
    $sqlDocumento = "SELECT a.id, a.status, a.processo_id, a.sigiloso, p.estabelecimento_id 
                    FROM arquivos a 
                    JOIN processos p ON a.processo_id = p.id 
                    WHERE a.id = ?";
    $stmtDocumento = $this->conn->prepare($sqlDocumento);
    $stmtDocumento->bind_param("i", $arquivoId);
    $stmtDocumento->execute();
    $resultDocumento = $stmtDocumento->get_result();
    
    if ($resultDocumento->num_rows === 0) {
        return false; // Documento não encontrado
    }
    
    $documento = $resultDocumento->fetch_assoc();
    $estabelecimentoId = $documento['estabelecimento_id'];
    
    // Verificar se o usuário é interno (funcionário) ou externo (empresa)
    $sqlUsuarioInterno = "SELECT id FROM usuarios WHERE id = ?";
    $stmtUsuarioInterno = $this->conn->prepare($sqlUsuarioInterno);
    $stmtUsuarioInterno->bind_param("i", $usuarioId);
    $stmtUsuarioInterno->execute();
    $resultUsuarioInterno = $stmtUsuarioInterno->get_result();
    
    $isUsuarioInterno = $resultUsuarioInterno->num_rows > 0;
    
    // Verificar se existe um usuário externo com o mesmo ID
    $sqlUsuarioExterno = "SELECT id FROM usuarios_externos WHERE id = ?";
    $stmtUsuarioExterno = $this->conn->prepare($sqlUsuarioExterno);
    $stmtUsuarioExterno->bind_param("i", $usuarioId);
    $stmtUsuarioExterno->execute();
    $resultUsuarioExterno = $stmtUsuarioExterno->get_result();
    
    $isUsuarioExterno = $resultUsuarioExterno->num_rows > 0;
    
    // Se não for nem interno nem externo, retorna falso
    if (!$isUsuarioInterno && !$isUsuarioExterno) {
        return false;
    }
    
    // Se for usuário interno, pode visualizar qualquer documento
    if ($isUsuarioInterno) {
        // Check if already visualized
        $checkSql = "SELECT id FROM log_visualizacoes WHERE arquivo_id = ? AND usuario_id = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $arquivoId, $usuarioId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        // If already viewed, return success
        if ($result->num_rows > 0) {
            return true;
        }
        
        // Insert new visualization record
        $sql = "INSERT INTO log_visualizacoes (usuario_id, arquivo_id, data_visualizacao) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $usuarioId, $arquivoId);
        $stmt->execute();
        
        return $stmt->affected_rows > 0;
    } else if ($isUsuarioExterno) {
        // Se for usuário externo, verificar se:
        // 1. O documento está assinado
        // 2. O usuário está vinculado ao estabelecimento
        // 3. O documento não é sigiloso
        
        // Verificar se o documento está assinado
        if ($documento['status'] !== 'assinado') {
            return false; // Usuário externo não pode visualizar documentos não assinados
        }
        
        // Verificar se o documento é sigiloso
        if ($documento['sigiloso'] == 1) {
            return false; // Usuário externo não pode visualizar documentos sigilosos
        }
        
        // Verificar se o usuário está vinculado ao estabelecimento
        $sqlVinculo = "SELECT id FROM usuarios_estabelecimentos 
                      WHERE usuario_id = ? AND estabelecimento_id = ?";
        $stmtVinculo = $this->conn->prepare($sqlVinculo);
        $stmtVinculo->bind_param("ii", $usuarioId, $estabelecimentoId);
        $stmtVinculo->execute();
        $resultVinculo = $stmtVinculo->get_result();
        
        // Se não tiver vínculo com o estabelecimento, retorna falso
        if ($resultVinculo->num_rows === 0) {
            return false; // Usuário não está vinculado ao estabelecimento
        }
        
        // Check if already visualized
        $checkSql = "SELECT id FROM log_visualizacoes WHERE arquivo_id = ? AND usuario_id = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $arquivoId, $usuarioId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        // If already viewed, return success
        if ($result->num_rows > 0) {
            return true;
        }
        
        // Insert new visualization record
        $sql = "INSERT INTO log_visualizacoes (usuario_id, arquivo_id, data_visualizacao) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $usuarioId, $arquivoId);
        $stmt->execute();
        
        return $stmt->affected_rows > 0;
    }
    
    return false;
}
}
