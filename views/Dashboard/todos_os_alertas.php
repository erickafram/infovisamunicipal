<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';
require_once '../../models/Processo.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado
    exit();
}

$municipioUsuario = $_SESSION['user']['municipio']; // Obtendo o município do usuário logado
$processoModel = new Processo($conn);

// Buscar todos os alertas próximos de vencer
$alertasProximosAVencer = $processoModel->getAlertasProximosAVencer($municipioUsuario);

?>

<div class="container mt-4">
    <h2>Todos os Alertas Próximos a Vencer</h2>
    <div class="card">
        <div class="card-body">
            <?php if (empty($alertasProximosAVencer)) : ?>
                <p class="card-text">Não há alertas próximos a vencer no momento.</p>
            <?php else : ?>
                <ul class="list-group">
                    <?php foreach ($alertasProximosAVencer as $alerta) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div style="font-size:12px;">
                                <strong>Nome Estabelecimento:</strong> <?php echo htmlspecialchars($alerta['nome_fantasia'] ?? ''); ?><br>
                                <strong>Prazo:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($alerta['prazo']))); ?><br>
                                <strong>Vencimento:</strong>
                                <?php
                                $diasRestantes = $alerta['dias_restantes'] ?? 0;
                                if ($diasRestantes > 0) {
                                    echo "Faltam $diasRestantes dias para o vencimento.";
                                } elseif ($diasRestantes == 0) {
                                    echo "Alerta vence hoje!";
                                } else {
                                    echo "Vencido há " . abs($diasRestantes) . " dias.";
                                }
                                ?>
                            </div>
                            <div>
                                <a href="../Alertas/detalhes_alerta.php?alerta_id=<?php echo htmlspecialchars($alerta['id'] ?? ''); ?>" class="text-warning"><i class="far fa-eye"></i></a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>