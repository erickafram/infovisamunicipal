<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principais Dúvidas do Sistema</title>
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
    <h1>Principais Dúvidas do Sistema</h1>
    <?php include 'faq.php'; ?>
    <div class="faq-list">
        <?php foreach ($faq as $question => $answer): ?>
            <div class="faq-item">
                <div class="faq-question"><?php echo htmlspecialchars($question); ?></div>
                <div class="faq-answer"><?php echo htmlspecialchars($answer); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
