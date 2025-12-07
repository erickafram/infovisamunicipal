<?php
session_start();
ob_start(); // Inicia o buffer de saída - Manter se necessário para redirects
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/ResponsavelLegal.php';
require_once '../../models/ResponsavelTecnico.php';

$estabelecimento = new Estabelecimento($conn);
$responsavelLegal = new ResponsavelLegal($conn);
$responsavelTecnico = new ResponsavelTecnico($conn);

$id = $_GET['id'] ?? null; // ID do Estabelecimento

if (!$id) {
    $_SESSION['error_message'] = "ID do estabelecimento não fornecido!";
    header("Location: listar_estabelecimentos.php"); // Redireciona para a lista
    exit();
}

$dadosEstabelecimento = $estabelecimento->findById($id);

if (!$dadosEstabelecimento) {
    $_SESSION['error_message'] = "Estabelecimento não encontrado!";
    header("Location: listar_estabelecimentos.php");
    exit();
}

$responsaveisLegais = $responsavelLegal->getByEstabelecimento($id);
$responsaveisTecnicos = $responsavelTecnico->getByEstabelecimento($id);
$qsa = json_decode($dadosEstabelecimento['qsa'] ?? '[]', true);

// --- Processamento de Formulários POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $redirectUrl = "responsaveis.php?id=$id"; // URL base para redirecionamento

    try {
        if (isset($_POST['add_legal'])) {
            $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? ''); // Limpa CPF
            $responsavelExistente = $responsavelLegal->findByCpf($cpf);
            $nome = $_POST['nome'] ?? null;
            $email = $_POST['email'] ?? null;
            $telefone = $_POST['telefone'] ?? null;
            $documento_identificacao_path = null;

            if ($responsavelExistente) {
                // Apenas vincula, não cria/upload novo doc
                $responsavelLegal->create($id, $responsavelExistente['nome'], $responsavelExistente['cpf'], $responsavelExistente['email'], $responsavelExistente['telefone'], $responsavelExistente['documento_identificacao']);
                $_SESSION['success_message'] = "Responsável Legal existente vinculado com sucesso!";
            } else {
                // Upload do documento
                if (isset($_FILES['documento_identificacao']) && $_FILES['documento_identificacao']['error'] == UPLOAD_ERR_OK) {
                    $target_dir = "../../uploads/";
                    // Gerar nome único para evitar conflitos
                    $fileExtension = strtolower(pathinfo($_FILES["documento_identificacao"]["name"], PATHINFO_EXTENSION));
                    $uniqueName = "doc_" . $id . "_" . uniqid() . "." . $fileExtension;
                    $target_file = $target_dir . $uniqueName;

                    if (move_uploaded_file($_FILES["documento_identificacao"]["tmp_name"], $target_file)) {
                        $documento_identificacao_path = "uploads/" . $uniqueName; // Caminho relativo para DB
                    } else {
                        throw new Exception("Falha ao mover o arquivo de documento.");
                    }
                } else {
                    throw new Exception("Erro no upload do documento ou arquivo não enviado.");
                }
                $responsavelLegal->create($id, $nome, $cpf, $email, $telefone, $documento_identificacao_path);
                $_SESSION['success_message'] = "Responsável Legal adicionado com sucesso!";
            }
        } elseif (isset($_POST['add_tecnico'])) {
            $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
            $responsavelExistente = $responsavelTecnico->findByCpf($cpf);
            $nome = $_POST['nome'] ?? null;
            $email = $_POST['email'] ?? null;
            $telefone = $_POST['telefone'] ?? null;
            $conselho = $_POST['conselho'] ?? null;
            $numero_registro_conselho = $_POST['numero_registro_conselho'] ?? null;
            $carteirinha_conselho_path = null;

            if ($responsavelExistente) {
                $responsavelTecnico->create($id, $responsavelExistente['nome'], $responsavelExistente['cpf'], $responsavelExistente['email'], $responsavelExistente['telefone'], $responsavelExistente['conselho'], $responsavelExistente['numero_registro_conselho'], $responsavelExistente['carteirinha_conselho']);
                $_SESSION['success_message'] = "Responsável Técnico existente vinculado com sucesso!";
            } else {
                if (isset($_FILES['carteirinha_conselho']) && $_FILES['carteirinha_conselho']['error'] == UPLOAD_ERR_OK) {
                    $target_dir = "../../uploads/";
                    $fileExtension = strtolower(pathinfo($_FILES["carteirinha_conselho"]["name"], PATHINFO_EXTENSION));
                    // Usa prefixo diferente quando for "Não se aplica"
                    $prefix = ($conselho == 'Não se aplica') ? "doc_id_" : "cart_";
                    $uniqueName = $prefix . $id . "_" . uniqid() . "." . $fileExtension;
                    $target_file = $target_dir . $uniqueName;
                    
                    // Verifica se o diretório existe
                    if (!is_dir($target_dir)) {
                        throw new Exception("Diretório de upload não existe: " . $target_dir);
                    }
                    
                    if (move_uploaded_file($_FILES["carteirinha_conselho"]["tmp_name"], $target_file)) {
                        $carteirinha_conselho_path = "uploads/" . $uniqueName;
                    } else {
                        throw new Exception("Falha ao mover o arquivo. Verifique as permissões da pasta uploads.");
                    }
                } else {
                    $errorMsg = "Erro no upload: ";
                    if (isset($_FILES['carteirinha_conselho']['error'])) {
                        switch ($_FILES['carteirinha_conselho']['error']) {
                            case UPLOAD_ERR_INI_SIZE:
                                $errorMsg .= "O arquivo excede o tamanho máximo permitido.";
                                break;
                            case UPLOAD_ERR_FORM_SIZE:
                                $errorMsg .= "O arquivo excede o tamanho máximo do formulário.";
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $errorMsg .= "Nenhum arquivo foi enviado.";
                                break;
                            default:
                                $errorMsg .= "Erro desconhecido no upload.";
                        }
                    }
                    throw new Exception($errorMsg);
                }
                $responsavelTecnico->create($id, $nome, $cpf, $email, $telefone, $conselho, $numero_registro_conselho, $carteirinha_conselho_path);
                $_SESSION['success_message'] = "Responsável Técnico adicionado com sucesso!";
            }
        } elseif (isset($_POST['edit_legal'])) {
            $responsavel_id = $_POST['responsavel_id'] ?? null;
            $nome = $_POST['nome'] ?? null;
            $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
            $email = $_POST['email'] ?? null;
            $telefone = $_POST['telefone'] ?? null;
            $documento_identificacao = $_POST['old_documento_identificacao'] ?? null; // Assume o antigo por padrão

            if (isset($_FILES['documento_identificacao']) && $_FILES['documento_identificacao']['error'] == UPLOAD_ERR_OK) {
                $target_dir = "../../uploads/";
                $fileExtension = strtolower(pathinfo($_FILES["documento_identificacao"]["name"], PATHINFO_EXTENSION));
                $uniqueName = "doc_" . $id . "_" . uniqid() . "." . $fileExtension;
                $target_file = $target_dir . $uniqueName;
                if (move_uploaded_file($_FILES["documento_identificacao"]["tmp_name"], $target_file)) {
                    $documento_identificacao = "uploads/" . $uniqueName; // Atualiza com o novo caminho
                } else {
                    throw new Exception("Falha ao mover o novo arquivo de documento.");
                }
            }
            $responsavelLegal->update($responsavel_id, $nome, $cpf, $email, $telefone, $documento_identificacao);
            $_SESSION['success_message'] = "Responsável Legal atualizado com sucesso!";
        } elseif (isset($_POST['edit_tecnico'])) {
            $responsavel_id = $_POST['responsavel_id'] ?? null;
            $nome = $_POST['nome'] ?? null;
            $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
            $email = $_POST['email'] ?? null;
            $telefone = $_POST['telefone'] ?? null;
            $conselho = $_POST['conselho'] ?? null;
            $numero_registro_conselho = $_POST['numero_registro_conselho'] ?? null;
            $carteirinha_conselho = $_POST['old_carteirinha_conselho'] ?? null;

            if (isset($_FILES['carteirinha_conselho']) && $_FILES['carteirinha_conselho']['error'] == UPLOAD_ERR_OK) {
                $target_dir = "../../uploads/";
                $fileExtension = strtolower(pathinfo($_FILES["carteirinha_conselho"]["name"], PATHINFO_EXTENSION));
                // Usa prefixo diferente quando for "Não se aplica"
                $prefix = ($conselho == 'Não se aplica') ? "doc_id_" : "cart_";
                $uniqueName = $prefix . $id . "_" . uniqid() . "." . $fileExtension;
                $target_file = $target_dir . $uniqueName;
                if (move_uploaded_file($_FILES["carteirinha_conselho"]["tmp_name"], $target_file)) {
                    $carteirinha_conselho = "uploads/" . $uniqueName;
                } else {
                    throw new Exception("Falha ao mover o novo arquivo. Verifique as permissões da pasta uploads.");
                }
            }
            $responsavelTecnico->update($responsavel_id, $nome, $cpf, $email, $telefone, $conselho, $numero_registro_conselho, $carteirinha_conselho);
            $_SESSION['success_message'] = "Responsável Técnico atualizado com sucesso!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro: " . $e->getMessage();
    }

    header("Location: $redirectUrl");
    exit();
}
// --- Fim Processamento POST ---

// --- Processamento de Exclusão GET ---
if (isset($_GET['action'])) {
    $redirectUrl = "responsaveis.php?id=$id";
    try {
        if ($_GET['action'] == 'delete_legal' && isset($_GET['responsavel_id'])) {
            $responsavel_id = $_GET['responsavel_id'];
            // Adicionar verificação se o responsável pode ser excluído (ex: se não for o único)
            $result = $responsavelLegal->delete($responsavel_id, $id);
            if ($result['success']) {
                $_SESSION['success_message'] = "Responsável Legal excluído com sucesso!";
            } else {
                $_SESSION['error_message'] = $result['message'];
            }
        } elseif ($_GET['action'] == 'delete_tecnico' && isset($_GET['responsavel_id'])) {
            $responsavel_id = $_GET['responsavel_id'];
            $responsavelTecnico->delete($responsavel_id);
            $_SESSION['success_message'] = "Responsável Técnico excluído com sucesso!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao excluir: " . $e->getMessage();
    }
    header("Location: $redirectUrl");
    exit();
}
// --- Fim Processamento GET ---


// Tratar mensagens da sessão para exibição
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

ob_end_flush(); // Envia o buffer de saída
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsáveis - <?= htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? $dadosEstabelecimento['razao_social'] ?? 'N/A') ?></title>
    <style>
        .card-header {
            border-bottom: 1px solid #e9ecef !important;
        }
        .bg-gradient-to-r.from-blue-50.to-blue-100 {
            background: linear-gradient(to right, #eff6ff, #dbeafe);
        }
        .bg-gradient-to-r.from-green-50.to-green-100 {
            background: linear-gradient(to right, #f0fdf4, #dcfce7);
        }
        .bg-gradient-to-r.from-purple-50.to-purple-100 {
            background: linear-gradient(to right, #faf5ff, #f3e8ff);
        }
        .btn-group-vertical .btn {
            border-radius: 0.375rem !important;
            margin-bottom: 0.25rem;
        }
        .btn-group-vertical .btn:last-child {
            margin-bottom: 0;
        }
        .card {
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease-in-out;
        }
        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>

<body>

<div class="container mx-auto px-4 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md overflow-hidden h-full border border-gray-100 transition-all duration-300 hover:shadow-lg">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b border-gray-200">
                    <h3 class="text-gray-700 font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                        Menu Rápido
                    </h3>
                    </div>

                <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                <div class="flex flex-col flex-grow-1 divide-y divide-gray-100">
                    <a href="detalhes_estabelecimento.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'detalhes_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Detalhes</span>
                    </a>
                    <a href="editar_estabelecimento.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'editar_estabelecimento.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'editar_estabelecimento.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        <span class="text-sm">Editar</span>
                    </a>
                    <a href="atividades.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'atividades.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'atividades.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Atividades (CNAE)</span>
                    </a>
                    <a href="responsaveis.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'responsaveis.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'responsaveis.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                        </svg>
                        <span class="text-sm">Responsáveis</span>
                    </a>
                    <a href="acesso_empresa.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'acesso_empresa.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'acesso_empresa.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 00-1-1H7a1 1 0 00-1 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm">Acesso Empresa</span>
                    </a>
                    <a href="../Processo/processos.php?id=<?= $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-200 <?= ($currentPage == 'processos.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?= ($currentPage == 'processos.php') ? 'text-blue-500' : 'text-gray-500'; ?>" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1H8a3 3 0 00-3 3v1.5a1.5 1.5 0 01-3 0V6z" clip-rule="evenodd" />
                            <path d="M6 12a2 2 0 012-2h8a2 2 0 012 2v2a2 2 0 01-2 2H2h2a2 2 0 002-2v-2z" />
                        </svg>
                        <span class="text-sm">Processos</span>
                    </a>
                    </div>
                <div class="p-4 bg-gray-50 border-t border-gray-100">
                    <a href="detalhes_estabelecimento.php?id=<?= $id; ?>" class="flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Voltar aos Detalhes
                        </a>
                    </div>
                </div>
            </div>

        <div class="lg:col-span-3">
            <div id="alertContainer">
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded shadow-sm relative" role="alert">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        <?= htmlspecialchars($success_message) ?>
                        </div>
                        <button type="button" class="absolute top-0 right-0 mt-4 mr-4" data-bs-dismiss="alert" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-700" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded shadow-sm relative" role="alert">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        <?= htmlspecialchars($error_message) ?>
                        </div>
                        <button type="button" class="absolute top-0 right-0 mt-4 mr-4" data-bs-dismiss="alert" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-700" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="h3 font-weight-bold text-dark mb-2">Responsáveis pelo Estabelecimento</h2>
                        <p class="text-muted mb-0">Gerencie os responsáveis legais e técnicos do estabelecimento</p>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEscolherResponsavel">
                            <i class="fas fa-plus me-2"></i> Adicionar Responsável
                    </button>
                    </div>
                </div>
                </div>

            <div class="mt-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-gradient-to-r from-blue-50 to-blue-100 py-3 border-bottom">
                        <h6 class="mb-0 text-primary font-weight-bold"><i class="fas fa-user-tie me-2"></i>Responsáveis Legais</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($responsaveisLegais)): ?>
                            <div class="p-4 text-center">
                                <i class="fas fa-user-slash text-muted mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted mb-0">Nenhum responsável legal cadastrado.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($responsaveisLegais as $index => $responsavel) : ?>
                                <div class="<?= $index > 0 ? 'border-top' : '' ?> p-4">
                                    <div class="row align-items-center">
                                        <div class="col-lg-8">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user text-primary me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">Nome</small>
                                                            <strong><?= htmlspecialchars($responsavel['nome']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-id-card text-primary me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">CPF</small>
                                                            <strong><?= htmlspecialchars($responsavel['cpf']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-envelope text-primary me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">Email</small>
                                                            <strong><?= htmlspecialchars($responsavel['email']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-phone text-primary me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">Telefone</small>
                                                            <strong><?= htmlspecialchars($responsavel['telefone']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                                            <div class="mb-3">
                                            <?php if (!empty($responsavel['documento_identificacao'])): ?>
                                                    <a href="/visamunicipal/uploads/<?= htmlspecialchars($responsavel['documento_identificacao']); ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                                        <i class="fas fa-file-alt me-1"></i> Ver Documento
                                                </a>
                                            <?php else: ?>
                                                    <span class="badge bg-secondary">Sem documento</span>
                                            <?php endif; ?>
                                            </div>
                                            <div class="btn-group-vertical gap-1" role="group">
                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditLegal<?= $responsavel['id']; ?>">
                                            <i class="fas fa-edit me-1"></i>Editar
                                        </button>
                                                <a href="responsaveis.php?id=<?= $id; ?>&action=delete_legal&responsavel_id=<?= $responsavel['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este responsável legal?')">
                                            <i class="fas fa-trash me-1"></i>Excluir
                                        </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal fade" id="modalEditLegal<?= $responsavel['id']; ?>" tabindex="-1" aria-labelledby="modalEditLegalLabel<?= $responsavel['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalEditLegalLabel<?= $responsavel['id']; ?>"><i class="fas fa-edit me-2"></i>Editar Responsável Legal</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="responsaveis.php?id=<?= $id; ?>" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="responsavel_id" value="<?= $responsavel['id']; ?>">
                                                    <input type="hidden" name="edit_legal" value="1">
                                                    <div class="row g-3">
                                                        <div class="col-md-12">
                                                            <label for="edit_nome_legal_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Nome</label>
                                                            <input type="text" class="form-control form-control-sm" id="edit_nome_legal_<?= $responsavel['id']; ?>" name="nome" value="<?= htmlspecialchars($responsavel['nome']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_cpf_legal_<?= $responsavel['id']; ?>" class="form-label form-label-sm">CPF</label>
                                                            <input type="text" class="form-control form-control-sm" id="edit_cpf_legal_<?= $responsavel['id']; ?>" name="cpf" value="<?= htmlspecialchars($responsavel['cpf']); ?>" required pattern="\d{11}" title="Digite 11 dígitos sem pontos ou traços">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_telefone_legal_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Telefone</label>
                                                            <input type="tel" class="form-control form-control-sm" id="edit_telefone_legal_<?= $responsavel['id']; ?>" name="telefone" value="<?= htmlspecialchars($responsavel['telefone']); ?>" required placeholder="(00) 00000-0000">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label for="edit_email_legal_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Email</label>
                                                            <input type="email" class="form-control form-control-sm" id="edit_email_legal_<?= $responsavel['id']; ?>" name="email" value="<?= htmlspecialchars($responsavel['email']); ?>" required>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label for="edit_documento_legal_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Substituir Documento Ident. (Opcional)</label>
                                                            <input type="file" class="form-control form-control-sm" id="edit_documento_legal_<?= $responsavel['id']; ?>" name="documento_identificacao" accept=".pdf,.jpg,.jpeg,.png">
                                                            <input type="hidden" name="old_documento_identificacao" value="<?= htmlspecialchars($responsavel['documento_identificacao']); ?>">
                                                            <div class="form-text">Envie um novo arquivo apenas se desejar substituir o atual:
                                                                <?php if (!empty($responsavel['documento_identificacao'])): ?>
                                                                    <a href="/visamunicipal/<?= htmlspecialchars($responsavel['documento_identificacao']); ?>" target="_blank"><?= basename(htmlspecialchars($responsavel['documento_identificacao'])); ?></a>
                                                                <?php else: ?>
                                                                    Nenhum
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer mt-3 pb-0">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Alterações</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-gradient-to-r from-green-50 to-green-100 py-3 border-bottom">
                        <h6 class="mb-0 text-success font-weight-bold"><i class="fas fa-user-cog me-2"></i>Responsáveis Técnicos</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($responsaveisTecnicos)): ?>
                            <div class="p-4 text-center">
                                <i class="fas fa-user-cog text-muted mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted mb-0">Nenhum responsável técnico cadastrado.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($responsaveisTecnicos as $index => $responsavel) : ?>
                                <div class="<?= $index > 0 ? 'border-top' : '' ?> p-4">
                                    <div class="row align-items-center">
                                        <div class="col-lg-8">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user text-success me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">Nome</small>
                                                            <strong><?= htmlspecialchars($responsavel['nome']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-id-card text-success me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">CPF</small>
                                                            <strong><?= htmlspecialchars($responsavel['cpf']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-envelope text-success me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">Email</small>
                                                            <strong><?= htmlspecialchars($responsavel['email']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-phone text-success me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">Telefone</small>
                                                            <strong><?= htmlspecialchars($responsavel['telefone']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-certificate text-success me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block">Conselho</small>
                                                            <strong><?= htmlspecialchars($responsavel['conselho']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-hashtag text-success me-2"></i>
                                                        <div>
                                                            <small class="text-muted d-block"><?= ($responsavel['conselho'] == 'Não se aplica') ? 'CPF' : 'Nº Registro' ?></small>
                                                            <strong><?= htmlspecialchars($responsavel['numero_registro_conselho']) ?: 'Não informado'; ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                                            <div class="mb-3">
                                            <?php if (!empty($responsavel['carteirinha_conselho'])): ?>
                                                    <a href="/visamunicipal/<?= htmlspecialchars($responsavel['carteirinha_conselho']); ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-id-card me-1"></i> <?= ($responsavel['conselho'] == 'Não se aplica') ? 'Ver Documento' : 'Ver Carteirinha' ?>
                                                </a>
                                            <?php else: ?>
                                                    <span class="badge bg-secondary"><?= ($responsavel['conselho'] == 'Não se aplica') ? 'Sem documento' : 'Sem carteirinha' ?></span>
                                            <?php endif; ?>
                                            </div>
                                            <div class="btn-group-vertical gap-1" role="group">
                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditTecnico<?= $responsavel['id']; ?>">
                                            <i class="fas fa-edit me-1"></i>Editar
                                        </button>
                                                <a href="responsaveis.php?id=<?= $id; ?>&action=delete_tecnico&responsavel_id=<?= $responsavel['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este responsável técnico?')">
                                            <i class="fas fa-trash me-1"></i>Excluir
                                        </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal fade" id="modalEditTecnico<?= $responsavel['id']; ?>" tabindex="-1" aria-labelledby="modalEditTecnicoLabel<?= $responsavel['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalEditTecnicoLabel<?= $responsavel['id']; ?>"><i class="fas fa-edit me-2"></i>Editar Responsável Técnico</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="responsaveis.php?id=<?= $id; ?>" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="responsavel_id" value="<?= $responsavel['id']; ?>">
                                                    <input type="hidden" name="edit_tecnico" value="1">
                                                    <div class="row g-3">
                                                        <div class="col-md-12">
                                                            <label for="edit_nome_tec_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Nome</label>
                                                            <input type="text" class="form-control form-control-sm" id="edit_nome_tec_<?= $responsavel['id']; ?>" name="nome" value="<?= htmlspecialchars($responsavel['nome']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_cpf_tec_<?= $responsavel['id']; ?>" class="form-label form-label-sm">CPF</label>
                                                            <input type="text" class="form-control form-control-sm" id="edit_cpf_tec_<?= $responsavel['id']; ?>" name="cpf" value="<?= htmlspecialchars($responsavel['cpf']); ?>" required pattern="\d{11}" title="Digite 11 dígitos sem pontos ou traços">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_telefone_tec_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Telefone</label>
                                                            <input type="tel" class="form-control form-control-sm" id="edit_telefone_tec_<?= $responsavel['id']; ?>" name="telefone" value="<?= htmlspecialchars($responsavel['telefone']); ?>" required placeholder="(00) 00000-0000">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label for="edit_email_tec_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Email</label>
                                                            <input type="email" class="form-control form-control-sm" id="edit_email_tec_<?= $responsavel['id']; ?>" name="email" value="<?= htmlspecialchars($responsavel['email']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_conselho_tec_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Conselho</label>
                                                            <select class="form-select form-select-sm" id="edit_conselho_tec_<?= $responsavel['id']; ?>" name="conselho" required>
                                                                <option value="Não se aplica" <?= ($responsavel['conselho'] == 'Não se aplica') ? 'selected' : ''; ?>>Não se aplica</option>
                                                                <option value="CRM" <?= ($responsavel['conselho'] == 'CRM') ? 'selected' : ''; ?>>CRM</option>
                                                                <option value="CRF" <?= ($responsavel['conselho'] == 'CRF') ? 'selected' : ''; ?>>CRF</option>
                                                                <option value="CRO" <?= ($responsavel['conselho'] == 'CRO') ? 'selected' : ''; ?>>CRO</option>
                                                                <option value="CREFITO" <?= ($responsavel['conselho'] == 'CREFITO') ? 'selected' : ''; ?>>CREFITO</option>
                                                                <option value="COREN" <?= ($responsavel['conselho'] == 'COREN') ? 'selected' : ''; ?>>COREN</option>
                                                                <option value="CRP" <?= ($responsavel['conselho'] == 'CRP') ? 'selected' : ''; ?>>CRP</option>
                                                                <option value="CRMV" <?= ($responsavel['conselho'] == 'CRMV') ? 'selected' : ''; ?>>CRMV</option>
                                                                <option value="CREFONO" <?= ($responsavel['conselho'] == 'CREFONO') ? 'selected' : ''; ?>>CREFONO</option>
                                                                <option value="CRN" <?= ($responsavel['conselho'] == 'CRN') ? 'selected' : ''; ?>>CRN</option>
                                                                <option value="CREF" <?= ($responsavel['conselho'] == 'CREF') ? 'selected' : ''; ?>>CREF</option>
                                                                <option value="CRAS" <?= ($responsavel['conselho'] == 'CRAS') ? 'selected' : ''; ?>>CRAS</option>
                                                                <option value="CRT" <?= ($responsavel['conselho'] == 'CRT') ? 'selected' : ''; ?>>CRT</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="edit_registro_tec_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Nº Registro Conselho <span class="text-danger asterisco-registro-edit">*</span></label>
                                                            <input type="text" class="form-control form-control-sm" id="edit_registro_tec_<?= $responsavel['id']; ?>" name="numero_registro_conselho" value="<?= htmlspecialchars($responsavel['numero_registro_conselho']); ?>" required>
                                                        </div>
                                                        <div class="col-12">
                                                            <label for="edit_carteirinha_tec_<?= $responsavel['id']; ?>" class="form-label form-label-sm">Substituir Carteirinha (Opcional)</label>
                                                            <input type="file" class="form-control form-control-sm" id="edit_carteirinha_tec_<?= $responsavel['id']; ?>" name="carteirinha_conselho" accept=".pdf,.jpg,.jpeg,.png">
                                                            <input type="hidden" name="old_carteirinha_conselho" value="<?= htmlspecialchars($responsavel['carteirinha_conselho']); ?>">
                                                            <div class="form-text">Envie um novo arquivo apenas se desejar substituir o atual:
                                                                <?php if (!empty($responsavel['carteirinha_conselho'])): ?>
                                                                    <a href="/visamunicipal/<?= htmlspecialchars($responsavel['carteirinha_conselho']); ?>" target="_blank"><?= basename(htmlspecialchars($responsavel['carteirinha_conselho'])); ?></a>
                                                                <?php else: ?>
                                                                    Nenhuma
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer mt-3 pb-0">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Alterações</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-gradient-to-r from-purple-50 to-purple-100 py-3 border-bottom">
                        <h6 class="mb-0 text-secondary font-weight-bold"><i class="fas fa-users-cog me-2"></i>Sociedade (QSA - Quadro de Sócios e Administradores)</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($qsa)): ?>
                            <div class="p-4 text-center">
                                <i class="fas fa-users text-muted mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted mb-0">Nenhum sócio/administrador informado no QSA.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($qsa as $index => $socio) : ?>
                                <div class="<?= $index > 0 ? 'border-top' : '' ?> p-4">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-friends text-secondary me-2"></i>
                                                <div>
                                                    <small class="text-muted d-block">Nome do Sócio</small>
                                                    <strong><?= htmlspecialchars($socio['nome_socio'] ?? 'N/A'); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-briefcase text-secondary me-2"></i>
                                                <div>
                                                    <small class="text-muted d-block">Qualificação</small>
                                                    <strong><?= htmlspecialchars($socio['qualificacao_socio'] ?? 'N/A'); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEscolherResponsavel" tabindex="-1" aria-labelledby="modalEscolherResponsavelLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEscolherResponsavelLabel"><i class="fas fa-user-plus me-2"></i>Adicionar Responsável</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="text-muted">Selecione o tipo de responsável que deseja adicionar:</p>
                    <button class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#modalAddLegal" data-bs-dismiss="modal">
                        <i class="fas fa-user-tie me-1"></i> Responsável Legal
                    </button>
                    <button class="btn btn-info mb-2" data-bs-toggle="modal" data-bs-target="#modalAddTecnico" data-bs-dismiss="modal">
                        <i class="fas fa-user-cog me-1"></i> Responsável Técnico
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAddLegal" tabindex="-1" aria-labelledby="modalAddLegalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddLegalLabel"><i class="fas fa-user-tie me-2"></i>Adicionar Responsável Legal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formAddLegal" action="responsaveis.php?id=<?= $id; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_legal" value="1">
                        <div class="mb-3">
                            <label for="cpfLegal" class="form-label form-label-sm">CPF <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="cpfLegal" name="cpf" required pattern="\d{11}" title="Digite 11 dígitos sem pontos ou traços" placeholder="00000000000">
                                <button type="button" class="btn btn-secondary" id="buscarCpfLegal"> <i class="fas fa-search me-1"></i> Verificar CPF</button>
                            </div>
                            <div class="form-text">Verifique se o responsável já existe no sistema antes de prosseguir.</div>
                        </div>

                        <div id="alertLegal" class="alert alert-info alert-sm py-2" style="display:none;">
                            <i class="fas fa-check-circle me-1"></i> Responsável já cadastrado. Será apenas vinculado a este estabelecimento.
                        </div>

                        <div id="legalFields" style="display: none;">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="nomeLegal" class="form-label form-label-sm">Nome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="nomeLegal" name="nome">
                                </div>
                                <div class="col-md-6">
                                    <label for="telefoneLegal" class="form-label form-label-sm">Telefone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control form-control-sm" id="telefoneLegal" name="telefone" placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-6">
                                    <label for="emailLegal" class="form-label form-label-sm">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control form-control-sm" id="emailLegal" name="email">
                                </div>
                                <div class="col-12">
                                    <label for="documento_identificacaoLegal" class="form-label form-label-sm">Documento de Identificação (PDF, JPG, PNG) <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control form-control-sm" id="documento_identificacaoLegal" name="documento_identificacao" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer mt-3 pb-0">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm" id="btnAddLegal" style="display: none;"><i class="fas fa-plus me-1"></i> Adicionar Responsável</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAddTecnico" tabindex="-1" aria-labelledby="modalAddTecnicoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddTecnicoLabel"><i class="fas fa-user-cog me-2"></i>Adicionar Responsável Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formAddTecnico" action="responsaveis.php?id=<?= $id; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_tecnico" value="1">
                        <div class="mb-3">
                            <label for="cpfTecnico" class="form-label form-label-sm">CPF <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="cpfTecnico" name="cpf" required pattern="\d{11}" title="Digite 11 dígitos sem pontos ou traços" placeholder="00000000000">
                                <button type="button" class="btn btn-secondary" id="buscarCpfTecnico"> <i class="fas fa-search me-1"></i> Verificar CPF</button>
                            </div>
                            <div class="form-text">Verifique se o responsável já existe no sistema antes de prosseguir.</div>
                        </div>

                        <div id="alertTecnico" class="alert alert-info alert-sm py-2" style="display:none;">
                            <i class="fas fa-check-circle me-1"></i> Responsável já cadastrado. Será apenas vinculado a este estabelecimento.
                        </div>

                        <div id="tecnicoFields" style="display: none;">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="nomeTecnico" class="form-label form-label-sm">Nome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="nomeTecnico" name="nome">
                                </div>
                                <div class="col-md-6">
                                    <label for="telefoneTecnico" class="form-label form-label-sm">Telefone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control form-control-sm" id="telefoneTecnico" name="telefone" placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-6">
                                    <label for="emailTecnico" class="form-label form-label-sm">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control form-control-sm" id="emailTecnico" name="email">
                                </div>
                                <div class="col-md-6">
                                    <label for="conselho" class="form-label form-label-sm">Conselho <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="conselho" name="conselho">
                                        <option value="" selected disabled>Selecione...</option>
                                        <option value="Não se aplica">Não se aplica</option>
                                        <option value="CRM">CRM</option>
                                        <option value="CRF">CRF</option>
                                        <option value="CRO">CRO</option>
                                        <option value="CREFITO">CREFITO</option>
                                        <option value="COREN">COREN</option>
                                        <option value="CRP">CRP</option>
                                        <option value="CRMV">CRMV</option>
                                        <option value="CREFONO">CREFONO</option>
                                        <option value="CRN">CRN</option>
                                        <option value="CREF">CREF</option>
                                        <option value="CRAS">CRAS</option>
                                        <option value="CRT">CRT</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="numero_registro_conselho" class="form-label form-label-sm">Nº Registro Conselho <span class="text-danger" id="asterisco_registro">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="numero_registro_conselho" name="numero_registro_conselho">
                                </div>
                                <div class="col-12">
                                    <label for="carteirinha_conselho" class="form-label form-label-sm">Carteirinha Conselho (PDF, JPG, PNG) <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control form-control-sm" id="carteirinha_conselho" name="carteirinha_conselho" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer mt-3 pb-0">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm" id="btnAddTecnico" style="display: none;"><i class="fas fa-plus me-1"></i> Adicionar Responsável</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Função de validação de CPF
        function validarCPF(cpf) {
            cpf = cpf.replace(/\D/g, ''); // Remove não dígitos
            return cpf.length === 11;
        }

        // Função para alternar campos required nos modais de adição
        function toggleRequiredFields(containerId, isRequired) {
            var container = document.getElementById(containerId);
            var fields = container.querySelectorAll('input, select');
            fields.forEach(function(field) {
                // Não mexe no 'required' do input de file se ele não for obrigatório ao encontrar CPF
                if (field.type === 'file' && containerId === 'legalFields' && !isRequired && field.id === 'documento_identificacaoLegal') {
                    field.removeAttribute('required');
                    return; // Pula para o próximo field
                }
                if (field.type === 'file' && containerId === 'tecnicoFields' && !isRequired && field.id === 'carteirinha_conselho') {
                    field.removeAttribute('required');
                    return; // Pula para o próximo field
                }

                if (isRequired) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                }
            });
        }

        // Lógica para buscar CPF - Responsável Legal
        document.getElementById('buscarCpfLegal').addEventListener('click', function() {
            var cpfInput = document.getElementById('cpfLegal');
            var cpf = cpfInput.value.replace(/\D/g, '');
            var fieldsContainer = document.getElementById('legalFields');
            var alertContainer = document.getElementById('alertLegal');
            var addButton = document.getElementById('btnAddLegal');

            if (!validarCPF(cpf)) {
                alert('O CPF deve conter 11 dígitos.');
                return;
            }

            fetch('verificar_cpf.php?cpf=' + cpf + '&tipo=legal')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    if (data.existe) {
                        // Preenche e desabilita/requer campos conforme necessário
                        $('#nomeLegal').val(data.nome || '').prop('readonly', true).removeAttr('required');
                        $('#emailLegal').val(data.email || '').prop('readonly', true).removeAttr('required');
                        $('#telefoneLegal').val(data.telefone || '').prop('readonly', true).removeAttr('required');
                        $('#documento_identificacaoLegal').removeAttr('required'); // Documento não é obrigatório se já existe

                        fieldsContainer.style.display = 'none'; // Esconde campos
                        alertContainer.style.display = 'block'; // Mostra alerta
                        toggleRequiredFields('legalFields', false); // Marca campos como não requeridos
                    } else {
                        // Limpa, habilita e requer campos
                        $('#nomeLegal').val('').prop('readonly', false).prop('required', true);
                        $('#emailLegal').val('').prop('readonly', false).prop('required', true);
                        $('#telefoneLegal').val('').prop('readonly', false).prop('required', true);
                        $('#documento_identificacaoLegal').prop('required', true); // Documento é obrigatório se novo

                        fieldsContainer.style.display = 'block'; // Mostra campos
                        alertContainer.style.display = 'none'; // Esconde alerta
                        toggleRequiredFields('legalFields', true); // Marca campos como requeridos
                    }
                    addButton.style.display = 'block'; // Mostra botão de adicionar
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('Erro ao verificar o CPF: ' + error.message);
                    fieldsContainer.style.display = 'none';
                    alertContainer.style.display = 'none';
                    addButton.style.display = 'none';
                });
        });

        // Lógica para buscar CPF - Responsável Técnico
        document.getElementById('buscarCpfTecnico').addEventListener('click', function() {
            var cpfInput = document.getElementById('cpfTecnico');
            var cpf = cpfInput.value.replace(/\D/g, '');
            var fieldsContainer = document.getElementById('tecnicoFields');
            var alertContainer = document.getElementById('alertTecnico');
            var addButton = document.getElementById('btnAddTecnico');

            if (!validarCPF(cpf)) {
                alert('O CPF deve conter 11 dígitos.');
                return;
            }

            fetch('verificar_cpf.php?cpf=' + cpf + '&tipo=tecnico')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    if (data.existe) {
                        $('#nomeTecnico').val(data.nome || '').prop('readonly', true).removeAttr('required');
                        $('#emailTecnico').val(data.email || '').prop('readonly', true).removeAttr('required');
                        $('#telefoneTecnico').val(data.telefone || '').prop('readonly', true).removeAttr('required');
                        $('#conselho').val(data.conselho || '').prop('disabled', true).removeAttr('required'); // Desabilita select
                        
                        // Se for "Não se aplica", usa o CPF inicial, senão usa o registro do conselho
                        if (data.conselho === 'Não se aplica') {
                            $('#numero_registro_conselho').val(cpf).prop('readonly', true).removeAttr('required');
                            $('#asterisco_registro').hide();
                        } else {
                            $('#numero_registro_conselho').val(data.numero_registro_conselho || '').prop('readonly', true).removeAttr('required');
                        }
                        
                        $('#carteirinha_conselho').removeAttr('required'); // Carteirinha não obrigatória

                        fieldsContainer.style.display = 'none';
                        alertContainer.style.display = 'block';
                        toggleRequiredFields('tecnicoFields', false);
                    } else {
                        $('#nomeTecnico').val('').prop('readonly', false).prop('required', true);
                        $('#emailTecnico').val('').prop('readonly', false).prop('required', true);
                        $('#telefoneTecnico').val('').prop('readonly', false).prop('required', true);
                        $('#conselho').val('').prop('disabled', false).prop('required', true); // Habilita select
                        $('#numero_registro_conselho').val('').prop('readonly', false).prop('required', true);
                        $('#carteirinha_conselho').prop('required', true); // Carteirinha obrigatória

                        // Garante que o asterisco esteja visível para novos cadastros
                        $('#asterisco_registro').show();

                        fieldsContainer.style.display = 'block';
                        alertContainer.style.display = 'none';
                        toggleRequiredFields('tecnicoFields', true);
                    }
                    addButton.style.display = 'block';
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('Erro ao verificar o CPF: ' + error.message);
                    fieldsContainer.style.display = 'none';
                    alertContainer.style.display = 'none';
                    addButton.style.display = 'none';
                });
        });

        // Adiciona máscara/validação simples para Telefone nos modais de edição (se necessário)
        $('input[id^="edit_telefone_"]').on('input', function() {
            let v = this.value.replace(/\D/g, '');
            v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
            if (v.length > 10) {
                v = v.replace(/(\d{5})(\d)/, "$1-$2");
            } else {
                v = v.replace(/(\d{4})(\d)/, "$1-$2");
            }
            this.value = v.substring(0, 15);
        });
        $('input[id^="telefoneLegal"], input[id^="telefoneTecnico"]').on('input', function() {
            let v = this.value.replace(/\D/g, '');
            v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
            if (v.length > 10) {
                v = v.replace(/(\d{5})(\d)/, "$1-$2");
            } else {
                v = v.replace(/(\d{4})(\d)/, "$1-$2");
            }
            this.value = v.substring(0, 15);
        });
        $('input[id^="edit_cpf_"], input[id^="cpfLegal"], input[id^="cpfTecnico"]').on('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 11);
        });

        // Controla obrigatoriedade do campo Nº Registro Conselho no modal de adicionar
        $('#conselho').on('change', function() {
            var numeroRegistroField = $('#numero_registro_conselho');
            var asterisco = $('#asterisco_registro');
            var labelRegistro = $('label[for="numero_registro_conselho"]');
            var labelCarteirinha = $('label[for="carteirinha_conselho"]');
            var cpfInicial = $('#cpfTecnico').val().replace(/\D/g, ''); // Pega o CPF do início do formulário
            
            if (this.value === 'Não se aplica') {
                numeroRegistroField.removeAttr('required');
                asterisco.hide();
                // Muda o label e placeholder para CPF quando for "Não se aplica"
                labelRegistro.html('CPF do Responsável Técnico <span class="text-danger" id="asterisco_registro" style="display:none;">*</span>');
                // Preenche automaticamente com o CPF inicial e torna readonly
                numeroRegistroField.val(cpfInicial);
                numeroRegistroField.prop('readonly', true);
                numeroRegistroField.attr('placeholder', '');
                numeroRegistroField.attr('pattern', '\\d{11}');
                numeroRegistroField.attr('title', 'CPF preenchido automaticamente');
                // Muda o label da carteirinha
                labelCarteirinha.html('Identidade ou CNH (PDF, JPG, PNG) <span class="text-danger">*</span>');
            } else {
                numeroRegistroField.attr('required', 'required');
                asterisco.show();
                // Volta ao label e placeholder padrão
                labelRegistro.html('Nº Registro Conselho <span class="text-danger" id="asterisco_registro">*</span>');
                // Limpa o campo e remove readonly
                numeroRegistroField.val('');
                numeroRegistroField.prop('readonly', false);
                numeroRegistroField.attr('placeholder', '');
                numeroRegistroField.removeAttr('pattern');
                numeroRegistroField.removeAttr('title');
                // Volta ao label padrão da carteirinha
                labelCarteirinha.html('Carteirinha Conselho (PDF, JPG, PNG) <span class="text-danger">*</span>');
            }
        });

        // Controla obrigatoriedade do campo Nº Registro Conselho nos modais de edição
        $(document).on('change', 'select[id^="edit_conselho_tec_"]', function() {
            var responsavelId = this.id.replace('edit_conselho_tec_', '');
            var numeroRegistroField = $('#edit_registro_tec_' + responsavelId);
            var asteriscoEdit = $(this).closest('.modal').find('.asterisco-registro-edit');
            var labelRegistro = $(this).closest('.modal').find('label[for="edit_registro_tec_' + responsavelId + '"]');
            var labelCarteirinha = $(this).closest('.modal').find('label[for="edit_carteirinha_tec_' + responsavelId + '"]');
            
            if (this.value === 'Não se aplica') {
                numeroRegistroField.removeAttr('required');
                asteriscoEdit.hide();
                // Muda o label e placeholder para CPF quando for "Não se aplica"
                labelRegistro.html('CPF do Responsável Técnico <span class="text-danger asterisco-registro-edit" style="display:none;">*</span>');
                numeroRegistroField.attr('placeholder', '00000000000');
                numeroRegistroField.attr('pattern', '\\d{11}');
                numeroRegistroField.attr('title', 'Digite 11 dígitos sem pontos ou traços');
                // Muda o label da carteirinha
                labelCarteirinha.html('Substituir Identidade ou CNH (Opcional)');
            } else {
                numeroRegistroField.attr('required', 'required');
                asteriscoEdit.show();
                // Volta ao label e placeholder padrão
                labelRegistro.html('Nº Registro Conselho <span class="text-danger asterisco-registro-edit">*</span>');
                numeroRegistroField.attr('placeholder', '');
                numeroRegistroField.removeAttr('pattern');
                numeroRegistroField.removeAttr('title');
                // Volta ao label padrão da carteirinha
                labelCarteirinha.html('Substituir Carteirinha (Opcional)');
            }
        });

        // Inicializar estado correto dos campos ao carregar a página (para modais de edição)
        $(document).on('shown.bs.modal', '.modal', function() {
            $(this).find('select[id^="edit_conselho_tec_"]').each(function() {
                var responsavelId = this.id.replace('edit_conselho_tec_', '');
                var numeroRegistroField = $('#edit_registro_tec_' + responsavelId);
                var asteriscoEdit = $(this).closest('.modal').find('.asterisco-registro-edit');
                var labelRegistro = $(this).closest('.modal').find('label[for="edit_registro_tec_' + responsavelId + '"]');
                var labelCarteirinha = $(this).closest('.modal').find('label[for="edit_carteirinha_tec_' + responsavelId + '"]');
                
                if (this.value === 'Não se aplica') {
                    numeroRegistroField.removeAttr('required');
                    asteriscoEdit.hide();
                    // Atualiza labels e configurações do campo
                    labelRegistro.html('CPF do Responsável Técnico <span class="text-danger asterisco-registro-edit" style="display:none;">*</span>');
                    numeroRegistroField.attr('placeholder', '00000000000');
                    numeroRegistroField.attr('pattern', '\\d{11}');
                    numeroRegistroField.attr('title', 'Digite 11 dígitos sem pontos ou traços');
                    labelCarteirinha.html('Substituir Identidade ou CNH (Opcional)');
                } else {
                    asteriscoEdit.show();
                    // Mantém labels padrão
                    labelRegistro.html('Nº Registro Conselho <span class="text-danger asterisco-registro-edit">*</span>');
                    numeroRegistroField.attr('placeholder', '');
                    numeroRegistroField.removeAttr('pattern');
                    numeroRegistroField.removeAttr('title');
                    labelCarteirinha.html('Substituir Carteirinha (Opcional)');
                }
            });
        });

        // Limpar estado quando modal de adicionar técnico for fechado
        $('#modalAddTecnico').on('hidden.bs.modal', function() {
            $('#asterisco_registro').show();
            $('#numero_registro_conselho').attr('required', 'required');
            // Limpa o valor, remove readonly e restaura o label padrão
            $('#numero_registro_conselho').val('').prop('readonly', false).attr('placeholder', '').removeAttr('pattern').removeAttr('title');
            $('label[for="numero_registro_conselho"]').html('Nº Registro Conselho <span class="text-danger" id="asterisco_registro">*</span>');
            $('label[for="carteirinha_conselho"]').html('Carteirinha Conselho (PDF, JPG, PNG) <span class="text-danger">*</span>');
        });

        // Adiciona validação de CPF no campo número de registro quando for "Não se aplica" e não estiver readonly
        $('#numero_registro_conselho').on('input', function() {
            if ($('#conselho').val() === 'Não se aplica' && !$(this).prop('readonly')) {
                // Remove tudo que não é número e limita a 11 dígitos
                this.value = this.value.replace(/\D/g, '').substring(0, 11);
            }
        });

        // Adiciona validação de CPF nos campos de edição quando for "Não se aplica"
        $(document).on('input', 'input[id^="edit_registro_tec_"]', function() {
            var responsavelId = this.id.replace('edit_registro_tec_', '');
            var conselhoSelect = $('#edit_conselho_tec_' + responsavelId);
            
            if (conselhoSelect.val() === 'Não se aplica') {
                // Remove tudo que não é número e limita a 11 dígitos
                this.value = this.value.replace(/\D/g, '').substring(0, 11);
            }
        });

        // Validação adicional no submit do formulário
        $('#formAddTecnico').on('submit', function(e) {
            var conselho = $('#conselho').val();
            var numeroRegistro = $('#numero_registro_conselho').val();
            var cpfInicial = $('#cpfTecnico').val().replace(/\D/g, '');
            
            if (conselho === 'Não se aplica') {
                // Garante que o campo está preenchido com o CPF inicial
                if (!numeroRegistro || numeroRegistro !== cpfInicial) {
                    $('#numero_registro_conselho').val(cpfInicial);
                }
                // Valida se o CPF inicial é válido (11 dígitos)
                if (cpfInicial.length !== 11) {
                    e.preventDefault();
                    alert('O CPF informado deve conter 11 dígitos válidos.');
                    $('#cpfTecnico').focus();
                    return false;
                }
            }
        });
    </script>


    <?php
    $conn->close();
    include '../footer.php';
    ?>
</body>

</html>