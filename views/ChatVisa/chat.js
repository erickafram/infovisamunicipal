document.addEventListener('DOMContentLoaded', () => {
    // Elementos do DOM
    const chatBox              = document.getElementById('chat-box');
    const userList             = document.getElementById('user-list');
    const chatWith             = document.getElementById('chat-with');
    const backToUsers          = document.getElementById('back-to-users');
    const messagesContainer    = document.getElementById('messages-container');
    const chatForm             = document.getElementById('chat-form');
    const destinatarioIdInput  = document.getElementById('destinatario-id');
    const userListContainer    = document.getElementById('user-list-container');
    const mensagemInput        = document.getElementById('mensagem');
    const userIdToFirstName = {};
    

    // NOVO: elemento do alerta flutuante
    const floatingNotification = document.getElementById('floating-notification');

    const loggedUserId         = parseInt(document.getElementById('logged-user-id').value);
    let destinatarioId         = null;
    let chatPollingInterval    = null;

    // Variável para armazenar quantas mensagens não lidas foram detectadas
    let lastTotalNotificacoes  = 0;

    // ------------------- FUNÇÕES -------------------

    // Carrega lista de usuários (e suas msgs não lidas)
    function carregarUsuarios() {
        fetch('/visamunicipal/views/ChatVisa/buscar_usuarios.php')
            .then(response => response.json())
            .then(usuarios => {
                if (!userListContainer) return;
                userListContainer.innerHTML = '';
    
                // Ordenar quem tem mais msgs não lidas primeiro
                usuarios.sort((a, b) => {
                    if (b.mensagens_nao_lidas !== a.mensagens_nao_lidas) {
                        return b.mensagens_nao_lidas - a.mensagens_nao_lidas;
                    }
                    // Depois por última mensagem
                    return new Date(b.ultima_mensagem) - new Date(a.ultima_mensagem);
                });
    
                // Renderiza a lista e popula o mapa
                // Renderiza a lista e popula o mapa
usuarios.forEach(usuario => {
    const onlineClass = usuario.online ? 'bg-success' : 'bg-secondary';
    const mensagemBadge = usuario.mensagens_nao_lidas > 0
        ? `<span class="badge bg-danger">${usuario.mensagens_nao_lidas}</span>`
        : '';

    // Extrai e armazena o primeiro nome
    const firstName = usuario.nome_completo.split(' ')[0];
    userIdToFirstName[usuario.id] = firstName;

    const userItem = `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <span class="status-indicator ${onlineClass}" data-user-id="${usuario.id}"></span>
                <button class="btn btn-link p-0 user-btn" data-user-id="${usuario.id}">
                    <span class="user-name">${firstName}</span>
                </button>
            </div>
            ${mensagemBadge}
        </li>
    `;
    userListContainer.insertAdjacentHTML('beforeend', userItem);
});

ativarEventoUsuarios();
})
.catch(error => console.error('Erro ao carregar usuários:', error));

    }
    

    // Carrega as mensagens de um destinatário
    function carregarMensagens() {
        if (!destinatarioId || !messagesContainer) return;

        fetch(`/visamunicipal/views/ChatVisa/carregar_mensagens.php?destinatario_id=${destinatarioId}`)
            .then(response => response.json())
            .then(mensagens => {
                messagesContainer.innerHTML = '';

                mensagens.forEach(mensagem => {
                    const isMyMessage = (mensagem.remetente_id === loggedUserId);
                    const alignClass  = isMyMessage ? 'text-end' : 'text-start';

                    let statusIcon = '';
                    if (isMyMessage) {
                        statusIcon = mensagem.status_visualizacao
                            ? '<span class="status-icon">✔✔</span>'
                            : '<span class="status-icon">✔</span>';
                    }

                    const messageItem = `
                        <div class="message-item ${alignClass}">
                            <div class="message-text">${mensagem.mensagem}</div>
                            <small class="message-time">
                                ${new Date(mensagem.data_envio).toLocaleString('pt-BR', {
                                    day: '2-digit', month: '2-digit', year: 'numeric',
                                    hour: '2-digit', minute: '2-digit'
                                })} ${statusIcon}
                            </small>
                        </div>
                    `;
                    messagesContainer.insertAdjacentHTML('beforeend', messageItem);
                });

                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                // Após carregar msgs, podemos recarregar lista para atualizar contadores
                carregarUsuarios();
            })
            .catch(error => console.error('Erro ao carregar mensagens:', error));
    }

    // Envia mensagem
    function enviarMensagem() {
        const mensagem = mensagemInput.value.trim();
        if (!mensagem || !destinatarioId) return;

        const formData = new FormData();
        formData.append('destinatario_id', destinatarioId);
        formData.append('mensagem', mensagem);

        fetch('/visamunicipal/views/ChatVisa/enviar_mensagem.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                carregarMensagens();
                mensagemInput.value = '';
                carregarUsuarios();
            } else {
                console.error('Erro ao enviar mensagem:', data.error);
            }
        })
        .catch(error => console.error('Erro ao processar envio:', error));
    }

    // Ativar eventos de clique nos usuários
    function ativarEventoUsuarios() {
        document.querySelectorAll('.user-btn').forEach(item => {
            item.addEventListener('click', () => {
                if (!chatBox || !chatWith || !userList || !destinatarioIdInput) return;

                destinatarioId = item.getAttribute('data-user-id');
                const userName = item.querySelector('.user-name').textContent.trim();
                destinatarioIdInput.value = destinatarioId;
                chatWith.textContent = `Chat com ${userName}`;

                userList.classList.add('d-none');
                chatBox.classList.remove('d-none');

                carregarMensagens();

                if (chatPollingInterval) clearInterval(chatPollingInterval);
                chatPollingInterval = setInterval(() => carregarMensagens(), 2000);
            });
        });
    }

    /**
     * Atualiza badges de notificações e exibe o alerta flutuante se houver
     * mensagens não lidas.
     */
    function atualizarNotificacoes() {
        fetch('/visamunicipal/views/ChatVisa/notificacoes_mensagens.php')
            .then(response => response.json())
            .then(notificacoes => {
                let totalNaoLidas = 0;
                let novoUsuarioId = null;
    
                // Atualiza badges e identifica o primeiro usuário com mensagens não lidas
                notificacoes.forEach(notificacao => {
                    totalNaoLidas += notificacao.mensagens_nao_lidas; // Soma todas
    
                    const userItem = document.querySelector(`.user-btn[data-user-id="${notificacao.id}"]`);
                    if (userItem) {
                        const parent = userItem.closest('.list-group-item');
                        let badge = parent.querySelector('.badge');
    
                        if (notificacao.mensagens_nao_lidas > 0) {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'badge bg-danger';
                                parent.appendChild(badge);
                            }
                            badge.textContent = notificacao.mensagens_nao_lidas;
                            
                            // Se ainda não identificamos um novo usuário, atribua
                            if (!novoUsuarioId) {
                                novoUsuarioId = notificacao.id;
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    }
                });
    
                // Se houve aumento nas mensagens não lidas
                if (totalNaoLidas > lastTotalNotificacoes && novoUsuarioId) {
                    const firstName = userIdToFirstName[novoUsuarioId] || 'Usuário';
                    mostrarAlertaFlutuante(firstName);
                    animarShake(chatContainer); // Opcional: Sacude o chat-container
                }
    
                // Atualiza a variável para a próxima comparação
                lastTotalNotificacoes = totalNaoLidas;
            })
            .catch(error => console.error('Erro ao atualizar notificações:', error));
    }
    

// Mostra o alerta flutuante e o oculta após 5 segundos
function mostrarAlertaFlutuante(firstName) {
    if (!floatingNotification) return;
    floatingNotification.textContent = `Nova mensagem recebida no chat de ${firstName}.`;
    floatingNotification.classList.add('show');

    // Oculta a notificação após 5 segundos (5000 ms)
    setTimeout(() => {
        ocultarAlertaFlutuante();
    }, 5000);
}

// Oculta o alerta flutuante
function ocultarAlertaFlutuante() {
    if (!floatingNotification) return;
    floatingNotification.classList.remove('show');
}



    // ----------------- EVENTOS DOM -----------------

    // Submit do form de chat
    if (chatForm) {
        chatForm.addEventListener('submit', event => {
            event.preventDefault();
            enviarMensagem();
        });
    }

    // ENTER no textarea sem shift
    if (mensagemInput) {
        mensagemInput.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                enviarMensagem();
            }
        });
    }

    // Botão "Voltar"
    if (backToUsers) {
        backToUsers.addEventListener('click', () => {
            if (!userList || !chatBox || !chatWith) return;

            chatWith.textContent = '';
            chatBox.classList.add('d-none');
            userList.classList.remove('d-none');

            if (chatPollingInterval) clearInterval(chatPollingInterval);
        });
    }

    // ----------------- INICIALIZAÇÃO -----------------
    carregarUsuarios();
    atualizarNotificacoes();

    // A cada 5s, recarrega lista e notificações
    setInterval(() => {
        carregarUsuarios();
        atualizarNotificacoes();
    }, 5000);
});


// NOVAS FUNÇÕES PARA O CHAT DA EMPRESA (LIMITES DE PALAVRAS)

// Verifica se este é o chat da empresa
const chatTypeElement = document.getElementById('chat-type');
const isEmpresaChat = chatTypeElement && chatTypeElement.value === 'empresa';

// Função para contar as palavras de um texto
function countWords(text) {
    if (!text.trim()) return 0;
    return text.trim().split(/\s+/).length;
}

// Função para checar o limite diário de 500 palavras usando localStorage
function checkDailyLimit(newMessageWordCount) {
    const today = new Date().toISOString().slice(0,10); // "YYYY-MM-DD"
    const storageKey = "empresaDailyWordCount_" + loggedUserId + "_" + today;
    const currentCount = parseInt(localStorage.getItem(storageKey)) || 0;
    return (currentCount + newMessageWordCount <= 500);
}

// Função para atualizar a contagem diária após o envio
function updateDailyCount(newMessageWordCount) {
    const today = new Date().toISOString().slice(0,10);
    const storageKey = "empresaDailyWordCount_" + loggedUserId + "_" + today;
    const currentCount = parseInt(localStorage.getItem(storageKey)) || 0;
    localStorage.setItem(storageKey, currentCount + newMessageWordCount);
}

// Função para exibir mensagem de erro abaixo do campo de mensagem
function displayMessageError(msg) {
    let errorElem = document.getElementById('message-error');
    if (!errorElem) {
        errorElem = document.createElement('div');
        errorElem.id = 'message-error';
        errorElem.style.color = 'red';
        errorElem.style.marginTop = '5px';
        mensagemInput.parentNode.appendChild(errorElem);
    }
    errorElem.textContent = msg;
}

// Função para limpar a mensagem de erro
function clearMessageError() {
    let errorElem = document.getElementById('message-error');
    if (errorElem) {
        errorElem.textContent = '';
    }
}

// Se for o chat da empresa, sobrepor a função de envio de mensagem
if (isEmpresaChat) {
    // Sobrescreve a função global enviarMensagem para a versão com validação
    window.enviarMensagem = function enviarMensagemEmpresa() {
        const mensagem = mensagemInput.value.trim();
        const wordCount = countWords(mensagem);
        
        // Verifica se a mensagem ultrapassa 100 palavras
        if (wordCount > 100) {
            displayMessageError("Sua mensagem não pode ultrapassar 100 palavras.");
            return;
        }
        
        // Verifica se o limite diário de 500 palavras foi atingido
        if (!checkDailyLimit(wordCount)) {
            displayMessageError("Você ultrapassou o limite diário de 500 palavras. Você não pode enviar mais mensagens hoje.");
            mensagemInput.disabled = true;
            const submitButton = document.querySelector('#chat-form button[type="submit"]');
            if (submitButton) submitButton.disabled = true;
            return;
        }
        
        // Se as validações passarem, envia a mensagem normalmente
        const formData = new FormData();
        formData.append('destinatario_id', destinatarioId);
        formData.append('mensagem', mensagem);
        
        fetch('/visamunicipal/views/ChatVisa/enviar_mensagem.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualiza a contagem diária com o número de palavras enviadas
                updateDailyCount(wordCount);
                carregarMensagens();
                mensagemInput.value = '';
                carregarUsuarios();
                clearMessageError();
            } else {
                console.error('Erro ao enviar mensagem:', data.error);
            }
        })
        .catch(error => console.error('Erro ao processar envio:', error));
    };

    // Validação em tempo real para limitar a 100 palavras enquanto o usuário digita
    mensagemInput.addEventListener('input', function() {
        const wordCount = countWords(mensagemInput.value);
        const submitButton = document.querySelector('#chat-form button[type="submit"]');
        if (wordCount > 100) {
            displayMessageError("Limite de 100 palavras atingido.");
            if (submitButton) submitButton.disabled = true;
        } else {
            clearMessageError();
            if (submitButton) submitButton.disabled = false;
        }
    });
}

