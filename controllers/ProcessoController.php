<?php
require_once '../conf/database.php';
require_once '../models/Processo.php';

$log_file = '../logs/processo_controller.log'; // Defina o caminho para o arquivo de log

// Verificar se o diretório de logs existe, se não existir, criá-lo
if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0777, true);
}

function log_message($message)
{
    global $log_file;
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $log_file);
}

if (isset($_GET['action']) && $_GET['action'] === 'create') {
    // Verificar se todos os campos obrigatórios foram enviados
    if (isset($_POST['estabelecimento_id']) && isset($_POST['tipo_processo']) && isset($_POST['data_abertura'])) {
        $estabelecimento_id = $_POST['estabelecimento_id'];
        $tipo_processo = $_POST['tipo_processo'];
        $data_abertura = $_POST['data_abertura'];
        $ano_licenciamento = null;
        
        // Se for um processo de licenciamento, pegar o ano de licenciamento
        if ($tipo_processo == 'LICENCIAMENTO' && isset($_POST['ano_licenciamento'])) {
            $ano_licenciamento = $_POST['ano_licenciamento'];
        }
        
        log_message("Tentando criar um processo para o estabelecimento ID $estabelecimento_id");
        
        $processo = new Processo($conn);
        
        // Verificar se já existe um processo de licenciamento para o mesmo ano
        if ($tipo_processo == 'LICENCIAMENTO' && $processo->checkProcessoExistente($estabelecimento_id, date('Y'), $ano_licenciamento)) {
            log_message("Já existe um processo de licenciamento para o estabelecimento ID $estabelecimento_id no ano $ano_licenciamento");
            
            // Determinar para qual página redirecionar
            $return_url = "../views/Processo/processos.php?id=$estabelecimento_id";
            // Verifica se é uma pessoa física verificando a URL que chamou esta ação
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'processos_pessoa_fisica.php') !== false) {
                $return_url = "../views/Processo/processos_pessoa_fisica.php?id=$estabelecimento_id";
            }
            
            header("Location: $return_url&error=Já existe um processo de licenciamento para este estabelecimento no ano $ano_licenciamento");
            exit();
        }
        
        // Criar o processo
        if ($processo->createProcesso($estabelecimento_id, $tipo_processo, $ano_licenciamento)) {
            log_message("Processo criado com sucesso para o estabelecimento ID $estabelecimento_id");
            
            // Determinar para qual página redirecionar
            $return_url = "../views/Processo/processos.php?id=$estabelecimento_id";
            // Verifica se é uma pessoa física verificando a URL que chamou esta ação
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'processos_pessoa_fisica.php') !== false) {
                $return_url = "../views/Processo/processos_pessoa_fisica.php?id=$estabelecimento_id";
            }
            
            header("Location: $return_url&success=Processo criado com sucesso");
            exit();
        } else {
            log_message("Erro ao criar o processo para o estabelecimento ID $estabelecimento_id: " . $processo->getLastError());
            
            // Determinar para qual página redirecionar
            $return_url = "../views/Processo/processos.php?id=$estabelecimento_id";
            // Verifica se é uma pessoa física verificando a URL que chamou esta ação
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'processos_pessoa_fisica.php') !== false) {
                $return_url = "../views/Processo/processos_pessoa_fisica.php?id=$estabelecimento_id";
            }
            
            header("Location: $return_url&error=Erro ao criar o processo: " . urlencode($processo->getLastError()));
            exit();
        }
    } else {
        log_message("Campos obrigatórios não fornecidos ao criar um processo");
        header("Location: ../views/Dashboard/dashboard.php?error=Campos obrigatórios não fornecidos");
        exit();
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
    if (isset($_GET['id'])) {
        $processo_id = $_GET['id'];
        log_message("Tentando excluir o processo com ID $processo_id");
        
        $processo = new Processo($conn);
        $estabelecimento_id = $processo->getEstabelecimentoIdByProcessoId($processo_id);
        
        if ($processo->deleteProcesso($processo_id)) {
            log_message("Processo com ID $processo_id excluído com sucesso");
            
            // Determinar para qual página redirecionar
            $return_url = "../views/Processo/processos.php?id=$estabelecimento_id";
            // Se foi fornecido um id de retorno específico
            if (isset($_GET['return_id'])) {
                $estabelecimento_id = $_GET['return_id'];
            }
            
            // Verifica se é uma pessoa física verificando a URL que chamou esta ação
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'processos_pessoa_fisica.php') !== false) {
                $return_url = "../views/Processo/processos_pessoa_fisica.php?id=$estabelecimento_id";
            }
            
            header("Location: $return_url&success=Processo excluído com sucesso");
            exit();
        } else {
            log_message("Erro ao excluir o processo com ID $processo_id: " . $processo->getLastError());
            
            // Determinar para qual página redirecionar
            $return_url = "../views/Processo/processos.php?id=$estabelecimento_id";
            // Se foi fornecido um id de retorno específico
            if (isset($_GET['return_id'])) {
                $estabelecimento_id = $_GET['return_id'];
            }
            
            // Verifica se é uma pessoa física verificando a URL que chamou esta ação
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'processos_pessoa_fisica.php') !== false) {
                $return_url = "../views/Processo/processos_pessoa_fisica.php?id=$estabelecimento_id";
            }
            
            header("Location: $return_url&error=Erro ao excluir o processo: " . urlencode($processo->getLastError()));
            exit();
        }
    } else {
        log_message("ID do processo não fornecido para exclusão");
        header("Location: ../views/Dashboard/dashboard.php?error=ID do processo não fornecido");
        exit();
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'finalize') {
    if (isset($_GET['id'])) {
        $processo_id = $_GET['id'];
        log_message("Tentando finalizar o processo com ID $processo_id");

        // Atualizar status para 'resolvido' na tabela processos_responsaveis
        $stmt = $conn->prepare("UPDATE processos_responsaveis SET status = 'resolvido' WHERE processo_id = ?");
        if ($stmt === false) {
            log_message("Erro ao preparar a consulta: " . $conn->error);
            header("Location: ../views/Dashboard/dashboard.php?error=Erro ao preparar a consulta.");
            exit();
        }

        $stmt->bind_param('i', $processo_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                log_message("Processo_responsavel com processo_id $processo_id finalizado com sucesso.");
                header("Location: ../views/Dashboard/dashboard.php?success=Processo finalizado com sucesso");
                exit();
            } else {
                log_message("Nenhuma linha foi afetada ao finalizar o processo_responsavel com processo_id $processo_id.");
                header("Location: ../views/Dashboard/dashboard.php?error=Nenhuma linha foi afetada. Verifique o ID do processo.");
                exit();
            }
        } else {
            $error = $stmt->error;
            log_message("Erro ao finalizar o processo_responsavel com processo_id $processo_id: $error");
            header("Location: ../views/Dashboard/dashboard.php?error=Erro ao finalizar o processo: " . urlencode($error));
            exit();
        }
    } else {
        log_message("ID do processo não fornecido.");
        header("Location: ../views/Dashboard/dashboard.php?error=ID do processo não fornecido.");
        exit();
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'updateAlerta') {
    // Essa ação lida com requisições AJAX para atualizar o status de um alerta
    // Retorna resposta em formato JSON
    header('Content-Type: application/json');
    
    // Verificar se o método é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST.']);
        exit();
    }
    
    // Verificar se os parâmetros necessários estão presentes
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios não fornecidos (id, status).']);
        exit();
    }
    
    $alerta_id = $_POST['id'];
    $status = $_POST['status'];
    
    // Validar o status (apenas status válidos)
    $status_validos = ['ativo', 'finalizado', 'cancelado'];
    if (!in_array($status, $status_validos)) {
        echo json_encode(['success' => false, 'message' => 'Status inválido. Use: ' . implode(', ', $status_validos)]);
        exit();
    }
    
    log_message("Tentando atualizar o alerta com ID $alerta_id para o status: $status");
    
    // Atualizar o status do alerta
    $stmt = $conn->prepare("UPDATE alertas_processo SET status = ? WHERE id = ?");
    if ($stmt === false) {
        log_message("Erro ao preparar a consulta: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar a consulta: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param('si', $status, $alerta_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            log_message("Alerta com ID $alerta_id atualizado para o status: $status");
            echo json_encode(['success' => true, 'message' => 'Alerta atualizado com sucesso.']);
        } else {
            log_message("Nenhuma linha foi afetada ao atualizar o alerta com ID $alerta_id.");
            echo json_encode(['success' => false, 'message' => 'Nenhum alerta foi atualizado. Verifique o ID do alerta.']);
        }
    } else {
        $error = $stmt->error;
        log_message("Erro ao atualizar o alerta com ID $alerta_id: $error");
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o alerta: ' . $error]);
    }
    
    exit();
} else {
    log_message("Ação não reconhecida.");
    header("Location: ../views/Dashboard/dashboard.php?error=Ação não reconhecida.");
    exit();
}
