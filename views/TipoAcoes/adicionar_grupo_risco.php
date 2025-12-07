<?php
session_start();
include '../header.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 1) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descricao = $_POST['descricao'];

    $stmt = $conn->prepare("INSERT INTO grupo_risco (descricao) VALUES (?)");
    $stmt->bind_param("s", $descricao);

    if ($stmt->execute()) {
        header("Location: adicionar_grupo_risco.php?success=Grupo de risco adicionado com sucesso.");
        exit();
    } else {
        $error = "Erro ao adicionar o grupo de risco: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}

$gruposRisco = $conn->query("SELECT id, descricao FROM grupo_risco");
?>

<div class="container mt-5">
    <h4>Adicionar Grupo de Risco</h4>

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

    <form action="adicionar_grupo_risco.php" method="POST">
        <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <input type="text" class="form-control" id="descricao" name="descricao" required>
        </div>
        <button type="submit" class="btn btn-primary">Adicionar Grupo de Risco</button>
    </form>

    <h4 class="mt-5">Lista de Grupos de Risco</h4>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($grupo = $gruposRisco->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($grupo['id']); ?></td>
                    <td><?php echo htmlspecialchars($grupo['descricao']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include '../footer.php'; ?>
