<?php
header('Content-Type: application/json');

if ($_FILES['file']['name']) {
    if (!$_FILES['file']['error']) {
        $name = md5(rand(100, 200));
        $ext = explode('.', $_FILES['file']['name']);
        $filename = $name . '.' . end($ext);
        $destination = '../../uploads/' . $filename; // mude o caminho conforme necessário
        $location = $_FILES['file']['tmp_name'];

        if (move_uploaded_file($location, $destination)) {
            echo json_encode(['location' => $destination]);
        } else {
            echo json_encode(['error' => 'Falha ao mover o arquivo.']);
        }
    } else {
        echo json_encode(['error' => 'Erro no upload: ' . $_FILES['file']['error']]);
    }
} else {
    echo json_encode(['error' => 'Nenhum arquivo enviado.']);
}
?>
