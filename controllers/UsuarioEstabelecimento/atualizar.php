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

// Verificar se todos os campos necessários foram enviados
if (!isset($_POST['vinculo_id']) || !isset($_POST['tipo_vinculo'])) {
    $_SESSION['error_message'] = "Todos os campos são obrigatórios.";
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$vinculoId = $_POST['vinculo_id'];
$tipoVinculo = $_POST['tipo_vinculo'];

// Instanciar o modelo
$usuarioEstabelecimentoModel = new UsuarioEstabelecimento($conn);

// Atualizar o tipo de vínculo
$resultado = $usuarioEstabelecimentoModel->atualizarVinculo($vinculoId, $tipoVinculo);

if ($resultado['success']) {
    $_SESSION['success_message'] = $resultado['message'];
} else {
    $_SESSION['error_message'] = $resultado['message'];
}

// Redirecionar de volta para a página anterior
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>
