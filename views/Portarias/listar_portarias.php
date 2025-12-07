<?php
session_start();

// Verificar se o usuário está logado e tem permissão (níveis 1, 2, 3)
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header('Location: ../Usuario/login.php');
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Portaria.php';

$portaria = new Portaria($conn);

// Processar ações
if (isset($_GET['action'])) {
    $id = $_GET['id'] ?? null;
    
    switch ($_GET['action']) {
        case 'toggle_status':
            if ($id) {
                $portaria_data = $portaria->getPortariaById($id);
                $novo_status = ($portaria_data['status'] == 'ativo') ? 'inativo' : 'ativo';
                if ($portaria->alterarStatus($id, $novo_status)) {
                    $_SESSION['mensagem_sucesso'] = 'Status alterado com sucesso!';
                }
            }
            break;
            
        case 'delete':
            if ($id && $portaria->deletePortaria($id)) {
                $_SESSION['mensagem_sucesso'] = 'Portaria excluída com sucesso!';
            }
            break;
    }
    
    header('Location: listar_portarias.php');
    exit();
}

$portarias = $portaria->getAllPortarias();

// Incluir o header após processar as ações
include '../header.php';
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <!-- Mensagens de Feedback -->
    <?php if (isset($_SESSION['mensagem_sucesso'])): ?>
    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-700"><?php echo $_SESSION['mensagem_sucesso']; ?></p>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['mensagem_sucesso']); ?>
    <?php endif; ?>

    <!-- Cabeçalho da Página -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-3 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                        </svg>
                        Gerenciar Documentos e Portarias
                    </h1>
                    <p class="text-gray-600 mt-1">Gerencie as portarias exibidas no site público</p>
                </div>
                <a href="cadastrar_portaria.php" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nova Portaria
                </a>
            </div>
        </div>
    </div>

    <!-- Lista de Portarias -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
        <?php if (empty($portarias)): ?>
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma portaria cadastrada</h3>
            <p class="mt-1 text-sm text-gray-500">Comece criando uma nova portaria para exibir no site.</p>
            <div class="mt-6">
                <a href="cadastrar_portaria.php" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nova Portaria
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Portaria
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Número
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Data Publicação
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ordem
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($portarias as $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($item['titulo']); ?>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    <?php echo htmlspecialchars(substr($item['subtitulo'], 0, 100)) . (strlen($item['subtitulo']) > 100 ? '...' : ''); ?>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    Por: <?php echo htmlspecialchars($item['usuario_nome'] ?? 'N/A'); ?>
                                    em <?php echo date('d/m/Y H:i', strtotime($item['data_criacao'])); ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 font-mono">
                                <?php echo htmlspecialchars($item['numero_portaria']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo $item['data_publicacao'] ? date('d/m/Y', strtotime($item['data_publicacao'])) : 'N/A'; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $item['status'] == 'ativo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($item['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo $item['ordem_exibicao']; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <!-- Visualizar PDF -->
                                <a href="<?php echo htmlspecialchars($item['arquivo_pdf']); ?>" 
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-900 p-1 rounded-full hover:bg-blue-50"
                                   title="Visualizar PDF">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                                
                                <!-- Editar -->
                                <a href="editar_portaria.php?id=<?php echo $item['id']; ?>" 
                                   class="text-indigo-600 hover:text-indigo-900 p-1 rounded-full hover:bg-indigo-50"
                                   title="Editar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                </a>
                                
                                <!-- Toggle Status -->
                                <a href="listar_portarias.php?action=toggle_status&id=<?php echo $item['id']; ?>" 
                                   class="<?php echo $item['status'] == 'ativo' ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?> p-1 rounded-full hover:bg-gray-50"
                                   onclick="return confirm('Tem certeza que deseja alterar o status desta portaria?')"
                                   title="<?php echo $item['status'] == 'ativo' ? 'Desativar' : 'Ativar'; ?>">
                                    <?php if ($item['status'] == 'ativo'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                                    </svg>
                                    <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <?php endif; ?>
                                </a>
                                
                                <!-- Excluir -->
                                <a href="listar_portarias.php?action=delete&id=<?php echo $item['id']; ?>" 
                                   class="text-red-600 hover:text-red-900 p-1 rounded-full hover:bg-red-50"
                                   onclick="return confirm('Tem certeza que deseja excluir esta portaria? Esta ação não pode ser desfeita.')"
                                   title="Excluir">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../footer.php'; ?> 