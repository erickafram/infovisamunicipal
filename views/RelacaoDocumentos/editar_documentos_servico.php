<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$servico_id = $_GET['id'] ?? null;
if (!$servico_id) {
    echo "ID do tipo de serviço não fornecido.";
    exit();
}

// Busca informações do tipo de serviço
$stmt_servico = $conn->prepare("SELECT * FROM tipo_servico WHERE id = ?");
$stmt_servico->bind_param("i", $servico_id);
$stmt_servico->execute();
$servico = $stmt_servico->get_result()->fetch_assoc();

// Busca todos os documentos
$documentos = $conn->query("SELECT * FROM documento");

// Busca documentos vinculados ao tipo de serviço
$vinculos = $conn->prepare("SELECT documento_id FROM servico_documento WHERE tipo_servico_id = ?");
$vinculos->bind_param("i", $servico_id);
$vinculos->execute();
$vinculos_result = $vinculos->get_result();
$documentos_vinculados = [];
while ($row = $vinculos_result->fetch_assoc()) {
    $documentos_vinculados[] = $row['documento_id'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['vincular_documentos'])) {
    // Remove todos os vínculos antigos
    $conn->query("DELETE FROM servico_documento WHERE tipo_servico_id = $servico_id");

    // Adiciona os novos vínculos
    if (isset($_POST['documentos'])) {
        foreach ($_POST['documentos'] as $documento_id) {
            $stmt = $conn->prepare("INSERT INTO servico_documento (tipo_servico_id, documento_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $servico_id, $documento_id);
            $stmt->execute();
        }
    }

    header("Location: editar_documentos_servico.php?id=$servico_id");
    exit();
}

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Vincular Documentos - <?php echo htmlspecialchars($servico['nome']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h3>Vincular Documentos ao Serviço: <?php echo htmlspecialchars($servico['nome']); ?></h3>

        <!-- Formulário para vincular documentos -->
        <form action="" method="POST">
            <h5>Selecione os documentos necessários</h5>
            <ul class="list-group">
                <?php while ($doc = $documentos->fetch_assoc()): ?>
                    <li class="list-group-item">
                        <input type="checkbox" name="documentos[]" value="<?php echo $doc['id']; ?>"
                            <?php echo in_array($doc['id'], $documentos_vinculados) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($doc['nome']); ?> - <?php echo htmlspecialchars($doc['descricao']); ?>
                    </li>
                <?php endwhile; ?>
            </ul>
            <button type="submit" name="vincular_documentos" class="btn btn-primary mt-3">Salvar Vinculações</button>
        </form>
    </div>
</body>

</html>