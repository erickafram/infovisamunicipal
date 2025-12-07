// Classes
var AlertaHandler = /** @class */ (function () {
    function AlertaHandler(seletor) {
        this.elemento = document.querySelector(seletor);
    }
    AlertaHandler.prototype.adicionarAlerta = function (tipo, mensagem) {
        var _this = this;
        if (!this.elemento)
            return;
        var alerta = document.createElement('div');
        alerta.className = "alert alert-".concat(tipo, " tailwind-alert p-4 mb-4 rounded-lg");
        alerta.innerHTML = "\n      <div class=\"flex items-center\">\n        <svg class=\"w-5 h-5 mr-2\" fill=\"currentColor\" viewBox=\"0 0 20 20\" xmlns=\"http://www.w3.org/2000/svg\">\n          <path fill-rule=\"evenodd\" d=\"M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zm-1 9a1 1 0 01-1-1v-4a1 1 0 112 0v4a1 1 0 01-1 1z\" clip-rule=\"evenodd\"></path>\n        </svg>\n        <span>".concat(mensagem, "</span>\n      </div>\n    ");
        this.elemento.appendChild(alerta);
        // Remover alerta após 5 segundos
        setTimeout(function () {
            alerta.remove();
        }, 5000);
    };
    return AlertaHandler;
}());

// Menu Handler para gerenciar comportamentos do menu
var MenuHandler = /** @class */ (function () {
    function MenuHandler() {
        this.sidebar = document.getElementById('sidebar');
        this.body = document.body;
    }
    
    // Verificar se o menu está colapsado
    MenuHandler.prototype.isCollapsed = function() {
        return this.sidebar && this.sidebar.classList.contains('collapsed');
    };
    
    // Expandir o menu
    MenuHandler.prototype.expandMenu = function() {
        if (this.sidebar && this.isCollapsed()) {
            this.sidebar.classList.remove('collapsed');
            this.body.classList.remove('sidebar-collapsed');
            
            // Salvar estado em cookie
            this.saveMenuState('expanded');
            
            return true; // Menu foi expandido
        }
        return false; // Menu já estava expandido
    };
    
    // Salvar estado do menu em cookie
    MenuHandler.prototype.saveMenuState = function(state) {
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() + 30);
        document.cookie = "sidebarState=" + state + "; expires=" + expiryDate.toUTCString() + "; path=/";
    };
    
    return MenuHandler;
}());

// Inicialização quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function () {
    // Sistema de alertas usando TypeScript e classes
    var alertaHandler = new AlertaHandler('#sistema-alertas');
    
    // Inicializar o gerenciador de menu
    var menuHandler = new MenuHandler();
    
    // Adicionar eventos para os ícones do menu quando colapsado
    var menuItems = document.querySelectorAll('.sidebar .nav-link');
    if (menuItems.length) {
        menuItems.forEach(function(item) {
            item.addEventListener('click', function(e) {
                // Se o menu estiver colapsado e clicarmos em qualquer item
                if (menuHandler.isCollapsed()) {
                    // Impedir navegação imediata
                    e.preventDefault();
                    
                    // Expandir o menu
                    if (menuHandler.expandMenu()) {
                        // Se for um item com dropdown
                        if (this.hasAttribute('data-bs-toggle') && this.getAttribute('data-bs-toggle') === 'collapse') {
                            // Abrir o submenu após um pequeno delay
                            var targetId = this.getAttribute('href');
                            setTimeout(function() {
                                var targetElement = document.querySelector(targetId);
                                if (targetElement && !targetElement.classList.contains('show')) {
                                    targetElement.classList.add('show');
                                    item.setAttribute('aria-expanded', 'true');
                                }
                            }, 300);
                        } else {
                            // Se for um link direto, navegar após um breve delay
                            var href = this.getAttribute('href');
                            setTimeout(function() {
                                window.location.href = href;
                            }, 300);
                        }
                    }
                }
            });
        });
    }
    
    // Exemplo: adicionar botões para demonstração
    var botaoInfo = document.querySelector('#btn-info');
    var botaoSucesso = document.querySelector('#btn-sucesso');
    var botaoErro = document.querySelector('#btn-erro');
    if (botaoInfo) {
        botaoInfo.addEventListener('click', function () {
            alertaHandler.adicionarAlerta('info', 'Informação importante!');
        });
    }
    if (botaoSucesso) {
        botaoSucesso.addEventListener('click', function () {
            alertaHandler.adicionarAlerta('success', 'Operação concluída com sucesso!');
        });
    }
    if (botaoErro) {
        botaoErro.addEventListener('click', function () {
            alertaHandler.adicionarAlerta('danger', 'Erro ao processar sua solicitação!');
        });
    }
    // Aplicar classes do Tailwind aos elementos do menu
    var applyTailwindStyles = function () {
        // Estilizar navbar
        var navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.classList.add('shadow-md');
        }
        // Estilizar todos os botões dropdown
        var dropdownButtons = document.querySelectorAll('.dropdown-toggle');
        dropdownButtons.forEach(function (button) {
            button.classList.add('inline-flex', 'items-center');
        });
        // Estilizar todos os links de menu
        var navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(function (link) {
            link.classList.add('hover:text-blue-600', 'transition-colors', 'duration-300');
        });
    };
    // Executar a função de estilos
    applyTailwindStyles();
}); 