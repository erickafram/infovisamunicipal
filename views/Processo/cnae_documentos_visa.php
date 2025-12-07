<?php
session_start();
require_once '../../conf/database.php';
require_once '../../includes/documentos_helper.php';

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    // Verifica se o usuário está logado
    if (!isset($_SESSION['user'])) {
        header("Location: ../../login.php");
        exit();
    }

    // Recebe os parâmetros
    $estabelecimento_id = $_GET['id'];
    $tipo_processo = $_GET['tipo'] ?? 'primeiro'; // Valores: primeiro, renovacao, manter
    $processo_id = $_GET['processo_id'] ?? '';

    // Buscar estabelecimento
    $stmt = $conn->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
    $stmt->bind_param('i', $estabelecimento_id);
    $stmt->execute();
    $estabelecimento = $stmt->get_result()->fetch_assoc();

    // A função normalizarCnae agora está no documentos_helper.php

    // Processar CNAEs
    $cnae_fiscal = $estabelecimento['cnae_fiscal'] ?? '';
    $cnaes = [];
    if (!empty($cnae_fiscal)) {
        $cnaes[] = normalizarCnae($cnae_fiscal);
    }
    
    $cnaes_secundarios_json = $estabelecimento['cnaes_secundarios'] ?? null;
    $secundarios = !empty($cnaes_secundarios_json) ? json_decode($cnaes_secundarios_json, true) : [];
    if (!empty($secundarios)) {
        foreach ($secundarios as $cnae) {
            $cnaes[] = normalizarCnae($cnae['codigo']);
        }
    }

    // SISTEMA HÍBRIDO: Primeiro tenta a nova estrutura, depois a antiga
    $documentos = [];
    $usandoNovaEstrutura = false;
    
    // Verificar se existe dados na nova estrutura
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cnae_documentos_requisitos WHERE ativo = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        // NOVA ESTRUTURA: Buscar documentos baseado na nova estrutura
        $usandoNovaEstrutura = true;
        
        // Mapear tipo de processo para ID na nova estrutura
        $tipoLicenciamentoMap = [
            'primeiro' => 1,   // Assumindo que ID 1 = Primeiro Licenciamento
            'renovacao' => 2,  // Assumindo que ID 2 = Renovação
            'manter' => 3      // Assumindo que ID 3 = Manutenção
        ];
        
        $tipoLicenciamentoId = $tipoLicenciamentoMap[$tipo_processo] ?? 1;
        
        // Buscar documentos para cada CNAE na nova estrutura
        foreach ($cnaes as $cnae) {
            $stmt = $conn->prepare("
                SELECT DISTINCT td.codigo 
                FROM cnae_documentos_requisitos cdr
                JOIN atividades_cnae ac ON cdr.atividade_cnae_id = ac.id
                JOIN tipos_documentos td ON cdr.tipo_documento_id = td.id
                WHERE ac.codigo_cnae = ? 
                AND cdr.tipo_licenciamento_id = ?
                AND cdr.ativo = 1
                AND td.ativo = 1
            ");
            $stmt->bind_param('si', $cnae, $tipoLicenciamentoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $documentos[] = $row['codigo'];
            }
        }
        $documentos = array_unique($documentos);
    }
    
    // Se não encontrou documentos na nova estrutura, usar a antiga
    if (empty($documentos)) {
        $usandoNovaEstrutura = false;
        foreach ($cnaes as $cnae) {
            $stmt = $conn->prepare("SELECT * FROM cnae_documentos WHERE cnae = ? AND pactuacao = 'Municipal'");
            $stmt->bind_param('s', $cnae);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $docs = match ($tipo_processo) {
                    'primeiro'  => explode(',', $row['primeiro_licenciamento']),
                    'renovacao' => explode(',', $row['renovacao']),
                    'manter'    => explode(',', $row['manter_estabelecimento']),
                    default     => []
                };
                
                // Limpar códigos (remover espaços)
                $docs = array_map('trim', $docs);
                $docs = array_filter($docs); // Remove vazios
                
                $documentos = array_merge($documentos, $docs);
            }
        }
        $documentos = array_unique($documentos);
    }

    // Buscar nomes dos documentos do banco
    $nomesDocumentos = getTodosDocumentosBanco($conn);
?>

    <style>
        #documentosList .list-group-item {
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
        }

        #documentosList .form-check-input {
            cursor: pointer;
        }

        #documentosList .form-check-label {
            cursor: pointer;
        }
    </style>
    <div class="tabs-container">
        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link <?= ($tipo_processo === 'primeiro' ? 'active' : 'text-muted') ?>" href="#" onclick="loadDocumentosNecessarios('<?= $estabelecimento_id ?>', 'primeiro', '<?= $processo_id ?>')">1°</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($tipo_processo === 'renovacao' ? 'active' : 'text-muted') ?>" href="#" onclick="loadDocumentosNecessarios('<?= $estabelecimento_id ?>', 'renovacao', '<?= $processo_id ?>')">Renovação</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($tipo_processo === 'manter' ? 'active' : 'text-muted') ?>" href="#" onclick="loadDocumentosNecessarios('<?= $estabelecimento_id ?>', 'manter', '<?= $processo_id ?>')">Manutenção</a>
            </li>
        </ul>

        <div id="documentosList" class="border rounded p-3">
            <?php if (!empty($documentos)): ?>
                <p class="small text-muted mb-3 fst-italic">Marque o documento se aprovado para auxiliar na conferência.</p>

                <ul class="list-group list-group-flush">
                    <?php foreach ($documentos as $cod):
                        $checkboxId = 'docCheck_' . htmlspecialchars($estabelecimento_id) . '_' . htmlspecialchars($tipo_processo) . '_' . htmlspecialchars(trim($cod));
                    ?>
                        <li class="list-group-item" data-doc-code="<?= htmlspecialchars(trim($cod)) ?>">
                            <div class="form-check">
                                <input class="form-check-input document-approve-checkbox"
                                    type="checkbox"
                                    value="<?= htmlspecialchars(trim($cod)) ?>"
                                    id="<?= $checkboxId ?>"
                                    onchange="toggleApproved(this, '<?= htmlspecialchars(trim($cod)) ?>', '<?= $processo_id ?>', '<?= $estabelecimento_id ?>')">
                                <label class="form-check-label small" for="<?= $checkboxId ?>">
                                    <?= htmlspecialchars($nomesDocumentos[trim($cod)] ?? 'Documento não especificado (' . trim($cod) . ')') ?>
                                </label>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="alert alert-info mb-0">Nenhum documento necessário para esta categoria.</div>
            <?php endif; ?>
        </div>
    </div>
<?php
    exit();
}


if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$nivelAcesso = $_SESSION['user']['nivel_acesso'] ?? null;
if (in_array($nivelAcesso, [1, 2, 3])) {
    include '../header.php';
} else {
    include '../../includes/header_empresa.php';
}

// A função normalizarCnae agora está no documentos_helper.php

$estabelecimento_id = $_GET['id'];
$tipo_processo = $_GET['tipo'] ?? 'primeiro';

// Buscar estabelecimento
$stmt = $conn->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
$stmt->bind_param('i', $estabelecimento_id);
$stmt->execute();
$estabelecimento = $stmt->get_result()->fetch_assoc();

// Processar CNAEs
$cnae_fiscal = $estabelecimento['cnae_fiscal'] ?? '';
$cnaes = [];
if (!empty($cnae_fiscal)) {
    $cnaes[] = normalizarCnae($cnae_fiscal);
}

$cnaes_secundarios_json = $estabelecimento['cnaes_secundarios'] ?? null;
$secundarios = !empty($cnaes_secundarios_json) ? json_decode($cnaes_secundarios_json, true) : [];
if (!empty($secundarios)) {
    foreach ($secundarios as $cnae) {
        $cnaes[] = normalizarCnae($cnae['codigo']);
    }
}

// SISTEMA HÍBRIDO: Primeiro tenta a nova estrutura, depois a antiga
$documentos = [];
$usandoNovaEstrutura = false;

// Verificar se existe dados na nova estrutura
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM cnae_documentos_requisitos WHERE ativo = 1");
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['count'];

if ($count > 0) {
    // NOVA ESTRUTURA: Buscar documentos baseado na nova estrutura
    $usandoNovaEstrutura = true;
    
    // Mapear tipo de processo para ID na nova estrutura
    $tipoLicenciamentoMap = [
        'primeiro' => 1,   // Assumindo que ID 1 = Primeiro Licenciamento
        'renovacao' => 2,  // Assumindo que ID 2 = Renovação
        'manter' => 3      // Assumindo que ID 3 = Manutenção
    ];
    
    $tipoLicenciamentoId = $tipoLicenciamentoMap[$tipo_processo] ?? 1;
    
    // Buscar documentos para cada CNAE na nova estrutura
    foreach ($cnaes as $cnae) {
        $stmt = $conn->prepare("
            SELECT DISTINCT td.codigo 
            FROM cnae_documentos_requisitos cdr
            JOIN atividades_cnae ac ON cdr.atividade_cnae_id = ac.id
            JOIN tipos_documentos td ON cdr.tipo_documento_id = td.id
            WHERE ac.codigo_cnae = ? 
            AND cdr.tipo_licenciamento_id = ?
            AND cdr.ativo = 1
            AND td.ativo = 1
        ");
        $stmt->bind_param('si', $cnae, $tipoLicenciamentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $documentos[] = $row['codigo'];
        }
    }
    $documentos = array_unique($documentos);
}

// Se não encontrou documentos na nova estrutura, usar a antiga
if (empty($documentos)) {
    $usandoNovaEstrutura = false;
    foreach ($cnaes as $cnae) {
        $stmt = $conn->prepare("SELECT * FROM cnae_documentos WHERE cnae = ? AND pactuacao = 'Municipal'");
        $stmt->bind_param('s', $cnae);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $docs = match ($tipo_processo) {
                'primeiro'  => explode(',', $row['primeiro_licenciamento']),
                'renovacao' => explode(',', $row['renovacao']),
                'manter'    => explode(',', $row['manter_estabelecimento']),
                default     => []
            };
            
            // Limpar códigos (remover espaços)
            $docs = array_map('trim', $docs);
            $docs = array_filter($docs); // Remove vazios
            
            $documentos = array_merge($documentos, $docs);
        }
    }
    $documentos = array_unique($documentos);
}

// Buscar nomes dos documentos do banco
$nomesDocumentos = getTodosDocumentosBanco($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title>Documentos Necessários - Sistema</title>
    <!-- Inclua aqui seus links de CSS e meta tags -->
</head>

<body class="bg-light">
    <div class="container py-4">
        <!-- Cabeçalho e informações iniciais -->
        <div class="pb-3 border-bottom">
            <h4 class="h5 text-dark mb-1">Documentos Necessários</h4>
            <p class="text-muted small">Selecione o tipo de processo</p>
        </div>

        <!-- Exibe informações do estabelecimento -->
        <div class="mb-4 mt-4">
            <h5 class="text-dark">
                <?= htmlspecialchars($estabelecimento['razao_social'] ?? $estabelecimento['nome_fantasia'] ?? $estabelecimento['nome']) ?>
            </h5>
        </div>

        <!-- Card com informações e link para a portaria completa -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Relação de Documentos</h5>
                <p class="mb-2">Para consultar a lista completa de CNAEs e documentos exigidos de acordo com cada atividade, acesse a Portaria GAB/SEMUS Nº 0272/2024 publicada no Diário Oficial:</p>
                <a href="/visamunicipal/uploads/portaria_visa.pdf" target="_blank" class="btn btn-info btn-sm">
                    <i class="fas fa-file-pdf me-1"></i> Consultar Portaria Completa
                </a>
            </div>
        </div>

        <!-- Seletor de Processo: Abas para Primeiro Licenciamento, Renovação e Manutenção -->
        <nav class="nav nav-pills justify-content-center gap-2 my-4">
            <a href="?id=<?= $estabelecimento_id ?>&tipo=primeiro<?= isset($_GET['processo_id']) ? '&processo_id=' . htmlspecialchars($_GET['processo_id']) : '' ?>"
                class="nav-link <?= $tipo_processo === 'primeiro' ? 'active' : 'text-muted' ?> small">
                Primeiro Licenciamento
            </a>
            <a href="?id=<?= $estabelecimento_id ?>&tipo=renovacao<?= isset($_GET['processo_id']) ? '&processo_id=' . htmlspecialchars($_GET['processo_id']) : '' ?>"
                class="nav-link <?= $tipo_processo === 'renovacao' ? 'active' : 'text-muted' ?> small">
                Renovação
            </a>
            <a href="?id=<?= $estabelecimento_id ?>&tipo=manter<?= isset($_GET['processo_id']) ? '&processo_id=' . htmlspecialchars($_GET['processo_id']) : '' ?>"
                class="nav-link <?= $tipo_processo === 'manter' ? 'active' : 'text-muted' ?> small">
                Manutenção
            </a>
        </nav>

        <!-- Conteúdo: Exibe a lista de documentos -->
        <div class="bg-white rounded-3 p-4 shadow-sm">
            <h5 class="text-secondary mb-3 small">
                <?= match ($tipo_processo) {
                    'primeiro'  => 'Documentos para Primeiro Licenciamento',
                    'renovacao' => 'Documentos para Renovação',
                    'manter'    => 'Documentos para Manter no Estabelecimento'
                } ?>
            </h5>

            <?php if (!empty($documentos)): ?>
                <ul class="list-unstyled">
                    <?php foreach ($documentos as $cod): ?>
                        <li class="py-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small"><?= htmlspecialchars($cod) ?></span>
                                <span class="text-dark small"><?= htmlspecialchars($nomesDocumentos[$cod] ?? 'Documento não especificado') ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="text-center py-4 text-muted small">
                    Nenhum documento necessário para esta categoria
                </div>
            <?php endif; ?>
        </div>

        <!-- Botão Voltar -->
        <div class="mt-4">
            <?php if (isset($_GET['processo_id'])) : ?>
                <a href="../Processo/detalhes_processo_empresa.php?id=<?= htmlspecialchars($_GET['processo_id']) ?>" class="text-decoration-none small">
                    ← Voltar para detalhes
                </a>
            <?php else: ?>
                <a href="../Processo/detalhes_estabelecimento_empresa.php?id=<?= htmlspecialchars($estabelecimento_id) ?>" class="text-decoration-none small">
                    ← Voltar para estabelecimento
                </a>
            <?php endif; ?>
        </div>
    </div>


    <?php include '../footer.php'; ?>
</body>

</html>