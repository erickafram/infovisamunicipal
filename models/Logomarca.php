<?php
class Logomarca {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAllLogomarcas() {
        $stmt = $this->conn->prepare("SELECT * FROM logomarcas");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function createLogomarca($municipio, $caminho_logomarca, $espacamento) {
        $stmt = $this->conn->prepare("INSERT INTO logomarcas (municipio, caminho_logomarca, espacamento) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $municipio, $caminho_logomarca, $espacamento);
        return $stmt->execute();
    }

    public function updateLogomarca($municipio, $caminho_logomarca, $espacamento) {
        $stmt = $this->conn->prepare("UPDATE logomarcas SET caminho_logomarca = ?, espacamento = ? WHERE municipio = ?");
        $stmt->bind_param("sis", $caminho_logomarca, $espacamento, $municipio);
        return $stmt->execute();
    }

    public function deleteLogomarca($municipio) {
        $stmt = $this->conn->prepare("DELETE FROM logomarcas WHERE municipio = ?");
        $stmt->bind_param("s", $municipio);
        return $stmt->execute();
    }

    public function getLogomarcaByMunicipio($municipio) {
        $stmt = $this->conn->prepare("SELECT * FROM logomarcas WHERE municipio = ?");
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getLogomarcaByUserMunicipio($municipio) {
        $stmt = $this->conn->prepare("SELECT * FROM logomarcas WHERE municipio = ?");
        $stmt->bind_param("s", $municipio);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

?>
