<?php
session_start();
require '../conf/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];

    // Verificar se o token existe e não expirou
    $stmt = $conn->prepare("SELECT email FROM senha_tokens WHERE token = ? AND expira_em > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['error_message'] = "Token inválido ou expirado.";
        header("Location: login.php");
        exit;
    }

    $user = $result->fetch_assoc();
    $email = $user['email'];
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    if ($nova_senha !== $confirmar_senha) {
        $_SESSION['error_message'] = "As senhas não coincidem.";
    } else {
        // Verificar o token novamente
        $stmt = $conn->prepare("SELECT email FROM senha_tokens WHERE token = ? AND expira_em > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $_SESSION['error_message'] = "Token inválido ou expirado.";
            header("Location: login.php");
            exit;
        }

        $user = $result->fetch_assoc();
        $email = $user['email'];

        // Atualizar a senha
        $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios_externos SET senha = ? WHERE email = ?");
        $stmt->bind_param("ss", $nova_senha_hash, $email);

        if ($stmt->execute()) {
            // Remover o token usado
            $stmt = $conn->prepare("DELETE FROM senha_tokens WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();

            $_SESSION['success_message'] = "Senha redefinida com sucesso!";
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Erro ao redefinir senha. Tente novamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <title>Redefinir Senha - Infovisa</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-container {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 100%;
        }

        .login-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .login-header h2 {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            font-size: 0.9rem;
            color: #666;
        }

        .form-label {
            font-weight: 500;
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem;
        }

        .btn-primary {
            background-color: #007bff;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .register-link {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
        }

        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .terms {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.75rem;
            color: #666;
        }

        .terms a {
            color: #007bff;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .alert {
            font-size: 0.875rem;
            border-radius: 0.5rem;
        }

        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo img {
            max-width: 150px;
            height: auto;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Logomarca -->
        <div class="logo">
            <img src="/visamunicipal/assets/img/logo.png" alt="Logo Infovisa">
        </div>

        <!-- Cabeçalho -->
        <div class="login-header">
            <h2>Redefinir Senha</h2>
            <p>Digite sua nova senha abaixo.</p>
        </div>

        <!-- Mensagens de sucesso/erro -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php elseif (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Formulário de Redefinição de Senha -->
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
            <div class="mb-3">
                <label for="nova_senha" class="form-label">Nova Senha</label>
                <input type="password" name="nova_senha" class="form-control" placeholder="Digite sua nova senha" required>
            </div>
            <div class="mb-3">
                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                <input type="password" name="confirmar_senha" class="form-control" placeholder="Confirme sua nova senha" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Redefinir Senha</button>
        </form>

        <!-- Links de Ação -->
        <div class="register-link">
            <a href="login.php">Voltar para o Login</a>
        </div>
    </div>
</body>

</html>