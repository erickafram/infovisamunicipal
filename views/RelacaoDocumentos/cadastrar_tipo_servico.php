<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Lógica para inserir um novo tipo de serviço
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_servico'])) {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $tipo_processo = $_POST['tipo_processo'];

    $stmt = $conn->prepare("INSERT INTO tipo_servico (nome, descricao, tipo_processo) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nome, $descricao, $tipo_processo);

    if ($stmt->execute()) {
        header("Location: cadastrar_tipo_servico.php?success=Tipo de serviço cadastrado com sucesso!");
        exit();
    } else {
        echo "Erro ao cadastrar tipo de serviço: " . $conn->error;
    }
    $stmt->close();
}

// Lógica para excluir um tipo de serviço
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_servico'])) {
    $servico_id = $_POST['servico_id'];
    $stmt = $conn->prepare("DELETE FROM tipo_servico WHERE id = ?");
    $stmt->bind_param("i", $servico_id);

    if ($stmt->execute()) {
        header("Location: cadastrar_tipo_servico.php?success=Tipo de serviço excluído com sucesso!");
        exit();
    } else {
        echo "Erro ao excluir o tipo de serviço: " . $conn->error;
    }
    $stmt->close();
}

include '../header.php';

// Consulta para obter todos os tipos de serviço
$servicos = $conn->query("SELECT * FROM tipo_servico");
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Cadastrar Tipo de Serviço</title>
</head>

<body>
    <div class="container mt-5">
        <h3 class="mb-4">Cadastrar Tipo de Serviço</h3>
        <!-- Formulário para adicionar um novo tipo de serviço -->
        <form action="cadastrar_tipo_servico.php" method="POST" class="mb-4 shadow p-4 border rounded">
            <input type="hidden" name="adicionar_servico" value="1">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome do Serviço</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" required></textarea>
            </div>
            <div class="mb-3">
                <label for="tipo_processo" class="form-label">Tipo de Processo</label>
                <select id="tipo_processo" name="tipo_processo" class="form-select" required>
                    <option value="LICENCIAMENTO">Licenciamento</option>
                    <option value="PROJETO ARQUITETÔNICO">Projeto Arquitetônico</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Cadastrar</button>
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </form>

        <hr>

        <!-- Listagem de tipos de serviço existentes com opções de edição e exclusão -->
        <h4 class="mt-4">Tipos de Serviço Cadastrados</h4>
        <?php if ($servicos->num_rows > 0): ?>
            <table class="table table-bordered table-hover mt-3 shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th>Tipo de Processo</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($servico = $servicos->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($servico['nome']); ?></td>
                            <td><?php echo htmlspecialchars($servico['descricao']); ?></td>
                            <td><?php echo htmlspecialchars($servico['tipo_processo']); ?></td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Ações
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="editar_tipo_servico.php?id=<?php echo $servico['id']; ?>">Editar</a>
                                        </li>
                                        <li>
                                            <form action="cadastrar_tipo_servico.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este tipo de serviço?');" style="display:inline;">
                                                <input type="hidden" name="servico_id" value="<?php echo $servico['id']; ?>">
                                                <button type="submit" name="excluir_servico" class="dropdown-item text-danger">Excluir</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Nenhum tipo de serviço cadastrado.</p>
        <?php endif; ?>
    </div>
</body>

</html>