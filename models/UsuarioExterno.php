<?php
class UsuarioExterno
{
    private $conn;
    private $table_name = "usuarios_externos";

    public $id;
    public $nome_completo;
    public $cpf;
    public $telefone;
    public $email;
    public $vinculo_estabelecimento;
    public $senha;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function create()
    {
        $query = "INSERT INTO " . $this->table_name . " (nome_completo, cpf, telefone, email, vinculo_estabelecimento, senha) VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $hashed_password = password_hash($this->senha, PASSWORD_DEFAULT);
        $stmt->bind_param("ssssss", $this->nome_completo, $this->cpf, $this->telefone, $this->email, $this->vinculo_estabelecimento, $hashed_password);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    public function desvincularUsuarioEstabelecimento($usuarioId, $estabelecimentoId)
    {
        $stmt = $this->conn->prepare("DELETE FROM usuarios_estabelecimentos WHERE usuario_id = ? AND estabelecimento_id = ?");
        $stmt->bind_param('ii', $usuarioId, $estabelecimentoId);
        return $stmt->execute();
    }

    public function findByEmail($email)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function searchUsuarios($term)
    {
        $termLike = '%' . $term . '%';
    
        $query = "
            (SELECT id, nome_completo, cpf, email, telefone, tipo_vinculo, 'interno' AS origem 
             FROM usuarios 
             WHERE nome_completo LIKE ? OR cpf LIKE ?)
            UNION
            (SELECT id, nome_completo, cpf, email, telefone, tipo_vinculo, 'externo' AS origem 
             FROM " . $this->table_name . " 
             WHERE nome_completo LIKE ? OR cpf LIKE ?)
        ";
    
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            die("Prepare failed: " . $this->conn->error);
        }
        $stmt->bind_param("ssss", $termLike, $termLike, $termLike, $termLike);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        return $usuarios;
    }
    


    public function findByTelefone($telefone)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE telefone = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $telefone);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }



    public function findByCPF($cpf)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE cpf = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getAllUsuarios()
    {
        $result = $this->conn->query("SELECT * FROM usuarios_externos");
        if ($result->num_rows > 0) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            return [];
        }
    }

    public function getUsuariosByEstabelecimento($estabelecimentoId)
    {
        $query = "
            SELECT ue.*, ue_tipo.tipo_vinculo 
            FROM usuarios_externos ue
            JOIN usuarios_estabelecimentos ue_tipo ON ue.id = ue_tipo.usuario_id
            WHERE ue_tipo.estabelecimento_id = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $estabelecimentoId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getUsuarioById($id)
    {
        $query = "SELECT * FROM usuarios_externos WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getTodosUsuariosExternos()
    {
        $sql = "SELECT * FROM usuarios_externos";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function atualizarSenha($id, $novaSenhaHash)
    {
        $query = "UPDATE usuarios_externos SET senha = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $novaSenhaHash, $id);
        return $stmt->execute();
    }

    public function buscarUsuariosExternos($search, $municipio, $nivel_acesso)
    {
        if ($nivel_acesso == 1) {
            // Administrador tem acesso a todos os usuários
            $sql = "SELECT * FROM " . $this->table_name . " WHERE nome_completo LIKE ? OR cpf LIKE ?";
            $stmt = $this->conn->prepare($sql);
            $search = '%' . $search . '%';
            $stmt->bind_param("ss", $search, $search);
        } else {
            // Usuários não administradores têm acesso apenas aos usuários do mesmo município
            $sql = "SELECT * FROM " . $this->table_name . " WHERE (nome_completo LIKE ? OR cpf LIKE ?) AND id IN (SELECT usuario_id FROM usuarios_estabelecimentos WHERE estabelecimento_id IN (SELECT id FROM estabelecimentos WHERE municipio = ?))";
            $stmt = $this->conn->prepare($sql);
            $search = '%' . $search . '%';
            $stmt->bind_param("sss", $search, $search, $municipio);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function atualizarDados($id, $nomeCompleto, $email, $cpf, $telefone)
    {
        $sql = "UPDATE usuarios_externos SET nome_completo = ?, email = ?, cpf = ?, telefone = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$nomeCompleto, $email, $cpf, $telefone, $id]);
    }


    public function vincularUsuarioEstabelecimento($usuarioId, $estabelecimentoId, $tipoVinculo)
    {
        // Verifica se já existe o vínculo
        $checkQuery = "SELECT * FROM usuarios_estabelecimentos WHERE usuario_id = ? AND estabelecimento_id = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param("ii", $usuarioId, $estabelecimentoId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Vínculo já existe
            return false;
        } else {
            // Insere novo vínculo
            $query = "INSERT INTO usuarios_estabelecimentos (usuario_id, estabelecimento_id, tipo_vinculo) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iis", $usuarioId, $estabelecimentoId, $tipoVinculo);
            return $stmt->execute();
        }
    }
}
