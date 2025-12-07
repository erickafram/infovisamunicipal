<?php
session_start();
require_once '../../conf/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Verificar se o CPF foi enviado
if (!isset($_POST['cpf']) || empty($_POST['cpf'])) {
    echo json_encode(['success' => false, 'message' => 'CPF não informado.']);
    exit;
}

$cpf = trim($_POST['cpf']);
// Remover caracteres especiais do CPF para a busca
$cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

// Formatar o CPF no formato XXX.XXX.XXX-XX
$cpfFormatado = '';
if (strlen($cpfLimpo) === 11) {
    $cpfFormatado = substr($cpfLimpo, 0, 3) . '.' . 
                    substr($cpfLimpo, 3, 3) . '.' . 
                    substr($cpfLimpo, 6, 3) . '-' . 
                    substr($cpfLimpo, 9, 2);
}

// Buscar usuário pelo CPF (verificando ambos formatos)
$query = "SELECT id, nome_completo, cpf, email, telefone, tipo_vinculo FROM usuarios_externos WHERE cpf = ? OR cpf = ? OR REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $cpf, $cpfFormatado, $cpfLimpo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $usuario = $result->fetch_assoc();
    echo json_encode(['success' => true, 'usuario' => $usuario]);
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum usuário encontrado com este CPF.']);
}

$stmt->close();
?>
