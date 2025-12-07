<?php
session_start();
include '../header.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}
?>

<div class="container mt-5">
    <h1>Bem-vindo ao Painel de Controle</h1>
    <p>Olá, <?php echo htmlspecialchars($_SESSION['user']['nome_completo']); ?>! Este é o seu painel de controle.</p>
    <!-- Adicione aqui o conteúdo específico do seu painel de controle -->
</div>

<?php include '../footer.php'; ?>
