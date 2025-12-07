<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Parâmetros de filtro
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t');
$usuario_id = isset($_GET['usuario_id']) ? $_GET['usuario_id'] : null;

// Consulta SQL para obter ações
$query = "
    SELECT
        u.id AS usuario_id,
        u.nome_completo AS tecnico,
        tae.descricao AS acao_descricao,
        COALESCE(gr.descricao, 'Sem Grupo de Risco') AS grupo_risco_descricao,
        pt.pontuacao,
        pt.data,
        COALESCE(e.nome_fantasia, COALESCE(e.nome, 'Sem Estabelecimento')) AS estabelecimento,
        COALESCE(e.id, 0) AS estabelecimento_id,
        e.tipo_pessoa,
        CASE 
            WHEN gr.descricao = 'GRUPO 1' THEN 'Baixo'
            WHEN gr.descricao = 'GRUPO 2' THEN 'Médio'
            WHEN gr.descricao = 'GRUPO 3' THEN 'Alto'
            ELSE 'Não definido'
        END AS grupo
    FROM pontuacao_tecnicos pt
    INNER JOIN usuarios u ON pt.tecnico_id = u.id
    INNER JOIN ordem_servico os ON pt.ordem_id = os.id
    LEFT JOIN estabelecimentos e ON os.estabelecimento_id = e.id
    LEFT JOIN tipos_acoes_executadas tae ON pt.acao_id = tae.id
    LEFT JOIN grupo_risco gr ON gr.id = (
        CASE 
            WHEN e.tipo_pessoa = 'fisica' THEN (
                SELECT MAX(agr.grupo_risco_id)
                FROM estabelecimento_cnaes ec
                JOIN atividade_grupo_risco agr ON ec.cnae = agr.cnae AND e.municipio = agr.municipio
                WHERE ec.estabelecimento_id = e.id
            )
            ELSE (
                SELECT MAX(agr.grupo_risco_id)
                FROM atividade_grupo_risco agr
                WHERE agr.municipio = e.municipio
                AND (
                    agr.cnae = e.cnae_fiscal
                    OR agr.cnae IN (
                        SELECT cnae_sec
                        FROM JSON_TABLE(
                            e.cnaes_secundarios,
                            '$[*]' COLUMNS(cnae_sec VARCHAR(20) PATH '$.codigo')
                        ) jt_cnaes
                    )
                )
            )
        END
    )
    WHERE pt.data BETWEEN ? AND ?
";

if ($usuario_id) {
    $query .= " AND u.id = ?";
}

$query .= " ORDER BY u.nome_completo, pt.data ASC";

$stmt = $conn->prepare($query);

if ($usuario_id) {
    $stmt->bind_param("ssi", $data_inicio, $data_fim, $usuario_id);
} else {
    $stmt->bind_param("ss", $data_inicio, $data_fim);
}

$stmt->execute();
$result = $stmt->get_result();
$acoes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Consulta para obter usuários para o filtro
$usersQuery = "SELECT id, nome_completo FROM usuarios ORDER BY nome_completo ASC";
$usersResult = $conn->query($usersQuery);
$usuarios = [];
while ($user = $usersResult->fetch_assoc()) {
    $usuarios[] = $user;
}

// Calcular total de pontuação
$totalPontuacao = !empty($acoes) ? array_sum(array_column($acoes, 'pontuacao')) : 0;

include '../header.php';
?>

<div class="container mx-auto px-4 py-6 mt-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Relatório de Ações e Pontuações</h1>
    </div>
    
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-6">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" 
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                    </div>
                    
                    <div>
                        <label for="data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                        <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" 
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                    </div>
                    
                    <div>
                        <label for="usuario_id" class="block text-sm font-medium text-gray-700 mb-1">Selecione o Usuário (opcional)</label>
                        <select id="usuario_id" name="usuario_id" 
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Todos os Usuários</option>
                            <?php foreach ($usuarios as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($usuario_id == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                        </svg>
                        Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resultados -->
    <?php if (!empty($acoes)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <div class="mb-4">
                <h2 class="text-lg font-medium text-gray-900">
                    Ações Realizadas entre: <?php echo htmlspecialchars(date('d/m/Y', strtotime($data_inicio))) . " e " . htmlspecialchars(date('d/m/Y', strtotime($data_fim))); ?>
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    <?php echo count($acoes); ?> resultado(s) encontrado(s)
                </p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Técnico</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Ação</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Estabelecimento</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Grupo de Risco</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Pontuação</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Data</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Grupo</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($acoes as $acao): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($acao['tecnico']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="break-words max-w-[100px]">
                                    <?php echo htmlspecialchars($acao['acao_descricao']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="truncate max-w-[160px]" title="<?php echo htmlspecialchars($acao['estabelecimento']); ?>">
                                    <?php if ($acao['estabelecimento_id'] > 0): ?>
                                        <a href="../Estabelecimento/<?php echo $acao['tipo_pessoa'] == 'fisica' ? 'detalhes_pessoa_fisica' : 'detalhes_estabelecimento'; ?>.php?id=<?php echo $acao['estabelecimento_id']; ?>" class="text-blue-600 hover:text-blue-800 hover:underline">
                                            <?php echo htmlspecialchars($acao['estabelecimento']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($acao['estabelecimento']); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($acao['grupo_risco_descricao']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($acao['pontuacao']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('d/m/Y', strtotime($acao['data']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($acao['grupo']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-sm font-medium text-gray-900">Total de Pontuação:</td>
                            <td colspan="3" class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo number_format($totalPontuacao, 0, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 text-center">
            <div class="flex flex-col items-center justify-center py-10">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma ação encontrada</h3>
                <p class="mt-1 text-sm text-gray-500">Não foram encontradas ações para os filtros selecionados.</p>
                <div class="mt-6">
                    <button type="button" onclick="window.location.href='relatorio_acoes_usuario.php'" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Limpar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>