<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

$tipo = $_GET['tipo'] ?? '';
$municipioUsuario = $_SESSION['user']['municipio'];

header('Content-Type: application/json');

try {
    switch ($tipo) {
        case 'estabelecimentos-mes':
            $estabelecimentoModel = new Estabelecimento($conn);
            $estabelecimentosPorMes = $estabelecimentoModel->getEstabelecimentosPorMes($municipioUsuario);
            
            if (empty($estabelecimentosPorMes)) {
                echo json_encode([
                    'success' => true,
                    'html' => '<p class="text-gray-500 text-xs italic">Nenhum cadastro registrado.</p>'
                ]);
            } else {
                $html = '<ul class="divide-y divide-gray-100">';
                foreach ($estabelecimentosPorMes as $mes) {
                    $dataFormatada = date('m/Y', strtotime($mes['mes'] . '-01'));
                    $html .= '
                        <li class="py-2 hover:bg-blue-50/50 rounded transition-all duration-200 cursor-pointer">
                            <div class="flex justify-between items-center">
                                <div class="text-xs text-gray-700">' . htmlspecialchars($dataFormatada) . '</div>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 group-hover:bg-blue-200 transition-colors duration-300 transform group-hover:scale-105">
                                    ' . htmlspecialchars($mes['total']) . '
                                </span>
                            </div>
                        </li>';
                }
                $html .= '</ul>';
                
                echo json_encode([
                    'success' => true,
                    'html' => $html
                ]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Tipo não encontrado']);
            break;
    }
} catch (Exception $e) {
    error_log("Erro no lazy load: " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?>