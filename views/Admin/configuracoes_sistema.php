<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';
require_once '../../models/ConfiguracaoSistema.php';

// Verificar se o usuário tem permissão de administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 1) {
    header("Location: ../../login.php");
    exit();
}

$configModel = new ConfiguracaoSistema($conn);
$mensagem = '';
$tipoMensagem = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_chat'])) {
        $chatAtivo = isset($_POST['chat_ativo']) ? 1 : 0;
        
        // Log para auditoria
        error_log("Chat status alterado para: " . ($chatAtivo ? 'ATIVO' : 'INATIVO') . " por usuário ID: " . $_SESSION['user']['id']);
        
        if ($configModel->toggleChat($chatAtivo)) {
            $mensagem = 'Configuração do chat atualizada com sucesso! Status: ' . ($chatAtivo ? 'ATIVO' : 'INATIVO');
            $tipoMensagem = 'success';
            // Limpa o cache
            ConfiguracaoSistema::limparCache();
        } else {
            $mensagem = 'Erro ao atualizar configuração do chat.';
            $tipoMensagem = 'error';
        }
    }
}

// Obter configurações atuais
$configuracoes = $configModel->listarTodas();
$chatAtivo = ConfiguracaoSistema::chatAtivo($conn);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do Sistema - InfoVISA</title>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6">
        <!-- Cabeçalho -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-cogs text-blue-600 mr-3"></i>
                        Configurações do Sistema
                    </h1>
                    <p class="text-gray-600 mt-1">Gerencie as configurações globais do InfoVISA</p>
                </div>
                <a href="../Dashboard/dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>
        </div>

        <!-- Status atual do chat (debug) -->
        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center text-sm text-blue-800">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Status atual do banco:</strong> 
                <span class="ml-2 px-2 py-1 rounded <?php echo $chatAtivo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $chatAtivo ? 'ATIVO' : 'INATIVO'; ?>
                </span>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $tipoMensagem === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <div class="flex items-center">
                <i class="fas <?php echo $tipoMensagem === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Configurações do Chat -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-comments text-blue-600 mr-2"></i>
                    Sistema de Chat
                </h2>
                
                <form method="POST">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <h3 class="font-medium text-gray-800">Chat da VISA</h3>
                                <p class="text-sm text-gray-600">Permite comunicação entre usuários internos da vigilância sanitária</p>
                            </div>
                            <div class="relative">
                                <input type="checkbox" name="chat_ativo" id="chat_ativo" 
                                       class="sr-only" <?php echo $chatAtivo ? 'checked' : ''; ?>>
                                <label for="chat_ativo" class="flex items-center cursor-pointer">
                                    <div class="relative">
                                        <div class="block bg-gray-300 w-14 h-8 rounded-full transition-colors duration-200 <?php echo $chatAtivo ? 'bg-blue-600' : ''; ?>"></div>
                                        <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform duration-200 <?php echo $chatAtivo ? 'transform translate-x-6' : ''; ?>"></div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full <?php echo $chatAtivo ? 'bg-green-500' : 'bg-red-500'; ?> mr-2"></div>
                                <span class="<?php echo $chatAtivo ? 'text-green-700' : 'text-red-700'; ?>">
                                    Status: <?php echo $chatAtivo ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="pt-4 border-t">
                            <button type="submit" name="toggle_chat" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Salvar Configurações
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Informações do Sistema -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Informações do Sistema
                </h2>
                
                <div class="space-y-3">
                    <?php foreach ($configuracoes as $config): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($config['chave']); ?></h4>
                            <?php if ($config['descricao']): ?>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($config['descricao']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <?php if ($config['tipo'] === 'boolean'): ?>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $config['valor_convertido'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $config['valor_convertido'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-sm text-gray-600"><?php echo htmlspecialchars($config['valor']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Instruções -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-6">
            <h3 class="text-lg font-semibold text-blue-800 mb-2">
                <i class="fas fa-lightbulb mr-2"></i>
                Como funciona
            </h3>
            <div class="text-blue-700 space-y-2">
                <p><strong>Chat da VISA:</strong> Quando ativado, todos os usuários internos podem utilizar o sistema de chat em tempo real.</p>
                <p><strong>Desativação:</strong> Quando desativado, o chat não aparecerá no dashboard e os usuários não poderão enviar mensagens.</p>
                <p><strong>Aplicação:</strong> As alterações são aplicadas imediatamente em todo o sistema.</p>
            </div>
        </div>
    </div>

    <style>
        /* Toggle switch styles */
        input:checked + label .block {
            background-color: #3b82f6 !important;
        }
        input:checked + label .dot {
            transform: translateX(1.5rem) !important;
        }
        
        /* Estados não checado */
        input:not(:checked) + label .block {
            background-color: #d1d5db !important;
        }
        input:not(:checked) + label .dot {
            transform: translateX(0) !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('chat_ativo');
            const statusText = document.querySelector('.flex.items-center span');
            const statusDot = document.querySelector('.w-3.h-3.rounded-full');
            
            if (toggle) {
                // Função para atualizar a interface visual
                function updateToggleUI() {
                    const isChecked = toggle.checked;
                    const label = toggle.nextElementSibling;
                    const block = label.querySelector('.block');
                    const dot = label.querySelector('.dot');
                    
                    if (isChecked) {
                        block.classList.remove('bg-gray-300');
                        block.classList.add('bg-blue-600');
                        dot.classList.add('transform', 'translate-x-6');
                        statusText.textContent = 'Status: Ativo';
                        statusText.className = 'text-green-700';
                        statusDot.classList.remove('bg-red-500');
                        statusDot.classList.add('bg-green-500');
                    } else {
                        block.classList.remove('bg-blue-600');
                        block.classList.add('bg-gray-300');
                        dot.classList.remove('transform', 'translate-x-6');
                        statusText.textContent = 'Status: Inativo';
                        statusText.className = 'text-red-700';
                        statusDot.classList.remove('bg-green-500');
                        statusDot.classList.add('bg-red-500');
                    }
                }
                
                // Evento de clique no label
                toggle.nextElementSibling.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggle.checked = !toggle.checked;
                    updateToggleUI();
                });
                
                // Inicializar o estado visual
                updateToggleUI();
            }
        });
    </script>
</body>
</html>