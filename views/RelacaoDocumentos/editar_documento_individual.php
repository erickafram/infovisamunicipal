<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$documento_id = $_GET['id'] ?? null;
if (!$documento_id) {
    echo "ID do documento não fornecido.";
    exit();
}

// Busca o documento no banco de dados
$stmt = $conn->prepare("SELECT nome, descricao FROM documento WHERE id = ?");
$stmt->bind_param("i", $documento_id);
$stmt->execute();
$documento = $stmt->get_result()->fetch_assoc();

if (!$documento) {
    echo "Documento não encontrado.";
    exit();
}

// Atualiza o documento se o formulário for enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];

    $update_stmt = $conn->prepare("UPDATE documento SET nome = ?, descricao = ? WHERE id = ?");
    $update_stmt->bind_param("ssi", $nome, $descricao, $documento_id);

    if ($update_stmt->execute()) {
        header("Location: cadastrar_documento.php?success=Documento atualizado com sucesso!");
        exit();
    } else {
        echo "Erro ao atualizar documento: " . $conn->error;
    }

    $update_stmt->close();
}

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Editar Documento</title>
</head>

<body>
    <div class="container mt-5">
        <h3 class="mb-4">Editar Documento</h3>

        <!-- Formulário de edição de documento -->
        <form action="" method="POST">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome do Documento</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($documento['nome']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="4" required><?php echo htmlspecialchars($documento['descricao']); ?></textarea>
            </div>
            <div class="d-flex justify-content-between">
                <button type="submit" class="btn btn-success">Salvar Alterações</button>
                <a href="cadastrar_documento.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>

</html>