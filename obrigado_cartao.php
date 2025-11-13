<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado! - Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff007f; /* Rosa principal */
            --secondary-color: #00ff00; /* Verde para sucesso */
            --background-color: #2a2a2a; /* Fundo cinza escuro */
            --text-color: #ffffff;
            --card-background: rgba(40, 40, 40, 0.9);
            --border-color: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            padding: 20px;
            box-sizing: border-box;
            position: relative;
        }

        /* Manchas rosas (reutilizado do seu estilo original) */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 10% 20%, rgba(255, 0, 127, 0.1) 0%, transparent 25%),
                radial-gradient(circle at 90% 30%, rgba(252, 92, 172, 0.1) 0%, transparent 25%),
                radial-gradient(circle at 20% 70%, rgba(255, 105, 180, 0.1) 0%, transparent 25%),
                radial-gradient(circle at 80% 80%, rgba(255, 20, 147, 0.1) 0%, transparent 25%);
            z-index: -1;
            pointer-events: none;
        }

        .container {
            background-color: var(--card-background);
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            border: 1px solid var(--border-color);
            animation: fadeInScale 0.5s ease-out;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .logo {
            width: 120px;
            margin-bottom: 25px;
        }

        .success-icon {
            font-size: 4em;
            color: var(--secondary-color);
            margin-bottom: 20px;
            animation: iconPop 0.5s ease-out 0.3s;
            transform: scale(0); /* Start scaled down for animation */
        }

        @keyframes iconPop {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        h1 {
            font-size: 1.8em;
            color: var(--secondary-color);
            margin-bottom: 15px;
            font-weight: 700;
        }

        p {
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.85);
        }

        .order-info {
            font-size: 0.9em;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(to bottom, #00cc00, #009900); /* Seu verde do botão CTA */
            color: white;
            font-weight: 700;
            font-size: 1em;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0,255,0,0.3);
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,255,0,0.5);
        }

        .footer-text {
            margin-top: 30px;
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.6);
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat Logo" class="logo">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Pagamento Aprovado!</h1>
        <p>
            Obrigado por adquirir seu acesso ao Love Chat! Seu pagamento foi processado com sucesso.
        </p>
        <p>
            As instruções de acesso e todas as informações importantes foram enviadas para o seu e-mail.
            Por favor, verifique sua caixa de entrada (e também a pasta de spam/lixo eletrônico).
        </p>
        
        <div id="orderInfo" class="order-info" style="display: none;">
            <p>ID do seu Pedido: <strong id="orderIdValue"></strong></p>
        </div>

        <a href="URL_PARA_LOGIN_OU_AREA_DE_MEMBROS" class="cta-button">Acessar Agora</a> 
        <!-- Substitua URL_PARA_LOGIN_OU_AREA_DE_MEMBROS pelo link correto -->

        <p class="footer-text">
            Em caso de dúvidas, entre em contato com nosso <a href="https://wa.me/5534984474135" target="_blank" style="color: var(--primary-color);">suporte via WhatsApp</a>.
        </p>
    </div>

    <script>
        // Script para pegar o order_id da URL (se a Appmax enviar)
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const orderId = urlParams.get('order_id') || urlParams.get('appmax_order_id'); // Verifique o nome do parâmetro com a Appmax

            if (orderId) {
                document.getElementById('orderIdValue').textContent = orderId;
                document.getElementById('orderInfo').style.display = 'block';
            }
        });
    </script>
</body>
</html>