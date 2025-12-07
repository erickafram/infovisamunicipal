<?php
session_start();
require_once '../../conf/database.php';

// Verifica se os parâmetros de busca e sessão estão definidos
if (isset($_GET['search']) && isset($_SESSION['user']['municipio'])) {
    $search = "%" . $_GET['search'] . "%";
    $municipio = $_SESSION['user']['municipio'];

    // Consulta para buscar apenas estabelecimentos que possuem processos
    $query = "
        SELECT e.id, e.nome_fantasia, e.cnpj 
        FROM estabelecimentos e
        INNER JOIN processos p ON e.id = p.estabelecimento_id
        WHERE e.municipio = ? AND (e.nome_fantasia LIKE ? OR e.cnpj LIKE ?)
        GROUP BY e.id
        LIMIT 10
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $municipio, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<a href="#" class="block px-4 py-2 border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 estabelecimento-item" data-id="' . htmlspecialchars($row['id']) . '">' .
                '<div class="flex items-center">' .
                '<i class="fas fa-building text-gray-500 mr-2"></i>' .
                '<div>' .
                '<span class="font-medium text-gray-700">' . htmlspecialchars($row['nome_fantasia']) . '</span>' .
                '<span class="text-xs text-gray-500 ml-2">' . htmlspecialchars($row['cnpj']) . '</span>' .
                '</div>' .
                '</div>' .
                '</a>';
        }
    } else {
        echo '<div class="px-4 py-3 text-sm text-gray-700 bg-gray-50"><i class="fas fa-info-circle text-blue-500 mr-2"></i>Nenhum estabelecimento com processos encontrado</div>';
    }
} else {
    echo '<div class="px-4 py-3 text-sm text-red-700 bg-red-50"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>Erro na busca. Verifique os parâmetros.</div>';
}
