/**
 * Funções de validação para cadastro de usuários
 */
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone_cadastro');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.slice(0, 11);
            if (value.length > 2) value = value.replace(/(\d{2})(\d)/, '($1) $2');
            if (value.length > 7) value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            e.target.value = value;
        });
    }
    
    // Converter nome para maiúsculas
    const nomeInput = document.getElementById('nome_completo');
    if (nomeInput) {
        nomeInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Garantir que o valor seja maiúsculo ao enviar o formulário
        const form = document.getElementById('formCadastrarUsuario');
        if (form) {
            form.addEventListener('submit', function() {
                nomeInput.value = nomeInput.value.toUpperCase();
            });
        }
    }
    
    // Verificar a exibição do botão de cadastro no modal de usuário não encontrado
    const btnBuscarUsuario = document.getElementById('btn_buscar_usuario');
    if (btnBuscarUsuario) {
        const originalBtnClick = btnBuscarUsuario.onclick;
        btnBuscarUsuario.onclick = function(event) {
            if (originalBtnClick) {
                originalBtnClick.call(this, event);
            }
            
            // Verificar se o botão já existe
            setTimeout(function() {
                const divNaoEncontrado = document.getElementById('usuario_nao_encontrado');
                if (divNaoEncontrado && divNaoEncontrado.style.display !== 'none') {
                    // Remover botão antigo se existir
                    const oldBtn = document.getElementById('btn_cadastrar_novo');
                    if (oldBtn) {
                        oldBtn.remove();
                    }
                    
                    // Obter o CPF digitado
                    const cpf = document.getElementById('cpf_busca').value.trim();
                    
                    // Adicionar botão para cadastrar novo usuário
                    const btnCadastrarUsuario = document.createElement('button');
                    btnCadastrarUsuario.id = 'btn_cadastrar_novo';
                    btnCadastrarUsuario.className = 'inline-flex items-center mt-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500';
                    btnCadastrarUsuario.innerHTML = '<i class="fas fa-user-plus mr-2"></i>Cadastrar Novo Usuário';
                    btnCadastrarUsuario.onclick = function() {
                        // Fechar o modal atual
                        const modalVincularUsuario = bootstrap.Modal.getInstance(document.getElementById('modalVincularUsuario'));
                        modalVincularUsuario.hide();
                        
                        // Preparar e abrir o modal de cadastro
                        document.getElementById('cpf_cadastro').value = cpf;
                        const modalCadastrarUsuario = new bootstrap.Modal(document.getElementById('modalCadastrarUsuario'));
                        modalCadastrarUsuario.show();
                    };
                    
                    // Adicionar o botão ao final do div de usuário não encontrado
                    divNaoEncontrado.appendChild(btnCadastrarUsuario);
                }
            }, 300); // Pequeno delay para garantir que o div está visível
        };
    }
});
