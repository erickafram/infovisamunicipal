<?php
// Verifica se foi enviada uma consulta de CNAE
if ($_GET != null && isset($_GET['cnae'])) {
    $cnae = $_GET['cnae'];
    $municipio = isset($_GET['municipio']) ? $_GET['municipio'] : '';
    $check_risk_group = isset($_GET['check_risk_group']) && $_GET['check_risk_group'] === 'true';
    
    // Consulta a API do IBGE para obter informações sobre o CNAE
    $url_api = file_get_contents("https://servicodados.ibge.gov.br/api/v2/cnae/subclasses/{$cnae}");

    // Decodifica o resultado da consulta para array associativo
    $result = json_decode($url_api, true);

    // Verifica se o resultado da consulta é válido e se é um array
    if (!empty($result) && is_array($result)) {
        // Pega o primeiro item da lista de resultados
        $subclasse = $result[0];

        $descricao = $subclasse['descricao'];
        $grupo = $subclasse['classe']['grupo']['id'] . " - " . $subclasse['classe']['grupo']['descricao'];
        $observacao = '';

        if (isset($subclasse['observacoes'])) {
            foreach ($subclasse['observacoes'] as $obs) {
                $observacao .= $obs . '<br/>';
            }
        }
        
        // Buscar o grupo de risco no banco de dados, se solicitado
        $grupoRisco = '';
        if ($check_risk_group && !empty($municipio)) {
            require_once '../../conf/database.php';
            require_once '../../models/Estabelecimento.php';
            
            $estabelecimento = new Estabelecimento($conn);
            
            // Buscar todos os grupos de risco para este CNAE
            $query = "
                SELECT gr.descricao as grupo_risco
                FROM atividade_grupo_risco agr
                JOIN grupo_risco gr ON agr.grupo_risco_id = gr.id
                WHERE agr.cnae = ? AND agr.municipio = ?
                ORDER BY gr.id
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ss', $cnae, $municipio);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $grupos = [];
            while ($row = $result->fetch_assoc()) {
                $grupos[] = $row['grupo_risco'];
            }
            
            if (!empty($grupos)) {
                $grupoRisco = implode(' E ', $grupos);
            }
        }
        
        // Adicionar informação do grupo de risco à resposta se estiver disponível
        $grupoRiscoInfo = '';
        if ($grupoRisco) {
            $grupoRiscoInfo = ", \"grupo_risco\":\"{$grupoRisco}\"";
        }

        // Exibe os dados da subclasse de forma linear
        echo "
        <ul class='list-group'>
            <li class='list-group-item d-flex justify-content-between align-items-center'>
                <div>
                    <strong>CNAE:</strong> {$subclasse['id']} - <strong>Descrição:</strong> {$descricao}<br>
                    <strong>Grupo:</strong> {$grupo}
                    {$grupoRiscoInfo}
                </div>
                <!-- Ícone de + para vincular -->
                <button type='button' class='btn btn-secondary btn-sm' onclick='addCNAE(\"{$subclasse['id']}\", \"{$descricao}\")'>
                    <i class='fas fa-plus'></i>
                </button>
            </li>
        </ul>
        ";
    } else {
        echo "<div class='alert alert-danger'>CNAE Inválido</div>";
    }
}
