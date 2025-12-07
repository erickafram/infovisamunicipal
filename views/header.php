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
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

// Atualiza o tempo da última atividade
$_SESSION['ultima_atividade'] = time();

require_once '../../conf/database.php';
require_once '../../controllers/AlertaController.php';

$alertaController = new AlertaController($conn);
$usuario_id = $_SESSION['user']['id'];
$assinaturasPendentes = $alertaController->getAssinaturasPendentes($usuario_id);
$assinaturasRascunho = $alertaController->getAssinaturasRascunho($usuario_id);
$processosDesignadosPendentes = $alertaController->getProcessosDesignadosPendentes($usuario_id);

// Contar processos com documentação pendente
$query = "
    SELECT COUNT(*) AS total
    FROM processos p
    JOIN documentos d ON p.id = d.processo_id
    JOIN estabelecimentos e ON p.estabelecimento_id = e.id
    WHERE d.status = 'pendente' AND e.municipio = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $_SESSION['user']['municipio']);
$stmt->execute();
$result = $stmt->get_result();
$pendentes = $result->fetch_assoc()['total'];
$stmt->close();

// Contagem total de alertas
$totalAlertas = count($assinaturasPendentes) + count($assinaturasRascunho) + count($processosDesignadosPendentes) + $pendentes;

$ultimosAlertas = array_merge(
    array_slice($assinaturasPendentes, 0, 5),
    array_slice($assinaturasRascunho, 0, 5),
    array_slice($processosDesignadosPendentes, 0, 5)
);
$ultimosAlertas = array_slice($ultimosAlertas, 0, 5); // Garantir que sejam no máximo 5 alertas

if ($pendentes > 0) {
    $ultimosAlertas[] = array('tipo' => 'Processos com Documentação Pendente', 'total' => $pendentes);
}

// Buscar todas as chaves de API
$sql = "SELECT nome_api, chave_api FROM configuracoes_apis";
$result = $conn->query($sql);
$chavesApi = [];
while ($row = $result->fetch_assoc()) {
    $chavesApi[$row['nome_api']] = $row['chave_api'];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/visamunicipal/assets/css/style.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="/visamunicipal/assets/css/menu-fix.css" media="screen" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/visamunicipal/assets/css/mobile-override.css" media="screen" />
    <title>INFOVISA</title>
    
    <!-- Script de inicialização para corrigir menu em dispositivos móveis -->
    <script>
        // Script de inicialização rápida antes do carregamento completo da página
        (function() {
            // Verificar se estamos em mobile
            const isMobile = window.innerWidth < 992;
            
            if (isMobile) {
                // Adicionar classe para ativar os estilos móveis imediatamente
                document.documentElement.classList.add('mobile-view');
                
                // Função que será executada imediatamente e depois novamente quando o DOM estiver pronto
                function fixMobileMenuDisplay() {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) {
                        // Remover classes que possam interferir
                        if (sidebar.classList.contains('collapsed')) {
                            sidebar.classList.remove('collapsed');
                        }
                        
                        // Adicionar classe que indica que temos menu mobile
                        sidebar.classList.add('mobile-fix-applied');
                        
                        // Garantir que o menu seja exibido corretamente quando aberto
                        if (sidebar.classList.contains('mobile-show')) {
                            document.body.style.overflow = 'hidden';
                        }
                    }
                }
                
                // Executar imediatamente
                fixMobileMenuDisplay();
                
                // E executar novamente quando o DOM estiver pronto
                document.addEventListener('DOMContentLoaded', fixMobileMenuDisplay);
            }
        })();
    </script>
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            padding-left: 280px; /* Espaço para o menu lateral quando expandido - ajustado para nova largura */
            transition: padding-left 0.3s ease;
            padding-top: 80px; /* Espaço suficiente para o header não sobrepor o conteúdo */
            background-color: #f8fafc;
        }
        
        body.sidebar-collapsed {
            padding-left: 80px; /* Espaço para o menu lateral quando colapsado */
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            width: 280px; /* Aumentado de 250px para 280px para dar mais espaço ao texto */
            background-color: #fff;
            border-right: 1px solid #e9ecef;
            z-index: 900; /* Reduzido para não sobrepor alertas (z-index: 1000+) */
            transition: all 0.3s ease;
            padding-top: 60px; /* Altura da navbar superior */
            overflow-y: auto;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        
        .py-6 {
            padding-top: 1rem !important;
            padding-bottom: 1.5rem;
        }

        .mt-4 {
            margin-top: 0 !important;
        }
        
        /* Garantir que o primeiro elemento na página tenha espaçamento adequado */
        .container.mx-auto:first-of-type {
            margin-top: 0 !important;
            padding-top: 1rem !important;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar .nav-item {
            width: 100%;
            margin-bottom: 1px;
            min-height: 42px; /* Altura mínima para garantir espaço adequado */
        }
        
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            padding-right: 8px; /* Reduz o padding à direita para dar mais espaço ao texto */
            color: #475569;
            transition: all 0.2s ease;
            white-space: nowrap;
            border-radius: 6px;
            margin: 2px 8px;
            min-height: 40px; /* Altura mínima para garantir espaço adequado */
        }

        .fas.fa-chevron-down {
            font-size: 10px !important;
            opacity: 0.7;
            flex-shrink: 0; /* Impede que a seta seja comprimida */
            margin-left: auto; /* Empurra a seta para a direita */
        }
        
        .sidebar .nav-link i:not(.fa-chevron-down) {
            font-size: 16px;
            min-width: 24px;
            text-align: center;
            margin-right: 12px;
            transition: margin 0.3s;
            color: #64748b;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .sidebar .nav-link span {
            transition: opacity 0.3s, visibility 0.3s;
            opacity: 1;
            visibility: visible;
            font-size: 0.9rem;
            font-weight: 500;
            flex: 1; /* Permite que o texto ocupe o espaço disponível */
            margin-right: 8px; /* Espaço entre o texto e a seta */
        }
        
        .sidebar.collapsed .nav-link span {
            opacity: 0;
            visibility: hidden;
            width: 0;
            display: none; /* Adicionado para garantir que o texto não seja exibido */
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
        }
        
        .sidebar .dropdown-menu.show {
            max-height: 1000px; /* Ajuste conforme necessário */
            transition: max-height 0.3s ease;
        }
        
        .sidebar .dropdown-item {
            padding: 8px 12px 8px 45px; /* Reduzido padding direito para dar mais espaço ao texto */
            font-size: 0.82rem;
            color: #64748b;
            border-radius: 4px;
            margin: 2px 4px;
            transition: all 0.2s ease;
            white-space: normal; /* Permite quebra de linha se necessário */
            line-height: 1.3; /* Melhora a legibilidade */
        }
        
        .sidebar .dropdown-item:hover {
            background-color: #e9ecef;
            color: #3b82f6;
        }

        .sidebar.collapsed .dropdown-menu {
            position: absolute;
            left: 100%;
            top: 0;
            margin-top: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background-color: #fff;
            width: 220px; /* Aumentado para acomodar textos maiores */
        }
        
        /* Estilo para exibir o submenu ao passar o mouse sobre o item quando colapsado */
        .sidebar.collapsed .nav-item:hover .dropdown-menu {
            display: block;
            max-height: 1000px;
            opacity: 1;
            visibility: visible;
        }
        
        /* Estilo para o submenu quando o menu está colapsado */
        .sidebar.collapsed .dropdown-menu {
            display: none; /* Inicialmente oculto */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s, max-height 0.3s;
        }
        
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            z-index: 950; /* Reduzido para não sobrepor alertas */
            display: flex;
            align-items: center;
            padding: 0 15px 0 10px; /* Reduzir padding esquerdo */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .content-wrapper {
            transition: padding-left 0.3s ease;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }

        .shake {
            animation: shake 0.5s infinite;
        }

        .toggle-sidebar {
            cursor: pointer;
            font-size: 20px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: color 0.2s;
        }

        .toggle-sidebar:hover {
            color: #3b82f6;
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
                width: 280px !important; /* Largura fixa em móvel - ajustada para nova largura */
                overflow-y: auto;
                transition: transform 0.3s ease;
                box-shadow: none;
                padding-top: 60px; /* Altura da navbar superior */
                padding-left: 0; /* Garantir que não tenha padding extra */
                margin-left: 0; /* Garantir que esteja totalmente na esquerda */
            }
            
            /* Garantir que os textos dos menus apareçam em mobile */
            .sidebar.mobile-show .nav-link span,
            .sidebar.mobile-show.collapsed .nav-link span {
                opacity: 1;
                width: auto;
                height: auto;
                display: inline;
            }
            
            /* Garantir que as setas apareçam em mobile */
            .sidebar.mobile-show .fa-chevron-down,
            .sidebar.mobile-show.collapsed .fa-chevron-down {
                display: inline;
            }
            
            /* Garantir que a seta seja visível em mobile */
            .sidebar-toggle-arrow {
                padding: 12px 15px;
                border-bottom: 1px solid rgba(0,0,0,0.05);
                background-color: #f8fafc;
                position: sticky;
                top: 0;
                z-index: 1;
            }
            
            /* Em mobile, adicionar um botão hamburger na barra superior novamente */
            .mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                cursor: pointer;
                margin-right: 10px;
                color: #64748b;
            }
            
            .mobile-toggle i {
                font-size: 20px;
                transition: color 0.2s;
            }
            
            .mobile-toggle:hover i {
                color: #3b82f6;
            }
            
            .sidebar.mobile-show {
                transform: translateX(0);
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
            }
            
            /* Restaurar o estilo normal do menu em mobile mesmo quando colapsado */
            .sidebar.collapsed.mobile-show .nav-link i {
                margin-right: 12px;
            }
            
            .sidebar.collapsed.mobile-show .nav-link > div {
                width: auto;
                text-align: left;
                justify-content: flex-start;
            }
            
            /* Remover classe collapsed em mobile para evitar conflitos */
            .sidebar.collapsed.mobile-show {
                width: 280px !important; /* Ajustado para nova largura */
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
        }
        
        /* Esconder a seta em desktop quando o menu estiver colapsado */
        @media (min-width: 992px) {
            .sidebar.collapsed .sidebar-toggle-arrow span {
                display: none;
            }
            
            .sidebar.collapsed .sidebar-toggle-arrow {
                justify-content: center;
                padding: 12px 0;
            }
            
            .sidebar.collapsed .sidebar-toggle-arrow .toggle-content {
                justify-content: center;
            }
            
            /* Efeito hover para mostrar texto no menu colapsado */
            .sidebar.collapsed .nav-item:hover .nav-link span {
                position: absolute;
                left: 80px;
                background-color: #fff;
                padding: 8px 16px;
                border-radius: 6px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                opacity: 1;
                visibility: visible;
                display: block;
                width: auto;
                z-index: 1060;
            }
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
            padding: 0 5px;
            gap: 3px; /* Espaço entre o sino e a seta */
            margin-right: 8px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        
        /* Compactar os ícones no header */
        .logo-container {
            margin-right: 6px;
        }
        
        .mobile-toggle {
            margin-right: 6px;
        }
        
        #reportIcon {
            margin-right: 2px;
            padding: 0 3px;
        }
        
        #reportIcon i {
            font-size: 18px;
        }
        
        #alertDropdown {
            margin-right: 4px;
        }
        
        .notification-icon:hover {
            background-color: #f1f5f9;
        }
        
        .notification-icon i.fa-chevron-down {
            font-size: 10px;
            color: #94a3b8;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -3px;
            min-width: 16px;
            height: 16px;
            padding: 0 3px;
            font-size: 0.65rem;
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
            gap: 8px;
        }

        .user-menu .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
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
        
        /* Adicionando ícones para melhor visualização quando colapsado */
        .sidebar .nav-item {
            position: relative;
        }
        
        .sidebar .dropdown-toggle::after {
            display: none; /* Remove a seta padrão do Bootstrap */
        }
        
        /* Container específico para a seta customizada */
        .sidebar .chevron-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 14px;
        }
        
        .sidebar.collapsed .dropdown-toggle::after {
            opacity: 0;
        }
        
        .sidebar .submenu {
            padding-left: 20px;
            margin-left: 0;
            padding-top: 2px;
            padding-bottom: 2px;
        }
        
        .sidebar .submenu .dropdown-item {
            padding: 8px 12px 8px 25px; /* Reduzido padding direito */
            color: #64748b;
            font-size: 0.82rem;
            display: block;
            text-decoration: none;
            transition: all 0.2s;
            border-radius: 6px;
            white-space: normal; /* Permite quebra de linha se necessário */
            line-height: 1.3; /* Melhora a legibilidade */
        }
        
        .sidebar .submenu .dropdown-item:hover {
            background-color: #f1f5f9;
            color: #3b82f6;
        }
        
        .sidebar.collapsed .collapse {
            position: absolute;
            left: 100%;
            top: 0;
            width: 220px; /* Aumentado para acomodar textos maiores */
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 8px;
            z-index: 1000;
        }

        .sidebar .fa-chevron-down {
            font-size: 8px;
            transition: transform 0.3s;
            opacity: 0.7;
            margin-right: 2px;
        }
        
        .sidebar .nav-link[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }

        /* Esconder seta quando o menu está colapsado */
        .sidebar.collapsed .fa-chevron-down {
            display: none;
        }
        
        /* Ajuste no alinhamento do texto/ícones */
        .sidebar .nav-link > div {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1; /* Permite que ocupe o máximo de espaço disponível */
            min-width: 0; /* Permite que o flex funcione corretamente */
        }
        
        .sidebar.collapsed .nav-link > div {
            width: 100%;
            text-align: center;
            justify-content: center;
        }
        
        .sidebar.collapsed .nav-link i:not(.fa-chevron-down) {
            margin-right: 0;
        }

        /* Estilo para a nova seta de toggle do menu */
        .sidebar-toggle-arrow {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 5px;
            background-color: #f8fafc;
        }
        
        .sidebar-toggle-arrow .toggle-content {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .sidebar-toggle-arrow i {
            font-size: 14px;
            transition: transform 0.3s, color 0.3s;
        }
        
        .sidebar-toggle-arrow span {
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .sidebar-toggle-arrow:hover .toggle-content {
            color: #3b82f6;
        }
        
        /* Rotacionar seta quando o menu está colapsado */
        .sidebar.collapsed .sidebar-toggle-arrow i {
            transform: rotate(180deg);
        }
        
        /* Manter a logo centralizada sem o botão de menu */
        .top-navbar {
            padding-left: 15px;
        }

        /* Estilos para o dropdown de alertas - dimensões reduzidas */
        .notification-dropdown {
            max-width: 280px !important; /* Reduzido de largura padrão */
            font-size: 0.85rem;
            padding: 0.5rem 0;
            border-radius: 8px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-top: 10px !important;
        }
        
        .notification-dropdown .dropdown-header {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 0.25rem;
            color: #475569;
        }
        
        .notification-dropdown .dropdown-item {
            padding: 0.5rem 1rem;
            white-space: normal; /* Permite quebra de linha */
            line-height: 1.2;
            transition: background-color 0.2s;
            color: #475569;
        }
        
        .notification-dropdown .dropdown-item:hover {
            background-color: #f1f5f9;
        }
        
        .notification-dropdown .compact-alert {
            font-size: 0.8rem;
            line-height: 1.2;
        }
        
        .notification-dropdown .compact-alert small {
            font-size: 0.75rem;
            opacity: 0.8;
            color: #64748b;
        }

        .notification-dropdown .dropdown-divider {
            margin: 0.25rem 0;
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        .dropdown-menu-end {
            right: 0;
            left: auto;
        }
        
        @media (max-width: 767px) {
            .notification-dropdown {
                max-width: 250px !important; /* Reduzido ainda mais para mobile */
                font-size: 0.75rem;
                margin-top: 10px !important; /* Posiciona mais para baixo */
                position: absolute !important;
                right: 5px !important;
                left: auto !important;
            }
            
            .notification-dropdown .dropdown-header {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            .notification-dropdown .dropdown-item {
                padding: 0.4rem 0.8rem;
            }
            
            .notification-dropdown .compact-alert {
                font-size: 0.7rem;
                line-height: 1.1;
            }
            
            .notification-dropdown .compact-alert small {
                font-size: 0.65rem;
            }
        }

        /* Badge para itens de menu */
        .nav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            font-size: 0.65rem;
            font-weight: bold;
            color: white;
            background-color: #ef4444;
            border-radius: 10px;
            margin-left: 5px;
        }

        /* Melhoria na divisória de submenu */
        .dropdown-divider {
            margin: 0.25rem 1rem;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
    </style>

    <!-- Adicione os scripts das APIs configuradas -->
    <?php if (isset($chavesApi['tiny'])) : ?>
        <script src="https://cdn.tiny.cloud/1/<?php echo htmlspecialchars($chavesApi['tiny']); ?>/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
    <?php endif; ?>
    <?php if (isset($chavesApi['google_maps'])) : ?>
        <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($chavesApi['google_maps']); ?>"></script>
    <?php endif; ?>
</head>

<body <?php echo (isset($_COOKIE['sidebarState']) && $_COOKIE['sidebarState'] === 'expanded') ? '' : 'class="sidebar-collapsed"'; ?>>
    <!-- Mobile backdrop -->
    <div class="mobile-backdrop" id="mobileBackdrop"></div>
    
    <!-- Navbar Superior -->
    <div class="top-navbar">
        <!-- Botão hamburger visível apenas em mobile -->
        <div class="mobile-toggle d-lg-none" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </div>
        
        <div class="logo-container">
            <a href="../Dashboard/dashboard.php">
                <img src="/visamunicipal/assets/img/logo.png" alt="Logomarca" class="logo">
            </a>
        </div>
        
        <div class="user-menu">
            <!-- Notificações -->
            <div class="notification-icon" id="alertDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="notification-container">
                    <i class="fas fa-bell <?php echo ($totalAlertas > 0) ? 'shake' : ''; ?>"></i>
                    <?php if ($totalAlertas > 0) : ?>
                        <span class="notification-badge"><?php echo $totalAlertas < 10 ? $totalAlertas : '9+'; ?></span>
                    <?php endif; ?>
                </div>
                <i class="fas fa-chevron-down"></i>

                <ul class="dropdown-menu dropdown-menu-end notification-dropdown shadow-lg rounded-lg overflow-hidden" aria-labelledby="alertDropdown">
                    <li class="dropdown-header text-gray-600 font-medium">Centro de Alertas</li>
                    <?php if (empty($ultimosAlertas)) : ?>
                        <li><a class="dropdown-item hover:bg-gray-100" href="#">Nenhum alerta</a></li>
                    <?php else : ?>
                        <?php foreach ($ultimosAlertas as $alerta) : ?>
                            <li><a class="dropdown-item hover:bg-gray-100" href="../Alertas/alertas_usuario_logado.php">
                                    <div class="compact-alert">
                                    <?php
                                    $alerta_texto = '';
                                    if (isset($alerta['status']) && $alerta['status'] == 'rascunho') {
                                        $alerta_texto = 'Rascunho: ' . htmlspecialchars(substr($alerta['tipo_documento'], 0, 25));
                                    } elseif (isset($alerta['tipo_documento'])) {
                                        $alerta_texto = 'Assinatura: ' . htmlspecialchars(substr($alerta['tipo_documento'], 0, 25));
                                    } elseif (isset($alerta['descricao'])) {
                                        $alerta_texto = 'Processo: ' . htmlspecialchars(substr($alerta['descricao'], 0, 25));
                                    } elseif (isset($alerta['tipo']) && $alerta['tipo'] == 'Processos com Documentação Pendente') {
                                        $alerta_texto = 'Doc. Pendente: ' . htmlspecialchars($alerta['total']);
                                    } else {
                                        $alerta_texto = 'Designado: ' . htmlspecialchars(substr($alerta['nome_fantasia'] ?? '', 0, 25));
                                    }
                                    
                                    // Adicionar "..." se o texto foi truncado
                                    if (
                                        (isset($alerta['tipo_documento']) && strlen($alerta['tipo_documento']) > 25) ||
                                        (isset($alerta['descricao']) && strlen($alerta['descricao']) > 25) ||
                                        (isset($alerta['nome_fantasia']) && strlen($alerta['nome_fantasia']) > 25)
                                    ) {
                                        $alerta_texto .= '...';
                                    }
                                    
                                    echo $alerta_texto;
                                    
                                    // Exibir nome fantasia em texto menor se disponível
                                    if (!empty($alerta['nome_fantasia']) && 
                                        (!isset($alerta['tipo']) || $alerta['tipo'] != 'Processos Designados Pendentes')) {
                                        echo '<small class="d-block text-muted">' . htmlspecialchars(substr($alerta['nome_fantasia'], 0, 25)) . '</small>';
                                    }
                                    ?>
                                    </div>
                                </a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center text-blue-600 hover:bg-gray-100" href="../Alertas/alertas_usuario_logado.php" onclick="window.location.href='../Alertas/alertas_usuario_logado.php'; return false;">Ver todos os alertas</a></li>
                </ul>
            </div>
            
            <!-- Ícone de Relatório de Erro -->
            <div class="notification-icon" id="reportIcon" data-bs-toggle="modal" data-bs-target="#reportarErroModal" title="Relatar um problema">
                <div class="notification-container">
                    <i class="fas fa-bug"></i>
                </div>
            </div>
            
            <!-- Perfil/Conta do Usuário -->
            <div class="dropdown">
                <a class="dropdown-toggle d-flex align-items-center" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-1"></i>
                    <span class="d-none d-md-inline"><?php 
                        $nomeUsuario = '';
                        
                        // Adicione este log temporário para debug - remova depois
                        error_log('INFO USUÁRIO: ' . print_r($_SESSION['user'], true));
                        
                        if (isset($_SESSION['user']['nome']) && !empty($_SESSION['user']['nome'])) {
                            $partesNome = explode(' ', trim($_SESSION['user']['nome']));
                            $nomeUsuario = $partesNome[0]; // Pega apenas o primeiro nome
                        } elseif (isset($_SESSION['user']['username']) && !empty($_SESSION['user']['username'])) {
                            // Algumas aplicações usam 'username' em vez de 'nome'
                            $nomeUsuario = $_SESSION['user']['username'];
                        } elseif (isset($_SESSION['user']['email']) && !empty($_SESSION['user']['email'])) {
                            // Se não tiver nome, pegar a parte antes do @ do email
                            $emailParts = explode('@', $_SESSION['user']['email']);
                            $nomeUsuario = $emailParts[0];
                        } else {
                            $nomeUsuario = 'Usuário';
                        }
                        echo htmlspecialchars($nomeUsuario);
                    ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-lg" aria-labelledby="accountDropdown">
                    <li><a class="dropdown-item hover:bg-gray-100" href="../Admin/editar_cadastro_usuario.php">Editar Cadastro</a></li>
                    <li><a class="dropdown-item hover:bg-gray-100" href="../Usuario/alterar_senha.php">Alterar Senha</a></li>
                    <li><a class="dropdown-item hover:bg-gray-100" href="../Usuario/senha_digital.php">Senha Digital</a></li>
                    <li><a class="dropdown-item hover:bg-gray-100" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#reportarErroModal">
                        <i class="fas fa-bug me-1 text-danger"></i> Relatar Erro
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item hover:bg-gray-100" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Menu Lateral -->
    <div class="sidebar <?php echo (isset($_COOKIE['sidebarState']) && $_COOKIE['sidebarState'] === 'expanded') ? '' : 'collapsed'; ?>" id="sidebar">
        <ul class="nav flex-column">
            <!-- Seta para abrir/fechar o menu -->
            <li class="sidebar-toggle-arrow" id="sidebarToggleArrow">
                <div class="toggle-content">
                    <i class="fas fa-chevron-left"></i>
                    <span>Menu</span>
                </div>
                    </li>
            
            <!-- Estabelecimentos -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#estabelecimentoCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-building"></i>
                        <span>Estabelecimentos</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="estabelecimentoCollapse">
                    <ul class="nav flex-column submenu">
                        <li><a class="dropdown-item" href="../Estabelecimento/listar_estabelecimentos.php">Lista</a></li>
                        <li><a class="dropdown-item" href="../Estabelecimento/listar_todos_pendentes.php">Pendentes</a></li>
                        <li><a class="dropdown-item" href="../Estabelecimento/listar_estabelecimentos_rejeitados.php">Negados</a></li>
                        <li><a class="dropdown-item" href="../Estabelecimento/atualizar_situacao_cadastral.php">Situação Cadastral</a></li>
                        </ul>
                </div>
                    </li>

            <!-- Processos -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#processoCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-clipboard-list"></i>
                        <span>Processos</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="processoCollapse">
                    <ul class="nav flex-column submenu">
                        <li><a class="dropdown-item" href="/visamunicipal/views/Processo/listar_processos.php">Lista</a></li>
                        <li>
                            <a class="dropdown-item" href="/visamunicipal/views/Processo/listar_processos.php?search=&pendentes=1">
                                Documentação Pendente
                                <?php if ($pendentes > 0) : ?>
                                    <span class="badge bg-danger ms-1"><?php echo $pendentes; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                            <?php if ($_SESSION['user']['nivel_acesso'] == 1 || $_SESSION['user']['nivel_acesso'] == 3) : ?>
                            <li><a class="dropdown-item" href="/visamunicipal/views/Processo/listar_processos_designados.php">Processos Designados</a></li>
                            <?php endif; ?>
                        </ul>
                </div>
                    </li>

            <!-- Ordens de Serviço -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#osCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-tasks"></i>
                        <span>Ordens de Serviço</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="osCollapse">
                    <ul class="nav flex-column submenu">
                            <li><a class="dropdown-item" href="../OrdemServico/listar_ordens.php">Lista</a></li>
                        </ul>
                </div>
                    </li>

            <!-- Documentos -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#docCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-file-alt"></i>
                        <span>Documentos</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="docCollapse">
                    <ul class="nav flex-column submenu">
                            <li><a class="dropdown-item" href="../Arquivos/todos_arquivos.php">Lista</a></li>
                            <li><a class="dropdown-item" href="../Arquivos/documentos_para_finalizar.php">Finalizar</a></li>
                        </ul>
                </div>
                    </li>

            <!-- Assinaturas -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#assCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-signature"></i>
                        <span>Assinaturas</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="assCollapse">
                    <ul class="nav flex-column submenu">
                            <li><a class="dropdown-item" href="../Assinatura/listar_assinaturas_pendentes.php">Assinaturas Pendentes</a></li>
                            <li><a class="dropdown-item" href="../Assinatura/assinaturas_realizadas_usuario.php">Documentos Assinados</a></li>
                        </ul>
                </div>
                    </li>

            <!-- Alertas e Relatos -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#alertasCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Alertas e Relatos</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="alertasCollapse">
                    <ul class="nav flex-column submenu">
                        <li><a class="dropdown-item" href="../Alertas/alertas_usuario_logado.php">Meus Alertas</a></li>
                        <li><a class="dropdown-item" href="../Alertas/meus_relatos_internos.php">Meus Relatos</a></li>
                    </ul>
                </div>
            </li>
            
            <!-- Responsáveis -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#responsaveisCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-user-tie"></i>
                        <span>Responsáveis</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="responsaveisCollapse">
                    <ul class="nav flex-column submenu">
                        <li><a class="dropdown-item" href="../Responsaveis/listar_responsaveis.php">Listar Todos</a></li>
                    </ul>
                </div>
            </li>

            <!-- Gerenciar (condicionalmente exibido) -->
                    <?php if ($_SESSION['user']['nivel_acesso'] == 1 || $_SESSION['user']['nivel_acesso'] == 3) : ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#gerCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-cog"></i>
                        <span>Gerenciar</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="gerCollapse">
                    <ul class="nav flex-column submenu">
                                <li><a class="dropdown-item" href="../Portarias/listar_portarias.php">Portarias</a></li>
                                <li><a class="dropdown-item" href="../ModeloDocumentos/listar_modelos.php">Modelos de Documentos</a></li>
                        <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../TipoAcoes/listar_tipos.php">Adicionar Tipo Ação</a></li>
                                <li><a class="dropdown-item" href="../TipoAcoes/adicionar_pontuacao.php">Adicionar Pontuação</a></li>
                                <?php if ($_SESSION['user']['nivel_acesso'] == 1) : ?>
                                    <li><a class="dropdown-item" href="../TipoAcoes/adicionar_grupo_risco.php">Tipo Grupo de Risco</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="../TipoAcoes/adicionar_atividade_grupo_risco.php">Atividade/Grupo de Risco</a></li>
                        <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../Logomarcas/cadastrar_logomarca.php">Cadastrar Logomarca</a></li>
                                <li><a class="dropdown-item" href="../Logomarcas/listar_logomarcas.php">Lista Logomarca</a></li>
                        <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../Alertas/alert_all.php">Alertas Empresas</a></li>
                                <li><a class="dropdown-item" href="../Admin/listar_usuarios.php">Usuários Interno</a></li>
                                <li><a class="dropdown-item" href="../Company/listar_usuarios.php">Usuários Externos</a></li>
                                <li><a class="dropdown-item" href="../Logs/exclusoes.php">Logs Infovisa</a></li>
                            </ul>
                </div>
                        </li>
                    <?php endif; ?>

            <!-- Relatórios -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#relCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-chart-bar"></i>
                        <span>Relatórios</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="relCollapse">
                    <ul class="nav flex-column submenu">
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_estabelecimentos.php">Estabelecimentos</a></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_acoes_executadas.php">Ações Executadas</a></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_atividades.php">Atividades</a></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_alvara.php">Alvará Sanitário</a></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_documentos.php">Documentos</a></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_grupoderisco.php">Grupo de Risco</a></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorios_processo.php">Processos</a></li>
                        <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_pontuacao.php">Resumo de Pontuações</a></li>
                            <li><a class="dropdown-item" href="../Relatorio/relatorio_acoes_usuario.php">Detalhes de Pontuação</a></li>
                            <?php if ($_SESSION['user']['nivel_acesso'] == 1) : ?>
                                <li><a class="dropdown-item" href="../Relatorio/relatorio_usuarios_municipio.php">Usuários por Município</a></li>
                            <?php endif; ?>
                        </ul>
                </div>
                    </li>

            <!-- Admin (condicionalmente exibido) -->
                    <?php if ($_SESSION['user']['nivel_acesso'] == 1) : ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between" href="#adminCollapse" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="fas fa-user-shield"></i>
                        <span>Admin</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="adminCollapse">
                    <ul class="nav flex-column submenu">
                                <li><a class="dropdown-item" href="../Admin/configuracoes_sistema.php"><i class="fas fa-cogs mr-2"></i>Configurações do Sistema</a></li>
                                <li><a class="dropdown-item" href="../Admin/apitiny.php">Api</a></li>
                                <li><a class="dropdown-item" href="../RelacaoDocumentos/index.php">Adicionar Relação Documentos</a></li>
                                <li><a class="dropdown-item" href="../Admin/listar_relatos.php">Relatos</a></li>
                                <li><a class="dropdown-item" href="../Admin/gerenciar_documentos_cnae.php">Documentos por CNAE</a></li>
                            </ul>
                </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

    <!-- Área de conteúdo principal -->
    <div class="content-wrapper">
        <div class="container-fluid pt-3">
            <!-- O conteúdo de cada página será carregado aqui -->
            </div>
        </div>
        
    <!-- Modal para Reportar Erro -->
    <div class="modal fade" id="reportarErroModal" tabindex="-1" aria-labelledby="reportarErroModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportarErroModalLabel">
                        <i class="fas fa-bug text-danger me-2"></i>Reportar Problema ou Sugestão
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form action="/visamunicipal/controllers/RelatorController.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_relato_interno">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="tipo" class="form-label fw-medium">Tipo de Relato</label>
                            <div class="d-flex gap-3 mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoBugInterno" value="BUG" checked>
                                    <label class="form-check-label" for="tipoBugInterno">
                                        <i class="fas fa-bug text-danger me-1"></i> Problema/Erro
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoMelhoriaInterno" value="MELHORIA">
                                    <label class="form-check-label" for="tipoMelhoriaInterno">
                                        <i class="fas fa-lightbulb text-warning me-1"></i> Sugestão de Melhoria
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="descricao" class="form-label fw-medium">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="5" 
                                placeholder="Descreva o problema ou sua sugestão em detalhes..." required></textarea>
                            <div class="form-text small mt-2">
                                <i class="fas fa-info-circle me-1"></i> Seja claro e forneça detalhes para que possamos entender melhor.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-medium">Captura de Tela</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="captureScreenButton">
                                    <i class="fas fa-camera me-1"></i> Capturar Tela
                                </button>
                                <span class="text-muted small">ou</span>
                                <div class="input-group">
                                    <input type="file" class="form-control form-control-sm" id="screenshotFile" name="screenshot" accept="image/*">
                                </div>
                            </div>
                            <div id="screenshotPreview" class="mt-2 d-none">
                                <div class="d-flex align-items-center p-2 bg-light rounded border">
                                    <i class="fas fa-image text-primary me-2"></i>
                                    <span id="screenshotName">screenshot.png</span>
                                    <button type="button" class="btn btn-sm text-danger ms-auto border-0" id="removeScreenshot">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="screenshot_data" id="screenshotData">
                            <div class="form-text small mt-1">
                                Uma captura de tela pode ajudar a entender melhor o problema.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-medium">URL da Página</label>
                            <input type="text" class="form-control" id="pageUrl" name="page_url" readonly>
                            <div class="form-text small">
                                Este é o endereço da página atual onde o problema ocorreu.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-paper-plane me-1"></i> Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/typescript@5.2.2/lib/typescript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="/visamunicipal/assets/js/main.js"></script>
    <script src="/visamunicipal/assets/js/mobile-menu.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Garantir que o menu lateral funcione corretamente
            const menuLinks = document.querySelectorAll('.sidebar .nav-link');
            const sidebar = document.getElementById('sidebar');
            const body = document.body;
            
            // Verificar se estamos em dispositivo móvel
            const isMobile = () => window.innerWidth < 992;
            
            // Função para expandir o menu
            function expandSidebar() {
                if (sidebar && sidebar.classList.contains('collapsed')) {
                    sidebar.classList.remove('collapsed');
                    body.classList.remove('sidebar-collapsed');
                    
                    // Salvar estado em cookie
                    const expiryDate = new Date();
                    expiryDate.setDate(expiryDate.getDate() + 30);
                    document.cookie = "sidebarState=expanded; expires=" + expiryDate.toUTCString() + "; path=/";
                    
                    return true;
                }
                return false;
            }
            
            // Prevenir comportamento padrão para menus dropdown em mobile
            document.querySelectorAll('.sidebar .nav-link[data-bs-toggle="collapse"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (isMobile()) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const targetId = this.getAttribute('href');
                        const targetElement = document.querySelector(targetId);
                        
                        if (targetElement) {
                            // Toggle manual do menu
                            const isExpanded = this.getAttribute('aria-expanded') === 'true';
                            
                            // Fechar todos os outros dropdowns primeiro
                            document.querySelectorAll('.sidebar .collapse.show').forEach(openMenu => {
                                // Não fechar o que estamos prestes a abrir/fechar
                                if (openMenu.id !== targetId.substring(1)) {
                                    openMenu.classList.remove('show');
                                    const relatedLink = document.querySelector(`[href="#${openMenu.id}"]`);
                                    if (relatedLink) {
                                        relatedLink.setAttribute('aria-expanded', 'false');
                                    }
                                }
                            });
                            
                            // Alternar o dropdown atual
                            if (isExpanded) {
                                targetElement.classList.remove('show');
                                this.setAttribute('aria-expanded', 'false');
                            } else {
                                targetElement.classList.add('show');
                                this.setAttribute('aria-expanded', 'true');
                            }
                        }
                        
                        return false;
                    }
                });
            });
            
            // Adicionar evento de clique para cada item de menu
            menuLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Se o menu estiver colapsado e clicarmos em qualquer item (apenas em desktop)
                    if (sidebar.classList.contains('collapsed') && !isMobile()) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Expandir o menu
                        if (expandSidebar()) {
                            const isDropdown = this.hasAttribute('data-bs-toggle');
                            
                            if (isDropdown) {
                                // Para itens com submenu
                                const targetId = this.getAttribute('href');
                                
                                setTimeout(() => {
                                    const targetElement = document.querySelector(targetId);
                                    if (targetElement && !targetElement.classList.contains('show')) {
                                        targetElement.classList.add('show');
                                        this.setAttribute('aria-expanded', 'true');
                                    }
                                }, 300);
                            } else {
                                // Para links diretos
                                const href = this.getAttribute('href');
                                setTimeout(() => {
                                    window.location.href = href;
                                }, 300);
                            }
                        }
                    }
                });
            });
            
            // Toggle para o menu lateral (agora usando a seta)
            const sidebarToggleArrow = document.getElementById('sidebarToggleArrow');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileBackdrop = document.getElementById('mobileBackdrop');
            
            // Botão de menu hamburger para mobile
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleMobileSidebar();
                });
            }
            
            // Definir comportamento do botão de toggle (seta)
            if (sidebarToggleArrow) {
                sidebarToggleArrow.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (isMobile()) {
                        // Em mobile, a seta apenas fecha o menu
                        toggleMobileSidebar();
                    } else {
                        // Em desktop, alterna o menu
                        toggleSidebar();
                    }
                });
            }
            
            // Função para alternar o sidebar em dispositivos móveis
            function toggleMobileSidebar() {
                sidebar.classList.toggle('mobile-show');
                mobileBackdrop.classList.toggle('show');
                
                // Bloquear rolagem do body quando o menu estiver aberto
                if (sidebar.classList.contains('mobile-show')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
            
            // Evitar que cliques em itens de submenu fechem o menu em mobile
            document.querySelectorAll('.sidebar .dropdown-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (isMobile()) {
                        e.stopPropagation(); // Impedir propagação do evento
                    }
                });
            });
            
            // Fechar o menu ao clicar no backdrop
            if (mobileBackdrop) {
                mobileBackdrop.addEventListener('click', function() {
                    toggleMobileSidebar();
                });
            }
            
            // Logo clique - redirecionamento para dashboard com efeito de hover
            const logoLink = document.querySelector('.logo-container a');
            if (logoLink) {
                logoLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dashboardUrl = this.getAttribute('href');
                    
                    // Efeito visual sutil
                    this.style.opacity = '0.6';
                    
                    // Redireciona após breve delay para efeito visual
                    setTimeout(() => {
                        window.location.href = dashboardUrl;
                    }, 100);
                });
            }
            
            // Função para alternar o estado do sidebar
            function toggleSidebar() {
                sidebar.classList.toggle('collapsed');
                body.classList.toggle('sidebar-collapsed');
                
                // Salvar o estado em cookie com duração de 30 dias
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + 30);
                
                if (sidebar.classList.contains('collapsed')) {
                    document.cookie = "sidebarState=collapsed; expires=" + expiryDate.toUTCString() + "; path=/";
                    
                    // Fechar todos os submenus ao colapsar
                    document.querySelectorAll('.sidebar .collapse.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                } else {
                    document.cookie = "sidebarState=expanded; expires=" + expiryDate.toUTCString() + "; path=/";
                }
            }
            
            // Verificar tamanho da tela ao iniciar e redimensionar
            function checkScreenSize() {
                if (isMobile()) {
                    // Em mobile, apenas certificamos que não há conflito de classes
                    document.body.style.paddingLeft = '0';
                    // Não mexer no estado do menu aqui - ele deve ser controlado apenas pelo botão de toggle
                } else {
                    // Verificar cookie para tela grande
                    sidebar.classList.remove('mobile-show');
                    mobileBackdrop.classList.remove('show');
                    document.body.style.overflow = '';
                    
                    const sidebarState = getCookie('sidebarState');
                    if (sidebarState === 'expanded') {
                        sidebar.classList.remove('collapsed');
                        body.classList.remove('sidebar-collapsed');
                    } else {
                        sidebar.classList.add('collapsed');
                        body.classList.add('sidebar-collapsed');
                    }
                }
            }
            
            // Função para obter valor de cookie
            function getCookie(name) {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) return parts.pop().split(';').shift();
                return null;
            }
            
            // Executar ao carregar e ao redimensionar
            checkScreenSize();
            window.addEventListener('resize', function() {
                checkScreenSize();
            });
            
            // Preencher a URL da página atual
            const pageUrlInput = document.getElementById('pageUrl');
            if (pageUrlInput) {
                pageUrlInput.value = window.location.href;
            }
            
            // Manipular o upload de arquivo de screenshot
            const screenshotFile = document.getElementById('screenshotFile');
            if (screenshotFile) {
                screenshotFile.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        showScreenshotPreview(this.files[0].name);
                        // Limpar o screenshot capturado se um foi feito upload
                        document.getElementById('screenshotData').value = '';
                    }
                });
            }
            
            // Botão de remover screenshot
            const removeScreenshotBtn = document.getElementById('removeScreenshot');
            if (removeScreenshotBtn) {
                removeScreenshotBtn.addEventListener('click', function() {
                    document.getElementById('screenshotPreview').classList.add('d-none');
                    document.getElementById('screenshotFile').value = '';
                    document.getElementById('screenshotData').value = '';
                });
            }
            
            // Botão de capturar tela
            const captureScreenButton = document.getElementById('captureScreenButton');
            if (captureScreenButton) {
                captureScreenButton.addEventListener('click', function() {
                    // Fechar o modal temporariamente para capturar a tela
                    const reportModal = bootstrap.Modal.getInstance(document.getElementById('reportarErroModal'));
                    
                    // Salvar os valores do formulário para restaurar após a captura
                    const tipo = document.querySelector('input[name="tipo"]:checked')?.value || 'BUG';
                    const descricao = document.getElementById('descricao').value;
                    
                    reportModal.hide();
                    
                    // Aguardar o fechamento do modal
                    setTimeout(function() {
                        // Capturar a tela
                        html2canvas(document.body).then(canvas => {
                            // Converter canvas para imagem
                            const imageData = canvas.toDataURL('image/png');
                            
                            // Reabrir o modal
                            reportModal.show();
                            
                            // Restaurar valores do formulário
                            document.querySelector(`input[name="tipo"][value="${tipo}"]`).checked = true;
                            document.getElementById('descricao').value = descricao;
                            
                            // Armazenar a imagem no campo hidden
                            document.getElementById('screenshotData').value = imageData;
                            
                            // Gerar um nome de arquivo único
                            const timestamp = new Date().getTime();
                            const filename = `screenshot_${timestamp}.png`;
                            
                            // Exibir o preview
                            showScreenshotPreview(filename);
                            
                            // Limpar qualquer arquivo de upload
                            document.getElementById('screenshotFile').value = '';
                        });
                    }, 500);
                });
            }
            
            // Função para exibir o preview do screenshot
            function showScreenshotPreview(filename) {
                const preview = document.getElementById('screenshotPreview');
                const nameElement = document.getElementById('screenshotName');
                
                if (preview && nameElement) {
                    nameElement.textContent = filename;
                    preview.classList.remove('d-none');
                }
            }
        });
    </script>
</body>

</html>

