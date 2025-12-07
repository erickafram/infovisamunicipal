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

// Verifica se o usuário logado pertence ao município
if ($_SESSION['user']['municipio'] !== $municipio) {
    die('Você não tem permissão para editar a logomarca deste município.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $espacamento = isset($_POST['espacamento']) ? intval($_POST['espacamento']) : 40;
    $caminho_logomarca = '';

    if (isset($_FILES['logomarca']) && $_FILES['logomarca']['error'] == UPLOAD_ERR_OK) {
        $nome_logomarca = $_FILES['logomarca']['name'];
        $destino = '../../uploads/logomarcas/' . $nome_logomarca;

        if (move_uploaded_file($_FILES['logomarca']['tmp_name'], $destino)) {
            $caminho_logomarca = $destino;
        } else {
            $error = "Falha ao fazer upload da logomarca.";
        }
    } else {
        $caminho_logomarca = $_POST['logomarca_atual'];
    }

    if (empty($error)) {
        $logomarcaModel->updateLogomarca($municipio, $caminho_logomarca, $espacamento);
        header("Location: listar_logomarcas.php");
        exit();
    }
}

$logomarca = $logomarcaModel->getLogomarcaByMunicipio($municipio);
include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Logomarca</title>
</head>
<body>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Editar Logomarca</h6>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form action="editar_logomarca.php?municipio=<?php echo htmlspecialchars($municipio); ?>" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="logomarca">Logomarca Atual:</label>
                    <?php if ($logomarca && file_exists($logomarca['caminho_logomarca'])): ?>
                        <div class="mb-3">
                            <img src="<?php echo $logomarca['caminho_logomarca']; ?>" alt="Logomarca" style="max-width: 100px;">
                            <input type="hidden" name="logomarca_atual" value="<?php echo $logomarca['caminho_logomarca']; ?>">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="logomarca" id="logomarca" class="form-control">
                </div>
                <div class="form-group">
                    <label for="espacamento">Espaçamento abaixo da logomarca:</label>
                    <input type="number" name="espacamento" id="espacamento" class="form-control" value="<?php echo $logomarca['espacamento'] ?? 40; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
<?php include '../footer.php'; ?>
