<?php
session_start();

// Verifica se o usuário tem permissão para acessar (níveis 1, 2 ou 3)
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Verifica se o ID do documento foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['documento_id'])) {
    $documento_id = $_POST['documento_id'];

    // Prepara e executa a exclusão do documento no banco de dados
    $stmt = $conn->prepare("DELETE FROM documento WHERE id = ?");
    $stmt->bind_param("i", $documento_id);

    if ($stmt->execute()) {
        // Redireciona para a página de documentos com uma mensagem de sucesso
        header("Location: cadastrar_documento.php?success=Documento excluído com sucesso");
        exit();
    } else {
        echo "Erro ao excluir o documento: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "Ação inválida.";
}

$conn->close();
