<?php
session_start();


// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../login.php");
    exit();
}

$usuarioLogado = $_SESSION['user'];

if ($usuarioLogado['nivel_acesso'] == 3) {
    $municipioUsuario = $usuarioLogado['municipio'];
}

// Função para validar número de telefone usando a API da numverify e verificações adicionais
function validarTelefone($telefone)
{
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

// Função para verificar se o dado já existe no banco de dados
function dadoExiste($conn, $campo, $valor)
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE $campo = ?");
    $stmt->bind_param("s", $valor);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

// Verificar se o formulário foi submetido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = $_POST['nome_completo'];
    $cpf = $_POST['cpf'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $municipio = $_POST['municipio'];
    $cargo = $_POST['cargo'];
    $escolaridade = $_POST['escolaridade'];
    $tipo_vinculo = $_POST['tipo_vinculo'];
    $tempo_vinculo = $_POST['tempo_vinculo'];
    $nivel_acesso = $_POST['nivel_acesso'];
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Verificar se as senhas coincidem
    if ($senha !== $confirmar_senha) {
        header("Location: cadastro.php?error=" . urlencode('As senhas não coincidem.'));
        exit();
    }

    // Inserir o usuário no banco de dados
    require_once '../../conf/database.php';
    require_once '../../models/User.php';

    // Verificar se o CPF, email ou telefone já estão cadastrados
    if (dadoExiste($conn, 'cpf', $cpf)) {
        header("Location: cadastro.php?error=" . urlencode('CPF já cadastrado.'));
        exit();
    }

    if (dadoExiste($conn, 'email', $email)) {
        header("Location: cadastro.php?error=" . urlencode('Email já cadastrado.'));
        exit();
    }

    if (dadoExiste($conn, 'telefone', $telefone)) {
        header("Location: cadastro.php?error=" . urlencode('Telefone já cadastrado.'));
        exit();
    }

    // Validação do número de telefone
    if (!validarTelefone($telefone)) {
        header("Location: cadastro.php?error=" . urlencode('Número de telefone inválido.'));
        exit();
    }

    $user = new User($conn);

    if ($user->create($nome_completo, $cpf, $email, $telefone, $municipio, $cargo, $nivel_acesso, password_hash($senha, PASSWORD_BCRYPT), $tempo_vinculo, $escolaridade, $tipo_vinculo)) {
        header("Location: cadastro.php?success=1");
        exit();
    } else {
        header("Location: cadastro.php?error=" . urlencode($user->getLastError()));
        exit();
    }
}


include '../header.php';
?>

<div class="container mt-5">
    <h2>Cadastro de Usuário</h2>

    <?php if (isset($_GET['success'])) : ?>
        <div class="alert alert-success" role="alert">
            Usuário cadastrado com sucesso!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])) : ?>
        <div class="alert alert-danger" role="alert">
            Erro ao cadastrar usuário: <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="cadastro.php" method="POST">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="nome_completo" class="form-label">Nome Completo</label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo" required>
            </div>
            <div class="col-md-6">
                <label for="cpf" class="form-label">CPF</label>
                <input type="text" class="form-control" id="cpf" name="cpf" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="col-md-6">
                <label for="telefone" class="form-label">Telefone</label>
                <input type="text" class="form-control" id="telefone" name="telefone" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="municipio" class="form-label">Município</label>
                <select class="form-select" id="municipio" name="municipio" required>
                    <option value="" selected disabled>Selecione o município</option>
                    <?php if ($usuarioLogado['nivel_acesso'] == 3) : ?>
                        <option value="<?php echo htmlspecialchars($municipioUsuario); ?>"><?php echo htmlspecialchars($municipioUsuario); ?></option>
                    <?php else : ?>
                        <option value="" selected disabled>Selecione o município</option>
                        <option value="ABREULÂNDIA">ABREULÂNDIA</option>
                        <option value="AGUIARNÓPOLIS">AGUIARNÓPOLIS</option>
                        <option value="ALIANÇA DO TOCANTINS">ALIANÇA DO TOCANTINS</option>
                        <option value="ALMAS">ALMAS</option>
                        <option value="ALVORADA">ALVORADA</option>
                        <option value="ANANÁS">ANANÁS</option>
                        <option value="ANGICO">ANGICO</option>
                        <option value="APARECIDA DO RIO NEGRO">APARECIDA DO RIO NEGRO</option>
                        <option value="ARAGOMINAS">ARAGOMINAS</option>
                        <option value="ARAGUACEMA">ARAGUACEMA</option>
                        <option value="ARAGUAÇU">ARAGUAÇU</option>
                        <option value="ARAGUAÍNA">ARAGUAÍNA</option>
                        <option value="ARAGUANÃ">ARAGUANÃ</option>
                        <option value="ARAGUATINS">ARAGUATINS</option>
                        <option value="ARAPOEMA">ARAPOEMA</option>
                        <option value="ARRAIAS">ARRAIAS</option>
                        <option value="AUGUSTINÓPOLIS">AUGUSTINÓPOLIS</option>
                        <option value="AURORA DO TOCANTINS">AURORA DO TOCANTINS</option>
                        <option value="AXIXÁ DO TOCANTINS">AXIXÁ DO TOCANTINS</option>
                        <option value="BABAÇULÂNDIA">BABAÇULÂNDIA</option>
                        <option value="BANDEIRANTES DO TOCANTINS">BANDEIRANTES DO TOCANTINS</option>
                        <option value="BARRA DO OURO">BARRA DO OURO</option>
                        <option value="BARROLÂNDIA">BARROLÂNDIA</option>
                        <option value="BERNARDO SAYÃO">BERNARDO SAYÃO</option>
                        <option value="BOM JESUS DO TOCANTINS">BOM JESUS DO TOCANTINS</option>
                        <option value="BRASILÂNDIA DO TOCANTINS">BRASILÂNDIA DO TOCANTINS</option>
                        <option value="BREJINHO DE NAZARÉ">BREJINHO DE NAZARÉ</option>
                        <option value="BURITI DO TOCANTINS">BURITI DO TOCANTINS</option>
                        <option value="CACHOEIRINHA">CACHOEIRINHA</option>
                        <option value="CAMPOS LINDOS">CAMPOS LINDOS</option>
                        <option value="CARIRI DO TOCANTINS">CARIRI DO TOCANTINS</option>
                        <option value="CARMOLÂNDIA">CARMOLÂNDIA</option>
                        <option value="CARRASCO BONITO">CARRASCO BONITO</option>
                        <option value="CASEARA">CASEARA</option>
                        <option value="CENTENÁRIO">CENTENÁRIO</option>
                        <option value="CHAPADA DA NATIVIDADE">CHAPADA DA NATIVIDADE</option>
                        <option value="CHAPADA DE AREIA">CHAPADA DE AREIA</option>
                        <option value="COLINAS DO TOCANTINS">COLINAS DO TOCANTINS</option>
                        <option value="COLMÉIA">COLMÉIA</option>
                        <option value="COMBINADO">COMBINADO</option>
                        <option value="CONCEIÇÃO DO TOCANTINS">CONCEIÇÃO DO TOCANTINS</option>
                        <option value="COUTO MAGALHÃES">COUTO MAGALHÃES</option>
                        <option value="CRISTALÂNDIA">CRISTALÂNDIA</option>
                        <option value="CRIXÁS DO TOCANTINS">CRIXÁS DO TOCANTINS</option>
                        <option value="DARCINÓPOLIS">DARCINÓPOLIS</option>
                        <option value="DIANÓPOLIS">DIANÓPOLIS</option>
                        <option value="DIVINÓPOLIS DO TOCANTINS">DIVINÓPOLIS DO TOCANTINS</option>
                        <option value="DOIS IRMÃOS DO TOCANTINS">DOIS IRMÃOS DO TOCANTINS</option>
                        <option value="DUERÉ">DUERÉ</option>
                        <option value="ESPERANTINA">ESPERANTINA</option>
                        <option value="FÁTIMA">FÁTIMA</option>
                        <option value="FIGUEIRÓPOLIS">FIGUEIRÓPOLIS</option>
                        <option value="FILADÉLFIA">FILADÉLFIA</option>
                        <option value="FORMOSO DO ARAGUAIA">FORMOSO DO ARAGUAIA</option>
                        <option value="FORTALEZA DO TABOCÃO">FORTALEZA DO TABOCÃO</option>
                        <option value="GOIANORTE">GOIANORTE</option>
                        <option value="GOIATINS">GOIATINS</option>
                        <option value="GUARAÍ">GUARAÍ</option>
                        <option value="GURUPI">GURUPI</option>
                        <option value="IPUEIRAS">IPUEIRAS</option>
                        <option value="ITACAJÁ">ITACAJÁ</option>
                        <option value="ITAGUATINS">ITAGUATINS</option>
                        <option value="ITAPIRATINS">ITAPIRATINS</option>
                        <option value="ITAPORÃ DO TOCANTINS">ITAPORÃ DO TOCANTINS</option>
                        <option value="JAÚ DO TOCANTINS">JAÚ DO TOCANTINS</option>
                        <option value="JUARINA">JUARINA</option>
                        <option value="LAGOA DA CONFUSÃO">LAGOA DA CONFUSÃO</option>
                        <option value="LAGOA DO TOCANTINS">LAGOA DO TOCANTINS</option>
                        <option value="LAJEADO">LAJEADO</option>
                        <option value="LAVANDEIRA">LAVANDEIRA</option>
                        <option value="LIZARDA">LIZARDA</option>
                        <option value="LUZINÓPOLIS">LUZINÓPOLIS</option>
                        <option value="MARIANÓPOLIS DO TOCANTINS">MARIANÓPOLIS DO TOCANTINS</option>
                        <option value="MATEIROS">MATEIROS</option>
                        <option value="MAURILÂNDIA DO TOCANTINS">MAURILÂNDIA DO TOCANTINS</option>
                        <option value="MIRACEMA DO TOCANTINS">MIRACEMA DO TOCANTINS</option>
                        <option value="MIRANORTE">MIRANORTE</option>
                        <option value="MONTE DO CARMO">MONTE DO CARMO</option>
                        <option value="MONTE SANTO DO TOCANTINS">MONTE SANTO DO TOCANTINS</option>
                        <option value="MURICILÂNDIA">MURICILÂNDIA</option>
                        <option value="NATAL">NATAL</option>
                        <option value="NATIVIDADE">NATIVIDADE</option>
                        <option value="NAZARÉ">NAZARÉ</option>
                        <option value="NOVA OLINDA">NOVA OLINDA</option>
                        <option value="NOVA ROSALÂNDIA">NOVA ROSALÂNDIA</option>
                        <option value="NOVO ACORDO">NOVO ACORDO</option>
                        <option value="NOVO ALEGRE">NOVO ALEGRE</option>
                        <option value="NOVO JARDIM">NOVO JARDIM</option>
                        <option value="OLIVEIRA DE FÁTIMA">OLIVEIRA DE FÁTIMA</option>
                        <option value="PALMAS">PALMAS</option>
                        <option value="PALMEIRANTE">PALMEIRANTE</option>
                        <option value="PALMEIRAS DO TOCANTINS">PALMEIRAS DO TOCANTINS</option>
                        <option value="PALMEIROPOLIS">PALMEIROPOLIS</option>
                        <option value="PARAÍSO DO TOCANTINS">PARAÍSO DO TOCANTINS</option>
                        <option value="PARANÃ">PARANÃ</option>
                        <option value="PAU D'ARCO">PAU D'ARCO</option>
                        <option value="PEDRO AFONSO">PEDRO AFONSO</option>
                        <option value="PEIXE">PEIXE</option>
                        <option value="PEQUIZEIRO">PEQUIZEIRO</option>
                        <option value="PINDORAMA DO TOCANTINS">PINDORAMA DO TOCANTINS</option>
                        <option value="PIRAQUÊ">PIRAQUÊ</option>
                        <option value="PIUM">PIUM</option>
                        <option value="PONTE ALTA DO BOM JESUS">PONTE ALTA DO BOM JESUS</option>
                        <option value="PONTE ALTA DO TOCANTINS">PONTE ALTA DO TOCANTINS</option>
                        <option value="PORTO ALEGRE DO TOCANTINS">PORTO ALEGRE DO TOCANTINS</option>
                        <option value="PORTO NACIONAL">PORTO NACIONAL</option>
                        <option value="PRAIA NORTE">PRAIA NORTE</option>
                        <option value="PRESIDENTE KENNEDY">PRESIDENTE KENNEDY</option>
                        <option value="PUGMIL">PUGMIL</option>
                        <option value="RECURSOLÂNDIA">RECURSOLÂNDIA</option>
                        <option value="RIACHINHO">RIACHINHO</option>
                        <option value="RIO DA CONCEIÇÃO">RIO DA CONCEIÇÃO</option>
                        <option value="RIO DOS BOIS">RIO DOS BOIS</option>
                        <option value="RIO SONO">RIO SONO</option>
                        <option value="SAMPAIO">SAMPAIO</option>
                        <option value="SANDOLÂNDIA">SANDOLÂNDIA</option>
                        <option value="SANTA FÉ DO ARAGUAIA">SANTA FÉ DO ARAGUAIA</option>
                        <option value="SANTA MARIA DO TOCANTINS">SANTA MARIA DO TOCANTINS</option>
                        <option value="SANTA RITA DO TOCANTINS">SANTA RITA DO TOCANTINS</option>
                        <option value="SANTA ROSA DO TOCANTINS">SANTA ROSA DO TOCANTINS</option>
                        <option value="SANTA TEREZA DO TOCANTINS">SANTA TEREZA DO TOCANTINS</option>
                        <option value="SANTA TEREZINHA DO TOCANTINS">SANTA TEREZINHA DO TOCANTINS</option>
                        <option value="SÃO BENTO DO TOCANTINS">SÃO BENTO DO TOCANTINS</option>
                        <option value="SÃO FÉLIX DO TOCANTINS">SÃO FÉLIX DO TOCANTINS</option>
                        <option value="SÃO MIGUEL DO TOCANTINS">SÃO MIGUEL DO TOCANTINS</option>
                        <option value="SÃO SALVADOR DO TOCANTINS">SÃO SALVADOR DO TOCANTINS</option>
                        <option value="SÃO SEBASTIÃO DO TOCANTINS">SÃO SEBASTIÃO DO TOCANTINS</option>
                        <option value="SÃO VALÉRIO DA NATIVIDADE">SÃO VALÉRIO DA NATIVIDADE</option>
                        <option value="SILVANÓPOLIS">SILVANÓPOLIS</option>
                        <option value="SÍTIO NOVO DO TOCANTINS">SÍTIO NOVO DO TOCANTINS</option>
                        <option value="SUCUPIRA">SUCUPIRA</option>
                        <option value="TAGUATINGA">TAGUATINGA</option>
                        <option value="TAIPAS DO TOCANTINS">TAIPAS DO TOCANTINS</option>
                        <option value="TALISMÃ">TALISMÃ</option>
                        <option value="TOCANTÍNIA">TOCANTÍNIA</option>
                        <option value="TOCANTINÓPOLIS">TOCANTINÓPOLIS</option>
                        <option value="TUPIRAMA">TUPIRAMA</option>
                        <option value="TUPIRATINS">TUPIRATINS</option>
                        <option value="WANDERLÂNDIA">WANDERLÂNDIA</option>
                        <option value="XAMBIOÁ">XAMBIOÁ</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="cargo" class="form-label">Cargo</label>
                <select class="form-select" id="cargo" name="cargo" required>
                    <option value="" selected disabled>Selecione o cargo</option>
                    <?php if ($_SESSION['user']['nivel_acesso'] == 1) : ?>
                        <option value="Descentralização">Descentralização</option>
                    <?php endif; ?>
                    <option value="Gerente Municipal">Gerente Municipal</option>
                    <option value="Fiscal Municipal">Fiscal Municipal</option>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="escolaridade" class="form-label">Escolaridade</label>
                <select class="form-select" id="escolaridade" name="escolaridade" required>
                    <option value="" selected disabled>Selecione a escolaridade</option>
                    <option value="Fundamental">Fundamental</option>
                    <option value="Medio">Médio</option>
                    <option value="Superior">Superior</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="tipo_vinculo" class="form-label">Tipo de Vínculo</label>
                <select class="form-select" id="tipo_vinculo" name="tipo_vinculo" required>
                    <option value="" selected disabled>Selecione o tipo de vínculo</option>
                    <option value="Contratado">Contratado</option>
                    <option value="Nomeado">Nomeado</option>
                    <option value="Efetivo">Efetivo</option>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="tempo_vinculo" class="form-label">Tempo de Vínculo</label>
                <select class="form-select" id="tempo_vinculo" name="tempo_vinculo" required>
                    <option value="" selected disabled>Selecione o tempo de vínculo</option>
                    <?php for ($i = 0; $i <= 30; $i++) : ?>
                        <option value="<?php echo $i; ?>"><?php echo $i == 0 ? 'Menos de 1 Ano' : $i . ' anos'; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="nivel_acesso" class="form-label">Nível de Acesso</label>
                <select class="form-select" id="nivel_acesso" name="nivel_acesso" required>
                    <option value="" selected disabled>Selecione o nível de acesso</option>
                    <?php if ($_SESSION['user']['nivel_acesso'] == 1) : ?>
                        <option value="1">Administrador</option>
                        <option value="2">Suporte</option>
                    <?php endif; ?>
                    <option value="3">Gerente</option>
                    <option value="4">Fiscal</option>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <div class="col-md-6">
                <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Cadastrar</button>
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