<?php
include_once '../conf/database.php';
include_once '../models/UsuarioExterno.php';

class UsuarioExternoController
{
    private $db;
    private $usuarioExterno;

    public function __construct($conn)
    {
        $this->db = $conn;
        $this->usuarioExterno = new UsuarioExterno($this->db);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->usuarioExterno->nome_completo = $_POST['nome_completo'] ?? '';
            $this->usuarioExterno->cpf = $_POST['cpf'] ?? '';
            $this->usuarioExterno->telefone = $_POST['telefone'] ?? '';
            $this->usuarioExterno->email = $_POST['email'] ?? '';
            $this->usuarioExterno->vinculo_estabelecimento = $_POST['vinculo_estabelecimento'] ?? '';
            $this->usuarioExterno->senha = $_POST['senha'] ?? '';

            // Verifica se a senha e a confirmação de senha coincidem
            if ($_POST['senha'] !== $_POST['senha_confirmacao']) {
                $_SESSION['error_message'] = "Erro: As senhas não coincidem.";
                header("Location: ../views/Company/register.php");
                return;
            }

            // Verifica se o usuário já existe pelo e-mail ou CPF
            if ($this->usuarioExterno->findByEmail($this->usuarioExterno->email)) {
                $_SESSION['error_message'] = "Erro: Já existe um usuário com este e-mail.";
                header("Location: ../views/Company/register.php");
                return;
            }

            if ($this->usuarioExterno->findByCPF($this->usuarioExterno->cpf)) {
                $_SESSION['error_message'] = "Erro: Já existe um usuário com este CPF.";
                header("Location: ../views/Company/register.php");
                return;
            }

            if ($this->usuarioExterno->findByTelefone($this->usuarioExterno->telefone)) {
                $_SESSION['error_message'] = "Erro: Já existe um usuário com este telefone.";
                header("Location: ../views/Company/register.php");
                return;
            }

            if ($this->usuarioExterno->create()) {
                $_SESSION['success_message'] = "Usuário cadastrado com sucesso!";
                header("Location: ../views/Company/register.php");
            } else {
                $_SESSION['error_message'] = "Erro ao cadastrar usuário.";
                header("Location: ../views/Company/register.php");
            }
        }
    }

    public function register()
    {
        $this->create();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    include '../conf/database.php';
    session_start();
    $controller = new UsuarioExternoController($conn);
    $controller->register();
}
