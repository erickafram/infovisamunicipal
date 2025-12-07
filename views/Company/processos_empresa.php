<?php
session_start();
include '../../includes/header_empresa.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Processo.php';

$userId = $_SESSION['user']['id'];
$processoModel = new Processo($conn);

// Obter todos os processos das empresas vinculadas ao usuário
$processos = $processoModel->getProcessosByUsuario($userId);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processos da Empresa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Alpine.js para interatividade -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-3 py-6 mt-4">
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-800 flex items-center">
                    <i class="fas fa-clipboard-list mr-3 text-blue-500"></i>Processos das Empresas
                </h2>
            </div>
            <div class="p-6">
                <?php if (!empty($processos)) : ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Número do Processo
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Empresa
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Data de Abertura
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($processos as $processo) : ?>
                                    <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150" 
                                        onclick="window.location.href='../Processo/detalhes_processo_empresa.php?id=<?php echo htmlspecialchars($processo['id']); ?>'">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($processo['numero_processo']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($processo['nome_fantasia']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = '';
                                            $statusBg = '';
                                            switch ($processo['status']) {
                                                case 'ATIVO':
                                                    $statusClass = 'text-green-800';
                                                    $statusBg = 'bg-green-100';
                                                    break;
                                                case 'PARADO':
                                                    $statusClass = 'text-red-800';
                                                    $statusBg = 'bg-red-100';
                                                    break;
                                                case 'FINALIZADO':
                                                    $statusClass = 'text-gray-800';
                                                    $statusBg = 'bg-gray-100';
                                                    break;
                                                case 'ARQUIVADO':
                                                    $statusClass = 'text-gray-800';
                                                    $statusBg = 'bg-gray-100';
                                                    break;
                                                case 'APROVADO':
                                                    $statusClass = 'text-green-800';
                                                    $statusBg = 'bg-green-100';
                                                    break;
                                                default:
                                                    $statusClass = 'text-yellow-800';
                                                    $statusBg = 'bg-yellow-100';
                                                    break;
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusBg . ' ' . $statusClass; ?>">
                                                <?php echo htmlspecialchars($processo['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars(date('d/m/Y', strtotime($processo['data_abertura']))); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="bg-gray-50 rounded-lg p-6 text-center">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-folder-open text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">Nenhum processo encontrado.</p>
                            <p class="text-gray-400 text-sm mt-2">Quando você tiver processos, eles aparecerão aqui.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>