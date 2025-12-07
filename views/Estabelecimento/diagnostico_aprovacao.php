<?php
session_start();
require_once '../../conf/database.php';

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    die("Acesso não autorizado. Faça login primeiro.");
}

// Verificação do nível de acesso
if ($_SESSION['user']['nivel_acesso'] < 2) {
    die("Acesso não autorizado. Você não tem permissão para realizar esta operação.");
}

// Habilitar exibição de erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para registrar no arquivo de log
function log_action($message) {
    error_log(date("[Y-m-d H:i:s]") . " - " . $message, 3, __DIR__ . '/error_log');
}

// ID do estabelecimento a ser verificado/aprovado
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Processar aprovação direta
if (isset($_POST['aprovar']) && isset($_POST['id'])) {
    $id_aprovar = intval($_POST['id']);
    log_action("Tentando aprovar diretamente o estabelecimento ID: $id_aprovar");
    
    try {
        // Tentativa 1: UPDATE simples
        $sql = "UPDATE estabelecimentos SET status = 'aprovado' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_aprovar);
        $result = $stmt->execute();
        $rows_affected = $stmt->affected_rows;
        
        if ($result && $rows_affected > 0) {
            $mensagem = "Estabelecimento aprovado com sucesso! Linhas afetadas: $rows_affected";
            log_action($mensagem);
        } else {
            $mensagem = "Falha na aprovação. Linhas afetadas: $rows_affected. Erro: " . $stmt->error;
            log_action($mensagem);
            
            // Tentativa 2: UPDATE com diagnóstico adicional
            log_action("Tentando verificar problema com UPDATE");
            $check_sql = "SELECT status FROM estabelecimentos WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $id_aprovar);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $status_atual = $check_result->fetch_assoc()['status'] ?? 'desconhecido';
            log_action("Status atual do estabelecimento: $status_atual");
            
            if ($status_atual === 'aprovado') {
                $mensagem .= "<br>O estabelecimento já está com status 'aprovado'.";
            } elseif ($status_atual === 'pendente') {
                // Tentativa 3: UPDATE forçado
                log_action("Status é 'pendente', tentando UPDATE forçado");
                $force_sql = "UPDATE estabelecimentos SET status = 'aprovado', lido = 0 WHERE id = ?";
                $force_stmt = $conn->prepare($force_sql);
                $force_stmt->bind_param("i", $id_aprovar);
                $force_result = $force_stmt->execute();
                $force_rows = $force_stmt->affected_rows;
                
                if ($force_result && $force_rows > 0) {
                    $mensagem = "Estabelecimento aprovado com sucesso (método forçado)! Linhas afetadas: $force_rows";
                    log_action($mensagem);
                } else {
                    $mensagem .= "<br>Tentativa forçada também falhou. Erro: " . $force_stmt->error;
                    log_action("Tentativa forçada falhou: " . $force_stmt->error);
                }
            }
        }
    } catch (Exception $e) {
        $mensagem = "Erro na aprovação: " . $e->getMessage();
        log_action($mensagem);
    }
}

// Buscar dados do estabelecimento
$estabelecimento = null;
$estrutura_tabela = null;

if ($id) {
    // Busca informações do estabelecimento
    $sql = "SELECT * FROM estabelecimentos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $estabelecimento = $result->fetch_assoc();
    
    // Busca estrutura da tabela
    $result_estrutura = $conn->query("SHOW COLUMNS FROM estabelecimentos");
    while ($row = $result_estrutura->fetch_assoc()) {
        $estrutura_tabela[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Aprovação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .header { margin-bottom: 30px; }
        .table-responsive { margin-bottom: 30px; }
        pre { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Diagnóstico de Aprovação de Estabelecimento</h1>
            <p class="lead">Esta ferramenta permite diagnosticar problemas na aprovação de estabelecimentos.</p>
            
            <?php if (isset($mensagem)): ?>
                <div class="alert alert-info">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Consultar Estabelecimento</div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label for="id" class="form-label">ID do Estabelecimento</label>
                                <input type="number" class="form-control" id="id" name="id" value="<?php echo $id; ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Consultar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($estabelecimento): ?>
            <h2>Dados do Estabelecimento #<?php echo $id; ?></h2>
            
            <div class="card mb-4">
                <div class="card-header">Informações Básicas</div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Nome Fantasia</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($estabelecimento['nome_fantasia'] ?? $estabelecimento['nome'] ?? 'N/A'); ?></dd>
                        
                        <dt class="col-sm-3">Tipo de Pessoa</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($estabelecimento['tipo_pessoa'] ?? 'N/A'); ?></dd>
                        
                        <dt class="col-sm-3">Status Atual</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-<?php echo $estabelecimento['status'] === 'aprovado' ? 'success' : ($estabelecimento['status'] === 'pendente' ? 'warning' : 'danger'); ?>">
                                <?php echo htmlspecialchars($estabelecimento['status']); ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-3">CNPJ/CPF</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($estabelecimento['cnpj'] ?? $estabelecimento['cpf'] ?? 'N/A'); ?></dd>
                        
                        <dt class="col-sm-3">Município</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($estabelecimento['municipio'] ?? 'N/A'); ?></dd>
                    </dl>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Aprovar Estabelecimento</div>
                        <div class="card-body">
                            <?php if ($estabelecimento['status'] === 'aprovado'): ?>
                                <div class="alert alert-success">
                                    Este estabelecimento já está aprovado.
                                </div>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <p>Clique no botão abaixo para tentar aprovar diretamente este estabelecimento:</p>
                                    <button type="submit" name="aprovar" class="btn btn-success">Aprovar Estabelecimento</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <h3>Dados Completos</h3>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Campo</th>
                                    <th>Valor</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estabelecimento as $campo => $valor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campo); ?></td>
                                        <td><?php 
                                            if (is_array($valor)) {
                                                echo '<pre>' . htmlspecialchars(json_encode($valor, JSON_PRETTY_PRINT)) . '</pre>';
                                            } else {
                                                echo htmlspecialchars($valor !== null ? $valor : 'NULL'); 
                                            }
                                        ?></td>
                                        <td>
                                            <?php
                                                $tipo = 'desconhecido';
                                                foreach ($estrutura_tabela as $coluna) {
                                                    if ($coluna['Field'] === $campo) {
                                                        $tipo = $coluna['Type'];
                                                        break;
                                                    }
                                                }
                                                echo $tipo;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($id): ?>
            <div class="alert alert-danger">
                Estabelecimento com ID <?php echo $id; ?> não encontrado.
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="../Dashboard/dashboard.php" class="btn btn-secondary">Voltar para o Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 