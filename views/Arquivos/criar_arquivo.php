<?php
session_start();
ob_start();
include '../header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../controllers/ArquivoController.php';
require_once '../../models/Logomarca.php';
require_once '../../models/Usuario.php';
require_once '../../models/ResponsavelTecnico.php';

$controller = new ArquivoController($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'previsualizar') {
        $controller->previsualizar();
    } elseif ($_POST['acao'] == 'salvar') {
        $controller->create();
    } elseif ($_POST['acao'] == 'rascunho') {
        $controller->createDraft();
    }
}

if (!isset($_GET['processo_id']) || !isset($_GET['id'])) {
    echo "ID do processo ou estabelecimento não fornecido!";
    exit();
}

$processo_id = $_GET['processo_id'];
$estabelecimento_id = $_GET['id'];

// Verificar se o processo é do tipo "denúncia"
$processoQuery = $conn->prepare("SELECT tipo_processo FROM processos WHERE id = ?");
$processoQuery->bind_param("i", $processo_id);
$processoQuery->execute();
$result = $processoQuery->get_result();
$processo = $result->fetch_assoc();

$tipoProcesso = $processo ? $processo['tipo_processo'] : '';
$isDenuncia = ($tipoProcesso === 'DENÚNCIA');

$usuario_logado = (new Usuario($conn))->findById($_SESSION['user']['id']);
$logomarcaModel = new Logomarca($conn);
$logomarca = $logomarcaModel->getLogomarcaByMunicipio($usuario_logado['municipio']);

// Verificar se há responsável técnico vinculado ao estabelecimento
$responsavelTecnicoModel = new ResponsavelTecnico($conn);
$responsaveisTecnicos = $responsavelTecnicoModel->getByEstabelecimento($estabelecimento_id);
$temResponsavelTecnico = !empty($responsaveisTecnicos);

// Buscar todos os usuários do mesmo município do usuário logado
$usuariosMunicipio = $conn->query("SELECT id, nome_completo, cpf FROM usuarios WHERE municipio = '{$usuario_logado['municipio']}'");

// Função para formatar o CNAE
function formatCnae($cnae)
{
    return preg_replace('/(\d{4})(\d)(\d{2})/', '$1-$2/$3', $cnae);
}

// Consulta para obter os CNAEs que estão nos grupos de risco associados ao estabelecimento
$query = "
    SELECT 
        e.cnae_fiscal AS cnae_fiscal,
        e.cnae_fiscal_descricao AS cnae_fiscal_descricao,
        cnaes.codigo AS cnae_secundario,
        cnaes.descricao AS cnae_secundario_descricao,
        gr_fiscal.descricao AS grupo_risco_fiscal,
        gr_secundario.descricao AS grupo_risco_secundario
    FROM 
        estabelecimentos e
    LEFT JOIN 
        atividade_grupo_risco agr_fiscal ON e.cnae_fiscal = agr_fiscal.cnae AND e.municipio = agr_fiscal.municipio
    LEFT JOIN 
        grupo_risco gr_fiscal ON agr_fiscal.grupo_risco_id = gr_fiscal.id
    LEFT JOIN 
        JSON_TABLE(
            e.cnaes_secundarios, 
            '$[*]' COLUMNS (
                codigo VARCHAR(255) PATH '$.codigo', 
                descricao VARCHAR(255) PATH '$.descricao'
            )
        ) AS cnaes ON JSON_VALID(e.cnaes_secundarios)
    LEFT JOIN 
        atividade_grupo_risco agr_secundario ON cnaes.codigo = agr_secundario.cnae AND e.municipio = agr_secundario.municipio
    LEFT JOIN 
        grupo_risco gr_secundario ON agr_secundario.grupo_risco_id = gr_secundario.id
    WHERE 
        e.id = $estabelecimento_id AND JSON_VALID(e.cnaes_secundarios)
";

$result = $conn->query($query);

$cnaes = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['grupo_risco_fiscal'])) {
            //$cnaes[] = formatCnae($row['cnae_fiscal']) . " - " . $row['cnae_fiscal_descricao'] . " (Grupo de Risco: " . $row['grupo_risco_fiscal'] . ")";
            $cnaes[] = formatCnae($row['cnae_fiscal']) . " - " . $row['cnae_fiscal_descricao'];
        }
        if (!empty($row['grupo_risco_secundario'])) {
            //$cnaes[] = formatCnae($row['cnae_secundario']) . " - " . $row['cnae_secundario_descricao'] . " (Grupo de Risco: " . $row['grupo_risco_secundario'] . ")";
            $cnaes[] = formatCnae($row['cnae_secundario']) . " - " . $row['cnae_secundario_descricao'];
        }
    }
    $cnaes = array_unique($cnaes);
} else {
    echo "Erro ao buscar informações do estabelecimento!";
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Arquivo</title>
    <!-- Adicionar Select2 CSS somente se ainda não estiver incluído -->
    <script>
        // Verificar se jQuery já está carregado
        if (typeof jQuery === 'undefined') {
            document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
        }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Estilos personalizados para o Select2 -->
    <style>
        /* Reset completo para o Select2 */
        .select2-container {
            width: 100% !important;
            max-width: 100%;
        }
        
        /* Estilo base para o Select2 */
        .select2-container--default .select2-selection--single {
            height: 42px;
            padding: 6px 12px 6px 40px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: #fff;
        }
        
        /* Seta do dropdown */
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 10px;
        }
        
        /* Área do texto selecionado */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
            color: #374151;
            padding-left: 0;
        }
        
        /* Dropdown */
        .select2-dropdown {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 1060;
        }
        
        /* Grupos de opções */
        .select2-container--default .select2-results__group {
            padding: 8px 12px;
            font-weight: 600;
            color: #4b5563;
            background-color: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
        }
        
        /* Opções */
        .select2-container--default .select2-results__option {
            padding: 8px 12px;
        }
        
        /* Opção quando passa o mouse */
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #eff6ff;
            color: #1e40af;
        }
        
        /* Opção selecionada */
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        /* Campo de pesquisa */
        .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            padding: 6px 12px;
            margin: 8px;
            width: calc(100% - 16px) !important;
        }
        
        /* Quando o campo está focado */
        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-search--dropdown .select2-search__field:focus {
            border-color: #93c5fd;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        /* Posição do ícone à esquerda */
        .tipo-documento-wrapper {
            position: relative;
        }
        
        .tipo-documento-wrapper::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            width: 24px;
            height: 24px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%233b82f6'%3E%3Cpath fill-rule='evenodd' d='M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z' clip-rule='evenodd' /%3E%3C/svg%3E");
            background-size: contain;
            pointer-events: none;
        }
        
        /* Ajuste para o container do Select2 */
        .select2-container {
            display: block !important;
        }
    </style>
    <script>
        tinymce.init({
            selector: '#conteudo',
            plugins: 'advlist autolink lists link image charmap print preview hr anchor pagebreak image imagetools table paste code help wordcount searchreplace',
            toolbar_mode: 'floating',
            toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | table image link | code fullscreen',
            height: 500,
            paste_data_images: true,
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
            },
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save(); // Salva o conteúdo no textarea
                    validarFormulario(); // Valida o formulário ao mudar o conteúdo
                });
            },
            language: 'pt_BR', // Configura o idioma para português do Brasil
            language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n/langs/pt_BR.js',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; margin: 15px; }'
        });

        function carregarModelo(tipoDocumento) {
            if (tipoDocumento) {
                // Mostrar indicador de carregamento
                const editorContainer = tinymce.get('conteudo').getContainer();
                const loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'editor-loading-overlay';
                loadingOverlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div>';
                loadingOverlay.style.position = 'absolute';
                loadingOverlay.style.top = '0';
                loadingOverlay.style.left = '0';
                loadingOverlay.style.right = '0';
                loadingOverlay.style.bottom = '0';
                loadingOverlay.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
                loadingOverlay.style.display = 'flex';
                loadingOverlay.style.alignItems = 'center';
                loadingOverlay.style.justifyContent = 'center';
                loadingOverlay.style.zIndex = '1000';
                editorContainer.appendChild(loadingOverlay);

                // Verificar se é ALVARÁ SANITÁRIO e mostrar modal se necessário
                if (tipoDocumento === 'ALVARÁ SANITÁRIO') {
                    const temResponsavelTecnico = <?php echo $temResponsavelTecnico ? 'true' : 'false'; ?>;
                    if (!temResponsavelTecnico) {
                        // Mostrar o modal após um pequeno delay para melhor experiência do usuário
                        setTimeout(() => {
                            $('#modalResponsavelTecnico').modal('show');
                            
                            // Iniciar o contador de 5 segundos
                            let segundosRestantes = 5;
                            const countdownElement = document.getElementById('countdownRT');
                            const btnContinuar = document.getElementById('btnContinuarSemRT');
                            
                            const countdownInterval = setInterval(() => {
                                segundosRestantes--;
                                countdownElement.textContent = `(${segundosRestantes})`;
                                
                                if (segundosRestantes <= 0) {
                                    clearInterval(countdownInterval);
                                    countdownElement.textContent = '';
                                    btnContinuar.disabled = false;
                                    btnContinuar.classList.remove('opacity-50', 'cursor-not-allowed');
                                    btnContinuar.setAttribute('data-bs-dismiss', 'modal');
                                }
                            }, 1000);
                        }, 500);
                    }
                }

                fetch(`obter_modelo.php?tipo_documento=${tipoDocumento}`)
                    .then(response => response.text())
                    .then(data => {
                        tinymce.get('conteudo').setContent(data);
                        // Remover indicador de carregamento
                        editorContainer.removeChild(loadingOverlay);
                    })
                    .catch(error => {
                        console.error('Erro ao carregar modelo:', error);
                        // Remover indicador de carregamento em caso de erro
                        editorContainer.removeChild(loadingOverlay);
                        
                        // Mostrar mensagem de erro
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'alert alert-danger mt-2';
                        errorMsg.textContent = 'Erro ao carregar o modelo. Tente novamente.';
                        document.getElementById('tipo_documento').parentNode.appendChild(errorMsg);
                        
                        // Remover mensagem após 3 segundos
                        setTimeout(() => {
                            if (errorMsg.parentNode) {
                                errorMsg.parentNode.removeChild(errorMsg);
                            }
                        }, 3000);
                    });
                
                // Mostrar formulário de assinantes
                document.getElementById('assinantes-section').style.display = 'block';
            } else {
                tinymce.get('conteudo').setContent('');
                document.getElementById('assinantes-section').style.display = 'none';
            }
        }
    </script>
</head>

<body>

    <div class="container mx-auto px-3 py-6 mt-4">
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden transition-all duration-300 hover:shadow-xl">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-gray-50 border-b border-gray-200">
                <h2 class="text-xl font-medium text-gray-800 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                    </svg>
                    Criar Novo Documento
                </h2>
            </div>
            <div class="p-6">
                <form id="arquivo-form" action="criar_arquivo.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" method="POST" target="previewIframe" enctype="multipart/form-data" class="space-y-6">
                    <?php if ($logomarca) : ?>
                        <div class="flex justify-center mb-6 transform hover:scale-105 transition-transform duration-300">
                            <img src="<?php echo $logomarca['caminho_logomarca']; ?>" alt="Logomarca" class="h-20 w-auto object-contain drop-shadow-md">
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="group">
                            <label for="tipo_documento" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-blue-600 transition-colors duration-200">Tipo de Documento</label>
                            <div class="relative tipo-documento-wrapper">
                                <select class="select2-search w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2.5 transition-all duration-200 hover:border-blue-300" 
                                        id="tipo_documento" 
                                        name="tipo_documento" 
                                        required>
                                    <option value="">Selecione o tipo de documento</option>
                                    <optgroup label="Documentos de Licenciamento">
                                    <option value="ALVARÁ SANITÁRIO">ALVARÁ SANITÁRIO</option>
                                    <option value="CERTIDÃO">CERTIDÃO</option>
                                        <option value="PARECER TÉCNICO">PARECER TÉCNICO</option>
                                        <option value="TERMO DE COMPROMISSO">TERMO DE COMPROMISSO</option>
                                    </optgroup>
                                    <optgroup label="Documentos Fiscais">
                                        <option value="AUTO DE INFRAÇÃO">AUTO DE INFRAÇÃO</option>
                                        <option value="NOTIFICAÇÃO">NOTIFICAÇÃO</option>
                                        <option value="ORDEM DE SERVIÇO">ORDEM DE SERVIÇO</option>
                                        <option value="TERMO DE APREENSÃO">TERMO DE APREENSÃO</option>
                                        <option value="TERMO DE VISTORIA">TERMO DE VISTORIA</option>
                                        <option value="TERMO DE AVALIAÇÃO">TERMO DE AVALIAÇÃO</option>
                                    </optgroup>
                                    <optgroup label="Documentos Administrativos">
                                    <option value="DECLARAÇÃO">DECLARAÇÃO</option>
                                    <option value="DESPACHO">DESPACHO</option>
                                    <option value="INSTRUÇÃO NORMATIVA">INSTRUÇÃO NORMATIVA</option>
                                    <option value="LAUDO TÉCNICO">LAUDO TÉCNICO</option>
                                    <option value="MEMORANDO">MEMORANDO</option>
                                    <option value="ORIENTAÇÃO SANITÁRIA">ORIENTAÇÃO SANITÁRIA</option>
                                    <option value="RELATÓRIO TÉCNICO">RELATÓRIO TÉCNICO</option>
                                    <option value="REQUERIMENTO">REQUERIMENTO</option>
                                    <option value="MANIFESTAÇÃO PROCESSUAL">MANIFESTAÇÃO PROCESSUAL</option>
                                    </optgroup>
                                </select>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 italic">
                                <i class="fas fa-info-circle mr-1"></i>Selecione o tipo para carregar um modelo predefinido
                            </p>
                        </div>
                        <div class="group">
                            <label for="sigiloso" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-blue-600 transition-colors duration-200">Documento Sigiloso</label>
                            <div class="relative">
                                <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pl-10 py-2.5 transition-all duration-200 hover:border-blue-300" 
                                        id="sigiloso" 
                                        name="sigiloso" 
                                        required 
                                        <?php echo $isDenuncia ? 'disabled' : ''; ?>>
                                    <option value="0" <?php echo !$isDenuncia ? 'selected' : ''; ?>>Não</option>
                                    <option value="1" <?php echo $isDenuncia ? 'selected' : ''; ?>>Sim</option>
                                </select>
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            <?php if ($isDenuncia): ?>
                                <input type="hidden" name="sigiloso" value="1">
                                <p class="mt-1 text-sm text-amber-600 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    Documentos de denúncia são sempre sigilosos
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="group">
                        <label for="conteudo" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-blue-600 transition-colors duration-200 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                            </svg>
                            Conteúdo do Documento
                        </label>
                        <div class="relative bg-white rounded-md border border-gray-300 shadow-sm hover:border-blue-300 transition-all duration-200">
                            <div class="absolute top-2 right-3 opacity-70 text-xs text-white bg-blue-600 px-2 py-1 rounded-md shadow-sm z-10">
                                Editor de Texto Avançado
                            </div>
                            <textarea class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all duration-200" 
                                      id="conteudo" 
                                      name="conteudo" 
                                      rows="15"
                                      placeholder="Selecione um tipo de documento para carregar o modelo ou digite o conteúdo do documento aqui..."></textarea>
                            </div>
                        <div class="flex justify-end mt-1">
                            <button type="button" class="inline-flex items-center text-xs px-2 py-1 text-blue-600 hover:text-blue-800" onclick="previsualizarPDF()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                                Pré-visualizar documento
                            </button>
                        </div>
                    </div>

                    <!-- Visualização rápida do documento (opcional, controlada via JavaScript) -->
                    <div id="quick-preview" class="hidden mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-medium text-gray-700">Visualização rápida</h3>
                            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="toggleQuickPreview()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <div id="preview-content" class="bg-white p-3 border border-gray-200 rounded-md max-h-64 overflow-y-auto">
                            <!-- Conteúdo será inserido via JavaScript -->
                        </div>
                    </div>

                    <!-- Mostrar informações dos CNAEs vinculados aos grupos de risco -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden transition-all duration-300 hover:shadow-md">
                        <div class="bg-gradient-to-r from-gray-50 to-white px-4 py-3 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-gray-700 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                                    </svg>
                                    CNAEs do Estabelecimento
                                </h3>
                                <div class="flex space-x-2">
                                <button type="button" 
                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200" 
                                        onclick="copiarCNAEs()">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                        <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                                    </svg>
                                    Copiar CNAEs
                                </button>
                                    <button type="button" 
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200" 
                                            onclick="inserirCNAEs()">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                        Inserir no Editor
                                    </button>
                                </div>
                                <textarea id="cnaesParaCopiar" class="sr-only"></textarea>
                            </div>
                        </div>
                    </div>
                <div class="hidden space-y-4" id="assinantes-section">
                    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden transition-all duration-300 hover:shadow-xl mt-6">
                        <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-blue-50 border-b border-gray-200 transition-all duration-300 hover:from-blue-100 hover:to-indigo-100">
                            <h3 class="text-lg font-medium text-gray-900 flex items-center group">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 2a1 1 0 00-1 1v1a1 1 0 002 0V3a1 1 0 00-1-1zM4 4h3a3 3 0 006 0h3a2 2 0 012 2v9a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm2.5 7a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm2.45 4a2.5 2.5 0 10-4.9 0h4.9zM12 9a1 1 0 100 2h3a1 1 0 100-2h-3zm-1 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span class="transition-colors duration-300 group-hover:text-blue-700">Definir Assinaturas Digitais</span>
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 transition-all duration-300 group-hover:bg-blue-200">Obrigatório</span>
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4 rounded-r-md transition-all duration-300 hover:bg-blue-100">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">
                                            Selecione os usuários que devem assinar digitalmente este documento.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div id="assinantes-container" class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-60 overflow-y-auto p-2 bg-gray-50 rounded-lg border border-gray-100">
                            <?php
                            // Inicializar a variável $tipoDocumento com um valor padrão
                            $tipoDocumento = $_POST['tipo_documento'] ?? '';

                            // Verificar se o tipo de documento é ALVARÁ SANITÁRIO
                            if ($tipoDocumento === 'ALVARÁ SANITÁRIO') : ?>
                                <!-- Mostrar apenas usuários com nível de acesso 3 -->
                                <?php
                                $usuariosGerentes = $conn->query("SELECT id, nome_completo, cpf FROM usuarios WHERE municipio = '{$usuario_logado['municipio']}' AND nivel_acesso = 3");
                                while ($usuario = $usuariosGerentes->fetch_assoc()) : ?>
                                    <div class="relative flex items-start">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" 
                                                   id="assinante_<?php echo $usuario['id']; ?>" 
                                                   name="assinantes[]" 
                                                   value="<?php echo $usuario['id']; ?>" 
                                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="assinante_<?php echo $usuario['id']; ?>" class="font-medium text-gray-700">
                                                <?php echo htmlspecialchars($usuario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>
                                            </label>
                                            <p class="text-gray-500"><?php echo htmlspecialchars($usuario['cpf'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <!-- Exibição de assinantes com base nos níveis de acesso -->
                                <?php if ($usuario_logado['nivel_acesso'] == 4) : ?>
                                    <!-- Para usuários com nível de acesso 4 -->
                                    <?php while ($usuario = $usuariosMunicipio->fetch_assoc()) : ?>
                                        <div class="relative flex items-start">
                                            <div class="flex items-center h-5">
                                                <input type="checkbox" 
                                                       id="assinante_<?php echo $usuario['id']; ?>" 
                                                       name="assinantes[]" 
                                                       value="<?php echo $usuario['id']; ?>" 
                                                       <?php echo $usuario['id'] == $usuario_logado['id'] ? 'checked disabled' : ''; ?>
                                                       class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                <?php if ($usuario['id'] == $usuario_logado['id']) : ?>
                                                    <input type="hidden" name="assinantes[]" value="<?php echo $usuario['id']; ?>">
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="assinante_<?php echo $usuario['id']; ?>" class="font-medium text-gray-700 <?php echo $usuario['id'] == $usuario_logado['id'] ? 'font-bold' : ''; ?>">
                                                    <?php echo htmlspecialchars($usuario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php if ($usuario['id'] == $usuario_logado['id']) : ?>
                                                        <span class="text-blue-600 text-xs ml-1">(Você)</span>
                                                    <?php endif; ?>
                                                </label>
                                                <p class="text-gray-500"><?php echo htmlspecialchars($usuario['cpf'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php elseif (in_array($usuario_logado['nivel_acesso'], [1, 2, 3])) : ?>
                                    <!-- Para usuários com nível de acesso 1, 2, 3 -->
                                    <?php while ($usuario = $usuariosMunicipio->fetch_assoc()) : ?>
                                        <div class="relative flex items-start">
                                            <div class="flex items-center h-5">
                                                <input type="checkbox" 
                                                       id="assinante_<?php echo $usuario['id']; ?>" 
                                                       name="assinantes[]" 
                                                       value="<?php echo $usuario['id']; ?>" 
                                                       <?php echo $usuario['id'] == $usuario_logado['id'] ? 'checked' : ''; ?>
                                                       class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="assinante_<?php echo $usuario['id']; ?>" class="font-medium text-gray-700 <?php echo $usuario['id'] == $usuario_logado['id'] ? 'font-bold' : ''; ?>">
                                                    <?php echo htmlspecialchars($usuario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php if ($usuario['id'] == $usuario_logado['id']) : ?>
                                                        <span class="text-blue-600 text-xs ml-1">(Você)</span>
                                                    <?php endif; ?>
                                                </label>
                                                <p class="text-gray-500"><?php echo htmlspecialchars($usuario['cpf'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <!-- Para outros usuários -->
                                    <div class="relative flex items-start">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" 
                                                   id="assinante_<?php echo $usuario_logado['id']; ?>" 
                                                   name="assinantes[]" 
                                                   value="<?php echo $usuario_logado['id']; ?>" 
                                                   checked
                                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="assinante_<?php echo $usuario_logado['id']; ?>" class="font-medium text-gray-700">
                                                <?php echo htmlspecialchars($usuario_logado['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>
                                                <span class="text-blue-600 text-xs ml-1">(Você)</span>
                                            </label>
                                            <p class="text-gray-500"><?php echo htmlspecialchars($usuario_logado['cpf'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <input type="hidden" name="processo_id" value="<?php echo $processo_id; ?>">
                    <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimento_id; ?>">
                    <input type="hidden" name="acao" id="acao" value="">
                    
                    <div class="flex flex-wrap md:flex-nowrap justify-end space-y-3 md:space-y-0 space-x-0 md:space-x-4 pt-6 border-t border-gray-200 mt-6">
                        <a href="../Processo/documentos.php?processo_id=<?php echo $processo_id; ?>&id=<?php echo $estabelecimento_id; ?>" 
                           class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:-translate-y-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                            Voltar
                        </a>
                        
                        <button type="button" 
                                class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 transform hover:-translate-y-0.5" 
                                id="btn-previsualizar"
                                onclick="previsualizarPDF()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                            </svg>
                            Pré-visualizar
                        </button>
                        
                        <button type="button" 
                                class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 transform hover:-translate-y-0.5" 
                                id="btn-salvar-rascunho" 
                                onclick="salvarRascunho()" 
                                disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                            </svg>
                            Salvar como Rascunho
                        </button>
                        
                        <button type="button" 
                                class="w-full md:w-auto inline-flex items-center justify-center px-5 py-2.5 border border-transparent shadow-md text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 transform hover:-translate-y-0.5" 
                                id="btn-finalizar" 
                                onclick="salvarPDF()" 
                                disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-white" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span>Finalizar Documento</span>
                        </button>
                    </div>

                    <!-- Indicador de status do documento -->
                    <div id="document-status" class="hidden mt-4 p-3 bg-blue-50 text-blue-800 rounded-md border border-blue-200">
                        <div class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="status-text">Processando documento...</span>
                        </div>
                    </div>

                    <!-- Espaço adicional para evitar sobreposição com o ChatVisa fixo -->
                    <div class="h-24 md:h-32 mt-6"></div>
            </form>
            </div>
        </div>
    </div>

    <!-- Modal de Pré-visualização -->
    <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content rounded-lg shadow-xl overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900" id="previewModalLabel">
                            <span class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                                Pré-visualização do Documento
                            </span>
                        </h3>
                        <button type="button" class="text-gray-400 hover:text-gray-500" data-bs-dismiss="modal" aria-label="Close">
                            <span class="sr-only">Fechar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="p-0">
                    <iframe id="previewIframe" name="previewIframe" src="" class="w-full h-[600px] border-0"></iframe>
                </div>
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                            data-bs-dismiss="modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Fechar
                    </button>
                    <button type="button" 
                            class="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                            onclick="salvarPDF()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        Salvar Documento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Salvamento -->
    <div class="modal fade" id="confirmSaveModal" tabindex="-1" role="dialog" aria-labelledby="confirmSaveModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content rounded-lg shadow-xl overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900" id="confirmSaveModalLabel">
                            <span class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                Confirmação de Salvamento
                            </span>
                        </h3>
                        <button type="button" class="text-gray-400 hover:text-gray-500" data-bs-dismiss="modal" aria-label="Close">
                            <span class="sr-only">Fechar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="px-4 py-5">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Atenção</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Após finalizar o documento de forma definitiva, <span class="font-medium text-amber-600">não será possível editá-lo</span>. 
                                </p>
                                <p class="text-sm text-gray-500 mt-2">
                                    Caso queira que o documento seja editado posteriormente, salve-o como rascunho.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm" 
                            onclick="confirmarSalvarPDF()">
                        Confirmar e Salvar
                    </button>
                    <button type="button" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" 
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Alerta de Responsável Técnico -->
    <div class="modal fade" id="modalResponsavelTecnico" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="modalResponsavelTecnicoLabel" aria-hidden="true">
        <div class="modal-dialog modal-xs" role="document">
            <div class="modal-content rounded shadow-sm overflow-hidden">
                <div class="px-2 py-1 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-center">
                        <h3 class="text-sm font-medium text-gray-800" id="modalResponsavelTecnicoLabel">
                            <span class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                Atenção
                            </span>
                        </h3>
                    </div>
                </div>
                <div class="px-3 py-2 bg-white">
                    <div class="flex items-start space-x-2">
                        <div class="flex-shrink-0">
                            <svg class="h-4 w-4 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-medium text-gray-900">Responsável Técnico não encontrado</h4>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Este estabelecimento não possui Responsável Técnico vinculado.
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Para algumas atividades, é obrigatório ter um Responsável Técnico para emissão do Alvará Sanitário.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-3 py-2 bg-gray-50 border-t border-gray-200 flex justify-end space-x-1">
                    <button type="button" 
                            id="btnContinuarSemRT"
                            class="px-2 py-1 text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-blue-500 opacity-50 cursor-not-allowed"
                            disabled>
                        Continuar mesmo assim
                        <span id="countdownRT" class="ml-1">(5)</span>
                    </button>
                    <a href="../Estabelecimento/responsaveis.php?id=<?php echo $estabelecimento_id; ?>" 
                       class="px-2 py-1 text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-blue-500">
                        Cadastrar Responsável
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar elementos e estados do formulário
            const tipoDocumento = document.getElementById('tipo_documento');
            const btnSalvarRascunho = document.getElementById('btn-salvar-rascunho');
            const btnFinalizar = document.getElementById('btn-finalizar');
            const btnPrevisualizar = document.getElementById('btn-previsualizar');
            
            // Desativar botões inicialmente
            btnSalvarRascunho.disabled = true;
            btnFinalizar.disabled = true;
            
            // Adicionar tooltips para botões desativados
            updateButtonTooltips();
            
            // Verificar estado inicial do formulário
            validarFormulario();

            // Adicionar evento de clique ao botão "Continuar mesmo assim"
            document.getElementById('btnContinuarSemRT').addEventListener('click', function() {
                if (!this.disabled) {
                    $('#modalResponsavelTecnico').modal('hide');
                }
            });
        });
        
        // Evento de mudança no tipo de documento
        document.getElementById('tipo_documento').addEventListener('change', function() {
            const tipoDocumento = this.value;
            const section = document.getElementById('assinantes-section');
            const container = document.getElementById('assinantes-container');
            const btnSalvarRascunho = document.getElementById('btn-salvar-rascunho');
            const btnFinalizar = document.getElementById('btn-finalizar');

            if (tipoDocumento) {
                // Mostrar a seção e desativar os botões inicialmente
                section.style.display = 'block';
                container.innerHTML = ''; // Limpar o container antes de carregar
                btnSalvarRascunho.disabled = true;
                btnFinalizar.disabled = true;

                // Adicionar classe de carregamento ao container
                container.classList.add('relative', 'min-h-[100px]');
                container.innerHTML = `
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="flex flex-col items-center">
                            <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">Carregando usuários...</p>
                        </div>
                    </div>
                `;

                // Carregar os usuários via AJAX
                fetch('carregar_usuarios.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            tipo_documento: tipoDocumento,
                        }),
                    })
                    .then((response) => response.text())
                    .then((data) => {
                        container.classList.remove('relative', 'min-h-[100px]');
                        container.innerHTML = data;

                        // Verificar se há algum checkbox no container
                        validarSelecaoUsuarios();
                        
                        // Adicionar animação fade-in
                        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach((checkbox, index) => {
                            const parent = checkbox.closest('.relative');
                            if (parent) {
                                parent.style.opacity = '0';
                                parent.style.animation = `fadeIn 0.3s ease forwards ${index * 0.05}s`;
                            }
                        });
                    })
                    .catch((error) => {
                        console.error('Erro ao carregar usuários:', error);
                        container.classList.remove('relative', 'min-h-[100px]');
                        container.innerHTML = `
                            <div class="p-4 bg-red-50 rounded-md text-red-800 text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto mb-2 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p>Erro ao carregar usuários. Tente novamente.</p>
                                <button type="button" class="mt-2 text-sm text-blue-600 hover:text-blue-800 underline" onclick="recarregarUsuarios('${tipoDocumento}')">
                                    Tentar novamente
                                </button>
                            </div>
                        `;
                    });
            } else {
                // Ocultar a seção e desativar os botões
                section.style.display = 'none';
                container.innerHTML = '';
                btnSalvarRascunho.disabled = true;
                btnFinalizar.disabled = true;
            }
            
            // Atualizar tooltips
            updateButtonTooltips();
        });

        // Função para recarregar usuários em caso de erro
        function recarregarUsuarios(tipoDocumento) {
            document.getElementById('tipo_documento').value = tipoDocumento;
            document.getElementById('tipo_documento').dispatchEvent(new Event('change'));
        }

        // Função para verificar a seleção de usuários e ativar/desativar os botões
        function validarSelecaoUsuarios() {
            const checkboxes = document.querySelectorAll('#assinantes-container input[type="checkbox"]');
            const btnSalvarRascunho = document.getElementById('btn-salvar-rascunho');
            const btnFinalizar = document.getElementById('btn-finalizar');
            const tipoDocumento = document.getElementById('tipo_documento').value;
            const conteudo = tinymce.get('conteudo').getContent();

            const algumSelecionado = Array.from(checkboxes).some((checkbox) => checkbox.checked);
            const formularioValido = tipoDocumento !== '' && conteudo !== '' && algumSelecionado;

            btnSalvarRascunho.disabled = !algumSelecionado;
            btnFinalizar.disabled = !formularioValido;
            
            // Atualizar tooltips
            updateButtonTooltips();
        }

        // Função para atualizar tooltips nos botões
        function updateButtonTooltips() {
            const btnSalvarRascunho = document.getElementById('btn-salvar-rascunho');
            const btnFinalizar = document.getElementById('btn-finalizar');
            
            if (btnSalvarRascunho.disabled) {
                btnSalvarRascunho.setAttribute('title', 'Selecione pelo menos um usuário para assinar o documento');
            } else {
                btnSalvarRascunho.removeAttribute('title');
            }
            
            if (btnFinalizar.disabled) {
                btnFinalizar.setAttribute('title', 'Preencha todos os campos e selecione pelo menos um usuário para assinar');
            } else {
                btnFinalizar.removeAttribute('title');
            }
        }

        // Adicionar evento de mudança aos checkboxes dinamicamente
        document.addEventListener('change', (event) => {
            if (event.target.matches('#assinantes-container input[type="checkbox"]')) {
                validarSelecaoUsuarios();
            }
        });

        // Função para mostrar/ocultar visualização rápida
        function toggleQuickPreview() {
            const quickPreview = document.getElementById('quick-preview');
            const previewContent = document.getElementById('preview-content');
            
            if (quickPreview.classList.contains('hidden')) {
                // Mostrar visualização
                const htmlContent = tinymce.get('conteudo').getContent();
                previewContent.innerHTML = htmlContent;
                quickPreview.classList.remove('hidden');
                quickPreview.classList.add('fade-in');
            } else {
                // Ocultar visualização
                quickPreview.classList.add('hidden');
                quickPreview.classList.remove('fade-in');
            }
        }

        function previsualizarPDF() {
            // Mostrar indicador de status
            const statusDiv = document.getElementById('document-status');
            statusDiv.classList.remove('hidden');
            document.getElementById('status-text').textContent = 'Gerando pré-visualização...';
            
            document.getElementById('acao').value = 'previsualizar';
            document.getElementById('arquivo-form').action = 'previsualizar.php';
            document.getElementById('arquivo-form').target = 'previewIframe';
            document.getElementById('arquivo-form').submit();
            
            // Abrir modal depois de um pequeno delay para permitir o carregamento
            setTimeout(() => {
            $('#previewModal').modal('show');
                statusDiv.classList.add('hidden');
            }, 800);
        }

        function validarFormulario() {
            var tipoDocumento = document.getElementById('tipo_documento').value;
            var conteudo = tinymce.get('conteudo') ? tinymce.get('conteudo').getContent() : '';
            const checkboxes = document.querySelectorAll('#assinantes-container input[type="checkbox"]');
            const algumSelecionado = Array.from(checkboxes).some((checkbox) => checkbox.checked);
            
            // Verificar se todos os campos obrigatórios estão preenchidos
            const formularioValido = tipoDocumento !== '' && conteudo !== '' && algumSelecionado;
            
            // Atualizar estado dos botões
            document.getElementById('btn-salvar-rascunho').disabled = !algumSelecionado;
            document.getElementById('btn-finalizar').disabled = !formularioValido;
            
            return formularioValido;
        }

        function salvarPDF() {
            if (validarFormulario()) {
                $('#confirmSaveModal').modal('show');
            } else {
                // Destacar campos não preenchidos
                highlightEmptyFields();
            }
        }
        
        // Função para destacar campos não preenchidos
        function highlightEmptyFields() {
            const tipoDocumento = document.getElementById('tipo_documento');
            const editorContainer = tinymce.get('conteudo').getContainer();
            const checkboxes = document.querySelectorAll('#assinantes-container input[type="checkbox"]');
            const algumSelecionado = Array.from(checkboxes).some((checkbox) => checkbox.checked);
            
            // Adicionar classe de erro aos campos vazios
            if (tipoDocumento.value === '') {
                tipoDocumento.classList.add('border-red-500');
                tipoDocumento.parentElement.insertAdjacentHTML('afterend', 
                    '<p class="mt-1 text-xs text-red-600 error-message"><i class="fas fa-exclamation-circle mr-1"></i>Selecione um tipo de documento</p>');
            }
            
            if (tinymce.get('conteudo').getContent() === '') {
                editorContainer.classList.add('border', 'border-red-500');
                editorContainer.parentElement.insertAdjacentHTML('beforeend', 
                    '<p class="mt-1 text-xs text-red-600 error-message"><i class="fas fa-exclamation-circle mr-1"></i>O conteúdo do documento não pode estar vazio</p>');
            }
            
            if (!algumSelecionado) {
                const container = document.getElementById('assinantes-container');
                container.classList.add('border-red-500');
                container.parentElement.insertAdjacentHTML('beforeend', 
                    '<p class="mt-1 text-xs text-red-600 error-message"><i class="fas fa-exclamation-circle mr-1"></i>Selecione pelo menos um usuário para assinar o documento</p>');
            }
            
            // Remover mensagens de erro após 3 segundos
            setTimeout(() => {
                tipoDocumento.classList.remove('border-red-500');
                editorContainer.classList.remove('border', 'border-red-500');
                document.getElementById('assinantes-container').classList.remove('border-red-500');
                
                const errorMessages = document.querySelectorAll('.error-message');
                errorMessages.forEach(msg => {
                    msg.remove();
                });
            }, 3000);
        }

        function confirmarSalvarPDF() {
            if (validarFormulario()) {
                // Mostrar indicador de status
                const statusDiv = document.getElementById('document-status');
                statusDiv.classList.remove('hidden');
                statusDiv.classList.remove('bg-blue-50', 'text-blue-800', 'border-blue-200');
                statusDiv.classList.add('bg-green-50', 'text-green-800', 'border-green-200');
                document.getElementById('status-text').textContent = 'Salvando documento...';
                
                document.getElementById('acao').value = 'salvar';
                document.getElementById('arquivo-form').action = 'criar_arquivo.php';
                document.getElementById('arquivo-form').target = '';
                document.getElementById('arquivo-form').submit();
                $('#confirmSaveModal').modal('hide');
            }
        }

        function salvarRascunho() {
            if (validarFormulario()) {
                // Mostrar indicador de status
                const statusDiv = document.getElementById('document-status');
                statusDiv.classList.remove('hidden');
                statusDiv.classList.remove('bg-green-50', 'text-green-800', 'border-green-200');
                statusDiv.classList.add('bg-blue-50', 'text-blue-800', 'border-blue-200');
                document.getElementById('status-text').textContent = 'Salvando rascunho...';
                
                document.getElementById('acao').value = 'rascunho';
                document.getElementById('arquivo-form').action = 'criar_arquivo.php';
                document.getElementById('arquivo-form').target = '';
                document.getElementById('arquivo-form').submit();
            }
        }

        function copiarCNAEs() {
            var cnaes = <?php echo json_encode($cnaes); ?>;
            if (typeof cnaes === 'object') {
                var cnaesArray = Object.values(cnaes);
                var cnaesTexto = cnaesArray.join('\n');
                var cnaesTextarea = document.getElementById('cnaesParaCopiar');
                cnaesTextarea.value = cnaesTexto;
                cnaesTextarea.select();
                document.execCommand('copy');
                
                // Mostrar tooltip de sucesso
                const btn = document.querySelector('button[onclick="copiarCNAEs()"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Copiado!
                `;
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            }
        }
        
        function inserirCNAEs() {
            var cnaes = <?php echo json_encode($cnaes); ?>;
            if (typeof cnaes === 'object') {
                var cnaesArray = Object.values(cnaes);
                var cnaesTexto = cnaesArray.join('\n');
                tinymce.get('conteudo').execCommand('mceInsertContent', false, '<p><strong>CNAEs:</strong></p><ul><li>' + cnaesArray.join('</li><li>') + '</li></ul>');
                
                // Mostrar tooltip de sucesso
                const btn = document.querySelector('button[onclick="inserirCNAEs()"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Inserido!
                `;
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
                
                // Validar formulário após inserção
                validarFormulario();
            }
        }
        
        // Adicionar estilos para animação
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .fade-in {
                animation: fadeIn 0.3s ease forwards;
            }
            
            .editor-loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(255, 255, 255, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
        `;
        document.head.appendChild(style);
    </script>

    <?php include '../footer.php'; ?>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Inicializar Select2 após a página ser carregada
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar se jQuery está disponível
            if (typeof jQuery !== 'undefined') {
                // Usar jQuery em modo seguro (usando uma função para isolar o $)
                (function($) {
                    // Destruir qualquer instância existente
                    if ($('#tipo_documento').data('select2')) {
                        $('#tipo_documento').select2('destroy');
                    }
                    
                    // Inicializar Select2 no campo de tipo de documento
                    $('#tipo_documento').select2({
                        placeholder: 'Pesquisar ou selecionar tipo de documento',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('.tipo-documento-wrapper'),
                        language: {
                            noResults: function() {
                                return "Nenhum resultado encontrado";
                            },
                            searching: function() {
                                return "Pesquisando...";
                            }
                        }
                    }).on('select2:select', function(e) {
                        // Chamar a função original de carregamento do modelo
                        carregarModelo(this.value);
                        validarFormulario();
                    });
                    
                    // Ajustar o comportamento de foco
                    $('#tipo_documento').on('select2:open', function(e) {
                        setTimeout(function() {
                            $('.select2-search__field').focus();
                        }, 50);
                    });
                    
                    // Verificar se já existe um valor selecionado
                    if ($('#tipo_documento').val() && $('#tipo_documento').val() !== '') {
                        carregarModelo($('#tipo_documento').val());
                    }
                })(jQuery);
            } else {
                console.error('jQuery não encontrado. O componente Select2 não pôde ser inicializado.');
                
                // Restaurar comportamento padrão para o select se o jQuery não estiver disponível
                document.getElementById('tipo_documento').addEventListener('change', function() {
                    carregarModelo(this.value);
                    validarFormulario();
                });
            }
        });
    </script>
</body>

</html>