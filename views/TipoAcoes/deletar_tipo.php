<?php
session_start();


if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $query = "DELETE FROM tipos_acoes_executadas WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: listar_tipos.php?success=Tipo de ação deletado com sucesso");
    } else {
        header("Location: listar_tipos.php?error=Erro ao deletar o tipo de ação");
    }
    
    $stmt->close();
    $conn->close();
} else {
    header("Location: listar_tipos.php");
    exit();
}
include '../header.php';
?>
