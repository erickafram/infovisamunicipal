<?php
require_once '../../conf/database.php';
require_once '../../models/Processo.php';
require_once '../../models/Estabelecimento.php';

session_start();

// Inclua o arquivo FAQ
include 'faq.php';

$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'];
$response = ['reply' => ''];

if (!isset($_SESSION['chat_state'])) {
    $_SESSION['chat_state'] = 'initial';
}

$processoModel = new Processo($conn);
$estabelecimentoModel = new Estabelecimento($conn);

// Função para buscar respostas no FAQ
function searchFaq($message, $faq) {
    $message = strtolower($message);
    $bestMatch = null;
    $bestMatchCount = 0;
    
    foreach ($faq as $question => $answer) {
        $keywords = explode(' ', strtolower($question));
        $matchCount = 0;
        
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                $matchCount++;
            }
        }
        
        if ($matchCount > $bestMatchCount) {
            $bestMatchCount = $matchCount;
            $bestMatch = $answer;
        }
    }
    
    if ($bestMatch) {
        return $bestMatch . "\n\nTem mais alguma coisa que posso ajudar ou deseja encerrar?\n1. Nova consulta\n2. Finalizar atendimento\n3. Iniciar nova conversa";
    }
    
    return null;
}

switch ($_SESSION['chat_state']) {
    case 'initial':
        if ($message === '1') {
            $_SESSION['chat_state'] = 'consulta';
            $response['reply'] = "Por favor, escolha uma das opções abaixo para continuar:\n1. Você deseja saber sobre o andamento do seu processo\n2. Informações de quais documentos devem ser apresentados para o Processo de Licenciamento Sanitário\n3. Tire suas dúvidas sobre qualquer tema.";
        } else {
            $response['reply'] = "Opção inválida. Por favor, escolha uma das opções abaixo para continuar:\nDigite 1 para tirar suas dúvidas.";
        }
        break;

    case 'consulta':
        if ($message === '1') {
            $_SESSION['chat_state'] = 'process_status';
            $response['reply'] = 'Por favor, digite o CNPJ ou nome do estabelecimento:';
        } elseif ($message === '2') {
            $_SESSION['chat_state'] = 'licenciamento';
            $response['reply'] = 'Para mais informações sobre os documentos necessários para o processo de licenciamento sanitário, visite: [link para o sistema]\n\nDeseja realizar uma nova consulta ou finalizar o atendimento?\n1. Nova consulta\n2. Finalizar atendimento\n3. Iniciar nova conversa';
            $_SESSION['chat_state'] = 'after_consulta';
        } elseif ($message === '3') {
            $_SESSION['chat_state'] = 'faq';
            $response['reply'] = 'Digite sua dúvida e eu tentarei encontrar a resposta no manual do sistema:';
        } else {
            $response['reply'] = "Opção inválida. Por favor, escolha uma das opções abaixo para continuar:\n1. Você deseja saber sobre o andamento do seu processo\n2. Informações de quais documentos devem ser apresentados para o Processo de Licenciamento Sanitário\n3. Tire suas dúvidas sobre qualquer tema.";
        }
        break;

    case 'process_status':
        if (preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}$/', $message)) {
            // Se a mensagem for um CNPJ
            $estabelecimento = $estabelecimentoModel->findByCnpjAndUsuario($message, $_SESSION['user']['id']);
            if ($estabelecimento) {
                $processos = $processoModel->getProcessosByEstabelecimento($estabelecimento['id']);
                if ($processos) {
                    $response['reply'] = "Processos encontrados para o estabelecimento {$estabelecimento['nome_fantasia']}:\n";
                    foreach ($processos as $processo) {
                        $response['reply'] .= "- Estabelecimento: {$estabelecimento['nome_fantasia']}, Número do Processo: {$processo['numero_processo']}, Status: {$processo['status']}\n";
                    }
                } else {
                    $response['reply'] = "Nenhum processo encontrado para o CNPJ $message.";
                }
            } else {
                $response['reply'] = "Nenhum estabelecimento encontrado para o CNPJ $message.";
            }
        } else {
            // Se a mensagem for um nome do estabelecimento
            $estabelecimentos = $estabelecimentoModel->searchByNameAndUsuario($message, $_SESSION['user']['id']);
            if ($estabelecimentos) {
                foreach ($estabelecimentos as $estabelecimento) {
                    $processos = $processoModel->getProcessosByEstabelecimento($estabelecimento['id']);
                    if ($processos) {
                        $response['reply'] .= "Processos encontrados para o estabelecimento {$estabelecimento['nome_fantasia']}:\n";
                        foreach ($processos as $processo) {
                            $response['reply'] .= "- Estabelecimento: {$estabelecimento['nome_fantasia']}, Número do Processo: {$processo['numero_processo']}, Status: {$processo['status']}\n";
                        }
                    } else {
                        $response['reply'] .= "Nenhum processo encontrado para o estabelecimento {$estabelecimento['nome_fantasia']}.\n";
                    }
                }
            } else {
                $response['reply'] = "Nenhum estabelecimento encontrado com o nome $message.";
            }
        }
        $response['reply'] .= "\n\nDeseja realizar uma nova consulta ou finalizar o atendimento?\n1. Nova consulta\n2. Finalizar atendimento\n3. Iniciar nova conversa";
        $_SESSION['chat_state'] = 'after_consulta';
        break;

    case 'after_consulta':
        if ($message === '1') {
            $_SESSION['chat_state'] = 'consulta';
            $response['reply'] = "Por favor, escolha uma das opções abaixo para continuar:\n1. Você deseja saber sobre o andamento do seu processo\n2. Informações de quais documentos devem ser apresentados para o Processo de Licenciamento Sanitário\n3. Tire suas dúvidas sobre qualquer tema.";
        } elseif ($message === '3') {
            $_SESSION['chat_state'] = 'initial';
            $response['reply'] = "Digite 1 para tirar suas dúvidas.";
        } else {
            $response['reply'] = 'Atendimento finalizado. Obrigado por usar o serviço de chat.';
            $_SESSION['chat_state'] = 'initial';
        }
        break;

    case 'licenciamento':
        $response['reply'] = 'Para mais informações sobre os documentos necessários para o processo de licenciamento sanitário, visite: [link para o sistema]\n\nDeseja realizar uma nova consulta ou finalizar o atendimento?\n1. Nova consulta\n2. Finalizar atendimento\n3. Iniciar nova conversa';
        $_SESSION['chat_state'] = 'after_consulta';
        break;

    case 'faq':
        $faqResponse = searchFaq($message, $faq);
        if ($faqResponse) {
            $response['reply'] = $faqResponse;
        } else {
            $response['reply'] = "Desculpe, não encontrei uma resposta para sua dúvida. Aqui estão as principais dúvidas do sistema:\n- como consultar o andamento de um processo\n- documentos necessários para o licenciamento sanitário\n- quais documentos devo apresentar\n\nPara mais dúvidas, acesse nosso [Manual do Sistema](manual.php).\n\nTem mais alguma coisa que posso ajudar ou deseja encerrar?\n1. Nova consulta\n2. Finalizar atendimento\n3. Iniciar nova conversa";
            $_SESSION['chat_state'] = 'initial';
        }
        break;

    default:
        $response['reply'] = 'Desculpe, não entendi sua pergunta. Tente novamente ou escolha uma das opções disponíveis.';
        break;
}

echo json_encode($response);
?>
