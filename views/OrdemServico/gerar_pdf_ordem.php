<?php
session_start();
require_once '../../conf/database.php';
require '../../vendor/setasign/fpdf/fpdf.php';

// Verificar autenticação
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

// Verificar ID da OS
if (!isset($_GET['id'])) {
    die("ID da ordem de serviço não fornecido.");
}

// Carregar o modelo da Ordem de Serviço
require_once '../../models/OrdemServico.php';
$ordemServico = new OrdemServico($conn);
$ordem = $ordemServico->getOrdemById($_GET['id']);

if (!$ordem || $ordem['status'] !== 'finalizada') {
    die("Ordem de serviço inválida ou não finalizada.");
}

// Função para formatar data
function formatDate($date)
{
    $dateTime = new DateTime($date);
    return $dateTime->format('d/m/Y');
}

// Função para tratar caracteres UTF-8 para ISO-8859-1
function encodeForPDF($text)
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
}

// Iniciar PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Título
$pdf->Cell(0, 10, encodeForPDF('Detalhes da Ordem de Serviço'), 0, 1, 'C');
$pdf->Ln(10);

// Detalhes da OS
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, encodeForPDF('Número OS: ' . $ordem['id']), 0, 1);
$pdf->Cell(0, 10, encodeForPDF('Data Início: ' . formatDate($ordem['data_inicio'])), 0, 1);
$pdf->Cell(0, 10, encodeForPDF('Data Fim: ' . formatDate($ordem['data_fim'])), 0, 1);
$pdf->MultiCell(0, 10, encodeForPDF('Técnicos: ' . (isset($ordem['tecnicos_nomes']) ? implode(', ', $ordem['tecnicos_nomes']) : 'N/A')), 0, 1);
$pdf->Cell(0, 10, encodeForPDF('Status: ' . ucfirst($ordem['status'])), 0, 1);
$pdf->Ln(10);

// Ações Executadas
if (!empty($ordem['acoes_executadas'])) {
    $acoes_ids = json_decode($ordem['acoes_executadas'], true);
    $acoes_nomes = $ordemServico->getAcoesNomes($acoes_ids);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, encodeForPDF('Ações Executadas:'), 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($acoes_nomes as $acao) {
        $pdf->MultiCell(0, 10, '- ' . encodeForPDF($acao), 0, 1);
    }
    $pdf->Ln(10);
}

// Observações
if (!empty($ordem['observacao'])) {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, encodeForPDF('Observação:'), 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 10, encodeForPDF($ordem['observacao']));
    $pdf->Ln(10);
}

// Descrição de Encerramento
if (!empty($ordem['descricao_encerramento'])) {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, encodeForPDF('Descrição do Encerramento:'), 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 10, encodeForPDF($ordem['descricao_encerramento']));
}

// Output do PDF
$pdf->Output();
