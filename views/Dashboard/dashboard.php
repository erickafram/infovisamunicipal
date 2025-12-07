<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../models/OrdemServico.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/User.php'; // Adicionando o modelo User
require_once '../../models/Assinatura.php'; // Adicionando o modelo Assinatura
require_once '../../models/Arquivo.php'; // Adicionando o modelo Arquivo
require_once '../../models/ConfiguracaoSistema.php'; // Adicionando o modelo ConfiguracaoSistema

// Verificar se o usuário tem senha digital configurada
$userModel = new User($conn);
$usuarioLogado = $userModel->findById($_SESSION['user']['id']);
$tem_senha_digital = !empty($usuarioLogado['senha_digital']);

// Verificar se o chat está ativo
$chatAtivo = ConfiguracaoSistema::chatAtivo($conn);

// Função para consultar saldo na API do GovNex
function consultarSaldoGovNex($cnpj) {
    $token = "8ab984d986b155d84b4f88dec6d4f8c3cd2e11c685d9805107df78e94ab488ca";
    $url = "https://govnex.site/govnex/api/saldo_api.php?token={$token}&cnpj={$cnpj}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return ['error' => 'Erro ao consultar API: ' . curl_error($ch)];
    }
    
    curl_close($ch);
    
    return json_decode($response, true);
}

// Função otimizada para consultar saldo na API do GovNex (com timeout reduzido)
function consultarSaldoGovNexRapido($cnpj) {
    $token = "8ab984d986b155d84b4f88dec6d4f8c3cd2e11c685d9805107df78e94ab488ca";
    $url = "https://govnex.site/govnex/api/saldo_api.php?token={$token}&cnpj={$cnpj}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Aumentado para 5 segundos
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Timeout de conexão de 3 segundos
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Seguir redirecionamentos
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['error' => 'API indisponível: ' . $curlError];
    }
    
    curl_close($ch);
    
    // Verificar código HTTP
    if ($httpCode !== 200) {
        return ['error' => 'API retornou código HTTP ' . $httpCode];
    }
    
    // Verificar se a resposta está vazia
    if (empty($response)) {
        return ['error' => 'API retornou resposta vazia'];
    }
    
    // Tentar decodificar JSON
    $result = json_decode($response, true);
    
    // Verificar se houve erro na decodificação
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => 'Erro ao decodificar JSON: ' . json_last_error_msg(),
            'raw_response' => substr($response, 0, 200) // Primeiros 200 caracteres para debug
        ];
    }
    
    // Verificar se o resultado é válido
    if (!is_array($result)) {
        return ['error' => 'Resposta da API não é um array válido'];
    }
    
    // Verificar se a API retornou erro
    if (isset($result['error'])) {
        return ['error' => 'API retornou erro: ' . $result['error']];
    }
    
    // Verificar se tem os campos necessários
    if (!isset($result['status'])) {
        return ['error' => 'Resposta da API sem campo status'];
    }
    
    return $result;
}

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}

$user_id = $_SESSION['user']['id'];
$municipioUsuario = $_SESSION['user']['municipio']; // Obtendo o município do usuário logado

// ===== OTIMIZAÇÃO: Inicializar modelos apenas uma vez =====
$ordemServicoModel = new OrdemServico($conn);
$processoModel = new Processo($conn);
$estabelecimentoModel = new Estabelecimento($conn);
$userModel = new User($conn);
$assinaturaModel = new Assinatura($conn);

// ===== OTIMIZAÇÃO: Cache de dados do usuário =====
$usuarioLogado = $userModel->findById($user_id);
$camposIncompletos = ($usuarioLogado['nivel_acesso'] != 1 && 
    (is_null($usuarioLogado['tempo_vinculo']) || is_null($usuarioLogado['escolaridade']) || is_null($usuarioLogado['tipo_vinculo'])));

// ===== OTIMIZAÇÃO: Carregamento condicional - só carrega dados necessários =====
// Inicializar variáveis com valores padrão
$ordensServico = [];
$totalOrdensAtivas = 0;
$pontuacaoMensal = 0;
$processosParados = [];
$estabelecimentosPendentes = [];
$alertasProximosAVencer = [];
$alertasVencidos = [];
$totalAlertasVencidos = 0;
$totalAlertasAtivos = 0;
$processosPendentes = [];
$processosAcompanhados = [];
$responsabilidades = [];
$assinaturasPendentes = [];
$alertasTratadosPendentes = 0;

// ===== OTIMIZAÇÃO: Carregamento assíncrono dos dados menos críticos =====
// Carregar apenas dados essenciais imediatamente
try {
    // Dados críticos (sempre carregados)
    $assinaturasPendentes = $assinaturaModel->getAssinaturasPendentes($user_id);
    $alertasTratadosPendentes = $processoModel->getAlertasTratadosPendentesVerificacaoCount($municipioUsuario);
    
    // Dados importantes (carregados com limite)
    $ordensServico = $ordemServicoModel->getOrdensAtivasByTecnico($user_id, 3); // Reduzido de 5 para 3
    $totalOrdensAtivas = $ordemServicoModel->countOrdensAtivasByTecnico($user_id);
    $estabelecimentosPendentes = $estabelecimentoModel->getEstabelecimentosPendentes($municipioUsuario, 2);
    $processosPendentes = $processoModel->getProcessosComDocumentacaoPendente($municipioUsuario);
    
    // Limitar processos pendentes para performance
    if (count($processosPendentes) > 10) {
        $processosPendentes = array_slice($processosPendentes, 0, 10);
    }
    
    // Dados menos críticos (carregados apenas se necessário)
    if ($usuarioLogado['nivel_acesso'] <= 2) { // Apenas para admin e supervisores
        $alertasProximosAVencer = $processoModel->getAlertasProximosAVencer($municipioUsuario, 2);
        $totalAlertasAtivos = $processoModel->getTotalAlertasProximosAVencer($municipioUsuario);
    }
    
    // Dados opcionais (podem ser carregados via AJAX posteriormente)
    $processosAcompanhados = $processoModel->getProcessosAcompanhados($user_id);
    $responsabilidades = $processoModel->getProcessosResponsaveisPorUsuario($user_id);
    
    // Filtrar responsabilidades
    $responsabilidades = array_filter($responsabilidades, function ($responsavel) {
        return isset($responsavel['status']) && $responsavel['status'] === 'pendente';
    });
    
    // Pontuação mensal (não crítica)
    $mesAtual = date('m');
    $anoAtual = date('Y');
    $pontuacaoMensal = $ordemServicoModel->getPontuacaoMensal($user_id, $mesAtual, $anoAtual);
    
} catch (Exception $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    // Em caso de erro, continua com valores padrão
}

// ===== OTIMIZAÇÃO: Calcular totais apenas com dados já carregados =====
$totalAlertas = count($assinaturasPendentes) + $alertasTratadosPendentes;

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="InfoVisa">
    <link rel="manifest" href="/visamunicipal/manifest.json">
    <link rel="apple-touch-icon" href="/visamunicipal/assets/images/icon-192x192.png">
    
    <!-- Script para funcionalidade de dropdown -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando dropdowns...');
            initDropdowns();
        });
        
        function initDropdowns() {
            // Encontrar todos os cards com dropdown
            const dropdownCards = document.querySelectorAll('[data-dropdown]');
            
            dropdownCards.forEach(card => {
                let isOpen = false;
                
                // Encontrar o header clicável
                const header = card.querySelector('.dropdown-header');
                const content = card.querySelector('.dropdown-content');
                const arrow = card.querySelector('.dropdown-arrow');
                const hint = card.querySelector('.dropdown-hint');
                
                if (header && content) {
                    // Esconder conteúdo inicialmente e mostrar hint
                    content.style.display = 'none';
                    if (hint) hint.style.display = 'block';
                    
                    // Adicionar event listener ao header
                    header.addEventListener('click', function(e) {
                        e.preventDefault();
                        isOpen = !isOpen;
                        
                        if (isOpen) {
                            // Esconder hint e mostrar conteúdo
                            if (hint) hint.style.display = 'none';
                            
                            content.style.display = 'block';
                            content.style.opacity = '0';
                            content.style.transform = 'scale(0.95)';
                            
                            // Animar entrada
                            requestAnimationFrame(() => {
                                content.style.transition = 'all 0.2s ease-out';
                                content.style.opacity = '1';
                                content.style.transform = 'scale(1)';
                            });
                            
                            if (arrow) arrow.style.transform = 'rotate(180deg)';
                        } else {
                            content.style.transition = 'all 0.15s ease-in';
                            content.style.opacity = '0';
                            content.style.transform = 'scale(0.95)';
                            
                            setTimeout(() => {
                                content.style.display = 'none';
                                // Mostrar hint novamente
                                if (hint) hint.style.display = 'block';
                            }, 150);
                            
                            if (arrow) arrow.style.transform = 'rotate(0deg)';
                        }
                    });
                }
            });
        }
        

    </script>
    
    <!-- CSS para animações do dropdown manual e tamanhos de fonte reduzidos -->
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        svg {
            transition: transform 0.2s ease;
        }
        
        [x-show] {
            transition: all 0.2s ease;
        }
        
        /* Reduzir tamanhos de fonte dos cards */
        [data-dropdown] h3 {
            font-size: 0.875rem !important; /* text-sm */
        }
        
        [data-dropdown] h3 i {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        [data-dropdown] .dropdown-content {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        [data-dropdown] .dropdown-content .font-medium {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        [data-dropdown] .dropdown-content .text-xs {
            font-size: 0.625rem !important; /* text-[10px] */
        }
        
        [data-dropdown] .dropdown-content .text-sm {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        [data-dropdown] .dropdown-hint {
            font-size: 0.625rem !important; /* text-[10px] */
        }
        
        /* Reduzir padding dos cards */
        [data-dropdown] .p-4 {
            padding: 0.75rem !important; /* p-3 */
        }
        
        /* Reduzir espaçamento entre elementos */
        [data-dropdown] .mb-3 {
            margin-bottom: 0.5rem !important; /* mb-2 */
        }
        
        [data-dropdown] .py-2 {
            padding-top: 0.375rem !important; /* py-1.5 */
            padding-bottom: 0.375rem !important;
        }
        
        /* Reduzir badges/contadores */
        [data-dropdown] .px-2 {
            padding-left: 0.375rem !important; /* px-1.5 */
            padding-right: 0.375rem !important;
        }
        
        [data-dropdown] .py-0\.5 {
            padding-top: 0.25rem !important; /* py-1 */
            padding-bottom: 0.25rem !important;
        }
        
        /* Reduzir ícones */
        [data-dropdown] .h-4 {
            height: 0.875rem !important; /* h-3.5 */
            width: 0.875rem !important; /* w-3.5 */
        }
        
        [data-dropdown] .w-4 {
            width: 0.875rem !important; /* w-3.5 */
        }
        
        /* Reduzir texto dos botões */
        [data-dropdown] .dropdown-content a {
            font-size: 0.625rem !important; /* text-[10px] */
        }
        
        /* Reduzir margens */
        [data-dropdown] .mt-3 {
            margin-top: 0.5rem !important; /* mt-2 */
        }
        
        [data-dropdown] .mb-2 {
            margin-bottom: 0.375rem !important; /* mb-1.5 */
        }
        
        /* Reduzir fontes dos cards estáticos (não dropdown) */
        .col-span-1:not([data-dropdown]) h3 {
            font-size: 0.875rem !important; /* text-sm */
        }
        
        .col-span-1:not([data-dropdown]) h3 i {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        .col-span-1:not([data-dropdown]) .text-xs {
            font-size: 0.625rem !important; /* text-[10px] */
        }
        
        .col-span-1:not([data-dropdown]) .text-sm {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        .col-span-1:not([data-dropdown]) .text-base {
            font-size: 0.875rem !important; /* text-sm */
        }
        
        /* Reduzir padding dos cards estáticos */
        .col-span-1:not([data-dropdown]) .p-4 {
            padding: 0.75rem !important; /* p-3 */
        }
        
        /* Reduzir espaçamento dos cards estáticos */
        .col-span-1:not([data-dropdown]) .mb-3 {
            margin-bottom: 0.5rem !important; /* mb-2 */
        }
        
        .col-span-1:not([data-dropdown]) .py-2 {
            padding-top: 0.375rem !important; /* py-1.5 */
            padding-bottom: 0.375rem !important;
        }
        
        /* Reduzir badges dos cards estáticos */
        .col-span-1:not([data-dropdown]) .px-2 {
            padding-left: 0.375rem !important; /* px-1.5 */
            padding-right: 0.375rem !important;
        }
        
        .col-span-1:not([data-dropdown]) .py-0\.5 {
            padding-top: 0.25rem !important; /* py-1 */
            padding-bottom: 0.25rem !important;
        }
        
        /* Reduzir ícones dos cards estáticos */
        .col-span-1:not([data-dropdown]) .h-4 {
            height: 0.875rem !important; /* h-3.5 */
            width: 0.875rem !important; /* w-3.5 */
        }
        
        .col-span-1:not([data-dropdown]) .w-4 {
            width: 0.875rem !important; /* w-3.5 */
        }
        
        /* Reduzir margens dos cards estáticos */
        .col-span-1:not([data-dropdown]) .mt-3 {
            margin-top: 0.5rem !important; /* mt-2 */
        }
        
        .col-span-1:not([data-dropdown]) .mb-2 {
            margin-bottom: 0.375rem !important; /* mb-1.5 */
        }
        
        /* Reduzir texto de links/botões dos cards estáticos */
        .col-span-1:not([data-dropdown]) a {
            font-size: 0.625rem !important; /* text-[10px] */
        }
        
        /* Reduzir texto específico dos cards estáticos */
        .col-span-1:not([data-dropdown]) .font-medium {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        .col-span-1:not([data-dropdown]) .truncate {
            font-size: 0.75rem !important; /* text-xs */
        }
        
        /* Ajustes específicos para elementos dos cards */
        .col-span-1 .text-\[10px\] {
            font-size: 0.5rem !important; /* ainda menor */
        }
        
        /* Reduzir texto de avisos/alertas */
        .col-span-1 .bg-red-100,
        .col-span-1 .bg-amber-100,
        .col-span-1 .bg-green-100 {
            font-size: 0.625rem !important; /* text-[10px] */
            padding: 0.375rem !important; /* p-1.5 */
        }
        
        /* Reduzir badges de status */
        .col-span-1 .rounded-full {
            font-size: 0.625rem !important; /* text-[10px] */
            padding: 0.125rem 0.375rem !important; /* py-0.5 px-1.5 */
        }
        
        /* Reduzir divisores de lista */
        .col-span-1 .divide-y > * {
            font-size: 0.625rem !important; /* text-[10px] */
        }
        
        /* Reduzir texto em itálico */
        .col-span-1 .italic {
            font-size: 0.625rem !important; /* text-[10px] */
        }
        
        /* Reduzir spans com informações */
        .col-span-1 span:not(.rounded-full) {
            font-size: 0.625rem !important; /* text-[10px] */
        }
    </style>
    
    <!-- Script para registrar o service worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/visamunicipal/sw.js').then(function(registration) {
                    console.log('ServiceWorker registrado com sucesso: ', registration.scope);
                }).catch(function(error) {
                    console.log('Falha ao registrar o ServiceWorker: ', error);
                });
            });
        }
    </script>
    <!-- Estilos específicos da dashboard que complementam o Tailwind CSS do header -->
    <style>
        /* Animações e transições personalizadas */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
        }
        
        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Estilo para o efeito de transição em campos preenchidos */
        .transition-effect.filled {
            @apply bg-green-50 border-green-500;
        }
    </style>

<div class="hidden sm:block">  
    <?php if ($chatAtivo): ?>
        <?php include '../ChatVisa/chat_card.php'; ?>
    <?php endif; ?>
</div>
</head>

<body class="bg-gray-50">


    <!-- Banner flutuante para instalar o app (visível apenas em celulares) -->
    <div id="pwaPrompt" class="md:hidden fixed top-0 left-0 w-full bg-gradient-to-r from-blue-700 to-blue-600 p-2 z-[1001] shadow-lg hidden">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-mobile-alt text-white text-lg mr-2"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-white">BAIXE O APP DO INFOVISA</p>
                </div>
            </div>
            <div class="flex items-center space-x-1">
                <button id="installPwa" class="px-2 py-1 bg-white text-blue-700 rounded text-xs font-bold">
                    <i class="fas fa-download text-xs"></i> INSTALAR
                </button>
                <button id="dismissPwa" class="p-1 text-white text-xs">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    

    
    <div class="container mx-auto px-3 py-6 mt-4">
        <?php if (!$tem_senha_digital): ?>
        <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 border-l-4 border-yellow-400 p-4 mb-6 rounded-lg shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    </div>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-yellow-800">Atenção: Senha Digital não configurada</h3>
                    <div class="mt-1">
                        <p class="text-sm text-yellow-700">
                            Para assinar documentos eletrônicos, você precisa configurar sua senha digital.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="../Usuario/senha_digital.php" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-yellow-700 bg-yellow-200 hover:bg-yellow-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-150">
                            <i class="fas fa-key mr-1.5"></i>
                            Configurar Senha Digital
                        </a>
                    </div>
                </div>
                <div class="ml-4 flex-shrink-0 self-start">
                    <button onclick="this.parentElement.parentElement.parentElement.style.display='none'" class="text-yellow-500 hover:text-yellow-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Estilo para animação piscante -->
        <style>
            @keyframes pulse-border {
                0% { box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(139, 92, 246, 0); }
                100% { box-shadow: 0 0 0 0 rgba(139, 92, 246, 0); }
            }
            
            @keyframes glow {
                0% { background-color: rgba(139, 92, 246, 0.9); }
                50% { background-color: rgba(124, 58, 237, 1); }
                100% { background-color: rgba(139, 92, 246, 0.9); }
            }
            
            .pulse-animate {
                animation: pulse-border 2s infinite, glow 3s infinite;
            }
            
            .shake-icon {
                animation: shake 1.5s infinite;
            }
            
            @keyframes shake {
                0% { transform: rotate(0deg); }
                10% { transform: rotate(-10deg); }
                20% { transform: rotate(10deg); }
                30% { transform: rotate(0deg); }
                100% { transform: rotate(0deg); }
            }
            
            .sticky-alert {
                position: sticky;
                top: 70px; /* Ajustado para ficar abaixo do menu superior */
                z-index: 40; /* Reduzido para não sobrepor modais (z-index 50) */
            }
        </style>
        
        <?php if (isset($assinaturasPendentes) && count($assinaturasPendentes) > 0): ?>
        <!-- Alerta chamativo de assinaturas pendentes com fonte reduzida para mobile -->    
        <div id="assinaturaAlertTop" class="sticky-alert pulse-animate bg-violet-600 rounded-lg shadow-lg mb-3 overflow-hidden text-white hover:shadow-xl transition-all duration-300">
            <div class="px-2 sm:px-4 py-2 sm:py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 mr-2 sm:mr-3 shake-icon">
                            <i class="fas fa-signature text-white text-sm sm:text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white flex items-center text-xs sm:text-sm">
                                ATENÇÃO! Assinaturas Pendentes
                            </h3>
                            <p class="text-white/90 text-xs">
                                <span class="font-semibold bg-white/20 px-1.5 py-0.5 rounded"><?php echo count($assinaturasPendentes); ?></span> documento<?php echo count($assinaturasPendentes) != 1 ? 's' : ''; ?>
                            </p>
                        </div>
                    </div>
                    <a href="../Assinatura/listar_assinaturas_pendentes.php" class="px-2 py-1 sm:px-3 sm:py-1.5 bg-yellow-400 text-gray-900 rounded text-xs font-bold hover:bg-yellow-300 transition-colors duration-200 flex items-center shadow-md ml-1">
                        <i class="fas fa-pen-alt mr-1"></i>
                        <span class="hidden xs:inline">ASSINAR</span>
                        <span class="inline xs:hidden"><i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="bg-blue-600 rounded-lg shadow-md p-4 mb-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold mb-1">
                        Dashboard
                    </h2>
                    <p class="text-blue-100 text-sm">
                        Olá, <strong><?php echo htmlspecialchars($_SESSION['user']['nome_completo'] ?? ''); ?></strong> •
                        <a href="../Alertas/alertas_usuario_logado.php" class="text-white underline">
                            <strong><?php echo $totalAlertas; ?></strong> alertas
                        </a> •
                        <a href="../Assinatura/listar_assinaturas_pendentes.php" class="text-white underline">
                            <strong><?php echo count($assinaturasPendentes); ?></strong> assinaturas
                        </a> •
                        <strong><?php echo htmlspecialchars($pontuacaoMensal ?? 0); ?></strong> pontos
                    </p>
                </div>
                <div class="hidden md:block">
                    <i class="fas fa-tachometer-alt text-2xl text-blue-200"></i>
                </div>
            </div>
        </div>
        

        
        <?php if ($totalAlertasVencidos > 0): ?>
        <!-- Botão para mostrar o popup de alertas vencidos - Tamanho reduzido -->
        <div class="mb-4 bg-red-50 border-l-4 border-red-500 rounded-md overflow-hidden shadow-sm hover:shadow-md mt-2">
            <div class="px-3 py-2">  <!-- Reduzido o padding -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500 text-sm"></i> <!-- Trocado por ícone mais simples -->
                        </div>
                        <div class="ml-2">
                            <div class="text-xs font-medium text-red-800">
                                <strong><?php echo $totalAlertasVencidos; ?></strong> <?php echo $totalAlertasVencidos > 1 ? 'alertas' : 'alerta'; ?> com prazo vencido
                            </div>
                        </div>
                    </div>
                    <button id="showAlertasVencidosBtn" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded transition-colors duration-300 flex items-center">
                        Resolver Alertas
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // ===== OTIMIZAÇÃO: Cache da consulta GovNex para evitar lentidão =====
        // Limpar cache antigo para forçar nova consulta
        if (isset($_SESSION['govnex_saldo']) && isset($_GET['refresh_api'])) {
            unset($_SESSION['govnex_saldo']);
            unset($_SESSION['govnex_saldo_time']);
        }
        
        // Usar cache de 5 minutos para não impactar performance
        if (isset($_SESSION['govnex_saldo']) && (time() - $_SESSION['govnex_saldo_time']) < 300) {
            // Usar cache de 5 minutos
            $saldoInfo = $_SESSION['govnex_saldo'];
        } else {
            // Se o usuário não tiver CNPJ, usamos um CNPJ padrão para teste
            $cnpj = isset($_SESSION['user']['cnpj']) ? $_SESSION['user']['cnpj'] : '11336672000199';
            
            // Timeout mais baixo para não travar a página
            $saldoInfo = consultarSaldoGovNexRapido($cnpj);
            $_SESSION['govnex_saldo'] = $saldoInfo;
            $_SESSION['govnex_saldo_time'] = time();
        }
        ?>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            

            <!-- Card para Saldo GovNex -->
            <div class="col-span-1">
                <div class="bg-white rounded-lg shadow-sm border-l-4 border-green-400 border border-gray-100">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-wallet text-green-500 mr-2 text-xs"></i>
                                Saldo API
                            </h3>
                            <div class="flex items-center space-x-2">
                                <a href="?refresh_api=1" class="text-gray-400 hover:text-green-500 transition-colors duration-200" title="Atualizar saldo">
                                    <i class="fas fa-sync-alt text-xs"></i>
                                </a>
                                <?php if (isset($saldoInfo['status']) && $saldoInfo['status'] === 'success'): ?>
                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800">
                                    Ativo
                                </span>
                                <?php else: ?>
                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-800">
                                    Inativo
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (isset($saldoInfo['error'])): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-2 mb-2">
                                <p class="text-red-700 text-xs font-medium mb-1">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    Erro ao consultar saldo
                                </p>
                                <p class="text-red-600 text-xs"><?php echo htmlspecialchars($saldoInfo['error']); ?></p>
                                <?php if (isset($saldoInfo['raw_response'])): ?>
                                    <details class="mt-2">
                                        <summary class="text-xs text-gray-600 cursor-pointer hover:text-gray-800">Ver resposta da API</summary>
                                        <pre class="text-xs bg-gray-100 p-2 mt-1 rounded overflow-auto max-h-32"><?php echo htmlspecialchars($saldoInfo['raw_response']); ?></pre>
                                    </details>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 space-y-1">
                                <p><strong>CNPJ consultado:</strong> <?php echo htmlspecialchars($cnpj ?? 'N/A'); ?></p>
                                <p><strong>URL:</strong> <a href="https://govnex.site/govnex/api/saldo_api.php" target="_blank" class="text-blue-600 hover:underline">govnex.site/api</a></p>
                                <a href="?refresh_api=1" class="inline-block mt-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                                    <i class="fas fa-sync-alt mr-1"></i> Tentar novamente
                                </a>
                            </div>
                        <?php elseif (isset($saldoInfo['status']) && $saldoInfo['status'] === 'success'): ?>
                            <div class="flex flex-col space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-500">Crédito disponível:</span>
                                    <?php 
                                    // Extrair o valor do saldo de forma segura
                                    $saldo_valor = 0;
                                    if (isset($saldoInfo['saldo'])) {
                                        // Verificar se o formato existe e extrair o valor numérico dele
                                        if (isset($saldoInfo['saldo']['formato'])) {
                                            // Converter o formato (ex: "0,00") para um valor numérico
                                            $saldo_formato = $saldoInfo['saldo']['formato'];
                                            // Substituir vírgula por ponto para conversão correta
                                            $saldo_formato = str_replace(',', '.', $saldo_formato);
                                            $saldo_valor = floatval($saldo_formato);
                                        }
                                    }
                                    
                                    $cor_texto = 'text-green-600';
                                    if ($saldo_valor <= 0) {
                                        $cor_texto = 'text-red-600';
                                    } elseif ($saldo_valor < 3) {
                                        $cor_texto = 'text-amber-600';
                                    }
                                    ?>
                                    <span class="text-sm font-bold <?php echo $cor_texto; ?>">R$ <?php echo htmlspecialchars($saldoInfo['saldo']['formato']); ?></span>
                                </div>
                                
                                <?php if ($saldo_valor <= 0): ?>
                                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-2 text-xs">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Saldo zerado! Faça uma recarga imediatamente.
                                </div>
                                <?php elseif ($saldo_valor < 3): ?>
                                <div class="bg-amber-100 border-l-4 border-amber-500 text-amber-700 p-2 text-xs">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Saldo baixo! Considere fazer uma recarga em breve.
                                </div>
                                <?php endif; ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-500">Usuário:</span>
                                    <span class="text-xs text-gray-700 break-words"><?php echo htmlspecialchars($saldoInfo['usuario']['nome']); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-500">CNPJ:</span>
                                    <span class="text-xs text-gray-700"><?php echo htmlspecialchars($saldoInfo['usuario']['cnpj']); ?></span>
                                </div>
                            </div>
                            <div class="mt-3 text-center">
                                <a href="https://govnex.site/govnex/" target="_blank" 
                                   class="inline-block px-3 py-1 text-xs font-medium text-green-800 bg-green-100 rounded hover:bg-green-200 transition-all duration-200 transform hover:scale-105">
                                   Acessar GovNex
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-xs italic">Não foi possível consultar o saldo.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Card para Aprovação de Estabelecimentos -->
            <div class="col-span-1">
                <div class="bg-gradient-to-br from-white to-blue-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-1 border-l-4 border-blue-400 border-t border-r border-b border-gray-100 overflow-hidden group">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-base font-bold text-gray-800 flex items-center group-hover:text-blue-600 transition-colors duration-300">
                                <i class="fas fa-check-circle text-blue-500 mr-2 group-hover:text-blue-600 group-hover:animate-bounce transition-all duration-300"></i>
                                <span class="truncate">Aprovação de Estabelecimentos</span>
                            </h3>
                            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 group-hover:bg-amber-200 transition-colors duration-300 border border-amber-200 shadow-sm flex items-center">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                                <?php echo htmlspecialchars($estabelecimentoModel->countEstabelecimentosPendentes($municipioUsuario)); ?>
                            </span>
                        </div>
                        <?php if (empty($estabelecimentosPendentes)) : ?>
                            <p class="text-gray-500 text-xs italic">Não há estabelecimentos pendentes de aprovação.</p>
                        <?php else : ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($estabelecimentosPendentes as $estabelecimento) : ?>
                                    <li class="py-2 flex justify-between items-center hover:bg-blue-50/50 rounded transition-all duration-200 cursor-pointer">
                                        <div class="text-xs text-gray-700 break-words flex-1 min-w-0">
                                            <?php echo htmlspecialchars($estabelecimento['nome_fantasia'] ?? ''); ?>
                                        </div>
                                        <div class="flex space-x-1">
                                            <!-- Ícone para Atividades -->
                                            <a href="#" class="text-gray-400 hover:text-blue-600 transition-all duration-200 transform hover:scale-110 p-1" 
                                               data-bs-toggle="modal" data-bs-target="#atividadesModal"
                                               data-id="<?php echo htmlspecialchars($estabelecimento['id']); ?>"
                                               data-tipo-pessoa="<?php echo htmlspecialchars($estabelecimento['tipo_pessoa']); ?>">
                                                <i class="fas fa-tasks text-xs"></i>
                                            </a>

                                            <!-- Ícone para Detalhes -->
                                            <a href="<?php echo $estabelecimento['tipo_pessoa'] === 'fisica'
                                                            ? '../Estabelecimento/detalhes_pessoa_fisica.php?id=' . htmlspecialchars($estabelecimento['id'])
                                                            : '../Estabelecimento/detalhes_estabelecimento.php?id=' . htmlspecialchars($estabelecimento['id']); ?>"
                                                class="text-blue-500 hover:text-blue-700 transition-all duration-200 transform hover:scale-110 p-1">
                                                <i class="far fa-eye text-xs"></i>
                                            </a>

                                            <!-- Ícone para Aprovar -->
                                            <a href="../Estabelecimento/aprovar_forcado.php?id=<?php echo htmlspecialchars($estabelecimento['id']); ?>"
                                                class="text-green-500 hover:text-green-700 transition-all duration-200 transform hover:scale-110 p-1"
                                                onclick="return confirm('Deseja aprovar este estabelecimento?');">
                                                <i class="fas fa-check text-xs"></i>
                                            </a>

                                            <!-- Botão para Rejeitar -->
                                            <button class="text-white bg-red-500 hover:bg-red-600 rounded transition-all duration-200 transform hover:scale-110 p-1 w-5 h-5 flex items-center justify-center" 
                                                    data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                    data-id="<?php echo htmlspecialchars($estabelecimento['id']); ?>">
                                                <i class="fas fa-times text-[10px]"></i>
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-3 text-center">
                                <a href="../Estabelecimento/listar_todos_pendentes.php" 
                                   class="inline-block px-3 py-1 text-xs font-medium text-amber-800 bg-amber-100 rounded hover:bg-amber-200 transition-all duration-200 transform hover:scale-105">
                                   Ver todos
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="atividadesModal" tabindex="-1" aria-labelledby="atividadesModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="atividadesModalLabel">Atividades</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="atividadesContent">
                                <!-- Conteúdo das atividades será carregado aqui via AJAX -->
                                <p>Carregando...</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>

                   <!-- Card para Processos com Documentação Pendente -->
                   <div class="col-span-1">
                <div class="bg-white rounded-lg shadow-sm border-l-4 border-amber-400 border border-gray-100">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-file-alt text-amber-500 mr-2 text-xs"></i>
                                Documentação Pendente
                            </h3>
                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-800">
                                <?php echo count($processosPendentes); ?>
                            </span>
                        </div>

                        <?php
                        // Pegar apenas os 5 últimos processos pendentes
                        $ultimosPendentes = array_slice($processosPendentes, 0, 3);
                        if (empty($ultimosPendentes)) : ?>
                            <p class="text-gray-500 text-xs italic">Não há processos com documentação pendente no momento.</p>
                        <?php else : ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($ultimosPendentes as $processo) : ?>
                                    <li class="py-2 hover:bg-blue-50 rounded transition-all duration-200 cursor-pointer" 
                                        onclick="window.location.href='../Processo/documentos.php?processo_id=<?php echo htmlspecialchars($processo['processo_id'] ?? ''); ?>&id=<?php echo htmlspecialchars($processo['estabelecimento_id'] ?? ''); ?>'">
                                        <div class="text-xs text-gray-700">
                                            <div class="font-medium break-words leading-tight mb-1">Processo #<?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?> - <?php echo htmlspecialchars($processo['nome_fantasia'] ?? ''); ?></div>
                                            <?php
                                            $dataUploadPendente = new DateTime($processo['data_upload_pendente'] ?? 'now');
                                            $dataAtual = new DateTime();
                                            $diasPendentes = $dataUploadPendente->diff($dataAtual)->days;
                                            ?>
                                            <div class="text-[10px] <?php echo $diasPendentes > 5 ? 'text-red-500 font-medium' : 'text-gray-500'; ?> flex items-center">
                                                <i class="fas fa-clock mr-1 <?php echo $diasPendentes > 5 ? 'animate-pulse' : ''; ?>"></i>
                                                Pendente há <?php echo $diasPendentes; ?> dias
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (count($processosPendentes) > 4) : ?>
                                <div class="mt-3 text-center">
                                    <a href="../Processo/listar_pendentes.php" 
                                       class="inline-block px-3 py-1 text-xs font-medium text-amber-800 bg-amber-100 rounded hover:bg-amber-200 transition-all duration-200 transform hover:scale-105">
                                       Ver todos
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

                     <!-- Card para Ordens de Serviço Ativas -->
                     <div class="col-span-1">
                <div class="bg-gradient-to-br from-white to-indigo-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-1 border-l-4 border-indigo-400 border-t border-r border-b border-gray-100 overflow-hidden group">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-base font-bold text-gray-800 flex items-center group-hover:text-blue-600 transition-colors duration-300">
                                <i class="fas fa-clipboard-list text-blue-500 mr-2 group-hover:text-blue-600 transition-all duration-300"></i>
                                <span class="truncate">Minhas Ordens de Serviço</span>
                            </h3>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 group-hover:bg-amber-200 transition-colors duration-300">
                                <?php echo $totalOrdensAtivas; ?>
                            </span>
                        </div>

                        <?php if (empty($ordensServico)) : ?>
                            <p class="text-gray-500 text-xs italic">Não há ordens de serviço ativas para você no momento.</p>
                        <?php else : ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($ordensServico as $ordem) : ?>
                                    <li class="py-2 hover:bg-blue-50/50 rounded transition-all duration-200 cursor-pointer">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="text-xs text-gray-700 flex-1 min-w-0">
                                                <?php 
                                                    $osNumber = $ordem['id'] . '.' . date('Y', strtotime($ordem['data_inicio']));
                                                    $estabelecimento = !empty($ordem['nome_fantasia']) ? $ordem['nome_fantasia'] : 'Sem estabelecimento';
                                                ?>
                                                <div class="font-medium break-words leading-tight mb-1">OS #<?php echo htmlspecialchars($osNumber); ?></div>
                                                <div class="text-[10px] text-gray-500 break-words leading-tight">
                                                    <?php echo htmlspecialchars($estabelecimento); ?>
                                                </div>
                                            </div>
                                            <div class="flex space-x-1 shrink-0">
                                                <a href="<?php echo empty($ordem['estabelecimento_id']) ? '../OrdemServico/detalhes_ordem_sem_estabelecimento.php?id=' . htmlspecialchars($ordem['id']) : '../OrdemServico/detalhes_ordem.php?id=' . htmlspecialchars($ordem['id']); ?>" 
                                                   class="text-blue-500 hover:text-blue-700 transition-all duration-200 transform hover:scale-110 p-1">
                                                   <i class="far fa-eye text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-3 text-center">
                                <a href="../OrdemServico/listar_ordens_tecnico.php" 
                                   class="inline-block px-3 py-1 text-xs font-medium text-amber-800 bg-amber-100 rounded hover:bg-amber-200 transition-all duration-200 transform hover:scale-105">
                                   Ver todos
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

                        <!-- Card para Alertas Próximos a Vencer -->
                        <div class="col-span-1">
                <div class="bg-gradient-to-br from-white to-red-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-1 border-l-4 border-red-400 border-t border-r border-b border-gray-100 overflow-hidden group" data-dropdown>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3 cursor-pointer dropdown-header">
                            <h3 class="text-sm font-bold text-gray-800 flex items-center group-hover:text-blue-600 transition-colors duration-300">
                                <i class="fas fa-exclamation-triangle text-amber-500 mr-2 group-hover:text-amber-600 transition-all duration-300 text-xs"></i>
                                <span class="truncate">Alertas Próximos a Vencer</span>
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 group-hover:bg-amber-200 transition-colors duration-300">
                                    <?php echo $totalAlertasAtivos; ?>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200 dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Texto de instrução quando fechado -->
                        <div class="dropdown-hint text-xs text-gray-400 italic mb-2">
                            <i class="fas fa-mouse-pointer mr-1"></i>Clique para ver os dados
                        </div>

                        <div class="dropdown-content">
                        <?php if (empty($alertasProximosAVencer)) : ?>
                            <p class="text-gray-500 text-xs italic">Não há alertas próximos a vencer no momento.</p>
                        <?php else : ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($alertasProximosAVencer as $alerta) : ?>
                                    <?php
                                    $diasRestantes = $alerta['dias_restantes'] ?? 0;
                                    $urgencyClass = '';
                                    if ($diasRestantes <= 0) {
                                        $urgencyClass = 'bg-red-50 border-l-2 border-red-500';
                                    } elseif ($diasRestantes <= 3) {
                                        $urgencyClass = 'bg-amber-50 border-l-2 border-amber-500';
                                    }
                                    ?>
                                    <li class="py-2 hover:bg-blue-50/50 rounded transition-all duration-200 cursor-pointer <?php echo $urgencyClass; ?>">
                                        <div class="flex justify-between items-start px-1">
                                            <div class="text-xs text-gray-700 flex-1 min-w-0">
                                                <div class="font-medium break-words leading-tight mb-1"><?php echo htmlspecialchars($alerta['nome_fantasia'] ?? ''); ?></div>
                                                <div class="text-[10px] text-gray-500">Prazo: <?php echo htmlspecialchars(date('d/m/Y', strtotime($alerta['prazo']))); ?></div>
                                                <div class="text-[10px] flex items-center <?php 
                                                    if ($diasRestantes <= 0) echo 'text-red-500 font-medium';
                                                    elseif ($diasRestantes <= 3) echo 'text-amber-500 font-medium';
                                                    else echo 'text-gray-500';
                                                ?>">
                                                    <i class="fas fa-clock mr-1 <?php echo ($diasRestantes <= 3) ? 'animate-pulse' : ''; ?>"></i>
                                                    <?php
                                                    if ($diasRestantes > 0) {
                                                        echo "Faltam $diasRestantes dias";
                                                    } elseif ($diasRestantes == 0) {
                                                        echo "Vence hoje!";
                                                    } else {
                                                        echo "Vencido há " . abs($diasRestantes) . " dias";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="shrink-0">
                                                <a href="../Alertas/detalhes_alerta.php?alerta_id=<?php echo htmlspecialchars($alerta['id'] ?? ''); ?>" 
                                                   class="text-amber-500 hover:text-amber-700 transition-all duration-200 transform hover:scale-110 p-1">
                                                   <i class="far fa-eye text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($totalAlertas > 3) : ?>
                                <div class="mt-3 text-center">
                                    <a href="todos_os_alertas.php" 
                                       class="inline-block px-3 py-1 text-xs font-medium text-amber-800 bg-amber-100 rounded hover:bg-amber-200 transition-all duration-200 transform hover:scale-105">
                                       Ver todos
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card para Processos para Resolver -->
            <div class="col-span-1">
                <div class="bg-gradient-to-br from-white to-purple-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-1 border-l-4 border-purple-400 border-t border-r border-b border-gray-100 overflow-hidden group" data-dropdown>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3 cursor-pointer dropdown-header">
                            <h3 class="text-base font-bold text-gray-800 flex items-center group-hover:text-blue-600 transition-colors duration-300">
                                <i class="fas fa-tasks text-blue-500 mr-2 group-hover:text-blue-600 transition-all duration-300"></i>
                                <span class="truncate">Processos Designados</span>
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 group-hover:bg-amber-200 transition-colors duration-300">
                                    <?php echo count($responsabilidades); ?>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200 dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Texto de instrução quando fechado -->
                        <div class="dropdown-hint text-xs text-gray-400 italic mb-2">
                            <i class="fas fa-mouse-pointer mr-1"></i>Clique para ver os dados
                        </div>
                        
                        <div class="dropdown-content">
                        <?php if (empty($responsabilidades)) : ?>
                            <p class="text-gray-500 text-xs italic">Não há processos designados para você resolver no momento.</p>
                        <?php else : ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($responsabilidades as $resp) : ?>
                                    <li class="py-2 hover:bg-blue-50/50 rounded transition-all duration-200 cursor-pointer">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="text-xs text-gray-700 flex-1 min-w-0">
                                                <div class="font-medium break-words leading-tight mb-1">Processo #<?php echo htmlspecialchars($resp['numero_processo'] ?? ''); ?> - <?php echo htmlspecialchars($resp['nome_fantasia'] ?? ''); ?></div>
                                                <div class="text-[10px] text-gray-500 break-words leading-tight">Descrição: <?php echo htmlspecialchars($resp['descricao'] ?? ''); ?></div>
                                            </div>
                                            <div class="flex space-x-1 shrink-0">
                                                <a href="../Processo/documentos.php?processo_id=<?php echo htmlspecialchars($resp['id'] ?? ''); ?>&id=<?php echo htmlspecialchars($resp['estabelecimento_id'] ?? ''); ?>" 
                                                   class="text-blue-500 hover:text-blue-700 transition-all duration-200 transform hover:scale-110 p-1">
                                                   <i class="far fa-eye text-xs"></i>
                                                </a>
                                                <a href="#" onclick="confirmFinalize('<?php echo htmlspecialchars($resp['id'] ?? ''); ?>')" 
                                                   class="text-green-500 hover:text-green-700 transition-all duration-200 transform hover:scale-110 p-1">
                                                   <i class="fas fa-check text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-3 text-center">
                                <a href="../Processo/listar_pendentes.php" 
                                   class="inline-block px-3 py-1 text-xs font-medium text-amber-800 bg-amber-100 rounded hover:bg-amber-200 transition-all duration-200 transform hover:scale-105">
                                   Ver todos
                                </a>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card para Processos Acompanhados -->
            <div class="col-span-1">
                <div class="bg-gradient-to-br from-white to-teal-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-1 border-l-4 border-teal-400 border-t border-r border-b border-gray-100 overflow-hidden group" data-dropdown>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3 cursor-pointer dropdown-header">
                            <h3 class="text-base font-bold text-gray-800 flex items-center group-hover:text-blue-600 transition-colors duration-300">
                                <i class="fas fa-eye text-blue-500 mr-2 group-hover:text-blue-600 transition-all duration-300"></i>
                                <span class="truncate">Processos Acompanhados</span>
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 group-hover:bg-amber-200 transition-colors duration-300">
                                    <?php echo count($processosAcompanhados); ?>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200 dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Texto de instrução quando fechado -->
                        <div class="dropdown-hint text-xs text-gray-400 italic mb-2">
                            <i class="fas fa-mouse-pointer mr-1"></i>Clique para ver os dados
                        </div>

                        <div class="dropdown-content">
                        <?php if (empty($processosAcompanhados)) : ?>
                            <p class="text-gray-500 text-xs italic">Você não está acompanhando nenhum processo no momento.</p>
                        <?php else : ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($processosAcompanhados as $processo) : ?>
                                    <li class="py-2 hover:bg-blue-50/50 rounded transition-all duration-200 cursor-pointer">
                                        <div class="flex justify-between items-center gap-2">
                                            <div class="text-xs text-gray-700 flex-1 min-w-0">
                                                <div class="font-medium break-words leading-tight">Processo #<?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?> - <?php echo htmlspecialchars($processo['nome_fantasia'] ?? ''); ?></div>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <a href="../Processo/documentos.php?processo_id=<?php echo htmlspecialchars($processo['id'] ?? ''); ?>&id=<?php echo htmlspecialchars($processo['estabelecimento_id'] ?? ''); ?>" 
                                                   class="text-blue-500 hover:text-blue-700 transition-all duration-200 transform hover:scale-110 p-1">
                                                   <i class="far fa-eye text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card para Processos Parados -->
            <div class="col-span-1">
                <div class="bg-gradient-to-br from-white to-red-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-1 border-l-4 border-red-500 border-t border-r border-b border-gray-100 overflow-hidden group" data-dropdown>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3 cursor-pointer dropdown-header">
                            <h3 class="text-base font-bold text-gray-800 flex items-center group-hover:text-blue-600 transition-colors duration-300">
                                <i class="fas fa-pause-circle text-blue-500 mr-2 group-hover:text-blue-600 transition-all duration-300"></i>
                                <span class="truncate">Processos Parados</span>
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 group-hover:bg-amber-200 transition-colors duration-300">
                                    <?php echo count($processosParados); ?>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200 dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Texto de instrução quando fechado -->
                        <div class="dropdown-hint text-xs text-gray-400 italic mb-2">
                            <i class="fas fa-mouse-pointer mr-1"></i>Clique para ver os dados
                        </div>

                        <div class="dropdown-content">
                        <?php if (empty($processosParados)) : ?>
                            <p class="text-gray-500 text-xs italic">Não há processos parados no momento.</p>
                        <?php else : ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($processosParados as $processo) : ?>
                                    <li class="py-2 hover:bg-blue-50/50 rounded transition-all duration-200 cursor-pointer">
                                        <div class="flex justify-between items-center gap-2">
                                            <div class="text-xs text-gray-700 flex-1 min-w-0">
                                                <div class="font-medium break-words leading-tight">Processo #<?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?> - <?php echo htmlspecialchars($processo['nome_fantasia'] ?? ''); ?></div>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <a href="../Processo/documentos.php?processo_id=<?php echo htmlspecialchars($processo['id'] ?? ''); ?>&id=<?php echo htmlspecialchars($processo['estabelecimento_id'] ?? ''); ?>" 
                                                   class="text-blue-500 hover:text-blue-700 transition-all duration-200 transform hover:scale-110 p-1">
                                                   <i class="far fa-eye text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Card para Alertas Tratados Pendentes de Verificação -->
            <div class="col-span-1">
                <div class="bg-gradient-to-br from-white to-yellow-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-1 border-l-4 border-yellow-400 border-t border-r border-b border-gray-100 overflow-hidden group" data-dropdown>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3 cursor-pointer dropdown-header">
                            <h3 class="text-base font-bold text-gray-800 flex items-center group-hover:text-blue-600 transition-colors duration-300">
                                <i class="fas fa-check-double text-amber-500 mr-2 group-hover:text-amber-600 transition-all duration-300"></i>
                                <span class="truncate">Alertas Resolvidos Pendentes</span>
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 group-hover:bg-amber-200 transition-colors duration-300 border border-amber-200 shadow-sm flex items-center">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                                    <?php echo $alertasTratadosPendentes; ?>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200 dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Texto de instrução quando fechado -->
                        <div class="dropdown-hint text-xs text-gray-400 italic mb-2">
                            <i class="fas fa-mouse-pointer mr-1"></i>Clique para ver os dados
                        </div>

                        <div class="dropdown-content">
                        <p class="text-sm text-gray-500 mb-2">Alertas que foram Resolvidos pelos estabelecimentos e aguardam sua verificação.</p>
                        
                        <?php if ($alertasTratadosPendentes > 0): ?>
                            <div class="mt-2 flex items-center">
                                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 mr-3">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-600">
                                        Alertas que precisam de sua avaliação
                                    </div>
                                    <div class="mt-1 text-center">
                                        <a href="../Alertas/alertas_tratados_pendentes.php" class="inline-block px-3 py-1 text-xs font-medium text-amber-800 bg-amber-100 rounded hover:bg-amber-200 transition-all duration-200 transform hover:scale-105">
                                            Verificar Alertas
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center mt-3">
                                <span class="text-green-600 text-sm font-medium">
                                    <i class="fas fa-check-circle mr-1"></i> Nenhum alerta pendente
                                </span>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Inserir Motivo de Rejeição -->
            <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectModalLabel">Negar Estabelecimento</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="../../controllers/EstabelecimentoController.php?action=rejectEstabelecimento" method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="id" id="rejectEstabelecimentoId">
                                <div class="form-group">
                                    <label for="motivoSelect">Selecione o Motivo da Rejeição</label>
                                    <select class="form-control" id="motivoSelect">
                                        <option value="">Selecione um motivo Predefinido</option>
                                        <option value="1" data-full-text="Estabelecimento não é de competência da VISA Municipal de {municipio}. Para dar abertura ao seu processo, acesse o site https://vigilancia-to.com.br ou entre em contato com a Vigilância Sanitária Estadual através do número (63) 3218-3264.">
                                            Competência estadual
                                        </option>
                                        <option value="2" data-full-text="Nenhum cnae compatível com a portaria n°0272/2024 de 10 de setembro de 2024( Publicado no Diário Oficial do Município de Gurupi n° 1083).">
                                            Nenhum cnae compatível com a portaria
                                        </option>
                                        <option value="3" data-full-text="">
                                            Escrever Motivo
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="motivo">Motivo da Rejeição</label>
                                    <textarea class="form-control transition-effect" id="motivo" name="motivo" rows="5" required></textarea>
                                </div>


                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-danger">Negar Estabelecimento</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>


            <?php if ($camposIncompletos) : ?>
                <div class="modal fade" id="incompleteProfileModal" tabindex="-1" aria-labelledby="incompleteProfileModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="incompleteProfileModalLabel">Informações Incompletas</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Você precisa atualizar suas informações cadastrais. Por favor, complete os campos do cadastro do usuário.</p>
                            </div>
                            <div class="modal-footer">
                                <a href="../Admin/editar_cadastro_usuario.php" class="btn btn-primary">Atualizar Agora</a>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    $(document).ready(function() {
                        $('#incompleteProfileModal').modal('show');
                    });
                </script>
            <?php endif; ?>


            <script>
                var rejectModal = document.getElementById('rejectModal');
                rejectModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget; // Botão que acionou o modal
                    var estabelecimentoId = button.getAttribute('data-id'); // ID do estabelecimento
                    var modalBodyInput = rejectModal.querySelector('.modal-body input#rejectEstabelecimentoId');
                    modalBodyInput.value = estabelecimentoId;
                });

                function confirmFinalize(processoId) {
                    if (confirm("Tem certeza que você resolveu as pendências neste processo?")) {
                        window.location.href = '../../controllers/ProcessoController.php?action=finalize&id=' + processoId;
                    }
                }
                document.getElementById('motivoSelect').addEventListener('change', function() {
                    var selectedOption = this.options[this.selectedIndex];
                    var fullText = selectedOption.getAttribute('data-full-text');
                    var motivoTextarea = document.getElementById('motivo');
                    var motivoLabel = document.querySelector('label[for="motivo"]');

                    // Substituir {municipio} pelo município do usuário logado
                    var municipio = '<?php echo htmlspecialchars($municipioUsuario); ?>';
                    if (fullText) {
                        fullText = fullText.replace('{municipio}', municipio);
                    }

                    motivoTextarea.value = fullText;

                    // Mostrar o campo motivo e seu label se qualquer opção for selecionada
                    if (this.value !== "") {
                        motivoTextarea.style.display = "block";
                        motivoTextarea.disabled = false;
                        motivoLabel.style.display = "block";
                    } else {
                        motivoTextarea.style.display = "none";
                        motivoTextarea.disabled = true;
                        motivoLabel.style.display = "none";
                    }

                    // Adicionar a classe 'filled' para o efeito visual
                    motivoTextarea.classList.add('filled');
                });

                // Inicialmente ocultar o campo motivo e seu label se a opção "Selecione um motivo Predefinido" estiver selecionada
                document.addEventListener('DOMContentLoaded', function() {
                    var motivoTextarea = document.getElementById('motivo');
                    var motivoLabel = document.querySelector('label[for="motivo"]');
                    motivoTextarea.style.display = "none";
                    motivoTextarea.disabled = true;
                    motivoLabel.style.display = "none";
                });

                // Remover a classe 'filled' quando o conteúdo da textarea for removido
                document.getElementById('motivo').addEventListener('input', function() {
                    if (this.value === '') {
                        this.classList.remove('filled');
                    }
                });

                // Verificar o motivo selecionado antes de enviar o formulário
                document.querySelector('form[action="../../controllers/EstabelecimentoController.php?action=rejectEstabelecimento"]').addEventListener('submit', function(event) {
                    var motivoSelect = document.getElementById('motivoSelect');
                    if (motivoSelect.value === "") {
                        event.preventDefault();
                        alert('Por favor, selecione um motivo válido para a rejeição.');
                    }
                });

                document.addEventListener('DOMContentLoaded', function() {
                    var atividadesModal = document.getElementById('atividadesModal');
                    atividadesModal.addEventListener('show.bs.modal', function(event) {
                        var button = event.relatedTarget; // Botão que acionou o modal
                        var estabelecimentoId = button.getAttribute('data-id'); // ID do estabelecimento
                        var atividadesContent = document.getElementById('atividadesContent');

                        // Limpar conteúdo antigo
                        atividadesContent.innerHTML = '<p>Carregando...</p>';

                        // Fazer a requisição AJAX
                        fetch(`get_atividades.php?id=${estabelecimentoId}`)
                            .then(response => response.text())
                            .then(data => {
                                atividadesContent.innerHTML = data;
                            })
                            .catch(error => {
                                atividadesContent.innerHTML = '<p>Erro ao carregar atividades.</p>';
                            });
                    });
                });
            </script>

            <style>
                /* Estilos para animação do modal */
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes slideDown {
                    from { transform: translateY(-50px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                
                @keyframes scaleUp {
                    from { transform: scale(0.8); opacity: 0; }
                    to { transform: scale(1); opacity: 1; }
                }
                
                /* Animações de bounce, shake e pulse foram removidas */
                
                .modal-backdrop-animate {
                    animation: fadeIn 0.3s ease-out;
                }
                
                .modal-content-animate {
                    animation: scaleUp 0.4s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
                }
                
                .modal-header-animate {
                    animation: slideDown 0.5s ease-out forwards;
                    animation-delay: 0.2s;
                    opacity: 0;
                }
                
                .modal-item-animate {
                    animation: slideDown 0.5s ease-out forwards;
                    opacity: 0;
                }
                
                /* Classes de efeito removidas */
            </style>
            
            <!-- Modal de Alertas Vencidos 
            <div id="alertasVencidosModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 overflow-y-auto flex justify-center items-start pt-16">
                <div class="w-full max-w-2xl mx-auto px-4" id="modalBackdrop">
                    
                    <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-h-[70vh] md:max-h-[70vh] relative" id="modalContent">
                        <div class="absolute top-0 right-0 pt-3 pr-3">
                            <button type="button" id="closeAlertasModal" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none">
                                <span class="sr-only">Fechar</span>
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                        
                       
                        <div class="bg-gradient-to-r from-red-600 to-red-700 px-4 py-3 sm:px-6 flex items-center" id="modalHeader">
                            <div class="mr-3 h-8 w-8 bg-red-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                            <h3 class="text-lg leading-6 font-medium text-white">
                                Alertas Vencidos 
                                <span class="bg-red-800 text-white text-xs font-bold px-2 py-0.5 rounded ml-2">
                                    <?php echo $totalAlertasVencidos; ?>
                                </span>
                            </h3>
                        </div>
                        
                       
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <p class="text-sm text-gray-500 mb-4">Estes alertas estão com o prazo vencido e precisam ser encerrados ou ter seus prazos atualizados.</p>
                            
                            <?php if (!empty($alertasVencidos)): ?>
                                <div class="overflow-y-auto max-h-48 md:max-h-80">
                                    <div class="rounded-md border border-gray-200 divide-y divide-gray-200">
                                        <?php foreach ($alertasVencidos as $index => $alerta): ?>
                                            <div id="alerta-item-<?php echo $alerta['id']; ?>" class="p-4 <?php echo $index % 2 == 0 ? 'bg-white' : 'bg-gray-50'; ?> modal-item-animate" style="animation-delay: <?php echo (0.2 + ($index * 0.1)); ?>s;">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <div class="flex flex-wrap items-center mb-1">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] md:text-xs font-medium bg-red-100 text-red-800">
                                                                <i class="fas fa-calendar-times mr-0.5"></i> <?php echo floor((time() - strtotime($alerta['prazo'])) / 86400); ?> dias
                                                            </span>
                                                            <span class="ml-1 text-[10px] md:text-xs text-gray-500">
                                                                <?php echo date('d/m/Y', strtotime($alerta['prazo'])); ?>
                                                            </span>
                                                        </div>
                                                        <h4 class="text-xs md:text-sm font-medium text-gray-900 line-clamp-2"><?php echo htmlspecialchars($alerta['descricao']); ?></h4>
                                                        <p class="text-[10px] md:text-xs text-gray-500 mt-0.5">
                                                            <i class="fas fa-file-alt mr-0.5"></i> <?php echo htmlspecialchars($alerta['numero_processo']); ?>
                                                        </p>
                                                    </div>
                                                    <div class="ml-2 flex-shrink-0 flex gap-1 md:gap-2">
                                                        <button type="button" class="px-2 py-1 md:px-3 md:py-1.5 bg-green-600 hover:bg-green-700 text-white text-[10px] md:text-xs rounded shadow-sm transition-colors duration-150 finalizar-alerta" data-alerta-id="<?php echo $alerta['id']; ?>">
                                                            <i class="fas fa-check md:mr-1"></i><span class="hidden md:inline"> Finalizar</span>
                                                        </button>
                                                        <a href="../Alertas/detalhes_alerta.php?alerta_id=<?php echo $alerta['id']; ?>" class="px-2 py-1 md:px-3 md:py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] md:text-xs rounded shadow-sm transition-colors duration-150 flex items-center">
                                                            <i class="fas fa-eye md:mr-1"></i><span class="hidden md:inline"> Detalhes</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-6 bg-gray-50 rounded-md">
                                    <p class="text-gray-500">Nenhum alerta vencido.</p>
                                </div>
                            <?php endif; ?>
                        </div> 
                        
                       
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div> -->

<style>
    /* Estilos para animação do modal */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { transform: translateY(100px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    #modalContent {
        transition: all 0.3s ease;
        transform: translateY(100px);
        opacity: 0;
                    }
                    
    #alertasVencidosModal.hidden {
        display: none;
                    }
                    
    #alertasVencidosModal:not(.hidden) #modalContent {
        animation: slideUp 0.3s forwards;
    }
    
    /* Ajustes específicos para dispositivos móveis */
    @media (max-width: 767px) {
        #alertasVencidosModal {
            align-items: flex-start;
            padding-top: 80px; /* Espaço para o menu superior */
        }
        
        #modalContent {
            max-height: 75vh !important;
            width: 95%;
        }
        
        #modalBackdrop {
            padding-left: 8px;
            padding-right: 8px;
        }
        
        #closeAlertasModal {
            transform: scale(0.9);
            padding: 4px;
        }
        
        #closeAlertasModal i {
            font-size: 0.9rem;
        }
        
        /* Reduzir tamanho da fonte e espaçamentos em telas pequenas */
        #modalHeader h3 {
            font-size: 0.95rem;
        }
        
        #alertasVencidosModal .p-4 {
            padding: 0.75rem;
                                    }
        
        #alertasVencidosModal .py-3 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
                            }
</style>

            <!-- Script para gerenciar a instalação do atalho na tela inicial -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Variáveis para armazenar o evento de instalação e elementos da interface
                let deferredPrompt;
                const pwaPrompt = document.getElementById('pwaPrompt');
                const installButton = document.getElementById('installPwa');
                const dismissButton = document.getElementById('dismissPwa');
                
                // Verificar se os elementos existem antes de continuar
                if (!pwaPrompt || !installButton || !dismissButton) {
                    console.warn('Elementos PWA não encontrados no DOM');
                    return;
                }
                
                // Verifica se o usuário já dispensou a notificação anteriormente
                const isPwaPromptDismissed = localStorage.getItem('pwaPromptDismissed');
                
                // Para dispositivos móveis, mostrar o banner fixo no topo imediatamente se não foi dispensado
                if (window.innerWidth < 768 && !isPwaPromptDismissed) {
                    pwaPrompt.classList.remove('hidden');
                        
                        // Ajustar o padding-top do body para compensar a altura do banner e não sobrepor o conteúdo
                        const bannerHeight = pwaPrompt.offsetHeight;
                        document.body.style.paddingTop = bannerHeight + 'px';
                    }
                    
                    // Captura o evento beforeinstallprompt que é disparado quando o app pode ser instalado
                    window.addEventListener('beforeinstallprompt', (e) => {
                        // Impede que o navegador mostre automaticamente seu prompt nativo
                        e.preventDefault();
                        // Armazena o evento para usar depois
                        deferredPrompt = e;
                    });
                    
                    // Botão de instalação
                    installButton.addEventListener('click', async () => {
                        // Alterar o texto do botão para indicar que está processando
                        installButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Instalando...';
                        installButton.disabled = true;
                        
                        // Verificar se podemos usar o prompt nativo
                        if (deferredPrompt) {
                            try {
                                // Dispara o prompt de instalação nativo
                                deferredPrompt.prompt();
                                
                                // Espera o usuário responder ao prompt
                                const { outcome } = await deferredPrompt.userChoice;
                                
                                // Limpa a referência ao evento - só pode ser usado uma vez
                                deferredPrompt = null;
                                
                                // Se o usuário aceitou, salvamos essa informação e mostramos mensagem de sucesso
                                if (outcome === 'accepted') {
                                    localStorage.setItem('pwaPromptDismissed', 'installed');
                                    pwaPrompt.innerHTML = `
                                        <div class="flex items-center justify-center w-full">
                                            <div class="text-center py-2">
                                                <i class="fas fa-check-circle text-3xl mb-2"></i>
                                                <p class="font-medium">App instalado com sucesso!</p>
                                                <p class="text-xs mt-1">Procure o ícone do InfoVisa na sua tela inicial.</p>
                                            </div>
                                        </div>
                                    `;
                                    setTimeout(() => {
                                        pwaPrompt.classList.add('hidden');
                                    }, 3000);
                                } else {
                                    // Se o usuário rejeitou, voltamos ao estado normal
                                    resetInstallButton();
                                }
                            } catch (error) {
                                console.error('Erro ao instalar:', error);
                                showManualInstructions();
                                resetInstallButton();
                            }
                        } else {
                            // Método alternativo para criar atalho (mais manual)
                            createHomeScreenShortcut();
                        }
                    });
                    
                    // Função para restaurar o botão ao estado original
                    function resetInstallButton() {
                        installButton.innerHTML = '<i class="fas fa-download mr-1"></i> BAIXAR APP';
                        installButton.disabled = false;
                    }
                    
                    // Método alternativo para criar atalho na tela inicial
                    function createHomeScreenShortcut() {
                        const userAgent = navigator.userAgent.toLowerCase();
                        const isIOS = /iphone|ipad|ipod/.test(userAgent);
                        const isAndroid = /android/.test(userAgent);
                        
                        if (isIOS) {
                            // Para iOS, mostramos uma imagem com instruções visuais
                            pwaPrompt.innerHTML = `
                                <div class="p-4 text-center">
                                    <h3 class="text-sm font-bold mb-2">Como instalar o InfoVisa no iPhone/iPad:</h3>
                                    <div class="flex flex-col items-center gap-2 mb-2">
                                        <div class="p-2 bg-white/20 rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                            </svg>
                                        </div>
                                        <p class="text-xs">1. Toque no botão de compartilhamento</p>
                                    </div>
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="p-2 bg-white/20 rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </div>
                                        <p class="text-xs">2. Escolha "Adicionar à tela de início"</p>
                                    </div>
                                    <button class="mt-4 px-3 py-1 bg-white text-blue-700 rounded-full text-xs font-medium" onclick="pwaPrompt.classList.add('hidden')">
                                        Entendi
                                    </button>
                                </div>
                            `;
                        } else if (isAndroid) {
                            // Para Android, mostramos instruções visuais
                            pwaPrompt.innerHTML = `
                                <div class="p-4 text-center">
                                    <h3 class="text-sm font-bold mb-2">Como instalar o InfoVisa no Android:</h3>
                                    <div class="flex flex-col items-center gap-2 mb-2">
                                        <div class="p-2 bg-white/20 rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                            </svg>
                                        </div>
                                        <p class="text-xs">1. Toque no menu de três pontos</p>
                                    </div>
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="p-2 bg-white/20 rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <p class="text-xs">2. Escolha "Instalar aplicativo" ou "Adicionar à tela inicial"</p>
                                    </div>
                                    <button class="mt-4 px-3 py-1 bg-white text-blue-700 rounded-full text-xs font-medium" onclick="pwaPrompt.classList.add('hidden')">
                                        Entendi
                                    </button>
                                </div>
                            `;
                        } else {
                            // Para outros dispositivos, mostramos uma mensagem genérica
                            showManualInstructions();
                            resetInstallButton();
                        }
                    }
                    
                    // Botão para fechar o banner
                    dismissButton.addEventListener('click', () => {
                        pwaPrompt.classList.add('hidden');
                        // Restaurar o padding do body ao fechar o banner
                        document.body.style.paddingTop = '0';
                        // Salvamos a preferência do usuário para não mostrar o banner por 7 dias
                        const expiryDate = new Date();
                        expiryDate.setDate(expiryDate.getDate() + 7);
                        localStorage.setItem('pwaPromptDismissed', expiryDate.toISOString());
                    });
                    
                    // Função para mostrar instruções manuais dependendo do navegador
                    function showManualInstructions() {
                        // Detecta o navegador e o sistema operacional
                        const userAgent = navigator.userAgent.toLowerCase();
                        const isIOS = /iphone|ipad|ipod/.test(userAgent);
                        const isAndroid = /android/.test(userAgent);
                        const isSafari = /safari/.test(userAgent) && !/chrome/.test(userAgent);
                        const isChrome = /chrome/.test(userAgent);
                        
                        let message = '';
                        
                        if (isIOS && isSafari) {
                            message = 'Para instalar o InfoVisa no seu iPhone/iPad: toque no ícone de compartilhamento e depois em "Adicionar à Tela de Início".';
                        } else if (isAndroid && isChrome) {
                            message = 'Para instalar o InfoVisa no seu Android: toque no menu (três pontos) e depois em "Adicionar à tela inicial".';
                        } else {
                            message = 'Para instalar o InfoVisa: use um navegador como Chrome ou Safari no seu celular.';
                        }
                        
                        alert(message);
                    }
                    
                    // Verifica se devemos mostrar o banner baseado na data salva
                    function checkShouldShowBanner() {
                        if (isPwaPromptDismissed) {
                            // Se o valor é "installed", nunca mostramos o banner novamente
                            if (isPwaPromptDismissed === 'installed') {
                                return false;
                            }
                            
                            // Verifica se o período de dispensa ainda é válido
                            try {
                                const dismissDate = new Date(isPwaPromptDismissed);
                                const now = new Date();
                                if (now < dismissDate) {
                                    return false; // Ainda dentro do período de dispensa
                                }
                            } catch (e) {
                                // Se a data estiver em formato inválido, mostrar o banner
                                return true;
                            }
                        }
                        return true;
                    }
                });
            </script>
            
            <!-- Script para gerenciar o alerta de assinaturas pendentes -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const assinaturaAlert = document.getElementById('assinaturaAlert');
                    const dismissAssinaturaAlert = document.getElementById('dismissAssinaturaAlert');
                    
                    if (dismissAssinaturaAlert) {
                        // Verifica se já foi fechado anteriormente
                        const alertClosed = localStorage.getItem('assinaturaAlertClosed');
                        
                        if (alertClosed && assinaturaAlert) {
                            // Verifica se o tempo de fechamento expirou (3 horas)
                            const now = new Date().getTime();
                            const closedTime = parseInt(alertClosed);
                            
                            // Se passaram menos de 3 horas, mantém fechado
                            if (now - closedTime < 3 * 60 * 60 * 1000) {
                                assinaturaAlert.classList.add('hidden');
                            } else {
                                // Se passaram mais de 3 horas, remove o item do localStorage
                                localStorage.removeItem('assinaturaAlertClosed');
                            }
                        }
                        
                        // Configura o evento de clique para fechar o alerta
                        dismissAssinaturaAlert.addEventListener('click', function() {
                            if (assinaturaAlert) {
                                assinaturaAlert.classList.add('hidden');
                                // Guarda o timestamp de quando foi fechado
                                localStorage.setItem('assinaturaAlertClosed', new Date().getTime());
                            }
                        });
                    }
                });
            </script>
            
            <?php $conn->close(); ?>

            <script>
                // Script para controlar o modal de alertas vencidos
                document.addEventListener('DOMContentLoaded', function() {
                    const modal = document.getElementById('alertasVencidosModal');
                    const showBtn = document.getElementById('showAlertasVencidosBtn');
                    const closeBtn = document.getElementById('closeModalBtn');
                    const closeXBtn = document.getElementById('closeAlertasModal');
                    const totalAlertasVencidos = <?php echo $totalAlertasVencidos; ?>;
                    
                    // Função para abrir o modal com animações
                    function openModal() {
                        // Remover a classe hidden para exibir o modal
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                        
                        // Posicionar o modal mais para baixo
                        const modalContent = document.getElementById('modalContent');
                        modalContent.style.transform = 'translateY(0)';
                        modalContent.style.opacity = '1';
                    }
                    
                    // Função para fechar o modal
                    function closeModal() {
                        const modalContent = document.getElementById('modalContent');
                        modalContent.style.transform = 'translateY(100px)';
                        modalContent.style.opacity = '0';
                        
                        setTimeout(() => {
                            modal.classList.add('hidden');
                            document.body.style.overflow = 'auto';
                        }, 200);
                    }
                    
                    // Abrir o modal automaticamente ao carregar a página se houver alertas vencidos
                    if (totalAlertasVencidos > 0) {
                        // Pequeno atraso para garantir que a página carregue completamente primeiro
                        setTimeout(openModal, 500);
                    }
                    
                    // Adicionar event listeners para abrir/fechar o modal
                    if (showBtn) showBtn.addEventListener('click', openModal);
                    if (closeBtn) closeBtn.addEventListener('click', closeModal);
                    if (closeXBtn) closeXBtn.addEventListener('click', closeModal);
                    
                    // Fechar o modal quando clicar fora dele
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) closeModal();
                    });
                    
                    // Função para finalizar um alerta diretamente do modal
                    document.querySelectorAll('.finalizar-alerta').forEach(button => {
                        button.addEventListener('click', function() {
                            const alertaId = this.getAttribute('data-alerta-id');
                            
                            if (confirm("Tem certeza que deseja finalizar este alerta?")) {
                                // Mostrar indicador de carregamento no botão
                                this.disabled = true;
                                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Finalizando...';
                                
                                // Fazer a requisição AJAX para finalizar o alerta
                                fetch('../../controllers/ProcessoController.php?action=updateAlerta', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `id=${alertaId}&status=finalizado`
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Remover o item da lista de alertas com animação
                                        const alertaItem = document.getElementById(`alerta-item-${alertaId}`);
                                        alertaItem.style.transition = 'all 0.3s ease';
                                        alertaItem.style.opacity = '0';
                                        alertaItem.style.height = '0';
                                        alertaItem.style.overflow = 'hidden';
                                        
                                        setTimeout(() => {
                                            alertaItem.remove();
                                            
                                            // Verificar se não há mais alertas e fechar o modal
                                            const remainingAlerts = document.querySelectorAll('[id^="alerta-item-"]');
                                            if (remainingAlerts.length === 0) {
                                                closeModal();
                                                // Recarregar a página para atualizar os contadores
                                                setTimeout(() => {
                                                    window.location.reload();
                                                }, 500);
                                            }
                                        }, 300);
                                    } else {
                                        // Restaurar o botão e mostrar erro
                                        this.disabled = false;
                                        this.innerHTML = '<i class="fas fa-check mr-1"></i> Finalizar';
                                        alert("Erro ao finalizar o alerta: " + (data.message || "Erro desconhecido"));
                                    }
                                })
                                .catch(error => {
                                    // Restaurar o botão e mostrar erro
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-check mr-1"></i> Finalizar';
                                    alert("Erro ao finalizar o alerta: " + error);
                                });
                            }
                        });
                    });
                });
            </script>

            <style>
            
            <!-- REMOVIDO: Modal de Ajuda sobre Atualizações do Sistema -->
            <?php /* 
            Modal de novidades do InfoVISA foi removido conforme solicitado
            O modal estava causando interrupção na experiência do usuário
            */ ?>

            <style>
            </style>

            <?php // include 'help_update_popup.php'; // REMOVIDO - Popup de novidades desabilitado ?>

            <script>
                // JavaScript para atualizar o modal de atividades
                document.addEventListener('DOMContentLoaded', function() {
                    var atividadesModal = document.getElementById('atividadesModal');
                    if (atividadesModal) {
                        atividadesModal.addEventListener('show.bs.modal', function(event) {
                            var button = event.relatedTarget; // Botão que acionou o modal
                            var estabelecimentoId = button.getAttribute('data-id'); // ID do estabelecimento
                            var atividadesContent = document.getElementById('atividadesContent');
                            
                            // Tentar obter o tipo de pessoa pelo mais próximo li pai
                            var listItem = button.closest('li');
                            var tipoPessoa = '';
                            
                            // Verificar se o elemento tem um atributo data-tipo-pessoa
                            if (button.hasAttribute('data-tipo-pessoa')) {
                                tipoPessoa = button.getAttribute('data-tipo-pessoa');
                            }
                            // Se não encontrou pelo atributo, pesquisa na tabela para elementos no card de estabelecimentos
                            else if (button.closest('.card')) {
                                // Para botões dentro do card de estabelecimentos pendentes
                                var estabelecimentosCard = document.querySelector('.card');
                                if (estabelecimentosCard) {
                                    var rows = estabelecimentosCard.querySelectorAll('li');
                                    for (var i = 0; i < rows.length; i++) {
                                        if (rows[i].contains(button)) {
                                            var links = rows[i].querySelectorAll('a');
                                            for (var j = 0; j < links.length; j++) {
                                                var href = links[j].getAttribute('href');
                                                if (href && href.includes('detalhes_pessoa_fisica.php')) {
                                                    tipoPessoa = 'fisica';
                                                    break;
                                                }
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // Atualizar o título do modal com base no tipo de pessoa
                            var modalTitle = atividadesModal.querySelector('.modal-title');
                            if (tipoPessoa === 'fisica') {
                                modalTitle.textContent = 'Atividades da Pessoa Física';
                            } else {
                                modalTitle.textContent = 'Atividades do Estabelecimento';
                            }

                            // Limpar conteúdo antigo
                            atividadesContent.innerHTML = '<p>Carregando...</p>';

                            // Fazer a requisição AJAX
                            fetch(`../Dashboard/get_atividades.php?id=${estabelecimentoId}`)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Erro ao carregar as atividades.');
                                    }
                                    return response.text();
                                })
                                .then(data => {
                                    atividadesContent.innerHTML = data;
                                })
                                .catch(error => {
                                    atividadesContent.innerHTML = '<p>Erro ao carregar atividades.</p>';
                                    console.error(error);
                                });
                        });
                    }
                });
            </script>
</body>

</html>