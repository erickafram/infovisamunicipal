<?php

session_start();
ob_start();
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo_documento']) && isset($_POST['conteudo'])) {
    $municipio = $_SESSION['user']['municipio'];
    $tipo_documento = $_POST['tipo_documento'];
    $conteudo = $_POST['conteudo'];

    // Verificar se já existe um modelo com o mesmo tipo de documento no mesmo município
    $stmt = $conn->prepare("SELECT COUNT(*) FROM modelos_documentos WHERE tipo_documento = ? AND municipio = ?");
    $stmt->bind_param('ss', $tipo_documento, $municipio);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $message = 'Já existe um modelo de documento com este tipo no seu município!';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO modelos_documentos (tipo_documento, conteudo, municipio) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $tipo_documento, $conteudo, $municipio);

        if ($stmt->execute()) {
            $message = 'Modelo de documento inserido com sucesso!';
            $messageType = 'success';
        } else {
            $message = 'Erro ao inserir modelo de documento: ' . $stmt->error;
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserir Modelo de Documento</title>
    <style>
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .hover-scale {
            transition: transform 0.2s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
        }
        
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .select-custom {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
    </style>
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
    <!-- Header com navegação -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <h1 class="text-2xl font-bold text-gray-900">Inserir Modelo de Documento</h1>
                    </div>
                </div>
                <nav class="flex space-x-4">
                    <a href="listar_modelos.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                        Voltar à Lista
                    </a>
                </nav>
            </div>
        </div>
    </div>

    <!-- Conteúdo principal -->
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Cards informativos -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Novo Modelo</dt>
                                    <dd class="text-lg font-medium text-gray-900">Documento</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Município</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['user']['municipio'], ENT_QUOTES, 'UTF-8'); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow rounded-lg hover-scale">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Editor</dt>
                                    <dd class="text-lg font-medium text-green-600">TinyMCE</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulário -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg fade-in">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Formulário de Inserção</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Preencha os dados para criar um novo modelo de documento</p>
                </div>
                
                <div class="px-4 py-5 sm:p-6">
                    <form action="inserir_modelo.php" method="POST" id="form-modelo">
                        <div class="space-y-6">
                            <!-- Tipo de Documento -->
                            <div>
                                <label for="tipo_documento" class="block text-sm font-medium text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a.997.997 0 01-1.414 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                        Tipo de Documento
                                        <span class="text-red-500 ml-1">*</span>
                                    </div>
                                </label>
                                <select class="select-custom mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                        id="tipo_documento" 
                                        name="tipo_documento" 
                                        required>
                                    <option value="">Selecione o tipo de documento</option>
                                    <option value="ALVARÁ SANITÁRIO">ALVARÁ SANITÁRIO</option>
                                    <option value="AUTO DE INFRAÇÃO">AUTO DE INFRAÇÃO</option>
                                    <option value="CERTIDÃO">CERTIDÃO</option>
                                    <option value="DECLARAÇÃO">DECLARAÇÃO</option>
                                    <option value="DESPACHO">DESPACHO</option>
                                    <option value="INSTRUÇÃO NORMATIVA">INSTRUÇÃO NORMATIVA</option>
                                    <option value="LAUDO TÉCNICO">LAUDO TÉCNICO</option>
                                    <option value="MEMORANDO">MEMORANDO</option>
                                    <option value="NOTIFICAÇÃO">NOTIFICAÇÃO</option>
                                    <option value="ORDEM DE SERVIÇO">ORDEM DE SERVIÇO</option>
                                    <option value="ORIENTAÇÃO SANITÁRIA">ORIENTAÇÃO SANITÁRIA</option>
                                    <option value="PARECER TÉCNICO">PARECER TÉCNICO</option>
                                    <option value="RELATÓRIO TÉCNICO">RELATÓRIO TÉCNICO</option>
                                    <option value="REQUERIMENTO">REQUERIMENTO</option>
                                    <option value="TERMO DE AVALIAÇÃO">TERMO DE AVALIAÇÃO</option>
                                    <option value="TERMO DE APREENSÃO">TERMO DE APREENSÃO</option>
                                    <option value="TERMO DE COMPROMISSO">TERMO DE COMPROMISSO</option>
                                    <option value="TERMO DE VISTORIA">TERMO DE VISTORIA</option>
                                </select>
                                <p class="mt-2 text-sm text-gray-500">Escolha o tipo de documento que será criado</p>
                            </div>

                            <!-- Conteúdo do Modelo -->
                            <div>
                                <label for="conteudo" class="block text-sm font-medium text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Conteúdo do Modelo
                                        <span class="text-red-500 ml-1">*</span>
                                    </div>
                                </label>
                                <div class="mt-1">
                                    <textarea class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                              id="conteudo" 
                                              name="conteudo" 
                                              rows="15" 
                                              placeholder="Digite o conteúdo do modelo de documento..."
                                              required></textarea>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Use o editor para formatar o conteúdo do documento. Você pode inserir imagens e aplicar formatação rica.</p>
                            </div>
                        </div>

                        <!-- Botões de ação -->
                        <div class="mt-8 flex justify-end space-x-3">
                            <a href="listar_modelos.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Cancelar
                            </a>
                            <button type="submit" 
                                    id="btn-submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span id="btn-text">Inserir Modelo</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast de notificação -->
    <div id="toast" class="fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
        <div class="flex items-center">
            <svg id="toast-icon" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span id="toast-message">Operação realizada com sucesso!</span>
        </div>
    </div>

    <script>
        function mostrarToast(mensagem, tipo = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            const toastIcon = document.getElementById('toast-icon');
            
            toastMessage.textContent = mensagem;
            
            if (tipo === 'error') {
                toast.className = toast.className.replace('bg-green-500 text-white', 'bg-red-500 text-white');
                toast.classList.add('bg-red-500', 'text-white');
                toastIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
            } else {
                toast.className = toast.className.replace('bg-red-500 text-white', 'bg-green-500 text-white');
                toast.classList.add('bg-green-500', 'text-white');
                toastIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
            }
            
            toast.classList.remove('translate-x-full');
            
            setTimeout(() => {
                toast.classList.add('translate-x-full');
            }, 4000);
        }
        
        // Validação do formulário
        document.getElementById('form-modelo').addEventListener('submit', function(e) {
            const tipoDocumento = document.getElementById('tipo_documento').value;
            const conteudo = tinymce.get('conteudo').getContent();
            
            if (!tipoDocumento) {
                e.preventDefault();
                mostrarToast('Por favor, selecione o tipo de documento.', 'error');
                return;
            }
            
            if (!conteudo.trim()) {
                e.preventDefault();
                mostrarToast('Por favor, insira o conteúdo do modelo.', 'error');
                return;
            }
            
            // Mostrar loading no botão
            const btnSubmit = document.getElementById('btn-submit');
            const btnText = document.getElementById('btn-text');
            
            btnSubmit.classList.add('btn-loading');
            btnText.textContent = '';
            btnSubmit.disabled = true;
        });
        
        // Mostrar toast se houver mensagem
        <?php if (!empty($message)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            mostrarToast('<?php echo addslashes($message); ?>', '<?php echo $messageType; ?>');
        });
        <?php endif; ?>
        
        // Animação de entrada para o formulário
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.fade-in');
            form.style.opacity = '0';
            form.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                form.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Validação em tempo real
        document.getElementById('tipo_documento').addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('border-red-300');
                this.classList.add('border-green-300');
            }
        });
    </script>

    <?php include '../footer.php'; ?>
</body>

</html>