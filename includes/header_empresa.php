<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define o tempo limite de inatividade (em segundos)
$tempoLimiteInatividade = 1800; // 30 minutos

// Verifica a última atividade do usuário
if (isset($_SESSION['ultima_atividade']) && (time() - $_SESSION['ultima_atividade']) > $tempoLimiteInatividade) {
    // Destrói a sessão
    session_unset();
    session_destroy();
    // Redireciona para a página de login
    header("Location: ../../login.php");
    exit();
}

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

// Atualiza o tempo da última atividade
$_SESSION['ultima_atividade'] = time();

require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Arquivo.php';

// Obter o ID do usuário logado
$userId = $_SESSION['user']['id'];

// Instanciar o objeto Processo e obter a contagem de alertas e processos parados
$processo = new Processo($conn);
$alertasCount = $processo->getAlertasCountByUsuario($userId);
$processosParadosCount = $processo->getProcessosParadosCountByUsuario($userId);

$estabelecimento = new Estabelecimento($conn);
$documentosNegadosCount = count($estabelecimento->getDocumentosNegadosByUsuario($userId));

$arquivoModel = new Arquivo($conn);
$arquivosNaoVisualizadosCount = count($arquivoModel->getArquivosNaoVisualizados($userId));

// Combinar as contagens de alertas, processos parados, documentos negados e arquivos não visualizados
$totalNotificacoes = $alertasCount + $processosParadosCount + $documentosNegadosCount + $arquivosNaoVisualizadosCount;

// Adicionar a contagem de empresas pendentes
$empresasPendentesCount = count($estabelecimento->getEstabelecimentosPendentesByUsuario($userId));

// Atualizar o total de notificações
$totalNotificacoes = $alertasCount + $processosParadosCount + $documentosNegadosCount + $arquivosNaoVisualizadosCount + $empresasPendentesCount;


?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/visamunicipal/assets/css/style.css" media="screen" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap mantido temporariamente para compatibilidade -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- TypeCSS (Tailwind CSS) -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <title>INFOVISA - Empresa</title>
    <script>
        // Aplicar o estado do menu antes que a página seja renderizada
        (function() {
            // Verificar se é uma nova sessão de login
            const lastLoginTime = localStorage.getItem('lastLoginTime');
            const currentSessionTime = '<?php echo $_SESSION['ultima_atividade'] ?? ""; ?>';
            
            // Se for um novo login ou não houver registro de login anterior, iniciar com menu aberto
            if (lastLoginTime !== currentSessionTime && currentSessionTime !== "") {
                // Atualizar o tempo de login no localStorage
                localStorage.setItem('lastLoginTime', currentSessionTime);
                // Iniciar com menu aberto após login
                localStorage.setItem('sidebarCollapsed', 'false');
                document.documentElement.classList.add('sidebar-open-init');
                return;
            }
            
            // Caso contrário, verificar o estado salvo do menu normalmente
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'false' || savedState === null) {
                // Se estava aberto ou não há estado salvo, iniciar aberto
                document.documentElement.classList.add('sidebar-open-init');
            }
        })();
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            padding-left: 250px; /* Espaço para o menu lateral quando expandido */
            transition: padding-left 0.25s ease-out;
            padding-top: 60px;
        }
        
        body.sidebar-collapsed {
            padding-left: 80px; /* Espaço para o menu lateral quando colapsado */
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            width: 250px;
            background-color: #fff;
            border-right: 1px solid #e9ecef;
            z-index: 1040;
            transition: all 0.25s ease-out;
            padding-top: 60px; /* Altura da navbar superior */
            overflow-y: auto;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar .nav-item {
            width: 100%;
            margin-bottom: 1px;
            position: relative; /* Para posicionar corretamente os submenus */
        }
        
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            padding-right: 12px; /* Reduz o padding à direita */
            color: #475569;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-radius: 6px;
            margin: 2px 8px;
            cursor: pointer; /* Adicionar cursor pointer para indicar que é clicável */
        }
        
        /* Garantir alinhamento consistente para todos os ícones no menu */
        .sidebar .nav-link div {
            display: flex;
            align-items: center;
            width: 100%; /* Garantir que ocupe toda a largura disponível */
        }
        
        /* Todos os ícones devem ter a mesma largura */
        .sidebar .nav-link i:not(.fa-chevron-down) {
            font-size: 16px;
            min-width: 24px; /* Largura fixa para todos os ícones */
            width: 24px; /* Largura fixa */
            text-align: center;
            margin-right: 12px;
            transition: margin 0.3s;
            color: #64748b;
            cursor: pointer; /* Adicionar cursor pointer para indicar que é clicável */
        }
        
        /* Garantir que todos os textos dos itens de menu se alinhem perfeitamente */
        .sidebar .nav-link span {
            transition: opacity 0.3s, visibility 0.3s;
            opacity: 1;
            visibility: visible;
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.5;
            display: inline-block;
            vertical-align: middle;
        }
        
        /* Garantir que os ícones de chevron fiquem alinhados à direita */
        .sidebar .nav-link i.fa-chevron-down {
            min-width: 10px;
            width: 10px;
            margin-left: auto; /* Empurra para o extremo direito */
            font-size: 10px !important;
            opacity: 0.7;
            text-align: right;
        }
        
        /* Alinhamento vertical para os espaçadores */
        .sidebar .nav-link span[style="width: 10px;"] {
            min-width: 10px;
            display: inline-block;
            height: 10px;
        }
        
        /* Quando o menu está colapsado */
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px 0;
        }
        
        .sidebar.collapsed .nav-link i:not(.fa-chevron-down) {
            margin: 0 auto; /* Centralizar os ícones quando o menu está colapsado */
            font-size: 18px; /* Aumentar tamanho dos ícones quando o menu está colapsado */
            width: auto; /* Permitir que fique mais largo quando aumentar o tamanho */
        }
        
        /* Esconder a seta e os espaçadores quando o menu está colapsado */
        .sidebar.collapsed .nav-link i.fa-chevron-down,
        .sidebar.collapsed .nav-link span[style="width: 10px;"] {
            display: none;
        }
        
        .sidebar .nav-link:hover {
            background-color: #f1f5f9;
            color: #3b82f6;
        }
        
        .sidebar .nav-link:hover i:not(.fa-chevron-down) {
            color: #3b82f6;
        }
        
        .sidebar .dropdown-menu {
            position: relative;
            width: 100%;
            box-shadow: none;
            border: none;
            padding: 0;
            margin: 0 8px 5px 8px;
            border-radius: 8px;
            background-color: #f8fafc;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            display: block; /* Garantir que o menu sempre esteja no DOM */
        }
        
        .sidebar .dropdown-menu.show,
        .sidebar .collapse.show .submenu {
            max-height: 1000px; /* Ajuste conforme necessário */
            transition: max-height 0.3s ease;
            padding: 5px 0; /* Adicionar padding quando visível */
        }
        
        .sidebar .collapse:not(.show) .submenu {
            max-height: 0;
            padding: 0;
            overflow: hidden;
        }
        
        .sidebar .collapse.show .submenu {
            display: block;
            visibility: visible;
            opacity: 1;
        }
        
        .sidebar .dropdown-item {
            padding: 8px 15px 8px 45px;
            font-size: 0.82rem;
            color: #64748b;
            border-radius: 4px;
            margin: 2px 4px;
            transition: all 0.2s ease;
            display: block; /* Garantir que os itens sejam blocos */
        }
        
        .sidebar .dropdown-item:hover {
            background-color: #f1f5f9;
            color: #3b82f6;
        }
        
        /* Estilo especial para garantir submenu visível */
        .sidebar .collapse.show {
            display: block !important;
            visibility: visible !important;
            height: auto !important;
            opacity: 1 !important;
            max-height: 1000px !important;
        }
        
        .sidebar .collapse.show .submenu,
        .sidebar .collapse.show ul.nav {
            display: block !important;
            visibility: visible !important;
            height: auto !important;
            opacity: 1 !important;
            max-height: 1000px !important;
            overflow: visible !important;
        }
        
        /* Garantir que os submenus não sejam escondidos por outros elementos */
        .sidebar .collapse.show .submenu {
            position: relative;
            z-index: 10;
        }

        /* Destaque visual para o item de menu ativo */
        .sidebar .nav-link.active,
        .sidebar .nav-link:active {
            background-color: #ebf5ff;
            color: #3b82f6;
            font-weight: 600;
        }
        
        .sidebar .nav-link.active i:not(.fa-chevron-down),
        .sidebar .nav-link:active i:not(.fa-chevron-down) {
            color: #3b82f6;
        }
        
        /* Esconder textos quando o menu está colapsado */
        .sidebar.collapsed .nav-link span:not([style="width: 10px;"]) {
            opacity: 0;
            visibility: hidden;
            width: 0;
            display: none; /* Adicionado para garantir que o texto não seja exibido */
        }
        
        /* Destacar item ativo no submenu */
        .sidebar .dropdown-item.active {
            background-color: #ebf5ff;
            color: #3b82f6;
            font-weight: 600;
        }
        
        .sidebar .nav-item .submenu {
            padding-left: 0; /* Remover padding da lista */
            list-style-type: none; /* Remover marcadores de lista */
        }
        
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            z-index: 1050;
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .fas.fa-chevron-down {
            font-size: 10px !important;
            opacity: 0.7;
        }
        
        .nav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 16px;
            height: 16px;
            padding: 1px 6px;
            font-size: 0.65rem !important;
            font-weight: bold;
            color: white;
            background-color: #ef4444;
            border-radius: 10px;
            margin-left: 5px;
        }

        .notification-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            margin-right: 0px;
        }
        
        .notification-container .fa-bell {
            font-size: 22px;
            color: #64748b;
        }
        
        .notification-container .fa-bell.shake {
            animation: shake 0.5s infinite;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            width: auto;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2px;
            gap: 6px;
            margin-right: 5px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        
        .notification-icon:hover {
            background-color: #f1f5f9;
        }
        
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -3px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            font-size: 0.7rem;
            font-weight: bold;
            color: white;
            background-color: #ef4444;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            z-index: 10;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .user-menu {
            display: flex;
            align-items: center;
            margin-left: auto;
            gap: 5px;
        }
        
        .user-menu .d-flex {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .user-menu .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 6px 8px;
            border-radius: 6px;
            transition: background-color 0.2s;
            color: #475569;
            font-weight: 500;
        }

        .user-menu .dropdown-toggle:hover {
            background-color: #f1f5f9;
            color: #3b82f6;
        }

        .user-menu .dropdown-toggle i {
            font-size: 16px;
            color: #64748b;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .logo-container a {
            display: flex;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .logo-container a:hover {
            opacity: 0.8;
        }
        
        .logo-container img {
            height: 32px;
        }

        /* Removido o estilo do sidebar-toggle-arrow já que o elemento foi removido */

        .toggle-sidebar {
            cursor: pointer;
            font-size: 20px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: color 0.2s;
        }

        .toggle-sidebar:hover {
            color: #3b82f6;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }

        .shake {
            animation: shake 0.5s infinite;
        }

        /* Adaptações responsivas */
        @media (max-width: 991.98px) {
            body {
                padding-left: 0 !important;
            }
            
            body.sidebar-collapsed {
                padding-left: 0 !important;
            }
            
            .sidebar {
                transform: translateX(-100%);
                z-index: 1060; /* Maior que a navbar */
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 250px !important; /* Largura fixa em móvel */
                overflow-y: auto;
                transition: transform 0.3s ease;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                padding-top: 60px; /* Altura da navbar superior */
                padding-left: 0; /* Garantir que não tenha padding extra */
                margin-left: 0; /* Garantir que esteja totalmente na esquerda */
            }
            
            /* Quando o sidebar estiver visível em mobile */
            .sidebar.mobile-show {
                transform: translateX(0);
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
            }
            
            /* Em mobile, adicionar um botão hamburger na barra superior novamente */
            .mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                cursor: pointer;
                margin-right: 12px;
                color: #64748b;
            }
            
            .mobile-toggle i {
                font-size: 20px;
                transition: color 0.2s;
            }
            
            .mobile-toggle:hover i {
                color: #3b82f6;
            }
            
            .mobile-backdrop {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1055; /* Logo abaixo do sidebar */
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .mobile-backdrop.show {
                display: block;
                opacity: 1;
            }
            
            /* Certifique-se de que os menus dropdown estejam visíveis quando abertos em mobile */
            .sidebar .dropdown-menu.show {
                display: block !important;
                max-height: 500px;
                opacity: 1;
                visibility: visible;
            }
        }

        /* Estilo para controlar a visibilidade inicial do menu */
        .sidebar-open-init body {
            padding-left: 250px !important;
        }
        .sidebar-open-init .sidebar {
            width: 250px !important;
            transform: none !important;
        }
        .sidebar-open-init .sidebar.collapsed {
            width: 250px !important;
        }
        
        /* Desativar transições durante o carregamento inicial */
        .sidebar.no-transition,
        .sidebar.no-transition * {
            transition: none !important;
        }

        html, body {
            visibility: visible;
        }
        
        /* Pré-renderização - evita o flickering */
        html.sidebar-open-init .sidebar {
            opacity: 0;
        }
        
        html.sidebar-open-init.ready .sidebar {
            opacity: 1;
            transition: opacity 0.2s ease-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .animation-pulse {
            animation: pulse 1.5s infinite;
        }
        
        /* Adicionar animação ao botão de reportar problema */
        .highlight-button {
            position: relative;
        }
        
        .highlight-button::after {
            content: '';
            position: absolute;
            top: -5px;
            right: -5px;
            bottom: -5px;
            left: -5px;
            border: 2px solid #f97316;
            border-radius: 8px;
            animation: pulse 1.5s infinite;
            z-index: -1;
        }
        
        /* Animação de piscar para o ícone de reportar problema */
        @keyframes blink-icon {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .blink-attention {
            animation: blink-icon 1.2s infinite;
            position: relative;
        }
        
        .blink-attention::after {
            content: '';
            position: absolute;
            top: -5px;
            right: -5px;
            bottom: -5px;
            left: -5px;
            border: 2px solid #ef4444;
            border-radius: 50%;
            animation: pulse-outer 2s infinite;
            z-index: -1;
        }
        
        /* Indicador visual para o botão real na interface */
        .button-indicator {
            display: none; /* Escondido por padrão */
            position: fixed;
            z-index: 9999;
            pointer-events: none; /* Não interferir com cliques */
        }
        
        .button-indicator.active {
            display: block;
        }
        
        .button-indicator .circle-highlight {
            position: absolute;
            width: 60px;
            height: 60px;
            border: 3px dashed #ef4444;
            border-radius: 50%;
            animation: rotate-circle 8s linear infinite;
            filter: drop-shadow(0 0 8px rgba(239, 68, 68, 0.5));
        }
        
        @keyframes rotate-circle {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="<?php echo (isset($_SESSION['sidebar_open']) && $_SESSION['sidebar_open']) ? '' : 'sidebar-collapsed'; ?>">
    <!-- Mobile backdrop -->
    <div class="mobile-backdrop" id="mobileBackdrop"></div>

    <!-- Navbar Superior -->
    <div class="top-navbar">
        <!-- Botão hamburger visível apenas em mobile -->
        <div class="mobile-toggle d-lg-none" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </div>
        
        <div class="toggle-sidebar d-none d-lg-flex" id="sidebarToggle">
            <i class="fas fa-bars"></i>
    </div>

        <div class="logo-container">
            <a href="../Company/dashboard_empresa.php">
                <img src="/visamunicipal/assets/img/logo.png" alt="Logomarca" class="logo">
            </a>
        </div>
        
        <div class="user-menu">
            <!-- Notificações - movido para a direita, junto ao menu do usuário -->
            <div class="notification-icon" id="alertDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="notification-container">
                    <i class="fas fa-bell <?php echo ($totalNotificacoes > 0) ? 'shake' : ''; ?>"></i>
                    <!-- Sempre mostrar o badge, mesmo com zero -->
                    <span class="notification-badge"><?php echo $totalNotificacoes; ?></span>
                </div>

                <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-lg" aria-labelledby="alertDropdown">
                    <li class="dropdown-header">Centro de Alertas</li>
                    <?php if ($alertasCount > 0) : ?>
                        <li><a class="dropdown-item" href="../Company/alertas_empresas.php">
                            <i class="fas fa-bell-exclamation text-warning me-2"></i> <?php echo $alertasCount; ?> Alertas
                        </a></li>
                    <?php endif; ?>
                    <?php if ($processosParadosCount > 0) : ?>
                        <li><a class="dropdown-item" href="../Company/processos_empresa.php?filter=parados">
                            <i class="fas fa-pause-circle text-danger me-2"></i> <?php echo $processosParadosCount; ?> Processos Parados
                        </a></li>
                    <?php endif; ?>
                    <?php if ($documentosNegadosCount > 0) : ?>
                        <li><a class="dropdown-item" href="../Company/documentos_negados.php">
                            <i class="fas fa-times-circle text-danger me-2"></i> <?php echo $documentosNegadosCount; ?> Documentos Negados
                        </a></li>
                    <?php endif; ?>
                    <?php if ($arquivosNaoVisualizadosCount > 0) : ?>
                        <li><a class="dropdown-item" href="../Company/arquivos_nao_visualizados.php">
                            <i class="fas fa-file-alt text-primary me-2"></i> <?php echo $arquivosNaoVisualizadosCount; ?> Arquivos Não Visualizados
                        </a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center text-blue-600 fw-bold" href="../Company/alertas_empresas.php" onclick="window.location.href='../Company/alertas_empresas.php'; return false;">Ver todos os alertas</a></li>
                </ul>
            </div>
            
            <!-- Perfil/Conta do Usuário com ícone de ajuda próximo -->
            <div class="d-flex align-items-center" style="gap: 2px;">
                <!-- Botão de Reportar Erro -->
                <a href="#" class="notification-icon" data-bs-toggle="modal" data-bs-target="#reportErrorModal" style="margin-right: 0; padding-right: 2px;">
                    <i class="fas fa-bug text-red-500"></i>
                </a>
                
                <div class="dropdown">
                    <a class="dropdown-toggle user-menu-toggle d-flex align-items-center" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding-left: 10px;">
                        <i class="fas fa-user-circle me-1"></i>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Usuário'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-lg" aria-labelledby="accountDropdown">
                        <li><a class="dropdown-item" href="../Company/alterar_senha_empresa.php">Alterar senha</a></li>
                        <li><a class="dropdown-item" href="../Company/alterar_dados_empresa.php">Editar dados cadastrais</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu Lateral - começar colapsado, mas com classe no-transition para evitar animação indesejada -->
    <div class="sidebar collapsed no-transition" id="sidebar">
        <ul class="nav flex-column">
            <!-- Dashboard -->
                    <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="../Company/dashboard_empresa.php">
                    <div>
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </div>
                    <!-- Espaço vazio para alinhar com outros itens que têm o ícone de dropdown -->
                    <span style="width: 10px;"></span>
                </a>
                    </li>
            
            <!-- Estabelecimentos -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#estabelecimentoCollapse" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="estabelecimentoCollapse">
                    <div>
                        <i class="fas fa-building"></i>
                        <span>Estabelecimentos</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="estabelecimentoCollapse">
                    <ul class="nav flex-column submenu">
                            <li><a class="dropdown-item" href="../Company/todos_estabelecimentos.php">Lista</a></li>
                        <li><a class="dropdown-item" href="../Company/listar_estabelecimentos_rejeitados.php">Negados</a></li>
                        </ul>
                </div>
                    </li>

            <!-- Processos -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#processoCollapse" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="processoCollapse">
                    <div>
                        <i class="fas fa-clipboard-list"></i>
                        <span>Processos</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="processoCollapse">
                    <ul class="nav flex-column submenu">
                            <li><a class="dropdown-item" href="../Company/processos_empresa.php">Todos os Processos</a></li>
                        <?php if ($processosParadosCount > 0): ?>
                        <li>
                            <a class="dropdown-item" href="../Company/processos_empresa.php?filter=parados">
                                Processos Parados
                                <span class="nav-badge"><?php echo $processosParadosCount; ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                        </ul>
                </div>
                    </li>

            <!-- Documentos -->
            <?php if ($documentosNegadosCount > 0 || $arquivosNaoVisualizadosCount > 0): ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#documentosCollapse" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="documentosCollapse">
                    <div>
                        <i class="fas fa-file-alt"></i>
                        <span>Documentos</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="documentosCollapse">
                    <ul class="nav flex-column submenu">
                        <?php if ($documentosNegadosCount > 0): ?>
                        <li>
                            <a class="dropdown-item" href="../Company/documentos_negados.php">
                                Documentos Negados
                                <span class="nav-badge"><?php echo $documentosNegadosCount; ?></span>
                        </a>
                    </li>
                        <?php endif; ?>
                        <?php if ($arquivosNaoVisualizadosCount > 0): ?>
                        <li>
                            <a class="dropdown-item" href="../Company/arquivos_nao_visualizados.php">
                                Não Visualizados
                                <span class="nav-badge"><?php echo $arquivosNaoVisualizadosCount; ?></span>
                        </a>
                    </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>
            
            <!-- Minha Conta -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#contaCollapse" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="contaCollapse">
                    <div>
                        <i class="fas fa-user-circle"></i>
                        <span>Minha Conta</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="contaCollapse">
                    <ul class="nav flex-column submenu">
                            <li><a class="dropdown-item" href="../Company/alterar_senha_empresa.php">Alterar senha</a></li>
                            <li><a class="dropdown-item" href="../Company/alterar_dados_empresa.php">Editar dados cadastrais</a></li>
                        <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                </div>
                    </li>
                
            <!-- Contato -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#contatoCollapse" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="contatoCollapse">
                    <div>
                        <i class="fas fa-phone-alt"></i>
                        <span>Contato</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="contatoCollapse">
                    <div class="p-3 bg-gray-50 rounded-lg mx-2 text-sm">
                        <h6 class="font-medium text-gray-700 mb-2">Vigilância Sanitária Gurupi</h6>
                        <div class="flex items-center mb-2">
                            <i class="fas fa-phone-alt text-blue-600 mr-2"></i>
                            <span>(63) 3142-2575</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-blue-600 mr-2"></i>
                            <a href="mailto:visagurupi@gmail.com" class="text-blue-600 hover:underline">visagurupi@gmail.com</a>
                        </div>
                    </div>
                </div>
            </li>
                </ul>
            </div>

    <div>
        </div>

    <!-- Modal de Ajuda -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">Relatar Erros ou Melhorias</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="relatarErroMelhoriaForm">
                        <div class="mb-3">
                            <label for="tipoRelato" class="form-label">Tipo</label>
                            <select id="tipoRelato" name="tipo" class="form-select" required>
                                <option value="" disabled selected>Selecione</option>
                                <option value="BUG">Erro/Bug</option>
                                <option value="MELHORIA">Melhoria</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descricaoRelato" class="form-label">Descrição</label>
                            <textarea id="descricaoRelato" name="descricao" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Enviar</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('relatarErroMelhoriaForm');

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(form);

                fetch('relatar_erro_melhoria.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert(data.message);
                            form.reset();
                            const modal = bootstrap.Modal.getInstance(document.getElementById('helpModal'));
                            modal.hide();
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => console.error('Erro:', error));
            });
        });
    </script>

    <!-- Bootstrap JS mantido temporariamente para compatibilidade -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- TypeScript -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos da barra lateral
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileBackdrop = document.getElementById('mobileBackdrop');
            const body = document.body;
            
            // Atualizar o timestamp da sessão no localStorage
            const currentSessionTime = '<?php echo $_SESSION['ultima_atividade'] ?? ""; ?>';
            if (currentSessionTime !== "") {
                localStorage.setItem('lastLoginTime', currentSessionTime);
            }

            // Remover a classe de inicialização
            document.documentElement.classList.remove('sidebar-open-init');
            
            // Função para salvar o estado do menu no localStorage
            function saveSidebarState(isCollapsed) {
                localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
                
                // Também salvar na sessão PHP através de uma chamada AJAX
                fetch('../../ajax/save_sidebar_state.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'sidebar_open=' + (isCollapsed ? '0' : '1')
                }).catch(error => console.error('Erro ao salvar estado do menu:', error));
            }
            
            // Função para aplicar o estado do menu sem animação
            function applyMenuStateInstantly() {
                const savedState = localStorage.getItem('sidebarCollapsed');
                
                // Por padrão, abrir o menu se não houver estado salvo ou se o estado for "não colapsado"
                if (savedState === null || savedState === 'false') {
                    // Aplicar os estilos sem transição para evitar animação
                    sidebar.style.transition = 'none';
                    body.style.transition = 'none';
                    
                    // Remover as classes collapsed
                    sidebar.classList.remove('collapsed');
                    body.classList.remove('sidebar-collapsed');
                    
                    // Forçar reflow para aplicar as mudanças imediatamente
                    sidebar.offsetHeight;
                }
                
                // Remover a classe no-transition após aplicar o estado
                setTimeout(function() {
                    sidebar.classList.remove('no-transition');
                    sidebar.style.transition = '';
                    body.style.transition = '';
                }, 50);
            }
            
            // Aplicar o estado do menu imediatamente
            applyMenuStateInstantly();
            
            // Marcar o HTML como pronto para mostrar o sidebar
            setTimeout(function() {
                document.documentElement.classList.add('ready');
            }, 50);
            
            // Toggle para desktop - abrir/fechar menu lateral
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Se o menu estiver fechado, abrir
                    if (sidebar.classList.contains('collapsed')) {
                        sidebar.classList.remove('collapsed');
                        body.classList.remove('sidebar-collapsed');
                        saveSidebarState(false); // Salvar estado como aberto
                    } else {
                        // Se estiver aberto, fechar
                        sidebar.classList.add('collapsed');
                        body.classList.add('sidebar-collapsed');
                        saveSidebarState(true); // Salvar estado como fechado
                    }
                });
            }
            
            // Garantir que clicar em qualquer ícone dentro do menu, quando colapsado, abra o menu
            document.querySelectorAll('.sidebar .nav-link i').forEach(function(icon) {
                icon.addEventListener('click', function(e) {
                    // Se o menu estiver colapsado, abrir ao clicar no ícone
                    if (sidebar.classList.contains('collapsed')) {
                        e.preventDefault();
                        e.stopPropagation();
                        sidebar.classList.remove('collapsed');
                        body.classList.remove('sidebar-collapsed');
                        saveSidebarState(false); // Salvar estado como aberto
                    }
                });
            });
            
            // Toggle para mobile
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('mobile-show');
                    mobileBackdrop.classList.toggle('show');
                });
            }
            
            // Fechar mobile sidebar ao clicar no backdrop
            if (mobileBackdrop) {
                mobileBackdrop.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-show');
                    mobileBackdrop.classList.remove('show');
                });
            }
            
            // Inicializar os collapse do Bootstrap para os submenus - com verificação adicional
            try {
                var collapseElementList = [].slice.call(document.querySelectorAll('.sidebar .collapse'));
                var collapseList = collapseElementList.map(function (collapseEl) {
                    // Verificar se o Bootstrap já está disponível
                    if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                        // Criar instâncias do Bootstrap Collapse para cada elemento
                        return new bootstrap.Collapse(collapseEl, {
                            toggle: false
                        });
                    } else {
                        console.warn('Bootstrap não disponível para inicializar Collapse');
                        // Implementação alternativa simples para garantir funcionalidade
                        const toggleLink = document.querySelector(`[href="#${collapseEl.id}"]`);
                        if (toggleLink) {
                            toggleLink.addEventListener('click', function(e) {
                                e.preventDefault();
                                collapseEl.classList.toggle('show');
                                const arrow = toggleLink.querySelector('.fa-chevron-down');
                                if (arrow) {
                                    arrow.style.transform = collapseEl.classList.contains('show') ? 'rotate(180deg)' : '';
                                }
                            });
                        }
                        return null;
                    }
                });
            } catch (e) {
                console.error('Erro ao inicializar collapses:', e);
            }
            
            // Adicionar animação à seta de dropdown quando abre/fecha
            document.querySelectorAll('.sidebar a[data-bs-toggle="collapse"]').forEach(function(link) {
                // Encontrar o elemento alvo do collapse
                const targetId = link.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    // Verificar se já tem eventos do bootstrap
                    if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                        // Adicionar listener para mudar a seta quando o collapse mostrar
                        targetElement.addEventListener('show.bs.collapse', function() {
                            const arrow = link.querySelector('.fa-chevron-down');
                            if (arrow) arrow.style.transform = 'rotate(180deg)';
                        });
                        
                        // Adicionar listener para mudar a seta quando o collapse esconder
                        targetElement.addEventListener('hide.bs.collapse', function() {
                            const arrow = link.querySelector('.fa-chevron-down');
                            if (arrow) arrow.style.transform = '';
                        });
                    }
                    
                    // Adicionar também um listener de clique para garantir compatibilidade
                    link.addEventListener('click', function(e) {
                        // Verificar se o Bootstrap está processando o evento
                        if (typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
                            e.preventDefault();
                            targetElement.classList.toggle('show');
                            const arrow = link.querySelector('.fa-chevron-down');
                            if (arrow) {
                                arrow.style.transform = targetElement.classList.contains('show') ? 'rotate(180deg)' : '';
                            }
                        }
                    });
                }
            });
            
            // Em telas pequenas, fechar o menu lateral quando um item for clicado
            document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('mobile-show');
                        mobileBackdrop.classList.remove('show');
                    }
                    
                    // Se o menu estiver colapsado e não for um link de dropdown, redirecionar após abrir o menu
                    if (sidebar.classList.contains('collapsed') && !this.hasAttribute('data-bs-toggle')) {
                        const href = this.getAttribute('href');
                        if (href && !href.startsWith('#')) {
                            e.preventDefault();
                            sidebar.classList.remove('collapsed');
                            body.classList.remove('sidebar-collapsed');
                            saveSidebarState(false);
                            
                            setTimeout(() => {
                                window.location.href = href;
                            }, 300);
                        }
                    }
                });
            });
            
            // Inicializar os dropdowns do Bootstrap para notificações e conta do usuário
            try {
                var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
                dropdownElementList.forEach(function(element) {
                    new bootstrap.Dropdown(element);
                });
            } catch (e) {
                console.error('Erro ao inicializar dropdowns:', e);
            }
            
            // Ajustar para dispositivos móveis
            function checkWindowSize() {
                if (window.innerWidth < 992) {
                    // Em mobile, garantir que o menu comece escondido
                    sidebar.classList.remove('mobile-show');
                    mobileBackdrop.classList.remove('show');
                }
            }
            
            // Verificar no carregamento
            checkWindowSize();
            
            // Verificar ao redimensionar a janela
            window.addEventListener('resize', checkWindowSize);
            
            // Adicionar handler para o link de logout
            const logoutLinks = document.querySelectorAll('a[href="../logout.php"]');
            logoutLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    // Não forçar mais o menu como fechado após logout
                    // Apenas limpar o timestamp da sessão
                    localStorage.removeItem('lastLoginTime');
                });
            });
            
            // Forçar a visibilidade dos submenus quando seus pais estiverem abertos
            document.querySelectorAll('.sidebar .collapse.show').forEach(function(collapse) {
                const submenu = collapse.querySelector('.submenu');
                if (submenu) {
                    submenu.style.display = 'block';
                    submenu.style.maxHeight = '1000px';
                    submenu.style.opacity = '1';
                    submenu.style.visibility = 'visible';
                }
            });
        });
    </script>
    
    <!-- Elemento para destacar o botão na interface real -->
    <div id="buttonIndicator" class="button-indicator">
        <div class="circle-highlight"></div>
    </div>
    
    <!-- Script para exibir tutorial do recurso de reportar problema -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Definir a versão atual do tutorial
            const currentTutorialVersion = '1.0'; // Incrementar essa versão quando houver atualizações importantes
            
            // Verificar se o usuário já viu esta versão do tutorial
            const reportFeatureVersion = localStorage.getItem('reportFeatureTutorialVersion');
            
            // Encontrar o botão de reportar problema
            const reportButton = document.querySelector('[data-bs-target="#reportErrorModal"]');
            
            // Não adicionar a animação imediatamente, apenas quando o tutorial for exibido
            
            if (reportFeatureVersion !== currentTutorialVersion) {
                // Se não viu esta versão, mostrar o tutorial após 1.5 segundos
                setTimeout(function() {
                    // Adicionar a classe de animação piscar APENAS quando o tutorial é mostrado
                    if (reportButton) {
                        reportButton.classList.add('blink-attention');
                        
                        // Posicionar e mostrar o indicador visual apontando para o botão real
                        positionButtonIndicator(reportButton);
                    }
                    
                    const tutorialModal = new bootstrap.Modal(document.getElementById('reportFeatureTutorialModal'), {
                        backdrop: 'static',  // Não permite fechar clicando fora do modal
                        keyboard: false      // Não permite fechar com ESC
                    });
                    
                    // Mostrar o modal
                    tutorialModal.show();
                    
                    // Adicionar evento apenas ao botão de entendido
                    const btnEntendi = document.querySelector('#reportFeatureTutorialModal .modal-footer .btn-primary');
                    if (btnEntendi) {
                        btnEntendi.addEventListener('click', function() {
                            // Marcar esta versão como vista apenas quando clicar em "Entendi"
                            localStorage.setItem('reportFeatureTutorialVersion', currentTutorialVersion);
                            
                            // Remover a animação quando o usuário clicar em "Entendi"
                            if (reportButton) {
                                reportButton.classList.remove('blink-attention');
                                
                                // Esconder o indicador visual
                                hideButtonIndicator();
                            }
                        });
                    }
                }, 1500);
            }
            
            // Preparar um indicador visual para quando o usuário precisar clicar no botão
            // Esta função será usada quando quisermos mostrar ao usuário onde clicar
            window.highlightReportButton = function(duration = 5000) {
                if (reportButton) {
                    reportButton.classList.add('blink-attention');
                    setTimeout(function() {
                        reportButton.classList.remove('blink-attention');
                    }, duration);
                }
            };
            
            // Função para posicionar o indicador visual perto do botão real
            function positionButtonIndicator(button) {
                const indicator = document.getElementById('buttonIndicator');
                if (!indicator || !button) return;
                
                const rect = button.getBoundingClientRect();
                
                // Posicionar o círculo ao redor do botão
                const circle = indicator.querySelector('.circle-highlight');
                circle.style.top = (rect.top - 15) + 'px';
                circle.style.left = (rect.left - 15) + 'px';
                
                // Mostrar o indicador
                indicator.classList.add('active');
                
                // Reposicionar em caso de redimensionamento
                window.addEventListener('resize', function() {
                    if (indicator.classList.contains('active')) {
                        const updatedRect = button.getBoundingClientRect();
                        circle.style.top = (updatedRect.top - 15) + 'px';
                        circle.style.left = (updatedRect.left - 15) + 'px';
                    }
                });
            }
            
            // Função para esconder o indicador visual
            function hideButtonIndicator() {
                const indicator = document.getElementById('buttonIndicator');
                if (indicator) {
                    indicator.classList.remove('active');
                }
            }
        });
    </script>
    
    <!-- Modal para Reportar Erro -->
    <div class="modal fade" id="reportErrorModal" tabindex="-1" aria-labelledby="reportErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-to-r from-red-500 to-red-600 text-white">
                    <h5 class="modal-title" id="reportErrorModalLabel">
                        <i class="fas fa-bug me-2"></i>Reportar Problema
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="reportErrorForm" action="/visamunicipal/views/Company/salvar_relato.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Utilize este formulário para reportar problemas ou sugerir melhorias no sistema.
                            <strong>Sua contribuição é muito importante para aprimorarmos o sistema.</strong>
                        </div>
                        
                        <input type="hidden" id="pageUrl" name="page_url" value="">
                        <input type="hidden" id="screenCapture" name="screen_capture" value="">
                        
                        <div class="mb-3">
                            <label for="tipoRelato" class="form-label fw-bold">Tipo de Relato</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoBug" value="BUG" checked>
                                    <label class="form-check-label" for="tipoBug">
                                        <i class="fas fa-bug text-danger me-1"></i> Problema/Erro
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoMelhoria" value="MELHORIA">
                                    <label class="form-check-label" for="tipoMelhoria">
                                        <i class="fas fa-lightbulb text-warning me-1"></i> Sugestão de Melhoria
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao" class="form-label fw-bold">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="4" 
                                placeholder="Descreva o problema ou sugestão em detalhes..." required></textarea>
                            <div class="form-text">
                                Seja específico e inclua os passos para reproduzir o problema, se aplicável.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Captura de Tela</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" id="captureScreenButton" class="btn btn-sm btn-primary">
                                    <i class="fas fa-camera me-1"></i> Capturar Tela Atual
                                </button>
                                <button type="button" id="clearCaptureButton" class="btn btn-sm btn-outline-secondary" disabled>
                                    <i class="fas fa-trash-alt me-1"></i> Limpar Captura
                                </button>
                            </div>
                            <div id="screenshotPreview" class="d-none">
                                <div class="border rounded p-2 mb-2 position-relative">
                                    <img id="screenshotImage" src="" class="img-fluid" alt="Captura de tela" style="max-height: 200px; width: auto;">
                                    <div id="annotationCanvas" class="position-absolute top-0 start-0 w-100 h-100"></div>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Você pode clicar na imagem para destacar onde está o problema.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Enviar Relato
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Script para captura de tela -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Guarda URL da página atual quando o modal é aberto
            const reportModal = document.getElementById('reportErrorModal');
            if (reportModal) {
                reportModal.addEventListener('show.bs.modal', function() {
                    document.getElementById('pageUrl').value = window.location.href;
                });
            }
            
            // Botões de captura de tela
            const captureBtn = document.getElementById('captureScreenButton');
            const clearBtn = document.getElementById('clearCaptureButton');
            const preview = document.getElementById('screenshotPreview');
            const screenshotImg = document.getElementById('screenshotImage');
            const screenshotInput = document.getElementById('screenCapture');
            
            if (captureBtn) {
                captureBtn.addEventListener('click', function() {
                    // Usando html2canvas para capturar a tela
                    html2canvas(document.body).then(canvas => {
                        // Ocultar o modal temporariamente para capturar a tela sem ele
                        const modal = bootstrap.Modal.getInstance(reportModal);
                        modal.hide();
                        
                        setTimeout(() => {
                            html2canvas(document.body).then(canvas => {
                                const imageData = canvas.toDataURL('image/png');
                                screenshotImg.src = imageData;
                                screenshotInput.value = imageData;
                                preview.classList.remove('d-none');
                                clearBtn.disabled = false;
                                
                                // Reexibir o modal
                                modal.show();
                            });
                        }, 100);
                    });
                });
            }
            
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    screenshotImg.src = '';
                    screenshotInput.value = '';
                    preview.classList.add('d-none');
                    clearBtn.disabled = true;
                });
            }
            
            // Permitir anotações na captura de tela
            const annotationCanvas = document.getElementById('annotationCanvas');
            if (annotationCanvas && screenshotImg) {
                // Criar elemento canvas para desenho
                let drawingCanvas = document.createElement('canvas');
                drawingCanvas.style.position = 'absolute';
                drawingCanvas.style.top = '0';
                drawingCanvas.style.left = '0';
                drawingCanvas.style.pointerEvents = 'auto';
                drawingCanvas.style.cursor = 'crosshair';
                annotationCanvas.appendChild(drawingCanvas);
                
                // Ajustar tamanho do canvas quando a imagem é carregada
                screenshotImg.addEventListener('load', function() {
                    drawingCanvas.width = screenshotImg.clientWidth;
                    drawingCanvas.height = screenshotImg.clientHeight;
                    
                    const ctx = drawingCanvas.getContext('2d');
                    ctx.strokeStyle = 'red';
                    ctx.lineWidth = 4;
                    
                    // Desenhar círculo ao clicar
                    drawingCanvas.addEventListener('click', function(e) {
                        const rect = drawingCanvas.getBoundingClientRect();
                        const x = e.clientX - rect.left;
                        const y = e.clientY - rect.top;
                        
                        ctx.beginPath();
                        ctx.arc(x, y, 20, 0, 2 * Math.PI);
                        ctx.stroke();
                        
                        // Atualizar imagem com anotações
                        mergeAnnotations();
                    });
                    
                    // Função para mesclar a imagem original com as anotações
                    function mergeAnnotations() {
                        const tempCanvas = document.createElement('canvas');
                        tempCanvas.width = screenshotImg.naturalWidth;
                        tempCanvas.height = screenshotImg.naturalHeight;
                        
                        const tempCtx = tempCanvas.getContext('2d');
                        
                        // Desenhar imagem original
                        const img = new Image();
                        img.src = screenshotImg.src;
                        tempCtx.drawImage(img, 0, 0);
                        
                        // Desenhar anotações em escala apropriada
                        const scaleX = screenshotImg.naturalWidth / drawingCanvas.width;
                        const scaleY = screenshotImg.naturalHeight / drawingCanvas.height;
                        
                        tempCtx.strokeStyle = ctx.strokeStyle;
                        tempCtx.lineWidth = ctx.lineWidth * scaleX;
                        
                        // Copiar anotações do canvas de desenho
                        const annotations = drawingCanvas.toDataURL('image/png');
                        const annotImg = new Image();
                        annotImg.onload = function() {
                            tempCtx.drawImage(annotImg, 0, 0, tempCanvas.width, tempCanvas.height);
                            // Atualizar valor do input com a imagem anotada
                            screenshotInput.value = tempCanvas.toDataURL('image/png');
                        };
                        annotImg.src = annotations;
                    }
                });
            }
            
            // Adicionar html2canvas via CDN se não estiver carregado
            if (typeof html2canvas === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://html2canvas.hertzen.com/dist/html2canvas.min.js';
                script.onload = function() {
                    console.log('html2canvas carregado dinamicamente');
                };
                document.head.appendChild(script);
            }
        });
    </script>
</body>
</html>