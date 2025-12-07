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

if ($ordemServico->reiniciarOrdem($id)) {
    header("Location: detalhes_ordem.php?id=$id&success=Ordem de serviço reiniciada com sucesso.");
    exit();
} else {
    echo "Erro ao reiniciar a ordem de serviço: " . $ordemServico->getLastError();
    exit();
}
