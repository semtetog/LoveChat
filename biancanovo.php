<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Currículo Profissional | Bianca Azevedo de Souza</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #5a7df4;
            --secondary-color: #9a6bd6;
            --tertiary-color: #e055a3;
            --text-color: #333333;
            --bg-color: #ffffff;
            --light-gray: #f5f8ff;
            --border-color: #e0e6f0;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4ff;
            color: var(--text-color);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        .container {
            max-width: 1000px;
            margin: 40px auto;
            background-color: var(--bg-color);
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            position: relative;
        }
        
        h1, h2, h3, h4 { 
            font-weight: 600;
            line-height: 1.3;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color), var(--tertiary-color));
            color: white; 
            padding: 50px 40px; 
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .profile-pic {
            width: 160px; 
            height: 160px; 
            border-radius: 50%;
            border: 6px solid rgba(255, 255, 255, 0.85);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            object-fit: cover; 
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
            transition: transform 0.3s ease;
        }
        
        .profile-pic:hover {
            transform: scale(1.05);
        }
        
        .header h1 { 
            font-size: 2.75rem; 
            letter-spacing: 0.5px; 
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h2 { 
            font-size: 1.5rem; 
            font-weight: 400; 
            opacity: 0.95; 
            position: relative;
            z-index: 2;
        }
        
        .main-content { 
            padding: 50px; 
            position: relative;
        }
        
        .section { 
            margin-bottom: 40px; 
        }
        
        .section-title {
            font-size: 1.8rem; 
            color: var(--primary-color); 
            padding-bottom: 12px;
            margin-bottom: 30px; 
            border-bottom: 3px solid var(--light-gray); 
            letter-spacing: 0.5px;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .contact-info {
            display: flex; 
            justify-content: center; 
            flex-wrap: wrap; 
            gap: 15px 40px;
            padding: 25px 0; 
            border-bottom: 1px solid var(--border-color); 
            margin-bottom: 40px;
        }
        
        .contact-item { 
            font-size: 1rem; 
            color: #555; 
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .about-me p { 
            font-size: 1.05rem; 
            text-align: justify;
            color: #444;
        }
        
        .skills-list ul, .experience-item ul { 
            list-style-type: none; 
            padding-left: 5px; 
        }
        
        .skills-list li, .experience-item li {
            font-size: 1.05rem; 
            margin-bottom: 12px; 
            padding-left: 28px; 
            position: relative;
            color: #444;
        }
        
        .skills-list li::before, .experience-item li::before {
            content: '•'; 
            position: absolute; 
            left: 0; 
            color: var(--primary-color); 
            font-weight: bold;
            font-size: 1.4rem;
            line-height: 1;
        }
        
        .item { 
            margin-bottom: 35px; 
        }
        
        .item-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: baseline; 
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        
        .item-title { 
            font-size: 1.25rem; 
            color: var(--text-color); 
            font-weight: 600; 
        }
        
        .item-subtitle { 
            font-size: 1.1rem; 
            color: var(--secondary-color); 
            font-weight: 500;
            margin-top: 2px;
        }
        
        .item-date { 
            font-size: 0.95rem; 
            color: #666; 
            font-style: italic; 
            background: var(--light-gray);
            padding: 3px 10px;
            border-radius: 12px;
            white-space: nowrap;
        }
        
        .item-description { 
            font-size: 1.05rem; 
            margin-top: 10px; 
            color: #444;
        }
        
        .certificates-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 25px;
            margin-top: 20px;
        }
        
        .certificate-thumbnail {
            border: 1px solid var(--border-color); 
            border-radius: 12px; 
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05); 
            background: white;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .certificate-thumbnail:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .certificate-thumbnail img { 
            width: 100%; 
            height: 160px;
            object-fit: cover;
            display: block; 
            border-bottom: 1px solid var(--border-color);
        }
        
        .certificate-label {
            padding: 12px;
            font-size: 0.9rem;
            text-align: center;
            color: #555;
        }
        
        #pdf-button {
            position: fixed; 
            bottom: 30px; 
            right: 30px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white; 
            border: none; 
            border-radius: 50px;
            padding: 16px 28px; 
            font-size: 1rem; 
            font-weight: 500;
            font-family: 'Poppins', sans-serif; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            cursor: pointer; 
            z-index: 1000; 
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        #pdf-button:hover { 
            box-shadow: 0 12px 30px rgba(0,0,0,0.2); 
            transform: translateY(-3px); 
        }
        
        #pdf-button:active {
            transform: translateY(0);
        }
        
        #pdf-button.hidden { 
            opacity: 0; 
            pointer-events: none; 
        }
        
        /* ESTILOS ESPECÍFICOS PARA PDF */
        .pdf-render-container { 
            position: absolute; 
            left: -9999px; 
            top: 0;
            width: 210mm;
        }
        
        .pdf-page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            background: #fff;
            box-sizing: border-box;
            page-break-after: always;
            position: relative;
            overflow: hidden;
        }
        
        .pdf-page:last-child {
            page-break-after: auto;
        }
        
        .pdf-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color), var(--tertiary-color));
            color: white;
            padding: 30px 0;
            text-align: center;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        
        .pdf-profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.85);
            object-fit: cover;
            margin-bottom: 15px;
        }
        
        .pdf-certificate-page {
            width: 210mm;
            height: 297mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20mm;
            background: #fff;
            box-sizing: border-box;
            page-break-after: always;
        }
        
        .pdf-certificate-image {
            max-width: 100%;
            max-height: 240mm;
            object-fit: contain;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        
        .pdf-certificate-title {
            margin-top: 15px;
            font-size: 1.2rem;
            color: var(--primary-color);
            text-align: center;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .container { 
                margin: 0; 
                border-radius: 0; 
                box-shadow: none;
            }
            
            .main-content { 
                padding: 30px; 
            }
            
            .header {
                padding: 40px 20px;
            }
            
            .contact-info {
                gap: 12px;
                padding: 20px 0;
            }
            
            .certificates-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 15px;
            }
            
            #pdf-button {
                bottom: 20px;
                right: 20px;
                padding: 12px 20px;
                font-size: 0.9rem;
            }
        }
        
        @media print {
            body {
                background: none;
            }
            
            .container {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
            
            #pdf-button {
                display: none;
            }
        }
    </style>
</head>
<body>

<button id="pdf-button">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    Gerar PDF Completo
</button>

<div class="container" id="resume-container">
    <header class="header">
        <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk@main/upscalemedia-transformed-_3_.webp" alt="Foto de Bianca Azevedo de Souza" class="profile-pic"/>
        <h1>BIANCA AZEVEDO DE SOUZA</h1>
        <h2>Psicóloga e Psicopedagoga</h2>
    </header>
    <div class="main-content">
        <div class="contact-info">
            <span class="contact-item">📞 +55 34 99104-6998</span>
            <span class="contact-item">✉️ bbcazvd@gmail.com</span>
            <span class="contact-item">📍 Uberlândia, Minas Gerais, Brasil</span>
        </div>
        <section class="section about-me">
            <h3 class="section-title">Sobre Mim</h3>
            <p>Psicóloga formada pela Universidade Federal de Uberlândia, com especialização em Psicopedagogia. Minha carreira é dedicada ao atendimento de crianças, adolescentes e adultos no espectro autista. Oriento meus estudos e prática na Psicologia Histórico-Cultural, com ênfase na intersecção entre arte e desenvolvimento socioemocional. Além da atuação clínica, possuo formação em artes visuais e experiência como professora de artes, aplicando essa bagagem para enriquecer as intervenções terapêuticas.</p>
        </section>
        <section class="section">
            <h3 class="section-title">Experiência Profissional</h3>
            <div class="item">
                <div class="item-header">
                    <div>
                        <h4 class="item-title">Psicóloga e Psicopedagoga</h4>
                        <p class="item-subtitle">CEEM - Centro Especializado Educa Mais</p>
                    </div>
                    <span class="item-date">Janeiro 2024 – Atualmente</span>
                </div>
                <p class="item-description">Atuação como psicóloga infantil e psicopedagoga para alunos da Educação Especial, desenvolvendo estratégias personalizadas de aprendizagem e acompanhamento psicológico.</p>
            </div>
            <div class="item">
                <div class="item-header">
                    <div>
                        <h4 class="item-title">ABA Escolar</h4>
                        <p class="item-subtitle">Clínica Mundo ABA</p>
                    </div>
                    <span class="item-date">Fevereiro 2023 – Dezembro 2023</span>
                </div>
                <p class="item-description">Acompanhamento de alunos da Educação Especial, mediando o processo de aprendizagem através da metodologia ABA (Applied Behavior Analysis).</p>
            </div>
            <div class="item">
                <div class="item-header">
                    <div>
                        <h4 class="item-title">Acompanhante Terapêutica</h4>
                        <p class="item-subtitle">Clínica Elementar</p>
                    </div>
                    <span class="item-date">Setembro 2022 – Janeiro 2023</span>
                </div>
                <p class="item-description">Aplicação das terapias ABA e DENVER em contextos domiciliares e escolares, com foco no desenvolvimento de habilidades sociais e comunicativas.</p>
            </div>
            <div class="item">
                <div class="item-header">
                    <div>
                        <h4 class="item-title">Apoio Educacional - Projeto Incluir</h4>
                        <p class="item-subtitle">ESEBA / UFU</p>
                    </div>
                    <span class="item-date">Março 2020 – Março 2022</span>
                </div>
                <ul class="experience-item">
                    <li>Mediação do processo de aprendizagem e bem-estar de alunos da Educação Especial.</li>
                    <li>Criação de materiais pedagógicos adaptados para desenvolvimento da leitura e escrita.</li>
                    <li>Participação ativa no planejamento pedagógico da equipe multidisciplinar.</li>
                    <li>Implementação de estratégias inclusivas em sala de aula regular.</li>
                </ul>
            </div>
        </section>
        <section class="section">
            <h3 class="section-title">Formação Acadêmica</h3>
            <div class="item">
                <div class="item-header">
                    <div>
                        <h4 class="item-title">Pós-Graduação em Psicopedagogia Institucional e Clínica</h4>
                        <p class="item-subtitle">Faculdade Iguaçu</p>
                    </div>
                    <span class="item-date">Concluído em 2024</span>
                </div>
                <p class="item-description">Especialização focada em diagnóstico e intervenção psicopedagógica, abordando dificuldades de aprendizagem em diversos contextos.</p>
            </div>
            <div class="item">
                <div class="item-header">
                    <div>
                        <h4 class="item-title">Graduação em Psicologia</h4>
                        <p class="item-subtitle">Universidade Federal de Uberlândia (UFU)</p>
                    </div>
                    <span class="item-date">Concluído em 2022</span>
                </div>
                <p class="item-description">Formação generalista em Psicologia com ênfase em processos de desenvolvimento humano e aprendizagem.</p>
            </div>
        </section>
        <section class="section skills-list">
            <h3 class="section-title">Habilidades e Competências</h3>
            <ul>
                <li><strong>Terapias Especializadas:</strong> ABA, DENVER II, DIRFloortime, Integração Sensorial</li>
                <li><strong>Desenvolvimento Socioemocional:</strong> Habilidades sociais, regulação emocional, comunicação alternativa (PECS)</li>
                <li><strong>Psicopedagogia:</strong> Avaliação psicopedagógica, intervenção clínica, adaptação curricular</li>
                <li><strong>Idiomas:</strong> Inglês avançado (leitura, escrita e conversação)</li>
                <li><strong>Artes:</strong> Desenho artístico, pintura em aquarela, arte-terapia</li>
                <li><strong>Tecnologia:</strong> Pacote Office, plataformas de teleatendimento, softwares educacionais</li>
            </ul>
        </section>
        <section class="section certificates-section">
            <h3 class="section-title">Certificados e Cursos</h3>
            <div class="certificates-grid">
                <div class="certificate-thumbnail">
                    <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk/Curriculo-2025BBK-8.webp" alt="Certificado de Curso" loading="lazy"/>
                    <div class="certificate-label">Intervenção ABA para TEA</div>
                </div>
                <div class="certificate-thumbnail">
                    <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk/Curriculo-2025BBK-9.webp" alt="Certificado de Curso" loading="lazy"/>
                    <div class="certificate-label">Comunicação Alternativa</div>
                </div>
                <div class="certificate-thumbnail">
                    <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk/Curriculo-2025BBK-7.webp" alt="Certificado de Curso" loading="lazy"/>
                    <div class="certificate-label">Psicopedagogia Clínica</div>
                </div>
                <div class="certificate-thumbnail">
                    <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk/Curriculo-2025BBK-6.webp" alt="Certificado de Curso" loading="lazy"/>
                    <div class="certificate-label">DIRFloortime Básico</div>
                </div>
                <div class="certificate-thumbnail">
                    <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk/Curriculo-2025BBK-5.webp" alt="Certificado de Curso" loading="lazy"/>
                    <div class="certificate-label">Integração Sensorial</div>
                </div>
                <div class="certificate-thumbnail">
                    <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk/Curriculo-2025BBK-10.webp" alt="Certificado de Curso" loading="lazy"/>
                    <div class="certificate-label">DENVER II Avançado</div>
                </div>
                <div class="certificate-thumbnail">
                    <img src="https://cdn.jsdelivr.net/gh/semtetog/bbjk/Curriculo-2025BBK-4.webp" alt="Certificado de Curso" loading="lazy"/>
                    <div class="certificate-label">Arte-Terapia</div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pdfButton = document.getElementById('pdf-button');
    
    pdfButton.addEventListener('click', async () => {
        pdfButton.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            Preparando PDF...
        `;
        pdfButton.classList.add('hidden');
        
        try {
            // 1. Criar container temporário para renderização do PDF
            const renderContainer = document.createElement('div');
            renderContainer.classList.add('pdf-render-container');
            document.body.appendChild(renderContainer);
            
            // 2. Criar página principal do currículo
            const resumePage = document.createElement('div');
            resumePage.classList.add('pdf-page');
            
            // Clonar o conteúdo principal, removendo os certificados
            const resumeContent = document.getElementById('resume-container').cloneNode(true);
            resumeContent.querySelector('.certificates-section').remove();
            
            // Ajustar estilos específicos para PDF
            resumeContent.querySelector('.header').classList.add('pdf-header');
            resumeContent.querySelector('.profile-pic').classList.add('pdf-profile-pic');
            
            resumePage.appendChild(resumeContent);
            renderContainer.appendChild(resumePage);
            
            // 3. Criar páginas individuais para cada certificado
            const certificates = document.querySelectorAll('.certificate-thumbnail');
            const certificateTitles = [
                "Intervenção ABA para TEA",
                "Comunicação Alternativa",
                "Psicopedagogia Clínica",
                "DIRFloortime Básico",
                "Integração Sensorial",
                "DENVER II Avançado",
                "Arte-Terapia"
            ];
            
            certificates.forEach((cert, index) => {
                const certPage = document.createElement('div');
                certPage.classList.add('pdf-certificate-page');
                
                const img = cert.querySelector('img').cloneNode();
                img.classList.add('pdf-certificate-image');
                
                const title = document.createElement('h3');
                title.classList.add('pdf-certificate-title');
                title.textContent = certificateTitles[index] || "Certificado Profissional";
                
                certPage.appendChild(img);
                certPage.appendChild(title);
                renderContainer.appendChild(certPage);
            });
            
            // 4. Configurações avançadas para geração do PDF
            const opt = {
                margin: 0,
                filename: 'Curriculo_Bianca_Azevedo_Souza_Completo.pdf',
                image: { 
                    type: 'jpeg', 
                    quality: 0.98 
                },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    letterRendering: true,
                    allowTaint: true
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait',
                    hotfixes: ["px_scaling"]
                },
                pagebreak: {
                    mode: ['avoid-all', 'css', 'legacy']
                }
            };
            
            // 5. Gerar e salvar o PDF
            await html2pdf().set(opt).from(renderContainer).save();
            
            // 6. Limpeza e restauração do estado inicial
            document.body.removeChild(renderContainer);
            
        } catch (error) {
            console.error("Erro ao gerar PDF:", error);
            alert("Ocorreu um erro ao gerar o PDF. Por favor, tente novamente.");
        } finally {
            pdfButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Gerar PDF Completo
            `;
            pdfButton.classList.remove('hidden');
        }
    });
});
</script>
</body>
</html>