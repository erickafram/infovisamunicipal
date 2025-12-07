<?php
session_start();
ob_start(); // Inicia o buffer de saída
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descricao = $_POST['descricao'];
    $codigo_procedimento = $_POST['codigo_procedimento'];
    $atividade_sia = isset($_POST['atividade_sia']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO tipos_acoes_executadas (descricao, codigo_procedimento, atividade_sia) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $descricao, $codigo_procedimento, $atividade_sia);

    if ($stmt->execute()) {
        ob_clean(); // Limpa o buffer de saída antes de enviar o header
        header("Location: listar_tipos.php?success=Tipo de ação adicionado com sucesso.");
        exit();
    } else {
        $error = "Erro ao adicionar o tipo de ação: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>

<div class="container mt-5">
    <h4>Adicionar Tipo de Ação</h4>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="adicionar_tipo.php" method="POST">
        <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <input type="text" class="form-control" id="descricao" name="descricao" required>
        </div>
        <div class="mb-3">
            <label for="codigo_procedimento" class="form-label">Código Procedimento</label>
            <input type="text" class="form-control" id="codigo_procedimento" name="codigo_procedimento" required>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="atividade_sia" name="atividade_sia">
            <label for="atividade_sia" class="form-check-label">É uma atividade do sistema SIA?</label>
        </div>
        <button type="submit" class="btn btn-primary">Adicionar</button>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.2/jquery.validate.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.7/jquery.inputmask.min.js"></script>
<script>
    $(document).ready(function () {
        $("form").validate();
        $('#codigo_procedimento').inputmask('99.99.99.999-9');
    });
</script>

<?php include '../footer.php'; ?>
