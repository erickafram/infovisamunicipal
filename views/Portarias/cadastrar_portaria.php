<?php
session_start();

// Verificar se o usuário está logado e tem permissão (níveis 1, 2, 3)
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
    header('Location: ../Usuario/login.php');
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Portaria.php';

$portaria = new Portaria($conn);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Debug temporário - remover após resolver o problema
    error_log("DEBUG: POST recebido");
    error_log("DEBUG: _FILES: " . print_r($_FILES, true));
    error_log("DEBUG: _POST: " . print_r($_POST, true));
    error_log("DEBUG: upload_max_filesize: " . ini_get('upload_max_filesize'));
    error_log("DEBUG: post_max_size: " . ini_get('post_max_size'));
    error_log("DEBUG: max_file_uploads: " . ini_get('max_file_uploads'));
    
    // Validar campos obrigatórios
    if (empty($_POST['titulo'])) {
        $errors[] = 'Título é obrigatório';
    }
    if (empty($_POST['subtitulo'])) {
        $errors[] = 'Descrição é obrigatória';
    }
    if (empty($_POST['numero_portaria'])) {
        $errors[] = 'Número da portaria é obrigatório';
    }
    
    // Processar upload do arquivo
    $arquivo_pdf = '';
    $nome_arquivo_original = '';
    
    // Debug: verificar se o arquivo foi enviado
    if (!isset($_FILES['arquivo_pdf'])) {
        $errors[] = 'Nenhum arquivo foi enviado';
    } else {
        $upload_error = $_FILES['arquivo_pdf']['error'];
        
        // Verificar erros de upload
        switch ($upload_error) {
            case UPLOAD_ERR_OK:
                // Arquivo enviado com sucesso, processar
                $arquivo_temp = $_FILES['arquivo_pdf']['tmp_name'];
                $nome_original = $_FILES['arquivo_pdf']['name'];
                $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
                
                // Validar extensão
                if ($extensao !== 'pdf') {
                    $errors[] = 'Apenas arquivos PDF são permitidos';
                }
                
                // Validar tamanho (máximo 10MB)
                if ($_FILES['arquivo_pdf']['size'] > 10 * 1024 * 1024) {
                    $errors[] = 'Arquivo muito grande. Máximo 10MB permitido';
                }
                
                // Validar se o arquivo temporário existe
                if (!is_uploaded_file($arquivo_temp)) {
                    $errors[] = 'Erro: arquivo temporário não encontrado';
                }
                
                if (empty($errors)) {
                    // Gerar nome único para o arquivo
                    $nome_arquivo = 'portaria_' . time() . '_' . uniqid() . '.pdf';
                    $diretorio = '../../uploads/portarias/';
                    
                    // Criar diretório se não existir
                    if (!is_dir($diretorio)) {
                        if (!mkdir($diretorio, 0755, true)) {
                            $errors[] = 'Erro ao criar diretório de upload';
                        }
                    }
                    
                    if (empty($errors)) {
                        $caminho_completo = $diretorio . $nome_arquivo;
                        
                        if (move_uploaded_file($arquivo_temp, $caminho_completo)) {
                            $arquivo_pdf = '/visamunicipal/uploads/portarias/' . $nome_arquivo;
                            $nome_arquivo_original = $nome_original;
                        } else {
                            $errors[] = 'Erro ao mover arquivo para destino final';
                        }
                    }
                }
                break;
                
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'Arquivo PDF é obrigatório';
                break;
                
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'Arquivo muito grande. Verifique o tamanho máximo permitido';
                break;
                
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'Upload foi interrompido. Tente novamente';
                break;
                
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = 'Erro do servidor: diretório temporário não encontrado';
                break;
                
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = 'Erro do servidor: não foi possível escrever o arquivo';
                break;
                
            case UPLOAD_ERR_EXTENSION:
                $errors[] = 'Upload bloqueado por extensão do arquivo';
                break;
                
            default:
                $errors[] = 'Erro desconhecido no upload do arquivo (código: ' . $upload_error . ')';
                break;
        }
    }
    
    // Se não há erros, salvar no banco
    if (empty($errors)) {
        $dados = [
            'titulo' => $_POST['titulo'],
            'subtitulo' => $_POST['subtitulo'],
            'numero_portaria' => $_POST['numero_portaria'],
            'arquivo_pdf' => $arquivo_pdf,
            'nome_arquivo_original' => $nome_arquivo_original,
            'status' => $_POST['status'] ?? 'ativo',
            'ordem_exibicao' => (int)($_POST['ordem_exibicao'] ?? 1),
            'data_publicacao' => $_POST['data_publicacao'] ?: null,
            'usuario_criacao' => $_SESSION['user']['id']
        ];
        
        if ($portaria->createPortaria($dados)) {
            $_SESSION['mensagem_sucesso'] = 'Portaria cadastrada com sucesso!';
            header('Location: listar_portarias.php');
            exit();
        } else {
            $errors[] = 'Erro ao salvar portaria no banco de dados';
        }
    }
}

// Incluir o header após processar o formulário
include '../header.php';
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <!-- Mensagens de Erro -->
    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Erros encontrados:</h3>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cabeçalho da Página -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-3 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Nova Portaria
                    </h1>
                    <p class="text-gray-600 mt-1">Cadastre uma nova portaria para exibição no site público</p>
                </div>
                <a href="listar_portarias.php" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0L2.586 11H16a1 1 0 110 2H2.586l3.707 3.707a1 1 0 01-1.414 1.414l-5.414-5.414a1 1 0 010-1.414l5.414-5.414a1 1 0 011.414 1.414L2.586 9H16a1 1 0 110 2H7.707z" clip-rule="evenodd" />
                    </svg>
                    Voltar
                </a>
            </div>
        </div>
    </div>

    <!-- Formulário -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            
            <!-- Título -->
            <div>
                <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">
                    Título da Portaria *
                </label>
                <input type="text" 
                       id="titulo" 
                       name="titulo" 
                       value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: Portaria - Licenciamento e Fiscalização Sanitária"
                       required>
                <p class="mt-1 text-sm text-gray-500">Título que aparecerá no site público</p>
            </div>

            <!-- Número da Portaria -->
            <div>
                <label for="numero_portaria" class="block text-sm font-medium text-gray-700 mb-2">
                    Número da Portaria *
                </label>
                <input type="text" 
                       id="numero_portaria" 
                       name="numero_portaria" 
                       value="<?php echo htmlspecialchars($_POST['numero_portaria'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: GAB/SEMUS Nº 0272/2024"
                       required>
                <p class="mt-1 text-sm text-gray-500">Número oficial da portaria</p>
            </div>

            <!-- Descrição -->
            <div>
                <label for="subtitulo" class="block text-sm font-medium text-gray-700 mb-2">
                    Descrição *
                </label>
                <textarea id="subtitulo" 
                          name="subtitulo" 
                          rows="4"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Ex: Acesse a Portaria GAB/SEMUS Nº 0272/2024, que define as classificações de risco e competências para atividades econômicas no município de Gurupi."
                          required><?php echo htmlspecialchars($_POST['subtitulo'] ?? ''); ?></textarea>
                <p class="mt-1 text-sm text-gray-500">Descrição que aparecerá abaixo do título no site</p>
            </div>

            <!-- Upload do Arquivo PDF -->
            <div>
                <label for="arquivo_pdf" class="block text-sm font-medium text-gray-700 mb-2">
                    Arquivo PDF *
                </label>
                
                <!-- Input simples e visível para debug -->
                <div class="mt-2">
                    <input type="file" 
                           id="arquivo_pdf" 
                           name="arquivo_pdf" 
                           accept=".pdf,application/pdf"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                           required>
                    <p class="mt-2 text-sm text-gray-500">
                        Selecione apenas arquivos PDF (máximo 10MB)
                    </p>
                    <div id="file-info" class="mt-2 text-sm text-green-600" style="display: none;"></div>
                </div>
            </div>

            <!-- Data de Publicação -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="data_publicacao" class="block text-sm font-medium text-gray-700 mb-2">
                        Data de Publicação
                    </label>
                    <input type="date" 
                           id="data_publicacao" 
                           name="data_publicacao" 
                           value="<?php echo htmlspecialchars($_POST['data_publicacao'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Data em que a portaria foi publicada</p>
                </div>

                <!-- Ordem de Exibição -->
                <div>
                    <label for="ordem_exibicao" class="block text-sm font-medium text-gray-700 mb-2">
                        Ordem de Exibição
                    </label>
                    <input type="number" 
                           id="ordem_exibicao" 
                           name="ordem_exibicao" 
                           value="<?php echo htmlspecialchars($_POST['ordem_exibicao'] ?? '1'); ?>"
                           min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Ordem de exibição no site (1 = primeira)</p>
                </div>
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                    Status
                </label>
                <select id="status" 
                        name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="ativo" <?php echo (($_POST['status'] ?? 'ativo') == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo (($_POST['status'] ?? '') == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                </select>
                <p class="mt-1 text-sm text-gray-500">Portarias inativas não aparecem no site público</p>
            </div>

            <!-- Botões -->
            <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                <a href="listar_portarias.php" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    Cancelar
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Salvar Portaria
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Melhorar a experiência do upload de arquivo
document.getElementById('arquivo_pdf').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const fileInfo = document.getElementById('file-info');
    
    if (file) {
        console.log('Arquivo selecionado:', file);
        console.log('Nome:', file.name);
        console.log('Tipo:', file.type);
        console.log('Tamanho:', file.size);
        
        // Validar se é PDF
        if (file.type !== 'application/pdf') {
            alert('Por favor, selecione apenas arquivos PDF');
            this.value = '';
            fileInfo.style.display = 'none';
            return;
        }
        
        // Validar tamanho (10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Arquivo muito grande. Máximo 10MB permitido');
            this.value = '';
            fileInfo.style.display = 'none';
            return;
        }
        
        // Mostrar informações do arquivo
        fileInfo.innerHTML = `✓ Arquivo selecionado: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        fileInfo.style.display = 'block';
    } else {
        fileInfo.style.display = 'none';
    }
});

// Debug do formulário
document.querySelector('form').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('arquivo_pdf');
    
    console.log('=== DEBUG FORMULÁRIO ===');
    console.log('Método:', this.method);
    console.log('Action:', this.action);
    console.log('Enctype:', this.enctype);
    console.log('Input file:', fileInput);
    console.log('Arquivos selecionados:', fileInput.files);
    console.log('Quantidade de arquivos:', fileInput.files ? fileInput.files.length : 0);
    
    if (fileInput.files && fileInput.files.length > 0) {
        const file = fileInput.files[0];
        console.log('Arquivo a ser enviado:');
        console.log('- Nome:', file.name);
        console.log('- Tipo:', file.type);
        console.log('- Tamanho:', file.size);
        console.log('- Última modificação:', file.lastModified);
    }
    
    console.log('FormData será criado com:');
    const formData = new FormData(this);
    for (let pair of formData.entries()) {
        console.log('- ' + pair[0] + ':', pair[1]);
    }
    
    // Não impedir o envio para testar
    console.log('Enviando formulário...');
});
</script>

<?php include '../footer.php'; ?> 