<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Verificar se o usuário está logado
if (!isset($_SESSION['user'])) {
    echo "<h1>Você precisa estar logado</h1>";
    exit();
}

// Verificar se o arquivo foi especificado
if (!isset($_GET['arquivo']) || empty($_GET['arquivo'])) {
    echo "<h1>Arquivo não especificado</h1>";
    exit();
}

$arquivo = basename($_GET['arquivo']);

// Lista de caminhos possíveis
$paths = [
    '../../uploads/screenshots/' . $arquivo,
    $_SERVER['DOCUMENT_ROOT'] . '/visamunicipal/uploads/screenshots/' . $arquivo,
    realpath('../../uploads/screenshots/') . '/' . $arquivo,
    'C:/wamp/www/visamunicipal/uploads/screenshots/' . $arquivo
];

$found = false;
$caminho = null;

foreach ($paths as $path) {
    if (file_exists($path)) {
        $caminho = $path;
        $found = true;
        break;
    }
}

if (!$found) {
    echo "<h1>Imagem não encontrada</h1>";
    echo "<p>Caminhos verificados:</p>";
    echo "<ul>";
    foreach ($paths as $path) {
        echo "<li>" . $path . " - " . (file_exists($path) ? 'Existe' : 'Não existe') . "</li>";
    }
    echo "</ul>";
    exit();
}

// Verificar tipo da imagem
$fileinfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $fileinfo->file($caminho);

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
    echo "<h1>Arquivo não é uma imagem válida</h1>";
    echo "<p>MIME detectado: " . $mime . "</p>";
    exit();
}

// Apenas para debug - mostrar a imagem e o HTML
if (isset($_GET['debug'])) {
    echo "<h1>Informações da Imagem</h1>";
    echo "<p>Caminho: " . $caminho . "</p>";
    echo "<p>Tamanho: " . filesize($caminho) . " bytes</p>";
    echo "<p>MIME: " . $mime . "</p>";
    echo "<img src='?arquivo=" . urlencode($arquivo) . "' alt='Imagem' style='max-width:100%;'>";
    exit();
}

// Exibir a imagem diretamente
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: max-age=86400');
readfile($caminho);
exit(); 