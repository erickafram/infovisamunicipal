<?php
session_start();
include '../../includes/header_empresa.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}
?>

<div class="container mt-5">
    <h2>Alterar Senha</h2>
    <form action="../../controllers/EmpresaController.php?action=alterar_senha" method="POST">
        <div class="mb-3">
            <label for="senha_atual" class="form-label">Senha Atual</label>
            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
        </div>
        <div class="mb-3">
            <label for="nova_senha" class="form-label">Nova Senha</label>
            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
        </div>
        <div class="mb-3">
            <label for="confirmar_nova_senha" class="form-label">Confirmar Nova Senha</label>
            <input type="password" class="form-control" id="confirmar_nova_senha" name="confirmar_nova_senha" required>
        </div>
        <button type="submit" class="btn btn-primary">Alterar Senha</button>
    </form>
</div>