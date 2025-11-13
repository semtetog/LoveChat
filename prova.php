<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Venda</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            line-height: 1.4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .prova-item {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .prova-img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 5px auto;
            border: 1px solid #ddd;
        }
        @media print {
            body {
                padding: 0;
            }
            .prova-item {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="prova-item">
            <img src="https://i.ibb.co/8k2ZG50/Prova1.jpg" alt="Prova 1" class="prova-img">
        </div>

        <div class="prova-item">
            <img src="https://i.ibb.co/DfRkXqjS/Prova2.jpg" alt="Prova 2" class="prova-img">
        </div>

        <div class="prova-item">
            <img src="https://i.ibb.co/LDyttNH9/Prova3.jpg" alt="Prova 3" class="prova-img">
        </div>

        <div class="prova-item">
            <img src="https://i.ibb.co/gZQjQzD5/Prova4.jpg" alt="Prova 4" class="prova-img">
        </div>
    </div>
</body>
</html>