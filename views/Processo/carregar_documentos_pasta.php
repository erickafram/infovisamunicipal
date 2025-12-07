<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../models/Documento.php';
require_once '../../models/Arquivo.php';
require_once '../../models/PastaDocumento.php';

// Verificar se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Requisição inválida']);
    exit();
}

// Verificar parâmetros
if (!isset($_GET['processo_id']) || !isset($_GET['pasta'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros obrigatórios não fornecidos']);
    exit();
}

$processoId = intval($_GET['processo_id']);
$pastaId = $_GET['pasta'];

$processoModel = new Processo($conn);
$documentoModel = new Documento($conn);
$arquivoModel = new Arquivo($conn);
$pastaDocumento = new PastaDocumento($conn);

// Verificar se o processo existe e se o usuário tem acesso
$dadosProcesso = $processoModel->findById($processoId);
if (!$dadosProcesso) {
    http_response_code(404);
    echo json_encode(['error' => 'Processo não encontrado']);
    exit();
}

// Verificar se o usuário está vinculado ao estabelecimento
$userId = $_SESSION['user']['id'];
$estabelecimentos = $processoModel->getEstabelecimentosByUsuario($userId);
$estabelecimentoIds = array_column($estabelecimentos, 'estabelecimento_id');

if (!in_array($dadosProcesso['estabelecimento_id'], $estabelecimentoIds) || $dadosProcesso['tipo_processo'] == 'DENÚNCIA') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit();
}

// Carregar documentos e arquivos
$documentos = $documentoModel->getDocumentosByProcesso($processoId);
$arquivos = $arquivoModel->getArquivosComAssinaturasCompletas($processoId);

// Garantir que são arrays
if (!is_array($documentos)) {
    $documentos = [];
}
if (!is_array($arquivos)) {
    $arquivos = [];
}

$itens = array_merge(
    array_map(function ($doc) {
        $doc['tipo'] = 'documento';
        return $doc;
    }, $documentos),
    array_map(function ($arq) {
        $arq['tipo'] = 'arquivo';
        return $arq;
    }, $arquivos)
);

usort($itens, function ($a, $b) {
    return strtotime($b['data_upload']) - strtotime($a['data_upload']);
});

// Carregar pastas do processo
$pastas = $pastaDocumento->getPastasByProcesso($processoId);

// Adicionar informação da pasta a cada item
foreach ($itens as &$item) {
    $pasta_item = $pastaDocumento->getItemPasta($item['tipo'], $item['id']);
    $item['pasta'] = $pasta_item;
}

// Filtrar itens com base na pasta
if ($pastaId === 'geral') {
    $itens_exibir = array_filter($itens, function($item) {
        return $item['pasta'] === false;
    });
    // Garantir que seja um array indexado
    $itens_exibir = array_values($itens_exibir);
} else {
    // Verificar se a pasta existe antes de buscar itens
    $pasta_existe = false;
    foreach ($pastas as $pasta) {
        if ($pasta['id'] == $pastaId) {
            $pasta_existe = true;
            break;
        }
    }
    
    if ($pasta_existe) {
        $itens_exibir = $pastaDocumento->getItensByPasta($pastaId);
        // Garantir que seja um array
        if (!is_array($itens_exibir)) {
            $itens_exibir = [];
        }
    } else {
        $itens_exibir = [];
    }
}

// Buscar informações da pasta se não for 'geral'
$pasta_info = null;
if ($pastaId !== 'geral') {
    foreach ($pastas as $pasta) {
        if ($pasta['id'] == $pastaId) {
            $pasta_info = $pasta;
            break;
        }
    }
}

// Garantir que itens_exibir seja um array válido antes de retornar

// Garantir que itens_exibir seja um array indexado
$itens_exibir = array_values((array) $itens_exibir);

// Preparar dados para retorno
$response = [
    'success' => true,
    'pasta_id' => $pastaId,
    'pasta_info' => $pasta_info,
    'itens' => $itens_exibir,
    'total_itens' => count($itens_exibir),
    'contadores' => [
        'geral' => count(array_filter($itens, function($item) {
            return $item['pasta'] === false;
        }))
    ]
];

// Adicionar contadores das pastas
foreach ($pastas as $pasta) {
    $response['contadores'][$pasta['id']] = count($pastaDocumento->getItensByPasta($pasta['id']));
}

header('Content-Type: application/json');
echo json_encode($response);
?> 