<?php
class ResponsavelLegal
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function findByCpf($cpf)
    {
        $stmt = $this->conn->prepare("SELECT * FROM responsaveis_legais WHERE cpf = ?");
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($estabelecimento_id, $nome, $cpf, $email, $telefone, $documento_identificacao)
    {
        $stmt = $this->conn->prepare("INSERT INTO responsaveis_legais (estabelecimento_id, nome, cpf, email, telefone, documento_identificacao) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $estabelecimento_id, $nome, $cpf, $email, $telefone, $documento_identificacao);
        $stmt->execute();
    }

    public function getByEstabelecimento($estabelecimento_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM responsaveis_legais WHERE estabelecimento_id = ?");
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function update($responsavel_id, $nome, $cpf, $email, $telefone, $documento_identificacao)
    {
        $stmt = $this->conn->prepare("UPDATE responsaveis_legais SET nome = ?, cpf = ?, email = ?, telefone = ?, documento_identificacao = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nome, $cpf, $email, $telefone, $documento_identificacao, $responsavel_id);
        $stmt->execute();
    }

    public function delete($responsavel_id, $estabelecimento_id)
    {
        // Verificar quantos responsáveis estão vinculados ao estabelecimento
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM responsaveis_legais WHERE estabelecimento_id = ?");
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        // Se houver apenas 1 responsável, não permitir exclusão
        if ($result['total'] <= 1) {
            return ['success' => false, 'message' => 'É obrigatório ter pelo menos um responsável legal cadastrado.'];
        }

        // Excluir responsável
        $stmt = $this->conn->prepare("DELETE FROM responsaveis_legais WHERE id = ?");
        $stmt->bind_param("i", $responsavel_id);
        $stmt->execute();

        return ['success' => true];
    }

    public function findByEstabelecimentoId($estabelecimento_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM responsaveis_legais WHERE estabelecimento_id = ?");
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}


?>
