<?php
require_once '../../conf/database.php';
require_once '../../models/ResponsavelLegal.php';

$responsavelLegalModel = new ResponsavelLegal($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $estabelecimentoId = $_POST['estabelecimento_id'];
    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $documento_identificacao = $_POST['documento_atual']; // Documento atual

    if (isset($_FILES['documento_identificacao']) && $_FILES['documento_identificacao']['error'] == UPLOAD_ERR_OK) {
        $documento_identificacao = $_FILES['documento_identificacao']['name'];
        $target_dir = "../../uploads/";
        $target_file = $target_dir . basename($documento_identificacao);
        move_uploaded_file($_FILES["documento_identificacao"]["tmp_name"], $target_file);
    }

    $responsavelLegalModel->update($id, $nome, $cpf, $email, $telefone, $documento_identificacao);
    header("Location: detalhes_estabelecimento_empresa.php?id=" . $estabelecimentoId);
    exit();
}
