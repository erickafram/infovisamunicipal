<?php
require_once '../conf/database.php';
require_once '../models/UsuarioExterno.php';

session_start();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'alterar_senha':
        alterarSenha();
        break;
    case 'reset_senha':
        resetSenha();
        break;
    case 'alterar_dados':
        alterarDados();
        break;
    default:
        // Ação padrão
        break;
}

function alterarSenha()
{
    global $conn;

    if (!isset($_SESSION['user'])) {
        header("Location: ../../login.php");
        exit();
    }

    $userId = $_SESSION['user']['id'];
    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];
    $confirmarNovaSenha = $_POST['confirmar_nova_senha'];

    if ($novaSenha !== $confirmarNovaSenha) {
        header("Location: ../views/alterar_senha.php?mensagem=As novas senhas não coincidem.&tipoMensagem=danger");
        return;
    }

    $usuarioExternoModel = new UsuarioExterno($conn);
    $usuario = $usuarioExternoModel->getUsuarioById($userId);

    if (!password_verify($senhaAtual, $usuario['senha'])) {
        header("Location: ../views/alterar_senha.php?mensagem=A senha atual está incorreta.&tipoMensagem=danger");
        return;
    }

    $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $usuarioExternoModel->atualizarSenha($userId, $novaSenhaHash);

    header("Location: ../views/alterar_senha.php?mensagem=Senha alterada com sucesso.&tipoMensagem=success");
}

function alterarDados()
{
    global $conn;

    if (!isset($_SESSION['user'])) {
        header("Location: ../../login.php");
        exit();
    }

    $userId = $_SESSION['user']['id'];
    $nomeCompleto = $_POST['nome_completo'];
    $email = $_POST['email'];
    // Mantenha o CPF original da sessão, não aceite alterações
    $cpf = $_SESSION['user']['cpf'];
    $telefone = $_POST['telefone'];

    $usuarioExternoModel = new UsuarioExterno($conn);

    if ($usuarioExternoModel->atualizarDados($userId, $nomeCompleto, $email, $cpf, $telefone)) {
        // Atualiza a sessão com os novos valores (exceto o CPF que permanece o mesmo)
        $_SESSION['user']['nome_completo'] = $nomeCompleto;
        $_SESSION['user']['email'] = $email;
        // CPF não é alterado - $_SESSION['user']['cpf'] já contém o valor correto
        $_SESSION['user']['telefone'] = $telefone;

        header("Location: ../views/Company/alterar_dados_empresa.php?mensagem=Dados alterados com sucesso.&tipoMensagem=success");
    } else {
        header("Location: ../views/Company/alterar_dados_empresa.php?mensagem=Falha ao alterar os dados.&tipoMensagem=danger");
    }
}


function resetSenha()
{
    global $conn;

    if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 3])) {
        header("Location: ../../login.php");
        exit();
    }

    $usuarioId = $_POST['usuario_id'];
    $novaSenhaHash = password_hash('@visa2024', PASSWORD_DEFAULT);

    $usuarioExternoModel = new UsuarioExterno($conn);
    if ($usuarioExternoModel->atualizarSenha($usuarioId, $novaSenhaHash)) {
        header("Location: ../views/Company/listar_usuarios.php?mensagem=Senha redefinida com sucesso.&tipoMensagem=success");
    } else {
        header("Location: ../views/Company/listar_usuarios.php?mensagem=Falha ao redefinir a senha.&tipoMensagem=danger");
    }
}
