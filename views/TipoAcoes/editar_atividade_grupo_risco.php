<?php
session_start();
ob_start(); // Inicia o buffer de saída

include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$errors = [];
$municipio = $_GET['municipio'];
$grupo_risco = $_GET['grupo_risco'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $grupo_risco_id = $_POST['grupo_risco_id'];
    $cnaes = $_POST['cnae'];
    $cnaes_array = explode(',', $cnaes);

    // Primeiro, removemos todas as atividades antigas do grupo de risco e município
    $delete_stmt = $conn->prepare("DELETE FROM atividade_grupo_risco WHERE grupo_risco_id = ? AND municipio = ?");
    $delete_stmt->bind_param("is", $grupo_risco_id, $municipio);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Depois, adicionamos as novas atividades
    foreach ($cnaes_array as $cnae) {
        $cnae = trim($cnae);

        $stmt = $conn->prepare("INSERT INTO atividade_grupo_risco (cnae, grupo_risco_id, municipio) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $cnae, $grupo_risco_id, $municipio);

        if (!$stmt->execute()) {
            $errors[] = "Erro ao atualizar a atividade $cnae: " . $conn->error;
        }

        $stmt->close();
    }

    if (empty($errors)) {
        ob_clean();
        header("Location: adicionar_atividade_grupo_risco.php?success=Atividade atualizada com sucesso.");
        exit();
    }
}

// Obtenha as atividades do grupo de risco e município
$atividadesExistentes = $conn->query("SELECT cnae FROM atividade_grupo_risco WHERE grupo_risco_id = (SELECT id FROM grupo_risco WHERE descricao = '$grupo_risco') AND municipio = '$municipio'");
$cnaes = [];
while ($row = $atividadesExistentes->fetch_assoc()) {
    $cnaes[] = $row['cnae'];
}
$cnaes_str = implode(', ', $cnaes);

$gruposRisco = $conn->query("SELECT id, descricao FROM grupo_risco");
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Editar Atividade do Grupo de Risco</h1>
                    <p class="text-gray-600 mt-1">Modifique as atividades vinculadas ao grupo de risco</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="adicionar_atividade_grupo_risco.php" 
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar
                    </a>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                        <span class="text-sm text-gray-600">Editando</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Município</h3>
                        <p class="text-sm text-blue-600"><?php echo htmlspecialchars($municipio, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Grupo de Risco Atual</h3>
                        <p class="text-sm text-green-600"><?php echo htmlspecialchars($grupo_risco, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
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
                                echo "<p>• " . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Formulário de Edição</h2>
                <p class="text-sm text-gray-600 mt-1">Atualize as informações da atividade do grupo de risco</p>
            </div>
            
            <div class="p-6">
                <form action="editar_atividade_grupo_risco.php?municipio=<?php echo $municipio; ?>&grupo_risco=<?php echo $grupo_risco; ?>" method="POST" class="space-y-6">
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
                            <?php while ($grupo = $gruposRisco->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($grupo['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                        <?php if ($grupo['descricao'] == $grupo_risco) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($grupo['descricao'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="cnae" class="block text-sm font-medium text-gray-700 mb-2">
                            CNAEs *
                            <span class="text-xs text-gray-500">(somente números separados por vírgula, até 7 dígitos cada)</span>
                        </label>
                        <textarea
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                            id="cnae"
                            name="cnae"
                            rows="4"
                            value="<?php echo htmlspecialchars($cnaes_str, ENT_QUOTES, 'UTF-8'); ?>"
                            pattern="^(\d{1,7})(,\d{1,7})*$"
                            title="Insira somente números separados por vírgula, até 7 dígitos cada."
                            placeholder="Ex: 1234567, 2345678, 3456789"
                            required><?php echo htmlspecialchars($cnaes_str, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Exemplo: 1234567, 2345678, 3456789</p>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <span id="cnae-count"><?php echo count($cnaes); ?></span> CNAEs cadastrados
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                        <a href="adicionar_atividade_grupo_risco.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancelar
                        </a>
                        
                        <button 
                            type="submit" 
                            class="inline-flex items-center px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Atualizar Atividade
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Help Section -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Dicas importantes:</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Os CNAEs devem conter apenas números (sem pontos ou traços)</li>
                            <li>Cada CNAE pode ter no máximo 7 dígitos</li>
                            <li>Separe múltiplos CNAEs com vírgula</li>
                            <li>Ao atualizar, todos os CNAEs anteriores serão substituídos pelos novos</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const cnaeInput = document.getElementById('cnae');
    const cnaeCount = document.getElementById('cnae-count');
    
    function updateCnaeCount() {
        const cnaes = cnaeInput.value.split(',').filter(cnae => cnae.trim().length > 0);
        cnaeCount.textContent = cnaes.length;
    }
    
    cnaeInput.addEventListener('input', function(e) {
        // Substitui caracteres inválidos por vazio
        e.target.value = e.target.value.replace(/[^0-9,]/g, '');

        // Divide os CNAEs e valida comprimento
        const cnaes = e.target.value.split(',');
        if (cnaes.some(cnae => cnae.trim().length > 7)) {
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
            
            e.target.value = cnaes.filter(cnae => cnae.trim().length <= 7).join(',');
        }
        
        updateCnaeCount();
    });

    // Adiciona feedback visual ao formulário
    document.querySelector('form').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Atualizando...
        `;
        submitBtn.disabled = true;
    });
    
    // Inicializa o contador
    updateCnaeCount();
</script>

<?php include '../footer.php'; ?>
<?php
ob_end_flush(); // Libera o buffer de saída
?>