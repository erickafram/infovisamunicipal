<?php
require_once 'conf/database.php';

echo "=== VERIFICANDO TABELA tipos_documentos ===\n\n";

// Verificar se a tabela existe
$result = $conn->query("SHOW TABLES LIKE 'tipos_documentos'");
if ($result->num_rows > 0) {
    echo "✓ Tabela tipos_documentos existe\n\n";
    
    // Contar registros
    $count = $conn->query("SELECT COUNT(*) as total FROM tipos_documentos")->fetch_assoc()['total'];
    echo "Total de registros: $count\n\n";
    
    // Mostrar alguns exemplos
    echo "Exemplos de documentos:\n";
    $stmt = $conn->query("SELECT codigo, nome FROM tipos_documentos WHERE codigo IN ('001', '002', '003', '004', '007', '008', '009', '010', '011', '012', '013', '018', '019', '020', '029') ORDER BY codigo");
    while ($row = $stmt->fetch_assoc()) {
        echo "Código: {$row['codigo']} - Nome: {$row['nome']}\n";
    }
} else {
    echo "✗ Tabela tipos_documentos NÃO existe!\n\n";
    
    // Verificar se existe a estrutura antiga
    echo "Verificando estrutura antiga (tabela relacao_documentos):\n";
    $result = $conn->query("SHOW TABLES LIKE 'relacao_documentos'");
    if ($result->num_rows > 0) {
        echo "✓ Tabela relacao_documentos existe\n\n";
        
        // Mostrar alguns exemplos
        echo "Exemplos de documentos:\n";
        $stmt = $conn->query("SELECT codigo, nome FROM relacao_documentos WHERE codigo IN ('001', '002', '003', '004', '007', '008', '009', '010', '011', '012', '013', '018', '019', '020', '029') ORDER BY codigo");
        while ($row = $stmt->fetch_assoc()) {
            echo "Código: {$row['codigo']} - Nome: {$row['nome']}\n";
        }
    }
}

echo "\n\n=== VERIFICANDO FUNÇÃO getTodosDocumentosBanco ===\n\n";
require_once 'includes/documentos_helper.php';
$docs = getTodosDocumentosBanco($conn);
echo "Total de documentos retornados pela função: " . count($docs) . "\n\n";

// Verificar documentos específicos
$codigos_teste = ['001', '002', '003', '004', '007', '008', '009', '010', '011', '012', '013', '018', '019', '020', '029'];
foreach ($codigos_teste as $codigo) {
    if (isset($docs[$codigo])) {
        echo "✓ $codigo: {$docs[$codigo]}\n";
    } else {
        echo "✗ $codigo: NÃO ENCONTRADO\n";
    }
}
?> 