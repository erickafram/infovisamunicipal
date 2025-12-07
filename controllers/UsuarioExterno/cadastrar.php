<?php
session_start();
require_once '../../conf/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    $_SESSION['error_message'] = "Você precisa estar logado para realizar esta ação.";
    header('Location: ../../login.php');
    exit;
}

// Verificar se todos os campos obrigatórios foram enviados
if (!isset($_POST['cpf']) || !isset($_POST['nome_completo']) || !isset($_POST['telefone']) || 
    !isset($_POST['email']) || !isset($_POST['tipo_vinculo']) ||
    !isset($_POST['estabelecimento_id'])) {
    $_SESSION['error_message'] = "Todos os campos são obrigatórios.";
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Capturar os dados do formulário
$cpf = trim($_POST['cpf']);
$nomeCompleto = strtoupper(trim($_POST['nome_completo'])); // Converter para maiúsculas
$telefone = trim($_POST['telefone']);
$email = trim($_POST['email']);
$tipoVinculo = $_POST['tipo_vinculo'];
$estabelecimentoId = $_POST['estabelecimento_id'];

// Gerar senha padrão: @Visa@ + ano atual
$anoAtual = date('Y');
$senhaPadrao = '@Visa@' . $anoAtual;

// Remover formatação do CPF
$cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

// Formatar o CPF no formato XXX.XXX.XXX-XX
$cpfFormatado = '';
if (strlen($cpfLimpo) === 11) {
    $cpfFormatado = substr($cpfLimpo, 0, 3) . '.' . 
                    substr($cpfLimpo, 3, 3) . '.' . 
                    substr($cpfLimpo, 6, 3) . '-' . 
                    substr($cpfLimpo, 9, 2);
}

// Verificar se já existe um usuário com este CPF, email ou telefone
$query = "SELECT * FROM usuarios_externos WHERE cpf = ? OR email = ? OR telefone = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $cpfFormatado, $email, $telefone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $usuario = $result->fetch_assoc();
    if ($usuario['cpf'] === $cpfFormatado) {
        $_SESSION['error_message'] = "Já existe um usuário cadastrado com este CPF.";
    } elseif ($usuario['email'] === $email) {
        $_SESSION['error_message'] = "Já existe um usuário cadastrado com este e-mail.";
    } elseif ($usuario['telefone'] === $telefone) {
        $_SESSION['error_message'] = "Já existe um usuário cadastrado com este telefone.";
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Criptografar a senha
$senhaCriptografada = password_hash($senhaPadrao, PASSWORD_DEFAULT);

// Inserir o novo usuário no banco de dados
$query = "INSERT INTO usuarios_externos (nome_completo, cpf, telefone, email, senha, vinculo_estabelecimento, tipo_vinculo) 
          VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("sssssss", $nomeCompleto, $cpfFormatado, $telefone, $email, $senhaCriptografada, $tipoVinculo, $tipoVinculo);

if ($stmt->execute()) {
    // Obter o ID do usuário recém-cadastrado
    $usuarioId = $conn->insert_id;
    
    // Vincular o usuário ao estabelecimento
    require_once '../../models/UsuarioEstabelecimento.php';
    $usuarioEstabelecimentoModel = new UsuarioEstabelecimento($conn);
    $vinculo = $usuarioEstabelecimentoModel->vincularUsuario($usuarioId, $estabelecimentoId, $tipoVinculo);
    
    if ($vinculo['success']) {
        $_SESSION['success_message'] = "Usuário cadastrado e vinculado com sucesso! Senha padrão: " . $senhaPadrao;
    } else {
        $_SESSION['error_message'] = "Usuário cadastrado, mas ocorreu um erro ao vinculá-lo: " . $vinculo['message'];
    }
} else {
    $_SESSION['error_message'] = "Erro ao cadastrar usuário: " . $stmt->error;
}

// Redirecionar de volta para a página anterior
header('Location: ../../views/Estabelecimento/detalhes_estabelecimento_empresa.php?id=' . $estabelecimentoId);
exit;
?>
