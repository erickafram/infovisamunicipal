<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Logomarca.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$municipio = $_GET['municipio'];
$logomarcaModel = new Logomarca($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logomarcaModel->deleteLogomarca($municipio);
    header("Location: listar_logomarcas.php");
    exit();
}

$logomarca = $logomarcaModel->getLogomarcaByMunicipio($municipio);
include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Logomarca</title>
</head>
<body>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Excluir Logomarca</h6>
        </div>
        <div class="card-body">
            <p>Você tem certeza que deseja excluir a logomarca do município <strong><?php echo htmlspecialchars($municipio); ?></strong>?</p>
            <form action="excluir_logomarca.php?municipio=<?php echo htmlspecialchars($municipio); ?>" method="post">
                <button type="submit" class="btn btn-danger">Excluir</button>
                <a href="listar_logomarcas.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

</body>
</html>
