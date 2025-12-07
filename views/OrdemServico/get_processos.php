<?php
require_once '../../conf/database.php';

if (isset($_GET['estabelecimento_id'])) {
    $estabelecimento_id = $_GET['estabelecimento_id'];

    $query = "SELECT id, numero_processo, tipo_processo FROM processos WHERE estabelecimento_id = ? AND status = 'ATIVO'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $estabelecimento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $processos = $result->fetch_all(MYSQLI_ASSOC);

    if (count($processos) > 0) {
        foreach ($processos as $processo) {
            echo '<option value="' . htmlspecialchars($processo['id']) . '">' . htmlspecialchars($processo['numero_processo']) . ' - ' . htmlspecialchars($processo['tipo_processo']) . '</option>';
        }
    } else {
        echo '<option value="">Nenhum processo encontrado</option>';
    }
}
