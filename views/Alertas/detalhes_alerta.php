<?php
session_start();
ob_start();
include '../header.php';
require_once '../../conf/database.php';
require_once '../../models/Processo.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['alerta_id'])) {
    echo "<div class='alert alert-danger'>ID do alerta não fornecido.</div>";
    exit();
}

$alerta_id = $_GET['alerta_id'];
$processoModel = new Processo($conn);

// Finalizar o alerta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_alerta'])) {
    $processoModel->updateAlerta($alerta_id, null, null, 'finalizado');
    header("Location: ../Dashboard/dashboard.php");
    exit();
}

$alerta = $processoModel->getAlertaById($alerta_id);

if (!$alerta) {
    echo "<div class='alert alert-danger'>Alerta não encontrado.</div>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Alerta</title>
    
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-3 py-6 mt-4">
        <!-- Breadcrumb navigation -->
        <nav class="flex mb-5" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="../Dashboard/dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <i class="fas fa-home mr-2"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2 text-sm"></i>
                        <span class="text-sm font-medium text-gray-500">Detalhes do Alerta</span>
                    </div>
                </li>
            </ol>
        </nav>
        
        <!-- Alert card -->
        <div class="alert-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                    Detalhes do Alerta
                </h1>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $alerta['status'] === 'finalizado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800 pulse-animation'; ?>">
                    <i class="<?php echo $alerta['status'] === 'finalizado' ? 'fas fa-check-circle mr-1' : 'fas fa-clock mr-1'; ?>"></i>
                    <?php echo htmlspecialchars(ucfirst($alerta['status'])); ?>
                </span>
            </div>
            
            <!-- Content -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left column -->
                    <div class="space-y-4">
                        <div class="detail-item p-3 rounded-lg border border-gray-100">
                            <p class="text-sm text-gray-500 mb-1">Estabelecimento</p>
                            <div class="flex items-center">
                                <i class="fas fa-building text-blue-500 mr-3"></i>
                                <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($alerta['nome_fantasia']); ?></p>
                            </div>
                        </div>
                        
                        <div class="detail-item p-3 rounded-lg border border-gray-100">
                            <p class="text-sm text-gray-500 mb-1">Número do Processo</p>
                            <div class="flex items-center">
                                <i class="fas fa-file-alt text-blue-500 mr-3"></i>
                                <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($alerta['numero_processo']); ?></p>
                            </div>
                        </div>
                        
                        <div class="detail-item p-3 rounded-lg border border-gray-100">
                            <p class="text-sm text-gray-500 mb-1">Tipo de Processo</p>
                            <div class="flex items-center">
                                <i class="fas fa-tasks text-blue-500 mr-3"></i>
                                <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($alerta['tipo_processo']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right column -->
                    <div class="space-y-4">
                        <div class="detail-item p-3 rounded-lg border border-gray-100">
                            <p class="text-sm text-gray-500 mb-1">Descrição</p>
                            <div class="flex items-start">
                                <i class="fas fa-align-left text-blue-500 mr-3 mt-1"></i>
                                <p class="text-gray-800"><?php echo htmlspecialchars($alerta['descricao']); ?></p>
                            </div>
                        </div>
                        
                        <div class="detail-item p-3 rounded-lg border border-gray-100">
                            <p class="text-sm text-gray-500 mb-1">Prazo</p>
                            <div class="flex items-center">
                                <i class="fas fa-calendar-day text-<?php echo strtotime($alerta['prazo']) < time() ? 'red' : 'blue'; ?>-500 mr-3"></i>
                                <p class="text-gray-800 font-medium <?php echo strtotime($alerta['prazo']) < time() ? 'text-red-600' : ''; ?>">
                                    <?php echo htmlspecialchars(date('d/m/Y', strtotime($alerta['prazo']))); ?>
                                    <?php if(strtotime($alerta['prazo']) < time() && $alerta['status'] !== 'finalizado'): ?>
                                        <span class="text-red-600 text-sm ml-2">(Vencido)</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="detail-item p-3 rounded-lg border border-gray-100 bg-gray-50">
                            <p class="text-sm text-gray-500 mb-1">Dias restantes</p>
                            <div class="flex items-center">
                                <i class="fas fa-hourglass-half text-blue-500 mr-3"></i>
                                <?php 
                                $hoje = new DateTime();
                                $prazo = new DateTime($alerta['prazo']);
                                $diff = $hoje->diff($prazo);
                                $diasRestantes = $prazo > $hoje ? $diff->days : -$diff->days;
                                $cor = $diasRestantes > 5 ? 'green' : ($diasRestantes >= 0 ? 'yellow' : 'red');
                                ?>
                                <p class="text-<?php echo $cor; ?>-600 font-medium">
                                    <?php if($alerta['status'] === 'finalizado'): ?>
                                        <span class="text-green-600">Alerta finalizado</span>
                                    <?php else: ?>
                                        <?php echo $diasRestantes > 0 ? $diasRestantes . ' dias restantes' : ($diasRestantes == 0 ? 'Vence hoje' : abs($diasRestantes) . ' dias atrasado'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer with actions -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-wrap justify-end gap-3">
                <?php if($alerta['status'] !== 'finalizado'): ?>
                <form method="POST" class="inline-block"
                    onsubmit="return confirm('Deseja realmente finalizar este alerta?');">
                    <button type="submit" name="finalizar_alerta" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors duration-300 flex items-center shadow-sm">
                        <i class="fas fa-check mr-2"></i> Finalizar Alerta
                    </button>
                </form>
                <?php endif; ?>
                
                <a href="../Processo/documentos.php?processo_id=<?php echo $alerta['processo_id']; ?>&id=<?php echo $alerta['estabelecimento_id']; ?>"
                   class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-300 flex items-center shadow-sm">
                    <i class="fas fa-folder mr-2"></i> Ver Processo
                </a>
                
                <a href="../Dashboard/dashboard.php" 
                   class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors duration-300 flex items-center shadow-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Voltar
                </a>
            </div>
        </div>
    </div>
</body>

</html>

<?php
include '../footer.php';
ob_end_flush();
?>