<?php
require_once '../../conf/database.php';
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo 'Usuário não autenticado.';
    exit();
}

$usuario_logado = $_SESSION['user'];
$municipio = $usuario_logado['municipio'];

$data = json_decode(file_get_contents('php://input'), true);
$tipoDocumento = $data['tipo_documento'] ?? '';

if ($tipoDocumento === 'ALVARÁ SANITÁRIO') {
    $usuarios = $conn->query("SELECT id, nome_completo, cpf FROM usuarios WHERE municipio = '$municipio' AND nivel_acesso = 3");
} else {
    $usuarios = $conn->query("SELECT id, nome_completo, cpf FROM usuarios WHERE municipio = '$municipio'");
}

if ($usuarios->num_rows > 0) {
    while ($usuario = $usuarios->fetch_assoc()) {
        echo '<div class="form-check">';
        echo '<input class="form-check-input" type="checkbox" id="assinante_' . $usuario['id'] . '" name="assinantes[]" value="' . $usuario['id'] . '">';
        echo '<label class="form-check-label" for="assinante_' . $usuario['id'] . '">';
        echo htmlspecialchars($usuario['nome_completo'] . " - " . $usuario['cpf'], ENT_QUOTES, 'UTF-8');
        echo '</label></div>';
    }
} else {
    echo '<p>Nenhum usuário disponível para este tipo de documento.</p>';
}
