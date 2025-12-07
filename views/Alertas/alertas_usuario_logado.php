<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../controllers/AlertaController.php';
require_once '../../models/Processo.php';
require_once '../../models/LogVisualizacao.php';

$municipioUsuario = $_SESSION['user']['municipio'];
$processoModel = new Processo($conn);
$alertas = $processoModel->getTodosAlertas($municipioUsuario);

$usuario_id = $_SESSION['user']['id'];
$alertaController = new AlertaController($conn);
$assinaturasPendentes = $alertaController->getAssinaturasPendentes($usuario_id);
$assinaturasRascunho = $alertaController->getAssinaturasRascunho($usuario_id);
$processosDesignadosPendentes = $alertaController->getProcessosDesignadosPendentes($usuario_id);
$processosPendentes = $processoModel->getProcessosComDocumentacaoPendente($municipioUsuario);

// Ordenar processos pendentes do mais antigo para o mais recente
usort($processosPendentes, function ($a, $b) {
    return strtotime($a['data_upload_pendente']) - strtotime($b['data_upload_pendente']);
});

$logVisualizacaoModel = new LogVisualizacao($conn);

// Processar a marcação como resolvido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_resolvido'])) {
    $processo_id = $_POST['processo_id'];
    $alertaController->marcarProcessoComoResolvido($processo_id, $usuario_id);
    header("Location: alertas_usuario_logado.php");
    exit();
}

include '../header.php';

// Combinar todos os alertas em uma única lista
$todosAlertas = array_merge(
    array_map(function ($assinatura) {
        return [
            'tipo' => 'Assinatura Pendente',
            'descricao' => $assinatura['tipo_documento'],
            'data' => $assinatura['data_upload'],
            'processo_id' => $assinatura['processo_id'],
            'estabelecimento_id' => $assinatura['estabelecimento_id'],
            'arquivo_id' => $assinatura['arquivo_id'],
            'acao' => 'Assinar Documento'
        ];
    }, $assinaturasPendentes),
    array_map(function ($assinatura) {
        return [
            'tipo' => 'Documento Rascunho a Finalizar',
            'descricao' => $assinatura['tipo_documento'],
            'data' => $assinatura['data_upload'],
            'processo_id' => $assinatura['processo_id'],
            'estabelecimento_id' => $assinatura['estabelecimento_id'],
            'acao' => 'Finalizar Documento'
        ];
    }, $assinaturasRascunho),
    array_map(function ($processo) {
        return [
            'tipo' => 'Processo com Documentação Pendente',
            'descricao' => 'Processo #' . $processo['numero_processo'] . ' - ' . $processo['nome_fantasia'],
            'data' => $processo['data_upload_pendente'],
            'processo_id' => $processo['processo_id'],
            'estabelecimento_id' => $processo['estabelecimento_id'],
            'acao' => 'Ver Processo'
        ];
    }, $processosPendentes),
    array_map(function ($alerta) {
        return [
            'tipo' => 'Alerta Pendente',
            'descricao' => $alerta['descricao'],
            'data' => $alerta['prazo'],
            'processo_id' => $alerta['processo_id'],
            'estabelecimento_id' => $alerta['estabelecimento_id'],
            'acao' => 'Ver Processo'
        ];
    }, $alertas),
    array_map(function ($processo) {
        return [
            'tipo' => 'Processo Designado Pendente',
            'descricao' => $processo['descricao'],
            'data' => null,
            'processo_id' => $processo['processo_id'],
            'estabelecimento_id' => $processo['estabelecimento_id'],
            'acao' => 'Ver Processo'
        ];
    }, $processosDesignadosPendentes)
);

// Agrupar alertas por tipo
$agrupados = [];
foreach ($todosAlertas as $alerta) {
    $agrupados[$alerta['tipo']][] = $alerta;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-3 py-6 mt-4">
        <div class="mb-6">
            <h1 class="text-xl font-medium text-gray-800 flex items-center">
                <i class="fas fa-bell text-blue-500 mr-2"></i> Alertas Pendentes
            </h1>
        </div>

        <?php if (!empty($todosAlertas)) : ?>
            <?php 
            // Tipos e cores para ícones
            $typeIcons = [
                'Assinatura Pendente' => 'file-signature',
                'Documento Rascunho a Finalizar' => 'file-alt',
                'Processo com Documentação Pendente' => 'folder-open',
                'Alerta Pendente' => 'bell',
                'Processo Designado Pendente' => 'tasks'
            ];
            
            // Ordenar alertas por tipo e depois por data
            usort($todosAlertas, function($a, $b) {
                if ($a['tipo'] === $b['tipo']) {
                    if (empty($a['data']) && empty($b['data'])) return 0;
                    if (empty($a['data'])) return 1;
                    if (empty($b['data'])) return -1;
                    return strtotime($a['data']) - strtotime($b['data']);
                }
                return strcmp($a['tipo'], $b['tipo']);
            });
            
            $currentType = '';
            ?>
            
            <div class="bg-white rounded-md shadow-sm overflow-hidden">
                <?php foreach ($todosAlertas as $index => $alerta) : ?>
                    <?php
                    // Adiciona cabeçalho de seção quando muda o tipo
                    if ($currentType !== $alerta['tipo']) {
                        $currentType = $alerta['tipo'];
                        if ($index > 0) echo '<hr class="border-gray-100">'; // Divisor entre grupos (exceto antes do primeiro)
                        echo '<div class="px-4 py-2 bg-gray-50 flex items-center justify-between">';
                        echo '<span class="text-sm text-gray-600 font-medium">' . htmlspecialchars($currentType) . '</span>';
                        echo '<span class="px-1.5 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-700">' . 
                             count(array_filter($todosAlertas, function($a) use ($currentType) { return $a['tipo'] === $currentType; })) . 
                             '</span>';
                        echo '</div>';
                    }
                    
                    $diasPendentes = $alerta['data'] ?
                        floor((strtotime($alerta['data']) - time()) / (60 * 60 * 24)) : null;
                        
                    $borderLeftClass = '';
                    $statusClass = '';
                    $statusText = '';
                    
                    if ($diasPendentes !== null) {
                        if ($diasPendentes < 0) {
                            $borderLeftClass = 'border-l-2 border-red-400';
                            $statusClass = 'text-red-500';
                            $statusText = 'Vencido';
                        } elseif ($diasPendentes <= 3) {
                            $borderLeftClass = 'border-l-2 border-amber-400';
                            $statusClass = 'text-amber-500';
                            $statusText = 'Urgente';
                        }
                    }
                    
                    $icon = isset($typeIcons[$alerta['tipo']]) ? $typeIcons[$alerta['tipo']] : 'file';
                    ?>
                    
                    <div class="group px-4 py-3 hover:bg-gray-50 <?php echo $borderLeftClass; ?> transition-all duration-150">
                        <div class="flex items-center gap-x-4">
                            <!-- Ícone e conteúdo -->                           
                            <div class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 text-gray-500 group-hover:bg-blue-50 group-hover:text-blue-500 transition-colors duration-150">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            
                            <div class="min-w-0 flex-auto">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars($alerta['descricao']); ?>
                                </p>
                                
                                <?php if ($alerta['data']) : ?>
                                    <div class="mt-1 flex items-center text-xs text-gray-500 space-x-2">
                                        <span><?php echo date("d/m/Y", strtotime($alerta['data'])); ?></span>
                                        <?php if ($statusText) : ?>
                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 <?php echo $statusClass; ?> bg-gray-50 text-xs font-medium">
                                                <?php echo $statusText; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Botões de ação -->
                            <div class="flex-shrink-0 ml-auto">
                                <?php if ($alerta['tipo'] === 'Processo Designado Pendente') : ?>
                                    <div class="inline-flex">
                                        <a href="../Processo/documentos.php?processo_id=<?php echo $alerta['processo_id']; ?>&id=<?php echo $alerta['estabelecimento_id']; ?>" 
                                           class="inline-flex mr-2 items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-colors duration-150" 
                                           title="<?php echo htmlspecialchars($alerta['acao']); ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="processo_id" value="<?php echo $alerta['processo_id']; ?>">
                                            <button type="submit" name="marcar_resolvido" 
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-green-500 hover:bg-green-50 transition-colors duration-150"
                                                    title="Finalizar">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else : ?>
                                    <a href="../Processo/documentos.php?processo_id=<?php echo $alerta['processo_id']; ?>&id=<?php echo $alerta['estabelecimento_id']; ?>" 
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-colors duration-150"
                                       title="<?php echo htmlspecialchars($alerta['acao']); ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="bg-white rounded-md shadow-sm p-4 text-center text-gray-500">
                <i class="fas fa-check-circle text-blue-400 mr-2"></i> Nenhum alerta pendente no momento.
            </div>
        <?php endif; ?>
    </div>
</body>

</html>