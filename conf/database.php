<?php
$servername = "localhost";
$username = "semus";
$password = "Semus@#2125/";
$dbname = "infovisa";

// Cria a conexão

$conn = new mysqli($servername, $username, $password, $dbname);


// Verifica a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
