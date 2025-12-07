<?php

/**
 * Controller para gerenciamento de relatos de erros e melhorias
 * Permite que usuários internos e externos reportem problemas ou sugiram melhorias
 */
class RelatorController {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Processar as requisições enviadas para o controlador
     */
    public function processRequest() {
        session_start();
        
        if (!isset($_SESSION['user'])) {
            header("Location: /visamunicipal/login.php");
            exit();
        }

        if (isset($_POST['action']) && method_exists($this, $_POST['action'])) {
            $action = $_POST['action'];
            $this->$action();
        } else {
            $this->redirectWithError("Ação não encontrada ou não permitida.");
        }
    }

    /**
     * Verificar e atualizar a estrutura da tabela se necessário
     */
    private function checkAndUpdateTableStructure() {
        // Verificar se a tabela existe
        $tableExists = $this->conn->query("SHOW TABLES LIKE 'relatos_usuarios'")->num_rows > 0;
        
        if (!$tableExists) {
            // Criar a tabela com a estrutura atualizada
            $createTableSQL = "CREATE TABLE relatos_usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_externo_id INT NULL,
                usuario_id INT NULL,
                tipo VARCHAR(20) NOT NULL,
                descricao TEXT NOT NULL,
                data_criacao DATETIME NOT NULL,
                resposta TEXT NULL,
                data_resposta DATETIME NULL,
                admin_id INT NULL,
                origem VARCHAR(20) NULL,
                INDEX (usuario_externo_id),
                INDEX (usuario_id),
                INDEX (admin_id)
            )";
            
            $this->conn->query($createTableSQL);
            
            return true;
        }
        
        // Verificar e adicionar colunas necessárias
        $columnsToAdd = [
            'resposta' => "ALTER TABLE relatos_usuarios ADD COLUMN resposta TEXT NULL AFTER data_criacao",
            'data_resposta' => "ALTER TABLE relatos_usuarios ADD COLUMN data_resposta DATETIME NULL AFTER resposta",
            'admin_id' => "ALTER TABLE relatos_usuarios ADD COLUMN admin_id INT NULL AFTER data_resposta, ADD INDEX(admin_id)",
            'origem' => "ALTER TABLE relatos_usuarios ADD COLUMN origem VARCHAR(20) NULL AFTER admin_id",
            'usuario_id' => "ALTER TABLE relatos_usuarios ADD COLUMN usuario_id INT NULL AFTER usuario_externo_id, ADD INDEX(usuario_id)"
        ];
        
        foreach ($columnsToAdd as $column => $sql) {
            $columnExists = $this->conn->query("SHOW COLUMNS FROM relatos_usuarios LIKE '$column'")->num_rows > 0;
            
            if (!$columnExists) {
                $this->conn->query($sql);
            }
        }
        
        return true;
    }

    /**
     * Salvar relato de usuário interno
     */
    public function save_relato_interno() {
        // Verificar se os campos necessários foram enviados
        if (!isset($_POST['tipo']) || !isset($_POST['descricao']) || empty($_POST['descricao'])) {
            $this->redirectWithError("Todos os campos são obrigatórios.");
            return;
        }
        
        // Verificar e atualizar a estrutura da tabela
        $this->checkAndUpdateTableStructure();

        $usuario_id = $_SESSION['user']['id'];
        $tipo = $_POST['tipo'];
        $descricao = trim($_POST['descricao']);
        $data_criacao = date('Y-m-d H:i:s');
        $origem = 'INTERNO'; // Diferencia relatos de usuários internos
        
        // Verificar se temos a coluna usuario_id
        $usuarioIdExists = $this->conn->query("SHOW COLUMNS FROM relatos_usuarios LIKE 'usuario_id'")->num_rows > 0;
        $origemExists = $this->conn->query("SHOW COLUMNS FROM relatos_usuarios LIKE 'origem'")->num_rows > 0;
        
        // Adicionar URL da página ao relato, se disponível
        if (isset($_POST['page_url']) && !empty($_POST['page_url'])) {
            $descricao .= "\n\nURL da Página: " . $_POST['page_url'];
        }
        
        // Processar screenshot, se enviado
        $screenshotFilename = null;
        
        // Verifica se temos dados de screenshot via canvas
        if (isset($_POST['screenshot_data']) && !empty($_POST['screenshot_data'])) {
            $screenshotFilename = $this->saveScreenshotFromBase64($_POST['screenshot_data']);
            if ($screenshotFilename) {
                $descricao .= "\n\nCaptura de Tela: " . $screenshotFilename;
            }
        }
        // Verifica se temos upload de arquivo
        elseif (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] == 0) {
            $screenshotFilename = $this->saveScreenshotFromUpload($_FILES['screenshot']);
            if ($screenshotFilename) {
                $descricao .= "\n\nCaptura de Tela: " . $screenshotFilename;
            }
        }

        // Obter um ID válido da tabela usuarios_externos
        $usuarioExternoValido = $this->getValidUsuarioExternoId();
        if (!$usuarioExternoValido) {
            $this->redirectWithError("Erro: Não foi possível encontrar um usuário externo válido. Contate o administrador.");
            return;
        }
        
        // Obter o nome do usuário (pode estar em diferentes campos)
        $nomeUsuario = '';
        if (isset($_SESSION['user']['nome_completo'])) {
            $nomeUsuario = $_SESSION['user']['nome_completo'];
        } elseif (isset($_SESSION['user']['nome'])) {
            $nomeUsuario = $_SESSION['user']['nome'];
        } elseif (isset($_SESSION['user']['username'])) {
            $nomeUsuario = $_SESSION['user']['username'];
        } else {
            $nomeUsuario = 'ID: ' . $usuario_id;
        }
        
        // Adicionar informação do usuário interno ao relato
        $descricao .= "\n\nRelato enviado pelo usuário interno: " . $nomeUsuario . " (ID: " . $usuario_id . ")";
        
        // Consulta preparada para inserir relato, adaptando campos conforme disponibilidade
        if ($usuarioIdExists && $origemExists) {
            // Temos as colunas adicionais para distinguir usuários internos
            $stmt = $this->conn->prepare("
                INSERT INTO relatos_usuarios 
                (usuario_externo_id, usuario_id, tipo, descricao, data_criacao, origem) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissss", $usuarioExternoValido, $usuario_id, $tipo, $descricao, $data_criacao, $origem);
        } else {
            // Versão compatível com a estrutura original da tabela
            $stmt = $this->conn->prepare("
                INSERT INTO relatos_usuarios 
                (usuario_externo_id, tipo, descricao, data_criacao) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $usuarioExternoValido, $tipo, $descricao, $data_criacao);
        }

        if ($stmt->execute()) {
            // Salvar log da ação
            $this->logRelato($usuario_id, $tipo, 'Usuário interno reportou problema/sugestão');
            
            $_SESSION['mensagem'] = [
                'tipo' => 'success',
                'texto' => 'Relato enviado com sucesso! Agradecemos sua contribuição.'
            ];
            
            // Redirecionar de volta para a página atual
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        } else {
            $this->redirectWithError("Erro ao salvar relato: " . $stmt->error);
        }
    }
    
    /**
     * Salvar screenshot a partir de base64 (canvas)
     */
    private function saveScreenshotFromBase64($base64data) {
        // Verificar se o diretório de screenshots existe
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/visamunicipal/uploads/screenshots/';
        
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                // Gravar log de erro
                error_log("Falha ao criar diretório: " . $uploadDir);
                return false;
            }
        }
        
        // Remover cabeçalho do base64 se existir
        if (strpos($base64data, ';base64,') !== false) {
            list(, $base64data) = explode(';base64,', $base64data);
        }
        
        // Decodificar os dados
        $fileData = base64_decode($base64data);
        if (!$fileData) {
            error_log("Falha ao decodificar base64");
            return false;
        }
        
        // Gerar nome de arquivo único
        $filename = 'screenshot_' . uniqid() . '.png';
        $filePath = $uploadDir . $filename;
        
        // Salvar o arquivo
        if (file_put_contents($filePath, $fileData)) {
            error_log("Screenshot salvo com sucesso: " . $filePath);
            return $filename;
        }
        
        error_log("Falha ao salvar screenshot: " . $filePath);
        return false;
    }
    
    /**
     * Salvar screenshot a partir de upload
     */
    private function saveScreenshotFromUpload($file) {
        // Verificar se o diretório de screenshots existe
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/visamunicipal/uploads/screenshots/';
        
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                error_log("Falha ao criar diretório: " . $uploadDir);
                return false;
            }
        }
        
        // Verificar se o arquivo é uma imagem válida
        $fileType = exif_imagetype($file['tmp_name']);
        if (!$fileType || !in_array($fileType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
            error_log("Tipo de arquivo inválido: " . $file['type']);
            return false;
        }
        
        // Gerar nome de arquivo único
        $filename = 'screenshot_' . uniqid() . '.png';
        $filePath = $uploadDir . $filename;
        
        // Mover o arquivo para o destino
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            error_log("Screenshot enviado com sucesso: " . $filePath);
            return $filename;
        }
        
        error_log("Falha ao mover arquivo enviado: " . $file['tmp_name'] . " para " . $filePath);
        return false;
    }

    /**
     * Registrar log do relato
     */
    private function logRelato($usuario_id, $tipo, $acao) {
        $data = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Verificar se a tabela de logs existe
        $checkTableStmt = $this->conn->prepare("SHOW TABLES LIKE 'logs_sistema'");
        $checkTableStmt->execute();
        $tableExists = ($checkTableStmt->get_result()->num_rows > 0);
        
        if ($tableExists) {
            $stmt = $this->conn->prepare("INSERT INTO logs_sistema (usuario_id, acao, ip, data) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $usuario_id, $acao, $ip, $data);
            $stmt->execute();
        }
    }

    /**
     * Encontra um ID válido da tabela usuarios_externos
     */
    private function getValidUsuarioExternoId() {
        // Primeiro tenta encontrar o registro mais antigo
        $query = "SELECT id FROM usuarios_externos ORDER BY id ASC LIMIT 1";
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['id'];
        }
        
        // Se não encontrou, verificar se o ID 13 existe (mencionado no arquivo SQL)
        $query = "SELECT id FROM usuarios_externos WHERE id = 13";
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            return 13;
        }
        
        // Se ainda não encontrou, verificar se o ID 27 existe (mencionado no arquivo SQL)
        $query = "SELECT id FROM usuarios_externos WHERE id = 27";
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            return 27;
        }
        
        return false;
    }

    /**
     * Redirecionar com mensagem de erro
     */
    private function redirectWithError($message) {
        $_SESSION['mensagem'] = [
            'tipo' => 'danger',
            'texto' => $message
        ];
        
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Verificar se o script está sendo chamado diretamente
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    require_once __DIR__ . '/../conf/database.php';
    $controller = new RelatorController($conn);
    $controller->processRequest();
} 