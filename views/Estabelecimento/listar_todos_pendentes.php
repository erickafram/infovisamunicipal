<?php
session_start();
include '../header.php'; // Caminho ajustado

// Verificação de autenticação
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php'; // Caminho ajustado
require_once '../../models/Estabelecimento.php'; // Caminho ajustado

$municipioUsuario = $_SESSION['user']['municipio'];

$estabelecimentoModel = new Estabelecimento($conn);

// Configurações de paginação
$limit = 10; // Registros por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Busca
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Obter estabelecimentos com busca e paginação
$totalEstabelecimentos = $estabelecimentoModel->countEstabelecimentosPendentes($municipioUsuario, $search);
$estabelecimentosPendentes = $estabelecimentoModel->getEstabelecimentosPendentesPaginated($municipioUsuario, $search, $limit, $offset);

$totalPages = ceil($totalEstabelecimentos / $limit);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estabelecimentos Pendentes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Estabelecimentos Pendentes</h1>
                    <p class="text-sm text-gray-600 mt-1">Gerencie os estabelecimentos aguardando aprovação</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?php echo $totalEstabelecimentos; ?> pendente(s)
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" action="" class="space-y-4 md:space-y-0 md:flex md:items-end md:space-x-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        Buscar estabelecimento
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               name="search" 
                               id="search"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Digite o nome fantasia ou tipo..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <button type="submit" 
                        class="w-full md:w-auto inline-flex items-center justify-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>
                    Buscar
                </button>
                <?php if (!empty($search)): ?>
                <a href="?" 
                   class="w-full md:w-auto inline-flex items-center justify-center px-6 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>
                    Limpar
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nome Fantasia
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($estabelecimentosPendentes)) : ?>
                            <?php foreach ($estabelecimentosPendentes as $estabelecimento) : ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $estabelecimento['tipo_pessoa'] == 'fisica' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($estabelecimento['tipo_pessoa'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <!-- Atividades -->
                                            <button type="button"
                                                    class="inline-flex items-center p-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                                    onclick="openModal('atividadesModal', <?php echo htmlspecialchars($estabelecimento['id']); ?>)"
                                                    title="Ver atividades">
                                                <i class="fas fa-tasks text-gray-600"></i>
                                            </button>

                                            <!-- Visualizar -->
                                            <?php if($estabelecimento['tipo_pessoa'] == 'fisica'): ?>
                                            <a href="../Estabelecimento/detalhes_pessoa_fisica.php?id=<?php echo htmlspecialchars($estabelecimento['id']); ?>" 
                                               class="inline-flex items-center p-2 border border-blue-300 rounded-md text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                               title="Visualizar detalhes">
                                                <i class="far fa-eye"></i>
                                            </a>
                                            <?php else: ?>
                                            <a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?php echo htmlspecialchars($estabelecimento['id']); ?>" 
                                               class="inline-flex items-center p-2 border border-blue-300 rounded-md text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                               title="Visualizar detalhes">
                                                <i class="far fa-eye"></i>
                                            </a>
                                            <?php endif; ?>

                                            <!-- Aprovar -->
                                            <a href="../Estabelecimento/aprovar_forcado.php?id=<?php echo htmlspecialchars($estabelecimento['id']); ?>" 
                                               class="inline-flex items-center p-2 border border-green-300 rounded-md text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                               onclick="return confirm('Tem certeza que deseja aprovar este estabelecimento?');"
                                               title="Aprovar estabelecimento">
                                                <i class="fas fa-check"></i>
                                            </a>

                                            <!-- Rejeitar -->
                                            <button type="button"
                                                    class="inline-flex items-center p-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                                                    onclick="openRejectModal(<?php echo htmlspecialchars($estabelecimento['id']); ?>)"
                                                    title="Rejeitar estabelecimento">
                                                <i class="fas fa-times"></i>
                                            </button>


                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum estabelecimento encontrado</h3>
                                        <p class="text-gray-500">Tente ajustar os filtros de busca ou verifique novamente mais tarde.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <!-- Mobile pagination -->
                        <?php if ($page > 1): ?>
                        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Anterior
                        </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Próxima
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando 
                                <span class="font-medium"><?php echo ($offset + 1); ?></span>
                                até 
                                <span class="font-medium"><?php echo min($offset + $limit, $totalEstabelecimentos); ?></span>
                                de 
                                <span class="font-medium"><?php echo $totalEstabelecimentos; ?></span>
                                resultados
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                                    <?php if ($i == $page): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                                            <?php echo $i; ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors duration-200">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Inserir Motivo de Rejeição -->
    <div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Negar Estabelecimento</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('rejectModal')">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form action="../../controllers/EstabelecimentoController.php?action=rejectEstabelecimento" method="POST" class="space-y-4">
                    <input type="hidden" name="id" id="rejectEstabelecimentoId">
                    
                    <div>
                        <label for="motivoSelect" class="block text-sm font-medium text-gray-700 mb-2">
                            Selecione o Motivo da Rejeição
                        </label>
                        <select id="motivoSelect" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione um motivo predefinido</option>
                            <option value="1" data-full-text="Estabelecimento não é de competência da VISA Municipal de {municipio}. Para dar abertura ao seu processo, acesse o site https://vigilancia-to.com.br  ou entre em contato com a Vigilância Sanitária Estadual através do número (63) 3218-3264.">
                                Competência estadual
                            </option>
                            <option value="2" data-full-text="Nenhum cnae compatível com a portaria n°0272/2024 de 10 de setembro de 2024( Publicado no Diário Oficial do Município de Gurupi n° 1083).">
                                Nenhum cnae compatível com a portaria
                            </option>
                            <option value="3" data-full-text="">Escrever Motivo</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="motivo" class="block text-sm font-medium text-gray-700 mb-2">
                            Motivo da Rejeição
                        </label>
                        <textarea id="motivo" 
                                  name="motivo" 
                                  rows="5" 
                                  required
                                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Digite o motivo da rejeição..."></textarea>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3 pt-4">
                        <button type="button" 
                                onclick="closeModal('rejectModal')"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                            Negar Estabelecimento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Atividades -->
    <div id="atividadesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between pb-3">
                    <h3 id="atividadesModalLabel" class="text-lg font-bold text-gray-900">Atividades</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('atividadesModal')">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="atividadesContent" class="max-h-96 overflow-y-auto">
                    <!-- Conteúdo das atividades será carregado aqui via AJAX -->
                    <div class="flex items-center justify-center py-8">
                        <div class="flex items-center space-x-2">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                            <span class="text-gray-600">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end pt-4">
                    <button type="button" 
                            onclick="closeModal('atividadesModal')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funções para controle dos modais
        function openModal(modalId, estabelecimentoId = null) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            if (modalId === 'atividadesModal' && estabelecimentoId) {
                loadAtividades(estabelecimentoId);
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openRejectModal(estabelecimentoId) {
            document.getElementById('rejectEstabelecimentoId').value = estabelecimentoId;
            openModal('rejectModal');
        }

        function loadAtividades(estabelecimentoId) {
            const atividadesContent = document.getElementById('atividadesContent');
            const modalTitle = document.getElementById('atividadesModalLabel');
            
            // Reset content
            atividadesContent.innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <div class="flex items-center space-x-2">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        <span class="text-gray-600">Carregando...</span>
                    </div>
                </div>
            `;

            // Determinar tipo de pessoa pela tabela
            const row = document.querySelector(`button[onclick*="${estabelecimentoId}"]`).closest('tr');
            const tipoPessoa = row.querySelector('td:nth-child(2) span').textContent.trim().toLowerCase();
            
            // Atualizar título do modal
            if (tipoPessoa === 'fisica') {
                modalTitle.textContent = 'Atividades da Pessoa Física';
            } else {
                modalTitle.textContent = 'Atividades do Estabelecimento';
            }

            // Fazer requisição AJAX
            fetch(`../Dashboard/get_atividades.php?id=${estabelecimentoId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao carregar as atividades.');
                    }
                    return response.text();
                })
                .then(data => {
                    atividadesContent.innerHTML = data;
                })
                .catch(error => {
                    atividadesContent.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <p class="text-red-600">Erro ao carregar atividades.</p>
                        </div>
                    `;
                    console.error(error);
                });
        }

        // Event listener para o select de motivos
        document.getElementById('motivoSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const fullText = selectedOption.getAttribute('data-full-text');
            const motivoTextarea = document.getElementById('motivo');
            const municipio = '<?php echo htmlspecialchars($municipioUsuario); ?>';
            
            if (fullText) {
                const processedText = fullText.replace('{municipio}', municipio);
                motivoTextarea.value = processedText;
            } else {
                motivoTextarea.value = '';
            }
        });

        // Fechar modal clicando fora dele
        window.addEventListener('click', function(event) {
            const rejectModal = document.getElementById('rejectModal');
            const atividadesModal = document.getElementById('atividadesModal');
            
            if (event.target === rejectModal) {
                closeModal('rejectModal');
            }
            if (event.target === atividadesModal) {
                closeModal('atividadesModal');
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal('rejectModal');
                closeModal('atividadesModal');
            }
        });
    </script>
</body>

</html>