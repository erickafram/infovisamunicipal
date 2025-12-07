<?php
session_start();
require_once '../../conf/database.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Usuário não autorizado']);
    exit();
}

// Verificar se o termo de busca foi fornecido
if (!isset($_GET['term']) || empty(trim($_GET['term']))) {
    echo json_encode([]);
    exit();
}

$termo = trim($_GET['term']);
$municipio = $_SESSION['user']['municipio'];

// Buscar usuários que correspondam ao termo de pesquisa no mesmo município
$sql = "SELECT id, nome_completo, cpf 
        FROM usuarios 
        WHERE municipio = ? 
        AND (nome_completo LIKE ? OR cpf LIKE ?)
        AND status = 'ativo'
        ORDER BY nome_completo ASC 
        LIMIT 10";

$stmt = $conn->prepare($sql);
$termoBusca = "%{$termo}%";
$stmt->bind_param('sss', $municipio, $termoBusca, $termoBusca);
$stmt->execute();
$result = $stmt->get_result();

$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = [
        'id' => $row['id'],
        'nome_completo' => $row['nome_completo'],
        'cpf' => $row['cpf'],
        'label' => $row['nome_completo'] . ' - ' . $row['cpf']
    ];
}

echo json_encode($usuarios);
?> 