<?php
session_start();
include '../header.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Assinatura.php';
require_once '../../models/Arquivo.php';

$assinaturaModel = new Assinatura($conn);
$arquivoModel = new Arquivo($conn);
$user_id = $_SESSION['user']['id'];

$assinaturasPendentes = $assinaturaModel->getAssinaturasPendentes($user_id);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas Pendentes</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h5>Assinaturas Pendentes</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($assinaturasPendentes)) : ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tipo de Documento</th>
                                <th>Data de Criação Documento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assinaturasPendentes as $assinatura) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assinatura['tipo_documento']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($assinatura['data_assinatura']))); ?></td>
                                    <td>
                                        <a href="../Dashboard/visualizar_arquivo.php?arquivo_id=<?php echo $assinatura['arquivo_id']; ?>" class="btn btn-primary btn-sm">Visualizar e Assinar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>Não há assinaturas pendentes.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>
