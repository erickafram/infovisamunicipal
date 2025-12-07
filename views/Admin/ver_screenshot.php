<?php
session_start();
require_once '../../conf/database.php';

// Verificar autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

// Verificar se o arquivo foi especificado
if (!isset($_GET['arquivo']) || empty($_GET['arquivo'])) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 - Arquivo não encontrado</h1>";
    echo "<p>O parâmetro 'arquivo' não foi especificado na URL.</p>";
    exit();
}

// Validar o nome do arquivo para evitar vulnerabilidades de segurança
$arquivo = basename($_GET['arquivo']);

// Verificar se o nome do arquivo segue o padrão esperado
if (!preg_match('/^screenshot_[a-zA-Z0-9]+\.png$/', $arquivo)) {
    header("HTTP/1.0 403 Forbidden");
    echo "<h1>403 - Acesso negado</h1>";
    echo "<p>O nome do arquivo '" . htmlspecialchars($arquivo) . "' não segue o padrão esperado.</p>";
    exit();
}

// Verificar diversos caminhos possíveis para o arquivo
$possiblePaths = [
    '../../uploads/screenshots/' . $arquivo,                        // Caminho relativo padrão
    $_SERVER['DOCUMENT_ROOT'] . '/visamunicipal/uploads/screenshots/' . $arquivo, // Caminho absoluto
    realpath('../../uploads/screenshots/') . '/' . $arquivo,         // Caminho real resolvido
    'C:/wamp/www/visamunicipal/uploads/screenshots/' . $arquivo      // Caminho explicito
];

$found = false;
$caminho = null;

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $caminho = $path;
        $found = true;
        break;
    }
}

// Verificar se o arquivo existe
if (!$found) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 - Imagem não encontrada</h1>";
    echo "<p>O arquivo '" . htmlspecialchars($arquivo) . "' não foi encontrado no servidor.</p>";
    
    echo "<p>Tentei os seguintes caminhos:</p>";
    echo "<ul>";
    foreach ($possiblePaths as $path) {
        echo "<li>" . htmlspecialchars($path) . " - " . (file_exists($path) ? 'Existe' : 'Não existe') . "</li>";
    }
    echo "</ul>";
    
    echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
    echo "<p>Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
    echo "<p>Diretório atual: " . dirname(__FILE__) . "</p>";
    echo "<p>Diretório de screenshots: " . realpath('../../uploads/screenshots') . "</p>";
    
    // Listar arquivos no diretório de screenshots
    echo "<p>Arquivos no diretório de screenshots:</p>";
    echo "<ul>";
    $dirs = [
        '../../uploads/screenshots/',
        $_SERVER['DOCUMENT_ROOT'] . '/visamunicipal/uploads/screenshots/',
        'C:/wamp/www/visamunicipal/uploads/screenshots/'
    ];
    
    foreach ($dirs as $dir) {
        echo "<li>Verificando diretório: " . htmlspecialchars($dir) . "</li>";
        echo "<ul>";
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    echo "<li>" . htmlspecialchars($file) . "</li>";
                }
                closedir($dh);
            } else {
                echo "<li>Não foi possível abrir o diretório</li>";
            }
        } else {
            echo "<li>Diretório não existe ou não é acessível</li>";
        }
        echo "</ul>";
    }
    echo "</ul>";
    
    exit();
}

// Obter informações do arquivo
$info = @getimagesize($caminho);
if ($info === false) {
    header("HTTP/1.0 415 Unsupported Media Type");
    echo "<h1>415 - Formato de imagem não suportado</h1>";
    echo "<p>O arquivo '" . htmlspecialchars($arquivo) . "' não é uma imagem válida.</p>";
    echo "<p>Caminho: " . htmlspecialchars($caminho) . "</p>";
    echo "<p>Tamanho do arquivo: " . (file_exists($caminho) ? filesize($caminho) . " bytes" : "arquivo não existe") . "</p>";
    exit();
}

// Mime type da imagem
$mimeType = $info['mime'];

// Verificar se é uma imagem válida
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
    header("HTTP/1.0 415 Unsupported Media Type");
    echo "<h1>415 - Formato de imagem não suportado</h1>";
    echo "<p>O tipo MIME '" . htmlspecialchars($mimeType) . "' não é suportado.</p>";
    exit();
}

// Se for para exibir a imagem diretamente
if (isset($_GET['raw']) && $_GET['raw'] == '1') {
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($caminho));
    readfile($caminho);
    exit();
}

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Screenshot</title>
    <style>
        .screenshot-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .screenshot-image {
            max-width: 100%;
            height: auto;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-image me-2"></i> Visualizar Screenshot
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Esta captura de tela foi enviada junto com um relato de problema ou sugestão.
                </div>
                
                <div class="screenshot-container">
                    <img src="?arquivo=<?= htmlspecialchars($arquivo) ?>&raw=1" alt="Screenshot" class="screenshot-image">
                </div>
                
                <div class="controls mt-3">
                    <a href="?arquivo=<?= htmlspecialchars($arquivo) ?>&raw=1" class="btn btn-primary" download="<?= htmlspecialchars($arquivo) ?>">
                        <i class="fas fa-download me-2"></i> Baixar Imagem
                    </a>
                    <a href="javascript:history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 