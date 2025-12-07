<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$servico_id = $_GET['id'] ?? null;
if (!$servico_id) {
    echo "ID do tipo de serviço não fornecido.";
    exit();
}

// Busca os dados do tipo de serviço no banco
$stmt_servico = $conn->prepare("SELECT * FROM tipo_servico WHERE id = ?");
$stmt_servico->bind_param("i", $servico_id);
$stmt_servico->execute();
$servico = $stmt_servico->get_result()->fetch_assoc();

if (!$servico) {
    echo "Tipo de serviço não encontrado.";
    exit();
}

// Atualiza o tipo de serviço se o formulário for enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $tipo_processo = $_POST['tipo_processo'];

    $update_stmt = $conn->prepare("UPDATE tipo_servico SET nome = ?, descricao = ?, tipo_processo = ? WHERE id = ?");
    $update_stmt->bind_param("sssi", $nome, $descricao, $tipo_processo, $servico_id);

    if ($update_stmt->execute()) {
        header("Location: cadastrar_tipo_servico.php?success=Tipo de serviço atualizado com sucesso!");
        exit();
    } else {
        echo "Erro ao atualizar tipo de serviço: " . $conn->error;
    }

    $update_stmt->close();
}

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Editar Tipo de Serviço</title>
</head>

<body>
    <div class="container mt-5">
        <h3 class="mb-4">Editar Tipo de Serviço</h3>
        <form action="" method="POST">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome do Serviço</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($servico['nome']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" required><?php echo htmlspecialchars($servico['descricao']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="tipo_processo" class="form-label">Tipo de Processo</label>
                <select id="tipo_processo" name="tipo_processo" class="form-select" required>
                    <option value="LICENCIAMENTO" <?php echo $servico['tipo_processo'] == 'LICENCIAMENTO' ? 'selected' : ''; ?>>Licenciamento</option>
                    <option value="PROJETO ARQUITETÔNICO" <?php echo $servico['tipo_processo'] == 'PROJETO ARQUITETÔNICO' ? 'selected' : ''; ?>>Projeto Arquitetônico</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="cadastrar_tipo_servico.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>

</html>