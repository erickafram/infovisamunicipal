<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
include '../header.php';

// Consulta para obter todos os documentos cadastrados
$documentos = $conn->query("SELECT * FROM documento");

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Cadastrar Documento</title>
</head>

<body>
    <div class="container mt-5">
        <h3 class="mb-4">Cadastrar Novo Documento</h3>

        <!-- Formulário de cadastro de documento -->
        <form action="salvar_documento.php" method="POST" class="mb-5">
            <div class="form-group mb-3">
                <label for="nome">Nome do Documento</label>
                <input type="text" id="nome" name="nome" class="form-control" required>
            </div>
            <div class="form-group mb-3">
                <label for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao" class="form-control" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Cadastrar</button>
        </form>

        <hr>

        <!-- Listagem dos documentos existentes com opções de edição e exclusão -->
        <h5 class="mt-4">Documentos Cadastrados</h5>
        <?php if ($documentos->num_rows > 0): ?>
            <ul class="list-group">
                <?php while ($documento = $documentos->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($documento['nome']); ?></strong><br>
                            <small><?php echo htmlspecialchars($documento['descricao']); ?></small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Ações
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a href="editar_documento_individual.php?id=<?php echo $documento['id']; ?>" class="dropdown-item text-warning">Editar</a>
                                </li>
                                <li>
                                    <form action="excluir_documento.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="documento_id" value="<?php echo $documento['id']; ?>">
                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Tem certeza que deseja excluir este documento?');">Excluir</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">Nenhum documento cadastrado.</p>
        <?php endif; ?>
    </div>
</body>

</html>