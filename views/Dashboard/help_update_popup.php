<!-- Modal de Ajuda sobre Atualizações do Sistema -->
<div class="modal fade" id="sistemaAtualizacoesModal" tabindex="-1" aria-labelledby="sistemaAtualizacoesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <!-- Header com logo GovNex e título -->
            <div class="modal-header p-0 border-0">
                <div class="w-full bg-gradient-to-r from-blue-900 via-blue-700 to-blue-800 text-white p-4 relative overflow-hidden">
                    <!-- Círculos decorativos/animados no fundo -->
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-blue-400 opacity-10 rounded-full"></div>
                    <div class="absolute top-5 right-5 w-20 h-20 bg-blue-300 opacity-20 rounded-full animate-pulse"></div>
                    <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-blue-500 opacity-10 rounded-full"></div>
                    
                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center">
                            <!-- Logo GovNex -->
                            <div class="mr-3 bg-white/90 p-2 rounded-lg shadow-md">
                                <span class="text-blue-800 font-bold">Gov</span><span class="text-green-600 font-bold">Nex</span>
                            </div>
                            <!-- Título com ícone animado -->
                            <div>
                                <h5 class="font-bold text-xl mb-0 flex items-center" id="sistemaAtualizacoesModalLabel">
                                    <span class="relative">
                                        <i class="fas fa-gift text-yellow-300 absolute -top-1 -left-1 animate-ping opacity-70"></i>
                                        <i class="fas fa-gift text-yellow-300"></i>
                                    </span>
                                    <span class="ml-3">Novidades no InfoVISA!</span>
                                </h5>
                                <p class="text-sm text-blue-100 mb-0">Atualizado por GovNex</p>
                            </div>
                        </div>
                        <!-- Botão de fechar estilizado -->
                        <button type="button" class="bg-white/20 hover:bg-white/30 rounded-full w-8 h-8 flex items-center justify-center text-white transition-all duration-200" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="modal-body p-0">
                <!-- Banner destaque -->
                <div class="bg-blue-700 text-white p-3 text-center">
                    <p class="mb-0 text-sm flex items-center justify-center">
                        <i class="fas fa-code-branch text-yellow-300 mr-2"></i>
                        <span class="italic">O GovNex concluiu uma importante atualização visual e funcional no sistema</span>
                    </p>
                </div>
                
                <!-- Seção de novidades - Cards com animação ao entrar -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-4 bg-gradient-to-br from-white to-blue-50/30 fadeInUp">
                    <!-- Card 1 -->
                    <div class="feature-card bg-white rounded-lg shadow-sm border border-gray-100 p-4 transform transition-all duration-500 hover:shadow-md hover:scale-[1.02] hover:border-blue-200">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white mr-3 flex-shrink-0 shadow-sm">
                                <i class="fas fa-paint-brush"></i>
                            </div>
                            <div>
                                <h6 class="font-bold mb-1">Design Repensado</h6>
                                <p class="text-sm text-gray-600 mb-0">Nova interface com Tailwind CSS, componentes modernos e responsivos, e experiência visual aprimorada.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="feature-card bg-white rounded-lg shadow-sm border border-gray-100 p-4 transform transition-all duration-500 hover:shadow-md hover:scale-[1.02] hover:border-blue-200" style="animation-delay: 0.1s;">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white mr-3 flex-shrink-0 shadow-sm">
                                <i class="fas fa-sitemap"></i>
                            </div>
                            <div>
                                <h6 class="font-bold mb-1">Navegação Otimizada</h6>
                                <p class="text-sm text-gray-600 mb-0">Menu lateral consistente em todo o sistema e fluxo de trabalho mais intuitivo.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 3 -->
                    <div class="feature-card bg-white rounded-lg shadow-sm border border-gray-100 p-4 transform transition-all duration-500 hover:shadow-md hover:scale-[1.02] hover:border-blue-200" style="animation-delay: 0.2s;">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white mr-3 flex-shrink-0 shadow-sm">
                                <i class="fas fa-search"></i>
                            </div>
                            <div>
                                <h6 class="font-bold mb-1">Busca Inteligente</h6>
                                <p class="text-sm text-gray-600 mb-0">Pesquisa mais rápida e filtros melhorados para localizar informações com praticidade.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 4 -->
                    <div class="feature-card bg-white rounded-lg shadow-sm border border-gray-100 p-4 transform transition-all duration-500 hover:shadow-md hover:scale-[1.02] hover:border-blue-200" style="animation-delay: 0.3s;">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white mr-3 flex-shrink-0 shadow-sm">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <h6 class="font-bold mb-1">Notificações Aprimoradas</h6>
                                <p class="text-sm text-gray-600 mb-0">Sistema de alertas mais visível e organizado para melhor acompanhamento das tarefas.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção de páginas atualizadas -->
                <div class="p-4 pt-0 bg-gradient-to-br from-white to-blue-50/30 fadeInUp" style="animation-delay: 0.4s;">
                    <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg p-4 border border-blue-100">
                        <h6 class="font-bold mb-2 flex items-center text-blue-700">
                            <i class="fas fa-check-circle mr-2"></i>
                            Páginas Atualizadas pelo GovNex:
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div class="flex items-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-700 mr-2 flex-shrink-0">
                                    <i class="fas fa-building text-xs"></i>
                                </span>
                                <span class="text-sm text-gray-700">Gestão de estabelecimentos</span>
                            </div>
                            <div class="flex items-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 text-green-700 mr-2 flex-shrink-0">
                                    <i class="fas fa-users text-xs"></i>
                                </span>
                                <span class="text-sm text-gray-700">Responsáveis</span>
                            </div>
                            <div class="flex items-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 mr-2 flex-shrink-0">
                                    <i class="fas fa-folder text-xs"></i>
                                </span>
                                <span class="text-sm text-gray-700">Processos</span>
                            </div>
                            <div class="flex items-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 text-purple-700 mr-2 flex-shrink-0">
                                    <i class="fas fa-user-lock text-xs"></i>
                                </span>
                                <span class="text-sm text-gray-700">Controle de acesso</span>
                            </div>
                            <div class="flex items-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-700 mr-2 flex-shrink-0">
                                    <i class="fas fa-list text-xs"></i>
                                </span>
                                <span class="text-sm text-gray-700">Sistema de atividades CNAE</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer com logo e botões -->
            <div class="modal-footer justify-between bg-gray-50 p-4">
                <div class="flex items-center">
                   
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="naoMostrarNovamente">
                        <label class="custom-control-label text-sm text-gray-600" for="naoMostrarNovamente">Não mostrar novamente</label>
                    </div>
                </div>
                <button type="button" class="btn btn-primary px-4 py-2" id="entendiBotao" data-bs-dismiss="modal">
                    <i class="fas fa-thumbs-up mr-1"></i> Legal!
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Animações para os cards de features */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fadeInUp {
        animation: fadeInUp 0.5s ease-out forwards;
    }
    
    .feature-card {
        opacity: 0;
        animation: fadeInUp 0.5s ease-out forwards;
    }
    
    /* Efeito de notificação no ícone de presente */
    @keyframes ping {
        75%, 100% {
            transform: scale(1.2);
            opacity: 0;
        }
    }
    
    /* Pulsar animação para o botão de confirmação */
    #entendiBotao {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4);
        }
        70% {
            box-shadow: 0 0 0 6px rgba(59, 130, 246, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
        }
    }
</style>

<script>
    // Script para controlar o modal de atualizações do sistema
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se o popup já foi mostrado antes
        const popupJaMostrado = localStorage.getItem('infoVisaUpdatePopupShown');
        
        // Se não foi mostrado, exibe o modal automaticamente após um pequeno atraso
        if (!popupJaMostrado) {
            setTimeout(function() {
                try {
                    const modal = new bootstrap.Modal(document.getElementById('sistemaAtualizacoesModal'));
                    modal.show();
                } catch (e) {
                    console.error("Erro ao mostrar modal:", e);
                }
            }, 1200);
        }
        
        // Adiciona evento para o botão "Entendi"
        document.getElementById('entendiBotao').addEventListener('click', function() {
            const naoMostrarNovamente = document.getElementById('naoMostrarNovamente').checked;
            if (naoMostrarNovamente) {
                localStorage.setItem('infoVisaUpdatePopupShown', 'true');
            }
        });
    });
</script> 