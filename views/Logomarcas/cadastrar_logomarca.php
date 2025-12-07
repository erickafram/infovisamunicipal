<?php
session_start();
require_once '../../conf/database.php'; // Inclua o arquivo de configuração do banco de dados
require_once '../../controllers/LogomarcaController.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$controller = new LogomarcaController($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['acao'] == 'cadastrar') {
        // Verificar se o município enviado no formulário corresponde ao município do usuário logado
        if ($_POST['municipio'] !== $_SESSION['user']['municipio']) {
            header("Location: cadastrar_logomarca.php?error=" . urlencode("Você só pode cadastrar logomarca para o seu município."));
            exit();
        }
        $controller->create();
    } elseif ($_POST['acao'] == 'atualizar') {
        // Verificar se o município enviado no formulário corresponde ao município do usuário logado
        if ($_POST['municipio'] !== $_SESSION['user']['municipio']) {
            header("Location: cadastrar_logomarca.php?error=" . urlencode("Você só pode atualizar logomarca para o seu município."));
            exit();
        }
        $controller->update();
    }
}
include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Logomarca</title>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Main Container -->
<div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Header Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Cadastrar Logomarca</h1>
                    <p class="text-sm text-gray-600 mt-1">Gerencie a logomarca do seu município</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-red-800 font-medium"><?php echo htmlspecialchars($_GET['error']); ?></p>
            </div>
        </div>
    <?php elseif (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-green-800 font-medium"><?php echo htmlspecialchars($_GET['success']); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Informações da Logomarca</h2>
            <p class="text-sm text-gray-600 mt-1">Preencha os dados para cadastrar a logomarca</p>
        </div>
        
        <div class="p-6">
            <form action="cadastrar_logomarca.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Município -->
                <div>
                    <label for="municipio" class="block text-sm font-medium text-gray-700 mb-2">
                        Município
                        <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="text" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors" 
                               id="municipio" 
                               name="municipio" 
                               value="<?php echo $_SESSION['user']['municipio']; ?>" 
                               readonly 
                               required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Campo bloqueado para seu município</p>
                </div>

                <!-- Logomarca -->
                <div>
                    <label for="logomarca" class="block text-sm font-medium text-gray-700 mb-2">
                        Arquivo da Logomarca
                        <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="file" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" 
                               id="logomarca" 
                               name="logomarca" 
                               accept="image/*"
                               required>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Formatos aceitos: JPG, PNG, GIF (máx. 2MB)</p>
                </div>

                <!-- Espaçamento -->
                <div>
                    <label for="espacamento" class="block text-sm font-medium text-gray-700 mb-2">
                        Espaçamento abaixo da logomarca
                        <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors" 
                               id="espacamento" 
                               name="espacamento" 
                               value="40" 
                               min="0"
                               max="200"
                               required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 text-sm">px</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Valor em pixels (0-200)</p>
                </div>

                <input type="hidden" name="acao" value="cadastrar">
                
                <!-- Submit Button -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button type="button" 
                            onclick="window.history.back()" 
                            class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-8 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span>Cadastrar Logomarca</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Notification Script -->
<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('[class*="bg-red-50"], [class*="bg-green-50"]');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease-out';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// File input preview
document.getElementById('logomarca').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // You can add preview functionality here if needed
            console.log('File selected:', file.name);
        };
        reader.readAsDataURL(file);
    }
});
</script>

</body>
</html>