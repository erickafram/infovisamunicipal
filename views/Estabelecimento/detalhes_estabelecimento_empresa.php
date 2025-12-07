<?php
session_start();

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Processo.php';
require_once '../../models/ResponsavelLegal.php';
require_once '../../models/ResponsavelTecnico.php';
require_once '../../models/UsuarioEstabelecimento.php';

$estabelecimentoModel = new Estabelecimento($conn);
$processoModel = new Processo($conn);
$responsavelLegalModel = new ResponsavelLegal($conn);
$responsavelTecnicoModel = new ResponsavelTecnico($conn);
$usuarioEstabelecimentoModel = new UsuarioEstabelecimento($conn);

$userId = $_SESSION['user']['id'];
$vinculosEstabelecimentos = $estabelecimentoModel->getEstabelecimentosByUsuario($userId);

// -------------------------------------------------------------------------
// 1) Defina uma variável para controlar qual seção deve estar ativa:
$sectionAtiva = 'info-estab'; // valor padrão
// Caso exista alguma $_SESSION['error_message'] (por exemplo, ao excluir responsável)
if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) {
    $mensagemErro = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
    // Se o erro for relacionado aos responsáveis, queremos mostrar a aba deles:
    $sectionAtiva = 'resp-estab';
}
// -------------------------------------------------------------------------

if (isset($_GET['id'])) {
    $estabelecimentoId = $_GET['id'];
    $dadosEstabelecimento = $estabelecimentoModel->findById($estabelecimentoId);

    if (!$dadosEstabelecimento || !in_array($estabelecimentoId, array_column($vinculosEstabelecimentos, 'id'))) {
        echo "Estabelecimento não encontrado ou acesso negado!";
        exit();
    }

    // Buscar processos vinculados ao estabelecimento
    $processos = $estabelecimentoModel->getProcessosByEstabelecimento($estabelecimentoId);

    // Buscar CNAEs (atividades)
    $atividades = $estabelecimentoModel->getCnaesByEstabelecimentoId($estabelecimentoId);

    // Buscar responsáveis
    $responsaveisLegais = $responsavelLegalModel->getByEstabelecimento($estabelecimentoId);
    $responsaveisTecnicos = $responsavelTecnicoModel->getByEstabelecimento($estabelecimentoId);

    // Buscar usuários vinculados ao estabelecimento
    $usuariosVinculados = $usuarioEstabelecimentoModel->getUsuariosByEstabelecimento($estabelecimentoId);

    // Verificação de criação de novo processo
    if (isset($_POST['criar_processo'])) {
        $tipoProcesso = $_POST['tipo_processo'];

        if (empty($responsaveisLegais)) {
            $mensagemErro = "Por favor, cadastre um responsável legal antes de criar um novo processo.";
            // 2) Se houve erro ao criar processo, forçamos a aba de criação:
            $sectionAtiva = 'criar-processo';
        } else {
            $anoAtual = date('Y');
            $processosAnoAtual = array_filter($processos, function ($processo) use ($anoAtual, $tipoProcesso) {
                return date('Y', strtotime($processo['data_abertura'])) == $anoAtual
                    && $processo['tipo_processo'] == $tipoProcesso;
            });

            if (!empty($processosAnoAtual)) {
                $mensagemErro = "Já existe um processo de $tipoProcesso criado para este ano.";
                // Também ficamos na aba de criação se houver este erro
                $sectionAtiva = 'criar-processo';
            } else {
                if ($tipoProcesso == 'LICENCIAMENTO') {
                    $processoModel->createProcessoLicenciamento($estabelecimentoId);
                } elseif ($tipoProcesso == 'PROJETO ARQUITETÔNICO') {
                    $processoModel->createProcessoProjetoArquitetonico($estabelecimentoId);
                }
                header("Location: detalhes_estabelecimento_empresa.php?id=$estabelecimentoId");
                exit();
            }
        }
    }


    // Processamento de POST para CNAEs e Responsáveis
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        // Vincular CNAEs
        if (isset($_POST['cnaes'])) {
            $cnaes = json_decode($_POST['cnaes'], true); // Recebe o array de CNAEs a serem vinculados

            // Obtém os CNAEs já vinculados ao estabelecimento
            $cnaesExistentes = $estabelecimentoModel->getCnaesByEstabelecimentoId($estabelecimentoId);

            foreach ($cnaes as $cnae) {
                // Verifica se o CNAE já está vinculado
                if (in_array($cnae['id'], array_column($cnaesExistentes, 'cnae'))) {
                    echo json_encode(['success' => false, 'error' => "CNAE {$cnae['cnae']} já está vinculado!"]);
                    exit();
                }
            }
            // Se nenhum CNAE estiver duplicado, prossegue com a vinculação
            $result = $estabelecimentoModel->vincularCnaes($estabelecimentoId, $cnaes);

            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao vincular CNAEs.']);
            }
            exit();
        }

        // Exclusão de Responsável Legal (via modal)
        if (isset($_POST['id']) && isset($_POST['estabelecimento_id']) && !isset($_POST['add_legal']) && !isset($_POST['add_tecnico'])) {
            $responsavelId = $_POST['id'];
            $estabelecimentoId = $_POST['estabelecimento_id'];

            $deleteResult = $responsavelLegalModel->delete($responsavelId, $estabelecimentoId);

            if (!$deleteResult['success']) {
                $_SESSION['error_message'] = $deleteResult['message'];
                header("Location: detalhes_estabelecimento_empresa.php?id=$estabelecimentoId");
                exit();
            } else {
                $_SESSION['success_message'] = 'Responsável legal excluído com sucesso!';
                header("Location: detalhes_estabelecimento_empresa.php?id=$estabelecimentoId");
                exit();
            }
        }

        // Adicionar Responsável Legal
        if (isset($_POST['add_legal'])) {
            $cpf = $_POST['cpf'];
            $responsavelExistente = $responsavelLegalModel->findByCpf($cpf);

            if ($responsavelExistente) {
                // Caso já exista no sistema, apenas vincular
                $responsavelLegalModel->create(
                    $estabelecimentoId,
                    $responsavelExistente['nome'],
                    $responsavelExistente['cpf'],
                    $responsavelExistente['email'],
                    $responsavelExistente['telefone'],
                    $responsavelExistente['documento_identificacao']
                );
                $_SESSION['success_message'] = 'Responsável legal vinculado com sucesso!';
            } else {
                // Novo cadastro
                $nome = $_POST['nome'];
                $email = $_POST['email'];
                $telefone = $_POST['telefone'];
                $documento_identificacao = $_FILES['documento_identificacao']['name'];
                $target_dir = "../../uploads/";
                $target_file = $target_dir . basename($_FILES["documento_identificacao"]["name"]);
                move_uploaded_file($_FILES["documento_identificacao"]["tmp_name"], $target_file);

                $responsavelLegalModel->create(
                    $estabelecimentoId,
                    $nome,
                    $cpf,
                    $email,
                    $telefone,
                    $documento_identificacao
                );
                $_SESSION['success_message'] = 'Responsável legal adicionado com sucesso!';
            }
            header("Location: detalhes_estabelecimento_empresa.php?id=$estabelecimentoId");
            exit();
        }

        // Adicionar Responsável Técnico
        elseif (isset($_POST['add_tecnico'])) {
            $cpf = $_POST['cpf'];
            $responsavelExistente = $responsavelTecnicoModel->findByCpf($cpf);

            if ($responsavelExistente) {
                // Caso já exista no sistema, apenas vincular
                $responsavelTecnicoModel->create(
                    $estabelecimentoId,
                    $responsavelExistente['nome'],
                    $responsavelExistente['cpf'],
                    $responsavelExistente['email'],
                    $responsavelExistente['telefone'],
                    $responsavelExistente['conselho'],
                    $responsavelExistente['numero_registro_conselho'],
                    $responsavelExistente['carteirinha_conselho']
                );
                $_SESSION['success_message'] = 'Responsável técnico vinculado com sucesso!';
            } else {
                // Novo cadastro
                $nome = $_POST['nome'];
                $email = $_POST['email'];
                $telefone = $_POST['telefone'];
                $conselho = $_POST['conselho'];
                $numero_registro_conselho = $_POST['numero_registro_conselho'];
                $carteirinha_conselho = $_FILES['carteirinha_conselho']['name'];
                $target_dir = "../../uploads/";
                $target_file = $target_dir . basename($_FILES["carteirinha_conselho"]["name"]);
                move_uploaded_file($_FILES["carteirinha_conselho"]["tmp_name"], $target_file);

                $responsavelTecnicoModel->create(
                    $estabelecimentoId,
                    $nome,
                    $cpf,
                    $email,
                    $telefone,
                    $conselho,
                    $numero_registro_conselho,
                    $carteirinha_conselho
                );
                $_SESSION['success_message'] = 'Responsável técnico adicionado com sucesso!';
            }
            header("Location: detalhes_estabelecimento_empresa.php?id=$estabelecimentoId");
            exit();
        }
    }
} else {
    echo "ID do estabelecimento não fornecido!";
    exit();
}

include '../../includes/header_empresa.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Estabelecimento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Adicionar fontes do Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #93c5fd;
            --secondary: #6b7280;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #93c5fd;
            --secondary: #6b7280;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        /* Texto muito pequeno para labels e títulos de campos */
        .text-2xs {
            font-size: 0.65rem !important;
            letter-spacing: 0.01em;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: var(--gray-700);
        }
        
        /* Card styles */
        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid var(--gray-100);
            transition: box-shadow 0.2s;
        }
        
        .card:hover {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            padding: 0.7rem 1rem;
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 0.9rem;
        }

        /* Font Sizing */
        .card-title {
            color: var(--gray-800);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            font-weight: 500;
            letter-spacing: 0.01em;
        }
        
        .card-title i {
            margin-right: 0.5rem;
            color: var(--primary);
            font-size: 0.75rem;
        }
        
        /* Form Control Styling */
        .form-control {
            font-size: 0.875rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--gray-300);
            width: 100%;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        
        /* Button styling */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .btn-secondary {
            background-color: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-300);
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #0da271;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #d97706;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        /* Info list styling */
        .info-list {
            display: flex;
            flex-wrap: wrap;
            list-style-type: none;
            padding-left: 0;
            margin: 0;
            gap: 1rem;
        }

        .info-item {
            flex: 1 1 calc(50% - 1rem);
            padding: 1rem;
            background-color: var(--gray-50);
            border-radius: 0.75rem;
            border: 1px solid var(--gray-100);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .info-item:hover {
            background-color: var(--gray-100);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
        }
        
        .info-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--primary);
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .info-item:hover::before {
            opacity: 1;
        }

        .info-item strong {
            display: block;
            font-size: 0.65rem;
            font-weight: 500;
            color: var(--gray-500);
            margin-bottom: 0.15rem;
            letter-spacing: 0.02em;
        }
        
        .info-item span, .info-item p {
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.8rem;
            line-height: 1.3;
        }
        
        /* Badge styling */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1;
            letter-spacing: 0.025em;
        }
        
        .badge-primary {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }
        
        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .badge-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Status indicator */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 500;
            white-space: nowrap;
            line-height: 1;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-active::before {
            content: '';
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            background-color: var(--success);
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-paused {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .status-paused::before {
            content: '';
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            background-color: var(--danger);
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-archived {
            background-color: rgba(107, 114, 128, 0.1);
            color: var(--gray-600);
        }
        
        .status-archived::before {
            content: '';
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            background-color: var(--gray-600);
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        /* Process card styling */
        .process-card {
            background-color: white;
            border-radius: 0.5rem;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .process-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-color: var(--primary-light);
        }
        
        .process-card-header {
            padding: 0.75rem;
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-100);
            text-align: center;
        }
        
        .process-card-title {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.85rem;
            margin: 0;
        }
        
        .process-card-body {
            padding: 0.75rem;
            flex-grow: 1;
            font-size: 0.85rem;
        }
        
        .process-card-footer {
            padding: 0.75rem;
            background-color: var(--gray-50);
            border-top: 1px solid var(--gray-100);
            text-align: center;
        }
        
        /* Improved sidebar menu */
        .side-menu {
            background-color: white;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .side-menu .menu-header {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .side-menu .menu-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        
        .side-menu .menu-body {
            padding: 0.75rem;
        }
        
        .side-menu-item {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.75rem;
            color: var(--gray-700);
            border-radius: 0.4rem;
            text-decoration: none;
            transition: all 0.2s ease;
            margin-bottom: 0.2rem;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .side-menu-item:hover {
            background-color: var(--gray-50);
            color: var(--primary);
        }
        
        .side-menu-item.active {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary);
            font-weight: 500;
        }
        
        .side-menu-item i {
            margin-right: 0.5rem;
            font-size: 0.85rem;
            width: 1.15rem;
            text-align: center;
            color: inherit;
        }
        
        /* Empty state styling */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background-color: var(--gray-50);
            border-radius: 0.75rem;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1.5rem;
        }
        
        .empty-state-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .empty-state-description {
            color: var(--gray-500);
            max-width: 24rem;
            margin: 0 auto;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .info-item {
                flex: 1 1 100%;
            }
            
            .card {
                border-radius: 0.75rem;
            }
            
            .card-body {
                padding: 1rem;
            }
        }

        /* Ajustes para as seções principais */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 0.75rem;
        }

        .info-item {
            padding: 0.75rem;
            background-color: var(--gray-50);
            border-radius: 0.4rem;
            border: 1px solid var(--gray-100);
        }

        .info-item-label {
            font-size: 0.65rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .info-item-value {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-800);
        }

        /* Informações principais na parte superior */
        .main-info {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            padding: 0;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }

        .main-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }

        .main-info-item {
            padding: 0.75rem 1rem;
            border-right: 1px solid var(--gray-100);
            border-bottom: 1px solid var(--gray-100);
        }

        .main-info-item:last-child {
            border-right: none;
        }

        .main-info-label {
            font-size: 0.65rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .main-info-value {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-800);
        }

        /* Lista de atividades e processos */
        .activity-list,
        .process-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            background-color: white;
            border-radius: 0.4rem;
            padding: 0.6rem 0.75rem;
            margin-bottom: 0.5rem;
            border: 1px solid var(--gray-100);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }

        .activity-item:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            transform: translateY(-1px);
        }

        .activity-item i {
            color: var(--primary);
            margin-right: 0.5rem;
            font-size: 0.7rem;
        }

        .activity-code {
            font-weight: 600;
            margin-right: 0.5rem;
            color: var(--gray-800);
        }

        /* Ajustes para os forms */
        .form-group {
            margin-bottom: 0.75rem;
        }

        /* Tabelas mais compactas */
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .table th {
            background-color: var(--gray-50);
            padding: 0.5rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.75rem;
        }

        .table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background-color: var(--gray-50);
        }

        /* Status badges mais compactos */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-ativo {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-parado {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .status-arquivado {
            background-color: #e4e4e7;
            color: #3f3f46;
        }

        .status-pendente {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Botões menores e mais compactos */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            line-height: 1.5;
            border-radius: 0.25rem;
        }

        .btn-icon {
            width: 1.75rem;
            height: 1.75rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.25rem;
        }

        .btn-icon i {
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <div class="container mx-auto px-3 py-6 mt-4">
        <?php if (isset($mensagemErro)) : ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?php echo $mensagemErro; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) : ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?php echo $_SESSION['success_message']; ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) : ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?php echo $_SESSION['error_message']; ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- ==================== MENU LATERAL ==================== -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-1">
                <div class="side-menu">
                    <div class="menu-header">
                        <h2 class="menu-title">
                            <i class="fas fa-th-large text-blue-500 mr-2"></i>Menu
                        </h2>
                    </div>
                    <div class="menu-body">
                        <ul class="space-y-1">
                            <li>
                                <a href="#" onclick="showSection('info-estab')" class="side-menu-item <?php echo ($sectionAtiva == 'info-estab') ? 'active' : ''; ?>">
                                    <i class="fas fa-info-circle"></i>
                                    Informações do Estabelecimento
                                </a>
                            </li>
                            <li>
                                <a href="#" onclick="showSection('processos-estab')" class="side-menu-item <?php echo ($sectionAtiva == 'processos-estab') ? 'active' : ''; ?>">
                                    <i class="fas fa-clipboard-list"></i>
                                    Processos do Estabelecimento
                                </a>
                            </li>
                            <li>
                                <a href="#" onclick="showSection('resp-estab')" class="side-menu-item <?php echo ($sectionAtiva == 'resp-estab') ? 'active' : ''; ?>">
                                    <i class="fas fa-users"></i>
                                    Responsáveis
                                </a>
                            </li>
                            <li>
                                <a href="#" onclick="showSection('usuarios-estab')" class="side-menu-item <?php echo ($sectionAtiva == 'usuarios-estab') ? 'active' : ''; ?>">
                                    <i class="fas fa-user-friends"></i>
                                    Usuários Vinculados
                                </a>
                            </li>
                            <li>
                                <a href="#" onclick="showSection('criar-processo')" class="side-menu-item <?php echo ($sectionAtiva == 'criar-processo') ? 'active' : ''; ?>">
                                    <i class="fas fa-plus-circle"></i>
                                    Criar Novo Processo
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Card do estabelecimento (resumo) -->
                <div class="card mt-6">
                    <div class="card-header">
                        <h2 class="text-sm font-semibold">Resumo do Estabelecimento</h2>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-building fa-lg"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                <?php echo htmlspecialchars($dadosEstabelecimento['nome_fantasia']); ?>
                            </h3>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center mr-3">
                                    <i class="fas fa-id-card text-blue-500"></i>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-500">
                                        <?php echo ($dadosEstabelecimento['tipo_pessoa'] == 'fisica') ? 'CPF' : 'CNPJ'; ?>
                                    </span>
                                    <span class="font-medium">
                                        <?php echo htmlspecialchars($dadosEstabelecimento['tipo_pessoa'] == 'fisica' ? 
                                            ($dadosEstabelecimento['cpf'] ?? 'Não informado') : 
                                            ($dadosEstabelecimento['cnpj'] ?? 'Não informado')); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center mr-3">
                                    <i class="fas fa-phone text-blue-500"></i>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-500">Telefone</span>
                                    <span class="font-medium">
                                        <?php echo htmlspecialchars($dadosEstabelecimento['ddd_telefone_1'] ?? 'Não informado'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conteúdo Principal -->
            <div class="md:col-span-3">
                <!-- SEÇÃO: INFORMAÇÕES DO ESTABELECIMENTO -->
                <div id="info-estab" style="display: none;">
                    <!-- Card: Informações do Estabelecimento -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-info-circle"></i>
                                Informações do Estabelecimento
                            </h5>
                            
                            <?php if ($dadosEstabelecimento['tipo_pessoa'] == 'fisica') : ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editarCadastroModal">
                                <i class="fas fa-edit mr-1"></i> Editar Cadastro
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <ul class="info-list">
                                <?php if ($dadosEstabelecimento['tipo_pessoa'] == 'fisica') : ?>
                                    <!-- Informações de Pessoa Física -->
                                    <li class="info-item">
                                        <strong>Nome Completo</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['nome'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Nome Fantasia</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>CPF</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['cpf'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>RG</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['rg'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Órgão Emissor</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['orgao_emissor'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Endereço</strong>
                                        <span>
                                        <?php
                                        echo htmlspecialchars($dadosEstabelecimento['logradouro'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['numero'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['bairro'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['municipio'] ?? '') . ' - ' .
                                            htmlspecialchars($dadosEstabelecimento['uf'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['cep'] ?? '');
                                        ?>
                                        </span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Telefone</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['ddd_telefone_1'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>E-mail</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['email'] ?? 'Não informado'); ?></span>
                                    </li>
                                <?php else : ?>
                                    <!-- Informações de Pessoa Jurídica -->
                                    <li class="info-item">
                                        <strong>Nome Fantasia</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Razão Social</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['razao_social'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>CNPJ</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['cnpj'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Endereço</strong>
                                        <span>
                                        <?php
                                        echo htmlspecialchars($dadosEstabelecimento['logradouro'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['numero'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['bairro'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['municipio'] ?? '') . ' - ' .
                                            htmlspecialchars($dadosEstabelecimento['uf'] ?? '') . ', ' .
                                            htmlspecialchars($dadosEstabelecimento['cep'] ?? '');
                                        ?>
                                        </span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Telefone</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['ddd_telefone_1'] ?? 'Não informado'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <strong>Situação Cadastral</strong>
                                        <span><?php echo htmlspecialchars($dadosEstabelecimento['descricao_situacao_cadastral'] ?? 'Não informado'); ?></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Card para Atividades (CNAE) -->
                    <?php if (!empty($atividades)) : ?>
                    <div class="card mt-6">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-list-alt"></i>
                                Atividades Cadastradas (CNAEs)
                            </h5>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editarCNAEModal">
                                <i class="fas fa-edit mr-1"></i> Editar Atividades
                                </button>
                            </div>
                            <div class="card-body">
                            <div class="grid grid-cols-1 gap-3">
                                        <?php foreach ($atividades as $atividade) : ?>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 hover:shadow-sm transition-shadow">
                                        <div class="flex items-start">
                                            <div class="rounded-md bg-blue-50 p-2 mr-3 text-blue-500">
                                                <i class="fas fa-sitemap"></i>
                                            </div>
                                            <div>
                                                <span class="text-sm font-medium text-gray-900 block">
                                                    <?php echo htmlspecialchars($atividade['cnae']); ?>
                                                </span>
                                                <span class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($atividade['descricao']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                        <?php endforeach; ?>
                            </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card de Processos em Andamento -->
                    <div class="card mt-6">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-tasks"></i>
                                Processos em Andamento
                            </h5>
                            <button type="button" class="btn btn-primary btn-sm" onclick="showSection('criar-processo')">
                                <i class="fas fa-plus mr-1"></i> Criar Processo
                            </button>
                        </div>
                        <div class="card-body">
                        <?php
                            // Filtrar SOMENTE os processos cujo status seja ATIVO ou PARADO
                        $processosFiltrados = array_filter($processos, function ($proc) {
                            return in_array($proc['status'], ['ATIVO', 'PARADO']);
                        });
                        ?>

                        <?php if (!empty($processosFiltrados)) : ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($processosFiltrados as $processo) : ?>
                                        <div class="process-card">
                                            <div class="process-card-header">
                                                <h3 class="process-card-title">
                                                        <?php echo htmlspecialchars($processo['tipo_processo']); ?>
                                                </h3>
                                            </div>
                                            <div class="process-card-body">
                                                <div class="space-y-3">
                                                    <div class="flex justify-between">
                                                        <span class="text-sm text-gray-500">Nº Processo:</span>
                                                        <span class="text-sm font-medium"><?php echo htmlspecialchars($processo['numero_processo']); ?></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-sm text-gray-500">Data de Abertura:</span>
                                                        <span class="text-sm font-medium"><?php echo date('d/m/Y', strtotime($processo['data_abertura'])); ?></span>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-sm text-gray-500 mb-1">Status:</span>
                                                        <?php if ($processo['status'] === 'PARADO') : ?>
                                                            <span class="status-indicator status-paused self-start">PARADO</span>
                                                        <?php elseif ($processo['status'] === 'ATIVO') : ?>
                                                            <span class="status-indicator status-active self-start">EM ANDAMENTO</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="process-card-footer">
                                                <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($processo['id']); ?>" class="btn btn-primary">
                                                    <i class="fas fa-eye mr-1"></i> Ver Processo
                                                </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <h3 class="empty-state-title">Nenhum processo em andamento</h3>
                                    <p class="empty-state-description">
                                        Não existem processos ativos ou parados para este estabelecimento.
                                        Você pode criar um novo processo utilizando o botão acima.
                                    </p>
                                </div>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
                <!-- FIM DA SEÇÃO: INFORMAÇÕES DO ESTABELECIMENTO -->


                <!-- SEÇÃO: PROCESSOS DO ESTABELECIMENTO -->
                <div id="processos-estab" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-clipboard-list"></i>
                                Processos do Estabelecimento
                            </h5>
                            <button type="button" class="btn btn-primary btn-sm" onclick="showSection('criar-processo')">
                                <i class="fas fa-plus mr-1"></i> Criar Processo
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($processos)) : ?>
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                                    <?php foreach ($processos as $processo) : ?>
                                        <div class="process-card">
                                            <div class="process-card-header">
                                                <h3 class="process-card-title">
                                                        <?php echo htmlspecialchars($processo['tipo_processo']); ?>
                                                    </h3>
                                                </div>
                                            <div class="process-card-body">
                                                <div class="space-y-3">
                                                    <div class="flex justify-between">
                                                        <span class="text-sm text-gray-500">Nº Processo:</span>
                                                        <span class="text-sm font-medium"><?php echo htmlspecialchars($processo['numero_processo']); ?></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-sm text-gray-500">Data de Abertura:</span>
                                                        <span class="text-sm font-medium"><?php echo date('d/m/Y', strtotime($processo['data_abertura'])); ?></span>
                                                    </div>
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-sm text-gray-500">Status:</span>
                                                        <?php if ($processo['status'] === 'PARADO') : ?>
                                                            <span class="status-indicator status-paused">
                                                                PARADO
                                                            </span>
                                                        <?php elseif ($processo['status'] === 'ATIVO') : ?>
                                                            <span class="status-indicator status-active">
                                                                EM ANDAMENTO
                                                            </span>
                                                        <?php elseif ($processo['status'] === 'ARQUIVADO') : ?>
                                                            <span class="status-indicator status-archived">
                                                                ARQUIVADO
                                                            </span>
                                                        <?php else : ?>
                                                            <span class="badge badge-primary">
                                                                <?php echo htmlspecialchars($processo['status']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="process-card-footer">
                                                    <a href="../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($processo['id']); ?>" 
                                                   class="btn btn-primary">
                                                        <i class="fas fa-eye mr-1"></i> Ver Processo
                                                    </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <h3 class="empty-state-title">Nenhum processo encontrado</h3>
                                    <p class="empty-state-description">
                                        Este estabelecimento ainda não possui processos registrados.
                                        Você pode criar um novo processo utilizando o botão acima.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Status dos Processos -->
                    <?php if (!empty($processos)) : ?>
                    <div class="card mt-6">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-chart-pie"></i>
                                Resumo de Status dos Processos
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <?php
                                // Contagem de processos por status
                                $statusCounts = [
                                    'ATIVO' => 0,
                                    'PARADO' => 0,
                                    'ARQUIVADO' => 0
                                ];
                                
                                foreach ($processos as $processo) {
                                    if (isset($statusCounts[$processo['status']])) {
                                        $statusCounts[$processo['status']]++;
                                    }
                                }
                                
                                // Definir as cores para cada status
                                $statusColors = [
                                    'ATIVO' => 'bg-green-100 text-green-800',
                                    'PARADO' => 'bg-red-100 text-red-800',
                                    'ARQUIVADO' => 'bg-gray-100 text-gray-800'
                                ];
                                
                                $statusIcons = [
                                    'ATIVO' => 'fa-play-circle',
                                    'PARADO' => 'fa-pause-circle',
                                    'ARQUIVADO' => 'fa-archive'
                                ];
                                
                                foreach ($statusCounts as $status => $count) :
                                    if ($count > 0) :
                                ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 rounded-full <?php echo str_replace('text-', 'bg-', explode(' ', $statusColors[$status])[1]); ?> bg-opacity-20 flex items-center justify-center mr-4">
                                            <i class="fas <?php echo $statusIcons[$status]; ?> <?php echo explode(' ', $statusColors[$status])[1]; ?>"></i>
                                        </div>
                                        <div>
                                            <span class="block text-2xl font-bold <?php echo explode(' ', $statusColors[$status])[1]; ?>"><?php echo $count; ?></span>
                                            <span class="text-sm text-gray-500"><?php echo $status === 'ATIVO' ? 'Em Andamento' : $status; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- FIM DA SEÇÃO: PROCESSOS DO ESTABELECIMENTO -->


                <!-- SEÇÃO: RESPONSÁVEIS PELO ESTABELECIMENTO -->
                <div id="resp-estab" style="display: none;">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                        <div class="border-b border-gray-200 px-6 py-4">
                            <h2 class="text-lg font-medium text-gray-800 flex items-center">
                                <i class="fas fa-users mr-3 text-blue-500"></i>Responsáveis pelo Estabelecimento
                            </h2>
                        </div>
                        <div class="p-6">
                            <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mb-6" data-bs-toggle="modal" data-bs-target="#modalEscolherResponsavel">
                                <i class="fas fa-plus-circle mr-2"></i> Adicionar Responsável
                            </button>

                            <!-- Lista de Responsáveis Legais -->
                            <div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
                                <div class="border-b border-gray-200 px-6 py-4">
                                    <h2 class="text-lg font-medium text-gray-800 flex items-center">
                                        <i class="fas fa-user-tie mr-3 text-blue-500"></i>Responsáveis Legais
                                    </h2>
                                </div>
                                <div class="p-6">
                                    <?php if (empty($responsaveisLegais)): ?>
                                        <div class="bg-gray-50 rounded-lg p-6 text-center">
                                            <div class="flex flex-col items-center">
                                                <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                                                <p class="text-gray-500">Nenhum responsável legal cadastrado.</p>
                                                <p class="text-gray-400 text-sm mt-2">Adicione um responsável legal para criar processos.</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($responsaveisLegais as $responsavel) : ?>
                                            <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Nome:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['nome']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">CPF:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['cpf']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Email:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['email']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Telefone:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['telefone']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Documento de Identificação:</p>
                                                        <?php if (!empty($responsavel['documento_identificacao'])) : ?>
                                                            <a href="../../uploads/<?php echo htmlspecialchars($responsavel['documento_identificacao']); ?>" 
                                                               target="_blank"
                                                               class="text-blue-600 hover:text-blue-800 font-medium">
                                                                <i class="fas fa-file-pdf mr-1"></i>
                                                                <?php echo htmlspecialchars($responsavel['documento_identificacao']); ?>
                                                            </a>
                                                        <?php else : ?>
                                                            <span class="text-gray-500">Nenhum documento</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="p-2 flex items-center justify-end space-x-2">
                                                        <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalEditarResponsavelLegal"
                                                            data-id="<?php echo $responsavel['id']; ?>"
                                                            data-nome="<?php echo $responsavel['nome']; ?>"
                                                            data-cpf="<?php echo $responsavel['cpf']; ?>"
                                                            data-email="<?php echo $responsavel['email']; ?>"
                                                            data-telefone="<?php echo $responsavel['telefone']; ?>"
                                                            data-documento="<?php echo $responsavel['documento_identificacao']; ?>">
                                                            <i class="fas fa-edit mr-1"></i> Editar
                                                        </button>
                                                        <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalExcluirResponsavelLegal"
                                                            data-id="<?php echo $responsavel['id']; ?>">
                                                            <i class="fas fa-trash-alt mr-1"></i> Excluir
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lista de Responsáveis Técnicos -->
                            <div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
                                <div class="border-b border-gray-200 px-6 py-4">
                                    <h2 class="text-lg font-medium text-gray-800 flex items-center">
                                        <i class="fas fa-user-md mr-3 text-blue-500"></i>Responsáveis Técnicos
                                    </h2>
                                </div>
                                <div class="p-6">
                                    <?php if (empty($responsaveisTecnicos)): ?>
                                        <div class="bg-gray-50 rounded-lg p-6 text-center">
                                            <div class="flex flex-col items-center">
                                                <i class="fas fa-user-md text-gray-400 text-4xl mb-4"></i>
                                                <p class="text-gray-500">Nenhum responsável técnico cadastrado.</p>
                                                <p class="text-gray-400 text-sm mt-2">Adicione um responsável técnico quando necessário.</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($responsaveisTecnicos as $responsavel) : ?>
                                            <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Nome:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['nome']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">CPF:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['cpf']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Email:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['email']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Telefone:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['telefone']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Conselho:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['conselho']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Número do Registro:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($responsavel['numero_registro_conselho']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Carteirinha do Conselho:</p>
                                                        <a href="../../uploads/<?php echo htmlspecialchars($responsavel['carteirinha_conselho']); ?>" 
                                                           target="_blank"
                                                           class="text-blue-600 hover:text-blue-800 font-medium">
                                                            <i class="fas fa-file-pdf mr-1"></i>
                                                            <?php echo htmlspecialchars($responsavel['carteirinha_conselho']); ?>
                                                        </a>
                                                    </div>
                                                    <div class="p-2 flex items-center justify-end space-x-2">
                                                        <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalEditarResponsavelTecnico"
                                                            data-id="<?php echo $responsavel['id']; ?>"
                                                            data-nome="<?php echo $responsavel['nome']; ?>"
                                                            data-cpf="<?php echo $responsavel['cpf']; ?>"
                                                            data-email="<?php echo $responsavel['email']; ?>"
                                                            data-telefone="<?php echo $responsavel['telefone']; ?>"
                                                            data-conselho="<?php echo $responsavel['conselho']; ?>"
                                                            data-numero-registro="<?php echo $responsavel['numero_registro_conselho']; ?>"
                                                            data-carteirinha="<?php echo $responsavel['carteirinha_conselho']; ?>">
                                                            <i class="fas fa-edit mr-1"></i> Editar
                                                        </button>
                                                        <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalExcluirResponsavelTecnico"
                                                            data-id="<?php echo $responsavel['id']; ?>">
                                                            <i class="fas fa-trash-alt mr-1"></i> Excluir
                                                        </button>
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
                <!-- FIM DA SEÇÃO: RESPONSÁVEIS PELO ESTABELECIMENTO -->

                <!-- SEÇÃO: USUÁRIOS VINCULADOS AO ESTABELECIMENTO -->
                <div id="usuarios-estab" style="display: none;">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                        <div class="border-b border-gray-200 px-6 py-4">
                            <h2 class="text-lg font-medium text-gray-800 flex items-center">
                                <i class="fas fa-user-friends mr-3 text-blue-500"></i>Usuários Vinculados ao Estabelecimento
                            </h2>
                        </div>
                        <div class="p-6">
                            <!-- Botão para adicionar novo usuário -->
                            <div class="mb-6">
                                <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalVincularUsuario">
                                    <i class="fas fa-user-plus mr-2"></i> Vincular Novo Usuário
                                </button>
                            </div>

                            <!-- Lista de Usuários Vinculados -->
                            <div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
                                <div class="border-b border-gray-200 px-6 py-4">
                                    <h2 class="text-lg font-medium text-gray-800 flex items-center">
                                        <i class="fas fa-users mr-3 text-blue-500"></i>Usuários
                                    </h2>
                                </div>
                                <div class="p-6">
                                    <?php if (empty($usuariosVinculados)): ?>
                                        <div class="bg-gray-50 rounded-lg p-6 text-center">
                                            <div class="flex flex-col items-center">
                                                <i class="fas fa-user-slash text-gray-400 text-4xl mb-4"></i>
                                                <p class="text-gray-500">Nenhum usuário vinculado ao estabelecimento.</p>
                                                <p class="text-gray-400 text-sm mt-2">Vincule um usuário para gerenciar o estabelecimento.</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($usuariosVinculados as $usuario) : ?>
                                            <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Nome:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($usuario['nome']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Email:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($usuario['email']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Telefone:</p>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($usuario['telefone']); ?></p>
                                                    </div>
                                                    <div class="p-2">
                                                        <p class="text-sm text-gray-500 mb-1">Tipo de Vínculo:</p>
                                                        <p class="font-medium text-gray-900">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                <?php echo htmlspecialchars($usuario['tipo_vinculo']); ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="p-2 flex items-center justify-end space-x-2 col-span-1 md:col-span-2">
                                                        <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalEditarVinculo"
                                                            data-id="<?php echo $usuario['id']; ?>"
                                                            data-tipo-vinculo="<?php echo $usuario['tipo_vinculo']; ?>">
                                                            <i class="fas fa-edit mr-1"></i> Editar Vínculo
                                                        </button>
                                                        <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalDesvincularUsuario"
                                                            data-id="<?php echo $usuario['id']; ?>"
                                                            data-nome="<?php echo $usuario['nome']; ?>">
                                                            <i class="fas fa-unlink mr-1"></i> Desvincular
                                                        </button>
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
                <!-- FIM DA SEÇÃO: USUÁRIOS VINCULADOS AO ESTABELECIMENTO -->

                <!-- SEÇÃO: CRIAR NOVO PROCESSO -->
                <div id="criar-processo" style="display: none;">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                        <div class="border-b border-gray-200 px-6 py-4">
                            <h2 class="text-lg font-medium text-gray-800 flex items-center">
                                <i class="fas fa-plus-circle mr-3 text-blue-500"></i>Criar Novo Processo
                            </h2>
                        </div>
                        <div class="p-6">
                            <?php if (isset($mensagemErro)) : ?>
                                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p><?php echo $mensagemErro; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form action="detalhes_estabelecimento_empresa.php?id=<?php echo $estabelecimentoId; ?>" method="POST">
                                <div class="mb-6">
                                    <label for="tipo_processo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Processo</label>
                                    <select class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-2 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm" id="tipo_processo" name="tipo_processo" required>
                                        <option value="" selected disabled>SELECIONE O TIPO DE PROCESSO</option>
                                        <option value="LICENCIAMENTO">LICENCIAMENTO</option>
                                        <option value="PROJETO ARQUITETÔNICO">PROJETO ARQUITETÔNICO</option>
                                    </select>
                                    <p class="mt-2 text-sm text-gray-500">
                                        Selecione o tipo de processo que deseja criar para este estabelecimento.
                                    </p>
                                </div>
                                
                                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle text-blue-500"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-gray-700">
                                                Só é possível criar um processo de cada tipo por ano. Certifique-se de que não existe um processo do mesmo tipo já criado para este ano.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <button type="submit" name="criar_processo" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-plus-circle mr-2"></i> Criar Processo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- FIM DA SEÇÃO: CRIAR NOVO PROCESSO -->

            </div>
        </div>
    </div>

    <!-- ==================== MODAIS GERAIS ==================== -->

    <!-- Modal para Editar Cadastro Pessoa Física -->
    <div class="modal fade" id="editarCadastroModal" tabindex="-1" aria-labelledby="editarCadastroModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarCadastroModalLabel">Editar Cadastro - Pessoa Física</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="atualizar_pessoa_fisica.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo $estabelecimentoId; ?>">

                        <!-- Dados Gerais -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Dados Gerais</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="nome" class="form-label">Nome Completo</label>
                                        <input type="text" class="form-control" id="nome" name="nome"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['nome'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                                        <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cpf" class="form-label">CPF</label>
                                        <input type="text" class="form-control" id="cpf" name="cpf"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['cpf'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="rg" class="form-label">RG</label>
                                        <input type="text" class="form-control" id="rg" name="rg"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['rg'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="orgao_emissor" class="form-label">Órgão Emissor</label>
                                        <input type="text" class="form-control" id="orgao_emissor" name="orgao_emissor"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['orgao_emissor'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Endereço -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Endereço</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="logradouro" class="form-label">Endereço</label>
                                        <input type="text" class="form-control" id="logradouro" name="logradouro"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['logradouro'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="numero" class="form-label">Número</label>
                                        <input type="text" class="form-control" id="numero" name="numero"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['numero'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="complemento" class="form-label">Complemento</label>
                                        <input type="text" class="form-control" id="complemento" name="complemento"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['complemento'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="bairro" class="form-label">Bairro</label>
                                        <input type="text" class="form-control" id="bairro" name="bairro"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['bairro'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cep" class="form-label">CEP</label>
                                        <input type="text" class="form-control" id="cep" name="cep"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['cep'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="municipio" class="form-label">Município</label>
                                        <input type="text" class="form-control" id="municipio" name="municipio"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['municipio'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="uf" class="form-label">UF</label>
                                        <input type="text" class="form-control" id="uf" name="uf"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['uf'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Outros Dados -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Outros Dados</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="telefone" class="form-label">Telefone</label>
                                        <input type="text" class="form-control" id="telefone" name="ddd_telefone_1"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['ddd_telefone_1'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">E-mail</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="inicio_funcionamento" class="form-label">Início de Funcionamento</label>
                                        <input type="date" class="form-control" id="inicio_funcionamento" name="inicio_funcionamento"
                                            value="<?php echo htmlspecialchars($dadosEstabelecimento['inicio_funcionamento'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar CNAEs -->
    <?php if ($dadosEstabelecimento['tipo_pessoa'] == 'fisica') : ?>
        <div class="modal fade" id="editarCNAEModal" tabindex="-1" aria-labelledby="editarCNAEModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editarCNAEModalLabel">Editar Atividades (CNAEs)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Campo para buscar CNAEs -->
                        <div class="mb-3">
                            <label for="cnae_search_modal" class="form-label">Buscar CNAE</label>
                            <input type="text" class="form-control" id="cnae_search_modal"
                                placeholder="Digite o código do CNAE" maxlength="7"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 7);">
                            <button type="button" class="btn btn-secondary mt-2" onclick="searchCNAEModal()">Buscar</button>
                        </div>
                        <!-- Resultado da busca -->
                        <div id="cnae_result_modal"></div>
                        <!-- Campo oculto para armazenar os CNAEs selecionados -->
                        <input type="hidden" id="cnaes" value='[]'>
                        <!-- Lista de CNAEs já selecionados -->
                        <div class="mt-4">
                            <h6>CNAEs Vinculados</h6>
                            <ul id="cnaes_list_modal" class="list-group">
                                <?php foreach ($atividades as $atividade) : ?>
                                    <li class="list-group-item">
                                        <?php echo htmlspecialchars($atividade['cnae']); ?> - <?php echo htmlspecialchars($atividade['descricao']); ?>
                                        <button class="btn btn-danger btn-sm float-end"
                                            onclick="removeCNAE(this, '<?php echo $atividade['cnae']; ?>')">
                                            Remover
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="button" class="btn btn-primary" onclick="saveCNAEs()">Salvar Alterações</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal para escolher tipo de Responsável -->
    <div class="modal fade" id="modalEscolherResponsavel" tabindex="-1" aria-labelledby="modalEscolherResponsavelLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEscolherResponsavelLabel">Adicionar Responsável</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <button class="btn btn-primary btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalAddLegal" data-bs-dismiss="modal">
                        Responsável Legal
                    </button>
                    <button class="btn btn-primary btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalAddTecnico" data-bs-dismiss="modal">
                        Responsável Técnico
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para adicionar Responsável Legal -->
    <div class="modal fade" id="modalAddLegal" tabindex="-1" aria-labelledby="modalAddLegalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddLegalLabel">Adicionar Responsável Legal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formAddLegal" action="detalhes_estabelecimento_empresa.php?id=<?php echo $estabelecimentoId; ?>"
                        method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="cpfLegal" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="cpfLegal" name="cpf" required maxlength="11">
                            <small class="form-text text-muted">Digite apenas números, sem ponto ou hífen.</small>
                            <button type="button" class="btn btn-secondary mt-2" id="buscarCpfLegal">Buscar CPF</button>
                        </div>
                        <div id="alertLegal" class="alert alert-info" style="display:none;">
                            Responsável Legal já cadastrado e vinculado ao estabelecimento.
                        </div>
                        <div id="legalFields" style="display: none;">
                            <div class="mb-3">
                                <label for="nomeLegal" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="nomeLegal" name="nome" required readonly>
                            </div>
                            <div class="mb-3">
                                <label for="emailLegal" class="form-label">Email</label>
                                <input type="email" class="form-control" id="emailLegal" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="telefoneLegal" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="telefoneLegal" name="telefone" required>
                            </div>
                            <div class="mb-3">
                                <label for="documento_identificacaoLegal" class="form-label">Documento de Identificação</label>
                                <input type="file" class="form-control" id="documento_identificacaoLegal" name="documento_identificacao" required>
                            </div>
                        </div>
                        <input type="hidden" name="add_legal" value="1">
                        <button type="submit" class="btn btn-primary btn-sm" id="btnAddLegal" style="display: none;">Adicionar Responsável</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para adicionar Responsável Técnico -->
    <div class="modal fade" id="modalAddTecnico" tabindex="-1" aria-labelledby="modalAddTecnicoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddTecnicoLabel">Adicionar Responsável Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formAddTecnico" action="detalhes_estabelecimento_empresa.php?id=<?php echo $estabelecimentoId; ?>"
                        method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="cpfTecnico" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="cpfTecnico" name="cpf" required maxlength="11">
                            <small class="form-text text-muted">Digite apenas números, sem ponto ou hífen.</small>
                            <button type="button" class="btn btn-secondary mt-2" id="buscarCpfTecnico">Buscar CPF</button>
                        </div>
                        <div id="alertTecnico" class="alert alert-info" style="display:none;">
                            Responsável Técnico já cadastrado e vinculado ao estabelecimento.
                        </div>
                        <div id="tecnicoFields" style="display: none;">
                            <div class="mb-3">
                                <label for="nomeTecnico" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="nomeTecnico" name="nome" required readonly>
                            </div>
                            <div class="mb-3">
                                <label for="emailTecnico" class="form-label">Email</label>
                                <input type="email" class="form-control" id="emailTecnico" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="telefoneTecnico" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="telefoneTecnico" name="telefone" required>
                            </div>
                            <div class="mb-3">
                                <label for="conselho" class="form-label">Conselho</label>
                                <select class="form-control" id="conselho" name="conselho" required>
                                    <option value="CRM">CRM</option>
                                    <option value="CRF">CRF</option>
                                    <!-- Adicione outros conselhos conforme necessário -->
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="numero_registro_conselho" class="form-label">Número do Registro do Conselho</label>
                                <input type="text" class="form-control" id="numero_registro_conselho" name="numero_registro_conselho" required>
                            </div>
                            <div class="mb-3">
                                <label for="carteirinha_conselho" class="form-label">Carteirinha do Conselho</label>
                                <input type="file" class="form-control" id="carteirinha_conselho" name="carteirinha_conselho" required>
                            </div>
                        </div>
                        <input type="hidden" name="add_tecnico" value="1">
                        <button type="submit" class="btn btn-primary btn-sm" id="btnAddTecnico" style="display: none;">Adicionar Responsável</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar Responsável Legal -->
    <div class="modal fade" id="modalEditarResponsavelLegal" tabindex="-1" aria-labelledby="modalEditarResponsavelLegalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarResponsavelLegalLabel">Editar Responsável Legal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarLegal" action="editar_responsavel_legal.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="editLegalId">
                        <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimentoId; ?>">
                        <input type="hidden" name="documento_atual" id="editLegalDocumentoAtual">
                        <div class="mb-3">
                            <label for="editLegalNome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="editLegalNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="editLegalCpf" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="editLegalCpf" name="cpf" required>
                        </div>
                        <div class="mb-3">
                            <label for="editLegalEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editLegalEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editLegalTelefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="editLegalTelefone" name="telefone" required>
                        </div>
                        <div class="mb-3">
                            <label for="editLegalDocumento" class="form-label">Documento de Identificação</label>
                            <input type="file" class="form-control" id="editLegalDocumento" name="documento_identificacao">
                            <small class="form-text text-muted" id="documentoAtualText"></small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Salvar Alterações</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para excluir Responsável Legal -->
    <div class="modal fade" id="modalExcluirResponsavelLegal" tabindex="-1" aria-labelledby="modalExcluirResponsavelLegalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalExcluirResponsavelLegalLabel">Excluir Responsável Legal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (count($responsaveisLegais) > 1): ?>
                        <form id="formExcluirLegal" action="detalhes_estabelecimento_empresa.php?id=<?php echo $estabelecimentoId; ?>" method="POST">
                            <input type="hidden" name="id" id="deleteLegalId">
                            <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimentoId; ?>">
                            <p>Tem certeza de que deseja excluir este responsável legal?</p>
                            <button type="submit" class="btn btn-danger">Excluir</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            Não é possível excluir o último responsável legal. Adicione outro responsável antes de excluir este.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar Responsável Técnico -->
    <div class="modal fade" id="modalEditarResponsavelTecnico" tabindex="-1" aria-labelledby="modalEditarResponsavelTecnicoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarResponsavelTecnicoLabel">Editar Responsável Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarTecnico" action="editar_responsavel_tecnico.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="editTecnicoId">
                        <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimentoId; ?>">
                        <input type="hidden" name="carteirinha_atual" id="editTecnicoCarteirinhaAtual">
                        <div class="mb-3">
                            <label for="editTecnicoNome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="editTecnicoNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTecnicoCpf" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="editTecnicoCpf" name="cpf" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTecnicoEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editTecnicoEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTecnicoTelefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="editTecnicoTelefone" name="telefone" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTecnicoConselho" class="form-label">Conselho</label>
                            <input type="text" class="form-control" id="editTecnicoConselho" name="conselho" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTecnicoNumeroRegistro" class="form-label">Número do Registro do Conselho</label>
                            <input type="text" class="form-control" id="editTecnicoNumeroRegistro" name="numero_registro_conselho" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTecnicoCarteirinha" class="form-label">Carteirinha do Conselho</label>
                            <input type="file" class="form-control" id="editTecnicoCarteirinha" name="carteirinha_conselho">
                            <small class="form-text text-muted" id="carteirinhaAtualText"></small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Salvar Alterações</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para excluir Responsável Técnico -->
    <div class="modal fade" id="modalExcluirResponsavelTecnico" tabindex="-1" aria-labelledby="modalExcluirResponsavelTecnicoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalExcluirResponsavelTecnicoLabel">Excluir Responsável Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formExcluirTecnico" action="excluir_responsavel_tecnico.php" method="POST">
                        <input type="hidden" name="id" id="deleteTecnicoId">
                        <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimentoId; ?>">
                        <p>Tem certeza de que deseja excluir este responsável técnico?</p>
                        <button type="submit" class="btn btn-danger">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: VINCULAR USUÁRIO -->
    <div class="modal fade" id="modalVincularUsuario" tabindex="-1" aria-labelledby="modalVincularUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-lg shadow-lg">
                <div class="modal-header bg-blue-50 border-b border-blue-100 px-6 py-4">
                    <h5 class="modal-title text-lg font-medium text-blue-800" id="modalVincularUsuarioLabel">
                        <i class="fas fa-user-plus mr-2"></i>Vincular Usuário ao Estabelecimento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-6">
                    <form id="formBuscarUsuario" action="javascript:void(0);" method="POST">
                        <div class="mb-4">
                            <label for="cpf_busca" class="block text-sm font-medium text-gray-700 mb-2">CPF do Usuário</label>
                            <div class="flex">
                                <input type="text" id="cpf_busca" name="cpf_busca" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Digite o CPF (apenas números)" required>
                                <button type="button" id="btn_buscar_usuario" class="ml-2 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-search mr-2"></i>Buscar
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Digite apenas os números do CPF</p>
                        </div>
                    </form>
                    
                    <!-- Resultado da busca - Usuário encontrado -->
                    <div id="usuario_encontrado" style="display: none;" class="mt-4">
                        <div class="bg-green-50 rounded-md p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">Usuário encontrado!</h3>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p>Nome: <strong id="nome_usuario_encontrado"></strong></p>
                                        <p>Email: <strong id="email_usuario_encontrado"></strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form id="formVincularUsuario" action="../../controllers/UsuarioEstabelecimento/vincular.php" method="POST">
                            <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimentoId; ?>">
                            <input type="hidden" id="usuario_id" name="usuario_id" value="">
                            
                            <div class="mb-4">
                                <label for="tipo_vinculo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Vínculo</label>
                                <select id="tipo_vinculo" name="tipo_vinculo" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                                    <option value="">Selecione o tipo de vínculo</option>
                                    <option value="CONTADOR">CONTADOR</option>
                                    <option value="RESPONSÁVEL LEGAL">RESPONSÁVEL LEGAL</option>
                                    <option value="RESPONSÁVEL TÉCNICO">RESPONSÁVEL TÉCNICO</option>
                                    <option value="FUNCIONÁRIO">FUNCIONÁRIO</option>
                                </select>
                            </div>
                            
                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" data-bs-dismiss="modal">
                                    Cancelar
                                </button>
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-user-plus mr-2"></i>Vincular Usuário
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Usuário não encontrado - Mensagem -->
                    <div id="usuario_nao_encontrado" style="display: none;" class="mt-4">
                        <div class="bg-yellow-50 rounded-md p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">Usuário não encontrado!</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Nenhum usuário encontrado com este CPF. Verifique se o CPF está correto ou cadastre um novo usuário no sistema.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: EDITAR VÍNCULO -->
    <div class="modal fade" id="modalEditarVinculo" tabindex="-1" aria-labelledby="modalEditarVinculoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-lg shadow-lg">
                <div class="modal-header bg-yellow-50 border-b border-yellow-100 px-6 py-4">
                    <h5 class="modal-title text-lg font-medium text-yellow-800" id="modalEditarVinculoLabel">
                        <i class="fas fa-edit mr-2"></i>Editar Tipo de Vínculo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-6">
                    <form id="formEditarVinculo" action="../../controllers/UsuarioEstabelecimento/atualizar.php" method="POST">
                        <input type="hidden" name="vinculo_id" id="editar_vinculo_id">
                        
                        <div class="mb-4">
                            <label for="editar_tipo_vinculo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Vínculo</label>
                            <select id="editar_tipo_vinculo" name="tipo_vinculo" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm" required>
                                <option value="CONTADOR">CONTADOR</option>
                                <option value="RESPONSÁVEL LEGAL">RESPONSÁVEL LEGAL</option>
                                <option value="RESPONSÁVEL TÉCNICO">RESPONSÁVEL TÉCNICO</option>
                                <option value="FUNCIONÁRIO">FUNCIONÁRIO</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                <i class="fas fa-save mr-2"></i>Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: DESVINCULAR USUÁRIO -->
    <div class="modal fade" id="modalDesvincularUsuario" tabindex="-1" aria-labelledby="modalDesvincularUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-lg shadow-lg">
                <div class="modal-header bg-red-50 border-b border-red-100 px-6 py-4">
                    <h5 class="modal-title text-lg font-medium text-red-800" id="modalDesvincularUsuarioLabel">
                        <i class="fas fa-unlink mr-2"></i>Desvincular Usuário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-6">
                    <div class="bg-red-50 rounded-md p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Atenção!</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>Você está prestes a desvincular o usuário <strong id="nome_usuario_desvincular"></strong> deste estabelecimento. Esta ação não pode ser desfeita.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form id="formDesvincularUsuario" action="../../controllers/UsuarioEstabelecimento/desvincular.php" method="POST">
                        <input type="hidden" name="vinculo_id" id="desvincular_vinculo_id">
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-unlink mr-2"></i>Confirmar Desvinculação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: CADASTRAR NOVO USUÁRIO -->
    <div class="modal fade" id="modalCadastrarUsuario" tabindex="-1" aria-labelledby="modalCadastrarUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-lg shadow-lg">
                <div class="modal-header bg-green-50 border-b border-green-100 px-6 py-4">
                    <h5 class="modal-title text-lg font-medium text-green-800" id="modalCadastrarUsuarioLabel">
                        <i class="fas fa-user-plus mr-2"></i>Cadastrar Novo Usuário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-6">
                    <div class="bg-blue-50 text-blue-800 p-4 rounded-md mb-6">
                        Preencha corretamente todos os campos abaixo para cadastrar um novo usuário.
                    </div>
                    
                    <form id="formCadastrarUsuario" action="../../controllers/UsuarioExterno/cadastrar.php" method="POST" class="space-y-6">
                        <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimentoId; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="cpf_cadastro" class="block text-gray-700 text-sm font-bold mb-2">CPF</label>
                                <input type="text" id="cpf_cadastro" name="cpf" readonly class="bg-gray-100 shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight">
                            </div>
                            <div>
                                <label for="nome_completo" class="block text-gray-700 text-sm font-bold mb-2">Nome Completo</label>
                                <input type="text" id="nome_completo" name="nome_completo" placeholder="Digite o nome completo" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Use apenas letras maiúsculas</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="telefone_cadastro" class="block text-gray-700 text-sm font-bold mb-2">Telefone Celular</label>
                                <input type="text" id="telefone_cadastro" name="telefone" placeholder="(00) 00000-0000" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="email_cadastro" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                                <input type="email" id="email_cadastro" name="email" placeholder="seuemail@exemplo.com" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="tipo_vinculo_cadastro" class="block text-gray-700 text-sm font-bold mb-2">Tipo de Vínculo</label>
                                <select id="tipo_vinculo_cadastro" name="tipo_vinculo" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                                    <option value="">Selecione o tipo de vínculo</option>
                                    <option value="CONTADOR">CONTADOR</option>
                                    <option value="RESPONSÁVEL LEGAL">RESPONSÁVEL LEGAL</option>
                                    <option value="RESPONSÁVEL TÉCNICO">RESPONSÁVEL TÉCNICO</option>
                                    <option value="FUNCIONÁRIO">FUNCIONÁRIO</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 text-yellow-800 p-4 rounded-md my-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm">
                                        Uma senha padrão será gerada automaticamente no formato <strong>@Visa@<?php echo date('Y'); ?></strong>. 
                                        O usuário poderá alterá-la após o primeiro acesso.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-user-plus mr-2"></i>Cadastrar e Vincular
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/usuario_validacoes.js"></script>
    
    <!-- JavaScript para controlar a exibição das seções -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // JavaScript para controlar a exibição das seções
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar a seção ativa
            showSection('<?php echo $sectionAtiva; ?>');
            
            // Adicionar classe active para o item de menu correspondente
            const menuItems = document.querySelectorAll('.side-menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Remover classe active de todos os itens
                    menuItems.forEach(i => i.classList.remove('active'));
                    // Adicionar classe active ao item clicado
                    this.classList.add('active');
                });
            });
            
            // Se houver uma mensagem de erro, mostrar um toast
            <?php if (isset($mensagemErro)) : ?>
            // Usar SweetAlert2 para exibir a mensagem de erro
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: '<?php echo addslashes($mensagemErro); ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
            });
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) : ?>
            // Usar SweetAlert2 para exibir a mensagem de sucesso
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
        });
        
        // Função para alternar entre as seções
        function showSection(sectionId) {
            // Esconder todas as seções
            const sections = ['info-estab', 'processos-estab', 'resp-estab', 'usuarios-estab', 'criar-processo'];
            sections.forEach(section => {
                const el = document.getElementById(section);
                if (el) el.style.display = 'none';
            });
            
            // Mostrar a seção selecionada
            const selectedSection = document.getElementById(sectionId);
            if (selectedSection) {
                selectedSection.style.display = 'block';
                
                // Animar a entrada da seção com uma leve transição
                selectedSection.style.opacity = '0';
                selectedSection.style.transform = 'translateY(15px)';
                
                setTimeout(() => {
                    selectedSection.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    selectedSection.style.opacity = '1';
                    selectedSection.style.transform = 'translateY(0)';
                }, 50);
            }
            
            // Atualizar o estado ativo no menu
            document.querySelectorAll('.side-menu-item').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('onclick').includes(sectionId)) {
                    item.classList.add('active');
                }
            });
        }

        /**
         * Função para mostrar/esconder seções baseado no ID
         */
        function showSection(sectionId) {
            const sections = ['info-estab', 'processos-estab', 'resp-estab', 'usuarios-estab', 'criar-processo'];
            sections.forEach(id => {
                document.getElementById(id).style.display = 'none';
            });
            document.getElementById(sectionId).style.display = 'block';
        }

        // Mostrar a seção ativa por padrão
        document.addEventListener('DOMContentLoaded', function() {
            showSection('<?php echo $sectionAtiva; ?>');
            
            // Configurar modal de editar vínculo
            const modalEditarVinculo = document.getElementById('modalEditarVinculo');
            if (modalEditarVinculo) {
                modalEditarVinculo.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const tipoVinculo = button.getAttribute('data-tipo-vinculo');
                    
                    document.getElementById('editar_vinculo_id').value = id;
                    document.getElementById('editar_tipo_vinculo').value = tipoVinculo;
                });
            }
            
            // Configurar modal de desvincular usuário
            const modalDesvincularUsuario = document.getElementById('modalDesvincularUsuario');
            if (modalDesvincularUsuario) {
                modalDesvincularUsuario.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const nome = button.getAttribute('data-nome');
                    
                    document.getElementById('desvincular_vinculo_id').value = id;
                    document.getElementById('nome_usuario_desvincular').textContent = nome;
                });
            }
            
            // Configurar busca de usuário por CPF
            const btnBuscarUsuario = document.getElementById('btn_buscar_usuario');
            if (btnBuscarUsuario) {
                btnBuscarUsuario.addEventListener('click', function() {
                    const cpf = document.getElementById('cpf_busca').value.trim();
                    if (!cpf) {
                        alert('Por favor, digite um CPF válido.');
                        return;
                    }
                    
                    // Esconder as seções de resultado
                    document.getElementById('usuario_encontrado').style.display = 'none';
                    document.getElementById('usuario_nao_encontrado').style.display = 'none';
                    
                    // Remover botão antigo se existir
                    const oldBtn = document.getElementById('btn_cadastrar_novo');
                    if (oldBtn) {
                        oldBtn.remove();
                    }
                    
                    // Fazer a requisição AJAX
                    const formData = new FormData();
                    formData.append('cpf', cpf);
                    
                    fetch('../../controllers/UsuarioExterno/buscar_por_cpf.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Usuário encontrado
                            document.getElementById('nome_usuario_encontrado').textContent = data.usuario.nome_completo;
                            document.getElementById('email_usuario_encontrado').textContent = data.usuario.email;
                            document.getElementById('usuario_id').value = data.usuario.id;
                            document.getElementById('usuario_encontrado').style.display = 'block';
                        } else {
                            // Usuário não encontrado
                            document.getElementById('usuario_nao_encontrado').style.display = 'block';
                            
                            // Adicionar botão para cadastrar novo usuário
                            const divNaoEncontrado = document.getElementById('usuario_nao_encontrado');
                            const btnCadastrarUsuario = document.createElement('button');
                            btnCadastrarUsuario.id = 'btn_cadastrar_novo';
                            btnCadastrarUsuario.className = 'inline-flex items-center mt-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500';
                            btnCadastrarUsuario.innerHTML = '<i class="fas fa-user-plus mr-2"></i>Cadastrar Novo Usuário';
                            btnCadastrarUsuario.onclick = function() {
                                // Fechar o modal atual
                                const modalVincularUsuario = bootstrap.Modal.getInstance(document.getElementById('modalVincularUsuario'));
                                modalVincularUsuario.hide();
                                
                                // Preparar e abrir o modal de cadastro
                                document.getElementById('cpf_cadastro').value = cpf;
                                const modalCadastrarUsuario = new bootstrap.Modal(document.getElementById('modalCadastrarUsuario'));
                                modalCadastrarUsuario.show();
                            };
                            
                            // Adicionar o botão ao final do div de usuário não encontrado
                            divNaoEncontrado.appendChild(btnCadastrarUsuario);
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar usuário:', error);
                        alert('Ocorreu um erro ao buscar o usuário. Por favor, tente novamente.');
                    });
                });
            }
        });
        
        /**
         * Função para validar CPF
         */
        function validarCPF(cpf) {
            return cpf.length === 11; // Verifica se o CPF tem exatamente 11 dígitos
        }

        /* ===================== Responsável Legal ===================== */
        document.getElementById('cpfLegal').addEventListener('input', function(event) {
            var value = event.target.value;
            event.target.value = value.replace(/[^0-9]/g, '').slice(0, 11); // Remove caracteres não numéricos
            
            // Se tiver 11 dígitos, consulta a API para preencher o nome automaticamente
            if (value.length === 11) {
                consultarCPF(value, 'legal');
            }
        });

        document.getElementById('buscarCpfLegal').addEventListener('click', function() {
            var cpf = document.getElementById('cpfLegal').value;
            if (validarCPF(cpf)) {
                // Primeiro verifica se o CPF já existe como responsável legal
                fetch('verificar_cpf.php?cpf=' + cpf + '&tipo=legal')
                    .then(response => response.json())
                    .then(data => {
                        if (data.existe) {
                            document.getElementById('nomeLegal').value = data.nome;
                            document.getElementById('emailLegal').value = data.email;
                            document.getElementById('telefoneLegal').value = data.telefone;
                            document.getElementById('documento_identificacaoLegal').required = false;
                            document.getElementById('legalFields').style.display = 'none';
                            document.getElementById('alertLegal').style.display = 'block';
                        } else {
                            // Se não existe, consulta a API para preencher o nome
                            consultarCPF(cpf, 'legal');
                            document.getElementById('legalFields').style.display = 'block';
                            document.getElementById('documento_identificacaoLegal').required = true;
                            document.getElementById('alertLegal').style.display = 'none';
                        }
                        toggleRequiredFields('legalFields', !data.existe);
                        document.getElementById('btnAddLegal').style.display = 'block';
                    });
            } else {
                alert('O CPF deve conter 11 dígitos.');
            }
        });

        /* ===================== Responsável Técnico ===================== */
        document.getElementById('cpfTecnico').addEventListener('input', function(event) {
            var value = event.target.value;
            event.target.value = value.replace(/[^0-9]/g, '').slice(0, 11); // Remove caracteres não numéricos
            
            // Se tiver 11 dígitos, consulta a API para preencher o nome automaticamente
            if (value.length === 11) {
                consultarCPF(value, 'tecnico');
            }
        });

        document.getElementById('buscarCpfTecnico').addEventListener('click', function() {
            var cpf = document.getElementById('cpfTecnico').value;
            if (validarCPF(cpf)) {
                // Primeiro verifica se o CPF já existe como responsável técnico
                fetch('verificar_cpf.php?cpf=' + cpf + '&tipo=tecnico')
                    .then(response => response.json())
                    .then(data => {
                        if (data.existe) {
                            document.getElementById('nomeTecnico').value = data.nome;
                            document.getElementById('emailTecnico').value = data.email;
                            document.getElementById('telefoneTecnico').value = data.telefone;
                            document.getElementById('conselho').value = data.conselho;
                            document.getElementById('numero_registro_conselho').value = data.numero_registro_conselho;
                            document.getElementById('carteirinha_conselho').required = false;
                            document.getElementById('tecnicoFields').style.display = 'none';
                            document.getElementById('alertTecnico').style.display = 'block';
                        } else {
                            // Se não existe, consulta a API para preencher o nome
                            consultarCPF(cpf, 'tecnico');
                            document.getElementById('tecnicoFields').style.display = 'block';
                            document.getElementById('carteirinha_conselho').required = true;
                            document.getElementById('alertTecnico').style.display = 'none';
                        }
                        toggleRequiredFields('tecnicoFields', !data.existe);
                        document.getElementById('btnAddTecnico').style.display = 'block';
                    });
            } else {
                alert('O CPF deve conter 11 dígitos.');
            }
        });

        // Função para consultar CPF na API e preencher o nome automaticamente
        function consultarCPF(cpf, tipo) {
            fetch('../../api/consulta_cpf.php?cpf=' + cpf)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Preenche o nome automaticamente em maiúsculo
                        if (tipo === 'legal') {
                            document.getElementById('nomeLegal').value = data.nome;
                        } else if (tipo === 'tecnico') {
                            document.getElementById('nomeTecnico').value = data.nome;
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao consultar CPF na API:', error);
                });
        }

        // Alterna atributos "required" nos campos do container
        function toggleRequiredFields(containerId, isRequired) {
            var container = document.getElementById(containerId);
            var fields = container.querySelectorAll('input, select');
            fields.forEach(function(field) {
                if (isRequired) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                }
            });
        }

        /* ===================== Editar / Excluir Responsáveis ===================== */
        $('#modalEditarResponsavelLegal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var nome = button.data('nome');
            var cpf = button.data('cpf');
            var email = button.data('email');
            var telefone = button.data('telefone');
            var documento = button.data('documento');

            var modal = $(this);
            modal.find('#editLegalId').val(id);
            modal.find('#editLegalNome').val(nome);
            modal.find('#editLegalCpf').val(cpf);
            modal.find('#editLegalEmail').val(email);
            modal.find('#editLegalTelefone').val(telefone);
            modal.find('#editLegalDocumentoAtual').val(documento);
            if (documento) {
                modal.find('#documentoAtualText').text('Documento Atual: ' + documento);
            } else {
                modal.find('#documentoAtualText').text('');
            }
        });

        $('#modalExcluirResponsavelLegal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var modal = $(this);
            modal.find('#deleteLegalId').val(id);
        });

        $('#modalEditarResponsavelTecnico').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var nome = button.data('nome');
            var cpf = button.data('cpf');
            var email = button.data('email');
            var telefone = button.data('telefone');
            var conselho = button.data('conselho');
            var numeroRegistro = button.data('numero-registro');
            var carteirinha = button.data('carteirinha');

            var modal = $(this);
            modal.find('#editTecnicoId').val(id);
            modal.find('#editTecnicoNome').val(nome);
            modal.find('#editTecnicoCpf').val(cpf);
            modal.find('#editTecnicoEmail').val(email);
            modal.find('#editTecnicoTelefone').val(telefone);
            modal.find('#editTecnicoConselho').val(conselho);
            modal.find('#editTecnicoNumeroRegistro').val(numeroRegistro);
            modal.find('#editTecnicoCarteirinhaAtual').val(carteirinha);

            if (carteirinha) {
                modal.find('#carteirinhaAtualText').text('Carteirinha Atual: ' + carteirinha);
            } else {
                modal.find('#carteirinhaAtualText').text('');
            }
        });

        $('#modalExcluirResponsavelTecnico').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var modal = $(this);
            modal.find('#deleteTecnicoId').val(id);
        });

        /* ===================== CNAEs ===================== */
        function searchCNAEModal() {
            let cnae_code = $('#cnae_search_modal').val().trim();
            if (cnae_code.length === 7) {
                $.ajax({
                    url: '../Company/api.php',
                    type: 'GET',
                    data: {
                        cnae: cnae_code
                    },
                    success: function(response) {
                        $('#cnae_result_modal').html(response);
                    },
                    error: function() {
                        $('#cnae_result_modal').html('<div class="alert alert-danger">Erro ao consultar o CNAE. Tente novamente.</div>');
                    }
                });
            } else {
                $('#cnae_result_modal').html('<div class="alert alert-warning">Digite um código CNAE válido com 7 dígitos.</div>');
            }
        }

        function addCNAE(cnaeId, cnaeDesc) {
            let cnaesList = document.getElementById('cnaes_list_modal');
            let cnaesField = document.getElementById('cnaes');
            let currentCnaes = cnaesField.value ? JSON.parse(cnaesField.value) : [];

            // Verifica se o CNAE já foi adicionado
            if (currentCnaes.some(cnae => cnae.id === cnaeId)) {
                alert('Este CNAE já foi adicionado.');
                return;
            }

            // Cria elemento na lista
            let cnaeItem = document.createElement('li');
            cnaeItem.className = 'list-group-item';
            cnaeItem.innerHTML = `${cnaeId} - ${cnaeDesc}
        <button class="btn btn-danger btn-sm float-end" onclick="removeCNAE(this, '${cnaeId}')">
            Remover
        </button>`;
            cnaesList.appendChild(cnaeItem);

            // Adiciona no array
            currentCnaes.push({
                id: cnaeId,
                descricao: cnaeDesc
            });
            cnaesField.value = JSON.stringify(currentCnaes);
        }

        function removeCNAE(element, cnaeId) {
            element.parentElement.remove();
            let cnaesField = document.getElementById('cnaes');
            if (!cnaesField) {
                alert('Campo de CNAEs não encontrado!');
                return;
            }
            let currentCnaes = JSON.parse(cnaesField.value);
            cnaesField.value = JSON.stringify(currentCnaes.filter(cnae => cnae.id !== cnaeId));
        }

        function saveCNAEs() {
            let estabelecimentoId = "<?php echo $estabelecimentoId; ?>";
            let cnaesField = document.getElementById('cnaes');
            let cnaes = cnaesField.value ? JSON.parse(cnaesField.value) : [];

            $.ajax({
                url: '../../controllers/EstabelecimentoController.php?action=updateCnaes',
                type: 'POST',
                data: {
                    estabelecimento_id: estabelecimentoId,
                    cnaes: JSON.stringify(cnaes)
                },
                success: function(response) {
                    try {
                        let res = JSON.parse(response);
                        if (res.success) {
                            alert('CNAEs atualizados com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro: ' + res.error);
                        }
                    } catch (e) {
                        console.error("Resposta inválida", response);
                        alert('Erro inesperado: ' + response);
                    }
                }
            });
        }
    </script>

    <?php include '../footer.php'; ?>
</body>

</html>