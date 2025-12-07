<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

// Paginação
$registrosPorPagina = 10;
$paginaAtual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($paginaAtual - 1) * $registrosPorPagina;

// Filtros
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';

// Construir a consulta SQL base
$sqlTecnico = "SELECT 
            'tecnico' as tipo_responsavel,
            rt.id,
            rt.nome,
            rt.cpf,
            rt.email,
            rt.telefone,
            rt.conselho,
            rt.numero_registro_conselho,
            e.id as estabelecimento_id,
            e.nome_fantasia,
            e.razao_social,
            e.cnpj,
            e.cpf as estabelecimento_cpf
        FROM 
            responsaveis_tecnicos rt
        JOIN 
            estabelecimentos e ON rt.estabelecimento_id = e.id";

// Adicionar condição de busca para técnicos se houver
if (!empty($busca)) {
    $sqlTecnico .= " WHERE rt.nome LIKE ? OR rt.cpf LIKE ? OR e.nome_fantasia LIKE ? OR e.razao_social LIKE ? OR e.cnpj LIKE ?";
}

// Consulta para responsáveis legais
$sqlLegal = "SELECT 
            'legal' as tipo_responsavel,
            rl.id,
            rl.nome,
            rl.cpf,
            rl.email,
            rl.telefone,
            '' as conselho,
            '' as numero_registro_conselho,
            e.id as estabelecimento_id,
            e.nome_fantasia,
            e.razao_social,
            e.cnpj,
            e.cpf as estabelecimento_cpf
        FROM 
            responsaveis_legais rl
        JOIN 
            estabelecimentos e ON rl.estabelecimento_id = e.id";

// Adicionar condição de busca para legais se houver
if (!empty($busca)) {
    $sqlLegal .= " WHERE rl.nome LIKE ? OR rl.cpf LIKE ? OR e.nome_fantasia LIKE ? OR e.razao_social LIKE ? OR e.cnpj LIKE ?";
}

// Filtrar por tipo de responsável
if ($tipo == 'tecnico') {
    $sql = $sqlTecnico . " ORDER BY nome LIMIT ? OFFSET ?";
    $sqlCount = "SELECT COUNT(*) as total FROM ($sqlTecnico) as temp";
} else if ($tipo == 'legal') {
    $sql = $sqlLegal . " ORDER BY nome LIMIT ? OFFSET ?";
    $sqlCount = "SELECT COUNT(*) as total FROM ($sqlLegal) as temp";
} else {
    $sql = "($sqlTecnico) UNION ALL ($sqlLegal) ORDER BY nome LIMIT ? OFFSET ?";
    $sqlCount = "SELECT COUNT(*) as total FROM (($sqlTecnico) UNION ALL ($sqlLegal)) as temp";
}

// Preparar termo de busca
if (!empty($busca)) {
    $busca = '%' . $busca . '%';
}

// Preparar e executar a consulta de contagem
$stmtCount = $conn->prepare($sqlCount);
if (!empty($busca)) {
    if ($tipo == 'tecnico' || $tipo == 'legal') {
        $stmtCount->bind_param("sssss", $busca, $busca, $busca, $busca, $busca);
    } else {
        // Para "todos", precisamos vincular 10 parâmetros (5 para cada subconsulta)
        $stmtCount->bind_param("ssssssssss", 
            $busca, $busca, $busca, $busca, $busca,  // Para técnicos
            $busca, $busca, $busca, $busca, $busca   // Para legais
        );
    }
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalRegistros = $resultCount->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Preparar e executar a consulta principal
$stmt = $conn->prepare($sql);
if (!empty($busca)) {
    if ($tipo == 'tecnico' || $tipo == 'legal') {
        $stmt->bind_param("sssssii", 
            $busca, $busca, $busca, $busca, $busca,
            $registrosPorPagina, $offset
        );
    } else {
        // Para "todos", precisamos vincular 12 parâmetros (5 para cada subconsulta + 2 para LIMIT/OFFSET)
        $stmt->bind_param("ssssssssssii", 
            $busca, $busca, $busca, $busca, $busca,  // Para técnicos
            $busca, $busca, $busca, $busca, $busca,  // Para legais
            $registrosPorPagina, $offset
        );
    }
} else {
    $stmt->bind_param("ii", $registrosPorPagina, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$responsaveis = $result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listagem de Responsáveis</title>
</head>
<body>
    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Listagem de Responsáveis</h2>
            
            <!-- Filtros -->
            <div class="mb-6">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="mb-0">
                        <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Responsável</label>
                        <select id="tipo" name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="todos" <?php echo $tipo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="tecnico" <?php echo $tipo == 'tecnico' ? 'selected' : ''; ?>>Técnico</option>
                            <option value="legal" <?php echo $tipo == 'legal' ? 'selected' : ''; ?>>Legal</option>
                        </select>
                    </div>
                    
                    <div class="mb-0">
                        <label for="busca" class="block text-sm font-medium text-gray-700 mb-2">Busca</label>
                        <input type="text" id="busca" name="busca" value="<?php echo htmlspecialchars($busca); ?>" 
                               placeholder="Nome, CPF, CNPJ ou Estabelecimento" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <i class="fas fa-search mr-2"></i>Filtrar
                        </button>
                        
                        <a href="listar_responsaveis.php" class="ml-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                            <i class="fas fa-times mr-2"></i>Limpar
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Tabela de Responsáveis -->
            <?php if (!empty($responsaveis)) : ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contato</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estabelecimento</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($responsaveis as $responsavel) : ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($responsavel['tipo_responsavel'] == 'tecnico') : ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Técnico
                                            </span>
                                        <?php else : ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Legal
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($responsavel['nome']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($responsavel['cpf']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div><?php echo htmlspecialchars($responsavel['email']); ?></div>
                                        <?php if (!empty($responsavel['telefone'])) : ?>
                                            <div><?php echo htmlspecialchars($responsavel['telefone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($responsavel['tipo_responsavel'] == 'tecnico' && !empty($responsavel['conselho'])) : ?>
                                            <?php echo htmlspecialchars($responsavel['conselho']); ?> - 
                                            <?php echo htmlspecialchars($responsavel['numero_registro_conselho']); ?>
                                        <?php else : ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="font-medium">
                                            <?php echo htmlspecialchars($responsavel['nome_fantasia'] ?: $responsavel['razao_social']); ?>
                                        </div>
                                        <div>
                                            <?php echo !empty($responsavel['cnpj']) ? htmlspecialchars($responsavel['cnpj']) : htmlspecialchars($responsavel['estabelecimento_cpf']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="../Estabelecimento/detalhes_estabelecimento.php?id=<?php echo $responsavel['estabelecimento_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-building mr-1"></i>Ver Estabelecimento
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                <div class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Mostrando <span class="font-medium"><?php echo $offset + 1; ?></span> a 
                        <span class="font-medium"><?php echo min($offset + $registrosPorPagina, $totalRegistros); ?></span> de 
                        <span class="font-medium"><?php echo $totalRegistros; ?></span> resultados
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($paginaAtual > 1): ?>
                            <a href="?tipo=<?php echo $tipo; ?>&busca=<?php echo urlencode($busca); ?>&pagina=1" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                <span class="sr-only">Primeira</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <a href="?tipo=<?php echo $tipo; ?>&busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $paginaAtual - 1; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $inicio = max(1, $paginaAtual - 2);
                        $fim = min($totalPaginas, $paginaAtual + 2);
                        
                        for ($i = $inicio; $i <= $fim; $i++): ?>
                            <a href="?tipo=<?php echo $tipo; ?>&busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $i; ?>" 
                               class="px-3 py-1 <?php echo $i == $paginaAtual ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'; ?> rounded-md hover:bg-gray-200">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($paginaAtual < $totalPaginas): ?>
                            <a href="?tipo=<?php echo $tipo; ?>&busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $paginaAtual + 1; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                Próxima
                            </a>
                            <a href="?tipo=<?php echo $tipo; ?>&busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $totalPaginas; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                <span class="sr-only">Última</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else : ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Nenhum responsável encontrado com os filtros selecionados.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html> 