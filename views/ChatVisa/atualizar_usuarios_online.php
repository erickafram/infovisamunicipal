<?php
// Incluir o seu arquivo de conexão
require_once '../../conf/database.php';

// Verificar se é POST e se possui os campos necessários
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['usuario_id'])) {
    $usuario_id = $_POST['usuario_id'];

    if ($_POST['acao'] === 'conectar') {
        // Usar prepared statement para inserir
        $sql = "INSERT INTO usuarios_online (usuario_id) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_id);

        if ($stmt->execute()) {
            echo "Usuário conectado com sucesso!";
        } else {
            echo "Erro ao conectar usuário: " . $conn->error;
        }

        $stmt->close();
    } elseif ($_POST['acao'] === 'desconectar') {
        // Usar prepared statement para deletar
        $sql = "DELETE FROM usuarios_online WHERE usuario_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_id);

        if ($stmt->execute()) {
            echo "Usuário desconectado com sucesso!";
        } else {
            echo "Erro ao desconectar usuário: " . $conn->error;
        }

        $stmt->close();
    }
}

// Fechar a conexão
$conn->close();
