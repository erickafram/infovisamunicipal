<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] < 1) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';

$municipioUsuario = $_SESSION['user']['municipio'];
$processoModel = new Processo($conn);

// Processar ação de finalizar alerta - ANTES de qualquer saída HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['finalizar_alerta'])) {
        $alertaId = intval($_POST['alerta_id']);
        $processoModel->atualizarStatusAlerta($alertaId, 'finalizado');
        
        // Mensagem de sucesso
        $_SESSION['success_message'] = "Alerta finalizado com sucesso!";
        header("Location: alertas_tratados_pendentes.php");
        exit();
    } elseif (isset($_POST['reativar_alerta'])) {
        $alertaId = intval($_POST['alerta_id']);
        $processoModel->atualizarStatusAlerta($alertaId, 'ativo');
        
        // Mensagem de sucesso
        $_SESSION['success_message'] = "Alerta reativado com sucesso! O estabelecimento será notificado.";
        header("Location: alertas_tratados_pendentes.php");
        exit();
    } elseif (isset($_POST['finalizar_selecionados'])) {
        if (isset($_POST['alertas_selecionados']) && is_array($_POST['alertas_selecionados'])) {
            $alertasSelecionados = $_POST['alertas_selecionados'];
            $total = 0;
            
            foreach ($alertasSelecionados as $alertaId) {
                $alertaId = intval($alertaId);
                if ($alertaId > 0) {
                    $processoModel->atualizarStatusAlerta($alertaId, 'finalizado');
                    $total++;
                }
            }
            
            // Mensagem de sucesso
            $_SESSION['success_message'] = "$total alertas finalizados com sucesso!";
        } else {
            $_SESSION['error_message'] = "Nenhum alerta foi selecionado.";
        }
        header("Location: alertas_tratados_pendentes.php");
        exit();
    } elseif (isset($_POST['reativar_selecionados'])) {
        if (isset($_POST['alertas_selecionados']) && is_array($_POST['alertas_selecionados'])) {
            $alertasSelecionados = $_POST['alertas_selecionados'];
            $total = 0;
            
            foreach ($alertasSelecionados as $alertaId) {
                $alertaId = intval($alertaId);
                if ($alertaId > 0) {
                    $processoModel->atualizarStatusAlerta($alertaId, 'ativo');
                    $total++;
                }
            }
            
            // Mensagem de sucesso
            $_SESSION['success_message'] = "$total alertas reativados com sucesso! Os estabelecimentos serão notificados.";
        } else {
            $_SESSION['error_message'] = "Nenhum alerta foi selecionado.";
        }
        header("Location: alertas_tratados_pendentes.php");
        exit();
    }
}

// Buscar alertas tratados pendentes de verificação - ANTES do include do header
$alertasTratados = $processoModel->getAlertasTratadosPendentesVerificacao($municipioUsuario);
$totalAlertasTratados = $processoModel->getAlertasTratadosPendentesVerificacaoCount($municipioUsuario);

// DEPOIS de todos os redirecionamentos e processamentos, incluir o header
include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas Resolvidos Pendentes de Verificação</title>
    <style>
        .alerta-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            background-color: #fff;
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.06);
        }
        
        .alerta-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .alerta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 20px 10px 20px;
        }
        
        .alerta-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #2c3e50;
        }
        
        .alerta-date {
            color: #7f8c8d;
            font-size: 0.9rem;
            background-color: #f8f9fa;
            padding: 4px 10px;
            border-radius: 50px;
        }
        
        .alerta-processo {
            background-color: #e8f5e9;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            display: inline-block;
            margin: 0 20px 15px 20px;
            color: #2e7d32;
        }
        
        .alerta-content {
            padding: 0 20px 15px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .alerta-descricao {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .alerta-prazo {
            font-weight: 600;
            color: #e53935;
            background-color: #ffebee;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .alerta-actions {
            display: flex;
            gap: 10px;
            padding: 15px 20px;
            background-color: #f9f9f9;
        }
        
        .tratado-info {
            background-color: #e3f2fd;
            padding: 15px;
            margin: 0 20px 15px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .tratado-por {
            font-weight: 600;
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .tratado-por i {
            margin-right: 8px;
            color: #1976d2;
        }
        
        .tratado-data {
            display: flex;
            align-items: center;
            color: #546e7a;
            font-size: 0.85rem;
        }
        
        .tratado-data i {
            margin-right: 8px;
            color: #78909c;
        }
        
        .observacao-container {
            background-color: #fff;
            border-left: 3px solid #2196f3;
            padding: 12px;
            margin-top: 12px;
            font-style: italic;
            border-radius: 0 8px 8px 0;
        }
        
        .alert-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #43a047;
            color: white;
            padding: 5px 12px;
            border-radius: 0 0 0 8px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .page-header {
            background-color: #fff;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .alert-count {
            background-color: #4caf50;
            color: white;
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .alert-count i {
            font-size: 0.8rem;
        }
        
        .btn-finalizar {
            background-color: #4caf50;
            color: white;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-finalizar:hover {
            background-color: #388e3c;
            transform: translateY(-2px);
        }
        
        .btn-reativar {
            background-color: #ff9800;
            color: white;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-reativar:hover {
            background-color: #f57c00;
            transform: translateY(-2px);
        }
        
        .btn-ver {
            background-color: #2196f3;
            color: white;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-ver:hover {
            background-color: #1976d2;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #b0bec5;
            margin-bottom: 15px;
        }
        
        .empty-state-text {
            font-size: 1.1rem;
            color: #546e7a;
            margin-bottom: 0;
        }
        
        /* Estilos para os checkboxes e ações em massa */
        .form-check-input {
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        
        .form-check-input:checked {
            background-color: #4caf50;
            border-color: #4caf50;
        }
        
        .form-check-label {
            cursor: pointer;
            user-select: none;
        }
        
        .acoes-massa {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: sticky;
            top: 70px;
            z-index: 10;
        }
        
        .alerta-card.selecionado {
            border: 2px solid #4caf50;
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        @media (max-width: 768px) {
            .alerta-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .alerta-date {
                margin-top: 8px;
            }
            
            .alerta-actions {
                flex-direction: column;
            }
            
            .alerta-actions .btn {
                width: 100%;
                margin-bottom: 8px;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 10px;
            }
            
            .d-flex.gap-2 {
                width: 100%;
            }
            
            .d-flex.gap-2 .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5 pt-4 mb-5">
        <div class="page-header">
            <h4 class="page-title">
                <i class="fas fa-check-double me-2 text-success"></i>
                Alertas Resolvido Pendentes
            </h4>
            <div class="alert-count">
                <i class="fas fa-bell"></i>
                <?php echo $totalAlertasTratados; ?> pendente<?php echo $totalAlertasTratados != 1 ? 's' : ''; ?>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($alertasTratados)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p class="empty-state-text">Não há alertas Relatados pendentes de verificação.</p>
            </div>
        <?php else: ?>
            <form method="POST" id="form-alertas">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selecionar-todos">
                        <label class="form-check-label" for="selecionar-todos">
                            Selecionar todos
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="finalizar_selecionados" class="btn btn-success btn-sm" id="btn-finalizar-selecionados" disabled>
                            <i class="fas fa-check-double me-1"></i> Finalizar Selecionados
                        </button>
                        <button type="submit" name="reativar_selecionados" class="btn btn-warning btn-sm" id="btn-reativar-selecionados" disabled>
                            <i class="fas fa-redo-alt me-1"></i> Reativar Selecionados
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <?php foreach ($alertasTratados as $alerta): ?>
                        <div class="col-lg-6">
                            <div class="alerta-card">
                                <div class="alert-badge">
                                    <i class="fas fa-check me-1"></i> Resolvido
                                </div>
                                
                                <div class="alerta-header">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input alerta-checkbox" type="checkbox" name="alertas_selecionados[]" value="<?php echo $alerta['id']; ?>" id="alerta-<?php echo $alerta['id']; ?>">
                                        </div>
                                        <div class="alerta-title"><?php echo htmlspecialchars($alerta['nome_fantasia'] ?? ''); ?></div>
                                    </div>
                                    <div class="alerta-date">
                                        <i class="far fa-calendar-alt me-1"></i> 
                                        <?php echo !empty($alerta['data_tratamento']) ? date('d/m/Y', strtotime($alerta['data_tratamento'])) : 'Data não definida'; ?>
                                    </div>
                                </div>
                                
                                <div class="alerta-processo">
                                    <i class="fas fa-file-alt me-1"></i> 
                                    Processo: <?php echo htmlspecialchars($alerta['numero_processo']); ?>
                                </div>
                                
                                <div class="alerta-content">
                                    <div class="alerta-descricao"><?php echo htmlspecialchars($alerta['descricao']); ?></div>
                                    <div class="mt-2">
                                        <strong>Prazo:</strong> 
                                        <span class="alerta-prazo">
                                            <i class="fas fa-calendar-day me-1"></i>
                                            <?php echo !empty($alerta['prazo']) ? date('d/m/Y', strtotime($alerta['prazo'])) : 'Não definido'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="tratado-info">
                                    <div class="tratado-por">
                                        <i class="fas fa-user-check"></i> 
                                        Resolvido por: <?php echo htmlspecialchars($alerta['tratado_por_nome'] ?? 'Usuário'); ?>
                                    </div>
                                    <div class="tratado-data">
                                        <i class="far fa-clock"></i> 
                                        Em: <?php echo !empty($alerta['data_tratamento']) ? date('d/m/Y H:i', strtotime($alerta['data_tratamento'])) : 'Data não registrada'; ?>
                                    </div>
                                    
                                    <?php if (!empty($alerta['observacao_estabelecimento'])): ?>
                                        <div class="observacao-container">
                                            <strong>Observação do estabelecimento:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($alerta['observacao_estabelecimento'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="alerta-actions">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="submit" name="finalizar_alerta" class="btn btn-finalizar btn-sm flex-grow-1" onclick="setAlertaId(<?php echo $alerta['id']; ?>)">
                                            <i class="fas fa-check-circle me-1"></i> Finalizar Alerta
                                        </button>
                                        
                                        <button type="submit" name="reativar_alerta" class="btn btn-reativar btn-sm flex-grow-1" onclick="setAlertaId(<?php echo $alerta['id']; ?>)">
                                            <i class="fas fa-redo me-1"></i> Reativar Alerta
                                        </button>
                                        
                                        <a href="../Processo/documentos.php?processo_id=<?php echo $alerta['processo_id']; ?>&id=<?php echo $alerta['estabelecimento_id'] ?? ''; ?>" 
                                           class="btn btn-ver btn-sm flex-grow-1">
                                            <i class="fas fa-eye me-1"></i> Ver Processo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <input type="hidden" name="alerta_id" id="alerta_id_input" value="">
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Função para definir o ID do alerta para ações individuais
        function setAlertaId(id) {
            document.getElementById('alerta_id_input').value = id;
        }
        
        // Adicionar animação de entrada aos cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.alerta-card');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
            
            // Adicionar confirmação antes de finalizar ou reativar
            const finalizarBtns = document.querySelectorAll('[name="finalizar_alerta"]');
            const reativarBtns = document.querySelectorAll('[name="reativar_alerta"]');
            
            finalizarBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('Tem certeza que deseja finalizar este alerta?')) {
                        e.preventDefault();
                    }
                });
            });
            
            reativarBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('Tem certeza que deseja reativar este alerta? O estabelecimento será notificado.')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Gerenciar seleção múltipla
            const selecionarTodos = document.getElementById('selecionar-todos');
            const checkboxes = document.querySelectorAll('.alerta-checkbox');
            const btnFinalizarSelecionados = document.getElementById('btn-finalizar-selecionados');
            const btnReativarSelecionados = document.getElementById('btn-reativar-selecionados');
            
            // Função para atualizar o estado dos botões de ação em massa
            function atualizarBotoesAcao() {
                const checkboxesSelecionados = document.querySelectorAll('.alerta-checkbox:checked');
                const temSelecionados = checkboxesSelecionados.length > 0;
                
                btnFinalizarSelecionados.disabled = !temSelecionados;
                btnReativarSelecionados.disabled = !temSelecionados;
                
                // Atualizar o texto dos botões para incluir a contagem
                if (temSelecionados) {
                    btnFinalizarSelecionados.innerHTML = `<i class="fas fa-check-double me-1"></i> Finalizar (${checkboxesSelecionados.length})`;
                    btnReativarSelecionados.innerHTML = `<i class="fas fa-redo-alt me-1"></i> Reativar (${checkboxesSelecionados.length})`;
                } else {
                    btnFinalizarSelecionados.innerHTML = `<i class="fas fa-check-double me-1"></i> Finalizar Selecionados`;
                    btnReativarSelecionados.innerHTML = `<i class="fas fa-redo-alt me-1"></i> Reativar Selecionados`;
                }
            }
            
            // Evento para o checkbox "selecionar todos"
            if (selecionarTodos) {
                selecionarTodos.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    atualizarBotoesAcao();
                });
            }
            
            // Evento para cada checkbox individual
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // Verificar se todos estão selecionados
                    const todosSelecionados = document.querySelectorAll('.alerta-checkbox:checked').length === checkboxes.length;
                    if (selecionarTodos) {
                        selecionarTodos.checked = todosSelecionados;
                    }
                    
                    // Adicionar classe visual ao card quando selecionado
                    const card = this.closest('.alerta-card');
                    if (card) {
                        if (this.checked) {
                            card.classList.add('selecionado');
                        } else {
                            card.classList.remove('selecionado');
                        }
                    }
                    
                    atualizarBotoesAcao();
                });
            });
            
            // Adicionar confirmação para ações em massa
            if (btnFinalizarSelecionados) {
                btnFinalizarSelecionados.addEventListener('click', function(e) {
                    const qtdSelecionados = document.querySelectorAll('.alerta-checkbox:checked').length;
                    if (!confirm(`Tem certeza que deseja finalizar ${qtdSelecionados} alerta(s)?`)) {
                        e.preventDefault();
                    }
                });
            }
            
            if (btnReativarSelecionados) {
                btnReativarSelecionados.addEventListener('click', function(e) {
                    const qtdSelecionados = document.querySelectorAll('.alerta-checkbox:checked').length;
                    if (!confirm(`Tem certeza que deseja reativar ${qtdSelecionados} alerta(s)? Os estabelecimentos serão notificados.`)) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html> 