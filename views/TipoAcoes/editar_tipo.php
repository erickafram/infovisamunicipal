<?php
session_start();
ob_start();
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if (!isset($_GET['id'])) {
    echo "ID do tipo de ação não fornecido!";
    exit();
}

$id = $_GET['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descricao = $_POST['descricao'];
    $codigo_procedimento = $_POST['codigo_procedimento'];
    $atividade_sia = isset($_POST['atividade_sia']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE tipos_acoes_executadas SET descricao = ?, codigo_procedimento = ?, atividade_sia = ? WHERE id = ?");
    $stmt->bind_param("ssii", $descricao, $codigo_procedimento, $atividade_sia, $id);

    if ($stmt->execute()) {
        header("Location: listar_tipos.php?success=Tipo de ação atualizado com sucesso.");
        exit();
    } else {
        $error = "Erro ao atualizar o tipo de ação: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
} else {
    $stmt = $conn->prepare("SELECT descricao, codigo_procedimento, atividade_sia FROM tipos_acoes_executadas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($descricao, $codigo_procedimento, $atividade_sia);
    $stmt->fetch();
    $stmt->close();
}
?>

<div class="container mt-5">
    <h4>Editar Tipo de Ação</h4>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="editar_tipo.php?id=<?php echo htmlspecialchars($id); ?>" method="POST">
        <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <input type="text" class="form-control" id="descricao" name="descricao" value="<?php echo htmlspecialchars($descricao); ?>" required>
        </div>
        <div class="mb-3">
            <label for="codigo_procedimento" class="form-label">Código Procedimento</label>
            <input type="text" class="form-control" id="codigo_procedimento" name="codigo_procedimento" value="<?php echo htmlspecialchars($codigo_procedimento); ?>" required>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="atividade_sia" name="atividade_sia" <?php if ($atividade_sia) echo 'checked'; ?>>
            <label for="atividade_sia" class="form-check-label">É uma atividade do sistema SIA?</label>
        </div>
        <button type="submit" class="btn btn-primary">Atualizar</button>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.7/jquery.inputmask.min.js"></script>
<script>
    $(document).ready(function(){
        $('#codigo_procedimento').inputmask('99.99.99.999-9');
    });
</script>

<?php include '../footer.php'; ?>
<?php
ob_end_flush();
?>
