<?php
session_start();
include '../header.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Processo.php';
require_once '../../models/Estabelecimento.php';

$arquivoModel = new Arquivo($conn);
$processoModel = new Processo($conn);
$estabelecimentoModel = new Estabelecimento($conn);

if (isset($_GET['arquivo_id'])) {
    $arquivo_id = $_GET['arquivo_id'];
    $arquivo = $arquivoModel->getArquivoById($arquivo_id);
    if ($arquivo) {
        $processo_id = $arquivoModel->getProcessoIdByArquivoId($arquivo_id);
        $numero_processo = $processoModel->getNumeroProcesso($processo_id);
        $estabelecimento_id = $processoModel->getEstabelecimentoIdByProcessoId($processo_id);

        if ($estabelecimento_id === null) {
            echo "Erro: Estabelecimento não encontrado para o processo.";
            exit();
        }

        $estabelecimento = $estabelecimentoModel->findById($estabelecimento_id);
?>
        <!DOCTYPE html>
        <html lang="pt-br">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Visualizar Arquivo</title>
            <link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
            <style>
                .content {
                    border: 1px solid #ddd;
                    padding: 20px;
                    border-radius: 5px;
                    background-color: #f9f9f9;
                    font-size: 12px;
                }

                h3 {
                    text-align: center;
                    margin-bottom: 20px;
                }

                .section-title {
                    font-weight: bold;
                    margin-top: 10px;
                }

                .info-table {
                    width: 100%;
                    margin-bottom: 20px;
                }

                .info-table th,
                .info-table td {
                    padding: 8px;
                    border: 1px solid #ddd;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <h3><?php echo htmlspecialchars($arquivo['tipo_documento']); ?></h3>
                <?php if ($estabelecimento) : ?>
                    <div class="content">
                        <h4 class="section-title">Dados da Empresa</h4>
                        <table class="info-table">
                            <tr>
                                <th>Nome Fantasia</th>
                                <td><?php echo htmlspecialchars($estabelecimento['nome_fantasia']); ?></td>
                            </tr>
                            <tr>
                                <th>Razão Social</th>
                                <td><?php echo htmlspecialchars($estabelecimento['razao_social']); ?></td>
                            </tr>
                            <tr>
                                <th>CNPJ</th>
                                <td><?php echo htmlspecialchars($estabelecimento['cnpj']); ?></td>
                            </tr>
                            <tr>
                                <th>Endereço</th>
                                <td><?php echo htmlspecialchars($estabelecimento['logradouro'] . ', ' . $estabelecimento['numero'] . ', ' . $estabelecimento['bairro'] . ', ' . $estabelecimento['municipio'] . '-' . $estabelecimento['uf']); ?></td>
                            </tr>
                            <tr>
                                <th>CEP</th>
                                <td><?php echo htmlspecialchars($estabelecimento['cep']); ?></td>
                            </tr>
                            <tr>
                                <th>Telefone</th>
                                <td><?php echo htmlspecialchars($estabelecimento['ddd_telefone_1'] . ' / ' . $estabelecimento['ddd_telefone_2']); ?></td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="content">
                    <h4 class="section-title">Dados do Processo</h4>
                    <table class="info-table">
                        <tr>
                            <th>Número do Processo</th>
                            <td>
                                <a href="../Processo/documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>">
                                    <?php echo htmlspecialchars($numero_processo); ?>
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="content">
                    <h4 class="section-title">Conteúdo do Arquivo</h4>
                    <?php echo $arquivo['conteudo']; ?>
                </div>
                <?php if ($arquivo['status'] == 'finalizado') : ?>
                    <form method="POST" action="assinar_arquivo.php" class="mt-3">
                        <input type="hidden" name="arquivo_id" value="<?php echo $arquivo_id; ?>">
                        <button type="submit" class="btn btn-success">Assinar Digitalmente</button>
                    </form>
                <?php else : ?>
                    <div class="mt-3">
                        <p class="text-danger">Este documento está no status de rascunho e não pode ser assinado.</p>
                    </div>
                <?php endif; ?>
            </div>
        </body>

        </html>
<?php
    } else {
        echo "<p>Arquivo não encontrado.</p>";
    }
} else {
    echo "<p>ID do arquivo não fornecido.</p>";
}
?>
