<?php
session_start();

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Limpa a mensagem após exibição
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Limpa a mensagem após exibição
    echo '<script>setTimeout(function() { window.location.href = "../../login.php"; }, 2000);</script>';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuário</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 flex justify-center items-center min-h-screen p-4">
    <div class="bg-white rounded-xl shadow-2xl p-8 w-full max-w-3xl">
        <div class="bg-blue-50 text-blue-800 p-4 rounded-md mb-6">
            Para ter acesso ao sistema Infovisa, preencha corretamente todos os campos abaixo.
        </div>

        <?php
        if (isset($error_message)) {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4" role="alert">
                    <strong class="font-bold">Erro!</strong>
                    <span class="block sm:inline">' . $error_message . '</span>
                  </div>';
        }
        if (isset($success_message)) {
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4" role="alert">
                    <strong class="font-bold">Sucesso!</strong>
                    <span class="block sm:inline">' . $success_message . '</span>
                  </div>
                  <script>setTimeout(function() { window.location.href = "../../login.php"; }, 2000);</script>';
        }
        ?>

        <form action="../../controllers/UsuarioExternoController.php?action=register" method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="cpf" class="block text-gray-700 text-sm font-bold mb-2 transition-transform duration-300 ease-in-out">CPF</label>
                    <div class="flex items-center">
                        <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        <div id="cpf-loading" class="loading-spinner"></div>
                    </div>
                    <div id="cpf-error" class="text-red-500 text-sm mt-1 hidden"></div>
                </div>
                <div>
                    <label for="nome_completo" class="block text-gray-700 text-sm font-bold mb-2 transition-transform duration-300 ease-in-out">Nome Completo</label>
                    <input type="text" id="nome_completo" name="nome_completo" placeholder="Preenchimento automático" required readonly class="bg-gray-100 shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="telefone" class="block text-gray-700 text-sm font-bold mb-2 transition-transform duration-300 ease-in-out">Telefone Celular</label>
                    <input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2 transition-transform duration-300 ease-in-out">Email</label>
                    <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                <div>
                    <label for="vinculo_estabelecimento" class="block text-gray-700 text-sm font-bold mb-2 transition-transform duration-300 ease-in-out">Vínculo com Estabelecimento</label>
                    <select id="vinculo_estabelecimento" name="vinculo_estabelecimento" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        <option value="">Selecione...</option>
                        <option value="CONTADOR">CONTADOR</option>
                        <option value="RESPONSÁVEL LEGAL">RESPONSÁVEL LEGAL</option>
                        <option value="RESPONSÁVEL TÉCNICO">RESPONSÁVEL TÉCNICO</option>
                        <option value="FUNCIONÁRIO">FUNCIONÁRIO</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="senha" class="block text-gray-700 text-sm font-bold mb-2 transition-transform duration-300 ease-in-out">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Crie uma senha" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div>
                    <label for="senha_confirmacao" class="block text-gray-700 text-sm font-bold mb-2 transition-transform duration-300 ease-in-out">Confirmar Senha</label>
                    <input type="password" id="senha_confirmacao" name="senha_confirmacao" placeholder="Repita a senha" required class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
            </div>

            <div class="mb-6">
                <label class="inline-flex items-center">
                    <input type="checkbox" id="aceitar_termos" name="aceitar_termos" required class="form-checkbox h-5 w-5 text-blue-600 rounded-md focus:ring-blue-500">
                    <span class="ml-2 text-gray-700 text-sm">
                        Li e aceito os <a href="#" id="termosModalTrigger" class="text-blue-500 hover:underline transition-colors duration-300">Termos e Condições de Uso</a>
                    </span>
                </label>
            </div>

            <div class="flex justify-between mt-8">
                <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline" onclick="history.back()">Voltar</button>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md focus:outline-none focus:shadow-outline">Cadastrar</button>
            </div>
        </form>
    </div>

    <div class="fixed z-10 inset-0 overflow-y-auto hidden" id="termosModal">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-xl shadow-2xl text-left overflow-hidden shadow-xl transform  sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-gray-50 rounded-t-xl px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h5 class="text-xl font-semibold text-gray-900" id="termosModalLabel">
                                Termos e Condições de Uso
                            </h5>
                        </div>
                    </div>
                </div>
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 overflow-y-auto max-h-[60vh]">
                    <div class="text-gray-700">
                        <p class="mb-4">
                            O credenciando acima identificado aceita as condições do presente TERMO DE COMPROMISSO para a utilização do Sistema INFOVISA-M da Vigilância Sanitária do Município de Gurupi-TO...
                        </p>
                        <ul class="list-disc list-inside space-y-2">
                            <li>O credenciamento é ato pessoal, direto, intransferível e indelegável, sendo os atos praticados no Sistema de Licenciamento Sanitário Eletrônico de sua responsabilidade exclusiva.</li>
                            <li>Os atos gerados no Sistema serão registrados com a identificação do usuário, a data e o horário de sua realização.</li>
                            <li>A aquisição e utilização dos equipamentos necessários ao acesso do Sistema de Processo Eletrônico no INFOVISA-M, assim como dos serviços correlatos (provedor de acesso à Internet, certificação digital etc.), correrá por conta e risco da pessoa.</li>
                            <li>A digitalização de requerimentos e documentos deverá ser realizada pelo próprio usuário, sendo sua a exclusiva responsabilidade pela qualidade e/ou legibilidade dos documentos anexados ao Sistema.</li>
                            <li>Os documentos digitalizados e juntados em processo eletrônico somente estarão disponíveis para acesso por meio da rede externa para suas respectivas partes processuais, respeitado o disposto em lei para as situações de sigilo.</li>
                            <li>Caso o usuário cadastrado tenha acesso ao teor dos atos emanados pela autoridade sanitária por meio da funcionalidade "tomar ciência", o sistema irá proceder com os registros para contagem de início dos prazos.</li>
                        </ul>
                        <p class="mt-6 font-semibold">1) Para usuários que utilizam o sistema INFOVISA com uso de senha:</p>
                        <ul class="list-disc list-inside space-y-2">
                            <li>O acesso ao Sistema, a prática de atos processuais em geral e o envio de requerimentos, defesas e recursos, por meio eletrônico, serão admitidos mediante uso de assinatura digital devidamente certificada.</li>
                            <li>A conclusão do credenciamento com a assinatura digital certificada do termo de compromisso torna a pessoa apta para a utilização do Sistema.</li>
                            <li>Os documentos produzidos eletronicamente e juntados aos processos eletrônicos com garantia da origem e de seu signatário, através de certificação digital, serão considerados originais para todos os efeitos legais.</li>
                            <li>É da exclusiva responsabilidade do usuário a utilização de sua assinatura digital para acesso e prática de atos no Sistema, devendo adotar cautelas para preservação da senha respectiva e respondendo por eventual uso indevido.</li>
                            <li>O usuário credenciado se compromete a manter seu cadastro e especialmente endereço eletrônico atualizados, aceitando expressamente o recebimento de atos emanados pela autoridade sanitária através do sistema INFOVISA-M e endereço eletrônico.</li>
                            <li>Concorda que após a disposição dos atos emanados pela autoridade sanitária em sua dashboard, esses não abertos até o quinto dia terão seus prazos iniciados a partir do sexto dia e correrão até o final do prazo estipulado em lei.</li>
                            <li>A não manifestação da parte no prazo estipulado no ato da autoridade sanitária será considerada como desinteresse processual e causará sua revelia.</li>
                        </ul>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-b-xl px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm" onclick="toggleModal()">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cpfInput = document.getElementById('cpf');
            const nomeInput = document.getElementById('nome_completo');
            const telefoneInput = document.getElementById('telefone');
            const termosModalTrigger = document.getElementById('termosModalTrigger');
            const termosModal = document.getElementById('termosModal');
            const cpfLoading = document.getElementById('cpf-loading');
            const cpfError = document.getElementById('cpf-error');
            
            let typingTimer;
            const doneTypingInterval = 1000; // 1 segundo
            let cpfConsultado = false;

            if (cpfInput) {
                cpfInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.slice(0, 11);
                    if (value.length > 3) value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    if (value.length > 6) value = value.replace(/(\d{3})(\d{3})(\d)/, '$1.$2.$3');
                    if (value.length > 9) value = value.replace(/(\d{3})(\d{3})(\d{3})(\d)/, '$1.$2.$3-$4');
                    e.target.value = value;
                    
                    // Limpa o timeout anterior
                    clearTimeout(typingTimer);
                    
                    // Verifica se o CPF tem 11 dígitos (sem pontuação)
                    const cpfSemFormatacao = value.replace(/\D/g, '');
                    if (cpfSemFormatacao.length === 11) {
                        cpfLoading.style.display = 'inline-block';
                        cpfError.classList.add('hidden');
                        
                        // Inicia o timer
                        typingTimer = setTimeout(() => {
                            consultarCPF(cpfSemFormatacao);
                        }, doneTypingInterval);
                    } else {
                        // Limpa o campo de nome se o CPF for alterado/apagado
                        nomeInput.value = '';
                        cpfConsultado = false;
                    }
                });
            }

            function consultarCPF(cpf) {
                fetch(`../../api/consulta_cpf.php?cpf=${cpf}`)
                    .then(response => response.json())
                    .then(data => {
                        cpfLoading.style.display = 'none';
                        
                        if (data.status === 'success') {
                            nomeInput.value = data.nome;
                            cpfConsultado = true;
                        } else {
                            nomeInput.value = '';
                            cpfError.textContent = data.msg || 'Erro ao consultar CPF';
                            cpfError.classList.remove('hidden');
                            cpfConsultado = false;
                        }
                    })
                    .catch(error => {
                        cpfLoading.style.display = 'none';
                        cpfError.textContent = 'Erro ao consultar o serviço de CPF';
                        cpfError.classList.remove('hidden');
                        cpfConsultado = false;
                    });
            }

            if (telefoneInput) {
                telefoneInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.slice(0, 11);
                    if (value.length > 2) value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    if (value.length > 7) value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                    e.target.value = value;
                });
            }

            if (termosModalTrigger && termosModal) {
                termosModalTrigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    toggleModal();
                });
            }
            
            // Validação de formulário para garantir que o CPF foi consultado com sucesso
            document.querySelector('form').addEventListener('submit', function(e) {
                if (!cpfConsultado) {
                    e.preventDefault();
                    cpfError.textContent = 'Por favor, consulte um CPF válido antes de prosseguir';
                    cpfError.classList.remove('hidden');
                }
            });
        });

        function toggleModal() {
            const termosModal = document.getElementById('termosModal');
            termosModal.classList.toggle('hidden');
        }
    </script>
</body>

</html>