<?php
session_start();

require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../models/Estabelecimento.php';

// Verificação de autenticação e nível de acesso PRIMEIRO
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

$processo = new Processo($conn);
$estabelecimento = new Estabelecimento($conn);

$estabelecimento_id = $_GET['id'] ?? null;
// $mensagemErro e $mensagemSucesso são substituídos pelas variáveis de sessão
// $mensagemErro = ''; 
// $mensagemSucesso = '';

if (!$estabelecimento_id) {
    $_SESSION['error_message'] = "ID do estabelecimento não fornecido!";
    header("Location: ../Estabelecimento/listar_estabelecimentos.php");
    exit();
}

$dadosEstabelecimento = $estabelecimento->findById($estabelecimento_id);

if (!$dadosEstabelecimento) {
    $_SESSION['error_message'] = "Estabelecimento não encontrado!";
    header("Location: ../Estabelecimento/listar_estabelecimentos.php");
    exit();
}

// --- Processamento de Formulários POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $redirectUrl = "processos.php?id=$estabelecimento_id";

    try {
        if (isset($_POST['tipo_processo'])) {
            $tipo_processo = $_POST['tipo_processo'];
            $anoAtual = date('Y');
            $ano_licenciamento = null;

            // Se for um processo de licenciamento, verifica se já existe para o ano de licenciamento especificado
            if (strtoupper($tipo_processo) == 'LICENCIAMENTO') {
                if (isset($_POST['ano_licenciamento'])) {
                    $ano_licenciamento = intval($_POST['ano_licenciamento']);
                } else {
                    $ano_licenciamento = $anoAtual;
                }

                if ($processo->checkProcessoExistente($estabelecimento_id, $anoAtual, $ano_licenciamento)) {
                    $_SESSION['error_message'] = "Já existe um processo de LICENCIAMENTO para o ano $ano_licenciamento.";
                    header("Location: $redirectUrl");
                    exit();
                }
            }

            // Cria o processo com o ano de licenciamento, se aplicável
            if ($processo->createProcesso($estabelecimento_id, $tipo_processo, $ano_licenciamento)) {
                $_SESSION['success_message'] = "Processo '$tipo_processo' criado com sucesso!";
            } else {
                throw new Exception("Erro ao criar processo: " . $conn->error);
            }
        } elseif (isset($_POST['archive_processo_id'])) { // Lógica mantida, mas sem botão no front
            $archive_processo_id = $_POST['archive_processo_id'];
            if ($processo->archiveProcesso($archive_processo_id)) {
                $_SESSION['success_message'] = "Processo arquivado com sucesso!";
            } else {
                throw new Exception("Erro ao arquivar processo: " . $conn->error);
            }
        } elseif (isset($_POST['unarchive_processo_id'])) { // Lógica mantida
            $unarchive_processo_id = $_POST['unarchive_processo_id'];
            if ($processo->unarchiveProcesso($unarchive_processo_id)) {
                $_SESSION['success_message'] = "Processo desarquivado com sucesso!";
            } else {
                throw new Exception("Erro ao desarquivar processo: " . $conn->error);
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header("Location: $redirectUrl");
    exit();
}
// --- Fim Processamento POST ---


$processos = $processo->getProcessosByEstabelecimento($estabelecimento_id);
$isPessoaFisica = ($dadosEstabelecimento['tipo_pessoa'] ?? 'juridica') === 'fisica'; // Default para juridica se nulo


// Obter e limpar mensagens da sessão ANTES de incluir header
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Incluir Header AGORA
include '../header.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processos - <?= htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? $dadosEstabelecimento['razao_social'] ?? 'N/A') ?></title>
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
                    <a href="../Estabelecimento/<?= $isPessoaFisica ? 'detalhes_pessoa_fisica.php' : 'detalhes_estabelecimento.php'; ?>?id=<?= $estabelecimento_id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'detalhes_estabelecimento.php' || $currentPage == 'detalhes_pessoa_fisica.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'detalhes_estabelecimento.php' || $currentPage == 'detalhes_pessoa_fisica.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Detalhes</span>
                    </a>
                    <a href="../Estabelecimento/<?= $isPessoaFisica ? 'editar_pessoa_fisica.php' : 'editar_estabelecimento.php'; ?>?id=<?= $estabelecimento_id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'editar_estabelecimento.php' || $currentPage == 'editar_pessoa_fisica.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'editar_estabelecimento.php' || $currentPage == 'editar_pessoa_fisica.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        <span class="text-sm">Editar</span>
                    </a>
                    <?php if (!$isPessoaFisica) : ?>
                    <a href="../Estabelecimento/atividades.php?id=<?= $estabelecimento_id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'atividades.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'atividades.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Atividades (CNAE)</span>
                    </a>
                    <a href="../Estabelecimento/responsaveis.php?id=<?= $estabelecimento_id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'responsaveis.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'responsaveis.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                        </svg>
                        <span class="text-sm">Responsáveis</span>
                    </a>
                    <?php endif; ?>
                    <a href="../Estabelecimento/acesso_empresa.php?id=<?= $estabelecimento_id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'acesso_empresa.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'acesso_empresa.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 00-1-1H7a1 1 0 00-1 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Acesso Empresa</span>
                    </a>
                    <a href="processos.php?id=<?= $estabelecimento_id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'processos.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'processos.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1H8a3 3 0 00-3 3v1.5a1.5 1.5 0 01-3 0V6z" clip-rule="evenodd" />
                            <path d="M6 12a2 2 0 012-2h8a2 2 0 012 2v2a2 2 0 01-2 2H2h2a2 2 0 002-2v-2z" />
                        </svg>
                        <span class="text-sm">Processos</span>
                    </a>
                </div>
                <div class="p-4 bg-gray-50 border-t border-gray-100">
                    <a href="../Estabelecimento/<?= $isPessoaFisica ? 'detalhes_pessoa_fisica.php' : 'detalhes_estabelecimento.php'; ?>?id=<?= $estabelecimento_id; ?>" class="flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
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
                        <button type="button" class="absolute top-0 right-0 mt-4 mr-4 text-green-700 hover:text-green-900" onclick="this.parentElement.style.display='none';" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
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
                        <button type="button" class="absolute top-0 right-0 mt-4 mr-4 text-red-700 hover:text-red-900" onclick="this.parentElement.style.display='none';" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <h5 class="font-medium text-xl mb-6 text-gray-800 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 4a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2H3zm0 2h14v10H3V6zm2 4a1 1 0 00-1 1v2a1 1 0 102 0v-2a1 1 0 00-1-1zm4 0a1 1 0 00-1 1v2a1 1 0 102 0v-2a1 1 0 00-1-1zm4 0a1 1 0 00-1 1v2a1 1 0 102 0v-2a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                Processos: <span class="text-blue-600 ml-2"><?= htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? ($dadosEstabelecimento['nome'] ?? $dadosEstabelecimento['razao_social'] ?? 'N/A')); ?></span>
            </h5>

            <div class="bg-white rounded-lg shadow-md p-6 mb-6 border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h6 class="text-lg font-semibold text-gray-700 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm6 4a1 1 0 100 2h4a1 1 0 100-2H9z" clip-rule="evenodd" />
                        </svg>
                        Lista de Processos
                    </h6>
                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow transition-colors duration-200 flex items-center" onclick="openModal('criarProcessoModal')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" />
                        </svg>
                        Criar Novo Processo
                    </button>
                </div>
                
                <?php if (empty($processos)) : ?>
                    <p class="text-gray-500 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Nenhum processo encontrado para este estabelecimento.
                    </p>
                <?php else : ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($processos as $proc) : ?>
                            <div class="bg-gray-50 rounded-lg shadow-sm border border-gray-200 flex flex-col hover:shadow-md transition-shadow duration-200">
                                <div class="p-3 flex flex-col flex-grow">
                                    <h6 class="text-center text-blue-700 font-semibold mb-2 text-sm" title="Número do Processo">
                                        <i class="fas fa-hashtag fa-xs"></i> <?= htmlspecialchars($proc['numero_processo']); ?>
                                    </h6>
                                    <p class="text-sm text-gray-600 mb-1 flex-grow">
                                        <span class="font-medium text-gray-500 block">Tipo:</span> <?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $proc['tipo_processo'])))); ?><br>
                                        <span class="font-medium text-gray-500 block">Autuação:</span> <?= !empty($proc['data_abertura']) ? (new DateTime($proc['data_abertura']))->format('d/m/Y') : 'N/A'; ?>
                                        <?php if ($proc['tipo_processo'] == 'LICENCIAMENTO' && !empty($proc['ano_licenciamento'])): ?>
                                            <br><span class="font-medium text-gray-500 block">Ano de Licenciamento:</span> <?= htmlspecialchars($proc['ano_licenciamento']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="mt-3">
                                        <a href="documentos.php?processo_id=<?= $proc['id']; ?>&id=<?= $estabelecimento_id; ?>" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-3 rounded-md text-center text-sm transition-colors duration-200 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414L9 11.586 6.707 9.293z" clip-rule="evenodd" />
                                            </svg>
                                            Ver Processo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="criarProcessoModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3 border-b border-gray-200">
            <h5 class="text-lg font-medium text-gray-800 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" />
                </svg>
                Criar Novo Processo
            </h5>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('criarProcessoModal')" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="mt-4">
            <form action="processos.php?id=<?= $estabelecimento_id; ?>" method="POST">
                <div class="mb-4">
                    <label for="tipo_processo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Processo <span class="text-red-500">*</span></label>
                    <select id="tipo_processo" name="tipo_processo" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm" required>
                        <option value="" selected disabled>Selecione...</option>
                        <option value="ADMINISTRATIVO">ADMINISTRATIVO</option>
                        <option value="AÇÕES DE ROTINA">AÇÕES DE ROTINA</option>
                        <option value="DENÚNCIA">DENÚNCIA</option>
                        <option value="LICENCIAMENTO">LICENCIAMENTO</option>
                        <option value="PROJETO ARQUITETÔNICO">PROJETO ARQUITETÔNICO</option>
                        <option value="OUTROS">OUTROS</option>
                    </select>
                </div>

                <div class="mb-4" id="ano_licenciamento_container" style="display: none;">
                    <label for="ano_licenciamento" class="block text-sm font-medium text-gray-700 mb-1">Ano de Licenciamento <span class="text-red-500">*</span></label>
                    <select id="ano_licenciamento" name="ano_licenciamento" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <?php
                        $anoAtual = date('Y');
                        $anoAnterior = $anoAtual - 1;
                        $anoProximo = $anoAtual + 1;
                        ?>
                        <option value="<?= $anoAnterior ?>"><?= $anoAnterior ?> (Ano Anterior)</option>
                        <option value="<?= $anoAtual ?>" selected><?= $anoAtual ?> (Ano Atual)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Selecione o ano para o qual este licenciamento se aplica. Você pode criar processos para anos diferentes, mesmo que já exista um processo de licenciamento para o estabelecimento no ano atual.</p>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-200">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md shadow mr-2 transition-colors duration-200" onclick="closeModal('criarProcessoModal')">Cancelar</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow transition-colors duration-200 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" />
                        </svg>
                        Criar Processo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tipoProcessoSelect = document.getElementById('tipo_processo');
        const anoLicenciamentoContainer = document.getElementById('ano_licenciamento_container');
        const anoLicenciamentoSelect = document.getElementById('ano_licenciamento');

        tipoProcessoSelect.addEventListener('change', function() {
            if (this.value === 'LICENCIAMENTO') {
                anoLicenciamentoContainer.style.display = 'block';
                anoLicenciamentoSelect.setAttribute('required', 'required');
            } else {
                anoLicenciamentoContainer.style.display = 'none';
                anoLicenciamentoSelect.removeAttribute('required');
            }
        });

        // Funções para abrir e fechar o modal
        window.openModal = function(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        window.closeModal = function(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Fechar modal ao clicar fora dele
        const modal = document.getElementById('criarProcessoModal');
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal('criarProcessoModal');
            }
        });
    });
</script>

<?php
$conn->close();
include '../footer.php';
?>
</body>

</html>