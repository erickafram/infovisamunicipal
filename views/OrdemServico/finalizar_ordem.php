<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

$ordemServico = new OrdemServico($conn);

if (isset($_POST['descricao_encerramento']) && isset($_GET['id'])) {
    $descricao_encerramento = $_POST['descricao_encerramento'];
    $id = $_GET['id'];
    if ($ordemServico->finalizarOrdem($id, $descricao_encerramento)) {
        header("Location: detalhes_ordem.php?id=$id&success=Ordem de serviço finalizada com sucesso.");
        exit();
    } else {
        header("Location: detalhes_ordem.php?id=$id&error=" . urlencode($ordemServico->getLastError()));
        exit();
    }
}
?>
