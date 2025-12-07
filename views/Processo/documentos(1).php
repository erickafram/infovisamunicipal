<?php
session_start();
ob_start(); // Inicia o buffer de saída
include '../header.php';

// Exibir mensagem de sucesso, se houver
if (isset($_SESSION['mensagem_sucesso'])) {
    echo "<div class='container mt-5 mt-alerta'><div class='alert alert-success' role='alert'>";
    echo $_SESSION['mensagem_sucesso'];
    echo "</div>";
    unset($_SESSION['mensagem_sucesso']); // Limpar a mensagem após exibir
}

// Definir variáveis do processo
if (isset($_GET['processo_id']) && isset($_GET['id'])) {
    $processo_id = $_GET['processo_id'];
    $estabelecimento_id = $_GET['id'];
} else {
    echo "ID do processo ou estabelecimento não fornecido!";
    exit();
}

// Instanciar objetos necessários
require_once '../../conf/database.php';
require_once '../../models/Documento.php';
require_once '../../models/Processo.php';
require_once '../../models/OrdemServico.php';
require_once '../../models/Arquivo.php';

$documento = new Documento($conn);
$processo = new Processo($conn);
$ordemServico = new OrdemServico($conn);
$arquivo = new Arquivo($conn);

// Verificar e exibir alerta de documentos pendentes
$documentosPendentes = $documento->getDocumentosByProcessoAndStatus($processo_id, 'pendente');
if (!empty($documentosPendentes)) {
    echo "<div class='container mt-3'>
            <div class='alert alert-warning d-flex align-items-center' role='alert'>
                <i class='fas fa-exclamation-triangle me-2'></i>
                Atenção: Existem " . count($documentosPendentes) . " documento(s) pendente(s) de aprovação.
            </div>
          </div>";
}


if (isset($_GET['processo_id']) && isset($_GET['id'])) {
    $processo_id = $_GET['processo_id'];
    $estabelecimento_id = $_GET['id'];
} else {
    echo "ID do processo ou estabelecimento não fornecido!";
    exit();
}

// Obtém o número do processo e outras informações
$processoDados = $processo->findById($processo_id);
$numero_processo = $processoDados['numero_processo'];
$nome_fantasia = $processoDados['nome_fantasia'];
$cnpj = $processoDados['cnpj'];
$endereco = $processoDados['logradouro'] . ', ' . $processoDados['numero'] . ', ' . $processoDados['complemento'] . ', ' . $processoDados['bairro'];
$telefone = $processoDados['ddd_telefone_1'] . ', ' . $processoDados['ddd_telefone_2'];
$tipo_processo = $processoDados['tipo_processo'];
$status_processo = $processoDados['status'];
$municipio_estabelecimento = $processoDados['municipio']; // Obtendo o município do estabelecimento

// Verificação de acesso ao município
if ($_SESSION['user']['nivel_acesso'] != 1 && $_SESSION['user']['municipio'] != $municipio_estabelecimento) {
    echo "Você não tem permissão para acessar informações deste processo.";
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['files'])) {
    $total_files = count($_FILES['files']['name']);
    $upload_dir = "../../uploads/";

    for ($i = 0; $i < $total_files; $i++) {
        $file_name = basename($_FILES["files"]["name"][$i]);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES["files"]["tmp_name"][$i], $target_file)) {
            $caminho_arquivo = 'uploads/' . $file_name;
            $documento->createDocumento($processo_id, $file_name, $caminho_arquivo);
        } else {
            echo "Erro ao fazer upload do arquivo: " . $file_name;
        }
    }

    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_nome'])) {
    $novo_nome = $_POST['novo_nome'];
    $documento_id = $_POST['documento_id'];
    if ($documento->updateNomeDocumento($documento_id, $novo_nome)) {
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
        exit();
    } else {
        echo "Erro ao atualizar o nome do documento.";
    }
}

if (isset($_GET['action'])) {
    $documento_id = isset($_GET['doc_id']) ? $_GET['doc_id'] : null;

    if ($_GET['action'] == 'delete' && $documento_id) {
        $usuario_id = $_SESSION['user']['id']; // Obtém o ID do usuário logado
        if ($documento->deleteDocumento($documento_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
            exit();
        } else {
            echo "Erro ao deletar o documento.";
        }
    } elseif ($_GET['action'] == 'approve' && $documento_id) {
        $usuario_id = $_SESSION['user']['id']; // ID do usuário logado
        if ($documento->approveDocumento($documento_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id&status=approved");
            exit();
        } else {
            echo "Erro ao aprovar o documento.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['negar_documento'])) {
    $documento_id = $_POST['documento_id'];
    $motivo_negacao = $_POST['motivo_negacao'];
    if ($documento->denyDocumento($documento_id, $motivo_negacao)) {
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id&status=denied");
        exit();
    } else {
        echo "Erro ao negar o documento.";
    }
}

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['doc_id'])) {
        $documento_id = $_GET['doc_id'];
        $usuario_id = $_SESSION['user']['id']; // Obtém o ID do usuário logado
        if ($documento->deleteDocumento($documento_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
            exit();
        } else {
            echo "Erro ao deletar o documento.";
        }
    } elseif ($_GET['action'] == 'delete_arquivo' && isset($_GET['arquivo_id'])) {
        $arquivo_id = $_GET['arquivo_id'];
        $usuario_id = $_SESSION['user']['id']; // Obtém o ID do usuário logado
        if ($arquivo->deleteArquivo($arquivo_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
            exit();
        } else {
            echo "Erro ao deletar o arquivo.";
        }
    }
}


if (isset($_GET['action']) && $_GET['action'] == 'delete_processo' && isset($_GET['processo_id'])) {
    $processo_id = $_GET['processo_id'];
    if ($processo->deleteProcesso($processo_id)) {
        header("Location: ../Estabelecimento/detalhes_estabelecimento.php?id=$estabelecimento_id&success=Processo excluído com sucesso.");
        exit();
    } else {
        echo "Erro ao excluir o processo.";
    }
}

if (isset($_GET['action']) && ($_GET['action'] == 'archive_processo' || $_GET['action'] == 'unarchive_processo') && isset($_GET['processo_id'])) {
    $processo_id = $_GET['processo_id'];
    if ($_GET['action'] == 'archive_processo') {
        $processo->archiveProcesso($processo_id);
    } else {
        $processo->unarchiveProcesso($processo_id);
    }
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['motivo_parado'])) {
    $motivo = $_POST['motivo'];
    $processo->stopProcesso($processo_id, $motivo);
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'restart_processo' && isset($_GET['processo_id'])) {
    $processo_id = $_GET['processo_id'];
    $processo->restartProcesso($processo_id);
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

// Processamento de alertas
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['adicionar_alerta'])) {
        $descricao = $_POST['descricao'];
        $prazo = $_POST['prazo'];
        $processo->createAlerta($processo_id, $descricao, $prazo);
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
        exit();
    } elseif (isset($_POST['editar_alerta'])) {
        $alerta_id = $_POST['alerta_id'];
        $descricao = $_POST['descricao'];
        $prazo = $_POST['prazo'];
        $status = $_POST['status'];
        $processo->updateAlerta($alerta_id, $descricao, $prazo, $status);
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
        exit();
    } elseif (isset($_POST['excluir_alerta'])) {
        $alerta_id = $_POST['alerta_id'];
        $processo->deleteAlerta($alerta_id);
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
        exit();
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'finalize_alerta' && isset($_GET['alerta_id'])) {
    $alerta_id = $_GET['alerta_id'];
    if ($processo->updateAlerta($alerta_id, null, null, 'finalizado')) {
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
        exit();
    } else {
        echo "Erro ao finalizar o alerta.";
    }
}

// Adicione o seguinte código onde achar mais apropriado no HTML, dentro do card de informações do processo:

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['designar_responsavel'])) {
    $usuario_id = $_POST['usuario_id'];
    $processo_id = $_POST['processo_id'];
    $descricao = $_POST['descricao'];

    $sql = "INSERT INTO processos_responsaveis (processo_id, usuario_id, descricao) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iis', $processo_id, $usuario_id, $descricao);
    $stmt->execute();
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remover_responsavel'])) {
    $responsavel_id = $_POST['responsavel_id'];

    $sql = "DELETE FROM processos_responsaveis WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $responsavel_id);
    $stmt->execute();
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

// Obter lista de usuários do mesmo município
$municipio = $_SESSION['user']['municipio'];
$sql = "SELECT id, nome_completo FROM usuarios WHERE municipio = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $municipio);
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obter responsáveis designados para o processo
$sql = "SELECT pr.id as responsavel_id, u.id as usuario_id, u.nome_completo, pr.descricao, pr.status
        FROM processos_responsaveis pr
        JOIN usuarios u ON pr.usuario_id = u.id
        WHERE pr.processo_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $processo_id);
$stmt->execute();
$responsaveis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Adicione o seguinte código onde achar mais apropriado no HTML, dentro do card de informações do processo:

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acompanhar_processo'])) {
    $usuario_id = $_SESSION['user']['id'];
    $processo_id = $_POST['processo_id'];
    $sql = "INSERT INTO processos_acompanhados (usuario_id, processo_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $usuario_id, $processo_id);
    $stmt->execute();
    $_SESSION['mensagem_sucesso'] = "Processo Acompanhado com sucesso.";
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['desacompanhar_processo'])) {
    $usuario_id = $_SESSION['user']['id'];
    $processo_id = $_POST['processo_id'];
    $sql = "DELETE FROM processos_acompanhados WHERE usuario_id = ? AND processo_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $usuario_id, $processo_id);
    $stmt->execute();
    $_SESSION['mensagem_sucesso'] = "Processo Desacompanhado com sucesso.";
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_resolvido'])) {
    $responsavel_id = $_POST['responsavel_id'];

    $sql = "UPDATE processos_responsaveis SET status = 'resolvido' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $responsavel_id);
    $stmt->execute();
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

//Rerveter status do documento - Erick Vinicius 16/12/2024
if (isset($_GET['action']) && $_GET['action'] == 'revert' && isset($_GET['doc_id'])) {
    $documento_id = $_GET['doc_id'];
    if ($documento->revertDocumento($documento_id)) {
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id&status=reverted");
        exit();
    } else {
        echo "Erro ao reverter o status do documento.";
    }
}

$historicoNegacoes = [];
$stmt = $conn->prepare("SELECT motivo_negacao, data_negacao, u.nome_completo 
                        FROM historico_negacoes h 
                        JOIN usuarios u ON h.usuario_id = u.id 
                        WHERE h.documento_id = ?");
$stmt->bind_param("i", $item['id']); // Certifique-se de que $item['id'] está definido
$stmt->execute();
$result = $stmt->get_result();
$historicoNegacoes = $result->fetch_all(MYSQLI_ASSOC);

$usuariosAcompanhando = [];
$sql = "SELECT u.nome_completo 
        FROM processos_acompanhados pa
        JOIN usuarios u ON pa.usuario_id = u.id
        WHERE pa.processo_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$result = $stmt->get_result();
$usuariosAcompanhando = $result->fetch_all(MYSQLI_ASSOC);



// Verificar se o usuário já está acompanhando o processo
$sql = "SELECT COUNT(*) as count FROM processos_acompanhados WHERE usuario_id = ? AND processo_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $_SESSION['user']['id'], $processo_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$isAcompanhando = $row['count'] > 0;

$documentos = $documento->getDocumentosByProcesso($processo_id);
$arquivos = $arquivo->getArquivosByProcesso($processo_id); // Obter arquivos do processo
$ordensServico = $ordemServico->getOrdensByProcesso($processo_id);
$motivo_parado = $processoDados['motivo_parado'];

$itens = array_merge(
    array_map(function ($doc) {
        $doc['tipo'] = 'documento';
        return $doc;
    }, $documentos),
    array_map(function ($arq) {
        $arq['tipo'] = 'arquivo';
        return $arq;
    }, $arquivos)
);

usort($itens, function ($a, $b) {
    return strtotime($b['data_upload']) - strtotime($a['data_upload']);
});
?>

<style>
    /* Estilos gerais */
    .custom-line {
        border-top: 1px solid #EEE;
        margin: 15px 0;
    }

    .card {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #eee;
        padding: 15px 20px;
    }

    .card-header h6 {
        margin: 0;
        font-weight: 600;
        color: #333;
    }

    .card-body {
        padding: 20px;
    }

    .list-group-item {
        padding: 15px 20px;
        border: 1px solid rgba(0, 0, 0, .125);
        margin-bottom: 8px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .list-group-item:hover {
        background-color: #f8f9fa;
        transform: translateX(5px);
    }

    .btn-group .btn {
        margin: 0 4px;
        border-radius: 4px;
    }

    .badge {
        font-size: 12px;
        padding: 4px 6px;
        border-radius: 10px;
    }

    .card-body .d-flex {
        align-items: center;
        gap: 8px;
    }


    /* Estilos específicos */
    .document-status {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
    }

    .document-actions {
        display: flex;
        gap: 8px;
    }

    .document-info {
        flex: 1;
    }

    .document-info small {
        display: block;
        color: #666;
        margin-top: 4px;
    }

    .alert-warning {
        background-color: #fff3cd;
        border-color: #ffeeba;
        color: #856404;
    }

    .alert-warning i {
        color: #ffc107;
    }

    .card.menu {
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: none;
    }

    .card-header {
        background-color: #f8f9fa;
        padding: 8px;
        font-size: 14px;
    }

    .card-body {
        padding: 10px;
    }

    .card-body a {
        font-size: 13px;
        color: #333;
        text-decoration: none;
    }

    .card-body a:hover {
        text-decoration: underline;
        color: #007bff;
    }

    .list-unstyled li {
        padding: 5px 0;
        line-height: 1.5;
    }

    .mb-1 {
        margin-bottom: 0rem !important;
    }

    .list-unstyled li {
        padding: 3px 0;
        line-height: 1.5;
    }

    .btn-group a {
        color: white;
    }

    /* Responsividade */
    @media (max-width: 768px) {
        .card-body {
            padding: 15px;
        }

        .list-group-item {
            padding: 12px 15px;
        }

        .btn-group .btn {
            padding: 4px 8px;
            font-size: 12px;
        }
    }
</style>
<div class="container mt-5">
    <div class="card mb-4">
        <div class="card-body">
            <p class="card-text estabelecimento">
                <?php if ($processoDados['tipo_pessoa'] == 'fisica'): ?>
                    <strong>Nome:</strong> <?php echo htmlspecialchars($processoDados['nome']); ?><br>
                    <strong>CPF:</strong> <?php echo htmlspecialchars($processoDados['cpf']); ?><br>
                <?php else: ?>
                    <strong>Nome do Estabelecimento:</strong> <strong style="text-decoration: underline;"><a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?php echo $estabelecimento_id; ?>"><?php echo htmlspecialchars($nome_fantasia); ?></a><br></strong>
                    <strong>CNPJ:</strong> <?php echo htmlspecialchars($cnpj); ?><br>
                <?php endif; ?>
                <strong>ENDEREÇO:</strong> <?php echo htmlspecialchars($endereco); ?><br>
                <strong>TELEFONE(S):</strong> <?php echo htmlspecialchars($telefone); ?><br>
            </p>
            <div class="custom-line"></div>
            <p class="card-text">
                <strong>TIPO DO PROCESSO:</strong> <?php echo htmlspecialchars($tipo_processo); ?><br>
                <strong>NÚMERO DO PROCESSO:</strong> <?php echo htmlspecialchars($numero_processo); ?><br>
                <strong>STATUS:</strong> <?php echo htmlspecialchars($status_processo); ?><br>
                <?php if ($status_processo == 'PARADO') : ?>
            <div class="custom-line"></div>
            <strong style="color:red">MOTIVO PARADO:</strong> <?php echo htmlspecialchars($motivo_parado); ?><br>
        <?php endif; ?>
        </p>

        <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST" style="margin-top:10px;">
            <input type="hidden" name="processo_id" value="<?php echo $processo_id; ?>">
            <?php if ($isAcompanhando) : ?>
                <button type="submit" name="desacompanhar_processo" class="btn btn-danger btn-sm">Desacompanhar Processo</button>
            <?php else : ?>
                <button type="submit" name="acompanhar_processo" class="btn btn-primary btn-sm">Acompanhar Processo</button>
            <?php endif; ?>
        </form>

        <!-- Exibição dos nomes dos usuários acompanhando -->
        <?php if (!empty($usuariosAcompanhando)) : ?>
            <small class="text-muted" style="display: block; margin-top: 10px;">
                <strong>Usuários Acompanhando este Processo:</strong>
                <?php foreach ($usuariosAcompanhando as $usuario) : ?>
                    <?php echo htmlspecialchars($usuario['nome_completo']); ?><?php if (end($usuariosAcompanhando) !== $usuario) echo ', '; ?>
                <?php endforeach; ?>
            </small>
        <?php else : ?>
            <small class="text-muted" style="display: block; margin-top: 10px;">Nenhum usuário está acompanhando este processo.</small>
        <?php endif; ?>

        </div>
    </div>

    <?php if (!empty($responsaveis)) : ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Responsáveis Designados</h6>
            </div>

            <div class="card-body">
                <ul class="list-group">
                    <?php foreach ($responsaveis as $responsavel) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($responsavel['nome_completo']); ?></strong>
                                <br>
                                <small>Descrição: <?php echo htmlspecialchars($responsavel['descricao']); ?></small>
                                <br>
                                <small>Status: <?php echo htmlspecialchars($responsavel['status']); ?></small>
                            </div>
                            <div class="btn-group">
                                <?php if ($responsavel['status'] != 'resolvido') : ?>
                                    <?php if ($_SESSION['user']['nivel_acesso'] == 3 || (isset($responsavel['usuario_id']) && $_SESSION['user']['id'] == $responsavel['usuario_id'])) : ?>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resolverModal<?php echo $responsavel['responsavel_id']; ?>">
                                            Finalizar
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($_SESSION['user']['nivel_acesso'] == 3) : ?>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#removerModal<?php echo $responsavel['responsavel_id']; ?>">
                                        Remover
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>

                        <!-- Modal para remover responsável -->
                        <div class="modal fade" id="removerModal<?php echo $responsavel['responsavel_id']; ?>" tabindex="-1" aria-labelledby="removerModalLabel<?php echo $responsavel['responsavel_id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="removerModalLabel<?php echo $responsavel['responsavel_id']; ?>">Remover Responsável</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                                            <p>Tem certeza que deseja remover este responsável?</p>
                                            <input type="hidden" name="responsavel_id" value="<?php echo $responsavel['responsavel_id']; ?>">
                                            <button type="submit" name="remover_responsavel" class="btn btn-danger">Remover</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal para marcar como resolvido -->
                        <div class="modal fade" id="resolverModal<?php echo $responsavel['responsavel_id']; ?>" tabindex="-1" aria-labelledby="resolverModalLabel<?php echo $responsavel['responsavel_id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="resolverModalLabel<?php echo $responsavel['responsavel_id']; ?>">Finalizar</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                                            <p>Tem certeza que deseja marcar este responsável como resolvido?</p>
                                            <input type="hidden" name="responsavel_id" value="<?php echo $responsavel['responsavel_id']; ?>">
                                            <button type="submit" name="marcar_resolvido" class="btn btn-success">Finalizar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </ul>
            </div> <!-- Fechamento do card-body -->
        </div> <!-- Fechamento do card -->
    <?php endif; ?>

    <!-- Modal para designar responsável -->
    <div class="modal fade" id="designarModal" tabindex="-1" aria-labelledby="designarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="designarModalLabel">Designar Responsável</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                        <div class="mb-3">
                            <label for="usuario_id" class="form-label">Usuário</label>
                            <select class="form-control" id="usuario_id" name="usuario_id" required>
                                <?php foreach ($usuarios as $usuario) : ?>
                                    <option value="<?php echo htmlspecialchars($usuario['id']); ?>"><?php echo htmlspecialchars($usuario['nome_completo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3" required></textarea>
                        </div>
                        <input type="hidden" name="processo_id" value="<?php echo $processo_id; ?>">
                        <button type="submit" name="designar_responsavel" class="btn btn-primary">Designar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Coluna dos Documentos -->
    <div class="row" style="padding-top:10px;">

        <!-- Coluna para upload de documentos e ações -->
        <div class="col-md-4">

            <div class="card menu">
                <div class="card-header text-center">
                    <b>Menu</b>
                </div>
                <div class="card-body p-2">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-1">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-upload"></i> Upload de Arquivos
                            </a>
                        </li>
                        <li class="mb-1 d-flex align-items-center">
                            <a href="#" class="flex-grow-1" onclick="confirmarCriacaoDocumento('<?php echo $processo_id; ?>', '<?php echo $estabelecimento_id; ?>', <?php echo !empty($documentosPendentes) ? 'true' : 'false'; ?>)">
                                <i class="fas fa-file-alt"></i> Criar Documento Digital
                            </a>
                            <?php if (!empty($documentosPendentes)) : ?>
                                <span class="badge bg-warning text-dark ms-2" style="font-size: 12px;">
                                    <?php echo count($documentosPendentes); ?> Arquivo pendente(s)
                                </span>
                            <?php endif; ?>
                        </li>
                        <?php if ($_SESSION['user']['nivel_acesso'] == 1 || $_SESSION['user']['nivel_acesso'] == 2 || $_SESSION['user']['nivel_acesso'] == 3) : ?>
                            <li class="mb-1">
                                <a href="../OrdemServico/ordem_servico.php?id=<?php echo $estabelecimento_id; ?>&processo_id=<?php echo $processo_id; ?>">
                                    <i class="fas fa-concierge-bell"></i> Ordem de Serviço
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="mb-1">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#addAlertaModal">
                                <i class="fas fa-bell"></i> Alertas
                            </a>
                        </li>
                            <li>
                                <a href="#" data-bs-toggle="modal" data-bs-target="#designarModal">
                                    <i class="fas fa-user-tag"></i> Designar Responsável
                                </a>
                            </li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4" style="margin-top:10px;">
                <div class="card-header">
                    <h6 class="mb-0">Ações do Processo</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="gerar_processo.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" class="list-group-item list-group-item-action text-info" target="_blank">
                            <i class="fas fa-file-pdf"></i> Processo na íntegra
                        </a>
                        <?php if ($status_processo == 'ATIVO') : ?>
                            <a href="#" class="list-group-item list-group-item-action text-danger" data-bs-toggle="modal" data-bs-target="#modalStopProcesso">
                                <i class="fas fa-stop"></i> Parar Processo
                            </a>
                            <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=archive_processo" class="list-group-item list-group-item-action text-warning" onclick="return confirm('Tem certeza que deseja arquivar este processo?')">
                                <i class="fas fa-archive"></i> Arquivar Processo
                            </a>
                        <?php elseif ($status_processo == 'PARADO') : ?>
                            <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=restart_processo" class="list-group-item list-group-item-action text-success" onclick="return confirm('Tem certeza que deseja reiniciar este processo?')">
                                <i class="fas fa-play"></i> Reiniciar Processo
                            </a>
                        <?php else : ?>
                            <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=unarchive_processo" class="list-group-item list-group-item-action text-success" onclick="return confirm('Tem certeza que deseja desarquivar este processo?')">
                                <i class="fas fa-folder-open"></i> Desarquivar Processo
                            </a>
                        <?php endif; ?>

                        <!-- SOMENTE USUARIOS ADMINISTRADORES, SUPORTE E GERENTE PODEM EXCLUIR O PROCESSO -->
                        <?php if ($_SESSION['user']['nivel_acesso'] == 1 || $_SESSION['user']['nivel_acesso'] == 2 || $_SESSION['user']['nivel_acesso'] == 3) : ?>
                            <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=delete_processo" class="list-group-item list-group-item-action text-danger" onclick="return confirm('Tem certeza que deseja excluir este processo? Todos os documentos vinculados a este processo serão apagados.')">
                                <i class="fas fa-trash"></i> Excluir Processo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="coluna">
                    <div class="card-header">
                        <h6 class="mb-0">Lista de Documentos/Arquivos</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach ($itens as $item) : ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($item['tipo'] == 'documento') : ?>
                                            <?php
                                            // Buscar registros de negação
                                            $temNegacao = false;
                                            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM historico_negacoes WHERE documento_id = ?");
                                            $stmt->bind_param("i", $item['id']);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $registro = $result->fetch_assoc();

                                            if ($registro['total'] > 0) {
                                                $temNegacao = true;
                                            }
                                            ?>

                                            <!-- Exibição do nome do arquivo e status -->
                                            <a href="#"
                                                onclick="openDocumentModal('<?php echo addslashes('../../' . $item['caminho_arquivo']); ?>', '<?php echo addslashes($item['nome_arquivo']); ?>')"
                                                data-bs-toggle="modal"
                                                data-bs-target="#documentModal">
                                                <?php echo htmlspecialchars($item['nome_arquivo']); ?>
                                            </a>

                                            <span class="badge bg-<?php echo $item['status'] == 'aprovado' ? 'success' : ($item['status'] == 'negado' ? 'danger' : 'warning'); ?> text-light"
                                                style="cursor: pointer;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#historicoNegacoesModal<?php echo $item['id']; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>

                                            <div class="small-line-height">
                                                <!-- Adicionado em e Verificação de Negações -->

                                                <small style="color: #b1b1b1; font-size:10px;">Adicionado em: <?php echo date("d/m/Y H:i", strtotime($item['data_upload'])); ?></small>

                                                <?php if ($item['status'] == 'aprovado') : ?>
                                                    <?php
                                                    $aprovador_nome = "Não informado"; // Valor padrão
                                                    $data_aprovacao = "Data não registrada"; // Valor padrão

                                                    // Obter informações do aprovador
                                                    if ($item['aprovado_por']) {
                                                        $stmt = $conn->prepare("SELECT nome_completo FROM usuarios WHERE id = ?");
                                                        $stmt->bind_param("i", $item['aprovado_por']);
                                                        $stmt->execute();
                                                        $result = $stmt->get_result();
                                                        $aprovador = $result->fetch_assoc();
                                                        if ($aprovador) {
                                                            $aprovador_nome = $aprovador['nome_completo'];
                                                        }
                                                    }

                                                    // Verificar a data de aprovação
                                                    if (!empty($item['data_aprovacao'])) {
                                                        $data_aprovacao = date("d/m/Y H:i", strtotime($item['data_aprovacao']));
                                                    }
                                                    ?>

                                                    <small style="color: #b1b1b1; font-size:10px;">
                                                        - Aprovado por: <?php echo htmlspecialchars($aprovador_nome); ?> em <?php echo htmlspecialchars($data_aprovacao); ?>
                                                    </small>
                                                <?php endif; ?>

                                                <?php if ($temNegacao) : ?>
                                                    <br>
                                                    <small style="color: red; font-size:10px;">Este documento possui registro(s) de negação.</small>
                                                <?php endif; ?>

                                            </div>


                                            <?php if ($item['status'] == 'negado') : ?>
                                                <small style="color: red; font-size:11px; font-weight:bold;">Motivo: <?php echo htmlspecialchars($item['motivo_negacao']); ?></small>
                                            <?php endif; ?>

                                            <?php
                                            $historicoNegacoes = [];
                                            $stmt = $conn->prepare("SELECT motivo_negacao, data_negacao, u.nome_completo 
                                            FROM historico_negacoes h 
                                            JOIN usuarios u ON h.usuario_id = u.id 
                                            WHERE h.documento_id = ?");
                                            $stmt->bind_param("i", $item['id']);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $historicoNegacoes = $result->fetch_all(MYSQLI_ASSOC);
                                            ?>

                                        <?php else : ?>
                                            <?php if ($item['caminho_arquivo'] && $arquivo->todasAssinaturasConcluidas($item['id'])) : ?>
                                                <a href="#" onclick="openDocumentModal('<?php echo '../../' . htmlspecialchars($item['caminho_arquivo']); ?>', '<?php echo htmlspecialchars($item['tipo_documento'] . ' ' . $item['id'] . '.' . date('Y')); ?>')" data-bs-toggle="modal" data-bs-target="#documentModal">
                                                    <?php echo htmlspecialchars($item['tipo_documento'] . ' ' . $item['id'] . '.' . date('Y')); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo htmlspecialchars($item['tipo_documento'] . ' ' . $item['id'] . '.' . date('Y')); ?> (Rascunho)
                                            <?php endif; ?>
                                            <span class="badge bg-primary text-light" style="margin: 0 0 0 4px;">Documento</span>
                                            <?php if ($item['sigiloso']) : ?>
                                                <span class="badge bg-danger text-light" style="margin: 0 0px;">Sigiloso</span>
                                            <?php endif; ?>
                                            <br>
                                            <small style="color: #b1b1b1; font-size:10px;">Adicionado em: <?php echo date("d/m/Y  H:i", strtotime($item['data_upload'])); ?></small>
                                            <?php if ($arquivo->isVisualizadoPorUsuarioExterno($item['id'])) : ?>
                                                <small style="color: green; font-size:10px;">- Visualizado</small>
                                            <?php else : ?>
                                                <small style="color: red; font-size:10px;">- Não Visualizado</small>
                                            <?php endif; ?>

                                            <?php if ($item['caminho_arquivo'] === '') : ?>
                                                <small style="color: orange; font-size:10px;">- Falta finalizar o documento</small>
                                            <?php elseif ($arquivo->todasAssinaturasPendentes($item['id'])) : ?>
                                                <small style="color: orange; font-size:10px;">- Aguardando assinaturas</small>
                                            <?php elseif ($arquivo->arquivoFinalizadoComAssinaturasPendentes($item['id'])) : ?>
                                                <small style="color: red; font-size:10px;">- Documento finalizado, mas com assinaturas pendentes</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="btn-group">
                                        <?php if ($item['tipo'] == 'documento') : ?>
                                            <?php if (in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4]) && $item['status'] == 'pendente') : ?>
                                                <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=approve&doc_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success custom-btn">
                                                    <i class="fas fa-check"></i> Aprovar
                                                </a>
                                                <button class="btn btn-sm btn-danger custom-btn" data-bs-toggle="modal" data-bs-target="#denyModal<?php echo $item['id']; ?>">
                                                    <i class="fas fa-times"></i> Negar
                                                </button>
                                            <?php endif; ?>
                                            <!-- Rerverter Aprovação ou Negação  Erick Vinicius - 16/12/24 -->
                                            <?php if (in_array($item['status'], ['aprovado', 'negado'])) : ?>
                                                <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=revert&doc_id=<?php echo $item['id']; ?>"
                                                    class="btn btn-sm btn-warning custom-btn"
                                                    onclick="return confirm('Tem certeza que deseja reverter o status deste documento para pendente?');">
                                                    <i class="fas fa-undo"></i>
                                                </a>
                                            <?php endif; ?>

                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $item['id']; ?>" style="margin-right: 0px;">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="#"
                                                class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#confirmDeleteModalArquivo"
                                                data-url="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=delete&doc_id=<?php echo $item['id']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>

                                        <?php else : ?>
                                            <?php if (!$arquivo->todasAssinaturasConcluidas($item['id'])) : ?>
                                                <a href="pre_visualizar_arquivo.php?arquivo_id=<?php echo $item['id']; ?>&processo_id=<?php echo $processo_id; ?>&estabelecimento_id=<?php echo $estabelecimento_id; ?>" class="btn btn-sm btn-secondary" style="margin-right: 4px;">Assinaturas</a>
                                            <?php endif; ?>
                                            <?php if (!$item['caminho_arquivo']) : ?>
                                                <a href="editar_documento.php?arquivo_id=<?php echo $item['id']; ?>&processo_id=<?php echo $processo_id; ?>&estabelecimento_id=<?php echo $estabelecimento_id; ?>" class="btn btn-sm btn-primary" style="margin-right: 0px;">Editar</a>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewersModal<?php echo $item['id']; ?>" style="margin-right: 0px;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-delete-url="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=delete_arquivo&arquivo_id=<?php echo $item['id']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>

                                        <?php endif; ?>
                                    </div>
                                </li>

                                <!-- Modal para Histórico de Negações -->
                                <div class="modal fade" id="historicoNegacoesModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="historicoNegacoesLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="historicoNegacoesLabel<?php echo $item['id']; ?>">Histórico de Negações</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php if (!empty($historicoNegacoes)) : ?>
                                                    <ul class="list-group">
                                                        <?php foreach ($historicoNegacoes as $historico) : ?>
                                                            <li class="list-group-item">
                                                                <strong><?php echo htmlspecialchars($historico['nome_completo']); ?>:</strong>
                                                                <?php echo htmlspecialchars($historico['motivo_negacao']); ?>
                                                                <br>
                                                                <small><?php echo date("d/m/Y H:i", strtotime($historico['data_negacao'])); ?></small>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else : ?>
                                                    <p>Nenhuma negação registrada.</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="documentModalLabel">Visualizar Documento</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <iframe id="documentViewer" src="" style="width: 100%; height: 500px; border: none;"></iframe>
                                            </div>
                                            <div class="modal-footer">
                                                <a id="openInNewTab" href="#" target="_blank" class="btn btn-primary">Abrir em Nova Aba</a>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <!-- Modal de confirmação para exclusão -->
                                <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Tem certeza que deseja excluir este documento?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <a href="#" id="confirmDeleteButton" class="btn btn-danger">Excluir</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal de Confirmação para Arquivo -->
                                <div class="modal fade" id="confirmDeleteModalArquivo" tabindex="-1" aria-labelledby="confirmDeleteLabelArquivo" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="confirmDeleteLabelArquivo">Confirmar Exclusão de Arquivo</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>
                                            <div class="modal-body">
                                                Tem certeza de que deseja excluir este arquivo? Esta ação não pode ser desfeita.
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <a id="deleteConfirmButtonArquivo" href="#" class="btn btn-danger">Excluir</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>



                                <!-- Modais para documentos -->
                                <?php if ($item['tipo'] == 'documento') : ?>
                                    <div class="modal fade" id="denyModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="denyModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="denyModalLabel<?php echo $item['id']; ?>">Negar Documento</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">

                                                        <!-- Seleção de Motivos Pré-Preenchidos -->
                                                        <div class="mb-3">
                                                            <label for="motivo_predefinido<?php echo $item['id']; ?>" class="form-label">Escolha um Motivo</label>
                                                            <select class="form-select" id="motivo_predefinido<?php echo $item['id']; ?>" onchange="atualizarMotivo(<?php echo $item['id']; ?>)">
                                                                <option value="">Selecione um motivo</option>
                                                                <option value="Arquivo ilegível">Arquivo ilegível</option>
                                                                <option value="Arquivo não atende os requisitos da vigilância sanitária">Arquivo não atende os requisitos da vigilância sanitária</option>
                                                                <option value="Informações incompletas">Informações incompletas</option>
                                                                <option value="Arquivo duplicado">Arquivo duplicado</option>
                                                                <option value="">Escrever Motivo</option>
                                                            </select>
                                                        </div>

                                                        <!-- Campo para o Motivo Personalizado -->
                                                        <div class="mb-3">
                                                            <label for="motivo_negacao<?php echo $item['id']; ?>" class="form-label">Motivo da Negação</label>
                                                            <textarea class="form-control" id="motivo_negacao<?php echo $item['id']; ?>" name="motivo_negacao" rows="3" required></textarea>
                                                        </div>

                                                        <input type="hidden" name="documento_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-danger" name="negar_documento">Salvar</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="editModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editModalLabel<?php echo $item['id']; ?>">Editar Documento</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                                                        <div class="form-group">
                                                            <label for="novo_nome">Novo Nome</label>
                                                            <input type="text" class="form-control" id="novo_nome" name="novo_nome" value="<?php echo htmlspecialchars($item['nome_arquivo']); ?>" required>
                                                        </div>
                                                        <input type="hidden" name="documento_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;" name="editar_nome">Salvar</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Modal para visualizações -->
                                <div class="modal fade" id="viewersModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="viewersModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="viewersModalLabel<?php echo $item['id']; ?>">Visualizações do Documento</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php
                                                $visualizacoes = $arquivo->getVisualizacoes($item['id']);
                                                if (!empty($visualizacoes)) : ?>
                                                    <ul class="list-group">
                                                        <?php foreach ($visualizacoes as $visualizacao) : ?>
                                                            <li class="list-group-item">
                                                                <?php echo htmlspecialchars($visualizacao['nome_completo']); ?>
                                                                <br>
                                                                <small><?php echo date("d/m/Y H:i:s", strtotime($visualizacao['data_visualizacao'])); ?></small>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else : ?>
                                                    <p>Nenhuma visualização registrada.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Ordens de Serviço</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($ordensServico as $ordem) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    Ordem de Serviço <?php echo htmlspecialchars($ordem['id'] . '.' . date('Y', strtotime($ordem['data_inicio']))); ?>

                                    <br>
                                    <small>
                                        <?php
                                        $data_inicio = date("d/m/Y", strtotime($ordem['data_inicio']));
                                        $data_fim = date("d/m/Y", strtotime($ordem['data_fim']));
                                        echo $data_inicio . " - " . $data_fim;
                                        ?>
                                        <br>
                                        Status: <?php echo htmlspecialchars($ordem['status']); ?>
                                    </small>
                                </div>
                                <div class="coluna">
                                    <div>
                                        <a href="../OrdemServico/detalhes_ordem.php?id=<?php echo $ordem['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="card mt-4" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h6 class="mb-0">Alertas</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($processo->getAlertasByProcesso($processo_id) as $alerta) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong style="font-size:13px;"><?php echo htmlspecialchars($alerta['descricao']); ?></strong>
                                    <br>
                                    <small>Prazo: <?php echo date("d/m/Y", strtotime($alerta['prazo'])); ?></small>
                                    <br>
                                    <small>Status: <?php echo htmlspecialchars($alerta['status']); ?></small>
                                </div>
                                <div>
                                    <?php if ($alerta['status'] == 'ativo') : ?>
                                        <button class="btn btn-sm btn-success" style="margin:0 -1px; font-size:11px;" onclick="finalizeAlerta(<?php echo $alerta['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" style="margin:0 -4px; font-size:11px;" data-bs-toggle="modal" data-bs-target="#editAlertaModal<?php echo $alerta['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" style="margin:0 -1px; font-size:11px;" data-bs-toggle="modal" data-bs-target="#deleteAlertaModal<?php echo $alerta['id']; ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Modal para editar alerta -->
            <div class="modal fade" id="editAlertaModal<?php echo $alerta['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editAlertaModalLabel<?php echo $alerta['id']; ?>" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title" id="editAlertaModalLabel<?php echo $alerta['id']; ?>">Editar Alerta</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                                <div class="form-group">
                                    <label for="descricao<?php echo $alerta['id']; ?>">Descrição</label>
                                    <input type="text" class="form-control" id="descricao<?php echo $alerta['id']; ?>" name="descricao" value="<?php echo htmlspecialchars($alerta['descricao']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="prazo<?php echo $alerta['id']; ?>">Prazo</label>
                                    <input type="date" class="form-control" id="prazo<?php echo $alerta['id']; ?>" name="prazo" value="<?php echo htmlspecialchars($alerta['prazo']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="status<?php echo $alerta['id']; ?>">Status</label>
                                    <select class="form-control" id="status<?php echo $alerta['id']; ?>" name="status" required>
                                        <option value="ativo" <?php if ($alerta['status'] == 'ativo') echo 'selected'; ?>>Ativo</option>
                                        <option value="finalizado" <?php if ($alerta['status'] == 'finalizado') echo 'selected'; ?>>Finalizado</option>
                                    </select>
                                </div>
                                <input type="hidden" name="alerta_id" value="<?php echo $alerta['id']; ?>">
                                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;" name="editar_alerta">Salvar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Visualizar Documento -->
            <div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-labelledby="viewDocumentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="viewDocumentModalLabel">Visualizar Documento</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <iframe id="documentViewer" src="" frameborder="0" style="width: 100%; height: 500px;"></iframe>
                        </div>
                        <div class="modal-footer">
                            <a id="openInNewTab" href="#" target="_blank" class="btn btn-primary">Abrir em Nova Aba</a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Modal para excluir alerta -->
            <div class="modal fade" id="deleteAlertaModal<?php echo $alerta['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteAlertaModalLabel<?php echo $alerta['id']; ?>" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title" id="deleteAlertaModalLabel<?php echo $alerta['id']; ?>">Excluir Alerta</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                                <p>Tem certeza que deseja excluir este alerta?</p>
                                <input type="hidden" name="alerta_id" value="<?php echo $alerta['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" name="excluir_alerta">Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            </ul>
        </div>
    </div>

    <!-- Modal para adicionar alerta -->
    <div class="modal fade" id="addAlertaModal" tabindex="-1" role="dialog" aria-labelledby="addAlertaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="addAlertaModalLabel">Adicionar Alerta</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <input type="text" class="form-control" id="descricao" name="descricao" required>
                        </div>
                        <div class="form-group">
                            <label for="prazo">Prazo</label>
                            <input type="date" class="form-control" id="prazo" name="prazo" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm mt-3" name="adicionar_alerta">Adicionar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Upload de Arquivos -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload de Arquivos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="files" class="form-label">Escolha os arquivos</label>
                        <input class="form-control" type="file" id="files" name="files[]" multiple required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para adicionar alerta -->
<div class="modal fade" id="addAlertaModal" tabindex="-1" aria-labelledby="addAlertaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAlertaModalLabel">Adicionar Alerta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <input type="text" class="form-control" id="descricao" name="descricao" required>
                    </div>
                    <div class="form-group">
                        <label for="prazo">Prazo</label>
                        <input type="date" class="form-control" id="prazo" name="prazo" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="ativo">Ativo</option>
                            <option value="finalizado">Finalizado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" name="adicionar_alerta">Adicionar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar e excluir alertas (gerado dinamicamente) -->
<?php foreach ($processo->getAlertasByProcesso($processo_id) as $alerta) : ?>
    <div class="modal fade" id="editAlertaModal<?php echo $alerta['id']; ?>" tabindex="-1" aria-labelledby="editAlertaModalLabel<?php echo $alerta['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAlertaModalLabel<?php echo $alerta['id']; ?>">Editar Alerta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                        <div class="form-group">
                            <label for="descricao<?php echo $alerta['id']; ?>">Descrição</label>
                            <input type="text" class="form-control" id="descricao<?php echo $alerta['id']; ?>" name="descricao" value="<?php echo htmlspecialchars($alerta['descricao']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="prazo<?php echo $alerta['id']; ?>">Prazo</label>
                            <input type="date" class="form-control" id="prazo<?php echo $alerta['id']; ?>" name="prazo" value="<?php echo htmlspecialchars($alerta['prazo']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status<?php echo $alerta['id']; ?>">Status</label>
                            <select class="form-control" id="status<?php echo $alerta['id']; ?>" name="status" required>
                                <option value="ativo" <?php if ($alerta['status'] == 'ativo') echo 'selected'; ?>>Ativo</option>
                                <option value="finalizado" <?php if ($alerta['status'] == 'finalizado') echo 'selected'; ?>>Finalizado</option>
                            </select>
                        </div>
                        <input type="hidden" name="alerta_id" value="<?php echo $alerta['id']; ?>">
                        <button type="submit" class="btn btn-primary btn-sm" name="editar_alerta">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteAlertaModal<?php echo $alerta['id']; ?>" tabindex="-1" aria-labelledby="deleteAlertaModalLabel<?php echo $alerta['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAlertaModalLabel<?php echo $alerta['id']; ?>">Excluir Alerta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                        <p>Tem certeza que deseja excluir este alerta?</p>
                        <input type="hidden" name="alerta_id" value="<?php echo $alerta['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" name="excluir_alerta">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<!-- Modal para adicionar alerta -->
<div class="modal fade" id="addAlertaModal" tabindex="-1" role="dialog" aria-labelledby="addAlertaModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="addAlertaModalLabel">Adicionar Alerta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <input type="text" class="form-control" id="descricao" name="descricao" required>
                    </div>
                    <div class="form-group">
                        <label for="prazo">Prazo</label>
                        <input type="date" class="form-control" id="prazo" name="prazo" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" name="adicionar_alerta">Adicionar</button>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>

<!-- Modal para Parar Processo -->
<div class="modal fade" id="modalStopProcesso" tabindex="-1" aria-labelledby="modalStopProcessoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalStopProcessoLabel">Parar Processo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST">
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" required></textarea>
                    </div>
                    <input type="hidden" name="motivo_parado" value="1">
                    <button type="submit" class="btn btn-danger">Parar Processo</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="successMessage" class="alert alert-success" style="position: fixed; top: 20px; right: -300px; z-index: 1000; transition: right 0.5s ease-in-out; display: none;">
    Documento aprovado com sucesso!
</div>

<div id="errorMessage" class="alert alert-danger" style="position: fixed; top: 20px; right: -300px; z-index: 1000; transition: right 0.5s ease-in-out; display: none;">
    Documento negado com sucesso!
</div>

<div id="warningMessage" class="alert alert-warning" style="position: fixed; top: 20px; right: -300px; z-index: 1000; transition: right 0.5s ease-in-out; display: none;">
    Documento revertido com sucesso!
</div>



<script>
    function finalizeAlerta(alerta_id) {
        if (confirm('Tem certeza que deseja finalizar este alerta?')) {
            window.location.href = 'documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=finalize_alerta&alerta_id=' + alerta_id;
        }
    }

    function confirmarCriacaoDocumento(processo_id, estabelecimento_id, hasPendentes) {
        if (hasPendentes) {
            const confirma = confirm('Atenção: Existem documentos pendentes de aprovação. Deseja continuar mesmo assim?');
            if (!confirma) {
                return; // O usuário cancelou
            }
        }
        // Redireciona para criar documento digital
        window.location.href = '../Arquivos/criar_arquivo.php?processo_id=' + processo_id + '&id=' + estabelecimento_id;
    }
</script>

<script>
    var confirmDeleteModal = document.getElementById('confirmDeleteModal');
    var confirmDeleteButton = document.getElementById('confirmDeleteButton');

    confirmDeleteModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget; // Botão que acionou o modal
        var deleteUrl = button.getAttribute('data-delete-url'); // Extrai a URL do atributo data-delete-url
        confirmDeleteButton.href = deleteUrl; // Atualiza o href do botão de confirmação de exclusão
    });

    document.addEventListener('DOMContentLoaded', function() {
        const confirmDeleteModalArquivo = document.getElementById('confirmDeleteModalArquivo');
        const deleteConfirmButtonArquivo = document.getElementById('deleteConfirmButtonArquivo');

        confirmDeleteModalArquivo.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget; // Botão que disparou o modal
            const url = button.getAttribute('data-url'); // URL do atributo data-url
            deleteConfirmButtonArquivo.setAttribute('href', url); // Atualiza o link do botão Excluir
        });
    });

    function openDocumentModal(documentPath, documentName) {
        const viewer = document.getElementById('documentViewer');
        const newTabLink = document.getElementById('openInNewTab');

        viewer.src = documentPath;
        newTabLink.href = documentPath;

        // Atualiza o título do modal com o nome do documento
        const modalTitle = document.getElementById('documentModalLabel');
        modalTitle.textContent = documentName;
    }

    function showMessage(type, message) {
        var messageDiv;

        if (type === 'success') {
            messageDiv = $('#successMessage');
        } else if (type === 'error') {
            messageDiv = $('#errorMessage');
        } else if (type === 'warning') {
            messageDiv = $('#warningMessage');
        }

        messageDiv.text(message); // Atualiza o texto da mensagem
        messageDiv.css('right', '20px').fadeIn();

        setTimeout(function() {
            messageDiv.css('right', '-300px').fadeOut();
        }, 3000); // Desaparece após 3 segundos
    }

    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            if (status === 'approved') {
                showMessage('success', 'Documento aprovado com sucesso!');
            } else if (status === 'denied') {
                showMessage('error', 'Documento negado com sucesso!');
            } else if (status === 'reverted') {
                showMessage('warning', 'Documento revertido com sucesso!');
            }
        }
    });

    // Função para copiar o motivo selecionado para a textarea
    function atualizarMotivo(itemId) {
        const selectElement = document.getElementById('motivo_predefinido' + itemId);
        const textareaElement = document.getElementById('motivo_negacao' + itemId);

        if (selectElement.value !== '') {
            textareaElement.value = selectElement.value;
        } else {
            textareaElement.value = ''; // Limpa a textarea se nenhum motivo for selecionado
        }
    }
</script>


<?php include '../footer.php'; ?>