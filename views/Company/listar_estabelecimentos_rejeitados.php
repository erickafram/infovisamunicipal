<?php
session_start();
include '../../includes/header_empresa.php'; // Incluindo o header

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$userId = $_SESSION['user']['id'];
$estabelecimentoModel = new Estabelecimento($conn);

// Obtém os estabelecimentos rejeitados
$estabelecimentosRejeitados = $estabelecimentoModel->getEstabelecimentosRejeitadosByUsuario($userId, false); // Inclui todos
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estabelecimentos Negado</title>
</head>

<body>
    <div class="container mt-5">
        <h4>Estabelecimentos Negado</h4>
        <?php if (empty($estabelecimentosRejeitados)) : ?>
            <p class="text-muted">Nenhum estabelecimento rejeitado encontrado.</p>
        <?php else : ?>
            <ul class="list-group">
                <?php foreach ($estabelecimentosRejeitados as $estabelecimento) : ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Nome:</strong> <?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?><br>
                            <strong>Motivo:</strong> <?php echo htmlspecialchars($estabelecimento['motivo_negacao']); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.marcar-lido');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const estabelecimentoId = this.dataset.id;

                    fetch('../Company/marcar_lido.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `estabelecimento_id=${estabelecimentoId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                alert('Estabelecimento marcado como lido.');
                                this.closest('li').remove();
                            } else {
                                alert('Erro ao marcar como lido: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                        });
                });
            });
        });
    </script>
</body>

</html>