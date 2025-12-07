<?php
session_start();
require_once '../../conf/database.php';

header('Content-Type: application/json'); // Garantir resposta JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $remetente_id = $_SESSION['user']['id'];
  $destinatario_id = $_POST['destinatario_id'] ?? null;
  $mensagem = trim($_POST['mensagem'] ?? '');

  if (!$destinatario_id || !$mensagem) {
    echo json_encode(['success' => false, 'error' => 'Destinatário ou mensagem não fornecidos.']);
    exit;
  }

  $sql = "INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, data_envio) 
            VALUES (?, ?, ?, NOW())";
  $stmt = $conn->prepare($sql);

  if ($stmt) {
    $stmt->bind_param("iis", $remetente_id, $destinatario_id, $mensagem);

    if ($stmt->execute()) {
      echo json_encode(['success' => true]);
    } else {
      echo json_encode(['success' => false, 'error' => 'Erro ao salvar mensagem.']);
    }

    $stmt->close();
  } else {
    echo json_encode(['success' => false, 'error' => 'Erro na preparação da consulta.']);
  }

  $conn->close();
  exit;
}

echo json_encode(['success' => false, 'error' => 'Método de solicitação inválido.']);
exit;
