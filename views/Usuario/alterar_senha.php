<?php
session_start();
include '../header.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}

// Verificar se há mensagens de sucesso ou erro na URL
$successMessage = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;
?>

<div class="container mt-5">
    <h2>Alterar Senha</h2>

    <!-- Exibir mensagem de sucesso, se existir -->
    <?php if ($successMessage): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $successMessage; ?>
        </div>
    <?php endif; ?>

    <!-- Exibir mensagem de erro, se existir -->
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <form action="../../controllers/UserController.php?action=alterar_senha" method="POST">
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

<?php include '../footer.php'; ?>