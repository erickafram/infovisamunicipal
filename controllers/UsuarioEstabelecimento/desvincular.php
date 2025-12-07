<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/UsuarioEstabelecimento.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    $_SESSION['error_message'] = "Você precisa estar logado para realizar esta ação.";
    header('Location: ../../login.php');
    exit;
}

// Verificar se o ID do vínculo foi enviado
if (!isset($_POST['vinculo_id'])) {
    $_SESSION['error_message'] = "ID do vínculo não informado.";
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$vinculoId = $_POST['vinculo_id'];

// Instanciar o modelo
$usuarioEstabelecimentoModel = new UsuarioEstabelecimento($conn);

// Desvincular o usuário do estabelecimento
$resultado = $usuarioEstabelecimentoModel->desvincularUsuario($vinculoId);

if ($resultado['success']) {
    $_SESSION['success_message'] = $resultado['message'];
} else {
    $_SESSION['error_message'] = $resultado['message'];
}

// Redirecionar de volta para a página anterior
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>
