<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bianca Azevedo - Psicóloga Infantil, Adolescentes e Adultos</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Favicon Links -->
    <link rel="icon" href="https://i.ibb.co/HDKh5Czc/BIANCA-LOGO-1.webp" type="image/webp">
    <link rel="shortcut icon" href="https://i.ibb.co/HDKh5Czc/BIANCA-LOGO-1.webp">
    <link rel="apple-touch-icon" href="https://i.ibb.co/HDKh5Czc/BIANCA-LOGO-1.webp">


    <style>
         /* --- CSS Reset Básico --- */
         * { margin: 0; padding: 0; box-sizing: border-box; }
         html { scroll-behavior: smooth; }

         /* --- PALETA DE CORES REFINADA V3 + Azul Escuro --- */
         :root {
             /* === Cores Base Escuras === */
             --color-bg-deep-space: #0a0415;
             --color-bg-midnight-blue: #0d103a;
             --color-bg-night-sky-dark: #1a0a2e;
             --color-bg-night-sky-medium: #2a1a3f;
             --color-bg-indigo-dark: #3a0e7a;

             /* === Roxos e Índigos === */
             --color-indigo-medium: #5a1ac9;
             --color-purple-medium: #7649E5;
             --color-purple-soft: #8f5fea;

             /* === Lavandas e Rosas Suaves === */
             --color-lavender-bright: #b592f1;
             --color-lavender-pale: #d8ccf1;
             --color-pink-soft: #f1a6ff;
             --color-pink-medium: #e88afc;

             /* === Rosa Vibrante para Destaques === */
             --color-pink-vibrant: #EA2FFD;

             /* === Cores de Texto e Neutros === */
             --color-text-header: #ffffff;
             --color-text-primary: #f0e8ff;
             --color-text-secondary: #d0bfe8;
             --color-moon-glow: rgba(253, 244, 161, 0.6);

             /* === RGBA para Fundos Translúcidos === */
             --color-bg-overlay-dark-rgba: rgba(26, 10, 46, 0.90);
             --color-bg-overlay-medium-rgba: rgba(42, 26, 63, 0.92);
             --color-bg-overlay-indigo-rgba: rgba(58, 14, 122, 0.90);
             --color-bg-overlay-purple-rgba: rgba(118, 73, 229, 0.89);
             --color-bg-overlay-blue-rgba: rgba(13, 16, 58, 0.90);

             /* === GRADIENTES REFINADOS V3.2 (com azul) === */
             --gradient-header: linear-gradient(100deg, rgba(13, 16, 58, 0.95) 0%, rgba(58, 14, 122, 0.93) 55%, rgba(118, 73, 229, 0.91) 100%);
             --gradient-button-cta: linear-gradient(90deg, var(--color-pink-vibrant), var(--color-purple-medium));
             --gradient-section-about: linear-gradient(145deg, var(--color-bg-overlay-indigo-rgba), var(--color-bg-overlay-purple-rgba));
             --gradient-section-contact: linear-gradient(145deg, var(--color-bg-overlay-indigo-rgba) , var(--color-bg-overlay-dark-rgba));
             --gradient-card-service: linear-gradient(160deg, var(--color-bg-overlay-medium-rgba), var(--color-bg-overlay-indigo-rgba));
             --gradient-card-service-hover: linear-gradient(160deg, rgba(90, 26, 201, 0.95), rgba(118, 73, 229, 0.97));
             --gradient-card-border: linear-gradient(to right, var(--color-pink-medium), var(--color-lavender-bright));
             --gradient-footer: linear-gradient(to top, rgba(13, 16, 58, 0.96), rgba(26, 10, 46, 0.92));
         }

         body {
             font-family: 'Montserrat', sans-serif;
             line-height: 1.75;
             color: var(--color-text-primary);
             background-color: var(--color-bg-deep-space);
             overflow-x: hidden;
             position: relative;
         }

         /* --- Fundo Estrelado Fixo (com Azul e Imagem Lua) --- */
         #starry-sky {
             position: fixed; top: 0; left: 0; width: 100%; height: 100%;
             z-index: -1;
             background: linear-gradient(to bottom, var(--color-bg-midnight-blue) 0%, var(--color-bg-night-sky-dark) 70%, var(--color-bg-deep-space) 100%);
             overflow: hidden;
         }
         .star {
             position: absolute; background-color: #ffffff; border-radius: 50%;
             box-shadow: 0 0 6px rgba(255, 255, 255, 0.7), 0 0 9px rgba(255, 255, 255, 0.5);
             animation: twinkle 5s infinite alternate ease-in-out;
         }
         @keyframes twinkle {
             0%   { opacity: 0.2; transform: scale(0.7); }
             25%  { opacity: 1.0; transform: scale(1.0); }
             50%  { opacity: 0.3; transform: scale(0.8); }
             75%  { opacity: 0.9; transform: scale(1.1); }
             100% { opacity: 0.25; transform: scale(0.75); }
         }
         /* Lua como Imagem */
         #starry-sky::after {
             content: ''; position: absolute;
             top: 14%; /* <<< LUA MAIS PARA BAIXO <<< */
             right: 10%;
             width: 80px; height: 80px;
             background-image: url('https://i.ibb.co/9QcdKq3/lua.png');
             background-size: contain; background-repeat: no-repeat;
             opacity: 0.9;
             filter: drop-shadow(0 0 15px var(--color-moon-glow)) drop-shadow(0 0 25px var(--color-moon-glow));
             pointer-events: none;
         }

         /* --- Estilos Gerais --- */
         .container { max-width: 1140px; margin: 0 auto; padding: 0 25px; }

         /* --- Estilos BASE para TODAS as seções --- */
         section {
             padding: 80px 0; margin-bottom: 55px;
             position: relative; z-index: 1; overflow: visible;
         }

         /* --- Estilos para seções QUE TÊM FUNDO --- */
         #sobre, #contato { /* Depoimentos REMOVIDO daqui */
             border-radius: 35px;
         }
         #sobre {
             background: var(--gradient-section-about);
             box-shadow: 0 0 45px rgba(118, 73, 229, 0.15), 0 0 28px rgba(16, 5, 26, 0.45);
         }
         #contato {
             background: var(--gradient-section-contact);
             box-shadow: 0 0 45px rgba(58, 14, 122, 0.18), 0 0 28px rgba(16, 5, 26, 0.5);
         }

         /* --- Estilos para seções SEM FUNDO --- */
         #hero, #servicos, #galeria, #depoimentos { /* Depoimentos ADICIONADO aqui */
             background: none; box-shadow: none; border-radius: 0;
         }
         #hero::before { display: none; }
         #hero { padding: 140px 0 120px 0; }


         h1, h2, h3 { margin-bottom: 25px; font-weight: 700; }
         h1 { /* Hero Title Desktop */
             font-size: 3.5rem; color: var(--color-text-header);
             text-shadow: 3px 3px 12px rgba(0, 0, 0, 0.8), 0 0 20px rgba(0, 0, 0, 0.65);
             line-height: 1.2;
         }
         h2 { /* Títulos de Seção */
             font-size: 2.6rem; text-align: center; margin-bottom: 65px;
             color: var(--color-lavender-bright);
             text-shadow: 0 0 15px rgba(181, 146, 241, 0.55);
         }
         /* Sombra extra para títulos em seções sem fundo */
         #servicos h2, #galeria h2, #depoimentos h2 {
              text-shadow: 1px 1px 8px rgba(0, 0, 0, 0.75), 0 0 18px rgba(181, 146, 241, 0.55);
         }
         #depoimentos h2 { color: var(--color-pink-medium); text-shadow: 0 0 15px rgba(232, 138, 252, 0.5); }

         h3 { /* Títulos menores (cards, etc) */
             font-size: 1.75rem; color: var(--color-lavender-bright);
             margin-bottom: 20px;
             display: flex; align-items: center;
         }
         p { color: var(--color-text-secondary); font-size: 1.1rem; line-height: 1.8;}
         #hero p { /* Hero Parágrafo Desktop */
             font-size: 1.5rem; margin-bottom: 50px; max-width: 780px; margin-left: auto; margin-right: auto;
             color: var(--color-text-primary);
             text-shadow: 1px 1px 7px rgba(0, 0, 0, 0.75);
         }

         /* --- Header --- */
         header {
             background: var(--gradient-header); padding: 20px 0;
             position: sticky; top: 0; z-index: 101;
             box-shadow: 0 7px 28px rgba(10, 4, 21, 0.8), 0 0 20px rgba(118, 73, 229, 0.1) inset;
             border-bottom: 1px solid rgba(118, 73, 229, 0.25);
         }
         header .container { display: flex; justify-content: space-between; align-items: center; }
         .logo img { max-height: 65px; transition: transform 0.3s ease, filter 0.3s ease; }
         .logo img:hover {
             transform: scale(1.05);
             filter: drop-shadow(0 0 12px var(--color-purple-soft)) drop-shadow(0 0 8px var(--color-pink-medium));
         }

        /* --- Navegação Desktop --- */
         nav { z-index: 100; position: relative;}
         nav ul { list-style: none; display: flex; }
         nav ul li { margin-left: 42px; }
         nav ul li a {
             text-decoration: none; color: var(--color-text-secondary);
             font-weight: bold; font-size: 1.05rem; padding-bottom: 10px; position: relative;
             transition: color 0.3s ease;
         }
         nav ul li a::after {
              content: ''; position: absolute; width: 0; height: 3px; bottom: 0; left: 50%;
              transform: translateX(-50%); background: var(--gradient-button-cta);
              transition: width 0.4s ease; border-radius: 2px;
         }
         nav ul li a:hover, nav ul li a.active { color: var(--color-text-header); }
         nav ul li a:hover::after { width: 100%; }

         /* --- Botão Menu Mobile --- */
         .menu-toggle {
             display: none; background: none; border: none; font-size: 1.9rem;
             cursor: pointer; color: var(--color-text-header); padding: 5px;
             z-index: 110;
         }

         /* --- Hero Section --- */
         #hero .container { position: relative; z-index: 2; text-align: center;}
         .cta-button {
             display: inline-flex; align-items: center; background: var(--gradient-button-cta);
             color: var(--color-text-header); padding: 18px 45px;
             border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 1.25rem;
             transition: transform 0.3s ease, box-shadow 0.3s ease;
             box-shadow: 0 8px 30px rgba(234, 47, 253, 0.4), 0 0 20px rgba(118, 73, 229, 0.25);
             border: none; cursor: pointer;
         }
         .cta-button:hover {
             transform: translateY(-6px) scale(1.05);
             box-shadow: 0 12px 35px rgba(234, 47, 253, 0.5), 0 0 25px rgba(118, 73, 229, 0.35);
         }
         .cta-button i.fab.fa-whatsapp {
             margin-right: 12px; color: var(--color-text-header) !important; font-size: 1.2em;
          }

         /* --- Sobre Section --- */
         #sobre .container { display: flex; align-items: center; gap: 70px; }
         #sobre .about-content { flex: 1; }
         #sobre .about-image img {
             max-width: 320px; border-radius: 50%; border: 10px solid var(--color-purple-soft);
             box-shadow: 0 0 40px rgba(143, 95, 234, 0.5), 0 0 25px rgba(143, 95, 234, 0.3);
             transition: transform 0.4s ease, box-shadow 0.4s ease;
         }
         #sobre .about-image img:hover {
              transform: rotate(4deg) scale(1.04);
              box-shadow: 0 0 55px rgba(143, 95, 234, 0.6), 0 0 35px rgba(143, 95, 234, 0.4);
         }
         #sobre h2 { text-align: left; margin-bottom: 30px; color: var(--color-lavender-bright);}
         #sobre p { margin-bottom: 20px; font-size: 1.15rem; }

         /* --- Serviços Section --- */
         .service-grid {
             display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
             gap: 50px;
         }
         .service-item {
             background: var(--gradient-card-service); padding: 40px 35px;
             border-radius: 25px;
             box-shadow: 0 12px 38px rgba(10, 5, 18, 0.7), 0 0 20px rgba(58, 14, 122, 0.2);
             transition: transform 0.35s ease, box-shadow 0.35s ease, background 0.35s ease;
             position: relative; overflow: hidden;
         }
         .service-item::before {
             content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px;
             background: var(--gradient-card-border);
             border-top-left-radius: 25px; border-top-right-radius: 25px; z-index: 1;
         }
         .service-item:hover {
             transform: translateY(-12px); background: var(--gradient-card-service-hover);
             box-shadow: 0 22px 50px rgba(10, 5, 18, 0.8), 0 0 35px rgba(118, 73, 229, 0.25);
         }
         .service-item h3 { margin-bottom: 18px; color: var(--color-lavender-bright); position: relative; z-index: 2; }
         .service-item p { position: relative; z-index: 2; font-size: 1.05rem; color: var(--color-text-secondary); }
         .service-item h3 i {
             margin-right: 18px; font-size: 1.8rem; color: var(--color-pink-medium);
             filter: drop-shadow(0 0 9px var(--color-pink-medium));
             width: 35px; text-align: center; flex-shrink: 0;
             transition: color 0.3s ease, filter 0.3s ease, transform 0.3s ease;
         }
          .service-item:hover h3 i {
             color: var(--color-pink-soft); filter: drop-shadow(0 0 14px var(--color-pink-soft));
             transform: scale(1.1) rotate(-5deg);
         }

         /* --- Galeria Section (Reposicionada) --- */
         #galeria { min-height: 1px; }
         .swiper { width: 100%; padding-top: 30px; padding-bottom: 80px; }
         .swiper-slide {
             background-position: center; background-size: cover; width: 340px; height: 440px;
             background-color: var(--color-bg-overlay-medium-rgba); border-radius: 25px; overflow: hidden;
             box-shadow: 0 12px 35px rgba(10, 5, 18, 0.7), 0 0 20px rgba(143, 95, 234, 0.18);
             display: flex; justify-content: center; align-items: center;
             transition: transform 0.4s ease, box-shadow 0.4s ease;
         }
         .swiper-slide-active:hover {
              transform: scale(1.05); box-shadow: 0 18px 45px rgba(10, 5, 18, 0.8), 0 0 30px rgba(143, 95, 234, 0.28);
         }
         .swiper-slide img { display: block; width: 100%; height: 100%; object-fit: cover; }

         /* Swiper Controls - Paleta V3 */
         .swiper-pagination-bullet {
              background-color: var(--color-purple-soft); opacity: 0.6; width: 13px; height: 13px;
              transition: background-color 0.3s ease, transform 0.3s ease, opacity 0.3s ease;
         }
         .swiper-pagination-bullet-active { background-color: var(--color-pink-vibrant); opacity: 1; transform: scale(1.25); }
         .swiper-button-next, .swiper-button-prev {
             color: var(--color-pink-vibrant); background-color: rgba(26, 10, 46, 0.8);
             border-radius: 50%; width: 50px; height: 50px; --swiper-navigation-size: 26px;
             transition: background-color 0.3s ease, color 0.3s ease, transform 0.3s ease;
              box-shadow: 0 3px 10px rgba(0,0,0,0.5);
         }
         .swiper-button-next:hover, .swiper-button-prev:hover {
              background-color: rgba(42, 26, 63, 0.95); color: var(--color-text-header); transform: scale(1.1);
         }
          .swiper-button-prev { left: 10px; }
          .swiper-button-next { right: 10px; }
          .swiper-coverflow .swiper-slide-shadow-cover, .swiper-coverflow .swiper-slide-shadow-left, .swiper-coverflow .swiper-slide-shadow-right { background-image: none !important; }

         /* --- Seção Depoimentos (SEM Fundo) --- */
         #depoimentos .container { max-width: 900px; }
         /* #depoimentos h2 { color: var(--color-pink-medium); } */ /* Cor já definida acima */
         .testimonial-grid {
             display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
             gap: 35px;
         }
         .testimonial-item {
             background: var(--gradient-card-service); /* Reutiliza gradiente */
             padding: 30px 35px; border-radius: 20px;
             box-shadow: 0 10px 30px rgba(10, 5, 18, 0.65), 0 0 15px rgba(58, 14, 122, 0.15);
             position: relative;
             border-top: 4px solid transparent; border-image-slice: 1;
             border-image-source: linear-gradient(to right, var(--color-purple-soft), var(--color-pink-medium));
         }
         .testimonial-item::before {
             content: '\f10d'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
             position: absolute; top: 25px; left: 25px; font-size: 2rem;
             color: var(--color-purple-medium); opacity: 0.1; z-index: 0;
         }
         .testimonial-item blockquote {
             font-style: italic; margin-bottom: 20px; color: var(--color-text-primary);
             position: relative; z-index: 1; font-size: 1.05rem; padding-left: 10px;
         }
         .testimonial-item cite {
             display: block; text-align: right; font-weight: bold;
             color: var(--color-lavender-bright); font-style: normal; position: relative; z-index: 1;
             margin-top: 15px;
         }

         /* --- Contato Section --- */
         #contato h2 { margin-bottom: 40px; color: var(--color-lavender-bright); }
         #contato > .container > p:nth-of-type(1) {
             text-align: center; margin-bottom: 45px; font-size: 1.25rem;
             color: var(--color-text-primary); max-width: 800px; margin-left: auto; margin-right: auto;
         }
         .contact-info { text-align: center; line-height: 2.4; padding-top: 15px; }
         .contact-info p { margin-bottom: 12px;}
         .contact-info strong { color: var(--color-lavender-bright); }
         .contact-info a:not(.cta-button) {
             color: var(--color-lavender-bright); text-decoration: none; font-weight: bold;
             transition: color 0.3s ease, text-shadow 0.3s ease;
         }
         .contact-info a:not(.cta-button):hover {
              color: var(--color-text-header); text-shadow: 0 0 10px var(--color-pink-medium);
              text-decoration: none;
         }
         .contact-info i {
              margin-right: 12px; color: var(--color-purple-soft); width: 24px;
              text-align: center; font-size: 1.15rem;
         }
         #contato .contact-info .cta-button { margin-top: 45px; }

         /* --- Footer --- */
         footer {
             background: var(--gradient-footer); color: var(--color-text-secondary); text-align: center;
             padding: 45px 0; margin-top: 60px; position: relative; z-index: 5;
             font-size: 1rem; border-top: 1px solid rgba(118, 73, 229, 0.3);
             box-shadow: 0 -7px 30px rgba(16, 5, 26, 0.65);
         }
         footer p { margin-bottom: 12px; }
         footer a {
             color: var(--color-lavender-bright); text-decoration: none; transition: color 0.3s ease;
         }
         footer a:hover { color: var(--color-text-header); text-decoration: underline; }

        /* --- Responsividade --- */
         @media (max-width: 992px) {
             .container { max-width: 90%; padding: 0 20px;}
             h1 { font-size: 3rem; }
             h2 { font-size: 2.3rem; }
             h3 { font-size: 1.65rem; }
             section { padding: 70px 0; margin-bottom: 45px; }
             #hero { padding: 120px 0 100px 0; }
             #sobre .container { flex-direction: column; gap: 50px; text-align: center;}
             #sobre h2 { text-align: center; }
             #sobre .about-image { margin-bottom: 40px; }
             #sobre .about-image img { max-width: 280px; border-width: 8px;}
             .swiper-slide { width: 310px; height: 410px; }
             .service-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px;}
             .service-item { padding: 35px 30px; border-radius: 20px;}
             .service-item::before { border-top-left-radius: 20px; border-top-right-radius: 20px;}
             .testimonial-grid { grid-template-columns: 1fr; gap: 30px;}
         }

         @media (max-width: 768px) {
             /* --- Ajustes Hero Mobile --- */
             #hero { padding: 80px 0 70px 0; } /* <<<< REDUZIDO PADDING HERO >>>> */
             #hero h1 {
                 font-size: 2.1rem; /* <<<< FONTE H1 MENOR >>>> */
                 line-height: 1.3;
                 margin-bottom: 18px; /* Menos margem */
             }
             #hero p {
                 font-size: 1.1rem; /* <<<< FONTE P MENOR >>>> */
                 margin-bottom: 35px; /* Menos margem */
                 max-width: 95%;
             }
             /* --- Fim Ajustes Hero Mobile --- */

             h2 { font-size: 2rem; margin-bottom: 50px; }
             h3 { font-size: 1.5rem; }
             section { padding: 60px 0; margin-bottom: 40px; }
             #sobre, #contato, #depoimentos { border-radius: 25px; }

             header { padding: 15px 0; }
             .logo img { max-height: 55px;} /* Logo menor */
             .menu-toggle { display: block; font-size: 1.8rem;}

             /* Estilos Menu Mobile - CORRIGIDO */
             nav {
                 position: absolute; top: 100%; left: 0; right: 0; width: 100%;
                 background: linear-gradient(to bottom, rgba(58, 14, 122, 0.99), rgba(26, 10, 46, 1.0)); /* Quase opaco */
                 max-height: 0; overflow: hidden;
                 transition: max-height 0.4s ease-out, box-shadow 0.3s ease-out;
                 box-shadow: none;
                 border-bottom-left-radius: 15px; border-bottom-right-radius: 15px;
                 z-index: 99; /* Abaixo do header */
             }
             nav.active {
                 max-height: calc(100vh - 80px); /* Altura dinâmica */
                 box-shadow: 0 10px 25px rgba(10, 5, 18, 0.7);
                 overflow-y: auto;
             }
             nav ul { display: flex; flex-direction: column; width: 100%; align-items: center; padding: 10px 0; margin: 0; }
             /* Ocultar UL desktop no mobile */
             @media (min-width: 769px) {
                 nav { max-height: none; overflow: visible; position: relative; background: none; box-shadow: none; padding: 0; margin-top: 0;}
                 nav ul { display: flex !important; flex-direction: row; position: static; height: auto; padding: 0;}
             }
             /* Garantir que UL esteja oculto quando nav não está ativo */
             @media (max-width: 768px) { nav:not(.active) ul { display: none; } }

             nav ul li { margin: 0; width: 100%; text-align: center; }
             nav ul li a {
                 display: block; padding: 14px 20px; width: 100%;
                 border-bottom: 1px solid rgba(118, 73, 229, 0.1);
                 color: var(--color-text-primary); font-size: 1rem;
             }
             nav ul li:last-child a { border-bottom: none; }
             nav ul li a:hover { background-color: rgba(118, 73, 229, 0.15); color: var(--color-text-header); }
             nav ul li a::after { display: none; }

             .cta-button { font-size: 1.1rem; padding: 15px 35px; }
             .service-grid { grid-template-columns: 1fr; gap: 35px; }
             .service-item { padding: 30px 25px; border-radius: 20px;}
             .service-item h3 i { font-size: 1.6rem; margin-right: 15px; width: 30px;}
             .swiper-slide { width: 85%; height: 380px; border-radius: 20px;}
             .swiper-pagination { bottom: 25px; }
             #sobre .about-image img { max-width: 230px; border-width: 8px;}
             /* Ajuste Contato Mobile */
             #contato h2 { font-size: 1.9rem; margin-bottom: 30px;}
             #contato > .container > p:nth-of-type(1) { font-size: 1.1rem; margin-bottom: 30px;}
             .contact-info { line-height: 2.1; }
             .contact-info p { font-size: 1rem; margin-bottom: 8px;}
             #contato .contact-info .cta-button { font-size: 1rem; padding: 14px 30px; margin-top: 30px;}
             .testimonial-item { padding: 30px 25px; }
         }

         @media (max-width: 480px) {
             h1 { font-size: 1.9rem; } /* H1 ainda menor */
             h2 { font-size: 1.7rem; margin-bottom: 40px; }
             h3 { font-size: 1.4rem; }
             section { padding: 55px 0; margin-bottom: 30px; }
             #hero { padding: 65px 0 55px 0; } /* Hero padding ainda menor */
             #hero p { font-size: 1rem; } /* Hero p menor */
             .cta-button { font-size: 1rem; padding: 14px 30px; }
             #starry-sky::after { width: 55px; height: 55px; top: 15%; right: 8%; } /* Lua posição ajustada */
             .swiper-slide { width: 90%; height: 360px; }
             .service-item { padding: 25px 20px; border-radius: 15px;}
             .service-item::before { border-top-left-radius: 15px; border-top-right-radius: 15px; height: 5px;}
             .service-item h3 i { font-size: 1.5rem; margin-right: 10px; width: 25px;}
             .testimonial-item { padding: 25px 20px;}
             .testimonial-item blockquote { font-size: 0.95rem;}
             .testimonial-item cite { font-size: 0.9rem;}
             .contact-info { line-height: 2.2; }
             #contato > .container > p:nth-of-type(1) { font-size: 1rem;}
             .contact-info p { font-size: 0.95rem; }
             footer { padding: 35px 0; font-size: 0.9rem;}
             .logo img { max-height: 50px; }
             .menu-toggle { font-size: 1.7rem; }
             nav ul li a { padding: 14px 20px; font-size: 0.95rem;}
         }
    </style>
</head>
<body>

    <!-- Fundo Estrelado -->
    <div id="starry-sky">
        <!-- Lua agora é ::after no CSS -->
    </div>

    <header>
        <div class="container">
            <div class="logo">
                <a href="#hero"><img src="https://i.ibb.co/HDKh5Czc/BIANCA-LOGO-1.webp" alt="Logo Psicóloga Bianca"></a>
            </div>
            <button class="menu-toggle" aria-label="Abrir Menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <nav> <!-- Menu de Navegação -->
                <ul>
                    <li><a href="#hero">Início</a></li>
                    <li><a href="#sobre">Sobre Mim</a></li>
                    <li><a href="#servicos">Serviços</a></li>
                    <li><a href="#galeria">Galeria</a></li>
                    <li><a href="#depoimentos">Depoimentos</a></li>
                    <li><a href="#contato">Contato</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <!-- Seção Hero (SEM fundo de seção) -->
        <section id="hero">
            <div class="container">
                <h1>Acolhendo Jornadas, <br class="mobile-break">Cultivando Futuros</h1> <!-- <br> opcional para forçar quebra no mobile -->
                <p>Psicologia especializada para crianças, adolescentes e adultos florescerem em um ambiente seguro. Atendimento presencial e online.</p>
                <a href="#contato" class="cta-button">
                    <i class="fab fa-whatsapp"></i> <!-- Ícone WhatsApp branco -->
                    Agende sua Consulta
                </a>
            </div>
        </section>

        <!-- Seção Sobre Mim (COM fundo gradiente translúcido) -->
        <section id="sobre">
            <div class="container">
                 <div class="about-image">
                    <img src="https://i.ibb.co/Gfhftrvw/upscalemedia-transformed-3.webp" alt="Foto da Psicóloga Bianca">
                 </div>
                <div class="about-content">
                    <h2>Sobre Mim</h2>
                    <p>Olá! Sou Bianca Azevedo, psicóloga dedicada a criar um espaço seguro e acolhedor, onde crianças, adolescentes e adultos possam explorar seus sentimentos, desafios e potenciais.</p>
                    <p>Minha paixão é guiar cada pessoa em sua jornada única de autoconhecimento e desenvolvimento, utilizando abordagens personalizadas e baseadas em evidências, sempre com muito carinho e respeito.</p>
                    <p>Com especializações em Análise do Comportamento Aplicada (ABA), Modelo Denver, Psicopedagogia e Arteterapia, busco oferecer um olhar completo e integrado para as necessidades de cada um.</p>
                </div>
            </div>
        </section>

        <!-- Seção Serviços (SEM fundo de seção, cards com topo arredondado e ÍCONES) -->
        <section id="servicos">
            <div class="container">
                <h2>Como Posso Ajudar?</h2>
                <div class="service-grid">
                    <div class="service-item">
                        <h3><i class="fas fa-laptop-medical"></i> Psicóloga Presencial ou Online</h3>
                        <p>Atendimento para crianças, adolescentes e adultos, adaptado às suas necessidades, no conforto do consultório ou de onde você estiver.</p>
                    </div>
                    <div class="service-item">
                        <h3><i class="fas fa-puzzle-piece"></i> Analista ABA/DENVER Infantil</h3>
                        <p>Intervenções especializadas e baseadas em evidências para o desenvolvimento infantil, especialmente no Transtorno do Espectro Autista (TEA).</p>
                    </div>
                    <div class="service-item">
                        <h3><i class="fas fa-book-reader"></i> Psicopedagogia</h3>
                        <p>Avaliação e intervenção em dificuldades de aprendizagem, ajudando a superar barreiras e a desenvolver habilidades escolares e cognitivas.</p>
                    </div>
                    <div class="service-item">
                        <h3><i class="fas fa-palette"></i> Arteterapia</h3>
                        <p>Um caminho criativo para a expressão emocional, autoconhecimento e resolução de conflitos através da arte e da ludicidade.</p>
                    </div>
                     <div class="service-item">
                        <h3><i class="fas fa-users"></i> Orientação Parental</h3>
                        <p>Apoio e estratégias para pais e cuidadores lidarem com os desafios da educação e fortalecerem os vínculos familiares de forma positiva.</p>
                    </div>
                     <div class="service-item">
                        <h3><i class="fas fa-clipboard-check"></i> Avaliação Psicológica</h3>
                        <p>Processo de investigação e compreensão de aspectos psicológicos para auxiliar em diagnósticos e direcionamento terapêutico.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção Galeria (Reposicionada) -->
        <section id="galeria">
            <div class="container">
                <h2>Espaços e Momentos</h2>
                <div class="swiper mySwiper">
                    <div class="swiper-wrapper">
                         <div class="swiper-slide"><img src="https://i.ibb.co/G4K6gDQV/b3bd4fb8-3723-4ce4-99d3-40ce285f5e27.jpg" alt="Ambiente terapêutico infantil 1" /></div>
                         <div class="swiper-slide"><img src="https://i.ibb.co/Tx81Kd1g/8ed3d73e-dfd2-464d-8687-a173fe9bc466.jpg" alt="Consultório adulto acolhedor" /></div>
                         <div class="swiper-slide"><img src="https://i.ibb.co/Xr7phc1P/8181e03d-cdcd-483b-b49e-965ed3b25f32.jpg" alt="Sessão de arteterapia" /></div>
                         <div class="swiper-slide"><img src="https://i.ibb.co/KjdJTwh8/2a440d3d-baa8-441c-a376-78cd4a7b4674.jpg" alt="Materiais lúdicos" /></div>
                         <div class="swiper-slide"><img src="https://i.ibb.co/n502VjM/3e50dd7f-fb68-494a-8d49-191c2703fc1c.jpg" alt="Detalhe do consultório" /></div>
                         <div class="swiper-slide"><img src="https://i.ibb.co/JRkhMXvT/e376b0ce-f781-4dce-8bb1-94b07bf7477d.jpg" alt="Terapia online - Representação" /></div>
                         <div class="swiper-slide"><img src="https://i.ibb.co/7x02HwjW/e190e7e1-3ee2-44cb-bf36-e6aade4398f6.jpg" alt="Espaço para adolescentes" /></div>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            </div>
        </section>

        <!-- Seção Depoimentos (SEM Fundo e Harmonizada) -->
        <section id="depoimentos">
            <div class="container">
                <h2>O Que Dizem Nossos Pacientes</h2>
                <div class="testimonial-grid">
                    <!-- Exemplo de Depoimento 1 -->
                    <div class="testimonial-item">
                        <blockquote>
                            "Ótima profissional, muito competente e carismática!"
                        </blockquote>
                        <cite>– Luís Guilherme</cite>
                    </div>

                    <!-- Exemplo de Depoimento 2 -->
                    <div class="testimonial-item">
                        <blockquote>
                            "Atendimento impecável e incomparável."
                        </blockquote>
                        <cite>– Polyana Gama</cite>
                    </div>

                    <!-- Exemplo de Depoimento 3 -->
                    <div class="testimonial-item">
                        <blockquote>
                            "Ótima profissional, com uma sensibilidade para o atendimento com as crianças e muito ética!"
                        </blockquote>
                        <cite>– Laura Bianca</cite>
                    </div>

                     <!-- Exemplo de Depoimento 4 (Adicionado) -->
                    <div class="testimonial-item">
                        <blockquote>
                            "A Bianca é uma profissional ímpar! Daquelas que se importam de verdade com o que fazem, e dão o seu melhor! A recomendo de olhos fechados."
                        </blockquote>
                        <cite>– Gabi Spagnuolo</cite>
                    </div>
                </div>
            </div>
        </section>
        <!-- Fim da Seção Depoimentos -->

        <!-- Seção Contato (COM fundo gradiente translúcido mais escuro) -->
        <section id="contato">
            <div class="container">
                <h2>Vamos Conversar?</h2>
                <p>Estou aqui para ouvir você. Entre em contato para agendar ou saber mais!</p>
                <div class="contact-info">
                    <!-- !! IMPORTANTE: Substitua pelas suas informações reais !! -->
                    <p><i class="fas fa-phone"></i> <strong>Telefone/WhatsApp:</strong> <a href="tel:+55XXYYYYYZZZZ">(34) 99164-2883</a></p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <a href="mailto:seuemail@dominio.com">seuemail@dominio.com</a></p>
                    <p><i class="fas fa-map-marker-alt"></i> <strong>Atendimento:</strong> Presencial em Uberlândia e Online.</p>
                    <p><i class="fab fa-instagram"></i> <strong>Instagram:</strong> <a href="https://instagram.com/seu_perfil" target="_blank">@psi.biancazevedo</a></p>
                    <br>
                     <!-- !! IMPORTANTE: Atualize o número no link do WhatsApp !! -->
                    <a href="https://wa.me/5534991642883?text=Olá!%20Gostaria%20de%20mais%20informações%20sobre%20os%20atendimentos." class="cta-button" target="_blank">
                        <i class="fab fa-whatsapp"></i> <!-- Ícone WhatsApp branco (forçado via CSS) -->
                         Falar pelo WhatsApp
                    </a>
                </div>
            </div>
        </section>

    </main>

    <footer>
        <div class="container">
             <!-- !! IMPORTANTE: Substitua pelo seu Sobrenome !! -->
            <p>© <?php echo date("Y"); ?> Bianca Azevedo - Psicologia. Todos os direitos reservados.</p>
             <!-- !! IMPORTANTE: Insira seu CRP aqui !! -->
            <p>CRP XX/YYYYY</p>
            
        </div>
    </footer>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- Font Awesome JS (opcional, se usar apenas CSS pode remover) -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script> -->

    <script>
        // --- Animação do Fundo Estrelado ---
        const starrySky = document.getElementById('starry-sky');
        const numberOfStars = 250;

        function createStars() {
            if (document.querySelector('#starry-sky .star')) return;
            // Lua via CSS ::after

            for (let i = 0; i < numberOfStars; i++) {
                let star = document.createElement('div');
                star.className = 'star';
                let xy = getRandomPosition();
                star.style.top = xy[0] + 'px';
                star.style.left = xy[1] + 'px';
                const size = Math.random() * 2.5 + 0.5;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.animationDelay = Math.random() * 10 + 's';
                star.style.animationDuration = (Math.random() * 4 + 3) + 's';
                starrySky.appendChild(star);
            }
        }
        function getRandomPosition() {
            if (!starrySky) return [0, 0];
            var y = starrySky.offsetHeight; var x = starrySky.offsetWidth;
            y = y > 0 ? y : window.innerHeight;
            x = x > 0 ? x : window.innerWidth;
            var randomY = Math.floor(Math.random() * y); var randomX = Math.floor(Math.random() * x);
            return [randomY, randomX];
        }

        document.addEventListener('DOMContentLoaded', createStars);
        // REMOVIDO listener de resize

        // --- Inicialização do Swiper ---
        document.addEventListener('DOMContentLoaded', (event) => {
            if (document.querySelector(".mySwiper")) {
                const swiper = new Swiper(".mySwiper", {
                    effect: "coverflow", grabCursor: true, centeredSlides: true, slidesPerView: "auto", loop: true,
                    autoplay: { delay: 4500, disableOnInteraction: false, },
                    coverflowEffect: { rotate: 30, stretch: 0, depth: 160, modifier: 1.1, slideShadows: false, },
                    pagination: { el: ".swiper-pagination", clickable: true, },
                    navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev", },
                    observer: true, observeParents: true
                });
            } else { console.log("Swiper container (.mySwiper) not found."); }
        });

        // --- Lógica Menu Mobile (Final) ---
        const menuToggle = document.querySelector('.menu-toggle');
        const nav = document.querySelector('nav');
        if (menuToggle && nav) {
            const navIcon = menuToggle.querySelector('i');
            menuToggle.addEventListener('click', (e) => {
                 e.stopPropagation();
                nav.classList.toggle('active');
                const isActive = nav.classList.contains('active');
                menuToggle.setAttribute('aria-expanded', isActive);
                if (isActive) {
                    if(navIcon) { navIcon.classList.remove('fa-bars'); navIcon.classList.add('fa-times'); }
                    menuToggle.setAttribute('aria-label', 'Fechar Menu');
                } else {
                     if(navIcon) { navIcon.classList.remove('fa-times'); navIcon.classList.add('fa-bars'); }
                    menuToggle.setAttribute('aria-label', 'Abrir Menu');
                }
            });

            // Fechar ao clicar fora
            document.addEventListener('click', (e) => {
                const isClickInsideNav = nav.contains(e.target);
                const isClickOnToggle = menuToggle.contains(e.target);
                if (nav.classList.contains('active') && !isClickInsideNav && !isClickOnToggle) {
                     nav.classList.remove('active');
                     menuToggle.setAttribute('aria-expanded', false);
                     if(navIcon) { navIcon.classList.remove('fa-times'); navIcon.classList.add('fa-bars'); }
                     menuToggle.setAttribute('aria-label', 'Abrir Menu');
                }
            });

            // Fechar ao clicar em um link do menu
            const navLinks = nav.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (nav.classList.contains('active')) {
                        nav.classList.remove('active');
                        menuToggle.setAttribute('aria-expanded', false);
                         if(navIcon) { navIcon.classList.remove('fa-times'); navIcon.classList.add('fa-bars'); }
                        menuToggle.setAttribute('aria-label', 'Abrir Menu');
                    }
                });
            });
        } else { console.error("Mobile menu elements not found."); }

    </script>

</body>
</html>