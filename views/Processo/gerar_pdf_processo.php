<?php
require_once '../../vendor/autoload.php';
require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../models/Estabelecimento.php';

ob_start();

if (!isset($_GET['id'])) {
    echo "ID do processo não fornecido!";
    exit();
}

$processoId = $_GET['id'];

$processoModel = new Processo($conn);
$dadosProcesso = $processoModel->findById($processoId);

if (!$dadosProcesso) {
    echo "Processo não encontrado!";
    exit();
}

$estabelecimentoModel = new Estabelecimento($conn);
$dadosEstabelecimento = $estabelecimentoModel->findById($dadosProcesso['estabelecimento_id']);

$pdf = new TCPDF();

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Secretaria Municipal de Saúde');
$pdf->SetTitle('Informações do Processo');
$pdf->SetSubject('Detalhes do Processo e Estabelecimento');
$pdf->SetKeywords('TCPDF, PDF, processo, estabelecimento, licenciamento');

$pdf->AddPage();

// Estilos atualizados
$styleTitle = 'style="color: #2c3e50; font-size: 15px; font-weight: bold;"';
$styleSubtitle = 'style="color: #34495e; font-size: 11px; margin-top: 12px; border-bottom: 1px solid #3498db; padding-bottom: 4px;"';
$styleTable = 'style="width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9.5px;"';
$styleCellLabel = 'style="width: 30%; padding: 5px; background-color: #f8f9fa; border: 1px solid #dee2e6; font-size: 9.5px;"';
$styleCellValue = 'style="width: 70%; padding: 5px; border: 1px solid #dee2e6; font-size: 9.5px;"';
$styleWarning = 'style="background-color: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeeba; border-radius: 5px; margin: 50px 0 30px 0; font-size: 8px; line-height: 1.4;"';

$pdf->SetFont('helvetica', 'B', 13);
$pdf->writeHTML('<div '.$styleTitle.' align="center">Informações do Processo</div>');

$status = ($dadosProcesso['status'] == 'ATIVO') ? 'EM ANDAMENTO' : $dadosProcesso['status'];

$html = '
<div '.$styleSubtitle.'>Dados do Processo</div>
<table '.$styleTable.'>
    <tr>
        <td '.$styleCellLabel.'><strong>Número do Processo:</strong></td>
        <td '.$styleCellValue.'>'.htmlspecialchars($dadosProcesso['numero_processo'] ?? '').'</td>
    </tr>
    <tr>
        <td '.$styleCellLabel.'><strong>Tipo de Processo:</strong></td>
        <td '.$styleCellValue.'>'.htmlspecialchars($dadosProcesso['tipo_processo'] ?? '').'</td>
    </tr>
    <tr>
        <td '.$styleCellLabel.'><strong>'.($dadosEstabelecimento['tipo_pessoa'] == 'fisica' ? 'Nome:' : 'Nome Fantasia:').'</strong></td>
        <td '.$styleCellValue.'>'.htmlspecialchars($dadosEstabelecimento['tipo_pessoa'] == 'fisica' ? ($dadosEstabelecimento['nome'] ?? '') : ($dadosEstabelecimento['nome_fantasia'] ?? '')).'</td>
    </tr>
    <tr>
        <td '.$styleCellLabel.'><strong>'.($dadosEstabelecimento['tipo_pessoa'] == 'fisica' ? 'CPF' : 'CNPJ').':</strong></td>
        <td '.$styleCellValue.'>'.htmlspecialchars($dadosEstabelecimento['tipo_pessoa'] == 'fisica' ? ($dadosEstabelecimento['cpf'] ?? '') : ($dadosEstabelecimento['cnpj'] ?? '')).'</td>
    </tr>
    <tr>
        <td '.$styleCellLabel.'><strong>Data de Abertura:</strong></td>
        <td '.$styleCellValue.'>'.(isset($dadosProcesso['data_abertura']) ? date('d/m/Y', strtotime($dadosProcesso['data_abertura'])) : '').'</td>
    </tr>
    <tr>
        <td '.$styleCellLabel.'><strong>Status:</strong></td>
        <td '.$styleCellValue.'>'.$status.'</td>
    </tr>
</table>';

if ($dadosEstabelecimento['tipo_pessoa'] != 'fisica') {
    $html .= '
    <div '.$styleSubtitle.' style="margin-top: 18px;">Dados do Estabelecimento</div>
    <table '.$styleTable.'>
        <tr>
            <td '.$styleCellLabel.'><strong>Razão Social:</strong></td>
            <td '.$styleCellValue.'>'.htmlspecialchars($dadosEstabelecimento['razao_social'] ?? '-').'</td>
        </tr>
        <tr>
            <td '.$styleCellLabel.'><strong>Endereço:</strong></td>
            <td '.$styleCellValue.'>'.
                htmlspecialchars($dadosEstabelecimento['logradouro'] ?? '').', '.
                htmlspecialchars($dadosEstabelecimento['numero'] ?? '').' - '.
                htmlspecialchars($dadosEstabelecimento['bairro'] ?? '').', '.
                htmlspecialchars($dadosEstabelecimento['municipio'] ?? '').'/'.
                htmlspecialchars($dadosEstabelecimento['uf'] ?? '').' - CEP: '.
                htmlspecialchars($dadosEstabelecimento['cep'] ?? '')
            .'</td>
        </tr>
        <tr>
            <td '.$styleCellLabel.'><strong>Telefone:</strong></td>
            <td '.$styleCellValue.'>'.htmlspecialchars($dadosEstabelecimento['ddd_telefone_1'] ?? '').'</td>
        </tr>
        <tr>
            <td '.$styleCellLabel.'><strong>Situação Cadastral:</strong></td>
            <td '.$styleCellValue.'>'.htmlspecialchars($dadosEstabelecimento['descricao_situacao_cadastral'] ?? '').'</td>
        </tr>
    </table>';
}

$html .= '
<br><br>
<div '.$styleWarning.'>
    <strong style="font-size: 9.5px;">ATENÇÃO:</strong> O protocolo deste processo não implica na aprovação automática do licenciamento sanitário. 
    A empresa somente estará apta para receber o alvará sanitário após a conferência completa da documentação e 
    aprovação na inspeção sanitária realizada pelos técnicos responsáveis.
</div>

<div style="margin-top: 15px; font-size: 8.5px; color: #666; line-height: 1.4;">
    Para consultar a autenticidade deste processo, acesse:<br>
    https://infovisa.gurupi.to.gov.br/visamunicipal  no "Consultar Andamento do Processo"<br>
    Informe o '.($dadosEstabelecimento['tipo_pessoa'] == 'fisica' ? 'CPF' : 'CNPJ').' do estabelecimento para verificação.
</div>';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('informacoes_processo.pdf', 'I');

ob_end_flush();
?>