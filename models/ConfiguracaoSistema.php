<?php
class ConfiguracaoSistema
{
    private $conn;
    private static $cache = [];

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Obtém uma configuração do sistema
     */
    public static function obter($conn, $chave, $padrao = null)
    {
        // Verifica se está no cache
        if (isset(self::$cache[$chave])) {
            return self::$cache[$chave];
        }

        $stmt = $conn->prepare("SELECT valor, tipo FROM configuracoes_sistema WHERE chave = ?");
        $stmt->bind_param("s", $chave);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $valor = $row['valor'];
            
            // Converte o valor baseado no tipo
            switch ($row['tipo']) {
                case 'boolean':
                    $valor = (bool)$valor;
                    break;
                case 'number':
                    $valor = is_numeric($valor) ? (float)$valor : $valor;
                    break;
                case 'json':
                    $valor = json_decode($valor, true);
                    break;
                // 'string' não precisa conversão
            }
            
            // Armazena no cache
            self::$cache[$chave] = $valor;
            return $valor;
        }
        
        $stmt->close();
        return $padrao;
    }

    /**
     * Define uma configuração do sistema
     */
    public function definir($chave, $valor, $descricao = null, $tipo = 'string')
    {
        // Remove do cache
        unset(self::$cache[$chave]);
        
        // Converte valor para string para armazenamento
        if ($tipo === 'boolean') {
            $valor = $valor ? '1' : '0';
        } elseif ($tipo === 'json') {
            $valor = json_encode($valor);
        }

        $stmt = $this->conn->prepare("
            INSERT INTO configuracoes_sistema (chave, valor, descricao, tipo) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor),
                descricao = COALESCE(VALUES(descricao), descricao),
                tipo = VALUES(tipo),
                data_atualizacao = CURRENT_TIMESTAMP
        ");
        
        $stmt->bind_param("ssss", $chave, $valor, $descricao, $tipo);
        $resultado = $stmt->execute();
        $stmt->close();
        
        return $resultado;
    }

    /**
     * Lista todas as configurações
     */
    public function listarTodas()
    {
        $stmt = $this->conn->prepare("
            SELECT chave, valor, descricao, tipo, data_atualizacao 
            FROM configuracoes_sistema 
            ORDER BY chave
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $configuracoes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Converte os valores baseado no tipo
        foreach ($configuracoes as &$config) {
            switch ($config['tipo']) {
                case 'boolean':
                    $config['valor_convertido'] = (bool)$config['valor'];
                    break;
                case 'number':
                    $config['valor_convertido'] = is_numeric($config['valor']) ? (float)$config['valor'] : $config['valor'];
                    break;
                case 'json':
                    $config['valor_convertido'] = json_decode($config['valor'], true);
                    break;
                default:
                    $config['valor_convertido'] = $config['valor'];
            }
        }
        
        return $configuracoes;
    }

    /**
     * Verifica se o chat está ativo
     */
    public static function chatAtivo($conn)
    {
        return self::obter($conn, 'chat_ativo', true);
    }

    /**
     * Ativa/desativa o chat
     */
    public function toggleChat($ativo)
    {
        return $this->definir('chat_ativo', $ativo, 'Define se o sistema de chat está ativo (1 = ativo, 0 = inativo)', 'boolean');
    }

    /**
     * Limpa o cache
     */
    public static function limparCache()
    {
        self::$cache = [];
    }
}
?>