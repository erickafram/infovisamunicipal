<?php
session_start();

// Verificar se o pedido é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sidebar_open'])) {
    // Salvar o estado na sessão
    $_SESSION['sidebar_open'] = (int)$_POST['sidebar_open'];
    echo json_encode(['success' => true]);
} else {
    // Retornar erro se não for um pedido POST válido
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
} 