<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../../includes/header_empresa.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Arquivo.php';

$processoModel = new Processo($conn);
$estabelecimentoModel = new Estabelecimento($conn);
$arquivoModel = new Arquivo($conn);

$userId = $_SESSION['user']['id'];

// Verificar qual aba está ativa
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'pendentes';

// Obter os estabelecimentos pendentes do usuário logado
$estabelecimentosPendentes = $estabelecimentoModel->getEstabelecimentosPendentesByUsuario($userId);

// Obter todos os alertas (pendentes e tratados)
$todoAlertas = $processoModel->getTodosAlertasComStatusEstabelecimento($userId);

// Separar alertas pendentes e tratados
$alertasPendentes = [];
$alertasTratados = [];

foreach ($todoAlertas as $alerta) {
    if ($alerta['status_estabelecimento'] === 'pendente') {
        $alertasPendentes[] = $alerta;
    } else {
        $alertasTratados[] = $alerta;
    }
}

// Obter outros tipos de notificações
$processosParados = $processoModel->getProcessosParadosByUsuario($userId);
$documentosNegados = $estabelecimentoModel->getDocumentosNegadosByUsuario($userId);
$arquivosNaoVisualizados = $arquivoModel->getArquivosNaoVisualizados($userId);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Alertas e Pendências</title>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 25px; /* Espaçamento entre os cartões */
            border: none; /* Remover borda padrão */
        }

        .card:hover {
            transform: translateY(-7px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .card-title {
            font-weight: 700;
            color: #212529;
            font-size: 1.25rem; /* Título um pouco maior */
            margin-bottom: 1.5rem; /* Mais espaçamento abaixo do título */
        }

        .alerta-item {
            background-color: #fff3cd; /* Amarelo claro para alertas */
            border-left: 6px solid #ffc107; /* Borda amarela mais forte */
            padding: 15px 20px;
            margin-bottom: 15px;
            font-size: 0.95rem;
            position: relative;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }
        
        .alerta-item.tratado {
            background-color: #d4edda; /* Verde claro para tratados */
            border-left: 6px solid #28a745; /* Borda verde mais forte */
            opacity: 0.9;
        }
        
        .alerta-item strong {
            color: #343a40;
        }

        .btn-marcar-resolvido {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.9rem; /* Tamanho maior para o botão */
            padding: 8px 15px;
            border-radius: 25px; /* Botão arredondado */
            background-color: #28a745; /* Cor verde vibrante */
            border-color: #28a745;
            color: white;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px; /* Espaçamento entre ícone e texto */
            font-weight: bold;
        }

        .btn-marcar-resolvido:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-desmarcar {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
            color: #6c757d;
            border-color: #6c757d;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .btn-desmarcar:hover {
            background-color: #e2e6ea;
            border-color: #5a6268;
            color: #495057;
        }
        
        .nav-tabs .nav-item .nav-link {
            color: #6c757d;
            font-weight: 600;
            padding: 12px 20px;
            border: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-item .nav-link.active {
            color: #007bff; /* Cor primária do Bootstrap */
            border-bottom: 3px solid #007bff;
            background-color: #e9ecef;
            border-radius: 8px 8px 0 0;
        }

        .badge-count {
            background-color: #007bff; /* Cor de destaque para o contador */
            color: white;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 15px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .nav-link.active .badge-count {
            background-color: #343a40; /* Cor escura para destaque na aba ativa */
        }

        .observacao-container {
            margin-top: 10px;
            padding: 10px;
            background-color: #f1f1f1;
            border-left: 4px solid #007bff;
            border-radius: 6px;
            font-style: italic;
            color: #555;
            font-size: 0.9em;
        }

        /* Estilo para links de processo */
        .alerta-item a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }

        .alerta-item a:hover {
            text-decoration: underline;
        }

        /* Estilo para alerts de informação/sucesso/erro */
        .alert {
            border-radius: 8px;
            margin-top: 20px;
        }

        .alert-info {
            background-color: #e0f7fa;
            color: #007bff;
            border-color: #007bff;
        }

        .alert-success {
            background-color: #e6ffed;
            color: #28a745;
            border-color: #28a745;
        }

        .alert-warning {
            background-color: #fff8e1;
            color: #ffc107;
            border-color: #ffc107;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #343a40;
            margin-top: 3rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
    </style>
</head>

<body>

    <div class="container mt-5">
        <h3 class="mb-4">Central de Alertas e Pendências</h3>
        
        <ul class="nav nav-tabs mt-4 mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'pendentes' ? 'active' : ''; ?>" href="?tab=pendentes" role="tab" aria-selected="<?php echo $tab === 'pendentes' ? 'true' : 'false'; ?>">
                    Pendentes <span class="badge-count"><?php echo count($alertasPendentes) + count($processosParados) + count($documentosNegados) + count($arquivosNaoVisualizados) + count($estabelecimentosPendentes); ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'tratados' ? 'active' : ''; ?>" href="?tab=tratados" role="tab" aria-selected="<?php echo $tab === 'tratados' ? 'true' : 'false'; ?>">
                    Resolvidos <span class="badge-count"><?php echo count($alertasTratados); ?></span>
                </a>
            </li>
        </ul>
        
        <div class="tab-content">
            <?php if ($tab === 'pendentes') : ?>
                <div class="tab-pane fade show active" id="pendentes" role="tabpanel">
                    <?php if (!empty($estabelecimentosPendentes) || !empty($alertasPendentes) || !empty($processosParados) || !empty($documentosNegados) || !empty($arquivosNaoVisualizados)) : ?>
                        <div class="card">
                            <div class="card-body">
                                <?php if (!empty($estabelecimentosPendentes)) : ?>
                                    <h4 class="section-title">Empresas com Cadastro Pendente</h4>
                                    <?php foreach ($estabelecimentosPendentes as $estabelecimento) : ?>
                                        <div class="alerta-item">
                                            <strong>Empresa:</strong> <?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?><br>
                                            <strong>CNPJ:</strong> <?php echo htmlspecialchars($estabelecimento['cnpj']); ?><br>
                                            <strong>Status:</strong> <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($estabelecimento['status']); ?></span><br>
                                            <strong>Local:</strong> <?php echo htmlspecialchars($estabelecimento['municipio'] . ' - ' . $estabelecimento['logradouro'] . ', ' . $estabelecimento['numero'] . ' - ' . $estabelecimento['bairro']); ?><br>
                                            <small class="text-muted mt-2 d-block">Aguardando aprovação e complementação de dados.</small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($alertasPendentes)) : ?>
                                    <h4 class="section-title">Alertas de Prazos e Vencimentos</h4>
                                    <?php foreach ($alertasPendentes as $alerta) : ?>
                                        <?php if ($alerta['status'] != 'FINALIZADO') : ?>
                                            <div class="alerta-item" id="alerta-<?php echo $alerta['id']; ?>">
                                                <strong>Empresa:</strong> <?php echo htmlspecialchars($alerta['empresa_nome']); ?><br>
                                                <strong>Descrição:</strong> <?php echo htmlspecialchars($alerta['descricao']); ?><br>
                                                <strong>Prazo Final:</strong> <span class="text-danger fw-bold"><?php echo htmlspecialchars(date('d/m/Y', strtotime($alerta['prazo']))); ?></span><br>
                                                <strong>Status:</strong> <span class="badge bg-danger"><?php echo htmlspecialchars($alerta['status']); ?></span><br>
                                                <strong>Processo Relacionado:</strong> <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($alerta['processo_id']); ?>"><?php echo htmlspecialchars($alerta['numero_processo']); ?></a>
                                                
                                                <button class="btn btn-sm btn-success btn-marcar-resolvido" 
                                                        onclick="marcarComoTratado(<?php echo $alerta['id']; ?>)">
                                                    <i class="fas fa-check-circle"></i> Marcar como Resolvida
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($processosParados)) : ?>
                                    <h4 class="section-title">Processos Parados / Aguardando Ação</h4>
                                    <?php foreach ($processosParados as $processo) : ?>
                                        <div class="alerta-item">
                                            <strong>Empresa:</strong> <?php echo htmlspecialchars($processo['empresa_nome'] ?? 'N/A'); ?><br>
                                            <strong>Processo:</strong> <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($processo['id']); ?>"><?php echo htmlspecialchars($processo['numero_processo']); ?></a><br>
                                            <strong>Último Status:</strong> <span class="badge bg-info text-dark"><?php echo htmlspecialchars($processo['status']); ?></span><br>
                                            <small class="text-muted mt-2 d-block">Este processo não teve movimentação recente ou requer sua atenção.</small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($arquivosNaoVisualizados)) : ?>
                                    <h4 class="section-title">Documentos Emitidos pela Vigilância Sanitária (Novos)</h4>
                                    <?php foreach ($arquivosNaoVisualizados as $arquivo) : ?>
                                        <div class="alerta-item">
                                            <strong>Empresa:</strong> <?php echo htmlspecialchars($arquivo['nome_fantasia']); ?><br>
                                            <strong>Documento:</strong> <?php echo htmlspecialchars($arquivo['nome_arquivo']); ?><br>
                                            <strong>Processo:</strong> <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($arquivo['processo_id']); ?>"><?php echo htmlspecialchars($arquivo['numero_processo']); ?></a><br>
                                            <a href="../../<?php echo htmlspecialchars($arquivo['caminho_arquivo']); ?>" target="_blank" class="btn btn-primary btn-sm mt-2" onclick="registrarVisualizacao(<?php echo $arquivo['id']; ?>)">
                                                <i class="fas fa-eye"></i> Ver Documento
                                            </a>
                                            <small class="text-muted mt-2 d-block">Marque como visualizado após a leitura.</small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($documentosNegados)) : ?>
                                    <h4 class="section-title">Documentos Negados (Correção Necessária)</h4>
                                    <?php foreach ($documentosNegados as $documento) : ?>
                                        <div class="alerta-item">
                                            <strong>Empresa:</strong> <?php echo htmlspecialchars($documento['nome_fantasia']); ?><br>
                                            <strong>Documento:</strong> <?php echo htmlspecialchars($documento['nome_arquivo']); ?><br>
                                            <strong>Motivo da Negação:</strong> <span class="text-danger fw-bold"><?php echo htmlspecialchars($documento['motivo_negacao']); ?></span><br>
                                            <strong>Processo:</strong>
                                            <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($documento['processo_id']); ?>">
                                                <?php echo htmlspecialchars($documento['numero_processo']); ?>
                                            </a>
                                            <small class="text-muted mt-2 d-block">Este documento foi negado e precisa ser corrigido ou reenviado.</small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info text-center py-4">
                            <i class="fas fa-info-circle fa-2x mb-2"></i>
                            <h4>Tudo em ordem!</h4>
                            <p class="mb-0">Não há alertas ou pendências ativas no momento.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else : ?>
                <div class="tab-pane fade show active" id="tratados" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <?php if (!empty($alertasTratados)) : ?>
                                <h4 class="section-title">Alertas Resolvidos por Você</h4>
                                <?php foreach ($alertasTratados as $alerta) : ?>
                                    <div class="alerta-item tratado" id="alerta-tratado-<?php echo $alerta['id']; ?>">
                                        <strong>Empresa:</strong> <?php echo htmlspecialchars($alerta['empresa_nome']); ?><br>
                                        <strong>Descrição:</strong> <?php echo htmlspecialchars($alerta['descricao']); ?><br>
                                        <strong>Prazo Original:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($alerta['prazo']))); ?><br>
                                        <strong>Processo:</strong> <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($alerta['processo_id']); ?>"><?php echo htmlspecialchars($alerta['numero_processo']); ?></a><br>
                                        <strong>Resolvido em:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($alerta['data_tratamento']))); ?>
                                        <strong>por:</strong> <?php echo htmlspecialchars($alerta['tratado_por_nome'] ?? 'Você'); ?>
                                        
                                        <?php if (!empty($alerta['observacao_estabelecimento'])): ?>
                                        <div class="observacao-container">
                                            <strong>Sua Observação:</strong> <?php echo nl2br(htmlspecialchars($alerta['observacao_estabelecimento'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($alerta['status'] === 'ativo'): ?>
                                        <div class="alert alert-warning mt-3 mb-0 py-2 px-3 d-flex align-items-center" style="font-size: 0.85rem;">
                                            <i class="fas fa-exclamation-triangle me-2"></i> Este alerta ainda está pendente de verificação pela Vigilância Sanitária.
                                        </div>
                                        <?php else: ?>
                                        <div class="alert alert-success mt-3 mb-0 py-2 px-3 d-flex align-items-center" style="font-size: 0.85rem;">
                                            <i class="fas fa-check-circle me-2"></i> Este alerta foi finalizado pela Vigilância Sanitária.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($alerta['status'] === 'ativo'): ?>
                                        <button class="btn btn-sm btn-outline-secondary btn-desmarcar" 
                                                onclick="desmarcarComoTratado(<?php echo $alerta['id']; ?>)">
                                            <i class="fas fa-undo"></i> Desmarcar
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="alert alert-info text-center py-4">
                                    <i class="fas fa-lightbulb fa-2x mb-2"></i>
                                    <h4>Nenhum Alerta Resolvido</h4>
                                    <p class="mb-0">Você ainda não marcou nenhum alerta como resolvido.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="modal fade" id="observacaoModal" tabindex="-1" aria-labelledby="observacaoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="observacaoModalLabel"><i class="fas fa-clipboard-check me-2"></i> Marcar como Resolvido</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="alerta_id_temp" value="">
                    <div class="mb-3">
                        <label for="observacao" class="form-label">Descreva brevemente como este alerta foi resolvido (opcional):</label>
                        <textarea class="form-control" id="observacao" rows="4" placeholder="Ex: 'Entramos em contato com a empresa e o documento foi enviado com sucesso.', 'Prazo prorrogado conforme acordado com a vigilância.'"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="confirmarTratamento"><i class="fas fa-check me-2"></i> Confirmar Resolução</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Função para registrar visualização de arquivo
        function registrarVisualizacao(arquivoId) {
            $.post("../Company/registrar_visualizacao.php", {
                arquivo_id: arquivoId
            }, function(data) {
                if (data.status === 'success') {
                    // Recarrega a página ou remove o item da lista
                    location.reload(); 
                } else {
                    alert('Erro ao registrar visualização.');
                }
            }, 'json');
        }
        
        // Função para mostrar modal de observação
        function marcarComoTratado(alertaId) {
            $('#alerta_id_temp').val(alertaId);
            $('#observacao').val(''); // Limpa o campo de observação
            var observacaoModal = new bootstrap.Modal(document.getElementById('observacaoModal'));
            observacaoModal.show();
        }
        
        // Quando o usuário confirmar o tratamento
        $('#confirmarTratamento').click(function() {
            var alertaId = $('#alerta_id_temp').val();
            var observacao = $('#observacao').val();
            
            var observacaoModal = bootstrap.Modal.getInstance(document.getElementById('observacaoModal'));
            observacaoModal.hide();
            
            $.post("../Company/tratar_alerta.php", {
                alerta_id: alertaId,
                acao: 'marcar',
                observacao: observacao
            }, function(data) {
                if (data.status === 'success') {
                    $('#alerta-' + alertaId).fadeOut(400, function() {
                        $(this).remove();
                        exibirMensagem('Alerta marcado como resolvido com sucesso!', 'success');
                        atualizarContadores();
                        verificarListaVazia();
                    });
                } else {
                    exibirMensagem('Erro ao marcar alerta como resolvido: ' + data.message, 'danger');
                }
            }, 'json')
            .fail(function() {
                exibirMensagem('Erro na comunicação com o servidor ao marcar o alerta.', 'danger');
            });
        });
        
        // Função para desmarcar alerta como tratado
        function desmarcarComoTratado(alertaId) {
            if (confirm('Tem certeza que deseja desmarcar este alerta como resolvido? Ele retornará para a lista de pendentes.')) {
                $.post("../Company/tratar_alerta.php", {
                    alerta_id: alertaId,
                    acao: 'desmarcar'
                }, function(data) {
                    if (data.status === 'success') {
                        $('#alerta-tratado-' + alertaId).fadeOut(400, function() {
                            $(this).remove();
                            exibirMensagem('Alerta desmarcado e movido para "Pendentes".', 'info');
                            atualizarContadores();
                            verificarListaVazia();
                        });
                    } else {
                        exibirMensagem('Erro ao desmarcar alerta: ' + data.message, 'danger');
                    }
                }, 'json')
                .fail(function() {
                    exibirMensagem('Erro na comunicação com o servidor ao desmarcar o alerta.', 'danger');
                });
            }
        }
        
        // Função para exibir mensagens de feedback (sucesso/erro)
        function exibirMensagem(mensagem, tipo) {
            const msgDiv = $('<div class="alert alert-' + tipo + ' alert-dismissible fade show" role="alert">')
                .html('<i class="fas fa-' + (tipo === 'success' ? 'check-circle' : (tipo === 'info' ? 'info-circle' : 'exclamation-triangle')) + ' me-2"></i>' + mensagem)
                .append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>');
            
            $('.container').prepend(msgDiv);
            // Auto-fechar a mensagem após alguns segundos
            setTimeout(() => {
                msgDiv.alert('close');
            }, 5000);
        }

        // Função para verificar se a lista de alertas está vazia e atualizar o UI
        function verificarListaVazia() {
            // Este é um mock, na realidade você precisaria recontar dinamicamente ou recarregar
            // Para simplificar, vamos recarregar a página para atualizar os contadores e a exibição
            setTimeout(function() {
                location.reload(); 
            }, 500); 
        }

        // Função para atualizar os contadores nas guias (após uma ação)
        function atualizarContadores() {
             // Simplesmente recarregamos a página para atualizar os contadores.
             // Para uma SPA (Single Page Application), você faria uma requisição AJAX
             // para obter os novos contadores e atualizar os badges.
             location.reload(); 
        }

        // Adicionar o evento para esconder o modal quando o botão fechar é clicado
        $(document).ready(function() {
            var observacaoModalElement = document.getElementById('observacaoModal');
            observacaoModalElement.addEventListener('hidden.bs.modal', function (event) {
                // Opcional: Limpar o campo de observação quando o modal é fechado
                $('#observacao').val('');
            });
        });
    </script>
</body>
</html>