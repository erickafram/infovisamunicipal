<?php
session_start(); // Inicializa a sessão

require_once '../../conf/database.php';
require_once '../../models/Assinatura.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$assinaturaModel = new Assinatura($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['arquivo_id'])) {
    $arquivo_id = $_POST['arquivo_id'];
    $usuario_id = $_SESSION['user']['id'];

    if ($assinaturaModel->addOrUpdateAssinatura($arquivo_id, $usuario_id)) {
        header("Location: ../Dashboard/dashboard.php");
        exit();
    } else {
        echo "Erro ao assinar o documento.";
    }
}
