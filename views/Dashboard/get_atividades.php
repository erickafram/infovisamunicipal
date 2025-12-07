<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    echo "Acesso não autorizado.";
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $estabelecimentoModel = new Estabelecimento($conn);
    $dadosEstabelecimento = $estabelecimentoModel->findById($id);

    if ($dadosEstabelecimento) {
        // Verificar o tipo de pessoa (física ou jurídica)
        if ($dadosEstabelecimento['tipo_pessoa'] == 'fisica') {
            // Para pessoa física, buscamos os CNAEs diretamente da tabela estabelecimento_cnaes
            $atividades = $estabelecimentoModel->getCnaesByEstabelecimentoId($id);
            
            echo '<div class="space-y-4">';
            echo '<div class="bg-blue-50 rounded-lg p-4 border border-blue-200">';
            echo '<h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center">';
            echo '<i class="fas fa-user mr-2"></i>Atividades da Pessoa Física';
            echo '</h3>';
            
            if (!empty($atividades)) {
                echo '<div class="space-y-3">';
                foreach ($atividades as $index => $atividade) {
                    echo '<div class="bg-white rounded-md p-3 border border-blue-100">';
                    echo '<div class="flex items-start space-x-3">';
                    echo '<div class="flex-shrink-0">';
                    echo '<span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-600 text-sm font-medium">' . ($index + 1) . '</span>';
                    echo '</div>';
                    echo '<div class="flex-1">';
                    echo '<div class="text-sm">';
                    echo '<span class="font-medium text-gray-700">Código:</span> ';
                    echo '<span class="text-blue-600 font-mono">' . htmlspecialchars($atividade['cnae']) . '</span>';
                    echo '</div>';
                    echo '<div class="text-sm text-gray-600 mt-1">';
                    echo '<span class="font-medium text-gray-700">Descrição:</span> ';
                    echo htmlspecialchars($atividade['descricao']);
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="text-center py-6">';
                echo '<i class="fas fa-exclamation-circle text-yellow-500 text-2xl mb-2"></i>';
                echo '<p class="text-gray-600">Nenhuma atividade cadastrada para esta pessoa física.</p>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        } else {
            // Para pessoa jurídica, continuamos com o comportamento original mas melhorado
            $cnaesSecundarios = !empty($dadosEstabelecimento['cnaes_secundarios']) ? 
                json_decode($dadosEstabelecimento['cnaes_secundarios'], true) : null;
?>

<div class="space-y-4">
    <!-- CNAE Principal -->
    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
        <h3 class="text-lg font-semibold text-green-900 mb-3 flex items-center">
            <i class="fas fa-star mr-2"></i>CNAE Principal
        </h3>
        <div class="bg-white rounded-md p-4 border border-green-100">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-building"></i>
                    </span>
                </div>
                <div class="flex-1">
                    <div class="text-sm mb-2">
                        <span class="font-medium text-gray-700">Código:</span> 
                        <span class="text-green-600 font-mono text-base font-semibold"><?php echo htmlspecialchars($dadosEstabelecimento['cnae_fiscal'] ?? 'Não informado'); ?></span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <span class="font-medium text-gray-700">Descrição:</span> 
                        <span class="text-gray-800"><?php echo htmlspecialchars($dadosEstabelecimento['cnae_fiscal_descricao'] ?? 'Não informado'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CNAEs Secundários -->
    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
        <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center">
            <i class="fas fa-list mr-2"></i>CNAEs Secundários
        </h3>
        
        <?php if (!empty($cnaesSecundarios)): ?>
            <div class="space-y-3">
                <?php foreach ($cnaesSecundarios as $index => $cnae): ?>
                    <div class="bg-white rounded-md p-4 border border-blue-100">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-600 text-sm font-medium"><?php echo $index + 1; ?></span>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm mb-2">
                                    <span class="font-medium text-gray-700">Código:</span> 
                                    <span class="text-blue-600 font-mono text-base font-semibold"><?php echo htmlspecialchars($cnae['codigo'] ?? 'Não informado'); ?></span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium text-gray-700">Descrição:</span> 
                                    <span class="text-gray-800"><?php echo htmlspecialchars($cnae['descricao'] ?? 'Não informado'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-md p-6 border border-blue-100 text-center">
                <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
                <p class="text-gray-600">Nenhum CNAE secundário registrado para este estabelecimento.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
        }
    } else {
        echo '<div class="bg-red-50 rounded-lg p-4 border border-red-200 text-center">';
        echo '<i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>';
        echo '<p class="text-red-600 font-medium">Estabelecimento não encontrado.</p>';
        echo '</div>';
    }
} else {
    echo '<div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-center">';
    echo '<i class="fas fa-exclamation-circle text-yellow-500 text-2xl mb-2"></i>';
    echo '<p class="text-yellow-600 font-medium">ID do estabelecimento não fornecido.</p>';
    echo '</div>';
}
$conn->close();
?>