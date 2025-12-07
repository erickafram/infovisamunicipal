<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';

$processo = new Processo($conn);

$municipioUsuario = $_SESSION['user']['municipio'];
$isAdmin = $_SESSION['user']['nivel_acesso'] == 1;

// Buscar todos os processos com documentação pendente
$processosPendentes = $processo->getProcessosComDocumentacaoPendente($municipioUsuario);

function formatDate($date)
{
    $dateTime = new DateTime($date);
    return $dateTime->format('d/m/Y');
}
?>

<style>
    th {
        font-size: 13px;
    }

    td {
        font-size: 14px;
    }
</style>

<div class="container mt-5">
    <div class="card-header">
        <h5 class="mb-0">Processos com Documentação Pendente</h5>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>N° do Processo</th>
                    <th>Estabelecimento</th>
                    <th>Data de Abertura</th>
                    <th>Documentos Pendentes</th>
                    <th>Dias Pendentes</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($processosPendentes as $proc) : ?>
                    <tr onclick="window.location='documentos.php?processo_id=<?php echo $proc['processo_id']; ?>&id=<?php echo $proc['estabelecimento_id']; ?>';" style="cursor:pointer;">
                        <td><?php echo htmlspecialchars($proc['numero_processo']); ?></td>
                        <td><?php echo htmlspecialchars($proc['nome_fantasia']); ?></td>
                        <td><?php echo htmlspecialchars(formatDate($proc['data_upload_pendente'])); ?></td>
                        <td>
                            <span class="badge bg-warning text-light">Pendentes</span>
                        </td>
                        <td>
                            <?php
                            $dataUploadPendente = new DateTime($proc['data_upload_pendente']);
                            $dataAtual = new DateTime();
                            $diasPendentes = $dataUploadPendente->diff($dataAtual)->days;
                            $classe = ($diasPendentes > 0 ? 'bg-danger' : 'bg-success');
                            echo '<span class="badge ' . $classe . ' text-white">' . $diasPendentes . '</span>';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conn->close();
include '../footer.php';
?>