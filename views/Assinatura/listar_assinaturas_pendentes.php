<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Assinatura.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Processo.php';

$assinaturaModel = new Assinatura($conn);
$arquivoModel = new Arquivo($conn);
$estabelecimentoModel = new Estabelecimento($conn); 
$processoModel = new Processo($conn);
$user_id = $_SESSION['user']['id'];

$assinaturasPendentes = $assinaturaModel->getAssinaturasPendentes($user_id);

// Obter informações completas para cada assinatura
foreach ($assinaturasPendentes as $key => $assinatura) {
    if (isset($assinatura['arquivo_id'])) {
        $arquivo = $arquivoModel->getArquivoById($assinatura['arquivo_id']);
        $assinaturasPendentes[$key]['arquivo'] = $arquivo;
        
        // Adicionar informações do estabelecimento e processo se disponíveis
        if (isset($assinatura['estabelecimento_id'])) {
            $estabelecimento = $estabelecimentoModel->findById($assinatura['estabelecimento_id']);
            $assinaturasPendentes[$key]['estabelecimento'] = $estabelecimento;
        }
        
        if (isset($assinatura['processo_id'])) {
            $processo = $processoModel->getProcessoById($assinatura['processo_id']);
            $assinaturasPendentes[$key]['processo'] = $processo;
        }
    }
}

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas Pendentes</title>
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="InfoVisa">
    <link rel="manifest" href="/visamunicipal/manifest.json">
    <link rel="apple-touch-icon" href="/visamunicipal/assets/images/icon-192x192.png">
    
    <style>
        /* Estilos para cards de documento e botões de ação */
        .document-action-btn {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .sign-btn {
            background-color: #e9d5ff;
            color: #7e22ce;
        }
        
        .sign-btn:hover {
            background-color: #d8b4fe;
        }
        
        /* Feedback de assinatura */
        .success-banner {
            background-color: #dcfce7;
            border-left: 4px solid #16a34a;
            color: #166534;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
        }
        
        .success-banner i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }
        
        .document-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .document-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .assinado-badge {
            background-color: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Cabeçalho da página -->
        <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-blue-500 rounded-xl shadow-lg p-6 mb-8 text-white transform hover:scale-[1.01] transition-all duration-300 ease-in-out border border-blue-400/30 backdrop-blur-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold mb-2 flex items-center">
                        <i class="fas fa-signature mr-3"></i>
                        <span>Assinaturas Pendentes</span>
                    </h1>
                    <p class="text-white/90 text-sm">
                        Documentos que aguardam sua assinatura para prosseguir no trâmite.
                    </p>
                </div>
                <div class="hidden md:block">
                    <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm shadow-inner border border-white/20">
                        <i class="fas fa-pen-fancy text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conteúdo principal -->
        <div class="bg-white rounded-xl shadow-md mb-6 overflow-hidden border border-gray-100">
            <!-- Contador e filtros -->
            <div class="bg-gradient-to-r from-violet-50 to-white p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center">
                    <div class="bg-violet-100 text-violet-700 rounded-full h-8 w-8 flex items-center justify-center mr-3">
                        <span class="font-semibold"><?php echo count($assinaturasPendentes); ?></span>
                    </div>
                    <h2 class="font-semibold text-gray-700">
                        <?php echo count($assinaturasPendentes) == 1 ? 'Documento pendente' : 'Documentos pendentes'; ?>
                    </h2>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="../Dashboard/dashboard.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-md text-sm hover:bg-gray-200 transition-colors duration-200 flex items-center">
                        <i class="fas fa-arrow-left mr-1.5 text-xs"></i>
                        <span>Voltar para Dashboard</span>
                    </a>
                </div>
            </div>

            <!-- Lista de assinaturas -->
            <div class="p-4">
                <?php if (!empty($assinaturasPendentes)) : ?>
                    <div class="space-y-4">
                        <?php foreach ($assinaturasPendentes as $index => $assinatura) : ?>
                            <div class="document-card">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($assinatura['nome_documento'] ?? $assinatura['tipo_documento'] ?? 'Documento'); ?>
                                        </h3>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <i class="far fa-calendar-alt mr-1"></i>
                                            <?php 
                                                $data = isset($assinatura['data_criacao']) ? $assinatura['data_criacao'] : 
                                                    (isset($assinatura['data_assinatura']) ? $assinatura['data_assinatura'] : date('Y-m-d H:i:s'));
                                                echo htmlspecialchars(date('d/m/Y H:i', strtotime($data))); 
                                            ?>
                                        </div>
                                        <?php if (isset($assinatura['processo']) && isset($assinatura['processo']['numero_processo'])): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-file-alt mr-1"></i>
                                            Processo: <?php echo htmlspecialchars($assinatura['processo']['numero_processo']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center mt-3 sm:mt-0">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 mr-2">
                                            <i class="fas fa-clock mr-1"></i> Pendente
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Botões de ação -->
                                <div class="flex justify-end space-x-2 mt-2">
                                    <a href="../Processo/pre_visualizar_arquivo.php?arquivo_id=<?php echo htmlspecialchars($assinatura['arquivo_id'] ?? ''); ?>&processo_id=<?php echo htmlspecialchars($assinatura['processo_id'] ?? ''); ?>&estabelecimento_id=<?php echo htmlspecialchars($assinatura['estabelecimento_id'] ?? ''); ?>" class="document-action-btn sign-btn">
                                        <i class="fas fa-signature mr-1"></i> Assinar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="text-center py-8">
                        <div class="inline-flex justify-center items-center w-14 h-14 rounded-full bg-violet-100 mb-4">
                            <i class="fas fa-check text-violet-600 text-xl"></i>
                        </div>
                        <h3 class="mb-1 text-lg font-semibold text-gray-900">Nenhuma assinatura pendente</h3>
                        <p class="text-gray-500">Você não possui documentos aguardando assinatura.</p>
                        <div class="mt-6">
                            <a href="../Dashboard/dashboard.php" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-200 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center mr-2 transition-all duration-200">
                                <i class="fas fa-home mr-2"></i>
                                Voltar para Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Rodapé com informações adicionais -->
    <footer class="py-4 text-center text-gray-500 text-sm mb-10">
        <p>&copy; <?php echo date('Y'); ?> InfoVisa - Documentos e assinaturas digitais seguras</p>
    </footer>
</body>
</html>
