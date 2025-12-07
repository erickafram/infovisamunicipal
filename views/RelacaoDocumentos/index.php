<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
include '../header.php';

$servicos = $conn->query("SELECT * FROM tipo_servico");
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relação de Documentos</title>
</head>

<body>
    <div class="container mt-5">
        <h3>Tipos de Serviços e Documentos Vinculados</h3>
        <a href="cadastrar_tipo_servico.php" class="btn btn-primary mb-3">Cadastrar Tipo de Serviço</a>
        <a href="cadastrar_documento.php" class="btn btn-secondary mb-3">Cadastrar Documento</a>

        <?php while ($servico = $servicos->fetch_assoc()): ?>
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p><strong>Tipo de Processo:</strong> <?php echo htmlspecialchars($servico['tipo_processo']); ?></p>
                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($servico['nome']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($servico['descricao']); ?></p>
                        </div>
                        <!-- Dropdown de Ações -->
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Ações
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="editar_tipo_servico.php?id=<?php echo $servico['id']; ?>">Editar Tipo de Serviço</a>
                                </li>
                                <li>
                                    <form action="excluir_tipo_servico.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este tipo de serviço?');">
                                        <input type="hidden" name="servico_id" value="<?php echo $servico['id']; ?>">
                                        <button type="submit" class="dropdown-item text-danger">Excluir Tipo de Serviço</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <h6>Documentos Vinculados:</h6>
                    <ul class="list-unstyled ms-3">
                        <?php
                        $documentos = $conn->prepare("
                            SELECT d.nome, d.descricao 
                            FROM documento d 
                            JOIN servico_documento sd ON d.id = sd.documento_id 
                            WHERE sd.tipo_servico_id = ?
                        ");
                        $documentos->bind_param("i", $servico['id']);
                        $documentos->execute();
                        $result = $documentos->get_result();

                        if ($result->num_rows > 0) {
                            while ($doc = $result->fetch_assoc()) {
                                echo "<li><strong>" . htmlspecialchars($doc['nome']) . ":</strong> " . htmlspecialchars($doc['descricao']) . "</li>";
                            }
                        } else {
                            echo "<li class='text-muted'>Nenhum documento vinculado</li>";
                        }
                        ?>
                    </ul>
                    <a href="editar_documentos_servico.php?id=<?php echo htmlspecialchars($servico['id']); ?>" class="btn btn-outline-warning mt-2">Editar Documentos</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</body>

</html>