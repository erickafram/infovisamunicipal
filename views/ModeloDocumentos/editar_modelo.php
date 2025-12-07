<?php
session_start();
ob_start();
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

if (!isset($_GET['id'])) {
    echo "ID do modelo de documento não fornecido!";
    exit();
}

$modelo_id = $_GET['id'];
$message = '';
$municipio = $_SESSION['user']['municipio'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo_documento']) && isset($_POST['conteudo'])) {
    $tipo_documento = $_POST['tipo_documento'];
    $conteudo = $_POST['conteudo'];

    // Verificar se já existe um modelo com o mesmo tipo de documento no mesmo município (excluindo o próprio modelo que está sendo editado)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM modelos_documentos WHERE tipo_documento = ? AND municipio = ? AND id != ?");
    $stmt->bind_param('ssi', $tipo_documento, $municipio, $modelo_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4" role="alert"><strong>Erro:</strong> Já existe um modelo de documento com este tipo no seu município!</div>';
    } else {
        $stmt = $conn->prepare("UPDATE modelos_documentos SET tipo_documento = ?, conteudo = ? WHERE id = ?");
        $stmt->bind_param('ssi', $tipo_documento, $conteudo, $modelo_id);

        if ($stmt->execute()) {
            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4" role="alert"><strong>Sucesso:</strong> Modelo de documento atualizado com sucesso!</div>';
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4" role="alert"><strong>Erro:</strong> Erro ao atualizar modelo de documento: ' . $stmt->error . '</div>';
        }
    }
} else {
    // Carregar os dados do modelo de documento para exibição no formulário
    $stmt = $conn->prepare("SELECT tipo_documento, conteudo FROM modelos_documentos WHERE id = ? AND municipio = ?");
    $stmt->bind_param('is', $modelo_id, $municipio);
    $stmt->execute();
    $stmt->bind_result($tipo_documento, $conteudo);
    $stmt->fetch();
    $stmt->close();

    if (!$tipo_documento) {
        echo "Modelo de documento não encontrado ou não pertence ao seu município!";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Modelo de Documento</title>
    <script>
    tinymce.init({
        selector: '#conteudo',
        plugins: 'advlist autolink lists link image charmap print preview hr anchor pagebreak image imagetools',
        toolbar_mode: 'floating',
        toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | image',
        images_upload_url: 'upload_image.php',
        automatic_uploads: true,
        file_picker_types: 'image',
        file_picker_callback: function(cb, value, meta) {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');

            input.onchange = function() {
                var file = this.files[0];

                var reader = new FileReader();
                reader.onload = function() {
                    var id = 'blobid' + (new Date()).getTime();
                    var blobCache = tinymce.activeEditor.editorUpload.blobCache;
                    var base64 = reader.result.split(',')[1];
                    var blobInfo = blobCache.create(id, file, base64);
                    blobCache.add(blobInfo);

                    cb(blobInfo.blobUri(), {
                        title: file.name
                    });
                };
                reader.readAsDataURL(file);
            };

            input.click();
        }
    });
    </script>
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Header com informações -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white py-6 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Editar Modelo de Documento</h1>
                    <p class="text-blue-100 mt-1">Modifique o modelo de documento existente</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-500 bg-opacity-50 px-3 py-2 rounded-lg">
                        <span class="text-sm font-medium">ID: <?php echo htmlspecialchars($modelo_id); ?></span>
                    </div>
                    <a href="listar_modelos.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-blue-50 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar à Lista
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Card de Informações -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Informações Importantes</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Certifique-se de que o tipo de documento seja único para seu município</li>
                            <li>Use o editor de texto para formatar adequadamente o conteúdo</li>
                            <li>Você pode inserir imagens através do editor</li>
                            <li>As alterações serão aplicadas imediatamente após salvar</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensagens de Feedback -->
        <?php if ($message): ?>
            <div class="mb-6">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Formulário Principal -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-edit text-blue-600 mr-3"></i>
                    Formulário de Edição
                </h2>
            </div>

            <form action="editar_modelo.php?id=<?php echo $modelo_id; ?>" method="POST" class="p-6 space-y-6" id="editForm">
                <!-- Tipo de Documento -->
                <div class="space-y-2">
                    <label for="tipo_documento" class="block text-sm font-medium text-gray-700 flex items-center">
                        <i class="fas fa-file-alt text-gray-400 mr-2"></i>
                        Tipo de Documento
                        <span class="text-red-500 ml-1">*</span>
                    </label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" 
                            id="tipo_documento" name="tipo_documento" required>
                        <option value="">Selecione o tipo de documento</option>
                        <option value="ALVARÁ SANITÁRIO" <?php if ($tipo_documento == 'ALVARÁ SANITÁRIO') echo 'selected'; ?>>ALVARÁ SANITÁRIO</option>
                        <option value="AUTO DE INFRAÇÃO" <?php if ($tipo_documento == 'AUTO DE INFRAÇÃO') echo 'selected'; ?>>AUTO DE INFRAÇÃO</option>
                        <option value="CERTIDÃO" <?php if ($tipo_documento == 'CERTIDÃO') echo 'selected'; ?>>CERTIDÃO</option>
                        <option value="DECLARAÇÃO" <?php if ($tipo_documento == 'DECLARAÇÃO') echo 'selected'; ?>>DECLARAÇÃO</option>
                        <option value="DESPACHO" <?php if ($tipo_documento == 'DESPACHO') echo 'selected'; ?>>DESPACHO</option>
                        <option value="INSTRUÇÃO NORMATIVA" <?php if ($tipo_documento == 'INSTRUÇÃO NORMATIVA') echo 'selected'; ?>>INSTRUÇÃO NORMATIVA</option>
                        <option value="LAUDO TÉCNICO" <?php if ($tipo_documento == 'LAUDO TÉCNICO') echo 'selected'; ?>>LAUDO TÉCNICO</option>
                        <option value="MEMORANDO" <?php if ($tipo_documento == 'MEMORANDO') echo 'selected'; ?>>MEMORANDO</option>
                        <option value="NOTIFICAÇÃO" <?php if ($tipo_documento == 'NOTIFICAÇÃO') echo 'selected'; ?>>NOTIFICAÇÃO</option>
                        <option value="ORDEM DE SERVIÇO" <?php if ($tipo_documento == 'ORDEM DE SERVIÇO') echo 'selected'; ?>>ORDEM DE SERVIÇO</option>
                        <option value="ORIENTAÇÃO SANITÁRIA" <?php if ($tipo_documento == 'ORIENTAÇÃO SANITÁRIA') echo 'selected'; ?>>ORIENTAÇÃO SANITÁRIA</option>
                        <option value="PARECER TÉCNICO" <?php if ($tipo_documento == 'PARECER TÉCNICO') echo 'selected'; ?>>PARECER TÉCNICO</option>
                        <option value="RELATÓRIO TÉCNICO" <?php if ($tipo_documento == 'RELATÓRIO TÉCNICO') echo 'selected'; ?>>RELATÓRIO TÉCNICO</option>
                        <option value="REQUERIMENTO" <?php if ($tipo_documento == 'REQUERIMENTO') echo 'selected'; ?>>REQUERIMENTO</option>
                        <option value="TERMO DE AVALIAÇÃO" <?php if ($tipo_documento == 'TERMO DE AVALIAÇÃO') echo 'selected'; ?>>TERMO DE AVALIAÇÃO</option>
                        <option value="TERMO DE APREENSÃO" <?php if ($tipo_documento == 'TERMO DE APREENSÃO') echo 'selected'; ?>>TERMO DE APREENSÃO</option>
                        <option value="TERMO DE COMPROMISSO" <?php if ($tipo_documento == 'TERMO DE COMPROMISSO') echo 'selected'; ?>>TERMO DE COMPROMISSO</option>
                        <option value="TERMO DE VISTORIA" <?php if ($tipo_documento == 'TERMO DE VISTORIA') echo 'selected'; ?>>TERMO DE VISTORIA</option>
                    </select>
                    <p class="text-sm text-gray-500">Selecione o tipo de documento que este modelo representa</p>
                </div>

                <!-- Conteúdo do Modelo -->
                <div class="space-y-2">
                    <label for="conteudo" class="block text-sm font-medium text-gray-700 flex items-center">
                        <i class="fas fa-file-text text-gray-400 mr-2"></i>
                        Conteúdo do Modelo
                        <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="border border-gray-300 rounded-lg overflow-hidden">
                        <textarea class="w-full px-4 py-3 border-0 focus:ring-0 resize-none" 
                                  id="conteudo" name="conteudo" rows="15" 
                                  placeholder="Digite o conteúdo do modelo de documento..."><?php echo htmlspecialchars($conteudo); ?></textarea>
                    </div>
                    <p class="text-sm text-gray-500">Use o editor de texto para formatar o conteúdo do modelo</p>
                </div>

                <!-- Botões de Ação -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                    <button type="submit" 
                            class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-3 rounded-lg font-medium hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 flex items-center justify-center"
                            id="submitBtn">
                        <i class="fas fa-save mr-2"></i>
                        <span>Atualizar Modelo</span>
                        <div class="hidden ml-2" id="loadingSpinner">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                        </div>
                    </button>
                    <a href="listar_modelos.php" 
                       class="flex-1 sm:flex-none bg-gray-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200 flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>

        <!-- Card de Ajuda -->
        <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-green-50 to-green-100 px-6 py-4 border-b border-green-200">
                <h3 class="text-lg font-semibold text-green-800 flex items-center">
                    <i class="fas fa-question-circle text-green-600 mr-3"></i>
                    Ajuda e Dicas
                </h3>
            </div>
            <div class="p-6">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium text-gray-800 mb-2">Tipos de Documento</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Cada tipo deve ser único por município</li>
                            <li>• Escolha o tipo mais adequado ao conteúdo</li>
                            <li>• Mantenha consistência na nomenclatura</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-800 mb-2">Editor de Conteúdo</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Use formatação para melhor legibilidade</li>
                            <li>• Insira imagens quando necessário</li>
                            <li>• Mantenha o conteúdo organizado</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        // Validação do formulário
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const btnText = submitBtn.querySelector('span');
            
            // Mostrar loading
            loadingSpinner.classList.remove('hidden');
            btnText.textContent = 'Atualizando...';
            submitBtn.disabled = true;
        });

        // Validação em tempo real
        document.getElementById('tipo_documento').addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('border-red-300');
                this.classList.add('border-green-300');
            }
        });

        // Função para mostrar toast
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            
            toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full opacity-0`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.getElementById('toast-container').appendChild(toast);
            
            // Animar entrada
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 100);
            
            // Remover após 5 segundos
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000);
        }

        // Verificar se há mensagem de sucesso/erro
        <?php if (strpos($message, 'Sucesso') !== false): ?>
            showToast('Modelo atualizado com sucesso!', 'success');
        <?php elseif (strpos($message, 'Erro') !== false): ?>
            showToast('Erro ao atualizar modelo. Verifique os dados.', 'error');
        <?php endif; ?>
    </script>

    <?php include '../footer.php'; ?>
</body>

</html>