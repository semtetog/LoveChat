<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            color: #fff;
            text-align: center;
            overflow-x: hidden;
            background: #2a2a2a; /* Uniform gray background */
            position: relative;
        }
        
        /* Pink splashes on the sides */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(255, 0, 127, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 90% 30%, rgba(252, 92, 172, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 20% 70%, rgba(255, 105, 180, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 80% 80%, rgba(255, 20, 147, 0.15) 0%, transparent 20%);
            z-index: -1;
            pointer-events: none;
        }
        
        /* Header Section */
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px 20px;
        }
        
        .logo {
            width: 140px;
            margin-bottom: 15px;
            filter: drop-shadow(0 0 10px rgba(255,0,127,0.5));
        }
        
        .headline {
            font-weight: 700;
            font-size: clamp(18px, 5vw, 28px);
            line-height: 1.25;
            margin-bottom: 5px;
            max-width: 800px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.7);
        }
        
        .highlight {
            color: transparent;
            background: linear-gradient(1deg, #96ff00, #00ff00);
            -webkit-background-clip: text;
            background-clip: text;
            text-shadow: 0 2px 5px rgba(150, 255, 0, 0.3);
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from { text-shadow: 0 0 5px rgba(150, 255, 0, 0.5); }
            to { text-shadow: 0 0 15px rgba(150, 255, 0, 0.8); }
        }
        
        /* Video Section */
        .video-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.7);
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,0,127,0.3);
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* CTA Button */
        .cta-button, .buy-button {
            display: inline-block;
            background: linear-gradient(to bottom, #00cc00, #009900);
            color: white;
            font-weight: 700;
            font-size: clamp(15px, 3vw, 17px);
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            margin: 40px auto 0;
            box-shadow: 0 4px 12px rgba(0,255,0,0.4);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
            border: none;
            cursor: pointer;
        }
        
        .cta-button:hover, .buy-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,255,0,0.6);
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(0, 255, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0); }
        }
        
        /* Spacing between blocks */
        .spacer {
            height: 10px;
        }

        /* Benefits Section */
        .benefits {
            padding: 20px 0px;
        }

        .benefits-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            font-size: 2em;
            margin-bottom: 40px;
            color: #fff;
            text-align: center;
            text-shadow: 0 2px 5px rgba(0,0,0,0.7);
            position: relative;
        }

        .section-title::after {
            content: "";
            display: block;
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, #ff007f, #fc5cac);
            margin: 15px auto;
            border-radius: 3px;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .benefit-item {
            background: rgba(40, 40, 40, 0.8);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(255,0,127,0.3);
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .benefit-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255,0,127,0.2);
            border-color: #fc5cac;
        }

        .benefit-content {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        .benefit-image {
            width: 90px;
            height: 90px;
            object-fit: contain;
            flex-shrink: 0;
            padding: 8px;
            
        }

        .benefit-text {
            flex: 1;
            text-align: left;
        }

        .benefit-text h3 {
            color: #ff007f;
            font-size: 1.3em;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .benefit-text p {
            font-size: 1em;
            line-height: 1.5;
            color: #e0e0e0;
            margin-left: 0;
        }

        /* WhatsApp Proof Section */
        .whatsapp-proof {
            padding: 45px 0px;
            background: rgba(30, 30, 30, 0.6);
        }

        .prints-desktop {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .prints-desktop img {
            width: 100%;
            object-fit: contain;
            
            padding: 5px;
        }

        .carousel-mobile {
            display: none;
            position: relative;
            overflow: hidden;
            margin: 0 5px;
        }

        .carousel-inner {
            display: flex;
            transition: transform 0.4s ease-out;
        }

        .carousel-mobile img {
            flex: 0 0 100%;
            width: 100%;
            padding: 0 -20px;
            box-sizing: border-box;
            
            object-fit: contain;
            max-height: 60vh;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            background: rgba(255, 0, 127, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .carousel-btn.prev {
            left: 10px;
        }

        .carousel-btn.next {
            right: 10px;
        }

        .carousel-btn:disabled {
            opacity: 0.3;
            cursor: default;
        }

        /* Final CTA Section */
        .final-cta {
            padding: 80px 20px;
            text-align: center;
            position: relative;
            background: rgba(30, 30, 30, 0.8);
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-title {
            font-size: 2em;
            margin-bottom: 20px;
            color: #fff;
            text-shadow: 0 2px 5px rgba(0,0,0,0.7);
        }

        .cta-text {
            font-size: 1.1em;
            margin-bottom: 25px;
            color: #ddd;
        }

        .counter-container {
            display: inline-block;
            background: rgba(0,0,0,0.5);
            border-radius: 5px;
            padding: 2px 10px;
            margin: 0 3px;
            min-width: 60px;
        }

        .counter {
            font-size: 1.1em;
            color: #00ff00;
            font-weight: bold;
            display: inline-block;
            min-width: 40px;
            text-align: center;
        }

        /* Responsiveness for small screens */
        @media (max-width: 768px) {
            .header-content {
                padding: 15px 8px 10px;
            }
            
            .logo {
                width: 120px;
                margin-bottom: 12px;
            }
            
            .headline {
                font-size: clamp(16px, 5vw, 22px);
                line-height: 1.2;
            }
            
            .video-content {
                padding: 10px 8px;
            }
            
            .cta-button, .buy-button {
                padding: 8px 20px;
                margin: 25px auto 0;
                font-size: clamp(14px, 3vw, 16px);
            }
            
            .spacer {
                height: 8px;
            }

            .benefits-grid {
            grid-template-columns: 1fr;
            gap: 15px;
            padding: 0;
        }

        .benefit-item {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            margin: 0 auto;
        }

        .benefit-content {
            flex-direction: row;
            align-items: center;
            gap: 15px;
            text-align: left;
            height: 100%;
        }

        .benefit-image {
            width: 60px;
            height: 60px;
            margin: 0;
            flex-shrink: 0;
        }

        .benefit-text {
            text-align: left;
            width: calc(100% - 75px);
        }

        .benefit-text h3 {
            font-size: 1.1em;
            margin-bottom: 8px;
        }

        .benefit-text p {
            font-size: 0.85em;
            max-width: 100%;
            margin: 0;
            line-height: 1.4;
        }

            .prints-desktop {
                display: none;
            }
            
            .carousel-mobile {
                display: block;
            }
            
            .counter-container {
                min-width: 50px;
            }
        }
        
        @media (max-width: 480px) {
            .header-content {
                padding: 12px 5px 8px;
            }
            
            .logo {
                width: 110px;
                margin-bottom: 10px;
            }
            
            .cta-button, .buy-button {
                width: 90%;
                max-width: 260px;
                padding: 8px 15px;
            }

            .section-title {
                font-size: 1.5em;
                margin-bottom: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Header Block -->
    <section class="header-block">
        <div class="header-content">
            <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat Logo" class="logo">
            <h1 class="headline">
                <span class="headline-line">Earn up to <span class="highlight">$500 PER DAY</span></span>
                <span class="headline-line">just by chatting on WhatsApp</span>
                <span class="headline-line">with the LOVE CHAT extension!</span>
            </h1>
        </div>
    </section>
    
    <!-- Spacing between blocks -->
    <div class="spacer"></div>
    
    <!-- Video Block -->
    <section class="video-block">
        <div class="video-content">
            <div class="video-container">
                <iframe 
                    src="https://player-vz-c53de432-3ad.tv.pandavideo.com.br/embed/?v=ecabacee-9549-48f7-a7b6-6cb743eb2634" 
                    allow="autoplay; fullscreen; encrypted-media; accelerometer; gyroscope" 
                    allowfullscreen>
                </iframe>
            </div>
            <a href="https://pay.hotmart.com/K98901235K?checkoutMode=10" class="cta-button">
                REGISTER AND<br>START TRAINING NOW
            </a>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits">
        <div class="benefits-container">
            <h2 class="section-title">WHY CHOOSE LOVE CHAT?</h2>
            <div class="benefits-grid">
                <!-- Item 1 -->
                <div class="benefit-item">
                    <div class="benefit-content">
                        <img src="https://emojiisland.com/cdn/shop/products/Money_Emoji_Icon_59b7293e-e703-4ba4-b3c3-a7b9401f89fb_large.png?v=1571606091" alt="Daily Earnings" class="benefit-image">
                        <div class="benefit-text">
                            <h3>Daily Earnings</h3>
                            <p>Complete the training and start earning between <strong>$50</strong> and <strong>$500</strong> per day in your bank account!</p>
                        </div>
                    </div>
                </div>

                <!-- Item 2 -->
                <div class="benefit-item">
                    <div class="benefit-content">
                        <img src="https://i.ibb.co/s9RsQmFc/11-large.webp" alt="Total Privacy" class="benefit-image">
                        <div class="benefit-text">
                            <h3>Total Privacy</h3>
                            <p>Your data is protected and won't be shared with social networks or carriers.</p>
                        </div>
                    </div>
                </div>

                <!-- Item 3 -->
                <div class="benefit-item">
                    <div class="benefit-content">
                        <img src="https://i.ibb.co/Gf610SLc/clockkkkkkk.webp" alt="Work From Anywhere" class="benefit-image">
                        <div class="benefit-text">
                            <h3>Set Your Own Hours</h3>
                            <p>Work from anywhere, at any time of day, including late nights!</p>
                        </div>
                    </div>
                </div>

                <!-- Item 4 -->
                <div class="benefit-item">
                    <div class="benefit-content">
                        <img src="https://symbl-world.akamaized.net/i/webp/0d/7c2f679102faa666537fd6e9dfca15.webp" alt="Lucrative Referrals" class="benefit-image">
                        <div class="benefit-text">
                            <h3>Lucrative Referrals</h3>
                            <p>Earn <strong>50%</strong> of the registration fee for each friend you refer!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- WhatsApp Proof Section -->
    <section class="whatsapp-proof">
        <h2 class="section-title">SEE THE RESULTS</h2>
        
        <!-- Desktop (3 columns) -->
        <div class="prints-desktop">
            <img src="https://i.ibb.co/SXyzb8kw/1p.webp" alt="Result 1" class="whatsapp-print">
            <img src="https://i.ibb.co/zVV48XM6/paypalnovo.webp" alt="Result 2" class="whatsapp-print">
        </div>
        
        <!-- Mobile (carousel) -->
        <div class="carousel-mobile">
            <div class="carousel-inner">
                <img src="https://i.ibb.co/SXyzb8kw/1p.webp" alt="Result 1">
                <img src="https://i.ibb.co/zVV48XM6/paypalnovo.webp" alt="Result 2">
            </div>
            <button class="carousel-btn prev">‹</button>
            <button class="carousel-btn next">›</button>
        </div>
    </section>

    <!-- Final CTA Section -->
    <section class="final-cta">
        <div class="cta-container">
            <h2 class="cta-title">DON'T WASTE TIME!</h2>
            <p class="cta-text">Join the <span class="counter-container"><span class="counter">0</span></span> women who are already turning conversations into real earnings every day. Success is just one click away!</p>
            <a href="https://pay.hotmart.com/K98901235K?checkoutMode=10" class="buy-button">I WANT TO REGISTER NOW</a>
        </div>
    </section>

    <script>
    // Mobile carousel script
    document.addEventListener('DOMContentLoaded', function() {
        const carousel = document.querySelector('.carousel-inner');
        const prevBtn = document.querySelector('.carousel-btn.prev');
        const nextBtn = document.querySelector('.carousel-btn.next');
        const slides = document.querySelectorAll('.carousel-mobile img');
        
        if (window.innerWidth <= 768 && slides.length > 0) {
            let currentIndex = 0;
            const slideWidth = slides[0].clientWidth;
            const maxIndex = slides.length - 1;
            
            function updateCarousel() {
                carousel.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
                prevBtn.disabled = currentIndex === 0;
                nextBtn.disabled = currentIndex === maxIndex;
            }
            
            prevBtn.addEventListener('click', () => {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateCarousel();
                }
            });
            
            nextBtn.addEventListener('click', () => {
                if (currentIndex < maxIndex) {
                    currentIndex++;
                    updateCarousel();
                }
            });
            
            let startX, moveX;
            carousel.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
            }, {passive: true});
            
            carousel.addEventListener('touchend', (e) => {
                const diff = startX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0 && currentIndex < maxIndex) {
                        currentIndex++;
                    } else if (diff < 0 && currentIndex > 0) {
                        currentIndex--;
                    }
                    updateCarousel();
                }
            }, {passive: true});
            
            const gif = document.querySelector('.carousel-mobile [src="GIF.gif"]');
            if (gif) {
                gif.src = 'GIF.gif?' + Date.now();
            }
        }
    });

    // Animated counter - Improved version
    // Contador animado - Versão suave e controlada
function animateCounter() {
    const counter = document.querySelector('.counter');
    if (!counter) return;
    
    // Se já foi animado, não faz nada
    if (counter.dataset.animated === 'true') return;
    counter.dataset.animated = 'true';
    
    let current = 0;
    const target = 1548;
    
    // Fase 1: Animação inicial suave até o target
    function initialAnimation() {
        const duration = 4000; // 4 segundos para a animação inicial
        const startTime = performance.now();
        
        function update(timestamp) {
            const elapsed = timestamp - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function para suavizar
            const easeOutQuad = progress => progress * (2 - progress);
            
            current = Math.floor(easeOutQuad(progress) * target);
            counter.textContent = current.toLocaleString('en-US');
            
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                // Inicia a fase 2 quando completa
                steadyIncrement();
            }
        }
        
        requestAnimationFrame(update);
    }
    
    // Fase 2: Incrementos lentos e ocasionais
    function steadyIncrement() {
        let incrementCount = 0;
        const maxIncrements = 20; // Número máximo de incrementos adicionais
        
        function slowIncrement() {
            if (incrementCount >= maxIncrements) return;
            
            // Incrementa apenas 1 ou 2
            current += Math.random() > 0.3 ? 1 : 2;
            counter.textContent = current.toLocaleString('en-US');
            incrementCount++;
            
            // Intervalo aleatório entre 3-8 segundos
            const nextDelay = 3000 + Math.random() * 5000;
            setTimeout(slowIncrement, nextDelay);
        }
        
        // Primeiro incremento após 3 segundos
        setTimeout(slowIncrement, 3000);
    }
    
    // Inicia a animação
    initialAnimation();
}

    window.addEventListener('DOMContentLoaded', animateCounter);
    </script>
</body>
</html>
<!-- ADD THIS CODE AFTER THE EXISTING CONTACT SECTION -->
<!-- WITHOUT MODIFYING ANYTHING FROM THE PREVIOUS CODE -->

<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
<style>
        /* ESTILOS GERAIS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            color: #ffffff;
            background-color: #2a2a2a; /* Fundo cinza uniforme */
            line-height: 1.6;
        }
        
        /* Manchas rosas nas laterais */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(255, 0, 127, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 90% 30%, rgba(252, 92, 172, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 20% 70%, rgba(255, 105, 180, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 80% 80%, rgba(255, 20, 147, 0.15) 0%, transparent 20%);
            z-index: -1;
            pointer-events: none;
        }
        
        /* CONTAINER PRINCIPAL */
        .new-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }
        
        /* TÍTULOS DAS SEÇÕES */
        .new-section-title {
            font-size: 2.2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 50px;
            color: white;
            position: relative;
            padding-bottom: 15px;
        }
        
        .new-section-title::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, #ff007f, #fc5cac);
            border-radius: 3px;
        }

        /* SEÇÃO DE DEPOIMENTOS */
        .new-testimonials {
            padding: 80px 0;
            position: relative;
            background-color: rgba(40, 40, 40, 0.8);
        }

        .new-testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
            position: relative;
            z-index: 2;
        }

        .new-testimonial-card {
            background: rgba(50, 50, 50, 0.8);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 0, 127, 0.3);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .new-testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 0, 127, 0.2);
        }

        .new-testimonial-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .new-testimonial-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ff007f;
            box-shadow: 0 0 15px rgba(255, 0, 127, 0.3);
        }

        .new-testimonial-author h3 {
            color: #ff66b3;
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .new-testimonial-author p {
            color: #aaaaaa;
            font-size: 0.9rem;
        }

        .new-testimonial-content p {
            font-style: italic;
            line-height: 1.6;
            margin-bottom: 15px;
            color: #e0e0e0;
        }

        .new-rating {
            color: #00cc00;
            font-size: 1.2rem;
        }

        /* SEÇÃO DE GARANTIA - MODIFICADA */
        .new-guarantee {
            padding: 80px 0;
            position: relative;
            background-color: rgba(40, 40, 40, 0.8);
        }

        .new-guarantee-card {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(50, 50, 50, 0.8);
            border-radius: 15px;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
            border: 1px solid rgba(255, 0, 127, 0.3);
            backdrop-filter: blur(5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .new-guarantee-badge {
            width: 220px;
            height: 220px;
            flex-shrink: 0;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 0, 127, 0.5);
            box-shadow: 0 0 20px rgba(255, 0, 127, 0.3);
        }

        .new-guarantee-badge img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .new-guarantee-content h2 {
            color: white;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .new-guarantee-content p {
            color: #dddddd;
            line-height: 1.6;
        }

        /* SEÇÃO DE OFERTA */
        .new-timer {
            padding: 80px 0;
            position: relative;
            background: rgba(40, 40, 40, 0.8);
        }

        .new-price-container {
            text-align: center;
            margin: 20px 0;
        }

        .new-price-main {
            font-size: 1.5rem;
            color: white;
            margin-bottom: 5px;
        }

        .new-price-main span {
            font-size: 2.2rem;
            font-weight: bold;
            color: #00ff00;
        }

        .new-price-secondary {
            font-size: 1.1rem;
            color: #aaa;
        }

        .new-timer-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
            position: relative;
            z-index: 2;
        }

        .new-timer-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 20px;
            min-width: 100px;
            text-align: center;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .new-timer-box span {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
            line-height: 1;
            color: white;
        }

        .new-timer-box small {
            font-size: 0.9rem;
            text-transform: uppercase;
            opacity: 0.8;
            color: white;
        }

        .new-timer-button {
            display: inline-block;
            background: linear-gradient(to bottom, #00cc00, #009900);
            color: white;
            font-weight: 700;
            font-size: clamp(15px, 3vw, 17px);
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            margin: 40px auto 0;
            box-shadow: 0 4px 12px rgba(0,255,0,0.4);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
            border: none;
            cursor: pointer;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .new-timer-button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 20px rgba(0,255,0,0.6);
        }

        /* RODAPÉ */
        .new-footer {
            padding: 60px 0 30px;
            background: linear-gradient(to bottom, #222, #111);
            position: relative;
            z-index: 2;
        }

        .new-footer::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(to right, transparent, #ff007f, transparent);
        }

        .new-footer-logo {
            width: 150px;
            margin-bottom: 30px;
            filter: drop-shadow(0 0 10px rgba(255, 0, 127, 0.5));
        }

        .new-footer-links {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .new-footer-links a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 0.9rem;
        }

        .new-footer-links a:hover {
            color: #ff66b3;
        }

        .new-footer-social {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .new-footer-social a {
            display: inline-flex;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .new-footer-social img {
            width: 20px;
            height: 20px;
            filter: brightness(0) invert(1);
            opacity: 0.8;
            transition: all 0.3s ease;
        }

        .new-footer-social a:hover {
            background: #ff007f;
        }

        .new-footer-social a:hover img {
            opacity: 1;
            transform: scale(1.1);
        }

        .new-footer-copyright {
            color: #666;
            font-size: 0.9rem;
            text-align: center;
        }

        /* SEÇÃO VANTAGENS EXCLUSIVAS */
        .new-exclusive {
            padding: 80px 0;
            position: relative;
            background-color: rgba(40, 40, 40, 0.8);
        }

        .new-exclusive-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
            position: relative;
            z-index: 2;
        }

        .new-exclusive-card {
            background: rgba(50, 50, 50, 0.8);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 0, 127, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .new-exclusive-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 0, 127, 0.2);
        }

        .new-exclusive-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 0, 127, 0.1);
            border-radius: 50%;
            border: 2px solid rgba(255, 0, 127, 0.3);
        }

        .new-exclusive-icon img {
            width: 30px;
            height: 30px;
            filter: brightness(0) invert(1);
        }

        .new-exclusive-card h3 {
            color: #ff66b3;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .new-exclusive-card p {
            color: #ddd;
            line-height: 1.6;
        }
        /* SEÇÃO QUEM SOMOS */
        .new-about {
            padding: 80px 0;
            position: relative;
            background-color: rgba(40, 40, 40, 0.8);
        }

        .new-about-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .new-about-content p {
            color: #ddd;
            line-height: 1.8;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

           /* SEÇÃO FAQ - ATUALIZADA */
        .new-faq {
            padding: 80px 0;
            position: relative;
            background-color: rgba(40, 40, 40, 0.8);
        }

        .new-faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .new-faq-item {
            background: rgba(50, 50, 50, 0.8);
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 0, 127, 0.3);
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .new-faq-item:hover {
            border-color: rgba(255, 0, 127, 0.6);
            box-shadow: 0 8px 20px rgba(255, 0, 127, 0.2);
        }

        .new-faq-question {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            color: white;
            background: rgba(255, 255, 255, 0.05);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .new-faq-question:hover {
            background: rgba(255, 0, 127, 0.1);
        }

        .new-faq-question::after {
            content: '';
            width: 16px;
            height: 16px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ff66b3'%3E%3Cpath d='M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            transition: transform 0.3s ease;
        }

        .new-faq-item.active .new-faq-question::after {
            transform: rotate(180deg);
        }

        .new-faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .new-faq-item.active .new-faq-answer {
            max-height: 500px;
        }

        .new-faq-answer-inner {
            padding: 0 20px 20px;
            color: #ddd;
            line-height: 1.7;
            font-size: 0.95rem;
        }

        /* RESPONSIVIDADE - ATUALIZADA */
        @media (max-width: 768px) {
            .new-section-title {
                font-size: 1.8rem;
            }
            
            .new-testimonials, 
            .new-faq, 
            .new-steps, 
            .new-guarantee, 
            .new-about, 
            .new-timer, 
            .new-lead, 
            .new-exclusive {
                padding: 60px 0;
            }
            
            .new-testimonials-grid, 
            .new-team-grid, 
            .new-exclusive-grid {
                grid-template-columns: 1fr;
            }
            
            .new-guarantee-card {
                flex-direction: column;
                text-align: center;
                padding: 30px 20px;
            }
            
            .new-guarantee-badge {
                margin-bottom: 0px;
            }
            
            .new-price-main {
                font-size: 1.3rem;
            }
            
            .new-price-main span {
                font-size: 1.8rem;
            }
            
            .new-faq-question {
                padding: 16px;
                font-size: 0.95rem;
            }
            
            .new-faq-answer-inner {
                padding: 0 16px 16px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .new-section-title {
                font-size: 1.5rem;
            }
            
            .new-testimonials, 
            .new-faq, 
            .new-steps, 
            .new-guarantee, 
            .new-comparison, 
            .new-about, 
            .new-timer, 
            .new-lead, 
            .new-exclusive {
                padding: 50px 0;
            }
            
            .new-testimonial-card,
            .new-faq-item,
            .new-step-content,
            .new-guarantee-card,
            .new-lead-card,
            .new-exclusive-card {
                padding: 20px;
            }
            
            .new-price-main {
                font-size: 1.1rem;
            }
            
            .new-price-main span {
                font-size: 1.5rem;
            }
            
            .new-price-secondary {
                font-size: 0.9rem;
            }
            
            .new-guarantee-badge {
                width: 150px;
                height: 150px;
            }
            
            .new-faq-question {
                padding: 14px;
                font-size: 0.9rem;
            }
            
            .new-faq-question::after {
                width: 14px;
                height: 14px;
            }
            
            .new-faq-answer-inner {
                padding: 0 14px 14px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

<section class="new-testimonials">
    <div class="new-container">
        <h2 class="new-section-title">REAL TESTIMONIALS</h2>
        <div class="new-testimonials-grid">
            <div class="new-testimonial-card">
                <div class="new-testimonial-header">
                    <img src="https://i0.statig.com.br/bancodeimagens/7k/vr/vi/7kvrvigb50zdeggj4w37ygg5v.jpg" alt="Testimonial" class="new-testimonial-photo">
                    <div class="new-testimonial-author">
                        <h3>Emily</h3>
                        <p>Chatter for 3 months</p>
                    </div>
                </div>
                <div class="new-testimonial-content">
                    <p>"In just one week I was already earning more than at my old job! Love Chat changed my life."</p>
                    <div class="new-rating">★★★★★</div>
                </div>
            </div>
            
            <div class="new-testimonial-card">
                <div class="new-testimonial-header">
                    <img src="https://blog.explicae.com.br/wp-content/uploads/2022/08/2.webp" alt="Testimonial" class="new-testimonial-photo">
                    <div class="new-testimonial-author">
                        <h3>Chloe</h3>
                        <p>Chatter for 5 months</p>
                    </div>
                </div>
                <div class="new-testimonial-content">
                    <p>"I was able to pay all my debts and still have money left to travel. The support is amazing!"</p>
                    <div class="new-rating">★★★★★</div>
                </div>
            </div>
            
            <div class="new-testimonial-card">
                <div class="new-testimonial-header">
                    <img src="https://i.pinimg.com/236x/00/8f/4f/008f4fde364f2e6d729770a0c951e2c9.jpg" alt="Testimonial" class="new-testimonial-photo">
                    <div class="new-testimonial-author">
                        <h3>Madison</h3>
                        <p>Chatter for 2 months</p>
                    </div>
                </div>
                <div class="new-testimonial-content">
                    <p>"I work only 3-4 hours a day and make around $200 on average. The best opportunity I've ever found."</p>
                    <div class="new-rating">★★★★★</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Guarantee Section -->
<section class="new-guarantee">
    <div class="new-container">
        <div class="new-guarantee-card">
            <div class="new-guarantee-badge">
                <img src="https://lirp.cdn-website.com/5b700bb6/dms3rep/multi/opt/badge-de8d9fd7-640w.png" alt="100% Satisfaction">
            </div>
            <div class="new-guarantee-content">
                <h2>MONEY-BACK GUARANTEE</h2>
                <p>Recover your investment in the first 3 days of work</p>
            </div>
        </div>
    </div>
</section>

<!-- Seção Quem Somos -->
<section class="new-about">
    <div class="new-container">
        <h2 class="new-section-title">ABOUT US</h2>
        <div class="new-about-content">
            <p>Love Chat was born from the desire to create a safe and profitable platform for women who want financial freedom working from home, on their own schedule.</p>
            <p>We are a team of technology and digital marketing specialists with over 10 years of experience, committed to offering the best chat platform on the market.</p>
            <p>Our mission is to empower women through technology, providing a fair and flexible income source, with complete privacy and security.</p>
        </div>
    </div>
</section>

<section class="new-timer">
    <div class="new-container">
        <h2 class="new-section-title">LIMITED TIME OFFER!</h2>
        <p>Sign up now and get lifetime access at a special price</p>
        
        <div class="new-price-container">
            <div class="new-price-main"><span>$17.00</span></div>
            <div class="new-price-secondary">Start now!</div>
        </div>
        
        <div class="new-timer-container">
            <div class="new-timer-box">
                <span id="new-timer-hours">12</span>
                <small>Hours</small>
            </div>
            <div class="new-timer-box">
                <span id="new-timer-minutes">45</span>
                <small>Minutes</small>
            </div>
            <div class="new-timer-box">
                <span id="new-timer-seconds">30</span>
                <small>Seconds</small>
            </div>
        </div>
        
        <a href="https://pay.hotmart.com/K98901235K?checkoutMode=10" class="new-timer-button">I WANT TO REGISTER NOW</a>
    </div>
</section>

<!-- FAQ Section -->
<section class="new-faq">
    <div class="new-container">
        <h2 class="new-section-title">FREQUENTLY ASKED QUESTIONS</h2>
        
        <div class="new-faq-container">
            <div class="new-faq-item">
                <div class="new-faq-question">How does payment work at Love Chat?</div>
                <div class="new-faq-answer">
                    <p>Payments are made daily, directly to your bank account. You can withdraw your balance at any time, with no fees or minimum limits.</p>
                </div>
            </div>
            
            <div class="new-faq-item">
                <div class="new-faq-question">Do I need previous experience?</div>
                <div class="new-faq-answer">
                    <p>No experience is required. Our platform offers complete training so you can start earning money from day one.</p>
                </div>
            </div>
            
            <div class="new-faq-item">
                <div class="new-faq-question">How much time do I need to dedicate per day?</div>
                <div class="new-faq-answer">
                    <p>You set your own schedule. Some chatters work just 2-3 hours a day, while others prefer longer shifts. It all depends on your financial goals.</p>
                </div>
            </div>
            
            <div class="new-faq-item">
                <div class="new-faq-question">How is my privacy guaranteed?</div>
                <div class="new-faq-answer">
                    <p>We use advanced technology to protect your data. Your profile is completely anonymous and we don't share any personal information with third parties.</p>
                </div>
            </div>
            
            <div class="new-faq-item">
                <div class="new-faq-question">Can I use Love Chat on my phone?</div>
                <div class="new-faq-answer">
                    <p>Yes! Our extension is fully responsive and works perfectly on both smartphones and computers.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Exclusive Benefits Section -->
<section class="new-exclusive">
    <div class="new-container">
        <h2 class="new-section-title">EXCLUSIVE BENEFITS</h2>
        
        <div class="new-exclusive-grid">
            <div class="new-exclusive-card">
                <div class="new-exclusive-icon">
                    <i class="fas fa-users fa-2x"></i>
                </div>
                <h3>VIP Community</h3>
                <p>Access to an exclusive group with top chatters for networking and advanced tips</p>
            </div>
            
            <div class="new-exclusive-card">
                <div class="new-exclusive-icon">
                    <i class="fas fa-sync-alt fa-2x"></i>
                </div>
                <h3>Free Updates</h3>
                <p>All new versions and features included at no additional cost</p>
            </div>
            
            <div class="new-exclusive-card">
                <div class="new-exclusive-icon">
                    <i class="fas fa-gift fa-2x"></i>
                </div>
                <h3>Exclusive Bonuses</h3>
                <p>Extra materials and complementary training to increase your earnings</p>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="new-footer">
    <div class="new-container">
        <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat" class="new-footer-logo">
        
        <p class="new-footer-copyright">© 2025 Love Chat - All rights reserved</p>
    </div>
</footer>

<script>
// Primeiro, vamos criar o elemento loader se ele não existir
function createLoader() {
    const loader = document.createElement('div');
    loader.id = 'content-loader';
    loader.style.position = 'fixed';
    loader.style.top = '50%';
    loader.style.left = '50%';
    loader.style.transform = 'translate(-50%, -50%)';
    loader.style.color = 'white';
    loader.style.padding = '20px';
    loader.style.borderRadius = '10px';
    loader.style.zIndex = '1000';
    loader.style.fontSize = '1.2rem';
    loader.style.fontWeight = 'bold';
    loader.style.textAlign = 'center';
    document.body.appendChild(loader);
    return loader;
}

// Função principal
function initPage() {
    if(localStorage.getItem('loveChatFirstVisit') === null) {
        // Esconde todas as seções exceto o essencial (header + vídeo)
        document.querySelectorAll('.cta-button, .benefits, .whatsapp-proof, .final-cta, .new-testimonials, .new-guarantee, .new-about, .new-timer, .new-faq, .new-exclusive, .new-footer').forEach(element => {
            element.style.display = 'none';
        });

        // Cria o loader
        const loader = createLoader();
        
        // Atualiza o contador
        let secondsLeft = 180; // Alterei para 10 segundos para testes (original 150)
        const timer = setInterval(() => {
            secondsLeft--;
            const mins = Math.floor(secondsLeft / 60);
            const secs = secondsLeft % 60;
            
            if(secondsLeft <= 0) {
                clearInterval(timer);
                loader.remove();
                
                // Mostra todo o conteúdo incluindo o botão
                document.querySelectorAll('.cta-button, .benefits, .whatsapp-proof, .final-cta, .new-testimonials, .new-guarantee, .new-about, .new-timer, .new-faq, .new-exclusive, .new-footer').forEach(element => {
                    element.style.display = '';
                });
                
                // Marca que já visitou
                localStorage.setItem('loveChatFirstVisit', '1');
                
                // Ativa os scripts que precisam rodar após carregamento
                if(typeof animateCounter === 'function') animateCounter();
                setupCarousel();
            }
        }, 1000);
    } else {
        // Se não é primeira visita, garante que tudo está visível
        document.querySelectorAll('.cta-button, .benefits, .whatsapp-proof, .final-cta, .new-testimonials, .new-guarantee, .new-about, .new-timer, .new-faq, .new-exclusive, .new-footer').forEach(element => {
            element.style.display = '';
        });
        
        // Ativa os scripts normalmente
        if(typeof animateCounter === 'function') animateCounter();
        setupCarousel();
    }
}

// Chama a função quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', initPage);

// Sua função setupCarousel permanece a mesma
function setupCarousel() {
    if (window.innerWidth > 768) return;
    
    const carousel = document.querySelector('.carousel-inner');
    const prevBtn = document.querySelector('.carousel-btn.prev');
    const nextBtn = document.querySelector('.carousel-btn.next');
    const slides = document.querySelectorAll('.carousel-mobile img');
    
    if (!carousel || slides.length === 0) return;
    
    let currentIndex = 0;
    const slideWidth = slides[0].clientWidth;
    const maxIndex = slides.length - 1;
    
    function updateCarousel() {
        carousel.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
        if(prevBtn) prevBtn.disabled = currentIndex === 0;
        if(nextBtn) nextBtn.disabled = currentIndex === maxIndex;
    }
    
    if(prevBtn) prevBtn.addEventListener('click', () => {
        if (currentIndex > 0) {
            currentIndex--;
            updateCarousel();
        }
    });
    
    if(nextBtn) nextBtn.addEventListener('click', () => {
        if (currentIndex < maxIndex) {
            currentIndex++;
            updateCarousel();
        }
    });
    
    let startX;
    carousel.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
    }, {passive: true});
    
    carousel.addEventListener('touchend', (e) => {
        const diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            if (diff > 0 && currentIndex < maxIndex) {
                currentIndex++;
            } else if (diff < 0 && currentIndex > 0) {
                currentIndex--;
            }
            updateCarousel();
        }
    }, {passive: true});
    
    const gif = document.querySelector('.carousel-mobile [src="GIF.gif"]');
    if (gif) {
        gif.src = 'GIF.gif?' + Date.now();
    }
}
</script>
<script>
// FAQ Functionality
document.addEventListener('DOMContentLoaded', function() {
    const faqItems = document.querySelectorAll('.new-faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.new-faq-question');
        
        question.addEventListener('click', () => {
            // Close all other items first
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Toggle current item
            item.classList.toggle('active');
        });
    });
});

// Timer Countdown
function updateTimer() {
    const now = new Date();
    const endOfDay = new Date();
    endOfDay.setHours(23, 59, 59, 999); // Set to end of day
    
    const diff = endOfDay - now;
    
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    document.getElementById('new-timer-hours').textContent = hours.toString().padStart(2, '0');
    document.getElementById('new-timer-minutes').textContent = minutes.toString().padStart(2, '0');
    document.getElementById('new-timer-seconds').textContent = seconds.toString().padStart(2, '0');
}

// Update timer every second
setInterval(updateTimer, 1000);
updateTimer(); // Initial call
</script>
</body>
</html>