<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: listar_ordens.php");
    exit();
}

$id = $_GET['id'];
$ordemServico = new OrdemServico($conn);

// Verificar se o usuário tem permissão para acessar esta ordem baseado no município
$municipioUsuario = $_SESSION['user']['municipio'];
if (!$ordemServico->podeAcessarOrdem($id, $municipioUsuario)) {
    header("Location: listar_ordens.php?error=Acesso negado. Você não tem permissão para editar esta ordem de serviço.");
    exit();
}

$ordem = $ordemServico->getOrdemById($id);

if (!$ordem) {
    echo "Ordem de serviço não encontrada.";
    exit();
}

// Obter técnicos disponíveis no município
$query = "SELECT id, nome_completo FROM usuarios WHERE nivel_acesso IN (3, 4)";
$result = $conn->query($query);
$tecnicos_disponiveis = $result->fetch_all(MYSQLI_ASSOC);

// Atualizar a ordem de serviço
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $tecnicos = isset($_POST['tecnicos']) ? json_encode($_POST['tecnicos']) : '';
    $observacao = $_POST['observacao'] ?? '';
    $descricao_encerramento = $_POST['descricao_encerramento'] ?? null;

    if (strtotime($data_fim) < strtotime($data_inicio)) {
        $error = "A data de fim não pode ser anterior à data de início.";
    } else {
        if ($ordemServico->update($id, $data_inicio, $data_fim, json_decode($ordem['acoes_executadas']), $tecnicos, $ordem['pdf_path'], null, null, $observacao, $descricao_encerramento)) {
            header("Location: detalhes_ordem_sem_estabelecimento.php?id=$id&success=Ordem de serviço atualizada com sucesso.");
            exit();
        } else {
            $error = "Erro ao atualizar a ordem de serviço: " . $ordemServico->getLastError();
        }
    }
}

function formatDate($date)
{
    $dateTime = new DateTime($date);
    return $dateTime->format('Y-m-d');
}
include '../header.php'; // Incluindo o arquivo de cabeçalho
?>

<!DOCTYPE html>
<html>

<head>
    <title>Editar Ordem de Serviço (Sem Estabelecimento)</title>
</head>

<body>
    <div class="container mt-5">
        <h4>Editar Ordem de Serviço (Sem Estabelecimento)</h4>

        <?php if (isset($error)) : ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo formatDate($ordem['data_inicio']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo formatDate($ordem['data_fim']); ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="tecnicos" class="form-label">Técnicos</label>
                <select class="form-control" id="tecnicos" name="tecnicos[]" multiple>
                    <?php foreach ($tecnicos_disponiveis as $tecnico) : ?>
                        <option value="<?php echo $tecnico['id']; ?>" <?php echo in_array($tecnico['id'], json_decode($ordem['tecnicos'], true)) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tecnico['nome_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="observacao" class="form-label">Observação</label>
                <textarea class="form-control" id="observacao" name="observacao" rows="3"><?php echo htmlspecialchars($ordem['observacao']); ?></textarea>
            </div>


            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            <a href="detalhes_ordem_sem_estabelecimento.php?id=<?php echo htmlspecialchars($id); ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>

</html>