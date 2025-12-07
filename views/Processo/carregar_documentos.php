<?php
session_start();

require_once '../../conf/database.php';

// Verifica se o tipo de processo foi enviado
$tipo_processo = $_POST['tipo_processo'] ?? null;
if (!$tipo_processo) {
    echo "Tipo de processo não especificado.";
    exit();
}

// Consulta para buscar os serviços e documentos agrupados pelo tipo de serviço
$stmt = $conn->prepare("
    SELECT ts.nome AS servico_nome, ts.descricao AS servico_descricao, d.nome AS documento_nome
    FROM tipo_servico ts
    JOIN servico_documento sd ON ts.id = sd.tipo_servico_id
    JOIN documento d ON sd.documento_id = d.id
    WHERE ts.tipo_processo = ?
    ORDER BY ts.nome, d.nome
");
$stmt->bind_param("s", $tipo_processo);
$stmt->execute();
$result = $stmt->get_result();

// Organiza os documentos em um array agrupado por tipo de serviço
$servicosDocumentos = [];
while ($row = $result->fetch_assoc()) {
    $servicosDocumentos[$row['servico_nome']][] = [
        'documento_nome' => $row['documento_nome']
    ];
}

$stmt->close();

// Gera o layout para exibir os documentos agrupados
if (count($servicosDocumentos) > 0) {
    echo "<h5>Documentos para o processo de " . htmlspecialchars($tipo_processo) . ":</h5>";
    foreach ($servicosDocumentos as $servicoNome => $documentos) {
        echo "<div class='card mt-3'>";
        echo "<div class='card-header bg-primary text-white'><strong>Serviço: " . htmlspecialchars($servicoNome) . "</strong></div>";
        echo "<div class='card-body'>";
        echo "<ul style='list-style-type: none; padding-left: 0; margin: 0;'>";
        foreach ($documentos as $documento) {
            echo "<li style='padding: 5px 0;'>" . htmlspecialchars($documento['documento_nome']) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
        echo "</div>";
    }
} else {
    echo "<p class='text-muted'>Nenhum documento encontrado para o processo de " . htmlspecialchars($tipo_processo) . ".</p>";
}

$conn->close();
