<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user'])) {
    echo "<h1>Você precisa estar logado</h1>";
    exit();
}

// Lista de caminhos a verificar
$paths = [
    '../../uploads/screenshots/',
    $_SERVER['DOCUMENT_ROOT'] . '/visamunicipal/uploads/screenshots/',
    realpath('../../uploads/screenshots/') . '/',
    'C:/wamp/www/visamunicipal/uploads/screenshots/'
];

echo "<h1>Teste de Acesso a Screenshots</h1>";

// Checar diretórios
echo "<h2>Verificação de Diretórios</h2>";
echo "<ul>";
foreach ($paths as $path) {
    echo "<li>";
    echo "Caminho: <strong>" . htmlspecialchars($path) . "</strong><br>";
    echo "Existe: <strong>" . (is_dir($path) ? 'SIM' : 'NÃO') . "</strong><br>";
    echo "Leitura: <strong>" . (is_readable($path) ? 'SIM' : 'NÃO') . "</strong><br>";
    echo "Escrita: <strong>" . (is_writable($path) ? 'SIM' : 'NÃO') . "</strong><br>";
    
    // Tentar listar arquivos
    if (is_dir($path) && is_readable($path)) {
        echo "Arquivos: <br>";
        echo "<ul>";
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            echo "<li>" . htmlspecialchars($file) . " - "
                . filesize($path . $file) . " bytes</li>";
        }
        echo "</ul>";
    }
    echo "</li>";
}
echo "</ul>";

// Tentar criar um arquivo de teste
echo "<h2>Teste de Criação de Arquivo</h2>";
$testFile = 'screenshot_test_' . time() . '.png';

foreach ($paths as $path) {
    if (is_dir($path) && is_writable($path)) {
        $fullPath = $path . $testFile;
        $testImage = file_get_contents('https://via.placeholder.com/150'); // Imagem de teste
        
        echo "<p>Tentando criar arquivo <strong>" . htmlspecialchars($fullPath) . "</strong>... ";
        if (file_put_contents($fullPath, $testImage)) {
            echo "<span style='color:green'>SUCESSO!</span></p>";
            echo "<p>Você pode acessar a imagem teste em: <a href='ver_screenshot.php?arquivo=" . urlencode($testFile) . "'>Ver Imagem Teste</a></p>";
            break; // Parar após criar um arquivo com sucesso
        } else {
            echo "<span style='color:red'>FALHA!</span></p>";
        }
    }
}

// Informações do PHP
echo "<h2>Informações do PHP</h2>";
echo "<ul>";
echo "<li>open_basedir: " . ini_get('open_basedir') . "</li>";
echo "<li>upload_tmp_dir: " . ini_get('upload_tmp_dir') . "</li>";
echo "<li>upload_max_filesize: " . ini_get('upload_max_filesize') . "</li>";
echo "<li>Diretório atual: " . getcwd() . "</li>";
echo "<li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "</ul>"; 