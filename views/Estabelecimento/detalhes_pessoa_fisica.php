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

$estabelecimento = new Estabelecimento($conn);

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
        header("Location: listar_estabelecimentos.php?error=" . urlencode("Você não tem permissão para acessar este estabelecimento."));
        exit();
    }

    // Buscar as atividades (CNAEs) associadas ao estabelecimento
    $atividades = $estabelecimento->getCnaesByEstabelecimentoIdWithRisco($id);
    
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
                    <a href="detalhes_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150 text-blue-800 font-medium">
                        <i class="fas fa-info-circle mr-3 text-blue-500"></i>Detalhes
                    </a>
                    <a href="editar_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-edit mr-3 text-gray-500"></i>Editar
                    </a>
                    <a href="../Processo/processos_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-folder-open mr-3 text-gray-500"></i>Processos
                    </a>
                    <a href="usuarios_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-users mr-3 text-gray-500"></i>Usuários Vinculados
                    </a>
                </div>
            </div>
            <div class="mt-4">
                <!-- EXCLUIR ESTABELECIMENTO -->
                <?php if ($_SESSION['user']['nivel_acesso'] == 1) : // Apenas administradores podem excluir 
                ?>
                    <form method="POST" action="excluir_estabelecimento.php" onsubmit="return confirm('Você tem certeza que deseja excluir este estabelecimento?');">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-150 flex items-center justify-center">
                            <i class="fas fa-trash-alt mr-2"></i>Excluir Estabelecimento
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="w-full md:w-3/4">
            <!-- Detalhes Pessoa Física -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3 flex justify-between items-center">
                    <h5 class="text-white font-medium text-lg">Dados da Pessoa Física</h5>
                </div>
                <div class="p-5">
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">CPF:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['cpf'] ?? 'Não informado'); ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">Nome Completo:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['nome'] ?? 'Não informado'); ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">Nome Fantasia:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? 'Não informado'); ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">RG:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['rg'] ?? 'Não informado'); ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">Órgão Emissor:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['orgao_emissor'] ?? 'Não informado'); ?></span>
                            </p>
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">Endereço:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars(($dadosEstabelecimento['logradouro'] ?? 'Não informado') . ', ' . ($dadosEstabelecimento['numero'] ?? 'S/N') . ' - ' . ($dadosEstabelecimento['bairro'] ?? 'Não informado') . ', ' . ($dadosEstabelecimento['municipio'] ?? 'Não informado') . ' - ' . ($dadosEstabelecimento['uf'] ?? 'Não informado') . ', ' . ($dadosEstabelecimento['cep'] ?? 'Não informado')); ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">E-mail:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['email'] ?? 'Não informado'); ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">Telefone:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['ddd_telefone_1'] ?? 'Não informado'); ?></span>
                            </p>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-700">Data de Início de Funcionamento:</span> 
                                <span class="text-gray-600"><?php echo htmlspecialchars((new DateTime($dadosEstabelecimento['inicio_funcionamento'] ?? 'now'))->format('d/m/Y')); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card para Atividades (CNAE) -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3 flex justify-between items-center">
                    <h5 class="text-white font-medium text-lg">Atividades Cadastradas (CNAEs)</h5>
                    <button type="button" class="bg-white text-blue-800 hover:bg-blue-50 text-sm font-medium py-1.5 px-3 rounded transition-colors duration-150" onclick="openCNAEModal()">
                        <i class="fas fa-edit mr-1"></i>Editar Atividades
                    </button>
                </div>
                <div class="p-5">
                    <?php if (!empty($atividades)) : ?>
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($atividades as $atividade) : ?>
                                <li class="py-3 flex items-start">
                                    <i class="fas fa-tag text-blue-500 mt-1 mr-2"></i>
                                    <div>
                                        <span class="font-medium text-gray-700"><?php echo htmlspecialchars($atividade['cnae']); ?></span> - 
                                        <span class="text-gray-600"><?php echo htmlspecialchars($atividade['descricao']); ?></span>
                                        <?php if (!empty($atividade['grupo_risco'])) : ?>
                                            <?php 
                                            // Separar grupos de risco se houver mais de um
                                            $grupos = explode(' E ', $atividade['grupo_risco']);
                                            foreach ($grupos as $grupo) {
                                                $badgeClass = '';
                                                if (strpos($grupo, '1') !== false) {
                                                    $badgeClass = 'bg-green-100 text-green-800';
                                                } else if (strpos($grupo, '2') !== false) {
                                                    $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                } else if (strpos($grupo, '3') !== false) {
                                                    $badgeClass = 'bg-red-100 text-red-800';
                                                } else {
                                                    $badgeClass = 'bg-gray-100 text-gray-800';
                                                }
                                            ?>
                                                <span class="ml-2 px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full <?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($grupo); ?>
                                                </span>
                                            <?php } ?>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <div class="flex items-center p-4 bg-blue-50 text-blue-800 rounded-md">
                            <i class="fas fa-info-circle mr-2"></i>
                            <p class="text-sm">Nenhuma atividade cadastrada.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Card para Grupos de Risco -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3">
                    <h5 class="text-white font-medium text-lg">Grupos de Risco</h5>
                </div>
                <div class="p-5">
                    <?php if (!empty($gruposRisco)) : ?>
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($gruposRisco as $grupo) : ?>
                                <li class="py-3 flex items-start">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
                                    <div>
                                        <span class="font-medium text-gray-700"><?php echo htmlspecialchars($grupo['grupo_risco']); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <div class="flex items-center p-4 bg-blue-50 text-blue-800 rounded-md">
                            <i class="fas fa-info-circle mr-2"></i>
                            <p class="text-sm">Nenhum grupo de risco associado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar CNAEs (Tailwind CSS) -->
<div id="editarCNAEModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="flex flex-col">
            <!-- Modal Header -->
            <div class="flex justify-between items-center pb-3 border-b">
                <h5 class="text-lg font-medium text-gray-800">Editar Atividades (CNAEs)</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeCNAEModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="space-y-4 py-4">
                <!-- Campo para buscar CNAEs -->
                <div>
                    <label for="cnae_search_modal" class="block text-sm font-medium text-gray-700 mb-1">Buscar CNAE</label>
                    <div class="flex items-center gap-2">
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="cnae_search_modal" placeholder="Digite o código do CNAE">
                        <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-150" onclick="searchCNAEModal()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Resultado da busca -->
                <div id="cnae_result_modal" class="mt-4"></div>

                <!-- Campo oculto para armazenar os CNAEs selecionados -->
                <input type="hidden" id="cnaes" value='[]'>

                <!-- Lista de CNAEs já selecionados -->
                <div class="mt-4">
                    <h6 class="text-sm font-medium text-gray-700 mb-2">CNAEs Vinculados</h6>
                    <ul id="cnaes_list_modal" class="divide-y divide-gray-200 max-h-60 overflow-y-auto">
                        <!-- CNAEs adicionados no modal -->
                        <?php foreach ($atividades as $atividade) : ?>
                            <li class="py-2 flex justify-between items-center">
                                <div class="text-sm">
                                    <span class="font-medium"><?php echo htmlspecialchars($atividade['cnae']); ?></span> - 
                                    <span class="text-gray-600"><?php echo htmlspecialchars($atividade['descricao']); ?></span>
                                    <?php if (!empty($atividade['grupo_risco'])) : ?>
                                        <?php 
                                        // Separar grupos de risco se houver mais de um
                                        $grupos = explode(' E ', $atividade['grupo_risco']);
                                        foreach ($grupos as $grupo) {
                                            $badgeClass = '';
                                            if (strpos($grupo, '1') !== false) {
                                                $badgeClass = 'bg-green-100 text-green-800';
                                            } else if (strpos($grupo, '2') !== false) {
                                                $badgeClass = 'bg-yellow-100 text-yellow-800';
                                            } else if (strpos($grupo, '3') !== false) {
                                                $badgeClass = 'bg-red-100 text-red-800';
                                            } else {
                                                $badgeClass = 'bg-gray-100 text-gray-800';
                                            }
                                        ?>
                                            <span class="ml-2 px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full <?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($grupo); ?>
                                            </span>
                                        <?php } ?>
                                    <?php endif; ?>
                                </div>
                                <button class="bg-red-100 text-red-600 hover:bg-red-200 text-xs font-medium py-1 px-2 rounded transition-colors duration-150" onclick="removeCNAE(this, '<?php echo $atividade['cnae']; ?>')">
                                    <i class="fas fa-trash-alt mr-1"></i>Remover
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex justify-end gap-2 pt-3 border-t">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-md transition-colors duration-150" onclick="closeCNAEModal()">
                    Fechar
                </button>
                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-150" onclick="saveCNAEs()">
                    Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
    // Funções para controlar o modal Tailwind
    function openCNAEModal() {
        document.getElementById('editarCNAEModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Impedir rolagem do body
    }
    
    function closeCNAEModal() {
        document.getElementById('editarCNAEModal').classList.add('hidden');
        document.body.style.overflow = 'auto'; // Restaurar rolagem do body
    }
    
    // Função para buscar CNAE via API e mostrar no modal
    function searchCNAEModal() {
        let cnae_code = $('#cnae_search_modal').val().trim();
        let resultContainer = $('#cnae_result_modal');
        
        // Mostrar indicador de carregamento
        resultContainer.html('<div class="flex justify-center items-center py-3"><i class="fas fa-spinner fa-spin text-blue-500 mr-2"></i> Buscando...</div>');

        if (cnae_code.length === 7) {
            $.ajax({
                url: '../Company/api.php',
                type: 'GET',
                data: {
                    cnae: cnae_code,
                    municipio: '<?php echo $dadosEstabelecimento['municipio']; ?>', // Adicionar o município para consultar grupo de risco
                    check_risk_group: true
                },
                success: function(response) {
                    // Adaptar a resposta da API para o estilo Tailwind
                    if (response.includes('alert-success')) {
                        // Extrair o conteúdo relevante da resposta
                        let match = response.match(/CNAE encontrado:.*?<\/div>/s);
                        if (match) {
                            let content = match[0];
                            // Substituir classes Bootstrap por Tailwind
                            content = content.replace('alert alert-success', 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded');
                            
                            // Formatação melhorada da resposta, incluindo botão
                            const cnaeParts = content.match(/(\d+)\s*-\s*(.+?)<\/div>/);
                            if (cnaeParts) {
                                const cnaeId = cnaeParts[1].trim();
                                const cnaeDesc = cnaeParts[2].trim();
                                
                                // Verificar se há informação de grupo de risco na resposta
                                let grupoRisco = '';
                                const riskMatch = response.match(/grupo_risco":"([^"]+)/);
                                if (riskMatch) {
                                    grupoRisco = riskMatch[1];
                                }
                                
                                content = `
                                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded">
                                        <p class="font-medium mb-2">CNAE encontrado:</p>
                                        <p>${cnaeId} - ${cnaeDesc}</p>
                                        ${grupoRisco ? `<p class="mt-1"><span class="font-medium">Grupo de Risco:</span> ${grupoRisco}</p>` : ''}
                                        <button onclick="addCNAE('${cnaeId}', '${cnaeDesc.replace(/'/g, "\\'")}', '${grupoRisco}')" class="mt-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium py-1 px-3 rounded transition-colors duration-150">
                                            <i class="fas fa-plus mr-1"></i> Adicionar CNAE
                                        </button>
                                    </div>
                                `;
                            }
                            
                            resultContainer.html(content);
                        } else {
                            resultContainer.html('<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded">CNAE encontrado, mas não foi possível processar a resposta.</div>');
                        }
                    } else if (response.includes('alert-danger')) {
                        resultContainer.html('<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded"><i class="fas fa-exclamation-circle mr-2"></i>CNAE não encontrado.</div>');
                    } else {
                        resultContainer.html(response);
                    }
                },
                error: function() {
                    resultContainer.html('<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded"><i class="fas fa-exclamation-circle mr-2"></i>Erro ao consultar o CNAE. Tente novamente.</div>');
                }
            });
        } else {
            resultContainer.html('<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded"><i class="fas fa-exclamation-triangle mr-2"></i>Digite um código CNAE válido com 7 dígitos.</div>');
        }
    }

    // Função para adicionar o CNAE ao modal
    function addCNAE(cnaeId, cnaeDesc, grupoRisco) {
        let cnaesList = document.getElementById('cnaes_list_modal');
        let cnaeItem = document.createElement('li');
        cnaeItem.className = 'py-2 flex justify-between items-center';
        
        // Definir os badges para cada grupo de risco
        let grupoRiscoHtml = '';
        
        if (grupoRisco) {
            // Separar grupos de risco se houver mais de um
            const grupos = grupoRisco.split(' E ');
            
            for (const grupo of grupos) {
                let badgeClass = '';
                if (grupo.includes('1')) {
                    badgeClass = 'bg-green-100 text-green-800';
                } else if (grupo.includes('2')) {
                    badgeClass = 'bg-yellow-100 text-yellow-800';
                } else if (grupo.includes('3')) {
                    badgeClass = 'bg-red-100 text-red-800';
                } else {
                    badgeClass = 'bg-gray-100 text-gray-800';
                }
                
                grupoRiscoHtml += `<span class="ml-2 px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full ${badgeClass}">${grupo}</span>`;
            }
        }
            
        cnaeItem.innerHTML = `
            <div class="text-sm">
                <span class="font-medium">${cnaeId}</span> - 
                <span class="text-gray-600">${cnaeDesc}</span>
                ${grupoRiscoHtml}
            </div>
            <button class="bg-red-100 text-red-600 hover:bg-red-200 text-xs font-medium py-1 px-2 rounded transition-colors duration-150" onclick="removeCNAE(this, '${cnaeId}')">
                <i class="fas fa-trash-alt mr-1"></i>Remover
            </button>
        `;

        cnaesList.appendChild(cnaeItem);

        // Adicionar ao campo oculto
        let cnaesField = document.getElementById('cnaes');
        let currentCnaes = cnaesField.value ? JSON.parse(cnaesField.value) : [];
        currentCnaes.push({
            id: cnaeId,
            descricao: cnaeDesc,
            grupo_risco: grupoRisco
        });
        cnaesField.value = JSON.stringify(currentCnaes);
        
        // Limpar campo de busca e resultado após adicionar
        document.getElementById('cnae_search_modal').value = '';
        document.getElementById('cnae_result_modal').innerHTML = '';
        
        // Feedback visual
        let feedback = document.createElement('div');
        feedback.className = 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mt-2';
        feedback.innerHTML = `<i class="fas fa-check-circle mr-2"></i>CNAE ${cnaeId} adicionado com sucesso!`;
        document.getElementById('cnae_result_modal').appendChild(feedback);
        
        // Remover feedback após 3 segundos
        setTimeout(() => {
            feedback.style.opacity = '0';
            feedback.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                feedback.remove();
            }, 500);
        }, 3000);
    }

    // Função para remover o CNAE do modal
    function removeCNAE(element, cnaeId) {
        // Animar a remoção
        let listItem = element.closest('li');
        listItem.style.opacity = '0';
        listItem.style.transition = 'opacity 0.3s';
        
        setTimeout(() => {
            listItem.remove();
            
            // Verificar se o campo 'cnaes' existe
            let cnaesField = document.getElementById('cnaes');
            if (!cnaesField) {
                alert('Campo de CNAEs não encontrado!');
                return;
            }
    
            let currentCnaes = JSON.parse(cnaesField.value);
            cnaesField.value = JSON.stringify(currentCnaes.filter(cnae => cnae.id !== cnaeId));
        }, 300);
    }

    // Função para salvar os CNAEs selecionados
    function saveCNAEs() {
        let estabelecimentoId = <?php echo $id; ?>;
        let cnaesField = document.getElementById('cnaes');
        let cnaes = cnaesField.value ? JSON.parse(cnaesField.value) : [];
        
        // Adicionar indicador de carregamento no botão de salvar
        let saveButton = document.querySelector('#editarCNAEModal .flex.justify-end.gap-2 button:last-child');
        let originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Salvando...';
        saveButton.disabled = true;

        $.ajax({
            url: '../../controllers/EstabelecimentoController.php?action=updateCnaes',
            type: 'POST',
            data: {
                estabelecimento_id: estabelecimentoId,
                cnaes: JSON.stringify(cnaes)
            },
            success: function(response) {
                try {
                    let res = JSON.parse(response);
                    if (res.success) {
                        // Mostrar mensagem de sucesso e recarregar
                        let messageContainer = document.createElement('div');
                        messageContainer.className = 'fixed top-0 left-0 right-0 flex justify-center items-center mt-4 z-50';
                        messageContainer.innerHTML = `
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative shadow-lg max-w-md">
                                <div class="flex">
                                    <div class="py-1"><i class="fas fa-check-circle text-green-500 mr-2"></i></div>
                                    <div>
                                        <p class="font-bold">Sucesso!</p>
                                        <p class="text-sm">CNAEs atualizados com sucesso.</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(messageContainer);
                        
                        // Fechar o modal
                        closeCNAEModal();
                        
                        // Recarregar após 1 segundo
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showErrorMessage('Erro ao atualizar os CNAEs: ' + res.error);
                        saveButton.innerHTML = originalText;
                        saveButton.disabled = false;
                    }
                } catch (e) {
                    console.error("Resposta inválida", response);
                    showErrorMessage('Erro inesperado ao processar a resposta');
                    saveButton.innerHTML = originalText;
                    saveButton.disabled = false;
                }
            },
            error: function() {
                showErrorMessage('Erro de conexão ao atualizar CNAEs');
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
            }
        });
    }
    
    // Função para mostrar mensagem de erro no modal
    function showErrorMessage(message) {
        let errorContainer = document.createElement('div');
        errorContainer.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mt-3';
        errorContainer.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
        
        // Remover qualquer mensagem de erro existente
        let existingError = document.querySelector('#editarCNAEModal .bg-red-100');
        if (existingError) {
            existingError.remove();
        }
        
        // Adicionar nova mensagem de erro antes do footer
        let modalBody = document.querySelector('#editarCNAEModal .space-y-4');
        modalBody.appendChild(errorContainer);
        
        // Auto-remover após 5 segundos
        setTimeout(() => {
            errorContainer.style.opacity = '0';
            errorContainer.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                errorContainer.remove();
            }, 500);
        }, 5000);
    }
</script>