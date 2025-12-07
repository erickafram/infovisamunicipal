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
require_once '../../models/Documento.php';
require_once '../../models/Arquivo.php';
require_once '../../models/LogVisualizacao.php';
require_once '../../models/PastaDocumento.php';
require_once '../../includes/documentos_helper.php';

$processoModel = new Processo($conn);
$documentoModel = new Documento($conn);
$arquivoModel = new Arquivo($conn);
$pastaDocumento = new PastaDocumento($conn);

if (isset($_GET['id'])) {
    $processoId = $_GET['id'];
    $dadosProcesso = $processoModel->findById($processoId);

    if (!$dadosProcesso) {
        echo "Processo não encontrado!";
        exit();
    }

    // Verificar se o usuário está vinculado ao estabelecimento e se o processo não é de denúncia
    $userId = $_SESSION['user']['id'];
    $estabelecimentos = $processoModel->getEstabelecimentosByUsuario($userId);
    $estabelecimentoIds = array_column($estabelecimentos, 'estabelecimento_id');

    if (!in_array($dadosProcesso['estabelecimento_id'], $estabelecimentoIds) || $dadosProcesso['tipo_processo'] == 'DENÚNCIA') {
        echo "Acesso negado!";
        exit();
    }

    // Verificar se é assinante
    $isAssinante = false;
    $stmt = $conn->prepare("SELECT * FROM assinatura_planos 
                          WHERE usuario_id = ? 
                          AND status = 'ativo' 
                          AND data_expiracao > NOW()");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $isAssinante = true;
    }
} else {
    echo "ID do processo não fornecido!";
    exit();
}

function generateUniqueFileName($dir, $filename)
{
    $path_info = pathinfo($filename);
    $basename = $path_info['filename'];
    $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
    $new_filename = $filename;
    $counter = 1;

    while (file_exists($dir . $new_filename)) {
        $new_filename = $basename . '(' . $counter . ')' . $extension;
        $counter++;
    }

    return $new_filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    if ($dadosProcesso['status'] !== 'ARQUIVADO') {
        $total_files = count($_FILES['files']['name']);
        $upload_dir = "../../uploads/";

        // Obter os nomes dos documentos, se disponíveis
        $doc_names = isset($_POST['doc_names']) ? $_POST['doc_names'] : [];

        // Array para armazenar mensagens de erro
        $upload_errors = [];
        

        
        // Array para rastrear arquivos processados e evitar duplicação
        $arquivos_processados = [];

        for ($i = 0; $i < $total_files; $i++) {
            // Verificar se o arquivo já foi processado (evitar duplicação)
            if (in_array($_FILES["files"]["name"][$i], $arquivos_processados)) {
                continue;
            }
            $arquivos_processados[] = $_FILES["files"]["name"][$i];
            $file_name = basename($_FILES["files"]["name"][$i]);
            $file_type = mime_content_type($_FILES["files"]["tmp_name"][$i]);
            $file_size = $_FILES["files"]["size"][$i];
            $target_file = $upload_dir . generateUniqueFileName($upload_dir, $file_name);

            // Obter o nome do documento, se disponível
            $doc_name = isset($doc_names[$i]) ? $doc_names[$i] : $file_name;



            // Tipos permitidos (todos os usuários podem enviar PDF e imagens)
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];

            if (!in_array($file_type, $allowedTypes)) {
                $upload_errors[] = "Erro: Tipo de arquivo não permitido: " . $file_name;
                continue;
            }

            if ($file_size > 5 * 1024 * 1024) {
                $upload_errors[] = "Erro: O arquivo " . $file_name . " excede o tamanho máximo permitido de 5MB.";
                continue;
            }


            // Adicione no início do script para exibir erros
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            // Converter imagens para PDF (disponível para todos os usuários)
            if (in_array($file_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                try {
                    error_log("Iniciando conversão de: " . $_FILES["files"]["tmp_name"][$i]);

                    // Verificar se o arquivo temporário existe
                    if (!file_exists($_FILES["files"]["tmp_name"][$i])) {
                        throw new Exception("Arquivo temporário não encontrado");
                    }

                    // Verificar se a extensão Imagick está disponível
                    if (extension_loaded('imagick') && class_exists('Imagick')) {
                        $imagick = new Imagick();
                        $imagick->readImage($_FILES["files"]["tmp_name"][$i]);
                        $imagick->setImageFormat('pdf');
                        $imagick->setResolution(300, 300); // Aumentar resolução para melhor qualidade

                        $new_name = pathinfo($file_name, PATHINFO_FILENAME) . '.pdf';
                        $target_file = $upload_dir . generateUniqueFileName($upload_dir, $new_name);

                        // Criar diretório recursivamente se necessário
                        if (!is_dir(dirname($target_file))) {
                            mkdir(dirname($target_file), 0755, true);
                        }

                        error_log("Salvando PDF em: " . $target_file);

                        // Salvar o PDF
                        if (!$imagick->writeImages($target_file, true)) { // Usar writeImages para múltiplas páginas
                            throw new Exception("Falha ao salvar o PDF");
                        }

                        // Verificar se o arquivo foi criado
                        if (!file_exists($target_file)) {
                            throw new Exception("Arquivo PDF não foi gerado");
                        }

                        error_log("Conversão bem-sucedida: " . $target_file);
                        $file_name = basename($target_file);

                        // Fechar recurso do Imagick
                        $imagick->clear();
                        $imagick->destroy();
                    } else {
                        // Fallback: apenas fazer upload da imagem original se Imagick não estiver disponível
                        if (!move_uploaded_file($_FILES["files"]["tmp_name"][$i], $target_file)) {
                            error_log("Falha no upload: " . $_FILES["files"]["name"][$i]);
                            continue;
                        }
                        error_log("Imagick não disponível, imagem enviada sem conversão");
                    }
                } catch (Exception $e) {
                    error_log("ERRO NA CONVERSÃO: " . $e->getMessage());
                    $_SESSION['error'] = "Erro ao converter imagem para PDF: " . $e->getMessage();

                    // Tentar fazer upload do arquivo original em caso de erro
                    if (!move_uploaded_file($_FILES["files"]["tmp_name"][$i], $target_file)) {
                        error_log("Falha no upload após erro de conversão: " . $_FILES["files"]["name"][$i]);
                        continue;
                    }
                }
            } else {
                // Upload normal para arquivos PDF
                if (!move_uploaded_file($_FILES["files"]["tmp_name"][$i], $target_file)) {
                    error_log("Falha no upload: " . $_FILES["files"]["name"][$i]);
                    continue;
                }
            }


            // Registrar arquivo no banco de dados
            $caminho_arquivo = 'uploads/' . basename($target_file);
            $result = $documentoModel->createDocumento($processoId, basename($target_file), $caminho_arquivo, $doc_name);
            

        }


        
        header("Location: detalhes_processo_empresa.php?id=$processoId");
        exit();
    } else {
        echo "Erro: Não é permitido fazer upload de arquivo para processos arquivados.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_arquivo_negado'])) {
    if ($dadosProcesso['status'] !== 'ARQUIVADO') {
        $documento_id = $_POST['documento_id'];

        if (isset($_FILES['novo_arquivo']) && $_FILES['novo_arquivo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../../uploads/";
            $file_name = basename($_FILES["novo_arquivo"]["name"]);
            $file_type = mime_content_type($_FILES["novo_arquivo"]["tmp_name"]);
            $file_size = $_FILES["novo_arquivo"]["size"];

            // Tipos permitidos (todos os usuários podem enviar PDF e imagens)
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];

            if (!in_array($file_type, $allowedTypes)) {
                $_SESSION['error'] = "Tipo de arquivo não permitido. Somente PDF e imagens (JPEG, PNG, GIF) são permitidos.";
                header("Location: detalhes_processo_empresa.php?id=$processoId");
                exit();
            }

            // Verificação de tamanho do arquivo
            if ($file_size > 5 * 1024 * 1024) { // 5MB
                $_SESSION['error'] = "O arquivo excede o tamanho máximo permitido de 5MB.";
                header("Location: detalhes_processo_empresa.php?id=$processoId");
                exit();
            }

            $target_file = $upload_dir . generateUniqueFileName($upload_dir, $file_name);
            $conversionSuccess = false;

            // Converter imagens para PDF (disponível para todos os usuários)
            if (in_array($file_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                try {
                    // Verificar se a extensão Imagick está disponível
                    if (extension_loaded('imagick') && class_exists('Imagick')) {
                        $imagick = new Imagick();
                        $imagick->readImage($_FILES["novo_arquivo"]["tmp_name"]);
                        $imagick->setImageFormat('pdf');

                        $new_name = pathinfo($file_name, PATHINFO_FILENAME) . '.pdf';
                        $target_file = $upload_dir . generateUniqueFileName($upload_dir, $new_name);

                        // Forçar criação do diretório se necessário
                        if (!is_dir(dirname($target_file))) {
                            mkdir(dirname($target_file), 0755, true);
                        }

                        if ($imagick->writeImage($target_file)) {
                            $file_name = basename($target_file);
                            $conversionSuccess = true;
                        } else {
                            throw new Exception("Falha ao salvar o PDF convertido");
                        }

                        // Fechar recurso do Imagick
                        $imagick->clear();
                        $imagick->destroy();
                    } else {
                        // Fallback: apenas fazer upload da imagem original se Imagick não estiver disponível
                        if (move_uploaded_file($_FILES["novo_arquivo"]["tmp_name"], $target_file)) {
                            $conversionSuccess = true;
                        } else {
                            throw new Exception("Falha no upload da imagem original");
                        }
                        error_log("Imagick não disponível, imagem enviada sem conversão");
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Erro na conversão para PDF: " . $e->getMessage();
                    header("Location: detalhes_processo_empresa.php?id=$processoId");
                    exit();
                }
            }

            // Movimentação do arquivo não convertido
            if (!$conversionSuccess) {
                if (!move_uploaded_file($_FILES["novo_arquivo"]["tmp_name"], $target_file)) {
                    $_SESSION['error'] = "Erro ao fazer upload do arquivo";
                    header("Location: detalhes_processo_empresa.php?id=$processoId");
                    exit();
                }
            }

            // Remover arquivo antigo
            $documentoAntigo = $documentoModel->findById($documento_id);
            if ($documentoAntigo && file_exists("../../" . $documentoAntigo['caminho_arquivo'])) {
                unlink("../../" . $documentoAntigo['caminho_arquivo']);
            }

            // Atualizar banco de dados
            $caminho_arquivo = 'uploads/' . basename($target_file);
            $documentoModel->updateDocumentoNegado($documento_id, $file_name, $caminho_arquivo);

            header("Location: detalhes_processo_empresa.php?id=$processoId");
            exit();
        }
    } else {
        $_SESSION['error'] = "Processo arquivado - não é permitido alterar documentos";
        header("Location: detalhes_processo_empresa.php?id=$processoId");
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_documento'])) {
    $documento_id = $_POST['documento_id'];
    $documento = $documentoModel->findById($documento_id);

    if ($documento && $documento['status'] == 'pendente') {
        $caminhoCompleto = "../../" . $documento['caminho_arquivo']; // Ajuste o caminho conforme necessário
        if ($documentoModel->deleteDocumento($documento_id, $userId)) {
            if (file_exists($caminhoCompleto)) {
                unlink($caminhoCompleto);
            }
            header("Location: detalhes_processo_empresa.php?id=$processoId");
            exit();
        } else {
            echo "Erro ao excluir o documento.";
        }
    } else {
        echo "Documento não encontrado ou não está pendente.";
    }
}

$documentos = $documentoModel->getDocumentosByProcesso($processoId);
$arquivos = $arquivoModel->getArquivosComAssinaturasCompletas($processoId); // Usar o novo método aqui
$alertas = $processoModel->getAlertasByProcesso($processoId);



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
unset($item); // Importante: remover referência após o loop

// Determinar pasta ativa (padrão é 'geral' para documentos não organizados)
$pasta_ativa = isset($_GET['pasta']) ? $_GET['pasta'] : 'geral';

// Filtrar itens com base na pasta ativa
if ($pasta_ativa === 'geral') {
    // Mostrar apenas itens que não estão em nenhuma pasta
    $itens_exibir = array_filter($itens, function($item) {
        return $item['pasta'] === false;
    });
    

    
    // Re-indexar array para garantir índices corretos
    $itens_exibir = array_values($itens_exibir);
} else {
    // Mostrar itens da pasta específica
    $itens_exibir = $pastaDocumento->getItensByPasta($pasta_ativa);
}

include '../../includes/header_empresa.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Processo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .document-category .category-title {
            font-size: 0.85rem;
            color: #495057;
        }

        .list-group-item {
            padding: 0.75rem 1.25rem;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .list-group-item:hover {
            background-color: #f8f9fa;
        }

        .form-check-input {
            margin-top: 0.25em;
        }

        .small {
            font-size: 0.8rem;
        }

        .fw-medium {
            font-weight: 500;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .document-item {
                padding: 12px;
            }
        }

        /* Documentos Container Styles */
        #documentosContainer {
            max-height: 400px;
            overflow-y: auto;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .document-item {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .doc-label {
            font-size: 0.95rem;
            font-weight: 500;
        }

        .doc-text {
            display: inline-block;
            margin-top: 2px;
        }

        .doc-input {
            margin-top: 10px;
        }

        .doc-file {
            padding: 8px;
            height: auto;
        }

        /* Estilos para as abas das pastas */
        .pasta-tabs {
            overflow-x: auto;
            scrollbar-width: thin;
        }

        .pasta-tabs::-webkit-scrollbar {
            height: 4px;
        }

        .pasta-tabs::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .pasta-tabs::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }

        .pasta-tabs::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Responsividade para as abas */
        @media (max-width: 768px) {
            .pasta-tabs nav {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .pasta-tabs nav a {
                flex-shrink: 0;
                min-width: max-content;
            }
        }
    </style>
</head>

<body>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="container mx-auto px-3 py-3 mt-0">
        <?php
        $temAlertas = false;
        foreach ($alertas as $alerta) {
            if ($alerta['status'] !== 'finalizado') {
                $temAlertas = true;
                break;
            }
        }

        if ($temAlertas || ($dadosProcesso['status'] === 'PARADO' && !empty($dadosProcesso['motivo_parado']))) :
        ?>
            <!-- Container de Alertas e Processos Parados -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
                <div class="bg-yellow-50 px-4 py-3 border-b border-yellow-200">
                    <h3 class="text-xs font-medium text-yellow-800 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-yellow-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        ATENÇÃO
                    </h3>
                </div>
                <div class="p-4">
                    <!-- Exibição de Alertas -->
                    <?php if ($temAlertas) : ?>
                        <div class="mb-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                                </svg>
                                Alertas
                            </h4>
                            <?php foreach ($alertas as $alerta) : ?>
                                <?php if ($alerta['status'] !== 'finalizado') : ?>
                                    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-3 rounded-md">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-xs text-amber-700">
                                                    <strong>Descrição:</strong> <?php echo htmlspecialchars($alerta['descricao']); ?>
                                                </p>
                                                <p class="text-xs text-amber-700 mt-1">
                                                    <strong>Prazo:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($alerta['prazo']))); ?>
                                                </p>
                                                <p class="text-xs text-amber-700 mt-1">
                                                    <strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($alerta['status'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Exibição de Motivo Parado -->
                    <?php if ($dadosProcesso['status'] === 'PARADO' && !empty($dadosProcesso['motivo_parado'])) : ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-red-800 mb-2 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd" />
                                        </svg>
                                        Processo Parado
                                    </h4>
                                    <p class="text-xs text-red-700">
                                        <strong>Motivo:</strong> <?php echo htmlspecialchars($dadosProcesso['motivo_parado']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>



        <!-- Card de Informações do Processo -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                <h3 class="text-xs font-medium text-gray-700 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    Informações do Processo
                </h3>
            </div>
            <div class="p-4">
                <!-- Informações Principais do Processo -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">Número do Processo:</span>
                        <span class="text-gray-900 text-sm font-mono"><?php echo htmlspecialchars($dadosProcesso['numero_processo'] ?? 'N/A'); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">Tipo do Processo:</span>
                        <span class="text-gray-900 text-sm font-medium"><?php echo htmlspecialchars($dadosProcesso['tipo_processo'] ?? 'N/A'); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">Status:</span>
                        <?php
                        $status_bg_color = '';
                        $status_text_color = '';
                        switch (strtolower($dadosProcesso['status'] ?? '')) {
                            case 'ativo':
                                $status_bg_color = 'bg-green-100';
                                $status_text_color = 'text-green-800';
                                break;
                            case 'parado':
                                $status_bg_color = 'bg-yellow-100';
                                $status_text_color = 'text-yellow-800';
                                break;
                            case 'arquivado':
                                $status_bg_color = 'bg-gray-100';
                                $status_text_color = 'text-gray-800';
                                break;
                            default:
                                $status_bg_color = 'bg-blue-100';
                                $status_text_color = 'text-blue-800';
                        }
                        ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_bg_color . ' ' . $status_text_color; ?>">
                            <?php echo htmlspecialchars(ucfirst(strtolower($dadosProcesso['status'] ?? 'N/A'))); ?>
                        </span>
                    </div>
                </div>

                <!-- Divisor -->
                <div class="border-t border-gray-200 my-3"></div>

                <!-- Informações do Estabelecimento/Pessoa -->
                <?php if ($dadosProcesso['tipo_pessoa'] === 'fisica'): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">Nome:</span>
                        <span class="text-gray-900 text-sm font-medium"><?php echo htmlspecialchars($dadosProcesso['nome'] ?? 'N/A'); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">CPF:</span>
                        <span class="text-gray-900 text-sm"><?php echo htmlspecialchars($dadosProcesso['cpf'] ?? 'N/A'); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">Telefone:</span>
                        <span class="text-gray-900 text-sm"><?php echo htmlspecialchars($dadosProcesso['ddd_telefone_1'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div class="md:col-span-2">
                        <span class="text-gray-500 font-medium text-xs block">Nome do Estabelecimento:</span>
                        <a href="../../views/Estabelecimento/detalhes_estabelecimento_empresa.php?id=<?php echo htmlspecialchars($dadosProcesso['estabelecimento_id']); ?>"
                            class="text-blue-600 hover:text-blue-800 font-medium text-sm hover:underline">
                            <?php echo htmlspecialchars($dadosProcesso['nome_fantasia'] ?? 'N/A'); ?>
                        </a>
                    </div>
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">CNPJ:</span>
                        <span class="text-gray-900 text-sm"><?php echo htmlspecialchars($dadosProcesso['cnpj'] ?? 'N/A'); ?></span>
                    </div>
                    </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">Telefone:</span>
                        <span class="text-gray-900 text-sm"><?php echo htmlspecialchars($dadosProcesso['ddd_telefone_1'] ?? 'N/A'); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 font-medium text-xs block">Data de Abertura:</span>
                        <span class="text-gray-900 text-sm"><?php echo htmlspecialchars(isset($dadosProcesso['data_abertura']) ? date('d/m/Y', strtotime($dadosProcesso['data_abertura'])) : 'N/A'); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Coluna direita: Upload de Arquivos -->
            <div class="lg:col-span-4">

                <?php
                // Verifica se o processo é de Licenciamento ou Projeto Arquitetônico
                if ($dadosProcesso['tipo_processo'] === 'LICENCIAMENTO' || $dadosProcesso['tipo_processo'] === 'PROJETO ARQUITETÔNICO' || $dadosProcesso['tipo_processo'] === 'AÇÕES DE ROTINA') :

                    // Buscar documentos necessários baseados nas atividades do estabelecimento
                    $estabelecimento_id = $dadosProcesso['estabelecimento_id'];

                    // Buscar estabelecimento
                    $stmtEstab = $conn->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
                    $stmtEstab->bind_param('i', $estabelecimento_id);
                    $stmtEstab->execute();
                    $estabelecimento = $stmtEstab->get_result()->fetch_assoc();

                    // A função normalizarCnae agora está no documentos_helper.php

                    // Processar CNAEs com base no tipo de pessoa
                    $cnaes = [];
                    
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
                        // Para pessoa jurídica, usar o formato original
                        if (!empty($estabelecimento['cnae_fiscal'])) {
                            $cnaes[] = normalizarCnae($estabelecimento['cnae_fiscal']);
                        }
                        
                        // Processar CNAEs secundários se existirem
                        if (!empty($estabelecimento['cnaes_secundarios'])) {
                            $secundarios = json_decode($estabelecimento['cnaes_secundarios'], true);
                            if (is_array($secundarios)) {
                                foreach ($secundarios as $cnae) {
                                    if (!empty($cnae['codigo'])) {
                                        $cnaes[] = normalizarCnae($cnae['codigo']);
                                    }
                                }
                            }
                        }
                    }

                    // Buscar documentos para cada CNAE (usando primeiro licenciamento como padrão)
                    $tipo_processo = 'primeiro';
                    $documentos = [];
                    foreach ($cnaes as $cnae) {
                        if (empty($cnae)) continue;
                        $stmtCnae = $conn->prepare("SELECT * FROM cnae_documentos WHERE normalizarCnae(cnae) = ? AND pactuacao = 'Municipal'");
                        $stmtCnae->bind_param('s', $cnae);
                        $stmtCnae->execute();
                        $result = $stmtCnae->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $docs = match ($tipo_processo) {
                                'primeiro'  => explode(',', $row['primeiro_licenciamento']),
                                'renovacao' => explode(',', $row['renovacao']),
                                'manter'    => explode(',', $row['manter_estabelecimento']),
                                default     => []
                            };
                            $documentos = array_merge($documentos, $docs);
                        }
                    }
                    $documentos = array_unique($documentos);

                    // Buscar nomes dos documentos do banco
                    $nomesDocumentos = getTodosDocumentosBanco($conn);
                ?>
                    <!-- Modal para exibir a lista de documentos -->
                    <div class="modal fade" id="documentosModal" tabindex="-1" aria-labelledby="documentosModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="documentosModalLabel">Relação de Documentos</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="documentosConteudo">
                                    <!-- O conteúdo do modal será carregado via AJAX -->
                                    Carregando...
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        function verRelacao(tipoProcesso) {
                            // Exibir o modal
                            $('#documentosModal').modal('show');

                            // Fazer requisição AJAX para carregar os documentos
                            $.ajax({
                                url: 'carregar_documentos.php',
                                method: 'POST',
                                data: {
                                    tipo_processo: tipoProcesso
                                },
                                success: function(data) {
                                    $('#documentosConteudo').html(data);
                                },
                                error: function() {
                                    $('#documentosConteudo').html("Erro ao carregar os documentos.");
                                }
                            });
                        }
                    </script>

                <?php endif; ?>

                <!-- Card de Upload de Arquivos -->
                <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                        <h3 class="text-xs font-medium text-gray-700 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                            Upload de Arquivos
                        </h3>
                    </div>
                    <div class="p-4">
                        <!-- Alerta importante -->
                        <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-amber-800">Atenção</h3>
                                    <div class="mt-2 text-xs text-amber-700">
                                        <p>Certifique-se de que os arquivos enviados estejam de acordo com as atividades do processo e sejam legíveis. Arquivos inválidos podem causar atrasos na análise.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($dadosProcesso['status'] === 'ARQUIVADO') : ?>
                            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 text-center">
                                <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm text-gray-600">O upload de arquivos não é permitido para processos arquivados.</p>
                            </div>
                        <?php else : ?>
                            <!-- Botões de Upload -->
                            <div class="space-y-3">
                                <?php if ($dadosProcesso['tipo_processo'] === 'LICENCIAMENTO'): ?>
                                    <!-- Para processos de LICENCIAMENTO -->
                                    <button type="button" 
                                        class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#tipoLicenciamentoModal">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        Fazer Upload de Documentos
                                    </button>
                                <?php else: ?>
                                    <!-- Para outros tipos de processos -->
                                    <button type="button" 
                                        class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#uploadDiretoModal">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        Fazer Upload de Documentos
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Modal para upload direto (para processos que não são de licenciamento) -->
                            <div class="modal fade" id="uploadDiretoModal" tabindex="-1" aria-labelledby="uploadDiretoModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="uploadDiretoModalLabel">Upload de Documentos</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Selecione os arquivos que deseja enviar para este processo.
                                            </div>
                                            <form action="detalhes_processo_empresa.php?id=<?php echo $processoId; ?>" method="POST" enctype="multipart/form-data">
                                                <div class="mb-3">
                                                    <label for="direct_files" class="form-label">Selecione os arquivos</label>
                                                    <input class="form-control" type="file" id="direct_files" name="files[]"
                                                        accept="application/pdf, image/jpeg, image/png, image/gif" multiple required>
                                                    <div class="form-text">Você pode selecionar múltiplos arquivos. Formatos permitidos: PDF, JPEG, PNG, GIF.</div>
                                                </div>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Nota:</strong> Imagens (JPEG, PNG, GIF) serão automaticamente convertidas para PDF.
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Enviar Documentos</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal para seleção do tipo de licenciamento (apenas para processos de licenciamento) -->
                            <div class="modal fade" id="tipoLicenciamentoModal" tabindex="-1" aria-labelledby="tipoLicenciamentoModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="tipoLicenciamentoModalLabel">Selecione o Tipo de Licenciamento</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Selecione o tipo de licenciamento para visualizar os documentos requeridos correspondentes.
                                            </div>

                                            <div class="d-grid gap-3">
                                                <button type="button" class="btn btn-outline-primary p-3 d-flex align-items-center" onclick="selecionarTipoLicenciamento('primeiro')">
                                                    <i class="fas fa-file-medical fa-2x me-3"></i>
                                                    <div class="text-start">
                                                        <strong>Primeiro Licenciamento</strong>
                                                        <div class="small text-muted">Para estabelecimentos que estão solicitando licença pela primeira vez</div>
                                                    </div>
                                                </button>

                                                <button type="button" class="btn btn-outline-primary p-3 d-flex align-items-center" onclick="selecionarTipoLicenciamento('renovacao')">
                                                    <i class="fas fa-sync-alt fa-2x me-3"></i>
                                                    <div class="text-start">
                                                        <strong>Renovação</strong>
                                                        <div class="small text-muted">Para renovação de licenças existentes</div>
                                                    </div>
                                                </button>

                                                <button type="button" class="btn btn-outline-primary p-3 d-flex align-items-center" onclick="selecionarTipoLicenciamento('manter')">
                                                    <i class="fas fa-clipboard-check fa-2x me-3"></i>
                                                    <div class="text-start">
                                                        <strong>Manutenção</strong>
                                                        <div class="small text-muted">Para manutenção de estabelecimentos já licenciados</div>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal para seleção de documentos -->
                            <div class="modal fade" id="uploadDocumentosModal" tabindex="-1" aria-labelledby="uploadDocumentosModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="uploadDocumentosModalLabel">Upload de Documentos</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="uploadForm" action="detalhes_processo_empresa.php?id=<?php echo $processoId; ?>" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" id="tipoLicenciamentoSelecionado" name="tipo_licenciamento" value="primeiro">

                                                <div class="alert alert-primary mb-3" id="tipoLicenciamentoInfo">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <span id="tipoLicenciamentoTexto">Documentos para Primeiro Licenciamento</span>
                                                </div>

                                                <!-- Abas para os diferentes tipos de documentos -->
                                                <ul class="nav nav-tabs mb-3" id="uploadTabs" role="tablist">
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link active" id="required-tab" data-bs-toggle="tab" data-bs-target="#required" type="button" role="tab" aria-controls="required" aria-selected="true">Documentos Requeridos</button>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link" id="additional-tab" data-bs-toggle="tab" data-bs-target="#additional" type="button" role="tab" aria-controls="additional" aria-selected="false">Documentos Adicionais</button>
                                                    </li>
                                                </ul>

                                                <div class="tab-content" id="uploadTabsContent">
                                                    <!-- Aba de Documentos Requeridos -->
                                                    <div class="tab-pane fade show active" id="required" role="tabpanel" aria-labelledby="required-tab">
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            Selecione os documentos requeridos para o seu estabelecimento com base nas atividades (CNAEs).
                                                        </div>

                                                        <div id="documentosContainer" class="mb-3">
                                                            <!-- Os documentos serão carregados dinamicamente via JavaScript -->
                                                            <div class="text-center p-3">
                                                                <div class="spinner-border text-primary" role="status">
                                                                    <span class="visually-hidden">Carregando...</span>
                                                                </div>
                                                                <p class="mt-2">Carregando documentos...</p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Aba de Documentos Adicionais -->
                                                    <div class="tab-pane fade" id="additional" role="tabpanel" aria-labelledby="additional-tab">
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            Você pode enviar documentos adicionais como ofícios, memorandos ou outros documentos relevantes.
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="additional_files" class="form-label">Selecione documentos adicionais</label>
                                                            <input class="form-control" type="file" id="additional_files" name="additional_files[]"
                                                                accept="application/pdf, image/jpeg, image/png, image/gif" multiple>
                                                            <div class="form-text">Você pode selecionar múltiplos arquivos.</div>
                                                        </div>

                                                        <div id="additionalFileList" class="mb-3" style="display: none;">
                                                            <h6>Arquivos adicionais selecionados:</h6>
                                                            <ul id="selectedAdditionalFiles" class="list-group"></ul>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="allSelectedFiles" class="mt-4">
                                                    <h6>Todos os arquivos selecionados:</h6>
                                                    <ul id="filesSummary" class="list-group"></ul>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" onclick="voltarParaTipoLicenciamento()">Voltar</button>
                                                    <button type="submit" class="btn btn-primary" id="submitUpload">Enviar Documentos</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script>
                                // Variável para verificar se o usuário é assinante
                                const isAssinante = <?php echo $isAssinante ? 'true' : 'false'; ?>;

                                // Objeto para armazenar todos os arquivos selecionados
                                const allFiles = {
                                    required: {},
                                    additional: []
                                };

                                // Função para selecionar o tipo de licenciamento
                                function selecionarTipoLicenciamento(tipo) {
                                    // Atualizar o valor do campo oculto
                                    document.getElementById('tipoLicenciamentoSelecionado').value = tipo;

                                    // Atualizar o texto informativo
                                    let textoTipo = '';
                                    switch (tipo) {
                                        case 'primeiro':
                                            textoTipo = 'Documentos para Primeiro Licenciamento';
                                            break;
                                        case 'renovacao':
                                            textoTipo = 'Documentos para Renovação de Licença';
                                            break;
                                        case 'manter':
                                            textoTipo = 'Documentos para Manutenção de Estabelecimento';
                                            break;
                                    }
                                    document.getElementById('tipoLicenciamentoTexto').textContent = textoTipo;

                                    // Carregar os documentos correspondentes
                                    carregarDocumentos(tipo);

                                    // Fechar o modal de seleção de tipo e abrir o modal de upload
                                    $('#tipoLicenciamentoModal').modal('hide');
                                    $('#uploadDocumentosModal').modal('show');
                                }

                                // Função para carregar os documentos baseados no tipo selecionado
                                function carregarDocumentos(tipo) {
                                    const container = document.getElementById('documentosContainer');
                                    const requiredTab = document.getElementById('required-tab');
                                    const additionalTab = document.getElementById('additional-tab');

                                    // Mostrar indicador de carregamento
                                    container.innerHTML = `
                                        <div class="text-center p-3">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Carregando...</span>
                                            </div>
                                            <p class="mt-2">Carregando documentos...</p>
                                        </div>
                                    `;

                                    // Fazer uma requisição AJAX para buscar os documentos
                                    $.ajax({
                                        url: 'carregar_documentos_upload.php',
                                        method: 'POST',
                                        data: {
                                            estabelecimento_id: <?php echo $dadosProcesso['estabelecimento_id']; ?>,
                                            tipo_licenciamento: tipo
                                        },
                                        success: function(response) {
                                            console.log("Resposta do servidor:", response); // Ajuda na depuração
                                            try {
                                                const data = JSON.parse(response);

                                                if (data.success && data.documentos && data.documentos.length > 0) {
                                                    let html = '';

                                                    data.documentos.forEach(doc => {
                                                        html += `
                                                            <div class="document-item">
                                                                <div class="doc-row mb-4">
                                                                    <div class="doc-label mb-2">
                                                                        <span class="badge bg-secondary me-2">${doc.codigo}</span>
                                                                        <span class="doc-text">${doc.nome}</span>
                                                                    </div>
                                                                    <div class="doc-input">
                                                                        <div class="input-group">
                                                                            <input type="file" class="form-control doc-file" 
                                                                                id="doc_${doc.codigo}" 
                                                                                name="doc_files[${doc.codigo}]" 
                                                                                accept="application/pdf, image/jpeg, image/png, image/gif"
                                                                                data-doc-id="${doc.codigo}"
                                                                                data-doc-name="${doc.nome}">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        `;
                                                    });

                                                    container.innerHTML = html;

                                                    // Adicionar eventos aos novos inputs de arquivo
                                                    document.querySelectorAll('.doc-file').forEach(input => {
                                                        input.addEventListener('change', function(e) {
                                                            const docId = this.dataset.docId;
                                                            const docName = this.dataset.docName;

                                                            if (this.files.length > 0) {
                                                                const file = this.files[0];
                                                                // Verificar se o tipo de arquivo é permitido
                                                                const isAllowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'].includes(file.type);

                                                                if (!isAllowed) {
                                                                    alert("Erro: Tipo de arquivo não permitido. Arquivo inválido: " + file.name);
                                                                    this.value = '';
                                                                } else {
                                                                    allFiles.required[docId] = {
                                                                        file: file,
                                                                        docName: docName
                                                                    };
                                                                }
                                                            } else {
                                                                delete allFiles.required[docId];
                                                            }

                                                            updateFilesSummary();
                                                        });
                                                    });
                                                } else {
                                                    // Alterar o título e a visibilidade das abas
                                                    document.getElementById('required-tab').style.display = 'none';
                                                    document.getElementById('additional-tab').click();
                                                    
                                                    // Mostrar mensagem amigável
                                                    container.innerHTML = `
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            <strong>Informação:</strong> Não há documentos específicos requeridos para as atividades deste estabelecimento.
                                                        </div>
                                                        <p class="mt-3 mb-3 text-center">Por favor, utilize a aba "Documentos Adicionais" para enviar seus documentos.</p>
                                                    `;
                                                }
                                            } catch (e) {
                                                console.error("Erro ao processar resposta:", e);
                                                console.error("Resposta original:", response);
                                                
                                                // Exibir a aba de documentos adicionais
                                                document.getElementById('required-tab').style.display = 'none';
                                                document.getElementById('additional-tab').click();
                                                
                                                container.innerHTML = `
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <strong>Informação:</strong> Não foi possível carregar a lista de documentos específicos.
                                                    </div>
                                                    <p class="mt-3 mb-3 text-center">Por favor, utilize a aba "Documentos Adicionais" para enviar seus documentos.</p>
                                                `;
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error("Erro AJAX:", status, error);
                                            
                                            // Exibir a aba de documentos adicionais
                                            document.getElementById('required-tab').style.display = 'none';
                                            document.getElementById('additional-tab').click();
                                            
                                            container.innerHTML = `
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Informação:</strong> Não foi possível carregar a lista de documentos específicos.
                                                </div>
                                                <p class="mt-3 mb-3 text-center">Por favor, utilize a aba "Documentos Adicionais" para enviar seus documentos.</p>
                                            `;
                                        }
                                    });
                                }

                                // Função para voltar ao modal de seleção de tipo de licenciamento
                                function voltarParaTipoLicenciamento() {
                                    $('#uploadDocumentosModal').modal('hide');
                                    $('#tipoLicenciamentoModal').modal('show');
                                }

                                // Atualizar a lista de arquivos selecionados
                                function updateFilesSummary() {
                                    const summaryList = document.getElementById('filesSummary');
                                    summaryList.innerHTML = '';
                                    let hasFiles = false;

                                    // Adicionar arquivos requeridos
                                    for (const docId in allFiles.required) {
                                        if (allFiles.required[docId]) {
                                            hasFiles = true;
                                            const file = allFiles.required[docId].file;
                                            const docName = allFiles.required[docId].docName;

                                            const listItem = document.createElement('li');
                                            listItem.className = 'list-group-item d-flex justify-content-between align-items-center';

                                            const fileInfo = document.createElement('div');
                                            fileInfo.innerHTML = `<span class="badge bg-primary me-2">Doc ${docId}</span> ${file.name} <small class="text-muted">(${docName})</small>`;

                                            const removeBtn = document.createElement('button');
                                            removeBtn.className = 'btn btn-danger btn-sm';
                                            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                                            removeBtn.onclick = (e) => {
                                                e.preventDefault();
                                                // Limpar o input de arquivo
                                                document.getElementById(`doc_${docId}`).value = '';
                                                delete allFiles.required[docId];
                                                updateFilesSummary();
                                            };

                                            listItem.appendChild(fileInfo);
                                            listItem.appendChild(removeBtn);
                                            summaryList.appendChild(listItem);
                                        }
                                    }

                                    // Adicionar arquivos adicionais
                                    allFiles.additional.forEach((file, index) => {
                                        hasFiles = true;
                                        const listItem = document.createElement('li');
                                        listItem.className = 'list-group-item d-flex justify-content-between align-items-center';

                                        const fileInfo = document.createElement('div');
                                        fileInfo.innerHTML = `<span class="badge bg-secondary me-2">Adicional</span> ${file.name}`;

                                        const removeBtn = document.createElement('button');
                                        removeBtn.className = 'btn btn-danger btn-sm';
                                        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                                        removeBtn.onclick = (e) => {
                                            e.preventDefault();
                                            allFiles.additional.splice(index, 1);
                                            updateAdditionalFileList();
                                            updateFilesSummary();
                                        };

                                        listItem.appendChild(fileInfo);
                                        listItem.appendChild(removeBtn);
                                        summaryList.appendChild(listItem);
                                    });

                                    // Mostrar ou esconder a lista de resumo
                                    document.getElementById('allSelectedFiles').style.display = hasFiles ? 'block' : 'none';

                                    // Habilitar ou desabilitar o botão de envio
                                    document.getElementById('submitUpload').disabled = !hasFiles;
                                }

                                // Atualizar a lista de arquivos adicionais
                                function updateAdditionalFileList() {
                                    const fileList = document.getElementById('additionalFileList');
                                    const selectedFiles = document.getElementById('selectedAdditionalFiles');
                                    selectedFiles.innerHTML = '';

                                    if (allFiles.additional.length > 0) {
                                        fileList.style.display = 'block';

                                        allFiles.additional.forEach((file, index) => {
                                            const listItem = document.createElement('li');
                                            listItem.className = 'list-group-item d-flex justify-content-between align-items-center';

                                            const fileName = document.createElement('span');
                                            fileName.textContent = file.name;

                                            const removeBtn = document.createElement('button');
                                            removeBtn.className = 'btn btn-danger btn-sm';
                                            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                                            removeBtn.onclick = (e) => {
                                                e.preventDefault();
                                                allFiles.additional.splice(index, 1);
                                                updateAdditionalFileList();
                                                updateFilesSummary();
                                            };

                                            listItem.appendChild(fileName);
                                            listItem.appendChild(removeBtn);
                                            selectedFiles.appendChild(listItem);
                                        });
                                    } else {
                                        fileList.style.display = 'none';
                                    }
                                }

                                // Inicializar os eventos quando o documento estiver pronto
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Adicionar evento para os arquivos de documentos requeridos
                                    document.querySelectorAll('.doc-file').forEach(input => {
                                        input.addEventListener('change', function(e) {
                                            const docId = this.dataset.docId;
                                            const docName = this.dataset.docName;

                                            if (this.files.length > 0) {
                                                const file = this.files[0];
                                                // Verificar se o tipo de arquivo é permitido
                                                const isAllowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'].includes(file.type);

                                                if (!isAllowed) {
                                                    alert("Erro: Tipo de arquivo não permitido. Arquivo inválido: " + file.name);
                                                    this.value = '';
                                                } else {
                                                    allFiles.required[docId] = {
                                                        file: file,
                                                        docName: docName
                                                    };
                                                }
                                            } else {
                                                delete allFiles.required[docId];
                                            }

                                            updateFilesSummary();
                                        });
                                    });

                                    // Adicionar evento para os arquivos adicionais
                                    const additionalFilesInput = document.getElementById('additional_files');
                                    if (additionalFilesInput) {
                                        additionalFilesInput.addEventListener('change', function(e) {
                                            if (this.files.length > 0) {
                                                // Filtrar apenas os tipos de arquivos permitidos
                                                Array.from(this.files).forEach(file => {
                                                    const isAllowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'].includes(file.type);

                                                    if (isAllowed) {
                                                        allFiles.additional.push(file);
                                                    } else {
                                                        alert("Erro: Tipo de arquivo não permitido. Arquivo inválido: " + file.name);
                                                    }
                                                });

                                                // Limpar o input para permitir selecionar os mesmos arquivos novamente
                                                this.value = '';

                                                updateAdditionalFileList();
                                                updateFilesSummary();
                                            }
                                        });
                                    }

                                    // Preparar o formulário para envio
                                    document.getElementById('uploadForm').addEventListener('submit', function(e) {
                                        e.preventDefault();

                                        // Criar um FormData para enviar os arquivos
                                        const formData = new FormData(this);

                                        // Adicionar os arquivos requeridos com seus nomes de documento
                                        let docIndex = 0;
                                        for (const docId in allFiles.required) {
                                            if (allFiles.required[docId]) {
                                                const file = allFiles.required[docId].file;
                                                const docName = allFiles.required[docId].docName;

                                                formData.append('files[]', file);
                                                formData.append('doc_names[]', docName);
                                                docIndex++;
                                            }
                                        }

                                        // Adicionar os arquivos adicionais
                                        allFiles.additional.forEach(file => {
                                            formData.append('files[]', file);
                                            formData.append('doc_names[]', file.name); // Usar o nome do arquivo para documentos adicionais
                                        });

                                        // Criar um novo formulário para envio
                                        const form = document.createElement('form');
                                        form.method = 'POST';
                                        form.action = 'detalhes_processo_empresa.php?id=<?php echo $processoId; ?>';
                                        form.enctype = 'multipart/form-data';

                                        // Adicionar cada arquivo como um input de arquivo
                                        let fileIndex = 0;
                                        for (const pair of formData.entries()) {
                                            if (pair[0] === 'files[]') {
                                                // Criar um input de arquivo para cada arquivo
                                                const fileInput = document.createElement('input');
                                                fileInput.type = 'file';
                                                fileInput.name = 'files[]';
                                                fileInput.style.display = 'none';

                                                // Criar um objeto DataTransfer para adicionar o arquivo ao input
                                                const dataTransfer = new DataTransfer();
                                                dataTransfer.items.add(pair[1]);
                                                fileInput.files = dataTransfer.files;

                                                form.appendChild(fileInput);
                                                fileIndex++;
                                            } else if (pair[0] === 'doc_names[]') {
                                                // Adicionar o nome do documento como um campo oculto
                                                const hiddenInput = document.createElement('input');
                                                hiddenInput.type = 'hidden';
                                                hiddenInput.name = 'doc_names[]';
                                                hiddenInput.value = pair[1];
                                                form.appendChild(hiddenInput);
                                            } else {
                                                // Adicionar outros campos do formulário
                                                const hiddenInput = document.createElement('input');
                                                hiddenInput.type = 'hidden';
                                                hiddenInput.name = pair[0];
                                                hiddenInput.value = pair[1];
                                                form.appendChild(hiddenInput);
                                            }
                                        }

                                        // Adicionar o formulário ao documento e enviá-lo
                                        document.body.appendChild(form);
                                        form.submit();
                                    });

                                    // Inicializar o resumo de arquivos
                                    updateFilesSummary();
                                });
                            </script>

                        <?php endif; ?>
                    </div>
                </div>
                        
                <!-- Botão Protocolo do Processo -->
                <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                        <h3 class="text-xs font-medium text-gray-700 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                            </svg>
                            Documentos do Processo
                        </h3>
                    </div>
                    <div class="p-4">
                        <a href="gerar_pdf_processo.php?id=<?php echo $processoId; ?>" 
                           class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                           target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V8z" clip-rule="evenodd" />
                            </svg>
                            Protocolo do Processo
                        </a>
                    </div>
                </div>
            </div>

            <!-- Coluna esquerda: Documentos e Arquivos -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                        <h3 class="text-xs font-medium text-gray-700 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                            </svg>
                            Documentos e Arquivos do Processo
                        </h3>
                    </div>
                    
                    <!-- Abas das Pastas -->
                    <?php if (!empty($pastas) || !empty($itens)) : ?>
                        <div class="border-b border-gray-200 pasta-tabs">
                            <nav class="-mb-px flex space-x-8 px-4" aria-label="Tabs">
                                <!-- Aba Documentos não organizados -->
                                <?php 
                                                $count_geral = count(array_filter($itens, function($item) {
                    return $item['pasta'] === false;
                }));
                                ?>
                                <a href="#" onclick="trocarPasta('geral'); return false;" 
                                   class="pasta-tab <?php echo $pasta_ativa === 'geral' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm flex items-center"
                                   data-pasta="geral">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                    </svg>
                                    Inicial
                                    <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded-full contador-geral"><?php echo $count_geral; ?></span>
                                </a>
                                
                                <!-- Abas das Pastas -->
                                <?php foreach ($pastas as $pasta) : ?>
                                    <?php 
                                    $count_pasta = count($pastaDocumento->getItensByPasta($pasta['id']));
                                    ?>
                                    <a href="#" onclick="trocarPasta('<?php echo $pasta['id']; ?>'); return false;" 
                                       class="pasta-tab <?php echo $pasta_ativa == $pasta['id'] ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm flex items-center"
                                       data-pasta="<?php echo $pasta['id']; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                        </svg>
                                        <?php echo htmlspecialchars($pasta['nome']); ?>
                                        <span class="ml-2 bg-purple-100 text-purple-800 text-xs font-medium px-2 py-0.5 rounded-full contador-pasta" data-pasta-id="<?php echo $pasta['id']; ?>"><?php echo $count_pasta; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-4" id="documentos-container">
                        <!-- Descrição da pasta ativa -->
                        <div id="pasta-descricao">
                            <?php if ($pasta_ativa !== 'geral') : ?>
                                <?php 
                                $pasta_info = null;
                                foreach ($pastas as $pasta) {
                                    if ($pasta['id'] == $pasta_ativa) {
                                        $pasta_info = $pasta;
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($pasta_info && !empty($pasta_info['descricao'])) : ?>
                                    <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-4 rounded-md">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-xs text-purple-700">
                                                    <strong><?php echo htmlspecialchars($pasta_info['nome']); ?>:</strong> <?php echo htmlspecialchars($pasta_info['descricao']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php
                        $documentosPendentes = [];
                        if (is_array($documentos)) {
                            $documentosPendentes = array_filter($documentos, function ($doc) {
                                return is_array($doc) && isset($doc['status']) && $doc['status'] === 'pendente';
                            });
                        }

                        if (!empty($documentosPendentes)) {
                            $quantidadePendentes = count($documentosPendentes);
                            echo '<div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4 rounded-md">';
                            echo '<div class="flex">';
                            echo '<div class="flex-shrink-0">';
                            echo '<svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
                            echo '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />';
                            echo '</svg>';
                            echo '</div>';
                            echo '<div class="ml-3">';
                            echo '<p class="text-xs text-blue-700">';
                            echo "Você tem $quantidadePendentes documento(s) pendente(s). Aguarde a análise desses documentos.";
                            echo '</p>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                        <div id="lista-documentos">
                            <?php if (!empty($itens_exibir)) : ?>
                                <ul class="divide-y divide-gray-200">
                                    <?php foreach ($itens_exibir as $item) : ?>
                                    <li class="py-4 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                                        <div class="flex-1">
                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <a href="#"
                                                onclick="openDocumentPopup('<?php echo addslashes('../../' . $item['caminho_arquivo']); ?>', '<?php echo addslashes($item['nome_arquivo'] ?? $item['tipo_documento'] ?? 'Documento'); ?>', <?php echo htmlspecialchars($item['id']); ?>); return false;"
                                                    class="text-blue-600 hover:text-blue-800 font-medium text-sm hover:underline">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php echo htmlspecialchars($item['nome_arquivo'] ?? $item['tipo_documento'] ?? 'Documento'); ?>
                                                </a>

                                                <?php if ($item['tipo'] == 'documento') : ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Arquivo</span>
                                                <?php else : ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Documento</span>
                                                <?php endif; ?>

                                            <?php if ($item['tipo'] == 'documento') : ?>
                                                    <?php
                                                    $statusBgColor = '';
                                                    $statusTextColor = '';
                                                    switch ($item['status']) {
                                                        case 'aprovado':
                                                            $statusBgColor = 'bg-green-100';
                                                            $statusTextColor = 'text-green-800';
                                                            break;
                                                        case 'negado':
                                                            $statusBgColor = 'bg-red-100';
                                                            $statusTextColor = 'text-red-800';
                                                            break;
                                                        default:  // pendente
                                                            $statusBgColor = 'bg-yellow-100';
                                                            $statusTextColor = 'text-yellow-800';
                                                    }
                                                    ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $statusBgColor . ' ' . $statusTextColor; ?>">
                                                    <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="text-xs text-gray-500 space-y-1">
                                                <p>Adicionado em: <?php echo date('d/m/Y H:i', strtotime($item['data_upload'])); ?></p>
                                                <?php if ($item['tipo'] == 'documento' && $item['status'] == 'negado') : ?>
                                                    <p class="text-red-600 font-medium">
                                                        <strong>Motivo:</strong> <?php echo htmlspecialchars($item['motivo_negacao']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-1">
                                            <?php if ($item['tipo'] == 'documento' && $item['status'] == 'pendente') : ?>
                                                    <form id="deleteForm<?php echo $item['id']; ?>" action="detalhes_processo_empresa.php?id=<?php echo $processoId; ?>" method="POST" style="display: none;">
                                                        <input type="hidden" name="documento_id" value="<?php echo $item['id']; ?>">
                                                        <input type="hidden" name="excluir_documento" value="1">
                                                    </form>
                                                <button type="button" 
                                                    class="inline-flex items-center px-2.5 py-1.5 border border-red-500 text-xs font-medium rounded shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                                                    onclick="confirmDelete(<?php echo $item['id']; ?>)"
                                                    title="Excluir documento">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    Excluir
                                                    </button>
                                                <?php endif; ?>

                                            <?php if ($item['tipo'] == 'documento' && $item['status'] == 'negado') : ?>
                                                <button 
                                                    class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#uploadModal<?php echo $item['id']; ?>"
                                                    title="Enviar arquivo corrigido">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                    Corrigir
                                                    </button>
                                                <?php endif; ?>
                                        </div>
                                    </li>

                                    <!-- Modal para novo upload -->
                                    <?php if ($item['tipo'] == 'documento' && $item['status'] == 'negado') : ?>
                                        <div class="modal fade" id="uploadModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Enviar Arquivo corrigido</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="detalhes_processo_empresa.php?id=<?php echo $processoId; ?>" method="POST" enctype="multipart/form-data">
                                                            <div class="mb-3">
                                                                <label class="form-label">Faça upload do Arquivo corrigido</label>
                                                                <input type="file" class="form-control" name="novo_arquivo"
                                                                    accept="<?php echo $isAssinante ? 'application/pdf, image/*' : 'application/pdf'; ?>" required>
                                                            </div>
                                                            <input type="hidden" name="documento_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-primary" name="novo_arquivo_negado">Enviar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                            </ul>

                            </ul>
                        <?php else : ?>
                            <?php if (empty($pastas)) : ?>
                                <p>Nenhum documento ou arquivo encontrado para este processo.</p>
                            <?php else : ?>
                                <div class="text-center py-8">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum documento nesta pasta</h3>
                                    <p class="mt-1 text-sm text-gray-500">Esta pasta não contém documentos ou arquivos.</p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para visualizar documentos (fixo na página) -->
        <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="documentModalLabel">Visualizar Documento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Alterado para usar embed -->
                        <embed
                            id="documentViewer"
                            type="application/pdf"
                            width="100%"
                            height="500px"
                            style="border: none;">
                    </div>
                    <div class="modal-footer">
                        <a id="openInNewTab" href="#" target="_blank" class="btn btn-primary">Abrir em Nova Aba</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Variável global para armazenar a pasta ativa
            let pastaAtiva = '<?php echo $pasta_ativa; ?>';

            function registrarVisualizacao(arquivoId) {
                fetch('registrar_visualizacao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        arquivo_id: arquivoId,
                        usuario_id: <?php echo $_SESSION['user']['id']; ?>
                    })
                }).then(response => response.json()).then(data => {
                    console.log(data.message);
                }).catch(error => {
                    console.error('Erro:', error);
                });
            }

            function confirmDelete(documentoId) {
                const form = document.getElementById(`deleteForm${documentoId}`);
                if (form) {
                    if (confirm("Deseja realmente apagar este Documento?")) {
                        form.submit();
                    }
                } else {
                    console.error(`Formulário com ID deleteForm${documentoId} não encontrado.`);
                }
            }

            function openDocumentPopup(documentPath, documentName, arquivoId) {
                const viewer = document.getElementById('documentViewer');
                const newTabLink = document.getElementById('openInNewTab');

                if (!viewer || !newTabLink) {
                    console.error('Elementos do modal não encontrados');
                    return;
                }

                // Define o PDF diretamente via embed
                viewer.src = documentPath;
                newTabLink.href = documentPath;

                // Atualiza título do modal
                document.getElementById('documentModalLabel').textContent = documentName;

                // Abrir o modal usando Bootstrap JavaScript API
                const modal = document.getElementById('documentModal');
                if (modal) {
                    // Verificar se Bootstrap está disponível
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const bootstrapModal = new bootstrap.Modal(modal);
                        bootstrapModal.show();
                    } else if (typeof $ !== 'undefined' && $.fn.modal) {
                        // Fallback para jQuery Bootstrap
                        $(modal).modal('show');
                    } else {
                        // Fallback manual - mostrar o modal
                        modal.classList.add('show');
                        modal.style.display = 'block';
                        document.body.classList.add('modal-open');
                        
                        // Criar backdrop
                        const backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        backdrop.id = 'modal-backdrop-temp';
                        document.body.appendChild(backdrop);
                        
                        // Adicionar evento para fechar
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                closeModal();
                            }
                        });
                    }
                } else {
                    console.error('Modal não encontrado');
                }
                
                function closeModal() {
                    const modal = document.getElementById('documentModal');
                    const backdrop = document.getElementById('modal-backdrop-temp');
                    
                    if (modal) {
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        document.body.classList.remove('modal-open');
                    }
                    
                    if (backdrop) {
                        backdrop.remove();
                    }
                }

                registrarVisualizacao(arquivoId); // Mantenha se necessário
            }
            
            // Função global para fechar modal
            function closeModal() {
                const modal = document.getElementById('documentModal');
                const backdrop = document.getElementById('modal-backdrop-temp');
                
                if (modal) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const bootstrapModal = bootstrap.Modal.getInstance(modal);
                        if (bootstrapModal) {
                            bootstrapModal.hide();
                        }
                    } else if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modal).modal('hide');
                    } else {
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        document.body.classList.remove('modal-open');
                    }
                }
                
                if (backdrop) {
                    backdrop.remove();
                }
            }
            
            // Adicionar eventos para fechar modal quando a página carregar
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('documentModal');
                if (modal) {
                    // Adicionar evento para botão de fechar
                    const closeButtons = modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
                    closeButtons.forEach(button => {
                        button.addEventListener('click', closeModal);
                    });
                }
            });

            // Função para trocar pasta sem recarregar a página
            function trocarPasta(pastaId) {
                // Mostrar indicador de carregamento
                const listaDocumentos = document.getElementById('lista-documentos');
                const pastaDescricao = document.getElementById('pasta-descricao');
                
                listaDocumentos.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">Carregando documentos...</p>
                    </div>
                `;

                // Fazer requisição AJAX
                fetch(`carregar_documentos_pasta.php?processo_id=<?php echo $processoId; ?>&pasta=${pastaId}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na requisição');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Validar se os dados estão no formato correto
                        if (!Array.isArray(data.itens)) {
                            console.error('Dados recebidos não são um array:', data.itens);
                            console.error('Tipo dos dados:', typeof data.itens);
                            data.itens = [];
                        }
                        
                        if (!data.contadores || typeof data.contadores !== 'object') {
                            console.error('Contadores inválidos:', data.contadores);
                            data.contadores = {};
                        }
                        
                        // Atualizar pasta ativa
                        pastaAtiva = pastaId;
                        
                        // Atualizar URL sem recarregar
                        const url = new URL(window.location);
                        url.searchParams.set('pasta', pastaId);
                        window.history.pushState({}, '', url);
                        
                        // Atualizar abas ativas
                        atualizarAbasAtivas(pastaId);
                        
                        // Atualizar contadores
                        atualizarContadores(data.contadores);
                        
                        // Atualizar descrição da pasta
                        atualizarDescricaoPasta(data.pasta_info);
                        
                        // Atualizar lista de documentos
                        atualizarListaDocumentos(data.itens);
                        
                    } else {
                        throw new Error(data.error || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    listaDocumentos.innerHTML = `
                        <div class="text-center py-8">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Erro ao carregar documentos</h3>
                            <p class="mt-1 text-sm text-gray-500">Tente novamente em alguns instantes.</p>
                        </div>
                    `;
                });
            }

            // Função para atualizar as abas ativas
            function atualizarAbasAtivas(pastaId) {
                // Remover classes ativas de todas as abas
                document.querySelectorAll('.pasta-tab').forEach(tab => {
                    tab.classList.remove('border-blue-500', 'text-blue-600', 'border-purple-500', 'text-purple-600');
                    tab.classList.add('border-transparent', 'text-gray-500');
                });

                // Adicionar classe ativa à aba selecionada
                const tabAtiva = document.querySelector(`[data-pasta="${pastaId}"]`);
                if (tabAtiva) {
                    tabAtiva.classList.remove('border-transparent', 'text-gray-500');
                    if (pastaId === 'geral') {
                        tabAtiva.classList.add('border-blue-500', 'text-blue-600');
                    } else {
                        tabAtiva.classList.add('border-purple-500', 'text-purple-600');
                    }
                }
            }

            // Função para atualizar contadores
            function atualizarContadores(contadores) {
                // Atualizar contador geral
                const contadorGeral = document.querySelector('.contador-geral');
                if (contadorGeral) {
                    contadorGeral.textContent = contadores.geral || 0;
                }

                // Atualizar contadores das pastas
                document.querySelectorAll('.contador-pasta').forEach(contador => {
                    const pastaId = contador.getAttribute('data-pasta-id');
                    if (contadores[pastaId] !== undefined) {
                        contador.textContent = contadores[pastaId];
                    }
                });
            }

            // Função para atualizar descrição da pasta
            function atualizarDescricaoPasta(pastaInfo) {
                const pastaDescricao = document.getElementById('pasta-descricao');
                
                if (pastaAtiva === 'geral' || !pastaInfo || !pastaInfo.descricao) {
                    pastaDescricao.innerHTML = '';
                } else {
                    pastaDescricao.innerHTML = `
                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-4 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs text-purple-700">
                                        <strong>${pastaInfo.nome}:</strong> ${pastaInfo.descricao}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }

            // Função para atualizar lista de documentos
            function atualizarListaDocumentos(itens) {
                const listaDocumentos = document.getElementById('lista-documentos');
                
                // Validar se itens é um array
                if (!Array.isArray(itens)) {
                    console.error('atualizarListaDocumentos: itens não é um array:', itens);
                    itens = [];
                }
                
                if (itens.length === 0) {
                    listaDocumentos.innerHTML = `
                        <div class="text-center py-8">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum documento nesta pasta</h3>
                            <p class="mt-1 text-sm text-gray-500">Esta pasta não contém documentos ou arquivos.</p>
                        </div>
                    `;
                    return;
                }

                let html = '<ul class="divide-y divide-gray-200">';
                
                itens.forEach(item => {
                    html += gerarHTMLItem(item);
                });
                
                html += '</ul>';
                listaDocumentos.innerHTML = html;
            }

            // Função para gerar HTML de um item
            function gerarHTMLItem(item) {
                const dataUpload = new Date(item.data_upload).toLocaleDateString('pt-BR') + ' ' + 
                                 new Date(item.data_upload).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                
                let statusBadge = '';
                if (item.tipo === 'documento') {
                    let statusClass = '';
                    switch (item.status) {
                        case 'aprovado':
                            statusClass = 'bg-green-100 text-green-800';
                            break;
                        case 'negado':
                            statusClass = 'bg-red-100 text-red-800';
                            break;
                        default:
                            statusClass = 'bg-yellow-100 text-yellow-800';
                    }
                    statusBadge = `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${statusClass}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span>`;
                }

                const tipoBadge = item.tipo === 'documento' ? 
                    '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Arquivo</span>' :
                    '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Documento</span>';

                let botoes = '';
                if (item.tipo === 'documento') {
                    if (item.status === 'pendente') {
                        botoes = `
                            <form id="deleteForm${item.id}" action="detalhes_processo_empresa.php?id=<?php echo $processoId; ?>" method="POST" style="display: none;">
                                <input type="hidden" name="documento_id" value="${item.id}">
                                <input type="hidden" name="excluir_documento" value="1">
                            </form>
                            <button type="button" 
                                class="inline-flex items-center px-2.5 py-1.5 border border-red-500 text-xs font-medium rounded shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                                onclick="confirmDelete(${item.id})"
                                title="Excluir documento">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                Excluir
                            </button>
                        `;
                    } else if (item.status === 'negado') {
                        botoes = `
                            <button 
                                class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                data-bs-toggle="modal" 
                                data-bs-target="#uploadModal${item.id}"
                                title="Enviar arquivo corrigido">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                Corrigir
                            </button>
                        `;
                    }
                }

                let motivoNegacao = '';
                if (item.tipo === 'documento' && item.status === 'negado' && item.motivo_negacao) {
                    motivoNegacao = `
                        <p class="text-red-600 font-medium">
                            <strong>Motivo:</strong> ${item.motivo_negacao}
                        </p>
                    `;
                }

                return `
                    <li class="py-4 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <a href="#"
                                   onclick="openDocumentPopup('../../${item.caminho_arquivo}', '${item.nome_arquivo || item.tipo_documento || 'Documento'}', ${item.id}); return false;"
                                   class="text-blue-600 hover:text-blue-800 font-medium text-sm hover:underline">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                    </svg>
                                    ${item.nome_arquivo || item.tipo_documento || 'Documento'}
                                </a>
                                ${tipoBadge}
                                ${statusBadge}
                            </div>
                            <div class="text-xs text-gray-500 space-y-1">
                                <p>Adicionado em: ${dataUpload}</p>
                                ${motivoNegacao}
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            ${botoes}
                        </div>
                    </li>
                `;
            }
        </script>


</body>

</html>