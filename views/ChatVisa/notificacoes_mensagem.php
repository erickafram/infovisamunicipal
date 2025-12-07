<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../conf/database.php';

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user']['id'];

/*
  Consulta que une as notificações (contagem de mensagens não lidas)
  dos usuários internos e externos.

  Na subconsulta para os usuários internos (tabela "usuarios"):
    - É contada a quantidade de mensagens onde o destinatário é o usuário logado e
      o remetente é o usuário interno, e que ainda não foram visualizadas.
  
  Na subconsulta para os usuários externos (tabela "usuarios_externos"):
    - É contada a quantidade de mensagens onde o destinatário é o usuário logado e
      o remetente é o usuário externo.
      
  Ambas as subconsultas são unidas para que o JavaScript (chat.js) possa processar
  as notificações de todos os contatos.
*/
$sql_notificacoes = "
SELECT * FROM (
  (SELECT 
      u.id, 
      (SELECT COUNT(*) 
       FROM mensagens 
       WHERE destinatario_id = ? AND remetente_id = u.id AND visualizada = 0
      ) AS mensagens_nao_lidas
   FROM usuarios u
   WHERE u.id != ?
  )
  UNION ALL
  (SELECT 
      ue.id, 
      (SELECT COUNT(*) 
       FROM mensagens 
       WHERE destinatario_id = ? AND remetente_id = ue.id AND visualizada = 0
      ) AS mensagens_nao_lidas
   FROM usuarios_externos ue
  )
) AS union_notificacoes
";

// Na subconsulta de "usuarios" temos 2 parâmetros e na de "usuarios_externos" mais 1 – total 3 parâmetros.
$stmt_notificacoes = $conn->prepare($sql_notificacoes);
$stmt_notificacoes->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_notificacoes->execute();
$notificacoes = $stmt_notificacoes->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($notificacoes);
