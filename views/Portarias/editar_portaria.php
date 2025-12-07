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

// Verificar se foi passado um ID válido
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    $_SESSION['mensagem_erro'] = 'ID da portaria não fornecido ou inválido';
    header('Location: listar_portarias.php');
    exit();
}

// Buscar dados da portaria
$dados_portaria = $portaria->getPortariaById($id);
if (!$dados_portaria) {
    $_SESSION['mensagem_erro'] = 'Portaria não encontrada';
    header('Location: listar_portarias.php');
    exit();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
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
    
    // Processar upload do arquivo (opcional na edição)
    $dados_atualizacao = [
        'titulo' => $_POST['titulo'],
        'subtitulo' => $_POST['subtitulo'],
        'numero_portaria' => $_POST['numero_portaria'],
        'status' => $_POST['status'] ?? 'ativo',
        'ordem_exibicao' => (int)($_POST['ordem_exibicao'] ?? 1),
        'data_publicacao' => $_POST['data_publicacao'] ?: null
    ];
    
    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == UPLOAD_ERR_OK) {
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
        
        if (empty($errors)) {
            // Remover arquivo antigo
            if (!empty($dados_portaria['arquivo_pdf'])) {
                $arquivo_antigo = $_SERVER['DOCUMENT_ROOT'] . $dados_portaria['arquivo_pdf'];
                error_log("DEBUG: Removendo arquivo antigo: " . $arquivo_antigo);
                if (file_exists($arquivo_antigo)) {
                    unlink($arquivo_antigo);
                }
            }
            
            // Gerar nome único para o novo arquivo
            $nome_arquivo = 'portaria_' . time() . '_' . uniqid() . '.pdf';
            $diretorio = '../../uploads/portarias/';
            
            // Criar diretório se não existir
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0755, true);
            }
            
            $caminho_completo = $diretorio . $nome_arquivo;
            error_log("DEBUG: Caminho completo: " . $caminho_completo);
            
            if (move_uploaded_file($arquivo_temp, $caminho_completo)) {
                $dados_atualizacao['arquivo_pdf'] = '/visamunicipal/uploads/portarias/' . $nome_arquivo;
                $dados_atualizacao['nome_arquivo_original'] = $nome_original;
                error_log("DEBUG: Upload realizado com sucesso");
                error_log("DEBUG: Novo caminho no banco: " . $dados_atualizacao['arquivo_pdf']);
            } else {
                $errors[] = 'Erro ao fazer upload do arquivo';
                error_log("DEBUG: ERRO ao mover arquivo");
            }
        }
    } else {
        error_log("DEBUG: Nenhum arquivo detectado para upload ou erro no upload");
        if (isset($_FILES['arquivo_pdf'])) {
            error_log("DEBUG: Erro do arquivo: " . $_FILES['arquivo_pdf']['error']);
        }
    }
    
    // Se não há erros, atualizar no banco
    if (empty($errors)) {
        error_log("DEBUG: Dados para atualização: " . print_r($dados_atualizacao, true));
        if ($portaria->updatePortaria($id, $dados_atualizacao)) {
            error_log("DEBUG: Portaria atualizada com sucesso no banco");
            $_SESSION['mensagem_sucesso'] = 'Portaria atualizada com sucesso!';
            header('Location: listar_portarias.php');
            exit();
        } else {
            $errors[] = 'Erro ao atualizar portaria no banco de dados';
            error_log("DEBUG: ERRO ao atualizar no banco de dados");
        }
    } else {
        error_log("DEBUG: Erros encontrados: " . print_r($errors, true));
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
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        Editar Portaria
                    </h1>
                    <p class="text-gray-600 mt-1">Edite as informações da portaria</p>
                </div>
                <div class="flex space-x-3">
                    <a href="<?php echo htmlspecialchars($dados_portaria['arquivo_pdf']); ?>" 
                       target="_blank"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                        </svg>
                        Ver PDF Atual
                    </a>
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
                       value="<?php echo htmlspecialchars($_POST['titulo'] ?? $dados_portaria['titulo']); ?>"
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
                       value="<?php echo htmlspecialchars($_POST['numero_portaria'] ?? $dados_portaria['numero_portaria']); ?>"
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
                          required><?php echo htmlspecialchars($_POST['subtitulo'] ?? $dados_portaria['subtitulo']); ?></textarea>
                <p class="mt-1 text-sm text-gray-500">Descrição que aparecerá abaixo do título no site</p>
            </div>

            <!-- Upload do Arquivo PDF (opcional na edição) -->
            <div>
                <label for="arquivo_pdf" class="block text-sm font-medium text-gray-700 mb-2">
                    Substituir Arquivo PDF (opcional)
                </label>
                <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors" id="upload-area">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="arquivo_pdf" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span id="file-label">Selecione um novo arquivo PDF</span>
                                <input id="arquivo_pdf" name="arquivo_pdf" type="file" class="sr-only" accept=".pdf">
                            </label>
                            <p class="pl-1">ou arraste e solte</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            Apenas arquivos PDF até 10MB
                        </p>
                        <div id="file-info">
                            <p class="text-xs text-blue-600 font-medium">
                                Arquivo atual: <?php echo htmlspecialchars($dados_portaria['nome_arquivo_original']); ?>
                            </p>
                        </div>
                        <div id="new-file-info" class="hidden">
                            <p class="text-xs text-green-600 font-medium">
                                Novo arquivo: <span id="new-file-name"></span>
                            </p>
                            <p class="text-xs text-green-600">
                                Tamanho: <span id="new-file-size"></span>
                            </p>
                            <p class="text-xs text-gray-500">
                                Este arquivo substituirá o arquivo atual quando salvar
                            </p>
                        </div>
                    </div>
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
                           value="<?php echo htmlspecialchars($_POST['data_publicacao'] ?? $dados_portaria['data_publicacao']); ?>"
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
                           value="<?php echo htmlspecialchars($_POST['ordem_exibicao'] ?? $dados_portaria['ordem_exibicao']); ?>"
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
                    <?php $status_atual = $_POST['status'] ?? $dados_portaria['status']; ?>
                    <option value="ativo" <?php echo ($status_atual == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo ($status_atual == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                </select>
                <p class="mt-1 text-sm text-gray-500">Portarias inativas não aparecem no site público</p>
            </div>

            <!-- Informações de Criação -->
            <div class="bg-gray-50 rounded-md p-4">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Informações de Criação</h3>
                <div class="text-sm text-gray-600 space-y-1">
                    <p><span class="font-medium">Criado em:</span> <?php echo date('d/m/Y H:i', strtotime($dados_portaria['data_criacao'])); ?></p>
                    <p><span class="font-medium">Última atualização:</span> <?php echo date('d/m/Y H:i', strtotime($dados_portaria['data_atualizacao'])); ?></p>
                </div>
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
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Melhorar a experiência do upload de arquivo
document.getElementById('arquivo_pdf').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Apenas atualizar o texto das informações, não remover o input
        document.getElementById('file-label').textContent = 'Arquivo selecionado';
        document.getElementById('new-file-name').textContent = file.name;
        document.getElementById('new-file-size').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        document.getElementById('new-file-info').classList.remove('hidden');
        
        console.log('Arquivo selecionado:', file.name, 'Tamanho:', file.size);
    }
});

// Debug do formulário
document.querySelector('form').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('arquivo_pdf');
    console.log('Submit do formulário:');
    console.log('Input file existe:', !!fileInput);
    console.log('Arquivo selecionado:', fileInput.files.length > 0 ? fileInput.files[0].name : 'Nenhum');
    console.log('FormData que será enviada:');
    const formData = new FormData(this);
    for (let [key, value] of formData.entries()) {
        console.log(key, ':', value);
    }
});
</script>

<?php include '../footer.php'; ?> 