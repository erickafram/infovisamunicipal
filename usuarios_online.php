<?php
require_once '../../database.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Conexão falhou: " . $conn->connect_error);
}

$sql = "SELECT nome_completo FROM usuarios WHERE status = 'ativo'";
$result = $conn->query($sql);

$usuarios = array();
if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
  }
}

echo json_encode($usuarios);

$conn->close();
?>
