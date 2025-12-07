<?php
session_start();

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$usuarioLogado = $_SESSION['user'];

// Conexão com o banco de dados
require_once '../../conf/database.php';
require_once '../../models/User.php';

$user = new User($conn);

// Função para validar número de telefone usando a API da numverify e verificações adicionais
function validarTelefone($telefone) {
    // Verificação de padrões repetitivos
    $padroesInvalidos = [
        '/(\d)\1{3,}/', // Sequência de 4 ou mais dígitos repetidos
        '/(\d{2,})\1+/', // Sequência de dois ou mais dígitos repetidos
    ];

    foreach ($padroesInvalidos as $padrao) {
        if (preg_match($padrao, preg_replace('/\D/', '', $telefone))) {
            return false;
        }
    }

    $access_key = '126254f53c5ea8ce0af6ed347ad1df76'; // Substitua com sua chave de API da numverify
    $country_code = 'BR'; // Defina o código do país, por exemplo, 'BR' para Brasil
    $url = 'http://apilayer.net/api/validate?access_key=' . $access_key . '&number=' . urlencode($telefone) . '&country_code=' . $country_code . '&format=1';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $validationResult = json_decode($response, true);
    return isset($validationResult['valid']) && $validationResult['valid'] && isset($validationResult['line_type']) && $validationResult['line_type'] == 'mobile';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $usuarioLogado['id'];
    $nome_completo = $_POST['nome_completo'];
    $cpf = $_POST['cpf'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $cargo = $_POST['cargo'];
    $tempo_vinculo = $_POST['tempo_vinculo'];
    $escolaridade = $_POST['escolaridade'];
    $tipo_vinculo = $_POST['tipo_vinculo'];
    $municipio = $usuarioLogado['municipio']; // Sempre usar o município do usuário logado

    // Obter o número de telefone atual do usuário
    $usuarioAtual = $user->findById($id);
    $telefoneAtual = $usuarioAtual['telefone'];

    // Validação do número de telefone somente se o número foi alterado
    if ($telefone !== $telefoneAtual && !validarTelefone($telefone)) {
        header("Location: editar_cadastro_usuario.php?error=" . urlencode('Número de telefone inválido.'));
        exit();
    }

    if ($user->update($id, $nome_completo, $cpf, $email, $telefone, $municipio, $cargo, $usuarioLogado['nivel_acesso'], $tempo_vinculo, $escolaridade, $tipo_vinculo)) {
        $_SESSION['user'] = $user->findById($id); // Atualiza as informações na sessão
        header("Location: editar_cadastro_usuario.php?success=1");
        exit();
    } else {
        header("Location: editar_cadastro_usuario.php?error=" . urlencode($user->getLastError()));
        exit();
    }
}

// Obter os dados atuais do usuário
$usuario = $user->findById($usuarioLogado['id']);

include '../header.php';
?>

<div class="container mt-5">
    <h3>Editar Cadastro</h3>

    <?php if (isset($_GET['success'])) : ?>
        <div class="alert alert-success" role="alert">
            Informações atualizadas com sucesso!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])) : ?>
        <div class="alert alert-danger" role="alert">
            Erro ao atualizar informações: <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="editar_cadastro_usuario.php" method="POST">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="nome_completo" class="form-label">Nome Completo</label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($usuario['nome_completo']); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="cpf" class="form-label">CPF</label>
                <input type="text" class="form-control" id="cpf" name="cpf" value="<?php echo htmlspecialchars($usuario['cpf']); ?>" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="telefone" class="form-label">Telefone</label>
                <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone']); ?>" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="cargo" class="form-label">Cargo</label>
                <select class="form-select" id="cargo" name="cargo" required>
                    <option value="" disabled>Selecione o cargo</option>
                    <option value="Gerente Municipal" <?php if ($usuario['cargo'] == 'Gerente Municipal') echo 'selected'; ?>>Gerente Municipal</option>
                    <option value="Fiscal Municipal" <?php if ($usuario['cargo'] == 'Fiscal Municipal') echo 'selected'; ?>>Fiscal Municipal</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="escolaridade" class="form-label">Escolaridade</label>
                <select class="form-select" id="escolaridade" name="escolaridade" required>
                    <option value="" <?php if ($usuario['escolaridade'] == 'Fundamental') echo 'selected'; ?>>Selecione</option>
                    <option value="Fundamental" <?php if ($usuario['escolaridade'] == 'Fundamental') echo 'selected'; ?>>Fundamental</option>
                    <option value="Medio" <?php if ($usuario['escolaridade'] == 'Medio') echo 'selected'; ?>>Médio</option>
                    <option value="Superior" <?php if ($usuario['escolaridade'] == 'Superior') echo 'selected'; ?>>Superior</option>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="tempo_vinculo" class="form-label">Tempo de Vínculo</label>
                <select class="form-select" id="tempo_vinculo" name="tempo_vinculo" required>
                    <option value="" <?php if (empty($usuario['tempo_vinculo'])) echo 'selected'; ?>>Selecione o tempo de vínculo</option>
                    <?php for ($i = 0; $i <= 30; $i++) : ?>
                        <option value="<?php echo $i; ?>" <?php if (isset($usuario['tempo_vinculo']) && $usuario['tempo_vinculo'] == $i) echo 'selected'; ?>><?php echo $i == 0 ? 'Menos de 1 Ano' : $i . ' anos'; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6" style="display: none;">
                <label for="municipio" class="form-label">Município</label>
                <input type="text" class="form-control" id="municipio" name="municipio" value="<?php echo htmlspecialchars($usuario['municipio']); ?>" disabled>
            </div>
            <div class="col-md-6">
                <label for="tipo_vinculo" class="form-label">Tipo de Vínculo</label>
                <select class="form-select" id="tipo_vinculo" name="tipo_vinculo" required>
                    <option value="" <?php if ($usuario['tipo_vinculo'] == '') echo 'selected'; ?>>Selecione o tipo de vínculo</option>
                    <option value="Contratado" <?php if ($usuario['tipo_vinculo'] == 'Contratado') echo 'selected'; ?>>Contratado</option>
                    <option value="Nomeado" <?php if ($usuario['tipo_vinculo'] == 'Nomeado') echo 'selected'; ?>>Nomeado</option>
                    <option value="Efetivo" <?php if ($usuario['tipo_vinculo'] == 'Efetivo') echo 'selected'; ?>>Efetivo</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Atualizar</button>
    </form>
</div>

<!-- Adicione a biblioteca jQuery e o plugin jQuery Mask -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
    $(document).ready(function() {
        $('#cpf').mask('000.000.000-00');

        var SPMaskBehavior = function(val) {
                return val.replace(/\D/g, '').length === 11 ? '(00) 0 0000-0000' : '(00) 0000-00009';
            },
            spOptions = {
                onKeyPress: function(val, e, field, options) {
                    field.mask(SPMaskBehavior.apply({}, arguments), options);
                }
            };

        $('#telefone').mask(SPMaskBehavior, spOptions);
    });
</script>

<?php include '../footer.php'; ?>
