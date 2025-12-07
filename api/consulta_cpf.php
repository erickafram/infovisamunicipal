<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Função para formatar a resposta JSON
function json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Recebe o CPF da requisição
$cpf = isset($_GET['cpf']) ? $_GET['cpf'] : '';

// Remove caracteres não numéricos
$cpf = preg_replace('/[^0-9]/', '', $cpf);

// Verifica se o CPF tem 11 dígitos
if (strlen($cpf) != 11) {
    json_response([
        'status' => 'error',
        'msg'    => 'CPF inválido. Deve conter 11 dígitos.',
    ], 400);
}

function consultaCPF($cpf) {
    $url = "https://api.dataget.site/api/v1/cpf/$cpf";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            "User-Agent: Mozilla/5.0 (Windows NT 12.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0",
            "Authorization: Bearer 0d21c8c1cf9bca5515949b26768252bc031d963cd2cce6618f126198a96a7519",
        ],
    ]);
    $result = curl_exec($ch);
    if ($result === false) {
        json_response(['status' => 'error', 'msg' => 'Falha ao consultar serviço externo.'], 502);
    }
    curl_close($ch);
    $resultToJSON = json_decode($result, true);
    if (isset($resultToJSON['CPF']) && !empty($resultToJSON['CPF'])) {
        $nome = $resultToJSON['NOME'] ?? 'Não informado';
        $nasc = $resultToJSON['NASC'] ?? 'Não informado';
        $nomeMae = $resultToJSON['NOME_MAE'] ?? 'Não informado';
        $nomePai = (isset($resultToJSON['NOME_PAI']) && strlen($resultToJSON['NOME_PAI']) > 2) ? $resultToJSON['NOME_PAI'] : 'Não informado';
        $sexo = $resultToJSON['SEXO'] ?? 'Não informado';
        
        json_response([
            'status'   => 'success',
            'cpf'      => $cpf,
            'nome'     => strtoupper($nome),
            'nasc'     => $nasc,
            'nomeMae'  => $nomeMae,
            'nomePai'  => $nomePai,
            'sexo'     => $sexo,
        ]);
    } else {
        json_response([
            'status' => 'error',
            'msg'    => 'CPF não encontrado',
        ], 404);
    }
}

// Executa a consulta
consultaCPF($cpf); 