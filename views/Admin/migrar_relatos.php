<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';

// Verifica se o usuário está logado como administrador
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['perfil']) || $_SESSION['user']['perfil'] !== 'ADMIN') {
    header("Location: ../../login.php");
    exit();
}

// Verifica se já existe a coluna resposta na tabela
$checkColumnStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'resposta'");
$checkColumnStmt->execute();
$columnExists = ($checkColumnStmt->get_result()->num_rows > 0);

$success = false;
$error = null;

if (!$columnExists) {
    try {
        // Adicionar as novas colunas para suportar respostas aos relatos
        $migrateStmt = $conn->prepare("
            ALTER TABLE relatos_usuarios 
            ADD COLUMN resposta TEXT NULL, 
            ADD COLUMN data_resposta DATETIME NULL, 
            ADD COLUMN admin_id INT NULL
        ");
        
        if ($migrateStmt->execute()) {
            $success = true;
            $_SESSION['mensagem'] = [
                'tipo' => 'success',
                'texto' => 'Migração concluída com sucesso! O sistema agora suporta respostas aos relatos.'
            ];
        } else {
            $error = "Erro ao executar a migração: " . $conn->error;
            $_SESSION['mensagem'] = [
                'tipo' => 'danger',
                'texto' => $error
            ];
        }
    } catch (Exception $e) {
        $error = "Erro ao executar a migração: " . $e->getMessage();
        $_SESSION['mensagem'] = [
            'tipo' => 'danger',
            'texto' => $error
        ];
    }
} else {
    $_SESSION['mensagem'] = [
        'tipo' => 'info',
        'texto' => 'A migração já foi realizada anteriormente.'
    ];
}

// Redireciona de volta para a lista de relatos
header("Location: listar_relatos.php");
exit();
?> 