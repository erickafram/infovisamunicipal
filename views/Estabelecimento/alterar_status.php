<?php
session_start();
require_once '../../conf/database.php';

// Verificar autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && isset($_POST['status'])) {
    $id = intval($_POST['id']);
    $novoStatus = $_POST['status'];

    // Atualizar status no banco de dados
    $stmt = $conn->prepare("UPDATE estabelecimentos SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $novoStatus, $id);

    if ($stmt->execute()) {
        header("Location: detalhes_estabelecimento.php?id=$id&success=1");
        exit();
    } else {
        header("Location: detalhes_estabelecimento.php?id=$id&error=" . urlencode("Erro ao alterar o status do estabelecimento."));
        exit();
    }
} else {
    header("Location: detalhes_estabelecimento.php?id=$id&error=" . urlencode("Dados inválidos."));
    exit();
}
