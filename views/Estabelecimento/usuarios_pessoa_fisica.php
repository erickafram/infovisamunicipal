<?php
ob_start(); // Start output buffering
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso
// 1 Administrador, 2 Suporte, 3 Gerente, 4 Fiscal
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3, 4])) {
    header("Location: ../../login.php"); // Redirecionar para a página de login se não estiver autenticado ou não for administrador
    exit();
}

require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';
require_once '../../models/UsuarioEstabelecimento.php';

$estabelecimento = new Estabelecimento($conn);
$usuarioEstabelecimentoModel = new UsuarioEstabelecimento($conn);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $dadosEstabelecimento = $estabelecimento->findById($id);

    if (!$dadosEstabelecimento) {
        echo "Estabelecimento não encontrado!";
        exit();
    }

    // Verificar se o usuário tem permissão para acessar o estabelecimento
    $usuarioMunicipio = $_SESSION['user']['municipio'];
    $nivel_acesso = $_SESSION['user']['nivel_acesso'];

    if ($nivel_acesso != 1 && $dadosEstabelecimento['municipio'] !== $usuarioMunicipio) {
        header("Location: listar_estabelecimentos.php?error=" . urlencode("Você não tem permissão para acessar este estabelecimento."));
        exit();
    }

    // Buscar usuários vinculados ao estabelecimento
    $usuariosVinculados = $usuarioEstabelecimentoModel->getUsuariosByEstabelecimento($id);
} else {
    echo "ID do estabelecimento não fornecido!";
    exit();
}

// Processamento de POST para vincular/desvincular usuários
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['vincular_usuario'])) {
        $usuarioId = $_POST['usuario_id'];
        $tipoVinculo = $_POST['tipo_vinculo'];
        
        $resultado = $usuarioEstabelecimentoModel->vincularUsuario($usuarioId, $id, $tipoVinculo);
        
        if ($resultado['success']) {
            $_SESSION['success_message'] = $resultado['message'];
        } else {
            $_SESSION['error_message'] = $resultado['message'];
        }
        
        header("Location: usuarios_pessoa_fisica.php?id=$id");
        exit();
    }
    
    if (isset($_POST['desvincular_usuario'])) {
        $vinculoId = $_POST['vinculo_id'];
        
        $resultado = $usuarioEstabelecimentoModel->desvincularUsuario($vinculoId);
        
        if ($resultado['success']) {
            $_SESSION['success_message'] = $resultado['message'];
        } else {
            $_SESSION['error_message'] = $resultado['message'];
        }
        
        header("Location: usuarios_pessoa_fisica.php?id=$id");
        exit();
    }
    
    if (isset($_POST['atualizar_vinculo'])) {
        $vinculoId = $_POST['vinculo_id'];
        $tipoVinculo = $_POST['tipo_vinculo'];
        
        $resultado = $usuarioEstabelecimentoModel->atualizarVinculo($vinculoId, $tipoVinculo);
        
        if ($resultado['success']) {
            $_SESSION['success_message'] = $resultado['message'];
        } else {
            $_SESSION['error_message'] = $resultado['message'];
        }
        
        header("Location: usuarios_pessoa_fisica.php?id=$id");
        exit();
    }
}
?>

<div class="container mx-auto px-3 py-6 mt-4">
    <div class="flex flex-col md:flex-row gap-6">

        <!-- Sidebar Menu -->
        <div class="w-full md:w-1/4">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3">
                    <h5 class="text-white font-medium text-lg">Menu</h5>
                </div>
                <div class="divide-y divide-gray-200">
                    <a href="detalhes_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-info-circle mr-3 text-gray-500"></i>Detalhes
                    </a>
                    <a href="editar_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-edit mr-3 text-gray-500"></i>Editar
                    </a>
                    <a href="../Processo/processos_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150">
                        <i class="fas fa-folder-open mr-3 text-gray-500"></i>Processos
                    </a>
                    <a href="usuarios_pessoa_fisica.php?id=<?php echo $id; ?>" class="flex items-center px-4 py-3 hover:bg-blue-50 transition-colors duration-150 text-blue-800 font-medium">
                        <i class="fas fa-users mr-3 text-blue-500"></i>Usuários Vinculados
                    </a>
                </div>
            </div>
            <div class="mt-4">
                <!-- EXCLUIR ESTABELECIMENTO -->
                <?php if ($_SESSION['user']['nivel_acesso'] == 1) : // Apenas administradores podem excluir 
                ?>
                    <form method="POST" action="excluir_estabelecimento.php" onsubmit="return confirm('Você tem certeza que deseja excluir este estabelecimento?');">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-150 flex items-center justify-center">
                            <i class="fas fa-trash-alt mr-2"></i>Excluir Estabelecimento
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="w-full md:w-3/4">
            <!-- Header com informações básicas -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3">
                    <h5 class="text-white font-medium text-lg">
                        Dados da Pessoa Física: <?php echo htmlspecialchars($dadosEstabelecimento['nome'] ?? 'Não informado'); ?>
                    </h5>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center">
                            <span class="font-semibold text-gray-700 mr-2">CPF:</span>
                            <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['cpf'] ?? 'Não informado'); ?></span>
                        </div>
                        <div class="flex items-center">
                            <span class="font-semibold text-gray-700 mr-2">Email:</span>
                            <span class="text-gray-600"><?php echo htmlspecialchars($dadosEstabelecimento['email'] ?? 'Não informado'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card para Usuários Vinculados -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3 flex justify-between items-center">
                    <h5 class="text-white font-medium text-lg">Usuários Vinculados</h5>
                    <button type="button" class="bg-white text-blue-800 hover:bg-blue-50 text-sm font-medium py-1.5 px-3 rounded transition-colors duration-150" data-bs-toggle="modal" data-bs-target="#modalVincularUsuario">
                        <i class="fas fa-user-plus mr-1"></i>Vincular Usuário
                    </button>
                </div>
                <div class="p-5">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="ml-3">
                                    <p><?php echo $_SESSION['success_message']; ?></p>
                                </div>
                            </div>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="ml-3">
                                    <p><?php echo $_SESSION['error_message']; ?></p>
                                </div>
                            </div>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <?php if (empty($usuariosVinculados)): ?>
                        <div class="bg-blue-50 rounded-lg p-6 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-user-slash text-blue-300 text-4xl mb-4"></i>
                                <p class="text-blue-800 font-medium">Nenhum usuário vinculado a esta pessoa física.</p>
                                <p class="text-blue-600 text-sm mt-2">Clique no botão "Vincular Usuário" para adicionar um vínculo.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-4">
                            <?php foreach ($usuariosVinculados as $usuario) : ?>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow duration-200">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="p-2">
                                            <p class="text-sm text-gray-500 mb-1">Nome:</p>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($usuario['nome']); ?></p>
                                        </div>
                                        <div class="p-2">
                                            <p class="text-sm text-gray-500 mb-1">Email:</p>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($usuario['email']); ?></p>
                                        </div>
                                        <div class="p-2">
                                            <p class="text-sm text-gray-500 mb-1">Telefone:</p>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($usuario['telefone']); ?></p>
                                        </div>
                                        <div class="p-2">
                                            <p class="text-sm text-gray-500 mb-1">Tipo de Vínculo:</p>
                                            <p class="font-medium text-gray-900">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo htmlspecialchars($usuario['tipo_vinculo']); ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="p-2 flex items-center justify-end space-x-2 col-span-1 md:col-span-2">
                                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarVinculo"
                                                data-id="<?php echo $usuario['id']; ?>"
                                                data-tipo-vinculo="<?php echo $usuario['tipo_vinculo']; ?>">
                                                <i class="fas fa-edit mr-1"></i> Editar Vínculo
                                            </button>
                                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDesvincularUsuario"
                                                data-id="<?php echo $usuario['id']; ?>"
                                                data-nome="<?php echo $usuario['nome']; ?>">
                                                <i class="fas fa-unlink mr-1"></i> Desvincular
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Vincular Usuário -->
<div class="modal fade" id="modalVincularUsuario" tabindex="-1" aria-labelledby="modalVincularUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVincularUsuarioLabel">Vincular Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="usuarios_pessoa_fisica.php?id=<?php echo $id; ?>" method="POST">
                    <div class="mb-3">
                        <label for="usuario_id" class="form-label">Selecione o Usuário</label>
                        <select class="form-select" name="usuario_id" id="usuario_id" required>
                            <option value="" selected disabled>Selecione um usuário</option>
                            <?php 
                            $usuariosDisponiveis = $usuarioEstabelecimentoModel->getUsuariosNaoVinculados($id);
                            foreach ($usuariosDisponiveis as $usuario): 
                            ?>
                                <option value="<?php echo $usuario['id']; ?>">
                                    <?php echo htmlspecialchars($usuario['nome'] . ' (' . $usuario['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="tipo_vinculo" class="form-label">Tipo de Vínculo</label>
                        <select class="form-select" name="tipo_vinculo" id="tipo_vinculo" required>
                            <option value="" selected disabled>Selecione o tipo de vínculo</option>
                            <option value="PROPRIETÁRIO">PROPRIETÁRIO</option>
                            <option value="RESPONSÁVEL LEGAL">RESPONSÁVEL LEGAL</option>
                            <option value="RESPONSÁVEL TÉCNICO">RESPONSÁVEL TÉCNICO</option>
                            <option value="CONTADOR">CONTADOR</option>
                            <option value="FUNCIONÁRIO">FUNCIONÁRIO</option>
                            <option value="OUTRO">OUTRO</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="vincular_usuario" class="btn btn-primary">Vincular</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Editar Vínculo -->
<div class="modal fade" id="modalEditarVinculo" tabindex="-1" aria-labelledby="modalEditarVinculoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarVinculoLabel">Editar Tipo de Vínculo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="usuarios_pessoa_fisica.php?id=<?php echo $id; ?>" method="POST">
                    <input type="hidden" name="vinculo_id" id="editar_vinculo_id">
                    <div class="mb-3">
                        <label for="editar_tipo_vinculo" class="form-label">Tipo de Vínculo</label>
                        <select class="form-select" name="tipo_vinculo" id="editar_tipo_vinculo" required>
                            <option value="PROPRIETÁRIO">PROPRIETÁRIO</option>
                            <option value="RESPONSÁVEL LEGAL">RESPONSÁVEL LEGAL</option>
                            <option value="RESPONSÁVEL TÉCNICO">RESPONSÁVEL TÉCNICO</option>
                            <option value="CONTADOR">CONTADOR</option>
                            <option value="FUNCIONÁRIO">FUNCIONÁRIO</option>
                            <option value="OUTRO">OUTRO</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="atualizar_vinculo" class="btn btn-primary">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Desvincular Usuário -->
<div class="modal fade" id="modalDesvincularUsuario" tabindex="-1" aria-labelledby="modalDesvincularUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDesvincularUsuarioLabel">Confirmar Desvinculação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja desvincular o usuário <span id="nome_usuario_desvincular" class="font-medium"></span>?</p>
                <form action="usuarios_pessoa_fisica.php?id=<?php echo $id; ?>" method="POST">
                    <input type="hidden" name="vinculo_id" id="desvincular_vinculo_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="desvincular_usuario" class="btn btn-danger">Desvincular</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
    // Modal de Editar Vínculo - Preencher dados
    document.querySelectorAll('[data-bs-target="#modalEditarVinculo"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const tipoVinculo = this.getAttribute('data-tipo-vinculo');
            
            document.getElementById('editar_vinculo_id').value = id;
            document.getElementById('editar_tipo_vinculo').value = tipoVinculo;
        });
    });
    
    // Modal de Desvincular Usuário - Preencher dados
    document.querySelectorAll('[data-bs-target="#modalDesvincularUsuario"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nome = this.getAttribute('data-nome');
            
            document.getElementById('desvincular_vinculo_id').value = id;
            document.getElementById('nome_usuario_desvincular').textContent = nome;
        });
    });
</script>
<?php ob_end_flush(); // End output buffering and send all content ?> 