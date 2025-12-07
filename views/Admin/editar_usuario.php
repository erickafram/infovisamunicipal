<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || $_SESSION['user']['nivel_acesso'] != 1) {
    header("Location: ../login.php"); // Redirecionar para a página de login se não estiver autenticado ou não for administrador
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/User.php';

$user = new User($conn);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $usuario = $user->findById($id);

    if (!$usuario) {
        echo "Usuário não encontrado!";
        exit();
    }
} else {
    echo "ID do usuário não fornecido!";
    exit();
}
?>

<div class="container mt-5">
    <h2>Editar Usuário</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" role="alert">
            Usuário atualizado com sucesso!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger" role="alert">
            Erro ao atualizar usuário: <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="../../controllers/UserController.php?action=update" method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($usuario['id']); ?>">
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
                <label for="municipio" class="form-label">Município</label>
                <select class="form-select" id="municipio" name="municipio" required>
                <option value="" selected disabled>Selecione o município</option>
            <option value="ABREULÂNDIA" <?php if ($usuario['municipio'] == "ABREULÂNDIA") echo 'selected'; ?>>ABREULÂNDIA</option>
            <option value="AGUIARNÓPOLIS" <?php if ($usuario['municipio'] == "AGUIARNÓPOLIS") echo 'selected'; ?>>AGUIARNÓPOLIS</option>
            <option value="ALIANÇA DO TOCANTINS" <?php if ($usuario['municipio'] == "ALIANÇA DO TOCANTINS") echo 'selected'; ?>>ALIANÇA DO TOCANTINS</option>
            <option value="ALMAS" <?php if ($usuario['municipio'] == "ALMAS") echo 'selected'; ?>>ALMAS</option>
            <option value="ALVORADA" <?php if ($usuario['municipio'] == "ALVORADA") echo 'selected'; ?>>ALVORADA</option>
            <option value="ANANÁS" <?php if ($usuario['municipio'] == "ANANÁS") echo 'selected'; ?>>ANANÁS</option>
            <option value="ANGICO" <?php if ($usuario['municipio'] == "ANGICO") echo 'selected'; ?>>ANGICO</option>
            <option value="APARECIDA DO RIO NEGRO" <?php if ($usuario['municipio'] == "APARECIDA DO RIO NEGRO") echo 'selected'; ?>>APARECIDA DO RIO NEGRO</option>
            <option value="ARAGOMINAS" <?php if ($usuario['municipio'] == "ARAGOMINAS") echo 'selected'; ?>>ARAGOMINAS</option>
            <option value="ARAGUACEMA" <?php if ($usuario['municipio'] == "ARAGUACEMA") echo 'selected'; ?>>ARAGUACEMA</option>
            <option value="ARAGUAÇU" <?php if ($usuario['municipio'] == "ARAGUAÇU") echo 'selected'; ?>>ARAGUAÇU</option>
            <option value="ARAGUAÍNA" <?php if ($usuario['municipio'] == "ARAGUAÍNA") echo 'selected'; ?>>ARAGUAÍNA</option>
            <option value="ARAGUANÃ" <?php if ($usuario['municipio'] == "ARAGUANÃ") echo 'selected'; ?>>ARAGUANÃ</option>
            <option value="ARAGUATINS" <?php if ($usuario['municipio'] == "ARAGUATINS") echo 'selected'; ?>>ARAGUATINS</option>
            <option value="ARAPOEMA" <?php if ($usuario['municipio'] == "ARAPOEMA") echo 'selected'; ?>>ARAPOEMA</option>
            <option value="ARRAIAS" <?php if ($usuario['municipio'] == "ARRAIAS") echo 'selected'; ?>>ARRAIAS</option>
            <option value="AUGUSTINÓPOLIS" <?php if ($usuario['municipio'] == "AUGUSTINÓPOLIS") echo 'selected'; ?>>AUGUSTINÓPOLIS</option>
            <option value="AURORA DO TOCANTINS" <?php if ($usuario['municipio'] == "AURORA DO TOCANTINS") echo 'selected'; ?>>AURORA DO TOCANTINS</option>
            <option value="AXIXÁ DO TOCANTINS" <?php if ($usuario['municipio'] == "AXIXÁ DO TOCANTINS") echo 'selected'; ?>>AXIXÁ DO TOCANTINS</option>
            <option value="BABAÇULÂNDIA" <?php if ($usuario['municipio'] == "BABAÇULÂNDIA") echo 'selected'; ?>>BABAÇULÂNDIA</option>
            <option value="BANDEIRANTES DO TOCANTINS" <?php if ($usuario['municipio'] == "BANDEIRANTES DO TOCANTINS") echo 'selected'; ?>>BANDEIRANTES DO TOCANTINS</option>
            <option value="BARRA DO OURO" <?php if ($usuario['municipio'] == "BARRA DO OURO") echo 'selected'; ?>>BARRA DO OURO</option>
            <option value="BARROLÂNDIA" <?php if ($usuario['municipio'] == "BARROLÂNDIA") echo 'selected'; ?>>BARROLÂNDIA</option>
            <option value="BERNARDO SAYÃO" <?php if ($usuario['municipio'] == "BERNARDO SAYÃO") echo 'selected'; ?>>BERNARDO SAYÃO</option>
            <option value="BOM JESUS DO TOCANTINS" <?php if ($usuario['municipio'] == "BOM JESUS DO TOCANTINS") echo 'selected'; ?>>BOM JESUS DO TOCANTINS</option>
            <option value="BRASILÂNDIA DO TOCANTINS" <?php if ($usuario['municipio'] == "BRASILÂNDIA DO TOCANTINS") echo 'selected'; ?>>BRASILÂNDIA DO TOCANTINS</option>
            <option value="BREJINHO DE NAZARÉ" <?php if ($usuario['municipio'] == "BREJINHO DE NAZARÉ") echo 'selected'; ?>>BREJINHO DE NAZARÉ</option>
            <option value="BURITI DO TOCANTINS" <?php if ($usuario['municipio'] == "BURITI DO TOCANTINS") echo 'selected'; ?>>BURITI DO TOCANTINS</option>
            <option value="CACHOEIRINHA" <?php if ($usuario['municipio'] == "CACHOEIRINHA") echo 'selected'; ?>>CACHOEIRINHA</option>
            <option value="CAMPOS LINDOS" <?php if ($usuario['municipio'] == "CAMPOS LINDOS") echo 'selected'; ?>>CAMPOS LINDOS</option>
            <option value="CARIRI DO TOCANTINS" <?php if ($usuario['municipio'] == "CARIRI DO TOCANTINS") echo 'selected'; ?>>CARIRI DO TOCANTINS</option>
            <option value="CARMOLÂNDIA" <?php if ($usuario['municipio'] == "CARMOLÂNDIA") echo 'selected'; ?>>CARMOLÂNDIA</option>
            <option value="CARRASCO BONITO" <?php if ($usuario['municipio'] == "CARRASCO BONITO") echo 'selected'; ?>>CARRASCO BONITO</option>
            <option value="CASEARA" <?php if ($usuario['municipio'] == "CASEARA") echo 'selected'; ?>>CASEARA</option>
            <option value="CENTENÁRIO" <?php if ($usuario['municipio'] == "CENTENÁRIO") echo 'selected'; ?>>CENTENÁRIO</option>
            <option value="CHAPADA DA NATIVIDADE" <?php if ($usuario['municipio'] == "CHAPADA DA NATIVIDADE") echo 'selected'; ?>>CHAPADA DA NATIVIDADE</option>
            <option value="CHAPADA DE AREIA" <?php if ($usuario['municipio'] == "CHAPADA DE AREIA") echo 'selected'; ?>>CHAPADA DE AREIA</option>
            <option value="COLINAS DO TOCANTINS" <?php if ($usuario['municipio'] == "COLINAS DO TOCANTINS") echo 'selected'; ?>>COLINAS DO TOCANTINS</option>
            <option value="COLMÉIA" <?php if ($usuario['municipio'] == "COLMÉIA") echo 'selected'; ?>>COLMÉIA</option>
            <option value="COMBINADO" <?php if ($usuario['municipio'] == "COMBINADO") echo 'selected'; ?>>COMBINADO</option>
            <option value="CONCEIÇÃO DO TOCANTINS" <?php if ($usuario['municipio'] == "CONCEIÇÃO DO TOCANTINS") echo 'selected'; ?>>CONCEIÇÃO DO TOCANTINS</option>
            <option value="COUTO MAGALHÃES" <?php if ($usuario['municipio'] == "COUTO MAGALHÃES") echo 'selected'; ?>>COUTO MAGALHÃES</option>
            <option value="CRISTALÂNDIA" <?php if ($usuario['municipio'] == "CRISTALÂNDIA") echo 'selected'; ?>>CRISTALÂNDIA</option>
            <option value="CRIXÁS DO TOCANTINS" <?php if ($usuario['municipio'] == "CRIXÁS DO TOCANTINS") echo 'selected'; ?>>CRIXÁS DO TOCANTINS</option>
            <option value="DARCINÓPOLIS" <?php if ($usuario['municipio'] == "DARCINÓPOLIS") echo 'selected'; ?>>DARCINÓPOLIS</option>
            <option value="DIANÓPOLIS" <?php if ($usuario['municipio'] == "DIANÓPOLIS") echo 'selected'; ?>>DIANÓPOLIS</option>
            <option value="DIVINÓPOLIS DO TOCANTINS" <?php if ($usuario['municipio'] == "DIVINÓPOLIS DO TOCANTINS") echo 'selected'; ?>>DIVINÓPOLIS DO TOCANTINS</option>
            <option value="DOIS IRMÃOS DO TOCANTINS" <?php if ($usuario['municipio'] == "DOIS IRMÃOS DO TOCANTINS") echo 'selected'; ?>>DOIS IRMÃOS DO TOCANTINS</option>
            <option value="DUERÉ" <?php if ($usuario['municipio'] == "DUERÉ") echo 'selected'; ?>>DUERÉ</option>
            <option value="ESPERANTINA" <?php if ($usuario['municipio'] == "ESPERANTINA") echo 'selected'; ?>>ESPERANTINA</option>
            <option value="FÁTIMA" <?php if ($usuario['municipio'] == "FÁTIMA") echo 'selected'; ?>>FÁTIMA</option>
            <option value="FIGUEIRÓPOLIS" <?php if ($usuario['municipio'] == "FIGUEIRÓPOLIS") echo 'selected'; ?>>FIGUEIRÓPOLIS</option>
            <option value="FILADÉLFIA" <?php if ($usuario['municipio'] == "FILADÉLFIA") echo 'selected'; ?>>FILADÉLFIA</option>
            <option value="FORMOSO DO ARAGUAIA" <?php if ($usuario['municipio'] == "FORMOSO DO ARAGUAIA") echo 'selected'; ?>>FORMOSO DO ARAGUAIA</option>
            <option value="FORTALEZA DO TABOCÃO" <?php if ($usuario['municipio'] == "FORTALEZA DO TABOCÃO") echo 'selected'; ?>>FORTALEZA DO TABOCÃO</option>
            <option value="GOIANORTE" <?php if ($usuario['municipio'] == "GOIANORTE") echo 'selected'; ?>>GOIANORTE</option>
            <option value="GOIATINS" <?php if ($usuario['municipio'] == "GOIATINS") echo 'selected'; ?>>GOIATINS</option>
            <option value="GUARAÍ" <?php if ($usuario['municipio'] == "GUARAÍ") echo 'selected'; ?>>GUARAÍ</option>
            <option value="GURUPI" <?php if ($usuario['municipio'] == "GURUPI") echo 'selected'; ?>>GURUPI</option>
            <option value="IPUEIRAS" <?php if ($usuario['municipio'] == "IPUEIRAS") echo 'selected'; ?>>IPUEIRAS</option>
            <option value="ITACAJÁ" <?php if ($usuario['municipio'] == "ITACAJÁ") echo 'selected'; ?>>ITACAJÁ</option>
            <option value="ITAGUATINS" <?php if ($usuario['municipio'] == "ITAGUATINS") echo 'selected'; ?>>ITAGUATINS</option>
            <option value="ITAPIRATINS" <?php if ($usuario['municipio'] == "ITAPIRATINS") echo 'selected'; ?>>ITAPIRATINS</option>
            <option value="ITAPORÃ DO TOCANTINS" <?php if ($usuario['municipio'] == "ITAPORÃ DO TOCANTINS") echo 'selected'; ?>>ITAPORÃ DO TOCANTINS</option>
            <option value="JAÚ DO TOCANTINS" <?php if ($usuario['municipio'] == "JAÚ DO TOCANTINS") echo 'selected'; ?>>JAÚ DO TOCANTINS</option>
            <option value="JUARINA" <?php if ($usuario['municipio'] == "JUARINA") echo 'selected'; ?>>JUARINA</option>
            <option value="LAGOA DA CONFUSÃO" <?php if ($usuario['municipio'] == "LAGOA DA CONFUSÃO") echo 'selected'; ?>>LAGOA DA CONFUSÃO</option>
            <option value="LAGOA DO TOCANTINS" <?php if ($usuario['municipio'] == "LAGOA DO TOCANTINS") echo 'selected'; ?>>LAGOA DO TOCANTINS</option>
            <option value="LAJEADO" <?php if ($usuario['municipio'] == "LAJEADO") echo 'selected'; ?>>LAJEADO</option>
            <option value="LAVANDEIRA" <?php if ($usuario['municipio'] == "LAVANDEIRA") echo 'selected'; ?>>LAVANDEIRA</option>
            <option value="LIZARDA" <?php if ($usuario['municipio'] == "LIZARDA") echo 'selected'; ?>>LIZARDA</option>
            <option value="LUZINÓPOLIS" <?php if ($usuario['municipio'] == "LUZINÓPOLIS") echo 'selected'; ?>>LUZINÓPOLIS</option>
            <option value="MARIANÓPOLIS DO TOCANTINS" <?php if ($usuario['municipio'] == "MARIANÓPOLIS DO TOCANTINS") echo 'selected'; ?>>MARIANÓPOLIS DO TOCANTINS</option>
            <option value="MATEIROS" <?php if ($usuario['municipio'] == "MATEIROS") echo 'selected'; ?>>MATEIROS</option>
            <option value="MAURILÂNDIA DO TOCANTINS" <?php if ($usuario['municipio'] == "MAURILÂNDIA DO TOCANTINS") echo 'selected'; ?>>MAURILÂNDIA DO TOCANTINS</option>
            <option value="MIRACEMA DO TOCANTINS" <?php if ($usuario['municipio'] == "MIRACEMA DO TOCANTINS") echo 'selected'; ?>>MIRACEMA DO TOCANTINS</option>
            <option value="MIRANORTE" <?php if ($usuario['municipio'] == "MIRANORTE") echo 'selected'; ?>>MIRANORTE</option>
            <option value="MONTE DO CARMO" <?php if ($usuario['municipio'] == "MONTE DO CARMO") echo 'selected'; ?>>MONTE DO CARMO</option>
            <option value="MONTE SANTO DO TOCANTINS" <?php if ($usuario['municipio'] == "MONTE SANTO DO TOCANTINS") echo 'selected'; ?>>MONTE SANTO DO TOCANTINS</option>
            <option value="MURICILÂNDIA" <?php if ($usuario['municipio'] == "MURICILÂNDIA") echo 'selected'; ?>>MURICILÂNDIA</option>
            <option value="NATAL" <?php if ($usuario['municipio'] == "NATAL") echo 'selected'; ?>>NATAL</option>
            <option value="NATIVIDADE" <?php if ($usuario['municipio'] == "NATIVIDADE") echo 'selected'; ?>>NATIVIDADE</option>
            <option value="NAZARÉ" <?php if ($usuario['municipio'] == "NAZARÉ") echo 'selected'; ?>>NAZARÉ</option>
            <option value="NOVA OLINDA" <?php if ($usuario['municipio'] == "NOVA OLINDA") echo 'selected'; ?>>NOVA OLINDA</option>
            <option value="NOVA ROSALÂNDIA" <?php if ($usuario['municipio'] == "NOVA ROSALÂNDIA") echo 'selected'; ?>>NOVA ROSALÂNDIA</option>
            <option value="NOVO ACORDO" <?php if ($usuario['municipio'] == "NOVO ACORDO") echo 'selected'; ?>>NOVO ACORDO</option>
            <option value="NOVO ALEGRE" <?php if ($usuario['municipio'] == "NOVO ALEGRE") echo 'selected'; ?>>NOVO ALEGRE</option>
            <option value="NOVO JARDIM" <?php if ($usuario['municipio'] == "NOVO JARDIM") echo 'selected'; ?>>NOVO JARDIM</option>
            <option value="OLIVEIRA DE FÁTIMA" <?php if ($usuario['municipio'] == "OLIVEIRA DE FÁTIMA") echo 'selected'; ?>>OLIVEIRA DE FÁTIMA</option>
            <option value="PALMAS" <?php if ($usuario['municipio'] == "PALMAS") echo 'selected'; ?>>PALMAS</option>
            <option value="PALMEIRANTE" <?php if ($usuario['municipio'] == "PALMEIRANTE") echo 'selected'; ?>>PALMEIRANTE</option>
            <option value="PALMEIRAS DO TOCANTINS" <?php if ($usuario['municipio'] == "PALMEIRAS DO TOCANTINS") echo 'selected'; ?>>PALMEIRAS DO TOCANTINS</option>
            <option value="PALMEIRÓPOLIS" <?php if ($usuario['municipio'] == "PALMEIRÓPOLIS") echo 'selected'; ?>>PALMEIRÓPOLIS</option>
            <option value="PARAÍSO DO TOCANTINS" <?php if ($usuario['municipio'] == "PARAÍSO DO TOCANTINS") echo 'selected'; ?>>PARAÍSO DO TOCANTINS</option>
            <option value="PARANÃ" <?php if ($usuario['municipio'] == "PARANÃ") echo 'selected'; ?>>PARANÃ</option>
            <option value="PAU D'ARCO" <?php if ($usuario['municipio'] == "PAU D'ARCO") echo 'selected'; ?>>PAU D'ARCO</option>
            <option value="PEDRO AFONSO" <?php if ($usuario['municipio'] == "PEDRO AFONSO") echo 'selected'; ?>>PEDRO AFONSO</option>
            <option value="PEIXE" <?php if ($usuario['municipio'] == "PEIXE") echo 'selected'; ?>>PEIXE</option>
            <option value="PEQUIZEIRO" <?php if ($usuario['municipio'] == "PEQUIZEIRO") echo 'selected'; ?>>PEQUIZEIRO</option>
            <option value="PINDORAMA DO TOCANTINS" <?php if ($usuario['municipio'] == "PINDORAMA DO TOCANTINS") echo 'selected'; ?>>PINDORAMA DO TOCANTINS</option>
            <option value="PIRAQUÊ" <?php if ($usuario['municipio'] == "PIRAQUÊ") echo 'selected'; ?>>PIRAQUÊ</option>
            <option value="PIUM" <?php if ($usuario['municipio'] == "PIUM") echo 'selected'; ?>>PIUM</option>
            <option value="PONTE ALTA DO BOM JESUS" <?php if ($usuario['municipio'] == "PONTE ALTA DO BOM JESUS") echo 'selected'; ?>>PONTE ALTA DO BOM JESUS</option>
            <option value="PONTE ALTA DO TOCANTINS" <?php if ($usuario['municipio'] == "PONTE ALTA DO TOCANTINS") echo 'selected'; ?>>PONTE ALTA DO TOCANTINS</option>
            <option value="PORTO ALEGRE DO TOCANTINS" <?php if ($usuario['municipio'] == "PORTO ALEGRE DO TOCANTINS") echo 'selected'; ?>>PORTO ALEGRE DO TOCANTINS</option>
            <option value="PORTO NACIONAL" <?php if ($usuario['municipio'] == "PORTO NACIONAL") echo 'selected'; ?>>PORTO NACIONAL</option>
            <option value="PRAIA NORTE" <?php if ($usuario['municipio'] == "PRAIA NORTE") echo 'selected'; ?>>PRAIA NORTE</option>
            <option value="PRESIDENTE KENNEDY" <?php if ($usuario['municipio'] == "PRESIDENTE KENNEDY") echo 'selected'; ?>>PRESIDENTE KENNEDY</option>
            <option value="PUGMIL" <?php if ($usuario['municipio'] == "PUGMIL") echo 'selected'; ?>>PUGMIL</option>
            <option value="RECURSOLÂNDIA" <?php if ($usuario['municipio'] == "RECURSOLÂNDIA") echo 'selected'; ?>>RECURSOLÂNDIA</option>
            <option value="RIACHINHO" <?php if ($usuario['municipio'] == "RIACHINHO") echo 'selected'; ?>>RIACHINHO</option>
            <option value="RIO DA CONCEIÇÃO" <?php if ($usuario['municipio'] == "RIO DA CONCEIÇÃO") echo 'selected'; ?>>RIO DA CONCEIÇÃO</option>
            <option value="RIO DOS BOIS" <?php if ($usuario['municipio'] == "RIO DOS BOIS") echo 'selected'; ?>>RIO DOS BOIS</option>
            <option value="RIO SONO" <?php if ($usuario['municipio'] == "RIO SONO") echo 'selected'; ?>>RIO SONO</option>
            <option value="SAMPAIO" <?php if ($usuario['municipio'] == "SAMPAIO") echo 'selected'; ?>>SAMPAIO</option>
            <option value="SANDOLÂNDIA" <?php if ($usuario['municipio'] == "SANDOLÂNDIA") echo 'selected'; ?>>SANDOLÂNDIA</option>
            <option value="SANTA FÉ DO ARAGUAIA" <?php if ($usuario['municipio'] == "SANTA FÉ DO ARAGUAIA") echo 'selected'; ?>>SANTA FÉ DO ARAGUAIA</option>
            <option value="SANTA MARIA DO TOCANTINS" <?php if ($usuario['municipio'] == "SANTA MARIA DO TOCANTINS") echo 'selected'; ?>>SANTA MARIA DO TOCANTINS</option>
            <option value="SANTA RITA DO TOCANTINS" <?php if ($usuario['municipio'] == "SANTA RITA DO TOCANTINS") echo 'selected'; ?>>SANTA RITA DO TOCANTINS</option>
            <option value="SANTA ROSA DO TOCANTINS" <?php if ($usuario['municipio'] == "SANTA ROSA DO TOCANTINS") echo 'selected'; ?>>SANTA ROSA DO TOCANTINS</option>
            <option value="SANTA TEREZA DO TOCANTINS" <?php if ($usuario['municipio'] == "SANTA TEREZA DO TOCANTINS") echo 'selected'; ?>>SANTA TEREZA DO TOCANTINS</option>
            <option value="SANTA TEREZINHA DO TOCANTINS" <?php if ($usuario['municipio'] == "SANTA TEREZINHA DO TOCANTINS") echo 'selected'; ?>>SANTA TEREZINHA DO TOCANTINS</option>
            <option value="SÃO BENTO DO TOCANTINS" <?php if ($usuario['municipio'] == "SÃO BENTO DO TOCANTINS") echo 'selected'; ?>>SÃO BENTO DO TOCANTINS</option>
            <option value="SÃO FÉLIX DO TOCANTINS" <?php if ($usuario['municipio'] == "SÃO FÉLIX DO TOCANTINS") echo 'selected'; ?>>SÃO FÉLIX DO TOCANTINS</option>
            <option value="SÃO MIGUEL DO TOCANTINS" <?php if ($usuario['municipio'] == "SÃO MIGUEL DO TOCANTINS") echo 'selected'; ?>>SÃO MIGUEL DO TOCANTINS</option>
            <option value="SÃO SALVADOR DO TOCANTINS" <?php if ($usuario['municipio'] == "SÃO SALVADOR DO TOCANTINS") echo 'selected'; ?>>SÃO SALVADOR DO TOCANTINS</option>
            <option value="SÃO SEBASTIÃO DO TOCANTINS" <?php if ($usuario['municipio'] == "SÃO SEBASTIÃO DO TOCANTINS") echo 'selected'; ?>>SÃO SEBASTIÃO DO TOCANTINS</option>
            <option value="SÃO VALÉRIO" <?php if ($usuario['municipio'] == "SÃO VALÉRIO") echo 'selected'; ?>>SÃO VALÉRIO</option>
            <option value="SILVANÓPOLIS" <?php if ($usuario['municipio'] == "SILVANÓPOLIS") echo 'selected'; ?>>SILVANÓPOLIS</option>
            <option value="SÍTIO NOVO DO TOCANTINS" <?php if ($usuario['municipio'] == "SÍTIO NOVO DO TOCANTINS") echo 'selected'; ?>>SÍTIO NOVO DO TOCANTINS</option>
            <option value="SUCUPIRA" <?php if ($usuario['municipio'] == "SUCUPIRA") echo 'selected'; ?>>SUCUPIRA</option>
            <option value="TAGUATINGA" <?php if ($usuario['municipio'] == "TAGUATINGA") echo 'selected'; ?>>TAGUATINGA</option>
            <option value="TAIPAS DO TOCANTINS" <?php if ($usuario['municipio'] == "TAIPAS DO TOCANTINS") echo 'selected'; ?>>TAIPAS DO TOCANTINS</option>
            <option value="TALISMÃ" <?php if ($usuario['municipio'] == "TALISMÃ") echo 'selected'; ?>>TALISMÃ</option>
            <option value="TOCANTÍNIA" <?php if ($usuario['municipio'] == "TOCANTÍNIA") echo 'selected'; ?>>TOCANTÍNIA</option>
            <option value="TOCANTINÓPOLIS" <?php if ($usuario['municipio'] == "TOCANTINÓPOLIS") echo 'selected'; ?>>TOCANTINÓPOLIS</option>
            <option value="TUPIRAMA" <?php if ($usuario['municipio'] == "TUPIRAMA") echo 'selected'; ?>>TUPIRAMA</option>
            <option value="TUPIRATINS" <?php if ($usuario['municipio'] == "TUPIRATINS") echo 'selected'; ?>>TUPIRATINS</option>
            <option value="WANDERLÂNDIA" <?php if ($usuario['municipio'] == "WANDERLÂNDIA") echo 'selected'; ?>>WANDERLÂNDIA</option>
            <option value="XAMBIOÁ" <?php if ($usuario['municipio'] == "XAMBIOÁ") echo 'selected'; ?>>XAMBIOÁ</option>
                </select>
            </div>

            <div class="col-md-6">
                <label for="cargo" class="form-label">Cargo</label>
                <select class="form-select" id="cargo" name="cargo" required>
                    <option value="" selected disabled>Selecione o cargo</option>
                    <option value="Descentralização" <?php if ($usuario['cargo'] == "Descentralização") echo 'selected'; ?>>Descentralização</option>
                    <option value="Gerente Municipal" <?php if ($usuario['cargo'] == "Gerente Municipal") echo 'selected'; ?>>Gerente Municipal</option>
                    <option value="Fiscal Municipal" <?php if ($usuario['cargo'] == "Fiscal Municipal") echo 'selected'; ?>>Fiscal Municipal</option>>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="nivel_acesso" class="form-label">Nível de Acesso</label>
                <select class="form-select" id="nivel_acesso" name="nivel_acesso" required>
                    <option value="" selected disabled>Selecione o nível de acesso</option>
                    <option value="1" <?php if ($usuario['nivel_acesso'] == 1) echo 'selected'; ?>>Administrador</option>
                    <option value="2" <?php if ($usuario['nivel_acesso'] == 2) echo 'selected'; ?>>Suporte</option>
                    <option value="3" <?php if ($usuario['nivel_acesso'] == 3) echo 'selected'; ?>>Gerente</option>
                    <option value="4" <?php if ($usuario['nivel_acesso'] == 4) echo 'selected'; ?>>Fiscal</option>
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

        var SPMaskBehavior = function (val) {
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

<?php
$conn->close();
include '../footer.php';
?>
