<?php
session_start();
ob_start();
include '../header.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 3) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if (!isset($_GET['id'])) {
    echo "ID da pontuação não fornecido!";
    exit();
}

$id = $_GET['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pontuacao = $_POST['pontuacao'];
    $acao_id = $_POST['acao'];
    $grupo_risco_id = $_POST['grupo_risco'];

    $stmt = $conn->prepare("UPDATE acoes_pontuacao SET pontuacao = ?, acao_id = ?, grupo_risco_id = ? WHERE id = ?");
    $stmt->bind_param("iiii", $pontuacao, $acao_id, $grupo_risco_id, $id);

    if ($stmt->execute()) {
        header("Location: adicionar_pontuacao.php?success=Pontuação atualizada com sucesso.");
        exit();
    } else {
        $error = "Erro ao atualizar a pontuação: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
} else {
    $stmt = $conn->prepare("
        SELECT ap.pontuacao, ta.id AS acao_id, ta.descricao AS acao, gr.id AS grupo_risco_id, gr.descricao AS grupo_risco
        FROM acoes_pontuacao ap
        JOIN tipos_acoes_executadas ta ON ap.acao_id = ta.id
        JOIN grupo_risco gr ON ap.grupo_risco_id = gr.id
        WHERE ap.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($pontuacao, $acao_id, $acao, $grupo_risco_id, $grupo_risco);
    $stmt->fetch();
    $stmt->close();
}
?>

<div class="container mt-5">
    <h4>Editar Pontuação da Ação</h4>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="editar_pontuacao.php?id=<?php echo htmlspecialchars($id); ?>" method="POST">
        <div class="mb-3">
            <label for="acao" class="form-label">Ação</label>
            <select class="form-control" id="acao" name="acao">
                <?php
                $acoes = $conn->query("SELECT id, descricao FROM tipos_acoes_executadas");
                while ($row = $acoes->fetch_assoc()) {
                    echo "<option value='{$row['id']}'" . ($row['id'] == $acao_id ? " selected" : "") . ">{$row['descricao']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="grupo_risco" class="form-label">Grupo de Risco</label>
            <select class="form-control" id="grupo_risco" name="grupo_risco">
                <?php
                $grupos = $conn->query("SELECT id, descricao FROM grupo_risco");
                while ($row = $grupos->fetch_assoc()) {
                    echo "<option value='{$row['id']}'" . ($row['id'] == $grupo_risco_id ? " selected" : "") . ">{$row['descricao']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="pontuacao" class="form-label">Pontuação</label>
            <input type="number" class="form-control" id="pontuacao" name="pontuacao" value="<?php echo htmlspecialchars($pontuacao); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Atualizar</button>
    </form>
</div>

<?php include '../footer.php'; ?>
<?php
ob_end_flush();
?>
