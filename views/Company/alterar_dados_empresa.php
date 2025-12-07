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
    <h2>Alterar Dados Pessoais</h2>

    <?php if (isset($_GET['mensagem'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_GET['tipoMensagem']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['mensagem']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="../../controllers/EmpresaController.php?action=alterar_dados" method="POST">
        <div class="mb-3">
            <label for="nome_completo" class="form-label">Nome Completo</label>
            <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($_SESSION['user']['nome_completo']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_SESSION['user']['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="cpf" class="form-label">CPF</label>
            <div class="input-group">
                <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($_SESSION['user']['cpf']) ?>" readonly required>
                <span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" title="O CPF não pode ser alterado por questões de segurança">
                    <i class="fas fa-lock text-secondary"></i>
                </span>
            </div>
            <small class="text-muted">O CPF não pode ser alterado. Entre em contato com o administrador se precisar modificá-lo.</small>
        </div>
        <div class="mb-3">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($_SESSION['user']['telefone']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
    </form>
</div>

<!-- Inclua os scripts necessários -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
    $(document).ready(function() {
        // Aplica a máscara para CPF
        $('#cpf').mask('000.000.000-00', {
            reverse: true
        });

        // Aplica a máscara para Telefone (com suporte a DDD e 9º dígito)
        $('#telefone').mask('(00) 00000-0000');
    });
</script>