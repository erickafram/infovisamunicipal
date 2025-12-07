<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

$ordemServico = new OrdemServico($conn);

// Verifique se o município do usuário está na sessão
if (!isset($_SESSION['user']['municipio'])) {
    echo "Município do usuário não encontrado!";
    exit();
}

$municipio_usuario = $_SESSION['user']['municipio'];

if (!isset($_GET['id'])) {
    echo "ID da ordem de serviço não fornecido!";
    exit();
}

$id = $_GET['id'];

// Verificar se o usuário tem permissão para acessar esta ordem baseado no município
$municipioUsuario = $_SESSION['user']['municipio'];
if (!$ordemServico->podeAcessarOrdem($id, $municipioUsuario)) {
    header("Location: listar_ordens.php?error=Acesso negado. Você não tem permissão para vincular esta ordem de serviço.");
    exit();
}

$ordem = $ordemServico->getOrdemById($id);

if (!$ordem) {
    echo "Ordem de serviço não encontrada!";
    exit();
}

// Verificação se a ordem está finalizada
if ($ordem['status'] == 'finalizada') {
    include '../header.php';
    echo '<div class="container mt-5">
            <div class="alert alert-danger" role="alert">
                Não é possível vincular uma ordem de serviço finalizada.
            </div>
          </div>';
    include '../footer.php';
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $estabelecimento_id = $_POST['estabelecimento_id'];
    $processo_id = $_POST['processo_id'];

    $acoes_executadas = json_decode($ordem['acoes_executadas'], true);

    if ($ordemServico->update($id, $ordem['data_inicio'], $ordem['data_fim'], $acoes_executadas, $ordem['tecnicos'], $ordem['pdf_path'], $estabelecimento_id, $processo_id)) {
        header("Location: detalhes_ordem.php?id=$id&success=Ordem de Serviço vinculada com sucesso.");
        exit();
    } else {
        $error = "Erro ao vincular a ordem de serviço: " . $ordemServico->getLastError();
    }
}
?>

<?php include '../header.php'; ?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-blue-100 to-blue-50">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Vincular Ordem de Serviço</h2>
                <span class="text-gray-500 text-sm">Associar a Estabelecimento</span>
            </div>
            
            <?php if (isset($error)) : ?>
                <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="p-6">
            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm">Você está vinculando a ordem de serviço #<?php echo htmlspecialchars($id); ?> a um estabelecimento. Para isso, busque pelo nome ou CNPJ do estabelecimento e selecione um dos processos disponíveis.</p>
                    </div>
                </div>
            </div>
            
            <form action="vincular_ordem.php?id=<?php echo htmlspecialchars($id); ?>" method="POST">
                <div class="mb-6">
                    <label for="search_estabelecimento" class="block text-sm font-medium text-gray-700 mb-1">Pesquisar Estabelecimento</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="search_estabelecimento" placeholder="Digite o nome ou CNPJ do estabelecimento">
                    </div>
                    <div id="estabelecimento_results" class="mt-2 max-h-60 overflow-y-auto rounded-md shadow-sm"></div>
                    <input type="hidden" id="estabelecimento_id" name="estabelecimento_id" required>
                    <p class="mt-1 text-xs text-gray-500">Digite pelo menos 3 caracteres para iniciar a busca</p>
                </div>
                
                <div class="mb-6">
                    <label for="processo_id" class="block text-sm font-medium text-gray-700 mb-1">Processo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-file-alt text-gray-400"></i>
                        </div>
                        <select class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 appearance-none" id="processo_id" name="processo_id" required>
                            <option value="">Selecione um processo</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Selecione um estabelecimento para ver os processos disponíveis</p>
                </div>
                
                <div class="flex justify-between pt-4 border-t border-gray-200">
                    <a href="listar_ordens.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <i class="fas fa-arrow-left mr-2"></i> Voltar
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <i class="fas fa-link mr-2"></i> Vincular
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Indicador de carregamento
        const loadingIndicator = '<div class="p-3 text-center text-gray-500"><i class="fas fa-circle-notch fa-spin mr-2"></i> Buscando...</div>';
        let searchTimeout;
        
        // Inicializar select de processo com estilo desabilitado
        $('#processo_id').prop('disabled', true);
        
        // Search establishments by name or CNPJ
        $('#search_estabelecimento').on('keyup', function() {
            const query = $(this).val();
            clearTimeout(searchTimeout);
            
            if (query.length > 2) {
                // Limpar qualquer mensagem ou resultado da pesquisa anterior
                $('#estabelecimento_results').html(loadingIndicator);
                
                // Definir um delay para evitar muitas requisições durante a digitação
                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: 'search_estabelecimento.php',
                        type: 'GET',
                        data: {
                            search: query
                        },
                        success: function(data) {
                            $('#estabelecimento_results').html(data);
                            if ($('#estabelecimento_results').html().trim() !== '') {
                                // Adicionar classe border apenas quando há resultados
                                $('#estabelecimento_results').addClass('border border-gray-200 rounded-md');
                            } else {
                                $('#estabelecimento_results').removeClass('border border-gray-200 rounded-md');
                            }
                        },
                        error: function() {
                            $('#estabelecimento_results').html('<div class="px-4 py-3 text-sm text-red-700 bg-red-50"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>Erro ao buscar estabelecimentos.</div>');
                        }
                    });
                }, 300); // 300ms de delay
            } else {
                $('#estabelecimento_results').html('').removeClass('border border-gray-200 rounded-md');
            }
        });

        // Select an establishment from the search results
        $(document).on('click', '.estabelecimento-item', function() {
            const estabelecimento_id = $(this).data('id');
            const estabelecimento_name = $(this).find('.font-medium').text().trim();
            const estabelecimento_cnpj = $(this).find('.text-xs').text().trim();
            
            // Atualizar o campo de entrada e o ID oculto
            $('#search_estabelecimento').val(estabelecimento_name + ' - ' + estabelecimento_cnpj);
            $('#estabelecimento_id').val(estabelecimento_id);
            $('#estabelecimento_results').html('').removeClass('border border-gray-200 rounded-md');
            
            // Mensagem de carregamento para o select de processos
            $('#processo_id').html('<option value="">Carregando processos...</option>');
            $('#processo_id').prop('disabled', true);

            // Fetch processes for the selected establishment
            if (estabelecimento_id) {
                $.ajax({
                    url: 'get_processos.php',
                    type: 'GET',
                    data: {
                        estabelecimento_id: estabelecimento_id
                    },
                    success: function(data) {
                        $('#processo_id').html(data).prop('disabled', false);
                        
                        // Adicionar animação sutil para chamar atenção
                        $('#processo_id').parent().addClass('pulse-animation');
                        setTimeout(function() {
                            $('#processo_id').parent().removeClass('pulse-animation');
                        }, 1000);
                    },
                    error: function() {
                        $('#processo_id').html('<option value="">Erro ao carregar processos</option>');
                    }
                });
            }
        });
        
        // Adicionar validação do formulário
        $('form').on('submit', function(e) {
            if ($('#estabelecimento_id').val() === '' || $('#processo_id').val() === '') {
                e.preventDefault();
                const errorMessage = '<div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert"><div class="flex"><div class="flex-shrink-0"><i class="fas fa-exclamation-circle text-red-500"></i></div><div class="ml-3"><p class="text-sm">Por favor, selecione um estabelecimento e um processo.</p></div></div></div>';
                
                // Verificar se já existe uma mensagem de erro
                if ($('.bg-red-100').length === 0) {
                    $(this).prepend(errorMessage);
                    
                    // Scroll para a mensagem de erro
                    $('html, body').animate({
                        scrollTop: $('.bg-red-100').offset().top - 100
                    }, 200);
                }
            }
        });
    });
</script>

<style>
    /* Animação para o select de processos */
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5); }
        70% { box-shadow: 0 0 0 6px rgba(59, 130, 246, 0); }
        100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    }
    
    .pulse-animation {
        animation: pulse 1s ease-in-out;
    }
</style>