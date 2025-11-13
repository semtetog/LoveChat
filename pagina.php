<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar no grupo WhatsApp</title>
    <style>
        :root {
            --whatsapp-green: #25D366;
            --whatsapp-teal-green: #128C7E;
            --whatsapp-button-hover: #0E7064;
            --text-dark: #1c1e21;
            --text-light: #f0f2f5;
            --text-muted: #667781; /* Cinza para texto secundário */
            --text-very-muted: #8696a0; /* Cinza ainda mais claro */
            --background-light: #FFFFFF;
            --background-page-alt: #F0F2F5;
            --background-dark-footer: #111B21;
            --border-color: #d1d7db;
            --header-height: 60px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--background-page-alt);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Header */
        header {
            background-color: var(--background-light);
            padding: 0 20px;
            min-height: var(--header-height);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        .logo-container img { height: 30px; vertical-align: middle; }
        .desktop-nav { display: flex; align-items: center; }
        .desktop-nav nav ul { list-style: none; display: flex; padding: 0; margin: 0; }
        .desktop-nav nav ul li { margin: 0 12px; }
        .desktop-nav nav ul li a { text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 500; }
        .desktop-nav nav ul li a:hover { color: var(--whatsapp-teal-green); }
        .download-button-header {
            background-color: var(--whatsapp-green); color: white; padding: 8px 18px;
            border-radius: 20px; text-decoration: none; font-weight: bold; font-size: 14px;
            display: flex; align-items: center; border: none; cursor: pointer;
            white-space: nowrap; margin-left: 20px;
        }
        .download-button-header svg { margin-left: 8px; fill: white; width: 16px; height: 16px; }
        .download-button-header:hover { background-color: #1DA851; }
        .hamburger-menu {
            display: none; background: none; border: none; font-size: 28px;
            color: var(--text-muted); cursor: pointer; padding: 5px;
        }
        .mobile-nav-content {
            display: none; position: absolute; top: var(--header-height); left: 0; right: 0;
            background-color: var(--background-light); border-top: 1px solid var(--border-color);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; padding: 15px 0;
        }
        .mobile-nav-content.active { display: block; }
        .mobile-nav-content nav ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; }
        .mobile-nav-content nav ul li a { display: block; padding: 12px 20px; text-decoration: none; color: var(--text-dark); font-size: 16px; }
        .mobile-nav-content nav ul li a:hover { background-color: var(--background-page-alt); }
        .mobile-nav-content .download-button-mobile {
            display: block; background-color: var(--whatsapp-green); color: white;
            padding: 10px 20px; margin: 15px 20px 5px; border-radius: 20px;
            text-align: center; text-decoration: none; font-weight: bold; font-size: 15px;
        }
        .mobile-nav-content .download-button-mobile:hover { background-color: #1DA851; }

        /* Main Content */
        main {
            flex-grow: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 40px 20px; /* Aumentado padding vertical */
            text-align: center; background-color: var(--background-light);
            margin: 20px auto; max-width: 580px; width: calc(100% - 40px);
            border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .group-icon-container {
            margin-bottom: 20px; /* Margem entre ícone e título */
        }
        .group-icon {
            width: 160px; /* << ALTERADO: Ícone bem maior */
            height: 160px; /* << ALTERADO: Ícone bem maior */
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #efefef; /* Borda mais suave */
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); /* Sombra mais pronunciada */
        }
        main h1 {
            font-size: 24px; /* Tamanho do título do grupo */
            color: var(--text-dark);
            margin-bottom: 8px; /* Margem entre título e subtítulo */
            font-weight: 500; /* Um pouco mais de peso */
            line-height: 1.3;
        }
        .group-invite-subtitle { /* << NOVO ESTILO */
            font-size: 16px;
            color: var(--text-muted);
            margin-bottom: 25px; /* Margem entre subtítulo e botão */
            line-height: 1.4;
        }
        .action-button {
            background-color: var(--whatsapp-teal-green); color: white;
            padding: 12px 30px; /* Botão ligeiramente maior */
            border-radius: 25px; text-decoration: none;
            font-size: 16px; /* Texto do botão maior */
            font-weight: 500; border: none; cursor: pointer;
            margin-bottom: 30px; transition: background-color 0.2s ease;
        }
        .action-button:hover { background-color: var(--whatsapp-button-hover); }
        .download-link-text { color: var(--text-muted); font-size: 14px; }
        .download-link-text a { color: var(--whatsapp-teal-green); text-decoration: none; font-weight: 500; }
        .download-link-text a:hover { text-decoration: underline; }

        /* Footer */
        footer {
            background-color: var(--background-dark-footer); color: var(--text-light);
            padding: 50px 20px 30px; font-size: 14px;
        }
        .footer-container { max-width: 1100px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; }
        .footer-brand-column { display: flex; flex-direction: column; align-items: flex-start; margin-bottom: 30px; flex-basis: 200px; margin-right: 20px; }
        .footer-brand-column img { height: 35px; margin-bottom: 20px; }
        .footer-download-button {
            background-color: var(--whatsapp-green); color: var(--background-dark-footer); padding: 10px 20px;
            border-radius: 20px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center;
        }
        .footer-download-button:hover { background-color: #1DA851; }
        .footer-links-columns-container { display: flex; flex-wrap: wrap; justify-content: space-between; flex-grow: 1; flex-basis: calc(100% - 240px); }
        .footer-column { margin-bottom: 20px; min-width: 150px; padding-right: 15px; flex: 1 1 150px; }
        .footer-column:last-child { padding-right: 0; }
        .footer-column h4 { color: #8696A0; margin-bottom: 15px; font-size: 12px; text-transform: uppercase; font-weight: 600; }
        .footer-column ul { list-style: none; }
        .footer-column ul li { margin-bottom: 10px; }
        .footer-column ul li a { text-decoration: none; color: var(--text-light); font-size: 14px; }
        .footer-column ul li a:hover { text-decoration: underline; }

        /* Media Queries */
        @media (max-width: 992px) {
            .desktop-nav { display: none; }
            .hamburger-menu { display: block; }
        }
        @media (max-width: 767px) {
            header { padding: 0 15px; }
            main { margin: 15px; width: calc(100% - 30px); padding: 30px 15px; }
            main h1 { font-size: 22px; }
            .group-invite-subtitle { font-size: 15px; }
            .group-icon { width: 140px; height: 140px; } /* Ajuste para mobile */
            .footer-brand-column { flex-basis: 100%; align-items: center; margin-right: 0; }
            .footer-links-columns-container { flex-basis: 100%; justify-content: flex-start; }
            .footer-column { flex-basis: 100%; min-width: unset; padding-right: 0; margin-bottom: 30px; }
        }
        @media (max-width: 480px) {
            main h1 { font-size: 20px; }
            .group-invite-subtitle { font-size: 14px; }
            .group-icon { width: 120px; height: 120px; } /* Ajuste adicional para telas bem pequenas */
            .action-button { font-size: 15px; padding: 10px 25px; }
            .mobile-nav-content .download-button-mobile { font-size: 14px; padding: 10px 18px; }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-container">
            <img src="https://static.whatsapp.net/rsrc.php/v3/y7/r/DSxOAUB0raA.png" alt="WhatsApp Logo">
        </div>
        <div class="desktop-nav">
            <nav>
                <ul>
                    <li><a href="#">Recursos</a></li>
                    <li><a href="#">Privacidade</a></li>
                    <li><a href="#">Central de Ajuda</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Para empresas</a></li>
                </ul>
            </nav>
            <a href="https://www.whatsapp.com/download" target="_blank" class="download-button-header">
                Baixar
                <svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor"><path d="M17.473 7.527V3H6.527v4.527H3V21h18V7.527h-3.527zM7.527 4.001h8.946v3.526H7.527V4.001zm12.473 15.999H4V8.527h2.527v1.526h11V8.527H20v11.473z"/><path d="m12 10.473-4 4L9.473 15.95l2.527-2.527V18h1V13.423l2.527 2.527L16 14.473l-4-4z"/></svg>
            </a>
        </div>
        <button class="hamburger-menu" aria-label="Abrir menu" aria-expanded="false">☰</button>
        <div class="mobile-nav-content">
            <nav>
                <ul>
                    <li><a href="#">Recursos</a></li>
                    <li><a href="#">Privacidade</a></li>
                    <li><a href="#">Central de Ajuda</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Para empresas</a></li>
                </ul>
            </nav>
            <a href="https://www.whatsapp.com/download" target="_blank" class="download-button-mobile">Baixar</a>
        </div>
    </header>

    <main>
        <div class="group-icon-container">
            <img src="https://media-gru2-1.cdn.whatsapp.net/v/t61.24694-24/479670001_1383105639514344_1706603685763934194_n.jpg?ccb=11-4&oh=01_Q5Aa1gHFuCbcRb_NzUVBOTEZDAEcCQax-9SmZ8PwxGEEcRfjkw&oe=6845C31C&_nc_sid=5e03e0&_nc_cat=109" alt="Ícone do Grupo" class="group-icon"
                 onerror="this.onerror=null; this.src='https://via.placeholder.com/160/25D366/FFFFFF?text=Grupo'; this.style.objectFit='contain';">
        </div>
        <h1>Grupo VIP- Seca Pochete</h1> <!-- << ALTERADO: Apenas nome do grupo -->
        <p class="group-invite-subtitle">Convite para grupo do WhatsApp</p> <!-- << NOVO: Subtítulo -->
        <a href="https://chat.whatsapp.com/Eq6Sz60WAPM2IWIAVupAyT" target="_blank" class="action-button">
            Entrar no grupo
        </a>
        <p class="download-link-text">
            Ainda não tem o WhatsApp?
            <a href="https://www.whatsapp.com/download" target="_blank">Baixar</a>
        </p>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-brand-column">
                <img src="https://static.whatsapp.net/rsrc.php/v3/y7/r/DSxOAUB0raA.png" alt="WhatsApp Logo">
                <a href="https://www.whatsapp.com/download" target="_blank" class="footer-download-button">Baixar</a>
            </div>
            <div class="footer-links-columns-container">
                <div class="footer-column"><h4>O que fazemos</h4><ul><li><a href="#">Recursos</a></li><li><a href="#">Blog</a></li><li><a href="#">Segurança</a></li><li><a href="#">Para empresas</a></li></ul></div>
                <div class="footer-column"><h4>Quem somos</h4><ul><li><a href="#">Sobre nós</a></li><li><a href="#">Carreiras</a></li><li><a href="#">Central de marcas</a></li><li><a href="#">Privacidade</a></li></ul></div>
                <div class="footer-column"><h4>Use o WhatsApp</h4><ul><li><a href="#">Android</a></li><li><a href="#">iPhone</a></li><li><a href="#">Mac/PC</a></li><li><a href="#">WhatsApp Web</a></li></ul></div>
                <div class="footer-column"><h4>Precisa de ajuda?</h4><ul><li><a href="#">Fale conosco</a></li><li><a href="#">Central de ajuda</a></li><li><a href="#">Alertas de Segurança</a></li></ul></div>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const hamburgerButton = document.querySelector('.hamburger-menu');
            const mobileNavContent = document.querySelector('.mobile-nav-content');
            if (hamburgerButton && mobileNavContent) {
                hamburgerButton.addEventListener('click', function () {
                    const isExpanded = mobileNavContent.classList.toggle('active');
                    hamburgerButton.setAttribute('aria-expanded', isExpanded);
                    hamburgerButton.innerHTML = isExpanded ? '✕' : '☰';
                });
            }
        });
    </script>

</body>
</html>