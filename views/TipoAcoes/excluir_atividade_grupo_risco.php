<?php
session_start();
ob_start(); // Inicia o buffer de saída

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$municipio = $_GET['municipio'];
$grupo_risco = $_GET['grupo_risco'];

// Obtém o ID do grupo de risco com base na descrição
$stmt = $conn->prepare("SELECT id FROM grupo_risco WHERE descricao = ?");
$stmt->bind_param("s", $grupo_risco);
$stmt->execute();
$stmt->bind_result($grupo_risco_id);
$stmt->fetch();
$stmt->close();

if (!$grupo_risco_id) {
    ob_clean();
    header("Location: adicionar_atividade_grupo_risco.php?error=Grupo de risco não encontrado.");
    exit();
}

$stmt = $conn->prepare("DELETE FROM atividade_grupo_risco WHERE grupo_risco_id = ? AND municipio = ?");
$stmt->bind_param("is", $grupo_risco_id, $municipio);

if ($stmt->execute()) {
    ob_clean();
    header("Location: adicionar_atividade_grupo_risco.php?success=Atividades excluídas com sucesso.");
    exit();
} else {
    ob_clean();
    header("Location: adicionar_atividade_grupo_risco.php?error=Erro ao excluir as atividades.");
    exit();
}

$stmt->close();
?>
