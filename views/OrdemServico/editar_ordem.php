<?php
session_start();
ob_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

$ordemServico = new OrdemServico($conn);
$nivel_acesso = $_SESSION['user']['nivel_acesso'];

if (!isset($_GET['id'])) {
    echo "ID da ordem de serviço não fornecido!";
    exit();
}

$id = $_GET['id'];

// Verificar se o usuário tem permissão para acessar esta ordem baseado no município
$municipioUsuario = $_SESSION['user']['municipio'];
if (!$ordemServico->podeAcessarOrdem($id, $municipioUsuario)) {
    header("Location: listar_ordens.php?error=Acesso negado. Você não tem permissão para editar esta ordem de serviço.");
    exit();
}

$ordem = $ordemServico->getOrdemById($id);

if (!$ordem) {
    echo "Ordem de serviço não encontrada!";
    exit();
}

// Verifica se a ordem de serviço está finalizada
if (isset($ordem['status']) && $ordem['status'] == 'finalizada') {
    header("Location: detalhes_ordem.php?id=$id&error=Não é possível editar uma ordem de serviço finalizada.");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupera os valores atuais para campos que não podem ser editados pelos usuários nível 4
    if ($nivel_acesso == 4) {
        $data_inicio = $ordem['data_inicio'];
        $data_fim = $ordem['data_fim'];
        $tecnicos_ids = json_decode($ordem['tecnicos'], true);
        $tecnicos = $ordem['tecnicos']; // Manter o JSON original
        $pdf_path = $ordem['pdf_path'];
        $estabelecimento_id = $ordem['estabelecimento_id'];
        $processo_id = $ordem['processo_id'];
        $observacao = $ordem['observacao'];
        
        // Apenas as ações executadas podem ser modificadas
        $acoes_executadas = $_POST['acoes_executadas'];
    } else {
        // Para níveis 1 e 3, todos os campos podem ser editados
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'];
        $acoes_executadas = $_POST['acoes_executadas'];
        $tecnicos_ids = $_POST['tecnicos'];
        $tecnicos = json_encode($tecnicos_ids);
        $pdf_path = $ordem['pdf_path']; // Assumindo que o caminho do PDF não muda
        $estabelecimento_id = $_POST['estabelecimento_id'];
        $processo_id = $_POST['processo_id'];
        $observacao = $_POST['observacao'];
    }

    $status = $ordem['status']; // Usar o status atual, pois o campo está desabilitado

    // Atualizar o PDF path se necessário
    // Adição de depuração
    error_log("Debug: Data Início - $data_inicio, Data Fim - $data_fim, Ações Executadas - " . json_encode($acoes_executadas) . ", Técnicos - $tecnicos, PDF Path - $pdf_path, Estabelecimento ID - $estabelecimento_id, Processo ID - $processo_id, Observação - $observacao");

    if ($ordemServico->update($id, $data_inicio, $data_fim, $acoes_executadas, $tecnicos, $pdf_path, $estabelecimento_id, $processo_id, $observacao)) {
        header("Location: detalhes_ordem.php?id=$id&success=Ordem de serviço atualizada com sucesso.");
        exit();
    } else {
        $error = "Erro ao atualizar a ordem de serviço: " . $ordemServico->getLastError();
    }
}


// Obter usuários técnicos do mesmo município e com nível de acesso 3 ou 4
$municipio_usuario = $_SESSION['user']['municipio'];
$query = "SELECT id, nome_completo FROM usuarios WHERE nivel_acesso IN (3, 4) AND municipio = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $municipio_usuario);
$stmt->execute();
$result = $stmt->get_result();
$tecnicos = $result->fetch_all(MYSQLI_ASSOC);

// Obter tipos de ações executadas
$tipos_acoes_executadas = $ordemServico->getTiposAcoesExecutadas();

// Decodifica tecnicos e garante que seja um array
$ordem_tecnicos = json_decode($ordem['tecnicos']);
if (!is_array($ordem_tecnicos)) {
    $ordem_tecnicos = [];
}

// Decodifica acoes_executadas e garante que seja um array
$ordem_acoes_executadas = json_decode($ordem['acoes_executadas'], true);
if (!is_array($ordem_acoes_executadas)) {
    $ordem_acoes_executadas = [];
}
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-blue-100 to-blue-50">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Editar Ordem de Serviço</h2>
                <span class="text-gray-500 text-sm">ID: <?php echo htmlspecialchars($id); ?></span>
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
            <form action="editar_ordem.php?id=<?php echo htmlspecialchars($id); ?>" method="POST">
                <input type="hidden" name="ordem_id" value="<?php echo htmlspecialchars($id); ?>">
                <input type="hidden" name="estabelecimento_id" value="<?php echo htmlspecialchars($ordem['estabelecimento_id']); ?>">
                <input type="hidden" name="processo_id" value="<?php echo htmlspecialchars($ordem['processo_id']); ?>">
                
                <!-- Informações do processo -->
                <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm">Você está editando a ordem de serviço #<?php echo htmlspecialchars($id); ?>. 
                            <?php if ($nivel_acesso == 4) : ?>
                                <span class="font-semibold">Como técnico, você só pode modificar as ações executadas.</span>
                            <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar-alt text-gray-400"></i>
                            </div>
                            <input type="date" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 <?php echo ($nivel_acesso == 4) ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($ordem['data_inicio']); ?>" required <?php echo ($nivel_acesso == 4) ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($nivel_acesso == 4) : ?>
                            <p class="mt-1 text-xs text-gray-500">Como técnico, você não pode alterar esta data</p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar-check text-gray-400"></i>
                            </div>
                            <input type="date" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 <?php echo ($nivel_acesso == 4) ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($ordem['data_fim']); ?>" required <?php echo ($nivel_acesso == 4) ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="acoes_executadas" class="block text-sm font-medium text-gray-700 mb-1">Ações Executadas</label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <i class="fas fa-tasks text-gray-400"></i>
                        </div>
                        <select class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="acoes_executadas" name="acoes_executadas[]" multiple required>
                            <?php foreach ($tipos_acoes_executadas as $tipo) : ?>
                                <option value="<?php echo htmlspecialchars($tipo['id']); ?>" <?php echo in_array($tipo['id'], $ordem_acoes_executadas) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="mt-1 text-xs text-gray-500"><i class="fas fa-info-circle mr-1"></i> Segure CTRL para selecionar múltiplas ações.</p>
                </div>

                <div class="mb-6">
                    <label for="tecnicos" class="block text-sm font-medium text-gray-700 mb-1">Técnicos</label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <i class="fas fa-user-md text-gray-400"></i>
                        </div>
                        <select class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 <?php echo ($nivel_acesso == 4) ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" id="tecnicos" name="tecnicos[]" multiple required <?php echo ($nivel_acesso == 4) ? 'disabled' : ''; ?>>
                            <?php foreach ($tecnicos as $tecnico) : ?>
                                <option value="<?php echo htmlspecialchars($tecnico['id']); ?>" <?php echo in_array($tecnico['id'], $ordem_tecnicos) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tecnico['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="mt-1 text-xs text-gray-500"><i class="fas fa-info-circle mr-1"></i> Segure CTRL para selecionar múltiplos técnicos.</p>
                </div>

                <div class="mb-4">
                    <label for="observacao_check" class="flex items-center text-sm font-medium text-gray-700 cursor-pointer <?php echo ($nivel_acesso == 4) ? 'opacity-70 cursor-not-allowed' : ''; ?>">
                        <input type="checkbox" id="observacao_check" onclick="toggleObservacao()" <?php echo !empty($ordem['observacao']) ? 'checked' : ''; ?> <?php echo ($nivel_acesso == 4) ? 'disabled' : ''; ?> class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 rounded border-gray-300">
                        Incluir Observação
                    </label>
                </div>

                <div id="observacao_container" class="mb-6 transition-all duration-300" style="display: <?php echo !empty($ordem['observacao']) ? 'block' : 'none'; ?>;">
                    <label for="observacao" class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <i class="fas fa-comment-alt text-gray-400"></i>
                        </div>
                        <textarea class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 <?php echo ($nivel_acesso == 4) ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" id="observacao" name="observacao" rows="4" <?php echo ($nivel_acesso == 4) ? 'disabled' : ''; ?>><?php echo isset($ordem['observacao']) ? htmlspecialchars($ordem['observacao']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-flag text-gray-400"></i>
                        </div>
                        <select class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-100 cursor-not-allowed" id="status" name="status" required disabled>
                            <option value="ativa" <?php echo (isset($ordem['status']) && $ordem['status'] == 'ativa') ? 'selected' : ''; ?>>Ativa</option>
                            <option value="finalizada" <?php echo (isset($ordem['status']) && $ordem['status'] == 'finalizada') ? 'selected' : ''; ?>>Finalizada</option>
                        </select>
                    </div>
                    <p class="mt-1 text-xs text-gray-500"><i class="fas fa-info-circle mr-1"></i> O status só pode ser alterado na página de detalhes da ordem.</p>
                </div>
                
                <div class="flex justify-between pt-4 border-t border-gray-200">
                    <a href="listar_ordens.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <i class="fas fa-arrow-left mr-2"></i> Voltar
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <i class="fas fa-save mr-2"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleObservacao() {
        const container = document.getElementById('observacao_container');
        const isHidden = container.style.display === 'none';
        
        // Usar animação para exibir/ocultar
        if (isHidden) {
            container.style.display = 'block';
            container.style.opacity = '0';
            container.style.maxHeight = '0';
            setTimeout(() => {
                container.style.opacity = '1';
                container.style.maxHeight = '200px';
            }, 10);
        } else {
            container.style.opacity = '0';
            container.style.maxHeight = '0';
            setTimeout(() => {
                container.style.display = 'none';
            }, 300); // Tempo da transição
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const dataInicio = document.getElementById('data_inicio');
        const dataFim = document.getElementById('data_fim');
        const acoesSelect = document.getElementById('acoes_executadas');
        const tecnicosSelect = document.getElementById('tecnicos');
        
        // Validação da data antes de enviar o formulário
        form.addEventListener('submit', function(event) {
            if (dataFim.value && dataInicio.value && dataFim.value < dataInicio.value) {
                event.preventDefault();
                // Exibir mensagem de erro moderna em vez de alert
                showError('A data de fim não pode ser anterior à data de início.');
                // Destacar os campos com problema
                dataInicio.classList.add('border-red-500', 'ring-red-300');
                dataFim.classList.add('border-red-500', 'ring-red-300');
                scrollToError();
            }
            
            // Validar que pelo menos uma ação foi selecionada
            if (acoesSelect.selectedOptions.length === 0) {
                event.preventDefault();
                showError('Selecione pelo menos uma ação executada.');
                acoesSelect.classList.add('border-red-500', 'ring-red-300');
                scrollToError();
            }
            
            // Validar que pelo menos um técnico foi selecionado (se o campo não estiver desabilitado)
            if (!tecnicosSelect.disabled && tecnicosSelect.selectedOptions.length === 0) {
                event.preventDefault();
                showError('Selecione pelo menos um técnico.');
                tecnicosSelect.classList.add('border-red-500', 'ring-red-300');
                scrollToError();
            }
        });
        
        // Remover destaque de erro ao alterar valores
        dataInicio.addEventListener('change', function() {
            this.classList.remove('border-red-500', 'ring-red-300');
            dataFim.classList.remove('border-red-500', 'ring-red-300');
            removeError();
        });
        
        dataFim.addEventListener('change', function() {
            this.classList.remove('border-red-500', 'ring-red-300');
            dataInicio.classList.remove('border-red-500', 'ring-red-300');
            removeError();
        });
        
        acoesSelect.addEventListener('change', function() {
            this.classList.remove('border-red-500', 'ring-red-300');
            removeError();
        });
        
        tecnicosSelect.addEventListener('change', function() {
            this.classList.remove('border-red-500', 'ring-red-300');
            removeError();
        });
        
        // Melhorar a experiência dos selects múltiplos
        [acoesSelect, tecnicosSelect].forEach(select => {
            if (!select.disabled) {
                select.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-blue-300');
                });
                
                select.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-blue-300');
                });
            }
        });
        
        // Função para exibir mensagem de erro
        function showError(message) {
            // Verificar se já existe uma mensagem de erro
            let errorDiv = document.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded';
                errorDiv.style.opacity = '0';
                errorDiv.innerHTML = `
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm error-text">${message}</p>
                        </div>
                    </div>
                `;
                
                // Inserir no início do formulário
                const firstChild = form.firstChild;
                form.insertBefore(errorDiv, firstChild);
                
                // Animar a entrada
                setTimeout(() => {
                    errorDiv.style.opacity = '1';
                    errorDiv.style.transition = 'opacity 0.3s ease';
                }, 10);
            } else {
                errorDiv.querySelector('.error-text').textContent = message;
            }
        }
        
        // Função para remover mensagem de erro
        function removeError() {
            const errorDiv = document.querySelector('.error-message');
            if (errorDiv) {
                errorDiv.style.opacity = '0';
                setTimeout(() => {
                    errorDiv.remove();
                }, 300);
            }
        }
        
        // Função para rolar até o erro
        function scrollToError() {
            const errorDiv = document.querySelector('.error-message');
            if (errorDiv) {
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        // Destaque visual para os selects múltiplos
        document.querySelectorAll('select[multiple] option').forEach(option => {
            option.addEventListener('mouseenter', function() {
                this.classList.add('bg-blue-50');
            });
            
            option.addEventListener('mouseleave', function() {
                if (!this.selected) {
                    this.classList.remove('bg-blue-50');
                }
            });
        });
    });
</script>

<!-- Estilos adicionais para melhorar a aparência dos selects múltiplos -->
<style>
    /* Animação para o container de observação */
    #observacao_container {
        transition: opacity 0.3s ease, max-height 0.3s ease;
        overflow: hidden;
    }
    
    /* Estilo para os selects múltiplos */
    select[multiple] {
        height: auto;
        min-height: 120px;
    }
    
    select[multiple] option {
        padding: 8px 12px;
        margin-bottom: 1px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    
    select[multiple] option:checked,
    select[multiple] option:hover {
        background-color: rgba(59, 130, 246, 0.1);
        color: #1e40af;
    }
    
    /* Animação para mensagem de erro */
    .error-message {
        transition: opacity 0.3s ease;
    }
</style>

<?php include '../footer.php'; ?>
