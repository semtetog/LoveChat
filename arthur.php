<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Currículo - Arthur Moraes</title>
    <style>
        /* Estilos Modernos e Profissionais */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        /* Cabeçalho */
        .header {
            background: linear-gradient(135deg, #2c3e50, #4b6cb7);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .contact-info {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .contact-item i {
            margin-right: 8px;
        }
        
        /* Conteúdo Principal */
        .main-content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #eaeaea;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: #4b6cb7;
        }
        
        /* Seção Objetivo */
        .objective {
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        /* Seção Formação */
        .education-item {
            margin-bottom: 15px;
        }
        
        .education-title {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .education-subtitle {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 3px;
        }
        
        .education-date {
            font-size: 0.85rem;
            color: #666;
        }
        
        /* Seção Perfil */
        .profile-text {
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        /* Seção Habilidades */
        .skills-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .skill-item {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        /* Seção Dados Pessoais */
        .personal-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .info-item {
            font-size: 0.9rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Rodapé */
        .footer {
            text-align: center;
            padding: 15px;
            background: #f5f7fa;
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Responsividade */
        @media (max-width: 600px) {
            .skills-container, .personal-info {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
        }
        
        @media print {
            body {
                background: none;
            }
            
            .container {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Arthur Moraes</h1>
            <p>Menor Aprendiz | Mecânica e Computação</p>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    Uberlândia - MG
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    (34) 99893-5111
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    arthurmoraess168@gmail.com
                </div>
            </div>
        </header>

        <div class="main-content">
            <section class="section">
                <h2 class="section-title">Objetivo</h2>
                <p class="objective">
                    Atuar como menor aprendiz, com foco em adquirir experiência e desenvolver habilidades profissionais, especialmente nas áreas de mecânica e computação.
                </p>
            </section>

            <section class="section">
                <h2 class="section-title">Formação</h2>
                <div class="education-item">
                    <div class="education-title">Ensino Fundamental - 9º ano</div>
                    <div class="education-subtitle">Colégio Finotti</div>
                    <div class="education-date">Previsão de conclusão: Dezembro 2025</div>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title">Perfil</h2>
                <p class="profile-text">
                    Sou um estudante de 14 anos, apaixonado por mecânica e computação. Tenho facilidade em aprender, sou proativo e gosto de trabalhar em equipe. Me interesso especialmente por carros, motores e tecnologia. Busco uma oportunidade de aprendizado ou estágio onde eu possa desenvolver minhas habilidades e contribuir com dedicação. Tenho disponibilidade para trabalhar em qualquer horário após as 13 horas.
                </p>
            </section>

            <section class="section">
                <h2 class="section-title">Habilidades</h2>
                <div class="skills-container">
                    <div class="skill-item">
                        <i class="fas fa-comments"></i> Boa comunicação e trabalho em equipe
                    </div>
                    <div class="skill-item">
                        <i class="fas fa-lightbulb"></i> Proatividade e vontade de aprender
                    </div>
                    <div class="skill-item">
                        <i class="fas fa-car"></i> Conhecimento geral sobre carros e mecânica
                    </div>
                    <div class="skill-item">
                        <i class="fas fa-laptop"></i> Interesse e noções básicas em computação
                    </div>
                    <div class="skill-item">
                        <i class="fas fa-language"></i> Inglês básico (compreensão e vocabulário simples)
                    </div>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title">Dados Pessoais</h2>
                <div class="personal-info">
                    <div class="info-item">
                        <span class="info-label">Idade:</span> 14 anos
                    </div>
                    <div class="info-item">
                        <span class="info-label">Data de Nascimento:</span> 07/04/2011
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nacionalidade:</span> Brasileira
                    </div>
                    <div class="info-item">
                        <span class="info-label">Disponibilidade:</span> Após as 13h
                    </div>
                </div>
            </section>
        </div>

        <footer class="footer">
            <p>Currículo atualizado em 2025</p>
        </footer>
    </div>
</body>
</html>