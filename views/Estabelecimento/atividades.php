<?php
session_start();
// O include '../header.php' será movido para depois do processamento das mensagens de sessão

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

$estabelecimento = new Estabelecimento($conn);

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "ID do estabelecimento não fornecido!";
    header("Location: listar_estabelecimentos.php");
    exit();
}

$id = $_GET['id'];
$dadosEstabelecimento = $estabelecimento->findById($id);

if (!$dadosEstabelecimento) {
    $_SESSION['error_message'] = "Estabelecimento não encontrado!";
    header("Location: listar_estabelecimentos.php");
    exit();
}

$cnaes_secundarios = json_decode($dadosEstabelecimento['cnaes_secundarios'] ?? '[]', true);

// Função Auxiliar
function formatCNAE($cnae)
{
    if (empty($cnae)) return 'N/A';
    $cnae = preg_replace('/\D/', '', $cnae);
    if (strlen($cnae) === 7) {
        return substr($cnae, 0, 4) . '-' . substr($cnae, 4, 1) . '/' . substr($cnae, 5, 2);
    }
    return htmlspecialchars($cnae); // Retorna formatado se não for padrão
}
// --- Fim Função Auxiliar ---

// Verificar se é uma empresa ou pessoa física
$isPessoaFisica = ($dadosEstabelecimento['tipo_pessoa'] ?? '') === 'fisica';
$nomeExibicao = $isPessoaFisica 
    ? htmlspecialchars($dadosEstabelecimento['nome'] ?? 'N/A') 
    : htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? $dadosEstabelecimento['razao_social'] ?? 'N/A');

// Tratar mensagens da sessão
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Incluir Header APÓS o processamento das mensagens
include '../header.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atividades (CNAE) - <?= $nomeExibicao ?></title>
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
                    <a href="<?= $isPessoaFisica ? 'detalhes_pessoa_fisica.php' : 'detalhes_estabelecimento.php'; ?>?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'detalhes_estabelecimento.php' || $currentPage == 'detalhes_pessoa_fisica.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'detalhes_estabelecimento.php' || $currentPage == 'detalhes_pessoa_fisica.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Detalhes</span>
                    </a>
                    <a href="<?= $isPessoaFisica ? 'editar_pessoa_fisica.php' : 'editar_estabelecimento.php'; ?>?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'editar_estabelecimento.php' || $currentPage == 'editar_pessoa_fisica.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'editar_estabelecimento.php' || $currentPage == 'editar_pessoa_fisica.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        <span class="text-sm">Editar</span>
                    </a>
                    <?php if (!$isPessoaFisica) : ?>
                    <a href="atividades.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'atividades.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'atividades.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Atividades (CNAE)</span>
                    </a>
                    <a href="responsaveis.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'responsaveis.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'responsaveis.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                        </svg>
                        <span class="text-sm">Responsáveis</span>
                    </a>
                    <?php endif; ?>
                    <a href="acesso_empresa.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'acesso_empresa.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'acesso_empresa.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 00-1-1H7a1 1 0 00-1 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Acesso Empresa</span>
                    </a>
                    <a href="../Processo/processos.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'processos.php' || $currentPage == 'documentos.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'processos.php' || $currentPage == 'documentos.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1H8a3 3 0 00-3 3v1.5a1.5 1.5 0 01-3 0V6z" clip-rule="evenodd" />
                            <path d="M6 12a2 2 0 012-2h8a2 2 0 012 2v2a2 2 0 01-2 2H2h2a2 2 0 002-2v-2z" />
                        </svg>
                        <span class="text-sm">Processos</span>
                    </a>
                </div>
                <div class="p-4 bg-gray-50 border-t border-gray-100">
                    <a href="<?= $isPessoaFisica ? 'detalhes_pessoa_fisica.php' : 'detalhes_estabelecimento.php'; ?>?id=<?= $id; ?>" class="flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
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
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                </svg>
                Atividades (CNAE): <span class="text-blue-600 ml-2"><?= $nomeExibicao ?></span>
            </h5>

            <div class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-gray-700 font-medium flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                            </svg>
                            Informações do Estabelecimento
                        </h3>
                        <?php if ($isPessoaFisica): ?>
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">Pessoa Física</span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-medium rounded-full">Pessoa Jurídica</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if ($isPessoaFisica): ?>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Nome</p>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($dadosEstabelecimento['nome'] ?? 'N/A') ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">CPF</p>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($dadosEstabelecimento['cpf'] ?? 'N/A') ?></p>
                            </div>
                        <?php else: ?>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Nome Fantasia</p>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? 'N/A') ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">CNPJ</p>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($dadosEstabelecimento['cnpj'] ?? 'N/A') ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3 border-b border-blue-700">
                    <h3 class="text-white font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd" />
                        </svg>
                        CNAE Principal
                    </h3>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Código CNAE</p>
                            <span class="inline-block font-mono px-2 py-1 text-sm font-semibold rounded-md bg-blue-100 text-blue-800"><?= formatCNAE(htmlspecialchars($dadosEstabelecimento['cnae_fiscal'] ?? '')); ?></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Descrição</p>
                            <p class="text-gray-800"><?= htmlspecialchars($dadosEstabelecimento['cnae_fiscal_descricao'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3 border-b border-blue-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-white font-medium flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                            </svg>
                            CNAEs Secundários
                        </h3>
                        <span class="px-3 py-1 bg-white text-blue-800 text-xs font-medium rounded-full"><?= count($cnaes_secundarios) ?> CNAEs</span>
                    </div>
                </div>

                <?php if (!empty($cnaes_secundarios)): ?>
                <div class="p-4 border-b border-gray-200">
                    <label for="searchCNAE" class="block text-sm font-medium text-gray-700 mb-1">Buscar CNAEs</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" id="searchCNAE" class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" placeholder="Buscar por código ou descrição...">
                    </div>
                    <p id="searchResults" class="mt-2 text-sm text-gray-500 hidden"></p>
                </div>

                <div class="divide-y divide-gray-200" id="cnaesContainer">
                    <?php foreach ($cnaes_secundarios as $index => $cnae) : ?>
                        <div class="p-4 cnae-item hover:bg-gray-50 transition-colors duration-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">Código CNAE</p>
                                    <span class="inline-block font-mono px-2 py-1 text-sm font-semibold rounded-md bg-blue-100 text-blue-800 cnae-codigo"><?= formatCNAE(htmlspecialchars($cnae['codigo'] ?? '')); ?></span>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">Descrição</p>
                                    <p class="text-gray-800 cnae-descricao"><?= htmlspecialchars($cnae['descricao'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="p-8 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum CNAE secundário encontrado</h3>
                    <p class="text-gray-500">Este estabelecimento não possui atividades econômicas secundárias registradas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($cnaes_secundarios)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchCNAE');
    const resultsInfo = document.getElementById('searchResults');
    const cnaeItems = document.querySelectorAll('.cnae-item');
    
    // Função para destacar texto encontrado
    function highlightText(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        // Usando a classe `bg-yellow-200` do Tailwind para o destaque
        return text.replace(regex, '<span class="bg-yellow-200 rounded-sm">$1</span>');
    }
    
    // Função para pesquisar CNAEs
    function searchCNAEs(query) {
        query = query.toLowerCase().trim();
        let matchCount = 0;
        
        cnaeItems.forEach(item => {
            // Restaurar texto original antes de pesquisar para remover highlights anteriores
            const originalCodigo = item.querySelector('.cnae-codigo').dataset.originalText;
            const originalDescricao = item.querySelector('.cnae-descricao').dataset.originalText;

            item.querySelector('.cnae-codigo').innerHTML = originalCodigo;
            item.querySelector('.cnae-descricao').innerHTML = originalDescricao;

            const codigo = originalCodigo.toLowerCase();
            const descricao = originalDescricao.toLowerCase();
            
            if (codigo.includes(query) || descricao.includes(query)) {
                // Destacar texto encontrado
                if (query) {
                    const codigoElem = item.querySelector('.cnae-codigo');
                    const descricaoElem = item.querySelector('.cnae-descricao');
                    
                    codigoElem.innerHTML = highlightText(codigoElem.textContent, query);
                    descricaoElem.innerHTML = highlightText(descricaoElem.textContent, query);
                }
                
                item.classList.remove('hidden');
                matchCount++;
            } else {
                item.classList.add('hidden');
            }
        });
        
        // Atualizar mensagem de resultados
        if (query) {
            resultsInfo.textContent = matchCount === 0 ? 
                'Nenhum resultado encontrado.' : 
                `Encontrados ${matchCount} resultado(s) para "${query}"`;
            resultsInfo.classList.remove('hidden');
        } else {
            resultsInfo.classList.add('hidden');
        }
    }
    
    // Armazenar o texto original para restauração
    cnaeItems.forEach(item => {
        item.querySelector('.cnae-codigo').dataset.originalText = item.querySelector('.cnae-codigo').textContent;
        item.querySelector('.cnae-descricao').dataset.originalText = item.querySelector('.cnae-descricao').textContent;
    });

    // Event listener para o campo de busca
    searchInput.addEventListener('input', function() {
        searchCNAEs(this.value);
    });
});
</script>
<?php endif; ?>

<?php
$conn->close();
include '../footer.php';
?>
</body>

</html>