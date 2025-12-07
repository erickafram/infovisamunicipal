<?php
// --- Início do Bloco PHP ---
session_start();
require_once 'conf/database.php';
require_once 'models/Estabelecimento.php';
require_once 'models/Processo.php';
require_once 'models/Arquivo.php';
require_once 'models/Portaria.php';

$estabelecimentoModel = new Estabelecimento($conn);
$processoModel = new Processo($conn);
$arquivoModel = new Arquivo($conn);
$portariaModel = new Portaria($conn);

$searchTerm = '';
$processoInfo = null;
$alvaraSanitario = null;
$erroVerificacao = '';
$erroBuscaCnpj = '';
$arquivoVerificado = null;
$showResults = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Busca por CNPJ ---
    if (isset($_POST['searchTerm'])) {
        $searchTerm = trim($_POST['searchTerm']);
        $showResults = true;

        // Limpa CNPJ para validação
        $cnpjNumerico = preg_replace('/[^0-9]/', '', $searchTerm);

        // Validação: Verifica se o CNPJ limpo tem 14 dígitos
        if (strlen($cnpjNumerico) !== 14) {
            $erroBuscaCnpj = "O CNPJ informado (" . htmlspecialchars($searchTerm) . ") parece estar em um formato inválido. Por favor, verifique.";
            $processoInfo = null;
        } else {
            try {
                $processoInfo = $processoModel->getProcessoByCnpj($searchTerm);
            } catch (Exception $e) {
                error_log("Erro em getProcessoByCnpj: " . $e->getMessage());
                $erroBuscaCnpj = "Ocorreu um erro ao buscar o processo. Tente novamente mais tarde.";
                $processoInfo = null;
            }

            // Processa o resultado se encontrado
            if ($processoInfo) {
                $processo_id = $processoInfo['id'];
                $arquivos = $arquivoModel->getArquivosByProcesso($processo_id);
                foreach ($arquivos as $arquivo) {
                    if (strpos(strtoupper($arquivo['tipo_documento']), 'ALVARÁ SANITÁRIO') !== false) {
                        $alvaraSanitario = $arquivo;
                        break;
                    }
                }
            } else if (empty($erroBuscaCnpj)) {
                $erroBuscaCnpj = "Nenhum processo encontrado para o CNPJ informado (" . htmlspecialchars($searchTerm) . "). Verifique o número digitado.";
            }
        }
    }
    
    // --- Verificação de Documento ---
    elseif (isset($_POST['codigo_verificador'])) {
        $codigo_verificador = trim($_POST['codigo_verificador'] ?? '');
        if (!empty($codigo_verificador)) {
            $arquivoVerificado = $arquivoModel->getArquivoByCodigo($codigo_verificador);
            if (!$arquivoVerificado) {
                $erroVerificacao = "Código verificador inválido ou não encontrado.";
            } else {
                try {
                    if (ob_get_level()) ob_end_clean();
                    header('Content-Type: application/pdf');
                    $nomeArquivoDisplay = $arquivoVerificado['nome_original'] ?? basename($arquivoVerificado['caminho_arquivo']);
                    header('Content-Disposition: inline; filename="' . htmlspecialchars($nomeArquivoDisplay) . '"');
                    $filePath = __DIR__ . '/' . $arquivoVerificado['caminho_arquivo'];
                    if (file_exists($filePath)) {
                        header('Content-Length: ' . filesize($filePath));
                        header('Cache-Control: private, max-age=0, must-revalidate');
                        header('Pragma: public');
                        readfile($filePath);
                        exit();
                    } else {
                        error_log("Arquivo PDF não encontrado: " . $filePath);
                        header('HTTP/1.1 404 Not Found');
                        echo "Erro: Arquivo não encontrado no servidor.";
                        exit();
                    }
                } catch (Exception $e) {
                    error_log("Erro ao exibir PDF: " . $e->getMessage());
                    $erroVerificacao = "Erro ao tentar exibir o documento.";
                }
            }
        } else {
            $erroVerificacao = "Por favor, insira um código verificador.";
        }
    }
}
// --- Fim do Bloco PHP ---
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Infovisa - Sistema de Informações de Vigilância Sanitária. Consulte processos, verifique documentos e acesse informações sobre vigilância sanitária.">
    <meta name="keywords" content="vigilância sanitária, alvará sanitário, consulta processo, verificação documento">
    <meta name="author" content="GovNex">
    
    <title>Infovisa - Sistema de Vigilância Sanitária</title>
    
    <!-- Favicon -->
    <link rel="icon" href="/visamunicipal/assets/img/favicon.ico" type="image/x-icon">
    
    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
        <style>
        body {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }

        .nav-link {
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .nav-link:hover {
            color: #3b82f6;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            transition: all 0.3s ease;
            border: none;
            font-size: 14px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .card {
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            border-color: #e2e8f0;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
        }

        .section-subtitle {
            font-size: 14px;
        }

        .card-title {
            font-size: 18px;
        }

        .card-text {
            font-size: 14px;
            line-height: 1.5;
        }

        .hero-title {
            font-size: 48px;
        }

        .hero-subtitle {
            font-size: 18px;
        }

        .form-label {
            font-size: 13px;
        }

        .form-input {
            font-size: 14px;
        }

        .info-label {
            font-size: 11px;
        }

        .info-value {
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 36px;
            }
            .hero-subtitle {
                font-size: 16px;
            }
            .section-title {
                font-size: 20px;
            }
        }

        /* Glassmorphism effect */
        .glass {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Subtle gradient backgrounds */
        .gradient-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-14">
                <div class="flex items-center">
                    <img src="/visamunicipal/assets/img/logo.png" alt="Infovisa" class="h-10 w-auto">
                </div>
            
                <nav class="hidden md:flex space-x-8">
                    <a href="#servicos" class="nav-link text-gray-600 hover:text-blue-600 font-medium">Serviços</a>
                    <a href="#consulta" class="nav-link text-gray-600 hover:text-blue-600 font-medium">Consultar</a>
                    <a href="#verificar" class="nav-link text-gray-600 hover:text-blue-600 font-medium">Verificar</a>
            </nav>
            
                <a href="views/login.php" class="btn-primary text-white px-4 py-2 rounded-lg font-medium flex items-center">
                    <i class="fas fa-user mr-2"></i>
                    Área Restrita
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="gradient-bg py-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="hero-title font-bold text-gray-900 mb-4">
                    INFO<span class="text-blue-600">VISA</span>
                </h1>
                <p class="hero-subtitle text-gray-600 mb-8 max-w-xl mx-auto">
                    Sistema de Vigilância Sanitária Municipal - Consulte processos e verifique documentos
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="#consulta" class="btn-primary text-white px-6 py-2.5 rounded-lg font-medium">
                        <i class="fas fa-search mr-2 text-sm"></i>
                        Consultar Processo
                    </a>
                    <a href="#verificar" class="bg-white hover:bg-gray-50 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors border border-gray-200">
                        <i class="fas fa-shield-alt mr-2 text-sm"></i>
                        Verificar Documento
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if (isset($_SESSION['mensagem_sucesso'])): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg" role="alert">
                <div class="flex">
                    <i class="fas fa-check-circle text-green-400 mr-3 mt-0.5"></i>
                        <div>
                        <p class="font-medium text-green-800">Sucesso!</p>
                        <p class="text-green-700"><?= htmlspecialchars($_SESSION['mensagem_sucesso']); ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['mensagem_sucesso']); ?>
        <?php endif; ?>

                <!-- Services Section -->
        <section id="servicos" class="mb-12">
            <div class="text-center mb-8">
                <h2 class="section-title text-gray-900 mb-2">Nossos Serviços</h2>
                <p class="section-subtitle text-gray-600 max-w-lg mx-auto">Soluções digitais para simplificar os processos da vigilância sanitária</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="card bg-white p-6 rounded-xl">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-file-alt text-blue-600"></i>
                    </div>
                    <h3 class="card-title font-semibold text-gray-900 mb-2">Processos Simplificados</h3>
                    <p class="card-text text-gray-600">Abertura e acompanhamento de processos sanitários de forma digital e transparente.</p>
                </div>
                
                <div class="card bg-white p-6 rounded-xl">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-search text-green-600"></i>
                    </div>
                    <h3 class="card-title font-semibold text-gray-900 mb-2">Consulta Rápida</h3>
                    <p class="card-text text-gray-600">Verifique o andamento do seu processo ou alvará sanitário em tempo real.</p>
                </div>
                
                <div class="card bg-white p-6 rounded-xl">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-purple-600"></i>
                    </div>
                    <h3 class="card-title font-semibold text-gray-900 mb-2">Documentos Autênticos</h3>
                    <p class="card-text text-gray-600">Verifique a autenticidade dos documentos usando o código verificador único.</p>
                </div>
            </div>
        </section>

                <!-- Portarias Section -->
        <?php
        $portarias = $portariaModel->getPortariasAtivas();
        if (!empty($portarias)):
        ?>
        <section class="bg-white rounded-xl shadow-sm p-6 mb-10">
            <h3 class="section-title text-gray-900 mb-6 flex items-center">
                <i class="fas fa-gavel text-blue-600 mr-2 text-lg"></i>
                Portarias e Documentos Normativos
            </h3>
            
            <div class="space-y-6">
                <?php foreach ($portarias as $portaria_item): ?>
                <div class="border border-gray-200 rounded-lg p-5 hover:shadow-md transition-shadow duration-200">
                    <h4 class="card-title text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                        <?php echo htmlspecialchars($portaria_item['titulo']); ?>
                    </h4>
                    
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg mb-4">
                        <p class="card-text text-gray-700">
                            <?php echo htmlspecialchars($portaria_item['subtitulo']); ?>
                        </p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Portaria:</span> <?php echo htmlspecialchars($portaria_item['numero_portaria']); ?>
                            <?php if ($portaria_item['data_publicacao']): ?>
                            <br><span class="font-medium">Publicada em:</span> <?php echo date('d/m/Y', strtotime($portaria_item['data_publicacao'])); ?>
                            <?php endif; ?>
                        </div>
                        
                        <a href="<?php echo htmlspecialchars($portaria_item['arquivo_pdf']); ?>" target="_blank"
                            class="btn-primary text-white px-5 py-2.5 rounded-lg font-medium inline-flex items-center justify-center">
                            <i class="fas fa-file-pdf mr-2 text-sm"></i>
                            Abrir Portaria (PDF)
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

                <!-- Process Consultation Section -->
        <section id="consulta" class="bg-white rounded-xl shadow-sm p-6 mb-10">
            <h3 class="section-title text-gray-900 mb-4 flex items-center">
                <i class="fas fa-search text-blue-600 mr-2 text-lg"></i>
                Consultar Andamento do Processo
            </h3>
            
            <div class="max-w-md mx-auto">
                <form method="POST" action="#consulta" class="mb-4">
                    <label for="searchTerm" class="form-label block font-medium text-gray-700 mb-2">CNPJ da Empresa</label>
                    <div class="flex">
                        <input type="text"
                            class="form-input flex-1 border border-gray-300 rounded-l-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            id="searchTerm" name="searchTerm" placeholder="00.000.000/0000-00"
                            value="<?= htmlspecialchars($searchTerm) ?>" required>
                        <button type="submit"
                            class="btn-primary text-white px-5 py-2.5 rounded-r-lg font-medium">
                            <i class="fas fa-search text-sm"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Formato: 00.000.000/0000-00</p>
                </form>
            </div>

                            <?php if ($showResults): ?>
                <?php if ($processoInfo): ?>
                    <div class="bg-gray-50 rounded-lg p-4 mt-6">
                        <h4 class="card-title font-semibold text-gray-900 mb-3">Informações do Processo</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="bg-white p-3 rounded-lg">
                                <p class="info-label text-gray-500 uppercase font-medium mb-1">Nome Fantasia</p>
                                <p class="info-value text-gray-900 font-medium"><?= htmlspecialchars($processoInfo['nome_fantasia']); ?></p>
                            </div>
                            
                            <div class="bg-white p-3 rounded-lg">
                                <p class="info-label text-gray-500 uppercase font-medium mb-1">CNPJ</p>
                                <p class="info-value text-gray-900 font-medium"><?= htmlspecialchars($processoInfo['cnpj']); ?></p>
                            </div>
                            
                            <div class="bg-white p-3 rounded-lg">
                                <p class="info-label text-gray-500 uppercase font-medium mb-1">Tipo de Processo</p>
                                <p class="info-value text-gray-900 font-medium"><?= htmlspecialchars($processoInfo['tipo_processo']); ?></p>
                            </div>
                            
                            <div class="bg-white p-3 rounded-lg">
                                <p class="info-label text-gray-500 uppercase font-medium mb-1">Nº do Processo</p>
                                <p class="info-value text-gray-900 font-medium"><?= htmlspecialchars($processoInfo['numero_processo']); ?></p>
                            </div>
                            
                            <div class="bg-white p-3 rounded-lg">
                                <p class="info-label text-gray-500 uppercase font-medium mb-1">Situação</p>
                                <p class="info-value font-medium <?= $processoInfo['status'] === 'ATIVO' ? 'text-green-600' : 'text-orange-600' ?>">
                                    <?= htmlspecialchars($processoInfo['status'] === 'ATIVO' ? 'EM ANDAMENTO' : strtoupper($processoInfo['status'])); ?>
                                </p>
                            </div>
                            
                            <?php if ($alvaraSanitario): ?>
                            <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                                <p class="info-label text-green-600 uppercase font-medium mb-1">Alvará Sanitário</p>
                                <a href="<?= htmlspecialchars($alvaraSanitario['caminho_arquivo']); ?>" target="_blank"
                                    class="inline-flex items-center text-green-700 hover:text-green-800 font-medium text-sm">
                                    <i class="fas fa-file-pdf mr-2"></i>
                                    Visualizar Alvará
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (!empty($erroBuscaCnpj)): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg mt-6" role="alert">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-yellow-400 mr-3 mt-0.5"></i>
                            <div>
                                <p class="font-medium text-yellow-800 card-text">Atenção</p>
                                <p class="card-text text-yellow-700"><?= htmlspecialchars($erroBuscaCnpj); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </section>

                <!-- Document Verification Section -->
        <section id="verificar" class="bg-white rounded-xl shadow-sm p-6 mb-10">
            <h3 class="section-title text-gray-900 mb-4 flex items-center">
                <i class="fas fa-shield-alt text-blue-600 mr-2 text-lg"></i>
                Verificar Autenticidade de Documento
            </h3>
            
            <?php if (!empty($erroVerificacao)): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg mb-4" role="alert">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle text-red-400 mr-3 mt-0.5"></i>
                        <div>
                            <p class="font-medium text-red-800 card-text">Erro</p>
                            <p class="card-text text-red-700"><?= htmlspecialchars($erroVerificacao); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="max-w-md mx-auto">
                <p class="card-text text-gray-600 mb-4">Digite o código verificador presente no documento para confirmar sua autenticidade.</p>
                
                <form method="POST" action="#verificar" target="_blank">
                    <div class="mb-4">
                        <label for="codigo_verificador" class="form-label block font-medium text-gray-700 mb-2">Código Verificador</label>
                        <input type="text"
                            class="form-input w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            id="codigo_verificador" name="codigo_verificador" 
                            placeholder="Digite o código presente no documento" required>
                        <p class="text-xs text-gray-500 mt-1">O código verificador está impresso no documento, geralmente no rodapé.</p>
                    </div>
                    <button type="submit"
                        class="w-full btn-primary text-white py-2.5 rounded-lg font-medium">
                        <i class="fas fa-shield-check mr-2 text-sm"></i>
                        Verificar Autenticidade
                    </button>
                </form>
                
                <div class="mt-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg">
                    <h4 class="font-medium text-blue-800 mb-1 card-text">Como funciona a verificação?</h4>
                    <p class="text-xs text-blue-700">Todos os documentos emitidos pela Vigilância Sanitária possuem um código único de verificação que garante sua autenticidade.</p>
                </div>
            </div>
        </section>

    </div>

        <!-- Footer -->
    <footer class="bg-white border-t border-gray-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <h3 class="card-title font-bold text-gray-900 mb-1">Infovisa</h3>
                <p class="section-subtitle text-gray-600 mb-4">Sistema de Vigilância Sanitária Municipal</p>
                
                <div class="flex justify-center space-x-6 mb-6">
                    <a href="#servicos" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Serviços</a>
                    <a href="#consulta" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Consulta</a>
                    <a href="#verificar" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Verificação</a>
                    <a href="views/login.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Área Restrita</a>
                </div>
                
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-gray-500 text-sm">
                        © <script>document.write(new Date().getFullYear())</script> Infovisa - Sistema de Vigilância Sanitária
                    </p>
                    <p class="text-gray-400 text-xs mt-1">GovNex - Soluções para Gestão Pública</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // CNPJ mask functionality
        const cnpjInput = document.getElementById('searchTerm');
        if (cnpjInput) {
            cnpjInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
                e.target.value = value.slice(0, 18);
            });
        }
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 70,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>

</body>

</html>