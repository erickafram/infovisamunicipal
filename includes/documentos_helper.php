<?php
/**
 * Helper para gerenciar documentos
 * Centraliza a busca de nomes de documentos do banco de dados
 */

/**
 * Busca o nome de um documento pelo código
 * @param mysqli $conn Conexão com o banco
 * @param string|int $codigo Código do documento
 * @return string Nome do documento ou 'Documento não especificado'
 */
function getNomeDocumentoBanco($conn, $codigo) {
    static $cache = [];
    
    // Formatar código com zeros à esquerda
    $codigo_formatado = str_pad($codigo, 3, '0', STR_PAD_LEFT);
    
    // Verificar cache
    if (isset($cache[$codigo_formatado])) {
        return $cache[$codigo_formatado];
    }
    
    try {
        $stmt = $conn->prepare("SELECT nome FROM tipos_documentos WHERE codigo = ? AND ativo = 1");
        $stmt->bind_param('s', $codigo_formatado);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $nome = $result ? $result['nome'] : 'Documento não especificado';
        
        // Armazenar no cache
        $cache[$codigo_formatado] = $nome;
        
        return $nome;
    } catch (Exception $e) {
        error_log("Erro ao buscar nome do documento $codigo: " . $e->getMessage());
        return 'Documento não especificado';
    }
}

/**
 * Busca todos os nomes de documentos do banco
 * @param mysqli $conn Conexão com o banco
 * @return array Array associativo [codigo => nome]
 */
function getTodosDocumentosBanco($conn) {
    static $cache_todos = null;
    
    if ($cache_todos !== null) {
        return $cache_todos;
    }
    
    try {
        $stmt = $conn->prepare("SELECT codigo, nome FROM tipos_documentos WHERE ativo = 1 ORDER BY codigo");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $documentos = [];
        while ($row = $result->fetch_assoc()) {
            // Usar tanto o código string quanto inteiro como chave para compatibilidade
            $codigo_str = $row['codigo'];
            $codigo_num = intval($row['codigo']);
            $documentos[$codigo_str] = $row['nome'];
            $documentos[$codigo_num] = $row['nome'];
        }
        
        $cache_todos = $documentos;
        return $documentos;
    } catch (Exception $e) {
        error_log("Erro ao buscar todos os documentos: " . $e->getMessage());
        return [];
    }
}

/**
 * Função para normalizar CNAE (centralizada)
 * @param string $cnae Código CNAE
 * @return string CNAE normalizado
 */
function normalizarCnae($cnae) {
    if (empty($cnae)) {
        return '';
    }
    
    try {
        $cnae = preg_replace('/[^0-9\/]/', '', $cnae);
        
        if (empty($cnae)) {
            return '';
        }
        
        $partes = explode('/', $cnae);
        $partes[0] = ltrim($partes[0], '0');
        
        if (count($partes) > 1) {
            $partes[1] = ltrim($partes[1], '0');
        }
        
        return implode('/', $partes);
    } catch (Exception $e) {
        error_log("Erro ao normalizar CNAE: " . $e->getMessage());
        return '';
    }
}
?> 