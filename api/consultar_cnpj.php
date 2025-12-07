<?php
/**
 * Proxy para consulta de CNPJ na API GovNex
 * Evita problemas de CORS ao fazer a requisição do lado do servidor
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se o CNPJ foi fornecido
if (!isset($_GET['cnpj']) && !isset($_POST['cnpj'])) {
    echo json_encode([
        'error' => true,
        'message' => 'CNPJ não fornecido'
    ]);
    exit;
}

$cnpj = $_GET['cnpj'] ?? $_POST['cnpj'];

// Limpar o CNPJ (remover caracteres não numéricos)
$cnpj = preg_replace('/[^0-9]/', '', $cnpj);

// Validar CNPJ
if (strlen($cnpj) !== 14) {
    echo json_encode([
        'error' => true,
        'message' => 'CNPJ inválido. Deve conter 14 dígitos.'
    ]);
    exit;
}

// Token da API GovNex
$token = "8ab984d986b155d84b4f88dec6d4f8c3cd2e11c685d9805107df78e94ab488ca";

// Tentar primeiro com HTTPS
$url = "https://govnex.site/govnex/api/cnpj_api.php?cnpj={$cnpj}&token={$token}";

// Log para debug (remover em produção)
error_log("Consultando CNPJ: {$cnpj} na URL: {$url}");

// Iniciar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desabilitar verificação do certificado
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Desabilitar verificação do host
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Enviar headers incluindo o domínio de origem correto
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'X-API-Token: ' . $token,
    'Content-Type: application/json',
    'Origin: https://infovisa.gurupi.to.gov.br',
    'Referer: https://infovisa.gurupi.to.gov.br/'
]);

// Executar requisição
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

curl_close($ch);

// Log detalhado para debug
error_log("Resposta da API - Código: {$httpCode}, URL Efetiva: {$effectiveUrl}, Resposta: " . substr($response, 0, 200));

// Se retornou 402, 301 ou erro SSL, tentar com HTTP
if ($httpCode === 402 || $httpCode === 301 || $curlErrno === 60 || $curlErrno === 77) {
    error_log("Tentando com HTTP ao invés de HTTPS... (Erro anterior: {$curlError})");
    $url = "http://govnex.site/govnex/api/cnpj_api.php?cnpj={$cnpj}&token={$token}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Enviar headers incluindo o domínio de origem correto
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'X-API-Token: ' . $token,
        'Content-Type: application/json',
        'Origin: https://infovisa.gurupi.to.gov.br',
        'Referer: https://infovisa.gurupi.to.gov.br/'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    
    curl_close($ch);
    
    error_log("Resultado com HTTP: Código {$httpCode}");
}

// Verificar se houve erro na requisição
if ($curlErrno) {
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao consultar API: ' . $curlError,
        'code' => $curlErrno
    ]);
    exit;
}

// Verificar código HTTP
if ($httpCode !== 200) {
    $errorMessage = 'API retornou código HTTP ' . $httpCode;
    
    // Mensagens específicas para códigos HTTP comuns
    switch ($httpCode) {
        case 402:
            // Tentar decodificar a resposta para ver se há mais informações
            $responseData = json_decode($response, true);
            
            if ($responseData && isset($responseData['message'])) {
                $detalhes = $responseData['message'];
            } elseif ($responseData && isset($responseData['error'])) {
                $detalhes = $responseData['error'];
            } elseif (!empty($response)) {
                $detalhes = $response;
            } else {
                $detalhes = 'A API retornou código 402. Possíveis causas: Token inválido, conta suspensa, ou problema com a configuração da API.';
            }
            
            $errorMessage = '⚠️ Erro 402 da API GovNex: ' . $detalhes;
            
            // Log para debug
            error_log("Erro 402 - Resposta completa: " . $response);
            error_log("Erro 402 - Token usado: " . substr($token, 0, 20) . "...");
            error_log("Erro 402 - CNPJ consultado: " . $cnpj);
            break;
        case 401:
            $errorMessage = 'Token de autenticação inválido ou expirado';
            break;
        case 404:
            $errorMessage = 'CNPJ não encontrado na base de dados da Receita Federal';
            break;
        case 429:
            $errorMessage = 'Limite de requisições excedido. Aguarde alguns minutos e tente novamente';
            break;
        case 500:
        case 502:
        case 503:
            $errorMessage = 'Servidor da API temporariamente indisponível. Tente novamente em alguns minutos';
            break;
    }
    
    $errorResponse = [
        'error' => true,
        'message' => $errorMessage,
        'code' => $httpCode
    ];
    
    // Se for erro 402, adicionar mais informações de debug
    if ($httpCode === 402) {
        $errorResponse['debug'] = [
            'raw_response' => substr($response, 0, 500),
            'token_prefix' => substr($token, 0, 20) . '...',
            'cnpj' => $cnpj,
            'url' => $url
        ];
    }
    
    echo json_encode($errorResponse);
    exit;
}

// Verificar se a resposta está vazia
if (empty($response)) {
    echo json_encode([
        'error' => true,
        'message' => 'API retornou resposta vazia'
    ]);
    exit;
}

// Tentar decodificar JSON
$result = json_decode($response, true);

// Verificar se houve erro na decodificação
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao decodificar resposta da API: ' . json_last_error_msg(),
        'raw_response' => substr($response, 0, 500)
    ]);
    exit;
}

// Retornar a resposta da API
echo json_encode($result);
?>
