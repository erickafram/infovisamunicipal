<?php
session_start();

if (isset($_SESSION['user'])) {
    require_once '../conf/database.php';
    $user_id = $_SESSION['user']['id'];

    // FORÇA remover o registro da tabela
    $sql = "DELETE FROM usuarios_online WHERE usuario_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logout</title>
    <script type="text/javascript">
        localStorage.removeItem('chatHistory');
        localStorage.removeItem('chatMinimized');
        window.location.href = "login.php";
    </script>
</head>
<body>
    <p>Saindo...</p>
</body>
</html>
