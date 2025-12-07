<?php
session_start();
ob_start(); // Inicia o buffer de saída

include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$errors = []; // Inicializa a variável $errors

$municipio_usuario = $_SESSION['user']['municipio'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $municipio = $municipio_usuario; // Define o município com base no usuário logado
    $grupo_risco_id = $_POST['grupo_risco_id'];
    $cnaes = $_POST['cnaes'];
    $cnaes_array = explode(',', $cnaes);

    foreach ($cnaes_array as $cnae) {
        $cnae = trim($cnae);

        // Verifica se o CNAE já existe no mesmo grupo de risco e município
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM atividade_grupo_risco WHERE cnae = ? AND grupo_risco_id = ? AND municipio = ?");
        $check_stmt->bind_param("sis", $cnae, $grupo_risco_id, $municipio);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            $errors[] = "A atividade $cnae já está adicionada ao grupo de risco para o município selecionado.";
        } else {
            $stmt = $conn->prepare("INSERT INTO atividade_grupo_risco (cnae, grupo_risco_id, municipio) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $cnae, $grupo_risco_id, $municipio);

            if (!$stmt->execute()) {
                $errors[] = "Erro ao adicionar a atividade $cnae ao grupo de risco: " . $conn->error;
            }

            $stmt->close();
        }
    }

    if (empty($errors)) {
        // Limpa o buffer de saída antes de enviar o header
        ob_clean();
        header("Location: adicionar_atividade_grupo_risco.php?success=Atividades adicionadas ao grupo de risco com sucesso.");
        exit();
    }
}

// Consulta para obter os grupos de risco
$gruposRisco = $conn->query("SELECT id, descricao FROM grupo_risco");

?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Adicionar Atividades ao Grupo de Risco</h1>
                    <p class="text-gray-600 mt-1">Gerencie as atividades por grupo de risco e município</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Sistema Ativo</span>
                </div>
            </div>
        </div>

        <!-- Alerts Section -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Erros encontrados:</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <?php foreach ($errors as $error) {
                                echo "<p>• " . htmlspecialchars($error ?? '', ENT_QUOTES, 'UTF-8') . "</p>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            <?php echo htmlspecialchars($_GET['success'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <div class="border-b border-gray-200 pb-4 mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Adicionar Nova Atividade</h2>
                <p class="text-sm text-gray-600 mt-1">Preencha os campos abaixo para adicionar atividades ao grupo de risco</p>
            </div>

            <form action="adicionar_atividade_grupo_risco.php" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="municipio" class="block text-sm font-medium text-gray-700 mb-2">
                            Município
                        </label>
                        <input 
                            type="text" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 text-gray-500 cursor-not-allowed focus:outline-none" 
                            id="municipio" 
                            name="municipio" 
                            value="<?php echo htmlspecialchars($municipio_usuario, ENT_QUOTES, 'UTF-8'); ?>" 
                            readonly
                        >
                    </div>

                    <div>
                        <label for="grupo_risco_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Grupo de Risco *
                        </label>
                        <select 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                            id="grupo_risco_id" 
                            name="grupo_risco_id" 
                            required
                        >
                            <option value="">Selecione um grupo de risco</option>
                            <?php while ($grupo = $gruposRisco->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($grupo['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($grupo['descricao'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="cnaes" class="block text-sm font-medium text-gray-700 mb-2">
                        CNAEs *
                        <span class="text-xs text-gray-500">(somente números separados por vírgula, até 7 dígitos cada)</span>
                    </label>
                    <input
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        id="cnaes"
                        name="cnaes"
                        pattern="^(\d{1,7})(,\d{1,7})*$"
                        title="Insira somente números separados por vírgula, até 7 dígitos cada."
                        placeholder="Ex: 1234567, 2345678, 3456789"
                        required
                    >
                    <p class="text-xs text-gray-500 mt-1">Exemplo: 1234567, 2345678, 3456789</p>
                </div>

                <div class="flex justify-end pt-4">
                    <button 
                        type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Adicionar Atividades
                    </button>
                </div>
            </form>
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Lista de Atividades por Grupo de Risco</h2>
                <p class="text-sm text-gray-600 mt-1">Visualize e gerencie as atividades cadastradas</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Município</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grupo de Risco</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CNAEs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $atividades = $conn->query("
                            SELECT 
                                agr.municipio, 
                                gr.descricao AS grupo_risco, 
                                GROUP_CONCAT(agr.cnae ORDER BY agr.cnae SEPARATOR ', ') AS cnaes
                            FROM 
                                atividade_grupo_risco agr
                            JOIN 
                                grupo_risco gr ON agr.grupo_risco_id = gr.id
                            GROUP BY 
                                agr.municipio, gr.descricao
                        ");

                        if ($atividades && $atividades->num_rows > 0) {
                            while ($atividade = $atividades->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                            <?php echo htmlspecialchars($atividade['municipio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <?php echo htmlspecialchars($atividade['grupo_risco'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($atividade['cnaes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($atividade['cnaes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="editar_atividade_grupo_risco.php?municipio=<?php echo htmlspecialchars($atividade['municipio'], ENT_QUOTES, 'UTF-8'); ?>&grupo_risco=<?php echo htmlspecialchars($atividade['grupo_risco'], ENT_QUOTES, 'UTF-8'); ?>" 
                                               class="text-yellow-600 hover:text-yellow-900 transition-colors p-1 rounded hover:bg-yellow-50" 
                                               title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            <a href="excluir_atividade_grupo_risco.php?municipio=<?php echo htmlspecialchars($atividade['municipio'], ENT_QUOTES, 'UTF-8'); ?>&grupo_risco=<?php echo htmlspecialchars($atividade['grupo_risco'], ENT_QUOTES, 'UTF-8'); ?>" 
                                               class="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50" 
                                               title="Excluir"
                                               onclick="return confirm('Tem certeza que deseja excluir esta atividade?')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                        <?php endwhile;
                        } else { ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <h3 class="text-sm font-medium text-gray-900 mb-1">Nenhuma atividade encontrada</h3>
                                        <p class="text-sm text-gray-500">Comece adicionando uma nova atividade ao grupo de risco.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('cnaes').addEventListener('input', function(e) {
        // Substitui caracteres inválidos por vazio
        e.target.value = e.target.value.replace(/[^0-9,]/g, '');

        // Divide os CNAEs e valida comprimento
        const cnaes = e.target.value.split(',');
        if (cnaes.some(cnae => cnae.length > 7)) {
            // Cria um toast de aviso mais elegante
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-lg z-50';
            toast.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Cada CNAE deve ter no máximo 7 dígitos.</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
            
            e.target.value = cnaes.filter(cnae => cnae.length <= 7).join(',');
        }
    });

    // Adiciona feedback visual ao formulário
    document.querySelector('form').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Processando...
        `;
        submitBtn.disabled = true;
    });
</script>

<?php include '../footer.php'; ?>
<?php
ob_end_flush(); // Libera o buffer de saída
?>