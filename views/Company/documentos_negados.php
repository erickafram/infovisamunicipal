<?php
session_start();
include '../../includes/header_empresa.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Documento.php';
require_once '../../models/Processo.php';

// Inicializar modelos
$estabelecimentoModel = new Estabelecimento($conn);
$documentoModel = new Documento($conn);
$processoModel = new Processo($conn);

// Obter o ID do usuário logado
$userId = $_SESSION['user']['id'];

// Buscar documentos negados do usuário
$documentosNegados = $estabelecimentoModel->getDocumentosNegadosByUsuario($userId);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Negados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #93c5fd;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: var(--gray-700);
        }
        
        .card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 1rem;
            background-color: white;
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-title {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 1.125rem;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .document-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .document-header {
            background-color: var(--gray-50);
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .document-body {
            padding: 1rem;
        }
        
        .document-footer {
            background-color: var(--gray-50);
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-negado {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .status-negado::before {
            content: '';
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            background-color: var(--danger);
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .btn-secondary {
            background-color: var(--gray-500);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-600);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background-color: var(--gray-50);
            border-radius: 0.75rem;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1.5rem;
        }
        
        .empty-state-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .empty-state-description {
            color: var(--gray-500);
            max-width: 24rem;
            margin: 0 auto;
        }
        
        .fade-out {
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.3s ease-out;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
        }
        
        .toast-success {
            background-color: var(--success);
        }
        
        .toast-error {
            background-color: var(--danger);
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-3 py-6 mt-4">
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    Documentos Negados
                </h2>
            </div>
            <div class="card-body">
                <?php if (!empty($documentosNegados)) : ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    Os documentos listados abaixo foram negados. Verifique o motivo da negação e faça o re-envio com as correções necessárias. 
                                    Você pode marcar um alerta como "resolvido" para ocultá-lo do painel quando já tiver tratado o documento.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <?php foreach ($documentosNegados as $documento) : ?>
                            <div class="document-card">
                                <div class="document-header">
                                    <div class="flex justify-between items-center">
                                        <h3 class="font-medium text-gray-900 truncate">
                                            <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                                            <?php echo htmlspecialchars($documento['nome_arquivo']); ?>
                                        </h3>
                                        <span class="status-badge status-negado">
                                            Negado
                                        </span>
                                    </div>
                                </div>
                                <div class="document-body">
                                    <div class="space-y-3">
                                        <div class="flex items-start">
                                            <div class="w-24 flex-shrink-0">
                                                <span class="text-sm text-gray-500">Processo:</span>
                                            </div>
                                            <div class="flex-1">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($documento['numero_processo']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="w-24 flex-shrink-0">
                                                <span class="text-sm text-gray-500">Estabelecimento:</span>
                                            </div>
                                            <div class="flex-1">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($documento['nome_fantasia']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="w-24 flex-shrink-0">
                                                <span class="text-sm text-gray-500">Data de Upload:</span>
                                            </div>
                                            <div class="flex-1">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($documento['data_upload']))); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="bg-red-50 p-3 rounded-md">
                                            <p class="text-sm text-gray-600 font-medium mb-1">Motivo da Negação:</p>
                                            <p class="text-sm text-red-700">
                                                <?php echo htmlspecialchars($documento['motivo_negacao']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="document-footer flex justify-between items-center">
                                    <div class="flex space-x-2">
                                        <button onclick="marcarComoResolvido(<?php echo htmlspecialchars($documento['id']); ?>, this)" 
                                                class="btn btn-success text-sm">
                                            <i class="fas fa-check mr-2"></i> Marcar como resolvido
                                        </button>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($documento['processo_id']); ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-upload mr-2"></i> Reenviar Documento
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="empty-state-title">Nenhum documento negado</h3>
                        <p class="empty-state-description">
                            Você não possui documentos negados no momento. Quando um documento for negado, ele aparecerá aqui para que você possa corrigir e reenviar.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
                    ${message}
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }

        function marcarComoResolvido(documentoId, button) {
            if (!confirm('Tem certeza que deseja marcar este alerta como resolvido? Esta ação ocultará o alerta do painel.')) {
                return;
            }

            // Desabilitar botão e mostrar loading
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processando...';

            fetch('../../ajax/marcar_alerta_resolvido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `documento_id=${documentoId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Adicionar animação de fade out no card
                    const card = button.closest('.document-card');
                    card.classList.add('fade-out');
                    
                    // Remover o card após a animação
                    setTimeout(() => {
                        card.remove();
                        
                        // Verificar se não há mais documentos
                        const grid = document.querySelector('.grid');
                        if (grid && grid.children.length === 0) {
                            // Mostrar estado vazio
                            const cardBody = document.querySelector('.card-body');
                            cardBody.innerHTML = `
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3 class="empty-state-title">Nenhum documento negado</h3>
                                    <p class="empty-state-description">
                                        Você não possui documentos negados no momento. Quando um documento for negado, ele aparecerá aqui para que você possa corrigir e reenviar.
                                    </p>
                                </div>
                            `;
                        }
                    }, 300);
                    
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                    // Restaurar botão
                    button.disabled = false;
                    button.innerHTML = originalContent;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Ocorreu um erro ao processar sua solicitação', 'error');
                // Restaurar botão
                button.disabled = false;
                button.innerHTML = originalContent;
            });
        }
    </script>
</body>

</html> 