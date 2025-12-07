<?php
session_start();

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Parâmetros de filtro
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y'); // Ano selecionado
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m'); // Mês selecionado (convertido para inteiro)
$usuario_id = isset($_GET['usuario_id']) ? $_GET['usuario_id'] : null; // Usuário selecionado

// Meses para exibição
$meses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'
];

// Consulta para obter pontuação dos usuários
$query = "
    SELECT 
        u.id, 
        u.nome_completo, 
        COALESCE(SUM(pt.pontuacao), 0) AS pontuacao_total
    FROM usuarios u
    LEFT JOIN pontuacao_tecnicos pt ON u.id = pt.tecnico_id
       AND MONTH(pt.data) = ?
       AND YEAR(pt.data) = ?
";

if ($usuario_id) {
    $query .= " WHERE u.id = ? ";
}

$query .= " GROUP BY u.id, u.nome_completo ORDER BY pontuacao_total DESC";

$stmt = $conn->prepare($query);
if ($usuario_id) {
    $stmt->bind_param("iii", $mes, $ano, $usuario_id);
} else {
    $stmt->bind_param("ii", $mes, $ano);
}

$stmt->execute();
$result = $stmt->get_result();
$usuarios = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Consulta para obter a lista de usuários para o filtro
$usersQuery = "SELECT id, nome_completo FROM usuarios ORDER BY nome_completo ASC";
$usersResult = $conn->query($usersQuery);
$all_usuarios = [];
while ($user = $usersResult->fetch_assoc()) {
    $all_usuarios[] = $user;
}

// Calcular total geral de pontuação
$totalGeral = !empty($usuarios) ? array_sum(array_column($usuarios, 'pontuacao_total')) : 0;

include '../header.php';
?>

<div class="container mx-auto px-4 py-6 mt-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Relatório de Pontuação dos Usuários</h1>
    </div>
    
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-6">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="ano" class="block text-sm font-medium text-gray-700 mb-1">Selecione o Ano</label>
                        <select id="ano" name="ano" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                            <?php
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= 2024; $year--) {
                                echo "<option value=\"$year\"" . ($ano == $year ? ' selected' : '') . ">$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="mes" class="block text-sm font-medium text-gray-700 mb-1">Selecione o Mês</label>
                        <select id="mes" name="mes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                            <?php
                            foreach ($meses as $numero => $nome) {
                                echo "<option value=\"$numero\"" . ($mes == $numero ? ' selected' : '') . ">$nome</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="usuario_id" class="block text-sm font-medium text-gray-700 mb-1">Selecione o Usuário (opcional)</label>
                        <select id="usuario_id" name="usuario_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Todos os Usuários</option>
                            <?php foreach ($all_usuarios as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($usuario_id == $user['id'] ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($user['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-sm">
                        Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resultados -->
    <?php if (!empty($usuarios)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <div class="mb-4">
                <h2 class="text-lg font-medium text-gray-900">
                    Pontuações do Ano: <?php echo htmlspecialchars($ano); ?> - Mês: <?php echo htmlspecialchars(isset($meses[$mes]) ? $meses[$mes] : "$mes"); ?>
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    <?php echo count($usuarios); ?> usuário(s) encontrado(s)
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="pontuacaoTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Pontuação Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($usuario['id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($usuario['nome_completo']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium"><?php echo number_format($usuario['pontuacao_total'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="2" class="px-6 py-4 text-sm font-medium text-gray-900">Total Geral:</td>
                            <td class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo number_format($totalGeral, 0, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="generatePDF()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 text-sm flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd" />
                    </svg>
                    Gerar PDF
                </button>
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
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma pontuação encontrada</h3>
                <p class="mt-1 text-sm text-gray-500">Não foram encontradas pontuações para os filtros selecionados.</p>
                <div class="mt-6">
                    <button type="button" onclick="window.location.href='relatorio_pontuacao.php'" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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

<!-- Script para gerar PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
<script>
    function generatePDF() {
        const { jsPDF } = window.jspdf;

        const meses = [
            "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho",
            "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"
        ];

        // Criar PDF
        const doc = new jsPDF();

        // Título do PDF
        doc.setFontSize(16);
        doc.text(`Relatório de Pontuação - ${meses[<?php echo $mes; ?> - 1]} de <?php echo $ano; ?>`, 14, 20);

        // Adicionar data atual
        const dataAtual = new Date().toLocaleDateString();
        doc.setFontSize(12);
        doc.text(`Gerado em: ${dataAtual}`, 14, 30);

        // Obter dados da tabela e adicionar ao PDF usando AutoTable
        doc.autoTable({
            html: '#pontuacaoTable',
            startY: 40,
            styles: {
                fontSize: 10,
                cellPadding: 3,
                lineWidth: 0.5,
                lineColor: [0, 0, 0]
            },
            headStyles: {
                fillColor: [66, 133, 244],
                textColor: 255,
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [240, 240, 240]
            },
            footStyles: {
                fontStyle: 'bold'
            }
        });

        // Salvar o PDF
        doc.save(`relatorio_pontuacao_${<?php echo $mes; ?>}_${<?php echo $ano; ?>}.pdf`);
    }
</script>

<?php include '../footer.php'; ?>