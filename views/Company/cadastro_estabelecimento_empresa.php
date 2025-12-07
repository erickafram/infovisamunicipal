<?php
session_start();
include '../../includes/header_empresa.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Estabelecimento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #93c5fd;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: var(--gray-700);
        }
        
        .card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 1rem;
            background-color: white;
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-title {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 1.125rem;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--gray-700);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            color: var(--gray-700);
            background-color: #fff;
            border-color: var(--primary-light);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }
        
        .form-label {
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .nav-tabs {
            display: flex;
            flex-wrap: wrap;
            padding-left: 0;
            margin-bottom: 0;
            list-style: none;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .nav-tabs .nav-item {
            margin-bottom: -1px;
        }
        
        .nav-tabs .nav-link {
            display: block;
            padding: 0.75rem 1rem;
            border: 1px solid transparent;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
            color: var(--gray-600);
            background-color: transparent;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link:hover,
        .nav-tabs .nav-link:focus {
            color: var(--primary);
            border-color: transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: white;
            border-color: var(--gray-200) var(--gray-200) white;
            border-bottom: 2px solid var(--primary);
        }
        
        .tab-content {
            padding: 1.5rem;
            background-color: white;
            border: 1px solid var(--gray-200);
            border-top: none;
            border-bottom-left-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }
        
        .spinner {
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 3px solid var(--primary);
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            position: relative;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.375rem;
        }
        
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .fade-in {
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Estilo adicional para melhorar campo de CNPJ */
        .cnpj-input {
            font-size: 1.25rem;
            letter-spacing: 1px;
            text-align: center;
            font-weight: 600;
        }
        
        /* Estilo para campos somente leitura */
        .form-control[readonly] {
            background-color: var(--gray-50);
            cursor: not-allowed;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-3 py-6 mt-4">
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-building"></i>
                    Cadastro de Estabelecimento
                </h2>
            </div>
            <div class="card-body">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                Basta inserir o CNPJ do estabelecimento e uma API fará a busca automática dos dados. Se o estabelecimento já estiver cadastrado, entre em contato com a Vigilância Sanitária Municipal.
                            </p>
                        </div>
                    </div>
    </div>

    <?php if (isset($_GET['success'])) : ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-md fade-in">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
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
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md fade-in">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
            Erro ao cadastrar estabelecimento: <?php echo htmlspecialchars($_GET['error']); ?>
                                </p>
                            </div>
                        </div>
        </div>
    <?php endif; ?>

    <form id="cadastroEstabelecimentoForm" action="../../controllers/EstabelecimentoController.php?action=registerEmpresa" method="POST">
                    <div class="mb-6">
                        <label for="cnpj" class="form-label text-center block">CNPJ do Estabelecimento</label>
                        <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-3 items-center">
                            <input type="text" class="form-control cnpj-input w-full md:w-64" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required>
                            <button type="button" class="btn btn-primary w-full md:w-auto" id="consultarCNPJ">
                                <span id="spinner" class="spinner" style="display: none;"></span>
                                <i class="fas fa-search mr-2" id="search-icon"></i> Consultar CNPJ
                            </button>
        </div>
                        <div id="cnpj-feedback" class="mt-2 text-sm"></div>
        </div>

        <!-- Campos para os dados retornados da consulta à API -->
                    <div id="dadosEstabelecimento" class="mt-8">
            <!-- Os campos para exibir e editar os dados do estabelecimento serão inseridos aqui pelo JavaScript -->
        </div>

        <!-- Botão Salvar (inicialmente oculto) -->
                    <div class="mb-3 mt-6 text-center" id="salvarContainer" style="display: none;">
                        <button type="submit" class="btn btn-primary px-8 py-3 text-base">
                            <i class="fas fa-save mr-2"></i> Salvar Estabelecimento
                        </button>
                    </div>
                </form>
            </div>
        </div>
</div>

<!-- Adicione a biblioteca jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Adicione o Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Adicione a biblioteca de máscara -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
    $(document).ready(function() {
            // Inicializa a máscara do CNPJ
            $('#cnpj').mask('00.000.000/0000-00', {
                completed: function() {
                    $('#cnpj-feedback').html('<span class="text-green-600"><i class="fas fa-check-circle"></i> Formato de CNPJ válido</span>');
                }
            });

            // Função para valores padrão
        function setDefaultValue(value, defaultValue = "Não Informado") {
            return value ? value : defaultValue;
        }

            // Evento de consulta de CNPJ
        $('#consultarCNPJ').on('click', function() {
            var cnpj = $('#cnpj').val().replace(/\D/g, '');
                
                // Validação de CNPJ
            if (cnpj.length !== 14) {
                    $('#cnpj-feedback').html('<span class="text-red-600"><i class="fas fa-exclamation-circle"></i> Por favor, insira um CNPJ válido com 14 dígitos.</span>');
                return;
            }

                // Mostrar indicador de carregamento
                $('#spinner').show();
                $('#search-icon').hide();
                $('#consultarCNPJ').prop('disabled', true);
                $('#cnpj-feedback').html('<span class="text-blue-600">Consultando dados, aguarde...</span>');
                $('#dadosEstabelecimento').html('');
                $('#salvarContainer').hide();

                // Fazer a requisição AJAX usando o proxy local
            $.ajax({
                url: '../../api/consultar_cnpj.php',
                method: 'GET',
                data: {
                    cnpj: cnpj
                },
                success: function(data) {
                        // Ocultar indicador de carregamento
                        $('#spinner').hide();
                        $('#search-icon').show();
                        $('#consultarCNPJ').prop('disabled', false);

                        // Verificar se a resposta contém erro ou dados vazios
                        if (typeof data === 'string') {
                            try {
                                data = JSON.parse(data);
                            } catch (e) {
                                $('#cnpj-feedback').html('<span class="text-red-600"><i class="fas fa-times-circle"></i> Erro: Resposta inválida da API.</span>');
                                return;
                            }
                        }

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
                                                <i class="fas fa-exclamation-circle text-red-500 text-2xl"></i>
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
                                $('#cnpj-feedback').html('<span class="text-red-600"><i class="fas fa-times-circle"></i> Erro ao consultar CNPJ</span>');
                            } else {
                                $('#cnpj-feedback').html('<span class="text-red-600"><i class="fas fa-times-circle"></i> Erro: ' + data.message + '</span>');
                            }
                            return;
                        }
                        
                        if (!data.razao_social) {
                            $('#cnpj-feedback').html('<span class="text-red-600"><i class="fas fa-times-circle"></i> Erro: CNPJ inválido ou dados não encontrados.</span>');
                        return;
                    }

                        // Verificação de situação cadastral
                        if (data.descricao_situacao_cadastral !== 'ATIVA') {
                            $('#cnpj-feedback').html('<span class="text-yellow-600"><i class="fas fa-exclamation-triangle"></i> Atenção: Esse estabelecimento possui situação cadastral ' + data.descricao_situacao_cadastral + '. Verifique antes de prosseguir.</span>');
                        } else {
                            $('#cnpj-feedback').html('<span class="text-green-600"><i class="fas fa-check-circle"></i> CNPJ válido - Dados encontrados</span>');
                        }

                        // Preparar os dados dos sócios
                        var qsaHTML = '';
                        if (data.qsa && data.qsa.length > 0) {
                            qsaHTML = data.qsa.map(function(socio) {
                        return `
                                <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="form-label text-sm">Nome do Sócio</label>
                                            <input type="text" class="form-control" value="${setDefaultValue(socio.nome_socio)}" readonly>
                                        </div>
                                        <div>
                                            <label class="form-label text-sm">Qualificação</label>
                                            <input type="text" class="form-control" value="${setDefaultValue(socio.qualificacao_socio)}" readonly>
                                </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                        } else {
                            qsaHTML = `
                                <div class="p-4 bg-gray-50 rounded-lg text-center">
                                    <p class="text-gray-600">Não há sócios cadastrados para este estabelecimento.</p>
                                </div>
                            `;
                        }

                        // Preparar os dados do CNAE principal
                    var cnaePrincipalHTML = `
                            <div class="mb-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h4 class="text-blue-700 font-medium mb-3">CNAE Principal</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                <label for="cnae_fiscal" class="form-label">Código CNAE Fiscal</label>
                                <input type="text" class="form-control cnae-mask" id="cnae_fiscal" name="cnae_fiscal" value="${setDefaultValue(data.cnae_fiscal)}" readonly>
                            </div>
                                    <div>
                                <label for="cnae_fiscal_descricao" class="form-label">Descrição CNAE Fiscal</label>
                                <input type="text" class="form-control" id="cnae_fiscal_descricao" name="cnae_fiscal_descricao" value="${setDefaultValue(data.cnae_fiscal_descricao)}" readonly>
                                    </div>
                            </div>
                        </div>
                    `;

                        // Preparar os dados dos CNAEs secundários
                        var cnaesHTML = '';
                        if (data.cnaes_secundarios && data.cnaes_secundarios.length > 0) {
                            cnaesHTML = data.cnaes_secundarios.map(function(cnae, index) {
                        return `
                                    <div class="mb-4 p-3 ${index % 2 === 0 ? 'bg-gray-50' : 'bg-white'} rounded-lg border border-gray-200">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="form-label text-sm">Código</label>
                                    <input type="text" class="form-control cnae-mask" name="cnaes_secundarios[][codigo]" value="${setDefaultValue(cnae.codigo)}" readonly>
                                </div>
                                            <div>
                                                <label class="form-label text-sm">Descrição</label>
                                    <input type="text" class="form-control" name="cnaes_secundarios[][descricao]" value="${setDefaultValue(cnae.descricao)}" readonly>
                                            </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                        } else {
                            cnaesHTML = `
                                <div class="p-4 bg-gray-50 rounded-lg text-center">
                                    <p class="text-gray-600">Não há CNAEs secundários cadastrados para este estabelecimento.</p>
                                </div>
                            `;
                        }

                        // Construir o HTML completo com as abas
                    var dadosHTML = `
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <ul class="nav nav-tabs" id="estabelecimentoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab" aria-controls="dados" aria-selected="true">
                                            <i class="fas fa-info-circle mr-2"></i>Dados da Empresa
                                        </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="cnae-principal-tab" data-bs-toggle="tab" data-bs-target="#cnae-principal" type="button" role="tab" aria-controls="cnae-principal" aria-selected="false">
                                            <i class="fas fa-tag mr-2"></i>CNAE Principal
                                        </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="cnaes-secundarios-tab" data-bs-toggle="tab" data-bs-target="#cnaes-secundarios" type="button" role="tab" aria-controls="cnaes-secundarios" aria-selected="false">
                                            <i class="fas fa-tags mr-2"></i>CNAEs Secundários
                                        </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="qsa-tab" data-bs-toggle="tab" data-bs-target="#qsa" type="button" role="tab" aria-controls="qsa" aria-selected="false">
                                            <i class="fas fa-users mr-2"></i>Responsáveis
                                        </button>
                            </li>
                        </ul>
                        <div class="tab-content" id="estabelecimentoTabsContent">
                                    <div class="tab-pane fade show active" id="dados" role="tabpanel" aria-labelledby="dados-tab">
                                        <div class="p-4">
                                            <h3 class="text-lg font-medium text-gray-800 mb-4 border-b pb-2">Informações Gerais</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="descricao_identificador_matriz_filial" class="form-label">Matriz/Filial</label>
                                        <input type="text" class="form-control" id="descricao_identificador_matriz_filial" name="descricao_identificador_matriz_filial" value="${setDefaultValue(data.descricao_identificador_matriz_filial)}" readonly required>
                                    </div>
                                                <div>
                                        <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                                        <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" value="${setDefaultValue(data.nome_fantasia)}" readonly required>
                                    </div>
                                                <div>
                                                    <label for="descricao_situacao_cadastral" class="form-label">Situação Cadastral</label>
                                                    <input type="text" class="form-control ${data.descricao_situacao_cadastral === 'ATIVA' ? 'text-green-600 font-medium' : 'text-red-600 font-medium'}" id="descricao_situacao_cadastral" name="descricao_situacao_cadastral" value="${setDefaultValue(data.descricao_situacao_cadastral)}" readonly required>
                                </div>
                                                <div>
                                        <label for="data_situacao_cadastral" class="form-label">Data Situação Cadastral</label>
                                                    <input type="date" class="form-control" id="data_situacao_cadastral" name="data_situacao_cadastral" value="${data.data_situacao_cadastral ? data.data_situacao_cadastral : ''}" required>
                                    </div>
                                                <div>
                                        <label for="data_inicio_atividade" class="form-label">Data Início Atividade</label>
                                                    <input type="date" class="form-control" id="data_inicio_atividade" name="data_inicio_atividade" value="${data.data_inicio_atividade ? data.data_inicio_atividade : ''}" readonly required>
                                    </div>
                                                <div>
                                                    <label for="razao_social" class="form-label">Razão Social</label>
                                                    <input type="text" class="form-control" id="razao_social" name="razao_social" value="${setDefaultValue(data.razao_social)}" readonly required>
                                    </div>
                                </div>
                                            
                                            <h3 class="text-lg font-medium text-gray-800 mb-4 mt-6 border-b pb-2">Endereço</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                        <label for="logradouro" class="form-label">Logradouro</label>
                                                    <div class="flex">
                                                        <span class="inline-flex items-center px-3 text-gray-500 bg-gray-50 rounded-l-md border border-r-0 border-gray-300">
                                                            ${setDefaultValue(data.descricao_tipo_de_logradouro, '')}
                                                        </span>
                                                        <input type="text" class="form-control rounded-l-none" id="logradouro" name="logradouro" value="${setDefaultValue(data.logradouro)}" readonly required>
                                                    </div>
                                    </div>
                                                <div>
                                        <label for="numero" class="form-label">Número</label>
                                        <input type="text" class="form-control" id="numero" name="numero" value="${setDefaultValue(data.numero)}" readonly required>
                                    </div>
                                                <div>
                                        <label for="complemento" class="form-label">Complemento</label>
                                        <input type="text" class="form-control" id="complemento" name="complemento" value="${setDefaultValue(data.complemento)}" readonly>
                                    </div>
                                                <div>
                                        <label for="bairro" class="form-label">Bairro</label>
                                        <input type="text" class="form-control" id="bairro" name="bairro" value="${setDefaultValue(data.bairro)}" readonly required>
                                    </div>
                                                <div>
                                        <label for="cep" class="form-label">CEP</label>
                                        <input type="text" class="form-control" id="cep" name="cep" value="${setDefaultValue(data.cep)}" readonly required>
                                    </div>
                                                <div>
                                                    <label for="municipio" class="form-label">Município</label>
                                                    <div class="flex">
                                                        <input type="text" class="form-control rounded-r-none" id="municipio" name="municipio" value="${setDefaultValue(data.municipio)}" readonly required>
                                                        <span class="inline-flex items-center px-3 text-gray-500 bg-gray-50 rounded-r-md border border-l-0 border-gray-300">
                                                            ${setDefaultValue(data.uf, '')}
                                                        </span>
                                    </div>
                                </div>
                                    </div>
                                            
                                            <h3 class="text-lg font-medium text-gray-800 mb-4 mt-6 border-b pb-2">Contato</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="ddd_telefone_1" class="form-label">Telefone Principal</label>
                                        <input type="text" class="form-control" id="ddd_telefone_1" name="ddd_telefone_1" value="${setDefaultValue(data.ddd_telefone_1)}" readonly>
                                    </div>
                                                <div>
                                                    <label for="ddd_telefone_2" class="form-label">Telefone Secundário</label>
                                        <input type="text" class="form-control" id="ddd_telefone_2" name="ddd_telefone_2" value="${setDefaultValue(data.ddd_telefone_2)}" readonly>
                                    </div>
                                                <div>
                                        <label for="natureza_juridica" class="form-label">Natureza Jurídica</label>
                                        <input type="text" class="form-control" id="natureza_juridica" name="natureza_juridica" value="${setDefaultValue(data.natureza_juridica)}" readonly required>
                                                </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="cnae-principal" role="tabpanel" aria-labelledby="cnae-principal-tab">
                                        <div class="p-4">
                                ${cnaePrincipalHTML}
                                        </div>
                            </div>
                            <div class="tab-pane fade" id="cnaes-secundarios" role="tabpanel" aria-labelledby="cnaes-secundarios-tab">
                                        <div class="p-4">
                                            <h3 class="text-lg font-medium text-gray-800 mb-4">CNAEs Secundários</h3>
                                ${cnaesHTML}
                                        </div>
                            </div>
                            <div class="tab-pane fade" id="qsa" role="tabpanel" aria-labelledby="qsa-tab">
                                        <div class="p-4">
                                            <h3 class="text-lg font-medium text-gray-800 mb-4">Quadro de Sócios e Administradores</h3>
                                            ${qsaHTML}
                                        </div>
                            </div>
                        </div>

                                <input type="hidden" name="qsa" value='${JSON.stringify(data.qsa || [])}'>
                                <input type="hidden" name="cnaes_secundarios" value='${JSON.stringify(data.cnaes_secundarios || [])}'>
                            <input type="hidden" name="cnae_fiscal" value="${setDefaultValue(data.cnae_fiscal)}">
                            <input type="hidden" name="cnae_fiscal_descricao" value="${setDefaultValue(data.cnae_fiscal_descricao)}">
                                <input type="hidden" name="descricao_tipo_de_logradouro" value="${setDefaultValue(data.descricao_tipo_de_logradouro)}">
                                <input type="hidden" name="uf" value="${setDefaultValue(data.uf)}">
                        </div>
                    `;

                        // Inserir HTML na página
                    $('#dadosEstabelecimento').html(dadosHTML);

                        // Exibir botão de salvar com efeito de fade-in
                        $('#salvarContainer').fadeIn(400);

                        // Aplicar máscara para CNAEs
                    $('.cnae-mask').mask('0000-0/00');

                        // Inicializar as abas
                        var triggerTabList = [].slice.call(document.querySelectorAll('#estabelecimentoTabs button'));
                        triggerTabList.forEach(function (triggerEl) {
                            var tabTrigger = new bootstrap.Tab(triggerEl);
                            triggerEl.addEventListener('click', function (event) {
                                event.preventDefault();
                                tabTrigger.show();
                            });
                        });

                        // Configura o evento de envio do formulário
                    $('#cadastroEstabelecimentoForm').off('submit').on('submit', function(e) {
                        e.preventDefault();
                            
                            // Mostrar indicador de carregamento
                            $('#salvarEstabelecimento').prop('disabled', true).html('<span class="spinner"></span> Salvando...');

                        var cnpj = $('#cnpj').val().replace(/\D/g, '');

                            // Verificar se o CNPJ já existe
                        $.ajax({
                            url: '../../controllers/EstabelecimentoController.php?action=checkCnpj',
                            method: 'POST',
                            data: {
                                cnpj: cnpj
                            },
                            success: function(response) {
                                var result = JSON.parse(response);
                                if (result.exists) {
                                        // Mostrar mensagem de erro
                                        Swal.fire({
                                            title: 'CNPJ já cadastrado',
                                            text: 'Já existe um cadastro com esse CNPJ, entre em contato com a Vigilância Sanitária Municipal.',
                                            icon: 'warning',
                                            confirmButtonText: 'OK'
                                        });
                                        $('#salvarEstabelecimento').prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Salvar Estabelecimento');
                                } else {
                                        // Enviar o formulário
                                    $('#cadastroEstabelecimentoForm')[0].submit();
                                }
                            },
                            error: function() {
                                alert('Erro ao verificar o CNPJ.');
                                    $('#salvarEstabelecimento').prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Salvar Estabelecimento');
                            }
                        });
                    });
                },
                error: function() {
                        // Ocultar indicador de carregamento
                        $('#spinner').hide();
                        $('#search-icon').show();
                        $('#consultarCNPJ').prop('disabled', false);
                        $('#cnpj-feedback').html('<span class="text-red-600"><i class="fas fa-times-circle"></i> Erro ao consultar o CNPJ. Por favor, tente novamente.</span>');
                }
            });
        });

            // Adicionar SweetAlert2 para alertas mais bonitos
            if (typeof Swal === 'undefined') {
                // Carrega SweetAlert2 se não estiver disponível
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                script.async = true;
                document.head.appendChild(script);
            }
    });
</script>
</body>

</html>