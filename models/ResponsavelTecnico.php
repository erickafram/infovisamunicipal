<?php
class ResponsavelTecnico {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function findByCpf($cpf) {
        $stmt = $this->conn->prepare("SELECT * FROM responsaveis_tecnicos WHERE cpf = ?");
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($estabelecimento_id, $nome, $cpf, $email, $telefone, $conselho, $numero_registro_conselho, $carteirinha_conselho) {
        $stmt = $this->conn->prepare("INSERT INTO responsaveis_tecnicos (estabelecimento_id, nome, cpf, email, telefone, conselho, numero_registro_conselho, carteirinha_conselho) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $estabelecimento_id, $nome, $cpf, $email, $telefone, $conselho, $numero_registro_conselho, $carteirinha_conselho);
        $stmt->execute();
    }

    public function getByEstabelecimento($estabelecimento_id) {
        $stmt = $this->conn->prepare("SELECT * FROM responsaveis_tecnicos WHERE estabelecimento_id = ?");
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function update($responsavel_id, $nome, $cpf, $email, $telefone, $conselho, $numero_registro_conselho, $carteirinha_conselho) {
        $stmt = $this->conn->prepare("UPDATE responsaveis_tecnicos SET nome = ?, cpf = ?, email = ?, telefone = ?, conselho = ?, numero_registro_conselho = ?, carteirinha_conselho = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $nome, $cpf, $email, $telefone, $conselho, $numero_registro_conselho, $carteirinha_conselho, $responsavel_id);
        $stmt->execute();
    }

    public function delete($responsavel_id) {
        $stmt = $this->conn->prepare("DELETE FROM responsaveis_tecnicos WHERE id = ?");
        $stmt->bind_param("i", $responsavel_id);
        $stmt->execute();
    }

    public function findByEstabelecimentoId($estabelecimento_id) {
        $stmt = $this->conn->prepare("SELECT * FROM responsaveis_tecnicos WHERE estabelecimento_id = ?");
        $stmt->bind_param("i", $estabelecimento_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

?>
