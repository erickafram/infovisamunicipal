<?php
require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$codigo_verificador = $_GET['codigo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo_verificador = $_POST['codigo_verificador'] ?? '';
}

if (!empty($codigo_verificador)) {
    $arquivo = new Arquivo($conn);
    $arquivo_info = $arquivo->getArquivoByCodigo($codigo_verificador);

    if (!$arquivo_info) {
        $erro = "Código verificador inválido.";
    } else {
        $caminho_arquivo = realpath(__DIR__ . '/../../' . $arquivo_info['caminho_arquivo']);
        if ($caminho_arquivo && file_exists($caminho_arquivo)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($caminho_arquivo) . '"');
            readfile($caminho_arquivo);
            exit();
        } else {
            $erro = "Falha ao carregar o documento PDF. Arquivo não encontrado.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Documento</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>

    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Verificar Documento</h6>
            </div>
            <div class="card-body">
                <?php if (isset($erro)): ?>
                    <div class="alert alert-danger"><?php echo $erro; ?></div>
                <?php endif; ?>
                <form method="POST" action="verificar.php">
                    <div class="form-group">
                        <label for="codigo_verificador">Código Verificador</label>
                        <input type="text" class="form-control" id="codigo_verificador" name="codigo_verificador" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Verificar</button>
                </form>
            </div>
        </div>
    </div>

</body>

</html>