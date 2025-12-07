<?php

require_once __DIR__ . "/../conf/database.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class PagamentoController
{
    private $conn;
    private $logFile = __DIR__ . "/../logs/digitopay_assinaturas.log";

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function handleRequest()
    {
        if (isset($_REQUEST['action'])) {
            switch ($_REQUEST['action']) {
                case 'gerar_assinatura':
                    $this->processarAssinatura();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
                    break;
            }
        }
    }

    private function processarAssinatura()
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Verifica se o usuário está logado usando a chave correta da sessão
            if (!isset($_SESSION['user'])) {
                throw new Exception("Usuário não autenticado. Faça login novamente.");
            }

            $valor = (float)$_POST['valor'];
            $anonimo = (int)$_POST['anonimo'];
            $user_id = $_SESSION['user']['id']; // Obtém o ID do usuário da sessão correta

            if ($valor < 29.99) {
                throw new Exception("Valor mínimo de R$ 29,99 para assinatura");
            }

            $dadosUsuario = $this->getDadosUsuario($user_id);
            $response = $this->gerarQRCodeAssinatura($valor, $dadosUsuario);

            if ($response['status']) {
                $this->registrarAssinaturaNoBanco(
                    $user_id,
                    $valor,
                    $response['payment_id'],
                    $anonimo,
                    $dadosUsuario
                );

                echo json_encode([
                    'success' => true,
                    'qrcode' => $response['qrcode'],
                    'payment_code' => $response['pixCopiaECola']
                ]);
            } else {
                throw new Exception($response['message']);
            }
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getDadosUsuario($user_id)
    {
        if (!$user_id) {
            throw new Exception("ID do usuário não está disponível na sessão");
        }

        $stmt = $this->conn->prepare("SELECT nome_completo AS nome, cpf FROM usuarios_externos WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Usuário não encontrado");
        }

        return $result->fetch_assoc();
    }

    private function gerarQRCodeAssinatura($valor, $dadosUsuario)
    {
        try {
            $clientUri = 'https://api.digitopayoficial.com.br/';
            $clientId = '41b9547d-1053-47ee-8b57-322ca8fd67b1';
            $clientSecret = '1697c51a-7b58-4370-b5dd-f54183169523';

            // Autenticação na API
            $token = $this->getAuthToken($clientUri, $clientId, $clientSecret);

            // Solicitar pagamento
            $paymentData = [
                "dueDate" => date('Y-m-d\TH:i:s', strtotime('+1 day')),
                "paymentOptions" => ["PIX"],
                "person" => [
                    "cpf" => preg_replace('/[^0-9]/', '', $dadosUsuario['cpf']),
                    "name" => $dadosUsuario['nome']
                ],
                "value" => $valor,
                "callbackUrl" => "https://visamunicipal.com.br/webhook_assinaturas.php"
            ];

            $paymentResponse = $this->callDigitopayAPI(
                $clientUri . 'api/deposit',
                $token,
                $paymentData
            );

            // Gerar QR Code
            $qrCode = new QrCode($paymentResponse['pixCopiaECola']);
            $writer = new PngWriter();
            $qrImage = $writer->write($qrCode)->getString();

            return [
                'status' => true,
                'qrcode' => base64_encode($qrImage),
                'pixCopiaECola' => $paymentResponse['pixCopiaECola'],
                'payment_id' => $paymentResponse['id']
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Erro na geração do QRCode: ' . $e->getMessage()
            ];
        }
    }

    private function getAuthToken($uri, $clientId, $clientSecret)
    {
        $ch = curl_init($uri . 'api/token/api');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode([
                'clientId' => $clientId,
                'secret' => $clientSecret
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            throw new Exception("Falha na autenticação com a Digitopay");
        }

        $tokenData = json_decode($response, true);
        return $tokenData['accessToken'] ?? null;
    }

    private function callDigitopayAPI($url, $token, $data)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            throw new Exception("Erro na comunicação com a Digitopay");
        }

        $responseData = json_decode($response, true);

        if (!isset($responseData['pixCopiaECola'])) {
            throw new Exception($responseData['message'] ?? "Erro desconhecido na API");
        }

        return $responseData;
    }

    // Substitua o método registrarAssinaturaNoBanco por:
    private function registrarAssinaturaNoBanco($user_id, $valor, $payment_id, $anonimo, $dadosUsuario)
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO assinatura_planos 
                (usuario_id, valor, payment_id, metodo_pagamento, data_inicio, data_expiracao, status, gateway)
                VALUES (?, ?, ?, 'pix', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 'pendente', 'digitopay')
            ");
            $stmt->bind_param("ids", $user_id, $valor, $payment_id);
            $stmt->execute();
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Erro ao registrar assinatura: " . $e->getMessage());
        }
    }
    private function logError($message)
    {
        error_log("[" . date("Y-m-d H:i:s") . "] ERRO: " . $message . PHP_EOL, 3, $this->logFile);
    }
}

// Inicialização do controller
if (isset($_SERVER['REQUEST_METHOD'])) {
    session_start();
    require_once __DIR__ . "/../conf/database.php";

    $pagamentoController = new PagamentoController($conn);
    $pagamentoController->handleRequest();
}
