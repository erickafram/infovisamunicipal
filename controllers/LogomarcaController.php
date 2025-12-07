<?php
require_once '../../models/Logomarca.php';

class LogomarcaController {
    private $conn;
    private $logomarca;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->logomarca = new Logomarca($conn);
    }

    public function create() {
        session_start();
        $municipio = $_SESSION['user']['municipio'];
        $espacamento = $_POST['espacamento']; // Capturar o espaçamento enviado pelo formulário
        
        // Verifica se já existe uma logomarca para o município
        $existingLogomarca = $this->logomarca->getLogomarcaByMunicipio($municipio);
        if ($existingLogomarca) {
            header("Location: cadastrar_logomarca.php?error=Logomarca já cadastrada para este município.");
            exit();
        }

        if (isset($_FILES['logomarca']) && $_FILES['logomarca']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/logomarcas/';
            $uploadFile = $uploadDir . basename($_FILES['logomarca']['name']);
            
            if (move_uploaded_file($_FILES['logomarca']['tmp_name'], $uploadFile)) {
                $caminho_logomarca = $uploadFile;
                $this->logomarca->createLogomarca($municipio, $caminho_logomarca, $espacamento);
                header("Location: cadastrar_logomarca.php?success=Logomarca cadastrada com sucesso.");
            } else {
                header("Location: cadastrar_logomarca.php?error=Erro ao fazer upload da logomarca.");
            }
        } else {
            header("Location: cadastrar_logomarca.php?error=Arquivo de logomarca inválido.");
        }
    }

    public function update() {
        session_start();
        $municipio = $_SESSION['user']['municipio'];
        $espacamento = $_POST['espacamento']; // Capturar o espaçamento enviado pelo formulário

        if (isset($_FILES['logomarca']) && $_FILES['logomarca']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/logomarcas/';
            $uploadFile = $uploadDir . basename($_FILES['logomarca']['name']);
            
            if (move_uploaded_file($_FILES['logomarca']['tmp_name'], $uploadFile)) {
                $caminho_logomarca = $uploadFile;
                $this->logomarca->updateLogomarca($municipio, $caminho_logomarca, $espacamento);
                header("Location: listar_logomarcas.php?success=Logomarca atualizada com sucesso.");
            } else {
                header("Location: listar_logomarcas.php?error=Erro ao fazer upload da logomarca.");
            }
        } else {
            header("Location: listar_logomarcas.php?error=Arquivo de logomarca inválido.");
        }
    }
}

?>
