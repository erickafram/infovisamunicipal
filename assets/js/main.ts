// Interfaces
interface Usuario {
  id: number;
  nome: string;
  nivel_acesso: number;
}

// Classes
class AlertaHandler {
  private elemento: HTMLElement | null;
  
  constructor(seletor: string) {
    this.elemento = document.querySelector(seletor);
  }

  adicionarAlerta(tipo: string, mensagem: string): void {
    if (!this.elemento) return;
    
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} tailwind-alert p-4 mb-4 rounded-lg`;
    alerta.innerHTML = `
      <div class="flex items-center">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zm-1 9a1 1 0 01-1-1v-4a1 1 0 112 0v4a1 1 0 01-1 1z" clip-rule="evenodd"></path>
        </svg>
        <span>${mensagem}</span>
      </div>
    `;
    
    this.elemento.appendChild(alerta);
    
    // Remover alerta após 5 segundos
    setTimeout(() => {
      alerta.remove();
    }, 5000);
  }
}

// Inicialização quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', () => {
  // Sistema de alertas usando TypeScript e classes
  const alertaHandler = new AlertaHandler('#sistema-alertas');
  
  // Exemplo: adicionar botões para demonstração
  const botaoInfo = document.querySelector('#btn-info');
  const botaoSucesso = document.querySelector('#btn-sucesso');
  const botaoErro = document.querySelector('#btn-erro');
  
  if (botaoInfo) {
    botaoInfo.addEventListener('click', () => {
      alertaHandler.adicionarAlerta('info', 'Informação importante!');
    });
  }
  
  if (botaoSucesso) {
    botaoSucesso.addEventListener('click', () => {
      alertaHandler.adicionarAlerta('success', 'Operação concluída com sucesso!');
    });
  }
  
  if (botaoErro) {
    botaoErro.addEventListener('click', () => {
      alertaHandler.adicionarAlerta('danger', 'Erro ao processar sua solicitação!');
    });
  }
  
  // Aplicar classes do Tailwind aos elementos do menu
  const applyTailwindStyles = () => {
    // Estilizar navbar
    const navbar = document.querySelector('.navbar');
    if (navbar) {
      navbar.classList.add('shadow-md');
    }
    
    // Estilizar todos os botões dropdown
    const dropdownButtons = document.querySelectorAll('.dropdown-toggle');
    dropdownButtons.forEach(button => {
      button.classList.add('inline-flex', 'items-center');
    });
  
    // Estilizar todos os links de menu
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
      link.classList.add('hover:text-blue-600', 'transition-colors', 'duration-300');
    });
  };
  
  // Executar a função de estilos
  applyTailwindStyles();
}); 