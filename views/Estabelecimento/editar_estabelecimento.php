<?php
session_start();
include '../header.php';

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

$qsa = json_decode($dadosEstabelecimento['qsa'] ?? '[]', true);
$cnaes_secundarios = json_decode($dadosEstabelecimento['cnaes_secundarios'] ?? '[]', true);

// --- Funções Auxiliares ---
function setDefaultValue($value, $defaultValue = "")
{ // Default vazio para inputs
    return $value && $value !== "Não Informado" ? htmlspecialchars($value) : $defaultValue;
}

function formatCNAE($cnae)
{
    if (empty($cnae)) return '';
    $cnae = preg_replace('/\D/', '', $cnae); // Remove não dígitos
    if (strlen($cnae) === 7) {
        return substr($cnae, 0, 4) . '-' . substr($cnae, 4, 1) . '/' . substr($cnae, 5, 2);
    }
    return htmlspecialchars($cnae); // Retorna original formatado se não for padrão
}
// --- Fim Funções Auxiliares ---

// Tratar mensagens da sessão
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

?>
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
                    <a href="detalhes_estabelecimento.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Detalhes</span>
                    </a>
                    <a href="editar_estabelecimento.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'editar_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'editar_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        <span class="text-sm">Editar</span>
                    </a>
                    <a href="atividades.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'atividades.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'atividades.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Atividades (CNAE)</span>
                    </a>
                    <a href="responsaveis.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'responsaveis.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'responsaveis.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                        </svg>
                        <span class="text-sm">Responsáveis (QSA)</span>
                    </a>
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
                    <a href="detalhes_estabelecimento.php?id=<?= $id; ?>" class="flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
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

            <form id="editarEstabelecimentoForm" action="../../controllers/EstabelecimentoController.php?action=update&id=<?= $id; ?>" method="POST">

                <div class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3 border-b border-blue-700">
                        <h3 class="text-white font-medium flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 00-1-1H7a1 1 0 00-1 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                            </svg>
                            Dados Principais
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="mb-4">
                            <label for="cnpj" class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                            <div class="flex">
                                <input type="text" class="flex-grow rounded-l-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="cnpj" name="cnpj" value="<?= htmlspecialchars($dadosEstabelecimento['cnpj']); ?>" readonly aria-label="CNPJ">
                                <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" id="atualizarInformacoes" title="Buscar dados atualizados da Receita Federal (via API externa)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                    </svg>
                                    Atualizar via API
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">O CNPJ não pode ser editado. Use o botão para buscar dados atualizados da Receita.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="nome_fantasia" class="block text-sm font-medium text-gray-700 mb-1">Nome Fantasia</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm uppercase" id="nome_fantasia" name="nome_fantasia" value="<?= setDefaultValue($dadosEstabelecimento['nome_fantasia']); ?>" required>
                            </div>
                            <div>
                                <label for="razao_social" class="block text-sm font-medium text-gray-700 mb-1">Razão Social</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm uppercase" id="razao_social" name="razao_social" value="<?= setDefaultValue($dadosEstabelecimento['razao_social']); ?>" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
                            <div class="md:col-span-3">
                                <label for="descricao_situacao_cadastral" class="block text-sm font-medium text-gray-700 mb-1">Situação Cadastral (Receita)</label>
                                <div class="relative">
                                    <input type="text" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 focus:ring-blue-500 focus:border-blue-500 sm:text-sm uppercase" id="descricao_situacao_cadastral" name="descricao_situacao_cadastral" value="<?= setDefaultValue($dadosEstabelecimento['descricao_situacao_cadastral']); ?>" readonly>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-1.5">
                                <label for="data_situacao_cadastral" class="block text-sm font-medium text-gray-700 mb-1">Data Situação</label>
                                <input type="date" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="data_situacao_cadastral" name="data_situacao_cadastral" value="<?= setDefaultValue($dadosEstabelecimento['data_situacao_cadastral'], date('Y-m-d')); ?>" required>
                            </div>
                            <div class="md:col-span-1.5">
                                <label for="data_inicio_atividade" class="block text-sm font-medium text-gray-700 mb-1">Início Atividade</label>
                                <input type="date" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="data_inicio_atividade" name="data_inicio_atividade" value="<?= setDefaultValue($dadosEstabelecimento['data_inicio_atividade']); ?>" required>
                            </div>
                        </div>
                        </div>
                    </div>

                <div class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                    <div class="bg-gradient-to-r from-green-600 to-green-700 px-4 py-3 border-b border-green-700">
                        <h3 class="text-white font-medium flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                            </svg>
                            Endereço
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                            <div class="md:col-span-4">
                                <label for="cep" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm" id="cep" name="cep" value="<?= setDefaultValue($dadosEstabelecimento['cep']); ?>" required pattern="\d{5}-?\d{3}" placeholder="00000-000" title="Formato: 00000-000">
                            </div>
                            <div class="md:col-span-8">
                                <label for="descricao_tipo_de_logradouro" class="block text-sm font-medium text-gray-700 mb-1">Tipo Logradouro</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="descricao_tipo_de_logradouro" name="descricao_tipo_de_logradouro" value="<?= setDefaultValue($dadosEstabelecimento['descricao_tipo_de_logradouro']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                            <div class="md:col-span-9">
                                <label for="logradouro" class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="logradouro" name="logradouro" value="<?= setDefaultValue($dadosEstabelecimento['logradouro']); ?>" required>
                            </div>
                            <div class="md:col-span-3">
                                <label for="numero" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="numero" name="numero" value="<?= setDefaultValue($dadosEstabelecimento['numero']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="complemento" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="complemento" name="complemento" value="<?= setDefaultValue($dadosEstabelecimento['complemento']); ?>">
                            </div>
                            <div>
                                <label for="bairro" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="bairro" name="bairro" value="<?= setDefaultValue($dadosEstabelecimento['bairro']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                            <div class="md:col-span-6">
                                <label for="municipio" class="block text-sm font-medium text-gray-700 mb-1">Município</label>
                                <div class="relative">
                                    <input type="text" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="municipio" name="municipio" value="<?= setDefaultValue($dadosEstabelecimento['municipio']); ?>" readonly>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label for="uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="uf" name="uf" value="<?= setDefaultValue($dadosEstabelecimento['uf']); ?>" required maxlength="2">
                            </div>
                            <div class="md:col-span-4">
                                <label for="natureza_juridica" class="block text-sm font-medium text-gray-700 mb-1">Natureza Jurídica</label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm uppercase" id="natureza_juridica" name="natureza_juridica" value="<?= setDefaultValue($dadosEstabelecimento['natureza_juridica']); ?>">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="ddd_telefone_1" class="block text-sm font-medium text-gray-700 mb-1">Telefone 1</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                        </svg>
                                    </div>
                                    <input type="tel" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm pl-10" id="ddd_telefone_1" name="ddd_telefone_1" value="<?= setDefaultValue($dadosEstabelecimento['ddd_telefone_1']); ?>" placeholder="(00) 0000-0000">
                                </div>
                            </div>
                            <div>
                                <label for="ddd_telefone_2" class="block text-sm font-medium text-gray-700 mb-1">Telefone 2</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                        </svg>
                                    </div>
                                    <input type="tel" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm pl-10" id="ddd_telefone_2" name="ddd_telefone_2" value="<?= setDefaultValue($dadosEstabelecimento['ddd_telefone_2']); ?>" placeholder="(00) 00000-0000">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-700 px-4 py-3 border-b border-indigo-700">
                        <h3 class="text-white font-medium flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd" />
                                <path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z" />
                            </svg>
                            Atividades (CNAE) e Sociedade (QSA)
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded-r flex items-start mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-sm text-blue-700">
                                Os dados de CNAE e Sociedade são atualizados apenas via API (botão azul acima) e não são editáveis manualmente aqui.
                            </span>
                        </div>

                        <div class="mb-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-indigo-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                CNAE Principal
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-3">
                                <div class="md:col-span-4">
                                    <label for="cnae_fiscal" class="sr-only">Código CNAE Fiscal</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 pl-10 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="cnae_fiscal" name="cnae_fiscal" value="<?= formatCNAE(htmlspecialchars($dadosEstabelecimento['cnae_fiscal'])); ?>" readonly placeholder="Código">
                                    </div>
                                </div>
                                <div class="md:col-span-8">
                                    <label for="cnae_fiscal_descricao" class="sr-only">Descrição CNAE Fiscal</label>
                                    <input type="text" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="cnae_fiscal_descricao" name="cnae_fiscal_descricao" value="<?= htmlspecialchars($dadosEstabelecimento['cnae_fiscal_descricao']); ?>" readonly placeholder="Descrição">
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-indigo-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                </svg>
                                CNAEs Secundários
                            </h4>
                            <div id="cnaesSecundariosContainer" class="space-y-2">
                                <?php if (empty($cnaes_secundarios)): ?>
                                    <p class="text-sm text-gray-500 italic">Nenhum CNAE secundário informado.</p>
                                <?php else: ?>
                                    <?php foreach ($cnaes_secundarios as $index => $cnae) : ?>
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 p-2 rounded-md bg-gray-50 border border-gray-100">
                                            <div class="md:col-span-4">
                                                <label for="cnae_sec_cod_<?= $index ?>" class="sr-only">Código CNAE Secundário <?= $index + 1 ?></label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <input type="text" id="cnae_sec_cod_<?= $index ?>" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 pl-10 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?= formatCNAE(htmlspecialchars($cnae['codigo'] ?? '')); ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="md:col-span-8">
                                                <label for="cnae_sec_desc_<?= $index ?>" class="sr-only">Descrição CNAE Secundário <?= $index + 1 ?></label>
                                                <input type="text" id="cnae_sec_desc_<?= $index ?>" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?= htmlspecialchars($cnae['descricao'] ?? ''); ?>" readonly>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-indigo-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                </svg>
                                Sociedade (QSA)
                            </h4>
                            <div id="qsaContainer" class="space-y-2">
                                <?php if (empty($qsa)): ?>
                                    <p class="text-sm text-gray-500 italic">Nenhum sócio/administrador informado.</p>
                                <?php else: ?>
                                    <?php foreach ($qsa as $index => $socio) : ?>
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 p-2 rounded-md bg-gray-50 border border-gray-100">
                                            <div class="md:col-span-7">
                                                <label for="qsa_nome_<?= $index ?>" class="sr-only">Nome Sócio/Administrador <?= $index + 1 ?></label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <input type="text" id="qsa_nome_<?= $index ?>" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 pl-10 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?= htmlspecialchars($socio['nome_socio'] ?? ''); ?>" readonly placeholder="Nome">
                                                </div>
                                            </div>
                                            <div class="md:col-span-5">
                                                <label for="qsa_qual_<?= $index ?>" class="sr-only">Qualificação Sócio/Administrador <?= $index + 1 ?></label>
                                                <input type="text" id="qsa_qual_<?= $index ?>" class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?= htmlspecialchars($socio['qualificacao_socio'] ?? ''); ?>" readonly placeholder="Qualificação">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
        
    
                <div class="flex justify-end space-x-3 mt-6 mb-8">
                    <a href="detalhes_estabelecimento.php?id=<?= $id; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Salvar Alterações
                    </button>
                </div>

                </form>
            </div>
        </div>
    </div>

    <script>
        // Função para formatar CNAE no JS (igual ao PHP)
        function formatCNAE_JS(cnae) {
            if (!cnae) return '';
            cnae = cnae.toString().replace(/\D/g, '');
            if (cnae.length === 7) {
                return cnae.substring(0, 4) + '-' + cnae.substring(4, 5) + '/' + cnae.substring(5, 7);
            }
            return cnae;
        }

        $(document).ready(function() {
            const alertContainer = $('#alertContainer');

            function showAlert(message, type = 'success') {
                let alertClass, iconSvg;
                
                if (type === 'success') {
                    alertClass = 'bg-green-100 border-l-4 border-green-500 text-green-700';
                    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>`;
                } else if (type === 'danger' || type === 'error') {
                    alertClass = 'bg-red-100 border-l-4 border-red-500 text-red-700';
                    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>`;
                } else if (type === 'warning') {
                    alertClass = 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700';
                    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>`;
                } else { // info
                    alertClass = 'bg-blue-100 border-l-4 border-blue-500 text-blue-700';
                    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>`;
                }
                
                const alertHtml = `
                <div class="${alertClass} p-4 mb-4 rounded shadow-sm relative" role="alert">
                    <div class="flex items-center">
                        ${iconSvg}
                        <span class="ml-2">${message}</span>
                    </div>
                    <button type="button" class="absolute top-1 right-1 text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 inline-flex h-8 w-8 ml-auto" data-dismiss="alert" aria-label="Close">
                        <span class="sr-only">Fechar</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>`;
                alertContainer.html(alertHtml); // Substitui alertas anteriores
                
                // Adiciona evento para fechar o alerta
                alertContainer.find('[data-dismiss="alert"]').on('click', function() {
                    $(this).closest('[role="alert"]').remove();
                });
            }


            $('#atualizarInformacoes').on('click', function() {
                var cnpj = $('#cnpj').val().replace(/\D/g, '');
                var $button = $(this);
                var originalHtml = $button.html(); // Salva o conteúdo original

                if (cnpj.length !== 14) {
                    showAlert('Por favor, insira um CNPJ válido.', 'danger');
                    return;
                }

                // Mostra estado de carregamento
                $button.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Buscando...').prop('disabled', true);

                $.ajax({
                    url: 'https://govnex.site/govnex/api/cnpj_api.php', // URL da sua API intermediária ou direta
                    method: 'GET',
                    data: {
                        cnpj: cnpj,
                        // Se sua API precisar de token, adicione aqui
                        token: '8ab984d986b155d84b4f88dec6d4f8c3cd2e11c685d9805107df78e94ab488ca'
                    },
                    dataType: 'json', // Espera JSON como resposta
                    success: function(data) {
                        if (data.status === "ERROR" || data.status === 404 || data.error || !data.razao_social) {
                            showAlert(`Erro ao buscar dados para o CNPJ ${cnpj}: ${data.message || 'Dados não encontrados ou inválidos.'}`, 'danger');
                            console.error("API Error/Not Found:", data);
                            return; // Interrompe aqui
                        }

                        if (data.status === "OK" || data.status === 200 || data.razao_social) { // Verifica se a resposta é válida
                            showAlert('Dados atualizados com sucesso via API!', 'success');

                            // Atualiza campos editáveis que vêm da API (se houver)
                            $('#nome_fantasia').val(data.nome_fantasia || '');
                            $('#razao_social').val(data.razao_social || '');
                            $('#data_situacao_cadastral').val(data.data_situacao_cadastral || '');
                            $('#data_inicio_atividade').val(data.data_inicio_atividade || '');
                            $('#descricao_tipo_de_logradouro').val(data.descricao_tipo_de_logradouro || '');
                            $('#logradouro').val(data.logradouro || '');
                            $('#numero').val(data.numero || '');
                            $('#complemento').val(data.complemento || '');
                            $('#bairro').val(data.bairro || '');
                            $('#cep').val(data.cep || '');
                            $('#uf').val(data.uf || '');
                            // Municipio é readonly, mas atualizamos o valor se vier da API
                            $('#municipio').val(data.municipio || '');
                            $('#ddd_telefone_1').val(data.ddd_telefone_1 || '');
                            $('#ddd_telefone_2').val(data.ddd_telefone_2 || '');
                            $('#natureza_juridica').val(data.natureza_juridica || '');

                            // Atualiza campos readonly
                            $('#descricao_situacao_cadastral').val(data.descricao_situacao_cadastral || '');
                            $('#cnae_fiscal').val(formatCNAE_JS(data.cnae_fiscal));
                            $('#cnae_fiscal_descricao').val(data.cnae_fiscal_descricao || '');

                            // Atualizar CNAEs Secundários
                            var cnaesSecContainer = $('#cnaesSecundariosContainer');
                            cnaesSecContainer.empty(); // Limpa container
                            if (data.cnaes_secundarios && data.cnaes_secundarios.length > 0) {
                                $.each(data.cnaes_secundarios, function(index, cnae) {
                                    const cnaeHtml = `
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                         <label for="cnae_sec_cod_${index}" class="form-label form-label-sm visually-hidden">Código CNAE Secundário ${index + 1}</label>
                                         <input type="text" id="cnae_sec_cod_${index}" class="form-control form-control-sm bg-light" value="${formatCNAE_JS(cnae.codigo)}" readonly>
                                    </div>
                                    <div class="col-md-8">
                                         <label for="cnae_sec_desc_${index}" class="form-label form-label-sm visually-hidden">Descrição CNAE Secundário ${index + 1}</label>
                                         <input type="text" id="cnae_sec_desc_${index}" class="form-control form-control-sm bg-light" value="${cnae.descricao || ''}" readonly>
                                    </div>
                                </div>`;
                                    cnaesSecContainer.append(cnaeHtml);
                                });
                            } else {
                                cnaesSecContainer.html('<p class="text-muted small mb-3">Nenhum CNAE secundário informado.</p>');
                            }

                            // Atualizar QSA
                            var qsaContainer = $('#qsaContainer');
                            qsaContainer.empty(); // Limpa container
                            if (data.qsa && data.qsa.length > 0) {
                                $.each(data.qsa, function(index, socio) {
                                    const qsaHtml = `
                                <div class="row g-2 mb-2">
                                    <div class="col-md-7">
                                         <label for="qsa_nome_${index}" class="form-label form-label-sm visually-hidden">Nome Sócio ${index + 1}</label>
                                         <input type="text" id="qsa_nome_${index}" class="form-control form-control-sm bg-light" value="${socio.nome_socio || ''}" readonly>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="qsa_qual_${index}" class="form-label form-label-sm visually-hidden">Qualificação Sócio ${index + 1}</label>
                                         <input type="text" id="qsa_qual_${index}" class="form-control form-control-sm bg-light" value="${socio.qualificacao_socio || ''}" readonly>
                                    </div>
                                </div>`;
                                    qsaContainer.append(qsaHtml);
                                });
                            } else {
                                qsaContainer.html('<p class="text-muted small mb-0">Nenhum sócio/administrador informado.</p>');
                            }

                        } else {
                            // Caso a API retorne status OK mas sem dados essenciais, ou formato inesperado
                            showAlert(`Resposta da API recebida, mas dados podem estar incompletos ou em formato inesperado.`, 'warning');
                            console.warn("API Warning/Unexpected Format:", data);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        showAlert('Erro de comunicação ao consultar a API do CNPJ.', 'danger');
                        console.error("AJAX Error:", textStatus, errorThrown);
                    },
                    complete: function() {
                        // Restaura o botão ao estado original
                        $button.html(originalHtml).prop('disabled', false);
                    }
                });
            });

            // Adiciona máscara/validação simples para CEP e Telefone (opcional)
            $('#cep').on('input', function() {
                this.value = this.value.replace(/\D/g, '').replace(/^(\d{5})(\d)/, '$1-$2').substring(0, 9);
            });
            $('#ddd_telefone_1, #ddd_telefone_2').on('input', function() {
                let v = this.value.replace(/\D/g, '');
                v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
                if (v.length > 10) { // Celular
                    v = v.replace(/(\d{5})(\d)/, "$1-$2");
                } else { // Fixo
                    v = v.replace(/(\d{4})(\d)/, "$1-$2");
                }
                this.value = v.substring(0, 15); // Limita tamanho (ex: (XX) XXXXX-XXXX)
            });

        });
    </script>

    <?php
    $conn->close();
    include '../footer.php';
    ?>
</body>

</html>