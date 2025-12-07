<?php
// Inicia a sessão e verifica login
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once '../../conf/database.php';
if (!isset($_SESSION['user'])) {
  header("Location: ../../login.php");
  exit();
}
$user_id = $_SESSION['user']['id'];

// ===== Carrega usuários internos (Visa) =====
$sql_interno = "
    SELECT 
      u.id,
      u.nome_completo,
      CASE
         WHEN EXISTS (
             SELECT 1
             FROM usuarios_online
             WHERE usuario_id = u.id
               AND last_activity > (NOW() - INTERVAL 5 MINUTE)
         ) THEN 1
         ELSE 0
      END AS online,
      (SELECT COUNT(*) FROM mensagens
        WHERE destinatario_id = ? AND remetente_id = u.id AND visualizada = 0
      ) AS mensagens_nao_lidas
    FROM usuarios u
    WHERE u.id != ?
    ORDER BY mensagens_nao_lidas DESC, online DESC
";
$stmt_interno = $conn->prepare($sql_interno);
$stmt_interno->bind_param("ii", $user_id, $user_id);
$stmt_interno->execute();
$usuarios_interno = $stmt_interno->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_interno->close();

// ===== Carrega usuários externos (Empresas) - COMENTADO =====
/*
$sql_externo = "
    SELECT 
      ue.id,
      ue.nome_completo,
      'empresa' AS tipo,
      0 AS online,
      (SELECT COUNT(*) 
         FROM mensagens 
         WHERE destinatario_id = ? AND remetente_id = ue.id AND visualizada = 0
      ) AS mensagens_nao_lidas,
      (SELECT IFNULL(MAX(data_envio), '0000-00-00 00:00:00')
         FROM mensagens
         WHERE (remetente_id = ue.id AND destinatario_id = ?)
            OR (remetente_id = ? AND destinatario_id = ue.id)
      ) AS ultima_mensagem
    FROM usuarios_externos ue
    ORDER BY mensagens_nao_lidas DESC, ultima_mensagem DESC
";
$stmt_externo = $conn->prepare($sql_externo);
$stmt_externo->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_externo->execute();
$usuarios_externo = $stmt_externo->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_externo->close();
*/
// Definindo array vazio para usuários externos
$usuarios_externo = [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Chat - Vigilância Sanitária</title>
  <style>
    :root {
      --primary-color: #4361ee;
      --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
      --online-color: #38b000;
      --offline-color: #6c757d;
      --unread-badge: #f72585;
      --message-sent: #e9f5ff;
      --message-received: #ffffff;
      --hover-bg: #f8f9fa;
      --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', system-ui, sans-serif;
      background-color: #f8fafc;
    }

    .d-none {
      display: none !important;
    }

    /* Container Principal */
    #chat-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 350px;
      z-index: 9999;
    }

    .list-group-item {
      transition: all 0.3s ease;
    }

    .chat-box {
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
      margin-bottom: 12px;
      border: 1px solid rgba(230, 230, 250, 0.5);
      transition: all 0.3s ease;
      transform-origin: bottom right;
    }
    
    .chat-box:hover {
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .chat-header {
      background: var(--primary-gradient);
      color: white;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      position: relative;
      overflow: hidden;
    }
    
    .chat-header::before {
      content: '';
      position: absolute;
      top: -10px;
      left: -10px;
      width: 40px;
      height: 40px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      z-index: 0;
    }
    
    .chat-header::after {
      content: '';
      position: absolute;
      bottom: -20px;
      right: -20px;
      width: 60px;
      height: 60px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 50%;
      z-index: 0;
    }

    .chat-header h6 {
      margin: 0;
      font-weight: 600;
      font-size: 1.1rem;
      position: relative;
      z-index: 1;
    }

    /* Área de listagem: abas e contatos */
    .user-list-body {
      height: 360px;
      overflow-y: auto;
      transition: height 0.5s cubic-bezier(0.19, 1, 0.22, 1);
      scrollbar-width: thin;
      scrollbar-color: rgba(0, 0, 0, 0.1) transparent;
    }
    
    .user-list-body::-webkit-scrollbar {
      width: 6px;
    }
    
    .user-list-body::-webkit-scrollbar-track {
      background: transparent;
    }
    
    .user-list-body::-webkit-scrollbar-thumb {
      background-color: rgba(0, 0, 0, 0.1);
      border-radius: 6px;
    }

    .user-list-body.collapsed {
      height: 0;
      overflow: hidden;
      padding: 0;
    }

    .tabs {
      display: flex;
      background: var(--primary-gradient);
    }

    .tab {
      flex: 1;
      padding: 12px 10px;
      text-align: center;
      cursor: pointer;
      border: none;
      background: transparent;
      color: white;
      font-weight: 500;
      position: relative;
      transition: all 0.3s;
      overflow: hidden;
      z-index: 1;
    }
    
    .tab::before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 3px;
      background-color: white;
      transition: width 0.3s ease;
      z-index: -1;
    }
    
    .tab:hover::before {
      width: 70%;
    }
    
    .tab.active::before {
      width: 85%;
    }

    .tab.active {
      font-weight: bold;
    }

    /* Badge para notificações nas abas */
    .tab .badge {
      background: var(--unread-badge);
      color: white;
      border-radius: 12px;
      padding: 2px 6px;
      font-size: 10px;
      position: absolute;
      top: 5px;
      right: 10px;
      display: none;
      animation: pulse-scale 1.5s infinite;
    }
    
    @keyframes pulse-scale {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.2);
      }
    }

    /* Lista de contatos */
    .list-group-item {
      display: flex;
      align-items: center;
      padding: 16px;
      border: none;
      border-bottom: 1px solid #f0f0f0;
      transition: all 0.3s ease;
      background: none;
      position: relative;
      overflow: hidden;
    }
    
    .list-group-item::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      height: 100%;
      width: 0;
      background: linear-gradient(90deg, rgba(67, 97, 238, 0.08), transparent);
      transition: width 0.3s ease;
    }

    .list-group-item:hover {
      background: var(--hover-bg);
      transform: translateX(4px);
    }
    
    .list-group-item:hover::before {
      width: 100%;
    }

    .user-name {
      font-weight: 500;
      color: #333;
      font-size: 14px;
      transition: color 0.3s;
    }
    
    .btn-link:hover .user-name {
      color: var(--primary-color);
    }

    .badge {
      font-weight: 500;
      padding: 4px 8px;
      border-radius: 12px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      transform: scale(1);
      transition: transform 0.3s;
    }
    
    .badge.bg-danger {
      background: var(--unread-badge) !important;
      animation: pulse-scale 1.5s infinite;
    }

    .status-indicator {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 12px;
      position: relative;
      display: inline-block;
    }

    .status-indicator.bg-success {
      background-color: var(--online-color);
      box-shadow: 0 0 0 2px rgba(56, 176, 0, 0.2);
    }

    .status-indicator.bg-success::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      background: var(--online-color);
      border-radius: 50%;
      animation: pulse 1.5s infinite;
      opacity: 0.8;
    }

    @keyframes pulse {
      0% {
        transform: scale(1);
        opacity: 0.8;
      }
      50% {
        transform: scale(1.5);
        opacity: 1;
      }
      100% {
        transform: scale(1);
        opacity: 0.8;
      }
    }

    .status-indicator.bg-secondary {
      background-color: var(--offline-color);
      box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.2);
    }

    .p-2 {
      padding: 10px;
    }

    #user-search {
      border: none;
      background: #f8f9fa;
      border-radius: 12px;
      padding: 12px;
      width: 100%;
      font-size: 14px;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }

    #user-search:focus {
      outline: none;
      box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.08), 0 0 0 3px rgba(67, 97, 238, 0.1);
      background: white;
    }

    /* Chat: histórico e envio */
    .chat-history {
      height: 300px;
      padding: 16px;
      overflow-y: auto;
      background: #f8f9fa;
      background-image: linear-gradient(rgba(230, 230, 250, 0.2) 1px, transparent 1px), 
                        linear-gradient(90deg, rgba(230, 230, 250, 0.2) 1px, transparent 1px);
      background-size: 20px 20px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      scroll-behavior: smooth;
      scrollbar-width: thin;
      scrollbar-color: rgba(0, 0, 0, 0.1) transparent;
    }
    
    .chat-history::-webkit-scrollbar {
      width: 6px;
    }
    
    .chat-history::-webkit-scrollbar-track {
      background: transparent;
    }
    
    .chat-history::-webkit-scrollbar-thumb {
      background-color: rgba(0, 0, 0, 0.1);
      border-radius: 6px;
    }

    .message-item {
      max-width: 80%;
      padding: 12px 16px;
      border-radius: 18px;
      margin-bottom: 10px;
      display: flex;
      flex-direction: column;
      position: relative;
      transition: transform 0.2s ease;
    }
    
    .message-item:hover {
      transform: translateY(-2px);
    }

    .message-item.text-end {
      align-self: flex-end;
      background: var(--message-sent);
      color: #2c3e50;
      border: 1px solid rgba(67, 97, 238, 0.1);
      box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
      border-bottom-right-radius: 4px;
    }
    
    .message-item.text-end::after {
      content: '';
      position: absolute;
      bottom: 0;
      right: -5px;
      width: 15px;
      height: 15px;
      background: var(--message-sent);
      border-right: 1px solid rgba(67, 97, 238, 0.1);
      border-bottom: 1px solid rgba(67, 97, 238, 0.1);
      transform: rotate(-45deg);
      border-radius: 0 0 4px 0;
    }

    .message-item.text-start {
      align-self: flex-start;
      background: var(--message-received);
      color: #2c3e50;
      border: 1px solid rgba(0, 0, 0, 0.05);
      box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
      border-bottom-left-radius: 4px;
    }
    
    .message-item.text-start::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: -5px;
      width: 15px;
      height: 15px;
      background: var(--message-received);
      border-left: 1px solid rgba(0, 0, 0, 0.05);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      transform: rotate(45deg);
      border-radius: 0 0 0 4px;
    }

    .message-text {
      word-wrap: break-word;
      white-space: pre-wrap;
      line-height: 1.5;
    }

    .message-time {
      font-size: 10px;
      color: #6c757d;
      margin-top: 4px;
      align-self: flex-end;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .status-icon {
      color: rgba(67, 97, 238, 0.7);
      font-size: 10px;
    }

    .chat-message {
      padding: 16px;
      border-top: 1px solid #eee;
      background: #fff;
      position: relative;
    }

    .message-input-container {
      position: relative;
      display: flex;
      align-items: center;
    }

    #mensagem {
      border: none;
      background: #f8f9fa;
      border-radius: 16px;
      padding: 14px 16px;
      resize: none;
      flex: 1;
      font-size: 14px;
      padding-right: 45px;
      width: 100%;
      min-height: 45px;
      line-height: 1.4;
      transition: all 0.3s;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    #mensagem:focus {
      outline: none;
      box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.08), 0 0 0 3px rgba(67, 97, 238, 0.1);
      background: white;
    }

    .send-icon {
      position: absolute;
      right: 12px;
      bottom: 50%;
      transform: translateY(50%);
      background: var(--primary-gradient);
      border: none;
      border-radius: 50%;
      width: 34px;
      height: 34px;
      cursor: pointer;
      color: white;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 3px 8px rgba(67, 97, 238, 0.3);
    }

    .send-icon:hover {
      transform: translateY(50%) scale(1.1);
      box-shadow: 0 5px 12px rgba(67, 97, 238, 0.4);
    }

    .send-icon svg {
      width: 18px;
      height: 18px;
      pointer-events: none;
      transform: rotate(45deg);
    }
    
    .btn-link {
      transition: all 0.3s;
      position: relative;
      display: inline-flex;
      align-items: center;
    }
    
    .btn-link:hover {
      text-decoration: none;
      opacity: 0.9;
    }
    
    #back-to-users {
      display: flex;
      align-items: center;
      padding: 6px 10px;
      border-radius: 12px;
      background-color: rgba(255, 255, 255, 0.1);
      transition: all 0.3s;
    }
    
    #back-to-users:hover {
      background-color: rgba(255, 255, 255, 0.2);
      text-decoration: none;
    }
    
    #toggle-user-list {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background-color: rgba(255, 255, 255, 0.1);
      border: none;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s;
      z-index: 2;
    }
    
    #toggle-user-list:hover {
      background-color: rgba(255, 255, 255, 0.2);
      transform: scale(1.1);
    }

    /* Notificação flutuante */
    #floating-notification {
      opacity: 0;
      transform: translateY(-20px);
      pointer-events: none;
      position: fixed;
      top: 65px;
      right: 20px;
      background: linear-gradient(135deg, #7b2cbf, #9d4edd);
      color: white;
      padding: 16px;
      border-radius: 12px;
      z-index: 9999;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      font-size: 0.9rem;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      width: 300px;
      border-left: 4px solid #5a189a;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    #floating-notification.show {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
      animation: pulse-border 2s infinite;
    }

    @keyframes pulse-border {
      0%, 100% {
        border-left-color: #5a189a;
      }
      50% {
        border-left-color: #c77dff;
      }
    }

    .notification-content {
      display: flex;
      align-items: center;
    }

    .notification-icon {
      background: rgba(255, 255, 255, 0.2);
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 12px;
      position: relative;
      flex-shrink: 0;
    }

    .notification-icon::after {
      content: '';
      position: absolute;
      width: 10px;
      height: 10px;
      background: #38b000;
      border-radius: 50%;
      top: 0;
      right: 0;
      border: 2px solid white;
      animation: pulse 1.5s infinite;
    }

    .notification-text {
      flex-grow: 1;
    }

    .notification-title {
      font-weight: 600;
      margin-bottom: 4px;
      font-size: 15px;
    }

    .notification-message {
      opacity: 0.9;
      font-size: 13px;
    }

    .notification-close {
      position: absolute;
      top: 8px;
      right: 8px;
      background: none;
      border: none;
      color: rgba(255, 255, 255, 0.7);
      cursor: pointer;
      font-size: 16px;
      transition: color 0.2s;
    }

    .notification-close:hover {
      color: white;
    }

    .highlight-user {
      background: rgba(123, 44, 191, 0.1) !important;
      box-shadow: 0 0 0 1px #9d4edd;
      animation: highlight-pulse 1.5s ease-in-out;
    }
    
    @keyframes highlight-pulse {
      0%, 100% {
        background: rgba(123, 44, 191, 0.1) !important;
      }
      50% {
        background: rgba(123, 44, 191, 0.2) !important;
      }
    }
  </style>
</head>

<body>
  <!-- Notificação flutuante para novas mensagens (permanece até ser clicada) -->
  <div id="floating-notification">
    <button class="notification-close" onclick="document.getElementById('floating-notification').classList.remove('show')">×</button>
    <div class="notification-content">
      <div class="notification-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zm.995-14.901a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901z"/>
        </svg>
      </div>
      <div class="notification-text">
        <div class="notification-title">Nova mensagem recebida!</div>
        <div class="notification-message" id="notification-message">Você recebeu uma nova mensagem no chat.</div>
      </div>
    </div>
  </div>

  <div id="chat-container">
    <input type="hidden" id="logged-user-id" value="<?php echo $user_id; ?>">

    <!-- LISTA DE USUÁRIOS (Com Abas) -->
    <div id="user-list" class="chat-box">
      <div class="chat-header d-flex justify-content-between align-items-center">
        <h6 style="margin:0;">Chat Visa</h6>
        <button id="toggle-user-list" class="btn btn-sm text-white" type="button">
          <span class="arrow-icon">▲</span>
        </button>
      </div>
      <div id="user-list-body" class="user-list-body collapsed">
        <!-- Abas -->
        <div class="tabs">
          <button id="tab-interno" class="tab active">
            Visa <span id="badge-interno" class="badge"></span>
          </button>
          <!-- Aba Externos comentada -->
          <!--
          <button id="tab-externo" class="tab">
            Externos <span id="badge-externo" class="badge"></span>
          </button>
          -->
        </div>
        <!-- Campo de pesquisa -->
        <div class="p-2">
          <input type="text" id="user-search" class="form-control form-control-sm" placeholder="Procurar usuário...">
        </div>
        <!-- Lista de usuários internos -->
        <ul class="list-group mb-3" id="user-list-container-interno">
          <?php foreach ($usuarios_interno as $u): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <?php $stClass = ($u['online'] == 1) ? 'bg-success' : 'bg-secondary'; ?>
                <span class="status-indicator <?php echo $stClass; ?>" data-user-id="<?php echo $u['id']; ?>"></span>
                <button class="btn btn-link p-0 user-btn" data-user-id="<?php echo $u['id']; ?>">
                  <span class="user-name"><?php echo htmlspecialchars($u['nome_completo']); ?></span>
                </button>
              </div>
              <?php if ($u['mensagens_nao_lidas'] > 0): ?>
                <span class="badge bg-danger"><?php echo $u['mensagens_nao_lidas']; ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <!-- Lista de usuários externos (COMENTADA) -->
        <!--
        <ul class="list-group mb-3 d-none" id="user-list-container-externo">
          <?php foreach ($usuarios_externo as $u): ?>
            <?php $stClass = 'bg-secondary'; ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <span class="status-indicator <?php echo $stClass; ?>" data-user-id="<?php echo $u['id']; ?>"></span>
                <button class="btn btn-link p-0 user-btn" data-user-id="<?php echo $u['id']; ?>">
                  <span class="user-name"><?php echo htmlspecialchars($u['nome_completo']); ?> <small>(Empresa)</small></span>
                </button>
              </div>
              <?php if ($u['mensagens_nao_lidas'] > 0): ?>
                <span class="badge bg-danger"><?php echo $u['mensagens_nao_lidas']; ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        -->
      </div>
    </div>

    <!-- CAIXA DE CHAT -->
    <div id="chat-box" class="chat-box d-none">
      <div class="chat-header d-flex justify-content-between align-items-center">
        <button class="btn btn-link btn-sm" id="back-to-users" style="font-size:14px;color:white;">← Voltar</button>
        <h6 id="chat-with" style="margin:0;">Chat</h6>
      </div>
      <div id="messages-container" class="chat-history">
        <p>Selecione um usuário para iniciar uma conversa.</p>
      </div>
      <div class="chat-message">
        <form id="chat-form">
          <input type="hidden" name="destinatario_id" id="destinatario-id">
          <div class="message-input-container">
            <textarea name="mensagem" id="mensagem" class="form-control" placeholder="Digite sua mensagem..." rows="2"></textarea>
            <button type="submit" class="send-icon">
              <svg xmlns="http://www.w3.org/2000/svg"
                width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 2L11 13" />
                <path d="M22 2l-7 20-4-9-9-4 20-7z" />
              </svg>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="/visamunicipal/views/ChatVisa/chat.js"></script>
  <script src="/visamunicipal/views/ChatVisa/atualizar_status.js"></script>
  <!-- Toggle, Abas e Pesquisa -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toggleUserListBtn = document.getElementById('toggle-user-list');
      const userListBody = document.getElementById('user-list-body');
      const arrowIcon = toggleUserListBtn.querySelector('.arrow-icon');
      const searchInput = document.getElementById('user-search');

      // Initialize based on localStorage
      const chatListState = localStorage.getItem('chatListState');
      if (chatListState === 'expanded') {
        userListBody.classList.remove('collapsed');
        arrowIcon.textContent = '▲';
      } else if (chatListState === 'collapsed') {
        userListBody.classList.add('collapsed');
        arrowIcon.textContent = '▼';
      }

      // Final check: If it's NOT collapsed, but localStorage says it SHOULD be, force collapse.
      if (!userListBody.classList.contains('collapsed') && chatListState !== 'expanded') {
        userListBody.classList.add('collapsed');
        arrowIcon.textContent = '▼';
      }

      toggleUserListBtn.addEventListener('click', () => {
        userListBody.classList.toggle('collapsed');
        const isCollapsed = userListBody.classList.contains('collapsed');
        arrowIcon.textContent = isCollapsed ? '▼' : '▲';
        localStorage.setItem('chatListState', isCollapsed ? 'collapsed' : 'expanded');
      });

      // Abas (Interno/Externo) - COMENTADO (só VISA agora)
      /*
      const tabInterno = document.getElementById('tab-interno');
      const tabExterno = document.getElementById('tab-externo');
      const internoList = document.getElementById('user-list-container-interno');
      const externoList = document.getElementById('user-list-container-externo');

      tabInterno.addEventListener('click', () => {
        tabInterno.classList.add('active');
        tabExterno.classList.remove('active');
        internoList.classList.remove('d-none');
        externoList.classList.add('d-none');
      });
      tabExterno.addEventListener('click', () => {
        tabExterno.classList.add('active');
        tabInterno.classList.remove('active');
        externoList.classList.remove('d-none');
        internoList.classList.add('d-none');
      });
      */

      // Pesquisa em tempo real (apenas usuários internos)
      searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase().trim();
        const internoList = document.getElementById('user-list-container-interno');
        const userItems = internoList.querySelectorAll('li');
        userItems.forEach(li => {
          const nameElem = li.querySelector('.user-name');
          if (nameElem) {
            const txt = nameElem.textContent.toLowerCase();
            li.classList.toggle('hidden-li', !txt.includes(filter));
          }
        });
      });
    });
  </script>

  <!-- Abertura de chat, polling e notificações -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const userBtns = document.querySelectorAll('.user-btn');
      const chatBox = document.getElementById('chat-box');
      const chatWith = document.getElementById('chat-with');
      const backToUsers = document.getElementById('back-to-users');
      const messagesContainer = document.getElementById('messages-container');
      const chatForm = document.getElementById('chat-form');
      const destinatarioIdInput = document.getElementById('destinatario-id');
      const mensagemInput = document.getElementById('mensagem');
      let chatPollingInterval = null;
      let destinatarioId = null;
      const loggedUserId = parseInt(document.getElementById('logged-user-id').value);

      function carregarMensagens() {
        if (!destinatarioId) return;
        fetch(`/visamunicipal/views/ChatVisa/carregar_mensagens.php?destinatario_id=${destinatarioId}`)
          .then(r => r.json())
          .then(msgs => {
            messagesContainer.innerHTML = '';
            msgs.reverse().forEach(m => {
              const isMy = (m.remetente_id == loggedUserId);
              const align = isMy ? 'text-end' : 'text-start';
              let statusIcon = '';
              if (isMy) {
                statusIcon = m.status_visualizacao ?
                  '<span class="status-icon">✔✔</span>' :
                  '<span class="status-icon">✔</span>';
              }
              const dataEnvio = new Date(m.data_envio).toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              });
              messagesContainer.insertAdjacentHTML('afterbegin', `
          <div class="message-item ${align}">
            <div class="message-text">${m.mensagem}</div>
            <small class="message-time">${dataEnvio} ${statusIcon}</small>
          </div>
        `);
            });

            // Força o scroll para o final do contêiner de mensagens
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
          })
          .catch(err => console.error('Erro carregar mensagens:', err));
      }

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
              carregarUsuarios(); // Atualiza a lista de usuários após o envio
            } else {
              console.error('Erro ao enviar mensagem:', data.error);
            }
          })
          .catch(error => console.error('Erro ao processar envio:', error));
      }


      userBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          destinatarioId = btn.getAttribute('data-user-id');
          const userName = btn.querySelector('.user-name').textContent.trim();
          destinatarioIdInput.value = destinatarioId;
          chatWith.textContent = "Chat com " + userName;
          document.getElementById('user-list').style.display = 'none';
          chatBox.classList.remove('d-none');
          carregarMensagens();
          if (chatPollingInterval) clearInterval(chatPollingInterval);
          chatPollingInterval = setInterval(carregarMensagens, 3000);
          // Força o scroll para o final do contêiner de mensagens
          messagesContainer.scrollTop = messagesContainer.scrollHeight;
          // Ao abrir o chat, ocultamos a notificação flutuante
          document.getElementById('floating-notification').classList.remove('show');
        });
      });

      backToUsers.addEventListener('click', () => {
        chatWith.textContent = '';
        chatBox.classList.add('d-none');
        document.getElementById('user-list').style.display = 'block';
        if (chatPollingInterval) clearInterval(chatPollingInterval);
      });

      chatForm.addEventListener('submit', e => {
        e.preventDefault();
        enviarMensagem();
      });
      mensagemInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          enviarMensagem();
        }
      });
    });
  </script>

  <!-- Atualização do status online (bola verde/indicador) -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      function atualizarStatusOnline() {
        fetch('/visamunicipal/views/ChatVisa/listar_status_online.php')
          .then(r => r.json())
          .then(json => {
            if (!json || !json.data) return;
            json.data.forEach(u => {
              const userId = u.id;
              // Comparação frouxa para aceitar "1" ou 1
              const isOnline = (u.online == 1);
              const span = document.querySelector(`.status-indicator[data-user-id="${userId}"]`);
              if (span) {
                span.classList.remove('bg-success', 'bg-secondary');
                span.classList.add(isOnline ? 'bg-success' : 'bg-secondary');
              }
            });
          })
          .catch(err => console.error('Erro ao atualizar status online:', err));
      }
      atualizarStatusOnline();
      setInterval(atualizarStatusOnline, 10000);
    });
  </script>

  <!-- Notificações: novas mensagens e badges nas abas; a notificação flutuante permanece até o usuário clicar -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // IDs dos usuários internos (obtidos via PHP)
      const internos = <?php echo json_encode(array_column($usuarios_interno, 'id')); ?>;
      // const externos = <?php echo json_encode(array_column($usuarios_externo, 'id')); ?>; // COMENTADO
      // Elementos dos badges nas abas
      const badgeInterno = document.getElementById('badge-interno');
      // const badgeExterno = document.getElementById('badge-externo'); // COMENTADO
      // Elemento da notificação flutuante
      const floatingNotification = document.getElementById('floating-notification');
      const notificationMessage = document.getElementById('notification-message');
      // Variável para armazenar notificações anteriores
      let notificacoesAnteriores = {};
      // Mapa para nomes de usuários
      const usuariosMap = new Map();
      
      // Preencher o mapa de usuários com IDs e nomes (apenas internos)
      <?php foreach ($usuarios_interno as $u): ?>
        usuariosMap.set(<?php echo $u['id']; ?>, "<?php echo htmlspecialchars($u['nome_completo']); ?>");
      <?php endforeach; ?>
      // Usuários externos comentados
      /*
      <?php foreach ($usuarios_externo as $u): ?>
        usuariosMap.set(<?php echo $u['id']; ?>, "<?php echo htmlspecialchars($u['nome_completo']); ?>");
      <?php endforeach; ?>
      */

      function fetchNotificacoes() {
        fetch('/visamunicipal/views/ChatVisa/notificacoes_mensagem.php')
          .then(r => r.json())
          .then(data => {
            let totalInterno = 0;
            // let totalExterno = 0; // COMENTADO
            let novaMensagem = null;
            data.forEach(notif => {
              const id = notif.id;
              const qtd = parseInt(notif.mensagens_nao_lidas);
              // Se houver aumento (nova mensagem) e ainda não estiver com chat aberto
              if (qtd > 0 && (!notificacoesAnteriores[id] || qtd > notificacoesAnteriores[id])) {
                novaMensagem = id;
              }
              if (internos.indexOf(parseInt(id)) !== -1) {
                totalInterno += qtd;
              }
              // Removido: else if (externos.indexOf(parseInt(id)) !== -1) { totalExterno += qtd; }
              notificacoesAnteriores[id] = qtd;
            });
            // Atualiza os badges nas abas (apenas interno)
            if (badgeInterno) {
              if (totalInterno > 0) {
                badgeInterno.textContent = totalInterno;
                badgeInterno.style.display = 'inline-block';
              } else {
                badgeInterno.style.display = 'none';
              }
            }
            // Badge externo comentado
            /*
            if (badgeExterno) {
              if (totalExterno > 0) {
                badgeExterno.textContent = totalExterno;
                badgeExterno.style.display = 'inline-block';
              } else {
                badgeExterno.style.display = 'none';
              }
            }
            */
            
            // Exibe notificação flutuante se houver nova mensagem
            if (novaMensagem !== null) {
              const userName = usuariosMap.get(parseInt(novaMensagem)) || "Usuário";
              notificationMessage.textContent = `${userName} enviou uma nova mensagem.`;
              floatingNotification.classList.add('show');
              
              // Reproduz som de notificação
              const audio = new Audio('/visamunicipal/views/ChatVisa/notification.mp3');
              audio.volume = 0.5;
              audio.play().catch(e => console.log('Reprodução de áudio bloqueada pelo navegador'));
              
              // Destaca visualmente o usuário que enviou a mensagem na lista
              const userButtons = document.querySelectorAll(`.user-btn[data-user-id="${novaMensagem}"]`);
              userButtons.forEach(btn => {
                btn.parentElement.parentElement.classList.add('highlight-user');
                setTimeout(() => {
                  btn.parentElement.parentElement.classList.remove('highlight-user');
                }, 3000);
              });
            }
          })
          .catch(err => console.error('Erro ao buscar notificações:', err));
      }

      // Inicia o polling
      fetchNotificacoes();
      setInterval(fetchNotificacoes, 5000);

      // Fecha a notificação ao clicar nela também (além do botão fechar)
      floatingNotification.addEventListener('click', function(e) {
        if (e.target.classList.contains('notification-close')) return;
        this.classList.remove('show');
      });
    });
  </script>
</body>

</html>