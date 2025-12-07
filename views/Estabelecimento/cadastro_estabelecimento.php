<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado ou não for administrador
    exit();
}
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <span class="font-medium">Atenção!</span> Basta inserir o CNPJ do estabelecimento e uma API fará a busca automática dos dados. Se o estabelecimento já estiver cadastrado, entre em contato com a Vigilância Sanitária Municipal.
                </p>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success'])) : ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        Estabelecimento cadastrado com sucesso! Aguarde a aprovação do cadastro em até 24 horas.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])) : ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        Erro ao cadastrar estabelecimento: <?php echo htmlspecialchars($_GET['error']); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form id="cadastroEstabelecimentoForm" action="../../controllers/EstabelecimentoController.php?action=register" method="POST">
            <div class="mb-4">
                <label for="cnpj" class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                <input type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="cnpj" name="cnpj" placeholder="Digite o CNPJ do estabelecimento" required>
            </div>
            <div class="mb-4">
                <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" id="consultarCNPJ">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    Consultar
                </button>
            </div>

        <!-- Campos para os dados retornados da consulta à API -->
        <div id="dadosEstabelecimento">
            <!-- Os campos para exibir e editar os dados do estabelecimento serão inseridos aqui pelo JavaScript -->
        </div>

            <!-- Botão Salvar (inicialmente oculto) -->
            <div class="mt-6" id="salvarContainer" style="display: none;">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200" id="salvarEstabelecimento">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Salvar Estabelecimento
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Adicione a biblioteca jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Adicione o Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
<!-- Adicione a biblioteca de máscara -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
    $(document).ready(function() {
        $('#cnpj').mask('00.000.000/0000-00');

        function setDefaultValue(value, defaultValue = "Não Informado") {
            return value ? value : defaultValue;
        }

        $('#consultarCNPJ').on('click', function() {
            var cnpj = $('#cnpj').val().replace(/\D/g, '');
            if (cnpj.length !== 14) {
                alert('Por favor, insira um CNPJ válido.');
                return;
            }

            $.ajax({
                url: '../../api/consultar_cnpj.php',
                method: 'GET',
                data: {
                    cnpj: cnpj
                },
                success: function(data) {
                    // Verificar se houve erro no proxy
                    if (data.error) {
                        // Se for erro de créditos (402), mostrar alerta especial
                        if (data.code === 402) {
                            let debugInfo = '';
                            if (data.debug) {
                                debugInfo = `
                                    <details class="mt-3 bg-gray-100 p-3 rounded">
                                        <summary class="cursor-pointer font-semibold text-gray-700">🔍 Informações Técnicas (Debug)</summary>
                                        <div class="mt-2 text-xs space-y-1">
                                            <p><strong>Token:</strong> ${data.debug.token_prefix}</p>
                                            <p><strong>CNPJ:</strong> ${data.debug.cnpj}</p>
                                            <p><strong>URL:</strong> ${data.debug.url}</p>
                                            <p><strong>Resposta da API:</strong></p>
                                            <pre class="bg-white p-2 rounded overflow-auto max-h-32">${data.debug.raw_response}</pre>
                                        </div>
                                    </details>
                                `;
                            }
                            
                            $('#dadosEstabelecimento').html(`
                                <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-md shadow-lg">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="h-8 w-8 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <h3 class="text-lg font-bold text-red-800 mb-2">
                                                ⚠️ Erro 402 da API GovNex
                                            </h3>
                                            <p class="text-sm text-red-700 mb-3">
                                                ${data.message}
                                            </p>
                                            <div class="bg-white border border-red-200 rounded p-3 text-sm text-gray-700">
                                                <p class="font-semibold mb-2">Possíveis causas:</p>
                                                <ul class="list-disc list-inside space-y-1">
                                                    <li>Token da API inválido ou expirado</li>
                                                    <li>Conta da API suspensa ou inativa</li>
                                                    <li>Problema de configuração na API GovNex</li>
                                                    <li>Saldo de créditos esgotado</li>
                                                </ul>
                                                <p class="font-semibold mt-3 mb-2">O que fazer:</p>
                                                <ol class="list-decimal list-inside space-y-1">
                                                    <li>Verifique o saldo de créditos no dashboard</li>
                                                    <li>Entre em contato com o suporte da GovNex</li>
                                                    <li>Verifique se o token está correto no banco de dados</li>
                                                </ol>
                                            </div>
                                            ${debugInfo}
                                        </div>
                                    </div>
                                </div>
                            `);
                        } else {
                            alert('Erro ao consultar CNPJ: ' + data.message);
                        }
                        return;
                    }
                    if (data.error || !data.razao_social) { // Verifica se há erro ou falta de dados obrigatórios
                        alert('Erro: CNPJ inválido ou dados não encontrados.');
                        return;
                    }

                    // Verifica a situação cadastral
                    if (data.descricao_situacao_cadastral !== 'ATIVA') {
                        alert('Esse estabelecimento não pode ser cadastrado pois a Situação Cadastral não está ATIVA.');
                        return;
                    }

                    // Preencher os campos com os dados recebidos da API
                    var qsaHTML = data.qsa.map(function(socio) {
                        return `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Sócio</label>
                                    <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" value="${setDefaultValue(socio.nome_socio)}" disabled>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Qualificação</label>
                                    <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" value="${setDefaultValue(socio.qualificacao_socio)}" disabled>
                                </div>
                            </div>
                        `;
                    }).join('');

                    var cnaePrincipalHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="cnae_fiscal" class="block text-sm font-medium text-gray-700 mb-1">Código CNAE Fiscal</label>
                                <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm cnae-mask" id="cnae_fiscal" name="cnae_fiscal" value="${setDefaultValue(data.cnae_fiscal)}" readonly>
                            </div>
                            <div>
                                <label for="cnae_fiscal_descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição CNAE Fiscal</label>
                                <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="cnae_fiscal_descricao" name="cnae_fiscal_descricao" value="${setDefaultValue(data.cnae_fiscal_descricao)}" readonly>
                            </div>
                        </div>
                    `;

                    var cnaesHTML = data.cnaes_secundarios.map(function(cnae) {
                        return `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                                    <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm cnae-mask" name="cnaes_secundarios[][codigo]" value="${setDefaultValue(cnae.codigo)}" readonly>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                                    <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" name="cnaes_secundarios[][descricao]" value="${setDefaultValue(cnae.descricao)}" readonly>
                                </div>
                            </div>
                        `;
                    }).join('');

                    var dadosHTML = `
                        <div class="border-b border-gray-200 mb-6">
                            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="estabelecimentoTabs" role="tablist">
                                <li class="mr-2" role="presentation">
                                    <button class="inline-block p-4 border-b-2 border-blue-500 rounded-t-lg text-blue-600 hover:text-blue-800 active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab" aria-controls="dados" aria-selected="true">Dados da Empresa</button>
                                </li>
                                <li class="mr-2" role="presentation">
                                    <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" id="cnae-principal-tab" data-bs-toggle="tab" data-bs-target="#cnae-principal" type="button" role="tab" aria-controls="cnae-principal" aria-selected="false">CNAE Principal</button>
                                </li>
                                <li class="mr-2" role="presentation">
                                    <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" id="cnaes-secundarios-tab" data-bs-toggle="tab" data-bs-target="#cnaes-secundarios" type="button" role="tab" aria-controls="cnaes-secundarios" aria-selected="false">CNAEs Secundários</button>
                                </li>
                                <li class="mr-2" role="presentation">
                                    <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" id="qsa-tab" data-bs-toggle="tab" data-bs-target="#qsa" type="button" role="tab" aria-controls="qsa" aria-selected="false">Responsáveis</button>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-content" id="estabelecimentoTabsContent">
                            <div class="tab-pane fade show active" id="dados" role="tabpanel" aria-labelledby="dados-tab">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="descricao_identificador_matriz_filial" class="block text-sm font-medium text-gray-700 mb-1">Descrição Identificador Matriz/Filial</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="descricao_identificador_matriz_filial" name="descricao_identificador_matriz_filial" value="${setDefaultValue(data.descricao_identificador_matriz_filial)}" readonly required>
                                    </div>
                                    <div>
                                        <label for="nome_fantasia" class="block text-sm font-medium text-gray-700 mb-1">Nome Fantasia</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="nome_fantasia" name="nome_fantasia" value="${setDefaultValue(data.nome_fantasia)}" readonly required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="descricao_situacao_cadastral" class="block text-sm font-medium text-gray-700 mb-1">Descrição Situação Cadastral</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="descricao_situacao_cadastral" name="descricao_situacao_cadastral" value="${setDefaultValue(data.descricao_situacao_cadastral)}" readonly required>
                                    </div>
                                    <div>
                                        <label for="data_situacao_cadastral" class="block text-sm font-medium text-gray-700 mb-1">Data Situação Cadastral</label>
                                        <input type="date" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="data_situacao_cadastral" name="data_situacao_cadastral" value="${setDefaultValue(data.data_situacao_cadastral, '0000-00-00')}" required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="data_inicio_atividade" class="block text-sm font-medium text-gray-700 mb-1">Data Início Atividade</label>
                                        <input type="date" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="data_inicio_atividade" name="data_inicio_atividade" value="${setDefaultValue(data.data_inicio_atividade)}" readonly required>
                                    </div>
                                    <div>
                                        <label for="descricao_tipo_de_logradouro" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Logradouro</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="descricao_tipo_de_logradouro" name="descricao_tipo_de_logradouro" value="${setDefaultValue(data.descricao_tipo_de_logradouro)}" readonly required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="logradouro" class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="logradouro" name="logradouro" value="${setDefaultValue(data.logradouro)}" readonly required>
                                    </div>
                                    <div>
                                        <label for="numero" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="numero" name="numero" value="${setDefaultValue(data.numero)}" readonly required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="complemento" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="complemento" name="complemento" value="${setDefaultValue(data.complemento)}" readonly>
                                    </div>
                                    <div>
                                        <label for="bairro" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="bairro" name="bairro" value="${setDefaultValue(data.bairro)}" readonly required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="cep" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="cep" name="cep" value="${setDefaultValue(data.cep)}" readonly required>
                                    </div>
                                    <div>
                                        <label for="uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="uf" name="uf" value="${setDefaultValue(data.uf)}" readonly required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="municipio" class="block text-sm font-medium text-gray-700 mb-1">Município</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="municipio" name="municipio" value="${setDefaultValue(data.municipio)}" readonly required>
                                    </div>
                                    <div>
                                        <label for="ddd_telefone_1" class="block text-sm font-medium text-gray-700 mb-1">DDD Telefone 1</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="ddd_telefone_1" name="ddd_telefone_1" value="${setDefaultValue(data.ddd_telefone_1)}" readonly>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="ddd_telefone_2" class="block text-sm font-medium text-gray-700 mb-1">DDD Telefone 2</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="ddd_telefone_2" name="ddd_telefone_2" value="${setDefaultValue(data.ddd_telefone_2)}" readonly>
                                    </div>
                                    <div>
                                        <label for="razao_social" class="block text-sm font-medium text-gray-700 mb-1">Razão Social</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="razao_social" name="razao_social" value="${setDefaultValue(data.razao_social)}" readonly required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="natureza_juridica" class="block text-sm font-medium text-gray-700 mb-1">Natureza Jurídica</label>
                                        <input type="text" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="natureza_juridica" name="natureza_juridica" value="${setDefaultValue(data.natureza_juridica)}" readonly required>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="cnae-principal" role="tabpanel" aria-labelledby="cnae-principal-tab">
                                ${cnaePrincipalHTML}
                            </div>
                            <div class="tab-pane fade" id="cnaes-secundarios" role="tabpanel" aria-labelledby="cnaes-secundarios-tab">
                                ${cnaesHTML}
                            </div>
                            <div class="tab-pane fade" id="qsa" role="tabpanel" aria-labelledby="qsa-tab">
                                ${qsaHTML}
                            </div>
                        </div>
                        <div class="mb-3">
                            <input type="hidden" name="qsa" value='${JSON.stringify(data.qsa)}'>
                            <input type="hidden" name="cnaes_secundarios" value='${JSON.stringify(data.cnaes_secundarios)}'>
                            <input type="hidden" name="cnae_fiscal" value="${setDefaultValue(data.cnae_fiscal)}">
                            <input type="hidden" name="cnae_fiscal_descricao" value="${setDefaultValue(data.cnae_fiscal_descricao)}">
                        </div>
                    `;

                    $('#dadosEstabelecimento').html(dadosHTML);

                    // Exibir o botão Salvar
                    $('#salvarContainer').show();

                    $('.cnae-mask').mask('0000-0/00');

                    $('#cadastroEstabelecimentoForm').off('submit').on('submit', function(e) {
                        e.preventDefault();

                        var cnpj = $('#cnpj').val().replace(/\D/g, '');

                        $.ajax({
                            url: '../../controllers/EstabelecimentoController.php?action=checkCnpj',
                            method: 'POST',
                            data: {
                                cnpj: cnpj
                            },
                            success: function(response) {
                                var result = JSON.parse(response);
                                if (result.exists) {
                                    alert('Já existe um cadastro com esse CNPJ, entre em contato com a Vigilância Sanitária Municipal.');
                                } else {
                                    $('#cadastroEstabelecimentoForm')[0].submit();
                                }
                            },
                            error: function() {
                                alert('Erro ao verificar o CNPJ.');
                            }
                        });
                    });
                },
                error: function() {
                    alert('Erro ao consultar o CNPJ.');
                }
            });
        });
    });
</script>