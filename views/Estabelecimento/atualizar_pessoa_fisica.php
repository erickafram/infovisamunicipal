<?php
session_start();
// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

$estabelecimento = new Estabelecimento($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $rg = $_POST['rg'] ?? '';
    $orgao_emissor = $_POST['orgao_emissor'] ?? '';
    $nome_fantasia = $_POST['nome_fantasia'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $logradouro = $_POST['logradouro'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $municipio = $_POST['municipio'] ?? '';
    $uf = $_POST['uf'] ?? '';
    $email = $_POST['email'] ?? '';
    $ddd_telefone_1 = $_POST['ddd_telefone_1'] ?? '';
    $inicio_funcionamento = $_POST['inicio_funcionamento'] ?? '';
    $ramo_atividade = $_POST['ramo_atividade'] ?? '';

    // Validação simples para garantir que os dados necessários foram fornecidos
    if (empty($id) || empty($cpf) || empty($nome) || empty($logradouro) || empty($numero) || empty($bairro) || empty($cep) || empty($municipio) || empty($uf)) {
        header("Location: detalhes_estabelecimento_empresa.php?error=" . urlencode("Por favor, preencha todos os campos obrigatórios."));
        exit();
    }

    // Atualizar os dados da pessoa física
    $atualizado = $estabelecimento->updatePessoaFisica(
        $id, 
        $cpf, 
        $nome, 
        $rg, 
        $orgao_emissor, 
        $nome_fantasia, 
        $cep, 
        $logradouro, 
        $numero, 
        $bairro, 
        $complemento, 
        $municipio, 
        $uf, 
        $email, 
        $ddd_telefone_1, 
        $inicio_funcionamento, 
        $ramo_atividade
    );

    if ($atualizado) {
        header("Location: detalhes_estabelecimento_empresa.php?id=$id&success=1");
        exit();
    } else {
        header("Location: detalhes_estabelecimento_empresa.php?id=$id&error=" . urlencode("Não foi possível atualizar os dados."));
        exit();
    }
} else {
    header("Location: detalhes_estabelecimento_empresa.php?error=" . urlencode("Requisição inválida."));
    exit();
}
?>
