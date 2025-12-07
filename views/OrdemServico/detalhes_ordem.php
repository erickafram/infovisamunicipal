<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

$ordemServico = new OrdemServico($conn);

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "ID da ordem de serviço não fornecido!";
    header("Location: listar_ordens.php");
    exit();
}

$id = $_GET['id'];

// Verificar se o usuário tem permissão para acessar esta ordem baseado no município
$municipioUsuario = $_SESSION['user']['municipio'];
if (!$ordemServico->podeAcessarOrdem($id, $municipioUsuario)) {
    $_SESSION['error_message'] = "Acesso negado. Você não tem permissão para visualizar esta ordem de serviço.";
    header("Location: listar_ordens.php");
    exit();
}

$ordem = $ordemServico->getOrdemById($id);

if (!$ordem) {
    $_SESSION['error_message'] = "Ordem de serviço não encontrada!";
    header("Location: listar_ordens.php");
    exit();
}

$tecnicos_ids = json_decode($ordem['tecnicos'] ?? '[]', true);
$nomes_tecnicos = $ordemServico->getTecnicosNomes($tecnicos_ids);

$acoes_ids = json_decode($ordem['acoes_executadas'] ?? '[]', true);
$acoes_nomes = $ordemServico->getAcoesNomes($acoes_ids);
$acoes_executadas_nomes = [];
if (is_array($acoes_ids)) {
    foreach ($acoes_ids as $acao_id) {
        if (isset($acoes_nomes[$acao_id])) {
            $acoes_executadas_nomes[] = $acoes_nomes[$acao_id];
        }
    }
}

// --- Funções Auxiliares ---
function formatDate($date)
{
    // ... (função existente) ...
    if (empty($date)) return 'N/A';
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('d/m/Y');
    } catch (Exception $e) {
        return 'Data inválida';
    }
}

function formatOSNumber($id, $date)
{
    // ... (função existente) ...
    if (empty($id) || empty($date)) return 'N/A';
    try {
        return htmlspecialchars($id . '.' . date('Y', strtotime($date)));
    } catch (Exception $e) {
        return htmlspecialchars($id) . '.????';
    }
}

function getStatusBadgeClass($status)
{
    // Função original para Bootstrap
    $status = strtolower($status ?? '');
    if ($status === 'ativa' || $status === 'em andamento') {
        return 'bg-success bg-opacity-75 text-white';
    } elseif ($status === 'finalizada' || $status === 'concluída') {
        return 'bg-primary bg-opacity-75 text-white';
    } elseif ($status === 'cancelada') {
        return 'bg-danger bg-opacity-75 text-white';
    } elseif ($status === 'pendente') {
        return 'bg-warning bg-opacity-75 text-dark';
    }
    return 'bg-secondary bg-opacity-50 text-dark';
}

function getStatusBadgeClassTailwind($status)
{
    // Nova função para Tailwind CSS
    $status = strtolower($status ?? '');
    if ($status === 'ativa' || $status === 'em andamento') {
        return 'bg-green-100 text-green-800';
    } elseif ($status === 'finalizada' || $status === 'concluída') {
        return 'bg-blue-100 text-blue-800';
    } elseif ($status === 'cancelada') {
        return 'bg-red-100 text-red-800';
    } elseif ($status === 'pendente') {
        return 'bg-yellow-100 text-yellow-800';
    }
    return 'bg-gray-100 text-gray-800';
}

// Função para geocodificar endereço usando Nominatim (OpenStreetMap - API gratuita)
function geocodificarEndereco($endereco, $nomeEstabelecimento = '') {
    // Criar uma consulta de pesquisa combinando nome e endereço, se disponíveis
    $consulta = '';
    if (!empty($nomeEstabelecimento) && !empty($endereco)) {
        $consulta = $nomeEstabelecimento . ', ' . $endereco;
    } elseif (!empty($endereco)) {
        $consulta = $endereco;
    } elseif (!empty($nomeEstabelecimento)) {
        $consulta = $nomeEstabelecimento;
    } else {
        return false;
    }
    
    // Preparar a URL da API do Nominatim
    $consulta = urlencode($consulta);
    $url = "https://nominatim.openstreetmap.org/search?q={$consulta}&format=json&limit=1&addressdetails=1";
    
    // Configurar o contexto da requisição com um user-agent personalizado
    // (Isso é uma boa prática e requerido pelo Nominatim)
    $options = [
        'http' => [
            'header' => "User-Agent: VisamunicipalApp/1.0\r\n"
        ]
    ];
    $context = stream_context_create($options);
    
    // Fazer a requisição
    $response = @file_get_contents($url, false, $context);
    
    // Verificar se obtivemos uma resposta
    if ($response === false) {
        return false;
    }
    
    // Decodificar a resposta JSON
    $data = json_decode($response, true);
    
    // Verificar se encontramos resultados
    if (empty($data) || !isset($data[0]['lat']) || !isset($data[0]['lon'])) {
        return false;
    }
    
    // Retornar as coordenadas encontradas
    return [
        'lat' => $data[0]['lat'],
        'lon' => $data[0]['lon'],
        'display_name' => $data[0]['display_name'] ?? '',
        'address' => $data[0]['address'] ?? []
    ];
}

$enderecoCompleto = htmlspecialchars($ordem['endereco'] ?? '');
$enderecoUrl = urlencode($ordem['endereco'] ?? '');

// Extrair componentes do endereço para busca mais precisa
$endereco = $ordem['endereco'] ?? '';
$cep = '';
$numero = '';
$rua = '';
$bairro = '';
$cidade = '';
$estado = '';

// Extrair CEP (formato brasileiro: 12345-678 ou 12345678)
if (preg_match('/\b(\d{5}-?\d{3})\b/', $endereco, $matches)) {
    $cep = $matches[1];
}

// Extrair número (padrões comuns em endereços brasileiros)
if (preg_match('/(?:,\s*|\s+)(?:n[º°\.]?\s*)?(\d+)(?:,|\s|$)/i', $endereco, $matches)) {
    $numero = $matches[1];
}

// Extrair rua (considerando vários prefixos comuns de logradouros brasileiros)
$padroes_rua = array(
    '/\b(?:R(?:ua)?\.?\s+)([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i',
    '/\b(?:Av(?:enida)?\.?\s+)([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i',
    '/\b(?:Al(?:ameda)?\.?\s+)([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i',
    '/\b(?:Pç(?:a)?\.?\s+|Praça\s+)([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i',
    '/\b(?:Tv\.?|Travessa\s+)([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i',
    '/\b(?:Rod(?:ovia)?\.?\s+)([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i',
    '/\b(?:Est(?:rada)?\.?\s+)([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i',
    '/^([^,\d]+?)(?:,|\s+n|\s+\d+|$)/i'  // Caso não tenha prefixo, tenta pegar o início até a vírgula ou número
);

foreach ($padroes_rua as $padrao) {
    if (preg_match($padrao, $endereco, $matches)) {
        $rua = trim($matches[1]);
        break;
    }
}

// Extrair bairro (geralmente após "Bairro", "B." ou entre vírgulas)
if (preg_match('/(?:Bairro|B\.)\s+([^,]+)/i', $endereco, $matches)) {
    $bairro = trim($matches[1]);
} elseif (preg_match('/,\s*([^,]+?)\s*,\s*[A-Z][A-Za-z\s]+\s*(?:-|,|\s+\d{5})/i', $endereco, $matches)) {
    // Tenta encontrar o que está entre vírgulas antes da cidade/estado
    $bairro = trim($matches[1]);
}

// Extrair cidade (geralmente antes de hífen seguido da sigla do estado, ou antes do CEP)
if (preg_match('/,\s*([A-Z][A-Za-z\s]+?)\s*-\s*[A-Z]{2}/i', $endereco, $matches)) {
    $cidade = trim($matches[1]);
} elseif (preg_match('/,\s*([A-Z][A-Za-z\s]+?)\s*,?\s*\d{5}/i', $endereco, $matches)) {
    $cidade = trim($matches[1]);
}

// Extrair estado (geralmente como sigla de 2 letras após hífen)
if (preg_match('/\s*-\s*([A-Z]{2})\b/i', $endereco, $matches)) {
    $estado = strtoupper(trim($matches[1]));
}

// Preparar o nome do estabelecimento para a busca
$nomeEstabelecimento = '';
if ($ordem['tipo_pessoa'] == 'juridica') {
    $nomeEstabelecimento = $ordem['nome_fantasia'] ?? $ordem['razao_social'] ?? '';
} else {
    $nomeEstabelecimento = $ordem['nome'] ?? '';
}

// Tentar geocodificar usando a API Nominatim
$coordenadas = geocodificarEndereco($endereco, $nomeEstabelecimento);

// Se encontrou coordenadas, usar diretamente
if ($coordenadas) {
    $latitude = $coordenadas['lat'];
    $longitude = $coordenadas['lon'];
    $queryLocalizacao = "$latitude,$longitude";
    $displayName = $coordenadas['display_name'];
} else {
    // Caso contrário, construir uma consulta baseada nos componentes extraídos
    // Construir a consulta na seguinte ordem de prioridade
    if (!empty($nomeEstabelecimento) && !empty($rua) && !empty($numero) && !empty($cidade)) {
        // Construção mais completa possível
        $queryLocalizacao = $nomeEstabelecimento;
        $queryLocalizacao .= ", $rua";
        $queryLocalizacao .= ", $numero";
        if (!empty($bairro)) $queryLocalizacao .= ", $bairro";
        $queryLocalizacao .= ", $cidade";
        if (!empty($estado)) $queryLocalizacao .= " - $estado";
        if (!empty($cep)) $queryLocalizacao .= ", $cep";
        $queryLocalizacao = urlencode($queryLocalizacao);
    } elseif (!empty($nomeEstabelecimento) && !empty($rua) && !empty($cidade)) {
        // Sem número, mas com rua e cidade
        $queryLocalizacao = $nomeEstabelecimento;
        $queryLocalizacao .= ", $rua";
        if (!empty($bairro)) $queryLocalizacao .= ", $bairro";
        $queryLocalizacao .= ", $cidade";
        if (!empty($estado)) $queryLocalizacao .= " - $estado";
        if (!empty($cep)) $queryLocalizacao .= ", $cep";
        $queryLocalizacao = urlencode($queryLocalizacao);
    } elseif (!empty($nomeEstabelecimento) && !empty($cidade)) {
        // Apenas nome, cidade e possivelmente CEP
        $queryLocalizacao = "$nomeEstabelecimento, $cidade";
        if (!empty($estado)) $queryLocalizacao .= " - $estado";
        if (!empty($cep)) $queryLocalizacao .= ", $cep";
        $queryLocalizacao = urlencode($queryLocalizacao);
    } elseif (!empty($nomeEstabelecimento)) {
        // Nome + endereço completo
        $queryLocalizacao = urlencode("$nomeEstabelecimento, $endereco");
    } else {
        // Apenas endereço completo
        $queryLocalizacao = $enderecoUrl;
    }
    
    $displayName = '';
}

$statusBadgeClass = getStatusBadgeClass($ordem['status']);
$statusAtual = strtolower($ordem['status'] ?? '');
$isFinalizadaOuCancelada = in_array($statusAtual, ['finalizada', 'concluída', 'cancelada']);

$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

?>
<script>
    function openGoogleMaps() {
        <?php if (isset($latitude) && isset($longitude)): ?>
        const mapsUrl = `https://www.google.com/maps/search/?api=1&query=<?= $latitude ?>,<?= $longitude ?>`;
        <?php else: ?>
        const mapsUrl = `https://www.google.com/maps/search/?api=1&query=<?= $queryLocalizacao ?>`;
        <?php endif; ?>
        window.open(mapsUrl, '_blank');
    }

    function sendWhatsApp() {
        <?php if (isset($latitude) && isset($longitude)): ?>
        const message = `📍 Localização OS <?= formatOSNumber($ordem['id'] ?? null, $ordem['data_inicio'] ?? null) ?>:\n<?= $enderecoCompleto ?>\n\n🗺️ Ver no mapa:\nhttps://www.google.com/maps/search/?api=1&query=<?= $latitude ?>,<?= $longitude ?>`;
        <?php else: ?>
        const message = `📍 Localização OS <?= formatOSNumber($ordem['id'] ?? null, $ordem['data_inicio'] ?? null) ?>:\n<?= $enderecoCompleto ?>\n\n🗺️ Ver no mapa:\nhttps://www.google.com/maps/search/?api=1&query=<?= $queryLocalizacao ?>`;
        <?php endif; ?>
        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
        window.open(whatsappUrl, '_blank');
    }
</script>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="mb-6">
        <div class="flex items-center mb-4">
            <a href="listar_ordens.php" class="text-blue-600 hover:text-blue-800 mr-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold text-gray-800">Ordem de Serviço <?= formatOSNumber($ordem['id'] ?? null, $ordem['data_inicio'] ?? null) ?></h1>
            <span class="ml-3 px-3 py-1 text-sm font-medium rounded-full <?= getStatusBadgeClassTailwind($ordem['status']) ?>">
                <?= htmlspecialchars(ucfirst($ordem['status'] ?? 'N/A')) ?>
            </span>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
            <div class="flex items-center">
                <div class="py-1"><i class="fas fa-check-circle mr-2"></i></div>
                <div>
                    <p class="font-medium"><?= htmlspecialchars($success_message) ?></p>
                </div>
                <button type="button" class="ml-auto" onclick="this.parentElement.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
            <div class="flex items-center">
                <div class="py-1"><i class="fas fa-exclamation-circle mr-2"></i></div>
                <div>
                    <p class="font-medium"><?= htmlspecialchars($error_message) ?></p>
                </div>
                <button type="button" class="ml-auto" onclick="this.parentElement.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                        <h3 class="text-base font-medium text-gray-700 flex items-center">
                            <i class="fas fa-bars mr-2 text-gray-500"></i>Ações
                        </h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php
                        // Define se as ações de edição/exclusão/reinício estão habilitadas
                        $canEdit = in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4]); // Admin, Suporte, Gerente, Técnico
                        $canDelete = in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3]); // Apenas Admin, Suporte, Gerente
                        $canRestart = in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3]); // Apenas Admin, Suporte, Gerente
                        $isDisabled = $isFinalizadaOuCancelada; // Desabilita se finalizada ou cancelada
                        ?>

                        <?php if ($canEdit) : ?>
                            <a href="<?= !$isDisabled ? 'editar_ordem.php?id=' . htmlspecialchars($id) : '#' ?>"
                                class="flex items-center px-4 py-3 text-sm hover:bg-blue-50 transition-colors duration-150 <?= $isDisabled ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700' ?>"
                                <?= $isDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                                <i class="fas fa-edit mr-3 <?= !$isDisabled ? 'text-blue-500' : 'text-gray-400' ?>"></i> Editar Ordem
                            </a>
                        <?php endif; ?>
                            
                        <?php if ($canDelete) : ?>
                            <a href="<?= !$isDisabled ? 'excluir_ordem.php?id=' . htmlspecialchars($id) : '#' ?>"
                                class="flex items-center px-4 py-3 text-sm hover:bg-red-50 transition-colors duration-150 <?= $isDisabled ? 'text-gray-400 cursor-not-allowed' : 'text-red-600' ?>"
                                onclick="<?= !$isDisabled ? "return confirm('Tem certeza que deseja excluir esta ordem de serviço? Esta ação não pode ser desfeita.')" : "return false;" ?>"
                                <?= $isDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                                <i class="fas fa-trash mr-3"></i> Excluir Ordem
                            </a>
                        <?php endif; ?>

                        <?php if (!$isFinalizadaOuCancelada) : // Botão Finalizar só aparece se não estiver finalizada/cancelada 
                        ?>
                            <button type="button" 
                                    class="w-full text-left flex items-center px-4 py-3 text-sm hover:bg-green-50 transition-colors duration-150 text-green-600" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#finalizarModal">
                                <i class="fas fa-check-circle mr-3"></i> Finalizar Ordem
                            </button>
                        <?php elseif ($canRestart) : // Botão Reiniciar só aparece se finalizada/cancelada E usuário tem permissão 
                        ?>
                            <a href="reiniciar_ordem.php?id=<?= htmlspecialchars($id); ?>"
                                class="flex items-center px-4 py-3 text-sm hover:bg-yellow-50 transition-colors duration-150 text-yellow-600"
                                onclick="return confirm('Tem certeza que deseja reiniciar esta ordem de serviço?')">
                                <i class="fas fa-undo mr-3"></i> Reiniciar Ordem
                            </a>
                        <?php endif; ?>

                        <a href="listar_ordens.php" class="flex items-center px-4 py-3 text-sm hover:bg-gray-50 transition-colors duration-150 text-gray-500">
                            <i class="fas fa-arrow-left mr-3"></i> Voltar para Lista
                        </a>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden mb-6">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-medium text-gray-700 flex items-center">
                                <i class="fas fa-file-alt mr-2 text-blue-500"></i>Detalhes da Ordem de Serviço
                            </h3>
                        </div>
                    </div>
                    <div class="p-4">

                        <?php if ($isFinalizadaOuCancelada): ?>
                            <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-700 p-4 mb-4 rounded" role="alert">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-amber-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm">Não é possível editar ou excluir uma ordem de serviço com status '<?= htmlspecialchars($statusAtual) ?>'.
                                        <?php if ($canRestart): ?>
                                            Você pode <a href="#reiniciar" onclick="document.querySelector('a[href*=\'reiniciar_ordem\']').click(); return false;" class="font-medium underline">reiniciá-la</a> se necessário.
                                        <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">Número da OS:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900"><?= formatOSNumber($ordem['id'] ?? null, $ordem['data_inicio'] ?? null) ?></td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número do Processo:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <?php if (!empty($ordem['processo_id']) && !empty($ordem['numero_processo'])): ?>
                                                <a href="../Processo/documentos.php?processo_id=<?= htmlspecialchars($ordem['processo_id']); ?>&id=<?= htmlspecialchars($ordem['estabelecimento_id']); ?>" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    <?= htmlspecialchars($ordem['numero_processo']); ?> <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($ordem['numero_processo'] ?? 'N/A'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php if ($ordem['tipo_pessoa'] == 'juridica'): ?>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Razão Social:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <?php if (!empty($ordem['estabelecimento_id'])): ?>
                                                <a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?= htmlspecialchars($ordem['estabelecimento_id']); ?>" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    <?= htmlspecialchars($ordem['razao_social'] ?? 'N/A'); ?> <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($ordem['razao_social'] ?? 'N/A'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome Fantasia:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($ordem['nome_fantasia'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php else: // Pessoa Física 
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <?php if (!empty($ordem['estabelecimento_id'])): // ID de pessoa física também fica em estabelecimento_id 
                                            ?>
                                                <a href="../Estabelecimento/detalhes_pessoa_fisica.php?id=<?= htmlspecialchars($ordem['estabelecimento_id']); ?>" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    <?= htmlspecialchars($ordem['nome'] ?? 'N/A'); ?> <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($ordem['nome'] ?? 'N/A'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="hover:bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endereço:</th>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= $enderecoCompleto ?: 'N/A' ?></td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período:</th>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= formatDate($ordem['data_inicio'] ?? null); ?> a <?= formatDate($ordem['data_fim'] ?? null); ?></td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações Executadas:</th>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= !empty($acoes_executadas_nomes) ? htmlspecialchars(implode(', ', $acoes_executadas_nomes)) : '<span class="text-gray-500 italic">Nenhuma</span>'; ?></td>
                                </tr>
                                <?php if (!empty($ordem['observacao'])): ?>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider align-top">Observação:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900"><?= nl2br(htmlspecialchars($ordem['observacao'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($ordem['descricao_encerramento'])): ?>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider align-top">Desc. Encerramento:</th>
                                        <td class="px-4 py-3 text-sm text-gray-500 italic"><?= nl2br(htmlspecialchars($ordem['descricao_encerramento'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($ordem['nome_usuario_encerramento']) && !empty($ordem['data_encerramento'])): ?>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider align-top">Finalizada por:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($ordem['nome_usuario_encerramento']); ?></td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider align-top">Data de encerramento:</th>
                                        <td class="px-4 py-3 text-sm text-gray-900"><?= formatDate($ordem['data_encerramento']) . ' às ' . date('H:i', strtotime($ordem['data_encerramento'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="hover:bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Técnicos:</th>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= !empty($nomes_tecnicos) ? htmlspecialchars(implode(', ', $nomes_tecnicos)) : '<span class="text-gray-500 italic">Nenhum</span>'; ?></td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arquivo Anexado:</th>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php if (!empty($ordem['pdf_upload'])): ?>
                                            <a href="/<?= htmlspecialchars($ordem['pdf_upload']); ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                                <i class="fas fa-paperclip mr-1.5"></i> Visualizar Arquivo
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Nenhum arquivo.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gerar Documento:</th>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <form action="gerar_pdf.php" method="post" target="_blank" class="inline">
                                            <input type="hidden" name="ordem_id" value="<?= htmlspecialchars($id); ?>">
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-cyan-300 text-sm leading-4 font-medium rounded-md text-cyan-700 bg-white hover:bg-cyan-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 transition-colors duration-200">
                                                <i class="fas fa-file-pdf mr-1.5"></i>Gerar PDF da OS
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden mb-6">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                        <h3 class="text-base font-medium text-gray-700 flex items-center">
                            <i class="fas fa-map-marked-alt mr-2 text-green-500"></i>Localização
                        </h3>
                    </div>
                    <div class="p-4">
                        <?php if (!empty($queryLocalizacao)): ?>
                            <div class="mb-4 h-80 overflow-hidden rounded-lg">
                                <iframe
                                    src="https://maps.google.com/maps?q=<?= $queryLocalizacao; ?>&t=&z=15&ie=UTF8&iwloc=&output=embed"
                                    width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade" title="Mapa de Localização"></iframe>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" onclick="openGoogleMaps()">
                                    <i class="fas fa-external-link-alt mr-2"></i> Ver no Google Maps
                                </button>
                                <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200" onclick="sendWhatsApp()">
                                    <i class="fab fa-whatsapp mr-2"></i> Enviar Localização
                                </button>
                            </div>
                            
                            <?php if ($_SESSION['user']['nivel_acesso'] == 1): // Apenas administradores podem ver esta informação ?>
                                <div class="mt-4 p-3 bg-gray-50 rounded-md text-xs">
                                    <details>
                                        <summary class="font-medium text-gray-700 cursor-pointer">Informações de localização extraídas</summary>
                                        <div class="mt-2 space-y-1 text-gray-600">
                                            <p><strong>Nome:</strong> <?= htmlspecialchars($nomeEstabelecimento ?: 'Não encontrado') ?></p>
                                            <p><strong>Rua:</strong> <?= htmlspecialchars($rua ?: 'Não encontrada') ?></p>
                                            <p><strong>Número:</strong> <?= htmlspecialchars($numero ?: 'Não encontrado') ?></p>
                                            <p><strong>Bairro:</strong> <?= htmlspecialchars($bairro ?: 'Não encontrado') ?></p>
                                            <p><strong>Cidade:</strong> <?= htmlspecialchars($cidade ?: 'Não encontrada') ?></p>
                                            <p><strong>Estado:</strong> <?= htmlspecialchars($estado ?: 'Não encontrado') ?></p>
                                            <p><strong>CEP:</strong> <?= htmlspecialchars($cep ?: 'Não encontrado') ?></p>
                                            <?php if (isset($latitude) && isset($longitude)): ?>
                                                <p><strong>Coordenadas:</strong> <?= htmlspecialchars("$latitude, $longitude") ?></p>
                                                <p><strong>Método:</strong> <span class="text-green-600 font-medium">API Nominatim (OpenStreetMap)</span></p>
                                                <?php if (!empty($displayName)): ?>
                                                    <p><strong>Endereço encontrado:</strong> <?= htmlspecialchars($displayName) ?></p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p><strong>Método:</strong> <span class="text-yellow-600 font-medium">Extração manual de componentes</span></p>
                                                <p><strong>Consulta de mapa:</strong> <?= htmlspecialchars(urldecode($queryLocalizacao)) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="flex items-center text-gray-500 py-3">
                                <i class="fas fa-ban mr-2"></i>
                                <p class="m-0">Endereço não disponível para exibir o mapa.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal com Tailwind CSS mas mantendo as classes Bootstrap para funcionalidade -->
    <div class="modal fade" id="finalizarModal" tabindex="-1" aria-labelledby="finalizarModalLabel" aria-hidden="true">
        <div class="modal-dialog max-w-lg mx-auto">
            <div class="modal-content bg-white rounded-lg shadow-xl border-0 overflow-hidden">
                <div class="modal-header flex items-center justify-between bg-gray-50 px-6 py-4 border-b border-gray-100">
                    <h5 class="modal-title text-lg font-medium text-gray-900 flex items-center" id="finalizarModalLabel">
                        <i class="fas fa-check-circle mr-2 text-green-500"></i> Finalizar Ordem de Serviço
                    </h5>
                    <button type="button" class="btn-close text-gray-400 hover:text-gray-500 focus:outline-none" data-bs-dismiss="modal" aria-label="Close">
                        <span class="sr-only">Fechar</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="finalizar_ordem.php?id=<?= htmlspecialchars($id); ?>">
                    <div class="modal-body p-6">
                        <div class="mb-4">
                            <label for="descricao_encerramento" class="block text-sm font-medium text-gray-700 mb-1">Descrição do Encerramento <span class="text-red-500">*</span></label>
                            <textarea 
                                class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                id="descricao_encerramento" 
                                name="descricao_encerramento" 
                                rows="4" 
                                required 
                                placeholder="Descreva o motivo ou resultado do encerramento da ordem de serviço."></textarea>
                            <p class="mt-2 text-sm text-gray-500">Esta informação será registrada na ordem de serviço.</p>
                        </div>
                    </div>
                    <div class="modal-footer flex items-center justify-end px-6 py-4 bg-gray-50 border-t border-gray-100">
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ml-3 transition-colors duration-200">
                            <i class="fas fa-check mr-1.5"></i> Confirmar Finalização
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>

</html>