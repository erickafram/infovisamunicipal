<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../conf/database.php';

// Verifica se é usuário externo
if (!isset($_SESSION['user'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <?php include '../../includes/header_empresa.php'; ?>
    <title>Assinatura Premium</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .plan-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            border-radius: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
            position: relative;
            overflow: hidden;
        }

        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 123, 255, 0.2);
        }

        .plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #007bff;
        }

        .price {
            font-size: 1.5rem;
            color: #2d3436;
            font-weight: 700;
            margin: 1rem 0;
        }

        .benefit-list {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }

        .benefit-list li {
            padding: 15px 20px;
            margin: 10px 0;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .benefit-list i {
            font-size: 1.5rem;
            color: #007bff;
            margin-right: 15px;
            width: 30px;
        }

        #qrcode-container {
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 20px;
            margin: 2rem auto;
            max-width: 400px;
            background: white;
        }

        #timer {
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .btn-premium {
            background: linear-gradient(45deg, #007bff, #0062cc);
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4);
        }

        .billing-cycle {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: -10px;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            margin-right: 10px;
        }

        .promo-price {
            color: #e74c3c;
            font-weight: bold;
            font-size: 1.2em;
        }

        .countdown {
            font-size: 0.9em;
            color: #333;
            margin-top: 5px;
        }

        #timer {
            color: #c0392b;
            font-weight: bold;
        }

        .warning {
            color: #fff !important;
            background-color: #e74c3c;
            padding: 2px 5px;
            border-radius: 3px;
        }

        /* Adicione estes novos estilos */
        .copy-section {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
        }

        .copy-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f8f9fa;
            font-size: 0.9rem;
        }

        .copy-button {
            background: #007bff;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .copy-button:hover {
            background: #0056b3;
        }

        /* Ajuste para mobile */
        @media (max-width: 576px) {
            .copy-button span {
                display: none;
            }

            .copy-button {
                padding: 10px;
            }
        }

        .copy-button i {
            margin-right: 5px;
        }

        .copy-alert {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border-radius: 5px;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
            }

            to {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="plan-card p-4 text-center">
                    <h1 class="mb-3 display-6 fw-bold text-primary">Plano Premium</h1>

                    <!-- Seção de Temporizadores Centralizada -->
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-10">
                            <div class="bg-light p-3 rounded-3 text-center">
                                <h5 class="text-primary mb-3">
                                    <i class="bi bi-clock-history me-2"></i>Promoção Relâmpago
                                </h5>
                                <div id="promo-timer" class="fs-4 fw-bold text-danger">00:00:00</div>
                                <small class="text-muted">Termina em:</small>
                            </div>
                        </div>
                    </div>

                    <div class="price">
                        <span class="original-price">R$ 59,99</span>
                        <span class="promo-price">R$ 29,99<span class="fs-7 fw-normal">/mês</span></span>
                    </div>
                    <p class="billing-cycle">Cobrança mensal recorrente</p>

                    <ul class="benefit-list mt-4">
                        <li class="py-2">
                            <i class="bi bi-chat-dots fs-5"></i>
                            <span class="fs-6"><strong>Mensagens Ilimitadas</strong><br>Comunique-se com todos os setores da Vigilância em tempo real</span>
                        </li>
                        <li>
                            <i class="bi bi-clock-history"></i>
                            <span><strong>Histórico Completo</strong><br>Acesse todas as interações passadas quando precisar</span>
                        </li>
                        <li>
                            <i class="bi bi-image"></i>
                            <span><strong>Conversão Automática</strong><br>Transforme imagens em PDFs diretamente no sistema</span>
                        </li>
                        <li>
                            <i class="bi bi-person-lines-fill"></i>
                            <span><strong>Suporte</strong><br>Ajuda técnica com o sistema + orientação sobre processos da vigilância sanitária</span>
                        </li>
                    </ul>



                    <div id="qrcode-container" class="text-center mb-4" style="display: none;">
                        <img src="data:image/png;base64,${response.qrcode}" class="img-fluid mb-3">
                        <div class="copy-section">
                            <input type="text" id="payment-code" class="copy-input"
                                value="${response.payment_code}" readonly>
                            <button type="button" class="copy-button" onclick="copyPaymentCode()">
                                <i class="bi bi-clipboard"></i>
                                <span>Copiar Código</span>
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Ou escaneie o QR Code acima</small>
                        </div>
                    </div>


                    <div id="timer" class="text-danger mb-4"></div>
                    <div id="copy-alert" class="copy-alert">Código copiado!</div>

                    <form id="subscription-form">
                        <input type="hidden" name="action" value="gerar_assinatura">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-premium btn-sm" onclick="gerarAssinatura()">
                                <i class="bi bi-credit-card me-2"></i>Gerar QR Code
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function gerarAssinatura() {
        const valor = 29.99;

        $.ajax({
            url: '../../controllers/PagamentoController.php',
            method: 'POST',
            data: {
                action: 'gerar_assinatura',
                valor: valor,
                anonimo: 0
            },
            dataType: 'json',
            beforeSend: function() {
                $('#qrcode-container').hide().html('');
                $('#timer').text('');
            },
            success: function(response) {
                if (response.success) {
                    $('#qrcode-container').html(`
            <img src="data:image/png;base64,${response.qrcode}" class="img-fluid mb-3">
            <div class="copy-section">
                <input type="text" id="payment-code" class="copy-input" 
                       value="${response.payment_code}" readonly>
                            <button type="button" class="copy-button" onclick="copyPaymentCode()">
                                <i class="bi bi-clipboard"></i>
                                <span class="d-none d-md-inline">Copiar</span>
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Ou escaneie o QR Code acima</small>
                        </div>
                    `).fadeIn();
                    startTimer(180);
                } else {
                    alert(response.message || 'Erro ao gerar QR Code');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro:', xhr.responseText, status, error);
                alert('Erro na comunicação com o servidor. Verifique o console para detalhes.');
            }
        });
    }

    function copyPaymentCode() {
        const paymentCode = document.getElementById('payment-code');
        paymentCode.select();
        paymentCode.setSelectionRange(0, 99999); // Para dispositivos móveis

        try {
            navigator.clipboard.writeText(paymentCode.value).then(() => {
                showCopyAlert('Código copiado!', 'success');
            }).catch(err => {
                showCopyAlert('Erro ao copiar!', 'error');
            });
        } catch (err) {
            // Fallback para navegadores antigos
            try {
                document.execCommand('copy');
                showCopyAlert('Código copiado!', 'success');
            } catch (err) {
                showCopyAlert('Erro ao copiar!', 'error');
            }
        }
    }

    function showCopyAlert(message, type) {
        const alert = $('#copy-alert');
        alert.removeClass('bg-success bg-danger');

        if (type === 'success') {
            alert.addClass('bg-success');
        } else {
            alert.addClass('bg-danger');
        }

        alert.text(message).fadeIn().delay(2000).fadeOut();
    }


    function startTimer(duration) {
        let timer = duration;
        const interval = setInterval(() => {
            const minutes = parseInt(timer / 60, 10);
            const seconds = parseInt(timer % 60, 10);

            $('#timer').text(
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
            );

            if (--timer < 0) {
                clearInterval(interval);
                $('#timer').text('EXPIRADO');
                $('#qrcode-container').fadeOut();
            }
        }, 1000);
    }

    let promoInterval, qrcodeInterval;

    function startPromoTimer() {
        const endTime = new Date().getTime() + (24 * 60 * 60 * 1000);

        promoInterval = setInterval(() => {
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance < 0) {
                clearInterval(promoInterval);
                document.getElementById("promo-timer").innerHTML = "Promoção encerrada!";
                return;
            }

            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("promo-timer").innerHTML =
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            if (hours < 1) {
                document.getElementById("promo-timer").classList.add('warning');
            }
        }, 1000);
    }

    function startQRCodeTimer(duration) {
        clearInterval(qrcodeInterval);

        let timer = duration;
        const qrcodeTimerElement = document.getElementById("qrcode-timer");

        qrcodeTimerElement.innerHTML =
            `${Math.floor(timer / 60).toString().padStart(2, '0')}:${(timer % 60).toString().padStart(2, '0')}`;

        qrcodeInterval = setInterval(() => {
            timer--;

            if (timer < 0) {
                clearInterval(qrcodeInterval);
                qrcodeTimerElement.innerHTML = "EXPIRADO";
                $('#qrcode-container').fadeOut();
                return;
            }

            qrcodeTimerElement.innerHTML =
                `${Math.floor(timer / 60).toString().padStart(2, '0')}:${(timer % 60).toString().padStart(2, '0')}`;
        }, 1000);
    }

    $(document).ready(function() {
        startPromoTimer();
    });
</script>

</html>