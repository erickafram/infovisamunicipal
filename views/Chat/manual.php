<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual do Sistema INFOVISA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .faq-item {
            margin-bottom: 20px;
        }
        .faq-question {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .faq-answer {
            margin-left: 20px;
        }
    </style>
</head>
<body>
    <h1>Manual do Sistema INFOVISA</h1>
    <p>Bem-vindo ao manual do sistema INFOVISA. Aqui você encontrará respostas para as perguntas mais frequentes e instruções sobre como utilizar o sistema.</p>

    <h2>Perguntas Frequentes (FAQ)</h2>
    <?php include 'faq.php'; ?>
    <div class="faq-list">
        <?php foreach ($faq as $question => $answer): ?>
            <div class="faq-item">
                <div class="faq-question"><?php echo htmlspecialchars($question); ?></div>
                <div class="faq-answer"><?php echo htmlspecialchars($answer); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>Manual do Sistema</h2>
    <p>Aqui estão algumas instruções sobre como utilizar o sistema INFOVISA:</p>
    <ul>
        <li><strong>Como consultar o andamento de um processo:</strong> Para consultar o andamento de um processo, acesse a seção de processos no sistema e insira o número do processo ou o CNPJ do estabelecimento.</li>
        <li><strong>Documentos necessários para o licenciamento sanitário:</strong> Os documentos necessários para o processo de licenciamento sanitário incluem: formulário de solicitação, comprovante de pagamento de taxas, planta do estabelecimento, e outros documentos específicos conforme o tipo de atividade.</li>
        <li><strong>Como abrir um processo de licenciamento:</strong> Para abrir seu processo de licenciamento você precisa cadastrar o estabelecimento e ele estar aprovado pela equipe da vigilância sanitária. Após a aprovação, você poderá abrir o processo do ano vigente para o estabelecimento.</li>
        <!-- Adicione mais itens conforme necessário -->
    </ul>
</body>
</html>
