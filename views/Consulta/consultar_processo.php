<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Processo.php';

$estabelecimentoModel = new Estabelecimento($conn);
$processoModel = new Processo($conn);

$searchTerm = '';
$processoInfo = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $searchTerm = $_POST['searchTerm'];
    
    if (is_numeric($searchTerm)) {
        // Buscar pelo número do processo
        $processoInfo = $processoModel->getProcessoByNumero($searchTerm);
    } else {
        // Buscar pelo CNPJ
        $processoInfo = $processoModel->getProcessoByCnpj($searchTerm);
    }
}

?>

<div class="container mt-5">
    <h2>Consultar Andamento do Processo</h2>
    <form method="POST" action="consultar_processo.php">
        <div class="input-group mb-3">
            <input type="text" class="form-control" id="searchTerm" name="searchTerm" placeholder="Digite o CNPJ" value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button class="btn btn-primary" type="submit">Buscar</button>
        </div>
    </form>

    <?php if (!empty($processoInfo)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Informações do Processo</h5>
            </div>
            <div class="card-body">
                <p><strong>Nome do Estabelecimento:</strong> <?php echo htmlspecialchars($processoInfo['nome_fantasia']); ?></p>
                <p><strong>CNPJ:</strong> <?php echo htmlspecialchars($processoInfo['cnpj']); ?></p>
                <p><strong>Tipo do Processo:</strong> <?php echo htmlspecialchars($processoInfo['tipo_processo']); ?></p>
                <p><strong>Número do Processo:</strong> <?php echo htmlspecialchars($processoInfo['numero_processo']); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($processoInfo['status'] === 'ATIVO' ? 'ANDAMENTO' : $processoInfo['status']); ?></p>
                <?php if (!empty($processoInfo['motivo_parado'])): ?>
                    <p><strong>Motivo do Parado:</strong> <?php echo htmlspecialchars($processoInfo['motivo_parado']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
        <div class="alert alert-danger" role="alert">
            Nenhum processo encontrado para o CNPJ fornecido.
        </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
include '../footer.php';
?>
<script src="https://unpkg.com/imask"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchTermInput = document.getElementById('searchTerm');
    var maskOptions = {
        mask: '00.000.000/0000-00'
    };
    var mask = IMask(searchTermInput, maskOptions);
});
</script>