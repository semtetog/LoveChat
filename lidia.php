<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Um Pedido Especial para Lídia</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Parisienne&family=Montserrat:wght@300;400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cor-fundo-inicio: #ff9a9e; /* Rosa claro */
            --cor-fundo-fim: #fad0c4;    /* Rosa pálido */
            --cor-fundo-meio: #fbc2eb;   /* Rosa médio */
            --cor-container-bg: rgba(255, 255, 255, 0.3);
            --cor-container-border: rgba(255, 255, 255, 0.45);
            --cor-texto-principal: #5a3d5c; /* Roxo escuro para contraste */
            --cor-titulo: #d23669;      /* Rosa escuro vibrante */
            --cor-destaque: #ff6b81;     /* Rosa salmão */
            --cor-botao-sim: #ff4757;       /* Rosa vermelho */
            --cor-botao-sim-hover: #ff6b81;
            --cor-texto-botao: #ffffff;
            --cor-coracao: #ff4757;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--cor-fundo-inicio), var(--cor-fundo-meio), var(--cor-fundo-fim));
            background-size: 300% 300%;
            animation: gradientShift 15s ease infinite;
            color: var(--cor-texto-principal);
            padding: 15px;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            animation: float linear infinite;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        .heart {
            position: absolute;
            color: var(--cor-coracao);
            animation: floatHeart linear infinite;
            opacity: 0;
            z-index: 0;
            pointer-events: none;
            font-size: 1rem;
        }

        @keyframes floatHeart {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-90vh) rotate(360deg);
                opacity: 0;
            }
        }

        .container {
            background: var(--cor-container-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--cor-container-border);
            padding: 30px 25px;
            border-radius: 20px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
            z-index: 2;
            position: relative;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInSlideUp 1.2s ease-out 0.8s forwards;
            overflow: visible;
        }

        @keyframes fadeInSlideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .container::before {
            content: '';
            position: absolute;
            top: -30%;
            left: -30%;
            width: 160%;
            height: 160%;
            background: radial-gradient(circle, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0) 65%);
            animation: pulseGlow 7s infinite alternate;
            z-index: -1;
            opacity: 0;
        }

        @keyframes pulseGlow {
            0%, 100% {
                transform: scale(0.9);
                opacity: 0.1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.3;
            }
        }

        h1 {
            font-family: 'Parisienne', cursive;
            color: var(--cor-titulo);
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
            display: inline-block;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 5%;
            width: 90%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--cor-destaque), transparent);
            animation: underlineGrow 1.5s ease-out 1s forwards;
            transform: scaleX(0);
        }

        @keyframes underlineGrow {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }

        .question {
            font-size: 1rem;
            margin-bottom: 25px;
            line-height: 1.6;
            font-weight: 400;
            position: relative;
        }

        .question strong {
            font-weight: 700;
            color: var(--cor-titulo);
            position: relative;
        }

        .question strong::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--cor-destaque);
            transform-origin: left;
            animation: underlineGrow 1s ease-out 1.8s forwards;
            transform: scaleX(0);
        }

        .buttons {
            display: flex;
            justify-content: center; /* Centraliza o botão Sim */
            align-items: center;
            margin-top: 35px; /* Mais espaço acima do botão */
            position: relative;
            min-height: 70px; /* Altura para acomodar botão maior */
        }

        button { /* Estilo base para o botão Sim */
            padding: 16px 40px; /* Botão maior e mais destacado */
            border: none;
            border-radius: 50px; /* Bem arredondado */
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 1.1rem; /* Fonte maior */
            color: var(--cor-texto-botao);
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px; /* Espaço entre ícone e texto */
            position: relative;
            overflow: hidden;
            z-index: 1;
            -webkit-tap-highlight-color: transparent;
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.25), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
            z-index: -1;
        }

        button:hover::before {
            transform: translateX(100%);
        }

        button i {
            font-size: 1.3rem; /* Ícone maior */
        }

        #yesButton {
            background: var(--cor-botao-sim);
            box-shadow: 0 6px 20px rgba(255, 71, 87, 0.35); /* Sombra mais pronunciada */
        }

        #yesButton:hover {
            background: var(--cor-botao-sim-hover);
            transform: translateY(-3px) scale(1.05); /* Efeito hover mais notável */
            box-shadow: 0 8px 25px rgba(255, 107, 129, 0.45);
        }

        /* Resposta após o SIM */
        #response {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none; /* Começa escondido */
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.88);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 10;
            opacity: 0;
            animation: fadeOverlayIn 0.8s ease-out forwards;
            padding: 20px;
        }

        #confetti-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 11;
            pointer-events: none;
        }

        @keyframes fadeOverlayIn {
            to { opacity: 1; }
        }

        #response-content {
            text-align: center;
            max-width: 90%;
            width: 500px;
            animation: responseZoomIn 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.4s forwards;
            transform: scale(0.7);
            opacity: 0;
            z-index: 12;
            position: relative;
        }

        @keyframes responseZoomIn {
            to { transform: scale(1); opacity: 1; }
        }

        #response h2 {
            font-family: 'Parisienne', cursive;
            font-size: 3.5rem;
            color: #fff;
            margin-bottom: 15px;
            text-shadow: 0 0 12px rgba(255, 107, 129, 0.9);
            animation: textGlow 2.5s infinite alternate ease-in-out;
        }

        @keyframes textGlow {
            from { text-shadow: 0 0 8px rgba(255, 107, 129, 0.7); }
            to { text-shadow: 0 0 25px rgba(255, 107, 129, 1), 0 0 35px rgba(255, 107, 129, 0.7); }
        }

        #response p {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.1rem;
            color: #eee;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        #response button {
            background: transparent;
            border: 2px solid #fff;
            color: #fff;
            padding: 12px 28px;
            font-size: 1rem;
            transition: all 0.3s ease;
            z-index: 13;
            position: relative;
        }

        #response button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        /* Animação de corações pulsantes (espalhados) */
        .heart-beat {
            position: absolute;
            color: var(--cor-coracao);
            font-size: 1.5rem;
            animation: heartBeat 1.8s infinite ease-in-out;
            opacity: 0;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes heartBeat {
            0%, 100% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }

        /* Responsividade */
        @media (min-width: 600px) {
            .container {
                padding: 40px 40px;
                max-width: 480px;
            }
            h1 {
                font-size: 3rem;
            }
            .question {
                font-size: 1.1rem;
            }
            button { /* Botão Sim */
                padding: 18px 45px;
                font-size: 1.2rem;
            }
            button i {
                 font-size: 1.4rem;
            }
            #response h2 {
                font-size: 4.5rem;
            }
            #response p {
                font-size: 1.2rem;
            }
        }

        @media (min-width: 992px) {
            .container {
                max-width: 550px;
                padding: 50px 60px;
            }
            h1 {
                font-size: 3.5rem;
            }
            .question {
                font-size: 1.2rem;
            }
             button { /* Botão Sim */
                padding: 20px 50px;
                font-size: 1.3rem;
            }
             button i {
                 font-size: 1.5rem;
            }
            #response h2 {
                font-size: 5rem;
            }
            #response p {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Elementos de fundo animados -->
    <!-- <div class="fog"></div> --> <!-- Neblina desativada por padrão -->

    <!-- Container principal -->
    <div class="container" id="mainContent">
        <h1>Lídia Lacerda Gonçalves</h1>
        <p class="question">
            Desde que você entrou na minha vida, tudo ganhou mais cor e significado. Cada momento ao seu lado é um presente que guardo com carinho no coração.<br><br>
            Quero construir uma história linda com você, repleta de amor, risadas e cumplicidade.<br><br>
            <strong>Você aceita namorar comigo?</strong>
        </p>
        <div class="buttons">
            <button id="yesButton"><i class="fas fa-heart"></i> Sim, eu aceito!</button>
            <!-- Apenas o botão Sim agora -->
        </div>
    </div>

    <!-- Resposta -->
    <div id="response">
        <canvas id="confetti-canvas"></canvas>
        <div id="response-content">
            <h2>Eu te amo!</h2>
            <p>Este é o início da nossa linda história juntos. Prometo fazer você feliz todos os dias!</p>
            <button id="closeResponse"><i class="fas fa-heart"></i> Fechar</button>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const yesButton = document.getElementById('yesButton');
            const mainContent = document.getElementById('mainContent');
            const responseDiv = document.getElementById('response');
            const closeResponse = document.getElementById('closeResponse');
            const confettiCanvas = document.getElementById('confetti-canvas');
            const body = document.body;

            // Criar efeitos de fundo
            createBubbles(12);
            createFloatingHearts(6);
            createHeartBeats(4);

            // --- Lógica do Pedido ---
            yesButton.addEventListener('click', showResponse);

            closeResponse.addEventListener('click', function() {
                // Para a animação de confete se estiver ativa
                if (window.myConfetti && typeof window.myConfetti.reset === 'function') {
                    window.myConfetti.reset();
                }
                responseDiv.style.animation = 'fadeOverlayOut 0.5s ease-out forwards';
                setTimeout(() => {
                    responseDiv.style.display = 'none';
                    responseDiv.style.animation = ''; // Reseta a animação

                    // Opcional: Recarregar a página para voltar ao estado inicial
                    // window.location.reload();

                    // Ou apenas reexibir o conteúdo principal (sem recarregar):
                     mainContent.style.display = 'block';
                     mainContent.style.transition = 'opacity 0.5s ease-in, transform 0.5s ease-in';
                     mainContent.style.opacity = '1';
                     mainContent.style.transform = 'translateY(0)';
                     // Reabilitar botão Sim (caso queira permitir clicar novamente)
                     yesButton.disabled = false;

                }, 500);

                // Adiciona animação de saida (se não existir)
                if (!document.getElementById('fadeOverlayOutStyle')) {
                     const style = document.createElement('style');
                     style.id = 'fadeOverlayOutStyle';
                     style.textContent = `
                         @keyframes fadeOverlayOut {
                             from { opacity: 1; }
                             to { opacity: 0; }
                         }
                     `;
                     document.head.appendChild(style);
                }
            });

            // --- Funções Auxiliares ---
            function showResponse() {
                // Desabilitar botão para evitar cliques múltiplos
                 yesButton.disabled = true;

                mainContent.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
                mainContent.style.opacity = '0';
                mainContent.style.transform = 'translateY(-20px) scale(0.95)';

                setTimeout(() => {
                    mainContent.style.display = 'none';
                    responseDiv.style.display = 'flex';
                    // Dispara confetes SÓ DEPOIS que o overlay estiver visível
                    triggerConfetti();
                    // Chove corações
                    createHeartRain(25);
                }, 400);
            }

            function triggerConfetti() {
                if (!window.confetti) {
                    console.error("Biblioteca Confetti não carregada!");
                    return;
                }
                const canvas = document.getElementById('confetti-canvas');
                // Garante que não haja múltiplas instâncias de confete ativas
                if (window.myConfetti) {
                    window.myConfetti.reset();
                }
                 window.myConfetti = confetti.create(canvas, {
                    resize: true,
                    useWorker: true
                });

                const colors = ['#ff4757', '#ff6b81', '#ff8fa3', '#ffb3c1', '#ffffff'];

                function fire(particleRatio, opts) {
                    window.myConfetti(Object.assign({}, {
                        origin: { y: 0.6 },
                        colors: colors,
                        scalar: Math.random() * 0.6 + 0.8,
                        drift: Math.random() * 1 - 0.5,
                    }, opts, {
                        particleCount: Math.floor(200 * particleRatio)
                    }));
                }

                fire(0.25, { spread: 30, startVelocity: 55 });
                fire(0.2, { spread: 60 });
                fire(0.35, { spread: 100, decay: 0.91, scalar: 1 });
                fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 0.8 });
                fire(0.1, { spread: 120, startVelocity: 45 });

                // Confete de coração
                setTimeout(() => {
                    window.myConfetti({
                        particleCount: 30,
                        spread: 100,
                        origin: { y: 0.7 },
                        shapes: ['heart'],
                        colors: ['#ff4757', '#e84393', '#ff6b81'],
                        scalar: 1.2
                    });
                }, 300);
            }

            // --- Funções de Criação de Elementos Visuais (Otimizadas) ---

            function createBubbles(count) {
                const fragment = document.createDocumentFragment();
                for (let i = 0; i < count; i++) {
                    const bubble = document.createElement('div');
                    bubble.className = 'bubble';
                    const size = Math.random() * 25 + 8;
                    const duration = Math.random() * 25 + 15;
                    const delay = Math.random() * 15;

                    bubble.style.cssText = `
                        width: ${size}px;
                        height: ${size}px;
                        left: ${Math.random() * 100}%;
                        bottom: -${size + 10}px;
                        animation-duration: ${duration}s;
                        animation-delay: ${delay}s;
                        transform: scale(${Math.random() * 0.5 + 0.5});
                    `;
                    fragment.appendChild(bubble);
                }
                body.appendChild(fragment);
            }

            function createFloatingHearts(count) {
                const fragment = document.createDocumentFragment();
                for (let i = 0; i < count; i++) {
                    const heart = document.createElement('div');
                    heart.className = 'heart';
                    heart.innerHTML = '<i class="fas fa-heart"></i>';
                    const size = Math.random() * 16 + 8;
                    const duration = Math.random() * 20 + 12;
                    const delay = Math.random() * 18;

                    heart.style.cssText = `
                        font-size: ${size}px;
                        left: ${Math.random() * 100}%;
                        bottom: -${size + 10}px;
                        animation-duration: ${duration}s;
                        animation-delay: ${delay}s;
                        opacity: 0;
                    `;
                    fragment.appendChild(heart);
                }
                body.appendChild(fragment);
            }


            function createHeartBeats(count) {
                 const fragment = document.createDocumentFragment();
                 for (let i = 0; i < count; i++) {
                    const heart = document.createElement('div');
                    heart.className = 'heart-beat';
                    heart.innerHTML = '<i class="fas fa-heart"></i>';
                    const size = Math.random() * 25 + 15;
                    const duration = Math.random() * 2 + 1.8;
                    const delay = Math.random() * 7;

                    heart.style.cssText = `
                        font-size: ${size}px;
                        left: ${Math.random() * 95 + 2.5}%;
                        top: ${Math.random() * 95 + 2.5}%;
                        animation-duration: ${duration}s;
                        animation-delay: ${delay}s;
                    `;
                    fragment.appendChild(heart);
                }
                 body.appendChild(fragment);
            }

            function createHeartRain(count) {
                const fragment = document.createDocumentFragment();
                 for (let i = 0; i < count; i++) {
                    setTimeout(() => {
                        const heart = document.createElement('div');
                        heart.innerHTML = '<i class="fas fa-heart"></i>';
                        const size = Math.random() * 20 + 10;
                        const duration = Math.random() * 2.5 + 2;
                        const sway = Math.random() * 100 - 50;

                        heart.style.cssText = `
                            position: fixed;
                            font-size: ${size}px;
                            left: ${Math.random() * 100}%;
                            top: -${size + 10}px;
                            color: hsl(${Math.random() * 20 + 340}, 100%, ${Math.random() * 20 + 60}%);
                            z-index: 11;
                            pointer-events: none;
                            animation: fall_${i} ${duration}s linear forwards;
                            transform: translateX(0px);
                        `;

                        const style = document.createElement('style');
                        style.textContent = `
                            @keyframes fall_${i} {
                                0% { transform: translateX(0px) rotate(0deg); opacity: 1; }
                                100% { transform: translateX(${sway}px) rotate(${Math.random() * 360 - 180}deg); top: 110vh; opacity: 0.5; }
                            }
                        `;
                        document.head.appendChild(style); // Adiciona a keyframe rule
                        body.appendChild(heart); // Adiciona o coração ao body

                        // Remove após a animação + buffer e remove a keyframe rule
                        setTimeout(() => {
                            heart.remove();
                            style.remove(); // Limpa a keyframe rule do head
                        }, duration * 1000 + 200);

                    }, i * (8000 / count));
                }
            }
        });
    </script>
</body>
</html>