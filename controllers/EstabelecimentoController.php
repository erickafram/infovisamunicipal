<?php
require_once '../conf/database.php';
require_once '../models/Estabelecimento.php';

class EstabelecimentoController
{
    private $estabelecimento;

    public function __construct($conn)
    {
        $this->estabelecimento = new Estabelecimento($conn);
    }

    public function register()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            session_start();
            $usuarioMunicipio = $_SESSION['user']['municipio'];
            $usuarioNivelAcesso = $_SESSION['user']['nivel_acesso'];
            $estabelecimentoMunicipio = $_POST['municipio'];

            // Verifica se o usuário não é administrador e se o município do estabelecimento é diferente do município do usuário
            if ($usuarioNivelAcesso != 1 && $usuarioMunicipio !== $estabelecimentoMunicipio) {
                header("Location: ../views/Estabelecimento/cadastro_estabelecimento.php?error=" . urlencode("Só é permitido cadastrar estabelecimentos do mesmo município que o usuário."));
                exit();
            }

            $data = [
                'cnpj' => $_POST['cnpj'],
                'descricao_identificador_matriz_filial' => $_POST['descricao_identificador_matriz_filial'],
                'nome_fantasia' => $_POST['nome_fantasia'],
                'descricao_situacao_cadastral' => $_POST['descricao_situacao_cadastral'],
                'data_situacao_cadastral' => $_POST['data_situacao_cadastral'],
                'data_inicio_atividade' => $_POST['data_inicio_atividade'],
                'cnae_fiscal' => $_POST['cnae_fiscal'],
                'cnae_fiscal_descricao' => $_POST['cnae_fiscal_descricao'],
                'descricao_tipo_de_logradouro' => $_POST['descricao_tipo_de_logradouro'],
                'logradouro' => $_POST['logradouro'],
                'numero' => $_POST['numero'],
                'complemento' => $_POST['complemento'],
                'bairro' => $_POST['bairro'],
                'cep' => $_POST['cep'],
                'uf' => $_POST['uf'],
                'municipio' => $_POST['municipio'],
                'ddd_telefone_1' => $_POST['ddd_telefone_1'],
                'ddd_telefone_2' => $_POST['ddd_telefone_2'],
                'razao_social' => $_POST['razao_social'],
                'natureza_juridica' => $_POST['natureza_juridica'],
                'qsa' => isset($_POST['qsa']) ? json_decode($_POST['qsa'], true) : [],
                'cnaes_secundarios' => isset($_POST['cnaes_secundarios']) ? json_decode($_POST['cnaes_secundarios'], true) : [],
                'status' => 'aprovado'
            ];

            // Capturar o ID do estabelecimento criado
            $estabelecimento_id = $this->estabelecimento->create($data);

            if ($estabelecimento_id) {
                header("Location: ../views/Estabelecimento/detalhes_estabelecimento.php?id=" . $estabelecimento_id);
                exit();
            } else {
                header("Location: ../views/Estabelecimento/cadastro_estabelecimento.php?error=" . urlencode($this->estabelecimento->getLastError()));
                exit();
            }
        }
    }



    public function registerPessoaFisica()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            session_start();
            $usuarioExternoId = $_SESSION['user']['id']; // Pegando o ID do usuário externo logado
            $cpf = isset($_POST['cpf']) ? $_POST['cpf'] : null;
            $municipio = isset($_POST['municipio']) ? $_POST['municipio'] : null;

            // Verifica se já existe um CPF cadastrado no mesmo município
            if ($this->estabelecimento->checkCpfExists($cpf, $municipio)) {
                header("Location: ../views/Company/cadastro_estabelecimento_empresa.php?error=" . urlencode("Já existe um cadastro com este CPF para o município selecionado."));
                exit();
            }

            $data = [
                'tipo_pessoa' => 'fisica',
                'cpf' => $cpf,
                'nome' => isset($_POST['nome']) ? $_POST['nome'] : null,
                'nome_fantasia' => isset($_POST['nome_fantasia']) ? $_POST['nome_fantasia'] : null,
                'rg' => isset($_POST['rg']) ? $_POST['rg'] : null,
                'orgao_emissor' => isset($_POST['orgao_emissor']) ? $_POST['orgao_emissor'] : null,
                'descricao_tipo_de_logradouro' => isset($_POST['descricao_tipo_de_logradouro']) ? $_POST['descricao_tipo_de_logradouro'] : null,
                'logradouro' => isset($_POST['logradouro']) ? $_POST['logradouro'] : null,
                'numero' => isset($_POST['numero']) ? $_POST['numero'] : null,
                'complemento' => isset($_POST['complemento']) ? $_POST['complemento'] : null,
                'bairro' => isset($_POST['bairro']) ? $_POST['bairro'] : null,
                'cep' => isset($_POST['cep']) ? $_POST['cep'] : null,
                'uf' => isset($_POST['uf']) ? $_POST['uf'] : null,
                'municipio' => $municipio,
                'ddd_telefone_1' => isset($_POST['ddd_telefone_1']) ? $_POST['ddd_telefone_1'] : null,
                'ddd_telefone_2' => isset($_POST['ddd_telefone_2']) ? $_POST['ddd_telefone_2'] : null,
                'email' => isset($_POST['email']) ? $_POST['email'] : null,
                'inicio_funcionamento' => isset($_POST['inicio_funcionamento']) ? $_POST['inicio_funcionamento'] : null,
                'ramo_atividade' => isset($_POST['ramo_atividade']) ? $_POST['ramo_atividade'] : null,
                'descricao_situacao_cadastral' => 'ATIVA',
                'status' => 'pendente',
                'usuario_externo_id' => $usuarioExternoId
            ];

            $estabelecimentoId = $this->estabelecimento->createPessoaFisica($data);
            if ($estabelecimentoId) {
                $this->estabelecimento->vincularUsuarioEstabelecimento($usuarioExternoId, $estabelecimentoId, 'CONTADOR');

                if (!empty($_POST['cnaes'])) {
                    $cnaes = json_decode($_POST['cnaes'], true);
                    foreach ($cnaes as $cnae) {
                        $this->estabelecimento->salvarCnae($estabelecimentoId, $cnae['id'], $cnae['descricao']);
                    }
                }

                if (isset($_SESSION['user']['nivel_acesso']) && in_array($_SESSION['user']['nivel_acesso'], [1, 2, 3])) {
                    header("Location: ../views/Dashboard/dashboard.php");
                } else {
                    header("Location: ../views/Company/dashboard_empresa.php");
                }
                exit();
            } else {
                header("Location: ../views/Company/cadastro_estabelecimento_empresa.php?error=" . urlencode("Erro ao cadastrar estabelecimento."));
                exit();
            }
        }
    }

    public function checkCnpj()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $cnpj = $_POST['cnpj'];
            $exists = $this->estabelecimento->checkCnpjExists($cnpj);
            echo json_encode(['exists' => $exists]);
        }
    }

    public function updateCnaes()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $estabelecimentoId = $_POST['estabelecimento_id'];
            $cnaes = isset($_POST['cnaes']) ? json_decode($_POST['cnaes'], true) : [];

            // Verificar se houve erro ao decodificar o JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['success' => false, 'error' => 'Erro ao decodificar CNAEs.']);
                return;
            }

            // Remover todas as atividades atuais
            if (!$this->estabelecimento->removeAllCnaes($estabelecimentoId)) {
                echo json_encode(['success' => false, 'error' => 'Erro ao remover CNAEs atuais.']);
                return;
            }

            // Obter o município do estabelecimento para verificar o grupo de risco
            $estabelecimentoInfo = $this->estabelecimento->findById($estabelecimentoId);
            
            // Adicionar os novos CNAEs
            foreach ($cnaes as $cnae) {
                // Verificar se temos informações necessárias (ID e descrição são obrigatórios)
                if (empty($cnae['id']) || empty($cnae['descricao'])) {
                    continue; // Pular este CNAE se faltar informação essencial
                }
                
                if (!$this->estabelecimento->salvarCnae($estabelecimentoId, $cnae['id'], $cnae['descricao'])) {
                    echo json_encode(['success' => false, 'error' => 'Erro ao salvar CNAE: ' . $this->estabelecimento->getLastError()]);
                    return;
                }
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Método inválido']);
        }
    }



    public function checkCnpjDuplicado()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $cnpj = $_POST['cnpj'];
            $exists = $this->estabelecimento->checkCnpjExists($cnpj);
            echo json_encode(['exists' => $exists]);
        }
    }

    public function update()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $id = $_GET['id'];
            $data = [
                'descricao_identificador_matriz_filial' => $_POST['descricao_identificador_matriz_filial'],
                'nome_fantasia' => $_POST['nome_fantasia'],
                'descricao_situacao_cadastral' => $_POST['descricao_situacao_cadastral'],
                'data_situacao_cadastral' => $_POST['data_situacao_cadastral'],
                'data_inicio_atividade' => $_POST['data_inicio_atividade'],
                'descricao_tipo_de_logradouro' => $_POST['descricao_tipo_de_logradouro'],
                'logradouro' => $_POST['logradouro'],
                'numero' => $_POST['numero'],
                'complemento' => $_POST['complemento'],
                'bairro' => $_POST['bairro'],
                'cep' => $_POST['cep'],
                'uf' => $_POST['uf'],
                'municipio' => $_POST['municipio'],
                'ddd_telefone_1' => $_POST['ddd_telefone_1'],
                'ddd_telefone_2' => $_POST['ddd_telefone_2'],
                'razao_social' => $_POST['razao_social'],
                'natureza_juridica' => $_POST['natureza_juridica']
            ];

            if ($this->estabelecimento->update($id, $data)) {
                header("Location: ../views/Estabelecimento/editar_estabelecimento.php?id=$id&success=1");
                exit();
            } else {
                header("Location: ../views/Estabelecimento/editar_estabelecimento.php?id=$id&error=" . urlencode($this->estabelecimento->getLastError()));
                exit();
            }
        }
    }

    public function registerEmpresa()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            session_start();
            $usuarioExternoId = $_SESSION['user']['id'];

            $cnpj = $_POST['cnpj'];
            $razaoSocial = $_POST['razao_social'];
            $nomeFantasia = $_POST['nome_fantasia'];

            // Valida se todos os campos obrigatórios estão preenchidos
            if (empty($cnpj) || empty($razaoSocial) || empty($nomeFantasia)) {
                header("Location: ../views/Company/cadastro_estabelecimento_empresa.php?error=" . urlencode("Erro: Dados obrigatórios estão ausentes."));
                exit();
            }

            if ($this->estabelecimento->checkCnpjExists($cnpj)) {
                $municipio = $this->estabelecimento->getMunicipioByCnpj($cnpj);
                header("Location: ../views/Company/cadastro_estabelecimento_empresa.php?error=" . urlencode("Estabelecimento já existe no município " . $municipio . "."));
                exit();
            }

            // Prepara os dados do CNAE para verificação
            $cnaeFiscal = $_POST['cnae_fiscal'];
            $cnaesSecundarios = isset($_POST['cnaes_secundarios']) ? json_encode(json_decode($_POST['cnaes_secundarios'], true)) : json_encode([]);
            $municipio = $_POST['municipio'];

            // Verifica se tem competência da vigilância sanitária
            if (!$this->estabelecimento->temCompetenciaVigilanciaTemporaria($cnaeFiscal, $cnaesSecundarios, $municipio)) {
                header("Location: ../views/Company/cadastro_estabelecimento_empresa.php?error=" . urlencode("As atividades deste estabelecimento não são de competência da Vigilância Sanitária Municipal de Gurupi. Caso tenha alguma dúvida, por favor entre em contato através dos canais oficiais de comunicação com a Vigilância Sanitária."));
                exit();
            }

            $data = [
                'cnpj' => $_POST['cnpj'],
                'descricao_identificador_matriz_filial' => $_POST['descricao_identificador_matriz_filial'],
                'nome_fantasia' => $_POST['nome_fantasia'],
                'descricao_situacao_cadastral' => $_POST['descricao_situacao_cadastral'],
                'data_situacao_cadastral' => $_POST['data_situacao_cadastral'],
                'data_inicio_atividade' => $_POST['data_inicio_atividade'],
                'cnae_fiscal' => $cnaeFiscal,
                'cnae_fiscal_descricao' => $_POST['cnae_fiscal_descricao'],
                'descricao_tipo_de_logradouro' => $_POST['descricao_tipo_de_logradouro'],
                'logradouro' => $_POST['logradouro'],
                'numero' => $_POST['numero'],
                'complemento' => $_POST['complemento'],
                'bairro' => $_POST['bairro'],
                'cep' => $_POST['cep'],
                'uf' => $_POST['uf'],
                'municipio' => $municipio,
                'ddd_telefone_1' => $_POST['ddd_telefone_1'],
                'ddd_telefone_2' => $_POST['ddd_telefone_2'],
                'razao_social' => $_POST['razao_social'],
                'natureza_juridica' => $_POST['natureza_juridica'],
                'qsa' => isset($_POST['qsa']) ? json_decode($_POST['qsa'], true) : [],
                'cnaes_secundarios' => json_decode($cnaesSecundarios, true),
                'status' => 'pendente',
                'usuario_externo_id' => $usuarioExternoId
            ];

            $estabelecimentoId = $this->estabelecimento->create($data);
            if ($estabelecimentoId) {
                // Vincula o usuário externo ao estabelecimento
                $this->estabelecimento->vincularUsuarioEstabelecimento($usuarioExternoId, $estabelecimentoId, 'CONTADOR');

                header("Location: ../views/Company/cadastro_estabelecimento_empresa.php?success=1");
                exit();
            } else {
                $error = $this->estabelecimento->getLastError();
                header("Location: ../views/Company/cadastro_estabelecimento_empresa.php?error=" . urlencode("Erro ao cadastrar estabelecimento: " . $error));
                exit();
            }
        }
    }


    public function reiniciar()
    {
        if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
            $id = $_GET['id'];
            if ($this->estabelecimento->reiniciarEstabelecimento($id)) {
                header("Location: ../views/Estabelecimento/listar_estabelecimentos_rejeitados.php?success=1");
                exit();
            } else {
                header("Location: ../views/Estabelecimento/listar_estabelecimentos_rejeitados.php?error=" . urlencode("Erro ao reiniciar estabelecimento."));
                exit();
            }
        }
    }

    public function approveEstabelecimento()
    {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            // Executa a aprovação diretamente no banco de dados para garantir
            global $conn;
            $sql = "UPDATE estabelecimentos SET status = 'aprovado' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            
            if ($result) {
                header("Location: ../views/Dashboard/dashboard.php?success=Estabelecimento aprovado com sucesso.");
            } else {
                // Tenta pelo modelo se a atualização direta falhar
                if ($this->estabelecimento->approve($id)) {
                    header("Location: ../views/Dashboard/dashboard.php?success=Estabelecimento aprovado com sucesso.");
                } else {
                    header("Location: ../views/Dashboard/dashboard.php?error=" . urlencode("Erro ao aprovar estabelecimento."));
                }
            }
            exit();
        } else {
            header("Location: ../views/Dashboard/dashboard.php?error=" . urlencode("ID do estabelecimento não fornecido."));
            exit();
        }
    }


    public function rejectEstabelecimento()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && isset($_POST['motivo'])) {
            $id = $_POST['id'];
            $motivo = $_POST['motivo'];
            if ($this->estabelecimento->reject($id, $motivo)) {
                header("Location: ../views/Dashboard/dashboard.php?success=1");
                exit();
            } else {
                header("Location: ../views/Estabelecimento/listar_estabelecimentos.php?error=" . urlencode("Erro ao rejeitar estabelecimento."));
                exit();
            }
        }
    }
}

// Processa a ação com base no parâmetro de URL
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Verificar conexão com o banco de dados
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $controller = new EstabelecimentoController($conn);

    if ($action == "register") {
        $controller->register();
    } elseif ($action == "rejectEstabelecimento") {
        $controller->rejectEstabelecimento();
    } elseif ($action == "checkCnpj") {
        $controller->checkCnpj();
    } elseif ($action == "update") {
        $controller->update();
    } elseif ($action == 'registerEmpresa') {
        $controller->registerEmpresa();
    } elseif ($action == 'checkCnpjDuplicado') {
        $controller->checkCnpjDuplicado();
    } elseif ($action == 'approveEstabelecimento') {
        $controller->approveEstabelecimento();
    } elseif ($action == 'reiniciar') { // Nova ação para reiniciar o estabelecimento
        $controller->reiniciar();
    } elseif ($action == 'registerPessoaFisica') {
        $controller->registerPessoaFisica();
    } elseif ($action == 'updateCnaes') {
        $controller->updateCnaes();
    }

    $conn->close();
}
