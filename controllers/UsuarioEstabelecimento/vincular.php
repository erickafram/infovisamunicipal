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
if (!isset($_POST['estabelecimento_id']) || !isset($_POST['usuario_id']) || !isset($_POST['tipo_vinculo'])) {
    $_SESSION['error_message'] = "Todos os campos são obrigatórios.";
    header('Location: ../../views/Estabelecimento/detalhes_estabelecimento_empresa.php?id=' . $_POST['estabelecimento_id']);
    exit;
}

$estabelecimentoId = $_POST['estabelecimento_id'];
$usuarioId = $_POST['usuario_id'];
$tipoVinculo = $_POST['tipo_vinculo'];

// Instanciar o modelo
$usuarioEstabelecimentoModel = new UsuarioEstabelecimento($conn);

// Vincular o usuário ao estabelecimento
$resultado = $usuarioEstabelecimentoModel->vincularUsuario($usuarioId, $estabelecimentoId, $tipoVinculo);

if ($resultado['success']) {
    $_SESSION['success_message'] = $resultado['message'];
} else {
    $_SESSION['error_message'] = $resultado['message'];
}

// Redirecionar de volta para a página de detalhes do estabelecimento
header('Location: ../../views/Estabelecimento/detalhes_estabelecimento_empresa.php?id=' . $estabelecimentoId);
exit;
?>
