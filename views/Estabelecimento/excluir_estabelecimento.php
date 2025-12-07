<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 1) {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $estabelecimento = new Estabelecimento($conn);

    if ($estabelecimento->delete($id)) {
        header("Location: listar_estabelecimentos.php?success=" . urlencode("Estabelecimento excluído com sucesso."));
    } else {
        header("Location: listar_estabelecimentos.php?error=" . urlencode("Falha ao excluir o estabelecimento."));
    }
} else {
    header("Location: listar_estabelecimentos.php");
}
exit();
?>
