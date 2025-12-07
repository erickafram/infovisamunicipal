<?php
ob_start(); // Iniciar output buffering
session_start();


// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';

$userId = $_SESSION['user']['id'];

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
    $descricao = isset($_POST['descricao']) ? $_POST['descricao'] : '';
    
    // Obter o ID do usuário da sessão
    $usuario_id = $_SESSION['user']['id'];
    
    // Verificar se o usuário existe na tabela usuarios_externos
    $verificaUsuarioExterno = $conn->prepare("SELECT id FROM usuarios_externos WHERE id = ?");
    $verificaUsuarioExterno->bind_param("i", $usuario_id);
    $verificaUsuarioExterno->execute();
    $resultVerifica = $verificaUsuarioExterno->get_result();
    
    // Se o usuário não existe na tabela usuarios_externos
    if ($resultVerifica->num_rows === 0) {
        // Verificar se existe um usuário externo associado a este usuário
        // Podemos verificar por email ou outro campo comum
        if (isset($_SESSION['user']['email'])) {
            $email = $_SESSION['user']['email'];
            $buscaPorEmail = $conn->prepare("SELECT id FROM usuarios_externos WHERE email = ?");
            $buscaPorEmail->bind_param("s", $email);
            $buscaPorEmail->execute();
            $resultEmail = $buscaPorEmail->get_result();
            
            if ($resultEmail->num_rows > 0) {
                // Usar o ID encontrado
                $row = $resultEmail->fetch_assoc();
                $usuario_id = $row['id'];
            } else {
                // Criar um registro temporário na tabela usuarios_externos
                $nome_completo = $_SESSION['user']['username'] ?? 'Usuário do Sistema';
                $insertUsuario = $conn->prepare("INSERT INTO usuarios_externos (nome_completo, email) VALUES (?, ?)");
                $insertUsuario->bind_param("ss", $nome_completo, $email);
                
                if ($insertUsuario->execute()) {
                    $usuario_id = $conn->insert_id;
                } else {
                    $_SESSION['mensagem'] = [
                        'tipo' => 'danger',
                        'texto' => 'Erro ao criar referência de usuário: ' . $conn->error
                    ];
                    header("Location: " . $_SERVER['HTTP_REFERER'] ?? "meus_relatos.php");
                    exit();
                }
            }
        } else {
            // Se não tiver email, usar um ID admin
            $usuario_id = 1; // ID do admin ou outro usuário padrão
        }
    }
    
    // Continua com o código existente para capturar informações adicionais
    $pageUrl = isset($_POST['page_url']) ? $_POST['page_url'] : '';
    $screenCapture = isset($_POST['screen_capture']) ? $_POST['screen_capture'] : '';
    
    // Adiciona informações da página à descrição
    if (!empty($pageUrl)) {
        $descricao .= "\n\n--- Informações Adicionais ---";
        $descricao .= "\nURL da Página: " . $pageUrl;
    }
    
    // Se houver captura de tela, salvar como arquivo
    $screenshotFilename = '';
    if (!empty($screenCapture) && strpos($screenCapture, 'data:image/png;base64,') === 0) {
        $uploadDir = '../../uploads/screenshots/';
        
        // Criar diretório se não existir
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                error_log("Falha ao criar diretório: " . $uploadDir);
            }
        }
        
        // Remover prefixo da string base64
        $base64Image = str_replace('data:image/png;base64,', '', $screenCapture);
        $base64Image = str_replace(' ', '+', $base64Image);
        $imageData = base64_decode($base64Image);
        
        if (!$imageData) {
            error_log("Falha ao decodificar base64 da imagem");
        } else {
        // Gerar nome de arquivo único
        $screenshotFilename = 'screenshot_' . uniqid() . '.png';
        $filePath = $uploadDir . $screenshotFilename;
        
        // Salvar imagem
        if (file_put_contents($filePath, $imageData)) {
            $descricao .= "\nCaptura de Tela: " . $screenshotFilename;
                error_log("Screenshot salvo com sucesso: " . $filePath);
            } else {
                error_log("Falha ao salvar screenshot: " . $filePath);
            }
        }
    }
    
    // Validação básica
    if (empty($tipo) || empty($descricao)) {
        $_SESSION['mensagem'] = [
            'tipo' => 'danger',
            'texto' => 'Por favor, preencha todos os campos obrigatórios.'
        ];
        header("Location: meus_relatos.php");
        exit();
    }
    
    // Validação do tipo
    if ($tipo !== 'BUG' && $tipo !== 'MELHORIA') {
        $_SESSION['mensagem'] = [
            'tipo' => 'danger',
            'texto' => 'Tipo de relato inválido.'
        ];
        header("Location: meus_relatos.php");
        exit();
    }
    
    try {
        // Insere o relato no banco de dados
        $stmt = $conn->prepare("INSERT INTO relatos_usuarios (usuario_externo_id, tipo, descricao, data_criacao) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $usuario_id, $tipo, $descricao);
        
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = [
                'tipo' => 'success',
                'texto' => 'Relato enviado com sucesso! Os administradores do sistema serão notificados.'
            ];
        } else {
            throw new Exception("Erro ao inserir no banco de dados: " . $conn->error);
        }
    } catch (Exception $e) {
        $_SESSION['mensagem'] = [
            'tipo' => 'danger',
            'texto' => 'Erro ao salvar o relato: ' . $e->getMessage()
        ];
    }
}

// Redireciona de volta para a página anterior
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "meus_relatos.php";
header("Location: $redirect");
exit();
?><?php ob_end_flush(); // End output buffering ?> 