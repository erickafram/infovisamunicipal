<?php
session_start();
include '../header.php';

// Verificação de autenticação e nível de acesso 
// 1 Administrador, 2 Suporte, 3 Gerente, 4 Fiscal
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['nivel_acesso'], [1,3])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Obter o município do usuário logado
$usuario_municipio = $_SESSION['user']['municipio'];

// Obter usuários técnicos com nível de acesso 3 e 4 do mesmo município
$query = "SELECT id, nome_completo FROM usuarios WHERE (nivel_acesso = 3 OR nivel_acesso = 4) AND municipio = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $usuario_municipio);
$stmt->execute();
$result = $stmt->get_result();
$tecnicos = $result->fetch_all(MYSQLI_ASSOC);

// Obter tipos de ações
$query_tipos_acoes = "SELECT id, descricao FROM tipos_acoes_executadas";
$result_tipos_acoes = $conn->query($query_tipos_acoes);
$tipos_acoes = $result_tipos_acoes->fetch_all(MYSQLI_ASSOC);

$estabelecimento_id = $_GET['id'];
$processo_id = $_GET['processo_id'];

// Buscar informações do estabelecimento
$query_estabelecimento = "SELECT nome_fantasia, razao_social FROM estabelecimentos WHERE id = ?";
$stmt_estabelecimento = $conn->prepare($query_estabelecimento);
$stmt_estabelecimento->bind_param("i", $estabelecimento_id);
$stmt_estabelecimento->execute();
$result_estabelecimento = $stmt_estabelecimento->get_result();
$estabelecimento = $result_estabelecimento->fetch_assoc();
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../Dashboard/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="../Processo/detalhes_processo.php?id=<?php echo $processo_id; ?>">Processo</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Nova Ordem de Serviço</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary bg-gradient text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-clipboard-list fa-2x me-3"></i>
                <div>
                    <h5 class="card-title mb-0">Nova Ordem de Serviço</h5>
                    <?php if (isset($estabelecimento)): ?>
                    <p class="mb-0 opacity-75 small">
                        <?php echo htmlspecialchars($estabelecimento['nome_fantasia'] ?? $estabelecimento['razao_social']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card-body p-4">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="../../controllers/OrdemServicoController.php?action=criar" method="POST" id="ordemServicoForm" class="needs-validation" novalidate>
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" required>
                            <label for="data_inicio">Data de Início</label>
                            <div class="invalid-feedback">
                                Por favor, selecione a data de início.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="data_fim" name="data_fim" required>
                            <label for="data_fim">Data de Conclusão</label>
                            <div class="invalid-feedback">
                                Por favor, selecione a data de conclusão.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="acoes_executadas" class="form-label">
                        <i class="fas fa-tasks me-2"></i>Ações a serem Executadas
                    </label>
                    <select class="form-select select2" id="acoes_executadas" name="acoes_executadas[]" multiple required>
                        <?php foreach ($tipos_acoes as $tipo_acao): ?>
                            <option value="<?php echo $tipo_acao['id']; ?>"><?php echo htmlspecialchars($tipo_acao['descricao']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Selecione uma ou mais ações que serão executadas.</div>
                </div>

                <div class="mb-4">
                    <label for="tecnicos" class="form-label">
                        <i class="fas fa-users me-2"></i>Técnicos Responsáveis
                    </label>
                    <select class="form-select select2" id="tecnicos" name="tecnicos[]" multiple required>
                        <?php foreach ($tecnicos as $tecnico): ?>
                            <option value="<?php echo $tecnico['id']; ?>"><?php echo htmlspecialchars($tecnico['nome_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Selecione um ou mais técnicos que serão responsáveis.</div>
                </div>

                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="observacao_check" onclick="toggleObservacao()">
                        <label class="form-check-label" for="observacao_check">Adicionar Observação</label>
                    </div>
                </div>

                <div class="mb-4" id="observacao_container" style="display: none;">
                    <div class="form-floating">
                        <textarea class="form-control" id="observacao" name="observacao" style="height: 120px"></textarea>
                        <label for="observacao">Observações</label>
                    </div>
                </div>

                <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimento_id; ?>">
                <input type="hidden" name="processo_id" value="<?php echo $processo_id; ?>">

                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="../Processo/detalhes_processo.php?id=<?php echo $processo_id; ?>" class="btn btn-outline-secondary me-md-2">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Criar Ordem de Serviço
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link para Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Link para Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Selecione as opções'
        });

        // Definir data mínima como hoje
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('data_inicio').setAttribute('min', today);
        document.getElementById('data_inicio').value = today;
        
        // Atualizar data mínima de fim quando a data de início mudar
        document.getElementById('data_inicio').addEventListener('change', function() {
            document.getElementById('data_fim').setAttribute('min', this.value);
            if (document.getElementById('data_fim').value < this.value) {
                document.getElementById('data_fim').value = this.value;
            }
        });

        // Validação do formulário
        const form = document.getElementById('ordemServicoForm');
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Validação adicional para datas
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;
            
            if (dataFim < dataInicio) {
                event.preventDefault();
                alert('A data de conclusão não pode ser anterior à data de início.');
                return false;
            }
            
            form.classList.add('was-validated');
        });
    });

    function toggleObservacao() {
        const observacaoContainer = document.getElementById('observacao_container');
        observacaoContainer.style.display = observacaoContainer.style.display === 'none' ? 'block' : 'none';
        
        if (observacaoContainer.style.display === 'block') {
            document.getElementById('observacao').focus();
        }
    }
</script>

<style>
    .card {
        border-radius: 10px;
        overflow: hidden;
    }
    
    .card-header {
        border-bottom: 0;
    }
    
    .form-floating > .form-control {
        height: calc(3.5rem + 2px);
        line-height: 1.25;
    }
    
    .form-floating > textarea.form-control {
        height: 120px;
    }
    
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 58px;
        padding-top: 16px;
    }
    
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
</style>

<?php include '../footer.php'; ?>