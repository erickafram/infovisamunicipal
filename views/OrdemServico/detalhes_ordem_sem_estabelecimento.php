<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

// Finalizar a Ordem de Serviço
if (isset($_POST['finalizar']) && isset($_GET['id'])) {
    $descricao_pronta = $_POST['descricao_pronta'] ?? '';
    $descricao_personalizada = trim($_POST['descricao_personalizada'] ?? '');

    // Combinar a mensagem pronta e a personalizada
    $descricao_encerramento = $descricao_pronta;
    if (!empty($descricao_personalizada)) {
        $descricao_encerramento .= (!empty($descricao_pronta) ? ' - ' : '') . $descricao_personalizada;
    }

    $ordemServico = new OrdemServico($conn);
    if ($ordemServico->finalizarOrdem($_GET['id'], $descricao_encerramento)) {
        header("Location: detalhes_ordem_sem_estabelecimento.php?id=" . $_GET['id'] . "&success=Ordem de serviço finalizada com sucesso.");
        exit();
    } else {
        $error = "Erro ao finalizar a ordem de serviço: " . $ordemServico->getLastError();
    }
}

// Reiniciar a Ordem de Serviço
if (isset($_POST['reiniciar']) && isset($_GET['id'])) {
    $ordemServico = new OrdemServico($conn);
    if ($ordemServico->reiniciarOrdem($_GET['id'])) {
        header("Location: detalhes_ordem_sem_estabelecimento.php?id=" . $_GET['id'] . "&success=Ordem de serviço reiniciada com sucesso.");
        exit();
    } else {
        $error = "Erro ao reiniciar a ordem de serviço: " . $ordemServico->getLastError();
    }
}

include '../header.php';

if (!isset($_GET['id'])) {
    header("Location: listar_ordens.php");
    exit();
}

$ordemServico = new OrdemServico($conn);

// Verificar se o usuário tem permissão para acessar esta ordem baseado no município
$municipioUsuario = $_SESSION['user']['municipio'];
if (!$ordemServico->podeAcessarOrdem($_GET['id'], $municipioUsuario)) {
    header("Location: listar_ordens.php?error=Acesso negado. Você não tem permissão para visualizar esta ordem de serviço.");
    exit();
}

$ordem = $ordemServico->getOrdemById($_GET['id']);

if (!$ordem) {
    header("Location: listar_ordens.php");
    exit();
}

$acoes_ids = json_decode($ordem['acoes_executadas'], true);
$acoes_nomes = $ordemServico->getAcoesNomes($acoes_ids);

function formatDate($date)
{
    $dateTime = new DateTime($date);
    return $dateTime->format('d/m/Y');
}
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-blue-100 to-blue-50">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Detalhes da Ordem de Serviço</h2>
                <span class="text-gray-500 text-sm">Sem Estabelecimento</span>
            </div>
            
            <?php if (isset($_GET['success'])) : ?>
                <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded fade-out" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm"><?php echo htmlspecialchars($_GET['success']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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
        
        <div class="border-t border-gray-200">
            <dl>
                <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Número OS</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo isset($ordem['id']) ? htmlspecialchars($ordem['id']) : 'N/A'; ?>
                    </dd>
                </div>
                <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Data Início</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo isset($ordem['data_inicio']) ? htmlspecialchars(formatDate($ordem['data_inicio'])) : 'N/A'; ?>
                    </dd>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Data Fim</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo isset($ordem['data_fim']) ? htmlspecialchars(formatDate($ordem['data_fim'])) : 'N/A'; ?>
                    </dd>
                </div>
                <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Técnicos</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php if (isset($ordem['tecnicos_nomes']) && !empty($ordem['tecnicos_nomes'])) : ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($ordem['tecnicos_nomes'] as $tecnico) : ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-user-md mr-1"></i> <?php echo htmlspecialchars($tecnico); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <span class="text-gray-400">N/A</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Ações Executadas</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php if (!empty($acoes_ids)) : ?>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                foreach ($acoes_ids as $acao_id) :
                                    $acao_nome = $acoes_nomes[$acao_id];
                                ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i> <?php echo htmlspecialchars($acao_nome); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <span class="text-gray-400">Nenhuma ação realizada</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Documento Vinculado</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php if (!empty($ordem['pdf_upload'])) : ?>
                            <a href="/<?php echo htmlspecialchars($ordem['pdf_upload']); ?>" target="_blank" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                                <i class="fas fa-file-pdf mr-2"></i> Visualizar PDF
                            </a>
                        <?php else : ?>
                            <span class="text-gray-400">Nenhum arquivo anexado</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Observação</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php if (!empty($ordem['observacao'])) : ?>
                            <div class="bg-yellow-50 p-3 rounded border border-yellow-100">
                                <?php echo nl2br(htmlspecialchars($ordem['observacao'])); ?>
                            </div>
                        <?php else : ?>
                            <span class="text-gray-400">Sem observações</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php if ($ordem['status'] == 'ativa') : ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i> Ativa
                            </span>
                        <?php elseif ($ordem['status'] == 'finalizada') : ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-red-100 text-red-800">
                                <i class="fas fa-times-circle mr-1"></i> Finalizada
                            </span>
                        <?php else : ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-gray-100 text-gray-800">
                                <?php echo htmlspecialchars($ordem['status']); ?>
                            </span>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if (!empty($ordem['descricao_encerramento'])) : ?>
                    <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Descrição do Encerramento</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <div class="bg-red-50 p-3 rounded border border-red-100">
                                <?php echo nl2br(htmlspecialchars($ordem['descricao_encerramento'])); ?>
                            </div>
                        </dd>
                    </div>
                <?php endif; ?>
                
                <?php if (is_null($ordem['estabelecimento_id']) || is_null($ordem['processo_id'])) : ?>
                    <?php if ($ordem['status'] != 'finalizada') : ?>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Ações Disponíveis</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div class="flex space-x-2">
                                    <!-- Botão para Vincular Estabelecimento -->
                                    <?php if (is_null($ordem['estabelecimento_id']) || is_null($ordem['processo_id'])) : ?>
                                        <a href="vincular_ordem.php?id=<?php echo htmlspecialchars($ordem['id']); ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-150">
                                            <i class="fas fa-link mr-2"></i> Vincular Estabelecimento
                                        </a>
                                    <?php endif; ?>

                                    <!-- Botão para Editar Ordem de Serviço -->
                                    <?php if (in_array($_SESSION['user']['nivel_acesso'], [1, 3])) : ?>
                                        <a href="editar_ordem_sem_estabelecimento.php?id=<?php echo htmlspecialchars($ordem['id']); ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                                            <i class="fas fa-edit mr-2"></i> Editar Ordem
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </dd>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($ordem['status'] == 'finalizada') : ?>
                    <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Gerar Relatório</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <a href="gerar_pdf_ordem.php?id=<?php echo htmlspecialchars($ordem['id']); ?>" target="_blank" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-150">
                                <i class="fas fa-file-download mr-2"></i> Baixar PDF
                            </a>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
            <div class="flex space-x-2">
                <form method="POST" class="flex space-x-2">
                    <?php if ($ordem['status'] != 'finalizada') : ?>
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-150" data-bs-toggle="modal" data-bs-target="#finalizarModal">
                            <i class="fas fa-check-circle mr-2"></i> Finalizar
                        </button>
                    <?php endif; ?>
                    <?php if ($ordem['status'] == 'finalizada') : ?>
                        <button type="submit" name="reiniciar" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-150">
                            <i class="fas fa-redo mr-2"></i> Reiniciar
                        </button>
                    <?php endif; ?>
                    <a href="listar_ordens.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <i class="fas fa-arrow-left mr-2"></i> Voltar
                    </a>
                </form>
            </div>
            <div>
                <?php if ($ordem['status'] != 'finalizada' && in_array($_SESSION['user']['nivel_acesso'], [1, 3])) : ?>
                    <a href="excluir_ordem.php?id=<?php echo htmlspecialchars($ordem['id']); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-150" onclick="return confirm('Tem certeza que deseja excluir esta ordem de serviço?')">
                        <i class="fas fa-trash-alt mr-2"></i> Excluir
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal com estilo Tailwind -->
<div class="modal fade" id="finalizarModal" tabindex="-1" aria-labelledby="finalizarModalLabel" aria-hidden="true">
    <div class="modal-dialog max-w-lg">
        <div class="modal-content bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-800" id="finalizarModalLabel">
                        <i class="fas fa-check-circle text-blue-500 mr-2"></i>
                        Descrição do Encerramento
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <form method="POST" action="">
                <div class="px-6 py-4">
                    <div class="mb-4">
                        <label for="descricao_pronta" class="block text-sm font-medium text-gray-700 mb-1">
                            Selecione uma mensagem pronta:
                        </label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="descricao_pronta" name="descricao_pronta">
                            <option value="">Escolha uma mensagem</option>
                            <option value="Ordem de serviço atendida com sucesso.">Ordem de serviço atendida com sucesso.</option>
                            <option value="Problema resolvido, ordem de serviço encerrada.">Problema resolvido, ordem de serviço encerrada.</option>
                            <option value="Tarefa finalizada conforme o planejamento.">Tarefa finalizada conforme o planejamento.</option>
                        </select>
                    </div>
                    <div class="mt-6">
                        <label for="descricao_personalizada" class="block text-sm font-medium text-gray-700 mb-1">
                            Ou escreva uma mensagem personalizada:
                        </label>
                        <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="descricao_personalizada" name="descricao_personalizada" rows="3" placeholder="Digite sua mensagem aqui..."></textarea>
                        <p class="mt-1 text-xs text-gray-500">Descreva o motivo de encerramento da ordem de serviço.</p>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-2"></i> Cancelar
                    </button>
                    <button type="submit" name="finalizar" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-150">
                        <i class="fas fa-check mr-2"></i> Finalizar Ordem de Serviço
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script para melhorar a experiência do usuário no modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('finalizarModal');
    const descricaoPronte = document.getElementById('descricao_pronta');
    const descricaoPersonalizada = document.getElementById('descricao_personalizada');
    
    // Limpar os campos quando o modal é aberto
    modal.addEventListener('show.bs.modal', function() {
        descricaoPronte.selectedIndex = 0;
        descricaoPersonalizada.value = '';
    });
    
    // Adicionar efeito de fade-out para mensagens de sucesso
    const successAlert = document.querySelector('.fade-out');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.transition = 'opacity 1s';
            successAlert.style.opacity = '0';
            setTimeout(function() {
                successAlert.style.display = 'none';
            }, 1000);
        }, 3000);
    }
});
</script>

<?php include '../footer.php'; ?>