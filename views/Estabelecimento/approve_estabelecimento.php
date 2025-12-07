<?php
session_start();
require_once '../../conf/database.php';
require_once '../../models/Estabelecimento.php';

// Configurar o log para escrever no arquivo de log local
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log');

// Função para escrever no log
function escrever_log($mensagem) {
    error_log(date("[Y-m-d H:i:s]") . " - " . $mensagem);
}

escrever_log("=== INÍCIO DO PROCESSO DE APROVAÇÃO ===");
escrever_log("Requisição recebida: " . json_encode($_GET));

// Verificação de autenticação
if (!isset($_SESSION['user'])) {
    escrever_log("Erro: Usuário não autenticado");
    header("Location: ../../login.php");
    exit();
}

// Verificação do nível de acesso
if ($_SESSION['user']['nivel_acesso'] < 2) {
    escrever_log("Erro: Nível de acesso insuficiente. Nível do usuário: " . $_SESSION['user']['nivel_acesso']);
    header("Location: ../Dashboard/dashboard.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    escrever_log("ID do estabelecimento a ser aprovado: " . $id);
    
    // Verificar se o estabelecimento existe
    $estabelecimentoModel = new Estabelecimento($conn);
    $estabelecimento = $estabelecimentoModel->findById($id);
    
    if (!$estabelecimento) {
        escrever_log("Erro: Estabelecimento com ID $id não encontrado!");
        header("Location: ../Dashboard/dashboard.php?error=Estabelecimento não encontrado");
        exit();
    }
    
    escrever_log("Estabelecimento encontrado. Status atual: " . $estabelecimento['status']);
    
    // Se já estiver aprovado, apenas redirecionar
    if ($estabelecimento['status'] === 'aprovado') {
        escrever_log("Estabelecimento já está aprovado. Redirecionando.");
        header("Location: ../Dashboard/dashboard.php?info=Estabelecimento já estava aprovado");
        exit();
    }
    
    // Tentar atualizar diretamente no banco de dados
    escrever_log("Executando SQL UPDATE para aprovar o estabelecimento...");
    $sql = "UPDATE estabelecimentos SET status = 'aprovado' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        escrever_log("Erro ao preparar a query: " . $conn->error);
        header("Location: ../Dashboard/dashboard.php?error=Erro ao preparar a consulta");
        exit();
    }
    
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    
    if (!$result) {
        escrever_log("Erro na execução da query: " . $stmt->error);
        
        // Tentar pelo método do modelo como alternativa
        escrever_log("Tentando aprovar usando o método do modelo...");
        if ($estabelecimentoModel->approve($id)) {
            escrever_log("Aprovação concluída pelo método do modelo");
            header("Location: ../Dashboard/dashboard.php?success=Estabelecimento aprovado com sucesso");
        } else {
            escrever_log("Erro no método do modelo: " . $estabelecimentoModel->getLastError());
            header("Location: ../Dashboard/dashboard.php?error=Erro ao aprovar estabelecimento: " . urlencode($estabelecimentoModel->getLastError()));
        }
    } else {
        $affected = $stmt->affected_rows;
        escrever_log("SQL executado com sucesso. Linhas afetadas: $affected");
        
        if ($affected > 0) {
            escrever_log("Estabelecimento aprovado com sucesso!");
            header("Location: ../Dashboard/dashboard.php?success=Estabelecimento aprovado com sucesso");
        } else {
            escrever_log("Aviso: Nenhuma linha atualizada no banco de dados");
            
            // Verificar estado atual
            $verificacao = $conn->prepare("SELECT status FROM estabelecimentos WHERE id = ?");
            $verificacao->bind_param("i", $id);
            $verificacao->execute();
            $resultado = $verificacao->get_result();
            $estadoAtual = $resultado->fetch_assoc();
            
            escrever_log("Estado atual do estabelecimento: " . json_encode($estadoAtual));
            
            // Se já estiver aprovado, apenas informar
            if ($estadoAtual && $estadoAtual['status'] === 'aprovado') {
                header("Location: ../Dashboard/dashboard.php?info=Estabelecimento já estava aprovado");
            } else {
                header("Location: ../Dashboard/dashboard.php?warning=Nenhuma alteração foi aplicada");
            }
        }
    }
    escrever_log("=== FIM DO PROCESSO DE APROVAÇÃO ===");
    exit();
} else {
    escrever_log("Erro: ID do estabelecimento não fornecido na requisição");
    header("Location: ../Dashboard/dashboard.php?error=ID do estabelecimento não fornecido");
    exit();
}
?>
