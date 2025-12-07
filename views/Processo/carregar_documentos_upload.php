<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

// Configuração de log para debug
error_log("=== INÍCIO DO PROCESSAMENTO DE DOCUMENTOS ===");
error_log("Parâmetros recebidos: " . json_encode($_POST));

require_once '../../conf/database.php';
require_once '../../includes/documentos_helper.php';

// Verificar se os parâmetros necessários foram fornecidos
if (!isset($_POST['estabelecimento_id']) || !isset($_POST['tipo_licenciamento'])) {
    error_log("Erro: Parâmetros inválidos - ID estabelecimento ou tipo licenciamento não fornecidos");
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit();
}

$estabelecimento_id = intval($_POST['estabelecimento_id']);
$tipo_licenciamento = $_POST['tipo_licenciamento'];

// Validar o tipo de licenciamento
if (!in_array($tipo_licenciamento, ['primeiro', 'renovacao', 'manter'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de licenciamento inválido']);
    exit();
}

// A função normalizarCnae agora está no documentos_helper.php

// Buscar estabelecimento
$stmtEstab = $conn->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
$stmtEstab->bind_param('i', $estabelecimento_id);
$stmtEstab->execute();
$estabelecimento = $stmtEstab->get_result()->fetch_assoc();

if (!$estabelecimento) {
    echo json_encode(['success' => false, 'message' => 'Estabelecimento não encontrado']);
    exit();
}

// Processar CNAEs
$cnaes = [];

// Verificar se é pessoa física ou jurídica e processar CNAEs de acordo
if ($estabelecimento['tipo_pessoa'] === 'fisica') {
    // Para pessoa física, buscar CNAEs na tabela estabelecimento_cnaes
    $stmtCnaesPF = $conn->prepare("SELECT cnae, descricao FROM estabelecimento_cnaes WHERE estabelecimento_id = ?");
    $stmtCnaesPF->bind_param('i', $estabelecimento_id);
    $stmtCnaesPF->execute();
    $resultCnaesPF = $stmtCnaesPF->get_result();
    
    while ($row = $resultCnaesPF->fetch_assoc()) {
        $cnaes[] = normalizarCnae($row['cnae']);
    }
} else {
    // Para pessoa jurídica, usar o CNAE fiscal e secundários da tabela estabelecimentos
    if (!empty($estabelecimento['cnae_fiscal'])) {
        $cnaes[] = normalizarCnae($estabelecimento['cnae_fiscal']);
    }
    
    $secundarios = json_decode($estabelecimento['cnaes_secundarios'], true);
    if (!empty($secundarios)) {
        foreach ($secundarios as $cnae) {
            if (!empty($cnae['codigo'])) {
                $cnaes[] = normalizarCnae($cnae['codigo']);
            }
        }
    }
}

// Log para depuração
error_log("CNAEs encontrados para estabelecimento ID $estabelecimento_id: " . print_r($cnaes, true));

// Buscar documentos - PRIMEIRO TENTAR A NOVA ESTRUTURA
$documentos = [];
$usou_nova_estrutura = false;

// Verificar se as novas tabelas existem e têm dados
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM cnae_documentos_requisitos WHERE ativo = 1");
$stmt->execute();
$resultado = $stmt->get_result()->fetch_assoc();

if ($resultado['total'] > 0) {
    // Usar a nova estrutura
    error_log("Usando nova estrutura de documentos");
    $usou_nova_estrutura = true;
    
    // Mapear tipo de licenciamento para ID
    $tipo_licenciamento_map = [
        'primeiro' => 1,
        'renovacao' => 2,
        'manter' => 3
    ];
    
    $tipo_id = $tipo_licenciamento_map[$tipo_licenciamento];
    
    foreach ($cnaes as $cnae) {
        if (empty($cnae)) continue;
        
        // Buscar na nova estrutura
        $sql = "SELECT DISTINCT
            td.codigo,
            td.nome,
            cdr.obrigatorio
        FROM cnae_documentos_requisitos cdr
        JOIN atividades_cnae ac ON cdr.atividade_cnae_id = ac.id
        JOIN tipos_documentos td ON cdr.tipo_documento_id = td.id
        JOIN tipos_licenciamento tl ON cdr.tipo_licenciamento_id = tl.id
        WHERE ac.codigo_cnae = ? 
        AND tl.id = ?
        AND cdr.ativo = 1
        ORDER BY td.codigo";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $cnae, $tipo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $key = $row['codigo'];
            if (!isset($documentos[$key])) {
                $documentos[$key] = [
                    'codigo' => $row['codigo'],
                    'nome' => $row['nome'],
                    'obrigatorio' => $row['obrigatorio']
                ];
            }
        }
    }
}

// Se não encontrou documentos na nova estrutura, usar a estrutura antiga
if (!$usou_nova_estrutura || empty($documentos)) {
    error_log("Usando estrutura antiga de documentos");
    
    foreach ($cnaes as $cnae) {
        if (empty($cnae)) continue;
        
        $stmtCnae = $conn->prepare("SELECT * FROM cnae_documentos WHERE normalizarCnae(cnae) = ? AND pactuacao = 'Municipal'");
        $stmtCnae->bind_param('s', $cnae);
        $stmtCnae->execute();
        $result = $stmtCnae->get_result();

        if ($row = $result->fetch_assoc()) {
            $campo = '';
            switch ($tipo_licenciamento) {
                case 'primeiro':
                    $campo = 'primeiro_licenciamento';
                    break;
                case 'renovacao':
                    $campo = 'renovacao';
                    break;
                case 'manter':
                    $campo = 'manter_estabelecimento';
                    break;
            }

            $docs = explode(',', $row[$campo]);
            foreach ($docs as $doc) {
                $doc = trim($doc);
                if (!empty($doc)) {
                    $key = str_pad($doc, 3, '0', STR_PAD_LEFT);
                    if (!isset($documentos[$key])) {
                        // Buscar nome do documento usando helper
                        $nome_documento = getNomeDocumentoBanco($conn, $doc);
                        
                        $documentos[$key] = [
                            'codigo' => $doc,
                            'nome' => $nome_documento,
                            'obrigatorio' => true
                        ];
                    }
                }
            }
        }
    }
}

// Converter array associativo para array indexado
$documentos_lista = array_values($documentos);

// Verificar se encontrou documentos
if (empty($documentos_lista)) {
    error_log("Nenhum documento encontrado para os CNAEs: " . implode(", ", $cnaes));
    echo json_encode([
        'success' => true,
        'documentos' => [],
        'message' => 'Nenhum documento encontrado para as atividades deste estabelecimento',
        'debug_info' => [
            'estabelecimento_id' => $estabelecimento_id,
            'tipo_pessoa' => $estabelecimento['tipo_pessoa'],
            'tipo_licenciamento' => $tipo_licenciamento,
            'cnaes_encontrados' => $cnaes,
            'usou_nova_estrutura' => $usou_nova_estrutura
        ]
    ]);
} else {
    // Ordenar documentos por código
    usort($documentos_lista, function($a, $b) {
        return intval($a['codigo']) - intval($b['codigo']);
    });
    
    error_log("Documentos encontrados: " . count($documentos_lista));
    error_log("Estrutura usada: " . ($usou_nova_estrutura ? 'Nova' : 'Antiga'));
    
    echo json_encode([
        'success' => true,
        'documentos' => $documentos_lista,
        'estrutura_usada' => $usou_nova_estrutura ? 'nova' : 'antiga',
        'total_documentos' => count($documentos_lista)
    ]);
}
