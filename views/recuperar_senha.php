<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Autoload do PHPMailer

session_start();
include '../conf/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include '../models/UsuarioExterno.php';

    $email = $_POST['email'];
    $usuarioExterno = new UsuarioExterno($conn);

    // Verificar se existe um pedido recente
    $stmt = $conn->prepare("SELECT expira_em FROM senha_tokens WHERE email = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_request = $result->fetch_assoc();

    if ($recent_request) {
        $last_request_time = strtotime($recent_request['expira_em']);
        if (time() < $last_request_time - 3600 + 120) { // 3600s é 1h validade - 2 min de cooldown
            $remaining_time = ($last_request_time - time()) - 3540; // Calcula os 2 minutos restantes
            $_SESSION['error_message'] = "Você só pode solicitar outra redefinição em {$remaining_time} segundos.";
            header("Location: recuperar_senha.php");
            exit;
        }
    }

    $usuario = $usuarioExterno->findByEmail($email);

    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        $expira_em = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Salva o token no banco
        $stmt = $conn->prepare("INSERT INTO senha_tokens (email, token, expira_em) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expira_em);
        $stmt->execute();

        // Enviar e-mail com layout bonito
        $link = "https://infovisa.gurupi.to.gov.br/visamunicipal/views/redefinir_senha.php?token=$token";
        $assunto = "Recuperar Senha - Infovisa";
        $mensagem = "
            <html>
            <head>
                <title>Recuperação de Senha</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        text-align: center;
                        background-color: #f9f9f9;
                    }
                    .button {
                        background-color: #007bff;
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 5px;
                        display: inline-block;
                        margin-top: 20px;
                    }
                    .footer {
                        margin-top: 20px;
                        font-size: 12px;
                        color: #666;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Recuperação de Senha - Infovisa</h2>
                    <p>Você solicitou a recuperação de senha. Clique no botão abaixo para redefinir sua senha:</p>
                    <a href='$link' class='button'>Redefinir Senha</a>
                    <p>Se você não solicitou esta recuperação, ignore este e-mail.</p>
                    <div class='footer'>
                        <p>Infovisa - Sistema Municipal</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail = new PHPMailer(true);

        try {
            // Configurações do servidor SMTP
            $mail->isSMTP();
            $mail->Host = 'email-ssl.com.br';
            $mail->SMTPAuth = true;
            $mail->Username = 'ti.saude@gurupi.to.gov.br';
            $mail->Password = 'Dti@2021//';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Configuração do e-mail
            $mail->setFrom('ti.saude@gurupi.to.gov.br', 'Infovisa');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $mensagem;

            $mail->send();
            $_SESSION['success_message'] = "E-mail de recuperação enviado com sucesso!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Erro ao enviar e-mail: {$mail->ErrorInfo}";
        }
    } else {
        $_SESSION['error_message'] = "E-mail não encontrado.";
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
    <title>Recuperar Senha - Infovisa</title>
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
            <h3>Recuperar Senha</h3>
            <p>Digite seu e-mail para receber o link de redefinição de senha.</p>
        </div>

        <!-- Mensagens de sucesso/erro -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php elseif (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Formulário de Recuperação -->
        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" placeholder="exemplo@email.com" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Enviar</button>
        </form>

        <!-- Links de Ação -->
        <div class="register-link">
            <a href="login.php">Voltar para o Login</a>
        </div>
    </div>
</body>

</html>