<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Assinatura.php';
require_once '../../models/Usuario.php'; // Incluindo o modelo de Usuário

// Verificar se o usuário está autenticado
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

// Instanciar os modelos
$arquivoModel = new Arquivo($conn);
$estabelecimentoModel = new Estabelecimento($conn);
$assinaturaModel = new Assinatura($conn);
$usuarioModel = new Usuario($conn);

if (isset($_GET['arquivo_id']) && isset($_GET['processo_id']) && isset($_GET['estabelecimento_id'])) {
    $arquivo_id = $_GET['arquivo_id'];
    $processo_id = $_GET['processo_id'];
    $estabelecimento_id = $_GET['estabelecimento_id'];

    $arquivo = $arquivoModel->getArquivoById($arquivo_id);
    if ($arquivo) {
        // Buscar informações do estabelecimento
        $estabelecimento = $estabelecimentoModel->findById($estabelecimento_id);

        // Buscar todas as assinaturas
        $assinaturas = $assinaturaModel->getAssinaturasPorArquivo($arquivo_id);
        
        // Verificar se todas as assinaturas foram realizadas
        $todas_assinadas = true;
        $tem_assinaturas = !empty($assinaturas);
        
        if ($tem_assinaturas) {
            foreach ($assinaturas as $assinatura) {
                if ($assinatura['status'] !== 'assinado') {
                    $todas_assinadas = false;
                    break;
                }
            }
        } else {
            // Se não há assinaturas, consideramos que não estão todas assinadas
            $todas_assinadas = false;
        }

        // Processar adição de assinaturas, somente se o arquivo não estiver finalizado e não tiver todas as assinaturas
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_assinatura']) && $arquivo['status'] != 'finalizado' && !$todas_assinadas) {
            $usuario_id = $_POST['usuario_id'];
            if (!$assinaturaModel->isAssinaturaExistente($arquivo_id, $usuario_id)) {
                $assinaturaModel->addAssinatura($arquivo_id, $usuario_id);
            }
            header("Location: pre_visualizar_arquivo.php?arquivo_id={$arquivo_id}&processo_id={$processo_id}&estabelecimento_id={$estabelecimento_id}");
            exit();
        }

        // Processar remoção de assinaturas, somente se o arquivo não estiver finalizado
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_assinatura']) && $arquivo['status'] != 'finalizado') {
            $usuario_id = $_POST['usuario_id'];
            if ($usuario_id != $_SESSION['user']['id']) {
                $assinaturaModel->removeAssinatura($arquivo_id, $usuario_id);
            }
            header("Location: pre_visualizar_arquivo.php?arquivo_id={$arquivo_id}&processo_id={$processo_id}&estabelecimento_id={$estabelecimento_id}");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assinar_arquivo'])) {
            $usuario_id = $_SESSION['user']['id'];
            
            // Verificar se a senha digital foi fornecida
            if (isset($_POST['senha_digital'])) {
                $senha_digital = $_POST['senha_digital'];
                
                // Debug - Verificar se o usuário tem senha digital cadastrada
                $usuario_info = $usuarioModel->findById($usuario_id);
                $tem_senha = !empty($usuario_info['senha_digital']);
                
                // Log para debug
                error_log("ID do usuário: " . $usuario_id);
                error_log("Tem senha digital cadastrada: " . ($tem_senha ? "Sim" : "Não"));
                error_log("Senha fornecida: " . $senha_digital);
                
                // Verificar se a senha digital está correta
                $senha_valida = $usuarioModel->verificarSenhaDigital($usuario_id, $senha_digital);
                error_log("Senha válida: " . ($senha_valida ? "Sim" : "Não"));
                
                if ($senha_valida) {
            if ($assinaturaModel->isAssinaturaExistente($arquivo_id, $usuario_id)) {
                $assinaturaModel->addOrUpdateAssinatura($arquivo_id, $usuario_id);
                        $_SESSION['mensagem'] = "Documento assinado com sucesso!";
                        $_SESSION['tipo_mensagem'] = "success";
                        
                        // Redirecionar para atualizar a página após assinatura bem-sucedida
            header("Location: pre_visualizar_arquivo.php?arquivo_id={$arquivo_id}&processo_id={$processo_id}&estabelecimento_id={$estabelecimento_id}");
            exit();
                    }
                } else {
                    // Apenas definir as variáveis de erro sem redirecionar
                    $erro_senha = "Senha digital incorreta. Por favor, tente novamente.";
                    $tipo_erro = "danger";
                }
            }
            
            // Não redirecionar se houver erro - o modal permanecerá aberto
        }

        // Buscar usuários do mesmo município logado
        $usuariosMunicipio = $usuarioModel->getUsuariosPorMunicipio($_SESSION['user']['municipio']);

        include '../header.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualização de Documento - <?php echo htmlspecialchars($arquivo['tipo_documento']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .document-container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 0;
            overflow: hidden;
            margin-bottom: 24px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .document-container:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .document-header {
            background-color: #f8fafc;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
            padding: 12px 16px;
            color: #475569;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }
        
        .document-header i {
            margin-right: 8px;
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .info-table th {
            width: 30%;
            background-color: #f8fafc;
            font-weight: 500;
            color: #475569;
            font-size: 0.75rem;
        }
        
        .info-table td, .info-table th {
            padding: 8px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.75rem;
        }
        
        .info-table tr:last-child td,
        .info-table tr:last-child th {
            border-bottom: none;
        }
        
        .assinatura-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.2s;
        }
        
        .assinatura-item:hover {
            background-color: #f8fafc;
        }
        
        .assinatura-item:last-child {
            border-bottom: none;
        }
        
        .assinatura-info {
            flex-grow: 1;
        }
        
        .assinatura-nome {
            font-weight: 500;
            font-size: 0.875rem;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }
        
        .assinatura-data {
            color: #64748b;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .assinatura-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 6px;
        }
        
        .status-pendente {
            background-color: #fef9c3;
            color: #854d0e;
        }
        
        .status-assinado {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .page-title {
            font-size: 1.125rem;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        
        .breadcrumb-wrapper {
            background-color: transparent;
            padding: 0;
        }
        
        .breadcrumb-custom {
            display: flex;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        
        .breadcrumb-custom .breadcrumb-item {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            color: #64748b;
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: #3b82f6;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .breadcrumb-custom .breadcrumb-item a:hover {
            color: #2563eb;
            text-decoration: underline;
        }
        
        .breadcrumb-custom .breadcrumb-item+.breadcrumb-item::before {
            content: "/";
            padding: 0 6px;
            color: #94a3b8;
        }
        
        .voltar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            background-color: #fff;
            color: #475569;
        }
        
        .voltar-btn:hover {
            background-color: #f8fafc;
            color: #3b82f6;
            border-color: #cbd5e1;
        }
        
        .document-preview-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s;
            background-color: #3b82f6;
            border: none;
            color: white;
        }
        
        .document-preview-button:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-assinar {
            background-color: #22c55e;
            border: none;
            color: white;
        }
        
        .btn-assinar:hover {
            background-color: #16a34a;
            box-shadow: 0 2px 4px rgba(34, 197, 94, 0.2);
        }
        
        .btn-remover {
            background-color: #fff;
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .btn-remover:hover {
            background-color: #fee2e2;
            border-color: #dc2626;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-pendente {
            background-color: #fef9c3;
            color: #854d0e;
        }
        
        .badge-finalizado {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .document-body {
            padding: 12px;
        }
        
        .alert-custom {
            background-color: #f8fafc;
            border-left: 3px solid #3b82f6;
            color: #475569;
            padding: 10px 12px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
        }
        
        .alert-warning-custom {
            background-color: #fff7ed;
            border-left-color: #f97316;
        }
        
        .alert-success-custom {
            background-color: #f0fdf4;
            border-left-color: #22c55e;
        }
        
        .alert-danger-custom {
            background-color: #fef2f2;
            border-left-color: #ef4444;
        }
        
        .select-custom {
            display: block;
            font-size: 0.75rem;
            width: 100%;
            padding: 10px 14px;
            font-size: 0.95rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            background-color: white;
            transition: all 0.2s;
        }
        
        .select-custom:focus {
            border-color: #93c5fd;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-label-custom {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #475569;
            font-size: 0.95rem;
        }
        
        .btn-adicionar {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-adicionar:hover {
            background-color: #2563eb;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
        }
        
        .modal-custom .modal-content {
            border-radius: 12px;
            overflow: hidden;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .modal-custom .modal-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 20px;
        }
        
        .modal-custom .modal-title {
            font-weight: 600;
            color: #334155;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-custom .modal-body {
            padding: 20px;
        }
        
        .modal-custom .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-fechar {
            background-color: #f1f5f9;
            color: #475569;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-fechar:hover {
            background-color: #e2e8f0;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .breadcrumb-wrapper {
                margin-top: 8px;
                width: 100%;
                overflow-x: auto;
            }
            
            .info-table th {
                width: 40%;
            }
            
            .assinatura-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .assinatura-info {
                margin-bottom: 12px;
                width: 100%;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: flex-start;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
            
            .document-header {
                padding: 12px 16px;
                font-size: 0.95rem;
            }
            
            .document-body {
                padding: 16px;
            }
            
            .info-table td, 
            .info-table th {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
            
            .document-container {
                margin-bottom: 16px;
                border-radius: 8px;
            }
            
            .assinatura-nome {
                font-size: 0.95rem;
            }
            
            .assinatura-data {
                font-size: 0.8rem;
            }
            
            .status-badge {
                padding: 4px 10px;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .page-title {
                font-size: 1.2rem;
            }
            
            .voltar-btn {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
            
            .document-preview-button {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
            
            .btn-adicionar,
            .btn-fechar {
                padding: 8px 14px;
                font-size: 0.85rem;
            }
            
            .select-custom {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
            
            .form-label-custom {
                font-size: 0.9rem;
            }
        }
        
        .page-content {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .main-column {
            flex: 1 1 65%;
            min-width: 300px;
        }
        
        .side-column {
            flex: 1 1 30%;
            min-width: 300px;
        }
        
        @media (max-width: 992px) {
            .page-content {
                flex-direction: column;
            }
            
            .main-column, .side-column {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <!-- Cabeçalho da página e navegação -->
        <div class="d-flex justify-content-between align-items-center mb-4 page-header">
            <a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" class="voltar-btn">
                <i class="fas fa-arrow-left"></i> Voltar para Documentos
            </a>
            <nav aria-label="breadcrumb" class="breadcrumb-wrapper">
                <ol class="breadcrumb breadcrumb-custom mb-0">
                    <li class="breadcrumb-item"><a href="../Dashboard/dashboard.php">Início</a></li>
                    <li class="breadcrumb-item"><a href="documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>">Documentos</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($arquivo['tipo_documento']); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Título da página com status do documento -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?php echo htmlspecialchars($arquivo['tipo_documento']); ?></h1>
            <span class="status-badge <?php echo ($arquivo['status'] == 'finalizado') ? 'badge-finalizado' : 'badge-pendente'; ?>">
                <i class="fas <?php echo ($arquivo['status'] == 'finalizado') ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                <?php echo ucfirst(htmlspecialchars($arquivo['status'])); ?>
            </span>
        </div>

        <!-- Layout em duas colunas -->
        <div class="page-content">
            <!-- Coluna Principal -->
            <div class="main-column">
                <!-- Conteúdo do Documento -->
                <div class="document-container">
                    <div class="document-header">
                        <i class="fas fa-file-alt"></i> Conteúdo do Documento
                    </div>
                    <div class="document-body">
                        <button type="button" class="document-preview-button" onclick="openModal('conteudoModal')">
                            <i class="far fa-eye"></i> Visualizar Documento Completo
                        </button>
                    </div>
                </div>

                <!-- Assinaturas -->
                <div class="document-container">
                    <div class="document-header">
                        <i class="fas fa-signature"></i> Assinaturas
                    </div>
                    <div class="document-body p-0">
                        <?php if (!empty($assinaturas)) : ?>
                            <div class="assinaturas-container">
                                <?php foreach ($assinaturas as $assinatura) : ?>
                                    <div class="assinatura-item">
                                        <div class="assinatura-info">
                                            <div class="assinatura-nome">
                                                <i class="fas fa-user-circle text-secondary"></i>
                                                <?php echo htmlspecialchars($assinatura['nome_completo']); ?>
                                            </div>
                                            <div class="assinatura-data">
                                                <i class="far fa-calendar-alt"></i>
                                                <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($assinatura['data_assinatura']))); ?>
                                            </div>
                                            <span class="assinatura-status <?php echo ($assinatura['status'] === 'assinado') ? 'status-assinado' : 'status-pendente'; ?>">
                                                <i class="fas <?php echo ($assinatura['status'] === 'assinado') ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                                <?php echo ucfirst(htmlspecialchars($assinatura['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="action-buttons">
                                            <?php if ($assinatura['usuario_id'] == $_SESSION['user']['id'] && $assinatura['status'] != 'assinado') : ?>
                                                <?php 
                                                // Verificar se o usuário tem senha digital configurada
                                                $usuario_info = $usuarioModel->findById($_SESSION['user']['id']);
                                                $tem_senha_digital = !empty($usuario_info['senha_digital']);
                                                
                                                if ($tem_senha_digital) : 
                                                ?>
                                                                                                <button type="button" class="action-btn btn-assinar" onclick="abrirModalSenhaDigital()">
                                                    <i class="fas fa-pen-alt"></i> Assinar
                                                    </button>
                                                <?php else: ?>
                                                <div class="alert-custom alert-warning-custom" style="margin-bottom: 10px;">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    <div>
                                                        Você precisa configurar sua senha digital para assinar documentos.
                                                    </div>
                                                </div>
                                                <a href="../../views/Usuario/senha_digital.php?redirect=assinatura&arquivo_id=<?php echo $arquivo_id; ?>&processo_id=<?php echo $processo_id; ?>&estabelecimento_id=<?php echo $estabelecimento_id; ?>" class="action-btn btn-assinar">
                                                    <i class="fas fa-key"></i>Senha Digital
                                                </a>
                                                <?php endif; ?>
                                                <div id="mensagemSucesso" style="display: none; color: #16a34a; font-weight: 500;">
                                                    <i class="fas fa-check-circle"></i> Assinado com sucesso!
                                                </div>
                                            <?php elseif ($assinatura['usuario_id'] != $_SESSION['user']['id'] && $assinatura['status'] != 'assinado' && $arquivo['status'] != 'finalizado') : ?>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $assinatura['usuario_id']; ?>">
                                                    <button type="submit" name="remover_assinatura" class="action-btn btn-remover">
                                                        <i class="fas fa-trash-alt"></i> Remover
                                                    </button>
                                                </form>
                                            <?php elseif ($assinatura['status'] === 'assinado') : ?>
                                                <span class="text-success fw-bold"><i class="fas fa-check-circle"></i> Assinado</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="document-body">
                                <div class="alert-custom alert-info-custom">
                                    <i class="fas fa-info-circle fa-lg text-primary"></i>
                                    <div>Não há assinaturas para este documento.</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral -->
            <div class="side-column">
                <!-- Dados da Empresa -->
                <?php if ($estabelecimento) : ?>
                <div class="document-container">
                    <div class="document-header">
                        <i class="fas fa-building"></i> Dados da Empresa
                    </div>
                    <div class="document-body p-0">
                        <table class="info-table">
                            <?php if ($estabelecimento['tipo_pessoa'] == 'fisica'): ?>
                            <tr>
                                <th>Nome</th>
                                <td><?php echo htmlspecialchars($estabelecimento['nome']); ?></td>
                            </tr>
                            <tr>
                                <th>CPF</th>
                                <td><?php echo htmlspecialchars($estabelecimento['cpf']); ?></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <th>Nome Fantasia</th>
                                <td><?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?></td>
                            </tr>
                            <tr>
                                <th>Razão Social</th>
                                <td><?php echo htmlspecialchars($estabelecimento['razao_social']); ?></td>
                            </tr>
                            <tr>
                                <th>CNPJ</th>
                                <td><?php echo htmlspecialchars($estabelecimento['cnpj']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Endereço</th>
                                <td><?php echo htmlspecialchars($estabelecimento['logradouro'] . ', ' . $estabelecimento['numero'] . ', ' . $estabelecimento['bairro'] . ', ' . $estabelecimento['municipio'] . '-' . $estabelecimento['uf']); ?></td>
                            </tr>
                            <tr>
                                <th>CEP</th>
                                <td><?php echo htmlspecialchars($estabelecimento['cep']); ?></td>
                            </tr>
                            <tr>
                                <th>Telefone</th>
                                <td><?php echo htmlspecialchars($estabelecimento['ddd_telefone_1'] . ' / ' . $estabelecimento['ddd_telefone_2']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Adicionar Assinatura -->
                <?php if ($arquivo['status'] != 'finalizado') : ?>
                    <?php if (!$todas_assinadas) : ?>
                    <div class="document-container">
                        <div class="document-header">
                            <i class="fas fa-user-plus"></i> Adicionar Nova Assinatura
                        </div>
                        <div class="document-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="usuario_id" class="form-label-custom">
                                        <i class="fas fa-users"></i> Selecione o Usuário
                                    </label>
                                    <select class="select-custom" id="usuario_id" name="usuario_id" required>
                                        <option value="">-- Selecione um usuário --</option>
                                        <?php foreach ($usuariosMunicipio as $usuario) : ?>
                                            <option value="<?php echo $usuario['id']; ?>"><?php echo htmlspecialchars($usuario['nome_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="adicionar_assinatura" class="btn-adicionar">
                                    <i class="fas fa-plus-circle"></i> Adicionar Assinatura
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="document-container">
                        <div class="document-body">
                            <div class="alert-custom alert-success-custom">
                                <i class="fas fa-check-circle fa-lg text-success"></i>
                                <div>Todas as assinaturas foram concluídas. Não é possível adicionar novas assinaturas.</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="document-container">
                        <div class="document-body">
                            <div class="alert-custom alert-warning-custom">
                                <i class="fas fa-lock fa-lg text-orange"></i>
                                <div>Este documento está finalizado e não pode ser alterado.</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Conteúdo -->
    <div class="modal" id="conteudoModal" style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($arquivo['tipo_documento']); ?>
                    </h5>
                    <button type="button" class="btn-close" onclick="closeModal('conteudoModal')">×</button>
                </div>
                <div class="modal-body" id="conteudoModalBody">
                    <!-- O conteúdo será carregado via JavaScript -->
                    <div class="text-center">
                        <div class="spinner">
                            <span>Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando conteúdo do documento...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-fechar" onclick="closeModal('conteudoModal')">
                        <i class="fas fa-times"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Senha Digital -->
    <div class="modal" id="senhaDigitalModal" style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content modal-custom">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-lock"></i> Senha Digital para Assinatura
                    </h5>
                    <button type="button" class="btn-close" onclick="closeModal('senhaDigitalModal')">×</button>
                </div>
                <div class="modal-body">
                    <?php if (isset($erro_senha)): ?>
                        <div class="alert-custom alert-danger-custom">
                            <i class="fas fa-exclamation-circle"></i>
                            <div><?php echo $erro_senha; ?></div>
                        </div>
                    <?php elseif (isset($_SESSION['mensagem'])): ?>
                        <div class="alert-custom <?php echo $_SESSION['tipo_mensagem'] === 'success' ? 'alert-success-custom' : 'alert-danger-custom'; ?>">
                            <i class="fas <?php echo $_SESSION['tipo_mensagem'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <div><?php echo $_SESSION['mensagem']; ?></div>
                        </div>
                        <?php unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']); ?>
                    <?php endif; ?>
                    
                    <p class="mb-4">
                        Para assinar este documento, por favor digite sua senha digital de 6 dígitos.
                        <br>
                        <small class="text-muted">Esta é a senha numérica configurada especificamente para assinaturas.</small>
                    </p>
                    
                    <form method="POST" action="" id="assinaturaForm">
                        <div class="mb-3">
                            <label for="senha_digital" class="form-label-custom">Senha Digital (6 dígitos)</label>
                            <input type="password" class="select-custom" id="senha_digital" name="senha_digital" 
                                   maxlength="6" pattern="[0-9]{6}" required
                                   placeholder="Digite sua senha digital de 6 dígitos"
                                   <?php echo !$tem_senha_digital ? 'disabled' : ''; ?>>
                        </div>
                        <input type="hidden" name="assinar_arquivo" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-fechar" onclick="closeModal('senhaDigitalModal')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="document-preview-button" style="width: auto;" 
                            onclick="assinarDocumento()" <?php echo !$tem_senha_digital ? 'disabled' : ''; ?>>
                        <i class="fas fa-pen-alt"></i> Confirmar Assinatura
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            if (id === 'conteudoModal') {
            carregarConteudoModal();
            }
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function carregarConteudoModal() {
            setTimeout(function() {
                var conteudoCompleto = `<?php echo addslashes($arquivo['conteudo']); ?>`;
                document.getElementById('conteudoModalBody').innerHTML = conteudoCompleto;
            }, 300); // pequeno delay para mostrar o spinner
        }

        function abrirModalSenhaDigital() {
            <?php 
            // Verificar se o usuário tem senha digital configurada
            $usuario_atual = $usuarioModel->findById($_SESSION['user']['id']);
            $tem_senha_digital = !empty($usuario_atual['senha_digital']);
            
            if (!$tem_senha_digital): ?>
                // Redirecionar para a página de configuração de senha digital com parâmetros
                window.location.href = '../../views/Usuario/senha_digital.php?redirect=assinatura&arquivo_id=<?php echo $arquivo_id; ?>&processo_id=<?php echo $processo_id; ?>&estabelecimento_id=<?php echo $estabelecimento_id; ?>';
            <?php else: ?>
                openModal('senhaDigitalModal');
            <?php endif; ?>
        }
        
        function assinarDocumento() {
            const form = document.getElementById('assinaturaForm');
            const senhaDigital = document.getElementById('senha_digital').value;
            
            if (senhaDigital.length !== 6 || !/^\d+$/.test(senhaDigital)) {
                alert('Por favor, digite uma senha digital válida de 6 dígitos numéricos.');
                return;
            }
            
            // Mostrar indicador de carregamento ou feedback visual
            const btnConfirmar = document.querySelector('.modal-footer .document-preview-button');
            const textoOriginal = btnConfirmar.innerHTML;
            btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btnConfirmar.disabled = true;
            
            // Enviar o formulário via AJAX para evitar recarregar a página quando houver erro
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Se a senha estiver correta, a página será redirecionada automaticamente
                // Se houver erro, atualizamos o conteúdo do modal com a mensagem de erro
                if (html.includes('Senha digital incorreta')) {
                    // Extrair a mensagem de erro do HTML retornado
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Encontrar a mensagem de erro
                    const errorMsg = tempDiv.querySelector('.alert-danger-custom');
                    
                    if (errorMsg) {
                        // Atualizar a mensagem de erro no modal atual
                        const modalBody = document.querySelector('#senhaDigitalModal .modal-body');
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert-custom alert-danger-custom';
                        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><div>Senha digital incorreta. Por favor, tente novamente.</div>';
                        
                        // Remover alertas anteriores
                        const oldAlerts = modalBody.querySelectorAll('.alert-custom');
                        oldAlerts.forEach(alert => alert.remove());
                        
                        // Adicionar o novo alerta no início do modal
                        modalBody.insertBefore(alertDiv, modalBody.firstChild);
                        
                        // Restaurar o botão
                        btnConfirmar.innerHTML = textoOriginal;
                        btnConfirmar.disabled = false;
                        
                        // Limpar o campo de senha e focar nele para nova tentativa
                        document.getElementById('senha_digital').value = '';
                        document.getElementById('senha_digital').focus();
                    } else {
                        // Se não conseguirmos processar a resposta, recarregar a página
                        window.location.reload();
                    }
                } else {
                    // Se não encontrarmos a mensagem de erro, significa que a assinatura foi bem-sucedida
                    // ou houve outro tipo de resposta - recarregar a página
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Erro ao processar assinatura:', error);
                btnConfirmar.innerHTML = textoOriginal;
                btnConfirmar.disabled = false;
                
                // Mostrar mensagem de erro genérica
                const modalBody = document.querySelector('#senhaDigitalModal .modal-body');
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert-custom alert-danger-custom';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><div>Ocorreu um erro ao processar a assinatura. Por favor, tente novamente.</div>';
                
                // Remover alertas anteriores
                const oldAlerts = modalBody.querySelectorAll('.alert-custom');
                oldAlerts.forEach(alert => alert.remove());
                
                // Adicionar o novo alerta
                modalBody.insertBefore(alertDiv, modalBody.firstChild);
            });
        }
        
        // Adicionar evento de tecla Enter para o campo de senha digital
        document.addEventListener('DOMContentLoaded', function() {
            const senhaDigitalInput = document.getElementById('senha_digital');
            if (senhaDigitalInput) {
                senhaDigitalInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        assinarDocumento();
                    }
                });
        }
            
            <?php if (isset($_SESSION['mensagem']) || isset($erro_senha)): ?>
                abrirModalSenhaDigital();
            <?php endif; ?>
        });
        
        // Fecha o modal quando o usuário clica fora dele
        window.onclick = function(event) {
            var modals = document.getElementsByClassName('modal');
            for (var i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>
<?php
    } else {
        include '../header.php';
        echo '<div class="container mt-5"><div class="alert-custom alert-danger-custom" role="alert"><i class="fas fa-exclamation-triangle"></i> Arquivo não encontrado.</div>';
        echo '<a href="javascript:history.back()" class="voltar-btn"><i class="fas fa-arrow-left"></i> Voltar</a></div>';
    }
} else {
    include '../header.php';
    echo '<div class="container mt-5"><div class="alert-custom alert-danger-custom" role="alert"><i class="fas fa-exclamation-triangle"></i> ID do arquivo ou outros parâmetros não fornecidos!</div>';
    echo '<a href="javascript:history.back()" class="voltar-btn"><i class="fas fa-arrow-left"></i> Voltar</a></div>';
}
include '../footer.php';
?>