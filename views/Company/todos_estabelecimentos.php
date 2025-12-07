<?php
session_start();
include '../../includes/header_empresa.php';
require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$estabelecimentoModel = new Estabelecimento($conn);
$limit = 10; // Número de registros por página
$offset = isset($_GET['page']) ? ($_GET['page'] - 1) * $limit : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Obter estabelecimentos aprovados e pendentes com busca e paginação
$estabelecimentosAprovados = $estabelecimentoModel->searchAprovados($user_id, $search, $limit, $offset);
$estabelecimentosPendentes = $estabelecimentoModel->searchPendentes($user_id, $search, $limit, $offset);

// Total de estabelecimentos para paginação
$totalAprovados = $estabelecimentoModel->countAprovados($user_id, $search);
$totalPendentes = $estabelecimentoModel->countPendentes($user_id, $search);
$totalEstabelecimentos = $totalAprovados + $totalPendentes;
$totalPages = ceil($totalEstabelecimentos / $limit);
$currentPage = isset($_GET['page']) ? $_GET['page'] : 1;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos Estabelecimentos</title>
</head>


<body class="bg-gray-50">
    <div class="container mx-auto px-3 py-6 mt-4>
        <div class="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg mb-6">
            <div class="px-6 py-5">
                <h1 class="text-xl font-semibold text-gray-800 flex items-center mb-6">
                    <i class="fas fa-clipboard-list text-blue-500 mr-3"></i>
                    Todos Estabelecimentos
                </h1>
                
                <form method="GET" action="todos_estabelecimentos.php" class="mb-6">
                    <div class="flex">
                        <input type="text" 
                               class="flex-1 rounded-l-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-4 py-2" 
                               name="search" 
                               placeholder="Buscar estabelecimentos" 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-r-lg transition-colors duration-200" 
                                type="submit">
                            <i class="fas fa-search mr-2"></i>Buscar
                        </button>
                    </div>
                </form>

                <?php if (empty($estabelecimentosAprovados) && empty($estabelecimentosPendentes)) : ?>
                    <div class="flex items-center justify-center py-10">
                        <div class="text-center">
                            <i class="fas fa-search text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-500">Nenhum estabelecimento encontrado.</p>
                        </div>
                    </div>
                <?php else : ?>

                    <?php if (!empty($estabelecimentosAprovados)) : ?>
                        <div class="mb-8">
                            <h2 class="text-lg font-medium text-gray-800 flex items-center mb-4">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                Estabelecimentos Aprovados
                            </h2>
                            <div class="space-y-4">
                                <?php foreach ($estabelecimentosAprovados as $estabelecimento) : ?>
                                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden relative group transform translate-y-4 opacity-0">
                                        <div class="p-5">
                                            <div class="flex justify-between items-start">
                                                <div class="space-y-2">
                                                    <h3 class="font-medium text-gray-900">
                                                        <?php
                                                        if ($estabelecimento['tipo_pessoa'] == 'fisica') {
                                                            echo htmlspecialchars($estabelecimento['nome']);
                                                        } else {
                                                            echo htmlspecialchars($estabelecimento['nome_fantasia']);
                                                        }
                                                        ?>
                                                    </h3>
                                                    <div class="text-sm text-gray-600">
                                                        <?php if ($estabelecimento['tipo_pessoa'] == 'fisica') : ?>
                                                            <span class="inline-flex items-center">
                                                                <i class="far fa-id-card text-gray-400 mr-1"></i>
                                                                <span>CPF: <?php echo htmlspecialchars($estabelecimento['cpf'] ?? 'Não informado'); ?></span>
                                                            </span>
                                                        <?php else : ?>
                                                            <span class="inline-flex items-center">
                                                                <i class="far fa-building text-gray-400 mr-1"></i>
                                                                <span>CNPJ: <?php echo htmlspecialchars($estabelecimento['cnpj'] ?? 'Não informado'); ?></span>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-sm text-gray-600">
                                                        <span class="inline-flex items-center">
                                                            <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                                            <span><?php echo htmlspecialchars($estabelecimento['logradouro'] . ', ' . ($estabelecimento['numero'] ?? 'S/N') . ' - ' . $estabelecimento['bairro'] . ', ' . $estabelecimento['municipio'] . ' - ' . $estabelecimento['uf'] . ', ' . $estabelecimento['cep']); ?></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Aprovado
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="absolute top-0 right-0 p-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                <a href="../Estabelecimento/detalhes_estabelecimento_empresa.php?id=<?php echo htmlspecialchars($estabelecimento['id']); ?>" 
                                                   class="text-blue-500 hover:text-blue-700 transition-colors duration-200">
                                                    <i class="far fa-eye"></i>
                                                </a>
                                            </div>
                                            <a href="../Estabelecimento/detalhes_estabelecimento_empresa.php?id=<?php echo htmlspecialchars($estabelecimento['id']); ?>" 
                                               class="absolute inset-0 z-10" aria-label="Ver detalhes"></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($estabelecimentosPendentes)) : ?>
                        <div class="mb-8">
                            <h2 class="text-lg font-medium text-gray-800 flex items-center mb-4">
                                <i class="fas fa-clock text-yellow-500 mr-2"></i>
                                Estabelecimentos Pendentes
                            </h2>
                            <div class="space-y-4">
                                <?php foreach ($estabelecimentosPendentes as $estabelecimento) : ?>
                                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden relative transform translate-y-4 opacity-0">
                                        <div class="p-5">
                                            <div class="flex justify-between items-start">
                                                <div class="space-y-2">
                                                    <h3 class="font-medium text-gray-900">
                                                        <?php
                                                        if ($estabelecimento['tipo_pessoa'] == 'fisica') {
                                                            echo htmlspecialchars($estabelecimento['nome']);
                                                        } else {
                                                            echo htmlspecialchars($estabelecimento['nome_fantasia']);
                                                        }
                                                        ?>
                                                    </h3>
                                                    <div class="text-sm text-gray-600">
                                                        <?php if ($estabelecimento['tipo_pessoa'] == 'fisica') : ?>
                                                            <span class="inline-flex items-center">
                                                                <i class="far fa-id-card text-gray-400 mr-1"></i>
                                                                <span>CPF: <?php echo htmlspecialchars($estabelecimento['cpf'] ?? 'Não informado'); ?></span>
                                                            </span>
                                                        <?php else : ?>
                                                            <span class="inline-flex items-center">
                                                                <i class="far fa-building text-gray-400 mr-1"></i>
                                                                <span>CNPJ: <?php echo htmlspecialchars($estabelecimento['cnpj'] ?? 'Não informado'); ?></span>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-sm text-gray-600">
                                                        <span class="inline-flex items-center">
                                                            <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                                            <span><?php echo htmlspecialchars($estabelecimento['logradouro'] . ', ' . ($estabelecimento['numero'] ?? 'S/N') . ' - ' . $estabelecimento['bairro'] . ', ' . $estabelecimento['municipio'] . ' - ' . $estabelecimento['uf'] . ', ' . $estabelecimento['cep']); ?></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Pendente
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1) : ?>
                    <nav class="mt-8" aria-label="Paginação">
                        <ul class="flex justify-center space-x-1">
                            <?php if ($currentPage > 1) : ?>
                                <li>
                                    <a href="?search=<?php echo htmlspecialchars($search); ?>&page=<?php echo $currentPage - 1; ?>" 
                                       class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            // Determinar quais páginas mostrar
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            
                            // Ajustar se estamos perto do fim
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, $endPage - 4);
                            }
                            
                            // Mostrar primeira página e elipses se necessário
                            if ($startPage > 1) : 
                            ?>
                                <li>
                                    <a href="?search=<?php echo htmlspecialchars($search); ?>&page=1" 
                                       class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                        1
                                    </a>
                                </li>
                                <?php if ($startPage > 2) : ?>
                                    <li>
                                        <span class="px-3 py-2 text-gray-500">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++) : ?>
                                <li>
                                    <a href="?search=<?php echo htmlspecialchars($search); ?>&page=<?php echo $i; ?>" 
                                       class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($i == $currentPage) ? 'bg-blue-500 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php 
                            // Mostrar última página e elipses se necessário
                            if ($endPage < $totalPages) : 
                            ?>
                                <?php if ($endPage < $totalPages - 1) : ?>
                                    <li>
                                        <span class="px-3 py-2 text-gray-500">...</span>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <a href="?search=<?php echo htmlspecialchars($search); ?>&page=<?php echo $totalPages; ?>" 
                                       class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                        <?php echo $totalPages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($currentPage < $totalPages) : ?>
                                <li>
                                    <a href="?search=<?php echo htmlspecialchars($search); ?>&page=<?php echo $currentPage + 1; ?>" 
                                       class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Script para animar os cards quando a página carrega
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.bg-white.border');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('translate-y-0', 'opacity-100');
                    card.classList.remove('translate-y-4', 'opacity-0');
                }, 100 * index);
            });
        });
    </script>
</body>

</html>
