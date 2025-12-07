<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recebe os dados do formulário
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);

    // Validação básica para garantir que os campos não estão vazios
    if (empty($nome) || empty($descricao)) {
        echo "Todos os campos são obrigatórios!";
        exit();
    }

    // Prepara a consulta para inserir o documento no banco de dados
    $stmt = $conn->prepare("INSERT INTO documento (nome, descricao) VALUES (?, ?)");
    $stmt->bind_param("ss", $nome, $descricao);

    if ($stmt->execute()) {
        // Redireciona para a página principal com uma mensagem de sucesso
        header("Location: cadastrar_documento.php?success=Documento cadastrado com sucesso!");
        exit();
    } else {
        echo "Erro ao cadastrar documento: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "Método inválido.";
}

$conn->close();
