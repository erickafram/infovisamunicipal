<?php
require_once '../../conf/database.php';
require_once '../../models/ResponsavelLegal.php';

$responsavelLegalModel = new ResponsavelLegal($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $estabelecimentoId = $_POST['estabelecimento_id'];
    $responsavelLegalModel->delete($id);
    header("Location: detalhes_estabelecimento_empresa.php?id=" . $estabelecimentoId);
    exit();
}
