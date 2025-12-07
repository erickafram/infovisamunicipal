<?php
session_start();
include '../header.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/User.php';

$user = new User($conn);
$mensagem = '';
$tipo_mensagem = '';

// Verificar se o usuário já tem senha digital
$usuario_id = $_SESSION['user']['id'];
$usuario_info = $user->findById($usuario_id);
$tem_senha_digital = !empty($usuario_info['senha_digital']);

// Verificar se o usuário foi redirecionado da página de assinatura
if (isset($_GET['redirect']) && $_GET['redirect'] === 'assinatura') {
    $mensagem = "Você precisa configurar sua senha digital para poder assinar documentos.";
    $tipo_mensagem = "warning";
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($tem_senha_digital) {
        // Se já tem senha digital, verificar a senha de login primeiro
        $senha_login = $_POST['senha_login'] ?? '';
        if (!password_verify($senha_login, $usuario_info['senha'])) {
            $mensagem = "Senha de acesso incorreta.";
            $tipo_mensagem = "danger";
        } else {
            // Senha de login correta, prosseguir com a alteração da senha digital
            $senha_digital = $_POST['senha_digital'];
            $confirmar_senha = $_POST['confirmar_senha'];
            
            if (!ctype_digit($senha_digital)) {
                $mensagem = "A senha digital deve conter apenas números.";
                $tipo_mensagem = "danger";
            } 
            elseif (strlen($senha_digital) != 6) {
                $mensagem = "A senha digital deve ter exatamente 6 dígitos.";
                $tipo_mensagem = "danger";
            }
            elseif ($senha_digital !== $confirmar_senha) {
                $mensagem = "As senhas digitais não coincidem.";
                $tipo_mensagem = "danger";
            } 
            else {
                $senha_hash = password_hash($senha_digital, PASSWORD_DEFAULT);
                if ($user->updateSenhaDigital($usuario_id, $senha_hash)) {
                    $mensagem = "Senha digital atualizada com sucesso!";
                    $tipo_mensagem = "success";
                } else {
                    $mensagem = "Erro ao atualizar a senha digital: " . $user->getLastError();
                    $tipo_mensagem = "danger";
                }
            }
        }
    } else {
        // Primeira configuração da senha digital
        $senha_digital = $_POST['senha_digital'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        if (!ctype_digit($senha_digital)) {
            $mensagem = "A senha digital deve conter apenas números.";
            $tipo_mensagem = "danger";
        } 
        elseif (strlen($senha_digital) != 6) {
            $mensagem = "A senha digital deve ter exatamente 6 dígitos.";
            $tipo_mensagem = "danger";
        }
        elseif ($senha_digital !== $confirmar_senha) {
            $mensagem = "As senhas digitais não coincidem.";
            $tipo_mensagem = "danger";
        } 
        else {
            $senha_hash = password_hash($senha_digital, PASSWORD_DEFAULT);
            if ($user->updateSenhaDigital($usuario_id, $senha_hash)) {
                $mensagem = "Senha digital configurada com sucesso! Agora você pode assinar documentos.";
                $tipo_mensagem = "success";
                $tem_senha_digital = true;
            } else {
                $mensagem = "Erro ao configurar a senha digital: " . $user->getLastError();
                $tipo_mensagem = "danger";
            }
        }
    }
}
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-blue-600 px-6 py-4">
                <h4 class="text-xl font-medium text-white">
                    <?php echo $tem_senha_digital ? 'Alterar Senha Digital' : 'Configurar Senha Digital'; ?>
                </h4>
            </div>
            <div class="p-6">
                <?php if (!empty($mensagem)): ?>
                    <?php 
                    $alertClass = '';
                    $iconClass = '';
                    
                    if ($tipo_mensagem === 'success') {
                        $alertClass = 'bg-green-100 border-l-4 border-green-500 text-green-700';
                        $iconClass = 'fas fa-check-circle';
                    } elseif ($tipo_mensagem === 'warning') {
                        $alertClass = 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700';
                        $iconClass = 'fas fa-exclamation-triangle';
                    } else {
                        $alertClass = 'bg-red-100 border-l-4 border-red-500 text-red-700';
                        $iconClass = 'fas fa-exclamation-circle';
                    }
                    ?>
                    <div class="<?php echo $alertClass; ?> p-4 mb-6 rounded flex items-start">
                        <i class="<?php echo $iconClass; ?> mr-3 mt-1"></i>
                        <p><?php echo $mensagem; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($tem_senha_digital): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    Você já possui uma senha digital configurada. Para alterá-la, primeiro digite sua senha de acesso ao sistema.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="mb-6 text-gray-600">
                        Configure sua senha digital para assinar documentos eletrônicos. 
                        Esta senha deve ser composta por 6 dígitos numéricos e é diferente da sua senha de acesso ao sistema.
                    </p>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php if ($tem_senha_digital): ?>
                    <div class="mb-4">
                        <label for="senha_login" class="block text-sm font-medium text-gray-700 mb-1">Senha de Acesso ao Sistema</label>
                        <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               id="senha_login" name="senha_login" required>
                        <p class="mt-1 text-xs text-gray-500">Digite sua senha de acesso ao sistema para confirmar a alteração.</p>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label for="senha_digital" class="block text-sm font-medium text-gray-700 mb-1">
                            <?php echo $tem_senha_digital ? 'Nova Senha Digital (6 dígitos numéricos)' : 'Senha Digital (6 dígitos numéricos)'; ?>
                        </label>
                        <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               id="senha_digital" name="senha_digital" maxlength="6" pattern="[0-9]{6}" required>
                    </div>

                    <div class="mb-6">
                        <label for="confirmar_senha" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Senha Digital</label>
                        <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               id="confirmar_senha" name="confirmar_senha" maxlength="6" pattern="[0-9]{6}" required>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-1.5 px-3 rounded-md transition duration-300 ease-in-out text-sm">
                            <i class="fas fa-save mr-1"></i> Salvar
                        </button>
                        <?php if (isset($_GET['redirect']) && $_GET['redirect'] === 'assinatura'): ?>
                        <?php
                        $arquivo_id = $_GET['arquivo_id'] ?? '';
                        $processo_id = $_GET['processo_id'] ?? '';
                        $estabelecimento_id = $_GET['estabelecimento_id'] ?? '';
                        $url_retorno = "../Processo/pre_visualizar_arquivo.php?arquivo_id={$arquivo_id}&processo_id={$processo_id}&estabelecimento_id={$estabelecimento_id}";
                        ?>
                        <a href="<?php echo $url_retorno; ?>" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-medium py-1.5 px-3 rounded-md transition duration-300 ease-in-out inline-flex items-center justify-center text-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Voltar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?> 