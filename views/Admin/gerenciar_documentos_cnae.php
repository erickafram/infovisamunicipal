<?php
session_start();

// Verificação de autenticação e permissão
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../includes/documentos_helper.php';

// A função normalizarCnae agora está no documentos_helper.php

// Processar ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'migrar_cnae_existente':
            // Migrar dados da tabela cnae_documentos existente para as novas tabelas
            try {
                $conn->begin_transaction();
                
                // Buscar dados da tabela cnae_documentos
                $stmt = $conn->prepare("SELECT * FROM cnae_documentos WHERE pactuacao = 'Municipal'");
                $stmt->execute();
                $dados_cnae = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $migrados = 0;
                
                foreach ($dados_cnae as $cnae_data) {
                    // Inserir ou buscar CNAE na tabela atividades_cnae
                    $stmt = $conn->prepare("INSERT IGNORE INTO atividades_cnae (codigo_cnae) VALUES (?)");
                    $stmt->bind_param('s', $cnae_data['cnae']);
                    $stmt->execute();
                    
                    $stmt = $conn->prepare("SELECT id FROM atividades_cnae WHERE codigo_cnae = ?");
                    $stmt->bind_param('s', $cnae_data['cnae']);
                    $stmt->execute();
                    $cnae_id = $stmt->get_result()->fetch_assoc()['id'];
                    
                    // Buscar município padrão (primeiro da lista)
                    $stmt = $conn->prepare("SELECT id FROM municipios_documentos WHERE ativo = 1 LIMIT 1");
                    $stmt->execute();
                    $municipio_result = $stmt->get_result()->fetch_assoc();
                    $municipio_id = $municipio_result ? $municipio_result['id'] : 1;
                    
                    // Processar cada tipo de licenciamento
                    $tipos = [
                        'primeiro_licenciamento' => 1, // PRIMEIRO
                        'renovacao' => 2, // RENOVACAO
                        'manter_estabelecimento' => 3  // MANUTENCAO
                    ];
                    
                    foreach ($tipos as $campo => $tipo_id) {
                        $documentos = explode(',', $cnae_data[$campo]);
                        foreach ($documentos as $doc_codigo) {
                            $doc_codigo = trim($doc_codigo);
                            if (!empty($doc_codigo)) {
                                // Buscar documento
                                $stmt = $conn->prepare("SELECT id FROM tipos_documentos WHERE codigo = ?");
                                $doc_codigo_formatado = str_pad($doc_codigo, 3, '0', STR_PAD_LEFT);
                                $stmt->bind_param('s', $doc_codigo_formatado);
                                $stmt->execute();
                                $doc_result = $stmt->get_result()->fetch_assoc();
                                
                                if ($doc_result) {
                                    // Inserir requisito
                                    $stmt = $conn->prepare("INSERT IGNORE INTO cnae_documentos_requisitos 
                                        (atividade_cnae_id, tipo_documento_id, tipo_licenciamento_id, municipio_id, obrigatorio, criado_por) 
                                        VALUES (?, ?, ?, ?, 1, ?)");
                                    $stmt->bind_param('iiiii', $cnae_id, $doc_result['id'], $tipo_id, $municipio_id, $_SESSION['user']['id']);
                                    $stmt->execute();
                                    $migrados++;
                                }
                            }
                        }
                    }
                }
                
                $conn->commit();
                $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => "Migração concluída! $migrados requisitos migrados."];
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Erro na migração: ' . $e->getMessage()];
            }
            break;
            
        case 'criar_requisito':
            $cnae = $_POST['codigo_cnae'] ?? '';
            $documento_codigo = $_POST['codigo_documento'] ?? '';
            $tipo_licenciamento = $_POST['tipo_licenciamento'] ?? '';
            $municipio_nome = $_POST['municipio'] ?? '';
            $obrigatorio = isset($_POST['obrigatorio']) ? 1 : 0;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if ($cnae && $documento_codigo && $tipo_licenciamento && $municipio_nome) {
                try {
                    $conn->begin_transaction();
                    
                    // Buscar documento
                    $stmt = $conn->prepare("SELECT id FROM tipos_documentos WHERE codigo = ?");
                    $stmt->bind_param('s', $documento_codigo);
                    $stmt->execute();
                    $documento_result = $stmt->get_result()->fetch_assoc();
                    
                    if (!$documento_result) {
                        throw new Exception('Documento não encontrado.');
                    }
                    $documento_id = $documento_result['id'];
                    
                    // Buscar município
                    $stmt = $conn->prepare("SELECT id FROM municipios_documentos WHERE nome = ?");
                    $stmt->bind_param('s', $municipio_nome);
                    $stmt->execute();
                    $municipio_result = $stmt->get_result()->fetch_assoc();
                    
                    if (!$municipio_result) {
                        throw new Exception('Município não encontrado.');
                    }
                    $municipio_id = $municipio_result['id'];
                    
                    // Mapear tipo de licenciamento para ID
                    $tipoLicenciamentoMap = [
                        'PRIMEIRO' => 1,
                        'RENOVACAO' => 2,
                        'MANUTENCAO' => 3
                    ];
                    
                    $tipo_licenciamento_id = $tipoLicenciamentoMap[$tipo_licenciamento] ?? null;
                    if (!$tipo_licenciamento_id) {
                        throw new Exception('Tipo de licenciamento inválido.');
                    }
                    
                    // Inserir ou buscar CNAE
                    $stmt = $conn->prepare("INSERT IGNORE INTO atividades_cnae (codigo_cnae) VALUES (?)");
                    $stmt->bind_param('s', $cnae);
                    $stmt->execute();
                    
                    $stmt = $conn->prepare("SELECT id FROM atividades_cnae WHERE codigo_cnae = ?");
                    $stmt->bind_param('s', $cnae);
                    $stmt->execute();
                    $cnae_id = $stmt->get_result()->fetch_assoc()['id'];
                    
                    // Inserir requisito
                        $stmt = $conn->prepare("INSERT IGNORE INTO cnae_documentos_requisitos 
                            (atividade_cnae_id, tipo_documento_id, tipo_licenciamento_id, municipio_id, obrigatorio, observacoes, criado_por) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iiiiisi', $cnae_id, $documento_id, $tipo_licenciamento_id, $municipio_id, $obrigatorio, $observacoes, $_SESSION['user']['id']);
                        $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => 'Requisito criado com sucesso!'];
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Erro ao criar requisito: ' . $e->getMessage()];
                }
            } else {
                $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Todos os campos obrigatórios devem ser preenchidos.'];
            }
            break;
            
        case 'criar_municipio':
            $nome = $_POST['nome_municipio'] ?? '';
            $uf = $_POST['uf_municipio'] ?? '';
            $codigo_ibge = $_POST['codigo_ibge'] ?? '';
            
            if ($nome && $uf) {
                $stmt = $conn->prepare("INSERT INTO municipios_documentos (nome, uf, codigo_ibge) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $nome, $uf, $codigo_ibge);
                if ($stmt->execute()) {
                    $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => 'Município criado com sucesso!'];
                } else {
                    $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Erro ao criar município.'];
                }
            } else {
                $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Nome e UF são obrigatórios.'];
            }
            break;
            
        case 'editar_documento_agrupado':
            $documento_codigo = $_POST['documento_codigo'] ?? '';
            $tipo_licenciamento_id = $_POST['tipo_licenciamento_id'] ?? '';
            $municipio_nome = $_POST['municipio_nome'] ?? '';
            $obrigatorio = isset($_POST['obrigatorio']) ? 1 : 0;
            $observacoes = $_POST['observacoes'] ?? '';
            $cnaes_vinculados = $_POST['cnaes_vinculados'] ?? '';
            
            if ($documento_codigo && $tipo_licenciamento_id && $municipio_nome) {
                try {
                    $conn->begin_transaction();
                    
                    // Buscar IDs necessários
                    $stmt = $conn->prepare("SELECT id FROM tipos_documentos WHERE codigo = ?");
                    $stmt->bind_param('s', $documento_codigo);
                    $stmt->execute();
                    $documento_id = $stmt->get_result()->fetch_assoc()['id'];
                    
                    $stmt = $conn->prepare("SELECT id FROM municipios_documentos WHERE nome = ?");
                    $stmt->bind_param('s', $municipio_nome);
                    $stmt->execute();
                    $municipio_id = $stmt->get_result()->fetch_assoc()['id'];
                    
                    if ($documento_id && $municipio_id) {
                        // Se CNAEs foram editados, atualizar vinculações
                        if (!empty($cnaes_vinculados)) {
                            // Desativar todos os requisitos atuais
                            $stmt = $conn->prepare("
                                UPDATE cnae_documentos_requisitos 
                                SET ativo = 0, atualizado_em = NOW() 
                                WHERE tipo_documento_id = ? 
                                AND tipo_licenciamento_id = ? 
                                AND municipio_id = ? 
                                AND ativo = 1
                            ");
                            $stmt->bind_param('iii', $documento_id, $tipo_licenciamento_id, $municipio_id);
                            $stmt->execute();
                            
                            // Criar novos requisitos para os CNAEs editados
                            $cnaes_array = array_filter(array_map('trim', explode(',', $cnaes_vinculados)));
                            $cnaes_criados = 0;
                            
                            foreach ($cnaes_array as $cnae) {
                                if (empty($cnae)) continue;
                                
                                // Inserir ou buscar CNAE
                                $stmt = $conn->prepare("INSERT IGNORE INTO atividades_cnae (codigo_cnae) VALUES (?)");
                                $stmt->bind_param('s', $cnae);
                                $stmt->execute();
                                
                                $stmt = $conn->prepare("SELECT id FROM atividades_cnae WHERE codigo_cnae = ?");
                                $stmt->bind_param('s', $cnae);
                                $stmt->execute();
                                $cnae_id = $stmt->get_result()->fetch_assoc()['id'];
                                
                                if ($cnae_id) {
                                    // Inserir novo requisito
                                    $stmt = $conn->prepare("
                                        INSERT INTO cnae_documentos_requisitos 
                                        (atividade_cnae_id, tipo_documento_id, tipo_licenciamento_id, municipio_id, obrigatorio, observacoes, criado_por) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmt->bind_param('iiiiisi', $cnae_id, $documento_id, $tipo_licenciamento_id, $municipio_id, $obrigatorio, $observacoes, $_SESSION['user']['id']);
                                    $stmt->execute();
                                    $cnaes_criados++;
                                }
                            }
                            
                            $conn->commit();
                            $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => "Documento atualizado com sucesso! $cnaes_criados CNAEs vinculados."];
                        } else {
                            // Apenas atualizar propriedades sem modificar CNAEs
                            $stmt = $conn->prepare("
                                UPDATE cnae_documentos_requisitos 
                                SET obrigatorio = ?, observacoes = ?, atualizado_em = NOW() 
                                WHERE tipo_documento_id = ? 
                                AND tipo_licenciamento_id = ? 
                                AND municipio_id = ? 
                                AND ativo = 1
                            ");
                            $stmt->bind_param('isiii', $obrigatorio, $observacoes, $documento_id, $tipo_licenciamento_id, $municipio_id);
                            $stmt->execute();
                            
                            $conn->commit();
                            $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => 'Documento atualizado com sucesso!'];
                        }
                    } else {
                        $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Documento ou município não encontrado.'];
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Erro ao atualizar documento: ' . $e->getMessage()];
                }
            } else {
                $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Dados insuficientes para edição.'];
            }
            break;
            
        case 'remover_documento_agrupado':
            $documento_codigo = $_POST['documento_codigo'] ?? '';
            $tipo_licenciamento_id = $_POST['tipo_licenciamento_id'] ?? '';
            $municipio_nome = $_POST['municipio_nome'] ?? '';
            
            if ($documento_codigo && $tipo_licenciamento_id && $municipio_nome) {
                try {
                    $conn->begin_transaction();
                    
                    // Buscar IDs necessários
                    $stmt = $conn->prepare("SELECT id FROM tipos_documentos WHERE codigo = ?");
                    $stmt->bind_param('s', $documento_codigo);
                    $stmt->execute();
                    $documento_id = $stmt->get_result()->fetch_assoc()['id'];
                    
                    $stmt = $conn->prepare("SELECT id FROM municipios_documentos WHERE nome = ?");
                    $stmt->bind_param('s', $municipio_nome);
                    $stmt->execute();
                    $municipio_id = $stmt->get_result()->fetch_assoc()['id'];
                    
                    if ($documento_id && $municipio_id) {
                        // Marcar como inativo (soft delete) todos os requisitos deste documento para este tipo de licenciamento
                        $stmt = $conn->prepare("
                            UPDATE cnae_documentos_requisitos 
                            SET ativo = 0, atualizado_em = NOW() 
                            WHERE tipo_documento_id = ? 
                            AND tipo_licenciamento_id = ? 
                            AND municipio_id = ? 
                            AND ativo = 1
                        ");
                        $stmt->bind_param('iii', $documento_id, $tipo_licenciamento_id, $municipio_id);
                        $linhas_afetadas = $stmt->execute() ? $stmt->affected_rows : 0;
                        
                        $conn->commit();
                        $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => "Documento removido com sucesso! ($linhas_afetadas vínculos removidos)"];
                    } else {
                        $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Documento ou município não encontrado.'];
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Erro ao remover documento: ' . $e->getMessage()];
                }
            } else {
                $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Dados insuficientes para remoção.'];
            }
            break;
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Buscar dados para exibição
$stmt = $conn->prepare("SELECT * FROM municipios_documentos WHERE ativo = 1 ORDER BY nome");
$stmt->execute();
$municipios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT * FROM tipos_documentos WHERE ativo = 1 ORDER BY codigo");
$stmt->execute();
$tipos_documentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$municipio_selecionado = $_GET['municipio'] ?? '';
$documentos_agrupados = [];

if ($municipio_selecionado) {
    // Consulta agrupada por documento
    $sql = "SELECT 
        td.codigo as documento_codigo,
        td.nome as documento_nome,
        tl.nome as tipo_licenciamento_nome,
        tl.id as tipo_licenciamento_id,
        GROUP_CONCAT(DISTINCT ac.codigo_cnae ORDER BY ac.codigo_cnae SEPARATOR ', ') as cnaes_vinculados,
        COUNT(DISTINCT ac.id) as total_cnaes,
        MAX(cdr.obrigatorio) as obrigatorio,
        GROUP_CONCAT(DISTINCT cdr.observacoes) as observacoes_agrupadas,
        GROUP_CONCAT(DISTINCT cdr.id) as requisito_ids
    FROM cnae_documentos_requisitos cdr
    JOIN atividades_cnae ac ON cdr.atividade_cnae_id = ac.id
    JOIN tipos_documentos td ON cdr.tipo_documento_id = td.id
    JOIN tipos_licenciamento tl ON cdr.tipo_licenciamento_id = tl.id
    JOIN municipios_documentos md ON cdr.municipio_id = md.id
    WHERE md.nome = ? AND cdr.ativo = 1
    GROUP BY td.codigo, td.nome, tl.id, tl.nome
    ORDER BY td.codigo, tl.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $municipio_selecionado);
    $stmt->execute();
    $documentos_agrupados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Documentos por CNAE</title>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6">
        <!-- Mensagens -->
        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
                <div class="flex justify-between items-start">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <?php if ($_SESSION['mensagem']['tipo'] === 'success'): ?>
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            <?php else: ?>
                                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['mensagem']['texto']); ?></p>
                        </div>
                    </div>
                    <button type="button" class="ml-auto -mx-1.5 -my-1.5 rounded-lg p-1.5 hover:bg-gray-100 focus:ring-2 focus:ring-gray-300" onclick="this.parentElement.parentElement.remove()">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['mensagem']); ?>
        <?php endif; ?>

        <!-- Cabeçalho -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <svg class="h-8 w-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Gerenciar Documentos por CNAE
            </h1>
            <p class="text-gray-600 mt-2">Sistema organizado para gerenciar documentos necessários por atividade e município</p>
        </div>

        <!-- Navegação por abas -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button class="py-2 px-1 border-b-2 border-blue-500 text-blue-600 font-medium text-sm whitespace-nowrap tab-button active" data-tab="consultar">
                    <svg class="h-5 w-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Consultar Requisitos
                </button>
                <button class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap tab-button" data-tab="gerenciar">
                    <svg class="h-5 w-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Criar Requisito
                </button>
                <button class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap tab-button" data-tab="municipios">
                    <svg class="h-5 w-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Gerenciar Municípios
                </button>
                <button class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap tab-button" data-tab="migrar">
                    <svg class="h-5 w-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                    </svg>
                    Migrar Dados Antigos
                </button>
            </nav>
        </div>

        <div class="tab-content">
            <!-- Aba Consultar Requisitos -->
            <div class="tab-pane active" id="consultar">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-t-lg">
                        <h3 class="text-lg font-semibold flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Documentos Agrupados por Município
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" class="mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="municipio" class="block text-sm font-medium text-gray-700 mb-2">Selecionar Município:</label>
                                    <select name="municipio" id="municipio" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                                        <option value="">-- Selecione um município --</option>
                                        <?php foreach ($municipios as $municipio): ?>
                                            <option value="<?php echo htmlspecialchars($municipio['nome']); ?>" 
                                                    <?php echo $municipio_selecionado === $municipio['nome'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($municipio['nome'] . ' - ' . $municipio['uf']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($documentos_agrupados)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <thead class="bg-gray-800 text-white">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Documento</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Tipo Licenciamento</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">CNAEs Vinculados</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Total CNAEs</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Obrigatório</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Observações</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($documentos_agrupados as $documento): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col">
                                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-medium mb-1 w-fit">
                                                            <?php echo htmlspecialchars($documento['documento_codigo']); ?>
                                                        </span>
                                                        <div class="text-sm text-gray-900 font-medium">
                                                            <?php echo htmlspecialchars($documento['documento_nome']); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded font-medium">
                                                        <?php echo htmlspecialchars($documento['tipo_licenciamento_nome']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="max-w-xs">
                                                        <div class="flex flex-wrap gap-1">
                                                            <?php 
                                                            $cnaes = explode(', ', $documento['cnaes_vinculados']);
                                                            $max_display = 5; // Máximo de CNAEs para exibir diretamente
                                                            $cnaes_display = array_slice($cnaes, 0, $max_display);
                                                            $cnaes_hidden = array_slice($cnaes, $max_display);
                                                            
                                                            foreach ($cnaes_display as $cnae): ?>
                                                                <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                                                    <?php echo htmlspecialchars($cnae); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                            
                                                            <?php if (!empty($cnaes_hidden)): ?>
                                                                <span class="inline-block bg-gray-200 text-gray-600 text-xs px-2 py-1 rounded cursor-pointer" 
                                                                      title="<?php echo htmlspecialchars(implode(', ', $cnaes_hidden)); ?>"
                                                                      onclick="toggleCnaesCompletos(this)">
                                                                    +<?php echo count($cnaes_hidden); ?> mais
                                                                </span>
                                                                <div class="hidden mt-1 cnaes-completos">
                                                                    <?php foreach ($cnaes_hidden as $cnae): ?>
                                                                        <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded mr-1 mb-1">
                                                                            <?php echo htmlspecialchars($cnae); ?>
                                                                        </span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-block bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded font-medium">
                                                        <?php echo $documento['total_cnaes']; ?> CNAE<?php echo $documento['total_cnaes'] > 1 ? 's' : ''; ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php if ($documento['obrigatorio']): ?>
                                                        <span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-1 rounded font-medium">Obrigatório</span>
                                                    <?php else: ?>
                                                        <span class="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded font-medium">Opcional</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    <?php 
                                                    $observacoes = array_filter(explode(',', $documento['observacoes_agrupadas'] ?? ''));
                                                    echo !empty($observacoes) ? htmlspecialchars(implode('; ', array_unique($observacoes))) : '-'; 
                                                    ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex space-x-2">
                                                        <button class="inline-flex items-center px-2 py-1 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                                onclick="editarDocumento('<?php echo htmlspecialchars($documento['documento_codigo']); ?>', <?php echo $documento['tipo_licenciamento_id']; ?>)">
                                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                            Editar
                                                        </button>
                                                        <button class="inline-flex items-center px-2 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500" 
                                                                onclick="removerDocumento('<?php echo htmlspecialchars($documento['documento_codigo']); ?>', <?php echo $documento['tipo_licenciamento_id']; ?>)">
                                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                            Remover
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Resumo estatístico -->
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-blue-800">Total de Documentos</p>
                                            <p class="text-2xl font-bold text-blue-900"><?php echo count($documentos_agrupados); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-green-800">CNAEs Únicos</p>
                                            <p class="text-2xl font-bold text-green-900">
                                                <?php 
                                                $cnaes_unicos = [];
                                                foreach ($documentos_agrupados as $doc) {
                                                    $cnaes_unicos = array_merge($cnaes_unicos, explode(', ', $doc['cnaes_vinculados']));
                                                }
                                                echo count(array_unique($cnaes_unicos));
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-purple-800">Vinculos Totais</p>
                                            <p class="text-2xl font-bold text-purple-900">
                                                <?php echo array_sum(array_column($documentos_agrupados, 'total_cnaes')); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($municipio_selecionado): ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">Nenhum documento encontrado para este município.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Aba Criar Requisito -->
            <div class="tab-pane hidden" id="gerenciar">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-t-lg">
                        <h3 class="text-lg font-semibold flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Criar Novo Requisito
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="criar_requisito">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="codigo_cnae" class="block text-sm font-medium text-gray-700 mb-2">Código CNAE:</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="codigo_cnae" name="codigo_cnae" 
                                           placeholder="Ex: 47.11-3/01" required>
                                    <p class="mt-1 text-sm text-gray-500">Digite o código CNAE completo</p>
                                </div>
                                <div>
                                    <label for="municipio_req" class="block text-sm font-medium text-gray-700 mb-2">Município:</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="municipio_req" name="municipio" required>
                                        <option value="">-- Selecione --</option>
                                        <?php foreach ($municipios as $municipio): ?>
                                            <option value="<?php echo htmlspecialchars($municipio['nome']); ?>">
                                                <?php echo htmlspecialchars($municipio['nome'] . ' - ' . $municipio['uf']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label for="codigo_documento" class="block text-sm font-medium text-gray-700 mb-2">Documento:</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="codigo_documento" name="codigo_documento" required>
                                        <option value="">-- Selecione --</option>
                                        <?php foreach ($tipos_documentos as $documento): ?>
                                            <option value="<?php echo htmlspecialchars($documento['codigo']); ?>">
                                                <?php echo htmlspecialchars($documento['codigo'] . ' - ' . $documento['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="tipo_licenciamento" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Licenciamento:</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="tipo_licenciamento" name="tipo_licenciamento" required>
                                        <option value="">-- Selecione --</option>
                                        <option value="PRIMEIRO">Primeiro Licenciamento</option>
                                        <option value="RENOVACAO">Renovação</option>
                                        <option value="MANUTENCAO">Manutenção</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <div class="flex items-center">
                                        <input class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" type="checkbox" id="obrigatorio" name="obrigatorio" checked>
                                        <label class="ml-2 block text-sm text-gray-900" for="obrigatorio">
                                            Documento obrigatório
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-2">Observações:</label>
                                    <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="observacoes" name="observacoes" rows="2" 
                                              placeholder="Observações adicionais (opcional)"></textarea>
                                </div>
                            </div>

                            <div class="mt-6">
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    Criar Requisito
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Aba Gerenciar Municípios -->
            <div class="tab-pane hidden" id="municipios">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-t-lg">
                        <h3 class="text-lg font-semibold flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Gerenciar Municípios
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="mb-6">
                            <input type="hidden" name="action" value="criar_municipio">
                            
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div class="md:col-span-2">
                                    <label for="nome_municipio" class="block text-sm font-medium text-gray-700 mb-2">Nome do Município:</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="nome_municipio" name="nome_municipio" required>
                                        <option value="">Selecione o município</option>
                                        <option value="ABREULÂNDIA">ABREULÂNDIA</option>
                                        <option value="AGUIARNÓPOLIS">AGUIARNÓPOLIS</option>
                                        <option value="ALIANÇA DO TOCANTINS">ALIANÇA DO TOCANTINS</option>
                                        <option value="ALMAS">ALMAS</option>
                                        <option value="ALVORADA">ALVORADA</option>
                                        <option value="ANANÁS">ANANÁS</option>
                                        <option value="ANGICO">ANGICO</option>
                                        <option value="APARECIDA DO RIO NEGRO">APARECIDA DO RIO NEGRO</option>
                                        <option value="ARAGOMINAS">ARAGOMINAS</option>
                                        <option value="ARAGUACEMA">ARAGUACEMA</option>
                                        <option value="ARAGUAÇU">ARAGUAÇU</option>
                                        <option value="ARAGUAÍNA">ARAGUAÍNA</option>
                                        <option value="ARAGUANÃ">ARAGUANÃ</option>
                                        <option value="ARAGUATINS">ARAGUATINS</option>
                                        <option value="ARAPOEMA">ARAPOEMA</option>
                                        <option value="ARRAIAS">ARRAIAS</option>
                                        <option value="AUGUSTINÓPOLIS">AUGUSTINÓPOLIS</option>
                                        <option value="AURORA DO TOCANTINS">AURORA DO TOCANTINS</option>
                                        <option value="AXIXÁ DO TOCANTINS">AXIXÁ DO TOCANTINS</option>
                                        <option value="BABAÇULÂNDIA">BABAÇULÂNDIA</option>
                                        <option value="BANDEIRANTES DO TOCANTINS">BANDEIRANTES DO TOCANTINS</option>
                                        <option value="BARRA DO OURO">BARRA DO OURO</option>
                                        <option value="BARROLÂNDIA">BARROLÂNDIA</option>
                                        <option value="BERNARDO SAYÃO">BERNARDO SAYÃO</option>
                                        <option value="BOM JESUS DO TOCANTINS">BOM JESUS DO TOCANTINS</option>
                                        <option value="BRASILÂNDIA DO TOCANTINS">BRASILÂNDIA DO TOCANTINS</option>
                                        <option value="BREJINHO DE NAZARÉ">BREJINHO DE NAZARÉ</option>
                                        <option value="BURITI DO TOCANTINS">BURITI DO TOCANTINS</option>
                                        <option value="CACHOEIRINHA">CACHOEIRINHA</option>
                                        <option value="CAMPOS LINDOS">CAMPOS LINDOS</option>
                                        <option value="CARIRI DO TOCANTINS">CARIRI DO TOCANTINS</option>
                                        <option value="CARMOLÂNDIA">CARMOLÂNDIA</option>
                                        <option value="CARRASCO BONITO">CARRASCO BONITO</option>
                                        <option value="CASEARA">CASEARA</option>
                                        <option value="CENTENÁRIO">CENTENÁRIO</option>
                                        <option value="CHAPADA DA NATIVIDADE">CHAPADA DA NATIVIDADE</option>
                                        <option value="CHAPADA DE AREIA">CHAPADA DE AREIA</option>
                                        <option value="COLINAS DO TOCANTINS">COLINAS DO TOCANTINS</option>
                                        <option value="COLMÉIA">COLMÉIA</option>
                                        <option value="COMBINADO">COMBINADO</option>
                                        <option value="CONCEIÇÃO DO TOCANTINS">CONCEIÇÃO DO TOCANTINS</option>
                                        <option value="COUTO MAGALHÃES">COUTO MAGALHÃES</option>
                                        <option value="CRISTALÂNDIA">CRISTALÂNDIA</option>
                                        <option value="CRIXÁS DO TOCANTINS">CRIXÁS DO TOCANTINS</option>
                                        <option value="DARCINÓPOLIS">DARCINÓPOLIS</option>
                                        <option value="DIANÓPOLIS">DIANÓPOLIS</option>
                                        <option value="DIVINÓPOLIS DO TOCANTINS">DIVINÓPOLIS DO TOCANTINS</option>
                                        <option value="DOIS IRMÃOS DO TOCANTINS">DOIS IRMÃOS DO TOCANTINS</option>
                                        <option value="DUERÉ">DUERÉ</option>
                                        <option value="ESPERANTINA">ESPERANTINA</option>
                                        <option value="FÁTIMA">FÁTIMA</option>
                                        <option value="FIGUEIRÓPOLIS">FIGUEIRÓPOLIS</option>
                                        <option value="FILADÉLFIA">FILADÉLFIA</option>
                                        <option value="FORMOSO DO ARAGUAIA">FORMOSO DO ARAGUAIA</option>
                                        <option value="FORTALEZA DO TABOCÃO">FORTALEZA DO TABOCÃO</option>
                                        <option value="GOIANORTE">GOIANORTE</option>
                                        <option value="GOIATINS">GOIATINS</option>
                                        <option value="GUARAÍ">GUARAÍ</option>
                                        <option value="GURUPI">GURUPI</option>
                                        <option value="IPUEIRAS">IPUEIRAS</option>
                                        <option value="ITACAJÁ">ITACAJÁ</option>
                                        <option value="ITAGUATINS">ITAGUATINS</option>
                                        <option value="ITAPIRATINS">ITAPIRATINS</option>
                                        <option value="ITAPORÃ DO TOCANTINS">ITAPORÃ DO TOCANTINS</option>
                                        <option value="JAÚ DO TOCANTINS">JAÚ DO TOCANTINS</option>
                                        <option value="JUARINA">JUARINA</option>
                                        <option value="LAGOA DA CONFUSÃO">LAGOA DA CONFUSÃO</option>
                                        <option value="LAGOA DO TOCANTINS">LAGOA DO TOCANTINS</option>
                                        <option value="LAJEADO">LAJEADO</option>
                                        <option value="LAVANDEIRA">LAVANDEIRA</option>
                                        <option value="LIZARDA">LIZARDA</option>
                                        <option value="LUZINÓPOLIS">LUZINÓPOLIS</option>
                                        <option value="MARIANÓPOLIS DO TOCANTINS">MARIANÓPOLIS DO TOCANTINS</option>
                                        <option value="MATEIROS">MATEIROS</option>
                                        <option value="MAURILÂNDIA DO TOCANTINS">MAURILÂNDIA DO TOCANTINS</option>
                                        <option value="MIRACEMA DO TOCANTINS">MIRACEMA DO TOCANTINS</option>
                                        <option value="MIRANORTE">MIRANORTE</option>
                                        <option value="MONTE DO CARMO">MONTE DO CARMO</option>
                                        <option value="MONTE SANTO DO TOCANTINS">MONTE SANTO DO TOCANTINS</option>
                                        <option value="MURICILÂNDIA">MURICILÂNDIA</option>
                                        <option value="NATAL">NATAL</option>
                                        <option value="NATIVIDADE">NATIVIDADE</option>
                                        <option value="NAZARÉ">NAZARÉ</option>
                                        <option value="NOVA OLINDA">NOVA OLINDA</option>
                                        <option value="NOVA ROSALÂNDIA">NOVA ROSALÂNDIA</option>
                                        <option value="NOVO ACORDO">NOVO ACORDO</option>
                                        <option value="NOVO ALEGRE">NOVO ALEGRE</option>
                                        <option value="NOVO JARDIM">NOVO JARDIM</option>
                                        <option value="OLIVEIRA DE FÁTIMA">OLIVEIRA DE FÁTIMA</option>
                                        <option value="PALMAS">PALMAS</option>
                                        <option value="PALMEIRANTE">PALMEIRANTE</option>
                                        <option value="PALMEIRAS DO TOCANTINS">PALMEIRAS DO TOCANTINS</option>
                                        <option value="PALMEIROPOLIS">PALMEIROPOLIS</option>
                                        <option value="PARAÍSO DO TOCANTINS">PARAÍSO DO TOCANTINS</option>
                                        <option value="PARANÃ">PARANÃ</option>
                                        <option value="PAU D'ARCO">PAU D'ARCO</option>
                                        <option value="PEDRO AFONSO">PEDRO AFONSO</option>
                                        <option value="PEIXE">PEIXE</option>
                                        <option value="PEQUIZEIRO">PEQUIZEIRO</option>
                                        <option value="PINDORAMA DO TOCANTINS">PINDORAMA DO TOCANTINS</option>
                                        <option value="PIRAQUÊ">PIRAQUÊ</option>
                                        <option value="PIUM">PIUM</option>
                                        <option value="PONTE ALTA DO BOM JESUS">PONTE ALTA DO BOM JESUS</option>
                                        <option value="PONTE ALTA DO TOCANTINS">PONTE ALTA DO TOCANTINS</option>
                                        <option value="PORTO ALEGRE DO TOCANTINS">PORTO ALEGRE DO TOCANTINS</option>
                                        <option value="PORTO NACIONAL">PORTO NACIONAL</option>
                                        <option value="PRAIA NORTE">PRAIA NORTE</option>
                                        <option value="PRESIDENTE KENNEDY">PRESIDENTE KENNEDY</option>
                                        <option value="PUGMIL">PUGMIL</option>
                                        <option value="RECURSOLÂNDIA">RECURSOLÂNDIA</option>
                                        <option value="RIACHINHO">RIACHINHO</option>
                                        <option value="RIO DA CONCEIÇÃO">RIO DA CONCEIÇÃO</option>
                                        <option value="RIO DOS BOIS">RIO DOS BOIS</option>
                                        <option value="RIO SONO">RIO SONO</option>
                                        <option value="SAMPAIO">SAMPAIO</option>
                                        <option value="SANDOLÂNDIA">SANDOLÂNDIA</option>
                                        <option value="SANTA FÉ DO ARAGUAIA">SANTA FÉ DO ARAGUAIA</option>
                                        <option value="SANTA MARIA DO TOCANTINS">SANTA MARIA DO TOCANTINS</option>
                                        <option value="SANTA RITA DO TOCANTINS">SANTA RITA DO TOCANTINS</option>
                                        <option value="SANTA ROSA DO TOCANTINS">SANTA ROSA DO TOCANTINS</option>
                                        <option value="SANTA TEREZA DO TOCANTINS">SANTA TEREZA DO TOCANTINS</option>
                                        <option value="SANTA TEREZINHA DO TOCANTINS">SANTA TEREZINHA DO TOCANTINS</option>
                                        <option value="SÃO BENTO DO TOCANTINS">SÃO BENTO DO TOCANTINS</option>
                                        <option value="SÃO FÉLIX DO TOCANTINS">SÃO FÉLIX DO TOCANTINS</option>
                                        <option value="SÃO MIGUEL DO TOCANTINS">SÃO MIGUEL DO TOCANTINS</option>
                                        <option value="SÃO SALVADOR DO TOCANTINS">SÃO SALVADOR DO TOCANTINS</option>
                                        <option value="SÃO SEBASTIÃO DO TOCANTINS">SÃO SEBASTIÃO DO TOCANTINS</option>
                                        <option value="SÃO VALÉRIO DA NATIVIDADE">SÃO VALÉRIO DA NATIVIDADE</option>
                                        <option value="SILVANÓPOLIS">SILVANÓPOLIS</option>
                                        <option value="SÍTIO NOVO DO TOCANTINS">SÍTIO NOVO DO TOCANTINS</option>
                                        <option value="SUCUPIRA">SUCUPIRA</option>
                                        <option value="TAGUATINGA">TAGUATINGA</option>
                                        <option value="TAIPAS DO TOCANTINS">TAIPAS DO TOCANTINS</option>
                                        <option value="TALISMÃ">TALISMÃ</option>
                                        <option value="TOCANTÍNIA">TOCANTÍNIA</option>
                                        <option value="TOCANTINÓPOLIS">TOCANTINÓPOLIS</option>
                                        <option value="TUPIRAMA">TUPIRAMA</option>
                                        <option value="TUPIRATINS">TUPIRATINS</option>
                                        <option value="WANDERLÂNDIA">WANDERLÂNDIA</option>
                                        <option value="XAMBIOÁ">XAMBIOÁ</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="uf_municipio" class="block text-sm font-medium text-gray-700 mb-2">UF:</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="uf_municipio" name="uf_municipio" 
                                           maxlength="2" value="TO" required readonly>
                                </div>
                                <div>
                                    <label for="codigo_ibge" class="block text-sm font-medium text-gray-700 mb-2">Código IBGE:</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="codigo_ibge" name="codigo_ibge" placeholder="Opcional">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">&nbsp;</label>
                                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Adicionar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                                <thead class="bg-gray-800 text-white">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Nome</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">UF</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Código IBGE</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Data Criação</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($municipios as $municipio): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($municipio['nome']); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($municipio['uf']); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($municipio['codigo_ibge'] ?: '-'); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-900"><?php echo date('d/m/Y H:i', strtotime($municipio['data_criacao'])); ?></td>
                                            <td class="px-4 py-3">
                                                <?php if ($municipio['ativo']): ?>
                                                    <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded font-medium">Ativo</span>
                                                <?php else: ?>
                                                    <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded font-medium">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aba Migrar Dados -->
            <div class="tab-pane hidden" id="migrar">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-t-lg">
                        <h3 class="text-lg font-semibold flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                            </svg>
                            Migrar Dados da Estrutura Antiga
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">Atenção</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Esta operação irá migrar os dados da tabela antiga <code class="bg-yellow-100 px-1 rounded">cnae_documentos</code> 
                                        para a nova estrutura. Execute apenas uma vez por município.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="migrar_cnae_existente">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="municipio_migracao" class="block text-sm font-medium text-gray-700 mb-2">Município de Destino:</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="municipio_migracao" name="municipio_migracao" required>
                                        <option value="">-- Selecione --</option>
                                        <?php foreach ($municipios as $municipio): ?>
                                            <option value="<?php echo htmlspecialchars($municipio['nome']); ?>">
                                                <?php echo htmlspecialchars($municipio['nome'] . ' - ' . $municipio['uf']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500" 
                                            onclick="return confirm('Tem certeza que deseja migrar os dados? Esta operação não pode ser desfeita.')">
                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                        </svg>
                                        Migrar Dados
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="modalEditarDocumento" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Editar Documento</h3>
                    <button onclick="fecharModalEdicao()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="formEditarDocumento">
                    <input type="hidden" name="action" value="editar_documento_agrupado">
                    <input type="hidden" name="documento_codigo" id="edit_documento_codigo">
                    <input type="hidden" name="tipo_licenciamento_id" id="edit_tipo_licenciamento_id">
                    <input type="hidden" name="municipio_nome" id="edit_municipio_nome">
                    <input type="hidden" name="cnaes_vinculados" id="edit_cnaes_vinculados">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Documento:</label>
                        <div class="p-3 bg-gray-50 rounded-md">
                            <div class="flex flex-col">
                                <span id="edit_documento_info" class="text-sm font-medium text-gray-900"></span>
                                <span id="edit_tipo_licenciamento_info" class="text-xs text-gray-500 mt-1"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CNAEs Vinculados:</label>
                        <div class="p-3 bg-gray-50 rounded-md max-h-40 overflow-y-auto border border-gray-200">
                            <div id="edit_cnaes_info" class="flex flex-wrap gap-1"></div>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <span id="edit_total_cnaes" class="text-xs text-gray-500"></span>
                            <button type="button" onclick="toggleCnaeEditor()" 
                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                ✏️ Editar CNAEs
                            </button>
                        </div>
                        
                        <!-- Editor de CNAEs (oculto por padrão) -->
                        <div id="cnae_editor" class="hidden mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <div class="flex gap-2 mb-2">
                                <input type="text" id="novo_cnae" placeholder="Ex: 4711301" 
                                       class="flex-1 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                       maxlength="10">
                                <button type="button" onclick="adicionarCnae()" 
                                        class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">
                                    Adicionar
                                </button>
                            </div>
                            <div class="text-xs text-gray-600">
                                💡 Dica: Clique no ❌ ao lado de um CNAE para removê-lo
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="edit_obrigatorio" name="obrigatorio" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_obrigatorio" class="ml-2 block text-sm text-gray-900">
                                Documento obrigatório
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="edit_observacoes" class="block text-sm font-medium text-gray-700 mb-2">Observações:</label>
                        <textarea id="edit_observacoes" name="observacoes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Observações adicionais (opcional)"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="fecharModalEdicao()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Gerenciamento de abas
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;
                    
                    // Remove active class from all buttons
                    tabButtons.forEach(btn => {
                        btn.classList.remove('border-blue-500', 'text-blue-600');
                        btn.classList.add('border-transparent', 'text-gray-500');
                    });
                    
                    // Add active class to clicked button
                    this.classList.remove('border-transparent', 'text-gray-500');
                    this.classList.add('border-blue-500', 'text-blue-600');
                    
                    // Hide all tab panes
                    tabPanes.forEach(pane => {
                        pane.classList.remove('active');
                        pane.classList.add('hidden');
                    });
                    
                    // Show target tab pane
                    const targetPane = document.getElementById(targetTab);
                    if (targetPane) {
                        targetPane.classList.remove('hidden');
                        targetPane.classList.add('active');
                    }
                });
            });
        });

        function toggleCnaesCompletos(element) {
            const cnaesCompletos = element.parentElement.querySelector('.cnaes-completos');
            if (cnaesCompletos.classList.contains('hidden')) {
                cnaesCompletos.classList.remove('hidden');
                element.textContent = 'Ocultar';
            } else {
                cnaesCompletos.classList.add('hidden');
                element.textContent = element.getAttribute('data-original-text') || '+' + element.textContent.match(/\d+/)[0] + ' mais';
            }
        }

        function editarDocumento(documentoCodigo, tipoLicenciamentoId) {
            // Buscar dados do documento na tabela atual
            const municipioSelecionado = document.getElementById('municipio').value;
            if (!municipioSelecionado) {
                alert('Selecione um município primeiro.');
                return;
            }
            
            // Encontrar a linha na tabela
            const rows = document.querySelectorAll('#consultar tbody tr');
            let documentoData = null;
            
            rows.forEach((row, index) => {
                try {
                    // Buscar código do documento
                    const codigoElement = row.querySelector('td:first-child .flex span.inline-block');
                    if (!codigoElement) return;
                    
                    const codigo = codigoElement.textContent.trim();
                    
                    // Buscar tipo de licenciamento
                    const tipoCell = row.querySelector('td:nth-child(2) span.inline-block');
                    if (!tipoCell) return;
                    
                    const tipoTexto = tipoCell.textContent.trim();
                    
                    if (codigo === documentoCodigo) {
                        // Verificar se é o tipo de licenciamento correto
                        const isCorrectType = (
                            (tipoLicenciamentoId == 1 && tipoTexto.includes('Primeiro')) ||
                            (tipoLicenciamentoId == 2 && tipoTexto.includes('Renovação')) ||
                            (tipoLicenciamentoId == 3 && tipoTexto.includes('Manutenção'))
                        );
                        
                        if (isCorrectType) {
                            // Buscar nome do documento (segunda div dentro da estrutura flex)
                            const nomeElement = row.querySelector('td:first-child .flex div.text-sm');
                            
                            // Buscar outros elementos
                            const cnaesElement = row.querySelector('td:nth-child(3)');
                            const obrigatorioElement = row.querySelector('td:nth-child(5) span.inline-block');
                            const observacoesElement = row.querySelector('td:nth-child(6)');
                            
                            if (nomeElement && cnaesElement && obrigatorioElement && observacoesElement) {
                                documentoData = {
                                    codigo: codigo,
                                    nome: nomeElement.textContent.trim(),
                                    tipoLicenciamento: tipoTexto,
                                    cnaes: cnaesElement.textContent.trim(),
                                    obrigatorio: obrigatorioElement.textContent.trim() === 'Obrigatório',
                                    observacoes: observacoesElement.textContent.trim()
                                };
                                
                                // Parar a busca quando encontrar
                                return;
                            }
                        }
                    }
                } catch (error) {
                    console.error(`Erro ao processar linha ${index}:`, error);
                }
            });
            
            if (!documentoData) {
                alert('Documento não encontrado na tabela. Verifique se o município está selecionado e se há dados carregados.');
                return;
            }
            
            // Preencher modal
            document.getElementById('edit_documento_codigo').value = documentoData.codigo;
            document.getElementById('edit_tipo_licenciamento_id').value = tipoLicenciamentoId;
            document.getElementById('edit_municipio_nome').value = municipioSelecionado;
            document.getElementById('edit_documento_info').textContent = `${documentoData.codigo} - ${documentoData.nome}`;
            document.getElementById('edit_tipo_licenciamento_info').textContent = documentoData.tipoLicenciamento;
            
            // Formatar CNAEs como badges
            const cnaesContainer = document.getElementById('edit_cnaes_info');
            const totalCnaesSpan = document.getElementById('edit_total_cnaes');
            cnaesContainer.innerHTML = '';
            
            // Extrair e limpar CNAEs
            let cnaesText = documentoData.cnaes.trim();
            
            // Separar por espaços e vírgulas, remover texto "+X mais"
            let cnaes = cnaesText.split(/[\s,]+/)
                .map(cnae => cnae.trim())
                .filter(cnae => cnae && !cnae.includes('+') && !cnae.includes('mais'))
                .filter(cnae => /^\d/.test(cnae)); // Apenas códigos que começam com dígito
            
            // Criar badges para cada CNAE
            cnaes.forEach(cnae => {
                criarBadgeCnae(cnae, cnaesContainer);
            });
            
            // Mostrar total
            totalCnaesSpan.textContent = `Total: ${cnaes.length} CNAEs vinculados`;
            
            document.getElementById('edit_obrigatorio').checked = documentoData.obrigatorio;
            document.getElementById('edit_observacoes').value = documentoData.observacoes === '-' ? '' : documentoData.observacoes;
            
            // Mostrar modal
            document.getElementById('modalEditarDocumento').classList.remove('hidden');
        }

        function removerDocumento(documentoCodigo, tipoLicenciamentoId) {
            const municipioSelecionado = document.getElementById('municipio').value;
            if (!municipioSelecionado) {
                alert('Selecione um município primeiro.');
                return;
            }
            
            const tipoNomes = {
                1: 'Primeiro Licenciamento',
                2: 'Renovação', 
                3: 'Manutenção'
            };
            
            const tipoNome = tipoNomes[tipoLicenciamentoId] || 'Desconhecido';
            
            if (confirm(`Tem certeza que deseja remover o documento ${documentoCodigo} para ${tipoNome}?\n\nIsso removerá todas as vinculações deste documento com os CNAEs para este tipo de licenciamento.`)) {
                // Criar formulário para envio
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'remover_documento_agrupado';
                form.appendChild(actionInput);
                
                const codigoInput = document.createElement('input');
                codigoInput.type = 'hidden';
                codigoInput.name = 'documento_codigo';
                codigoInput.value = documentoCodigo;
                form.appendChild(codigoInput);
                
                const tipoInput = document.createElement('input');
                tipoInput.type = 'hidden';
                tipoInput.name = 'tipo_licenciamento_id';
                tipoInput.value = tipoLicenciamentoId;
                form.appendChild(tipoInput);
                
                const municipioInput = document.createElement('input');
                municipioInput.type = 'hidden';
                municipioInput.name = 'municipio_nome';
                municipioInput.value = municipioSelecionado;
                form.appendChild(municipioInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function fecharModalEdicao() {
            // Ocultar modal
            document.getElementById('modalEditarDocumento').classList.add('hidden');
            
            // Resetar estado do editor de CNAEs
            const editor = document.getElementById('cnae_editor');
            const removeBtns = document.querySelectorAll('.cnae-remove-btn');
            
            editor.classList.add('hidden');
            removeBtns.forEach(btn => btn.style.display = 'none');
            document.getElementById('novo_cnae').value = '';
        }
        
                 // Gerenciar submit do formulário de edição
        document.getElementById('formEditarDocumento').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Coletar CNAEs atuais do modal
            const cnaesContainer = document.getElementById('edit_cnaes_info');
            const cnaes = Array.from(cnaesContainer.querySelectorAll('div span')).map(span => span.textContent);
            document.getElementById('edit_cnaes_vinculados').value = cnaes.join(',');
            
            const formData = new FormData(this);
            
            // Criar formulário real para envio (já que estamos usando POST reload)
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        });
        
        // Fechar modal ao clicar fora dele
        document.getElementById('modalEditarDocumento').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalEdicao();
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('modalEditarDocumento').classList.contains('hidden')) {
                fecharModalEdicao();
            }
        });

        // Funções para gerenciar CNAEs no modal
        function criarBadgeCnae(cnae, container) {
            const badgeContainer = document.createElement('div');
            badgeContainer.className = 'inline-flex items-center bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-mono';
            
            const cnaeText = document.createElement('span');
            cnaeText.textContent = cnae;
            badgeContainer.appendChild(cnaeText);
            
            // Botão de remoção (só aparece no modo de edição)
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'ml-1 text-red-600 hover:text-red-800 font-bold cnae-remove-btn';
            removeBtn.innerHTML = '❌';
            removeBtn.title = 'Remover CNAE';
            removeBtn.style.display = 'none'; // Oculto por padrão
            removeBtn.onclick = () => removerCnae(badgeContainer);
            badgeContainer.appendChild(removeBtn);
            
            container.appendChild(badgeContainer);
            atualizarTotalCnaes();
        }
        
        function toggleCnaeEditor() {
            const editor = document.getElementById('cnae_editor');
            const removeBtns = document.querySelectorAll('.cnae-remove-btn');
            
            if (editor.classList.contains('hidden')) {
                // Mostrar editor
                editor.classList.remove('hidden');
                removeBtns.forEach(btn => btn.style.display = 'inline');
            } else {
                // Ocultar editor
                editor.classList.add('hidden');
                removeBtns.forEach(btn => btn.style.display = 'none');
            }
        }
        
        function adicionarCnae() {
            const input = document.getElementById('novo_cnae');
            const cnae = input.value.trim();
            
            if (!cnae) {
                alert('Digite um código CNAE válido.');
                return;
            }
            
            // Validar formato básico
            if (!/^\d{7}$/.test(cnae) && !/^\d{4}-?\d\/\d{2}$/.test(cnae)) {
                alert('Código CNAE deve ter 7 dígitos (ex: 4711301) ou formato padrão (ex: 4711-3/01).');
                return;
            }
            
            // Verificar se já existe
            const container = document.getElementById('edit_cnaes_info');
            const existingCnaes = Array.from(container.querySelectorAll('span')).map(span => span.textContent);
            
            if (existingCnaes.includes(cnae)) {
                alert('Este CNAE já está vinculado.');
                return;
            }
            
            // Adicionar badge
            criarBadgeCnae(cnae, container);
            input.value = '';
            input.focus();
        }
        
        function removerCnae(badgeElement) {
            const cnae = badgeElement.querySelector('span').textContent;
            if (confirm(`Remover CNAE ${cnae}?`)) {
                badgeElement.remove();
                atualizarTotalCnaes();
            }
        }
        
        function atualizarTotalCnaes() {
            const container = document.getElementById('edit_cnaes_info');
            const total = container.querySelectorAll('div').length;
            document.getElementById('edit_total_cnaes').textContent = `Total: ${total} CNAEs vinculados`;
        }
        
        // Permitir Enter no campo de CNAE
        document.addEventListener('DOMContentLoaded', function() {
            const novoCnaeInput = document.getElementById('novo_cnae');
            if (novoCnaeInput) {
                novoCnaeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        adicionarCnae();
                    }
                });
            }
        });

        function editarRequisito(id) {
            // Implementar edição
            alert('Funcionalidade de edição será implementada');
        }

        function removerRequisito(id) {
            if (confirm('Tem certeza que deseja remover este requisito?')) {
                // Implementar remoção
                alert('Funcionalidade de remoção será implementada');
            }
        }
    </script>
</body>
</html> 