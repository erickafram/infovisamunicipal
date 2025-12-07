<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

$ordemServico = new OrdemServico($conn);

if (!isset($_GET['id'])) {
    echo "ID da ordem de serviço não fornecido!";
    exit();
}

$id = $_GET['id'];

// Verificar se o usuário tem permissão para acessar esta ordem baseado no município
$municipioUsuario = $_SESSION['user']['municipio'];
if (!$ordemServico->podeAcessarOrdem($id, $municipioUsuario)) {
    header("Location: listar_ordens.php?error=Acesso negado. Você não tem permissão para excluir esta ordem de serviço.");
    exit();
}

if ($ordemServico->deleteOrdem($id)) {
    header("Location: listar_ordens.php?success=Ordem de serviço excluída com sucesso.");
    exit();
} else {
    echo "Erro ao excluir a ordem de serviço: " . $ordemServico->getLastError();
    exit();
}
