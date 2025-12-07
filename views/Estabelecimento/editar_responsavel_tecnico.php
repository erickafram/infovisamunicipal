<?php
require_once '../../conf/database.php';
require_once '../../models/ResponsavelTecnico.php';

$responsavelTecnicoModel = new ResponsavelTecnico($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $estabelecimentoId = $_POST['estabelecimento_id'];
    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $conselho = $_POST['conselho'];
    $numero_registro_conselho = $_POST['numero_registro_conselho'];
    $carteirinha_conselho = $_POST['carteirinha_atual']; // Carteirinha atual

    if (isset($_FILES['carteirinha_conselho']) && $_FILES['carteirinha_conselho']['error'] == UPLOAD_ERR_OK) {
        $carteirinha_conselho = $_FILES['carteirinha_conselho']['name'];
        $target_dir = "../../uploads/";
        $target_file = $target_dir . basename($carteirinha_conselho);
        move_uploaded_file($_FILES["carteirinha_conselho"]["tmp_name"], $target_file);
    }

    $responsavelTecnicoModel->update($id, $nome, $cpf, $email, $telefone, $conselho, $numero_registro_conselho, $carteirinha_conselho);
    header("Location: detalhes_estabelecimento_empresa.php?id=" . $estabelecimentoId);
    exit();
}
