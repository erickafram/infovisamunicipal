<?php
session_start();
include '../../includes/header_empresa.php';
require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Alerta.php';
require_once '../../controllers/AlertaController.php';
require_once '../../models/Processo.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$processoModel = new Processo($conn);
$nome_completo = $_SESSION['user']['nome_completo'];
$user_id = $_SESSION['user']['id'];

// Instanciação de modelos e controllers
$alertaController = new AlertaController($conn);
$estabelecimentoModel = new Estabelecimento($conn);
$arquivoModel = new Arquivo($conn);

// Carregamento de dados
try {
    $alertasAtivos = $alertaController->listarAlertasNaoLidos($user_id);
    $estabelecimentosAprovados = $estabelecimentoModel->getEstabelecimentosByUsuario($user_id);
    $estabelecimentosPendentes = $estabelecimentoModel->getEstabelecimentosPendentesByUsuario($user_id);
    $documentosNegados = $estabelecimentoModel->getDocumentosNegadosByUsuario($user_id);
    $documentosPendentes = $estabelecimentoModel->getDocumentosPendentesByUsuario($user_id);
    $estabelecimentosRejeitados = $estabelecimentoModel->getEstabelecimentosRejeitadosByUsuario($user_id);
    $arquivosNaoVisualizados = $arquivoModel->getArquivosNaoVisualizados($user_id);
    $processosParados = $processoModel->getProcessosParadosByUsuario($user_id);
} catch (Exception $e) {
    error_log("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Empresa</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- Tailwind CSS -->
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
                    transitionProperty: {
                        'height': 'height',
                        'spacing': 'margin, padding',
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
            .badge-warning {
                @apply bg-yellow-100 text-yellow-800;
            }
            .badge-danger {
                @apply bg-red-100 text-red-800;
            }
            .badge-success {
                @apply bg-green-100 text-green-800;
            }
            .badge-info {
                @apply bg-blue-100 text-blue-800;
            }
            .dashboard-card {
                @apply bg-white rounded-lg shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md border border-gray-100 hover:-translate-y-1;
            }
            .stat-value {
                @apply text-xl font-bold text-gray-800;
            }
            .stat-label {
                @apply text-xs font-medium text-gray-500;
            }
            .dashboard-section {
                @apply mb-6;
            }
            .section-title {
                @apply text-base font-semibold text-gray-700 mb-3 flex items-center;
            }
        }
    </style>
</head>

<?php //include '../ChatVisa/chat_empresa.php'; ?>

<body class="bg-gradient-to-br from-gray-50 to-blue-50/30 min-h-screen">
    <div class="container mx-auto px-3 py-6 mt-2">
        <!-- Header Section with Glass Effect -->
        <div class="mb-8">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6">
        
        <!-- Welcome Card -->
        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-lg shadow-md border border-white/30 w-full xl:w-auto flex items-center hover:shadow-lg transition-all duration-300">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg flex items-center justify-center shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-7-7v14" />
                    </svg>
                </div>
            </div>
            <div class="ml-3">
                <h1 class="text-lg font-bold text-gray-800 mb-0.5">Dashboard</h1>
                <p class="text-xs text-gray-600">
                    Bem-vindo, 
                    <span class="font-semibold text-blue-600"><?= htmlspecialchars($nome_completo) ?></span>
                </p>
            </div>
        </div>

        <!-- Action Buttons Grid -->
        <div class="flex flex-wrap gap-2 justify-end w-full xl:w-auto">

            <!-- Documentos Emitidos -->
            <a href="../Processo/documentos_emitidos.php" 
               class="group bg-white/80 backdrop-blur-sm border border-white/30 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 p-2 flex items-center gap-2 min-w-[120px]">
                <div class="w-8 h-8 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-lg flex items-center justify-center shadow-sm group-hover:shadow-md transition-shadow duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-semibold text-gray-800">Documentos</div>
                    <div class="text-xs text-gray-500">Emitidos</div>
                </div>
                <?php if (count($arquivosNaoVisualizados) > 0): ?>
                    <span class="inline-flex items-center justify-center min-w-[18px] h-5 rounded-full bg-gradient-to-r from-red-500 to-red-600 text-xs font-bold text-white shadow-md animate-pulse">
                        <?= count($arquivosNaoVisualizados) ?>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center justify-center min-w-[18px] h-5 rounded-full bg-gray-100 text-xs font-medium text-gray-500">
                        0
                    </span>
                <?php endif; ?>
            </a>

            <!-- Meus Relatos -->
            <a href="meus_relatos.php" 
               class="group bg-white/80 backdrop-blur-sm border border-white/30 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 p-2 flex items-center gap-2 min-w-[120px]">
                <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-sm group-hover:shadow-md transition-shadow duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-semibold text-gray-800">Meus</div>
                    <div class="text-xs text-gray-500">Relatos</div>
                </div>
                <?php 
                $novasRespostas = 0;
                $checkColumnsStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'resposta'");
                $checkColumnsStmt->execute();
                $columnsExist = ($checkColumnsStmt->get_result()->num_rows > 0);
                
                if ($columnsExist) {
                    $stmtRelatos = $conn->prepare("SELECT COUNT(*) as count FROM relatos_usuarios WHERE usuario_externo_id = ? AND resposta IS NOT NULL AND data_resposta > (NOW() - INTERVAL 7 DAY)");
                    $stmtRelatos->bind_param("i", $user_id);
                    $stmtRelatos->execute();
                    $resultRelatos = $stmtRelatos->get_result();
                    $novasRespostas = $resultRelatos->fetch_assoc()['count'];
                }
                
                if ($novasRespostas > 0): 
                ?>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-6 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 text-xs font-bold text-white shadow-lg animate-pulse">
                        <?= $novasRespostas ?>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-6 rounded-full bg-gray-100 text-xs font-medium text-gray-500">
                        0
                    </span>
                <?php endif; ?>
            </a>

            <!-- Documentos Negados -->
<?php if (count($documentosNegados) > 0): ?>
<a href="documentos_negados.php" 
   class="group bg-white/80 backdrop-blur-sm border border-white/30 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 p-2 flex items-center gap-2 min-w-[120px]">
    <div class="w-8 h-8 bg-gradient-to-br from-rose-500 to-rose-600 rounded-lg flex items-center justify-center shadow-sm group-hover:shadow-md transition-shadow duration-300">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
    </div>
    <div class="flex-1">
        <div class="text-xs font-semibold text-gray-800">Documentos</div>
        <div class="text-xs text-gray-500">Negados</div>
    </div>
    <span class="inline-flex items-center justify-center min-w-[18px] h-5 rounded-full bg-gradient-to-r from-rose-500 to-rose-600 text-xs font-bold text-white shadow-md">
        <?= count($documentosNegados) ?>
    </span>
</a>
<?php endif; ?>

            <!-- Dropdown Cadastrar -->
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" 
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-xs font-semibold rounded-lg shadow-md hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Cadastrar
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                
                <div x-show="open" 
                     @click.away="open = false" 
                     x-transition:enter="transition ease-out duration-200" 
                     x-transition:enter-start="transform opacity-0 scale-95" 
                     x-transition:enter-end="transform opacity-100 scale-100" 
                     x-transition:leave="transition ease-in duration-150" 
                     x-transition:leave-start="transform opacity-100 scale-100" 
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="origin-top-right absolute right-0 mt-3 w-64 rounded-xl shadow-xl bg-white/95 backdrop-blur-sm ring-1 ring-black/10 divide-y divide-gray-100 focus:outline-none z-20" 
                     style="display: none;">
                    <div class="py-2">
                        <a href="cadastro_estabelecimento_empresa.php" 
                           class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50/80 transition-colors group rounded-lg mx-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3 group-hover:shadow-md transition-shadow">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-800">Pessoa Jurídica</div>
                                <div class="text-xs text-gray-500">Empresas e estabelecimentos</div>
                            </div>
                        </a>
                        <a href="cadastro_pessoa_fisica.php" 
                           class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50/80 transition-colors group rounded-lg mx-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center mr-3 group-hover:shadow-md transition-shadow">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-800">Pessoa Física</div>
                                <div class="text-xs text-gray-500">Indivíduos e autônomos</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Estatísticas Rápidas -->
        <div class="dashboard-section">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Card 1 - Estabelecimentos -->
                <div class="dashboard-card p-3 bg-gradient-to-br from-white to-blue-50">
                    <div class="flex items-start justify-between mb-1.5">
                        <h6 class="text-xs font-semibold text-gray-600">Estabelecimentos</h6>
                        <span class="badge badge-info flex items-center text-xs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                            Total
                        </span>
                    </div>
                    <div class="flex items-center">
                        <div class="bg-primary-100 p-2 rounded-lg mr-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800"><?= count($estabelecimentosAprovados) ?></p>
                            <p class="text-xs font-medium text-gray-500">Aprovados</p>
                        </div>
                    </div>
                    <div class="mt-1.5 pt-1.5 border-t border-gray-100">
                        <a href="todos_estabelecimentos.php" class="text-primary-600 hover:text-primary-800 text-xs font-medium flex items-center transition-colors">
                            Ver todos
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Card 2 - Processos -->
                <div class="dashboard-card p-3 bg-gradient-to-br from-white to-yellow-50">
                    <div class="flex items-start justify-between mb-1.5">
                        <h6 class="text-xs font-semibold text-gray-600">Processos</h6>
                        <span class="badge badge-warning flex items-center text-xs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Parados
                        </span>
                    </div>
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-lg mr-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800"><?= count($processosParados) ?></p>
                            <p class="text-xs font-medium text-gray-500">Atenção necessária</p>
                        </div>
                    </div>
                    <div class="mt-1.5 pt-1.5 border-t border-gray-100">
                        <a href="../Company/processos_empresa.php?filter=parados" class="text-yellow-600 hover:text-yellow-800 text-xs font-medium flex items-center transition-colors">
                            Ver processos
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Card 3 - Documentos -->
                <div class="dashboard-card p-3 bg-gradient-to-br from-white to-red-50" id="documentos-section">
                    <div class="flex items-start justify-between mb-1.5">
                        <h6 class="text-xs font-semibold text-gray-600">Documentos</h6>
                        <span class="badge badge-danger flex items-center text-xs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Negados
                        </span>
                    </div>
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-lg mr-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800"><?= count($documentosNegados) ?></p>
                            <p class="text-xs font-medium text-gray-500">Revisão necessária</p>
                        </div>
                    </div>
                    <div class="mt-1.5 pt-1.5 border-t border-gray-100">
                        <a href="../Company/documentos_negados.php" class="text-red-600 hover:text-red-800 text-xs font-medium flex items-center transition-colors">
                            Ver negados
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Card 4 - Notificações -->
                <div class="dashboard-card p-3 bg-gradient-to-br from-white to-green-50">
                    <div class="flex items-start justify-between mb-1.5">
                        <h6 class="text-xs font-semibold text-gray-600">Notificações</h6>
                        <?php if ($totalNotificacoes > 0): ?>
                        <span class="badge badge-success flex items-center text-xs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Novas
                        </span>
                        <?php else: ?>
                        <span class="badge flex items-center bg-gray-100 text-gray-600 text-xs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Em dia
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-lg mr-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800"><?= $totalNotificacoes ?></p>
                            <p class="text-xs font-medium text-gray-500">Não lidas</p>
                        </div>
                    </div>
                    <div class="mt-1.5 pt-1.5 border-t border-gray-100">
                        <a href="../Company/alertas_empresas.php" class="text-green-600 hover:text-green-800 text-xs font-medium flex items-center transition-colors">
                            Ver alertas
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas Modal -->
        <?php if (!empty($alertasAtivos)) : ?>
            <div id="alertasModal" x-data="{ open: true }" x-show="open" class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-yellow-100 sm:mx-0 sm:h-8 sm:w-8">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-base leading-6 font-medium text-gray-900" id="modal-title">Avisos Importantes</h3>
                                    <div class="mt-3 space-y-3">
                                        <?php foreach ($alertasAtivos as $alerta) : ?>
                                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-3 rounded shadow-sm hover:shadow-md transition-shadow">
                                                <div class="flex items-start">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-yellow-600 mt-0.5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <div>
                                                        <p class="text-xs text-gray-700"><?= htmlspecialchars($alerta['descricao']) ?></p>
                                                        <?php if ($alerta['link']) : ?>
                                                            <a href="<?= htmlspecialchars($alerta['link']) ?>" target="_blank" class="mt-1.5 inline-flex items-center text-xs text-blue-600 hover:text-blue-800">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                                </svg>
                                                                Acessar link
                                                            </a>
                                                        <?php endif; ?>
                                                        <button class="mt-1.5 inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors marcar-lido" data-id="<?= $alerta['id'] ?>">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            Marcar como lido
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" @click="open = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Grid - Cards na mesma linha -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
            <!-- Card Estabelecimentos Aprovados -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100 hover:shadow-md transition-all duration-300">
                <div class="px-3 py-3 border-b border-gray-100">
                    <h5 class="font-semibold text-gray-700 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                            Estabelecimentos Aprovados
                        </h5>
                    </div>
                <div class="divide-y divide-gray-50">
                        <?php if (empty($estabelecimentosAprovados)) : ?>
                        <div class="py-6 text-center flex flex-col items-center text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-200 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-xs">Nenhum estabelecimento aprovado</p>
                            <a href="cadastro_estabelecimento_empresa.php" class="mt-1.5 inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Cadastrar estabelecimento
                            </a>
                        </div>
                        <?php else : ?>
                            <?php foreach (array_slice($estabelecimentosAprovados, 0, 3) as $estab) : ?>
                            <div class="p-3 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex flex-wrap md:flex-nowrap justify-between items-center gap-2">
                                    <div class="flex-grow min-w-0">
                                        <h6 class="font-medium text-gray-800 mb-0.5 text-xs truncate"><?= htmlspecialchars($estab['nome_fantasia']) ?></h6>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs text-gray-500 flex items-center">
                                                <?= $estab['tipo_pessoa'] === 'fisica' ?
                                                    'CPF: ' . htmlspecialchars(substr($estab['cpf'], 0, 3) . '.***.***-' . substr($estab['cpf'], -2)) :
                                                    'CNPJ: ' . htmlspecialchars(substr($estab['cnpj'], 0, 3) . '.***.***/****-' . substr($estab['cnpj'], -2)) ?>
                                            </span>
                                            
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                Aprovado
                                            </span>
                                        </div>
                                    </div>
                                    <a href="../Estabelecimento/detalhes_estabelecimento_empresa.php?id=<?= $estab['id'] ?>"
                                       class="text-blue-600 hover:text-blue-800 text-xs font-medium transition-colors">
                                        Detalhes →
                                    </a>
                                </div>
                    </div>
                        <?php endforeach; ?>
                        <?php if (count($estabelecimentosAprovados) > 3): ?>
                            <div class="text-center p-2 bg-gray-50">
                                <a href="todos_estabelecimentos.php" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                    Ver todos (<?= count($estabelecimentosAprovados) ?>)
                                        </a>
                                    </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

                <!-- Card Pendências -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100">
                <div class="px-3 py-3 border-b border-gray-100 flex justify-between items-center">
                    <h5 class="font-semibold text-gray-700 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                            Pendências
                        </h5>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        <?= count($estabelecimentosPendentes) + count($documentosPendentes) ?>
                    </span>
                    </div>
                <div x-data="{ activeTab: 'estabelecimentos' }" class="divide-y divide-gray-50">
                    <div class="flex border-b border-gray-100">
                        <button @click="activeTab = 'estabelecimentos'" :class="{ 'text-amber-600 border-b-2 border-amber-500': activeTab === 'estabelecimentos', 'text-gray-500 hover:text-gray-700': activeTab !== 'estabelecimentos' }" class="flex-1 py-1.5 px-1 text-center font-medium text-xs focus:outline-none transition-colors duration-200 flex items-center justify-center space-x-1">
                            <span>Estabelecimentos</span>
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-1 py-0.5 text-xs font-medium text-amber-700">
                                <?= count($estabelecimentosPendentes) ?>
                            </span>
                            </button>
                        <button @click="activeTab = 'documentos'" :class="{ 'text-amber-600 border-b-2 border-amber-500': activeTab === 'documentos', 'text-gray-500 hover:text-gray-700': activeTab !== 'documentos' }" class="flex-1 py-1.5 px-1 text-center font-medium text-xs focus:outline-none transition-colors duration-200 flex items-center justify-center space-x-1">
                            <span>Documentos</span>
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-1 py-0.5 text-xs font-medium text-amber-700">
                                <?= count($documentosPendentes) ?>
                            </span>
                            </button>
                        </div>

                    <div class="px-3 py-2">
                            <!-- Aba Estabelecimentos -->
                        <div x-show="activeTab === 'estabelecimentos'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
                                <?php if (empty($estabelecimentosPendentes)) : ?>
                                <div class="text-center py-4">
                                    <p class="text-sm text-gray-500">Todos estabelecimentos regularizados</p>
                                    </div>
                                <?php else : ?>
                                    <div class="space-y-3">
                                        <?php foreach ($estabelecimentosPendentes as $estab) : ?>
                                        <div class="py-2 px-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                                                <div class="flex items-center">
                                                    <div class="flex-grow">
                                                        <h6 class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($estab['nome_fantasia']) ?></h6>
                                                        <span class="text-xs text-gray-500">
                                                        <?= $estab['tipo_pessoa'] === 'fisica' ? 'CPF' : 'CNPJ' ?>: 
                                                        <?= $estab['tipo_pessoa'] === 'fisica' ?
                                                            htmlspecialchars(substr($estab['cpf'], 0, 3) . '.***.***-' . substr($estab['cpf'], -2)) :
                                                            htmlspecialchars(substr($estab['cnpj'], 0, 3) . '.***.***/****-' . substr($estab['cnpj'], -2)) ?>
                                                        </span>
                                                    </div>
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                    Pendente
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Aba Documentos -->
                        <div x-show="activeTab === 'documentos'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
                                <?php if (empty($documentosPendentes)) : ?>
                                <div class="text-center py-4">
                                    <p class="text-sm text-gray-500">Todos documentos regularizados</p>
                                    </div>
                                <?php else : ?>
                                    <div class="space-y-3">
                                        <?php foreach ($documentosPendentes as $doc) : ?>
                                            <a href="../Processo/detalhes_processo_empresa.php?id=<?= $doc['processo_id'] ?>"
                                            class="block py-2 px-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                                                <div class="flex items-center">
                                                <div class="flex-grow pr-2">
                                                    <h6 class="font-medium text-gray-800 text-sm truncate"><?= htmlspecialchars($doc['nome_arquivo']) ?></h6>
                                                        <span class="text-xs text-gray-500">
                                                            Processo: <?= htmlspecialchars($doc['numero_processo']) ?>
                                                        </span>
                                                    </div>
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                    Pendente
                                                    </span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Necessita Atenção -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100" id="atencao-section">
                <div class="px-3 py-3 border-b border-gray-100 flex justify-between items-center">
                    <h5 class="font-semibold text-gray-700 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                            Necessita Atenção
                    </h5>
                    <span class="inline-flex items-center rounded-full bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                <?= count($documentosNegados) + count($processosParados) + count($estabelecimentosRejeitados) ?>
                            </span>
                    </div>
                <div x-data="{ openTab: null }" class="divide-y divide-gray-50 max-h-[300px] overflow-y-auto">
                        <!-- Estabelecimentos Rejeitados -->
                        <div class="border-b border-gray-100">
                        <button @click="openTab = (openTab === 'estabelecimentos') ? null : 'estabelecimentos'" class="w-full px-3 py-2 flex items-center justify-between text-left hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-center">
                                <span class="font-medium text-gray-700 text-xs">Estabelecimentos Rejeitados</span>
                                <span class="ml-1.5 inline-flex items-center rounded-full bg-rose-50 px-1 py-0.5 text-xs font-medium text-rose-700">
                                    <?= count($estabelecimentosRejeitados) ?>
                                </span>
                                </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200" :class="{'rotate-90': openTab === 'estabelecimentos'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            </button>
                            
                        <div x-show="openTab === 'estabelecimentos'" x-collapse x-cloak class="px-4 pb-3">
                                <?php if (empty($estabelecimentosRejeitados)) : ?>
                                <div class="text-center py-3 text-gray-500">
                                        Nenhum estabelecimento rejeitado.
                                    </div>
                                <?php else : ?>
                                <div class="space-y-3 divide-y divide-gray-100">
                                    <?php foreach (array_slice($estabelecimentosRejeitados, 0, 2) as $estabelecimento) : ?>
                                        <div class="pt-3 first:pt-0 pb-2">
                                            <div class="p-2 bg-rose-50 rounded-lg shadow-sm">
                                                <div class="flex justify-between items-start">
                                                    <div class="space-y-1">
                                                        <p class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($estabelecimento['nome_fantasia']) ?></p>
                                                        <div>
                                                            <span class="text-xs text-gray-700 font-medium">Motivo:</span> 
                                                            <p class="text-xs text-gray-600 line-clamp-1"><?= htmlspecialchars($estabelecimento['motivo_negacao']) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php if (count($estabelecimentosRejeitados) > 2): ?>
                                        <div class="text-center pt-2">
                                            <a href="listar_estabelecimentos_rejeitados.php" class="text-xs text-rose-600 hover:text-rose-800">
                                                Ver todos (<?= count($estabelecimentosRejeitados) ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Documentos Negados -->
                        <div class="border-b border-gray-100">
                        <button @click="openTab = (openTab === 'documentos') ? null : 'documentos'" class="w-full px-3 py-2 flex items-center justify-between text-left hover:bg-rose-50 transition-colors duration-200">
                                <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-rose-500 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="font-medium text-gray-700 text-xs">Documentos Negados</span>
                                <span class="ml-1.5 inline-flex items-center justify-center px-1 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                    <?= count($documentosNegados) ?>
                                </span>
                                </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200" :class="{'rotate-90': openTab === 'documentos'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            </button>
                            
                        <div x-show="openTab === 'documentos'" x-collapse x-cloak class="px-4 pb-3">
                                <?php if (empty($documentosNegados)) : ?>
                                <div class="text-center py-3 text-gray-500">
                                        Nenhum documento negado.
                                    </div>
                                <?php else : ?>
                                    <div class="space-y-3">
                                    <?php foreach (array_slice($documentosNegados, 0, 2) as $doc) : ?>
                                        <div class="bg-rose-50 p-3 rounded-lg relative group hover:bg-rose-100 transition-colors duration-150 shadow-sm">
                                                <div class="flex items-start">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-rose-500 mt-0.5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                    <div>
                                                    <h6 class="font-medium text-gray-800 text-xs mb-0.5">Doc: <?= htmlspecialchars($doc['nome_arquivo']) ?></h6>
                                                    <p class="text-xs text-gray-600 line-clamp-1">Motivo: <?= htmlspecialchars($doc['motivo_negacao']) ?></p>
                                                </div>
                                            </div>
                                            <a href="../Processo/detalhes_processo_empresa.php?id=<?= $doc['processo_id'] ?>" class="absolute inset-0 z-10" aria-label="Ver detalhes"></a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php if (count($documentosNegados) > 2): ?>
                                        <div class="text-center pt-1">
                                            <a href="../Company/documentos_negados.php" class="text-xs text-rose-600 hover:text-rose-800">
                                                Ver todos (<?= count($documentosNegados) ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Processos Parados -->
                    <div class="border-b border-gray-100 last:border-0">
                        <button @click="openTab = (openTab === 'processos') ? null : 'processos'" class="w-full px-3 py-2 flex items-center justify-between text-left hover:bg-rose-50 transition-colors duration-200">
                                <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-rose-500 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-medium text-gray-700 text-xs">Processos Parados</span>
                                <span class="ml-1.5 inline-flex items-center justify-center px-1 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                    <?= count($processosParados) ?>
                                </span>
                                </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200" :class="{'rotate-90': openTab === 'processos'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            </button>
                            
                        <div x-show="openTab === 'processos'" x-collapse x-cloak class="px-4 pb-3">
                                <?php if (empty($processosParados)) : ?>
                                <div class="text-center py-3 text-gray-500">
                                        Nenhum processo parado.
                                    </div>
                                <?php else : ?>
                                    <div class="space-y-3">
                                    <?php foreach (array_slice($processosParados, 0, 2) as $processo) : ?>
                                        <div class="bg-rose-50 p-3 rounded-lg shadow-sm hover:bg-rose-100 transition-colors duration-150">
                                            <div class="flex justify-between items-start">
                                                <div class="space-y-1">
                                                                                        <h6 class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($processo['numero_processo']) ?></h6>
                                    <div class="flex items-center gap-1 text-xs text-gray-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Processo parado</span>
                                    </div>
                                                </div>
                                                <a href="../Processo/detalhes_processo_empresa.php?id=<?= $processo['id'] ?>" class="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10 hover:bg-rose-100 transition-colors duration-150">
                                                    Ver
                                                </a>
                                            </div>
                                                </div>
                                            <?php endforeach; ?>
                                    <?php if (count($processosParados) > 2): ?>
                                        <div class="text-center pt-1">
                                            <a href="../Company/processos_empresa.php?filter=parados" class="text-xs text-rose-600 hover:text-rose-800">
                                                Ver todos (<?= count($processosParados) ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

        <!-- Grid secundária para conteúdo adicional -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            <!-- Coluna Principal -->
            <div class="lg:col-span-12">
                <!-- Se quiser adicionar algum conteúdo aqui futuramente -->
            </div>
        </div>

    <!-- Modal para visualizar documentos -->
    <div class="modal fade" id="docModal" tabindex="-1" aria-labelledby="docModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="docModalLabel">Visualizar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ratio ratio-16x9">
                        <iframe id="docFrame" src="" frameborder="0" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
            // Manipulação para abrir o documento no iframe
            const visualizarLinks = document.querySelectorAll('.visualizar-arquivo');
            visualizarLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                            const arquivoId = this.getAttribute('data-arquivo-id');
                            const arquivoUrl = this.getAttribute('data-arquivo-url');

                    const docFrame = document.getElementById('docFrame');
                    docFrame.src = arquivoUrl;
                    
                    const modal = new bootstrap.Modal(document.getElementById('docModal'));
                    modal.show();
                    
                    // Registrar visualização via AJAX
                            fetch('registrar_visualizacao.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'arquivo_id=' + arquivoId
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                            // Remover o indicador de não visualizado
                            const parentElement = this.closest('.p-5');
                            const badgeElement = parentElement.querySelector('.badge-info.animate-pulse-slow');
                            if (badgeElement) {
                                badgeElement.classList.remove('animate-pulse-slow');
                                badgeElement.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Visualizado`;
                            }
                        }
                    })
                    .catch(error => console.error('Erro:', error));
                        });
                    });
                    
            // Manipulação para marcar alertas como lidos
                    const marcarLidoBtns = document.querySelectorAll('.marcar-lido');
                    marcarLidoBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const alertaId = this.getAttribute('data-id');
                    const alertaElement = this.closest('.bg-yellow-50, .bg-rose-50');
                            
                            fetch('marcar_lido.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                        body: 'alerta_id=' + alertaId
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                            // Remover o elemento após marcar como lido
                            if (alertaElement) {
                                alertaElement.style.opacity = '0.5';
                                        setTimeout(() => {
                                    alertaElement.style.height = '0';
                                    alertaElement.style.margin = '0';
                                    alertaElement.style.padding = '0';
                                    alertaElement.style.overflow = 'hidden';
                                    
                                    setTimeout(() => {
                                        alertaElement.remove();
                                        
                                        // Verificar se ainda há alertas
                                        const container = document.querySelector('#alertasModal .space-y-4');
                                        if (container && container.children.length === 0) {
                                            // Fechar o modal se não houver mais alertas
                                            const modalElement = document.getElementById('alertasModal');
                                            if (modalElement && typeof bootstrap !== 'undefined') {
                                                const modal = bootstrap.Modal.getInstance(modalElement);
                                                if (modal) modal.hide();
                                            }
                                        }
                                    }, 300);
                                }, 300);
                                        }
                                    }
                                })
                                .catch(error => console.error('Erro:', error));
                });
                        });
                });
            </script>

            <?php
            include '../footer.php';
            ?>
</body>

</html>