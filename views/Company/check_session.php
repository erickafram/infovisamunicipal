<?php
// Configurações críticas de sessão - DEVEM vir antes do session_start
ini_set('session.gc_maxlifetime', 86400); // 24 horas
ini_set('session.cookie_lifetime', 0); // Cookie de sessão expira quando o navegador é fechado
session_save_path(sys_get_temp_dir()); // Garante que o caminho de sessão é acessível

// Se um ID de sessão foi fornecido, usá-lo
if (isset($_GET['sid']) && !empty($_GET['sid'])) {
    session_id($_GET['sid']);
}

// Inicia a sessão
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false,
    'use_strict_mode' => true
]);

// Verifica se é apenas para manter a sessão ativa
if (isset($_GET['keep_alive'])) {
    // Atualiza o timestamp da última atividade
    $_SESSION['last_activity'] = time();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'alive', 'session_id' => session_id()]);
    exit();
}

// Verifica se o usuário está logado
$active = isset($_SESSION['user']) && !empty($_SESSION['user']);

// Se estiver logado, atualiza o timestamp da última atividade
if ($active) {
    $_SESSION['last_activity'] = time();
}

// Retorna resposta JSON com status e ID da sessão
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode([
    'active' => $active,
    'session_id' => session_id(),
    'timestamp' => time()
]);
?>
