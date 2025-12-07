<?php
session_start();
ob_start();
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Arquivo.php';
require_once '../../models/Assinatura.php';
require_once '../../models/Usuario.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/Logomarca.php'; // Inclua o arquivo Logomarca aqui
require_once '../../controllers/ArquivoController.php';
require_once '../../vendor/autoload.php';
require_once '../../models/ResponsavelLegal.php';
require_once '../../models/ResponsavelTecnico.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!class_exists('CustomPDF')) {
    class CustomPDF extends TCPDF
    {
        private $qrCodePath;
        private $logoPath;

        public function setQrCodePath($qrCodePath)
        {
            $this->qrCodePath = $qrCodePath;
        }

        public function setLogoPath($logoPath)
        {
            $this->logoPath = $logoPath;
        }
        public function Header()
        {
            if ($this->logoPath && $this->getPage() == 1) { // Apenas na primeira página
                $this->Image($this->logoPath, $this->getPageWidth() / 2 - 20, 10, 40, 20, 'PNG');
                $this->Ln(30); // Ajustar esse valor para criar espaço suficiente para a logomarca
            }
        }
        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            if ($this->qrCodePath) {
                $this->Image($this->qrCodePath, $this->getPageWidth() - 30, $this->getPageHeight() - 30, 20, 20, 'PNG');
            }
        }
    }
}

// Função para mascarar CPF
function mask($val, $mask)
{
    $maskared = '';
    $k = 0;
    for ($i = 0; $i < strlen($mask); $i++) {
        if ($mask[$i] == '#') {
            if (isset($val[$k])) {
                $maskared .= $val[$k++];
            }
        } else {
            if (isset($mask[$i])) {
                $maskared .= $mask[$i];
            }
        }
    }
    return $maskared;
}

if (!isset($_GET['arquivo_id']) || !isset($_GET['processo_id']) || !isset($_GET['estabelecimento_id'])) {
    die('ID do arquivo, processo ou estabelecimento não especificado.');
}

$arquivo_id = $_GET['arquivo_id'];
$processo_id = $_GET['processo_id'];
$estabelecimento_id = $_GET['estabelecimento_id'];

$arquivoModel = new Arquivo($conn);
$assinaturaModel = new Assinatura($conn);
$usuarioModel = new Usuario($conn);
$estabelecimentoModel = new Estabelecimento($conn);
$logomarcaModel = new Logomarca($conn); // Instancia o modelo Logomarca
$responsavelLegalModel = new ResponsavelLegal($conn);
$responsavelTecnicoModel = new ResponsavelTecnico($conn);

$estabelecimento = $estabelecimentoModel->findById($estabelecimento_id);
$arquivo = $arquivoModel->getArquivoById($arquivo_id);
$arquivoAssinado = $assinaturaModel->isArquivoAssinado($arquivo_id);

$assinaturasPendentes = $assinaturaModel->getAssinaturasPendentesByArquivoId($arquivo_id);
$temAssinaturasPendentes = !empty($assinaturasPendentes);

// Verificar se o processo é do tipo "denúncia"
$processoQuery = $conn->prepare("SELECT tipo_processo FROM processos WHERE id = ?");
$processoQuery->bind_param("i", $processo_id);
$processoQuery->execute();
$result = $processoQuery->get_result();
$processo = $result->fetch_assoc();

$tipoProcesso = $processo ? $processo['tipo_processo'] : '';
$isDenuncia = ($tipoProcesso === 'DENÚNCIA');


$responsavel_legal = $responsavelLegalModel->findByEstabelecimentoId($estabelecimento_id);
if ($responsavel_legal) {
    $responsavel_legal['cpf'] = mask($responsavel_legal['cpf'], '###.###.###-##');
} else {
    $responsavel_legal = ['nome' => 'N/A', 'cpf' => 'N/A'];
}

$responsavel_tecnico = $responsavelTecnicoModel->findByEstabelecimentoId($estabelecimento_id);
if ($responsavel_tecnico) {
    $responsavel_tecnico['cpf'] = mask($responsavel_tecnico['cpf'], '###.###.###-##');
} else {
    $responsavel_tecnico = ['nome' => 'N/A', 'cpf' => 'N/A'];
}



if (!$arquivo) {
    die('Arquivo não encontrado.');
}

// Verifica se o status do arquivo é finalizado
if ($arquivo['status'] === 'finalizado') {
    die('Este arquivo não pode ser editado pois já foi finalizado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conteudo = $_POST['conteudo'];
    $assinantes = $_POST['assinantes'];
    $sigiloso = isset($_POST['sigiloso']) ? intval($_POST['sigiloso']) : 0;
    $action = $_POST['action'];

    // Verificar se o arquivo já foi assinado
    if ($arquivoAssinado) {
        // Se já foi assinado, não permitir a atualização do conteúdo
        $conteudo = $arquivo['conteudo'];
    }

    $arquivoModel->updateArquivo($arquivo_id, $arquivo['tipo_documento'], $conteudo, $sigiloso);

    // Verificar se a assinatura já existe antes de adicionar
    if (!$assinaturaModel->isAssinaturaExistente($arquivo_id, $_SESSION['user']['id'])) {
        $assinaturaModel->addAssinatura($arquivo_id, $_SESSION['user']['id']);
    }

    if ($action === 'salvar_rascunho') {
        // Redirecionar para documentos.php após salvar rascunho
        header("Location: documentos.php?processo_id={$processo_id}&id={$estabelecimento_id}");
        exit();
    }

    if ($action === 'salvar_definitivo') {

        if (!empty($assinaturasPendentes)) {
            echo "<script>alert('Não é possível finalizar o documento. Existem assinaturas pendentes.');</script>";
        } else {
            // Reutilizando a lógica de criação de PDF do ArquivoController
            $arquivoController = new ArquivoController($conn);

            $upload_dir = realpath(__DIR__ . '/../../uploads');
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Obter a logomarca do município do estabelecimento
            $logomarca_info = $logomarcaModel->getLogomarcaByMunicipio($estabelecimento['municipio']);
            $logoPath = $logomarca_info ? $logomarca_info['caminho_logomarca'] : null;
            $espacamento = $logomarca_info ? $logomarca_info['espacamento'] : 40; // Adiciona o espaçamento

            $pdf = new CustomPDF();
            $pdf->setQrCodePath($qrCodePath);
            $pdf->setLogoPath($logoPath);
            $pdf->AddPage();
            $pdf->SetMargins(15, 50, 15); // Adjust the values for left, top, and right margins
            $pdf->SetFont('helvetica', '', 8);

            // Adiciona o cabeçalho com o número e tipo de processo centralizados
            $ano_vigente = date('Y');
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->Cell(0, 0, "{$nome_arquivo_header}.{$ano_vigente}", 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 5, "{$tipo_processo}: {$numero_processo}", 0, 1, 'C');
            $pdf->Ln(10);

            // Mover essa parte para depois da criação do cabeçalho
            $processo_info = $arquivoModel->getProcessoInfo($processo_id);
            $numero_processo = str_replace("", "", $processo_info['numero_processo']);
            $tipo_processo = $processo_info['tipo_processo'];
            $ano_vigente = date('Y');
            $nome_arquivo = "{$arquivo['tipo_documento']}_{$arquivo_id}_{$ano_vigente}";
            $nome_arquivo_header = "{$arquivo['tipo_documento']}: {$arquivo_id}";
            $caminho_arquivo = $upload_dir . DIRECTORY_SEPARATOR . $nome_arquivo;

            // Definir estilos
            $styles = "
                <style>
                    h2 {
                        font-size: 10pt;
                        font-weight: 100;
                    }
                    table {
                        width: 100%;
                        font-size: 10pt;
                        border-collapse: collapse;
                    }
                    td {
                        padding: 5px;
                        border: 1px solid #808080;
                    }
                    .header {
                        font-size: 16pt;
                        text-align: center;
                        color: #808080;
                    }
                    .centered {
                        text-align: center;
                        color: #808080;
                        font-size: 10pt;
                    }
                </style>
            ";

            // Adicionar conteúdo com estilos
            $informacoes_estabelecimento = "
            {$styles}
            <table>
                <tr>
                    <td><strong>NOME FANTASIA: </strong> {$estabelecimento['nome_fantasia']}</td>
                    <td><strong>RAZÃO SOCIAL:</strong> {$estabelecimento['razao_social']}</td>
                </tr>
                <tr>
                    <td><strong>CNPJ:</strong> {$estabelecimento['cnpj']}</td>
                    <td><strong>ENDEREÇO:</strong> {$estabelecimento['logradouro']}, {$estabelecimento['numero']}, {$estabelecimento['bairro']}, {$estabelecimento['municipio']}-{$estabelecimento['uf']}</td>
                </tr>
                <tr>
                    <td><strong>CEP:</strong> {$estabelecimento['cep']}</td>
                    <td><strong>TELEFONE:</strong> {$estabelecimento['ddd_telefone_1']} / {$estabelecimento['ddd_telefone_2']}</td>
                </tr>
            </table>
            <table>
                <tr>
                    <td><strong>RESPONSÁVEL LEGAL:</strong> {$responsavel_legal['nome']}</td>
                    <td><strong>CPF:</strong> {$responsavel_legal['cpf']}</td>
                </tr>
                <tr>
                    <td><strong>RESPONSÁVEL TÉCNICO:</strong> {$responsavel_tecnico['nome']}</td>
                    <td><strong>CPF:</strong> {$responsavel_tecnico['cpf']}</td>
                </tr>
            </table>
        ";


            $conteudo_completo = $informacoes_estabelecimento . $conteudo;

            $codigo_verificador = md5(uniqid(rand(), true));
            $link_verificacao = "https://infovisa.gurupi.to.gov.br/visamunicipal/views/Arquivos/verificar.php";

            $qrCode = new QrCode($link_verificacao);
            $qrCode->setSize(150);
            $writer = new PngWriter();
            $qrCodePath = $upload_dir . DIRECTORY_SEPARATOR . 'qrcode_' . time() . '.png';
            $writer->writeFile($qrCode, $qrCodePath);

            $pdf = new CustomPDF();
            $pdf->setQrCodePath($qrCodePath);
            $pdf->setLogoPath($logoPath);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 8);

            // Adiciona o cabeçalho com o número e tipo de processo centralizados
            $pdf->Ln($espacamento); // Utiliza o valor do espaçamento
            $ano_vigente = date('Y');
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->Cell(0, 0, "{$nome_arquivo_header}.{$ano_vigente}", 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, "{$tipo_processo}: {$numero_processo}", 0, 1, 'C');
            $pdf->Ln(10);

            $pdf->SetFont('helvetica', '', 11);
            $pdf->writeHTML($conteudo_completo);

            $assinaturas = [];
            $assinantes = $assinaturaModel->getAssinaturasByArquivoId($arquivo_id);
            foreach ($assinantes as $assinante) {
                $usuario = $usuarioModel->findById($assinante['usuario_id']);
                $data_assinatura = date('d/m/Y H:i:s', strtotime($assinante['data_assinatura']));
                $assinaturas[] = "Documento assinado eletronicamente por {$usuario['nome_completo']} em {$data_assinatura}";
            }

            $assinaturas_html = implode('<br>', $assinaturas);
            $rodape = "
                <div style='font-size: 8pt; text-align: center;'>
                    <p>{$assinaturas_html}</p>
                    <p>A autenticidade do documento pode ser conferida no link: <a href='{$link_verificacao}'>{$link_verificacao}</a> informando o código verificador {$codigo_verificador}</p>
                </div>
            ";

            $pdf->writeHTML($rodape, true, false, true, false, '');

            $pdf->Output($caminho_arquivo, 'F');

            $arquivoModel->updateArquivoPathAndCodigo($arquivo_id, 'uploads/' . $nome_arquivo, $codigo_verificador);
        }

        header("Location: documentos.php?processo_id={$processo_id}&id={$estabelecimento_id}");
        exit;
    }
}

$assinaturas = $assinaturaModel->getAssinaturasIdsByArquivoId($arquivo_id);
$assinaturas = $assinaturaModel->getAssinaturasByArquivoId($arquivo_id);
$usuario_logado = $usuarioModel->findById($_SESSION['user']['id']);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Documento</title>
    <script>
        tinymce.init({
            selector: '#conteudo',
            plugins: 'advlist autolink lists link image charmap print preview hr anchor pagebreak image imagetools',
            toolbar_mode: 'floating',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | image',
            images_upload_url: 'upload_image.php',
            automatic_uploads: true,
            file_picker_types: 'image',
            file_picker_callback: function(cb, value, meta) {
                var input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', 'image/*');

                input.onchange = function() {
                    var file = this.files[0];

                    var reader = new FileReader();
                    reader.onload = function() {
                        var id = 'blobid' + (new Date()).getTime();
                        var blobCache = tinymce.activeEditor.editorUpload.blobCache;
                        var base64 = reader.result.split(',')[1];
                        var blobInfo = blobCache.create(id, file, base64);
                        blobCache.add(blobInfo);

                        cb(blobInfo.blobUri(), {
                            title: file.name
                        });
                    };
                    reader.readAsDataURL(file);
                };

                input.click();
            },
            language: 'pt_BR', // Configura o idioma para português do Brasil
            language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n/langs/pt_BR.js' // URL externa para o idioma
        });

        function salvarPDF() {
            $('#confirmSaveModal').modal('show');
        }

        function confirmarSalvarPDF() {
            document.getElementById('action').value = 'salvar_definitivo';
            document.getElementById('documento-form').submit();
            $('#confirmSaveModal').modal('hide');
        }
    </script>
</head>

<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Editar Documento</h6>
            </div>
            <div class="card-body">
                <form id="documento-form" method="POST" action="">
                    <input type="hidden" name="arquivo_id" value="<?php echo $arquivo_id; ?>">
                    <input type="hidden" id="action" name="action" value="">
                    <input type="hidden" name="processo_id" value="<?php echo $processo_id; ?>">
                    <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimento_id; ?>">
                    <!-- Campo para exibir o tipo de documento sem permitir a edição -->
                    <div class="mb-3">
                        <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                        <input type="text" class="form-control" id="tipo_documento" name="tipo_documento" value="<?php echo $arquivo['tipo_documento']; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="sigiloso" class="form-label">Documento Sigiloso</label>
                        <select class="form-control" id="sigiloso" name="sigiloso" <?php echo $isDenuncia ? 'disabled' : ''; ?>>
                            <option value="0" <?php echo !$isDenuncia && $arquivo['sigiloso'] == 0 ? 'selected' : ''; ?>>Não</option>
                            <option value="1" <?php echo $isDenuncia || $arquivo['sigiloso'] == 1 ? 'selected' : ''; ?>>Sim</option>
                        </select>
                        <?php if ($isDenuncia): ?>
                            <input type="hidden" name="sigiloso" value="1">
                        <?php endif; ?>
                    </div>


                    <div class="mb-3">
                        <label for="conteudo" class="form-label">Conteúdo</label>
                        <?php if ($arquivoAssinado) : ?>
                            <textarea class="form-control" id="conteudo" name="conteudo" rows="10" readonly><?php echo htmlspecialchars($arquivo['conteudo']); ?></textarea>
                            <p class="text-danger">Este documento não pode ser editado pois já foi assinado.</p>
                        <?php else : ?>
                            <textarea class="form-control" id="conteudo" name="conteudo" rows="10"><?php echo htmlspecialchars($arquivo['conteudo']); ?></textarea>
                        <?php endif; ?>
                    </div>
                    <!-- Adicionar um campo para exibir o espaçamento da logomarca -->
                    <button type="submit" name="action" value="salvar_rascunho" class="btn btn-secondary">Salvar Rascunho</button>

                    <?php if (!$temAssinaturasPendentes) : ?>
                        <button type="button" class="btn btn-primary" onclick="salvarPDF()">Finalizar Documento</button>
                    <?php endif; ?>
                </form>
                <h6 style="padding-top:30px; font-weight:bold;">Assinaturas Definidas</h6>
                <ul class="list-group">
                    <?php foreach ($assinaturas as $assinatura) : ?>
                        <li class="list-group-item">
                            <?php
                            $usuario = $usuarioModel->findById($assinatura['usuario_id']);
                            $status = $assinatura['status'] == 'assinado' ? 'Assinado' : 'Pendente';
                            $data_assinatura = $assinatura['status'] == 'assinado' ? date("d/m/Y H:i:s", strtotime($assinatura['data_assinatura'])) : '';
                            echo htmlspecialchars($usuario['nome_completo']) . " - " . $status . ($data_assinatura ? " em " . $data_assinatura : "");
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmSaveModal" tabindex="-1" role="dialog" aria-labelledby="confirmSaveModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmSaveModalLabel">Confirmação de Salvamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Após Finalizar o documento de forma definitiva, não será possível editar o documento. Caso queira que o documento seja editado, por favor, salve como rascunho.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarSalvarPDF()">Confirmar e Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.0/js/bootstrap.min.js"></script>
</body>

</html>
<?php include '../footer.php'; ?>