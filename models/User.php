<?php
class User
{
    private $conn;
    private $lastError;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function create($nome_completo, $cpf, $email, $telefone, $municipio, $cargo, $nivel_acesso, $senha, $tempo_vinculo, $escolaridade, $tipo_vinculo)
    {
        $stmt = $this->conn->prepare("INSERT INTO usuarios (nome_completo, cpf, email, telefone, municipio, cargo, nivel_acesso, senha, tempo_vinculo, escolaridade, tipo_vinculo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("ssssssissss", $nome_completo, $cpf, $email, $telefone, $municipio, $cargo, $nivel_acesso, $senha, $tempo_vinculo, $escolaridade, $tipo_vinculo);
        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }

    public function getTotalUsers()
    {
        $result = $this->conn->query("SELECT COUNT(*) as total FROM usuarios");
        return $result->fetch_assoc()['total'];
    }

    public function getPaginatedUsers($offset, $limit)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios LIMIT ?, ?");
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalUsersByMunicipio($municipio)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE municipio = ?");
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getPaginatedUsersByMunicipio($municipio, $offset, $limit)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE municipio = ? LIMIT ?, ?");
        $stmt->bind_param("sii", $municipio, $offset, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function findByCPF($cpf)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE cpf = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function updateSenha($id, $novaSenha)
    {
        $stmt = $this->conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("si", $novaSenha, $id);
        return $stmt->execute();
    }


    public function findByEmail($email)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE email = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getUsersByMunicipio($municipio)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE municipio = ? AND nivel_acesso != 1 ORDER BY nome_completo");
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function resetPassword($id)
    {
        $novaSenha = password_hash('@visa@2024', PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("si", $novaSenha, $id);
        return $stmt->execute();
    }

    public function getTotalUsersBySearch($busca, $usuarioLogado)
    {
        $query = "SELECT COUNT(*) as total FROM usuarios WHERE (nome_completo LIKE ? OR cpf LIKE ?)";
        if ($usuarioLogado['nivel_acesso'] == 3) {
            $query .= " AND municipio = ?";
        }
        $stmt = $this->conn->prepare($query);

        $likeBusca = '%' . $busca . '%';
        if ($usuarioLogado['nivel_acesso'] == 3) {
            $stmt->bind_param("sss", $likeBusca, $likeBusca, $usuarioLogado['municipio']);
        } else {
            $stmt->bind_param("ss", $likeBusca, $likeBusca);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getUsersBySearch($busca, $usuarioLogado, $offset, $limit)
    {
        $query = "SELECT * FROM usuarios WHERE (nome_completo LIKE ? OR cpf LIKE ?)";
        if ($usuarioLogado['nivel_acesso'] == 3) {
            $query .= " AND municipio = ?";
        }
        $query .= " LIMIT ?, ?";
        $stmt = $this->conn->prepare($query);

        $likeBusca = '%' . $busca . '%';
        if ($usuarioLogado['nivel_acesso'] == 3) {
            $stmt->bind_param("sssii", $likeBusca, $likeBusca, $usuarioLogado['municipio'], $offset, $limit);
        } else {
            $stmt->bind_param("ssii", $likeBusca, $likeBusca, $offset, $limit);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function updateNivelAcesso($id, $nivel_acesso)
    {
        $stmt = $this->conn->prepare("UPDATE usuarios SET nivel_acesso = ? WHERE id = ?");
        $stmt->bind_param("ii", $nivel_acesso, $id);
        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }


    public function getAllUsers()
    {
        $result = $this->conn->query("SELECT * FROM usuarios");
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            $this->lastError = $this->conn->error;
            return false;
        }
    }

    public function activateUser($id)
    {
        $stmt = $this->conn->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function deactivateUser($id)
    {
        $stmt = $this->conn->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }


    public function findById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function update($id, $nome_completo, $cpf, $email, $telefone, $municipio, $cargo, $nivel_acesso, $tempo_vinculo, $escolaridade, $tipo_vinculo)
    {
        $stmt = $this->conn->prepare("UPDATE usuarios SET nome_completo = ?, cpf = ?, email = ?, telefone = ?, municipio = ?, cargo = ?, nivel_acesso = ?, tempo_vinculo = ?, escolaridade = ?, tipo_vinculo = ? WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            return false;
        }
        $stmt->bind_param("ssssssisssi", $nome_completo, $cpf, $email, $telefone, $municipio, $cargo, $nivel_acesso, $tempo_vinculo, $escolaridade, $tipo_vinculo, $id);
        if ($stmt->execute()) {
            return true;
        } else {
            $this->lastError = $stmt->error;
            return false;
        }
    }

    public function updateSenhaDigital($id, $senhaDigital)
    {
        // Debug
        error_log("Atualizando senha digital para usuário ID: " . $id);
        error_log("Hash da senha: " . substr($senhaDigital, 0, 20) . "...");
        
        $stmt = $this->conn->prepare("UPDATE usuarios SET senha_digital = ? WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            error_log("Erro ao preparar consulta: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("si", $senhaDigital, $id);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Erro ao executar consulta: " . $stmt->error);
        } else {
            error_log("Senha digital atualizada com sucesso");
        }
        
        return $result;
    }

    public function verificarSenhaDigital($id, $senha)
    {
        $stmt = $this->conn->prepare("SELECT senha_digital FROM usuarios WHERE id = ?");
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            error_log("Erro ao preparar consulta verificarSenhaDigital: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Debug detalhado
        error_log("User.php - verificarSenhaDigital - ID: " . $id);
        error_log("User.php - verificarSenhaDigital - Senha fornecida: " . substr($senha, 0, 1) . "****");
        
        if ($user && $user['senha_digital']) {
            error_log("User.php - verificarSenhaDigital - Hash armazenado: " . substr($user['senha_digital'], 0, 20) . "...");
            
            // Garantir que a senha seja tratada como string
            $senha = (string) $senha;
            
            // Verificar a senha
            $verificacao = password_verify($senha, $user['senha_digital']);
            
            error_log("User.php - verificarSenhaDigital - Resultado da verificação: " . ($verificacao ? "SUCESSO" : "FALHA"));
            error_log("User.php - verificarSenhaDigital - Tipo da senha: " . gettype($senha));
            error_log("User.php - verificarSenhaDigital - Comprimento da senha: " . strlen($senha));
            
            return $verificacao;
        }
        
        error_log("User.php - verificarSenhaDigital - Usuário não encontrado ou senha digital não configurada");
        return false;
    }
}
