<?php
require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Usuario.php';
require_once '../../models/UsuarioExterno.php';
require_once '../../models/Assinatura.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se o ID do arquivo foi passado
if (!isset($argv[1])) {
    die("ID do arquivo não especificado.\n");
}

$arquivo_id = intval($argv[1]);

// Instancia as classes necessárias
$arquivoModel = new Arquivo($conn);
$usuarioExternoModel = new UsuarioExterno($conn);

// Obtém informações do arquivo
$arquivo = $arquivoModel->getArquivoById($arquivo_id);
if (!$arquivo) {
    die("Arquivo ID: $arquivo_id não encontrado.\n");
}

// Verifica se o arquivo não é sigiloso
if ($arquivo['sigiloso'] != 0) {
    die("Envio de e-mails cancelado: Arquivo ID $arquivo_id é sigiloso.\n");
}

// Obtém informações do processo
$processo = $arquivoModel->getProcessoInfo($arquivo['processo_id']);
if (!$processo) {
    die("Processo associado ao arquivo ID: $arquivo_id não encontrado.\n");
}

// Obtém o ID do estabelecimento
$estabelecimento_id = $processo['estabelecimento_id'];
if (!$estabelecimento_id) {
    die("Nenhum estabelecimento associado ao processo ID: {$arquivo['processo_id']}.\n");
}

// Busca usuários externos vinculados ao estabelecimento
$usuariosExternos = $usuarioExternoModel->getUsuariosByEstabelecimento($estabelecimento_id);
if (empty($usuariosExternos)) {
    die("Nenhum usuário externo encontrado para o estabelecimento ID: $estabelecimento_id.\n");
}

// Define o link de acesso ao documento
$link_processo = "https://infovisa.gurupi.to.gov.br/visamunicipal/views/Company/dashboard_empresa.php";

// Configuração do e-mail
$assunto = "Novo documento assinado disponível";
$mensagem = "
<html>
<head>
    <title>Documento Disponível</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #007bff;
            text-align: center;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            color: #555;
            line-height: 1.5;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            font-size: 16px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Documento Assinado Disponível</h2>
        <p>Olá,</p>
        <p>O documento <strong>{$arquivo['tipo_documento']}</strong> foi gerado e está disponível para consulta. Para visualizá-lo, clique no botão abaixo:</p>
        <a href='$link_processo' class='button'>Visualizar Documento</a>
        <p>Este é um e-mail automático. Por favor, não responda.</p>
        <div class='footer'>
            <p>Infovisa - Desenvolvido por: <a href='https://govnex.site/' target='_blank' style='color: #007bff; text-decoration: none;'>Govnex</a></p>
            <p>&copy; " . date('Y') . " Infovisa. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
";

// Envia o e-mail para cada usuário externo
foreach ($usuariosExternos as $usuario) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'email-ssl.com.br';
        $mail->SMTPAuth = true;
        $mail->Username = 'ti.saude@gurupi.to.gov.br';
        $mail->Password = 'Dti@2021//';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('ti.saude@gurupi.to.gov.br', 'Infovisa');
        $mail->addAddress($usuario['email'], $usuario['nome_completo']);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body = $mensagem;

        $mail->send();
        echo "E-mail enviado para: {$usuario['email']}\n";
    } catch (Exception $e) {
        echo "Erro ao enviar e-mail para {$usuario['email']}: {$mail->ErrorInfo}\n";
    }
}
