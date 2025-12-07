<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
// 1 Administrador, 2 Suporte, 3 Gerente, 4 Fiscal
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado ou não for administrador
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Processo.php';

$estabelecimento = new Estabelecimento($conn);
$processo = new Processo($conn);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $dadosEstabelecimento = $estabelecimento->findById($id);

    if (!$dadosEstabelecimento) {
        echo "Estabelecimento não encontrado!";
        exit();
    }

    // Verificar se o usuário tem permissão para acessar o estabelecimento
    $usuarioMunicipio = $_SESSION['user']['municipio'];
    $nivel_acesso = $_SESSION['user']['nivel_acesso'];

    if ($nivel_acesso != 1 && $dadosEstabelecimento['municipio'] !== $usuarioMunicipio) {
        header("Location: ../Estabelecimento/listar_estabelecimentos.php?error=" . urlencode("Você não tem permissão para acessar este estabelecimento."));
        exit();
    }

    // Buscar os processos associados ao estabelecimento
    $processos = $processo->getProcessosByEstabelecimento($id);
    
    // Buscar os grupos de risco associados ao estabelecimento
    $gruposRisco = $estabelecimento->getGruposRiscoByEstabelecimento($id);
} else {
    echo "ID do estabelecimento não fornecido!";
    exit();
}
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="flex flex-col md:flex-row gap-6">

        <!-- Sidebar Menu -->
        <div class="w-full md:w-1/4">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3">
                    <h5 class="text-white font-medium text-lg">Menu</h5>
                </div>
                <div class="divide-y divide-gray-200">
                    <a href="../Estabelecimento/detalhes_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-info-circle mr-3 text-gray-500"></i>Detalhes
                    </a>
                    <a href="../Estabelecimento/editar_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-edit mr-3 text-gray-500"></i>Editar
                    </a>
                    <a href="processos_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150 text-blue-800 font-medium">
                        <i class="fas fa-folder-open mr-3 text-blue-500"></i>Processos
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3 flex justify-between items-center">
                    <h5 class="text-white font-medium text-lg">Processos de <?php echo htmlspecialchars($dadosEstabelecimento['nome']); ?></h5>
                    <?php if (in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) : ?>
                        <button type="button" class="bg-white text-blue-800 hover:bg-blue-50 text-sm font-medium py-1.5 px-3 rounded transition-colors duration-150" onclick="openNovoProcessoModal()">
                            <i class="fas fa-plus mr-1"></i>Novo Processo
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Exibição dos Grupos de Risco -->
                <div class="p-4 bg-gray-50 border-b">
                    <h6 class="font-medium text-gray-700 mb-2">Grupos de Risco:</h6>
                    <?php if (!empty($gruposRisco)) : ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($gruposRisco as $grupo) : ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                    <?php 
                                    // Atribuir cores diferentes com base no grupo de risco
                                    if (strpos($grupo['grupo_risco'], '1') !== false) echo 'bg-green-100 text-green-800';
                                    elseif (strpos($grupo['grupo_risco'], '2') !== false) echo 'bg-yellow-100 text-yellow-800';
                                    elseif (strpos($grupo['grupo_risco'], '3') !== false) echo 'bg-red-100 text-red-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <?php echo htmlspecialchars($grupo['grupo_risco']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="text-sm text-gray-600">Este estabelecimento não está classificado em nenhum grupo de risco.</p>
                    <?php endif; ?>
                </div>
                
                <div class="p-5">
                    <?php if (empty($processos)) : ?>
                        <div class="flex items-center p-4 bg-blue-50 text-blue-800 rounded-md">
                            <i class="fas fa-info-circle mr-2"></i>
                            <p class="text-sm">Nenhum processo registrado para este estabelecimento.</p>
                        </div>
                    <?php else : ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Abertura</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($processos as $proc) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($proc['numero_processo']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?php echo htmlspecialchars($proc['tipo_processo']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?php echo date('d/m/Y', strtotime($proc['data_abertura'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $proc['status'] == 'ATIVO' ? 'bg-green-100 text-green-800' : 
                                                        ($proc['status'] == 'ARQUIVADO' ? 'bg-gray-100 text-gray-800' : 
                                                        'bg-yellow-100 text-yellow-800'); ?>">
                                                    <?php echo htmlspecialchars($proc['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="documentos.php?processo_id=<?php echo $proc['id']; ?>&id=<?php echo $id; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($_SESSION['user']['nivel_acesso'] == 1) : ?>
                                                    <a href="#" onclick="confirmarExclusao(<?php echo $proc['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Novo Processo -->
<div id="novoProcessoModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="flex flex-col">
            <!-- Modal Header -->
            <div class="flex justify-between items-center pb-3 border-b">
                <h5 class="text-lg font-medium text-gray-800">Novo Processo</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeNovoProcessoModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="py-4">
                <form id="novoProcessoForm" action="../../controllers/ProcessoController.php?action=create" method="POST">
                    <input type="hidden" name="estabelecimento_id" value="<?php echo $id; ?>">
                    
                    <div class="mb-4">
                        <label for="tipo_processo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Processo</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                id="tipo_processo" name="tipo_processo" required>
                            <option value="">Selecione o tipo</option>
                            <option value="LICENCIAMENTO">Licenciamento</option>
                            <option value="ADMINISTRATIVO">Administrativo</option>
                            <option value="DENÚNCIA">Denúncia</option>
                        </select>
                        <p class="mt-1 text-xs text-yellow-600 italic" id="nota_licenciamento" style="display: none;">
                            <i class="fas fa-exclamation-circle mr-1"></i>Nota: Apenas um processo de licenciamento é permitido por ano.
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="data_abertura" class="block text-sm font-medium text-gray-700 mb-1">Data de Abertura</label>
                        <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-50" 
                               id="data_abertura" name="data_abertura" required value="<?php echo date('Y-m-d'); ?>" readonly>
                        <p class="mt-1 text-xs text-gray-500 italic">
                            <i class="fas fa-info-circle mr-1"></i>A data de abertura é automaticamente definida para a data atual.
                        </p>
                    </div>
                    
                    <div class="mb-4" id="campoAnoLicenciamento" style="display: none;">
                        <label for="ano_licenciamento" class="block text-sm font-medium text-gray-700 mb-1">Ano de Licenciamento</label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="ano_licenciamento" name="ano_licenciamento" min="2000" max="2100" value="<?php echo date('Y'); ?>">
                    </div>
                    
                    <div class="mt-6 text-right">
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-md transition-colors duration-150 mr-2" 
                                onclick="closeNovoProcessoModal()">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-150">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
    // Funções para controlar o modal
    function openNovoProcessoModal() {
        document.getElementById('novoProcessoModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Impedir rolagem do body
    }
    
    function closeNovoProcessoModal() {
        document.getElementById('novoProcessoModal').classList.add('hidden');
        document.body.style.overflow = 'auto'; // Restaurar rolagem do body
    }
    
    // Mostrar/ocultar o campo de ano de licenciamento dependendo do tipo de processo
    document.getElementById('tipo_processo').addEventListener('change', function() {
        if (this.value === 'LICENCIAMENTO') {
            document.getElementById('campoAnoLicenciamento').style.display = 'block';
            document.getElementById('nota_licenciamento').style.display = 'block';
        } else {
            document.getElementById('campoAnoLicenciamento').style.display = 'none';
            document.getElementById('nota_licenciamento').style.display = 'none';
        }
    });
    
    // Função para confirmar exclusão de processo
    function confirmarExclusao(processoId) {
        if (confirm('Tem certeza que deseja excluir este processo? Esta ação não pode ser desfeita.')) {
            window.location.href = `../../controllers/ProcessoController.php?action=delete&id=${processoId}&return_id=<?php echo $id; ?>`;
        }
    }
</script> 