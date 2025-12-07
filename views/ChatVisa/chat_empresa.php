<?php
// Evita tentar iniciar a sessão se ela já estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../conf/database.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];


$sql_usuarios = "
SELECT * FROM (
  (SELECT 
      u.id, 
      u.nome_completo, 
      'vigilancia' AS tipo,
      CASE 
          WHEN EXISTS (
              SELECT 1
              FROM usuarios_online
              WHERE usuario_id = u.id
                AND last_activity > (NOW() - INTERVAL 5 MINUTE)
          ) THEN 1
          ELSE 0
      END AS online,
      (SELECT COUNT(*) 
       FROM mensagens 
       WHERE destinatario_id = ? AND remetente_id = u.id AND visualizada = 0
      ) AS mensagens_nao_lidas,
      (SELECT MAX(data_envio)
       FROM mensagens
       WHERE (remetente_id = u.id AND destinatario_id = ?)
          OR (remetente_id = ? AND destinatario_id = u.id)
      ) AS ultima_mensagem
   FROM usuarios u
   WHERE u.id != ?
  )
  UNION ALL
  (SELECT 
      ue.id, 
      ue.nome_completo, 
      'empresa' AS tipo,
      0 AS online,
      (SELECT COUNT(*) 
       FROM mensagens 
       WHERE destinatario_id = ? AND remetente_id = ue.id AND visualizada = 0
      ) AS mensagens_nao_lidas,
      (SELECT MAX(data_envio)
       FROM mensagens
       WHERE (remetente_id = ue.id AND destinatario_id = ?)
          OR (remetente_id = ? AND destinatario_id = ue.id)
      ) AS ultima_mensagem
   FROM usuarios_externos ue
  )
) AS union_table
ORDER BY mensagens_nao_lidas DESC, ultima_mensagem DESC, online DESC
";

// Total de parâmetros: 7 (todos usando $user_id)
$stmt_usuarios = $conn->prepare($sql_usuarios);
$stmt_usuarios->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt_usuarios->execute();
$usuarios = $stmt_usuarios->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Chat - Empresa</title>
    <style>
        /* ===================== VARIÁVEIS DE COR ===================== */
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

        /* ===================== STATUS INDICATOR ===================== */
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 12px;
            position: relative;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(56, 176, 0, 0.2);
        }

        .status-indicator.bg-success {
            background-color: var(--online-color);
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

        /* ===================== MENSAGENS ===================== */
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
            margin-right: 0;
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
            margin-left: 0;
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

        /* Garante quebra de linha e evita overflow em mensagens longas */
        .message-text {
            word-wrap: break-word;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        /* ===================== LISTA DE USUÁRIOS ===================== */
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
            transition: transform 0.3s;
        }
        
        .badge.bg-danger {
            background: var(--unread-badge) !important;
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

        /* ===================== CONTAINER DO CHAT ===================== */
        #chat-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            font-family: 'Segoe UI', system-ui, sans-serif;
            z-index: 1000;
            transition: transform 0.3s ease;
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
        }

        #user-search {
            border: none;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px 16px;
            margin: 12px;
            width: calc(100% - 24px);
            font-size: 14px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        #user-search:focus {
            outline: none;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.08), 0 0 0 3px rgba(67, 97, 238, 0.1);
            background: white;
        }

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
            padding-right: 45px !important;
            width: 100%;
            min-height: 45px;
            line-height: 1.4;
            transition: all 0.3s ease;
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
            padding: 0;
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
        
        /* Botões e interações */
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
            text-decoration: none;
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
        
        .chat-info {
            padding: 12px 16px;
            background: rgba(67, 97, 238, 0.05);
            font-size: 13px;
            text-align: center;
            color: #4361ee;
            border-bottom: 1px solid rgba(67, 97, 238, 0.1);
            position: relative;
        }
        
        .chat-info::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--primary-gradient);
        }

        .info-icon {
            display: inline-flex;
            margin-right: 5px;
            color: #4361ee;
            animation: pulse-fade 2s infinite;
        }
        
        @keyframes pulse-fade {
            0%, 100% {
                opacity: 0.7;
            }
            50% {
                opacity: 1;
            }
        }

        @media (max-width: 480px) {
            #chat-container {
                width: 100%;
                right: 0;
                bottom: 0;
            }
        }

        /* ===================== NOTIFICAÇÃO FLUTUANTE ===================== */
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
    <!-- ALERTA FLUTUANTE -->
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


    <!-- BLOQUEIO INICIAL PARA NÃO ASSINANTES 
    <div id="bloqueio-inicial" class="alert alert-warning d-none"
        style="position: fixed; bottom: 120px; right: 30px; z-index: 2000; width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <div class="d-flex align-items-center mb-2">
            <i class="bi bi-shield-lock fs-5 me-2"></i>
            <h6 class="mb-0">Acesso Restrito</h6>
            <div class="ms-auto">
                <span class="badge bg-danger fs-6 px-2 py-1" id="contador-bloqueio">5</span>
            </div>
        </div>
        <p class="small mb-2">Você está usando o modo gratuito com restrições:</p>
        <ul class="small mb-3">
            <li>Limite de 30 caracteres por mensagem</li>
            <li>Máximo de 100 caracteres diários</li>
            <li>Bloqueio entre mensagens</li>
        </ul>
        <div class="d-grid gap-2">
            <a href="/visamunicipal/views/ChatVisa/assinatura.php"
                class="btn btn-success btn-sm">
                <i class="bi bi-unlock me-2"></i>Desbloquear acesso completo
            </a>
        </div>
        <div class="mt-2 small text-muted text-center">
            <i class="bi bi-clock-history me-1"></i>aguarde <span id="contador-texto">5</span> segundos
        </div>
    </div>
    -->

    <!-- CONTAINER PRINCIPAL DO CHAT -->
    <div id="chat-container">
        <!-- Guarda o ID do usuário logado para uso no JS -->
        <input type="hidden" id="logged-user-id" value="<?php echo $_SESSION['user']['id']; ?>">
        <!-- Indica que este é o chat da empresa -->
        <input type="hidden" id="chat-type" value="empresa">

        <!-- LISTA DE USUÁRIOS -->
        <div id="user-list" class="chat-box">
            <div class="chat-header d-flex align-items-center justify-content-between">
                <div>
                    <h6>ChatVisa</h6>
                </div>
                <button id="toggle-user-list" class="btn btn-sm text-white" type="button">
                    <span class="arrow-icon">▲</span>
                </button>
            </div>
            <!-- Canal de atendimento via Chat Vigilância Sanitária -->
            <div class="chat-info">
                <span class="info-icon"><i class="fas fa-info-circle"></i></span>
                Canal de atendimento via Chat Vigilância Sanitária.
            </div>
            <div id="user-list-body" class="user-list-body">
                <ul class="list-group mb-3" id="user-list-container">
                    <?php foreach ($usuarios as $usuario): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <?php
                                // Define a classe de status: online ou offline
                                $statusClass = ($usuario['online'] == 1) ? 'bg-success' : 'bg-secondary';
                                // Exibe somente o primeiro nome, independentemente do tipo
                                $nomePartes = explode(' ', trim($usuario['nome_completo']));
                                $nomeExibido = $nomePartes[0];
                                ?>
                                <span class="status-indicator <?php echo $statusClass; ?>" data-user-id="<?php echo $usuario['id']; ?>"></span>
                                <button class="btn btn-link p-0 user-btn" data-user-id="<?php echo $usuario['id']; ?>">
                                    <span class="user-name"><?php echo htmlspecialchars($nomeExibido); ?></span>
                                </button>
                            </div>
                            <?php if ($usuario['mensagens_nao_lidas'] > 0): ?>
                                <span class="badge bg-danger">
                                    <?php echo $usuario['mensagens_nao_lidas']; ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- JANELA DE CHAT -->
        <div id="chat-box" class="chat-box d-none">
            <div class="chat-header d-flex justify-content-between align-items-center">
                <button class="btn btn-link btn-sm" id="back-to-users" style="font-size: 14px; color: white;">← Voltar</button>
                <h6 id="chat-with" style="margin: 0;">Chat</h6>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 2L11 13" />
                                <path d="M22 2l-7 20-4-9-9-4 20-7z" />
                            </svg>
                        </button>
                    </div>
                    <!-- Div para exibir a mensagem de erro (abaixo do campo de entrada) -->
                    <div id="message-error" style="color: red; margin-top: 5px;"></div>
                    <!-- Div para exibir a contagem de caracteres restantes e o limite diário -->
                    <div id="char-counter" style="font-size: 12px; color: #555; margin-top: 4px;"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Inclusão dos scripts de lógica do chat -->
    <script src="/visamunicipal/views/ChatVisa/chat.js"></script>
    <script src="/visamunicipal/views/ChatVisa/atualizar_status.js"></script>
    <script>
        // SCRIPT: Toggle da lista de usuários e pesquisa em tempo real
        document.addEventListener('DOMContentLoaded', function() {
            const toggleUserListBtn = document.getElementById('toggle-user-list');
            const userListBody = document.getElementById('user-list-body');
            const arrowIcon = toggleUserListBtn.querySelector('.arrow-icon');
            const searchInput = document.getElementById('user-search');

            if (localStorage.getItem('chatListState') === 'collapsed') {
                userListBody.classList.add('collapsed');
                arrowIcon.textContent = '▼';
            } else {
                userListBody.classList.remove('collapsed');
                arrowIcon.textContent = '▲';
            }

            toggleUserListBtn.addEventListener('click', () => {
                userListBody.classList.toggle('collapsed');
                if (userListBody.classList.contains('collapsed')) {
                    arrowIcon.textContent = '▼';
                    localStorage.setItem('chatListState', 'collapsed');
                } else {
                    arrowIcon.textContent = '▲';
                    localStorage.setItem('chatListState', 'expanded');
                }
            });

            searchInput.addEventListener('input', function() {
                const filter = this.value.toLowerCase().trim();
                const userItems = document.querySelectorAll('#user-list-container li');
                userItems.forEach(li => {
                    const userNameElem = li.querySelector('.user-name');
                    if (userNameElem) {
                        const userNameText = userNameElem.textContent.toLowerCase();
                        li.classList.toggle('hidden-li', !userNameText.includes(filter));
                    }
                });
            });
        });
    </script>
    <!-- NOVAS FUNÇÕES PARA O CHAT DA EMPRESA (LIMITES DE CARACTERES E ACUMULO DIÁRIO) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatTypeElement = document.getElementById('chat-type');
            const isEmpresaChat = chatTypeElement && chatTypeElement.value === 'empresa';
            const mensagemInput = document.getElementById('mensagem');
            const loggedUserId = parseInt(document.getElementById('logged-user-id').value);


            // FUNÇÃO COMPLETA ATUALIZADA PARA VERIFICAR USO DIÁRIO VIA BANCO DE DADOS
            async function fetchDailyUsage() {
                try {
                    const response = await fetch('/visamunicipal/views/ChatVisa/verificarAssinatura.php');
                    if (!response.ok) throw new Error('Falha na comunicação');
                    const data = await response.json();

                    // Verifica se há qualquer assinatura ativa
                    const isPremium = data.assinante;

                    return {
                        isPremium: isPremium,
                        used: data.total || 0,
                        dailyLimit: 100
                    };
                } catch (error) {
                    console.error('Erro:', error);
                    return {
                        isPremium: false,
                        used: 0,
                        dailyLimit: 100
                    };
                }
            }


            // Inicializa o uso diário ao carregar a página (somente para usuários externos)
            function initializeDailyUsage() {
                const today = new Date().toISOString().slice(0, 10);
                const storageKey = "empresaDailyCharCount_" + loggedUserId + "_" + today;
                fetchDailyUsage().then(total => {
                    localStorage.setItem(storageKey, total);
                    updateCharCounter();
                });
            }

            // Atualiza o uso diário do servidor periodicamente para atualização em tempo real
            function periodicDailyUsageUpdate() {
                const today = new Date().toISOString().slice(0, 10);
                const storageKey = "empresaDailyCharCount_" + loggedUserId + "_" + today;
                fetchDailyUsage().then(total => {
                    localStorage.setItem(storageKey, total);
                    updateCharCounter();
                });
            }

            // Checa se o limite diário de 500 caracteres foi atingido (usando localStorage)
            function checkDailyLimit(newChars) {
                const planoAtivo = document.getElementById('plano-ativo').value;

                // Libera completamente se for usuário premium
                if (planoAtivo === 'premium') {
                    return true;
                }

                const today = new Date().toISOString().slice(0, 10);
                const storageKey = "empresaDailyCharCount_" + loggedUserId + "_" + today;
                const currentCount = parseInt(localStorage.getItem(storageKey)) || 0;

                // Verifica se a soma não ultrapassa 100 caracteres
                return (currentCount + newChars <= 100);
            }

            // Atualiza a contagem diária após envio
            function updateDailyCount(newChars) {
                const today = new Date().toISOString().slice(0, 10);
                const storageKey = "empresaDailyCharCount_" + loggedUserId + "_" + today;
                const currentCount = parseInt(localStorage.getItem(storageKey)) || 0;
                localStorage.setItem(storageKey, currentCount + newChars);
            }

            // Exibe mensagem de erro na div já existente
            function displayMessageError(msg) {
                const errorElem = document.getElementById('message-error');
                if (errorElem) {
                    errorElem.textContent = msg;
                }
            }

            // Limpa a mensagem de erro
            function clearMessageError() {
                const errorElem = document.getElementById('message-error');
                if (errorElem) {
                    errorElem.textContent = '';
                }
            }

            // Função para bloquear o Enter quando exceder o limite
            function handleChatInput(event) {
                if (event.key === 'Enter' && !isPremium) {
                    const mensagem = document.getElementById('mensagem').value;
                    if (mensagem.length >= 30) {
                        alert('Limite de 30 caracteres atingido!');
                        return false;
                    }
                    return true;
                }
                return true;
            }
            // Função para atualizar o contador de caracteres restantes para a mensagem e o limite diário
            async function updateCharCounter() {
                const {
                    isPremium,
                    used,
                    dailyLimit
                } = await fetchDailyUsage();
                const messageCharCount = mensagemInput.value.length;
                const charCounter = document.getElementById('char-counter');
                const submitButton = document.querySelector('#chat-form button[type="submit"]');

                if (isPremium) {
                    // Remove todas as restrições para premium
                    charCounter.innerHTML = `
            <div class="text-success">
                <i class="bi bi-unlock"></i> Plano Premium: Mensagens ilimitadas
            </div>
        `;
                    mensagemInput.removeAttribute('maxlength');
                    mensagemInput.disabled = false;
                    if (submitButton) submitButton.disabled = false;
                } else {
                    // Aplica restrições para não assinantes
                    const remaining = dailyLimit - used - messageCharCount;
                    const canSend = remaining >= 0 && messageCharCount <= 30;

                    // Atualiza o maxlength dinamicamente
                    mensagemInput.setAttribute('maxlength', 30);

                    // Atualiza mensagens e estado do campo
                    if (remaining <= 0) {
                        charCounter.innerHTML = `
                <div class="text-center">
                    <div class="alert alert-warning mb-2">Limite diário atingido!</div>
                    <!-- <button onclick="window.location.href='/visamunicipal/views/ChatVisa/assinatura.php'" 
                        class="btn btn-primary btn-sm">
                        <i class="bi bi-unlock"></i> Liberar Acesso
                    </button> -->
                </div>
            `;
                        mensagemInput.disabled = true;
                        if (submitButton) submitButton.disabled = true;
                    } else {
                        charCounter.textContent = `
                ${Math.max(30 - messageCharCount, 0)} caracteres restantes por mensagem.
                Faltam ${dailyLimit - used} caracteres no limite diário.
            `;
                        mensagemInput.disabled = !canSend;
                        if (submitButton) submitButton.disabled = !canSend;
                    }
                }
            }

            if (isEmpresaChat) {
                initializeDailyUsage();
                // Atualiza periodicamente (a cada 10 segundos) para refletir o uso diário em tempo real
                setInterval(periodicDailyUsageUpdate, 1000);
            }

            if (isEmpresaChat) {
                // Adicionar evento de focus para verificar assinatura
                mensagemInput.addEventListener('focus', async function() {
                    const {
                        isPremium
                    } = await fetchDailyUsage();

                    if (!isPremium && !window.mensagemInicialEnviada) {
                        const bloqueioDiv = document.getElementById('bloqueio-inicial');
                        const contador = bloqueioDiv.querySelector('#contador-bloqueio');
                        const contadorTexto = bloqueioDiv.querySelector('#contador-texto');
                        let tempoRestante = 5;

                        bloqueioDiv.classList.remove('d-none');
                        this.disabled = true;
                        document.querySelector('#chat-form button[type="submit"]').disabled = true;

                        const intervalo = setInterval(() => {
                            tempoRestante--;
                            contador.textContent = tempoRestante;
                            contadorTexto.textContent = tempoRestante;

                            if (tempoRestante <= 0) {
                                clearInterval(intervalo);
                                this.disabled = false;
                                document.querySelector('#chat-form button[type="submit"]').disabled = false;
                                bloqueioDiv.classList.add('d-none');
                                window.mensagemInicialEnviada = true;
                            }
                        }, 1000);
                    }
                });
                // Sobrescreve a função enviarMensagem para o chat da empresa com validações
                window.enviarMensagem = async function() {
                    const {
                        isPremium,
                        used
                    } = await fetchDailyUsage();
                    const mensagem = mensagemInput.value.trim();
                    const charCount = mensagem.length;

                    if (!isPremium) {
                        // Aplica validações apenas para não assinantes
                        if (charCount > 30) {
                            displayMessageError("Limite de 30 caracteres por mensagem!");
                            return;
                        }
                        if (used + charCount > 100) {
                            displayMessageError("Limite diário excedido!");
                            return;
                        }

                        // Bloqueia o chat por 10 segundos e mostra aviso
                        const bloqueioDiv = document.getElementById('bloqueio-inicial');
                        bloqueioDiv.classList.remove('d-none');
                        mensagemInput.disabled = true;
                        document.querySelector('#chat-form button[type="submit"]').disabled = true;

                        setTimeout(() => {
                            mensagemInput.disabled = false;
                            document.querySelector('#chat-form button[type="submit"]').disabled = false;
                            bloqueioDiv.classList.add('d-none');
                        }, 5000);
                    }

                    const mensagemLowerCase = mensagem.toLowerCase();
                    const keywords = ["processo", "documentos", "analise"];
                    const standardMessage = "A verificação de documentos e processo devem ser respeitadas os prazos, aguarde a analise do processo.";

                    // Check for keywords and send standard message
                    if (keywords.some(keyword => mensagemLowerCase.includes(keyword))) {
                        // Send the standard message
                        const formData = new FormData();
                        formData.append('destinatario_id', document.getElementById('destinatario-id').value);
                        formData.append('mensagem', standardMessage);

                        fetch('/visamunicipal/views/ChatVisa/enviar_mensagem.php', {
                                method: 'POST',
                                body: formData,
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    if (typeof carregarMensagens === 'function') {
                                        carregarMensagens();
                                    }
                                    mensagemInput.value = '';
                                    clearMessageError();
                                } else {
                                    console.error('Erro ao enviar mensagem:', data.error);
                                }
                            })
                            .catch(error => console.error('Erro ao processar envio:', error));

                        // Prevent the original message from being sent
                        return;
                    }

                    // Prevent sending the standard message
                    if (mensagem === standardMessage) {
                        return;
                    }

                    // If no keywords are found, send the original message
                    const formData = new FormData();
                    formData.append('destinatario_id', document.getElementById('destinatario-id').value);
                    formData.append('mensagem', mensagem);

                    fetch('/visamunicipal/views/ChatVisa/enviar_mensagem.php', {
                            method: 'POST',
                            body: formData,
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                updateDailyCount(charCount);
                                if (typeof carregarMensagens === 'function') {
                                    carregarMensagens();
                                }
                                mensagemInput.value = '';
                                if (typeof carregarUsuarios === 'function') {
                                    carregarUsuarios();
                                }
                                clearMessageError();
                                updateCharCounter();
                            } else {
                                console.error('Erro ao enviar mensagem:', data.error);
                            }
                        })
                        .catch(error => console.error('Erro ao processar envio:', error));
                };

                // Validação em tempo real para limitar a 30 caracteres e atualizar o contador
                mensagemInput.addEventListener('input', function() {
                    updateCharCounter();
                    const charCount = mensagemInput.value.length;
                    const submitButton = document.querySelector('#chat-form button[type="submit"]');

                    if (!isPremium && charCount > 30) {
                        displayMessageError("Limite de 30 caracteres atingido.");
                        if (submitButton) submitButton.disabled = true;
                    } else {
                        clearMessageError();
                        if (submitButton) submitButton.disabled = false;
                    }
                });
            }
        });
    </script>
    <!-- Script de notificações avançadas -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const floatingNotification = document.getElementById('floating-notification');
            const notificationMessage = document.getElementById('notification-message');
            const loggedUserId = parseInt(document.getElementById('logged-user-id').value);
            let notificacoesAnteriores = {};
            
            // Mapa para nomes de usuários
            const usuariosMap = new Map();
            
            // Preencher o mapa de usuários com IDs e nomes
            <?php foreach ($usuarios as $u): ?>
                usuariosMap.set(<?php echo $u['id']; ?>, "<?php echo htmlspecialchars($u['nome_completo']); ?>");
            <?php endforeach; ?>
            
            function verificarNovasMensagens() {
                fetch('/visamunicipal/views/ChatVisa/notificacoes_mensagem.php')
                    .then(response => response.json())
                    .then(data => {
                        let novaMensagem = null;
                        
                        // Verifica se há novas mensagens
                        data.forEach(notif => {
                            const id = parseInt(notif.id);
                            const qtd = parseInt(notif.mensagens_nao_lidas);
                            
                            if (qtd > 0 && (!notificacoesAnteriores[id] || qtd > notificacoesAnteriores[id])) {
                                novaMensagem = id;
                            }
                            
                            notificacoesAnteriores[id] = qtd;
                            
                            // Atualiza o contador de mensagens não lidas na lista
                            const userItem = document.querySelector(`.user-btn[data-user-id="${id}"]`)?.closest('li');
                            if (userItem) {
                                const badge = userItem.querySelector('.badge');
                                if (qtd > 0) {
                                    if (badge) {
                                        badge.textContent = qtd;
                                    } else {
                                        userItem.innerHTML += `<span class="badge bg-danger">${qtd}</span>`;
                                    }
                                } else if (badge) {
                                    badge.remove();
                                }
                            }
                        });
                        
                        // Exibe notificação se houver nova mensagem
                        if (novaMensagem !== null) {
                            const destinatarioId = document.getElementById('destinatario-id')?.value;
                            
                            // Só mostra notificação se NÃO estiver com o chat do remetente aberto
                            if (destinatarioId != novaMensagem) {
                                const userName = usuariosMap.get(novaMensagem) || "Usuário";
                                
                                // Obtém apenas o primeiro nome
                                const firstName = userName.split(' ')[0];
                                
                                notificationMessage.textContent = `${firstName} enviou uma nova mensagem.`;
                                floatingNotification.classList.add('show');
                                
                                // Reproduz som de notificação
                                const audio = new Audio('/visamunicipal/views/ChatVisa/notification.mp3');
                                audio.volume = 0.5;
                                audio.play().catch(e => console.log('Reprodução de áudio bloqueada'));
                                
                                // Destaca o usuário na lista
                                const userButton = document.querySelector(`.user-btn[data-user-id="${novaMensagem}"]`);
                                if (userButton) {
                                    const userItem = userButton.closest('li');
                                    userItem.classList.add('highlight-user');
                                    setTimeout(() => {
                                        userItem.classList.remove('highlight-user');
                                    }, 3000);
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Erro ao verificar mensagens:', err));
            }
            
            // Inicia o polling
            verificarNovasMensagens();
            setInterval(verificarNovasMensagens, 5000);
            
            // Fecha a notificação ao clicar nela
            floatingNotification.addEventListener('click', function(e) {
                if (e.target.classList.contains('notification-close')) return;
                this.classList.remove('show');
            });
        });
    </script>
</body>

</html>