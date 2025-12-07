<?php
require_once '../../conf/database.php';

// Verificar sessão do usuário (opcional, dependendo dos requisitos de segurança)
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 1) {
    echo "Acesso não autorizado.";
    exit;
}

// Script para limpar visualizações incorretas
echo "<h2>Limpando visualizações incorretas...</h2>";
echo "<pre>";

// Encontrar todas as visualizações de usuários externos
$sql = "
    SELECT 
        lv.id as log_id,
        lv.usuario_id,
        lv.arquivo_id,
        lv.data_visualizacao,
        a.processo_id,
        p.estabelecimento_id,
        ue.id as vinculo_id
    FROM 
        log_visualizacoes lv
    JOIN 
        usuarios_externos ux ON lv.usuario_id = ux.id
    JOIN 
        arquivos a ON lv.arquivo_id = a.id
    JOIN 
        processos p ON a.processo_id = p.id
    LEFT JOIN 
        usuarios_estabelecimentos ue ON ux.id = ue.usuario_id AND p.estabelecimento_id = ue.estabelecimento_id
    WHERE 
        ue.id IS NULL
";

$result = $conn->query($sql);
$count = 0;

if ($result->num_rows > 0) {
    echo "Encontradas " . $result->num_rows . " visualizações incorretas:\n\n";
    
    // Preparar a consulta de exclusão
    $delete_stmt = $conn->prepare("DELETE FROM log_visualizacoes WHERE id = ?");
    
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['log_id'] . 
             " | Usuário: " . $row['usuario_id'] . 
             " | Arquivo: " . $row['arquivo_id'] . 
             " | Data: " . $row['data_visualizacao'] . 
             " | Estabelecimento: " . $row['estabelecimento_id'] . "\n";
        
        // Excluir a visualização incorreta
        $delete_stmt->bind_param("i", $row['log_id']);
        if ($delete_stmt->execute()) {
            $count++;
        } else {
            echo "ERRO ao excluir registro " . $row['log_id'] . ": " . $conn->error . "\n";
        }
    }
    
    echo "\n" . $count . " visualizações incorretas foram removidas com sucesso.";
} else {
    echo "Não foram encontradas visualizações incorretas no sistema.";
}

echo "</pre>";
echo "<p><a href='javascript:history.back()'>Voltar</a></p>"; 