<?php
session_start();
require_once '../../conf/database.php';
require_once '../../controllers/AlertaController.php';

// Verifica se o usuário está autenticado
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

// Obter o município do usuário logado
$municipioUsuario = $_SESSION['user']['municipio'];

$controller = new AlertaController($conn);

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'];

    if ($acao === 'criar') {
        $descricao = $_POST['descricao'];
        $prazo = $_POST['prazo'];
        $link = $_POST['link'] ?? null;
        $controller->criarAlertaParaEmpresas($descricao, $prazo, $link);
    } elseif ($acao === 'editar') {
        $id = $_POST['id'];
        $descricao = $_POST['descricao'];
        $prazo = $_POST['prazo'];
        $status = $_POST['status'];
        $link = $_POST['link'] ?? null;
        $controller->editarAlertaEmpresa($id, $descricao, $prazo, $status, $link);
    } elseif ($acao === 'excluir') {
        $id = $_POST['id'];
        $controller->excluirAlertaEmpresa($id);
    }
}

// Filtrar alertas pelo município do usuário logado
$alertas = $controller->listarAlertasPorMunicipio($municipioUsuario);
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Alertas para Empresas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>
    <?php include '../header.php'; ?>

    <div class="container mt-5">
        <!-- Aviso sobre a funcionalidade -->
        <div class="alert alert-info shadow-sm p-4 mb-4 rounded">
            <h5 class="text-primary"><i class="bi bi-info-circle me-2"></i> Informações sobre os alertas</h5>
            <p class="mb-0">
                Aqui você pode criar alertas informativos que serão exibidos para todas as empresas cadastradas no sistema. Os alertas criados com o status "ativo" e dentro do prazo definido aparecerão automaticamente no ambiente da empresa.
            </p>
        </div>

        <h3>Gerenciar Alertas para Empresas</h3>

        <!-- Formulário para criar novo alerta -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="alert_all.php" method="POST">
                    <input type="hidden" name="acao" value="criar">
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição:</label>
                        <textarea name="descricao" id="descricao" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editLink" class="form-label">Link (Opcional):</label>
                        <input type="url" name="link" id="editLink" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label for="prazo" class="form-label">Prazo de Expiração:</label>
                        <input type="date" name="prazo" id="prazo" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Criar Alerta</button>
                </form>
            </div>
        </div>

        <hr>

        <!-- Listagem de alertas -->
        <div class="container mt-5">
            <h2 class="text-center mb-4">Lista de Alertas</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-center">ID</th>
                            <th scope="col">Descrição</th>
                            <th scope="col" class="text-center">Prazo</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alertas as $alerta): ?>
                            <tr>
                                <td class="text-center"><?php echo $alerta['id']; ?></td>
                                <td><?php echo htmlspecialchars($alerta['descricao'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-center"><?php echo $alerta['prazo']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $alerta['status'] === 'ativo' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($alerta['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <button
                                            class="btn btn-sm btn-warning"
                                            onclick="editarAlerta(<?php echo $alerta['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Editar
                                        </button>
                                        <form action="alert_all.php" method="POST" class="d-inline">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?php echo $alerta['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i> Excluir
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div class="modal" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editForm" action="alert_all.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Editar Alerta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" id="editId">
                        <div class="mb-3">
                            <label for="editDescricao" class="form-label">Descrição:</label>
                            <textarea name="descricao" id="editDescricao" class="form-control" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editLink" class="form-label">Link (Opcional):</label>
                            <input type="url" name="link" id="editLink" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label for="editPrazo" class="form-label">Prazo de Expiração:</label>
                            <input type="date" name="prazo" id="editPrazo" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status:</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="ativo">Ativo</option>
                                <option value="finalizado">Finalizado</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editarAlerta(id) {
            const alerta = <?php echo json_encode($alertas); ?>.find(a => a.id == id);
            document.getElementById('editId').value = alerta.id;
            document.getElementById('editDescricao').value = alerta.descricao;
            document.getElementById('editPrazo').value = alerta.prazo;
            document.getElementById('editStatus').value = alerta.status;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>

</html>