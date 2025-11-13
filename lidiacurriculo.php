<?php
// Dados do currículo
$dados = [
    'nome' => 'Lídia Lacerda Gonçalves',
    'titulo' => 'Psicologia em Formação | Atendimento ao Cliente',
    'contato' => [
        'telefone' => '(34) 99886-0586',
        'email' => 'lidialacer18@gmail.com',
        'endereco' => 'Santa Mônica, Uberlândia - MG'
    ],
    'foto' => 'https://i.ibb.co/ZpnyKZJb/lidia-com-camiseta.png',
    'objetivo' => 'Busco uma oportunidade para adquirir experiência no mercado de trabalho, com foco em desenvolvimento profissional e contribuição para equipes dinâmicas. Comprometida com aprendizado contínuo e excelência no atendimento.',
    'experiencias' => [
        [
            'cargo' => 'Consultora Comercial/Assistente Administrativo',
            'empresa' => 'Martins/SIMTECH - Sistema Martins Tecnologia',
            'periodo' => '2 meses',
            'detalhes' => 'Suporte administrativo e comercial, com foco em organização e eficiência nas atividades do dia a dia.'
        ],
        [
            'cargo' => 'Garçonete',
            'empresa' => 'Burg And Barrel Expo',
            'periodo' => '5 meses',
            'detalhes' => 'Atendimento ao cliente, preparação de pratos e interface entre cozinha e salão.'
        ],
        [
            'cargo' => 'Sushiman/Atendente',
            'empresa' => 'AFC Sushi',
            'periodo' => '5 meses',
            'detalhes' => 'Preparação de pratos em restaurante chinês e garantia de experiência gastronômica de qualidade.'
        ],
        [
            'cargo' => 'Esteticista de Animais Domésticos',
            'empresa' => 'Sunny Pets',
            'periodo' => '6 meses',
            'detalhes' => 'Realização de serviços de estética em animais, proporcionando cuidados e bem-estar aos pets.'
        ]
    ],
    'formacao' => [
        [
            'curso' => 'Psicologia',
            'instituicao' => 'UNITRI - Centro Universitário do Triângulo',
            'periodo' => '2024 - 2028 (Cursando)'
        ],
        [
            'curso' => 'Ensino Médio',
            'instituicao' => 'North Kansas City High School',
            'periodo' => 'Concluído em 2022'
        ]
    ],
    'habilidades' => [
        'Atendimento ao Cliente',
        'Microsoft Office (Word, Excel, PowerPoint)',
        'Organização e Gestão de Tempo',
        'Trabalho em Equipe',
        'Adaptabilidade'
    ],
    'idiomas' => [
        'Inglês - Fluente (C1)',
        'Espanhol - Intermediário (B1)'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Currículo - <?php echo $dados['nome']; ?></title>
    <style>
        :root {
            --verde-musgo-escuro: #4A5D23;
            --verde-musgo: #6B8E23;
            --verde-musgo-claro: #8FBC8F;
            --verde-claro-suave: #D8E2D0;
            --cinza-escuro: #333333;
            --cinza-medio: #555555;
            --cinza-claro: #f5f5f5;
            --branco: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--cinza-claro);
            color: var(--cinza-escuro);
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: var(--branco);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--verde-musgo-escuro), var(--verde-musgo));
            color: white;
            padding: 30px;
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .foto-perfil {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            margin-right: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .info-pessoal {
            flex: 1;
        }
        
        .nome {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .titulo {
            font-size: 18px;
            font-weight: 400;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .contato {
            font-size: 14px;
        }
        
        .contato div {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .contato i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        .secao {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
        }
        
        .secao:last-child {
            border-bottom: none;
        }
        
        .titulo-secao {
            color: var(--verde-musgo);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--verde-claro-suave);
            display: flex;
            align-items: center;
        }
        
        .titulo-secao i {
            margin-right: 10px;
        }
        
        .experiencia-item, .formacao-item {
            margin-bottom: 20px;
        }
        
        .cargo, .curso {
            font-weight: 600;
            font-size: 17px;
            color: var(--cinza-escuro);
        }
        
        .empresa, .instituicao {
            font-weight: 500;
            color: var(--verde-musgo);
            display: inline-block;
            margin-right: 10px;
        }
        
        .periodo {
            color: var(--cinza-medio);
            font-size: 14px;
            display: inline-block;
        }
        
        .detalhes {
            margin-top: 8px;
            font-size: 15px;
        }
        
        .habilidades {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .habilidade {
            background: linear-gradient(to right, var(--verde-claro-suave), var(--branco));
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            border: 1px solid #e0e0e0;
        }
        
        .idiomas {
            display: flex;
            gap: 20px;
        }
        
        .idioma {
            flex: 1;
        }
        
        .nivel {
            height: 8px;
            background-color: #e0e0e0;
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .nivel-preenchimento {
            height: 100%;
            background: linear-gradient(to right, var(--verde-musgo), var(--verde-musgo-escuro));
            border-radius: 4px;
        }
        
        .ingles .nivel-preenchimento { width: 100%; }
        .espanhol .nivel-preenchimento { width: 60%; }
        
        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--verde-musgo), var(--verde-musgo-escuro));
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            font-weight: 600;
            display: flex;
            align-items: center;
            z-index: 100;
        }
        
        .btn-print i {
            margin-right: 8px;
        }

        /* ======================================================= */
        /* ESTILOS PARA IMPRESSÃO - A MÁGICA PARA CABER EM UMA PÁGINA */
        /* ======================================================= */
        @media print {
            /* --- Reset e Configurações Gerais para Impressão --- */
            body {
                background: none;
                font-size: 10pt; /* Tamanho de fonte base para impressão */
                line-height: 1.3;
            }
            
            .container {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
                width: 100%;
                max-width: 100%;
                border: none;
            }
            
            .no-print {
                display: none !important; /* Esconde o botão de imprimir */
            }

            /* --- Ajustes de Espaçamento e Fonte para Otimização --- */
            .header {
                padding: 20px; /* Reduz o padding do cabeçalho */
                background: var(--verde-musgo-escuro) !important; /* Usa cor sólida para economizar tinta e garantir consistência */
                -webkit-print-color-adjust: exact; /* Força a impressão das cores de fundo no Chrome/Safari */
                print-color-adjust: exact; /* Padrão para forçar a impressão das cores de fundo */
            }
            
            .foto-perfil {
                width: 100px; /* Diminui a foto */
                height: 100px;
                margin-right: 20px;
                border-width: 3px;
            }

            .nome {
                font-size: 22pt; /* Reduz a fonte do nome */
                margin-bottom: 2px;
            }

            .titulo {
                font-size: 12pt; /* Reduz a fonte do título */
                margin-bottom: 10px;
            }
            
            .contato {
                font-size: 9pt; /* Reduz a fonte do contato */
            }

            .contato div {
                margin-bottom: 2px; /* Diminui o espaço entre os contatos */
            }

            .secao {
                padding: 12px 25px; /* Reduz drasticamente o padding das seções */
                page-break-inside: avoid; /* Tenta evitar que a seção quebre entre páginas */
            }
            
            .titulo-secao {
                font-size: 14pt;
                margin-bottom: 8px;
                padding-bottom: 4px;
            }
            
            .experiencia-item, .formacao-item {
                margin-bottom: 10px; /* Reduz a margem entre os itens de experiência/formação */
                page-break-inside: avoid;
            }

            .cargo, .curso {
                font-size: 11pt;
            }
            
            .periodo {
                font-size: 9pt;
            }

            .detalhes {
                margin-top: 3px;
                font-size: 9pt;
            }
            
            .habilidades {
                gap: 8px; /* Reduz o espaçamento entre as habilidades */
            }

            .habilidade {
                padding: 4px 10px; /* Diminui o padding das pílulas de habilidade */
                font-size: 9pt;
                background: #f0f0f0 !important; /* Cor sólida para impressão */
                border: 1px solid #ccc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .idioma {
                font-size: 10pt;
            }

            .nivel-preenchimento {
                background: var(--verde-musgo-escuro) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <?php if (!empty($dados['foto'])): ?>
                <img src="<?php echo htmlspecialchars($dados['foto']); ?>" alt="Foto de perfil" class="foto-perfil">
            <?php endif; ?>
            <div class="info-pessoal">
                <h1 class="nome"><?php echo htmlspecialchars($dados['nome']); ?></h1>
                <p class="titulo"><?php echo htmlspecialchars($dados['titulo']); ?></p>
                <div class="contato">
                    <?php if (!empty($dados['contato']['telefone'])): ?>
                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($dados['contato']['telefone']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($dados['contato']['email'])): ?>
                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($dados['contato']['email']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($dados['contato']['endereco'])): ?>
                        <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($dados['contato']['endereco']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <section class="secao">
            <h2 class="titulo-secao"><i class="fas fa-bullseye"></i> Objetivo</h2>
            <p><?php echo htmlspecialchars($dados['objetivo']); ?></p>
        </section>
        
        <section class="secao">
            <h2 class="titulo-secao"><i class="fas fa-briefcase"></i> Experiência Profissional</h2>
            <?php foreach ($dados['experiencias'] as $exp): ?>
                <div class="experiencia-item">
                    <div>
                        <span class="cargo"><?php echo htmlspecialchars($exp['cargo']); ?></span>
                        <span class="empresa"><?php echo htmlspecialchars($exp['empresa']); ?></span>
                        <span class="periodo"><?php echo htmlspecialchars($exp['periodo']); ?></span>
                    </div>
                    <p class="detalhes"><?php echo htmlspecialchars($exp['detalhes']); ?></p>
                </div>
            <?php endforeach; ?>
        </section>
        
        <section class="secao">
            <h2 class="titulo-secao"><i class="fas fa-graduation-cap"></i> Formação Acadêmica</h2>
            <?php foreach ($dados['formacao'] as $form): ?>
                <div class="formacao-item">
                    <div>
                        <span class="curso"><?php echo htmlspecialchars($form['curso']); ?></span>
                        <span class="instituicao"><?php echo htmlspecialchars($form['instituicao']); ?></span>
                        <span class="periodo"><?php echo htmlspecialchars($form['periodo']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        
        <section class="secao">
            <h2 class="titulo-secao"><i class="fas fa-star"></i> Habilidades</h2>
            <div class="habilidades">
                <?php foreach ($dados['habilidades'] as $hab): ?>
                    <div class="habilidade"><?php echo htmlspecialchars($hab); ?></div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <section class="secao">
            <h2 class="titulo-secao"><i class="fas fa-language"></i> Idiomas</h2>
            <div class="idiomas">
                <div class="idioma ingles">
                    <div>Inglês <small>(Fluente - C1)</small></div>
                    <div class="nivel"><div class="nivel-preenchimento"></div></div>
                </div>
                <div class="idioma espanhol">
                    <div>Espanhol <small>(Intermediário - B1)</small></div>
                    <div class="nivel"><div class="nivel-preenchimento"></div></div>
                </div>
            </div>
        </section>
    </div>
    
    <button class="btn-print no-print" onclick="window.print()">
        <i class="fas fa-file-pdf"></i> Gerar PDF
    </button>
    
    <script>
        // Este script é apenas para um efeito visual na tela, não afeta a impressão.
        document.addEventListener('DOMContentLoaded', function() {
            const elementos = document.querySelectorAll('.secao, .header');
            elementos.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>