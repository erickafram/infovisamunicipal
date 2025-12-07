<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../login.php");
    exit();
}

$usuarioLogado = $_SESSION['user'];

// Verificar se o formulário foi submetido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mensagem = $_POST['mensagem'];

    // Validar se a mensagem não está vazia
    if (empty($mensagem)) {
        header("Location: enviar_mensagem_chat.php?error=" . urlencode('A mensagem não pode estar vazia.'));
        exit();
    }

    // Conectar ao banco de dados
    require_once '../../conf/database.php';

    // Buscar todos os usuários externos
    $query = "SELECT id FROM usuarios_externos";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $remetente_id = $usuarioLogado['id']; // ID do usuário logado (Erick Vinicius)
        $tipo_remetente = 'usuario'; // Remetente é um usuário normal

        // Preparar a inserção da mensagem
        $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, tipo_remetente, destinatario_id, tipo_destinatario, mensagem) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiss", $remetente_id, $tipo_remetente, $destinatario_id, $tipo_destinatario, $mensagem);

        // Enviar a mensagem para cada usuário externo
        while ($row = $result->fetch_assoc()) {
            $destinatario_id = $row['id'];
            $tipo_destinatario = 'externo'; // Destinatário é um usuário externo
            $stmt->execute();
        }

        $stmt->close();
        header("Location: enviar_mensagem_chat.php?success=1");
        exit();
    } else {
        header("Location: enviar_mensagem_chat.php?error=" . urlencode('Nenhum usuário externo encontrado.'));
        exit();
    }
}

include '../header.php';

?>

<div class="container mt-5">
    <h2>Enviar Mensagem para Todos os Usuários Externos</h2>

    <?php if (isset($_GET['success'])) : ?>
        <div class="alert alert-success" role="alert">
            Mensagem enviada com sucesso para todos os usuários externos!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])) : ?>
        <div class="alert alert-danger" role="alert">
            Erro ao enviar mensagem: <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="enviar_mensagem_chat.php" method="POST">
        <div class="mb-3">
            <label for="mensagem" class="form-label">Mensagem</label>
            <textarea class="form-control" id="mensagem" name="mensagem" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Enviar Mensagem</button>
    </form>
</div>

<?php include '../footer.php'; ?>