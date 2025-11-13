<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decio Games - Consoles e Jogos ao seu Alcance</title>
    <meta name="description" content="A sua loja de games favorita. Encontre os melhores consoles, jogos e acessórios com preços incríveis. PlayStation, Xbox, Nintendo e muito mais!">
    
    <!-- Google Fonts para um visual mais profissional -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* --- CONFIGURAÇÕES GLOBAIS E VARIÁVEIS DE COR --- */
        :root {
            --primary-blue: #0A74DA; /* Azul principal, vibrante */
            --dark-blue: #084B8A;   /* Azul escuro para fundos e detalhes */
            --accent-red: #E63946;    /* Vermelho para botões e alertas */
            --accent-yellow: #FFC300; /* Amarelo para destaques e preços */
            --text-light: #F1FAEE;   /* Branco/gelo para textos */
            --bg-dark: #1D3557;     /* Fundo principal azul-marinho escuro */
            --card-bg: #457B9D;     /* Cor de fundo para os cards */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            line-height: 1.6;
        }

        h1, h2, h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            color: var(--text-light);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* --- ANIMAÇÕES DE SCROLL --- */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .animate-on-scroll.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- HEADER / MENU DE NAVEGAÇÃO --- */
        .main-header {
            background-color: rgba(29, 53, 87, 0.85);
            backdrop-filter: blur(10px);
            padding: 15px 0;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .main-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--text-light);
        }
        .logo span {
            color: var(--accent-yellow);
        }

        .main-nav a {
            color: var(--text-light);
            text-decoration: none;
            margin-left: 25px;
            font-weight: 500;
            position: relative;
            transition: color 0.3s ease;
        }
        .main-nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: var(--accent-yellow);
            transition: width 0.3s ease;
        }
        .main-nav a:hover {
            color: var(--accent-yellow);
        }
        .main-nav a:hover::after {
            width: 100%;
        }

        /* --- SEÇÃO HERO (BANNER PRINCIPAL) --- */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding-top: 80px; /* Espaço para o header fixo */
            background: linear-gradient(rgba(29, 53, 87, 0.7), rgba(29, 53, 87, 0.9)), url('https://www.transparenttextures.com/patterns/cubes.png');
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 0 4px 15px rgba(0,0,0,0.4);
        }

        .hero-content p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 30px auto;
        }

        .btn {
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .btn-primary {
            background-color: var(--accent-red);
            color: var(--text-light);
            box-shadow: 0 4px 15px rgba(230, 57, 70, 0.4);
        }
        .btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(230, 57, 70, 0.6);
        }

        /* --- SEÇÕES PADRÃO --- */
        .section {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 50px;
            position: relative;
        }
        .section-title::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 4px;
            background-color: var(--accent-red);
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        /* --- SEÇÃO DE DESTAQUES (PRODUTOS) --- */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background-color: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
        }

        .product-image {
            height: 200px;
            background-color: var(--dark-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .product-info {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-info h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-yellow);
            margin: auto 0 20px 0; /* Empurra para baixo */
        }

        .btn-buy {
            background-color: var(--primary-blue);
            color: var(--text-light);
            padding: 12px 20px;
            border-radius: 8px;
            text-align: center;
        }
        .btn-buy:hover {
            background-color: var(--accent-red);
            transform: scale(1.05);
        }

        /* --- SEÇÃO PLATAFORMAS --- */
        #platforms {
            background-color: var(--dark-blue);
        }
        .platforms-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        .platform-card {
            height: 250px;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 20px;
            text-decoration: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            transition: transform 0.4s ease;
        }
        .platform-card:hover {
            transform: scale(1.05);
        }
        .platform-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent 60%);
            z-index: 1;
        }
        .platform-card h3 {
            color: var(--text-light);
            font-size: 2rem;
            z-index: 2;
            position: relative;
        }
        /* Cores específicas para cada plataforma */
        .platform-card.playstation { background-color: #0070D1; }
        .platform-card.xbox { background-color: #107C10; }
        .platform-card.nintendo { background-color: #E60012; }


        /* --- SEÇÃO DE CONTATO --- */
        .contact-form {
            max-width: 700px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid var(--card-bg);
            background-color: var(--dark-blue);
            color: var(--text-light);
            font-size: 1rem;
            font-family: 'Roboto', sans-serif;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
        }

        /* --- FOOTER --- */
        .main-footer {
            background-color: var(--dark-blue);
            padding: 40px 0;
            text-align: center;
        }
        .main-footer p {
            margin-bottom: 15px;
        }
        .social-links a {
            color: var(--text-light);
            font-size: 1.5rem;
            margin: 0 15px;
            transition: color 0.3s ease;
        }
        .social-links a:hover {
            color: var(--accent-yellow);
        }
        
        /* --- RESPONSIVIDADE --- */
        @media (max-width: 768px) {
            .logo { font-size: 1.5rem; }
            .main-nav { display: none; } /* Simplificando para o exemplo, um menu hamburguer seria ideal aqui */
            .hero-content h1 { font-size: 2.5rem; }
            .section-title { font-size: 2rem; }
            .platforms-grid { grid-template-columns: 1fr; }
        }

    </style>
</head>
<body>

    <header class="main-header">
        <div class="container">
            <a href="#" class="logo">Decio<span>Games</span></a>
            <nav class="main-nav">
                <a href="#hero">Início</a>
                <a href="#destaques">Jogos</a>
                <a href="#platforms">Consoles</a>
                <a href="#contato">Contato</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero" id="hero">
            <div class="hero-content animate-on-scroll">
                <h1>O Universo Gamer ao Seu Alcance</h1>
                <p>Consoles de última geração, os jogos mais aguardados e acessórios para levar sua gameplay a outro nível. Tudo em um só lugar.</p>
                <a href="#destaques" class="btn btn-primary">Explorar Ofertas</a>
            </div>
        </section>

        <section id="destaques" class="section">
            <div class="container">
                <h2 class="section-title animate-on-scroll">Destaques da Semana</h2>
                <div class="products-grid">
                    <!-- Produto 1 -->
                    <div class="product-card animate-on-scroll">
                        <div class="product-image">IMAGEM DO PRODUTO</div>
                        <div class="product-info">
                            <h3>PlayStation 5</h3>
                            <p>Experimente o carregamento ultrarrápido com o SSD de altíssima velocidade.</p>
                            <p class="product-price">R$ 3.999,90</p>
                            <a href="#" class="btn btn-buy">Comprar Agora</a>
                        </div>
                    </div>
                    <!-- Produto 2 -->
                    <div class="product-card animate-on-scroll">
                        <div class="product-image">IMAGEM DO PRODUTO</div>
                        <div class="product-info">
                            <h3>Xbox Series X</h3>
                            <p>O Xbox mais rápido e poderoso de todos os tempos. Jogue milhares de títulos.</p>
                            <p class="product-price">R$ 4.349,00</p>
                            <a href="#" class="btn btn-buy">Comprar Agora</a>
                        </div>
                    </div>
                    <!-- Produto 3 -->
                    <div class="product-card animate-on-scroll">
                        <div class="product-image">IMAGEM DO PRODUTO</div>
                        <div class="product-info">
                            <h3>Nintendo Switch OLED</h3>
                            <p>Jogue em casa ou em qualquer lugar com a vibrante tela OLED de 7 polegadas.</p>
                            <p class="product-price">R$ 2.499,99</p>
                            <a href="#" class="btn btn-buy">Comprar Agora</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="platforms" class="section">
            <div class="container">
                <h2 class="section-title animate-on-scroll">Navegue por Plataforma</h2>
                <div class="platforms-grid">
                    <a href="#" class="platform-card playstation animate-on-scroll"><h3>PlayStation</h3></a>
                    <a href="#" class="platform-card xbox animate-on-scroll"><h3>Xbox</h3></a>
                    <a href="#" class="platform-card nintendo animate-on-scroll"><h3>Nintendo</h3></a>
                </div>
            </div>
        </section>

        <section id="contato" class="section">
            <div class="container">
                <h2 class="section-title animate-on-scroll">Fale Conosco</h2>
                <form class="contact-form animate-on-scroll" action="#" method="post">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Seu nome completo" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Seu melhor e-mail" required>
                    </div>
                    <div class="form-group">
                        <textarea name="message" placeholder="Sua mensagem..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-submit">Enviar Mensagem</button>
                </form>
            </div>
        </section>

    </main>

    <footer class="main-footer">
        <div class="container">
            <p>DecioGames © <?php echo date("Y"); ?> - Todos os direitos reservados.</p>
            <p>Este é um site de portfólio. Os produtos e preços são fictícios.</p>
            <div class="social-links">
                <!-- Substituir # pelos links reais. Ícones podem ser adicionados com FontAwesome ou SVGs -->
                <a href="#" aria-label="Instagram">IG</a>
                <a href="#" aria-label="Facebook">FB</a>
                <a href="#" aria-label="Twitter">TW</a>
            </div>
        </div>
    </footer>

    <script>
    // --- SCRIPT PARA ANIMAÇÃO AO ROLAR A PÁGINA ---
    document.addEventListener('DOMContentLoaded', function() {
        const targets = document.querySelectorAll('.animate-on-scroll');

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                // Se o elemento está visível na tela
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    // Opcional: para de observar o elemento depois que a animação ocorreu
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '0px',
            threshold: 0.1 // A animação começa quando 10% do elemento estiver visível
        });

        // Observa cada um dos elementos alvo
        targets.forEach(target => {
            observer.observe(target);
        });
    });
    </script>

</body>
</html>