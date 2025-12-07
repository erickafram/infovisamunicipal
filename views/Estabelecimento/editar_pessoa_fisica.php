<?php
session_start();


// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

$estabelecimento = new Estabelecimento($conn);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $dadosEstabelecimento = $estabelecimento->findById($id);

    if (!$dadosEstabelecimento) {
        echo "Estabelecimento não encontrado!";
        exit();
    }

    // Verificar se o usuário tem permissão para acessar o estabelecimento
    $usuarioMunicipio = $_SESSION['user']['municipio'];
    $nivel_acesso = $_SESSION['user']['nivel_acesso'];

    if ($nivel_acesso != 1 && $dadosEstabelecimento['municipio'] !== $usuarioMunicipio) {
        header("Location: listar_estabelecimentos.php?error=" . urlencode("Você não tem permissão para acessar este estabelecimento."));
        exit();
    }
} else {
    echo "ID do estabelecimento não fornecido!";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cpf = $_POST['cpf'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $rg = $_POST['rg'] ?? '';
    $orgao_emissor = $_POST['orgao_emissor'] ?? '';
    $nome_fantasia = $_POST['nome_fantasia'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $logradouro = $_POST['logradouro'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $municipio = $_POST['municipio'] ?? '';
    $uf = $_POST['uf'] ?? '';
    $email = $_POST['email'] ?? '';
    $ddd_telefone_1 = $_POST['ddd_telefone_1'] ?? '';
    $inicio_funcionamento = $_POST['inicio_funcionamento'] ?? '';
    $ramo_atividade = $_POST['ramo_atividade'] ?? '';

    // Atualizar os dados do estabelecimento
    $estabelecimento->updatePessoaFisica($id, $cpf, $nome, $rg, $orgao_emissor, $nome_fantasia, $cep, $logradouro, $numero, $bairro, $complemento, $municipio, $uf, $email, $ddd_telefone_1, $inicio_funcionamento, $ramo_atividade);
    header("Location: detalhes_pessoa_fisica.php?id=$id&success=1");
    exit();
}

include '../header.php';
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Editar Pessoa Física</h5>
        </div>
        <div class="card-body">
            <form action="editar_pessoa_fisica.php?id=<?php echo $id; ?>" method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" value="<?php echo htmlspecialchars($dadosEstabelecimento['cpf'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($dadosEstabelecimento['nome'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                        <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" value="<?php echo htmlspecialchars($dadosEstabelecimento['nome_fantasia'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="rg" class="form-label">RG</label>
                        <input type="text" class="form-control" id="rg" name="rg" value="<?php echo htmlspecialchars($dadosEstabelecimento['rg'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="orgao_emissor" class="form-label">Órgão Emissor</label>
                        <input type="text" class="form-control" id="orgao_emissor" name="orgao_emissor" value="<?php echo htmlspecialchars($dadosEstabelecimento['orgao_emissor'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" value="<?php echo htmlspecialchars($dadosEstabelecimento['cep'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="logradouro" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="logradouro" name="logradouro" value="<?php echo htmlspecialchars($dadosEstabelecimento['logradouro'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" value="<?php echo htmlspecialchars($dadosEstabelecimento['numero'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo htmlspecialchars($dadosEstabelecimento['bairro'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" value="<?php echo htmlspecialchars($dadosEstabelecimento['complemento'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="municipio" class="form-label">Cidade</label>
                        <select class="form-control" id="municipio" name="municipio" required>
                            <option value="">Selecione um município</option>
                            <?php
                            $municipiosTocantins = [
                                "ABREULÂNDIA",
                                "AGUIARNÓPOLIS",
                                "ALIANÇA DO TOCANTINS",
                                "ALMAS",
                                "ALVORADA",
                                "ANANÁS",
                                "ANGICO",
                                "APARECIDA DO RIO NEGRO",
                                "ARAGOMINAS",
                                "ARAGUACEMA",
                                "ARAGUAÇU",
                                "ARAGUAÍNA",
                                "ARAGUANÃ",
                                "ARAGUATINS",
                                "ARAPOEMA",
                                "ARRAIAS",
                                "AUGUSTINÓPOLIS",
                                "AURORA DO TOCANTINS",
                                "AXIXÁ DO TOCANTINS",
                                "BABAÇULÂNDIA",
                                "BANDEIRANTES DO TOCANTINS",
                                "BARRA DO OURO",
                                "BARROLÂNDIA",
                                "BERNARDO SAYÃO",
                                "BOM JESUS DO TOCANTINS",
                                "BRASILÂNDIA DO TOCANTINS",
                                "BREJINHO DE NAZARÉ",
                                "BURITI DO TOCANTINS",
                                "CACHOEIRINHA",
                                "CAMPOS LINDOS",
                                "CARIRI DO TOCANTINS",
                                "CARMOLÂNDIA",
                                "CARRASCO BONITO",
                                "CASEARA",
                                "CENTENÁRIO",
                                "CHAPADA DA NATIVIDADE",
                                "CHAPADA DE AREIA",
                                "COLINAS DO TOCANTINS",
                                "COLMÉIA",
                                "COMBINADO",
                                "CONCEIÇÃO DO TOCANTINS",
                                "COUTO DE MAGALHÃES",
                                "CRISTALÂNDIA",
                                "CRIXÁS DO TOCANTINS",
                                "DARCINÓPOLIS",
                                "DIANÓPOLIS",
                                "DIVINÓPOLIS DO TOCANTINS",
                                "DOIS IRMÃOS DO TOCANTINS",
                                "DUERÉ",
                                "ESPERANTINA",
                                "FÁTIMA",
                                "FIGUEIRÓPOLIS",
                                "FILADÉLFIA",
                                "FORMOSO DO ARAGUAIA",
                                "FORTALEZA DO TABOCÃO",
                                "GOIANORTE",
                                "GOIATINS",
                                "GUARAÍ",
                                "GURUPI",
                                "IPUEIRAS",
                                "ITACAJÁ",
                                "ITAGUATINS",
                                "ITAPIRATINS",
                                "ITAPORÃ DO TOCANTINS",
                                "JAÚ DO TOCANTINS",
                                "JUARINA",
                                "LAGOA DA CONFUSÃO",
                                "LAGOA DO TOCANTINS",
                                "LAJEADO",
                                "LAVANDEIRA",
                                "LIZARDA",
                                "LUZINÓPOLIS",
                                "MARIANÓPOLIS DO TOCANTINS",
                                "MATEIROS",
                                "MAURILÂNDIA DO TOCANTINS",
                                "MIRACEMA DO TOCANTINS",
                                "MIRANORTE",
                                "MONTE DO CARMO",
                                "MONTE SANTO DO TOCANTINS",
                                "MURICILÂNDIA",
                                "NATIVIDADE",
                                "NAZARÉ",
                                "NOVA OLINDA",
                                "NOVA ROSALÂNDIA",
                                "NOVO ACORDO",
                                "NOVO ALEGRE",
                                "NOVO JARDIM",
                                "OLIVEIRA DE FÁTIMA",
                                "PALMAS",
                                "PALMEIRANTE",
                                "PALMEIRAS DO TOCANTINS",
                                "PALMEIRÓPOLIS",
                                "PARAÍSO DO TOCANTINS",
                                "PARANÃ",
                                "PAU D'ARCO",
                                "PEDRO AFONSO",
                                "PEIXE",
                                "PEQUIZEIRO",
                                "PINDORAMA DO TOCANTINS",
                                "PIRAQUÊ",
                                "PIUM",
                                "PONTE ALTA DO BOM JESUS",
                                "PONTE ALTA DO TOCANTINS",
                                "PORTO ALEGRE DO TOCANTINS",
                                "PORTO NACIONAL",
                                "PRAIA NORTE",
                                "PRESIDENTE KENNEDY",
                                "PUGMIL",
                                "RECURSOLÂNDIA",
                                "RIACHINHO",
                                "RIO DA CONCEIÇÃO",
                                "RIO DOS BOIS",
                                "RIO SONO",
                                "SAMPAIO",
                                "SANDOLÂNDIA",
                                "SANTA FÉ DO ARAGUAIA",
                                "SANTA MARIA DO TOCANTINS",
                                "SANTA RITA DO TOCANTINS",
                                "SANTA ROSA DO TOCANTINS",
                                "SANTA TEREZA DO TOCANTINS",
                                "SANTA TEREZINHA DO TOCANTINS",
                                "SÃO BENTO DO TOCANTINS",
                                "SÃO FÉLIX DO TOCANTINS",
                                "SÃO MIGUEL DO TOCANTINS",
                                "SÃO SALVADOR DO TOCANTINS",
                                "SÃO SEBASTIÃO DO TOCANTINS",
                                "SÃO VALÉRIO",
                                "SILVANÓPOLIS",
                                "SÍTIO NOVO DO TOCANTINS",
                                "SUCUPIRA",
                                "TAGUATINGA",
                                "TAIPAS DO TOCANTINS",
                                "TALISMÃ",
                                "TOCANTÍNIA",
                                "TOCANTINÓPOLIS",
                                "TUPIRAMA",
                                "TUPIRATINS",
                                "WANDERLÂNDIA",
                                "XAMBIOÁ"
                            ];

                            foreach ($municipiosTocantins as $municipio) {
                                $selected = ($dadosEstabelecimento['municipio'] ?? '') == $municipio ? 'selected' : '';
                                echo "<option value=\"$municipio\" $selected>$municipio</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="uf" class="form-label">UF</label>
                        <input type="text" class="form-control" id="uf" name="uf" value="<?php echo htmlspecialchars($dadosEstabelecimento['uf'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($dadosEstabelecimento['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="ddd_telefone_1" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="ddd_telefone_1" name="ddd_telefone_1" value="<?php echo htmlspecialchars($dadosEstabelecimento['ddd_telefone_1'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="inicio_funcionamento" class="form-label">Início de Funcionamento</label>
                        <input type="date" class="form-control" id="inicio_funcionamento" name="inicio_funcionamento" value="<?php echo htmlspecialchars($dadosEstabelecimento['inicio_funcionamento'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>