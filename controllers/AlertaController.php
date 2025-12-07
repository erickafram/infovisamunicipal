<?php
require_once '../../conf/database.php';
require_once '../../models/Alerta.php';
require_once '../../models/Processo.php'; // Adicione esta linha

class AlertaController
{
    private $alertaModel;
    private $processoModel;

    public function __construct($conn)
    {
        $this->alertaModel = new Alerta($conn);
        $this->processoModel = new Processo($conn);
    }

    public function getAssinaturasPendentes($usuario_id)
    {
        return $this->alertaModel->getAssinaturasPendentes($usuario_id);
    }

    public function marcarProcessoComoResolvido($processo_id, $usuario_id)
    {
        return $this->processoModel->marcarComoResolvido($processo_id, $usuario_id);
    }

    public function getAssinaturasRascunho($usuario_id)
    {
        return $this->alertaModel->getAssinaturasRascunho($usuario_id);
    }

    public function getTodosAlertas($municipioUsuario)
    {
        return $this->processoModel->getTodosAlertas($municipioUsuario);
    }

    public function getProcessosDesignadosPendentes($usuario_id)
    {
        return $this->processoModel->getProcessosDesignadosPendentes($usuario_id);
    }

    public function criarAlertaParaEmpresas($descricao, $prazo, $link)
    {
        $municipio = $_SESSION['user']['municipio']; // Pega o município do usuário logado
        return $this->alertaModel->criarAlertaParaEmpresas($descricao, $prazo, $link, $municipio);
    }

    public function listarAlertasEmpresas()
    {
        return $this->alertaModel->listarAlertasEmpresas();
    }

    public function editarAlertaEmpresa($id, $descricao, $prazo, $status, $link)
    {
        return $this->alertaModel->editarAlertaEmpresa($id, $descricao, $prazo, $status, $link);
    }

    public function excluirAlertaEmpresa($id)
    {
        return $this->alertaModel->excluirAlertaEmpresa($id);
    }

    public function listarAlertasAtivos()
    {
        return $this->alertaModel->getAlertasAtivos();
    }

    public function marcarAlertaComoLido($alertaId, $usuarioId)
    {
        return $this->alertaModel->marcarAlertaComoLido($alertaId, $usuarioId);
    }

    public function listarAlertasNaoLidos($usuarioId)
    {
        try {
            // Obter todos os municípios vinculados ao usuário
            $municipios = $this->getMunicipiosByUsuario($usuarioId);

            if (empty($municipios)) {
                return []; // Retorna um array vazio se não houver municípios
            }

            return $this->alertaModel->listarAlertasNaoLidos($usuarioId, $municipios);
        } catch (Exception $e) {
            error_log("Erro ao listar alertas não lidos: " . $e->getMessage());
            return [];
        }
    }

    public function listarAlertasPorMunicipio($municipio)
    {
        return $this->alertaModel->listarAlertasPorMunicipio($municipio);
    }


    private function getMunicipiosByUsuario($usuarioId)
    {
        $conn = $this->alertaModel->getConnection();

        $query = "
            SELECT DISTINCT e.municipio 
            FROM usuarios_estabelecimentos ue
            JOIN estabelecimentos e ON ue.estabelecimento_id = e.id
            WHERE ue.usuario_id = ?
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();

        $municipios = [];
        while ($row = $result->fetch_assoc()) {
            $municipios[] = $row['municipio'];
        }

        return $municipios;
    }
}
