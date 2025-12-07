<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../includes/documentos_helper.php';

$processoModel = new Processo($conn);

// Verificar se os parâmetros necessários foram fornecidos
if (!isset($_GET['id']) || !isset($_GET['processo_id'])) {
    echo "Parâmetros inválidos!";
    exit();
}

$estabelecimento_id = intval($_GET['id']);
$processo_id = intval($_GET['processo_id']);

// Verificar se o usuário está vinculado ao estabelecimento
$userId = $_SESSION['user']['id'];
$estabelecimentos = $processoModel->getEstabelecimentosByUsuario($userId);
$estabelecimentoIds = array_column($estabelecimentos, 'estabelecimento_id');

if (!in_array($estabelecimento_id, $estabelecimentoIds)) {
    echo "Acesso negado!";
    exit();
}

// Buscar estabelecimento
$stmtEstab = $conn->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
$stmtEstab->bind_param('i', $estabelecimento_id);
$stmtEstab->execute();
$estabelecimento = $stmtEstab->get_result()->fetch_assoc();

if (!$estabelecimento) {
    echo "Estabelecimento não encontrado!";
    exit();
}

// Buscar processo
$dadosProcesso = $processoModel->findById($processo_id);
if (!$dadosProcesso || $dadosProcesso['estabelecimento_id'] != $estabelecimento_id) {
    echo "Processo não encontrado ou não pertence a este estabelecimento!";
    exit();
}

// A função normalizarCnae agora está no documentos_helper.php

// Processar CNAEs
$cnaes = [normalizarCnae($estabelecimento['cnae_fiscal'])];
$secundarios = json_decode($estabelecimento['cnaes_secundarios'], true);
if (!empty($secundarios)) {
    foreach ($secundarios as $cnae) {
        $cnaes[] = normalizarCnae($cnae['codigo']);
    }
}

// Buscar nomes dos documentos do banco
$nomesDocumentos = getTodosDocumentosBanco($conn);

// Buscar documentos para cada tipo de licenciamento
$documentos_primeiro = [];
$documentos_renovacao = [];
$documentos_manter = [];

foreach ($cnaes as $cnae) {
    $stmtCnae = $conn->prepare("SELECT * FROM cnae_documentos WHERE normalizarCnae(cnae) = ? AND pactuacao = 'Municipal'");
    $stmtCnae->bind_param('s', $cnae);
    $stmtCnae->execute();
    $result = $stmtCnae->get_result();

    if ($row = $result->fetch_assoc()) {
        // Primeiro licenciamento
        $docs = explode(',', $row['primeiro_licenciamento']);
        foreach ($docs as $doc) {
            $doc = trim($doc);
            if (!empty($doc) && !in_array($doc, $documentos_primeiro)) {
                $documentos_primeiro[] = $doc;
            }
        }

        // Renovação
        $docs = explode(',', $row['renovacao']);
        foreach ($docs as $doc) {
            $doc = trim($doc);
            if (!empty($doc) && !in_array($doc, $documentos_renovacao)) {
                $documentos_renovacao[] = $doc;
            }
        }

        // Manutenção
        $docs = explode(',', $row['manter_estabelecimento']);
        foreach ($docs as $doc) {
            $doc = trim($doc);
            if (!empty($doc) && !in_array($doc, $documentos_manter)) {
                $documentos_manter[] = $doc;
            }
        }
    }
}

// Ordenar os documentos por código
sort($documentos_primeiro);
sort($documentos_renovacao);
sort($documentos_manter);

include '../../includes/header_empresa.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos por CNAE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
        }

        .card-title {
            margin: 0;
            font-weight: 600;
            color: #2c3e50;
        }

        .card-body {
            padding: 20px;
        }

        .nav-tabs .nav-link {
            border-radius: 5px 5px 0 0;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-color: #dee2e6 #dee2e6 #f8f9fa;
        }

        .tab-content {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 5px 5px;
            padding: 20px;
        }

        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 12px 20px;
        }

        .list-group-item:first-child {
            border-top: none;
        }

        .list-group-item:last-child {
            border-bottom: none;
        }

        .badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            margin-right: 10px;
        }

        .cnae-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 4px;
            margin-right: 10px;
        }

        .info-section {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-section h5 {
            margin-top: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .info-section p {
            margin-bottom: 0;
            color: #6c757d;
        }

        .btn-back {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Documentos Requeridos por CNAE</h2>
            <a href="detalhes_processo_empresa.php?id=<?php echo $processo_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar para o Processo
            </a>
        </div>

        <div class="info-section">
            <div class="row">
                <div class="col-md-6">
                    <h5>Informações do Estabelecimento</h5>
                    <p><strong>Nome Fantasia:</strong> <?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?></p>
                    <p><strong>Razão Social:</strong> <?php echo htmlspecialchars($estabelecimento['razao_social']); ?></p>
                    <?php if ($estabelecimento['tipo_pessoa'] === 'fisica'): ?>
                        <p><strong>CPF:</strong> <?php echo htmlspecialchars($estabelecimento['cpf']); ?></p>
                    <?php else: ?>
                        <p><strong>CNPJ:</strong> <?php echo htmlspecialchars($estabelecimento['cnpj']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h5>CNAEs do Estabelecimento</h5>
                    <p><strong>CNAE Principal:</strong> <span class="cnae-badge"><?php echo htmlspecialchars($estabelecimento['cnae_fiscal']); ?></span></p>
                    <?php if (!empty($secundarios)): ?>
                        <p><strong>CNAEs Secundários:</strong></p>
                        <div>
                            <?php foreach ($secundarios as $cnae): ?>
                                <span class="cnae-badge"><?php echo htmlspecialchars($cnae['codigo']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Documentos Requeridos</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="documentosTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="primeiro-tab" data-bs-toggle="tab" data-bs-target="#primeiro" type="button" role="tab" aria-controls="primeiro" aria-selected="true">
                            Primeiro Licenciamento
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="renovacao-tab" data-bs-toggle="tab" data-bs-target="#renovacao" type="button" role="tab" aria-controls="renovacao" aria-selected="false">
                            Renovação
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manter-tab" data-bs-toggle="tab" data-bs-target="#manter" type="button" role="tab" aria-controls="manter" aria-selected="false">
                            Manutenção
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="documentosTabsContent">
                    <!-- Primeiro Licenciamento -->
                    <div class="tab-pane fade show active" id="primeiro" role="tabpanel" aria-labelledby="primeiro-tab">
                        <?php if (!empty($documentos_primeiro)): ?>
                            <div class="list-group">
                                <?php foreach ($documentos_primeiro as $codigo): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary"><?php echo $codigo; ?></span>
                                            <span><?php echo htmlspecialchars($nomesDocumentos[$codigo] ?? 'Documento não especificado'); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Nenhum documento específico encontrado para primeiro licenciamento.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Renovação -->
                    <div class="tab-pane fade" id="renovacao" role="tabpanel" aria-labelledby="renovacao-tab">
                        <?php if (!empty($documentos_renovacao)): ?>
                            <div class="list-group">
                                <?php foreach ($documentos_renovacao as $codigo): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary"><?php echo $codigo; ?></span>
                                            <span><?php echo htmlspecialchars($nomesDocumentos[$codigo] ?? 'Documento não especificado'); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Nenhum documento específico encontrado para renovação.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Manutenção -->
                    <div class="tab-pane fade" id="manter" role="tabpanel" aria-labelledby="manter-tab">
                        <?php if (!empty($documentos_manter)): ?>
                            <div class="list-group">
                                <?php foreach ($documentos_manter as $codigo): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary"><?php echo $codigo; ?></span>
                                            <span><?php echo htmlspecialchars($nomesDocumentos[$codigo] ?? 'Documento não especificado'); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Nenhum documento específico encontrado para manutenção.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Esta lista de documentos é baseada nas atividades (CNAEs) do seu estabelecimento. Os documentos podem variar de acordo com o tipo de licenciamento solicitado.
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ativar as abas do Bootstrap
            var triggerTabList = [].slice.call(document.querySelectorAll('#documentosTabs a'))
            triggerTabList.forEach(function(triggerEl) {
                var tabTrigger = new bootstrap.Tab(triggerEl)
                triggerEl.addEventListener('click', function(event) {
                    event.preventDefault()
                    tabTrigger.show()
                })
            })
        });
    </script>
</body>

</html>