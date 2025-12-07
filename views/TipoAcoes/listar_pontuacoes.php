<?php
session_start();
include '../header.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 3) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$municipio = $_SESSION['user']['municipio'];
$query = "SELECT acoes_pontuacao.id, tipos_acoes_executadas.descricao, acoes_pontuacao.pontuacao 
          FROM acoes_pontuacao 
          JOIN tipos_acoes_executadas ON acoes_pontuacao.acao_id = tipos_acoes_executadas.id 
          WHERE acoes_pontuacao.municipio = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $municipio);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container mt-5">
    <h4>Lista de Pontuações das Ações</h4>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <a href="adicionar_pontuacao.php" class="btn btn-primary mb-3">Adicionar Pontuação</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Descrição</th>
                <th>Pontuação</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['descricao']); ?></td>
                    <td><?php echo htmlspecialchars($row['pontuacao']); ?></td>
                    <td>
                        <a href="editar_pontuacao.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-info btn-sm">Editar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include '../footer.php'; ?>
