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
            // Validação anti-spam
            $resposta_usuario = isset($_POST['resposta_antispam']) ? trim(strtolower($_POST['resposta_antispam'])) : '';
            $resposta_correta = isset($_SESSION['resposta_antispam']) ? $_SESSION['resposta_antispam'] : '';
            
            // Remove acentos para comparação mais flexível
            $resposta_usuario = $this->removerAcentos($resposta_usuario);
            
            if ($resposta_usuario !== $resposta_correta) {
                $_SESSION['error_message'] = "Erro: Resposta de verificação incorreta. Por favor, tente novamente.";
                header("Location: ../views/Company/register.php");
                return;
            }
            
            // Limpa a resposta da sessão após validação
            unset($_SESSION['resposta_antispam']);

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
    
    /**
     * Remove acentos de uma string para comparação flexível
     */
    private function removerAcentos($string)
    {
        $acentos = array(
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        );
        return strtr($string, $acentos);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    include '../conf/database.php';
    session_start();
    $controller = new UsuarioExternoController($conn);
    $controller->register();
}
