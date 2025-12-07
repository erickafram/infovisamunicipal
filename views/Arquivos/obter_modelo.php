<?php
session_start();
require_once '../../conf/database.php';

if (isset($_GET['tipo_documento'])) {
    if (isset($_SESSION['user']['municipio'])) {
        $municipio = $_SESSION['user']['municipio'];
        $tipo_documento = $_GET['tipo_documento'];
        
        $stmt = $conn->prepare("SELECT conteudo FROM modelos_documentos WHERE tipo_documento = ? AND municipio = ?");
        $stmt->bind_param('ss', $tipo_documento, $municipio);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $modelo = $result->fetch_assoc();
            echo $modelo['conteudo'];
        } else {
            echo '';
        }
    } else {
        echo 'Municipio não definido na sessão.';
    }
} else {
    echo 'Tipo de documento não especificado.';
}
?>
