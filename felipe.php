<?php
// Habilitar exibição de erros para depuração (REMOVER em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ======================================================================
// DADOS ESSENCIAIS (Baseado nas planilhas e padronizações fornecidas)
// *** VERIFIQUE E COMPLETE ESTES DADOS CUIDADOSAMENTE ***
// ======================================================================

// 1. Alimentos DB (Nutrientes por 100g) - IDs inventados, use os reais se disponíveis
$alimentos_db = [
    // Grãos e Cereais
    2 => ['nome' => 'Arroz, tipo 1, cru', 'kcal' => 357.79, 'proteina' => 7.16, 'lipideos' => 0.34, 'carboidratos' => 78.76, 'calcio' => 4.41, 'ferro' => 0.68, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 1.02],
    10 => ['nome' => 'Macarrão, trigo, cru, com ovos', 'kcal' => 370.57, 'proteina' => 10.32, 'lipideos' => 1.97, 'carboidratos' => 76.62, 'calcio' => 19.45, 'ferro' => 0.92, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 14.74], // Para Parafuso/Espaguete
    27 => ['nome' => 'Farinha, de mandioca, torrada', 'kcal' => 365.27, 'proteina' => 1.23, 'lipideos' => 0.29, 'carboidratos' => 89.19, 'calcio' => 75.53, 'ferro' => 1.19, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 10.31],
    50 => ['nome' => 'Farinha de trigo', 'kcal' => 360.47, 'proteina' => 9.79, 'lipideos' => 1.37, 'carboidratos' => 75.09, 'calcio' => 17.86, 'ferro' => 0.95, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 0.74], // Para pães/bolos
    51 => ['nome' => 'Amido de milho', 'kcal' => 361.37, 'proteina' => 0.60, 'lipideos' => 0.00, 'carboidratos' => 87.15, 'calcio' => 1.06, 'ferro' => 0.13, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 8.08], // Para Mingau
    52 => ['nome' => 'Trigo para quibe, cru', 'kcal' => 336.00, 'proteina' => 12.30, 'lipideos' => 1.33, 'carboidratos' => 74.90, 'calcio' => 35.00, 'ferro' => 2.46, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 17.00],

    // Leguminosas
    3 => ['nome' => 'Feijão, carioca, cru', 'kcal' => 329.03, 'proteina' => 19.98, 'lipideos' => 1.26, 'carboidratos' => 61.22, 'calcio' => 122.57, 'ferro' => 7.99, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 0.00],
    32 => ['nome' => 'Lentilha, crua', 'kcal' => 339.14, 'proteina' => 23.15, 'lipideos' => 0.77, 'carboidratos' => 62.00, 'calcio' => 53.52, 'ferro' => 7.05, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 0.00],

    // Carnes e Ovos
    4 => ['nome' => 'Frango, coxa, sem pele, crua', 'kcal' => 119.95, 'proteina' => 17.81, 'lipideos' => 4.86, 'carboidratos' => 0.02, 'calcio' => 7.97, 'ferro' => 0.78, 'retinol' => 11.66, 'vitamina_c' => 0.00, 'sodio' => 98.00],
    5 => ['nome' => 'Frango, sobrecoxa, sem pele, crua', 'kcal' => 161.80, 'proteina' => 17.57, 'lipideos' => 9.62, 'carboidratos' => 0.00, 'calcio' => 6.29, 'ferro' => 0.90, 'retinol' => 3.92, 'vitamina_c' => 0.00, 'sodio' => 80.00],
    33 => ['nome' => 'Frango, peito, sem pele, cru', 'kcal' => 119.16, 'proteina' => 21.53, 'lipideos' => 3.02, 'carboidratos' => 0.00, 'calcio' => 7.36, 'ferro' => 0.43, 'retinol' => 2.00, 'vitamina_c' => 0.00, 'sodio' => 56.00],
    6 => ['nome' => 'Carne, bovina, acém, moído, cru', 'kcal' => 136.56, 'proteina' => 19.42, 'lipideos' => 5.95, 'carboidratos' => 0.00, 'calcio' => 2.61, 'ferro' => 1.76, 'retinol' => 2.32, 'vitamina_c' => 0.00, 'sodio' => 49.00], // Exemplo para picadinho/moída
    53 => ['nome' => 'Carne, bovina, músculo, sem gordura, cru', 'kcal' => 141.58, 'proteina' => 21.56, 'lipideos' => 5.49, 'carboidratos' => 0.00, 'calcio' => 3.64, 'ferro' => 1.86, 'retinol' => 1.91, 'vitamina_c' => 0.00, 'sodio' => 66.00], // Exemplo para carne em pedaço
    7 => ['nome' => 'Porco, pernil, cru', 'kcal' => 186.06, 'proteina' => 20.13, 'lipideos' => 11.10, 'carboidratos' => 0.00, 'calcio' => 12.94, 'ferro' => 0.89, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 102.00],
    14 => ['nome' => 'Ovo, de galinha, inteiro, cru', 'kcal' => 143.11, 'proteina' => 13.03, 'lipideos' => 8.90, 'carboidratos' => 1.64, 'calcio' => 42.02, 'ferro' => 1.56, 'retinol' => 78.83, 'vitamina_c' => 0.00, 'sodio' => 168.00],
    54 => ['nome' => 'Peixe, tilápia, filé, cru', 'kcal' => 94.00, 'proteina' => 18.20, 'lipideos' => 2.31, 'carboidratos' => 0.01, 'calcio' => 10.00, 'ferro' => 0.56, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 52.00], // Exemplo para 'Peixe'

    // Laticínios
    15 => ['nome' => 'Leite, de vaca, integral', 'kcal' => 65.00, 'proteina' => 2.93, 'lipideos' => 3.24, 'carboidratos' => 5.92, 'calcio' => 108.00, 'ferro' => 0.08, 'retinol' => 49.70, 'vitamina_c' => 0.00, 'sodio' => 63.80],
    18 => ['nome' => 'Manteiga, com sal', 'kcal' => 725.97, 'proteina' => 0.41, 'lipideos' => 82.36, 'carboidratos' => 0.06, 'calcio' => 9.42, 'ferro' => 0.15, 'retinol' => 923.55, 'vitamina_c' => 0.00, 'sodio' => 578.69],

    // Hortaliças (Tubérculos, Raízes, Legumes, Folhas)
    8 => ['nome' => 'Batata, inglesa, crua', 'kcal' => 64.37, 'proteina' => 1.77, 'lipideos' => 0.00, 'carboidratos' => 14.69, 'calcio' => 3.55, 'ferro' => 0.36, 'retinol' => 0.00, 'vitamina_c' => 31.08, 'sodio' => 0.00],
    12 => ['nome' => 'Batata, doce, crua', 'kcal' => 118.24, 'proteina' => 1.26, 'lipideos' => 0.13, 'carboidratos' => 28.20, 'calcio' => 21.11, 'ferro' => 0.39, 'retinol' => 0.00, 'vitamina_c' => 16.48, 'sodio' => 8.77],
    9 => ['nome' => 'Cenoura, crua', 'kcal' => 34.14, 'proteina' => 1.32, 'lipideos' => 0.17, 'carboidratos' => 7.66, 'calcio' => 22.54, 'ferro' => 0.18, 'retinol' => 835.00, 'vitamina_c' => 5.12, 'sodio' => 3.33], // Retinol TACO
    11 => ['nome' => 'Tomate, com semente, cru', 'kcal' => 15.34, 'proteina' => 1.10, 'lipideos' => 0.17, 'carboidratos' => 3.14, 'calcio' => 6.94, 'ferro' => 0.24, 'retinol' => 0.00, 'vitamina_c' => 21.21, 'sodio' => 1.02],
    13 => ['nome' => 'Beterraba, crua', 'kcal' => 48.83, 'proteina' => 1.95, 'lipideos' => 0.09, 'carboidratos' => 11.11, 'calcio' => 18.11, 'ferro' => 0.32, 'retinol' => 0.00, 'vitamina_c' => 3.12, 'sodio' => 9.72],
    25 => ['nome' => 'Alface, crespa, crua', 'kcal' => 10.68, 'proteina' => 1.35, 'lipideos' => 0.16, 'carboidratos' => 1.70, 'calcio' => 37.98, 'ferro' => 0.40, 'retinol' => 0.00, 'vitamina_c' => 15.58, 'sodio' => 3.38],
    26 => ['nome' => 'Couve, manteiga, crua', 'kcal' => 27.06, 'proteina' => 2.87, 'lipideos' => 0.55, 'carboidratos' => 4.33, 'calcio' => 130.87, 'ferro' => 0.45, 'retinol' => 0.00, 'vitamina_c' => 96.68, 'sodio' => 6.17],
    28 => ['nome' => 'Abobrinha, italiana, crua', 'kcal' => 19.28, 'proteina' => 1.14, 'lipideos' => 0.14, 'carboidratos' => 4.29, 'calcio' => 15.13, 'ferro' => 0.24, 'retinol' => 0.00, 'vitamina_c' => 6.87, 'sodio' => 0.00],
    29 => ['nome' => 'Abóbora, cabotian, crua', 'kcal' => 38.60, 'proteina' => 1.75, 'lipideos' => 0.54, 'carboidratos' => 8.36, 'calcio' => 17.96, 'ferro' => 0.37, 'retinol' => 0.00, 'vitamina_c' => 5.09, 'sodio' => 0.00], // Vit A provavelmente alta
    30 => ['nome' => 'Inhame, cru', 'kcal' => 96.70, 'proteina' => 2.05, 'lipideos' => 0.21, 'carboidratos' => 23.23, 'calcio' => 11.80, 'ferro' => 0.36, 'retinol' => 0.00, 'vitamina_c' => 5.62, 'sodio' => 0.00],
    34 => ['nome' => 'Repolho, branco, cru', 'kcal' => 17.12, 'proteina' => 0.88, 'lipideos' => 0.14, 'carboidratos' => 3.86, 'calcio' => 34.55, 'ferro' => 0.15, 'retinol' => 0.00, 'vitamina_c' => 18.72, 'sodio' => 3.64],
    35 => ['nome' => 'Chuchu, cru', 'kcal' => 16.98, 'proteina' => 0.70, 'lipideos' => 0.06, 'carboidratos' => 4.14, 'calcio' => 11.51, 'ferro' => 0.17, 'retinol' => 0.00, 'vitamina_c' => 10.61, 'sodio' => 0.00],
    55 => ['nome' => 'Mandioca, crua', 'kcal' => 151.42, 'proteina' => 1.13, 'lipideos' => 0.30, 'carboidratos' => 36.17, 'calcio' => 15.19, 'ferro' => 0.27, 'retinol' => 0.00, 'vitamina_c' => 16.53, 'sodio' => 2.15],

    // Frutas
    20 => ['nome' => 'Banana, prata, crua', 'kcal' => 98.25, 'proteina' => 1.27, 'lipideos' => 0.07, 'carboidratos' => 25.96, 'calcio' => 7.56, 'ferro' => 0.38, 'retinol' => 0.00, 'vitamina_c' => 21.59, 'sodio' => 0.00],
    56 => ['nome' => 'Mexerica, Rio, crua', 'kcal' => 36.87, 'proteina' => 0.65, 'lipideos' => 0.13, 'carboidratos' => 9.34, 'calcio' => 17.18, 'ferro' => 0.09, 'retinol' => 0.00, 'vitamina_c' => 111.97, 'sodio' => 1.82], // Exemplo Poncã/Rio
    57 => ['nome' => 'Melancia, crua', 'kcal' => 32.61, 'proteina' => 0.88, 'lipideos' => 0.00, 'carboidratos' => 8.14, 'calcio' => 7.72, 'ferro' => 0.23, 'retinol' => 0.00, 'vitamina_c' => 6.15, 'sodio' => 0.00],
    58 => ['nome' => 'Goiaba, vermelha, com casca, crua', 'kcal' => 54.17, 'proteina' => 1.09, 'lipideos' => 0.44, 'carboidratos' => 13.01, 'calcio' => 4.45, 'ferro' => 0.17, 'retinol' => 0.00, 'vitamina_c' => 80.60, 'sodio' => 0.00],
    59 => ['nome' => 'Manga, Tommy Atkins, crua', 'kcal' => 50.69, 'proteina' => 0.86, 'lipideos' => 0.22, 'carboidratos' => 12.77, 'calcio' => 7.64, 'ferro' => 0.08, 'retinol' => 0.00, 'vitamina_c' => 7.94, 'sodio' => 0.00], // Vit A deve ser alta

    // Pães, Bolachas, Bolos
    17 => ['nome' => 'Pão, trigo, forma, integral', 'kcal' => 253.19, 'proteina' => 9.43, 'lipideos' => 3.65, 'carboidratos' => 49.94, 'calcio' => 131.76, 'ferro' => 2.99, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 506.10],
    19 => ['nome' => 'Bolacha, doce, maisena', 'kcal' => 442.82, 'proteina' => 8.07, 'lipideos' => 11.97, 'carboidratos' => 75.23, 'calcio' => 54.45, 'ferro' => 1.76, 'retinol' => 0.00, 'vitamina_c' => 6.22, 'sodio' => 352.03],
    24 => ['nome' => 'Bolacha de água e sal', 'kcal' => 432, 'proteina' => 10.1, 'lipideos' => 14.4, 'carboidratos' => 68.7, 'calcio' => 20, 'ferro' => 2.2, 'retinol' => 0, 'vitamina_c' => 0, 'sodio' => 854], // Ou Cream Cracker?
    60 => ['nome' => 'Bolo, industrializado (média diferentes sabores)', 'kcal' => 420.00, 'proteina' => 6.49, 'lipideos' => 19.60, 'carboidratos' => 54.50, 'calcio' => 116.00, 'ferro' => 3.60, 'retinol' => 1.23, 'vitamina_c' => 1.90, 'sodio' => 332.00], // Usar para "Bolo de Vovó/Chocolate" se não tiver receita
    61 => ['nome' => 'Biscoito de polvilho', 'kcal' => 436.72, 'proteina' => 4.46, 'lipideos' => 29.08, 'carboidratos' => 38.37, 'calcio' => 18.28, 'ferro' => 0.57, 'retinol' => 59.54, 'vitamina_c' => 0.00, 'sodio' => 536.68],

    // Outros
    16 => ['nome' => 'Achocolatado, pó', 'kcal' => 401.02, 'proteina' => 4.20, 'lipideos' => 2.17, 'carboidratos' => 91.18, 'calcio' => 44.40, 'ferro' => 5.36, 'retinol' => 795.85, 'vitamina_c' => 0.00, 'sodio' => 65.00],
    62 => ['nome' => 'Açúcar, cristal', 'kcal' => 386.85, 'proteina' => 0.32, 'lipideos' => 0.00, 'carboidratos' => 99.61, 'calcio' => 7.59, 'ferro' => 0.16, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 0.00], // Usado no Leite Queimado
    31 => ['nome' => 'Tomate, extrato', 'kcal' => 60.93, 'proteina' => 2.43, 'lipideos' => 0.19, 'carboidratos' => 14.96, 'calcio' => 29.08, 'ferro' => 2.09, 'retinol' => 0.00, 'vitamina_c' => 18.01, 'sodio' => 497.93],
    63 => ['nome' => 'Caju, suco concentrado, envasado', 'kcal' => 45.11, 'proteina' => 0.40, 'lipideos' => 0.20, 'carboidratos' => 10.73, 'calcio' => 0.98, 'ferro' => 0.15, 'retinol' => 0.00, 'vitamina_c' => 138.70, 'sodio' => 45.04], // Exemplo para Suco

    // Temperos
    21 => ['nome' => 'Óleo, de soja', 'kcal' => 884.00, 'proteina' => 0.00, 'lipideos' => 100.00, 'carboidratos' => 0.00, 'calcio' => 0.00, 'ferro' => 0.00, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 0.00],
    22 => ['nome' => 'Cebola, crua', 'kcal' => 39.42, 'proteina' => 1.71, 'lipideos' => 0.08, 'carboidratos' => 8.85, 'calcio' => 14.00, 'ferro' => 0.20, 'retinol' => 0.00, 'vitamina_c' => 4.67, 'sodio' => 0.60],
    64 => ['nome' => 'Alho, cru', 'kcal' => 113.13, 'proteina' => 7.01, 'lipideos' => 0.22, 'carboidratos' => 23.91, 'calcio' => 13.56, 'ferro' => 0.80, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 5.36],
    23 => ['nome' => 'Sal, grosso', 'kcal' => 0.00, 'proteina' => 0.00, 'lipideos' => 0.00, 'carboidratos' => 0.00, 'calcio' => 0.00, 'ferro' => 0.00, 'retinol' => 0.00, 'vitamina_c' => 0.00, 'sodio' => 39943.00],
];

// 2. Preparações DB (Nomes que aparecem nos selects) - IDs > 1000
$preparacoes_db = [
    1    => ['nome' => "Selecione..."],
    // Refeições Principais
    1001 => ['nome' => "Arroz branco"], // Simples
    1002 => ['nome' => "Feijão de caldo"], // Simples
    1003 => ['nome' => "Coxa/sobrecoxa ao molho com batata inglesa"],
    1004 => ['nome' => "Picadinho de carne bovina ao molho com cenoura"],
    1005 => ['nome' => "Macarrão (parafuso) com molho a bolonhesa (pedaço) com cenoura"],
    1006 => ['nome' => "Frango cozido (peito) com batatas"],
    1007 => ['nome' => "Arroz com pernil"],
    1008 => ['nome' => "Salada de alface"], // Simples
    1009 => ['nome' => "Salada de tomate"], // Simples
    1010 => ['nome' => "Farofa de ovo"],
    1011 => ['nome' => "Purê de cabotiá"],
    1012 => ['nome' => "Ovos mexidos com tomate"],
    1013 => ['nome' => "Galinhada (coxa e sobrecoxa)"],
    1014 => ['nome' => "Tutu de feijão"],
    1015 => ['nome' => "Batata doce assada"], // Simples
    1016 => ['nome' => "Estrogonofe de frango com creme de inhame"],
    1017 => ['nome' => "Couve refogada"],
    1018 => ['nome' => "Farofa de abobrinha"],
    1019 => ['nome' => "Picadinho de carne bovina com cabotia refogada"],
    // 1020 => ['nome' => "Arroz com pernil"], // Repetido? Se sim, remover. Se diferente, manter.
    1021 => ['nome' => "Salada de alface e tomate"],
    1022 => ['nome' => "Sopa de legumes(cenoura e chuchu) e peito de frango"],
    1023 => ['nome' => "Feijão tropeiro (feijão,carne suína, farinha de mandioca e couve)"],
    1024 => ['nome' => "Carne bovina refogada com cenoura e chuchu"],
    1025 => ['nome' => "Baião de três (arroz, feijão e pernil)"],
    1026 => ['nome' => "Macarrão com frango desfiado (peito) e cenoura ao molho vermelho"],
    1027 => ['nome' => "Carne moída refogada com batata doce"],
    1028 => ['nome' => "Picadinho de frango (coxa e sobrecoxa) com cabotiá ao molho"],
    1029 => ['nome' => "Carne suína com lentilha"],
    1030 => ['nome' => "Quibebe de mandioca com pernil"],
    1031 => ['nome' => "Peixe assado com legumes (abobrinha e tomate)"],
    1032 => ['nome' => "Farofinha de ovo"], // Repetido?
    1033 => ['nome' => "Cabotiá cozida"], // Simples
    1034 => ['nome' => "Frango (peito) ao molho com batata doce"],
    1035 => ['nome' => "Sopa de macarrão (parafuso) com carne bovina (pedaço) e batatas"],
    1036 => ['nome' => "Quibe assado (carne bovina)"],
    1037 => ['nome' => "Ovo mexido com cenoura em cubos"],
    1038 => ['nome' => "Chuchu refogado"],
    1039 => ['nome' => "Macarrão espaguete ao molho vermelho com pernil"],
    1040 => ['nome' => "Frango (coxa e sobrecoxa) com abobrinha em cubos"],
    1041 => ['nome' => "Salada de repolho"], // Simples
    1042 => ['nome' => "Omelete de forno"],
    1043 => ['nome' => "Escondidinho de frango (coxa e sobrecoxa) com mandioca"],
    1044 => ['nome' => "Estrogonofe de carne bovina (pedaço)"],
    1045 => ['nome' => "Macarrão (espaguete) com abobrinha e frango desfiado (peito)"],
    1046 => ['nome' => "Frango assado (coxa e sobrecoxa) com creme de cabotiá"],
    1047 => ['nome' => "Macarrão ao molho branco com frango (coxa e sobrecoxa)"],

    // Cafés/Lanches (IDs > 2000)
    2001 => ['nome' => "Bolacha de água e sal"], // Simples
    2002 => ['nome' => "Leite com achocolatado"],
    2003 => ['nome' => "Pão de forma com manteiga"],
    2004 => ['nome' => "Leite puro"], // Simples
    2005 => ['nome' => "Bolo de Vovó"], // Assumir bolo industrializado médio? Ou receita?
    2006 => ['nome' => "Banana"], // Simples
    2007 => ['nome' => "Mexerica"], // Simples
    2008 => ['nome' => "Bolacha de maisena"], // Simples
    2009 => ['nome' => "Suco de caju"], // Simples (usar néctar/suco pronto)
    2010 => ['nome' => "Mingau de amido com achocolatado"],
    2011 => ['nome' => "Leite queimado"],
    2012 => ['nome' => "Torrada de pão de forma"], // Pão + Manteiga?
    2013 => ['nome' => "Biscoito de polvilho"], // Simples
    2014 => ['nome' => "Bolo de Chocolate"], // Assumir bolo médio?
    2015 => ['nome' => "Melancia"], // Simples
    2016 => ['nome' => "Goiaba"], // Simples
    2017 => ['nome' => "Manga"], // Simples
    2018 => ['nome' => "Preparação de Amido (geral)"], // Ex: Mingau simples
    2019 => ['nome' => "Preparação de Farinha de Trigo (geral)"], // Ex: Bolo simples
    2020 => ['nome' => "Preparação de Polvilho (geral)"], // Ex: Pão de queijo? Biscoito?
];

// 3. Receita Itens DB (Mapeia Preparação -> Alimentos)
// *** MAPEAMENTO CRÍTICO - PRECISA SER PRECISO ***
$receita_itens_db = [
    // Mapeamentos 1 para 1 (Simples)
    ['id_preparacao' => 1001, 'id_alimento' => 2],  // Arroz branco -> Arroz cru
    ['id_preparacao' => 1002, 'id_alimento' => 3],  // Feijão de caldo -> Feijão cru
    ['id_preparacao' => 1008, 'id_alimento' => 25], // Salada de alface -> Alface crua
    ['id_preparacao' => 1009, 'id_alimento' => 11], // Salada de tomate -> Tomate cru
    ['id_preparacao' => 1015, 'id_alimento' => 12], // Batata doce assada -> Batata doce crua
    ['id_preparacao' => 1033, 'id_alimento' => 29], // Cabotiá cozida -> Cabotia crua
    ['id_preparacao' => 1041, 'id_alimento' => 34], // Salada de repolho -> Repolho cru
    ['id_preparacao' => 2001, 'id_alimento' => 24], // Bolacha água e sal -> Bolacha água e sal
    ['id_preparacao' => 2004, 'id_alimento' => 15], // Leite puro -> Leite integral
    ['id_preparacao' => 2008, 'id_alimento' => 19], // Bolacha maisena -> Bolacha maisena
    ['id_preparacao' => 2006, 'id_alimento' => 20], // Banana -> Banana prata (ou outra?)
    ['id_preparacao' => 2007, 'id_alimento' => 56], // Mexerica -> Mexerica Rio
    ['id_preparacao' => 2015, 'id_alimento' => 57], // Melancia -> Melancia
    ['id_preparacao' => 2016, 'id_alimento' => 58], // Goiaba -> Goiaba Vermelha
    ['id_preparacao' => 2017, 'id_alimento' => 59], // Manga -> Manga Tommy
    ['id_preparacao' => 2009, 'id_alimento' => 63], // Suco de Caju -> Suco Caju Concentrado
    ['id_preparacao' => 2013, 'id_alimento' => 61], // Biscoito de polvilho -> Biscoito de polvilho

    // Mapeamentos Complexos (Exemplos - COMPLETAR/CORRIGIR)
    // 1003: Coxa/sobrecoxa ao molho com batata inglesa
    ['id_preparacao' => 1003, 'id_alimento' => 5],['id_preparacao' => 1003, 'id_alimento' => 8],['id_preparacao' => 1003, 'id_alimento' => 21],['id_preparacao' => 1003, 'id_alimento' => 22],['id_preparacao' => 1003, 'id_alimento' => 64],['id_preparacao' => 1003, 'id_alimento' => 23], ['id_preparacao' => 1003, 'id_alimento' => 31], // Adicionei Alho e Extrato Tomate
    // 1004: Picadinho bovino com cenoura
    ['id_preparacao' => 1004, 'id_alimento' => 53],['id_preparacao' => 1004, 'id_alimento' => 9],['id_preparacao' => 1004, 'id_alimento' => 21],['id_preparacao' => 1004, 'id_alimento' => 22],['id_preparacao' => 1004, 'id_alimento' => 64],['id_preparacao' => 1004, 'id_alimento' => 23], // Usando Músculo como ex. de 'pedaço'
    // 1005: Macarrão bolonhesa com cenoura
    ['id_preparacao' => 1005, 'id_alimento' => 10],['id_preparacao' => 1005, 'id_alimento' => 6],['id_preparacao' => 1005, 'id_alimento' => 9],['id_preparacao' => 1005, 'id_alimento' => 31],['id_preparacao' => 1005, 'id_alimento' => 21],['id_preparacao' => 1005, 'id_alimento' => 22],['id_preparacao' => 1005, 'id_alimento' => 64],['id_preparacao' => 1005, 'id_alimento' => 23],
    // 1010/1032: Farofa de ovo
    ['id_preparacao' => 1010, 'id_alimento' => 14],['id_preparacao' => 1010, 'id_alimento' => 27],['id_preparacao' => 1010, 'id_alimento' => 18],['id_preparacao' => 1010, 'id_alimento' => 22],['id_preparacao' => 1010, 'id_alimento' => 23],
    ['id_preparacao' => 1032, 'id_alimento' => 14],['id_preparacao' => 1032, 'id_alimento' => 27],['id_preparacao' => 1032, 'id_alimento' => 18],['id_preparacao' => 1032, 'id_alimento' => 22],['id_preparacao' => 1032, 'id_alimento' => 23],
    // 1013: Galinhada (coxa e sobrecoxa)
    ['id_preparacao' => 1013, 'id_alimento' => 5],['id_preparacao' => 1013, 'id_alimento' => 2],['id_preparacao' => 1013, 'id_alimento' => 21],['id_preparacao' => 1013, 'id_alimento' => 22],['id_preparacao' => 1013, 'id_alimento' => 64],['id_preparacao' => 1013, 'id_alimento' => 23], // Adicionar outros se houver (pimentão?)
    // 1017: Couve refogada
    ['id_preparacao' => 1017, 'id_alimento' => 26],['id_preparacao' => 1017, 'id_alimento' => 21],['id_preparacao' => 1017, 'id_alimento' => 64],['id_preparacao' => 1017, 'id_alimento' => 23],
    // 2002: Leite com achocolatado
    ['id_preparacao' => 2002, 'id_alimento' => 15],['id_preparacao' => 2002, 'id_alimento' => 16],
    // 2003: Pão de forma com manteiga
    ['id_preparacao' => 2003, 'id_alimento' => 17],['id_preparacao' => 2003, 'id_alimento' => 18],
    // 2011: Leite queimado
    ['id_preparacao' => 2011, 'id_alimento' => 15],['id_preparacao' => 2011, 'id_alimento' => 62], // Leite + Açúcar

    // *** MAPEIE TODAS AS OUTRAS PREPARAÇÕES COMPLEXAS AQUI ***
];

// 4. Porções Padrão DB (Base: Documento Padronização - Fund. 6-10 anos)
// *** VALORES CRÍTICOS - VERIFIQUE CADA UM CUIDADOSAMENTE ***
$porcoes_padrao_db = [
    // Fundamentais 6-10 anos
     2 => 40,  // Arroz cru
     3 => 25,  // Feijão cru
     4 => 40,  // Frango Coxa ('Carnes e ovos')
     5 => 40,  // Frango Sobrecoxa ('Carnes e ovos')
     6 => 40,  // Carne Bovina (Acém) ('Carnes e ovos')
     53 => 40, // Carne Bovina (Músculo) ('Carnes e ovos')
     7 => 40,  // Carne Suína (Pernil) ('Carnes e ovos')
    14 => 40,  // Ovo ('Carnes e ovos' - aprox 1 P)
    54 => 40,  // Peixe Tilápia ('Carnes e ovos')
     8 => 40,  // Batata Inglesa ('Batata/inhame/mandioca' - Usei 40g, doc diz 20g, CONFIRMAR)
    12 => 40,  // Batata Doce ('Batata/inhame/mandioca' - Idem)
    55 => 40,  // Mandioca ('Batata/inhame/mandioca' - Idem)
    30 => 40,  // Inhame ('Batata/inhame/mandioca' - Idem)
     9 => 20,  // Cenoura ('Legumes/verduras/saladas')
    11 => 20,  // Tomate ('Legumes/verduras/saladas')
    13 => 20,  // Beterraba ('Legumes/verduras/saladas')
    25 => 20,  // Alface ('Legumes/verduras/saladas')
    26 => 20,  // Couve ('Legumes/verduras/saladas')
    28 => 20,  // Abobrinha ('Legumes/verduras/saladas')
    29 => 20,  // Cabotia ('Legumes/verduras/saladas')
    34 => 20,  // Repolho ('Legumes/verduras/saladas')
    35 => 20,  // Chuchu ('Legumes/verduras/saladas')
    10 => 50,  // Macarrão cru
    27 => 10,  // Farinha de mandioca ou trigo
    50 => 10,  // Farinha de trigo (uso geral)
    31 => 8,   // Extrato de tomate
    32 => 25,  // Lentilha (porção similar ao feijão?) ***CONFIRMAR***
    52 => 30,  // Trigo para Quibe ***CONFIRMAR***
    20 => 60,  // Banana ('Frutas')
    56 => 60,  // Mexerica ('Frutas')
    57 => 60,  // Melancia ('Frutas')
    58 => 60,  // Goiaba ('Frutas')
    59 => 60,  // Manga ('Frutas')
    15 => 120, // Leite de vaca (mL ~= g)
    18 => 10,  // Manteiga
    19 => 50,  // Bolacha Maisena ('Bolachas/pães')
    24 => 50,  // Bolacha Agua e Sal ('Bolachas/pães')
    17 => 50,  // Pão de Forma ('Bolachas/pães')
    60 => 50,  // Bolo ('Bolachas/pães' - assumindo porção similar) ***CONFIRMAR***
    61 => 50,  // Biscoito Polvilho ('Bolachas/pães' - Idem) ***CONFIRMAR***
    51 => 15,  // Amido (para mingau, porção pó?) ***CONFIRMAR***
    16 => 10,  // Achocolatado/açúcar
    62 => 10,  // Açúcar (isolado)
    63 => 100, // Suco de Caju (pronto para beber, ml ~= g?) ***CONFIRMAR***

    // Temperos (Estimativas - AJUSTAR CONFORME PADRÃO REAL)
    21 => 5,   // Óleo (por preparação principal)
    22 => 5,   // Cebola (por preparação principal)
    64 => 2,   // Alho (por preparação principal)
    23 => 1,   // Sal (por preparação principal - CUIDADO!)
];

// 5. PNAE Referências (Fund. 6-10 anos, Integral 70%)
$pnae_ref = [
    'faixa' => 'Fundamental 6-10 anos - Integral (70%)',
    'kcal' => 1150,
    'ptn_min_vet' => 10, 'ptn_max_vet' => 15, // %VET
    'lpd_min_vet' => 15, 'lpd_max_vet' => 30, // %VET
    'cho_min_vet' => 55, 'cho_max_vet' => 65, // %VET
    'ca' => 700,  'fe' => 6.3,  'vita' => 280, 'vitc' => 17.5, // Micros
    'na_max' => 1400, // Limite Sódio (mg)
];

// ======================================================================
// LÓGICA PHP PARA CÁLCULO (Manipulador de Requisição AJAX)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calculate') {
    header('Content-Type: application/json; charset=utf-8');

    $cardapio_selecionado = isset($_POST['cardapio']) ? $_POST['cardapio'] : null;
    if (!is_array($cardapio_selecionado)) {
        echo json_encode(['error' => 'Dados do cardápio inválidos.']);
        exit;
    }

    $totais_semanais = array_fill_keys(array_keys($alimentos_db[array_key_first($alimentos_db)]), 0.0); // Inicializa com todas as chaves de nutrientes
    unset($totais_semanais['nome']); // Remove 'nome' dos totais

    $dias_processados = 0;
    $log_erros = [];

    foreach ($cardapio_selecionado as $dia => $refeicoes) {
        if (!is_array($refeicoes)) continue;
        $dia_teve_calculo = false;

        foreach ($refeicoes as $ref => $preparacoes_ids) {
            if (!is_array($preparacoes_ids)) continue;

            foreach ($preparacoes_ids as $id_preparacao_raw) {
                $id_preparacao = filter_var($id_preparacao_raw, FILTER_VALIDATE_INT);
                if ($id_preparacao === false || $id_preparacao <= 1) continue;

                $alimentos_da_preparacao_ids = [];
                $encontrou_receita = false;
                foreach ($receita_itens_db as $item) {
                    if ($item['id_preparacao'] == $id_preparacao) {
                        $alimentos_da_preparacao_ids[] = $item['id_alimento'];
                        $encontrou_receita = true;
                    }
                }

                if (!$encontrou_receita) {
                     if (isset($alimentos_db[$id_preparacao]) && isset($porcoes_padrao_db[$id_preparacao])) {
                         $alimentos_da_preparacao_ids[] = $id_preparacao;
                     } else {
                          $prep_nome = isset($preparacoes_db[$id_preparacao]['nome']) ? $preparacoes_db[$id_preparacao]['nome'] : "ID {$id_preparacao}";
                          $log_erros[] = "Receita/Porção não encontrada para: {$prep_nome}";
                          continue;
                     }
                 }

                foreach ($alimentos_da_preparacao_ids as $id_alimento) {
                    if (isset($alimentos_db[$id_alimento]) && isset($porcoes_padrao_db[$id_alimento])) {
                        $alimento_info = $alimentos_db[$id_alimento];
                        $porcao_g = floatval($porcoes_padrao_db[$id_alimento]);

                        if ($porcao_g > 0) {
                            $dia_teve_calculo = true;
                            foreach ($totais_semanais as $nutriente => &$valor_atual) { // Usar referência (&)
                                if (isset($alimento_info[$nutriente])) {
                                    $nutriente_por_100g = floatval($alimento_info[$nutriente]);
                                    $nutriente_na_porcao = ($nutriente_por_100g / 100.0) * $porcao_g;
                                    $valor_atual += $nutriente_na_porcao;
                                }
                            }
                            unset($valor_atual); // Quebrar referência
                        }
                    } else {
                        $alimento_nome = isset($alimentos_db[$id_alimento]['nome']) ? $alimentos_db[$id_alimento]['nome'] : "ID {$id_alimento}";
                        $tem_alimento = isset($alimentos_db[$id_alimento]) ? 'Sim' : 'Não';
                        $tem_porcao = isset($porcoes_padrao_db[$id_alimento]) ? 'Sim' : 'Não';
                        $log_erros[] = "Dados incompletos para Alimento: {$alimento_nome} (ID: {$id_alimento}). Info Nutricional: {$tem_alimento}, Porção Padrão: {$tem_porcao}.";
                    }
                }
            }
        }
         if ($dia_teve_calculo) {
             $dias_processados++;
         }
    }

    // Calcular Médias e Adequação
    $medias_semanais = [];
    $adequacao_percentual = [];
    $num_dias_calc = ($dias_processados > 0) ? $dias_processados : 1; // Evita divisão por zero, assume 1 se nada foi calculado

    foreach ($totais_semanais as $nutriente => $total) {
        $medias_semanais[$nutriente] = $total / $num_dias_calc;
    }

    // Mapear nomes e calcular VET
    $medias_semanais['cho'] = $medias_semanais['carboidratos']; unset($medias_semanais['carboidratos']);
    $medias_semanais['ptn'] = $medias_semanais['proteina']; unset($medias_semanais['proteina']);
    $medias_semanais['lpd'] = $medias_semanais['lipideos']; unset($medias_semanais['lipideos']);
    $medias_semanais['ca'] = $medias_semanais['calcio']; unset($medias_semanais['calcio']);
    $medias_semanais['fe'] = $medias_semanais['ferro']; unset($medias_semanais['ferro']);
    $medias_semanais['vita'] = $medias_semanais['retinol']; unset($medias_semanais['retinol']);
    $medias_semanais['vitc'] = $medias_semanais['vitamina_c']; unset($medias_semanais['vitamina_c']);
    $medias_semanais['na'] = $medias_semanais['sodio']; unset($medias_semanais['sodio']);

    $vet_medio = $medias_semanais['kcal'];
    if ($vet_medio > 0) {
        $adequacao_percentual['kcal'] = ($pnae_ref['kcal'] > 0) ? ($medias_semanais['kcal'] / $pnae_ref['kcal']) * 100 : 0;
        $adequacao_percentual['ptn_vet'] = (($medias_semanais['ptn'] * 4) / $vet_medio) * 100;
        $adequacao_percentual['lpd_vet'] = (($medias_semanais['lpd'] * 9) / $vet_medio) * 100;
        $adequacao_percentual['cho_vet'] = (($medias_semanais['cho'] * 4) / $vet_medio) * 100;
    } else {
        $adequacao_percentual = array_fill_keys(['kcal', 'ptn_vet', 'lpd_vet', 'cho_vet'], 0);
    }
    $adequacao_percentual['ca'] = ($pnae_ref['ca'] > 0) ? ($medias_semanais['ca'] / $pnae_ref['ca']) * 100 : 0;
    $adequacao_percentual['fe'] = ($pnae_ref['fe'] > 0) ? ($medias_semanais['fe'] / $pnae_ref['fe']) * 100 : 0;
    $adequacao_percentual['vita'] = ($pnae_ref['vita'] > 0) ? ($medias_semanais['vita'] / $pnae_ref['vita']) * 100 : 0;
    $adequacao_percentual['vitc'] = ($pnae_ref['vitc'] > 0) ? ($medias_semanais['vitc'] / $pnae_ref['vitc']) * 100 : 0;
    $adequacao_percentual['na'] = ($pnae_ref['na_max'] > 0) ? ($medias_semanais['na'] / $pnae_ref['na_max']) * 100 : 0;

    $resposta_final = [
        'medias' => $medias_semanais,
        'adequacao' => $adequacao_percentual,
        'pnae_ref' => $pnae_ref,
        'debug_errors' => $log_erros
    ];

    echo json_encode($resposta_final);
    exit; // ESSENCIAL!
}

// ======================================================================
// HTML, CSS e JavaScript (Renderização da Página)
// ======================================================================
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Cardápio Escolar - PNAE</title>
    <style>
        /* CSS Embutido - Estilo Clean e Profissional */
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        :root {
            --primary-color: #2980b9; /* Azul Padrão */
            --secondary-color: #2c3e50; /* Azul Escuro (Texto) */
            --light-gray: #ecf0f1;
            --medium-gray: #bdc3c7;
            --dark-gray: #7f8c8d;
            --bg-color: #f8f9fa;
            --white-color: #ffffff;
            --success-color: #27ae60; /* Verde */
            --warning-color: #e67e22; /* Laranja */
            --error-color: #c0392b; /* Vermelho */
            --info-color: #3498db; /* Azul Claro */
            --border-radius: 5px;
            --box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
            --gradient-primary: linear-gradient(135deg, #3498db, #2980b9);
            --gradient-results: linear-gradient(to bottom, #ffffff, #fdfefe);
        }

        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: var(--bg-color);
            color: var(--secondary-color);
        }

        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 25px;
            background-color: var(--white-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        h1, h2 {
            color: var(--secondary-color);
            margin-bottom: 15px;
        }
        h1 {
            font-size: 2em;
            font-weight: 500;
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary-color);
            border-bottom: none;
        }
        h2 {
            font-size: 1.5em;
            font-weight: 500;
            color: var(--primary-color);
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 8px;
            margin-top: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        th, td {
            border: 1px solid var(--medium-gray);
            padding: 12px 15px; /* Mais espaçamento */
            text-align: left;
            vertical-align: middle; /* Centraliza verticalmente */
        }

        th {
            background: var(--gradient-primary);
            color: var(--white-color);
            font-weight: 500; /* Peso médio */
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
        }
        td:first-child {
            font-weight: 500;
            background-color: var(--light-gray);
            color: var(--secondary-color);
            min-width: 150px; /* Espaço para nome da refeição */
            font-size: 0.95em;
        }

        td select {
            width: 100%;
            padding: 10px; /* Mais padding interno */
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius);
            background-color: var(--white-color);
            font-size: 0.95em;
            min-width: 200px;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease; /* Suaviza foco */
        }
        td select:focus {
             border-color: var(--primary-color);
             outline: none;
             box-shadow: 0 0 0 3px rgba(41, 128, 185, 0.2); /* Sombra suave no foco */
         }
        /* Estilização básica da seta (pode variar entre navegadores) */
        td select {
           appearance: none;
           -webkit-appearance: none;
           -moz-appearance: none;
           background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23007CB2%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E');
           background-repeat: no-repeat;
           background-position: right 10px top 50%;
           background-size: 10px auto;
           padding-right: 30px; /* Espaço para a seta */
        }


        #resultados {
            background: var(--gradient-results);
            border: 1px solid #e0e0e0;
            padding: 25px;
            margin-top: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: grid; /* Usar grid para layout mais flexível */
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Colunas responsivas */
            gap: 15px; /* Espaço entre itens */
        }

        #resultados .result-item {
            margin-bottom: 0; /* Removido por causa do grid gap */
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            background-color: #fff;
            font-size: 1em;
        }

        #resultados .result-label {
            display: block;
            font-weight: 500;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        #resultados .result-value {
            font-weight: 700;
            font-size: 1.2em;
            color: var(--primary-color);
            margin-right: 8px;
        }

        #resultados .adequacy {
            font-style: normal; /* Remover itálico padrão */
            font-size: 0.9em;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: var(--border-radius);
            margin-left: 5px;
            display: inline-block;
            border: 1px solid transparent; /* Borda base */
        }

        /* Classes de Adequação */
        .adequacy-ok { color: var(--success-color); background-color: #eafaf1; border-color: #abebc6; }
        .adequacy-low { color: var(--warning-color); background-color: #fef5e7; border-color: #f5cba7; }
        .adequacy-high { color: var(--warning-color); background-color: #fef5e7; border-color: #f5cba7;}
        .adequacy-na-high { color: var(--error-color); background-color: #fdedec; border-color: #f5b7b1; }
        .adequacy-neutral { color: var(--dark-gray); background-color: #f2f3f4; border-color: #e5e7e9;}

        .status {
            grid-column: 1 / -1; /* Ocupa toda a largura do grid */
            margin-top: 15px;
            font-weight: 500;
            padding: 12px;
            border-radius: var(--border-radius);
            text-align: center;
            font-size: 1.1em;
        }
        .status.loading { color: var(--info-color); background-color: #eaf2f8; border: 1px solid #aed6f1; }
        .status.error { color: var(--error-color); background-color: #fdedec; border: 1px solid #f5b7b1;}
        .status.success { color: var(--success-color); background-color: #eafaf1; border: 1px solid #abebc6;}
        .status.warning { color: var(--warning-color); background-color: #fef5e7; border: 1px solid #f5cba7;}

        hr { display: none; } /* Remover hr desnecessária */

    </style>
</head>
<body>
    <div class="container">
        <h1>Calculadora Nutricional de Cardápio Escolar</h1>
        <p>Selecione as preparações para cada refeição da semana. Os valores nutricionais médios (para Fundamental 6-10 anos, Integral) serão atualizados automaticamente.</p>

        <form id="cardapioForm">
            <table>
                <thead>
                    <tr>
                        <th>Refeição</th>
                        <th>Segunda-feira</th>
                        <th>Terça-feira</th>
                        <th>Quarta-feira</th>
                        <th>Quinta-feira</th>
                        <th>Sexta-feira</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $refeicoes = [
                        'cafe' => 'CAFÉ DA MANHÃ (07:30)',
                        'ref_manha' => 'REFEIÇÃO PRINCIPAL MANHÃ (9:15-10:00)',
                        'lanche' => 'LANCHE (13:00)',
                        'ref_tarde' => 'REFEIÇÃO PRINCIPAL TARDE (15:15-16:00)'
                    ];
                    $dias = ['seg', 'ter', 'qua', 'qui', 'sex'];

                    foreach ($refeicoes as $ref_key => $ref_nome) : ?>
                        <tr>
                            <td><?php echo $ref_nome; ?></td>
                            <?php foreach ($dias as $dia) : ?>
                                <td>
                                    <select class="prep-selector" name="<?php echo $dia . '_' . $ref_key; ?>" id="<?php echo $dia . '_' . $ref_key; ?>">
                                        <?php foreach ($preparacoes_db as $id => $prep) : ?>
                                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($prep['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>

        <h2>Resultados Nutricionais - Média Semanal (Ref: <?php echo htmlspecialchars($pnae_ref['faixa']); ?>)</h2>
        <div id="resultados">
            <div class="result-item"><span class="result-label">Energia</span> <span class="result-value" id="res_kcal">0</span> kcal <span class="adequacy adequacy-neutral">(<span id="perc_kcal">-</span>%)</span></div>
            <div class="result-item"><span class="result-label">Carboidratos</span> <span class="result-value" id="res_cho">0.0</span> g <span class="adequacy adequacy-neutral">(<span id="perc_cho_vet">-</span>% VET)</span></div>
            <div class="result-item"><span class="result-label">Proteínas</span> <span class="result-value" id="res_ptn">0.0</span> g <span class="adequacy adequacy-neutral">(<span id="perc_ptn_vet">-</span>% VET)</span></div>
            <div class="result-item"><span class="result-label">Lipídeos</span> <span class="result-value" id="res_lpd">0.0</span> g <span class="adequacy adequacy-neutral">(<span id="perc_lpd_vet">-</span>% VET)</span></div>
            <div class="result-item"><span class="result-label">Cálcio</span> <span class="result-value" id="res_ca">0</span> mg <span class="adequacy adequacy-neutral">(<span id="perc_ca">-</span>%)</span></div>
            <div class="result-item"><span class="result-label">Ferro</span> <span class="result-value" id="res_fe">0.0</span> mg <span class="adequacy adequacy-neutral">(<span id="perc_fe">-</span>%)</span></div>
            <div class="result-item"><span class="result-label">Vitamina A (RE)</span> <span class="result-value" id="res_vita">0</span> mcg <span class="adequacy adequacy-neutral">(<span id="perc_vita">-</span>%)</span></div>
            <div class="result-item"><span class="result-label">Vitamina C</span> <span class="result-value" id="res_vitc">0.0</span> mg <span class="adequacy adequacy-neutral">(<span id="perc_vitc">-</span>%)</span></div>
            <div class="result-item"><span class="result-label">Sódio</span> <span class="result-value" id="res_na">0</span> mg <span class="adequacy adequacy-neutral">(<span id="perc_na">-</span>% do Limite)</span></div>
            <div id="status-message" class="status">Preencha o cardápio para iniciar o cálculo.</div>
        </div>
    </div> <!-- /.container -->

    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <!-- JavaScript Embutido -->
    <script>
        $(document).ready(function() {
            const resultElements = {
                kcal: $('#res_kcal'), p_kcal: $('#perc_kcal'),
                cho: $('#res_cho'), p_cho_vet: $('#perc_cho_vet'),
                ptn: $('#res_ptn'), p_ptn_vet: $('#perc_ptn_vet'),
                lpd: $('#res_lpd'), p_lpd_vet: $('#perc_lpd_vet'),
                ca: $('#res_ca'), p_ca: $('#perc_ca'),
                fe: $('#res_fe'), p_fe: $('#perc_fe'),
                vita: $('#res_vita'), p_vita: $('#perc_vita'),
                vitc: $('#res_vitc'), p_vitc: $('#perc_vitc'),
                na: $('#res_na'), p_na: $('#perc_na')
            };
            const statusMessage = $('#status-message');
            let requestActive = false; // Flag para evitar chamadas simultâneas

            function calcularTotais() {
                if (requestActive) { return; } // Impede nova chamada se uma estiver ativa
                requestActive = true;

                statusMessage.text('Calculando...').removeClass('error success warning').addClass('loading');
                const cardapioSemanal = {};
                const dias = ['seg', 'ter', 'qua', 'qui', 'sex'];
                const refeicoes = ['cafe', 'ref_manha', 'lanche', 'ref_tarde'];
                let hasSelection = false; // Verifica se algo foi selecionado

                dias.forEach(dia => {
                    cardapioSemanal[dia] = {};
                    refeicoes.forEach(ref => {
                        const selectId = `#${dia}_${ref}`;
                        const prepId = $(selectId).val();
                        if (prepId && prepId != "1") {
                            cardapioSemanal[dia][ref] = [prepId];
                            hasSelection = true;
                        } else {
                            cardapioSemanal[dia][ref] = [];
                        }
                    });
                });

                 // Se nada foi selecionado, limpa e para
                if (!hasSelection) {
                    limparResultados();
                    statusMessage.text('Preencha o cardápio para iniciar o cálculo.').removeClass('loading error success warning');
                    requestActive = false;
                    return;
                }

                $.ajax({
                    url: 'index.php',
                    method: 'POST',
                    data: {
                        action: 'calculate', // Identificador da Ação
                        cardapio: cardapioSemanal
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response && !response.error) {
                             if(response.debug_errors && response.debug_errors.length > 0){
                                statusMessage.text('Cálculo concluído com avisos (ver console).').removeClass('loading success error').addClass('warning');
                                console.warn("Avisos do cálculo:", response.debug_errors);
                             } else {
                                statusMessage.text('Cálculo concluído.').removeClass('loading error warning').addClass('success');
                             }
                            updateResults(response.medias, response.adequacao, response.pnae_ref);
                        } else {
                            limparResultados();
                            statusMessage.text(response.error || 'Erro desconhecido no cálculo.').removeClass('loading success warning').addClass('error');
                            console.error("Erro retornado pelo PHP:", response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro no AJAX:", status, error, xhr.responseText);
                        limparResultados();
                        statusMessage.text('Erro crítico na comunicação. Verifique o console.').removeClass('loading success warning').addClass('error');
                    },
                    complete: function() {
                        requestActive = false; // Libera para novas chamadas
                    }
                });
            }

            function updateResults(medias, adequacao, pnae_ref) {
                 if (!medias || !adequacao || !pnae_ref) {
                     limparResultados();
                     statusMessage.text('Dados de resposta inválidos.').removeClass('loading success warning').addClass('error');
                     return;
                 }
                // Atualiza valores
                resultElements.kcal.text(medias.kcal?.toFixed(0) ?? '0');
                resultElements.cho.text(medias.cho?.toFixed(1) ?? '0.0');
                resultElements.ptn.text(medias.ptn?.toFixed(1) ?? '0.0');
                resultElements.lpd.text(medias.lpd?.toFixed(1) ?? '0.0');
                resultElements.ca.text(medias.ca?.toFixed(0) ?? '0');
                resultElements.fe.text(medias.fe?.toFixed(1) ?? '0.0');
                resultElements.vita.text(medias.vita?.toFixed(0) ?? '0');
                resultElements.vitc.text(medias.vitc?.toFixed(1) ?? '0.0');
                resultElements.na.text(medias.na?.toFixed(0) ?? '0');

                // Atualiza adequações
                updateAdequacy(resultElements.p_kcal, adequacao.kcal);
                updateAdequacyVET(resultElements.p_cho_vet, adequacao.cho_vet, pnae_ref.cho_min_vet, pnae_ref.cho_max_vet);
                updateAdequacyVET(resultElements.p_ptn_vet, adequacao.ptn_vet, pnae_ref.ptn_min_vet, pnae_ref.ptn_max_vet);
                updateAdequacyVET(resultElements.p_lpd_vet, adequacao.lpd_vet, pnae_ref.lpd_min_vet, pnae_ref.lpd_max_vet);
                updateAdequacy(resultElements.p_ca, adequacao.ca);
                updateAdequacy(resultElements.p_fe, adequacao.fe);
                updateAdequacy(resultElements.p_vita, adequacao.vita);
                updateAdequacy(resultElements.p_vitc, adequacao.vitc);
                updateAdequacySodium(resultElements.p_na, adequacao.na);
            }

            function updateAdequacy(element, percentage) {
                const perc = percentage?.toFixed(0);
                const spanElement = element.closest('.adequacy');
                spanElement.removeClass('adequacy-ok adequacy-low adequacy-high adequacy-neutral');

                if (perc !== undefined && perc !== null && !isNaN(perc)) {
                    element.text(perc);
                    const val = parseFloat(perc);
                    if (val >= 80 && val <= 120) { spanElement.addClass('adequacy-ok'); }
                    else if (val < 80) { spanElement.addClass('adequacy-low'); }
                    else { spanElement.addClass('adequacy-high'); }
                } else {
                    element.text('-'); spanElement.addClass('adequacy-neutral');
                }
            }

             function updateAdequacyVET(element, percentage, min_vet, max_vet) {
                 const perc = percentage?.toFixed(1); // VET com 1 decimal
                 const spanElement = element.closest('.adequacy');
                 spanElement.removeClass('adequacy-ok adequacy-low adequacy-high adequacy-neutral');

                 if (perc !== undefined && perc !== null && !isNaN(perc)) {
                     element.text(perc);
                     const val = parseFloat(perc);
                     if (val >= min_vet && val <= max_vet) { spanElement.addClass('adequacy-ok'); }
                     else if (val < min_vet) { spanElement.addClass('adequacy-low'); }
                     else { spanElement.addClass('adequacy-high'); }
                 } else {
                     element.text('-'); spanElement.addClass('adequacy-neutral');
                 }
             }

            function updateAdequacySodium(element, percentage) {
                const perc = percentage?.toFixed(0);
                const spanElement = element.closest('.adequacy');
                spanElement.removeClass('adequacy-ok adequacy-na-high adequacy-neutral');

                if (perc !== undefined && perc !== null && !isNaN(perc)) {
                    element.text(perc);
                    const val = parseFloat(perc);
                    if (val <= 100) { spanElement.addClass('adequacy-ok'); }
                    else { spanElement.addClass('adequacy-na-high'); }
                } else {
                    element.text('-'); spanElement.addClass('adequacy-neutral');
                }
            }

            function limparResultados() {
                 $('#res_kcal, #res_ca, #res_vita, #res_na').text('0');
                 $('#res_cho, #res_ptn, #res_lpd, #res_fe, #res_vitc').text('0.0');
                 $('.adequacy span').text('-');
                 $('.adequacy').removeClass('adequacy-ok adequacy-low adequacy-high adequacy-na-high').addClass('adequacy-neutral');
                 statusMessage.text('Preencha o cardápio para iniciar o cálculo.').removeClass('loading error success warning');
             }

            $(document).on('change', '.prep-selector', calcularTotais);
            limparResultados();

        });
    </script>
</body>
</html>