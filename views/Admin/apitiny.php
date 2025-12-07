<?php
session_start();
ob_start();
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'salvar' || $_POST['acao'] == 'editar') {
        $nomeApi = $_POST['nome_api'];
        $chaveApi = $_POST['chave_api'];
        
        if ($_POST['acao'] == 'salvar') {
            $sql = "INSERT INTO configuracoes_apis (nome_api, chave_api) VALUES (?, ?)";
        } elseif ($_POST['acao'] == 'editar' && isset($_POST['id'])) {
            $id = $_POST['id'];
            $sql = "UPDATE configuracoes_apis SET nome_api = ?, chave_api = ? WHERE id = ?";
        }

        $stmt = $conn->prepare($sql);
        if ($_POST['acao'] == 'salvar') {
            $stmt->bind_param('ss', $nomeApi, $chaveApi);
        } elseif ($_POST['acao'] == 'editar') {
            $stmt->bind_param('ssi', $nomeApi, $chaveApi, $id);
        }
        $stmt->execute();

        $message = "Chave API " . ($_POST['acao'] == 'salvar' ? "salva" : "atualizada") . " com sucesso!";
    } elseif ($_POST['acao'] == 'excluir' && isset($_POST['id'])) {
        $id = $_POST['id'];

        $sql = "DELETE FROM configuracoes_apis WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $message = "Chave API excluída com sucesso!";
    }
}

$sql = "SELECT id, nome_api, chave_api FROM configuracoes_apis";
$result = $conn->query($sql);
$configuracoesApis = [];
while ($row = $result->fetch_assoc()) {
    $configuracoesApis[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar APIs</title>
    <!-- Adicione o link para o Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <?php if ($message) : ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        Configurar APIs
                    </div>
                    <div class="card-body">
                        <form method="post" action="apitiny.php">
                            <input type="hidden" name="acao" id="form-acao" value="salvar">
                            <input type="hidden" name="id" id="form-id">
                            <div class="mb-3">
                                <label for="nome_api" class="form-label">Nome da API:</label>
                                <input type="text" class="form-control" id="nome_api" name="nome_api" required>
                            </div>
                            <div class="mb-3">
                                <label for="chave_api" class="form-label">Chave API:</label>
                                <input type="text" class="form-control" id="chave_api" name="chave_api" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </form>
                    </div>
                </div>
                <div class="mt-5">
                    <h5>Chaves API configuradas:</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome da API</th>
                                <th>Chave API</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configuracoesApis as $config) : ?>
                                <tr>
                                    <td><?php echo $config['id']; ?></td>
                                    <td><?php echo htmlspecialchars($config['nome_api']); ?></td>
                                    <td><?php echo htmlspecialchars($config['chave_api']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editarApi(<?php echo $config['id']; ?>, '<?php echo htmlspecialchars($config['nome_api']); ?>', '<?php echo htmlspecialchars($config['chave_api']); ?>')">Editar</button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmarExclusao(<?php echo $config['id']; ?>)">Excluir</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para confirmação de exclusão -->
    <div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalExcluirLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Tem certeza que deseja excluir esta chave API?
                </div>
                <div class="modal-footer">
                    <form method="post" action="apitiny.php" id="formExcluir">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" id="excluir-id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para edição de API -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarLabel">Editar API</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditar" method="post" action="apitiny.php">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" id="editar-id">
                        <div class="mb-3">
                            <label for="editar-nome-api" class="form-label">Nome da API:</label>
                            <input type="text" class="form-control" id="editar-nome-api" name="nome_api" required>
                        </div>
                        <div class="mb-3">
                            <label for="editar-chave-api" class="form-label">Chave API:</label>
                            <input type="text" class="form-control" id="editar-chave-api" name="chave_api" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editarApi(id, nome, chave) {
            document.getElementById('editar-id').value = id;
            document.getElementById('editar-nome-api').value = nome;
            document.getElementById('editar-chave-api').value = chave;
            var modalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));
            modalEditar.show();
        }

        function confirmarExclusao(id) {
            document.getElementById('excluir-id').value = id;
            var modalExcluir = new bootstrap.Modal(document.getElementById('modalExcluir'));
            modalExcluir.show();
        }
    </script>

</body>

</html>
