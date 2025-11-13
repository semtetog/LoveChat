<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio Hotel Reflexos - Terça-Feira Premium</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Fontes do Google */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@700;900&display=swap');

        /* Cores inspiradas na placa e buscando um toque premium */
        :root {
            --cor-vermelho-hotel: #C00027; /* Um vermelho forte e elegante, inspirado na placa */
            --cor-verde-detalhe: #6A8D73; /* Um verde mais sóbrio e premium, inspirado no telefone */
            --cor-fundo-principal: #FDF8F5; /* Um branco levemente off-white, tom quente */
            --cor-texto-escuro: #2C2C2C;
            --cor-texto-claro: #FFFFFF;
            --cor-dourado-suave: #B08D57; /* Um toque de dourado para detalhes premium */
            --cor-sombra: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px 0; /* Espaço acima e abaixo */
            background-color: #EAE0D5; /* Um fundo bege/cinza claro para contrastar com o cardápio */
            color: var(--cor-texto-escuro);
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Para permitir scroll se o cardápio for alto */
            min-height: 100vh;
        }

        .cardapio-container {
            background-color: var(--cor-fundo-principal);
            width: 90%;
            max-width: 700px; /* Largura do cardápio */
            border-radius: 15px;
            box-shadow: 0 10px 30px var(--cor-sombra);
            overflow: hidden; /* Para garantir que os cantos arredondados funcionem com o header */
            margin-bottom: 20px; /* Espaço no final */
        }

        .cardapio-header {
            background: linear-gradient(135deg, var(--cor-vermelho-hotel) 70%, #A00020 100%);
            color: var(--cor-texto-claro);
            padding: 30px 20px;
            text-align: center;
            position: relative;
        }

        .cardapio-header::before { /* Efeito sutil de brilho */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top left, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 50%);
            pointer-events: none;
        }

        .cardapio-header h1 { /* "CARDÁPIO" */
            font-family: 'Playfair Display', serif;
            font-size: 3.5em;
            margin: 0;
            font-weight: 900;
            letter-spacing: 2px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .cardapio-header .data-cardapio {
            font-family: 'Poppins', sans-serif;
            font-size: 1.2em;
            margin-top: 5px;
            opacity: 0.9;
            font-weight: 300;
            text-transform: uppercase;
        }

        .hotel-info {
            padding: 25px 20px;
            text-align: center;
            background-color: #fff; /* Um pouco mais branco que o fundo principal para leve contraste */
            border-bottom: 1px solid #eee;
        }

        .hotel-info h2 { /* "HOTEL REFLEXOS" */
            font-family: 'Playfair Display', serif;
            font-size: 2.5em;
            color: var(--cor-vermelho-hotel);
            margin: 0;
            font-weight: 700;
        }
        
        .hotel-info .telefone-placa {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3em;
            color: var(--cor-verde-detalhe);
            margin-top: 8px;
            font-weight: 600;
            letter-spacing: 1px;
        }
         .hotel-info .telefone-placa i {
            margin-right: 8px;
         }


        .cardapio-corpo {
            padding: 30px;
        }

        .secao {
            margin-bottom: 30px;
        }

        .secao h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.6em;
            color: var(--cor-texto-escuro);
            margin-bottom: 15px;
            font-weight: 600;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--cor-verde-detalhe);
            display: inline-block; /* Para a borda não ocupar a linha toda */
        }

        .lista-pratos {
            list-style: none;
            padding-left: 0;
        }

        .lista-pratos li {
            font-size: 1.1em;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
            display: flex;
            align-items: center;
            font-weight: 400;
        }
        .lista-pratos li:last-child {
            border-bottom: none;
        }
        .lista-pratos li i { /* Ícone antes do prato */
            color: var(--cor-verde-detalhe);
            margin-right: 12px;
            font-size: 1.2em;
        }

        .salada-destaque {
            background-color: #E8F5E9; /* Um verde bem clarinho */
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            font-size: 1.15em;
            font-weight: 600;
            color: var(--cor-verde-detalhe);
            border: 1px solid var(--cor-verde-detalhe);
        }
        .salada-destaque i {
            margin-right: 8px;
        }

        .precos-opcoes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .opcao-preco {
            background-color: var(--cor-texto-claro);
            border: 1px solid #E0E0E0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .opcao-preco:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .opcao-preco strong { /* P, M, G */
            font-family: 'Poppins', sans-serif;
            font-size: 1.3em;
            color: var(--cor-texto-escuro);
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .opcao-preco .valor {
            font-family: 'Poppins', sans-serif;
            font-size: 2em;
            color: var(--cor-vermelho-hotel);
            font-weight: 700;
            margin-bottom: 5px;
        }
        .opcao-preco .valor small {
            font-size: 0.6em;
            font-weight: 400;
        }

        .opcao-preco .peso {
            font-size: 0.9em;
            color: #777;
        }

        .pedido-info {
            margin-top: 30px;
            padding: 25px;
            background-color: #f9f9f9;
            border-radius: 10px;
            text-align: center;
        }

        .pedido-info .taxa-entrega {
            font-size: 1.1em;
            margin-bottom: 15px;
        }
        .pedido-info .taxa-entrega span {
            font-weight: 700;
            color: var(--cor-vermelho-hotel);
        }

        .botao-whatsapp {
            display: inline-block;
            background: linear-gradient(45deg, var(--cor-verde-detalhe), #5A7A63);
            color: var(--cor-texto-claro);
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            font-size: 1.2em;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            border: none;
        }
        .botao-whatsapp:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            background: linear-gradient(45deg, #5A7A63, var(--cor-verde-detalhe));
        }
        .botao-whatsapp i {
            margin-right: 10px;
        }
        .contato-alternativo {
            margin-top:15px;
            font-size: 0.9em;
            color: #555;
        }

        .rodape-cardapio {
            text-align: center;
            padding: 20px;
            font-size: 0.85em;
            color: #777;
            border-top: 1px solid #eee;
            margin-top: 10px; /* Ajuste o espaçamento do topo */
        }

        /* Efeito sutil de entrada para elementos */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .secao, .precos-opcoes > div, .pedido-info {
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0; /* Começa invisível */
        }
        /* Delay para animações */
        .lista-pratos li:nth-child(1) { animation-delay: 0.2s; }
        .lista-pratos li:nth-child(2) { animation-delay: 0.3s; }
        .lista-pratos li:nth-child(3) { animation-delay: 0.4s; }
        /* ... e assim por diante, ou use JS para um delay dinâmico */
        .precos-opcoes .opcao-preco:nth-child(1) { animation-delay: 0.5s; }
        .precos-opcoes .opcao-preco:nth-child(2) { animation-delay: 0.6s; }
        .precos-opcoes .opcao-preco:nth-child(3) { animation-delay: 0.7s; }
        .pedido-info { animation-delay: 0.8s; }


        /* Responsividade */
        @media (max-width: 600px) {
            body { padding: 10px 0; }
            .cardapio-container { width: 95%; }
            .cardapio-header h1 { font-size: 2.8em; }
            .cardapio-header .data-cardapio { font-size: 1em; }
            .hotel-info h2 { font-size: 2em; }
            .hotel-info .telefone-placa { font-size: 1.1em; }
            .secao h3 { font-size: 1.4em; }
            .lista-pratos li { font-size: 1em; }
            .opcao-preco .valor { font-size: 1.7em; }
            .botao-whatsapp { font-size: 1.1em; padding: 12px 25px; }
        }

    </style>
</head>
<body>

    <div class="cardapio-container">
        <header class="cardapio-header">
            <h1>CARDÁPIO</h1>
            <p class="data-cardapio">Terça-Feira Especial</p>
        </header>

        <section class="hotel-info">
            <h2>HOTEL REFLEXOS</h2>
            <p class="telefone-placa"><i class="fas fa-phone-alt"></i>99159-2073</p>
        </section>

        <div class="cardapio-corpo">
            <section class="secao">
                <h3><i class="fas fa-utensils"></i> Pratos do Dia</h3>
                <ul class="lista-pratos">
                    <li><i class="fas fa-circle fa-xs"></i>Arroz branco</li>
                    <li><i class="fas fa-circle fa-xs"></i>Feijão de caldo</li>
                    <li><i class="fas fa-circle fa-xs"></i>Costelinha de porco</li>
                    <li><i class="fas fa-circle fa-xs"></i>Bife bovino acebolado</li>
                    <li><i class="fas fa-circle fa-xs"></i>Farofa do chefe</li>
                    <li><i class="fas fa-circle fa-xs"></i>Macarrão parafuso ao sugo</li>
                    <li><i class="fas fa-circle fa-xs"></i>Seleta de legumes variados</li>
                    <li><i class="fas fa-circle fa-xs"></i>Purê de batata</li>
                </ul>
            </section>

            <div class="salada-destaque">
                <i class="fas fa-leaf"></i> Salada Fresquinha Separada Inclusa! <i class="fas fa-carrot"></i>
            </div>

            <section class="secao">
                <h3><i class="fas fa-dollar-sign"></i> Opções e Valores</h3>
                <div class="precos-opcoes">
                    <div class="opcao-preco">
                        <strong>Pequeno</strong>
                        <p class="valor"><small>R$</small>14<small>,00</small></p>
                        <p class="peso">500g</p>
                    </div>
                    <div class="opcao-preco">
                        <strong>Médio</strong>
                        <p class="valor"><small>R$</small>18<small>,00</small></p>
                        <p class="peso">750g</p>
                    </div>
                    <div class="opcao-preco">
                        <strong>Grande</strong>
                        <p class="valor"><small>R$</small>22<small>,00</small></p>
                        <p class="peso">1100g</p>
                    </div>
                </div>
            </section>

            <section class="pedido-info">
                <p class="taxa-entrega">Taxa de Entrega: <span>R$ 4,00</span></p>
                <a href="https://wa.me/5534997238864?text=Olá!%20Gostaria%20de%20fazer%20um%20pedido%20do%20cardápio%20de%20Terça-Feira%20do%20Hotel%20Reflexos." target="_blank" class="botao-whatsapp">
                    <i class="fab fa-whatsapp"></i> Fazer Pedido Agora
                </a>
                 <p class="contato-alternativo">Ou ligue: (34) 99723-8864</p>
            </section>
        </div>

        <footer class="rodape-cardapio">
            <p>© <?php echo date("Y"); ?> Hotel Reflexos. Qualidade e sabor que refletem em você!</p>
        </footer>

    </div>

    <script>
        // Pequeno script para adicionar delays de animação mais dinamicamente (opcional)
        document.addEventListener('DOMContentLoaded', function() {
            const animatedItems = document.querySelectorAll('.lista-pratos li, .precos-opcoes > div, .pedido-info');
            animatedItems.forEach((item, index) => {
                if (!item.style.animationDelay) { // Só aplica se não tiver um delay fixo no CSS
                    item.style.animationDelay = `${index * 0.07}s`;
                }
            });

            // Forçar o telefone da placa a não ser um link se não for o desejado
            // (o navegador pode tentar interpretar números como telefones)
            const telPlaca = document.querySelector('.hotel-info .telefone-placa');
            if(telPlaca) {
                telPlaca.addEventListener('click', (e) => e.preventDefault());
            }
        });
    </script>

</body>
</html>