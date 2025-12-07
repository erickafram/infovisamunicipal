<?php
session_start();
// ob_start(); // Geralmente não necessário se redirects ocorrem antes de qualquer output

require_once '../../conf/database.php';
require_once '../../models/UsuarioExterno.php';
require_once '../../models/Estabelecimento.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

$usuarioExterno = new UsuarioExterno($conn);
$estabelecimento = new Estabelecimento($conn);

$estabelecimentoId = $_GET['id'] ?? null;

if (!$estabelecimentoId) {
    $_SESSION['error_message'] = "ID do estabelecimento não fornecido!";
    header("Location: listar_estabelecimentos.php");
    exit();
}

$dadosEstabelecimento = $estabelecimento->findById($estabelecimentoId);

if (!$dadosEstabelecimento) {
    $_SESSION['error_message'] = "Estabelecimento não encontrado!";
    header("Location: listar_estabelecimentos.php");
    exit();
}

$usuarios = $usuarioExterno->getUsuariosByEstabelecimento($estabelecimentoId);
$searchTerm = $_GET['search'] ?? '';

if (!empty($searchTerm)) {
    $usuariosDisponiveis = $usuarioExterno->searchUsuarios($searchTerm);
} else {
    // Opcional: pode carregar todos ou nenhum por padrão para forçar a busca
    $usuariosDisponiveis = []; // Carrega apenas se houver busca
    // $usuariosDisponiveis = $usuarioExterno->getAllUsuarios(); // Para carregar todos inicialmente
}

// --- Processamento POST (Vincular) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'])) {
    $usuarioId = $_POST['usuario_id'];
    $tipoVinculo = $_POST['tipo_vinculo'];
    $redirectUrl = "acesso_empresa.php?id=$estabelecimentoId" . (!empty($searchTerm) ? "&search=" . urlencode($searchTerm) : ""); // Mantém a busca

    if ($usuarioExterno->vincularUsuarioEstabelecimento($usuarioId, $estabelecimentoId, $tipoVinculo)) {
        $_SESSION['success_message'] = "Usuário vinculado com sucesso!";
    } else {
        // A função vincularUsuarioEstabelecimento poderia retornar uma mensagem de erro específica
        $_SESSION['error_message'] = $conn->error ?: "O usuário já está vinculado ou ocorreu um erro.";
    }
    header("Location: $redirectUrl");
    exit();
}
// --- Fim Processamento POST ---

// --- Processamento GET (Desvincular) ---
if (isset($_GET['delete']) && isset($_GET['usuario_id'])) {
    $usuarioId = $_GET['usuario_id'];
    $redirectUrl = "acesso_empresa.php?id=$estabelecimentoId" . (!empty($searchTerm) ? "&search=" . urlencode($searchTerm) : ""); // Mantém a busca

    if ($usuarioExterno->desvincularUsuarioEstabelecimento($usuarioId, $estabelecimentoId)) {
        $_SESSION['success_message'] = "Usuário desvinculado com sucesso!";
    } else {
        $_SESSION['error_message'] = $conn->error ?: "Erro ao desvincular o usuário.";
    }
    header("Location: $redirectUrl");
    exit();
}
// --- Fim Processamento GET ---

// Incluir Header APÓS processamento e redirects
include '../header.php';

// Obter e limpar mensagens da sessão
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Função para badge de vínculo
function getVinculoBadgeClass($tipoVinculo)
{
    $tipo = strtoupper($tipoVinculo ?? '');
    switch ($tipo) {
        case 'CONTADOR':
            return 'bg-info text-dark';
        case 'RESPONSÁVEL LEGAL':
            return 'bg-primary';
        case 'RESPONSÁVEL TÉCNICO':
            return 'bg-success';
        case 'FUNCIONÁRIO':
            return 'bg-secondary';
        default:
            return 'bg-light text-dark';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Empresa - <?= htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? $dadosEstabelecimento['razao_social'] ?? 'N/A') ?></title>
</head>

<body>

<div class="container mx-auto px-4 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md overflow-hidden h-full border border-gray-100 transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b border-gray-200">
                    <h3 class="text-gray-700 font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                        Menu Rápido
                    </h3>
                    </div>

                <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                <div class="flex flex-col flex-grow-1 divide-y divide-gray-100">
                    <a href="detalhes_estabelecimento.php?id=<?= $estabelecimentoId; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Detalhes</span>
                    </a>
                    <a href="editar_estabelecimento.php?id=<?= $estabelecimentoId; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'editar_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'editar_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        <span class="text-sm">Editar</span>
                    </a>
                    <a href="atividades.php?id=<?= $estabelecimentoId; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'atividades.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'atividades.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Atividades (CNAE)</span>
                    </a>
                    <a href="responsaveis.php?id=<?= $estabelecimentoId; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'responsaveis.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'responsaveis.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                        </svg>
                        <span class="text-sm">Responsáveis</span>
                    </a>
                    <a href="acesso_empresa.php?id=<?= $estabelecimentoId; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'acesso_empresa.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'acesso_empresa.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 00-1-1H7a1 1 0 00-1 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Acesso Empresa</span>
                    </a>
                    <a href="../Processo/processos.php?id=<?= $estabelecimentoId; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'processos.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'processos.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1H8a3 3 0 00-3 3v1.5a1.5 1.5 0 01-3 0V6z" clip-rule="evenodd" />
                            <path d="M6 12a2 2 0 012-2h8a2 2 0 012 2v2a2 2 0 01-2 2H2h2a2 2 0 002-2v-2z" />
                        </svg>
                        <span class="text-sm">Processos</span>
                    </a>
                </div>
                <div class="p-4 bg-gray-50 border-t border-gray-100">
                    <a href="detalhes_estabelecimento.php?id=<?= $estabelecimentoId; ?>" class="flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Voltar aos Detalhes
                        </a>
                    </div>
                </div>
            </div>

        <div class="lg:col-span-3">
            <div id="alertContainer">
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded shadow-sm relative" role="alert">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        <?= htmlspecialchars($success_message) ?>
                        </div>
                        <button type="button" class="absolute top-0 right-0 mt-4 mr-4" data-bs-dismiss="alert" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-700" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded shadow-sm relative" role="alert">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        <?= htmlspecialchars($error_message) ?>
                        </div>
                        <button type="button" class="absolute top-0 right-0 mt-4 mr-4" data-bs-dismiss="alert" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-700" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <h5 class="font-medium text-lg mb-4 text-gray-700">
                <i class="fas fa-user-plus mr-2"></i>Acesso Empresa: <span class="text-blue-600"><?= htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? ($dadosEstabelecimento['nome'] ?? $dadosEstabelecimento['razao_social'] ?? 'Não informado')); ?></span>
            </h5>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 text-secondary"><i class="fas fa-search me-2"></i>Pesquisar e Vincular Usuário Externo</h6>
                    </div>
                    <div class="card-body p-3">
                        <form method="GET" action="acesso_empresa.php" class="mb-3">
                            <input type="hidden" name="id" value="<?= $estabelecimentoId; ?>">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Pesquisar usuário por nome ou CPF..." value="<?= htmlspecialchars($searchTerm); ?>" aria-label="Pesquisar usuário">
                                <button type="submit" class="btn btn-outline-secondary" id="button-search"><i class="fas fa-search"></i></button>
                            </div>
                        </form>

                        <?php if (!empty($searchTerm) && !empty($usuariosDisponiveis)): ?>
                            <form method="POST" action="acesso_empresa.php?id=<?= $estabelecimentoId; ?><?= (!empty($searchTerm) ? "&search=" . urlencode($searchTerm) : "") ?>">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-6">
                                        <label for="usuario_id" class="form-label form-label-sm">Selecionar Usuário:</label>
                                        <select class="form-select form-select-sm" id="usuario_id" name="usuario_id" required>
                                            <?php foreach ($usuariosDisponiveis as $usuario) : ?>
                                                <option value="<?= htmlspecialchars($usuario['id']); ?>">
                                                    <?= htmlspecialchars($usuario['nome_completo']); ?> (<?= htmlspecialchars($usuario['cpf']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="tipo_vinculo" class="form-label form-label-sm">Tipo de Vínculo:</label>
                                        <select class="form-select form-select-sm" id="tipo_vinculo" name="tipo_vinculo" required>
                                            <option value="CONTADOR">CONTADOR</option>
                                            <option value="RESPONSÁVEL LEGAL">RESPONSÁVEL LEGAL</option>
                                            <option value="RESPONSÁVEL TÉCNICO">RESPONSÁVEL TÉCNICO</option>
                                            <option value="FUNCIONÁRIO">FUNCIONÁRIO</option>
                                            <option value="OUTRO">OUTRO</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-link me-1"></i> Vincular
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php elseif (!empty($searchTerm) && empty($usuariosDisponiveis)): ?>
                            <p class="text-muted mt-3 mb-0"><i class="fas fa-info-circle me-1"></i> Nenhum usuário encontrado com o termo "<?= htmlspecialchars($searchTerm) ?>".</p>
                        <?php elseif (empty($searchTerm)): ?>
                            <p class="text-muted mt-3 mb-0"><i class="fas fa-info-circle me-1"></i> Digite um nome ou CPF para pesquisar usuários e vinculá-los.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 text-secondary"><i class="fas fa-users me-2"></i>Usuários Vinculados a Este Estabelecimento</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php if (!empty($usuarios)) : ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($usuarios as $usuario) : ?>
                                    <li class="list-group-item d-flex flex-column flex-sm-row justify-content-between align-items-sm-center px-0 py-2">
                                        <div class="mb-1 mb-sm-0 me-sm-2">
                                            <strong class="d-block"><?= htmlspecialchars($usuario['nome_completo']); ?></strong>
                                            <small class="text-muted">CPF: <?= htmlspecialchars($usuario['cpf']); ?></small>
                                        </div>
                                        <div class="d-flex align-items-center flex-shrink-0">
                                            <span class="badge rounded-pill me-2 <?= getVinculoBadgeClass($usuario['tipo_vinculo']); ?>"><?= htmlspecialchars($usuario['tipo_vinculo']); ?></span>
                                            <a href="acesso_empresa.php?id=<?= $estabelecimentoId; ?>&delete=true&usuario_id=<?= $usuario['id']; ?><?= (!empty($searchTerm) ? "&search=" . urlencode($searchTerm) : "") ?>"
                                                class="btn btn-outline-danger btn-sm py-0 px-1"
                                                onclick="return confirm('Tem certeza que deseja desvincular este usuário?')"
                                                title="Desvincular Usuário">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Nenhum usuário vinculado a este estabelecimento.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
</div>

<?php include '../footer.php'; ?>
</body>

</html>