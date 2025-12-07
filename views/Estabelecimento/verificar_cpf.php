<?php
require_once '../../conf/database.php';
require_once '../../models/ResponsavelLegal.php';
require_once '../../models/ResponsavelTecnico.php';

$cpf = $_GET['cpf'];
$tipo = $_GET['tipo'];

if ($tipo == 'legal') {
    $responsavelLegal = new ResponsavelLegal($conn);
    $responsavelExistente = $responsavelLegal->findByCpf($cpf);
    if ($responsavelExistente) {
        echo json_encode(['existe' => true, 'nome' => $responsavelExistente['nome'], 'email' => $responsavelExistente['email'], 'telefone' => $responsavelExistente['telefone'], 'documento_identificacao' => $responsavelExistente['documento_identificacao']]);
    } else {
        echo json_encode(['existe' => false]);
    }
} elseif ($tipo == 'tecnico') {
    $responsavelTecnico = new ResponsavelTecnico($conn);
    $responsavelExistente = $responsavelTecnico->findByCpf($cpf);
    if ($responsavelExistente) {
        echo json_encode(['existe' => true, 'nome' => $responsavelExistente['nome'], 'email' => $responsavelExistente['email'], 'telefone' => $responsavelExistente['telefone'], 'conselho' => $responsavelExistente['conselho'], 'numero_registro_conselho' => $responsavelExistente['numero_registro_conselho'], 'carteirinha_conselho' => $responsavelExistente['carteirinha_conselho']]);
    } else {
        echo json_encode(['existe' => false]);
    }
}
?>
