<?php
session_start();
require_once '../../conf/database.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    die("Acesso não autorizado. Você precisa estar logado.");
}

// Verificação do nível de acesso
if ($_SESSION['user']['nivel_acesso'] < 2) {
    die("Acesso não autorizado. Nível mínimo necessário: 2");
}

// Configuração de log
error_log("[" . date("Y-m-d H:i:s") . "] Tentativa de aprovação forçada - Usuário: " . $_SESSION['user']['email'], 3, __DIR__ . '/error_log');

// ID do estabelecimento
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id) {
    die("Erro: ID do estabelecimento não fornecido.");
}

// Registrar tentativa
error_log("[" . date("Y-m-d H:i:s") . "] Tentando aprovar estabelecimento ID: $id", 3, __DIR__ . '/error_log');

// Executa a SQL diretamente para evitar problemas com o modelo ou controlador
try {
    // Passo 1: Verificar status atual
    $check = $conn->prepare("SELECT status FROM estabelecimentos WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        error_log("[" . date("Y-m-d H:i:s") . "] Erro: Estabelecimento não encontrado.", 3, __DIR__ . '/error_log');
        die("Erro: Estabelecimento não encontrado.");
    }
    
    $status_atual = $result->fetch_assoc()['status'];
    
    if ($status_atual === 'aprovado') {
        error_log("[" . date("Y-m-d H:i:s") . "] Estabelecimento já está aprovado.", 3, __DIR__ . '/error_log');
        header("Location: ../Dashboard/dashboard.php?info=Estabelecimento já está aprovado");
        exit();
    }
    
    // Passo 2: Fazer a atualização direta
    $query = "UPDATE estabelecimentos SET status = 'aprovado' WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    
    if ($success) {
        $linhas_afetadas = $stmt->affected_rows;
        error_log("[" . date("Y-m-d H:i:s") . "] Aprovação realizada com sucesso. Linhas afetadas: $linhas_afetadas", 3, __DIR__ . '/error_log');
        
        // Verificar se realmente atualizou
        if ($linhas_afetadas > 0) {
            header("Location: ../Dashboard/dashboard.php?success=Estabelecimento aprovado com sucesso");
            exit();
        } else {
            error_log("[" . date("Y-m-d H:i:s") . "] SQL executado, mas nenhuma linha foi afetada.", 3, __DIR__ . '/error_log');
            
            // Tentar método alternativo com SQL mais específico
            $query_alt = "UPDATE estabelecimentos SET status = 'aprovado' WHERE id = ? AND status = 'pendente'";
            $stmt_alt = $conn->prepare($query_alt);
            $stmt_alt->bind_param("i", $id);
            $success_alt = $stmt_alt->execute();
            $linhas_alt = $stmt_alt->affected_rows;
            
            if ($success_alt && $linhas_alt > 0) {
                error_log("[" . date("Y-m-d H:i:s") . "] Aprovação alternativa bem-sucedida.", 3, __DIR__ . '/error_log');
                header("Location: ../Dashboard/dashboard.php?success=Estabelecimento aprovado com sucesso (método alternativo)");
                exit();
            } else {
                error_log("[" . date("Y-m-d H:i:s") . "] Falha também no método alternativo.", 3, __DIR__ . '/error_log');
                header("Location: ../Dashboard/dashboard.php?error=Não foi possível aprovar o estabelecimento. Tente o método de diagnóstico.");
                exit();
            }
        }
    } else {
        $erro = $stmt->error;
        error_log("[" . date("Y-m-d H:i:s") . "] Erro na aprovação: $erro", 3, __DIR__ . '/error_log');
        header("Location: ../Dashboard/dashboard.php?error=Erro ao aprovar: " . urlencode($erro));
        exit();
    }
} catch (Exception $e) {
    $mensagem = $e->getMessage();
    error_log("[" . date("Y-m-d H:i:s") . "] Exceção: $mensagem", 3, __DIR__ . '/error_log');
    header("Location: ../Dashboard/dashboard.php?error=Erro ao aprovar: " . urlencode($mensagem));
    exit();
}
?> 