<?php
require_once '../../conf/database.php';
require_once '../../models/ResponsavelTecnico.php';

$responsavelTecnicoModel = new ResponsavelTecnico($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $estabelecimentoId = $_POST['estabelecimento_id'];
    $responsavelTecnicoModel->delete($id);
    header("Location: detalhes_estabelecimento_empresa.php?id=" . $estabelecimentoId);
    exit();
}
