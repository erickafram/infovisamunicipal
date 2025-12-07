<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 1) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $grupo_risco_id = $_POST['grupo_risco_id'];
    $pontuacao = $_POST['pontuacao'];

    // Insere ou atualiza a pontuação do grupo de risco
    $stmt = $conn->prepare("
        INSERT INTO grupo_risco_pontuacao (grupo_risco_id, pontuacao)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE pontuacao = VALUES(pontuacao)
    ");
    $stmt->bind_param("ii", $grupo_risco_id, $pontuacao);

    if ($stmt->execute()) {
        header("Location: atualizar_pontuacao_grupo.php?success=Pontuação atualizada com sucesso.");
        exit();
    } else {
        $error = "Erro ao atualizar a pontuação: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}

$gruposRisco = $conn->query("SELECT id, descricao FROM grupo_risco");

include '../header.php';
?>

<div class="container mt-5">
    <h4>Atualizar Pontuação de Grupo de Risco</h4>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <form action="atualizar_pontuacao_grupo.php" method="POST">
        <div class="mb-3">
            <label for="grupo_risco_id" class="form-label">Grupo de Risco</label>
            <select class="form-control" id="grupo_risco_id" name="grupo_risco_id" required>
                <?php while ($grupo = $gruposRisco->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($grupo['id']); ?>"><?php echo htmlspecialchars($grupo['descricao']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="pontuacao" class="form-label">Pontuação</label>
            <input type="number" class="form-control" id="pontuacao" name="pontuacao" required>
        </div>
        <button type="submit" class="btn btn-primary">Atualizar Pontuação</button>
    </form>
</div>

<?php include '../footer.php'; ?>
