<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Processo.php';

$estabelecimento = new Estabelecimento($conn);
$processo = new Processo($conn);

// --- Input Validation ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "ID do estabelecimento inválido ou não fornecido!";
    header("Location: listar_estabelecimentos.php");
    exit();
}
$id = (int)$_GET['id'];

// --- Fetch Establishment Data ---
$dadosEstabelecimento = $estabelecimento->findById($id);

if (!$dadosEstabelecimento) {
    $_SESSION['error_message'] = "Estabelecimento não encontrado!";
    header("Location: listar_estabelecimentos.php");
    exit();
}

// --- Atualização automática via API ---
function formatarCNAE($cnae)
{
    if (empty($cnae)) return '';
    $cnae = preg_replace('/\D/', '', $cnae); // Remove não dígitos
    if (strlen($cnae) === 7) {
        return substr($cnae, 0, 4) . '-' . substr($cnae, 4, 1) . '/' . substr($cnae, 5, 2);
    }
    return $cnae; // Retorna original se não for padrão
}

// Verificar se é pessoa jurídica, tem CNPJ e não é um estabelecimento público
$isEstabelecimentoPublico = false;

// Verificar se a natureza jurídica indica um estabelecimento público
if (isset($dadosEstabelecimento['natureza_juridica'])) {
    $naturezaJuridica = strtolower($dadosEstabelecimento['natureza_juridica']);
    
    // Lista de termos que identificam estabelecimentos públicos
    $termosPublicos = [
        'fundo público',
        'administração direta municipal',
        'administração pública',
        'órgão público',
        'autarquia',
        'fundação pública',
        'município',
        'estado',
        'união',
        'federal',
        'estadual',
        'municipal'
    ];
    
    // Verificar se algum dos termos está presente na natureza jurídica
    foreach ($termosPublicos as $termo) {
        if (strpos($naturezaJuridica, $termo) !== false) {
            $isEstabelecimentoPublico = true;
            break;
        }
    }
}

// Só atualiza via API se não for estabelecimento público
if ($dadosEstabelecimento['tipo_pessoa'] === 'juridica' && !empty($dadosEstabelecimento['cnpj']) && !$isEstabelecimentoPublico) {
    $cnpj = preg_replace('/\D/', '', $dadosEstabelecimento['cnpj']);

    if (strlen($cnpj) === 14) {
        // Fazer requisição à API
        $url = "https://govnex.site/govnex/api/cnpj_api.php";
        $token = "8ab984d986b155d84b4f88dec6d4f8c3cd2e11c685d9805107df78e94ab488ca";

        // Configurar o cabeçalho para incluir o domínio de origem
        $headers = [
            'Origin: https://infovisa.gurupi.to.gov.br',
            'Referer: https://infovisa.gurupi.to.gov.br/',
            'Host: govnex.site'
        ];

        // Configurar os parâmetros da requisição
        $params = [
            'cnpj' => $cnpj,
            'token' => $token,
            'domain' => 'infovisa.gurupi.to.gov.br'
        ];

        // Construir a URL com os parâmetros
        $url = $url . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log da resposta para depuração
        error_log("API Response for CNPJ $cnpj: $response");

        if ($httpCode === 200 && $response) {
            $apiData = json_decode($response, true);

            // Verificar se há erro na resposta
            if (isset($apiData['error'])) {
                error_log("API Error: " . $apiData['error']);
                // Não exibir erro para o usuário, apenas registrar no log
            }
            // Verificar se a resposta é válida
            elseif (isset($apiData['razao_social']) && !empty($apiData['razao_social'])) {
                $dadosAtualizados = false;
                $camposParaAtualizar = [
                    'nome_fantasia',
                    'razao_social',
                    'descricao_situacao_cadastral',
                    'data_situacao_cadastral',
                    'data_inicio_atividade',
                    'descricao_tipo_de_logradouro',
                    'logradouro',
                    'numero',
                    'complemento',
                    'bairro',
                    'cep',
                    'uf',
                    'municipio',
                    'ddd_telefone_1',
                    'ddd_telefone_2',
                    'natureza_juridica',
                    'cnae_fiscal',
                    'cnae_fiscal_descricao'
                ];

                $dadosUpdate = [];

                // Comparar dados da API com dados do banco
                foreach ($camposParaAtualizar as $campo) {
                    if (isset($apiData[$campo]) && $apiData[$campo] !== $dadosEstabelecimento[$campo]) {
                        $dadosUpdate[$campo] = $apiData[$campo];
                        $dadosAtualizados = true;
                    }
                }

                // Verificar CNAEs secundários
                $cnaesSecundariosAPI = isset($apiData['cnaes_secundarios']) ? $apiData['cnaes_secundarios'] : [];
                $cnaesSecundariosDB = json_decode($dadosEstabelecimento['cnaes_secundarios'] ?? '[]', true);

                // Comparar CNAEs secundários
                if (json_encode($cnaesSecundariosAPI) !== json_encode($cnaesSecundariosDB)) {
                    $dadosUpdate['cnaes_secundarios'] = json_encode($cnaesSecundariosAPI);
                    $dadosAtualizados = true;
                }

                // Verificar QSA (Quadro de Sócios e Administradores)
                $qsaAPI = isset($apiData['qsa']) ? $apiData['qsa'] : [];
                $qsaDB = json_decode($dadosEstabelecimento['qsa'] ?? '[]', true);

                // Comparar QSA
                if (json_encode($qsaAPI) !== json_encode($qsaDB)) {
                    $dadosUpdate['qsa'] = json_encode($qsaAPI);

                    // Atualizar também os campos individuais de sócios
                    if (!empty($qsaAPI[0])) {
                        $dadosUpdate['nome_socio_1'] = $qsaAPI[0]['nome_socio'] ?? null;
                        $dadosUpdate['qualificacao_socio_1'] = $qsaAPI[0]['qualificacao_socio'] ?? null;
                    }

                    if (!empty($qsaAPI[1])) {
                        $dadosUpdate['nome_socio_2'] = $qsaAPI[1]['nome_socio'] ?? null;
                        $dadosUpdate['qualificacao_socio_2'] = $qsaAPI[1]['qualificacao_socio'] ?? null;
                    }

                    if (!empty($qsaAPI[2])) {
                        $dadosUpdate['nome_socio_3'] = $qsaAPI[2]['nome_socio'] ?? null;
                        $dadosUpdate['qualificacao_socio_3'] = $qsaAPI[2]['qualificacao_socio'] ?? null;
                    }

                    $dadosAtualizados = true;
                }

                // Se houver dados para atualizar, fazer o update no banco
                if ($dadosAtualizados) {
                    // Preparar a query de update
                    $campos = [];
                    $valores = [];
                    $tipos = '';

                    foreach ($dadosUpdate as $campo => $valor) {
                        $campos[] = "$campo = ?";
                        $valores[] = $valor;
                        $tipos .= 's'; // Assumindo que todos são strings
                    }

                    // Não adicionar data_atualizacao pois a coluna não existe no banco

                    $sql = "UPDATE estabelecimentos SET " . implode(', ', $campos) . " WHERE id = ?";
                    $stmt = $conn->prepare($sql);

                    if ($stmt) {
                        // Adicionar o ID do estabelecimento aos valores
                        $valores[] = $id;
                        $tipos .= 'i'; // ID é inteiro

                        // Bind dos parâmetros
                        $stmt->bind_param($tipos, ...$valores);

                        // Executar a query
                        if ($stmt->execute()) {
                            // Atualizar os dados na variável para exibição
                            $dadosEstabelecimento = $estabelecimento->findById($id);

                            // Adicionar mensagem de sucesso
                            //$_SESSION['success_message'] = "Dados do estabelecimento atualizados automaticamente via API.";
                        } else {
                            // Log de erro
                            error_log("Erro ao atualizar estabelecimento ID $id: " . $stmt->error);
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}

// --- Authorization Check ---
$usuarioMunicipio = $_SESSION['user']['municipio'];
$nivel_acesso = $_SESSION['user']['nivel_acesso'];

if ($nivel_acesso != 1 && $dadosEstabelecimento['municipio'] !== $usuarioMunicipio) {
    $_SESSION['error_message'] = "Você não tem permissão para acessar este estabelecimento.";
    header("Location: listar_estabelecimentos.php");
    exit();
}

// --- Fetch Processes ---
$processos = $processo->getProcessosByEstabelecimento($id);

// --- Fetch Risk Groups and associated CNAEs ---
$queryGruposRisco = "
    SELECT DISTINCT gr.id, gr.descricao AS grupo_risco
    FROM estabelecimentos e
    LEFT JOIN atividade_grupo_risco agr_fiscal
        ON REPLACE(REPLACE(REPLACE(e.cnae_fiscal, '.', ''), '-', ''), '/', '') = REPLACE(REPLACE(REPLACE(agr_fiscal.cnae, '.', ''), '-', ''), '/', '')
        AND e.municipio = agr_fiscal.municipio
    LEFT JOIN grupo_risco gr_fiscal ON agr_fiscal.grupo_risco_id = gr_fiscal.id
    LEFT JOIN LATERAL (
        SELECT JSON_UNQUOTE(JSON_EXTRACT(sec.value, '$.codigo')) AS cnae_sec
        FROM JSON_TABLE(e.cnaes_secundarios, '$[*]' COLUMNS (value JSON PATH '$')) AS sec
    ) cnaes_secundarios ON 1=1
    LEFT JOIN atividade_grupo_risco agr_secundario
        ON REPLACE(REPLACE(REPLACE(cnaes_secundarios.cnae_sec, '.', ''), '-', ''), '/', '') = REPLACE(REPLACE(REPLACE(agr_secundario.cnae, '.', ''), '-', ''), '/', '')
        AND e.municipio = agr_secundario.municipio
    LEFT JOIN grupo_risco gr_secundario ON agr_secundario.grupo_risco_id = gr_secundario.id
    LEFT JOIN grupo_risco gr ON gr.id = COALESCE(gr_fiscal.id, gr_secundario.id)
    WHERE e.id = ? AND gr.descricao IS NOT NULL
    ORDER BY gr.id
";

$stmtGruposRisco = $conn->prepare($queryGruposRisco);
$gruposRiscoDescricoes = [];
$gruposRiscoIds = [];
if ($stmtGruposRisco) {
    $stmtGruposRisco->bind_param("i", $id);
    $stmtGruposRisco->execute();
    $resultGruposRisco = $stmtGruposRisco->get_result();
    while ($row = $resultGruposRisco->fetch_assoc()) {
        $gruposRiscoDescricoes[] = $row['grupo_risco'];
        $gruposRiscoIds[] = $row['id'];
    }
    $stmtGruposRisco->close();
} else {
    // Log error: $conn->error
    $_SESSION['warning_message'] = "Não foi possível consultar os grupos de risco."; // User-friendly message
}

// Fetch CNAEs for each risk group
$gruposComAtividades = [];
if (!empty($gruposRiscoIds)) {
    foreach ($gruposRiscoIds as $index => $grupoId) {
        $grupoNome = $gruposRiscoDescricoes[$index];
        
        // Primeiro, verificar CNAE Fiscal
        $queryAtividadesFiscal = "
            SELECT CONCAT(e.cnae_fiscal, ' - ', e.cnae_fiscal_descricao) AS atividade
            FROM estabelecimentos e
            JOIN atividade_grupo_risco agr ON 
                REPLACE(REPLACE(REPLACE(e.cnae_fiscal, '.', ''), '-', ''), '/', '') = 
                REPLACE(REPLACE(REPLACE(agr.cnae, '.', ''), '-', ''), '/', '')
                AND e.municipio = agr.municipio
            WHERE e.id = ? AND agr.grupo_risco_id = ?
        ";
        
        $stmtAtividadesFiscal = $conn->prepare($queryAtividadesFiscal);
        $stmtAtividadesFiscal->bind_param("ii", $id, $grupoId);
        $stmtAtividadesFiscal->execute();
        $resultAtividadesFiscal = $stmtAtividadesFiscal->get_result();
        
        $atividades = [];
        while ($row = $resultAtividadesFiscal->fetch_assoc()) {
            $atividades[] = $row['atividade'];
        }
        
        // Depois, verificar CNAEs Secundários
        if (!empty($dadosEstabelecimento['cnaes_secundarios'])) {
            $queryAtividadesSecundarios = "
                WITH cnaes_sec AS (
                    SELECT 
                        JSON_UNQUOTE(JSON_EXTRACT(sec.value, '$.codigo')) AS cnae_codigo,
                        JSON_UNQUOTE(JSON_EXTRACT(sec.value, '$.descricao')) AS cnae_descricao
                    FROM JSON_TABLE(?, '$[*]' COLUMNS (value JSON PATH '$')) AS sec
                )
                SELECT 
                    CONCAT(cs.cnae_codigo, ' - ', cs.cnae_descricao) AS atividade
                FROM cnaes_sec cs
                JOIN atividade_grupo_risco agr ON 
                    REPLACE(REPLACE(REPLACE(cs.cnae_codigo, '.', ''), '-', ''), '/', '') = 
                    REPLACE(REPLACE(REPLACE(agr.cnae, '.', ''), '-', ''), '/', '')
                    AND ? = agr.municipio
                WHERE agr.grupo_risco_id = ?
            ";
            
            $cnaesSecundariosJson = $dadosEstabelecimento['cnaes_secundarios'];
            $municipio = $dadosEstabelecimento['municipio'];
            
            $stmtAtividadesSecundarios = $conn->prepare($queryAtividadesSecundarios);
            $stmtAtividadesSecundarios->bind_param("ssi", $cnaesSecundariosJson, $municipio, $grupoId);
            $stmtAtividadesSecundarios->execute();
            $resultAtividadesSecundarios = $stmtAtividadesSecundarios->get_result();
            
            while ($row = $resultAtividadesSecundarios->fetch_assoc()) {
                $atividades[] = $row['atividade'];
            }
        }
        
        if (!empty($atividades)) {
            $gruposComAtividades[$grupoNome] = $atividades;
        }
    }
}
// --- End Fetch Risk Groups and associated CNAEs ---

// --- Decode JSON Data ---
$qsa = json_decode($dadosEstabelecimento['qsa'] ?? '[]', true);
$cnaes_secundarios = json_decode($dadosEstabelecimento['cnaes_secundarios'] ?? '[]', true);

// --- Helper Functions ---
function formatTelefone($telefone)
{
    if (empty($telefone) || $telefone === 'Não Informado') {
        return '<span class="text-muted">Não Informado</span>';
    }
    $numeroLimpo = preg_replace('/\D/', '', $telefone);
    $len = strlen($numeroLimpo);
    if ($len == 10) {
        return sprintf("(%s) %s-%s", substr($numeroLimpo, 0, 2), substr($numeroLimpo, 2, 4), substr($numeroLimpo, 6));
    } elseif ($len == 11) {
        return sprintf("(%s) %s-%s", substr($numeroLimpo, 0, 2), substr($numeroLimpo, 2, 5), substr($numeroLimpo, 7));
    }
    return htmlspecialchars($telefone); // Fallback
}

function getStatusCadastralBadgeClass($status)
{
    $statusLower = strtolower(trim($status ?? ''));
    switch ($statusLower) {
        case 'ativa':
            return 'bg-success';
        case 'suspensa':
        case 'baixada':
        case 'inapta':
        case 'nula':
            return 'bg-danger';
        case 'pendente': // Assuming 'pendente' might be a system status, not from receita
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

function formatData($data, $formato = 'd/m/Y')
{
    if (empty($data)) return '<span class="text-muted">N/A</span>';
    try {
        $dt = new DateTime($data);
        return $dt->format($formato);
    } catch (Exception $e) {
        return '<span class="text-muted">Data Inválida</span>';
    }
}
// --- End Helper Functions ---

$situacaoCadastral = $dadosEstabelecimento['descricao_situacao_cadastral'] ?? 'N/A';
$statusBadgeClass = getStatusCadastralBadgeClass($situacaoCadastral);
$statusSistema = $dadosEstabelecimento['status'] ?? 'ativo'; // Assuming 'ativo' is default if null

// Check if establishment has associated processes (for status change logic)
$temProcessos = false;
if (in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    $stmtCheckProc = $conn->prepare("SELECT 1 FROM processos WHERE estabelecimento_id = ? LIMIT 1");
    $stmtCheckProc->bind_param("i", $id);
    $stmtCheckProc->execute();
    $stmtCheckProc->store_result();
    $temProcessos = $stmtCheckProc->num_rows > 0;
    $stmtCheckProc->close();
}

?>

<div class="container mx-auto px-4 py-6">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded shadow-sm relative"
        role="alert">
        <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-500" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd" />
            </svg>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <button type="button" class="absolute top-0 right-0 mt-4 mr-4" data-bs-dismiss="alert" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-700" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                    clip-rule="evenodd" />
            </svg>
        </button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded shadow-sm relative" role="alert">
        <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-red-500" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <button type="button" class="absolute top-0 right-0 mt-4 mr-4" data-bs-dismiss="alert" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-700" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                    clip-rule="evenodd" />
            </svg>
        </button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <div class="lg:col-span-1">
            <div
                class="bg-white rounded-lg shadow-md overflow-hidden h-full border border-gray-100 transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b border-gray-200">
                    <h3 class="text-gray-700 font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Menu Rápido
                    </h3>
                </div>

                <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                <div class="flex flex-col flex-grow-1 divide-y divide-gray-100">
                    <a href="detalhes_estabelecimento.php?id=<?= $id; ?>"
                        class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 mr-2 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Detalhes</span>
                    </a>
                    <a href="editar_estabelecimento.php?id=<?= $id; ?>"
                        class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'editar_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 mr-2 <?= ($currentPage == 'editar_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        <span class="text-sm">Editar</span>
                    </a>
                    <a href="atividades.php?id=<?= $id; ?>"
                        class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'atividades.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 mr-2 <?= ($currentPage == 'atividades.php') ? 'text-blue-500' : 'text-gray-500'; ?>"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Atividades (CNAE)</span>
                    </a>
                    <a href="responsaveis.php?id=<?= $id; ?>"
                        class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'responsaveis.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 mr-2 <?= ($currentPage == 'responsaveis.php') ? 'text-blue-500' : 'text-gray-500'; ?>"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                        </svg>
                        <span class="text-sm">Responsáveis (QSA)</span>
                    </a>
                    <a href="acesso_empresa.php?id=<?= $id; ?>"
                        class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'acesso_empresa.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 mr-2 <?= ($currentPage == 'acesso_empresa.php') ? 'text-blue-500' : 'text-gray-500'; ?>"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 00-1-1H7a1 1 0 00-1 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Acesso Empresa</span>
                    </a>
                    <a href="../Processo/processos.php?id=<?= $id; ?>"
                        class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'processos.php' || $currentPage == 'documentos.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 mr-2 <?= ($currentPage == 'processos.php' || $currentPage == 'documentos.php') ? 'text-blue-500' : 'text-gray-500'; ?>"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1H8a3 3 0 00-3 3v1.5a1.5 1.5 0 01-3 0V6z"
                                clip-rule="evenodd" />
                            <path d="M6 12a2 2 0 012-2h8a2 2 0 012 2v2a2 2 0 01-2 2H2h2a2 2 0 002-2v-2z" />
                        </svg>
                        <span class="text-sm">Processos</span>
                    </a>
                </div>
                <?php if ($_SESSION['user']['nivel_acesso'] == 1) : ?>
                <div class="p-4 bg-gray-50 border-t border-gray-100">
                    <form method="POST" action="excluir_estabelecimento.php"
                        onsubmit="return confirm('ATENÇÃO!\nVocê tem certeza que deseja excluir este estabelecimento?\nTODOS OS DADOS ASSOCIADOS (PROCESSOS, ORDENS DE SERVIÇO, ETC) SERÃO PERDIDOS.\nEsta ação não pode ser desfeita.');"
                        class="w-full">
                        <input type="hidden" name="id" value="<?= $id; ?>">
                        <button type="submit"
                            class="w-full flex items-center justify-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 transition-colors duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            Excluir Estabelecimento
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-3">

            <?php if (in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) : ?>
            <div
                class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b border-gray-200">
                    <h3 class="text-gray-700 font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                                clip-rule="evenodd" />
                        </svg>
                        Status no Sistema
                    </h3>
                </div>
                <div class="p-4">
                    <?php if ($statusSistema === 'pendente') : ?>
                    <div class="flex flex-wrap items-center">
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                    clip-rule="evenodd" />
                            </svg>
                            Pendente
                        </span>
                        <span class="text-sm text-gray-500 mr-4">Estabelecimento aguardando
                            análise/regularização.</span>
                        <form method="POST" action="alterar_status.php" class="mt-2 sm:mt-0"
                            onsubmit="return confirm('Tem certeza que deseja marcar este estabelecimento como ATIVO no sistema?');">
                            <input type="hidden" name="id" value="<?= $id; ?>">
                            <input type="hidden" name="status" value="ativo">
                            <button type="submit"
                                class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                Marcar Ativo
                            </button>
                        </form>
                    </div>
                    <?php elseif ($temProcessos) : ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded-r flex items-start">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm text-blue-700">
                            Status atual: <span class="font-medium mx-1">Ativo</span>. Não é possível marcar como
                            'Pendente' pois existem processos vinculados.
                        </span>
                    </div>
                    <?php else : // Status is 'ativo' and no processes exist 
                        ?>
                    <div class="flex flex-wrap items-center">
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            Ativo
                        </span>
                        <span class="text-sm text-gray-500 mr-4">Estabelecimento regular no sistema.</span>
                        <form method="POST" action="alterar_status.php" class="mt-2 sm:mt-0"
                            onsubmit="return confirm('Tem certeza que deseja marcar este estabelecimento como PENDENTE no sistema? \nIsso pode indicar necessidade de reanálise ou pendências.');">
                            <input type="hidden" name="id" value="<?= $id; ?>">
                            <input type="hidden" name="status" value="pendente">
                            <button type="submit"
                                class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-gray-700 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                                        clip-rule="evenodd" />
                                </svg>
                                Marcar Pendente
                            </button>
                        </form>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Use "Marcar Pendente" se houver necessidade de reanálise ou
                        regularização.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div
                class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3 border-b border-blue-800">
                    <h3 class="text-white font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 00-1-1H7a1 1 0 00-1 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z"
                                clip-rule="evenodd" />
                        </svg>
                        Dados do Estabelecimento
                    </h3>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M6.625 2.655A9 9 0 0119 11a1 1 0 11-2 0 7 7 0 00-9.625-6.492 1 1 0 11-.75-1.853zM4.662 4.959A1 1 0 014.75 6.37 6.97 6.97 0 003 11a1 1 0 11-2 0 8.97 8.97 0 012.25-5.953 1 1 0 011.412-.088z"
                                            clip-rule="evenodd" />
                                        <path fill-rule="evenodd"
                                            d="M5 11a5 5 0 1110 0 1 1 0 11-2 0 3 3 0 10-6 0c0 1.677-.345 3.276-.968 4.729a1 1 0 11-1.838-.789A9.964 9.964 0 005 11zm8.921 2.012a1 1 0 01.831 1.145 19.86 19.86 0 01-.545 2.436 1 1 0 11-1.92-.558c.207-.713.371-1.445.49-2.192a1 1 0 011.144-.83z"
                                            clip-rule="evenodd" />
                                        <path fill-rule="evenodd"
                                            d="M10 10a1 1 0 011 1c0 2.236-.46 4.368-1.29 6.304a1 1 0 01-1.838-.789A13.952 13.952 0 009 11a1 1 0 011-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    CNPJ
                                </div>
                                <div class="text-gray-800 font-medium">
                                    <?= htmlspecialchars($dadosEstabelecimento['cnpj'] ?? 'N/A'); ?></div>
                            </div>

                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Razão Social
                                </div>
                                <div class="text-gray-800">
                                    <?= htmlspecialchars($dadosEstabelecimento['razao_social'] ?? 'N/A'); ?></div>
                            </div>

                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Nome Fantasia
                                </div>
                                <div class="text-gray-800">
                                    <?= htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? 'N/A'); ?></div>
                            </div>

                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                    </svg>
                                    Natureza Jurídica
                                </div>
                                <div class="text-gray-800">
                                    <?= htmlspecialchars($dadosEstabelecimento['natureza_juridica'] ?? 'N/A'); ?></div>
                            </div>

                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Início Atividade
                                </div>
                                <div class="text-gray-800">
                                    <?= formatData($dadosEstabelecimento['data_inicio_atividade']); ?></div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Situação Cadastral
                                </div>
                                <div>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= str_replace('bg-', 'bg-', $statusBadgeClass) ?>">
                                        <?= htmlspecialchars(ucfirst($situacaoCadastral)) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Endereço
                                </div>
                                <div class="text-gray-800">
                                    <?= htmlspecialchars($dadosEstabelecimento['logradouro'] ?? ''); ?>,
                                    <?= htmlspecialchars($dadosEstabelecimento['numero'] ?? 's/n'); ?>
                                    <?= !empty($dadosEstabelecimento['complemento']) ? ' - ' . htmlspecialchars($dadosEstabelecimento['complemento']) : ''; ?><br>
                                    <?= htmlspecialchars($dadosEstabelecimento['bairro'] ?? ''); ?>,
                                    <?= htmlspecialchars($dadosEstabelecimento['municipio'] ?? ''); ?> -
                                    <?= htmlspecialchars($dadosEstabelecimento['uf'] ?? ''); ?><br>
                                    <span class="text-gray-500">CEP:</span>
                                    <?= htmlspecialchars($dadosEstabelecimento['cep'] ?? ''); ?>
                                </div>
                            </div>

                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                    </svg>
                                    Telefones
                                </div>
                                <div class="text-gray-800">
                                    <?php if (!empty($dadosEstabelecimento['ddd_telefone_1'])): ?>
                                    <div class="flex items-center">
                                        <span
                                            class="text-gray-800"><?= formatTelefone($dadosEstabelecimento['ddd_telefone_1']); ?></span>
                                        <span class="ml-2 text-xs text-gray-500">(Principal)</span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($dadosEstabelecimento['ddd_telefone_2'])): ?>
                                    <div class="flex items-center mt-1">
                                        <span
                                            class="text-gray-800"><?= formatTelefone($dadosEstabelecimento['ddd_telefone_2']); ?></span>
                                        <span class="ml-2 text-xs text-gray-500">(Alternativo)</span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (empty($dadosEstabelecimento['ddd_telefone_1']) && empty($dadosEstabelecimento['ddd_telefone_2'])): ?>
                                    <span class="text-gray-500 italic">Não informado</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="group transition-all duration-200">
                                <div
                                    class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                    E-mail
                                </div>
                                <div class="text-gray-800">
                                    <?= !empty($dadosEstabelecimento['email']) ? 
                                        '<a href="mailto:' . htmlspecialchars($dadosEstabelecimento['email']) . '" class="text-blue-600 hover:underline transition-all duration-200">' . htmlspecialchars($dadosEstabelecimento['email']) . '</a>' : 
                                        '<span class="text-gray-500 italic">Não informado</span>'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($gruposComAtividades)) : ?>
            <div
                class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-yellow-500 to-amber-500 px-4 py-3 border-b border-yellow-600">
                    <h3 class="text-white font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Grupos de Risco Associados
                    </h3>
                </div>
                <div class="p-4">
                    <p class="text-sm text-gray-600 mb-4">Grupos de risco identificados com base nas atividades (CNAE
                        Fiscal e Secundárias) e regras do município:</p>
                    
                    <div class="grid gap-4">
                        <?php foreach ($gruposComAtividades as $grupo => $atividades) : ?>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <!-- Cabeçalho do grupo de risco -->
                                <div class="px-4 py-3 bg-gray-800 text-white flex items-center justify-between">
                                    <h4 class="font-medium flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <?= htmlspecialchars($grupo); ?>
                                    </h4>
                                    <span class="text-xs bg-white text-gray-800 rounded-full px-2 py-1">
                                        <?= count($atividades); ?> atividade<?= count($atividades) > 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                                
                                <!-- Lista de atividades do grupo -->
                                <div class="p-3 bg-white">
                                    <ul class="divide-y divide-gray-100">
                                        <?php foreach ($atividades as $atividade) : ?>
                                            <li class="py-2 flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($atividade); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div
                class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-cyan-500 to-blue-500 px-4 py-3 border-b border-blue-600">
                    <h3 class="text-white font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1H8a3 3 0 00-3 3v1.5a1.5 1.5 0 01-3 0V6z"
                                clip-rule="evenodd" />
                            <path d="M6 12a2 2 0 012-2h8a2 2 0 012 2v2a2 2 0 01-2 2H2h2a2 2 0 002-2v-2z" />
                        </svg>
                        Processos Vinculados
                    </h3>
                </div>
                <div class="p-4">
                    <?php if (!empty($processos)) : ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($processos as $proc) : ?>
                        <div
                            class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md hover:border-blue-300 flex flex-col h-full">
                            <div class="p-4 flex flex-col h-full">
                                <div class="flex items-center mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-5 w-5 text-blue-500 mr-2 flex-shrink-0" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <h3 class="text-blue-600 font-medium truncate"
                                        title="<?= htmlspecialchars($proc['numero_processo']); ?>">
                                        Nº <?= htmlspecialchars($proc['numero_processo']); ?>
                                    </h3>
                                </div>
                                <div class="space-y-2 flex-grow">
                                    <div class="flex">
                                        <span class="text-xs text-gray-500 w-24 flex-shrink-0">Tipo:</span>
                                        <span
                                            class="text-sm text-gray-800"><?= htmlspecialchars(ucfirst($proc['tipo_processo'])); ?></span>
                                    </div>
                                    <div class="flex">
                                        <span class="text-xs text-gray-500 w-24 flex-shrink-0">Autuação:</span>
                                        <span
                                            class="text-sm text-gray-800"><?= formatData($proc['data_abertura']); ?></span>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-gray-100">
                                    <a href="../Processo/documentos.php?processo_id=<?= $proc['id']; ?>&id=<?= $id; ?>"
                                        class="flex items-center justify-center px-4 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5"
                                            viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                            <path fill-rule="evenodd"
                                                d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        Ver Processo
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                    <div class="bg-gray-100 rounded-lg p-4 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-600">Nenhum processo encontrado para este estabelecimento.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div
                class="bg-white rounded-lg shadow-md border border-gray-100 mb-6 overflow-hidden transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b border-gray-200">
                    <h3 class="text-gray-700 font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                                clip-rule="evenodd" />
                        </svg>
                        Informações do Sistema
                    </h3>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="group transition-all duration-200">
                            <div
                                class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                        clip-rule="evenodd" />
                                </svg>
                                Data de Cadastro
                            </div>
                            <div class="text-gray-800">
                                <?= formatData($dadosEstabelecimento['data_cadastro'], 'd/m/Y H:i'); ?></div>
                        </div>

                        <div class="group transition-all duration-200">
                            <div
                                class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors duration-200 mb-1 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 mr-1 text-gray-400 group-hover:text-blue-500" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                                        clip-rule="evenodd" />
                                </svg>
                                Última Atualização
                            </div>
                            <div class="text-gray-800">
                                <?= formatData($dadosEstabelecimento['data_atualizacao'] ?? null, 'd/m/Y H:i'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="h-24 md:h-32"></div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>