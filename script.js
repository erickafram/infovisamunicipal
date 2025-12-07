// função para verificar quais usuários estão online
function getUsuariosOnline() {
  fetch('../../usuarios_online.php')
    .then(response => response.json())
    .then(data => {
      const usuariosOnline = document.getElementById('usuarios-online');
      usuariosOnline.innerHTML = '';
      data.forEach(usuario => {
        const elemento = document.createElement('div');
        elemento.textContent = usuario.nome_completo;
        usuariosOnline.appendChild(elemento);
      });
    });
}

// função para enviar mensagem
function enviarMensagem() {
  const mensagem = document.getElementById('mensagem').value;
  const destinatario = document.getElementById('destinatario').value;
  fetch('../../enviar_mensagem.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ mensagem, destinatario })
  })
    .then(response => response.json())
    .then(data => {
      console.log(data);
    });
}

// chamar a função para verificar quais usuários estão online
getUsuariosOnline();
