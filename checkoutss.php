<?php
// checkout.php

// Configurações de erro (ajuste para produção depois)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Inicia a sessão para pegar o user_id depois, se precisar

// --- Pegar dados do Pacote da URL ---
$leads = filter_input(INPUT_GET, 'package', FILTER_VALIDATE_INT);
$price = filter_input(INPUT_GET, 'price', FILTER_VALIDATE_FLOAT);
$description = filter_input(INPUT_GET, 'description', FILTER_SANITIZE_SPECIAL_CHARS);

// --- Validação Básica ---
// Se faltar algo ou o preço for inválido, não continua
if ($leads === false || $price === false || $price <= 0 || empty($description)) {
    die("Erro: Informações do pacote inválidas ou ausentes. Volte e tente novamente.");
    // O ideal aqui seria redirecionar para uma página de erro ou de volta pro dashboard
    // header('Location: dashboard.php?error=invalid_package');
    // exit;
}

// --- SUA PUBLIC KEY DE TESTE ---
// IMPORTANTE: Troque pela sua Public Key de PRODUÇÃO quando for pro ar!
$mercadoPagoPublicKey = "TEST-ed41d7b6-cc94-4b76-a255-70092c6b5923"; // <<=== COLOQUE SUA PUBLIC KEY DE TESTE AQUI

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Love Chat - Checkout</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="https://i.ibb.co/71J7N6n/love-chat.png" type="image/png">

    <style>
        /* Copiando as variáveis de cor do seu dashboard */
        :root {
            --primary: #ff007f;
            --primary-light: rgba(255, 0, 127, 0.1);
            --secondary: #fc5cac;
            --dark: #1a1a1a;
            --darker: #121212;
            --darkest: #0a0a0a;
            --light: #e0e0e0;
            --lighter: #f5f5f5;
            --success: #00cc66;
            --warning: #ffcc00;
            --danger: #ff3333;
            --info: #0099ff;
            --gray: #2a2a2a;
            --gray-light: #3a3a3a;
            --gray-dark: #1e1e1e;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.6);
             --primary-rgb: 255, 0, 127;
             --danger-rgb: 255, 51, 51;
             --border-color: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--darker);
            color: var(--text-primary);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .checkout-container {
            width: 100%;
            max-width: 550px; /* Largura máxima do card */
        }

        .checkout-card {
            background: linear-gradient(145deg, var(--dark), var(--darkest));
            border-radius: 16px;
            padding: 2.5rem; /* Mais espaçamento interno */
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden; /* Para o spinner */
        }

        /* Efeito sutil de brilho no fundo */
        .checkout-card::before {
            content: '';
            position: absolute;
            inset: -5px; /* Cobre a borda */
            background: radial-gradient(circle at 20% 30%, rgba(var(--primary-rgb), 0.1) 0%, transparent 60%),
                        radial-gradient(circle at 80% 70%, rgba(var(--primary-rgb), 0.05) 0%, transparent 50%);
            z-index: 0;
            filter: blur(15px);
            opacity: 0.7;
            border-radius: 18px; /* Um pouco maior que o card */
            pointer-events: none;
        }


        .checkout-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative; /* Para ficar acima do ::before */
            z-index: 1;
        }

        .checkout-header img {
            width: 100px;
            margin-bottom: 1rem;
        }

        .checkout-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--lighter);
            margin-bottom: 0.5rem;
        }

        .checkout-header p {
            font-size: 1rem;
            color: var(--text-secondary);
        }

        .package-details {
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 1;
        }

        .package-details strong {
            color: var(--primary);
            font-weight: 600;
        }

        /* Estilos para os elementos onde o Mercado Pago vai renderizar */
        #form-checkout {
            position: relative;
            z-index: 1;
            min-height: 300px; /* Altura mínima enquanto carrega */
        }

        /* O container principal do Brick */
        #cardPaymentBrick_container {
            margin-bottom: 1.5rem;
            transition: opacity 0.3s ease;
        }

        /* Outros containers que o Brick pode criar */
        #cardNumber, #securityCode, #expirationDate {
            margin-bottom: 1rem;
        }

        /* Estilizando o botão de Pagar que o Brick pode precisar */
        /* Usamos !important com cautela, caso o MP force estilos */
         #form-checkout__submit {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
            margin-top: 1.5rem; /* Espaço acima do botão */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        #form-checkout__submit:hover:not(:disabled) {
            background: linear-gradient(135deg, #e0006f, #fc5cac);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.4);
        }

         #form-checkout__submit:disabled {
             background: var(--gray);
             cursor: not-allowed;
             opacity: 0.7;
         }

        /* Spinner de Carregamento */
        #loading-spinner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(18, 18, 18, 0.85); /* Fundo escuro semi-transparente */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
            border-radius: 16px; /* Para cobrir o card */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        #loading-spinner.show {
            opacity: 1;
            visibility: visible;
        }

        #loading-spinner i {
            font-size: 3rem;
            color: var(--primary);
            animation: spin 1.5s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mensagem de Status (Erro/Sucesso) */
        #payment-status {
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            display: none; /* Começa escondido */
            position: relative;
            z-index: 1;
        }

        #payment-status.success {
            background-color: rgba(var(--success-rgb), 0.1);
            color: var(--success);
            border: 1px solid rgba(var(--success-rgb), 0.3);
            display: block;
        }

        #payment-status.error {
            background-color: rgba(var(--danger-rgb), 0.1);
            color: var(--danger);
            border: 1px solid rgba(var(--danger-rgb), 0.3);
            display: block;
        }

        /* Ajustes para o formulário do MP (alguns estilos podem precisar de !important) */
        .mp-checkout-custom-card-payment-form-group label {
            color: var(--text-secondary) !important;
            font-size: 0.85rem !important;
            margin-bottom: 0.4rem !important;
            display: block;
        }

        .mp-checkout-custom-card-payment-form-control,
        .mp-checkout-custom-identification-form-control {
            background-color: var(--gray) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
            border-radius: 8px !important;
            padding: 0.75rem !important;
            width: 100% !important;
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.9rem !important;
        }
        .mp-checkout-custom-card-payment-form-control:focus,
        .mp-checkout-custom-identification-form-control:focus {
             border-color: var(--primary) !important;
             box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2) !important; /* Outline suave */
             outline: none !important;
        }
        /* Mensagens de erro do MP */
        .mp-checkout-custom-card-payment-form-error,
        .mp-checkout-custom-identification-form-error {
            color: var(--danger) !important;
            font-size: 0.8rem !important;
            margin-top: 0.3rem !important;
        }


        /* Responsividade básica */
        @media (max-width: 600px) {
            body {
                align-items: flex-start; /* Alinha no topo em telas menores */
                padding-top: 40px;
            }
            .checkout-card {
                padding: 1.5rem;
            }
             .checkout-header h1 {
                font-size: 1.4rem;
            }
            .checkout-header p {
                font-size: 0.9rem;
            }
        }
    </style>

    <!-- SDK Javascript do Mercado Pago V2 -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>

</head>
<body>

    <div class="checkout-container">
        <div class="checkout-card">
            <!-- Spinner de Carregamento (começa escondido) -->
            <div id="loading-spinner">
                <i class="fas fa-spinner"></i>
            </div>

            <div class="checkout-header">
                <img src="https://i.ibb.co/71J7N6n/love-chat.png" alt="Love Chat">
                <h1>Finalizar Pagamento</h1>
                <p>Complete os dados para adquirir seu pacote.</p>
            </div>

            <div class="package-details">
                <p>Você está comprando: <strong><?php echo htmlspecialchars($description); ?></strong></p>
                <p>Valor: <strong>R$ <?php echo number_format($price, 2, ',', '.'); ?></strong></p>
            </div>

            <!-- Container principal onde o formulário do cartão será renderizado -->
            <form id="form-checkout">
                <div id="cardPaymentBrick_container">
                    <!-- O Brick de Cartão será carregado aqui -->
                    <p style="text-align: center; color: var(--text-secondary); padding: 2rem 0;">Carregando formulário de pagamento...</p>
                </div>
                 <!-- Não precisa de um botão de submit aqui, o Brick geralmente tem o seu -->
                 <!-- Mas podemos ter um placeholder se o brick demorar -->
                 <button type="submit" id="form-checkout__submit" style="display: none;">Pagar R$ <?php echo number_format($price, 2, ',', '.'); ?></button>
            </form>

            <!-- Container para mensagens de status (sucesso/erro) -->
            <div id="payment-status"></div>

             <p style="text-align: center; margin-top: 2rem; font-size: 0.8rem; color: var(--text-secondary); position: relative; z-index: 1;">
                <i class="fas fa-lock"></i> Pagamento seguro processado por Mercado Pago.
            </p>

        </div>
    </div>

    <script>
        const mpPublicKey = "<?php echo $mercadoPagoPublicKey; ?>";
        const purchaseDescription = "<?php echo htmlspecialchars($description, ENT_QUOTES); ?>"; // Descrição para o MP
        const purchaseAmount = <?php echo $price; ?>; // Valor para o MP

        // Elementos do DOM
        const cardPaymentBrickContainer = document.getElementById('cardPaymentBrick_container');
        const paymentStatusContainer = document.getElementById('payment-status');
        const loadingSpinner = document.getElementById('loading-spinner');
        const submitButton = document.getElementById('form-checkout__submit'); // Pegamos o botão caso precise dele

        // Inicializa o SDK do Mercado Pago
        const mp = new MercadoPago(mpPublicKey, {
            locale: 'pt-BR' // Define o idioma
        });

        // Função para mostrar o spinner
        function showLoading(show = true) {
            if (loadingSpinner) {
                loadingSpinner.classList.toggle('show', show);
            }
             // Desabilita o botão (se existir e estiver visível) enquanto carrega
             if (submitButton) {
                submitButton.disabled = show;
                if(show && submitButton.style.display !== 'none') {
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                } else if (submitButton.style.display !== 'none'){
                    submitButton.innerHTML = 'Pagar R$ ' + purchaseAmount.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                }
            }
        }

        // Função para mostrar mensagens de status
        function showStatus(message, type = 'info') { // type pode ser 'success', 'error', 'info'
            if (paymentStatusContainer) {
                paymentStatusContainer.innerHTML = message;
                paymentStatusContainer.className = ''; // Limpa classes anteriores
                if (type === 'success' || type === 'error') {
                    paymentStatusContainer.classList.add(type);
                }
                paymentStatusContainer.style.display = 'block'; // Mostra o container
            }
        }

        // Função para carregar e configurar o Brick de Cartão
        async function loadCardPaymentBrick() {
            showLoading(true); // Mostra loading inicial
            const bricksBuilder = mp.bricks();

            const settings = {
                initialization: {
                    amount: purchaseAmount, // Valor total a ser pago
                    payer: { // Opcional: Pré-preencher email se tiver
                        // email: "seu_usuario@email.com", // Pegar da sessão PHP se logado
                    },
                },
                customization: {
                    visual: {
                        style: {
                            theme: 'dark', // 'dark', 'light' ou 'bootstrap'
                            // Cores baseadas nas suas variáveis CSS
                            customVariables: {
                                // Cor base (fundos, bordas) - Usando um cinza do seu tema
                                baseColor: getComputedStyle(document.documentElement).getPropertyValue('--gray').trim() || '#2a2a2a',
                                // Cor da fonte principal
                                baseColorFirstVariant: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#ffffff',
                               // Cor da fonte secundária (labels)
                                baseColorSecondVariant: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#cccccc',
                                // Cor de destaque (foco, links) - Usando sua cor primária
                                accentColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#ff007f',
                                // Cor de erro
                                errorColor: getComputedStyle(document.documentElement).getPropertyValue('--danger').trim() || '#ff3333',
                            },
                        },
                         // Ocultar botão de pagamento do Brick? (se sim, usaremos o nosso #form-checkout__submit)
                         // hidePaymentButton: true, // Descomente se quiser usar SEU botão
                    },
                    // Opcional: quais tipos de pagamento aceitar (deixe padrão se não tiver certeza)
                    // paymentMethods: {
                    //      maxInstallments: 1, // Exemplo: Sem parcelamento
                    // }
                },
                callbacks: {
                    onReady: () => {
                        // Callback chamado quando o Brick está pronto para ser usado.
                        console.log('Brick de Cartão pronto!');
                        showLoading(false); // Esconde o loading
                         // Se você OCULTOU o botão do brick (hidePaymentButton: true), mostre o seu:
                         // if (submitButton) submitButton.style.display = 'flex';
                    },
                    onSubmit: async (cardFormData) => {
                        // Callback chamado quando o usuário clica no botão de pagar DENTRO DO BRICK.
                        // É aqui que enviamos os dados para o NOSSO backend para processar o pagamento.
                        console.log('Dados do formulário recebidos:', cardFormData);
                        showLoading(true);
                        showStatus('Processando seu pagamento, aguarde...', 'info');

                        try {
                            const response = await fetch('api/process_payment.php', { // <<<=== APONTA PARA O SEU BACKEND PHP
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                // Enviamos os dados recebidos do Brick + a descrição da compra
                                body: JSON.stringify({
                                    ...cardFormData,
                                    description: purchaseDescription // Envia a descrição também
                                })
                            });

                            const paymentResult = await response.json();
                            console.log('Resultado do backend:', paymentResult);

                            if (response.ok && paymentResult.status === 'approved') {
                                // Pagamento Aprovado!
                                showLoading(false);
                                showStatus(`Pagamento Aprovado! ID: ${paymentResult.id}. Obrigado!`, 'success');
                                // Opcional: Redirecionar para uma página de sucesso após alguns segundos
                                // setTimeout(() => { window.location.href = 'sucesso.php?payment_id=' + paymentResult.id; }, 3000);

                                // Esconde o formulário e o botão após o sucesso
                                if (cardPaymentBrickContainer) cardPaymentBrickContainer.style.display = 'none';
                                if (submitButton) submitButton.style.display = 'none';

                            } else if (response.ok && paymentResult.status === 'in_process') {
                                 // Pagamento em processamento
                                showLoading(false);
                                showStatus(`Pagamento em processamento. ID: ${paymentResult.id}. Avisaremos quando for concluído.`, 'info');
                                if (cardPaymentBrickContainer) cardPaymentBrickContainer.style.display = 'none';
                                if (submitButton) submitButton.style.display = 'none';
                            }
                            else {
                                // Pagamento Recusado ou Erro no Backend
                                throw new Error(paymentResult.message || `Pagamento falhou com status: ${paymentResult.status || response.status}`);
                            }

                        } catch (error) {
                            console.error('Erro ao processar pagamento:', error);
                            showLoading(false);
                            showStatus(`Erro: ${error.message || 'Não foi possível processar o pagamento. Verifique os dados ou tente novamente.'}`, 'error');
                        }
                    },
                    onError: (error) => {
                        // Callback chamado em caso de erro DENTRO DO BRICK (ex: dados inválidos).
                        console.error('Erro no Brick de Cartão:', error);
                        showLoading(false); // Esconde loading caso estivesse ativo
                        showStatus('Erro ao validar os dados do cartão. Verifique as informações.', 'error');
                    },
                },
            };

            // Cria e renderiza o Brick
            window.cardPaymentBrickController = await bricksBuilder.create(
                'cardPayment', // Nome do Brick
                'cardPaymentBrick_container', // ID do container no HTML
                settings // Configurações que definimos
            );
            console.log('Instância do Brick criada.');

        } // Fim loadCardPaymentBrick

        // Carrega o Brick quando a página estiver pronta
        document.addEventListener('DOMContentLoaded', loadCardPaymentBrick);

        // Se você estiver usando SEU PRÓPRIO botão (hidePaymentButton: true no Brick)
        // adicione um listener para ele:
        /*
        if (submitButton) {
            submitButton.addEventListener('click', async (e) => {
                e.preventDefault(); // Previne o envio padrão do formulário

                // O ideal aqui seria chamar um método do controller do Brick, se disponível,
                // para pegar os dados e o token ANTES de enviar pro backend.
                // A documentação do MP pode ter detalhes sobre como fazer isso manualmente.
                // Por simplicidade, o callback onSubmit DENTRO do brick é mais direto.

                // Se o callback onSubmit do Brick já faz o envio, este listener pode não ser necessário
                // ou pode precisar interagir com `window.cardPaymentBrickController`
                console.log("Botão customizado clicado - Verifique a documentação do Brick para envio manual");
            });
        }
        */

    </script>

</body>
</html>