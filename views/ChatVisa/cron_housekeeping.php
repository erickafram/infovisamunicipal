<?php
// cron_housekeeping.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui a conexão com o banco de dados
require_once __DIR__ . '/../../conf/database.php';

echo "Iniciando o cron job...\n";

// 1. Atualiza os registros inativos: define logout_time para NOW()
//    se o usuário não tiver atividade há mais de 5 minutos (ou se last_activity for NULL)
$query_update = "
    UPDATE usuarios_online 
    SET logout_time = NOW(), 
        last_activity = IF(last_activity IS NULL, NOW(), last_activity)
    WHERE logout_time IS NULL 
      AND (last_activity IS NULL OR last_activity < (NOW() - INTERVAL 5 MINUTE))
";

if ($conn->query($query_update) === TRUE) {
    echo date("Y-m-d H:i:s") . " - Housekeeping (atualização) executado com sucesso.\n";
} else {
    echo date("Y-m-d H:i:s") . " - Erro ao executar housekeeping (atualização): " . $conn->error . "\n";
}

// 2. Apaga registros antigos que já foram finalizados e cuja logout_time é antiga  
//    (por exemplo, registros com logout_time há mais de 1 hora)
$query_delete = "
    DELETE FROM usuarios_online 
    WHERE logout_time IS NOT NULL 
      AND logout_time < (NOW() - INTERVAL 1 HOUR)
";

if ($conn->query($query_delete) === TRUE) {
    echo date("Y-m-d H:i:s") . " - Registros antigos apagados com sucesso.\n";
} else {
    echo date("Y-m-d H:i:s") . " - Erro ao apagar registros antigos: " . $conn->error . "\n";
}

$conn->close();
?>
