<?php
session_start();
ob_start();  // Inicia o buffer de saída
include '../header.php';

// Exibir mensagem de sucesso, se houver
if (isset($_SESSION['mensagem_sucesso'])) {
    echo "<div class='container mx-auto px-3 py-6 mt-4'>
            <div class='bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-md'>
                <div class='flex'>
                    <div class='flex-shrink-0'>
                        <svg class='h-5 w-5 text-green-400' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor'>
                            <path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z' clip-rule='evenodd' />
                        </svg>
                    </div>
                    <div class='ml-3'>
                        <p class='text-xs text-green-700'>";
    echo $_SESSION['mensagem_sucesso'];
    echo '</p>
                    </div>
                </div>
            </div>
          </div>';
    unset($_SESSION['mensagem_sucesso']);  // Limpar a mensagem após exibir
}

// Definir variáveis do processo
if (isset($_GET['processo_id']) && isset($_GET['id'])) {
    $processo_id = $_GET['processo_id'];
    $estabelecimento_id = $_GET['id'];
} else {
    echo 'ID do processo ou estabelecimento não fornecido!';
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
    echo "<div class='container mx-auto px-3 py-0 mt-5'>
        <div class='bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded-md'>
            <div class='flex'>
                <div class='flex-shrink-0'>
                    <svg class='h-5 w-5 text-amber-400' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor'>
                        <path fill-rule='evenodd' d='M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z' clip-rule='evenodd' />
                    </svg>
                </div>
                <div class='ml-3'>
                    <p class='text-xs text-amber-700 font-medium'>
                        Atenção: Existem " . count($documentosPendentes) . ' documento(s) pendente(s) de aprovação.
                    </p>
                </div>
            </div>
        </div>
      </div>';
}

if (isset($_GET['processo_id']) && isset($_GET['id'])) {
    $processo_id = $_GET['processo_id'];
    $estabelecimento_id = $_GET['id'];
} else {
    echo 'ID do processo ou estabelecimento não fornecido!';
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
$municipio_estabelecimento = $processoDados['municipio'];  // Obtendo o município do estabelecimento

// Verificação de acesso ao município
if ($_SESSION['user']['nivel_acesso'] != 1 && $_SESSION['user']['municipio'] != $municipio_estabelecimento) {
    echo 'Você não tem permissão para acessar informações deste processo.';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['files'])) {
    $total_files = count($_FILES['files']['name']);
    $upload_dir = '../../uploads/';

    for ($i = 0; $i < $total_files; $i++) {
        $file_name = basename($_FILES['files']['name'][$i]);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target_file)) {
            $caminho_arquivo = 'uploads/' . $file_name;
            $documento->createDocumento($processo_id, $file_name, $caminho_arquivo);
        } else {
            echo 'Erro ao fazer upload do arquivo: ' . $file_name;
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
        echo 'Erro ao atualizar o nome do documento.';
    }
}

if (isset($_GET['action'])) {
    $documento_id = isset($_GET['doc_id']) ? $_GET['doc_id'] : null;

    if ($_GET['action'] == 'delete' && $documento_id) {
        $usuario_id = $_SESSION['user']['id'];  // Obtém o ID do usuário logado
        if ($documento->deleteDocumento($documento_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
            exit();
        } else {
            echo 'Erro ao deletar o documento.';
        }
    } elseif ($_GET['action'] == 'approve' && $documento_id) {
        $usuario_id = $_SESSION['user']['id'];  // ID do usuário logado
        if ($documento->approveDocumento($documento_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id&status=approved");
            exit();
        } else {
            echo 'Erro ao aprovar o documento.';
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
        echo 'Erro ao negar o documento.';
    }
}

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['doc_id'])) {
        $documento_id = $_GET['doc_id'];
        $usuario_id = $_SESSION['user']['id'];  // Obtém o ID do usuário logado
        if ($documento->deleteDocumento($documento_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
            exit();
        } else {
            echo 'Erro ao deletar o documento.';
        }
    } elseif ($_GET['action'] == 'delete_arquivo' && isset($_GET['arquivo_id'])) {
        $arquivo_id = $_GET['arquivo_id'];
        $usuario_id = $_SESSION['user']['id'];  // Obtém o ID do usuário logado
        if ($arquivo->deleteArquivo($arquivo_id, $usuario_id)) {
            header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
            exit();
        } else {
            echo 'Erro ao deletar o arquivo.';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete_processo' && isset($_GET['processo_id'])) {
    $processo_id = $_GET['processo_id'];
    if ($processo->deleteProcesso($processo_id)) {
        header("Location: ../Estabelecimento/detalhes_estabelecimento.php?id=$estabelecimento_id&success=Processo excluído com sucesso.");
        exit();
    } else {
        echo 'Erro ao excluir o processo.';
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
        echo 'Erro ao finalizar o alerta.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['designar_responsavel'])) {
    $usuario_id = $_POST['usuario_id'];
    $processo_id = $_POST['processo_id'];
    $descricao = $_POST['descricao'];

    $sql = 'INSERT INTO processos_responsaveis (processo_id, usuario_id, descricao) VALUES (?, ?, ?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iis', $processo_id, $usuario_id, $descricao);
    $stmt->execute();
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remover_responsavel'])) {
    $responsavel_id = $_POST['responsavel_id'];

    $sql = 'DELETE FROM processos_responsaveis WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $responsavel_id);
    $stmt->execute();
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

// Obter lista de usuários do mesmo município
$municipio = $_SESSION['user']['municipio'];
$sql = 'SELECT id, nome_completo FROM usuarios WHERE municipio = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $municipio);
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obter responsáveis designados para o processo
$sql = 'SELECT pr.id as responsavel_id, u.id as usuario_id, u.nome_completo, pr.descricao, pr.status
        FROM processos_responsaveis pr
        JOIN usuarios u ON pr.usuario_id = u.id
        WHERE pr.processo_id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $processo_id);
$stmt->execute();
$responsaveis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acompanhar_processo'])) {
    $usuario_id = $_SESSION['user']['id'];
    $processo_id = $_POST['processo_id'];
    $sql = 'INSERT INTO processos_acompanhados (usuario_id, processo_id) VALUES (?, ?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $usuario_id, $processo_id);
    $stmt->execute();
    $_SESSION['mensagem_sucesso'] = 'Processo Acompanhado com sucesso.';
    header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['desacompanhar_processo'])) {
    $usuario_id = $_SESSION['user']['id'];
    $processo_id = $_POST['processo_id'];
    $sql = 'DELETE FROM processos_acompanhados WHERE usuario_id = ? AND processo_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $usuario_id, $processo_id);
    $stmt->execute();
    $_SESSION['mensagem_sucesso'] = 'Processo Desacompanhado com sucesso.';
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

if (isset($_GET['action']) && $_GET['action'] == 'revert' && isset($_GET['doc_id'])) {
    $documento_id = $_GET['doc_id'];
    if ($documento->revertDocumento($documento_id)) {
        header("Location: documentos.php?processo_id=$processo_id&id=$estabelecimento_id&status=reverted");
        exit();
    } else {
        echo 'Erro ao reverter o status do documento.';
    }
}

$historicoNegacoes = [];
$stmt = $conn->prepare('SELECT motivo_negacao, data_negacao, u.nome_completo 
                        FROM historico_negacoes h 
                        JOIN usuarios u ON h.usuario_id = u.id 
                        WHERE h.documento_id = ?');
$stmt->bind_param('i', $item['id']);  // Certifique-se de que $item['id'] está definido
$stmt->execute();
$result = $stmt->get_result();
$historicoNegacoes = $result->fetch_all(MYSQLI_ASSOC);

$usuariosAcompanhando = [];
$sql = 'SELECT u.nome_completo 
        FROM processos_acompanhados pa
        JOIN usuarios u ON pa.usuario_id = u.id
        WHERE pa.processo_id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $processo_id);
$stmt->execute();
$result = $stmt->get_result();
$usuariosAcompanhando = $result->fetch_all(MYSQLI_ASSOC);

$sql = 'SELECT COUNT(*) as count FROM processos_acompanhados WHERE usuario_id = ? AND processo_id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $_SESSION['user']['id'], $processo_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$isAcompanhando = $row['count'] > 0;

$documentos = $documento->getDocumentosByProcesso($processo_id);
$arquivos = $arquivo->getArquivosByProcesso($processo_id);
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

$processo_id = $_GET['processo_id'] ?? '';
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
        <div class="p-6">
            <div class="mb-4">
                <?php if ($processoDados['tipo_pessoa'] == 'fisica'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                    <div>
                        <span class="text-gray-600 font-medium text-xs text-xs text-xs">Nome:</span>
                        <span
                            class="text-gray-800 text-xs"><?php echo htmlspecialchars($processoDados['nome']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600 font-medium text-xs text-xs">CPF:</span>
                        <span
                            class="text-gray-800 text-xs"><?php echo htmlspecialchars($processoDados['cpf']); ?></span>
                    </div>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                    <div class="col-span-2">
                        <span class="text-gray-600 font-medium text-xs">Nome do Estabelecimento:</span>
                        <a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?php echo $estabelecimento_id; ?>"
                            class="text-blue-600 hover:text-blue-800 font-medium text-xs hover:underline">
                            <?php echo htmlspecialchars($nome_fantasia); ?>
                        </a>
                    </div>
                    <div>
                        <span class="text-gray-600 font-medium text-xs">CNPJ:</span>
                        <span class="text-gray-800 text-xs text-xs"><?php echo htmlspecialchars($cnpj); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                    <div class="col-span-2">
                        <span class="text-gray-600 font-medium text-xs">ENDEREÇO:</span>
                        <span class="text-gray-800 text-xs"><?php echo htmlspecialchars($endereco); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600 font-medium text-xs">TELEFONE(S):</span>
                        <span class="text-gray-800 text-xs"><?php echo htmlspecialchars($telefone); ?></span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 my-4"></div>

            <div class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                    <div>
                        <span class="text-gray-600 font-medium text-xs">TIPO DO PROCESSO:</span>
                        <span class="text-gray-800 text-xs"><?php echo htmlspecialchars($tipo_processo); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600 font-medium text-xs">NÚMERO DO PROCESSO:</span>
                        <span class="text-gray-800 text-xs"><?php echo htmlspecialchars($numero_processo); ?></span>
                    </div>
                </div>

                <?php if (strtoupper($tipo_processo) == 'LICENCIAMENTO' && isset($processoDados['ano_licenciamento'])): ?>
                <div class="mb-2">
                    <span class="text-gray-600 font-medium text-xs">ANO DE REFERÊNCIA:</span>
                    <span
                        class="text-gray-800 text-xs"><?php echo htmlspecialchars($processoDados['ano_licenciamento']); ?></span>
                </div>
                <?php endif; ?>

                <div class="mb-2">
                    <span class="text-gray-600 font-medium text-xs">STATUS:</span>
                    <?php
                    $status_classe = '';
                    $status_text_color = '';
                    $status_bg_color = '';

                    switch (strtolower($status_processo)) {
                        case 'ativo':
                            $status_bg_color = 'bg-green-100';
                            $status_text_color = 'text-green-800';
                            break;
                        case 'parado':
                            $status_bg_color = 'bg-yellow-100';
                            $status_text_color = 'text-yellow-800';
                            break;
                        case 'arquivado':
                            $status_bg_color = 'bg-gray-100';
                            $status_text_color = 'text-gray-800 text-xs';
                            break;
                        default:
                            $status_bg_color = 'bg-blue-100';
                            $status_text_color = 'text-blue-800';
                    }
                    ?>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-xs <?php echo $status_bg_color . ' ' . $status_text_color; ?>">
                        <?php echo htmlspecialchars(ucfirst(strtolower($status_processo))); ?>
                    </span>
                </div>

                <?php if ($status_processo == 'PARADO'): ?>
                <div class="mt-2 p-3 bg-red-50 border-l-4 border-red-500 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs text-red-700">
                                <span class="font-medium text-xs">MOTIVO PARADO:</span>
                                <?php echo htmlspecialchars($motivo_parado); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-6">
                <form
                    action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                    method="POST">
                    <input type="hidden" name="processo_id" value="<?php echo $processo_id; ?>">
                    <?php if ($isAcompanhando): ?>
                    <button type="submit" name="desacompanhar_processo"
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium text-xs rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                            <path fill-rule="evenodd"
                                d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                clip-rule="evenodd" />
                        </svg>
                        Desacompanhar Processo
                    </button>
                    <?php else: ?>
                    <button type="submit" name="acompanhar_processo"
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium text-xs rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                            <path fill-rule="evenodd"
                                d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                clip-rule="evenodd" />
                        </svg>
                        Acompanhar Processo
                    </button>
                    <?php endif; ?>
                </form>

                <?php if (!empty($usuariosAcompanhando)): ?>
                <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                    <p class="text-xs text-gray-600">
                        <span class="font-medium text-xs">Usuários Acompanhando este Processo:</span>
                        <span class="ml-1">
                            <?php foreach ($usuariosAcompanhando as $index => $usuario): ?>
                            <?php echo htmlspecialchars($usuario['nome_completo']); ?>
                            <?php if ($index < count($usuariosAcompanhando) - 1) echo ', '; ?>
                            <?php endforeach; ?>
                        </span>
                    </p>
                </div>
                <?php else: ?>
                <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                    <p class="text-xs text-gray-500 italic">Nenhum usuário está acompanhando este processo.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php if (!empty($responsaveis)): ?>
    <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
            <h3 class="text-base font-medium text-xs text-gray-700 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path
                        d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                </svg>
                Responsáveis Designados
            </h3>
        </div>
        <div class="divide-y divide-gray-200">
            <?php foreach ($responsaveis as $responsavel): ?>
            <div class="p-4 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div>
                    <h4 class="text-base font-medium text-xs text-gray-900">
                        <?php echo htmlspecialchars($responsavel['nome_completo']); ?></h4>
                    <p class="mt-1 text-xs text-gray-600">Descrição:
                        <?php echo htmlspecialchars($responsavel['descricao']); ?></p>
                    <?php
                            $statusClass = '';
                            switch (strtolower($responsavel['status'])) {
                                case 'pendente':
                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'em andamento':
                                    $statusClass = 'bg-blue-100 text-blue-800';
                                    break;
                                case 'resolvido':
                                    $statusClass = 'bg-green-100 text-green-800';
                                    break;
                                default:
                                    $statusClass = 'bg-gray-100 text-gray-800 text-xs';
                            }
                            ?>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-xs <?php echo $statusClass; ?> mt-1">
                        <?php echo htmlspecialchars(ucfirst($responsavel['status'])); ?>
                    </span>
                </div>
                <div class="flex space-x-2">
                    <?php if ($responsavel['status'] != 'resolvido'): ?>
                    <?php if ($_SESSION['user']['nivel_acesso'] == 3 || (isset($responsavel['usuario_id']) && $_SESSION['user']['id'] == $responsavel['usuario_id'])): ?>
                    <button type="button"
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium text-xs rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                        data-bs-toggle="modal"
                        data-bs-target="#resolverModal<?php echo $responsavel['responsavel_id']; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                clip-rule="evenodd" />
                        </svg>
                        Finalizar
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($_SESSION['user']['nivel_acesso'] == 3): ?>
                    <button type="button"
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium text-xs rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                        data-bs-toggle="modal"
                        data-bs-target="#removerModal<?php echo $responsavel['responsavel_id']; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Remover
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal para remover responsável -->
            <div class="modal fade" id="removerModal<?php echo $responsavel['responsavel_id']; ?>" tabindex="-1"
                aria-labelledby="removerModalLabel<?php echo $responsavel['responsavel_id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="removerModalLabel<?php echo $responsavel['responsavel_id']; ?>">
                                Remover Responsável</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form
                                action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                                method="POST">
                                <p>Tem certeza que deseja remover este responsável?</p>
                                <input type="hidden" name="responsavel_id"
                                    value="<?php echo $responsavel['responsavel_id']; ?>">
                                <button type="submit" name="remover_responsavel" class="btn btn-danger">Remover</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para marcar como resolvido -->
            <div class="modal fade" id="resolverModal<?php echo $responsavel['responsavel_id']; ?>" tabindex="-1"
                aria-labelledby="resolverModalLabel<?php echo $responsavel['responsavel_id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"
                                id="resolverModalLabel<?php echo $responsavel['responsavel_id']; ?>">Finalizar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form
                                action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                                method="POST">
                                <p>Tem certeza que deseja marcar este responsável como resolvido?</p>
                                <input type="hidden" name="responsavel_id"
                                    value="<?php echo $responsavel['responsavel_id']; ?>">
                                <button type="submit" name="marcar_resolvido" class="btn btn-success">Finalizar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para designar responsável -->
    <div class="modal fade" id="designarModal" tabindex="-1" aria-labelledby="designarModalLabel" aria-hidden="true">
        <div class="modal-dialog max-w-lg mx-auto">
            <div class="modal-content bg-white rounded-lg shadow-xl border-0 overflow-hidden">
                <div
                    class="modal-header flex items-center justify-between bg-gray-50 px-6 py-4 border-b border-gray-100">
                    <h5 class="modal-title text-lg font-medium text-xs text-gray-900 flex items-center"
                        id="designarModalLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path
                                d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                        </svg>
                        Designar Responsável
                    </h5>
                    <button type="button" class="btn-close text-gray-400 hover:text-gray-500 focus:outline-none"
                        data-bs-dismiss="modal" aria-label="Close">
                        <span class="sr-only">Fechar</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="modal-body p-6">
                    <form
                        action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                        method="POST">
                        <div class="mb-4">
                            <label for="usuario_id"
                                class="block text-xs font-medium text-xs text-gray-700 mb-1">Usuário</label>
                            <select
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs rounded-md"
                                id="usuario_id" name="usuario_id" required>
                                <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo htmlspecialchars($usuario['id']); ?>">
                                    <?php echo htmlspecialchars($usuario['nome_completo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="descricao"
                                class="block text-xs font-medium text-xs text-gray-700 mb-1">Descrição</label>
                            <textarea
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs"
                                id="descricao" name="descricao" rows="3" required
                                placeholder="Descreva a responsabilidade designada"></textarea>
                            <p class="mt-1 text-xs text-gray-500">Descreva detalhadamente a responsabilidade que está
                                sendo atribuída.</p>
                        </div>
                        <input type="hidden" name="processo_id" value="<?php echo $processo_id; ?>">
                        <div class="mt-5 flex justify-end">
                            <button type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-xs font-medium text-xs rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 mr-3"
                                data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" name="designar_responsavel"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-medium text-xs rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path
                                        d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                                </svg>
                                Designar Responsável
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Coluna dos Documentos -->
    <div class="row" style="padding-top:10px;">
        <!-- Coluna para upload de documentos e ações -->
        <div class="col-md-4">
            <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h3 class="text-xs font-medium text-gray-700 text-left">Menu de Opções</h3>
                </div>
                <div class="p-2">
                    <ul class="divide-y divide-gray-200">
                        <li class="py-2 px-2 hover:bg-gray-50 rounded-md transition-colors duration-150">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#uploadModal"
                                class="flex items-center text-gray-700 hover:text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-500"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="text-xs font-medium text-xs">Upload de Arquivos</span>
                            </a>
                        </li>
                        <li class="py-2 px-2 hover:bg-gray-50 rounded-md transition-colors duration-150">
                            <a href="#" class="flex items-center justify-between text-gray-700 hover:text-blue-600"
                                onclick="confirmarCriacaoDocumento('<?php echo $processo_id; ?>', '<?php echo $estabelecimento_id; ?>', <?php echo !empty($documentosPendentes) ? 'true' : 'false'; ?>)">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-500"
                                        viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-xs font-medium text-xs">Criar Documento Digital</span>
                                </div>
                                <?php if (!empty($documentosPendentes)): ?>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-xs bg-yellow-100 text-yellow-800">
                                    <?php echo count($documentosPendentes); ?> pendente(s)
                                </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if ($_SESSION['user']['nivel_acesso'] == 1 || $_SESSION['user']['nivel_acesso'] == 2 || $_SESSION['user']['nivel_acesso'] == 3): ?>
                        <li class="py-2 px-2 hover:bg-gray-50 rounded-md transition-colors duration-150">
                            <a href="../OrdemServico/ordem_servico.php?id=<?php echo $estabelecimento_id; ?>&processo_id=<?php echo $processo_id; ?>"
                                class="flex items-center text-gray-700 hover:text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-500"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                                </svg>
                                <span class="text-xs font-medium text-xs">Ordem de Serviço</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="py-2 px-2 hover:bg-gray-50 rounded-md transition-colors duration-150">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#addAlertaModal"
                                class="flex items-center text-gray-700 hover:text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-500"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="text-xs font-medium text-xs">Alertas</span>
                            </a>
                        </li>
                        <li class="py-2 px-2 hover:bg-gray-50 rounded-md transition-colors duration-150">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#designarModal"
                                class="flex items-center text-gray-700 hover:text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-500"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                </svg>
                                <span class="text-xs font-medium text-xs">Designar Responsável</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>


            <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h3 class="flex items-center space-x-1 text-xs font-medium text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414
               6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v2H7a1 1 0 100
               2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V8z" clip-rule="evenodd" />
                        </svg>
                        <span>Ações do Processo</span>
                    </h3>
                </div>

                <div class="p-3">
                    <div class="space-y-1">
                        <a href="gerar_processo.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                            class="flex items-center px-3 py-2 text-xs font-medium text-xs text-blue-700 hover:bg-blue-50 rounded-md transition-colors duration-150"
                            target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-blue-500"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                    clip-rule="evenodd" />
                            </svg>
                            Processo na íntegra
                        </a>

                        <?php if ($status_processo == 'ATIVO'): ?>
                        <a href="#"
                            class="flex items-center px-3 py-2 text-xs font-medium text-xs text-red-700 hover:bg-red-50 rounded-md transition-colors duration-150"
                            data-bs-toggle="modal" data-bs-target="#modalStopProcesso">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-red-500"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z"
                                    clip-rule="evenodd" />
                            </svg>
                            Parar Processo
                        </a>
                        <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=archive_processo"
                            class="flex items-center px-3 py-2 text-xs font-medium text-xs text-yellow-700 hover:bg-yellow-50 rounded-md transition-colors duration-150"
                            onclick="return confirm('Tem certeza que deseja arquivar este processo?')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-yellow-500"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path d="M4 3a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V3z" />
                                <path fill-rule="evenodd"
                                    d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            Arquivar Processo
                        </a>
                        <?php elseif ($status_processo == 'PARADO'): ?>
                        <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=restart_processo"
                            class="flex items-center px-3 py-2 text-xs font-medium text-xs text-green-700 hover:bg-green-50 rounded-md transition-colors duration-150"
                            onclick="return confirm('Tem certeza que deseja reiniciar este processo?')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-green-500"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"
                                    clip-rule="evenodd" />
                            </svg>
                            Reiniciar Processo
                        </a>
                        <?php else: // Assumindo status ARQUIVADO ?>
                        <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=unarchive_processo"
                            class="flex items-center px-3 py-2 text-xs font-medium text-xs text-green-700 hover:bg-green-50 rounded-md transition-colors duration-150"
                            onclick="return confirm('Tem certeza que deseja desarquivar este processo?')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-green-500"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                            </svg>
                            Desarquivar Processo
                        </a>
                        <?php endif; ?>

                        <?php if ($_SESSION['user']['nivel_acesso'] == 1 || $_SESSION['user']['nivel_acesso'] == 2 || $_SESSION['user']['nivel_acesso'] == 3): ?>
                        <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=delete_processo"
                            class="flex items-center px-3 py-2 text-xs font-medium text-xs text-red-700 hover:bg-red-50 rounded-md transition-colors duration-150"
                            onclick="return confirm('Tem certeza que deseja excluir este processo? Todos os documentos vinculados a este processo serão apagados.')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-red-500"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            Excluir Processo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h3 class="flex items-center space-x-1 text-xs font-medium text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414
               6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2
               6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0
               100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                        </svg>
                        <span>Lista de Documentos/Arquivos</span>
                    </h3>
                </div>

                <div class="p-4">
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($itens as $item): ?>
                        <li class="py-4 px-2 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                            <div class="flex-1">
                                <?php if ($item['tipo'] == 'documento'): ?>
                                <?php
                                            // Buscar registros de negação
                                            $temNegacao = false;
                                            $stmt = $conn->prepare('SELECT COUNT(*) as total FROM historico_negacoes WHERE documento_id = ?');
                                            $stmt->bind_param('i', $item['id']);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $registro = $result->fetch_assoc();
                                            if ($registro['total'] > 0) {
                                                $temNegacao = true;
                                            }
                                            ?>
                                <!-- Exibição do nome do arquivo e status -->
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <a href="#" onclick="openDocumentModal('<?php echo addslashes('../../' . $item['caminho_arquivo']); ?>',
                                                    '<?php echo addslashes($item['nome_arquivo']); ?>',
                                                    '<?php echo $item['id']; ?>',
                                                    '<?php echo $item['status']; ?>',
                                                    '<?php echo $estabelecimento_id; ?>',
                                                    '<?php echo $processo_id; ?>')" data-bs-toggle="modal"
                                        data-bs-target="#documentModal"
                                        class="text-blue-600 hover:text-blue-800 font-medium text-xs hover:underline">
                                        <?php echo htmlspecialchars($item['nome_arquivo']); ?>
                                    </a>

                                    <?php
                                                $statusBgColor = '';
                                                $statusTextColor = '';
                                                switch ($item['status']) {
                                                    case 'aprovado':
                                                        $statusBgColor = 'bg-green-100';
                                                        $statusTextColor = 'text-green-800';
                                                        break;
                                                    case 'negado':
                                                        $statusBgColor = 'bg-red-100';
                                                        $statusTextColor = 'text-red-800';
                                                        break;
                                                    default:  // pendente
                                                        $statusBgColor = 'bg-yellow-100';
                                                        $statusTextColor = 'text-yellow-800';
                                                }
                                                ?>

                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-xs cursor-pointer <?php echo $statusBgColor . ' ' . $statusTextColor; ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#historicoNegacoesModal<?php echo $item['id']; ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </div>

                                <div class="text-xs text-gray-500 space-y-1">
                                    <p>Adicionado em: <?php echo date('d/m/Y H:i', strtotime($item['data_upload'])); ?>
                                    </p>
                                    <?php if ($item['status'] == 'aprovado'): ?>
                                    <?php
                                                    $aprovador_nome = 'Não informado';
                                                    $data_aprovacao = 'Data não registrada';
                                                    if ($item['aprovado_por']) {
                                                        $stmt = $conn->prepare('SELECT nome_completo FROM usuarios WHERE id = ?');
                                                        $stmt->bind_param('i', $item['aprovado_por']);
                                                        $stmt->execute();
                                                        $result = $stmt->get_result();
                                                        $aprovador = $result->fetch_assoc();
                                                        if ($aprovador) {
                                                            $aprovador_nome = $aprovador['nome_completo'];
                                                        }
                                                    }
                                                    if (!empty($item['data_aprovacao'])) {
                                                        $data_aprovacao = date('d/m/Y H:i', strtotime($item['data_aprovacao']));
                                                    }
                                                    ?>
                                    <p class="text-green-600">
                                        Aprovado por: <?php echo htmlspecialchars($aprovador_nome); ?> em
                                        <?php echo htmlspecialchars($data_aprovacao); ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php if (isset($temNegacao) && $temNegacao): ?>
                                    <p class="text-red-600 font-medium text-xs">
                                        Este documento possui registro(s) de negação.
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($item['status']) && $item['status'] == 'negado'): ?>
                                <small style="color: red; font-size:11px; font-weight:bold;">Motivo:
                                    <?php echo htmlspecialchars($item['motivo_negacao']); ?></small>
                                <?php endif; ?>
                                <?php
                                            $historicoNegacoes = [];
                                            $stmt = $conn->prepare('SELECT motivo_negacao, data_negacao, u.nome_completo 
                                                FROM historico_negacoes h 
                                                JOIN usuarios u ON h.usuario_id = u.id 
                                                WHERE h.documento_id = ?');
                                            $stmt->bind_param('i', $item['id']);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $historicoNegacoes = $result->fetch_all(MYSQLI_ASSOC);
                                            ?>
                                <?php else: ?>
                                <?php if ($item['caminho_arquivo'] && $arquivo->todasAssinaturasConcluidas($item['id'])): ?>
                                <a href="#"
                                    onclick="openDocumentModal('<?php echo '../../' . htmlspecialchars($item['caminho_arquivo']); ?>', '<?php echo htmlspecialchars($item['tipo_documento'] . ' ' . $item['id'] . '.' . date('Y')); ?>')"
                                    data-bs-toggle="modal" data-bs-target="#documentModal">
                                    <?php echo htmlspecialchars($item['tipo_documento'] . ' ' . $item['id'] . '.' . date('Y')); ?>
                                </a>
                                <?php else: ?>
                                <?php echo htmlspecialchars($item['tipo_documento'] . ' ' . $item['id'] . '.' . date('Y')); ?>
                                (Rascunho)
                                <?php endif; ?>
                                <span class="badge bg-primary text-light" style="margin: 0 0 0 4px;">Documento</span>
                                <?php if ($item['sigiloso']): ?>
                                <span class="badge bg-danger text-light" style="margin: 0 0px;">Sigiloso</span>
                                <?php endif; ?>
                                <br>
                                <small style="color: #b1b1b1; font-size:10px;">Adicionado em:
                                    <?php echo date('d/m/Y  H:i', strtotime($item['data_upload'])); ?></small>
                                <?php if ($arquivo->isVisualizadoPorUsuarioExterno($item['id'])): ?>
                                <small style="color: green; font-size:10px;">- Visualizado</small>
                                <?php else: ?>
                                <small style="color: red; font-size:10px;">- Não Visualizado</small>
                                <?php endif; ?>
                                <?php if ($item['caminho_arquivo'] === ''): ?>
                                <small style="color: orange; font-size:10px;">- Falta finalizar o documento</small>
                                <?php elseif ($arquivo->todasAssinaturasPendentes($item['id'])): ?>
                                <small style="color: orange; font-size:10px;">- Aguardando assinaturas</small>
                                <?php elseif ($arquivo->arquivoFinalizadoComAssinaturasPendentes($item['id'])): ?>
                                <small style="color: red; font-size:10px;">- Documento finalizado, mas com assinaturas
                                    pendentes</small>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="flex flex-wrap gap-1" role="group" aria-label="Ações do Documento">
                                <?php if ($item['tipo'] == 'documento'): ?>

                                <?php if (in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4]) && $item['status'] == 'pendente'): ?>
                                <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=approve&doc_id=<?php echo $item['id']; ?>"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium text-xs rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                    title="Aprovar este documento">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Aprovar
                                </a>
                                <button
                                    class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium text-xs rounded shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                                    data-bs-toggle="modal" data-bs-target="#denyModal<?php echo $item['id']; ?>"
                                    title="Negar este documento">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Negar
                                </button>
                                <?php endif; ?>

                                <?php if (in_array($item['status'], ['aprovado', 'negado'])): ?>
                                <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=revert&doc_id=<?php echo $item['id']; ?>"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium text-xs rounded shadow-sm text-gray-700 bg-yellow-200 hover:bg-yellow-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200"
                                    onclick="return confirm('Tem certeza que deseja reverter o status deste documento para pendente?');"
                                    title="Reverter status para Pendente">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Reverter
                                </a>
                                <?php endif; ?>

                                <button
                                    class="inline-flex items-center px-2.5 py-1.5 border border-blue-500 text-xs font-medium text-xs rounded shadow-sm text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                    data-bs-toggle="modal" data-bs-target="#editModal<?php echo $item['id']; ?>"
                                    title="Editar nome do arquivo">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                    Editar
                                </button>
                                <button
                                    class="inline-flex items-center px-2.5 py-1.5 border border-red-500 text-xs font-medium text-xs rounded shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                                    data-bs-toggle="modal" data-bs-target="#confirmDeleteModalArquivo"
                                    data-url="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=delete&doc_id=<?php echo $item['id']; ?>"
                                    title="Excluir este arquivo">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Excluir
                                </button>

                                <?php else: ?>
                                <?php if (!$arquivo->todasAssinaturasConcluidas($item['id'])): ?>
                                <a href="pre_visualizar_arquivo.php?arquivo_id=<?php echo $item['id']; ?>&processo_id=<?php echo $processo_id; ?>&estabelecimento_id=<?php echo $estabelecimento_id; ?>"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium text-xs rounded shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200"
                                    title="Ver/Gerenciar Assinaturas">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                        <path fill-rule="evenodd"
                                            d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Assinaturas
                                </a>
                                <?php endif; ?>
                                <?php if (!$item['caminho_arquivo']): ?>
                                <a href="editar_documento.php?arquivo_id=<?php echo $item['id']; ?>&processo_id=<?php echo $processo_id; ?>&estabelecimento_id=<?php echo $estabelecimento_id; ?>"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-blue-500 text-xs font-medium text-xs rounded shadow-sm text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                    title="Editar Rascunho">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                    Editar
                                </a>
                                <?php endif; ?>
                                <button
                                    class="inline-flex items-center px-2.5 py-1.5 border border-blue-500 text-xs font-medium text-xs rounded shadow-sm text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                    data-bs-toggle="modal" data-bs-target="#viewersModal<?php echo $item['id']; ?>"
                                    title="Ver quem visualizou">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd"
                                            d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Visualizações
                                </button>
                                <button
                                    class="inline-flex items-center px-2.5 py-1.5 border border-red-500 text-xs font-medium text-xs rounded shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                                    data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                    data-delete-url="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=delete_arquivo&arquivo_id=<?php echo $item['id']; ?>"
                                    title="Excluir este documento">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Excluir
                                    <?php endif; ?>
                            </div>
                        </li>

                        <!-- Modal para Histórico de Negações -->
                        <div class="modal fade" id="historicoNegacoesModal<?php echo $item['id']; ?>" tabindex="-1"
                            aria-labelledby="historicoNegacoesLabel<?php echo $item['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="historicoNegacoesLabel<?php echo $item['id']; ?>">
                                            Histórico de Negações</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Fechar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if (!empty($historicoNegacoes)): ?>
                                        <ul class="list-group">
                                            <?php foreach ($historicoNegacoes as $historico): ?>
                                            <li class="list-group-item">
                                                <strong><?php echo htmlspecialchars($historico['nome_completo']); ?>:</strong>
                                                <?php echo htmlspecialchars($historico['motivo_negacao']); ?>
                                                <br>
                                                <small><?php echo date('d/m/Y H:i', strtotime($historico['data_negacao'])); ?></small>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php else: ?>
                                        <p>Nenhuma negação registrada.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal para Visualizar Documento -->
                        <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel"
                            aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
                                <!-- modal-xl para acomodar duas colunas, fullscreen em telas menores -->
                                <div class="modal-content rounded-lg shadow-sm overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-lg font-medium text-xs text-gray-900"
                                                id="documentModalLabel">
                                                <span class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                        class="h-5 w-5 mr-2 text-gray-500" viewBox="0 0 20 20"
                                                        fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    Visualizar Documento
                                                </span>
                                            </h3>
                                            <button type="button" class="text-gray-400 hover:text-gray-500"
                                                data-bs-dismiss="modal" aria-label="Fechar">
                                                <span class="sr-only">Fechar</span>
                                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="p-0">
                                        <div class="flex flex-col md:flex-row">
                                            <!-- Coluna do Visualizador (70%) -->
                                            <div class="w-full md:w-8/12 border-r border-gray-200">
                                                <iframe id="documentViewer" src="" class="w-full border-none"
                                                    style="min-height: 500px; height: calc(100vh - 300px); max-height: 70vh;"></iframe>
                                            </div>
                                            <!-- Coluna da Lista de Documentos (30%) -->
                                            <div class="w-full md:w-4/12 flex flex-col h-full">
                                                <div class="p-4 flex flex-col h-full">
                                                    <h4
                                                        class="text-base font-medium text-xs text-gray-900 pb-2 border-b border-gray-200 flex-shrink-0">
                                                        Documentos Necessários</h4>
                                                    <div class="document-list-container flex-grow overflow-y-auto mt-3"
                                                        style="max-height: calc(70vh - 150px);">
                                                        <!-- Conteúdo carregado via AJAX aparecerá aqui -->
                                                        <div class="flex justify-center items-center py-6">
                                                            <svg class="animate-spin h-8 w-8 text-blue-500"
                                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                                    stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor"
                                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                                </path>
                                                            </svg>
                                                            <span class="sr-only">Carregando...</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="px-4 py-2 bg-gray-50 border-t border-gray-200 flex justify-end space-x-2">
                                        <!-- Botão Aprovar -->
                                        <button id="approveButton"
                                            class="hidden inline-flex items-center px-2 py-1 border border-transparent text-xs font-normal rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-green-500">
                                            <span class="text-xs">Aprovar</span>
                                        </button>

                                        <!-- Botão Negar -->
                                        <button id="denyButton"
                                            class="hidden inline-flex items-center px-2 py-1 border border-transparent text-xs font-normal rounded shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-red-500">
                                            <span class="text-xs">Negar</span>
                                        </button>

                                        <!-- Botão Abrir em Nova Aba -->
                                        <a id="openInNewTab" href="#" target="_blank"
                                            class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-normal rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-blue-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path
                                                    d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
                                                <path
                                                    d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
                                            </svg>
                                            <span class="text-xs">Nova Aba</span>
                                        </a>
                                    </div>

                                </div>
                            </div>
                        </div>


                        <!-- Modal de confirmação para exclusão -->
                        <div class="modal fade" id="confirmDeleteModal" tabindex="-1"
                            aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Tem certeza que deseja excluir este documento?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancelar</button>
                                        <a href="#" id="confirmDeleteButton" class="btn btn-danger">Excluir</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal de Confirmação para Arquivo -->
                        <div class="modal fade" id="confirmDeleteModalArquivo" tabindex="-1"
                            aria-labelledby="confirmDeleteLabelArquivo" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="confirmDeleteLabelArquivo">Confirmar Exclusão de
                                            Arquivo</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Fechar"></button>
                                    </div>
                                    <div class="modal-body">
                                        Tem certeza de que deseja excluir este arquivo? Esta ação não pode ser desfeita.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancelar</button>
                                        <a id="deleteConfirmButtonArquivo" href="#" class="btn btn-danger">Excluir</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal para negar documento (abre ao clicar no botão de negação) -->
                        <?php if ($item['tipo'] == 'documento'): ?>
                        <div class="modal fade" id="denyModal<?php echo $item['id']; ?>" tabindex="-1"
                            aria-labelledby="denyModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="denyModalLabel<?php echo $item['id']; ?>">Negar
                                            Documento</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form
                                            action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                                            method="POST">
                                            <div class="mb-3">
                                                <label for="motivo_predefinido<?php echo $item['id']; ?>"
                                                    class="form-label">Escolha um Motivo</label>
                                                <select class="form-select"
                                                    id="motivo_predefinido<?php echo $item['id']; ?>"
                                                    onchange="atualizarMotivo(<?php echo $item['id']; ?>)">
                                                    <option value="">Selecione um motivo</option>
                                                    <option value="Arquivo ilegível">Arquivo ilegível</option>
                                                    <option
                                                        value="Arquivo não atende os requisitos da vigilância sanitária">
                                                        Arquivo não atende os requisitos da vigilância sanitária
                                                    </option>
                                                    <option value="Informações incompletas">Informações incompletas
                                                    </option>
                                                    <option value="Arquivo duplicado">Arquivo duplicado</option>
                                                    <option value="">Escrever Motivo</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="motivo_negacao<?php echo $item['id']; ?>"
                                                    class="form-label">Motivo da Negação</label>
                                                <textarea class="form-control"
                                                    id="motivo_negacao<?php echo $item['id']; ?>" name="motivo_negacao"
                                                    rows="3" required></textarea>
                                            </div>
                                            <input type="hidden" name="documento_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-danger"
                                                name="negar_documento">Salvar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="editModal<?php echo $item['id']; ?>" tabindex="-1"
                            aria-labelledby="editModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editModalLabel<?php echo $item['id']; ?>">Editar
                                            Documento</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form
                                            action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                                            method="POST">
                                            <div class="form-group">
                                                <label for="novo_nome">Novo Nome</label>
                                                <input type="text" class="form-control" id="novo_nome" name="novo_nome"
                                                    value="<?php echo htmlspecialchars($item['nome_arquivo']); ?>"
                                                    required>
                                            </div>
                                            <input type="hidden" name="documento_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm"
                                                style="margin-top:10px;" name="editar_nome">Salvar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="modal fade" id="viewersModal<?php echo $item['id']; ?>" tabindex="-1"
                            aria-labelledby="viewersModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="viewersModalLabel<?php echo $item['id']; ?>">
                                            Visualizações do Documento</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php
                                                $visualizacoes = $arquivo->getVisualizacoes($item['id']);
                                                if (!empty($visualizacoes)):
                                                    ?>
                                        <ul class="list-group">
                                            <?php foreach ($visualizacoes as $visualizacao): ?>
                                            <li class="list-group-item">
                                                <?php echo htmlspecialchars($visualizacao['nome_completo']); ?><br>
                                                <small style="margin-left:5px;"> -
                                                    <?php echo date('d/m/Y H:i:s', strtotime($visualizacao['data_visualizacao'])); ?></small>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php else: ?>
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

        <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
            <!-- ORDENS DE SERVIÇO -------------------------------------------->
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                <h3 class="flex items-center space-x-1 text-xs font-medium text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3
               2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3
               4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000
               2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0
               100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                    </svg>
                    <span>Ordens de Serviço</span>
                </h3>
            </div>
            <div class="p-4">
                <?php if (empty($ordensServico)): ?>
                <div class="py-4 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-gray-300" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="mt-2 text-xs text-gray-500">Nenhuma ordem de serviço para este processo.</p>
                </div>
                <?php else: ?>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($ordensServico as $ordem): ?>
                    <?php
                                $statusClass = '';
                                switch (strtolower($ordem['status'])) {
                                    case 'concluída':
                                    case 'concluida':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        break;
                                    case 'em andamento':
                                        $statusClass = 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'pendente':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'cancelada':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        break;
                                    default:
                                        $statusClass = 'bg-gray-100 text-gray-800 text-xs';
                                }
                                ?>
                    <li class="py-4 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <div class="flex-1">
                            <h4 class="text-base font-medium text-xs text-gray-900">Ordem de Serviço
                                <?php echo htmlspecialchars($ordem['id'] . '.' . date('Y', strtotime($ordem['data_inicio']))); ?>
                            </h4>
                            <div class="mt-1 flex flex-wrap gap-x-4 text-xs text-gray-600">
                                <p>
                                    <span class="font-medium text-xs text-gray-500">Período:</span>
                                    <?php
                                                $data_inicio = date('d/m/Y', strtotime($ordem['data_inicio']));
                                                $data_fim = date('d/m/Y', strtotime($ordem['data_fim']));
                                                echo $data_inicio . ' - ' . $data_fim;
                                                ?>
                                </p>
                                <p>
                                    <span class="font-medium text-xs text-gray-500">Status:</span>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-xs <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst(strtolower($ordem['status']))); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div>
                            <a href="../OrdemServico/detalhes_ordem.php?id=<?php echo $ordem['id']; ?>"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium text-xs rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd"
                                        d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                Visualizar
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
            <!-- ALERTAS ------------------------------------------------------->
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                <h3 class="flex items-center space-x-1 text-xs font-medium text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1
               1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0
               001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span>Alertas</span>
                </h3>
            </div>
            <div class="p-4">
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($processo->getAlertasByProcesso($processo_id) as $alerta): ?>
                    <li class="py-4 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div class="flex-1">
                            <h4 class="text-base font-medium text-xs text-gray-900">
                                <?php echo htmlspecialchars($alerta['descricao']); ?></h4>
                            <div class="mt-1 flex flex-wrap gap-x-4 text-xs text-gray-600">
                                <p>
                                    <span class="font-medium text-xs text-gray-500">Prazo:</span>
                                    <?php echo date('d/m/Y', strtotime($alerta['prazo'])); ?>
                                </p>
                                <?php
                                        $statusClass = $alerta['status'] == 'ativo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800 text-xs';
                                        ?>
                                <p>
                                    <span class="font-medium text-xs text-gray-500">Status:</span>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-xs <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst($alerta['status'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($alerta['status'] == 'ativo'): ?>
                            <button
                                class="inline-flex items-center p-1.5 border border-transparent rounded-full shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                title="Finalizar alerta" onclick="finalizeAlerta(<?php echo $alerta['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <?php endif; ?>
                            <button
                                class="inline-flex items-center p-1.5 border border-transparent rounded-full shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200"
                                title="Editar alerta" data-bs-toggle="modal"
                                data-bs-target="#editAlertaModal<?php echo $alerta['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path
                                        d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                </svg>
                            </button>
                            <button
                                class="inline-flex items-center p-1.5 border border-transparent rounded-full shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                                title="Excluir alerta" data-bs-toggle="modal"
                                data-bs-target="#deleteAlertaModal<?php echo $alerta['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Modal para editar alerta -->
        <div class="modal fade" id="editAlertaModal<?php echo $alerta['id']; ?>" tabindex="-1" role="dialog"
            aria-labelledby="editAlertaModalLabel<?php echo $alerta['id']; ?>" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content rounded-lg shadow-xl">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-xs text-gray-900"
                                id="editAlertaModalLabel<?php echo $alerta['id']; ?>">
                                Editar Alerta
                            </h3>
                            <button type="button" class="text-gray-400 hover:text-gray-500" data-bs-dismiss="modal"
                                aria-label="Close">
                                <span class="sr-only">Fechar</span>
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="px-4 py-5">
                        <form
                            action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                            method="POST">
                            <div class="space-y-4">
                                <div>
                                    <label for="descricao<?php echo $alerta['id']; ?>"
                                        class="block text-xs font-medium text-xs text-gray-700">Descrição</label>
                                    <input type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs"
                                        id="descricao<?php echo $alerta['id']; ?>" name="descricao"
                                        value="<?php echo htmlspecialchars($alerta['descricao']); ?>" required>
                                </div>
                                <div>
                                    <label for="prazo<?php echo $alerta['id']; ?>"
                                        class="block text-xs font-medium text-xs text-gray-700">Prazo</label>
                                    <input type="date"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs"
                                        id="prazo<?php echo $alerta['id']; ?>" name="prazo"
                                        value="<?php echo htmlspecialchars($alerta['prazo']); ?>" required>
                                </div>
                                <div>
                                    <label for="status<?php echo $alerta['id']; ?>"
                                        class="block text-xs font-medium text-xs text-gray-700">Status</label>
                                    <select
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs"
                                        id="status<?php echo $alerta['id']; ?>" name="status" required>
                                        <option value="ativo"
                                            <?php if ($alerta['status'] == 'ativo') echo 'selected'; ?>>Ativo</option>
                                        <option value="finalizado"
                                            <?php if ($alerta['status'] == 'finalizado') echo 'selected'; ?>>Finalizado
                                        </option>
                                    </select>
                                </div>
                                <input type="hidden" name="alerta_id" value="<?php echo $alerta['id']; ?>">
                                <div class="mt-5 flex justify-end space-x-3">
                                    <button type="button"
                                        class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-medium text-xs text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        data-bs-dismiss="modal">
                                        Cancelar
                                    </button>
                                    <button type="submit"
                                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-xs font-medium text-xs text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        name="editar_alerta">
                                        Salvar alterações
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Visualizar Documento (duplicado para outros usos) -->
        <div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-labelledby="viewDocumentModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewDocumentModalLabel">Visualizar Documento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <iframe id="documentViewer" src="" frameborder="0"
                            style="width: 100%; min-height: 800px; height: calc(100vh - 200px);"></iframe>
                    </div>
                    <div class="modal-footer">
                        <a id="openInNewTab" href="#" target="_blank" class="btn btn-primary">Abrir em Nova Aba</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para excluir alerta -->
        <div class="modal fade" id="deleteAlertaModal<?php echo $alerta['id']; ?>" tabindex="-1" role="dialog"
            aria-labelledby="deleteAlertaModalLabel<?php echo $alerta['id']; ?>" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content rounded-lg shadow-xl">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-xs text-gray-900"
                                id="deleteAlertaModalLabel<?php echo $alerta['id']; ?>">
                                Excluir Alerta
                            </h3>
                            <button type="button" class="text-gray-400 hover:text-gray-500" data-bs-dismiss="modal"
                                aria-label="Close">
                                <span class="sr-only">Fechar</span>
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="px-4 py-5">
                        <form
                            action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                            method="POST">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-xs text-gray-900">Confirmar exclusão
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500">
                                            Tem certeza que deseja excluir este alerta? Esta ação não poderá ser
                                            desfeita.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="alerta_id" value="<?php echo $alerta['id']; ?>">
                            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-xs text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-xs"
                                    name="excluir_alerta">
                                    Excluir
                                </button>
                                <button type="button"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-xs text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-xs"
                                    data-bs-dismiss="modal">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para adicionar alerta -->
<div class="modal fade" id="addAlertaModal" tabindex="-1" role="dialog" aria-labelledby="addAlertaModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content rounded-lg shadow-xl">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-xs text-gray-900" id="addAlertaModalLabel">
                        Adicionar Alerta
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500" data-bs-dismiss="modal"
                        aria-label="Close">
                        <span class="sr-only">Fechar</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-4 py-5">
                <form
                    action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                    method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="descricao"
                                class="block text-xs font-medium text-xs text-gray-700">Descrição</label>
                            <input type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs"
                                id="descricao" name="descricao" placeholder="Descreva o alerta" required>
                        </div>
                        <div>
                            <label for="prazo" class="block text-xs font-medium text-xs text-gray-700">Prazo</label>
                            <input type="date"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-xs"
                                id="prazo" name="prazo" required>
                        </div>
                        <div class="mt-5 flex justify-end space-x-3">
                            <button type="button"
                                class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-medium text-xs text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-xs font-medium text-xs text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                name="adicionar_alerta">
                                Adicionar alerta
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modal de Upload de Arquivos -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-lg shadow-xl">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-xs text-gray-900" id="uploadModalLabel">
                        <span class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                            Upload de Arquivos
                        </span>
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500" data-bs-dismiss="modal"
                        aria-label="Close">
                        <span class="sr-only">Fechar</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-4 py-5">
                <form
                    action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                    method="POST" enctype="multipart/form-data">
                    <div class="space-y-4">
                        <div>
                            <label for="files" class="block text-xs font-medium text-xs text-gray-700">Escolha os
                                arquivos</label>
                            <div
                                class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none"
                                        viewBox="0 0 48 48" aria-hidden="true">
                                        <path
                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-xs text-gray-600">
                                        <label for="files"
                                            class="relative cursor-pointer bg-white rounded-md font-medium text-xs text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>Selecione os arquivos</span>
                                            <input id="files" name="files[]" type="file" class="sr-only" multiple
                                                required>
                                        </label>
                                        <p class="pl-1">ou arraste e solte</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        PDF, DOC, DOCX, JPG, PNG até 10MB
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end space-x-3">
                            <button type="button"
                                class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-medium text-xs text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-xs font-medium text-xs text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                Enviar arquivos
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para adicionar alerta (duplicado) -->
<div class="modal fade" id="addAlertaModal" tabindex="-1" aria-labelledby="addAlertaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="addAlertaModalLabel">Adicionar Alerta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form
                    action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                    method="POST">
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

<!-- Modal para Parar Processo -->
<div class="modal fade" id="modalStopProcesso" tabindex="-1" aria-labelledby="modalStopProcessoLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalStopProcessoLabel">Parar Processo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form
                    action="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>"
                    method="POST">
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

<div id="successMessage" class="alert alert-success"
    style="position: fixed; top: 20px; right: -300px; z-index: 1000; transition: right 0.5s ease-in-out; display: none;">
    Documento aprovado com sucesso!
</div>
<div id="errorMessage" class="alert alert-danger"
    style="position: fixed; top: 20px; right: -300px; z-index: 1000; transition: right 0.5s ease-in-out; display: none;">
    Documento negado com sucesso!
</div>
<div id="warningMessage" class="alert alert-warning"
    style="position: fixed; top: 20px; right: -300px; z-index: 1000; transition: right 0.5s ease-in-out; display: none;">
    Documento revertido com sucesso!
</div>

<script>
// Script para melhorar a visualização de documentos no modal
$(document).ready(function() {
    // Configuração do modal de documentos para exibição em tela cheia
    $('#documentModal').on('shown.bs.modal', function() {
        // Ajustar a altura do iframe para ocupar o espaço disponível
        adjustIframeHeight();

        // Adicionar evento de redimensionamento da janela
        $(window).on('resize', adjustIframeHeight);
    });

    // Quando o modal for fechado, remover o evento de redimensionamento
    $('#documentModal').on('hidden.bs.modal', function() {
        $(window).off('resize', adjustIframeHeight);
    });

    // Função para ajustar a altura do iframe e da lista de documentos
    function adjustIframeHeight() {
        const windowHeight = window.innerHeight;
        const modalHeader = $('#documentModal .modal-header').outerHeight() || 0;
        const modalFooter = $('#documentModal .modal-footer').outerHeight() || 0;
        const padding = 60; // Espaço adicional para padding e margens

        // Calcular a altura disponível (limitada a 70% da altura da janela)
        const maxAvailableHeight = windowHeight * 0.7;
        const availableHeight = Math.min(maxAvailableHeight, windowHeight - modalHeader - modalFooter -
            padding);

        // Definir altura entre 600px e a altura disponível calculada
        const newHeight = Math.max(600, Math.min(availableHeight, 800));

        // Aplicar a nova altura ao iframe
        $('#documentViewer').css({
            'height': newHeight + 'px',
            'max-height': '80vh'
        });

        // Ajustar a altura da lista de documentos para acompanhar o iframe
        const documentListHeight = newHeight - 50; // Um pouco menor que o iframe para considerar o cabeçalho
        $('.document-list-container').css('max-height', documentListHeight + 'px');

        console.log('Altura do iframe ajustada para: ' + newHeight + 'px');
        console.log('Altura da lista de documentos ajustada para: ' + documentListHeight + 'px');
    }

    // Quando um documento é carregado no iframe
    $('#documentViewer').on('load', function() {
        try {
            // Tentar ajustar a altura com base no conteúdo do documento
            const iframeDoc = this.contentDocument || this.contentWindow.document;
            const docHeight = iframeDoc.body.scrollHeight;
            const windowHeight = window.innerHeight;

            // Limitar a altura a 70% da altura da janela e no máximo 700px
            const maxHeight = Math.min(windowHeight * 0.7, 700);
            // Definir altura entre 600px e a altura do documento (limitada ao máximo)
            const newHeight = Math.max(600, Math.min(docHeight + 50, maxHeight)); // +50px para margem

            $(this).css({
                'height': newHeight + 'px',
                'max-height': '70vh'
            });

            // Ajustar a lista de documentos também
            const documentListHeight = newHeight - 50;
            $('.document-list-container').css('max-height', documentListHeight + 'px');

            console.log('Documento carregado, altura ajustada para: ' + newHeight + 'px');
        } catch (e) {
            // Se não conseguir acessar o documento (por questões de segurança cross-origin)
            console.log('Não foi possível ajustar a altura automaticamente: ' + e.message);
            // Neste caso, usamos a função de ajuste baseada na janela
            adjustIframeHeight();
        }
    });

    // Adicionar timestamp para evitar cache ao abrir documentos
    $('a[data-bs-toggle="modal"][data-bs-target="#documentModal"]').on('click', function() {
        const documentUrl = $(this).data('document-url');
        if (documentUrl) {
            // Adicionar timestamp para evitar cache
            const nocacheUrl = documentUrl + (documentUrl.includes('?') ? '&' : '?') + 'nocache=' +
                new Date().getTime();
            $('#documentViewer').attr('src', nocacheUrl);
            $('#openInNewTab').attr('href', documentUrl);
        }
    });
});
</script>

<script>
function finalizeAlerta(alerta_id) {
    if (confirm('Tem certeza que deseja finalizar este alerta?')) {
        window.location.href =
            'documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=finalize_alerta&alerta_id=' +
            alerta_id;
    }
}

function confirmarCriacaoDocumento(processo_id, estabelecimento_id, hasPendentes) {
    if (hasPendentes) {
        const confirma = confirm('Atenção: Existem documentos pendentes de aprovação. Deseja continuar mesmo assim?');
        if (!confirma) return;
    }
    window.location.href = '../Arquivos/criar_arquivo.php?processo_id=' + processo_id + '&id=' + estabelecimento_id;
}
</script>

<script>
var confirmDeleteModal = document.getElementById('confirmDeleteModal');
var confirmDeleteButton = document.getElementById('confirmDeleteButton');
confirmDeleteModal.addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var deleteUrl = button.getAttribute('data-delete-url');
    confirmDeleteButton.href = deleteUrl;
});

document.addEventListener('DOMContentLoaded', function() {
    const confirmDeleteModalArquivo = document.getElementById('confirmDeleteModalArquivo');
    const deleteConfirmButtonArquivo = document.getElementById('deleteConfirmButtonArquivo');
    confirmDeleteModalArquivo.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const url = button.getAttribute('data-url');
        deleteConfirmButtonArquivo.setAttribute('href', url);
    });
});

// Função para abrir o modal de visualização do documento com os botões de Aprovar e Negar (se pendente)
function openDocumentModal(documentPath, documentName, docId, docStatus) {
    const viewer = document.getElementById('documentViewer');
    const newTabLink = document.getElementById('openInNewTab');
    const approveButton = document.getElementById('approveButton');
    const denyButton = document.getElementById('denyButton');
    viewer.src = documentPath;
    newTabLink.href = documentPath;
    const modalTitle = document.getElementById('documentModalLabel');
    modalTitle.textContent = documentName;
    if (docStatus === 'pendente') {
        approveButton.style.display = 'inline-block';
        approveButton.onclick = function() {
            window.location.href =
                "documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>&action=approve&doc_id=" +
                docId;
        };
        denyButton.style.display = 'inline-block';
        denyButton.onclick = function() {
            var documentModalEl = document.getElementById('documentModal');
            documentModalEl.addEventListener('shown.bs.modal', function() {
                loadDocumentosNecessarios('<?= $estabelecimento_id ?>', 'primeiro');
            });
            var modalInstance = bootstrap.Modal.getInstance(documentModalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
            var denyModalEl = document.getElementById('denyModal' + docId);
            var denyModal = new bootstrap.Modal(denyModalEl);
            denyModal.show();
        };
    } else {
        approveButton.style.display = 'none';
        approveButton.onclick = null;
        denyButton.style.display = 'none';
        denyButton.onclick = null;
    }
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
    messageDiv.text(message);
    messageDiv.css('right', '20px').fadeIn();
    setTimeout(function() {
        messageDiv.css('right', '-300px').fadeOut();
    }, 3000);
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
        textareaElement.value = '';
    }
}

// Salva a posição do scroll antes da página ser descarregada
window.onbeforeunload = function() {
    sessionStorage.setItem('scrollpos', window.pageYOffset);
};

// Ao carregar a página, se houver posição salva, restaura-a
document.addEventListener("DOMContentLoaded", function() {
    var scrollpos = sessionStorage.getItem('scrollpos');
    if (scrollpos) {
        window.scrollTo(0, scrollpos);
        sessionStorage.removeItem('scrollpos');
    }
});

function loadDocumentosNecessarios(estabelecimentoId, tipo, processoId) {
    const container = document.querySelector('.document-list-container');
    container.innerHTML = `
    <div class="text-center py-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Carregando...</span>
      </div>
    </div>
  `;

    fetch(`cnae_documentos_visa.php?id=${estabelecimentoId}&tipo=${tipo}&processo_id=${processoId}&ajax=1`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na resposta do servidor');
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
            updateApprovedStyles(processoId, estabelecimentoId);
        })
        .catch(error => {
            container.innerHTML =
                `<div class="alert alert-danger">Erro ao carregar documentos: ${error.message}</div>`;
        });
}




function openDocumentModal(documentPath, documentName, docId, docStatus, estabelecimentoId, processoId) {
    // Declare a chave única para este processo/estabelecimento:
    const storageKey = `aprovados_${processoId}_${estabelecimentoId}`;

    // Aqui você pode usar 'storageKey' para ler/salvar o estado no localStorage
    // Exemplo: recuperar o estado
    let aprovados = JSON.parse(localStorage.getItem(storageKey)) || {};

    // Configuração do modal...
    const viewer = document.getElementById('documentViewer');
    const newTabLink = document.getElementById('openInNewTab');
    const approveButton = document.getElementById('approveButton');
    const denyButton = document.getElementById('denyButton');
    const modalTitle = document.getElementById('documentModalLabel');

    viewer.src = documentPath;
    newTabLink.href = documentPath;
    modalTitle.textContent = documentName;

    // Exemplo de verificação se este documento já foi marcado como aprovado:
    if (aprovados[docId]) {
        // Pode alterar o visual do item para "aprovado"
        approveButton.classList.add('active');
    }

    // Carrega a lista de documentos necessários (via AJAX)
    loadDocumentosNecessarios(estabelecimentoId, 'primeiro');

    // Configura os botões de Aprovar/Negar se o status for 'pendente'
    if (docStatus === 'pendente') {
        approveButton.style.display = 'inline-block';
        approveButton.onclick = function() {
            window.location.href = "documentos.php?processo_id=" + processoId + "&id=" + estabelecimentoId +
                "&action=approve&doc_id=" + docId;
        };

        denyButton.style.display = 'inline-block';
        denyButton.onclick = function() {
            // Esconde o modal atual e exibe o modal de negação (supondo que exista um modal de negação com id "denyModal" + docId)
            const documentModalEl = document.getElementById('documentModal');
            const modalInstance = bootstrap.Modal.getInstance(documentModalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
            const denyModalEl = document.getElementById('denyModal' + docId);
            const denyModal = new bootstrap.Modal(denyModalEl);
            denyModal.show();
        };
    } else {
        approveButton.style.display = 'none';
        approveButton.onclick = null;
        denyButton.style.display = 'none';
        denyButton.onclick = null;
    }
}

// Função para alternar o estado de "aprovado" de um documento
function toggleApproved(checkbox, docCode, processoId, estabelecimentoId) {
    const storageKey = `aprovados_${processoId}_${estabelecimentoId}`;
    let approvedDocs = JSON.parse(localStorage.getItem(storageKey)) || {};
    if (checkbox.checked) {
        approvedDocs[docCode] = true;
    } else {
        delete approvedDocs[docCode];
    }
    localStorage.setItem(storageKey, JSON.stringify(approvedDocs));
    updateApprovedStyles(processoId, estabelecimentoId);
}


// Atualiza os estilos dos itens aprovados com base no localStorage
function updateApprovedStyles(processoId, estabelecimentoId) {
    const storageKey = `aprovados_${processoId}_${estabelecimentoId}`;
    let approvedDocs = JSON.parse(localStorage.getItem(storageKey)) || {};
    console.log("Atualizando estilos com", approvedDocs);
    document.querySelectorAll('.document-list-container li').forEach(function(li) {
        let docCode = li.getAttribute('data-doc-code');
        let cb = li.querySelector('.document-approve-checkbox');
        if (approvedDocs[docCode]) {
            li.classList.add('approved');
            if (cb) cb.checked = true;
        } else {
            li.classList.remove('approved');
            if (cb) cb.checked = false;
        }
    });
}

// Chama updateApprovedStyles() após carregar os documentos via AJAX
document.addEventListener("DOMContentLoaded", updateApprovedStyles);
</script>


<?php include '../footer.php'; ?>