<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposta para Dudu Neto | River Negócios Digitais</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #0a1f44;
            --primary-medium: #2a5c8d;
            --primary-light: #7eb5e4;
            
            --accent-primary: var(--primary-medium); 
            --accent-secondary: var(--primary-light); 
            
            --accent-decoration-border: rgba(126, 181, 228, 0.3); 
            --accent-decoration-bg-light: rgba(42, 92, 141, 0.08); 
            --accent-decoration-bg-very-light: rgba(42, 92, 141, 0.03);

            --text-dark: #1a1a1a;
            --text-medium: #4a4a4a;
            --text-light: #6d6d6d;
            --bg-gradient: linear-gradient(135deg, #f8faff 0%, #e6f0ff 100%);
        }

        @import url('https://fonts.cdnfonts.com/css/clash-display');
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Manrope', sans-serif;
            line-height: 1.7;
            color: var(--text-medium);
            background: var(--bg-gradient);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background-color: white;
            box-shadow: 0 30px 60px -20px rgba(10, 31, 68, 0.15);
            border-radius: 16px;
            position: relative;
            overflow: hidden; 
            z-index: 1;
        }

        .container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 12px;
            background: linear-gradient(90deg, 
                var(--primary-dark) 0%, 
                var(--primary-medium) 50%, 
                var(--accent-secondary) 100%);
        }

        .decoration { 
            position: absolute;
            opacity: 0.03;
            z-index: -1;
        }

        .decoration-1 {
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--primary-medium) 0%, transparent 70%);
        }

        .decoration-2 {
            bottom: -150px;
            left: -150px;
            width: 500px;
            height: 500px;
            background: conic-gradient(from 45deg, var(--primary-dark), var(--primary-medium), var(--accent-secondary), var(--primary-dark));
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            transform: rotate(15deg);
        }

        .header {
            padding: 40px 70px 30px; 
            text-align: center; 
            position: relative;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgwLCAwLCAwLCAwLjAxKSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
        }

         .logo-container {
            display: block; 
            margin: 0 auto 25px auto; 
            padding: 15px; 
            width: fit-content; 
            position: relative;
        }

        .logo-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 2px solid var(--accent-decoration-border);
            border-radius: 50%;
            transform: rotate(45deg);
            z-index: -1;
        }

        .logo-container::after {
            content: "";
            position: absolute;
            top: -8px; 
            left: -8px; 
            right: -8px; 
            bottom: -8px; 
            border: 1px solid var(--accent-decoration-border);
            border-radius: 50%;
            z-index: -2;
        }

        .logo {
            max-width: 160px; 
            filter: drop-shadow(0 4px 12px rgba(10, 31, 68, 0.1)); 
            display: block; 
            margin: 0 auto; 
        }

        h1, h2, h3 {
            font-family: 'ClashDisplay', sans-serif;
            font-weight: 600;
            margin: 0;
            line-height: 1.25; 
        }

        h1 { 
            color: var(--primary-dark);
            font-size: 36px; 
            margin-top: 0; 
            margin-bottom: 8px; 
            position: relative;
            display: inline-block; 
        }

        h1::after { 
            content: "";
            position: absolute;
            bottom: -8px; 
            left: 50%;
            transform: translateX(-50%);
            width: 70px; 
            height: 3px; 
            background: linear-gradient(90deg, var(--primary-dark), var(--accent-secondary));
            border-radius: 1.5px; 
        }
        
        .proposal-for { 
            font-size: 18px;
            color: var(--primary-medium);
            margin-top: 20px; 
            margin-bottom: 10px;
            font-weight: 500;
            font-family: 'Manrope', sans-serif; 
        }

        .subtitle { 
            font-size: 16px; 
            color: var(--text-light);
            max-width: 580px; 
            margin: 0 auto 20px; 
            font-weight: 400;
        }

        h2 { 
            color: var(--primary-dark);
            font-size: 26px; 
            margin: 40px 0 20px; 
            position: relative;
            padding-left: 25px; 
        }

        h2::before { 
            content: "";
            position: absolute;
            left: 0;
            top: 50%; 
            transform: translateY(-50%); 
            height: 26px; 
            width: 6px; 
            background: linear-gradient(to bottom, var(--primary-medium), var(--accent-secondary));
            border-radius: 3px; 
        }
        
        .content {
            padding: 0 60px 40px; 
            position: relative;
        }

        p {
            margin-bottom: 18px; 
            font-size: 15px; 
            color: var(--text-medium);
            line-height: 1.65; 
        }

        ul {
            margin-top: 10px;
            margin-bottom: 25px; 
            padding-left: 5px; 
            list-style-type: none; 
        }

        li {
            margin-bottom: 10px; 
            position: relative;
            padding-left: 22px; 
            font-size: 15px; 
        }

        li::before { 
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            content: "\f00c"; 
            color: var(--accent-primary);
            font-size: 13px;
            position: absolute;
            left: 0; 
            top: 6px; 
        }
        
        ul ul { /* Estilo para sub-listas */
            margin-top: 8px;
            margin-bottom: 8px;
            padding-left: 20px; 
        }
        ul ul li {
            font-size: 14.5px; /* Ligeiramente menor para sub-itens */
        }
        ul ul li::before { /* Marcador diferente ou mais sutil para sub-itens */
            content: "\f0da"; /* Ícone de "chevron-right" por exemplo, ou um círculo menor */
            color: var(--primary-light);
            font-size: 12px;
            top: 7px;
        }

        .footer {
            text-align: center;
            padding: 25px 70px; 
            background: linear-gradient(135deg, rgba(10, 31, 68, 0.02) 0%, rgba(42, 92, 141, 0.01) 100%); 
            border-top: 1px solid rgba(42, 92, 141, 0.08); 
            margin-top: 30px; 
        }

        .contact-item {
            display: block; 
            margin: 8px 10px; 
            color: var(--text-light);
            font-size: 14px; 
        }

        .contact-item i {
            color: var(--accent-primary);
            margin-right: 7px; 
            width: 16px; 
            text-align: center;
        }

        .btn-container {
            text-align: center;
            margin: 40px 0 15px; 
        }

        .btn-pdf {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-medium));
            color: white;
            border: none;
            padding: 14px 30px; 
            font-size: 14px; 
            font-weight: 600;
            cursor: pointer; 
            border-radius: 50px;
            box-shadow: 0 6px 20px rgba(10, 31, 68, 0.12); 
        }

        .btn-pdf::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--primary-medium), var(--accent-secondary));
            opacity: 0; 
            z-index: -1;
        }

        .btn-pdf i {
            margin-left: 7px; 
        }

        .watermark {
            position: absolute;
            bottom: 15px; 
            right: 15px; 
            opacity: 0.03; 
            font-size: 90px; 
            font-weight: bold;
            color: var(--primary-dark);
            font-family: 'ClashDisplay', sans-serif;
            pointer-events: none;
            z-index: -1;
        }

        @media print {
            html, body { 
                background: white !important; 
                font-size: 10pt; 
                line-height: 1.55; 
                height: auto !important; 
                overflow: visible !important; 
                -webkit-print-color-adjust: exact !important; 
                color-adjust: exact !important; 
            }
            
            .container {
                box-shadow: none !important;
                margin: 0 !important;
                border-radius: 0 !important;
                width: 100% !important; 
                max-width: 100% !important;
                overflow: visible !important;
                border: none !important; 
            }
            
            .container::before { 
                height: 8px !important; 
                background: linear-gradient(90deg, 
                    var(--primary-dark) 0%, 
                    var(--primary-medium) 50%, 
                    var(--accent-secondary) 100%) !important;
            }

            .header { padding: 25px 40px 15px !important; } 
            .content { padding: 0 40px 30px !important; }
            .footer { padding: 20px 40px !important; }


            .btn-container, .decoration { 
                display: none !important; 
            }

            .watermark { 
                opacity: 0.025 !important; 
                font-size: 60px !important; 
                bottom: 10px !important; 
                right: 10px !important;
                display: block !important; 
            }
            
            .strategy-card, .btn-pdf { /* Esses elementos não existem mais com esse nome, mas a regra é boa */
                transform: none !important;
            }
            .btn-pdf { 
                 background: var(--primary-medium) !important; 
                 box-shadow: none !important;
            }
            .btn-pdf::before {
                opacity: 0 !important; 
            }

            .header, .logo-container, .footer, p, li, .proposal-for, .subtitle {
                page-break-inside: avoid !important;
            }

            h1, h2 {
                page-break-after: avoid !important; 
            }
            
            .content > h2:not(:first-of-type) { 
                page-break-before: always !important;
                margin-top: 25px !important; 
                padding-top: 5px !important; 
            }
            
            ul {
                page-break-inside: auto; 
            }

            img.logo { 
                max-width: 140px !important; 
                page-break-inside: avoid !important;
            }

             h1 {
                font-size: 28px !important; /* Ajuste para impressão */
            }
            .proposal-for {
                font-size: 15px !important;
                margin-top: 15px !important;
                margin-bottom: 8px !important;
            }
            .subtitle {
                font-size: 13px !important; /* Ajuste para impressão */
            }
             h2 {
                font-size: 22px !important; /* Ajuste para impressão */
             }
             p, li {
                font-size: 9.5pt !important; /* Ajuste fino para mais texto por página */
             }

        }
    </style>
</head>
<body>
    <div class="container" id="pdf-content">
        <div class="decoration decoration-1"></div>
        <div class="decoration decoration-2"></div>
        
        <div class="header">
            <div class="logo-container">
                <img src="https://i.ibb.co/x82szCxw/LOGO-RIVER.png" alt="River Negócios Digitais" class="logo">
            </div>
            <h1>Proposta de Infoproduto</h1>
            <p class="proposal-for">Cliente : Dudu Neto</p>
            <p class="subtitle">A River Negócios Digitais é especialista em transformar conhecimento em negócios digitais de sucesso. Esta proposta detalha nosso plano para criar seu infoproduto.</p>
        </div>
        
        <div class="content">
            <div class="watermark">RIVER</div>
            
            <h2>Nosso Objetivo</h2>
            <p>
                O objetivo principal é a criação do infoproduto do Dudu, que funcionará como um "produto de entrada". 
                Este produto visa aumentar sua credibilidade como educador físico e, estrategicamente, atrair 
                novos clientes para os serviços de tickets mais altos que você já oferece ao seu público.
            </p>
            
            <h2>Estratégias</h2>
            <p>Para alcançar nosso objetivo, seguiremos as seguintes estratégias:</p>
            <ul>
                <li>Pesquisa de mercado e do seu público para entender qual tipo de produto se encaixa melhor com o objetivo definido.</li>
                <li>Idealização do nome e criação da estética visual completa do produto, transmitindo profissionalismo e valor.</li>
                <li>Criação de uma Landing Page (página de vendas) de alta conversão, utilizando as melhores referências e práticas do mercado.</li>
                <li>Desenvolvimento da Copy (textos persuasivos) e do Script para o VSL (Vídeo de Vendas) que ficará na Landing Page, com foco em converter visitantes em compradores.</li>
                <li>Criação da Copy e do Script do conteúdo do infoproduto em si, garantindo que o material entregue aos clientes seja claro, valioso e transformador.</li>
            </ul>
            
            <h2>Entregáveis do Projeto</h2>
            <p>Você receberá uma solução completa e pronta para operar, incluindo:</p>
            <ul>
                <li><strong>Site Oficial do Infoproduto:</strong> Um site profissional e exclusivo para o seu produto, com design moderno e responsivo (adaptado para celulares, tablets e computadores).</li>
                <li>
                    <strong>Área de Membros Integrada:</strong>
                    <ul>
                        <li>Plataforma para hospedar as videoaulas, organizadas em módulos de fácil navegação.</li>
                        <li>Sistema de cadastro e login seguro para seus alunos.</li>
                    </ul>
                </li>
                <li><strong>Painel de Administrador:</strong> Uma área restrita para você gerenciar seus clientes, acompanhar o progresso deles dentro do produto e ter controle total sobre o conteúdo.</li>
                <li><strong>Landing Page Otimizada:</strong> Conforme descrito nas estratégias, uma página de vendas completa com VSL.</li>
                <li><strong>Conteúdo do Infoproduto:</strong> Scripts e direcionamento para a produção do material que será entregue.</li>
            </ul>
            
            <p>
                Com a River Negócios Digitais, seu conhecimento se transforma em um ativo digital valioso, 
                pronto para gerar resultados e fortalecer sua marca no mercado.
            </p>

            <div class="btn-container">
                <button class="btn-pdf" id="generate-pdf">Baixar Proposta em PDF <i class="fas fa-download"></i></button>
            </div>
        </div>
        
        <div class="footer">
         
            <div class="contact-item">
                <i class="fas fa-phone"></i> +55 (34) 99298-1424
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        document.getElementById('generate-pdf').addEventListener('click', function() {
            const element = document.getElementById('pdf-content');
            const opt = {
                margin: [10, 5, 10, 5], 
                filename: 'Proposta_River_Dudu_Neto.pdf', 
                image: { 
                    type: 'jpeg', 
                    quality: 0.98 
                },
                html2canvas: { 
                    scale: 2, 
                    logging: false, 
                    useCORS: true,
                    letterRendering: true,
                    scrollX: 0,
                    scrollY: 0, 
                    windowWidth: element.offsetWidth, 
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait',
                    compress: true
                },
                pagebreak: { 
                    mode: ['css', 'avoid-all'], 
                    avoid: ['p', 'li', '.header', '.footer', '.logo-container', '.proposal-for', '.subtitle'] 
                }
            };
            
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando PDF...';
            btn.disabled = true;
            
            window.scrollTo(0,0); 

            setTimeout(() => { 
                html2pdf().from(element).set(opt).save()
                    .then(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    })
                    .catch(err => {
                        console.error("Erro ao gerar PDF:", err);
                        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao gerar';
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }, 3000); 
                    });
            }, 250); 
        });
    </script>
</body>
</html>