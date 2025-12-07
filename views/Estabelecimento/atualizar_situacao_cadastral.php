<?php
session_start();
include '../header.php';
require_once '../../conf/database.php';

function consultarSituacaoCadastral($cnpj)
{
    $token = '8ab984d986b155d84b4f88dec6d4f8c3cd2e11c685d9805107df78e94ab488ca';
    $url = 'https://govnex.site/govnex/api/cnpj_api.php?cnpj=' . urlencode($cnpj) . '&token=' . urlencode($token);

    $options = [
        'http' => [
            'method' => 'GET',
            'header' => 'Content-type: application/x-www-form-urlencoded',
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    return json_decode($response, true);
}
function atualizarSituacaoCadastral($conn, $estabelecimento)
{
    $dadosApi = consultarSituacaoCadastral($estabelecimento['cnpj']);
    $alteracoes = [];

    if (!empty($dadosApi) && isset($dadosApi['descricao_situacao_cadastral'])) {
        $novaSituacao = $dadosApi['descricao_situacao_cadastral'];

        // Remova ou comente a verificação abaixo para atualizar sempre:
        // if (strtoupper($novaSituacao) !== 'ATIVA') {
        //     return $alteracoes;
        // }

        // Se a situação mudou, atualize o cadastro
        if ($novaSituacao !== $estabelecimento['descricao_situacao_cadastral']) {
            // Atualiza a tabela de estabelecimentos
            $stmt = $conn->prepare("
                UPDATE estabelecimentos 
                SET descricao_situacao_cadastral = ?, data_situacao_cadastral = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $novaSituacao, $estabelecimento['id']);
            $stmt->execute();

            // Registra a atualização no histórico
            $stmt = $conn->prepare("
                INSERT INTO atualizacoes_cadastrais (estabelecimento_id, descricao_situacao_cadastral, data_alteracao) 
                VALUES (?, ?, NOW())
            ");
            $stmt->bind_param("is", $estabelecimento['id'], $novaSituacao);
            $stmt->execute();

            $alteracoes[] = [
                'estabelecimento' => $estabelecimento['cnpj'],
                'nova_situacao' => $novaSituacao,
                'data_alteracao' => date('Y-m-d H:i:s'),
            ];

            // Se a situação for INATIVA ou SUSPENSA, gera a receita
            if (in_array(strtoupper($novaSituacao), ['INATIVA', 'SUSPENSA'])) {
                gerarReceita($conn, $estabelecimento['id'], $novaSituacao);
            }
        }
    }

    return $alteracoes;
}


function gerarReceita($conn, $estabelecimentoId, $situacao)
{
    $valorReceita = 100.00; // Valor fixo ou dinâmico
    $descricao = "Cobrança por situação cadastral: $situacao";

    $stmt = $conn->prepare("
        INSERT INTO receitas (estabelecimento_id, descricao, valor, data_criacao) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("isd", $estabelecimentoId, $descricao, $valorReceita);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Selecionar todos os estabelecimentos
    $result = $conn->query("SELECT id, cnpj, descricao_situacao_cadastral FROM estabelecimentos");
    $alteracoes = [];

    if ($result->num_rows > 0) {
        while ($estabelecimento = $result->fetch_assoc()) {
            $alteracoes = array_merge($alteracoes, atualizarSituacaoCadastral($conn, $estabelecimento));
        }
    }
}
?>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Atualização de Situação Cadastral</h2>
        <form id="atualizacaoForm" method="POST" action="">
            <button type="submit" class="btn btn-primary" id="verificarBtn">Verificar Alterações</button>
        </form>

        <div id="loading" class="mt-3 text-center d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Verificando...</span>
            </div>
            <p class="mt-2">Aguarde, estamos verificando as alterações...</p>
        </div>

        <div id="resultado" class="mt-4">
            <!-- Resultados das alterações serão exibidos aqui -->
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#atualizacaoForm').on('submit', function(e) {
                e.preventDefault();

                // Mostrar o indicador de carregamento
                $('#loading').removeClass('d-none');
                $('#verificarBtn').prop('disabled', true);

                // Enviar o formulário via AJAX
                $.ajax({
                    url: '',
                    type: 'POST',
                    success: function(response) {
                        // Esconder o indicador de carregamento
                        $('#loading').addClass('d-none');
                        $('#verificarBtn').prop('disabled', false);

                        // Atualizar a seção de resultados
                        $('#resultado').html($(response).find('#resultado').html());
                    },
                    error: function() {
                        $('#loading').addClass('d-none');
                        $('#verificarBtn').prop('disabled', false);
                        alert('Erro ao verificar alterações. Tente novamente.');
                    }
                });
            });
        });
    </script>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Exibição do resultado após o POST
        echo '<div id="resultado">';
        if (!empty($alteracoes)) {
            echo '<h3>Estabelecimentos com Situação Alterada</h3>';
            echo '<table class="table table-bordered mt-3">';
            echo '<thead class="table-light">';
            echo '<tr><th>CNPJ</th><th>Nova Situação</th><th>Data da Alteração</th></tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($alteracoes as $alteracao) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($alteracao['estabelecimento']) . '</td>';
                echo '<td>' . htmlspecialchars($alteracao['nova_situacao']) . '</td>';
                echo '<td>' . htmlspecialchars($alteracao['data_alteracao']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<div class="alert alert-info mt-3" role="alert">Nenhuma alteração encontrada.</div>';
        }
        echo '</div>';
    }
    ?>
</body>

</html>