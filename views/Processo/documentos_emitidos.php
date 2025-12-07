<?php
session_start();
include '../../includes/header_empresa.php';
require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user']['id'];
$nome_completo = $_SESSION['user']['nome_completo'];

// Initialize models
$arquivoModel = new Arquivo($conn);

// Get all documents and unviewed documents
$arquivos = $arquivoModel->getArquivosByUsuario($user_id);
$arquivosNaoVisualizados = $arquivoModel->getArquivosNaoVisualizados($user_id);

// Count documents
$totalArquivos = count($arquivos);
$totalNaoVisualizados = count($arquivosNaoVisualizados);

// Generate dynamic base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
// Get project root by extracting from current script path
$currentPath = $_SERVER['SCRIPT_NAME']; // e.g., /visamunicipal/views/Processo/documentos_emitidos.php
$projectRoot = dirname(dirname(dirname($currentPath))); // Go up 3 levels to get /visamunicipal
if ($projectRoot === '/') $projectRoot = ''; // Handle root directory case
$baseUrl = $protocol . $host . $projectRoot;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Emitidos - INFOVISA</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer components {
            .card {
                @apply bg-white rounded-lg shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md border border-gray-100;
            }
            .card-header {
                @apply px-4 py-3 border-b;
            }
            .btn-primary {
                @apply px-3 py-1.5 bg-primary-600 text-white rounded-md font-medium hover:bg-primary-700 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2;
            }
            .btn-secondary {
                @apply px-3 py-1.5 bg-gray-100 text-gray-800 rounded-md font-medium hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2;
            }
            .badge {
                @apply px-2 py-0.5 rounded-full text-xs font-medium;
            }
            .badge-success {
                @apply bg-green-100 text-green-800;
            }
            .badge-info {
                @apply bg-blue-100 text-blue-800;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-blue-50/30 min-h-screen">
    <div x-data="{ activeFilter: 'all' }" class="container mx-auto px-3 py-6 mt-2">
        <!-- Header Section with Glass Effect -->
        <div class="mb-6 pt-3">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div class="bg-white bg-opacity-60 backdrop-filter backdrop-blur-lg p-4 rounded-lg shadow-sm border border-white/40 w-full md:w-auto flex items-center">
                    <div class="bg-cyan-500 text-white p-2.5 rounded-lg mr-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 mb-0.5">Documentos Emitidos</h1>
                        <p class="text-sm text-gray-600">
                            <span class="font-medium"><?= $totalArquivos ?></span> documentos encontrados
                            <?php if ($totalNaoVisualizados > 0): ?>
                                • <span class="text-cyan-600 font-medium"><?= $totalNaoVisualizados ?></span> não visualizados
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-2 justify-end w-full md:w-auto">
                    <a href="../Company/dashboard_empresa.php" class="flex items-center justify-center gap-2 px-3 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Voltar ao Dashboard</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtering Options -->
        <div class="mb-5">
            <div class="bg-white rounded-lg shadow-sm p-4 flex flex-wrap gap-2">
                <button @click="activeFilter = 'all'" :class="{ 'bg-blue-100 text-blue-700 border-blue-300': activeFilter === 'all', 'bg-gray-100 text-gray-700 hover:bg-gray-200': activeFilter !== 'all' }" class="px-3 py-1.5 rounded-md text-sm font-medium border transition-colors duration-150">
                    Todos os documentos (<?= $totalArquivos ?>)
                </button>
                <button @click="activeFilter = 'unread'" :class="{ 'bg-cyan-100 text-cyan-700 border-cyan-300': activeFilter === 'unread', 'bg-gray-100 text-gray-700 hover:bg-gray-200': activeFilter !== 'unread' }" class="px-3 py-1.5 rounded-md text-sm font-medium border transition-colors duration-150">
                    Não visualizados (<?= $totalNaoVisualizados ?>)
                </button>
                <button @click="activeFilter = 'read'" :class="{ 'bg-green-100 text-green-700 border-green-300': activeFilter === 'read', 'bg-gray-100 text-gray-700 hover:bg-gray-200': activeFilter !== 'read' }" class="px-3 py-1.5 rounded-md text-sm font-medium border transition-colors duration-150">
                    Visualizados (<?= $totalArquivos - $totalNaoVisualizados ?>)
                </button>
            </div>
        </div>

        <!-- Document List -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 class="font-semibold text-gray-800">Lista de Documentos Emitidos</h2>
            </div>

            <?php if (empty($arquivos)): ?>
                <div class="p-10 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-600 mb-2">Nenhum documento encontrado</h3>
                    <p class="text-gray-500 max-w-md mx-auto">
                        Você não possui documentos emitidos no momento. Os documentos serão mostrados aqui quando forem emitidos.
                    </p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($arquivos as $arquivo): ?>
                        <?php 
                            $isUnread = !$arquivo['visualizado']; 
                            $statusClass = $isUnread ? 'animate-pulse-slow' : '';
                            $statusText = $isUnread ? 'Não visualizado' : 'Visualizado';
                            $statusColor = $isUnread ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800';
                            $statusIcon = $isUnread ? 
                                '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>' : 
                                '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';
                        ?>
                        <div 
                            x-show="activeFilter === 'all' || (activeFilter === 'unread' && <?= $isUnread ? 'true' : 'false' ?>) || (activeFilter === 'read' && !<?= $isUnread ? 'true' : 'false' ?>)"
                            class="p-4 hover:bg-gray-50 transition-colors"
                        >
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <!-- Document Icon -->
                                    <div class="p-2 bg-cyan-100 rounded-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-cyan-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    
                                    <!-- Document Details -->
                                    <div class="flex-grow">
                                        <h3 class="font-medium text-gray-800 mb-0.5"><?= htmlspecialchars($arquivo['nome_arquivo']) ?></h3>
                                        <div class="flex flex-wrap items-center text-xs text-gray-500 gap-2">
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <?= date('d/m/Y', strtotime($arquivo['data_upload'])) ?>
                                            </span>
                                            
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                Processo: <a href="detalhes_processo_empresa.php?id=<?= $arquivo['processo_id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($arquivo['numero_processo'] ?? 'N/A') ?></a>
                                            </span>
                                            
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                </svg>
                                                <?= htmlspecialchars($arquivo['nome_fantasia'] ?? 'Estabelecimento não informado') ?>
                                            </span>
                                            
                                            <span class="inline-flex items-center <?= $statusColor ?> rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass ?>">
                                                <?= $statusIcon ?>
                                                <?= $statusText ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex items-center gap-2 mt-2 md:mt-0">
                                    <button 
                                        class="visualizar-arquivo px-3 py-1.5 bg-cyan-100 text-cyan-800 rounded-md text-xs font-medium hover:bg-cyan-200 transition-colors flex items-center gap-1"
                                        data-arquivo-id="<?= $arquivo['id'] ?>"
                                        data-arquivo-url="<?= $baseUrl . '/' . htmlspecialchars($arquivo['url_arquivo']) ?>"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Visualizar
                                    </button>
                                    
                                    <a 
                                        href="<?= $baseUrl . '/' . htmlspecialchars($arquivo['url_arquivo']) ?>" 
                                        download="<?= htmlspecialchars($arquivo['nome_arquivo']) ?>"
                                        class="px-3 py-1.5 bg-gray-100 text-gray-800 rounded-md text-xs font-medium hover:bg-gray-200 transition-colors flex items-center gap-1"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para visualizar documentos -->
    <div id="docModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl h-[90vh] flex flex-col">
            <div class="p-4 flex justify-between items-center border-b">
                <h3 class="text-lg font-medium text-gray-900" id="docModalTitle">Visualizar Documento</h3>
                <button type="button" id="closeModal" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex-grow p-1 bg-gray-100">
                <iframe id="docFrame" class="w-full h-full border-0" src="" frameborder="0"></iframe>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent Bootstrap modal conflicts
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                // Disable Bootstrap modal auto-initialization for our custom modal
                const docModal = document.getElementById('docModal');
                if (docModal) {
                    docModal.setAttribute('data-bs-backdrop', 'false');
                    docModal.setAttribute('data-bs-keyboard', 'false');
                }
            }
            
            // Modal handling
            const modal = document.getElementById('docModal');
            const closeModal = document.getElementById('closeModal');
            const docFrame = document.getElementById('docFrame');
            const docModalTitle = document.getElementById('docModalTitle');
            
            // Close modal when clicking the close button
            closeModal.addEventListener('click', function() {
                modal.classList.add('hidden');
                docFrame.src = '';
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    docFrame.src = '';
                }
            });
            
            // Open modal and view document
            const visualizarLinks = document.querySelectorAll('.visualizar-arquivo');
            visualizarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const arquivoId = this.getAttribute('data-arquivo-id');
                    const arquivoUrl = this.getAttribute('data-arquivo-url');
                    const fileName = this.closest('.flex-col, .flex-row').querySelector('h3').textContent;
                    
                    // Set the iframe source
                    docFrame.src = arquivoUrl;
                    docModalTitle.textContent = 'Documento: ' + fileName;
                    
                    // Show modal
                    modal.classList.remove('hidden');
                    
                    // Register view
                    fetch('./registrar_visualizacao.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'arquivo_id=' + arquivoId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Update UI to show document as viewed
                            const statusBadge = this.closest('.flex-col, .flex-row').querySelector('.inline-flex.items-center');
                            if (statusBadge) {
                                statusBadge.classList.remove('animate-pulse-slow', 'bg-blue-100', 'text-blue-800');
                                statusBadge.classList.add('bg-green-100', 'text-green-800');
                                statusBadge.innerHTML = `
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Visualizado`;
                            }
                        }
                    })
                    .catch(error => console.error('Erro:', error));
                });
            });
        });
    </script>
</body>

</html> 