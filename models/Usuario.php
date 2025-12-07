<?php
class Usuario
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getUsuariosByMunicipio($municipio)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE municipio = ?");
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getUsuariosPorMunicipio($municipio)
    {
        $sql = "SELECT id, nome_completo FROM usuarios WHERE municipio = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuarios = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $usuarios;
    }


    public function findById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function verificarSenhaDigital($id, $senha)
    {
        $stmt = $this->conn->prepare("SELECT senha_digital FROM usuarios WHERE id = ?");
        if (!$stmt) {
            error_log("Erro ao preparar consulta: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Debug
        error_log("Verificando senha digital para usuário ID: " . $id);
        error_log("Senha digital armazenada: " . ($user && $user['senha_digital'] ? "Existe" : "Não existe"));
        
        if ($user && $user['senha_digital']) {
            $verificacao = password_verify($senha, $user['senha_digital']);
            error_log("Resultado da verificação: " . ($verificacao ? "Senha correta" : "Senha incorreta"));
            return $verificacao;
        }
        
        error_log("Usuário não encontrado ou senha digital não configurada");
        return false;
    }
}
