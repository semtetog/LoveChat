<?php
// Configurações e variáveis do site
$site_title = "River Digital — Soluções que fluem com o seu sucesso";
$site_description = "Na River Digital, transformamos ideias em realidade digital. Somos especialistas em desenvolvimento de aplicativos, web design de ponta e estratégias de marketing que geram resultados concretos.";
$site_url = "https://riverdigital.com.br"; // Substitua pela sua URL final
$logo_url = "https://cdn.jsdelivr.net/gh/semtetog/river@main/riverlogo.webp";

$services = [
    [
        "icon" => "fa-mobile-alt",
        "title" => "Desenvolvimento de Apps",
        "description" => "Criamos aplicativos nativos e híbridos para iOS e Android, focados em usabilidade e performance, para qualquer nicho de mercado."
    ],
    [
        "icon" => "fa-laptop-code",
        "title" => "Sites e Plataformas Web",
        "description" => "Desenvolvemos de sites institucionais a e-commerces complexos, sempre responsivos, otimizados para SEO e com design impecável."
    ],
    [
        "icon" => "fa-bullhorn",
        "title" => "Estratégia e Marketing Digital",
        "description" => "Planejamos e executamos campanhas de marketing, gestão de tráfego e SEO para aumentar sua visibilidade, leads e vendas."
    ],
    [
        "icon" => "fa-palette",
        "title" => "Branding e Identidade Visual",
        "description" => "Construímos marcas fortes e memoráveis, do conceito à criação de logos e manuais de marca que contam a sua história."
    ]
];

// Mock de dados para o portfólio
$portfolio_items = [
    ["img" => "https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2070&auto=format&fit=crop", "title" => "App de Análise de Dados", "category" => "app", "desc" => "Plataforma SaaS inovadora"],
    ["img" => "https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?q=80&w=2070&auto=format&fit=crop", "title" => "E-commerce de Moda", "category" => "web", "desc" => "Loja virtual com alta conversão"],
    ["img" => "https://images.unsplash.com/photo-1558655146-364adaf1fcc9?q=80&w=1964&auto=format&fit=crop", "title" => "Branding para Café", "category" => "branding", "desc" => "Identidade visual completa"],
    ["img" => "https://images.unsplash.com/photo-1516321497487-e288fb19713f?q=80&w=2070&auto=format&fit=crop", "title" => "Plataforma Educacional", "category" => "web", "desc" => "Sistema de cursos online"],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    <meta name="description" content="<?php echo $site_description; ?>">
    
    <!-- SEO e Social Media Tags -->
    <link rel="canonical" href="<?php echo $site_url; ?>">
    <link rel="icon" type="image/webp" href="<?php echo $logo_url; ?>">
    <meta property="og:title" content="<?php echo $site_title; ?>">
    <meta property="og:description" content="<?php echo $site_description; ?>">
    <meta property="og:image" content="<?php echo $logo_url; ?>">
    <meta property="og:url" content="<?php echo $site_url; ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Google Fonts: Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome (para ícones) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        :root {
            --primary-blue: #0d47a1;
            --secondary-teal: #009688;
            --accent-gradient: linear-gradient(135deg, #009688, #0d47a1);
            --dark-navy: #0a192f;
            --light-slate: #ccd6f6;
            --slate: #8892b0;
            --white: #ffffff;
            --background-light: #f8f9fa;
            --background-dark: #0a192f;
            --shadow-light: 0 10px 30px -15px rgba(2, 12, 27, 0.7);
            --shadow-heavy: 0 20px 40px -15px rgba(2, 12, 27, 0.8);
            --border-radius: 8px;
            --transition: all 0.25s cubic-bezier(0.645, 0.045, 0.355, 1);
            --font-family: 'Montserrat', sans-serif;
            --container-width: 1140px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--background-dark);
            color: var(--slate);
            line-height: 1.6;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container {
            width: 100%;
            max-width: var(--container-width);
            margin: 0 auto;
            padding: 0 2rem;
        }

        h1, h2, h3, h4 {
            color: var(--light-slate);
            font-weight: 700;
        }

        h1 { font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1.1; }
        h2 { font-size: clamp(2rem, 4vw, 2.5rem); text-align: center; margin-bottom: 4rem; }
        h3 { font-size: 1.5rem; }

        section {
            padding: 100px 0;
            overflow: hidden;
        }
        
        /* Botão Principal */
        .btn {
            display: inline-block;
            padding: 14px 32px;
            font-family: var(--font-family);
            font-size: 1rem;
            font-weight: 600;
            border-radius: var(--border-radius);
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            border: 2px solid var(--secondary-teal);
            transition: var(--transition);
        }

        .btn-primary {
            background-image: var(--accent-gradient);
            background-size: 200% auto;
            color: var(--white);
            border: none;
            padding: 16px 34px;
        }
        
        .btn-primary:hover {
            background-position: right center;
            transform: translateY(-3px);
            box-shadow: var(--shadow-heavy);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--secondary-teal);
        }

        .btn-outline:hover {
            background-color: rgba(0, 150, 136, 0.1);
            transform: translateY(-3px);
        }
        
        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            padding: 1rem 0;
            transition: var(--transition);
        }

        .header.scrolled {
            background-color: rgba(10, 25, 47, 0.85);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px -10px rgba(2, 12, 27, 0.7);
            padding: 0.75rem 0;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo img {
            height: 50px;
            transition: transform 0.3s ease;
        }
        .logo:hover img {
            transform: scale(1.05);
        }

        .nav-menu { list-style: none; display: flex; align-items: center; }
        .nav-item { margin-left: 2rem; }
        .nav-link { 
            color: var(--light-slate); 
            text-decoration: none; 
            font-weight: 500;
            position: relative;
            padding: 5px 0;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 2px;
            background: var(--accent-gradient);
            transition: var(--transition);
        }
        .nav-link:hover, .nav-link.active { color: var(--secondary-teal); }
        .nav-link:hover::after { width: 100%; }

        .hamburger { display: none; } /* Será implementado na media query */

        /* Hero */
        .hero {
            position: relative;
            height: 100vh;
            display: flex;
            align-items: center;
            padding: 0;
            min-height: 600px;
        }

        .hero-video {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: translate(-50%, -50%);
            z-index: -2;
        }

        .hero-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(10, 25, 47, 1) 0%, rgba(10, 25, 47, 0.6) 60%, rgba(10, 25, 47, 0.4) 100%);
            z-index: -1;
        }

        .hero-content { color: var(--white); max-width: 800px; }
        .hero-content p { color: var(--light-slate); font-size: 1.25rem; margin: 1.5rem 0 2.5rem; max-width: 600px; }
        .hero-content .highlight { color: var(--secondary-teal); }
        .hero-btns { display: flex; gap: 1rem; }

        /* Serviços */
        #services { background-color: #112240; }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        .service-card {
            background-color: var(--dark-navy);
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            border: 1px solid #1d2d44;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
        }
        .service-card:hover {
            transform: translateY(-10px);
            border-color: var(--secondary-teal);
            box-shadow: 0 10px 30px -15px var(--secondary-teal);
        }
        .service-icon { font-size: 3rem; margin-bottom: 1.5rem; background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .service-card h3 { margin-bottom: 1rem; }

        /* Sobre Nós */
        .about-content {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 50px;
            align-items: center;
        }
        .about-text p { margin-bottom: 1.5rem; }
        .about-skills { list-style: none; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .about-skills li { position: relative; padding-left: 20px; }
        .about-skills li::before { content: '▹'; position: absolute; left: 0; color: var(--secondary-teal); }
        .about-image {
            position: relative;
            max-width: 300px;
            margin: auto;
        }
        .about-image::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid var(--secondary-teal);
            top: 20px;
            left: 20px;
            border-radius: var(--border-radius);
            z-index: -1;
            transition: var(--transition);
        }
        .about-image:hover::after {
            top: 15px;
            left: 15px;
        }
        .about-image img {
            width: 100%;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-heavy);
        }
        
        /* Portfólio */
        #portfolio { background-color: #112240; }
        .portfolio-filter { display: flex; justify-content: center; gap: 1rem; margin-bottom: 3rem; flex-wrap: wrap; }
        .filter-btn { background: none; border: none; color: var(--slate); font-family: var(--font-family); font-size: 1rem; font-weight: 500; cursor: pointer; padding: 0.5rem 1rem; border-radius: 30px; transition: var(--transition); }
        .filter-btn.active, .filter-btn:hover { color: var(--white); background-color: var(--secondary-teal); }
        
        .portfolio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem; }
        .portfolio-item {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            background-color: var(--dark-navy);
        }
        .portfolio-item:hover { transform: translateY(-5px); box-shadow: var(--shadow-heavy); }
        .portfolio-img { display: block; width: 100%; height: 250px; object-fit: cover; }
        .portfolio-content {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 1.5rem;
            background: linear-gradient(to top, rgba(10, 25, 47, 1) 20%, rgba(10, 25, 47, 0));
        }
        .portfolio-content h3 { font-size: 1.25rem; }
        .portfolio-content p { font-size: 0.9rem; margin-bottom: 0; }

        /* Contato */
        .contact-container { text-align: center; max-width: 700px; margin: auto; }
        .contact-container p { font-size: 1.1rem; margin-bottom: 3rem; }
        .contact-form { display: grid; gap: 1.5rem; }
        .form-group { position: relative; }
        .contact-form input, .contact-form textarea {
            width: 100%;
            padding: 1rem;
            background-color: #112240;
            border: 1px solid var(--slate);
            border-radius: var(--border-radius);
            color: var(--light-slate);
            font-family: var(--font-family);
            font-size: 1rem;
            transition: var(--transition);
        }
        .contact-form input:focus, .contact-form textarea:focus {
            outline: none;
            border-color: var(--secondary-teal);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.2);
        }
        .contact-form textarea { resize: vertical; min-height: 150px; }

        /* Footer */
        footer {
            background-color: #05101c;
            padding: 4rem 0 2rem;
            text-align: center;
        }
        .footer-logo img { height: 60px; margin-bottom: 1.5rem; }
        .footer-socials { display: flex; justify-content: center; gap: 1.5rem; list-style: none; margin-bottom: 1.5rem; }
        .footer-socials a { color: var(--slate); font-size: 1.5rem; transition: var(--transition); }
        .footer-socials a:hover { color: var(--secondary-teal); transform: translateY(-3px); }
        .footer-copyright { color: var(--slate); font-size: 0.9rem; }

        /* Animações de Scroll */
        .reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            h1 { font-size: 2.5rem; }
            h2 { font-size: 1.8rem; }
            section { padding: 80px 0; }

            .nav-menu {
                position: fixed;
                top: 0;
                right: -100%;
                width: min(75vw, 400px);
                height: 100vh;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                background-color: #112240;
                box-shadow: -10px 0px 30px -15px #020c1b;
                transition: var(--transition);
            }
            .nav-menu.active { right: 0; }
            .nav-item { margin: 1.5rem 0; }
            .nav-link { font-size: 1.2rem; }

            .hamburger {
                display: flex;
                flex-direction: column;
                justify-content: space-around;
                width: 30px;
                height: 25px;
                cursor: pointer;
                z-index: 1001;
                background: none;
                border: none;
            }
            .hamburger .bar {
                width: 100%;
                height: 3px;
                background-color: var(--light-slate);
                border-radius: 5px;
                transition: all 0.3s ease-in-out;
            }
            .hamburger.active .bar:nth-child(1) { transform: translateY(11px) rotate(45deg); }
            .hamburger.active .bar:nth-child(2) { opacity: 0; }
            .hamburger.active .bar:nth-child(3) { transform: translateY(-11px) rotate(-45deg); }
            
            .hero-btns { flex-direction: column; align-items: center; }
            .btn { width: 100%; max-width: 300px; }

            .about-content { grid-template-columns: 1fr; gap: 40px; }
            .about-image { order: -1; max-width: 250px; }
        }

    </style>
</head>
<body>

    <header class="header" id="header">
        <div class="container">
            <nav class="navbar">
                <a href="#hero" class="logo">
                    <img src="<?php echo $logo_url; ?>" alt="Logo River Digital">
                </a>
                <ul class="nav-menu" id="nav-menu">
                    <li class="nav-item"><a href="#services" class="nav-link">Serviços</a></li>
                    <li class="nav-item"><a href="#about" class="nav-link">Sobre</a></li>
                    <li class="nav-item"><a href="#portfolio" class="nav-link">Portfólio</a></li>
                    <li class="nav-item"><a href="#contact" class="nav-link">Contato</a></li>
                </ul>
                <button class="hamburger" id="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </button>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero" id="hero">
            <video class="hero-video" autoplay loop muted playsinline>
                <source src="assets/videos/meu-video-rio.mp4" type="video/mp4">
                Seu navegador não suporta o formato de vídeo.
            </video>
            <div class="hero-overlay"></div>
            <div class="container">
                <div class="hero-content reveal">
                    <h1>Soluções Digitais que <span class="highlight">Fluem</span></h1>
                    <p><?php echo $site_description; ?></p>
                    <div class="hero-btns">
                        <a href="#contact" class="btn btn-primary">Iniciar um Projeto</a>
                        <a href="#portfolio" class="btn btn-outline">Ver Trabalhos</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="services">
            <div class="container">
                <h2 class="reveal">Nossos Serviços</h2>
                <div class="services-grid">
                    <?php foreach ($services as $index => $service): ?>
                    <div class="service-card reveal" style="transition-delay: <?php echo $index * 100; ?>ms">
                        <div class="service-icon"><i class="fas <?php echo $service['icon']; ?>"></i></div>
                        <h3><?php echo $service['title']; ?></h3>
                        <p><?php echo $service['description']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="about">
            <div class="container">
                <div class="about-content">
                    <div class="about-text reveal">
                        <h2>Unindo Criatividade e Código</h2>
                        <p>A River Digital nasceu da confluência de duas paixões: design e estratégia. Um de nós mergulha no universo da criação visual e do desenvolvimento, enquanto o outro navega pelas correntes do marketing e das estratégias de venda. </p>
                        <p>Essa sinergia nos permite oferecer soluções completas e coesas, onde cada pixel e cada campanha são projetados para o máximo impacto.</p>
                        <ul class="about-skills">
                            <li>Web & Mobile Development</li>
                            <li>Branding & Identidade Visual</li>
                            <li>UI/UX Design</li>
                            <li>SEO & Tráfego Pago</li>
                        </ul>
                    </div>
                    <div class="about-image reveal" style="transition-delay: 200ms">
                        <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2070&auto=format&fit=crop" alt="Equipe River Digital">
                    </div>
                </div>
            </div>
        </section>

        <section id="portfolio">
            <div class="container">
                <h2 class="reveal">Nosso Portfólio</h2>
                <div class="portfolio-filter reveal">
                    <button class="filter-btn active" data-filter="all">Todos</button>
                    <button class="filter-btn" data-filter="web">Web</button>
                    <button class="filter-btn" data-filter="app">Apps</button>
                    <button class="filter-btn" data-filter="branding">Branding</button>
                </div>
                <div class="portfolio-grid">
                    <?php foreach ($portfolio_items as $index => $item): ?>
                    <a href="#" class="portfolio-item reveal" data-category="<?php echo $item['category']; ?>" style="transition-delay: <?php echo ($index % 2) * 100; ?>ms">
                        <img src="<?php echo $item['img']; ?>" alt="<?php echo $item['title']; ?>" class="portfolio-img">
                        <div class="portfolio-content">
                            <h3><?php echo $item['title']; ?></h3>
                            <p><?php echo $item['desc']; ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="contact">
            <div class="container">
                <div class="contact-container reveal">
                    <h2>Vamos Conversar?</h2>
                    <p>Tem uma ideia ou um projeto em mente? Adoraríamos ouvir sobre isso. Preencha o formulário e nossa equipe entrará em contato o mais breve possível.</p>
                    <form action="enviar_email.php" method="POST" class="contact-form">
                        <div class="form-group">
                            <input type="text" id="name" name="name" placeholder="Seu Nome" required>
                        </div>
                        <div class="form-group">
                            <input type="email" id="email" name="email" placeholder="Seu E-mail" required>
                        </div>
                        <div class="form-group">
                            <textarea id="message" name="message" rows="5" placeholder="Sua Mensagem" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin: auto; width: 100%; max-width: 300px;">Enviar Mensagem</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <a href="#hero" class="footer-logo">
                <img src="<?php echo $logo_url; ?>" alt="Logo River Digital">
            </a>
            <ul class="footer-socials">
                <li><a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a></li>
                <li><a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a></li>
                <li><a href="#" aria-label="GitHub"><i class="fab fa-github"></i></a></li>
                <li><a href="#" aria-label="Dribbble"><i class="fab fa-dribbble"></i></a></li>
            </ul>
            <p class="footer-copyright">© <?php echo date("Y"); ?> River Digital. Desenvolvido com paixão e código.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('header');
            const hamburger = document.getElementById('hamburger');
            const navMenu = document.getElementById('nav-menu');

            // Header com efeito de scroll
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            // Menu Hamburger
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navMenu.classList.toggle('active');
            });

            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    navMenu.classList.remove('active');
                });
            });

            // Filtro do Portfólio
            const filterButtons = document.querySelectorAll('.filter-btn');
            const portfolioItems = document.querySelectorAll('.portfolio-item');

            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');

                    const filter = button.dataset.filter;

                    portfolioItems.forEach(item => {
                        if (filter === 'all' || item.dataset.category === filter) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            // Animação de revelação no Scroll
            const revealElements = document.querySelectorAll('.reveal');
            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });

            revealElements.forEach(el => revealObserver.observe(el));
        });
    </script>
</body>
</html>