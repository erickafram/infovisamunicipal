<?php
require_once '../../database.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Conexão falhou: " . $conn->connect_error);
}

$mensagem = $_POST['mensagem'];
$destinatario = $_POST['destinatario'];

$sql = "INSERT INTO mensagens (mensagem, destinatario) VALUES ('$mensagem', '$destinatario')";
if ($conn->query($sql) === TRUE) {
  echo json_encode(array("mensagem" => "Mensagem enviada com sucesso!"));
} else {
  echo json_encode(array("mensagem" => "Erro ao enviar mensagem: " . $conn->error));
}

$conn->close();
?>
