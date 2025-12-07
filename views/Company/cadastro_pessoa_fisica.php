<?php
session_start();
if (isset($_SESSION['user']['nivel_acesso']) && in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    include '../header.php'; // Menu para administradores e usuários com níveis 2 e 3
} else {
    include '../../includes/header_empresa.php'; // Menu para outros usuários ou quando 'nivel_acesso' não estiver definido
}
// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}

// Inclui a API de consulta de CNAE
require_once 'api.php';
?>

<style>
    input[type="text"] {
        text-transform: uppercase;
    }
    
    .card {
        background-color: white;
        border-radius: 0.5rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 1.25rem;
        border: 1px solid #f3f4f6;
    }
    
    .card-header {
        padding: 1rem;
        background-color: #f9fafb;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .card-body {
        padding: 1.25rem;
    }
    
    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: #4b5563;
        margin-bottom: 0.5rem;
    }
    
    .form-control {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 400;
        color: #1f2937;
        background-color: #fff;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-control:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    
    .form-select {
        display: block;
        width: 100%;
        padding: 0.5rem 2.25rem 0.5rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 400;
        color: #1f2937;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        appearance: none;
    }
    
    .form-control-sm, .form-select-sm {
        padding: 0.375rem 0.5rem;
        font-size: 0.75rem;
        border-radius: 0.25rem;
    }
    
    .input-group {
        position: relative;
        display: flex;
        align-items: stretch;
        width: 100%;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25rem;
        border-radius: 0.375rem;
        cursor: pointer;
        border: 1px solid transparent;
        transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
    }
    
    .btn-primary {
        background-color: #3b82f6;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #2563eb;
    }
    
    .btn-danger {
        background-color: #ef4444;
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #dc2626;
    }
    
    .btn-sm {
        padding: 0.375rem 0.625rem;
        font-size: 0.75rem;
        border-radius: 0.25rem;
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
    
    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeeba;
    }
    
    .text-muted {
        color: #6c757d;
    }
    
    .mb-0 { margin-bottom: 0; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-3 { margin-bottom: 0.75rem; }
    .mb-4 { margin-bottom: 1rem; }
    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-3 { margin-top: 0.75rem; }
    .mt-4 { margin-top: 1rem; }
    .p-2 { padding: 0.5rem; }
    
    .list-group {
        display: flex;
        flex-direction: column;
        padding-left: 0;
        margin-bottom: 0;
        border-radius: 0.375rem;
    }
    
    .list-group-item {
        position: relative;
        display: block;
        padding: 0.75rem 1.25rem;
        background-color: #fff;
        border: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .list-group-item:first-child {
        border-top-left-radius: inherit;
        border-top-right-radius: inherit;
    }
    
    .list-group-item:last-child {
        border-bottom-right-radius: inherit;
        border-bottom-left-radius: inherit;
    }
    
    .border { border: 1px solid #dee2e6; }
    
    .d-flex { display: flex; }
    .justify-content-between { justify-content: space-between; }
    .align-items-center { align-items: center; }
</style>

<div class="mx-auto px-4 max-w-7xl mt-8">
    <div class="bg-blue-50 text-blue-800 p-4 rounded-md mb-6 border-l-4 border-blue-500" role="alert">
        <strong class="font-medium">Atenção!</strong> Preencha os dados para cadastrar uma pessoa física no sistema.
    </div>

    <?php if (isset($_GET['success'])) : ?>
        <div class="bg-green-50 text-green-800 p-4 rounded-md mb-6 border-l-4 border-green-500" role="alert">
            Pessoa física cadastrada com sucesso! Aguarde a aprovação do cadastro em até 24 horas.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])) : ?>
        <div class="bg-red-50 text-red-800 p-4 rounded-md mb-6 border-l-4 border-red-500" role="alert">
            Erro ao cadastrar pessoa física: <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form id="cadastroPessoaFisicaForm" action="../../controllers/EstabelecimentoController.php?action=registerPessoaFisica" method="POST">

        <!-- Dados Cadastrais -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="text-base font-medium text-gray-700 mb-0">Dados Cadastrais</h6>
            </div>
            <div class="card-body p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" placeholder="Digite o CPF" required>
                    </div>
                    <div>
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite o nome completo" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="rg" class="form-label">RG</label>
                        <input type="text" class="form-control" id="rg" name="rg" placeholder="Digite o RG" required>
                    </div>
                    <div>
                        <label for="orgao_emissor" class="form-label">Órgão Emissor</label>
                        <input type="text" class="form-control" id="orgao_emissor" name="orgao_emissor" placeholder="Digite o órgão emissor" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                        <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" placeholder="Digite o nome fantasia" required>
                    </div>
                    <div>
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Digite o e-mail" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="ddd_telefone_1" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="ddd_telefone_1" name="ddd_telefone_1" placeholder="Digite o telefone" required>
                    </div>
                    <div>
                        <label for="inicio_funcionamento" class="form-label">Início de Funcionamento</label>
                        <input type="date" class="form-control" id="inicio_funcionamento" name="inicio_funcionamento" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="text-base font-medium text-gray-700 mb-0">Endereço</h6>
            </div>
            <div class="card-body p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" placeholder="Digite o CEP" required>
                    </div>
                    <div>
                        <label for="logradouro" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="logradouro" name="logradouro" placeholder="Digite o endereço" required>
                    </div>
                    <div>
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" placeholder="Digite o bairro" required>
                    </div>
                    <div>
                        <label for="municipio" class="form-label">Cidade</label>
                        <select class="form-select" id="municipio" name="municipio" required>
                            <option value="">Selecione a cidade...</option>
                            <option value="ABREULÂNDIA">ABREULÂNDIA</option>
                            <option value="AGUIARNÓPOLIS">AGUIARNÓPOLIS</option>
                            <option value="ALIANÇA DO TOCANTINS">ALIANÇA DO TOCANTINS</option>
                            <option value="ALMAS">ALMAS</option>
                            <option value="ALVORADA">ALVORADA</option>
                            <option value="ANANÁS">ANANÁS</option>
                            <option value="ANGICO">ANGICO</option>
                            <option value="APARECIDA DO RIO NEGRO">APARECIDA DO RIO NEGRO</option>
                            <option value="ARAGOMINAS">ARAGOMINAS</option>
                            <option value="ARAGUACEMA">ARAGUACEMA</option>
                            <option value="ARAGUAÇU">ARAGUAÇU</option>
                            <option value="ARAGUAÍNA">ARAGUAÍNA</option>
                            <option value="ARAGUANÃ">ARAGUANÃ</option>
                            <option value="ARAGUATINS">ARAGUATINS</option>
                            <option value="ARAPOEMA">ARAPOEMA</option>
                            <option value="ARRAIAS">ARRAIAS</option>
                            <option value="AUGUSTINÓPOLIS">AUGUSTINÓPOLIS</option>
                            <option value="AURORA DO TOCANTINS">AURORA DO TOCANTINS</option>
                            <option value="AXIXÁ DO TOCANTINS">AXIXÁ DO TOCANTINS</option>
                            <option value="BABAÇULÂNDIA">BABAÇULÂNDIA</option>
                            <option value="BANDEIRANTES DO TOCANTINS">BANDEIRANTES DO TOCANTINS</option>
                            <option value="BARRA DO OURO">BARRA DO OURO</option>
                            <option value="BARROLÂNDIA">BARROLÂNDIA</option>
                            <option value="BERNARDO SAYÃO">BERNARDO SAYÃO</option>
                            <option value="BOM JESUS DO TOCANTINS">BOM JESUS DO TOCANTINS</option>
                            <option value="BRASILÂNDIA DO TOCANTINS">BRASILÂNDIA DO TOCANTINS</option>
                            <option value="BREJINHO DE NAZARÉ">BREJINHO DE NAZARÉ</option>
                            <option value="BURITI DO TOCANTINS">BURITI DO TOCANTINS</option>
                            <option value="CACHOEIRINHA">CACHOEIRINHA</option>
                            <option value="CAMPOS LINDOS">CAMPOS LINDOS</option>
                            <option value="CARIRI DO TOCANTINS">CARIRI DO TOCANTINS</option>
                            <option value="CARMOLÂNDIA">CARMOLÂNDIA</option>
                            <option value="CARRASCO BONITO">CARRASCO BONITO</option>
                            <option value="CASEARA">CASEARA</option>
                            <option value="CENTENÁRIO">CENTENÁRIO</option>
                            <option value="CHAPADA DA NATIVIDADE">CHAPADA DA NATIVIDADE</option>
                            <option value="CHAPADA DE AREIA">CHAPADA DE AREIA</option>
                            <option value="COLINAS DO TOCANTINS">COLINAS DO TOCANTINS</option>
                            <option value="COLMÉIA">COLMÉIA</option>
                            <option value="COMBINADO">COMBINADO</option>
                            <option value="CONCEIÇÃO DO TOCANTINS">CONCEIÇÃO DO TOCANTINS</option>
                            <option value="COUTO MAGALHÃES">COUTO MAGALHÃES</option>
                            <option value="CRISTALÂNDIA">CRISTALÂNDIA</option>
                            <option value="CRIXÁS DO TOCANTINS">CRIXÁS DO TOCANTINS</option>
                            <option value="DARCINÓPOLIS">DARCINÓPOLIS</option>
                            <option value="DIANÓPOLIS">DIANÓPOLIS</option>
                            <option value="DIVINÓPOLIS DO TOCANTINS">DIVINÓPOLIS DO TOCANTINS</option>
                            <option value="DOIS IRMÃOS DO TOCANTINS">DOIS IRMÃOS DO TOCANTINS</option>
                            <option value="DUERÉ">DUERÉ</option>
                            <option value="ESPERANTINA">ESPERANTINA</option>
                            <option value="FÁTIMA">FÁTIMA</option>
                            <option value="FIGUEIRÓPOLIS">FIGUEIRÓPOLIS</option>
                            <option value="FILADÉLFIA">FILADÉLFIA</option>
                            <option value="FORMOSO DO ARAGUAIA">FORMOSO DO ARAGUAIA</option>
                            <option value="FORTALEZA DO TABOCÃO">FORTALEZA DO TABOCÃO</option>
                            <option value="GOIANORTE">GOIANORTE</option>
                            <option value="GOIATINS">GOIATINS</option>
                            <option value="GUARAÍ">GUARAÍ</option>
                            <option value="GURUPI">GURUPI</option>
                            <option value="IPUEIRAS">IPUEIRAS</option>
                            <option value="ITACAJÁ">ITACAJÁ</option>
                            <option value="ITAGUATINS">ITAGUATINS</option>
                            <option value="ITAPIRATINS">ITAPIRATINS</option>
                            <option value="ITAPORÃ DO TOCANTINS">ITAPORÃ DO TOCANTINS</option>
                            <option value="JAÚ DO TOCANTINS">JAÚ DO TOCANTINS</option>
                            <option value="JUARINA">JUARINA</option>
                            <option value="LAGOA DA CONFUSÃO">LAGOA DA CONFUSÃO</option>
                            <option value="LAGOA DO TOCANTINS">LAGOA DO TOCANTINS</option>
                            <option value="LAJEADO">LAJEADO</option>
                            <option value="LAVANDEIRA">LAVANDEIRA</option>
                            <option value="LIZARDA">LIZARDA</option>
                            <option value="LUZINÓPOLIS">LUZINÓPOLIS</option>
                            <option value="MARIANÓPOLIS DO TOCANTINS">MARIANÓPOLIS DO TOCANTINS</option>
                            <option value="MATEIROS">MATEIROS</option>
                            <option value="MAURILÂNDIA DO TOCANTINS">MAURILÂNDIA DO TOCANTINS</option>
                            <option value="MIRACEMA DO TOCANTINS">MIRACEMA DO TOCANTINS</option>
                            <option value="MIRANORTE">MIRANORTE</option>
                            <option value="MONTE DO CARMO">MONTE DO CARMO</option>
                            <option value="MONTE SANTO DO TOCANTINS">MONTE SANTO DO TOCANTINS</option>
                            <option value="MURICILÂNDIA">MURICILÂNDIA</option>
                            <option value="NATAL">NATAL</option>
                            <option value="NATIVIDADE">NATIVIDADE</option>
                            <option value="NAZARÉ">NAZARÉ</option>
                            <option value="NOVA OLINDA">NOVA OLINDA</option>
                            <option value="NOVA ROSALÂNDIA">NOVA ROSALÂNDIA</option>
                            <option value="NOVO ACORDO">NOVO ACORDO</option>
                            <option value="NOVO ALEGRE">NOVO ALEGRE</option>
                            <option value="NOVO JARDIM">NOVO JARDIM</option>
                            <option value="OLIVEIRA DE FÁTIMA">OLIVEIRA DE FÁTIMA</option>
                            <option value="PALMAS">PALMAS</option>
                            <option value="PALMEIRANTE">PALMEIRANTE</option>
                            <option value="PALMEIRAS DO TOCANTINS">PALMEIRAS DO TOCANTINS</option>
                            <option value="PALMEIROPOLIS">PALMEIROPOLIS</option>
                            <option value="PARAÍSO DO TOCANTINS">PARAÍSO DO TOCANTINS</option>
                            <option value="PARANÃ">PARANÃ</option>
                            <option value="PAU D'ARCO">PAU D'ARCO</option>
                            <option value="PEDRO AFONSO">PEDRO AFONSO</option>
                            <option value="PEIXE">PEIXE</option>
                            <option value="PEQUIZEIRO">PEQUIZEIRO</option>
                            <option value="PINDORAMA DO TOCANTINS">PINDORAMA DO TOCANTINS</option>
                            <option value="PIRAQUÊ">PIRAQUÊ</option>
                            <option value="PIUM">PIUM</option>
                            <option value="PONTE ALTA DO BOM JESUS">PONTE ALTA DO BOM JESUS</option>
                            <option value="PONTE ALTA DO TOCANTINS">PONTE ALTA DO TOCANTINS</option>
                            <option value="PORTO ALEGRE DO TOCANTINS">PORTO ALEGRE DO TOCANTINS</option>
                            <option value="PORTO NACIONAL">PORTO NACIONAL</option>
                            <option value="PRAIA NORTE">PRAIA NORTE</option>
                            <option value="PRESIDENTE KENNEDY">PRESIDENTE KENNEDY</option>
                            <option value="PUGMIL">PUGMIL</option>
                            <option value="RECURSOLÂNDIA">RECURSOLÂNDIA</option>
                            <option value="RIACHINHO">RIACHINHO</option>
                            <option value="RIO DA CONCEIÇÃO">RIO DA CONCEIÇÃO</option>
                            <option value="RIO DOS BOIS">RIO DOS BOIS</option>
                            <option value="RIO SONO">RIO SONO</option>
                            <option value="SAMPAIO">SAMPAIO</option>
                            <option value="SANDOLÂNDIA">SANDOLÂNDIA</option>
                            <option value="SANTA FÉ DO ARAGUAIA">SANTA FÉ DO ARAGUAIA</option>
                            <option value="SANTA MARIA DO TOCANTINS">SANTA MARIA DO TOCANTINS</option>
                            <option value="SANTA RITA DO TOCANTINS">SANTA RITA DO TOCANTINS</option>
                            <option value="SANTA ROSA DO TOCANTINS">SANTA ROSA DO TOCANTINS</option>
                            <option value="SANTA TEREZA DO TOCANTINS">SANTA TEREZA DO TOCANTINS</option>
                            <option value="SANTA TEREZINHA DO TOCANTINS">SANTA TEREZINHA DO TOCANTINS</option>
                            <option value="SÃO BENTO DO TOCANTINS">SÃO BENTO DO TOCANTINS</option>
                            <option value="SÃO FÉLIX DO TOCANTINS">SÃO FÉLIX DO TOCANTINS</option>
                            <option value="SÃO MIGUEL DO TOCANTINS">SÃO MIGUEL DO TOCANTINS</option>
                            <option value="SÃO SALVADOR DO TOCANTINS">SÃO SALVADOR DO TOCANTINS</option>
                            <option value="SÃO SEBASTIÃO DO TOCANTINS">SÃO SEBASTIÃO DO TOCANTINS</option>
                            <option value="SÃO VALÉRIO DA NATIVIDADE">SÃO VALÉRIO DA NATIVIDADE</option>
                            <option value="SILVANÓPOLIS">SILVANÓPOLIS</option>
                            <option value="SÍTIO NOVO DO TOCANTINS">SÍTIO NOVO DO TOCANTINS</option>
                            <option value="SUCUPIRA">SUCUPIRA</option>
                            <option value="TAGUATINGA">TAGUATINGA</option>
                            <option value="TAIPAS DO TOCANTINS">TAIPAS DO TOCANTINS</option>
                            <option value="TALISMÃ">TALISMÃ</option>
                            <option value="TOCANTÍNIA">TOCANTÍNIA</option>
                            <option value="TOCANTINÓPOLIS">TOCANTINÓPOLIS</option>
                            <option value="TUPIRAMA">TUPIRAMA</option>
                            <option value="TUPIRATINS">TUPIRATINS</option>
                            <option value="WANDERLÂNDIA">WANDERLÂNDIA</option>
                            <option value="XAMBIOÁ">XAMBIOÁ</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" placeholder="Digite o número" required>
                    </div>
                    <div>
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" placeholder="Digite o complemento">
                    </div>
                    <div>
                        <label for="uf" class="form-label">UF</label>
                        <input type="text" class="form-control" id="uf" name="uf" value="TO" readonly required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card para Atividades (CNAE) -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="text-base font-medium text-gray-700 mb-0">Atividades Vinculadas (CNAE)</h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-4">Para vincular atividades, insira o código CNAE de 7 dígitos e clique em "Buscar". Você pode adicionar mais de uma atividade ao estabelecimento.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="cnae_search" class="form-label">Vincular CNAE</label>
                            <div class="flex">
                                <input type="text" class="form-control rounded-r-none" id="cnae_search" maxlength="7" placeholder="Digite o código do CNAE (7 dígitos)">
                                <button type="button" class="btn btn-primary rounded-l-none" onclick="searchCNAE()">Buscar</button>
                            </div>
                            <!-- Exibe resultado da pesquisa aqui -->
                            <div id="cnae_result" class="mt-3"></div>
                        </div>
                    </div>
                </div>

                <!-- Lista de CNAEs selecionados -->
                <div class="mt-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h6 class="text-base font-medium mb-2">CNAEs Selecionados</h6>
                            <div id="cnaes_list" class="bg-gray-50 border border-gray-200 rounded-md p-3">
                                <!-- CNAEs adicionados aparecem aqui -->
                                <p class="text-sm text-gray-500" id="cnaes_placeholder">Nenhuma atividade vinculada ainda.</p>
                            </div>
                            <!-- Campo oculto para armazenar CNAEs selecionados -->
                            <input type="hidden" name="cnaes" id="cnaes" value="">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botão para salvar o formulário completo -->
        <div class="mb-6">
            <button type="submit" class="btn btn-primary px-6 py-2">Salvar</button>
        </div>
    </form>
</div>
<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
    $(document).ready(function() {
        // Aplicar máscara nos campos de CPF, CEP e telefone
        $('#cpf').mask('000.000.000-00');
        $('#cep').mask('00000-000');
        $('#ddd_telefone_1').mask('(00) 00000-0000');

        // Converte para maiúsculas enquanto o usuário digita
        $('input[type="text"]').on('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Evento de mudança no campo de CEP
        $('#cep').on('blur', function() {
            let cep = $(this).val().replace(/\D/g, ''); // Remove caracteres não numéricos
            if (cep.length === 8) {
                searchCEP(cep);
            } else {
                alert('CEP inválido. Por favor, insira um CEP válido.');
            }
        });
    });

    // Função para consultar o CEP via API do ViaCEP
    function searchCEP(cep) {
        $.ajax({
            url: `https://viacep.com.br/ws/${cep}/json/`,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.erro) {
                    // Preenche os campos de endereço com os dados da API
                    $('#logradouro').val(response.logradouro);
                    $('#bairro').val(response.bairro);
                    selectCity(response.localidade); // Função para selecionar a cidade no dropdown
                    $('#uf').val(response.uf);
                } else {
                    alert('CEP não encontrado. Por favor, preencha o endereço manualmente.');
                    // Limpa os campos de endereço para preenchimento manual
                    clearAddressFields();
                }
            },
            error: function() {
                alert('Erro ao consultar o CEP. Por favor, tente novamente.');
                // Limpa os campos de endereço para preenchimento manual
                clearAddressFields();
            }
        });
    }

    // Função para limpar os campos de endereço
    function clearAddressFields() {
        $('#logradouro').val('');
        $('#bairro').val('');
        $('#municipio').val('');
        $('#uf').val('TO'); // Manter o estado como TO, mas o usuário pode alterar
    }

    // Função para selecionar a cidade no dropdown com base no nome retornado pela API
    function selectCity(cityName) {
        let cityDropdown = $('#municipio');
        let options = cityDropdown.find('option');

        options.each(function() {
            if ($(this).text().toUpperCase() === cityName.toUpperCase()) {
                $(this).prop('selected', true); // Seleciona a cidade no dropdown
                return false; // Para o loop após encontrar a cidade
            }
        });
    }
    // Verificação antes de salvar o formulário
    $('#cadastroPessoaFisicaForm').on('submit', function(e) {
        // Verificar se existe pelo menos um CNAE selecionado
        let cnaesField = $('#cnaes').val();
        if (!cnaesField || JSON.parse(cnaesField).length === 0) {
            e.preventDefault(); // Impede o envio do formulário
            alert('Por favor, selecione pelo menos um CNAE antes de salvar.');
        }
    });

    // Função para consultar o CNAE via AJAX
    function searchCNAE() {
        let cnae_code = $('#cnae_search').val().trim();

        if (cnae_code.length === 7) { // Verifica se tem 5 dígitos
            $.ajax({
                url: 'api.php', // O arquivo que faz a consulta na API
                type: 'GET',
                data: {
                    cnae: cnae_code
                },
                success: function(response) {
                    // Exibe o resultado da consulta no card
                    $('#cnae_result').html(response);
                },
                error: function() {
                    $('#cnae_result').html('<div class="alert alert-danger">Erro ao consultar o CNAE. Tente novamente.</div>');
                }
            });
        } else {
            $('#cnae_result').html('<div class="alert alert-warning">Digite um código CNAE válido com 5 dígitos.</div>');
        }
    }

    // Função para adicionar o CNAE à lista de selecionados
    // Função para exibir a lista de CNAEs selecionados
    function updateCnaePlaceholder() {
        const cnaeList = document.getElementById('cnaes_list');
        const placeholder = document.getElementById('cnaes_placeholder');
        if (cnaeList.children.length > 0) {
            placeholder.style.display = 'none';
        } else {
            placeholder.style.display = 'block';
        }
    }

    // Atualiza o placeholder ao adicionar ou remover CNAEs
    // Função para adicionar o CNAE à lista de selecionados
    function addCNAE(cnaeId, cnaeDesc) {
        let cnaesList = document.getElementById('cnaes_list');
        let cnaesField = document.getElementById('cnaes');
        let currentCnaes = cnaesField.value ? JSON.parse(cnaesField.value) : [];

        // Verifica se o CNAE já está na lista
        let cnaeExists = currentCnaes.some(cnae => cnae.id === cnaeId);
        if (cnaeExists) {
            alert('Este CNAE já foi adicionado.');
            return;
        }

        // Cria o elemento de lista para o CNAE
        let cnaeItem = document.createElement('div');
        cnaeItem.className = 'list-group-item d-flex justify-content-between align-items-center';
        cnaeItem.innerHTML = cnaeId + ' - ' + cnaeDesc + ' <button class="btn btn-sm btn-danger" onclick="removeCNAE(this, \'' + cnaeId + '\')">Remover</button>';

        // Adiciona o CNAE ao campo oculto
        currentCnaes.push({
            id: cnaeId,
            descricao: cnaeDesc
        });
        cnaesField.value = JSON.stringify(currentCnaes);

        // Adiciona o CNAE na lista visível
        cnaesList.appendChild(cnaeItem);
        updateCnaePlaceholder(); // Atualiza o placeholder
    }


    // Função para remover CNAE da lista
    function removeCNAE(element, cnaeId) {
        element.parentElement.remove();

        // Remove o CNAE do campo oculto
        let cnaesField = document.getElementById('cnaes');
        let currentCnaes = JSON.parse(cnaesField.value);
        cnaesField.value = JSON.stringify(currentCnaes.filter(cnae => cnae.id !== cnaeId));

        updateCnaePlaceholder(); // Atualiza o placeholder
    }
</script>