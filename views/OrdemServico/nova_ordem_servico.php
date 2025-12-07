<?php
session_start();
ob_start();
include '../header.php';
?>

<style>
    /* Estilo para melhorar os seletores múltiplos */
    select[multiple] {
        height: auto;
        min-height: 120px;
    }
    
    select[multiple] option {
        padding: 8px 12px;
        margin-bottom: 1px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    
    select[multiple] option:checked,
    select[multiple] option:hover {
        background-color: rgba(59, 130, 246, 0.1);
        color: #1e40af;
    }
    
    .file-drop-area {
        position: relative;
    }
    
    .file-drop-area.dragging {
        background-color: rgba(59, 130, 246, 0.05);
    }
</style>

<?php

// Verificação de autenticação e nível de acesso
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/OrdemServico.php';

$ordemServico = new OrdemServico($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $acoes_executadas = $_POST['acoes_executadas'];
    $tecnicos_ids = $_POST['tecnicos'];
    $tecnicos = json_encode($tecnicos_ids);
    $estabelecimento_id = $_POST['estabelecimento_id'];
    $processo_id = $_POST['processo_id'];
    $observacao = $_POST['observacao']; // Novo campo de observação
    $pdf_path = ''; // Defina o caminho do PDF após gerar
    $municipio = $_SESSION['user']['municipio'];

    $data_inicio_dt = new DateTime($data_inicio);
    $data_fim_dt = new DateTime($data_fim);

    $pdf_upload_path = null;

    if (isset($_FILES['pdf_upload']) && $_FILES['pdf_upload']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/pdf_ordens/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['pdf_upload']['name']);
        $pdf_upload_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['pdf_upload']['tmp_name'], $pdf_upload_path)) {
            $pdf_upload_path = 'visamunicipal/' . str_replace('../../', '', $pdf_upload_path); // Inclua o prefixo correto
        } else {
            $error = "Erro ao fazer upload do arquivo.";
        }
    }

    if ($data_fim_dt < $data_inicio_dt) {
        $error = "A data de fim não pode ser menor que a data de início.";
    } else {
        if ($ordemServico->create($estabelecimento_id, $processo_id, $data_inicio, $data_fim, $acoes_executadas, $tecnicos, $pdf_path, $municipio, 'ativa', $observacao, $pdf_upload_path)) {
            header("Location: listar_ordens.php?success=Ordem de Serviço criada com sucesso.");
            exit();
        } else {
            $error = "Erro ao criar a ordem de serviço: " . $ordemServico->getLastError();
        }
    }
}

// Obter tipos de ações executadas
$tipos_acoes_executadas = $ordemServico->getTiposAcoesExecutadas();

// Obter usuários técnicos do mesmo município e com nível de acesso 3 ou 4
$municipio_usuario = $_SESSION['user']['municipio'];
$query = "SELECT id, nome_completo FROM usuarios WHERE nivel_acesso IN (3, 4) AND municipio = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $municipio_usuario);
$stmt->execute();
$result = $stmt->get_result();
$tecnicos = $result->fetch_all(MYSQLI_ASSOC);

?>

<div class="max-w-5xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-xl font-medium text-gray-800 flex items-center">
            <i class="fas fa-clipboard-list text-blue-500 mr-2"></i> Nova Ordem de Serviço
        </h1>
    </div>
    
    <div class="bg-amber-50 border-l-2 border-amber-400 p-4 mb-6 text-sm text-amber-700 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="ml-3">
                <p>A criação de ordem de serviço neste lugar não vinculará ao estabelecimento. Após a inspeção, o técnico deverá encerrar a OS e vincular a ordem de serviço ao estabelecimento ou encerrar a ordem de serviço sem vincular ao estabelecimento.</p>
            </div>
        </div>
    </div>

    <?php if (isset($error)) : ?>
        <div class="bg-red-50 border-l-2 border-red-400 p-4 mb-6 text-sm text-red-700 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-sm rounded-md p-6">
        <form action="nova_ordem_servico.php" method="POST" enctype="multipart/form-data">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="data_inicio" name="data_inicio" required>
                </div>
                <div>
                    <label for="data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="data_fim" name="data_fim" required>
                </div>
            </div>
            
            <div class="mb-6">
                <label for="acoes_executadas" class="block text-sm font-medium text-gray-700 mb-1">Ações Executadas</label>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="acoes_executadas" name="acoes_executadas[]" multiple required>
                    <?php foreach ($tipos_acoes_executadas as $tipo) : ?>
                        <option value="<?php echo htmlspecialchars($tipo['id']); ?>">
                            <?php echo htmlspecialchars($tipo['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500">Segure Ctrl para selecionar múltiplas ações</p>
            </div>

            <div class="mb-6">
                <label for="tecnicos" class="block text-sm font-medium text-gray-700 mb-1">Técnicos</label>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="tecnicos" name="tecnicos[]" multiple required>
                    <?php foreach ($tecnicos as $tecnico) : ?>
                        <option value="<?php echo htmlspecialchars($tecnico['id']); ?>">
                            <?php echo htmlspecialchars($tecnico['nome_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500">Segure Ctrl para selecionar múltiplos técnicos</p>
            </div>

            <div class="mb-6">
                <label for="pdf_upload" class="block text-sm font-medium text-gray-700 mb-1">Anexar PDF (Opcional)</label>
                <div id="drop-area" class="file-drop-area mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors">
                    <div class="space-y-1 text-center">
                        <i class="fas fa-file-pdf text-gray-400 text-3xl mb-2"></i>
                        <div class="flex text-sm text-gray-600">
                            <label for="pdf_upload" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                <span>Selecionar arquivo</span>
                                <input id="pdf_upload" name="pdf_upload" type="file" class="sr-only" accept=".pdf">
                            </label>
                            <p class="pl-1">ou arraste e solte</p>
                        </div>
                        <p class="text-xs text-gray-500">PDF até 10MB</p>
                    </div>
                </div>
                <div id="file-name" class="mt-2 text-sm text-gray-600 hidden"></div>
            </div>

            <div class="mb-6">
                <label for="observacao" class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="observacao" name="observacao" rows="4"></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="listar_ordens.php" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors duration-150">Cancelar</a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none transition-colors duration-150">Salvar Ordem de Serviço</button>
            </div>
        </form>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        form.addEventListener('submit', function(event) {
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;

            if (new Date(dataFim) < new Date(dataInicio)) {
                event.preventDefault();
                alert('A data de fim não pode ser menor que a data de início.');
            }
        });
        
        // Script para mostrar o nome do arquivo PDF selecionado
        const fileInput = document.getElementById('pdf_upload');
        const fileNameDisplay = document.getElementById('file-name');
        const dropArea = document.getElementById('drop-area');
        
        // Função para mostrar o arquivo selecionado
        function showFileName(file) {
            if (file) {
                fileNameDisplay.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-file-pdf text-blue-500 mr-2"></i>
                        <span>${file.name}</span>
                    </div>
                `;
                fileNameDisplay.classList.remove('hidden');
            } else {
                fileNameDisplay.classList.add('hidden');
            }
        }
        
        // Tratamento para upload via input file
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                showFileName(fileInput.files[0]);
            } else {
                fileNameDisplay.classList.add('hidden');
            }
        });
        
        // Tratamento para drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('border-blue-400', 'bg-blue-50');
        }
        
        function unhighlight() {
            dropArea.classList.remove('border-blue-400', 'bg-blue-50');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                const file = files[0];
                if (file.type === 'application/pdf') {
                    fileInput.files = files;
                    showFileName(file);
                } else {
                    alert('Por favor, selecione apenas arquivos PDF.');
                }
            }
        }
        
        // Melhoria na experiência dos seletores múltiplos
        const selects = document.querySelectorAll('select[multiple]');
        selects.forEach(select => {
            const helperText = select.nextElementSibling;
            if (helperText && helperText.tagName === 'P') {
                helperText.innerHTML = '<i class="fas fa-info-circle mr-1"></i> ' + helperText.textContent;
            }
        });
    });
</script>