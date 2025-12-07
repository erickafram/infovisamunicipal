/**
 * Mobile Menu Handler
 * Script específico para garantir o funcionamento correto do menu em dispositivos móveis
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elementos do menu
    const sidebar = document.getElementById('sidebar');
    const dropdownToggles = document.querySelectorAll('.sidebar .nav-link[data-bs-toggle="collapse"]');
    const dropdownItems = document.querySelectorAll('.sidebar .dropdown-item');
    const mobileBackdrop = document.getElementById('mobileBackdrop');
    
    // Função para verificar se estamos em dispositivo móvel
    const isMobile = () => window.innerWidth < 992;
    
    // Aplicar correções imediatamente
    function applyMobileMenuFixes() {
        if (!isMobile()) return;
        
        console.log("Aplicando correções para menu mobile");
        
        // 1. Garantir que os textos dos menus estejam sempre visíveis
        document.querySelectorAll('.sidebar .nav-link span').forEach(span => {
            span.style.opacity = '1';
            span.style.visibility = 'visible';
            span.style.width = 'auto';
            span.style.display = 'inline-block';
        });
        
        // 2. Garantir que os ícones de seta estejam visíveis
        document.querySelectorAll('.sidebar .fa-chevron-down').forEach(icon => {
            icon.style.display = 'inline-block';
            icon.style.opacity = '1';
            icon.style.visibility = 'visible';
        });
        
        // 3. Adicionar classe especial para identificar que o fix foi aplicado
        if (!sidebar.classList.contains('mobile-fix-applied')) {
            sidebar.classList.add('mobile-fix-applied');
        }
    }
    
    // Corrigir eventos dos menus dropdown em mobile
    function fixMobileMenuEvents() {
        if (!isMobile()) return;
        
        console.log("Corrigindo eventos do menu mobile");
        
        // Remover eventos Bootstrap dos toggles de dropdown
        dropdownToggles.forEach(link => {
            // Remover qualquer evento anterior
            const newLink = link.cloneNode(true);
            if (link.parentNode) {
                link.parentNode.replaceChild(newLink, link);
                
                // Adicionar novo handler de clique
                newLink.addEventListener('click', function(e) {
                    if (isMobile()) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const targetId = this.getAttribute('href');
                        const targetElement = document.querySelector(targetId);
                        
                        if (targetElement) {
                            // Toggle manual do dropdown
                            if (targetElement.classList.contains('show')) {
                                targetElement.classList.remove('show');
                                this.setAttribute('aria-expanded', 'false');
                            } else {
                                // Abrir o dropdown atual
                                targetElement.classList.add('show');
                                this.setAttribute('aria-expanded', 'true');
                            }
                        }
                        
                        // Manter o menu aberto
                        if (!sidebar.classList.contains('mobile-show')) {
                            sidebar.classList.add('mobile-show');
                            if (mobileBackdrop) mobileBackdrop.classList.add('show');
                        }
                        
                        return false;
                    }
                });
            }
        });
        
        // Evitar que cliques em itens de dropdown fechem o menu
        dropdownItems.forEach(item => {
            const newItem = item.cloneNode(true);
            if (item.parentNode) {
                item.parentNode.replaceChild(newItem, item);
                
                newItem.addEventListener('click', function(e) {
                    if (isMobile()) {
                        e.stopPropagation();
                    }
                });
            }
        });
    }

    // Adicionar classes e estilos necessários para visualização adequada em mobile
    function setupMobileMenu() {
        if (!isMobile()) return;
        
        console.log("Configurando menu mobile");
        
        // Adicionar classe que indica que o menu foi preparado para mobile
        document.body.classList.add('mobile-menu-ready');
        
        // Garantir que os dropdowns em mobile não usem as regras para desktop
        document.querySelectorAll('.sidebar .dropdown-menu').forEach(menu => {
            menu.style.position = 'relative';
            menu.style.left = 'auto';
            menu.style.top = 'auto';
            menu.style.width = '100%';
            menu.style.border = 'none';
            menu.style.boxShadow = 'none';
        });
        
        // Remover classes que possam estar interferindo
        if (sidebar.classList.contains('collapsed') && isMobile()) {
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
            
            // Forçar exibição do texto em mobile mesmo se estiver colapsado
            document.querySelectorAll('.sidebar .nav-link span').forEach(span => {
                span.style.removeProperty('display');
                span.style.removeProperty('opacity');
                span.style.removeProperty('visibility');
                span.style.removeProperty('width');
            });
        }
    }
    
    // Função principal que aplica todas as correções
    function fixMobileMenu() {
        if (!isMobile()) return;
        
        console.log("Iniciando correções para menu mobile");
        
        // 1. Aplicar configurações iniciais
        setupMobileMenu();
        
        // 2. Aplicar correções de exibição
        applyMobileMenuFixes();
        
        // 3. Corrigir os eventos
        fixMobileMenuEvents();
        
        // 4. Adicionar classe para CSS específico
        document.documentElement.classList.add('mobile-view');
    }
    
    // Aplicar correções quando a página carrega
    if (isMobile()) {
        // Aplicar imediatamente
        fixMobileMenu();
        
        // E verificar periodicamente para garantir
        setInterval(applyMobileMenuFixes, 1000);
        
        // Observar mudanças no DOM que podem afetar o menu
        const observer = new MutationObserver(function() {
            applyMobileMenuFixes();
        });
        
        // Observar mudanças em atributos e estrutura
        if (sidebar) {
            observer.observe(sidebar, {
                attributes: true,
                childList: true,
                subtree: true,
                attributeFilter: ['class', 'style']
            });
        }
    }
    
    // Verificar ao redimensionar a janela
    window.addEventListener('resize', function() {
        if (isMobile()) {
            fixMobileMenu();
        } else {
            // Remover classe quando não estiver em mobile
            document.documentElement.classList.remove('mobile-view');
        }
    });
    
    // Verificar após carregar completamente a página
    window.addEventListener('load', function() {
        if (isMobile()) {
            // Dar tempo para o Bootstrap inicializar
            setTimeout(fixMobileMenu, 500);
        }
    });
    
    // Função adicional para corrigir altura do menu em orientação landscape
    function adjustMenuHeight() {
        if (isMobile() && sidebar) {
            const viewportHeight = window.innerHeight;
            sidebar.style.maxHeight = viewportHeight + 'px';
            sidebar.style.overflowY = 'auto';
        }
    }
    
    // Ajustar altura em orientação landscape
    window.addEventListener('orientationchange', adjustMenuHeight);
    adjustMenuHeight();
}); 