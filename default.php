<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Chat - Lucre Conversando</title>
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #ff007f;
            --secondary-color: #ff4DA6;
            --accent-color: #00ff8c;
            --accent-hover: #00e67e;
            --bg-dark: #121212;
            --bg-card: rgba(25, 25, 25, 0.5);
            --text-light: #f5f5f5;
            --text-medium: #b3b3b3;
            --card-border: rgba(255, 255, 255, 0.1);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-light);
            background-color: var(--bg-dark);
            text-align: center;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: 
                radial-gradient(circle at 15% 25%, rgba(255, 0, 127, 0.25) 0%, transparent 30%),
                radial-gradient(circle at 85% 75%, rgba(255, 77, 166, 0.2) 0%, transparent 35%);
            z-index: -2; /* Atrás do vídeo */
            animation: background-pan 25s infinite linear alternate;
        }

        @keyframes background-pan {
            from { transform: scale(1); }
            to { transform: scale(1.2); }
        }
        
        /* --- ESTILOS DO VÍDEO DE FUNDO --- */
        .video-background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            z-index: -1; /* Fica entre o gradiente e o conteúdo */
        }
        
        .video-background-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.2; /* Opacidade do vídeo */
        }
        
        /* Overlay e Degradê sobre o vídeo */
        .video-background-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(to top, rgba(18, 18, 18, 1) 0%, transparent 40%), /* Degradê Preto na Base */
                rgba(0, 0, 0, 0.1); /* Overlay escuro geral */
        }

        /* Container para todo o conteúdo visível */
        .content-wrapper {
            position: relative;
            z-index: 2;
            background-color: transparent;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .hero {
            padding: 60px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
        }

        .logo {
            width: 140px;
            margin-bottom: 25px;
            filter: drop-shadow(0 0 15px rgba(255,0,127,0.6));
        }

        .headline {
    font-family: 'Montserrat', sans-serif;
    font-weight: 700;
    font-size: clamp(1.8rem, 2.5vw, 3.2rem);
    line-height: 1.25;
    margin-bottom: 15px;
    max-width: 800px;
    text-shadow: 0 3px 15px rgba(0,0,0,0.8);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2em;
}

.headline-main {
    font-size: 0.9em;
    font-weight: 600;
    color: var(--text-light);
}

.highlight {
    color: var(--accent-color);
    text-shadow: 0 0 12px rgba(0, 255, 140, 0.5);
    font-size: 1.1em;
    text-align: center;
    line-height: 1.1;
    margin: 0.1em 0;
}

.headline-sub {
    font-size: 0.8em;
    font-weight: 600;
    color: var(--text-light);
    text-align: center;
}

@media (max-width: 600px) {
    .headline {
        gap: 0.1em;
    }
    .headline-main {
        font-size: 0.85em;
    }
    .highlight {
        font-size: 1em;
        line-height: 1;
    }
    .headline-sub {
        font-size: 0.75em;
    }
}

        .headline .highlight {
            color: var(--accent-color);
            text-shadow: 0 0 12px rgba(0, 255, 140, 0.5);
        }

        .subheadline {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: var(--text-medium);
            max-width: 600px;
            margin-bottom: 40px;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(45deg, var(--accent-color), var(--accent-hover));
            color: #000;
            font-weight: 700;
            font-size: clamp(1rem, 2.5vw, 1.1rem);
            padding: 16px 40px;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 5px 20px rgba(0, 255, 140, 0.3);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: pulse-green 2s infinite;
        }
        
        @keyframes pulse-green {
            0% { transform: scale(1); box-shadow: 0 5px 20px rgba(0, 255, 140, 0.3); }
            50% { transform: scale(1.05); box-shadow: 0 8px 30px rgba(0, 255, 140, 0.5); }
            100% { transform: scale(1); box-shadow: 0 5px 20px rgba(0, 255, 140, 0.3); }
        }

        .cta-button:hover {
            transform: scale(1.05) translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 255, 140, 0.6);
        }

        .section {
            padding: 80px 0;
            position: relative;
        }

        .section-title {
            font-size: clamp(2rem, 6vw, 2.8rem);
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            margin: 15px auto 0;
            border-radius: 2px;
        }

        .section-subtitle {
            font-size: clamp(1rem, 4vw, 1.1rem);
            color: var(--text-medium);
            max-width: 700px;
            margin: 0 auto 50px auto;
            line-height: 1.6;
        }
        
        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
        }
        
        .benefits-grid {
            display: grid;
            gap: 30px;
        }

        .benefit-item {
            display: flex;
            flex-direction: column;
            text-align: left;
            padding: 30px;
        }

        .benefit-content {
             flex-grow: 1;
        }

        .benefit-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .benefit-item h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .benefit-item p {
            color: var(--text-medium);
            line-height: 1.7;
            font-size: 1rem;
        }
        
        .proof-slider {
            display: none;
            position: relative;
            max-width: 400px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 15px;
        }

        .slider-track {
            display: flex;
            transition: transform 0.5s ease-in-out;
            cursor: grab;
        }
        .slider-track:active {
            cursor: grabbing;
        }

        .slider-slide {
            flex: 0 0 100%;
            width: 100%;
        }
        
        .slider-slide img {
            width: 100%;
            display: block;
            pointer-events: none;
        }
        
        .slider-button {
             display: none;
        }
        
        .slider-dots {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }
        
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dot.active {
            background-color: white;
            transform: scale(1.2);
        }

        .proof-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .proof-item img {
            width: 100%;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.4);
        }
        
        .proof-item img:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        }
        
        #plano {
            background-color: transparent;
        }
        
        .plan-card {
            max-width: 650px;
            margin: 0 auto;
            border: 2px solid var(--primary-color);
            box-shadow: 0 0 40px rgba(255, 0, 127, 0.4);
        }
        
        .plan-header {
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .plan-title {
            font-size: clamp(1.8rem, 5vw, 2.5rem);
        }
        
        .plan-price {
            display: flex;
            justify-content: center;
            align-items: baseline;
            margin-bottom: 10px;
        }
        .plan-price .currency {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 600;
            color: var(--text-medium);
            margin-right: 8px;
        }
        .plan-price .value {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(4.5rem, 12vw, 6rem);
            font-weight: 800;
            color: var(--accent-color);
            line-height: 1;
            text-shadow: 0 0 15px rgba(0, 255, 140, 0.3);
        }
        .plan-price .cents {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 600;
            color: var(--text-medium);
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 30px 0;
            text-align: left;
            display: inline-block;
        }
        
        .plan-features li {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: clamp(1rem, 3vw, 1.1rem);
        }
        
        .plan-features li i {
            color: var(--accent-color);
            margin-right: 15px;
            font-size: 1.3rem;
            width: 25px;
            text-align: center;
        }

        .guarantee-card {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 30px;
        }

        .guarantee-badge {
            width: 150px;
            height: 150px;
            flex-shrink: 0;
            margin: 0 auto;
        }
        .guarantee-content {
            flex: 1;
            min-width: 280px;
            text-align: left;
        }

        .guarantee-content h2 {
            font-size: clamp(1.5rem, 5vw, 1.8rem);
        }
        
        .footer {
            padding: 50px 20px;
            background: #0a0a0a;
            border-top: 1px solid var(--card-border);
        }
        
        .footer .whatsapp-button {
             background: transparent;
             border: 2px solid var(--accent-color);
             color: var(--accent-color);
             padding: 14px 30px;
             margin-top: 30px;
        }
        
        .footer .whatsapp-button:hover {
            background: var(--accent-color);
            color: #000;
        }
        
        @media (max-width: 767px) {
            .section {
                padding: 60px 0;
            }
            .benefits-grid {
                grid-template-columns: 1fr;
            }
            .proof-grid {
                display: none;
            }
            .proof-slider {
                display: block;
            }
            .guarantee-card {
                text-align: center;
            }
            .guarantee-content {
                text-align: center;
            }
        }

        @media (min-width: 768px) {
            .benefits-grid {
                grid-template-columns: repeat(2, 1fr);
                max-width: 900px;
                margin-left: auto;
                margin-right: auto;
            }
        }
    </style>
</head>
<body>
    
    <!-- VÍDEO DE FUNDO -->
    <div class="video-background-container">
        <video autoplay loop muted playsinline>
            <source src="background.webm" type="video/webm">
            <!-- Adicione um .mp4 para maior compatibilidade se tiver -->
            <!-- <source src="background.mp4" type="video/mp4"> -->
        </video>
    </div>

    <!-- CONTEÚDO DA PÁGINA -->
    <div class="content-wrapper">
        <header class="hero">
            <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Logo Love Chat" class="logo">
            <h1 class="headline">
    <span class="headline-main">Ganhe até <span class="highlight">R$5.000,00 POR DIA</span>
    
    <span class="headline-sub">com Inteligência Artificial</span>
    <span class="headline-sub">pelo WhatsApp de forma automática!</span>
</h1>
            <p class="subheadline">
                Descubra o método validado para transformar suas conversas em renda extra, com total segurança e anonimato. Junte-se à Love Chat!
            </p>
            <a href="#plano" class="cta-button">Quero Começar Agora</a>
        </header>

        <main>
            <section class="section benefits">
                <div class="container">
                    <h2 class="section-title">Por Que Escolher a Love Chat?</h2>
                    <p class="section-subtitle">
                        Oferecemos tudo o que você precisa para ter sucesso, de forma simples e direta, mesmo que você seja iniciante.
                    </p>
                    <div class="benefits-grid">
                        <div class="glass-card benefit-item">
                            <div class="benefit-content">
                                <i class="fas fa-hand-holding-dollar benefit-icon"></i>
                                <h3>Ganhos Diários</h3>
                                <p>Comece hoje mesmo e tenha a possibilidade de ganhar até <strong>R$3.000,00</strong> por dia, direto no seu PIX.</p>
                            </div>
                        </div>
                        <div class="glass-card benefit-item">
                            <div class="benefit-content">
                                <i class="fas fa-user-secret benefit-icon"></i>
                                <h3>Sigilo e Segurança</h3>
                                <p>Fornecemos materiais profissionais para você vender sem precisar expor sua identidade. Seu anonimato é nossa prioridade.</p>
                            </div>
                        </div>
                        <div class="glass-card benefit-item">
                            <div class="benefit-content">
                                <i class="fas fa-globe-americas benefit-icon"></i>
                                <h3>Total Flexibilidade</h3>
                                <p>Trabalhe de onde e quando quiser. Você só precisa de um celular e acesso à internet para lucrar a qualquer momento.</p>
                            </div>
                        </div>
                        <div class="glass-card benefit-item">
                            <div class="benefit-content">
                                 <i class="fas fa-headset benefit-icon"></i>
                                 <h3>Suporte Vitalício</h3>
                                 <p>Nossa equipe está disponível 24/7 via WhatsApp para te ajudar a alcançar suas metas e tirar todas as suas dúvidas.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section proof">
                <div class="container">
                    <h2 class="section-title">Resultados de Nossos Alunos</h2>
                    <p class="section-subtitle">
                        Veja o que pessoas como você estão conquistando todos os dias com a plataforma Love Chat.
                    </p>
                    <div class="proof-grid">
                        <div class="proof-item"><img src="https://i.ibb.co/LDBJTgBW/LOVECHATPIX2.webp" alt="Comprovante de ganhos 1"></div>
                        <div class="proof-item"><img src="https://i.ibb.co/W4Yzczr0/LOVECHATPIX.webp" alt="Comprovante de ganhos 2"></div>
                        <div class="proof-item"><img src="https://i.ibb.co/27hcFQf8/comprovante-1.webp" alt="Comprovante de ganhos 3"></div>
                        <div class="proof-item"><img src="https://i.ibb.co/67Mq3SG0/comprovante-2.webp" alt="Comprovante de ganhos 4"></div>
                    </div>
                    <div class="proof-slider" id="proof-slider">
                        <div class="slider-track">
                            <div class="slider-slide"><img src="https://i.ibb.co/LDBJTgBW/LOVECHATPIX2.webp" alt="Comprovante de ganhos 1"></div>
                            <div class="slider-slide"><img src="https://i.ibb.co/W4Yzczr0/LOVECHATPIX.webp" alt="Comprovante de ganhos 2"></div>
                            <div class="slider-slide"><img src="https://i.ibb.co/27hcFQf8/comprovante-1.webp" alt="Comprovante de ganhos 3"></div>
                            <div class="slider-slide"><img src="https://i.ibb.co/67Mq3SG0/comprovante-2.webp" alt="Comprovante de ganhos 4"></div>
                        </div>
                        <button class="slider-button prev">&lt;</button>
                        <button class="slider-button next">&gt;</button>
                        <div class="slider-dots"></div>
                    </div>
                </div>
            </section>

            <section id="plano" class="section">
                <div class="container">
                    <h2 class="section-title">Acesso Completo e Ilimitado</h2>
                    <p class="section-subtitle">
                        Chega de mensalidades e planos complicados. Oferecemos um único investimento para acesso vitalício a tudo que você precisa para lucrar.
                    </p>
                    <div class="glass-card plan-card">
                        <div class="plan-header">
                            <h3 class="plan-title">Plano Full Vitalício</h3>
                            <span class="plan-badge">Acesso Para Sempre</span>
                        </div>
                        <div class="plan-price">
                            <span class="currency">R$</span>
                            <span class="value">500</span>
                            <small class="cents">,00</small>
                        </div>
                        <p class="plan-price-details">Pagamento Único. Sem Surpresas.</p>
                        <ul class="plan-features">
                            <li><i class="fas fa-star"></i> Acesso vitalício à plataforma</li>
                            <li><i class="fas fa-book-open"></i> Aulas e materiais exclusivos</li>
                            <li><i class="fas fa-users"></i> +800 Clientes para começar</li>
                            <li><i class="fab fa-whatsapp"></i> Bot de atendimento WhatsApp</li>
                            <li><i class="fas fa-headset"></i> Suporte Premium Prioritário</li>
                            <li><i class="fas fa-gift"></i> Atualizações futuras gratuitas</li>
                        </ul>
                        <a href="/checkoutapp.php" target="_blank" class="cta-button">Garantir Meu Acesso Vitalício</a>
                    </div>
                </div>
            </section>
            
            <section class="section guarantee">
                <div class="container">
                     <div class="glass-card guarantee-card">
                        <img src="https://images.assets-landingi.com/uc/c46b214b-8377-485a-ac2c-2ca3926aa8ab/selodesatisfao.webp" alt="Selo de Garantia" class="guarantee-badge">
                        <div class="guarantee-content">
                            <h2>Retorno Garantido</h2>
                            <p>Temos tanta confiança no nosso método que garantimos: se você aplicar o que ensinamos e não recuperar seu investimento em até 7 dias, devolvemos 100% do seu dinheiro. O risco é todo nosso.</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer">
            <div class="container">
                <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Logo Love Chat" class="logo">
                <p><strong>Ainda tem dúvidas?</strong> Nosso time de suporte está pronto para te ajudar!</p>
                <a href="https://wa.me/5534992981424?text=Olá,%20tenho%20dúvidas%20sobre%20o%20Plano%20Vitalício%20da%20Love%20Chat" class="cta-button whatsapp-button" target="_blank">
                    <i class="fab fa-whatsapp" style="margin-right: 10px;"></i>Falar com Suporte
                </a>
                <p style="margin-top: 30px; font-size: 0.8rem; opacity: 0.7;">
                    © 2025 Love Chat - Todos os direitos reservados.
                </p>
            </div>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const sliderElement = document.getElementById('proof-slider');
        if (sliderElement && window.innerWidth <= 767) {
            const track = sliderElement.querySelector('.slider-track');
            const slides = Array.from(track.children);
            const dotsNav = sliderElement.querySelector('.slider-dots');
            if (!track || slides.length === 0) return;

            let currentIndex = 0;
            let autoplayInterval = null;
            let isDragging = false;
            let startPos = 0;
            let currentTranslate = 0;
            let prevTranslate = 0;
            const slideWidth = () => slides[0].getBoundingClientRect().width;

            slides.forEach((_, index) => {
                const dot = document.createElement('button');
                dot.classList.add('dot');
                if (index === 0) dot.classList.add('active');
                dotsNav.appendChild(dot);
                dot.addEventListener('click', () => {
                    moveToSlide(index);
                    resetAutoplay();
                });
            });
            const dots = Array.from(dotsNav.children);

            const setSliderPosition = () => {
                track.style.transform = `translateX(${currentTranslate}px)`;
            };

            const updateDots = (targetIndex) => {
                dots.forEach(dot => dot.classList.remove('active'));
                dots[targetIndex].classList.add('active');
            };

            const moveToSlide = (targetIndex) => {
                track.style.transition = 'transform 0.5s ease-in-out';
                currentTranslate = targetIndex * -slideWidth();
                prevTranslate = currentTranslate;
                setSliderPosition();
                updateDots(targetIndex);
                currentIndex = targetIndex;
            };
            
            const moveToNext = () => {
                 let nextIndex = currentIndex + 1;
                 if (nextIndex >= slides.length) nextIndex = 0;
                 moveToSlide(nextIndex);
            }

            const startAutoplay = () => {
                stopAutoplay();
                autoplayInterval = setInterval(moveToNext, 4000);
            };

            const stopAutoplay = () => {
                clearInterval(autoplayInterval);
            };
            
            const resetAutoplay = () => {
                stopAutoplay();
                startAutoplay();
            };

            const dragStart = (e) => {
                isDragging = true;
                startPos = e.type.includes('mouse') ? e.pageX : e.touches[0].clientX;
                track.style.transition = 'none';
                stopAutoplay();
            };

            const dragMove = (e) => {
                if (!isDragging) return;
                const currentPosition = e.type.includes('mouse') ? e.pageX : e.touches[0].clientX;
                currentTranslate = prevTranslate + currentPosition - startPos;
                setSliderPosition();
            };

            const dragEnd = () => {
                if (!isDragging) return;
                isDragging = false;
                const movedBy = currentTranslate - prevTranslate;

                if (movedBy < -100 && currentIndex < slides.length - 1) {
                    currentIndex += 1;
                }
                if (movedBy > 100 && currentIndex > 0) {
                    currentIndex -= 1;
                }
                moveToSlide(currentIndex);
                startAutoplay();
            };

            track.addEventListener('mousedown', dragStart);
            track.addEventListener('touchstart', dragStart, { passive: true });
            track.addEventListener('mousemove', dragMove);
            track.addEventListener('touchmove', dragMove, { passive: true });
            document.addEventListener('mouseup', dragEnd);
            track.addEventListener('mouseleave', dragEnd);
            track.addEventListener('touchend', dragEnd);
            
            window.addEventListener('resize', () => moveToSlide(currentIndex));
            
            startAutoplay();
        }
    });
    </script>
</body>
</html>