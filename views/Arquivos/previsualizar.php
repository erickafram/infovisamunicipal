<?php
session_start();
require_once '../../controllers/ArquivoController.php';

$controller = new ArquivoController($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean();
    $controller->previsualizar();
}
?>