<?php
// cardapio_auto/index.php

// 1. Configuração de Sessão (ANTES DE TUDO)
$session_cookie_path = '/cardapio_auto/'; // Caminho específico para este app
@session_set_cookie_params([ // O @ suprime avisos se headers já enviados por algum motivo inesperado
    'lifetime' => 0, // 0 = até fechar navegador
    'path' => $session_cookie_path,
    'domain' => $_SERVER['HTTP_HOST'] ?? '', // Usa o domínio atual
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // True se HTTPS
    'httponly' => true, // Ajuda a prevenir XSS
    'samesite' => 'Lax' // Proteção CSRF
]);
@session_name("CARDAPIOSESSID"); // Nome único
if (session_status() === PHP_SESSION_NONE) {
     @session_start(); // Inicia a sessão com nome e caminho configurados
}

// 2. Configuração de Erros
error_reporting(E_ALL);
$is_development = true; // Mude para false em produção final
ini_set('display_errors', $is_development ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
// error_log("--- Iniciando index.php ---");

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? null;

if (!$is_logged_in) {
    error_log("Acesso não autenticado a index.php. Redirecionando para login.");
    header('Location: login.php');
    exit;
}
// error_log("DEBUG (index.php): Usuário autenticado. UserID: " . $logged_user_id);

// 4. Conexão com Banco de Dados e Carregamento do Projeto
$projeto_id = filter_input(INPUT_GET, 'projeto_id', FILTER_VALIDATE_INT);
$projeto_nome = "Cardápio Inválido";
$cardapio_data_db = null; // JSON cru do BD
$db_connection_error = false;
$load_error_message = null;
$pdo = null;
$cardapio_processado = null; // Dados processados do JSON ou padrão

// Definições Padrão Globais (usadas se o projeto for novo ou dados inválidos)
$refeicoes_layout_default = ['ref_1' => ['label' => 'REFEIÇÃO 1', 'horario' => "09:00"], 'ref_2' => ['label' => 'REFEIÇÃO 2', 'horario' => "14:30"]];
$dias_keys = ['seg', 'ter', 'qua', 'qui', 'sex'];
$dias_semana_nomes = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'];
$faixa_etaria_default_key = 'fund_6_10'; // Chave padrão se nenhuma for salva

if (!$projeto_id) {
    error_log("Tentativa de acesso a index.php sem projeto_id.");
    header('Location: home.php?erro=projeto_invalido');
    exit;
}

try {
    require_once 'includes/db_connect.php'; // Define $pdo
    // error_log("DEBUG (index.php): db_connect.php incluído.");

    $sql = "SELECT nome_projeto, dados_json FROM cardapio_projetos WHERE id = :projeto_id AND usuario_id = :usuario_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':projeto_id', $projeto_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $projeto = $stmt->fetch();

    if (!$projeto) {
        error_log("Erro ao carregar projeto: Projeto ID $projeto_id não encontrado ou não pertence ao User ID $logged_user_id.");
        header('Location: home.php?erro=acesso_negado');
        exit;
    }

    $projeto_nome = $projeto['nome_projeto'];
    $cardapio_data_db = $projeto['dados_json']; // Pega o JSON cru
    // error_log("DEBUG (index.php): Projeto ID $projeto_id ('$projeto_nome') carregado. JSON size: " . strlen($cardapio_data_db ?? '') . " bytes.");

} catch (\PDOException $e) {
    $db_connection_error = true;
    $load_error_message = "Erro crítico ao conectar ou buscar dados do projeto.";
    error_log("Erro PDO em index.php ao carregar Projeto ID $projeto_id para User ID $logged_user_id: " . $e->getMessage());
} catch (\Throwable $th) {
     $db_connection_error = true;
     $load_error_message = "Erro inesperado ao carregar dados.";
     error_log("Erro Throwable em index.php ao carregar Projeto ID $projeto_id: " . $th->getMessage());
}

// --- Processamento dos Dados do Cardápio (JSON ou Padrão) ---
$faixa_etaria_inicial_key = $faixa_etaria_default_key; // Começa com o default

if (!$db_connection_error && $projeto) { // Só processa se conectou e encontrou o projeto
    if ($cardapio_data_db && $cardapio_data_db !== '{}' && $cardapio_data_db !== 'null') {
        $decoded_data = json_decode($cardapio_data_db, true);
        // Verifica se o JSON é válido E se tem as chaves principais esperadas
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data) && isset($decoded_data['dias'], $decoded_data['refeicoes'])) {
            $cardapio_processado = $decoded_data;
            // Garante chaves internas (compatibilidade com projetos antigos/parciais)
            $cardapio_processado['refeicoes'] = (is_array($cardapio_processado['refeicoes']) && !empty($cardapio_processado['refeicoes'])) ? $cardapio_processado['refeicoes'] : $refeicoes_layout_default;
            $cardapio_processado['datas_dias'] = $cardapio_processado['datas_dias'] ?? array_fill_keys($dias_keys, '');
            $cardapio_processado['dias_desativados'] = $cardapio_processado['dias_desativados'] ?? array_fill_keys($dias_keys, false);
            $cardapio_processado['faixa_etaria_selecionada'] = $cardapio_processado['faixa_etaria_selecionada'] ?? $faixa_etaria_default_key;
            $faixa_etaria_inicial_key = $cardapio_processado['faixa_etaria_selecionada'];

            // Validação profunda da estrutura dias/refeições/itens
            $refeicoes_validas_keys = array_keys($cardapio_processado['refeicoes']);
            foreach ($dias_keys as $dia) {
                 if (!isset($cardapio_processado['dias'][$dia]) || !is_array($cardapio_processado['dias'][$dia])) {
                     $cardapio_processado['dias'][$dia] = array_fill_keys($refeicoes_validas_keys, []);
                 }
                foreach ($refeicoes_validas_keys as $ref_key) {
                     if (!isset($cardapio_processado['dias'][$dia][$ref_key]) || !is_array($cardapio_processado['dias'][$dia][$ref_key])) {
                          $cardapio_processado['dias'][$dia][$ref_key] = [];
                     }
                     $cardapio_processado['dias'][$dia][$ref_key] = array_values(array_filter($cardapio_processado['dias'][$dia][$ref_key], function($item){
                          // Verifica se é array e tem todas as chaves esperadas
                          return is_array($item)
                                 && isset($item['foodId'], $item['qty'], $item['instanceGroup'], $item['placementId'])
                                 && is_scalar($item['foodId']) // Garante que IDs sejam strings ou números
                                 && is_numeric($item['qty']) && $item['qty'] > 0
                                 && is_numeric($item['instanceGroup']) && $item['instanceGroup'] > 0
                                 && !empty($item['placementId']);
                     }));
                }
                // Remove refeições do dia que não estão mais no layout atual
                $cardapio_processado['dias'][$dia] = array_intersect_key($cardapio_processado['dias'][$dia], $cardapio_processado['refeicoes']);
            }
            // error_log("DEBUG (index.php): JSON do projeto ID $projeto_id decodificado e validado.");

        } else {
            // JSON inválido ou estrutura incorreta
            error_log("AVISO: JSON inválido ou estrutura incorreta no BD para Projeto ID $projeto_id. JSON: " . substr($cardapio_data_db, 0, 200) . "... Erro JSON: " . json_last_error_msg() . ". Usando estrutura padrão.");
            $load_error_message = "Atenção: Os dados salvos deste cardápio podem estar corrompidos ou em formato antigo. Iniciando com um cardápio padrão.";
            $cardapio_processado = null; // Força usar o padrão
        }
    } else {
        // JSON é nulo ou vazio '{}' (caso de novo projeto)
        error_log("DEBUG (index.php): Projeto ID $projeto_id é novo ou JSON vazio/null. Usando estrutura padrão.");
        $cardapio_processado = null; // Força usar o padrão
        // Não define $load_error_message aqui para projeto novo, será tratado na inicialização do JS
    }
}

// Cria estrutura padrão se necessário (novo projeto, erro DB, JSON inválido)
if ($cardapio_processado === null) {
    if(!$db_connection_error) { error_log("DEBUG (index.php): Criando estrutura de cardápio padrão para Projeto ID $projeto_id."); }
    else { error_log("AVISO (index.php): Erro de conexão, criando estrutura de cardápio padrão em memória para ProjID $projeto_id.");}

    $cardapio_processado = [
        'refeicoes' => $refeicoes_layout_default,
        'dias' => [],
        'datas_dias' => array_fill_keys($dias_keys, ''),
        'dias_desativados' => array_fill_keys($dias_keys, false),
        'faixa_etaria_selecionada' => $faixa_etaria_default_key
    ];
    foreach ($dias_keys as $dia) {
        $cardapio_processado['dias'][$dia] = array_fill_keys(array_keys($refeicoes_layout_default), []);
    }
    $faixa_etaria_inicial_key = $faixa_etaria_default_key;
     // Define mensagem de erro apenas se não for erro de conexão e o JSON original existia mas era inválido
     if (!$load_error_message && !$db_connection_error && $cardapio_data_db !== null && $cardapio_data_db !== '{}' && $cardapio_data_db !== 'null') {
         $load_error_message = $load_error_message ?: "Atenção: Os dados salvos estavam em formato inesperado. Iniciando com um cardápio padrão.";
     }
}


$preparacoes_usuario = []; // Array para guardar preparações do usuário
// Verifica se a conexão foi bem sucedida E se temos o ID do usuário
if (!$db_connection_error && $logged_user_id && isset($pdo)) {
    error_log("DEBUG (index.php): Buscando preparações para UserID: $logged_user_id");
    try {
        // Ajuste o nome da coluna se você escolheu a Opção B (tabela separada) antes.
        // Se usou Opção A (coluna JSON), mantenha como está:
        $sql_prep = "SELECT preparacoes_personalizadas_json FROM cardapio_usuarios WHERE id = :user_id LIMIT 1";
        $stmt_prep = $pdo->prepare($sql_prep);
        $stmt_prep->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);
        $stmt_prep->execute();
        $json_preps = $stmt_prep->fetchColumn(); // Pega só a coluna JSON

        if ($json_preps && $json_preps !== 'null' && $json_preps !== '{}') {
            $decoded_preps = json_decode($json_preps, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_preps)) {
                $preparacoes_usuario = $decoded_preps;
                 error_log("DEBUG (index.php): " . count($preparacoes_usuario) . " preparações carregadas para UserID $logged_user_id.");
            } else {
                error_log("AVISO (index.php): JSON de preparações inválido para UserID $logged_user_id. JSON: ".substr($json_preps,0,100)."...");
                 // Não define $load_error_message aqui, apenas avisa no log.
            }
        } else {
             error_log("DEBUG (index.php): Nenhuma preparação personalizada encontrada ou JSON vazio para UserID $logged_user_id.");
        }
    } catch (PDOException $e) {
        // Loga o erro mas não impede o carregamento da página (o usuário verá apenas os alimentos base)
        error_log("Erro PDO ao buscar preparações para UserID $logged_user_id: " . $e->getMessage());
        $load_error_message = ($load_error_message ? $load_error_message . " | " : "") . "Erro ao carregar preparações salvas."; // Adiciona ao erro geral
    }
} elseif ($db_connection_error) {
     error_log("AVISO (index.php): Conexão BD falhou, não foi possível buscar preparações.");
} elseif (!$logged_user_id) {
     error_log("AVISO (index.php): User ID não encontrado, não foi possível buscar preparações.");
}
// ---> FIM DO CÓDIGO PARA CARREGAR PREPARAÇÕES <---


// --- Carregamento Robusto dos Dados Base (dados.php) ---
$dados_base_ok = false;
$erro_dados_base = '';
$lista_selecionaveis_db = [];
$alimentos_db = [];
$todas_porcoes_db = [];
$todos_pnae_ref = [];

try {
    $dados_file = __DIR__ . '/dados.php';
    if (!file_exists($dados_file) || !is_readable($dados_file)) {
        throw new Exception("Arquivo dados.php não encontrado ou sem permissão de leitura.");
    }
    ob_start();
    require $dados_file; // Define $dados_ok, $alimentos_db, $todas_porcoes_db, $todos_pnae_ref, $lista_selecionaveis_db
    $output = ob_get_clean();
    if (!empty($output)) { error_log("AVISO: Saída inesperada dados.php: " . substr($output, 0, 200)); }

    $dados_ok_interno = $dados_ok ?? false; // $dados_ok é definido em dados.php

    $dados_essenciais_ok = (
        isset($lista_selecionaveis_db, $alimentos_db, $todas_porcoes_db, $todos_pnae_ref) &&
        is_array($lista_selecionaveis_db) && !empty($lista_selecionaveis_db) &&
        is_array($alimentos_db) && !empty($alimentos_db) &&
        is_array($todas_porcoes_db) && !empty($todas_porcoes_db) &&
        is_array($todos_pnae_ref) && !empty($todos_pnae_ref)
    );
    $dados_base_ok = ($dados_ok_interno === true && $dados_essenciais_ok);

    if (!$dados_base_ok) {
        $erro_dados_base = "Falha ao carregar dados essenciais de 'dados.php'.";
        if (!$dados_ok_interno) $erro_dados_base .= " (Flag \$dados_ok não é true).";
        if (!$dados_essenciais_ok) $erro_dados_base .= " (Variáveis essenciais vazias/inválidas).";
        $load_error_message = "Erro crítico: " . $erro_dados_base;
    } else {
        // error_log("DEBUG (index.php): Dados base (dados.php) carregados OK.");
    }
} catch (Throwable $e) {
    $erro_dados_base = "Erro fatal ao carregar dados.php: " . $e->getMessage();
    if (ob_get_level() > 0) ob_end_clean();
    $dados_base_ok = false;
    $load_error_message = "Erro fatal ao carregar dados base: " . $erro_dados_base;
}
if (!$dados_base_ok) { error_log("Montador Cardápio (index.php) - Erro Carga Dados Base: " . $erro_dados_base); }

// --- Prepara dados para interface JavaScript ---
// Inicializa mesmo que haja erro para evitar erros fatais no JS
$alimentos_id_nome_map = [];
$alimentos_para_js = [];
$faixa_pnae_opcoes = [];
$faixa_pnae_titulo_inicial = 'Selecione Faixa';
$todas_porcoes_db_json = '{}';
$alimentos_disponiveis_json = '{}';
$alimentos_completos_json = '{}';
$cardapio_inicial_formatado_para_js = [];
$initial_instance_groups = [];
// Garante que $cardapio_processado exista, mesmo que vazio/default
$cardapio_processado = $cardapio_processado ?? [
    'refeicoes' => $refeicoes_layout_default, 'dias' => [],
    'datas_dias' => array_fill_keys($dias_keys, ''), 'dias_desativados' => array_fill_keys($dias_keys, false),
    'faixa_etaria_selecionada' => $faixa_etaria_default_key
];
$refeicoes_layout_atual = $cardapio_processado['refeicoes'];
$refeicoes_keys_atuais = array_keys($refeicoes_layout_atual);
$faixa_etaria_inicial_key = $cardapio_processado['faixa_etaria_selecionada']; // Pega a faixa definida


function generate_initial_placement_id_idx_local($dia, $ref, $idx) { return 'init_' . $dia . '_' . $ref . '_' . $idx . '_' . substr(bin2hex(random_bytes(4)), 0, 6); }

if ($dados_base_ok && !$db_connection_error) {
    // Mapeia Alimentos Base
    foreach ($lista_selecionaveis_db as $id => $data) {
        if (isset($data['nome']) && isset($alimentos_db[$id])) {
            $id_str = (string)$id;
            $alimentos_id_nome_map[$id_str] = $data['nome'];
            $alimentos_para_js[$id_str] = ['id' => $id_str, 'nome' => $data['nome'], 'isPreparacao' => false];
        }
    }
    
        // ---> INÍCIO DA MESCLAGEM DAS PREPARAÇÕES <---
    if (!empty($preparacoes_usuario)) { // Verifica se o array carregado não está vazio
        error_log("DEBUG (index.php - JS Data Prep): Mesclando " . count($preparacoes_usuario) . " preparações do usuário.");
        foreach ($preparacoes_usuario as $prep_id => $prep_data) {
            // Validação mínima da estrutura da preparação salva
            if (isset($prep_data['nome'], $prep_data['ingredientes_json'], $prep_data['porcao_padrao'])) {
                $id_str = (string)$prep_id; // Usa o ID salvo (ex: prep_...)

                // Adiciona/Sobrescreve no mapa ID->Nome
                $alimentos_id_nome_map[$id_str] = $prep_data['nome'];

                // Tenta decodificar os ingredientes para a estrutura JS
                $ingredientes_array = json_decode($prep_data['ingredientes_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($ingredientes_array)) {
                    error_log("AVISO: Ingredientes JSON inválidos ao mesclar preparação '$prep_id' (UserID $logged_user_id). Usando array vazio.");
                    $ingredientes_array = [];
                }

                 // Adiciona/Sobrescreve no array completo para o JS
                 $alimentos_para_js[$id_str] = [
                     'id' => $id_str,
                     'nome' => $prep_data['nome'],
                     'porcao_padrao' => max(1, (int)($prep_data['porcao_padrao'] ?? 100)), // Garante que seja >= 1
                     'isPreparacao' => true,
                     'ingredientes' => $ingredientes_array // Passa o array decodificado
                 ];
             } else {
                 error_log("AVISO: Estrutura inválida para preparação salva '$prep_id' (UserID $logged_user_id). Ignorando.");
             }
        }
    }
    // ---> FIM DA MESCLAGEM DAS PREPARAÇÕES <---

    // Re-codifica os JSONs AGORA que as preparações foram mescladas
    $alimentos_disponiveis_json = json_encode($alimentos_id_nome_map);
    $alimentos_completos_json = json_encode($alimentos_para_js);

    // Reordena a lista para o modal (necessário após mesclar)
    $alimentos_para_modal_lista = $alimentos_para_js; // Pega o array atualizado
    uasort($alimentos_para_modal_lista, fn($a, $b) => strcasecmp($a['nome'] ?? '', $b['nome'] ?? ''));

// ... (o resto da preparação para JS continua aqui, como opções PNAE, encode de porções, etc.) ...
    // TODO: Adicionar lógica para carregar/preparar dados de preparações salvas, se houver

    $alimentos_disponiveis_json = json_encode($alimentos_id_nome_map);
    $alimentos_completos_json = json_encode($alimentos_para_js);

    // Prepara opções PNAE e valida faixa inicial
    foreach ($todos_pnae_ref as $key => $ref) { $faixa_pnae_opcoes[$key] = htmlspecialchars($ref['faixa'] ?? $key); }
    if (!isset($faixa_pnae_opcoes[$faixa_etaria_inicial_key])) {
        $old_key = $faixa_etaria_inicial_key;
        $faixa_etaria_inicial_key = key($faixa_pnae_opcoes) ?: $faixa_etaria_default_key;
        $cardapio_processado['faixa_etaria_selecionada'] = $faixa_etaria_inicial_key; // Marca para salvar
        error_log("AVISO Pós-DadosBase: Faixa etária ('{$old_key}') inválida. Usando fallback '{$faixa_etaria_inicial_key}' para Proj {$projeto_id}.");
        if (empty($load_error_message)) { $load_error_message = "Aviso: A faixa etária salva era inválida."; }
    }
    $faixa_pnae_titulo_inicial = $faixa_pnae_opcoes[$faixa_etaria_inicial_key] ?? 'Faixa Inválida';

    // Codifica Porções
    $todas_porcoes_db_json = json_encode($todas_porcoes_db);

    // Formata Cardápio Carregado para JS
    foreach ($dias_keys as $dia) {
        $cardapio_inicial_formatado_para_js[$dia] = [];
        foreach ($refeicoes_keys_atuais as $ref_key) {
            $itens_celula_validos = $cardapio_processado['dias'][$dia][$ref_key] ?? [];
            foreach($itens_celula_validos as $item_valido) {
                 $foodId = $item_valido['foodId']; $instanceGroup = $item_valido['instanceGroup'];
                 if (!isset($initial_instance_groups[$foodId]) || $instanceGroup > $initial_instance_groups[$foodId]) { $initial_instance_groups[$foodId] = $instanceGroup; }
            }
            // Ordena (mantido)
            usort($itens_celula_validos, function($a, $b) use ($alimentos_id_nome_map) { $nA = $alimentos_id_nome_map[$a['foodId']] ?? ''; $nB = $alimentos_id_nome_map[$b['foodId']] ?? ''; $c = strcasecmp($nA, $nB); if ($c === 0) return ($a['instanceGroup'] ?? 1) <=> ($b['instanceGroup'] ?? 1); return $c; });
            $cardapio_inicial_formatado_para_js[$dia][$ref_key] = $itens_celula_validos;
        }
    }
} else { // Fallback se dados base ou conexão falharam
    if ($dados_base_ok) { // Se só a conexão falhou
         foreach ($todos_pnae_ref as $key => $ref) { $faixa_pnae_opcoes[$key] = htmlspecialchars($ref['faixa'] ?? $key); }
         if (!isset($faixa_pnae_opcoes[$faixa_etaria_inicial_key])) { $faixa_etaria_inicial_key = key($faixa_pnae_opcoes) ?: $faixa_etaria_default_key;}
         $faixa_pnae_titulo_inicial = $faixa_pnae_opcoes[$faixa_etaria_inicial_key] ?? 'Erro Carga';
    } else { // Se dados base falharam
         $faixa_pnae_opcoes[$faixa_etaria_default_key] = 'Padrão (Erro Carga)';
         $faixa_etaria_inicial_key = $faixa_etaria_default_key;
         $faixa_pnae_titulo_inicial = 'Erro Carga Dados';
    }
    // Sempre inicializa vazio se houve erro
    foreach ($dias_keys as $dia) { $cardapio_inicial_formatado_para_js[$dia] = array_fill_keys($refeicoes_keys_atuais, []); }
}

// Prepara JSONs finais (garantindo que $cardapio_processado exista)
$cardapio_inicial_json = json_encode($cardapio_inicial_formatado_para_js);
$initial_instance_groups_json = json_encode($initial_instance_groups);
$datas_dias_json = json_encode($cardapio_processado['datas_dias'] ?? array_fill_keys($dias_keys, ''));
$dias_desativados_json = json_encode($cardapio_processado['dias_desativados'] ?? array_fill_keys($dias_keys, false));
$refeicoes_layout_json = json_encode($refeicoes_layout_atual);
$dias_keys_json = json_encode($dias_keys);
$dias_nomes_json = json_encode(array_combine($dias_keys, $dias_semana_nomes));
$page_title = "Cardápio: " . htmlspecialchars($projeto_nome); // Usa o nome carregado

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* === COLOQUE SEU CSS COMPLETO AQUI === */
        /* Copie todo o CSS do arquivo anterior */
         :root {
            --font-primary: 'Poppins', sans-serif; --font-secondary: 'Roboto', sans-serif; --font-size-base: 14px;
            --primary-color: #005A9C; --primary-dark: #003A6A; --primary-light: #4D94DB; --accent-color: #EBF4FF;
            --secondary-color: #6c757d; --secondary-light: #adb5bd; --bg-color: #F8F9FA; --card-bg: #FFFFFF;
            --text-color: #343a40; --text-light: #6c757d; --border-color: #DEE2E6; --light-border: #E9ECEF;
            --success-color: #28a745; --success-light: #e2f4e6; --success-dark: #1e7e34;
            --warning-color: #ffc107; --warning-light: #fff8e1; --warning-dark: #d39e00;
            --error-color: #dc3545;   --error-light: #f8d7da;   --error-dark: #a71d2a;
            --info-color: #17a2b8;    --info-light: #d1ecf1;    --info-dark: #117a8b; --white-color: #FFFFFF;
            --border-radius: 6px; --box-shadow: 0 2px 10px rgba(0, 90, 156, 0.08); --box-shadow-hover: 0 5px 15px rgba(0, 90, 156, 0.12);
            --transition-speed: 0.2s; --selected-item-bg: #d6eaff; --selected-item-border: var(--primary-light);
            --disabled-day-bg: #f1f3f5; --disabled-day-text: #ced4da; --disabled-day-border: #e9ecef;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes scaleUp { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } } @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { font-family: var(--font-secondary); line-height: 1.6; background-color: var(--bg-color); color: var(--text-color); font-size: var(--font-size-base); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; display: flex; flex-direction: column; min-height: 100vh;}
        .main-content { flex-grow: 1; padding: 0px 20px 20px 20px; }

        /* Header */
        .main-header { background-color: var(--primary-dark); color: var(--white-color); padding: 10px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 100; }
        .header-container { max-width: 1900px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo-area a { color: var(--white-color); text-decoration: none; font-family: var(--font-primary); font-size: 1.4em; font-weight: 600; }
        .logo-area i { margin-right: 8px; color: var(--primary-light); }
        .user-nav { display: flex; align-items: center; gap: 15px; }
        .user-nav span { font-size: 0.9em; }
        .nav-button { color: var(--white-color); background-color: var(--primary-color); padding: 6px 12px; border-radius: var(--border-radius); text-decoration: none; font-size: 0.85em; transition: background-color var(--transition-speed); display: inline-flex; align-items: center; gap: 5px; }
        .nav-button:hover, .nav-button.active { background-color: var(--primary-light); }
        .nav-button.logout { background-color: var(--error-color); } .nav-button.logout:hover { background-color: var(--error-dark); }

         /* Footer */
         .main-footer-bottom { text-align: center; padding: 15px; margin-top: 30px; background-color: #eef2f7; color: var(--text-light); font-size: 0.85em; border-top: 1px solid var(--border-color); }

        /* Container específico do Index */
        .container.page-index { max-width: 1900px; margin: 20px auto; padding: 25px 30px; background-color: var(--card-bg); border-radius: var(--border-radius); box-shadow: var(--box-shadow); animation: fadeIn var(--transition-speed) ease-out; }
        h1, h2, h3, h4, h5, h6 { font-family: var(--font-primary); font-weight: 600; color: var(--primary-dark); }
        h3 { margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; font-size: 1.1em; }
        button, input, select, textarea { font-family: inherit; font-size: inherit; }
        .config-actions-area { margin-bottom: 25px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px 20px; padding: 15px 20px; background-color: var(--accent-color); border-radius: var(--border-radius); border: 1px solid var(--light-border); }
        .faixa-etaria-selector { flex-basis: 300px; display: flex; align-items: center; gap: 8px; }
        .faixa-etaria-selector label { font-weight: 500; font-size: 0.9rem; color: var(--primary-dark); white-space: nowrap; }
        #faixa-etaria-select { padding: 8px 12px; font-size: 0.9rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); min-width: 220px; background-color: var(--white-color); cursor: pointer; height: 38px; transition: border-color var(--transition-speed), box-shadow var(--transition-speed); font-family: var(--font-secondary); appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23005A9C'%3E%3Cpath fill-rule='evenodd' d='M8 10.5a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 0 1 .708-.708L8 9.293l3.646-3.647a.5.5 0 0 1 .708.708l-4 4A.5.5 0 0 1 8 10.5Z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 16px 16px; padding-right: 35px; flex-grow: 1; }
        #faixa-etaria-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.15); outline: none; }
        .action-buttons { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; flex-grow: 1; }
        .action-button { padding: 8px 18px; background-color: var(--primary-color); color: var(--white-color); border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 0.85rem; font-weight: 500; font-family: var(--font-primary); transition: background-color var(--transition-speed), box-shadow var(--transition-speed), transform var(--transition-speed); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); display: inline-flex; align-items: center; gap: 8px; line-height: 1.5; height: 38px; text-transform: uppercase; letter-spacing: 0.5px; }
        .action-button i { font-size: 0.95em; }
        .action-button:hover:not(:disabled) { background-color: var(--primary-dark); box-shadow: 0 4px 8px rgba(0, 90, 156, 0.1); transform: translateY(-1px); }
        .action-button:active:not(:disabled) { transform: translateY(0); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
        .action-button:disabled { background-color: #adb5bd; color: #f8f9fa; cursor: not-allowed; opacity: 0.7; box-shadow: none; transform: none;}
        .action-button.cancel { background-color: var(--error-color); } .action-button.cancel:hover:not(:disabled) { background-color: var(--error-dark); }
        .action-button.export-excel { background-color: var(--success-color); } .action-button.export-excel:hover:not(:disabled) { background-color: var(--success-dark); }
        #add-refeicao-btn { background-color: var(--info-color); } #add-refeicao-btn:hover:not(:disabled) { background-color: var(--info-dark); }
        #nova-preparacao-btn { background-color: var(--warning-color); color: var(--warning-dark); }
        #nova-preparacao-btn:hover:not(:disabled) { background-color: var(--warning-dark); color: var(--white-color); }
        .item-manipulation-buttons button { background-color: var(--secondary-color); }
        .item-manipulation-buttons button:hover:not(:disabled) { background-color: var(--primary-dark); }
        .item-manipulation-buttons { margin-left: 5px; margin-right: 5px; display: flex; gap: 8px; }
        #save-project-btn { background-color: var(--success-color); }
        #save-project-btn:hover:not(:disabled) { background-color: var(--success-dark); }
        #save-project-btn.unsaved { animation: pulse-save 1.5s infinite ease-in-out; }
        @keyframes pulse-save { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        #save-status { font-size: 0.8em; margin-left: 5px; display: inline-block; min-width: 50px; text-align: right; font-weight: 500;}

        /* Tabela Cardápio */
        .cardapio-montagem-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; border: 1px solid var(--border-color); border-radius: var(--border-radius); overflow: hidden; box-shadow: var(--box-shadow); }
        .cardapio-montagem-table th, .cardapio-montagem-table td { border-bottom: 1px solid var(--light-border); border-right: 1px solid var(--light-border); padding: 8px 10px; text-align: center; vertical-align: middle; font-size: 0.85rem; position: relative; transition: background-color var(--transition-speed); white-space: nowrap; }
        .cardapio-montagem-table th:last-child, .cardapio-montagem-table td:last-child { border-right: none; }
        .cardapio-montagem-table tr:last-child td { border-bottom: none; }
        .cardapio-montagem-table thead th { background-color: #eef2f7; color: var(--primary-dark); font-weight: 600; font-family: var(--font-primary); /* position: sticky; top: 0; */ z-index: 10; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 1px solid var(--border-color); }
        .cardapio-montagem-table thead th .dia-controles { margin-top: 5px; display: flex; flex-direction: column; align-items: center; gap: 4px;}
        .cardapio-montagem-table thead th .dia-data-input { width: 65px; padding: 3px 5px; font-size: 0.75rem; text-align: center; border: 1px solid #ced4da; border-radius: 4px; background: var(--white-color); color: var(--text-color); font-family: var(--font-secondary); transition: border-color var(--transition-speed); }
        .cardapio-montagem-table thead th .dia-data-input:focus { border-color: var(--primary-color); outline: none; }
        .cardapio-montagem-table thead th .toggle-feriado-btn { padding: 2px 8px; font-size: 0.7rem; border-radius: var(--border-radius); border: 1px solid var(--secondary-light); background-color: var(--white-color); color: var(--secondary-color); cursor: pointer; transition: all var(--transition-speed); font-weight: 500; }
        .cardapio-montagem-table thead th .toggle-feriado-btn:hover { background-color: var(--secondary-light); color: var(--white-color); border-color: var(--secondary-color); }
        .cardapio-montagem-table thead th .toggle-feriado-btn.active { background-color: var(--warning-light); color: var(--warning-dark); border-color: var(--warning-color); }
        .cardapio-montagem-table thead th .toggle-feriado-btn.active:hover { background-color: var(--warning-color); color: var(--white-color); }
        .cardapio-montagem-table thead th .dia-nome { font-weight: bold; display: block; font-size: 0.9rem; }
        .cardapio-montagem-table th.dia-desativado { background-color: var(--disabled-day-bg); color: var(--disabled-day-text); }
        .cardapio-montagem-table td.dia-desativado { background-color: var(--disabled-day-bg) !important; pointer-events: none; }
        .cardapio-montagem-table td.dia-desativado ul, .cardapio-montagem-table td.dia-desativado .add-item-cell-btn { opacity: 0.4; }
        .cardapio-montagem-table td.label-cell { background-color: #f8f9fa; font-weight: 500; text-align: center; vertical-align: middle; width: 150px; cursor: pointer; transition: background-color var(--transition-speed); border-right: 1px solid var(--border-color); white-space: normal; }
        .cardapio-montagem-table td.label-cell:hover { background-color: #e9ecef; }
        .cardapio-montagem-table td.label-cell span.editable-label { display: inline-block; padding: 4px 6px; min-width: 90%; min-height: 1.4em; font-size: 0.85rem; font-weight: 600; }
        .cardapio-montagem-table td.label-cell input.label-input { display: none; width: 95%; padding: 5px; font-size: 0.85rem; border: 1px solid var(--primary-color); border-radius: 4px; text-align: center; background-color: var(--white-color); box-sizing: border-box; font-family: var(--font-secondary); }
        .cardapio-montagem-table td.label-cell span.editing { display: none; } .cardapio-montagem-table td.label-cell input.editing { display: inline-block; }
        .cardapio-montagem-table td.horario-cell { width: 90px; font-size: 0.82rem; color: var(--text-light); white-space: normal; }
        .cardapio-montagem-table td.horario-cell span.editable-label { white-space: pre-line; font-weight: 400; }
        .cardapio-montagem-table td.horario-cell input.label-input { font-size: 0.82rem; }
        .cardapio-montagem-table td.action-cell { width: 40px; padding: 0; vertical-align: middle; border-left: 1px solid var(--border-color); background-color: #f8f9fa; }
        .remove-row-btn { background: none; border: none; color: var(--secondary-light); cursor: pointer; padding: 8px; font-size: 0.9rem; opacity: 0.6; transition: all var(--transition-speed) ease; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        .cardapio-montagem-table tr:hover .remove-row-btn { opacity: 1; color: var(--error-color); background-color: var(--error-light); }
        .cardapio-montagem-table tr:hover .remove-row-btn:hover { color: var(--error-dark); transform: scale(1.1); }
        .cardapio-montagem-table tr:first-child:only-child td.action-cell { visibility: hidden; }
        .editable-cell { min-height: 80px; background-color: var(--white-color); transition: background-color var(--transition-speed) ease; vertical-align: top; text-align: left; padding: 35px 10px 10px 10px; cursor: pointer; white-space: normal; }
        .editable-cell:hover:not(.dia-desativado) { background-color: var(--accent-color); }
        .editable-cell.target-cell-for-paste { background-color: var(--success-light) !important; border: 1px dashed var(--success-dark); }
        .add-item-cell-btn { position: absolute; top: 6px; right: 6px; font-size: 0.8rem; padding: 0; line-height: 1; cursor: pointer; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; transition: all var(--transition-speed) ease; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); z-index: 5; pointer-events: auto; }
        .add-item-cell-btn:hover:not(.dia-desativado *) { background-color: var(--primary-dark); box-shadow: 0 2px 5px rgba(0, 90, 156, 0.2); transform: scale(1.1); }
        .add-item-cell-btn i { font-size: 0.75rem; }
        .selected-items-list { list-style: none; padding: 0; margin: 0; }
        .selected-items-list li { background-color: #f8f9fa; border: 1px solid var(--light-border); color: var(--text-color); padding: 6px 8px; margin-bottom: 6px; border-radius: var(--border-radius); font-size: 0.85rem; line-height: 1.4; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background-color var(--transition-speed), box-shadow var(--transition-speed), border-color var(--transition-speed); position: relative; box-shadow: none; }
        .selected-items-list li:hover:not(.dia-desativado *) { background-color: var(--white-color); border-color: var(--primary-light); box-shadow: 0 2px 5px rgba(0, 90, 156, 0.08); z-index: 2; }
        .selected-items-list li.item-selecionado { background-color: var(--selected-item-bg); border-color: var(--selected-item-border); font-weight: 500; }
        .selected-items-list li .item-details { display: flex; align-items: center; flex-grow: 1; gap: 5px;}
        .selected-items-list li .item-name { pointer-events: none; flex-grow: 1; }
        .selected-items-list li .item-instance-group-number { font-size: 0.7em; vertical-align: super; color: var(--primary-dark); font-weight: bold; margin-left: 1px; pointer-events: none; }
        .selected-items-list li .item-qty-display { font-weight: 600; color: var(--primary-dark); pointer-events: none; padding: 2px 6px; background-color: var(--accent-color); border: 1px solid var(--light-border); border-radius: 4px; font-size: 0.8rem; cursor: help; }
        .selected-items-list li .item-actions { display: flex; align-items: center; gap: 2px; margin-left: 5px; }
        .selected-items-list li .item-edit-qty-btn,
        .selected-items-list li .item-remove-btn { background: none; border: none; color: var(--secondary-light); cursor: pointer; font-size: 0.9rem; padding: 0 4px; opacity: 0; transition: opacity var(--transition-speed), color var(--transition-speed); line-height: 1; }
        .selected-items-list li:hover:not(.dia-desativado *) .item-edit-qty-btn,
        .selected-items-list li:hover:not(.dia-desativado *) .item-remove-btn { opacity: 0.7; }
        .selected-items-list li .item-edit-qty-btn:hover { opacity: 1; color: var(--info-color); }
        .selected-items-list li .item-remove-btn:hover { opacity: 1; color: var(--error-color); }
        .selected-items-list li.placeholder { background: none; border: none; color: var(--text-light); font-style: italic; text-align: center; padding: 10px 0; font-size: 0.8rem; display: block; cursor: default; box-shadow: none; }
        .selected-items-list li.placeholder:hover { background: none; border-color: transparent; }

        /* Tabela Resultados */
        .resultados-simplificados-section { margin-top: 30px; }
        #resultados-diarios-table td { font-size: 0.8rem; padding: 6px 8px; }
        #resultados-diarios-table thead th { font-size: 0.75rem; padding: 8px 8px; }
        #resultados-diarios-table tfoot td { font-weight: bold; background-color: #eef2f7; color: var(--primary-dark); }
        #resultados-diarios-table td[data-nutrient*="_vet"] { color: var(--text-light); font-style: italic; }

        /* Status Message */
        #status-message { margin-top: 20px; font-weight: 500; padding: 10px 15px; border-radius: var(--border-radius); text-align: center; font-size: 0.95em; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; gap: 10px; transition: background-color var(--transition-speed), border-color var(--transition-speed), color var(--transition-speed); }
        #status-message i { font-size: 1.1em; }
        .status.loading { color: var(--info-dark); background-color: var(--info-light); border-color: var(--info-color); animation: pulse 1.5s infinite ease-in-out; } .status.error { color: var(--error-dark); background-color: var(--error-light); border-color: var(--error-color); } .status.success { color: var(--success-dark); background-color: var(--success-light); border-color: var(--success-color); } .status.warning { color: var(--warning-dark); background-color: var(--warning-light); border-color: var(--warning-color); } .status.info { color: var(--secondary-color); background-color: #e9ecef; border-color: #ced4da; }

        /* Modais */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(52, 58, 64, 0.7); display: none; justify-content: center; align-items: center; z-index: 1000; padding: 15px; box-sizing: border-box; backdrop-filter: blur(4px); animation: fadeInModal 0.25s ease-out; }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        .modal-content { background-color: var(--card-bg); padding: 20px 25px; border-radius: var(--border-radius); box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 700px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; animation: scaleUpModal 0.25s ease-out forwards; border: 1px solid var(--light-border); }
        @keyframes scaleUpModal { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } }
        .modal-header { border-bottom: 1px solid var(--light-border); padding-bottom: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 1.3em; margin: 0; color: var(--primary-dark); font-weight: 600; font-family: var(--font-primary); }
        .modal-close-btn { background:none; border:none; font-size: 1.6rem; cursor:pointer; color: var(--secondary-light); padding: 0 5px; line-height: 1; transition: color var(--transition-speed); } .modal-close-btn:hover { color: var(--error-color); }
        .modal-body { overflow-y: auto; flex-grow: 1; margin-bottom: 15px; padding-right: 10px; scrollbar-width: thin; scrollbar-color: var(--primary-light) var(--accent-color); }
        .modal-body::-webkit-scrollbar { width: 10px; } .modal-body::-webkit-scrollbar-track { background: var(--accent-color); border-radius: 5px; } .modal-body::-webkit-scrollbar-thumb { background-color: var(--primary-light); border-radius: 5px; border: 2px solid var(--accent-color); }
        #modal-search { display: block; width: 100%; padding: 9px 12px; margin-bottom: 18px; border: 1px solid var(--border-color); border-radius: var(--border-radius); box-sizing: border-box; font-size: 0.95em; font-family: var(--font-secondary); transition: border-color var(--transition-speed), box-shadow var(--transition-speed); }
        #modal-search:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.15); outline: none; }
        #modal-selected-items h5, #modal-search-items h5, #nova-preparacao-modal .modal-section h5 { font-size: 0.95em; font-weight: 600; margin: 0 0 10px 0; color: var(--primary-dark); padding-bottom: 6px; border-bottom: 1px solid var(--light-border); }
        .modal-search-list { list-style: none; padding: 0; max-height: 350px; overflow-y: auto; margin: 0; }
        .modal-search-list li { margin-bottom: 2px; }
        .modal-search-list label { display: block; padding: 8px 10px; font-size: 0.9rem; transition: background-color 0.15s ease; border-radius: 4px; cursor: pointer; display: flex; align-items: center; }
        .modal-search-list label:hover { background-color: var(--accent-color); }
        .modal-search-list input[type="checkbox"] { margin-right: 10px; transform: scale(1.05); accent-color: var(--primary-color); cursor: pointer; flex-shrink: 0; }
        .modal-search-list label span { flex-grow: 1; }
        .modal-search-list .no-results { text-align:center; color: var(--text-light); padding: 15px 0; font-size: 0.9em; font-style: italic; }
        .modal-footer { border-top: 1px solid var(--light-border); padding-top: 15px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;}
        .modal-button { padding: 9px 20px; font-size: 0.85em; margin-left: 0; }
        .modal-button.confirm { background-color: var(--success-color); } .modal-button.confirm:hover:not(:disabled) { background-color: var(--success-dark); }
        .modal-button.cancel { background-color: var(--secondary-color); } .modal-button.cancel:hover:not(:disabled) { background-color: #5a6268; }
        #quantity-edit-modal .modal-content { max-width: 380px; padding: 25px 30px; }
        #quantity-edit-modal .modal-header h2 { font-size: 1.2em; } #quantity-edit-modal .modal-body { text-align: center; padding-top: 15px; }
        #quantity-edit-modal h3 { font-size: 1.05em; margin: 0 0 20px 0; font-weight: 500; line-height: 1.4; color: var(--primary-dark); }
        #quantity-edit-modal label { font-weight: 500; margin-right: 10px; font-size: 0.95em; }
        #quantity-edit-input { width: 100px; padding: 8px 12px; font-size: 1.1em; text-align: right; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-family: var(--font-secondary); transition: border-color var(--transition-speed), box-shadow var(--transition-speed); }
        #quantity-edit-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.15); outline: none; }
        #quantity-edit-modal .modal-footer { margin-top: 25px; } #quantity-edit-modal .modal-button { padding: 8px 18px; }
        #instance-group-choice-modal .modal-content { max-width: 450px; } #instance-group-choice-modal .modal-body { padding-top: 10px; }
        #instance-group-choice-modal h3 { font-size: 1.1em; margin-bottom: 15px; font-weight: 500; color: var(--primary-dark); text-align: center; }
        #group-choice-options { list-style: none; padding: 0; margin: 0 0 15px 0; max-height: 250px; overflow-y: auto; } #group-choice-options li { margin-bottom: 10px; }
        #group-choice-options label { display: block; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: var(--border-radius); cursor: pointer; transition: background-color var(--transition-speed), border-color var(--transition-speed); font-size: 0.9rem; display: flex; align-items: center; }
        #group-choice-options label:hover { background-color: var(--accent-color); border-color: var(--primary-light); }
        #group-choice-options input[type="radio"] { margin-right: 12px; transform: scale(1.1); accent-color: var(--primary-color); flex-shrink: 0; }
        #group-choice-options .group-option-label { flex-grow: 1; } #group-choice-options .group-option-qty { font-weight: 600; color: var(--primary-dark); margin-left: 8px; font-size: 0.9em; } #group-choice-options .new-group-label { font-style: italic; color: var(--info-dark); }
        #nova-preparacao-modal .modal-content { max-width: 800px; }
        #nova-preparacao-modal .modal-body { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        #nova-preparacao-modal .modal-section { display: flex; flex-direction: column; gap: 15px; }
        #nova-preparacao-modal label { font-weight: 500; color: var(--primary-dark); display: block; margin-bottom: 5px; font-size: 0.9em; }
        #nova-preparacao-modal input[type="text"], #nova-preparacao-modal input[type="number"] { width: 100%; padding: 8px 10px; border: 1px solid var(--border-color); border-radius: var(--border-radius); transition: border-color var(--transition-speed); }
        #nova-preparacao-modal input[type="text"]:focus, #nova-preparacao-modal input[type="number"]:focus { border-color: var(--primary-color); outline: none; }
        #prep-ingredient-search { border-bottom: 1px solid var(--border-color); margin-bottom: 0; }
        #prep-search-results { list-style: none; padding: 0; margin: 0; max-height: 180px; overflow-y: auto; border: 1px solid var(--light-border); border-top: none; border-radius: 0 0 var(--border-radius) var(--border-radius); background-color: var(--white-color); position: absolute; width: calc(100% - 2px); z-index: 1010; display: none; }
        #prep-search-results li { padding: 6px 10px; font-size: 0.85rem; cursor: pointer; border-bottom: 1px solid var(--light-border); }
        #prep-search-results li:last-child { border-bottom: none; } #prep-search-results li:hover { background-color: var(--accent-color); }
        #prep-ingredients-list { list-style: none; padding: 0; margin: 0; max-height: 250px; overflow-y: auto; border: 1px solid var(--light-border); border-radius: var(--border-radius); background-color: #f8f9fa; padding: 10px; }
        #prep-ingredients-list li { background-color: var(--white-color); border: 1px solid var(--light-border); border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; }
        #prep-ingredients-list li .ingredient-name { flex-grow: 1; margin-right: 10px; }
        #prep-ingredients-list li .ingredient-qty-input { width: 70px; text-align: right; padding: 4px 6px; font-size: 0.85rem; margin-right: 5px; }
        #prep-ingredients-list li .ingredient-remove-btn { background: none; border: none; color: var(--secondary-light); cursor: pointer; font-size: 0.9rem; padding: 0 4px; transition: color var(--transition-speed); }
        #prep-ingredients-list li .ingredient-remove-btn:hover { color: var(--error-color); }

        /* Erro Container */
         .error-container { background-color: var(--card-bg); padding: 30px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); text-align: center; border: 1px solid var(--error-color); max-width: 600px; margin: 50px auto; }
         .error-container h1 { color: var(--error-dark); margin-bottom: 15px; font-family: var(--font-primary); font-size: 1.6em; display: flex; align-items: center; justify-content: center; }
         .error-container h1 i { margin-right: 10px; color: var(--error-color); }
         .error-container p { color: var(--text-light); margin-bottom: 10px; font-size: 1em; }
         .error-container p small { font-size: 0.9em; color: var(--secondary-color); }

        /* Responsividade */
         @media (max-width: 1200px) { .container.page-index { max-width: 95%; padding: 20px; } .cardapio-montagem-table th, .cardapio-montagem-table td { padding: 6px 8px; } .action-buttons { gap: 8px; } .action-button { padding: 8px 14px; font-size: 0.8rem; } }
         @media (max-width: 992px) { .container.page-index { max-width: 95%; } .config-actions-area { flex-direction: column; align-items: stretch; gap: 15px; } .faixa-etaria-selector { flex-basis: auto; justify-content: center; } #faixa-etaria-select { width: auto; min-width: 280px; flex-grow: 0; } .action-buttons { justify-content: center; } .item-manipulation-buttons { margin-left: 0; } }
         @media (max-width: 768px) { body { padding: 0; font-size: 13px; } .main-content {padding: 10px;} .container.page-index { padding: 15px; margin: 10px auto; } .cardapio-montagem-table:not(#resultados-diarios-table) { display: block; overflow-x: auto; white-space: nowrap; border: none; box-shadow: none;} .cardapio-montagem-table:not(#resultados-diarios-table) thead, .cardapio-montagem-table:not(#resultados-diarios-table) tbody, .cardapio-montagem-table:not(#resultados-diarios-table) tr { display: block; } .cardapio-montagem-table:not(#resultados-diarios-table) thead { position: relative; } .cardapio-montagem-table:not(#resultados-diarios-table) th { display: inline-block; width: 180px; vertical-align: top; padding: 10px; } .cardapio-montagem-table:not(#resultados-diarios-table) th:first-child, .cardapio-montagem-table:not(#resultados-diarios-table) th:nth-child(2), .cardapio-montagem-table:not(#resultados-diarios-table) th:last-child { display: none; } .cardapio-montagem-table:not(#resultados-diarios-table) tbody tr { border-bottom: 2px solid var(--primary-light); margin-bottom: 15px; padding-bottom: 10px; display: flex; flex-direction: column;} .cardapio-montagem-table:not(#resultados-diarios-table) td { display: block; text-align: left; border: none; border-bottom: 1px solid var(--light-border); padding-left: 10px; position: relative; white-space: normal; width: 100% !important; } .cardapio-montagem-table:not(#resultados-diarios-table) td:last-child { border-bottom: none; } .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell, .cardapio-montagem-table:not(#resultados-diarios-table) td.horario-cell, .cardapio-montagem-table:not(#resultados-diarios-table) td.action-cell { border-bottom: 1px solid var(--light-border); padding: 10px; height: auto; display: flex; justify-content: space-between; align-items: center; } .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell::before, .cardapio-montagem-table:not(#resultados-diarios-table) td.horario-cell::before, .cardapio-montagem-table:not(#resultados-diarios-table) td.action-cell::before { display: none; } .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell span.editable-label, .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell input.label-input { display: inline-block; width: auto; min-width: 150px; } .cardapio-montagem-table:not(#resultados-diarios-table) td.action-cell { height: 40px; } .remove-row-btn { position: static; width: auto; height: auto; padding: 5px 10px; } .cardapio-montagem-table:not(#resultados-diarios-table) tr td.action-cell { visibility: visible !important; } .editable-cell { padding: 10px; min-height: 60px;} .editable-cell::before { display: none; } .add-item-cell-btn { top: 5px; right: 5px; width: 26px; height: 26px; } .add-item-cell-btn i { font-size: 0.8rem; } .selected-items-list li { font-size: 0.8rem; padding: 5px 8px; } .modal-content { padding: 15px 20px; max-height: 85vh; } .modal-body { padding-right: 5px; } .modal-header h2 { font-size: 1.2em; } .modal-search-list { max-height: 300px; } .modal-footer { display: flex; justify-content: flex-end; gap: 8px;} .modal-button { margin-left: 0; padding: 8px 15px; } #nova-preparacao-modal .modal-body { grid-template-columns: 1fr; } }
         @media (max-width: 480px) { body { font-size: 12px; } .container.page-index { padding: 10px; margin: 5px auto; } .action-button { font-size: 0.75rem; padding: 6px 10px; gap: 4px; height: 32px;} .cardapio-montagem-table:not(#resultados-diarios-table) th { width: 150px; } h3 { font-size: 1.05em; } #resultados-diarios-table td { font-size: 0.75rem; padding: 4px 6px; } #resultados-diarios-table thead th { font-size: 0.7rem; padding: 6px 6px; } #quantity-edit-modal .modal-content, #instance-group-choice-modal .modal-content, #nova-preparacao-modal .modal-content { max-width: 95%; width: 95%; } }
         
         
         .editable-cell.target-cell-for-paste {
            background-color: var(--success-light) !important;
            border: 2px dashed var(--success-dark) !important; /* Aumenta destaque */
        }
         

    </style>
</head>
<body class="page-index"> <!-- Adiciona classe para CSS específico -->

    <!-- Header HTML -->
    <header class="main-header">
        <div class="header-container">
             <div class="logo-area">
                 <a href="home.php" title="Montador de Cardápio"> <!-- Link para home -->
                    <i class="fas fa-utensils"></i> Montador Cardápio
                 </a>
             </div>
             <nav class="user-nav">
                 <?php if ($is_logged_in && $logged_username): ?>
                     <span>Olá, <?php echo htmlspecialchars($logged_username); ?>!</span>
                     <a href="home.php" class="nav-button"><i class="fas fa-th-large"></i> Meus Projetos</a>
                     <a href="logout.php" class="nav-button logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
                 <?php else: ?>
                     <!-- Fallback caso a sessão falhe, mas não deveria acontecer devido ao auth_check -->
                     <a href="login.php" class="nav-button">Entrar</a>
                     <a href="register.php" class="nav-button">Registrar</a>
                 <?php endif; ?>
             </nav>
        </div>
    </header>

    <!-- Conteúdo Principal da Página Index -->
    <main class="main-content">

        <?php if ($db_connection_error || !$dados_base_ok): ?>
            <div class="error-container">
                <h1><i class="fas fa-exclamation-triangle"></i> Erro ao Carregar Cardápio</h1>
                <p>Não foi possível carregar os dados necessários para o montador.</p>
                <?php if ($db_connection_error): ?>
                    <p><?php echo htmlspecialchars($load_error_message ?: 'Ocorreu um problema ao acessar os dados do projeto.'); ?></p>
                <?php elseif (!$dados_base_ok): ?>
                    <p><?php echo htmlspecialchars($load_error_message ?: 'Ocorreu um problema ao carregar os dados base de alimentos.'); ?></p>
                <?php endif; ?>
                <p>Por favor, <a href="home.php">volte para seus projetos</a> ou contate o suporte.</p>
                 <p><small>(Detalhes técnicos foram registrados nos logs do servidor.)</small></p>
            </div>
        <?php else: // Só mostra o montador se tudo carregou OK ?>
            <!-- Input hidden para o ID do projeto -->
            <input type="hidden" id="current-project-id" value="<?php echo $projeto_id; ?>">

            <!-- Container específico do Montador -->
            <div class="container page-index">

                <?php if ($load_error_message && !$db_connection_error && $dados_base_ok): ?>
                    <!-- Mensagem de aviso sobre JSON inválido ou novo projeto (não erros críticos) -->
                    <div class="status <?php echo (strpos(strtolower($load_error_message), 'corrompido') !== false || strpos(strtolower($load_error_message), 'inválida') !== false || strpos(strtolower($load_error_message), 'inesperado') !== false) ? 'warning' : 'info'; ?>" style="margin-bottom: 20px;">
                        <i class="fas <?php echo (strpos(strtolower($load_error_message), 'corrompido') !== false || strpos(strtolower($load_error_message), 'inválida') !== false || strpos(strtolower($load_error_message), 'inesperado') !== false) ? 'fa-exclamation-triangle' : 'fa-info-circle'; ?>"></i> <?php echo htmlspecialchars($load_error_message); ?>
                    </div>
                 <?php endif; ?>

                <!-- Seção de Controles -->
                <section class="config-actions-area">
                     <div class="faixa-etaria-selector">
                        <label for="faixa-etaria-select">Faixa Etária:</label>
                        <select id="faixa-etaria-select" title="Selecione a faixa etária">
                            <option value="">-- Selecione --</option>
                            <?php foreach ($faixa_pnae_opcoes as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($key === $faixa_etaria_inicial_key) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="action-buttons">
                         <!-- Botão Salvar Projeto -->
                         <button type="button" id="save-project-btn" class="action-button" title="Salvar alterações neste cardápio">
                             <i class="fas fa-save"></i> Salvar
                         </button>
                         <span id="save-status" style="font-size: 0.8em; color: var(--text-light); margin-left: 5px; display: inline-block; min-width: 50px; text-align: right;"></span>
                         <!-- Restante dos botões -->
                         <button type="button" id="nova-preparacao-btn" class="action-button" title="Criar nova preparação"><i class="fas fa-mortar-pestle"></i> Preparação</button>
                         <button type="button" id="add-refeicao-btn" class="action-button" title="Adicionar nova refeição"><i class="fas fa-plus"></i> Refeição</button>
                        <button type="button" id="calcular-nutrientes-btn" class="action-button" title="Calcular totais"><i class="fas fa-sync-alt"></i> Calcular</button>
                        <div class="item-manipulation-buttons">
                            <button type="button" id="item-copy-btn" class="action-button" title="Copiar item" disabled><i class="fas fa-copy"></i></button>
                            <button type="button" id="item-cut-btn" class="action-button" title="Recortar item" disabled><i class="fas fa-cut"></i></button>
                            <button type="button" id="item-paste-btn" class="action-button" title="Colar item" disabled><i class="fas fa-paste"></i></button>
                            <button type="button" id="item-delete-btn" class="action-button cancel" title="Excluir item" disabled><i class="fas fa-trash"></i></button>
                        </div>
                        <button type="button" id="exportar-xlsx-btn" class="action-button export-excel" title="Exportar Excel"><i class="fas fa-file-excel"></i> Exportar</button>
                        <button type="button" id="limpar-cardapio-btn" class="action-button cancel" title="Limpar tudo"><i class="fas fa-trash"></i> Limpar</button>
                    </div>
                </section>

                <!-- Tabela de Montagem -->
                 <div style="overflow-x: auto;">
                    <table class="cardapio-montagem-table" id="cardapio-grid">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>

                 <!-- Formulário oculto para exportação -->
                 <form id="export-xlsx-form" action="export_xlsx.php" method="post" target="_blank" style="display: none;">
                    <input type="hidden" name="export_data" id="export-data-input">
                    <input type="hidden" name="project_name" id="export-project-name-input" value="<?php echo htmlspecialchars($projeto_nome); ?>">
                 </form>

                <!-- Seção de Resultados -->
                <section class="resultados-simplificados-section">
                    <h3>Análise Diária e Média Semanal</h3>
                     <div style="overflow-x: auto; margin-bottom: 20px;">
                          <table class="cardapio-montagem-table" id="resultados-diarios-table">
               <thead>
                    <!-- Linha 1: Títulos principais -->
                    <tr>
                         <th rowspan="2" style="vertical-align: bottom;">DIAS DA SEMANA</th>
                         <th rowspan="2" style="vertical-align: middle;">Energia (Kcal)</th>
                         <th colspan="3" style="text-align: center;">Proteína</th>
                         <th colspan="3" style="text-align: center;">Lipídeos</th>
                         <th colspan="3" style="text-align: center;">Carboidratos</th>
                         <th rowspan="2" style="vertical-align: middle;">Cálcio (mg)</th>
                         <th rowspan="2" style="vertical-align: middle;">Ferro (mg)</th>
                         <th rowspan="2" style="vertical-align: middle;">Vit. A (mcg RAE)</th>
                         <th rowspan="2" style="vertical-align: middle;">Vit. C (mg)</th>
                         <th rowspan="2" style="vertical-align: middle;">Sódio (mg)</th>
                    </tr>
                    <!-- Linha 2: Subtítulos (g, Kcal, % VET) -->
                    <tr>
                         <!-- Proteína -->
                         <th>(g)</th>
                         <th>Kcal</th>
                         <th>% VET</th>
                         <!-- Lipídeos -->
                         <th>(g)</th>
                         <th>Kcal</th>
                         <th>% VET</th>
                         <!-- Carboidratos -->
                         <th>(g)</th>
                         <th>Kcal</th>
                         <th>% VET</th>
                    </tr>
               </thead>
               <tbody>
                    <?php foreach ($dias_keys as $dk): $dia_nome = $dias_semana_nomes[array_search($dk, $dias_keys)]; ?>
                       <tr id="daily-<?php echo $dk; ?>">
                         <td><?php echo $dia_nome; ?></td>
                         <td data-nutrient="kcal">0</td>
                         <!-- Proteína -->
                         <td data-nutrient="ptn">0,0</td>
                         <td data-nutrient="ptn_kcal">0,00</td> <!-- Novo Kcal -->
                         <td data-nutrient="ptn_vet">-</td>
                         <!-- Lipídeos -->
                         <td data-nutrient="lpd">0,0</td>
                         <td data-nutrient="lpd_kcal">0,00</td> <!-- Novo Kcal -->
                         <td data-nutrient="lpd_vet">-</td>
                         <!-- Carboidratos -->
                         <td data-nutrient="cho">0,0</td>
                         <td data-nutrient="cho_kcal">0,00</td> <!-- Novo Kcal -->
                         <td data-nutrient="cho_vet">-</td>
                         <!-- Micros -->
                         <td data-nutrient="ca">0</td>
                         <td data-nutrient="fe">0,0</td>
                         <td data-nutrient="vita">0</td>
                         <td data-nutrient="vitc">0,0</td>
                         <td data-nutrient="na">0</td>
                       </tr>
                     <?php endforeach; ?>
               </tbody>
               <tfoot>
                    <tr id="weekly-avg">
                         <td>Média semanal</td>
                         <td data-nutrient="kcal">0</td>
                         <!-- Proteína -->
                         <td data-nutrient="ptn">0,0</td>
                         <td data-nutrient="ptn_kcal">0,00</td> <!-- Novo Kcal -->
                         <td data-nutrient="ptn_vet">-</td>
                         <!-- Lipídeos -->
                         <td data-nutrient="lpd">0,0</td>
                         <td data-nutrient="lpd_kcal">0,00</td> <!-- Novo Kcal -->
                         <td data-nutrient="lpd_vet">-</td>
                         <!-- Carboidratos -->
                         <td data-nutrient="cho">0,0</td>
                         <td data-nutrient="cho_kcal">0,00</td> <!-- Novo Kcal -->
                         <td data-nutrient="cho_vet">-</td>
                          <!-- Micros -->
                         <td data-nutrient="ca">0</td>
                         <td data-nutrient="fe">0,0</td>
                         <td data-nutrient="vita">0</td>
                         <td data-nutrient="vitc">0,0</td>
                         <td data-nutrient="na">0</td>
                    </tr>
               </tfoot>
          </table>
                     </div>
                     <div id="status-message" class="status info"><i class="fas fa-info-circle"></i> Carregando...</div>
                </section>


 <section id="referencia-pnae-section" style="margin-top: 30px;">
                    <h3 id="referencia-pnae-title">Valores de Referência PNAE</h3>
                    <div style="overflow-x: auto; margin-bottom: 20px;">
                        <div id="referencia-pnae-container">
                            <!-- A tabela será inserida aqui pelo JavaScript -->
                             <p style="text-align: center; color: var(--text-light); padding: 15px; background-color: #f8f9fa; border: 1px solid var(--light-border); border-radius: var(--border-radius);">
                                Selecione uma Faixa Etária acima para visualizar os valores de referência.
                            </p>
                        </div>
                    </div>
                </section>

            </div> <!-- /.container.page-index -->

             <!-- Modais -->
             <div id="selection-modal" class="modal-overlay"> <div class="modal-content"> <div class="modal-header"> <h2 id="modal-title">Adicionar Alimentos</h2> <button type="button" class="modal-close-btn" title="Fechar">×</button> </div> <div class="modal-body"> <div id="modal-search-container"> <input type="text" id="modal-search" placeholder="Digite para buscar..." autocomplete="off"> </div> <div id="modal-search-items"> <h5>Selecione os itens:</h5> <ul class="modal-search-list"></ul> </div> </div> <div class="modal-footer"> <button type="button" id="modal-cancel" class="action-button cancel modal-button"><i class="fas fa-times"></i> Cancelar</button> <button type="button" id="modal-confirm" class="action-button confirm modal-button"><i class="fas fa-check"></i> Processar</button> </div> </div> </div>
             <div id="quantity-edit-modal" class="modal-overlay"> <div class="modal-content"> <div class="modal-header"> <h2 id="quantity-modal-title">Editar Quantidade</h2> <button type="button" class="modal-close-btn" title="Fechar">×</button> </div> <div class="modal-body"> <h3 id="quantity-modal-food-name">Nome Alimento</h3> <div> <label for="quantity-edit-input">Quantidade:</label> <input type="number" id="quantity-edit-input" min="1" step="1" value="100"> <span>g</span> </div> <small>Qtd. atualizada para Grupo <span id="quantity-modal-group-number"></span>.</small> </div> <div class="modal-footer"> <button type="button" id="quantity-modal-cancel" class="action-button cancel modal-button"><i class="fas fa-times"></i> Cancelar</button> <button type="button" id="quantity-modal-confirm" class="action-button confirm modal-button"><i class="fas fa-save"></i> Salvar</button> </div> </div> </div>
             <div id="instance-group-choice-modal" class="modal-overlay"> <div class="modal-content"> <div class="modal-header"> <h2 id="group-choice-modal-title">Escolher Grupo</h2> <button type="button" class="modal-close-btn" title="Fechar">×</button> </div> <div class="modal-body"> <h3 id="group-choice-food-name">Adicionar [Nome]?</h3> <p>Este item já existe. Escolha como adicioná-lo:</p> <ul id="group-choice-options"></ul> </div> <div class="modal-footer"> <button type="button" id="group-choice-cancel" class="action-button cancel modal-button"><i class="fas fa-times"></i> Cancelar</button> <button type="button" id="group-choice-confirm" class="action-button confirm modal-button"><i class="fas fa-check"></i> Confirmar</button> </div> </div> </div>
             <div id="nova-preparacao-modal" class="modal-overlay"> <div class="modal-content"> <div class="modal-header"> <h2>Criar Nova Preparação</h2> <button type="button" class="modal-close-btn" title="Fechar">×</button> </div> <div class="modal-body"> <div class="modal-section" id="prep-details-section"> <h5>Detalhes</h5> <div> <label for="prep-nome">Nome:</label> <input type="text" id="prep-nome" placeholder="Ex: Farofinha de Ovo" required> </div> <div> <label for="prep-porcao-padrao">Porção Padrão (g):</label> <input type="number" id="prep-porcao-padrao" value="150" min="1" step="1"> </div> </div> <div class="modal-section" id="prep-ingredients-section"> <h5>Ingredientes</h5> <div style="position: relative;"> <label for="prep-ingredient-search">Buscar Ingrediente Base:</label> <input type="text" id="prep-ingredient-search" placeholder="Buscar..."> <ul id="prep-search-results"></ul> </div> <div> <label>Ingredientes Adicionados:</label> <ul id="prep-ingredients-list"> <li class="placeholder">- Nenhum -</li> </ul> </div> </div> </div> <div class="modal-footer"> <button type="button" id="prep-cancel" class="action-button cancel modal-button"><i class="fas fa-times"></i> Cancelar</button> <button type="button" id="prep-save" class="action-button confirm modal-button"><i class="fas fa-save"></i> Salvar</button> </div> </div> </div>

        <?php endif; // Fim do else que verifica erros de carregamento ?>
    </main>

    <!-- Footer HTML -->
    <footer class="main-footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> Montador de Cardápio. Todos os direitos reservados.</p>
    </footer>

    <!-- Scripts JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
    //<![CDATA[
    $(document).ready(function() {
        // --- Variáveis Globais e Configurações ---
        const currentProjectId = $('#current-project-id').val();
        const statusMessage = $('#status-message');
        const faixaEtariaSelect = $('#faixa-etaria-select');
        const dadosBaseOk = <?php echo $dados_base_ok ? 'true' : 'false'; ?>;
        const loadError = <?php echo $db_connection_error ? 'true' : 'false'; ?>;
        const $tableBody = $('#cardapio-grid tbody');
        const $tableHead = $('#cardapio-grid thead');
        let saveTimeout;
        let isSaving = false;
        const saveStatusSpan = $('#save-status');
                        // --- Dados de Referência PNAE (Transcritos da Imagem) ---
               // --- Dados de Referência PNAE (Ajustados conforme imagem completa) ---
        const referenciaValoresPnae = {
            'bercario': { // Creche (7-11 meses)
                faixa: 'Creche (7-11 meses)',
                colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' },
                    { key: 'n_ref', label: 'Nº ref.' },
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' }, // Coluna 10%
                    { key: 'proteina_g_15', label: '15% VET' },              // Coluna 15%
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },    // Coluna 15%
                    { key: 'lipidio_g_30', label: '30% VET' },              // Coluna 30%
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' }, // Coluna 55%
                    { key: 'carboidrato_g_65', label: '65% VET' },              // Coluna 65% (Valor 'a' na imagem, usaremos '-')
                    { key: 'ca_mg', label: 'Ca (mg)' },
                    { key: 'fe_mg', label: 'Fe (mg)' },
                    { key: 'vita_ug', label: 'Vit. A (µg RAE)' },
                    { key: 'vitc_mg', label: 'Vit. C (mg)' }
                ],
                refs: [
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 203, proteina_g_10: 5, proteina_g_15: 8, lipidio_g_15: 3, lipidio_g_30: 7, carboidrato_g_55: 28, carboidrato_g_65: 33, ca_mg: 78, fe_mg: 2.1, vita_ug: 150, vitc_mg: 15 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 475, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 65, carboidrato_g_65: 77, ca_mg: 182, fe_mg: 4.8, vita_ug: 350, vitc_mg: 35 } }
                ]
            },
            'creche': { // Creche (1-3 anos)
                faixa: 'Creche (1-3 anos)',
                 colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' },
                    { key: 'n_ref', label: 'Nº ref.' },
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' },
                    { key: 'proteina_g_15', label: '15% VET' },
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },
                    { key: 'lipidio_g_30', label: '30% VET' },
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' },
                    { key: 'carboidrato_g_65', label: '65% VET' },
                    { key: 'ca_mg', label: 'Ca (mg)' },
                    { key: 'fe_mg', label: 'Fe (mg)' },
                    { key: 'vita_ug', label: 'Vit. A (µg RAE)' },
                    { key: 'vitc_mg', label: 'Vit. C (mg)' }
                ],
                refs: [
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 304, proteina_g_10: 8, proteina_g_15: 11, lipidio_g_15: 5, lipidio_g_30: 10, carboidrato_g_55: 42, carboidrato_g_65: 49, ca_mg: 150, fe_mg: 0.9, vita_ug: 63, vitc_mg: 3.9 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 708, proteina_g_10: 18, proteina_g_15: 27, lipidio_g_15: 12, lipidio_g_30: 24, carboidrato_g_55: 97, carboidrato_g_65: 115, ca_mg: 350, fe_mg: 2.1, vita_ug: 147, vitc_mg: 9.1 } }
                ]
            },
            'pre_escola': {
                 faixa: 'Pré-escola',
                 colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' }, // Label ajustado
                    { key: 'n_ref', label: 'Nº ref.' },              // Label ajustado
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' },
                    { key: 'proteina_g_15', label: '15% VET' },
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },
                    { key: 'lipidio_g_30', label: '30% VET' },
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' },
                    { key: 'carboidrato_g_65', label: '65% VET' },
                    { key: 'na_mg', label: 'Na (mg)' }
                 ],
                 refs: [ // 3 linhas de dados
                    { nivel: '20% das necessidades nutricionais/dia', n_ref: '1 refeição', valores: { energia: 270, proteina_g_10: 7, proteina_g_15: 10, lipidio_g_15: 5, lipidio_g_30: 9, carboidrato_g_55: 37, carboidrato_g_65: 44, na_mg: 600 } },
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 405, proteina_g_10: 10, proteina_g_15: 15, lipidio_g_15: 7, lipidio_g_30: 14, carboidrato_g_55: 56, carboidrato_g_65: 66, na_mg: 800 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 945, proteina_g_10: 24, proteina_g_15: 35, lipidio_g_15: 16, lipidio_g_30: 32, carboidrato_g_55: 130, carboidrato_g_65: 154, na_mg: 1400 } }
                 ]
            },
            'fund_6_10': {
                 faixa: 'Ensino Fundamental (6 a 10 anos)',
                 colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' },
                    { key: 'n_ref', label: 'Nº ref.' },
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' },
                    { key: 'proteina_g_15', label: '15% VET' },
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },
                    { key: 'lipidio_g_30', label: '30% VET' },
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' },
                    { key: 'carboidrato_g_65', label: '65% VET' },
                    { key: 'na_mg', label: 'Na (mg)' }
                 ],
                 refs: [
                    { nivel: '20% das necessidades nutricionais/dia', n_ref: '1 refeição', valores: { energia: 329, proteina_g_10: 8, proteina_g_15: 12, lipidio_g_15: 5, lipidio_g_30: 11, carboidrato_g_55: 45, carboidrato_g_65: 53, na_mg: 600 } },
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 493, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 68, carboidrato_g_65: 80, na_mg: 800 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 1150, proteina_g_10: 29, proteina_g_15: 43, lipidio_g_15: 19, lipidio_g_30: 38, carboidrato_g_55: 158, carboidrato_g_65: 187, na_mg: 1400 } }
                 ]
            },
             'fund_11_15': {
                 faixa: 'Ensino Fundamental (11 a 15 anos)',
                 colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' },
                    { key: 'n_ref', label: 'Nº ref.' },
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' },
                    { key: 'proteina_g_15', label: '15% VET' },
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },
                    { key: 'lipidio_g_30', label: '30% VET' },
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' },
                    { key: 'carboidrato_g_65', label: '65% VET' },
                    { key: 'na_mg', label: 'Na (mg)' }
                 ],
                 refs: [
                    { nivel: '20% das necessidades nutricionais/dia', n_ref: '1 refeição', valores: { energia: 473, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 65, carboidrato_g_65: 77, na_mg: 600 } },
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 710, proteina_g_10: 18, proteina_g_15: 27, lipidio_g_15: 12, lipidio_g_30: 24, carboidrato_g_55: 98, carboidrato_g_65: 115, na_mg: 800 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 1656, proteina_g_10: 41, proteina_g_15: 62, lipidio_g_15: 28, lipidio_g_30: 55, carboidrato_g_55: 228, carboidrato_g_65: 269, na_mg: 1400 } }
                 ]
            },
            'medio': { // Assumindo chave 'medio' no PHP
                 faixa: 'Ensino Médio',
                 colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' },
                    { key: 'n_ref', label: 'Nº ref.' },
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' },
                    { key: 'proteina_g_15', label: '15% VET' },
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },
                    { key: 'lipidio_g_30', label: '30% VET' },
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' },
                    { key: 'carboidrato_g_65', label: '65% VET' },
                    { key: 'na_mg', label: 'Na (mg)' }
                 ],
                 refs: [
                    { nivel: '20% das necessidades nutricionais/dia', n_ref: '1 refeição', valores: { energia: 543, proteina_g_10: 14, proteina_g_15: 20, lipidio_g_15: 9, lipidio_g_30: 18, carboidrato_g_55: 75, carboidrato_g_65: 88, na_mg: 600 } },
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 815, proteina_g_10: 20, proteina_g_15: 31, lipidio_g_15: 14, lipidio_g_30: 27, carboidrato_g_55: 112, carboidrato_g_65: 132, na_mg: 800 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 1902, proteina_g_10: 48, proteina_g_15: 71, lipidio_g_15: 32, lipidio_g_30: 63, carboidrato_g_55: 262, carboidrato_g_65: 309, na_mg: 1400 } }
                 ]
            },
            'eja_19_30': { // Assumindo chave 'eja_19_30' no PHP
                 faixa: 'Educação de Jovens e Adultos (19 a 30 anos)',
                 colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' },
                    { key: 'n_ref', label: 'Nº ref.' },
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' },
                    { key: 'proteina_g_15', label: '15% VET' },
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },
                    { key: 'lipidio_g_30', label: '30% VET' },
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' },
                    { key: 'carboidrato_g_65', label: '65% VET' },
                    { key: 'na_mg', label: 'Na (mg)' }
                 ],
                 refs: [
                    { nivel: '20% das necessidades nutricionais/dia', n_ref: '1 refeição', valores: { energia: 477, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 66, carboidrato_g_65: 77, na_mg: 600 } },
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 715, proteina_g_10: 18, proteina_g_15: 27, lipidio_g_15: 12, lipidio_g_30: 24, carboidrato_g_55: 98, carboidrato_g_65: 116, na_mg: 800 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 1668, proteina_g_10: 42, proteina_g_15: 63, lipidio_g_15: 28, lipidio_g_30: 56, carboidrato_g_55: 229, carboidrato_g_65: 271, na_mg: 1400 } }
                 ]
            },
            'eja_31_60': { // Assumindo chave 'eja_31_60' no PHP
                 faixa: 'Educação de Jovens e Adultos (31 a 60 anos)',
                 colunas: [
                    { key: 'nivel', label: 'Valores de referência para:' },
                    { key: 'n_ref', label: 'Nº ref.' },
                    { key: 'energia', label: 'Energia (kcal)' },
                    { key: 'proteina_g_10', label: 'PROTEÍNAS (g) 10% VET' },
                    { key: 'proteina_g_15', label: '15% VET' },
                    { key: 'lipidio_g_15', label: 'LIPÍDIOS (g) 15% VET' },
                    { key: 'lipidio_g_30', label: '30% VET' },
                    { key: 'carboidrato_g_55', label: 'CARBOIDRATOS (g) 55% VET' },
                    { key: 'carboidrato_g_65', label: '65% VET' },
                    { key: 'na_mg', label: 'Na (mg)' }
                 ],
                 refs: [
                    { nivel: '20% das necessidades nutricionais/dia', n_ref: '1 refeição', valores: { energia: 459, proteina_g_10: 11, proteina_g_15: 17, lipidio_g_15: 8, lipidio_g_30: 15, carboidrato_g_55: 63, carboidrato_g_65: 75, na_mg: 600 } },
                    { nivel: '30% das necessidades nutricionais/dia', n_ref: '2 refeições', valores: { energia: 689, proteina_g_10: 17, proteina_g_15: 26, lipidio_g_15: 11, lipidio_g_30: 23, carboidrato_g_55: 95, carboidrato_g_65: 112, na_mg: 800 } },
                    { nivel: '70% das necessidades nutricionais/dia', n_ref: '3 refeições', valores: { energia: 1607, proteina_g_10: 40, proteina_g_15: 60, lipidio_g_15: 27, lipidio_g_30: 54, carboidrato_g_55: 221, carboidrato_g_65: 261, na_mg: 1400 } }
                 ]
            }
        };

        // Interrompe se erro fatal
        if (loadError || !dadosBaseOk || !currentProjectId) {
             console.error("ERRO FATAL: Carregamento inicial falhou ou ID do projeto ausente. Script JS interrompido.");
             if (!loadError && !dadosBaseOk) { showStatus('error', 'Erro: Dados base de alimentos não carregados.', 'fa-database'); }
             else if (!currentProjectId && !loadError) { showStatus('error', 'Erro: ID do projeto não especificado.', 'fa-link-slash'); }
             $('.action-button, #faixa-etaria-select, input, select').prop('disabled', true).css('cursor', 'not-allowed');
             return;
        }
        
        
        

        // --- Dados vindos do PHP ---
        let alimentosCompletos = <?php echo $alimentos_completos_json; ?>;
        let alimentosIdNomeMap = <?php echo $alimentos_disponiveis_json; ?>;
        const todasPorcoesDb = <?php echo $todas_porcoes_db_json; ?>;
        let cardapioAtual = <?php echo $cardapio_inicial_json; ?>;
        let refeicoesLayout = <?php echo $refeicoes_layout_json; ?>;
        let datasDias = <?php echo $datas_dias_json; ?>;
        let diasDesativados = <?php echo $dias_desativados_json; ?>;
        let faixaEtariaSelecionada = <?php echo json_encode($faixa_etaria_inicial_key); ?>;
        const initialInstanceGroups = <?php echo $initial_instance_groups_json; ?>;
        const diasKeys = <?php echo $dias_keys_json; ?>;
        const diasNomesMap = <?php echo $dias_nomes_json; ?>;
        let alimentosParaModalListaJS = JSON.parse(JSON.stringify(alimentosCompletos)); // Clonar para não modificar o original

        // Variáveis JS de estado
        let requestActive = false;
        let calculationTimeout;
        let currentEditingLi = null;
        let currentTargetQtySpan = null;
        let currentEditingPlacementId = null;
        let currentEditingInstanceGroup = null;
        const mainSelectionModal = $('#selection-modal');
        const quantityEditModal = $('#quantity-edit-modal');
        const groupChoiceModal = $('#instance-group-choice-modal');
        const novaPreparacaoModal = $('#nova-preparacao-modal');
        let modalCurrentSelections = new Set();
        let foodInstanceGroupCounters = {};
        let groupChoiceQueue = [];
        let selectedItemLi = null;
        let gridHasChanged = false;
        


        
         let selectedItemsCollection = $(); // <--- SUBSTITUI selectedItemLi
        let lastClickedLi = null; // Para futuro Shift+Click (opcional)
        let targetCellForPaste = null;
        let internalClipboard = { type: null, itemsData: [] }; // <-- MODIFICADO para array

        // --- Funções Essenciais ---
        function showStatus(type, message, iconClass = 'fa-info-circle') { statusMessage.removeClass('loading error success warning info').addClass(`status ${type}`).html(`<i class="fas ${iconClass}"></i> ${message}`); }
        function sanitizeString(str) { if (typeof str !== 'string') return ''; return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9\s]/g, ''); }
        function generatePlacementId() { return `place_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`; }
        function generateRefKey() { return `ref_dyn_${Date.now()}_${Math.random().toString(36).substring(2, 5)}`; }
        function generatePreparacaoId() { return `prep_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`; }
        function getNextInstanceGroupNumber(foodId) { return (foodInstanceGroupCounters[foodId] || 0) + 1; }
        function findExistingInstanceGroups(foodId) { const groups = {}; diasKeys.forEach(dia => { if (!diasDesativados[dia] && cardapioAtual[dia]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[dia][refKey]) { cardapioAtual[dia][refKey].forEach(item => { if (item && item.foodId === foodId && item.instanceGroup && item.qty) { if (!(item.instanceGroup in groups)) { groups[item.instanceGroup] = item.qty; } } }); } }); } }); return Object.entries(groups).map(([group, qty]) => ({ group: parseInt(group, 10), qty: parseInt(qty, 10) })).sort((a, b) => a.group - b.group); }
        function initializeInstanceGroupCounters() { console.log("Recalculando contadores de grupos..."); foodInstanceGroupCounters = {}; diasKeys.forEach(dia => { if (cardapioAtual[dia]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[dia][refKey] && Array.isArray(cardapioAtual[dia][refKey])) { cardapioAtual[dia][refKey].forEach(item => { if (item && item.foodId && item.instanceGroup) { const currentMax = foodInstanceGroupCounters[item.foodId] || 0; if (item.instanceGroup > currentMax) { foodInstanceGroupCounters[item.foodId] = item.instanceGroup; } } }); } }); } }); if (typeof initialInstanceGroups === 'object' && initialInstanceGroups !== null) { for (const foodId in initialInstanceGroups) { const initialMax = initialInstanceGroups[foodId]; const currentMax = foodInstanceGroupCounters[foodId] || 0; if (initialMax > currentMax) { foodInstanceGroupCounters[foodId] = initialMax; } } } console.log("Contadores atualizados:", foodInstanceGroupCounters); }
        function htmlspecialchars(str) { if (typeof str !== 'string') return ''; const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }; return str.replace(/[&<>"']/g, m => map[m]); }
        function updateRemoveButtonsVisibility() { const rows = $tableBody.find('tr'); rows.each(function(){ $(this).find('td.action-cell').css('visibility', rows.length <= 1 ? 'hidden' : 'visible'); }); /* console.log("Visibilidade dos botões de remover atualizada. Linhas:", rows.length); */ }

        // --- Funções de Salvamento e Estado ---
        function markGridChanged() { if (!currentProjectId) return; gridHasChanged = true; console.log("Grid marcada como alterada."); saveStatusSpan.text('Alterado').css('color', 'orange'); $('#save-project-btn').removeClass('saved').addClass('unsaved'); clearTimeout(saveTimeout); saveTimeout = setTimeout(saveProjectData, 5000); }
        function getGridStateForSaving() { const estadoAtual = { refeicoes: { ...refeicoesLayout }, dias: { ...cardapioAtual }, datas_dias: { ...datasDias }, dias_desativados: { ...diasDesativados }, faixa_etaria_selecionada: faixaEtariaSelect.val() || null }; return estadoAtual; }
        function saveProjectData() { if (!gridHasChanged || isSaving || !currentProjectId) { console.log("Salvar ignorado:", { gridHasChanged, isSaving, currentProjectId }); return; } isSaving = true; gridHasChanged = false; saveStatusSpan.text('Salvando...').css('color', 'var(--info-color)'); $('#save-project-btn').prop('disabled', true); const dataToSave = getGridStateForSaving(); const jsonData = JSON.stringify(dataToSave); console.log("Enviando para salvar Projeto ID:", currentProjectId); $.ajax({ url: 'save_project.php', method: 'POST', data: { projeto_id: currentProjectId, dados_json: jsonData }, dataType: 'json', success: function(response) { if (response.success) { console.log("Projeto salvo com sucesso!"); saveStatusSpan.text('Salvo').css('color', 'var(--success-color)'); $('#save-project-btn').removeClass('unsaved').addClass('saved'); setTimeout(() => { if (!gridHasChanged) { saveStatusSpan.text(''); } }, 3000); } else { console.error("Erro ao salvar (API):", response.message); saveStatusSpan.text('Erro!').css('color', 'var(--error-color)'); gridHasChanged = true; $('#save-project-btn').removeClass('saved').addClass('unsaved'); alert('Erro ao salvar: ' + (response.message || 'Erro desconhecido.')); } }, error: function(jqXHR, textStatus, errorThrown) { console.error("Erro AJAX Salvar:", textStatus, errorThrown, jqXHR.responseText); saveStatusSpan.text('Falha!').css('color', 'var(--error-color)'); gridHasChanged = true; $('#save-project-btn').removeClass('saved').addClass('unsaved'); alert('Erro de comunicação ao salvar. Verifique console.'); }, complete: function() { isSaving = false; $('#save-project-btn').prop('disabled', false); } }); }
        $('#save-project-btn').on('click', function() { clearTimeout(saveTimeout); saveProjectData(); });

        // --- Renderização Dinâmica da Tabela ---
        function renderCardapioGrid() { console.log("Iniciando renderCardapioGrid..."); $tableHead.empty(); $tableBody.empty(); let headerHtml = '<tr><th style="width: 150px;">Refeição</th><th style="width: 90px;">Horário</th>'; diasKeys.forEach((diaKey) => { const diaNome = diasNomesMap[diaKey] || diaKey.toUpperCase(); const isDesativado = diasDesativados[diaKey] ?? false; const dataDia = datasDias[diaKey] ?? ''; const thClass = isDesativado ? 'dia-desativado' : ''; const btnClass = isDesativado ? 'active' : ''; const btnIcon = isDesativado ? 'fa-toggle-on' : 'fa-toggle-off'; const btnTitle = isDesativado ? `Marcar ${diaNome} como Ativo` : `Marcar ${diaNome} como Feriado`; headerHtml += `<th data-dia-col="${diaKey}" style="min-width: 180px;" class="${thClass}"><span class="dia-nome">${diaNome}</span><div class="dia-controles"><input type="text" class="dia-data-input" data-dia="${diaKey}" placeholder="dd/mm" size="5" title="Opcional: Data" value="${htmlspecialchars(dataDia)}"><button type="button" class="toggle-feriado-btn ${btnClass}" data-dia="${diaKey}" title="${btnTitle}"><i class="fas ${btnIcon}"></i> Feriado</button></div></th>`; }); headerHtml += '<th style="width: 40px;">Ação</th></tr>'; $tableHead.html(headerHtml); console.log("Cabeçalho renderizado."); if (typeof refeicoesLayout !== 'object' || Object.keys(refeicoesLayout).length === 0) { console.error("ERRO: refeicoesLayout inválido!", refeicoesLayout); $tableBody.html('<tr><td colspan="'+ (diasKeys.length + 3) +'" style="color:red; text-align:center; padding: 20px;">Erro: Layout de refeições não definido.</td></tr>'); return; } console.log("Renderizando linhas para refeições:", Object.keys(refeicoesLayout)); Object.entries(refeicoesLayout).forEach(([refKey, refInfo]) => { $tableBody.append(createRowHTML(refKey, refInfo.label, refInfo.horario)); }); console.log("Preenchendo células..."); $tableBody.find('tr').each(function() { const row = $(this); const refKey = row.data('refeicao-key'); if (!refKey) return; row.find('td.editable-cell').each(function(){ const cell = $(this); const diaKey = cell.data('dia'); const itemsDaCelula = cardapioAtual[diaKey]?.[refKey] || []; updateCellDisplay(cell, itemsDaCelula); }); }); updateRemoveButtonsVisibility(); console.log("renderCardapioGrid concluído."); }
        function createRowHTML(k,l,h){ let cellsHtml = ''; diasKeys.forEach((d)=>{ const diaNome = diasNomesMap[d] || d.toUpperCase(); const isDisabled = diasDesativados[d] ?? false; const disabledClass = isDisabled ? ' dia-desativado' : ''; const itemsDaCelula = cardapioAtual[d]?.[k] || []; const itemsJson = JSON.stringify(itemsDaCelula); cellsHtml += `<td class="editable-cell${disabledClass}" data-label="${diaNome}" data-dia="${d}" title="Adicionar/Colar item"><button type="button" class="add-item-cell-btn" title="Add ${diaNome} - ${htmlspecialchars(l)}"><i class="fas fa-plus"></i></button><ul class="selected-items-list" data-selecionados='${itemsJson}'><li class="placeholder">- Vazio -</li></ul></td>`;}); const removeBtnHtml = `<td class="action-cell" data-label="Remover"><button type="button" class="remove-row-btn" title="Remover"><i class="fas fa-trash-alt"></i></button></td>`; return `<tr data-refeicao-key="${k}"><td class="label-cell refeicao-label" data-label="Refeição" title="Editar"><span class="editable-label">${htmlspecialchars(l)}</span><input type="text" class="label-input" value="${htmlspecialchars(l)}"></td><td class="label-cell horario-cell" data-label="Horário" title="Editar"><span class="editable-label">${htmlspecialchars(h)}</span><input type="text" class="label-input" value="${htmlspecialchars(h)}"></td>${cellsHtml}${removeBtnHtml}</tr>`; }

         // --- Funções de Manipulação da Grade ---
         function updateCellDisplay(cellElement, itemsData) { const listElement = cellElement.find('ul.selected-items-list'); listElement.empty(); if (itemsData && Array.isArray(itemsData) && itemsData.length > 0) { const sortedItems = [...itemsData].sort((a, b) => { const nA = alimentosIdNomeMap[a.foodId] || ''; const nB = alimentosIdNomeMap[b.foodId] || ''; const c = nA.localeCompare(nB, 'pt-BR', { sensitivity: 'base' }); if (c === 0) return (a.instanceGroup || 1) - (b.instanceGroup || 1); return c; }); sortedItems.forEach(item => { if (!item || typeof item.foodId==='undefined' || !alimentosIdNomeMap[item.foodId] || typeof item.instanceGroup==='undefined' || typeof item.placementId==='undefined') { console.warn("Item inválido ignorado em updateCellDisplay:", item); return; } const fId=item.foodId.toString(), iG=item.instanceGroup, pId=item.placementId; const fN=alimentosIdNomeMap[fId]; const vQ=Math.max(1,parseInt(item.qty,10)||100); const isPrep = (alimentosCompletos && alimentosCompletos[fId]) ? (alimentosCompletos[fId].isPreparacao ?? false) : false; const li = $(`<li data-food-id="${fId}" data-instance-group="${iG}" data-placement-id="${pId}" title="Clique para selecionar"></li>`); const nameSpan = $(`<span class="item-name"></span>`).text(fN).append(`<sup class="item-instance-group-number">${iG}</sup>`); if (isPrep) { nameSpan.prepend('<i class="fas fa-mortar-pestle" style="color: var(--warning-dark); margin-right: 4px; font-size: 0.9em;" title="Preparação"></i> '); } const qtySpan = $(`<span class="item-qty-display" title="Qtd Grupo ${iG}"></span>`).text(vQ+'g'); const editBtn = $(`<button type="button" class="item-edit-qty-btn" title="Editar Qtd (Grupo ${iG})"><i class="fas fa-pencil-alt"></i></button>`); const removeBtn = $(`<button type="button" class="item-remove-btn" title="Remover ${fN} (Grupo ${iG})"><i class="fas fa-times"></i></button>`); const detailsDiv = $('<div class="item-details"></div>').append(nameSpan).append(qtySpan); const actionsDiv = $('<div class="item-actions"></div>').append(editBtn).append(removeBtn); li.append(detailsDiv).append(actionsDiv); if (selectedItemLi && selectedItemLi.data('placement-id') === pId) { li.addClass('item-selecionado'); } listElement.append(li); }); } else { listElement.append('<li class="placeholder">- Vazio -</li>'); } }
         function addItemToCell(cellElement, foodId, instanceGroup, fixedQuantity) { if (!dadosBaseOk || !cellElement || cellElement.length === 0 || cellElement.hasClass('dia-desativado') || !foodId || !instanceGroup || !alimentosIdNomeMap[foodId]) { console.error("addItemToCell: Inválido."); return; } const diaKey = cellElement.data('dia'); const refKey = cellElement.closest('tr').data('refeicao-key'); if (!cardapioAtual[diaKey]) cardapioAtual[diaKey] = {}; if (!cardapioAtual[diaKey][refKey]) cardapioAtual[diaKey][refKey] = []; let itemQty; if (fixedQuantity !== null && !isNaN(fixedQuantity) && fixedQuantity > 0) { itemQty = parseInt(fixedQuantity, 10); } else { const faixaKey = faixaEtariaSelect.val(); itemQty = 100; if (faixaKey && todasPorcoesDb && todasPorcoesDb[faixaKey] && typeof todasPorcoesDb[faixaKey][foodId] !== 'undefined') { itemQty = parseInt(todasPorcoesDb[faixaKey][foodId], 10); } } itemQty = Math.max(1, isNaN(itemQty) ? 100 : itemQty); const newPlacement = { foodId: foodId, qty: itemQty, instanceGroup: instanceGroup, placementId: generatePlacementId() }; cardapioAtual[diaKey][refKey].push(newPlacement); if (instanceGroup > (foodInstanceGroupCounters[foodId] || 0)) { foodInstanceGroupCounters[foodId] = instanceGroup; } cardapioAtual[diaKey][refKey].sort((a, b) => { const nA = alimentosIdNomeMap[a.foodId] || ''; const nB = alimentosIdNomeMap[b.foodId] || ''; const c = nA.localeCompare(nB, 'pt-BR', { sensitivity: 'base' }); if (c === 0) return (a.instanceGroup || 1) - (b.instanceGroup || 1); return c; }); cellElement.find('ul.selected-items-list').attr('data-selecionados', JSON.stringify(cardapioAtual[diaKey][refKey])); updateCellDisplay(cellElement, cardapioAtual[diaKey][refKey]); if (fixedQuantity !== null) { updateQuantityForInstanceGroup(foodId, instanceGroup, itemQty); } markGridChanged(); }
                    // SUBSTITUIR ESTA FUNÇÃO (Refatorada)
        function removeItemFromCell(liElement) {
            // console.log('[removeItemFromCell] Iniciando para:', liElement?.data('placement-id')); // Log pode ser mantido para debug
            if (!liElement || liElement.length === 0 || liElement.hasClass('placeholder')) {
                // console.error('[removeItemFromCell] ERRO: Elemento inválido.');
                return false; // Retorna falha se o elemento for inválido
            }
            const pId = liElement.data('placement-id');
            const cell = liElement.closest('td.editable-cell');

            // Validações básicas do contexto (célula, pId)
            if (!pId || !cell.length || cell.hasClass('dia-desativado')) {
                // console.error('[removeItemFromCell] ERRO: Contexto inválido.', { pId, cellExists: cell.length, isDisabled: cell.hasClass('dia-desativado') });
                 return false;
            }

            const diaKey = cell.data('dia');
            const refKey = cell.closest('tr').data('refeicao-key');
            // console.log(`[removeItemFromCell] Buscando em cardapioAtual[${diaKey}][${refKey}] para pId: ${pId}`);

            // Verifica se a estrutura de dados existe e é um array
            if (!cardapioAtual[diaKey]?.[refKey] || !Array.isArray(cardapioAtual[diaKey][refKey])) {
                 // console.error('[removeItemFromCell] ERRO: Estrutura de dados cardapioAtual[', diaKey, '][', refKey, '] inválida.');
                 return false;
            }

            const initialLength = cardapioAtual[diaKey][refKey].length;
            // Filtra para criar um NOVO array sem o item com o pId correspondente
            const itemsAfterFilter = cardapioAtual[diaKey][refKey].filter(item => !(item && item.placementId === pId));
            const wasRemoved = itemsAfterFilter.length < initialLength;

            if (wasRemoved) {
                 // ATUALIZA A ESTRUTURA DE DADOS COM O NOVO ARRAY FILTRADO
                 cardapioAtual[diaKey][refKey] = itemsAfterFilter;
                 // console.log(`[removeItemFromCell] Item ${pId} REMOVIDO DOS DADOS de ${diaKey}-${refKey}.`);
                 markGridChanged(); // Marca alteração *aqui* pois os dados mudaram
                 return true; // Retorna sucesso
            } else {
                 // console.warn(`[removeItemFromCell] AVISO: Item ${pId} NÃO encontrado nos dados para remoção em ${diaKey}-${refKey}.`);
                 return false; // Retorna falha
            }
            // NÃO FAZ: Atualização de UI (updateCellDisplay)
            // NÃO FAZ: Atualização de selectedItemsCollection global
            // NÃO FAZ: updateManipulationButtons
            // NÃO FAZ: triggerCalculation ou initializeInstanceGroupCounters
        }
         function updateQuantityForInstanceGroup(foodId, instanceGroup, newQuantity) { let changed = false; diasKeys.forEach(dia => { if (cardapioAtual[dia]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[dia][refKey]) { let cellChanged = false; cardapioAtual[dia][refKey].forEach(item => { if (item && item.foodId === foodId && item.instanceGroup === instanceGroup && item.qty !== newQuantity) { item.qty = newQuantity; cellChanged = true; changed = true; } }); if (cellChanged) { const cellElement = $tableBody.find(`tr[data-refeicao-key="${refKey}"] td[data-dia="${dia}"]`); if(cellElement.length) { cellElement.find('ul.selected-items-list').attr('data-selecionados', JSON.stringify(cardapioAtual[dia][refKey])); updateCellDisplay(cellElement, cardapioAtual[dia][refKey]); } } } }); } }); if (changed) { markGridChanged(); } }
         function updateGridQuantitiesForAgeGroup(newFaixaKey) { console.log(`Atualizando quantidades para faixa: ${newFaixaKey}`); if (!newFaixaKey || !todasPorcoesDb || !todasPorcoesDb[newFaixaKey]) { console.warn("Faixa inválida ou dados ausentes:", newFaixaKey); return; } const porcoesDaFaixa = todasPorcoesDb[newFaixaKey]; let globalChange = false; diasKeys.forEach(diaKey => { if (!diasDesativados[diaKey] && cardapioAtual[diaKey]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[diaKey][refKey]) { let cellDataChanged = false; cardapioAtual[diaKey][refKey].forEach(item => { if (item && item.foodId && item.qty && item.instanceGroup && item.placementId) { const foodId = item.foodId.toString(); const currentQty = parseInt(item.qty, 10); const newDefaultQty = parseInt(porcoesDaFaixa[foodId] ?? 100, 10); const safeNewQty = Math.max(1, isNaN(newDefaultQty) ? 100 : newDefaultQty); if (currentQty !== safeNewQty) { item.qty = safeNewQty; cellDataChanged = true; globalChange = true; } } }); if (cellDataChanged) { const cellElement = $tableBody.find(`tr[data-refeicao-key="${refKey}"] td[data-dia="${diaKey}"]`); if (cellElement.length) { cellElement.find('ul.selected-items-list').attr('data-selecionados', JSON.stringify(cardapioAtual[diaKey][refKey])); updateCellDisplay(cellElement, cardapioAtual[diaKey][refKey]); } } } }); } }); console.log("Atualização de quantidades concluída."); if (globalChange) { markGridChanged(); } }
         function saveLabelEdit(inputElement) { if (!inputElement.hasClass('editing')) return; const newValue = inputElement.val().trim(); const spanElement = inputElement.siblings('span.editable-label'); const cellElement = inputElement.closest('td'); const rowElement = inputElement.closest('tr'); const refKey = rowElement.data('refeicao-key'); spanElement.text(newValue); inputElement.removeClass('editing').hide(); spanElement.removeClass('editing'); if (refKey && refeicoesLayout[refKey]) { if (cellElement.hasClass('refeicao-label')) { refeicoesLayout[refKey].label = newValue; } else if (cellElement.hasClass('horario-cell')) { refeicoesLayout[refKey].horario = newValue; } markGridChanged(); } }
         function addNewRefeicaoRow() { const newKey = generateRefKey(); const newLabel = `NOVA REFEIÇÃO ${Object.keys(refeicoesLayout).length + 1}`; const newHorario = "HH:MM"; refeicoesLayout[newKey] = { label: newLabel, horario: newHorario }; diasKeys.forEach(diaKey => { if (!cardapioAtual[diaKey]) cardapioAtual[diaKey] = {}; cardapioAtual[diaKey][newKey] = []; if (typeof datasDias[diaKey] === 'undefined') datasDias[diaKey] = ''; if (typeof diasDesativados[diaKey] === 'undefined') diasDesativados[diaKey] = false; }); renderCardapioGrid(); const newRowElement = $tableBody.find(`tr[data-refeicao-key="${newKey}"]`); updateRemoveButtonsVisibility(); markGridChanged(); newRowElement.find('.refeicao-label span.editable-label').click(); }

        // --- Funções e Listeners dos Modais ---
        function openMainSelectionModal(cellElement) { if (!dadosBaseOk || cellElement.hasClass('dia-desativado')) return; const targetCell = cellElement; modalCurrentSelections.clear(); const diaKey = targetCell.data('dia'); const refeicaoKey = targetCell.closest('tr').data('refeicao-key'); const diaNome = diasNomesMap[diaKey] || diaKey; const refeicaoNome = targetCell.closest('tr').find('.refeicao-label .editable-label').text().trim() || refeicaoKey; mainSelectionModal.find('#modal-title').text(`Adicionar em: ${diaNome} - ${refeicaoNome}`); populateMainModalSearchList(''); mainSelectionModal.data('targetCell', targetCell); mainSelectionModal.css('display', 'flex').hide().fadeIn(200); mainSelectionModal.find('#modal-search').val('').focus(); }
        function populateMainModalSearchList(searchTerm) { const ulSearch = mainSelectionModal.find('.modal-search-list'); ulSearch.empty(); const term = sanitizeString(searchTerm); let count = 0; if (typeof alimentosParaModalListaJS === 'object' && alimentosParaModalListaJS !== null && Object.keys(alimentosParaModalListaJS).length > 0) { const sortedList = Object.values(alimentosParaModalListaJS).sort((a, b) => (a.nome || '').localeCompare(b.nome || '', 'pt-BR', { sensitivity: 'base' })); for (const itemData of sortedList) { const foodId = itemData.id; const nome = itemData.nome || 'Nome Inválido'; const nomeSanitized = sanitizeString(nome); const isPrep = itemData.isPreparacao || false; if (term === '' || nomeSanitized.includes(term)) { const isChecked = modalCurrentSelections.has(foodId); ulSearch.append(createSearchListItem(foodId, nome, isChecked, isPrep)); count++; } } } if (count === 0) ulSearch.append('<li class="no-results">- Nenhum item encontrado -</li>'); }
        function createSearchListItem(id, name, isChecked, isPreparacao = false) { const li = $('<li></li>'); const label = $('<label></label>').attr('for', `mchk_${id}`); const checkbox = $('<input type="checkbox" class="modal-add-item-chk">').val(id).attr('id', `mchk_${id}`).prop('checked', isChecked); const span = $('<span></span>').text(` ${name}`); if (isPreparacao) { span.prepend('<i class="fas fa-mortar-pestle" style="color: var(--warning-dark); margin-right: 5px;" title="Preparação"></i> '); } label.append(checkbox).append(span); li.append(label); return li; }
        mainSelectionModal.on('change', '.modal-add-item-chk', function() { const foodId = $(this).val(); if ($(this).is(':checked')) modalCurrentSelections.add(foodId); else modalCurrentSelections.delete(foodId); });
        mainSelectionModal.on('keyup', '#modal-search', function() { populateMainModalSearchList($(this).val()); });
        mainSelectionModal.on('click', '#modal-confirm', function() { if (!dadosBaseOk) return; const targetCell = mainSelectionModal.data('targetCell'); if (!targetCell || targetCell.length === 0 || targetCell.hasClass('dia-desativado')) { closeModal(mainSelectionModal); return; } const foodIdsToAdd = Array.from(modalCurrentSelections); if (foodIdsToAdd.length === 0) { closeModal(mainSelectionModal); return; } groupChoiceQueue = foodIdsToAdd.map(foodId => ({ foodId: foodId, targetCell: targetCell })); closeModal(mainSelectionModal); processNextGroupChoice(); });
        function processNextGroupChoice() { if (groupChoiceQueue.length === 0) { triggerCalculation(); return; } const currentChoice = groupChoiceQueue.shift(); const foodId = currentChoice.foodId; const targetCell = currentChoice.targetCell; if (!foodId || !targetCell || targetCell.length === 0 || targetCell.hasClass('dia-desativado')) { console.error("Erro: Dados inválidos na fila.", currentChoice); processNextGroupChoice(); return; } const foodName = alimentosIdNomeMap[foodId] || `ID ${foodId}`; const existingGroups = findExistingInstanceGroups(foodId); if (existingGroups.length > 0) { openGroupChoiceModal(foodId, foodName, targetCell, existingGroups); } else { addItemToCell(targetCell, foodId, 1, null); processNextGroupChoice(); } }
        function openGroupChoiceModal(foodId, foodName, targetCell, existingGroups) { groupChoiceModal.data('foodId', foodId); groupChoiceModal.data('targetCellRef', { refKey: targetCell.closest('tr').data('refeicao-key'), diaKey: targetCell.data('dia') }); groupChoiceModal.find('#group-choice-food-name').text(`Adicionar ${foodName}?`); const optionsList = groupChoiceModal.find('#group-choice-options'); optionsList.empty(); existingGroups.forEach((groupInfo, index) => { const li=$('<li></li>'); const rId=`group_choice_${groupInfo.group}`; const lbl=$(`<label for="${rId}"></label>`); const r=$('<input type="radio" name="group_choice" required>').attr('id',rId).val(groupInfo.group).data('qty',groupInfo.qty); if(index===0)r.prop('checked',true); lbl.append(r).append(`<span class="group-option-label">Adicionar ao Grupo ${groupInfo.group}</span>`).append(`<span class="group-option-qty">(${groupInfo.qty}g)</span>`); li.append(lbl); optionsList.append(li); }); const nextGroupNumber = getNextInstanceGroupNumber(foodId); const newLi=$('<li></li>'); const nId='group_choice_new'; const nLbl=$(`<label for="${nId}"></label>`); const nR=$('<input type="radio" name="group_choice" required>').attr('id',nId).val('new').data('next-group',nextGroupNumber); nLbl.append(nR).append(`<span class="group-option-label new-group-label">Criar Novo Grupo (${nextGroupNumber})</span>`).append(`<span class="group-option-qty">(Qtd Padrão)</span>`); newLi.append(nLbl); optionsList.append(newLi); groupChoiceModal.css('display', 'flex').hide().fadeIn(150); }
        $('#group-choice-confirm').on('click', function() { if (!dadosBaseOk) return; const selectedOption = groupChoiceModal.find('input[name="group_choice"]:checked'); if (!selectedOption.length) { alert("Selecione uma opção."); return; } const storedFoodId = groupChoiceModal.data('foodId'); const targetCellRef = groupChoiceModal.data('targetCellRef'); let targetCellFromStorage = null; if (targetCellRef?.refKey && targetCellRef?.diaKey) { targetCellFromStorage = $tableBody.find(`tr[data-refeicao-key="${targetCellRef.refKey}"] td.editable-cell[data-dia="${targetCellRef.diaKey}"]`); } const choiceValue = selectedOption.val(); let instanceGroupToAdd; let quantityToAdd = null; if (choiceValue === 'new') { instanceGroupToAdd = selectedOption.data('next-group'); quantityToAdd = null; } else { instanceGroupToAdd = parseInt(choiceValue, 10); quantityToAdd = selectedOption.data('qty'); } if (targetCellFromStorage?.length > 0 && !targetCellFromStorage.hasClass('dia-desativado') && storedFoodId && instanceGroupToAdd) { addItemToCell(targetCellFromStorage, storedFoodId, instanceGroupToAdd, quantityToAdd); } else { console.error("Erro ao confirmar escolha de grupo."); alert("Erro ao adicionar item."); } groupChoiceModal.removeData('foodId'); groupChoiceModal.removeData('targetCellRef'); closeModal(groupChoiceModal); processNextGroupChoice(); });
        $(document).on('click', '.item-edit-qty-btn', function(e){ e.stopPropagation(); if (!dadosBaseOk) return; currentEditingLi = $(this).closest('li'); if (!currentEditingLi.length || currentEditingLi.hasClass('placeholder')) return; currentTargetQtySpan = currentEditingLi.find('.item-qty-display'); currentEditingPlacementId = currentEditingLi.data('placement-id'); const foodIdForEdit = currentEditingLi.data('food-id').toString(); currentEditingInstanceGroup = currentEditingLi.data('instance-group'); if (!alimentosIdNomeMap[foodIdForEdit]) { console.error("Erro editar: foodId inválido", foodIdForEdit); return; } const foodName = alimentosIdNomeMap[foodIdForEdit]; const currentQtyText = currentTargetQtySpan.text().replace(/\D/g,''); const currentQty = parseInt(currentQtyText, 10) || 100; if (!currentEditingPlacementId || typeof currentEditingInstanceGroup === 'undefined') { alert("Erro interno."); return; } quantityEditModal.find('#quantity-modal-food-name').text(foodName); quantityEditModal.find('#quantity-edit-input').val(currentQty); quantityEditModal.find('#quantity-modal-group-number').text(currentEditingInstanceGroup); quantityEditModal.css('display', 'flex').hide().fadeIn(150); quantityEditModal.find('#quantity-edit-input').focus().select(); });
        $('#quantity-modal-confirm').on('click', function(){ if (!dadosBaseOk) return; const foodIdToUpdate = currentEditingLi ? currentEditingLi.data('food-id').toString() : null; if (!currentEditingPlacementId || !foodIdToUpdate || typeof currentEditingInstanceGroup === 'undefined') { console.error("Erro confirmar edição."); closeModal(quantityEditModal); return; } let newQuantity = parseInt($('#quantity-edit-input').val(), 10); if (isNaN(newQuantity) || newQuantity < 1) { newQuantity = 1; } updateQuantityForInstanceGroup(foodIdToUpdate, currentEditingInstanceGroup, newQuantity); closeModal(quantityEditModal); triggerCalculation(); });
        $('#quantity-edit-input').on('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); $('#quantity-modal-confirm').click(); } });
        $('#nova-preparacao-btn').on('click', function() { if (!dadosBaseOk) return; novaPreparacaoModal.find('#prep-nome').val(''); novaPreparacaoModal.find('#prep-porcao-padrao').val('150'); novaPreparacaoModal.find('#prep-ingredient-search').val(''); novaPreparacaoModal.find('#prep-search-results').empty().hide(); novaPreparacaoModal.find('#prep-ingredients-list').empty().append('<li class="placeholder">- Nenhum ingrediente -</li>'); novaPreparacaoModal.css('display', 'flex').hide().fadeIn(200); novaPreparacaoModal.find('#prep-nome').focus(); });
        $('#prep-ingredient-search').on('keyup', function() { const searchTerm = sanitizeString($(this).val()); const resultsUl = $('#prep-search-results'); resultsUl.empty(); if (searchTerm.length < 2) { resultsUl.hide(); return; } let count = 0; if (typeof alimentosCompletos === 'object' && alimentosCompletos !== null) { const sortedBaseFoods = Object.values(alimentosCompletos).filter(item => item && !item.isPreparacao).sort((a, b) => (a.nome || '').localeCompare(b.nome || '', 'pt-BR', { sensitivity: 'base' })); for (const food of sortedBaseFoods) { if (food?.id && food.nome && sanitizeString(food.nome).includes(searchTerm)) { resultsUl.append(`<li data-id="${food.id}" data-nome="${food.nome}">${food.nome}</li>`); count++; } } } if (count === 0) resultsUl.append('<li class="no-results">- Nenhum encontrado -</li>'); resultsUl.show(); });
        $(document).on('click', function(e) { if (!$(e.target).closest('#prep-ingredient-search, #prep-search-results').length) { $('#prep-search-results').hide(); } });
        $(document).on('click', '#prep-search-results li:not(.no-results)', function() { if (!dadosBaseOk) return; const foodId = $(this).data('id').toString(); const foodName = $(this).data('nome'); const listUl = $('#prep-ingredients-list'); listUl.find('.placeholder').remove(); const defaultQty = (alimentosCompletos?.[foodId]?.porcao_padrao ?? 100); const li = $(`<li data-id="${foodId}"></li>`).append(`<span class="ingredient-name">${foodName}</span>`).append(`<input type="number" class="ingredient-qty-input" value="${defaultQty}" min="1" step="1">`).append(`<span>g</span>`).append(`<button type="button" class="ingredient-remove-btn" title="Remover ${foodName}"><i class="fas fa-times"></i></button>`); listUl.append(li); $('#prep-ingredient-search').val(''); $('#prep-search-results').empty().hide(); });
        $(document).on('click', '.ingredient-remove-btn', function() { const li = $(this).closest('li'); const listUl = li.parent(); li.remove(); if (listUl.children().length === 0) { listUl.append('<li class="placeholder">- Nenhum ingrediente -</li>'); } });
        $('#prep-save').on('click', function() { if (!dadosBaseOk) return; const nomePrep = $('#prep-nome').val().trim(); const porcaoPadrao = parseInt($('#prep-porcao-padrao').val(), 10) || 150; const ingredientes = []; $('#prep-ingredients-list li:not(.placeholder)').each(function() { const id = $(this).data('id').toString(); const qty = parseInt($(this).find('.ingredient-qty-input').val(), 10); if (id && !isNaN(qty) && qty > 0) { ingredientes.push({ foodId: id, qty: qty }); } }); if (!nomePrep) { alert("Dê um nome para a preparação."); $('#prep-nome').focus(); return; } if (ingredientes.length === 0) { alert("Adicione ingredientes."); return; } const newPrepId = generatePreparacaoId(); const newPrepData = { id: newPrepId, nome: nomePrep, porcao_padrao: porcaoPadrao, isPreparacao: true, ingredientes: ingredientes }; if (typeof alimentosCompletos !== 'object') alimentosCompletos = {}; if (typeof alimentosIdNomeMap !== 'object') alimentosIdNomeMap = {}; if (typeof alimentosParaModalListaJS !== 'object') alimentosParaModalListaJS = {}; alimentosCompletos[newPrepId] = newPrepData; alimentosIdNomeMap[newPrepId] = nomePrep; alimentosParaModalListaJS[newPrepId] = { ...newPrepData }; console.log("Nova preparação criada (JS):", newPrepData); alert(`Preparação "${nomePrep}" criada! Disponível para seleção.`); closeModal(novaPreparacaoModal); /* TODO: Salvar preparação no BD? */ });
        function closeModal(modalElement) { modalElement.fadeOut(150, function() { $(this).css('display', 'none'); if (modalElement.is(mainSelectionModal)) { modalCurrentSelections.clear(); $(this).find('#modal-search').val(''); $(this).find('.modal-search-list').empty(); $(this).removeData('targetCell'); } if (modalElement.is(quantityEditModal)) { currentEditingLi = null; currentTargetQtySpan = null; currentEditingPlacementId = null; currentEditingInstanceGroup = null; $(this).find('#quantity-edit-input').val(100); } if (modalElement.is(groupChoiceModal)) { $(this).find('#group-choice-options').empty(); $(this).removeData('foodId'); $(this).removeData('targetCellRef'); } if (modalElement.is(novaPreparacaoModal)) { $('#prep-search-results').hide(); } }); }
        $(document).on('click', '.modal-close-btn', function(){ closeModal($(this).closest('.modal-overlay')); });
        $(document).on('click', '#modal-cancel, #quantity-modal-cancel, #group-choice-cancel, #prep-cancel', function(){ closeModal($(this).closest('.modal-overlay')); });
        $('.modal-overlay').on('click', function(e) { if ($(e.target).is($(this))) { closeModal($(this)); } });
        $(document).on('keydown', function(e) { if (e.key === "Escape") { $('.modal-overlay:visible').each(function(){ closeModal($(this)); }); } });

        // --- Leitura para API/Export ---
        function lerCardapioParaApi() { const currentState = getGridStateForSaving(); let hasItems = false; const diasAtivosSet = new Set(); diasKeys.forEach(diaKey => { if (!currentState.dias_desativados[diaKey] && currentState.dias[diaKey]) { Object.values(currentState.dias[diaKey]).forEach(refItens => { if (Array.isArray(refItens) && refItens.length > 0) { hasItems = true; diasAtivosSet.add(diaKey); } }); } }); const faixaKey = currentState.faixa_etaria_selecionada; const diasAtivos = Array.from(diasAtivosSet); if (!faixaKey) return { error: 'faixa_etaria' }; if (!hasItems || diasAtivos.length === 0) return { error: 'sem_itens_ativos' }; const cardapioApi = {}; diasAtivos.forEach(diaKey => { cardapioApi[diaKey] = {}; if(currentState.dias[diaKey]) { Object.entries(currentState.dias[diaKey]).forEach(([refKey, itens]) => { cardapioApi[diaKey][refKey] = itens.map(item => ({ id: item.foodId, qty: item.qty, is_prep: item.foodId.toString().startsWith('prep_') || (alimentosCompletos?.[item.foodId]?.isPreparacao) ? true : false })); }); } }); const finalData = { cardapio: cardapioApi, dias_ativos: diasAtivos, faixa_etaria: faixaKey, refeicoes_info: currentState.refeicoes, meta: { faixa_etaria_texto: $("#faixa-etaria-select option:selected").text().trim(), datas: currentState.datas_dias, preparacoes_defs: {}, dias_keys: diasKeys }}; if (typeof alimentosCompletos === 'object') { diasAtivos.forEach(dia => { if (cardapioApi[dia]) { Object.values(cardapioApi[dia]).forEach(refItens => { refItens.forEach(item => { if (item.is_prep && alimentosCompletos[item.id] && !finalData.meta.preparacoes_defs[item.id]) { finalData.meta.preparacoes_defs[item.id] = { nome: alimentosCompletos[item.id].nome, ingredientes: alimentosCompletos[item.id].ingredientes }; } }); }); } }); } return finalData; }
        function lerResultadosParaExport() { const r = { analise_diaria: {}, media_semanal: {} }; $('#resultados-diarios-table tbody tr').each(function() { const diaKey = $(this).attr('id').replace('daily-', ''); r.analise_diaria[diaKey] = {}; $(this).find('td[data-nutrient]').each(function() { r.analise_diaria[diaKey][$(this).data('nutrient')] = $(this).text(); }); }); $('#weekly-avg td[data-nutrient]').each(function() { r.media_semanal[$(this).data('nutrient')] = $(this).text(); }); return r; }

        // --- Cálculo e Display de Resultados ---
        function triggerCalculation() { if (!dadosBaseOk) return; clearTimeout(calculationTimeout); calculationTimeout = setTimeout(() => { initializeInstanceGroupCounters(); const dataToSend = lerCardapioParaApi(); if (dataToSend && !dataToSend.error) { calcularNutrientes(dataToSend); } else { limparResultados(); if (dataToSend?.error === 'faixa_etaria') { showStatus('warning', 'Selecione faixa etária.', 'fa-users'); } else if (dataToSend?.error === 'sem_itens_ativos') { showStatus('info', 'Cardápio vazio ou apenas em dias inativos.', 'fa-info-circle'); } else { showStatus('info', 'Nada para calcular.', 'fa-info-circle'); } } }, 600); }
        function calcularNutrientes(apiData) { if (requestActive || !dadosBaseOk) return; requestActive = true; showStatus('loading', 'Calculando...', 'fa-spinner fa-spin'); $('#calcular-nutrientes-btn').prop('disabled', true); $.ajax({ url: 'api_calculator.php', method: 'POST', data: { payload: JSON.stringify(apiData) }, dataType: 'json', success: function(response) { if (response && !response.error && response.daily_totals && response.weekly_average) { updateResultsDisplay(response.daily_totals, response.weekly_average); if (response.debug_errors && response.debug_errors.length > 0) { showStatus('warning', 'Cálculo com avisos. Ver console.', 'fa-exclamation-triangle'); console.warn("Avisos API:", response.debug_errors); } else { showStatus('success', 'Cálculo Realizado!', 'fa-check-circle'); } } else { const errMsg = response?.error || 'Resposta inválida.'; limparResultados(); showStatus('error', `Erro cálculo: ${errMsg}`, 'fa-times-circle'); console.error("Erro API Calc:", response); } }, error: function(jqXHR, textStatus, errorThrown) { console.error("Erro AJAX Calc:", textStatus, errorThrown, jqXHR.responseText); limparResultados(); let detail = `Erro ${jqXHR.status}: ${errorThrown||textStatus}`; try { const err = JSON.parse(jqXHR.responseText); if(err?.error) detail += ` - ${err.error}`; } catch(e){} showStatus('error', `Falha comunicação Calc: ${detail}`, 'fa-server'); }, complete: function() { requestActive = false; $('#calcular-nutrientes-btn').prop('disabled', false); setTimeout(() => { if (!requestActive && statusMessage.hasClass('loading')) showStatus('info', 'Pronto.', 'fa-info-circle'); }, 800); } }); }
        function updateResultsDisplay(dailyTotals, weeklyAverage) {
            const dias = diasKeys;
            // Lista original de nutrientes retornados pela API (vamos calcular os Kcal específicos no JS)
            const nutrientesApi = ['kcal', 'ptn', 'lpd', 'cho', 'ca', 'fe', 'vita', 'vitc', 'na'];
            // Fatores Atwater
            const ATWATER = { ptn: 4, lpd: 9, cho: 4 };

            // Formatadores atualizados
            const formatters = {
                kcal: (v) => v !== null && !isNaN(v) ? Math.round(v).toLocaleString('pt-BR') : '0',
                ptn:  (v) => v !== null && !isNaN(v) ? v.toFixed(1).replace('.', ',') : '0,0',
                lpd:  (v) => v !== null && !isNaN(v) ? v.toFixed(1).replace('.', ',') : '0,0',
                cho:  (v) => v !== null && !isNaN(v) ? v.toFixed(1).replace('.', ',') : '0,0',
                ca:   (v) => v !== null && !isNaN(v) ? Math.round(v).toLocaleString('pt-BR') : '0',
                fe:   (v) => v !== null && !isNaN(v) ? v.toFixed(1).replace('.', ',') : '0,0',
                vita: (v) => v !== null && !isNaN(v) ? Math.round(v).toLocaleString('pt-BR') : '0',
                vitc: (v) => v !== null && !isNaN(v) ? v.toFixed(1).replace('.', ',') : '0,0',
                na:   (v) => v !== null && !isNaN(v) ? Math.round(v).toLocaleString('pt-BR') : '0',
                // Novos formatadores para Kcal específicos (2 casas decimais como na imagem)
                ptn_kcal: (v) => v !== null && !isNaN(v) ? v.toFixed(2).replace('.', ',') : '0,00',
                lpd_kcal: (v) => v !== null && !isNaN(v) ? v.toFixed(2).replace('.', ',') : '0,00',
                cho_kcal: (v) => v !== null && !isNaN(v) ? v.toFixed(2).replace('.', ',') : '0,00',
                // Formatadores VET mantidos
                ptn_vet: (v) => v !== null && !isNaN(v) && v > 0 ? Math.round(v) + '%' : '-',
                lpd_vet: (v) => v !== null && !isNaN(v) && v > 0 ? Math.round(v) + '%' : '-',
                cho_vet: (v) => v !== null && !isNaN(v) && v > 0 ? Math.round(v) + '%' : '-',
            };

            // Função para calcular %VET (mantida)
            function calculateVET(macroGrams, totalKcal, atwaterFactor) {
                if (totalKcal > 0 && macroGrams !== null && !isNaN(macroGrams)) {
                    return ((macroGrams * atwaterFactor) / totalKcal) * 100;
                }
                return null;
            }

            // Processa totais diários
            dias.forEach(dia => {
                const row = $(`#daily-${dia}`);
                const dD = dailyTotals[dia] || {}; // Dados diários
                const totalKcalDia = dD.kcal;

                // Popula nutrientes básicos retornados pela API
                nutrientesApi.forEach(nut => {
                     row.find(`td[data-nutrient="${nut}"]`).text(formatters[nut](dD[nut]));
                });

                // Calcula e popula Kcal específicos e %VET
                const ptnKcalD = (dD.ptn !== null && !isNaN(dD.ptn)) ? dD.ptn * ATWATER.ptn : null;
                const lpdKcalD = (dD.lpd !== null && !isNaN(dD.lpd)) ? dD.lpd * ATWATER.lpd : null;
                const choKcalD = (dD.cho !== null && !isNaN(dD.cho)) ? dD.cho * ATWATER.cho : null;

                row.find('td[data-nutrient="ptn_kcal"]').text(formatters.ptn_kcal(ptnKcalD));
                row.find('td[data-nutrient="lpd_kcal"]').text(formatters.lpd_kcal(lpdKcalD));
                row.find('td[data-nutrient="cho_kcal"]').text(formatters.cho_kcal(choKcalD));

                row.find('td[data-nutrient="ptn_vet"]').text(formatters.ptn_vet(calculateVET(dD.ptn, totalKcalDia, ATWATER.ptn)));
                row.find('td[data-nutrient="lpd_vet"]').text(formatters.lpd_vet(calculateVET(dD.lpd, totalKcalDia, ATWATER.lpd)));
                row.find('td[data-nutrient="cho_vet"]').text(formatters.cho_vet(calculateVET(dD.cho, totalKcalDia, ATWATER.cho)));
            });

            // Processa média semanal
            const avgRow = $(`#weekly-avg`);
            const wA = weeklyAverage || {}; // Dados da média
            const totalKcalAvg = wA.kcal;

             // Popula nutrientes básicos da média
            nutrientesApi.forEach(nut => {
                 avgRow.find(`td[data-nutrient="${nut}"]`).text(formatters[nut](wA[nut]));
            });

             // Calcula e popula Kcal específicos e %VET da média
            const ptnKcalA = (wA.ptn !== null && !isNaN(wA.ptn)) ? wA.ptn * ATWATER.ptn : null;
            const lpdKcalA = (wA.lpd !== null && !isNaN(wA.lpd)) ? wA.lpd * ATWATER.lpd : null;
            const choKcalA = (wA.cho !== null && !isNaN(wA.cho)) ? wA.cho * ATWATER.cho : null;

            avgRow.find('td[data-nutrient="ptn_kcal"]').text(formatters.ptn_kcal(ptnKcalA));
            avgRow.find('td[data-nutrient="lpd_kcal"]').text(formatters.lpd_kcal(lpdKcalA));
            avgRow.find('td[data-nutrient="cho_kcal"]').text(formatters.cho_kcal(choKcalA));

            avgRow.find('td[data-nutrient="ptn_vet"]').text(formatters.ptn_vet(calculateVET(wA.ptn, totalKcalAvg, ATWATER.ptn)));
            avgRow.find('td[data-nutrient="lpd_vet"]').text(formatters.lpd_vet(calculateVET(wA.lpd, totalKcalAvg, ATWATER.lpd)));
            avgRow.find('td[data-nutrient="cho_vet"]').text(formatters.cho_vet(calculateVET(wA.cho, totalKcalAvg, ATWATER.cho)));
        }

        function limparResultados() {
            $('#resultados-diarios-table tbody tr, #resultados-diarios-table tfoot tr').each(function(){
                $(this).find('td[data-nutrient]').each(function(){
                    const nut = $(this).data('nutrient');
                    let dv = '0'; // Default para Kcal total, Ca, Vita, Na
                    // Gramas, Ferro, VitC e Kcal específicos
                    if (['ptn', 'lpd', 'cho', 'fe', 'vitc', 'ptn_kcal', 'lpd_kcal', 'cho_kcal'].includes(nut)) {
                         dv = '0,00'; // Usar duas casas para Kcal específicos também
                    } else if (nut.includes('_vet')) { // % VET
                         dv = '-';
                    } else if (['ptn','lpd','cho','fe','vitc'].includes(nut)) {
                         // Garante que gramas fiquem com uma casa decimal
                         dv = '0,0';
                    }
                    $(this).text(dv);
                });
            });
        }
        function limparResultados() { $('#resultados-diarios-table tbody tr, #resultados-diarios-table tfoot tr').each(function(){ $(this).find('td[data-nutrient]').each(function(){ const nut = $(this).data('nutrient'); let dv = '0'; if (['ptn', 'lpd', 'cho', 'fe', 'vitc'].includes(nut)) { dv = '0,0'; } else if (nut?.includes('_vet')) { dv = '-'; } $(this).text(dv); }); }); }

        // --- Outros Listeners ---
         $('#calcular-nutrientes-btn').on('click', function(e) { e.preventDefault(); if (!$(this).prop('disabled') && dadosBaseOk) triggerCalculation(); });
        faixaEtariaSelect.on('change', function() {
            if (!dadosBaseOk) return;
            const newFaixaKey = $(this).val();
            faixaEtariaSelecionada = newFaixaKey;

            updateReferenciaTable(newFaixaKey); // <<< ADICIONE ESTA LINHA

            if (newFaixaKey) {
                updateGridQuantitiesForAgeGroup(newFaixaKey);
                clearSelectionAndClipboard();
                clearPasteTarget();
                markGridChanged();
                triggerCalculation(); // triggerCalculation já lida com status
            } else {
                limparResultados();
                showStatus('warning', 'Selecione uma faixa etária.', 'fa-users');
            }
        });
         $('#limpar-cardapio-btn').on('click', function(e) { e.preventDefault(); if (!dadosBaseOk || $(this).prop('disabled') || !confirm("Limpar TODO o cardápio deste projeto?")) return; diasKeys.forEach(diaKey => { if (cardapioAtual[diaKey]) { Object.keys(cardapioAtual[diaKey]).forEach(refKey => { cardapioAtual[diaKey][refKey] = []; }); } }); datasDias = Object.fromEntries(diasKeys.map(k => [k, ''])); diasDesativados = Object.fromEntries(diasKeys.map(k => [k, false])); renderCardapioGrid(); initializeInstanceGroupCounters(); limparResultados(); clearSelectionAndClipboard(); clearPasteTarget(); markGridChanged(); showStatus('info', 'Cardápio limpo. Salve para confirmar.', 'fa-eraser'); });
         $(document).on('click', '.add-item-cell-btn', function(e) { e.stopPropagation(); if (!dadosBaseOk) return; openMainSelectionModal($(this).closest('td.editable-cell')); });
         $('#exportar-xlsx-btn').on('click', function(e){ e.preventDefault(); if (!dadosBaseOk || $(this).prop('disabled')) return; const dataCardapio = lerCardapioParaApi(); if (dataCardapio && !dataCardapio.error) { const dataResultados = lerResultadosParaExport(); const dataToExport = { ...dataCardapio, resultados_display: dataResultados, projeto_nome: $('#export-project-name-input').val() }; $('#export-data-input').val(JSON.stringify(dataToExport)); $('#export-xlsx-form').submit(); } else { alert("Não há dados válidos para exportar ou falta selecionar a faixa etária."); } });
         $(document).on('change', '.dia-data-input', function() { const diaKey = $(this).data('dia'); const novaData = $(this).val().trim(); if (datasDias[diaKey] !== novaData) { datasDias[diaKey] = novaData; markGridChanged(); } });
         $(document).on('click', '.toggle-feriado-btn', function() { const btn = $(this); const diaKey = btn.data('dia'); const th = $tableHead.find(`th[data-dia-col="${diaKey}"]`); const cells = $tableBody.find(`td.editable-cell[data-dia="${diaKey}"]`); const diaNome = diasNomesMap[diaKey] || diaKey.toUpperCase(); const estavaDesativado = th.hasClass('dia-desativado'); if (estavaDesativado) { diasDesativados[diaKey] = false; btn.removeClass('active').attr('title', `Marcar ${diaNome} como Feriado`); btn.find('i').removeClass('fa-toggle-on').addClass('fa-toggle-off'); th.removeClass('dia-desativado'); cells.removeClass('dia-desativado'); } else { if (confirm(`Marcar ${diaNome} como feriado/inativo?`)) { diasDesativados[diaKey] = true; btn.addClass('active').attr('title', `Marcar ${diaNome} como Ativo`); btn.find('i').removeClass('fa-toggle-off').addClass('fa-toggle-on'); th.addClass('dia-desativado'); cells.addClass('dia-desativado'); cells.find('.item-selecionado').removeClass('item-selecionado'); if (selectedItemLi?.closest('td').data('dia') === diaKey) clearSelectionAndClipboard(); if (targetCellForPaste?.data('dia') === diaKey) clearPasteTarget(); } else return; } markGridChanged(); triggerCalculation(); });
         // Cut/Copy/Paste/Delete listeners
          $(document).on('click', '.selected-items-list li:not(.placeholder)', function(e) {
            if (!dadosBaseOk || $(e.target).closest('.item-actions').length > 0 || $(this).closest('td.editable-cell').hasClass('dia-desativado')) {
                return; // Ignora dias desativados ou cliques nos botões de ação do item
            }

            const clickedLi = $(this);
            lastClickedLi = clickedLi;

            // Lógica de seleção (Ctrl ou clique simples)
            if (e.ctrlKey) {
                clickedLi.toggleClass('item-selecionado');
                selectedItemsCollection = $('.selected-items-list li.item-selecionado'); // Recalcula a coleção
            } else {
                // Se o item clicado JÁ era o ÚNICO selecionado, deseleciona ele
                if (selectedItemsCollection.length === 1 && selectedItemsCollection.is(clickedLi)) {
                     clickedLi.removeClass('item-selecionado');
                     selectedItemsCollection = $(); // Esvazia coleção
                } else {
                    // Seleciona apenas o clicado
                    selectedItemsCollection.removeClass('item-selecionado');
                    clickedLi.addClass('item-selecionado');
                    selectedItemsCollection = clickedLi;
                }
            }

            // Ação CRUCIAL: Clicar em qualquer item limpa o estado de "alvo para colar"
            clearPasteTarget();
            updateManipulationButtons(); // Atualiza os botões Copy/Cut/Delete/Paste
        });
    $(document).on('click', 'td.editable-cell', function(e) {
            const cell = $(this);
            const isCellClick = $(e.target).is(cell); // Clique EXATO no fundo

            if (cell.hasClass('dia-desativado')) return;

            const clickedOnItemOrButton = $(e.target).closest('li:not(.placeholder), .add-item-cell-btn').length > 0;

            // Ação: Definir Alvo de Colar
            // Condições: tem algo no clipboard E o clique foi no fundo da célula
            if (internalClipboard.itemsData.length > 0 && isCellClick) {
                if (!targetCellForPaste || !targetCellForPaste.is(cell)) {
                     $('.target-cell-for-paste').removeClass('target-cell-for-paste');
                     cell.addClass('target-cell-for-paste');
                     targetCellForPaste = cell;
                     console.log('Alvo de colar DEFINIDO (single click):', targetCellForPaste.data('dia'), targetCellForPaste.closest('tr').data('refeicao-key'));
                     updateManipulationButtons();
                }
            }
            // Se clicou no fundo mas NÃO tem clipboard, ou clicou em espaço vazio (não item/botão)
            else if (!clickedOnItemOrButton) {
                 clearPasteTarget(); // Limpa qualquer alvo anterior
                 updateManipulationButtons();
            }
            // Clicar em item/botão é tratado por outros listeners
        });

         $(document).on('dblclick', 'td.editable-cell', function(e) {
            const cell = $(this);
            if (cell.hasClass('dia-desativado')) return;

            // Só seleciona tudo se o duplo clique foi no fundo da célula
            if ($(e.target).is(cell)) {
                console.log('Duplo clique detectado na célula:', cell.data('dia'), cell.closest('tr').data('refeicao-key'));

                // --- MUDANÇA AQUI ---
                // Limpa a SELEÇÃO ATUAL e o ALVO DE COLAR, mas NÃO o clipboard
                if (selectedItemsCollection.length > 0) {
                    selectedItemsCollection.removeClass('item-selecionado');
                    selectedItemsCollection = $(); // Limpa a coleção de seleção
                }
                clearPasteTarget(); // Limpa o alvo de colar (borda verde)
                // NÃO CHAMA MAIS clearSelectionAndClipboard() que limparia tudo
                // --------------------

                const itemsInCell = cell.find('ul.selected-items-list li:not(.placeholder)');
                if (itemsInCell.length > 0) {
                    itemsInCell.addClass('item-selecionado');
                    selectedItemsCollection = itemsInCell; // Define a nova seleção
                    console.log(`Selecionado(s) ${itemsInCell.length} item(ns) na célula via dblclick.`);
                } else {
                    console.log("Duplo clique em célula vazia, nada selecionado.");
                }
                updateManipulationButtons(); // Atualiza botões (habilita copy/cut/delete se algo foi selecionado)
            }
        });

   // Adicionar/Garantir que clearPasteTarget() está aqui
        function clearSelectionAndClipboard() {
            if (selectedItemsCollection.length > 0) {
                selectedItemsCollection.removeClass('item-selecionado');
                selectedItemsCollection = $(); // Limpa a coleção
            }
            internalClipboard = { type: null, itemsData: [] };
            clearPasteTarget(); // <--- GARANTIR QUE ESTA LINHA ESTÁ AQUI
            updateManipulationButtons(); // Atualiza estado dos botões
            console.log("Seleção e clipboard limpos.");
        }
           function clearPasteTarget() {
            if (targetCellForPaste) {
                targetCellForPaste.removeClass('target-cell-for-paste');
                targetCellForPaste = null;
            }
            $('#item-paste-btn').prop('disabled', true);
        }
        
        
          function updateManipulationButtons() {
            // Verifica se há itens selecionados na coleção
            const hasSelection = selectedItemsCollection.length > 0;
            // Verifica se há itens no clipboard E se uma célula alvo foi definida
            const canPaste = internalClipboard.itemsData.length > 0 && targetCellForPaste !== null && !targetCellForPaste.hasClass('dia-desativado');

            // Habilita/desabilita botões baseado nas condições
            $('#item-copy-btn').prop('disabled', !hasSelection);
            $('#item-cut-btn').prop('disabled', !hasSelection);
            $('#item-delete-btn').prop('disabled', !hasSelection);
            $('#item-paste-btn').prop('disabled', !canPaste);
        }

  $('#item-delete-btn').on('click', function() {
            console.log("[Delete Button] Clicado. Itens selecionados:", selectedItemsCollection.length);
            if (!dadosBaseOk || selectedItemsCollection.length === 0 || $(this).prop('disabled')) {
                console.log("[Delete Button] Ação bloqueada.");
                return;
            }

            const count = selectedItemsCollection.length;
            const firstItemName = selectedItemsCollection.first().find('.item-name').contents().filter(function(){ return this.nodeType === 3; }).text().trim();
            const confirmMsg = count === 1 ? `Remover "${firstItemName}"?` : `Remover ${count} itens selecionados?`;

            if (confirm(confirmMsg)) {
                console.log("[Delete Button] Confirmação OK.");
                let itemsEffectivelyRemovedCount = 0;
                const affectedCells = new Map(); // Para rastrear células que precisam de update visual

                // 1. CRIA UMA CÓPIA ESTÁTICA dos elementos a serem removidos
                const itemsToRemove = selectedItemsCollection.toArray().map(el => $(el));
                console.log("[Delete Button] Iterando sobre", itemsToRemove.length, "itens para remover.");

                // 2. ITERA sobre a cópia estática e remove dos DADOS
                itemsToRemove.forEach(($liElement, index) => {
                    const pId = $liElement.data('placement-id');
                    const cell = $liElement.closest('td.editable-cell');
                    const diaKey = cell.data('dia');
                    const refKey = cell.closest('tr').data('refeicao-key');
                    const cellKey = `${diaKey}-${refKey}`; // Chave única para a célula

                    // console.log(`[Delete Button] Tentando remover item ${index + 1}/${itemsToRemove.length}: pId=${pId} em ${cellKey}`);

                    // Chama a função refatorada que SÓ mexe nos dados e retorna true/false
                    if (removeItemFromCell($liElement)) {
                        itemsEffectivelyRemovedCount++;
                        // Marca a célula como afetada para atualizar a UI depois
                        if (!affectedCells.has(cellKey)) {
                            affectedCells.set(cellKey, cell); // Armazena o elemento jQuery da célula
                        }
                         console.log(`[Delete Button] Item ${pId} removido dos DADOS.`);
                    } else {
                         console.warn(`[Delete Button] Falha ao remover pId=${pId} dos DADOS.`);
                    }
                });

                console.log(`[Delete Button] Total de itens efetivamente removidos dos dados: ${itemsEffectivelyRemovedCount}`);

                // 3. ATUALIZA A INTERFACE *APÓS* o loop de remoção dos dados
                if (itemsEffectivelyRemovedCount > 0) {
                     console.log("[Delete Button] Atualizando UI das células afetadas:", affectedCells.size);
                     affectedCells.forEach((cellElement, key) => {
                         const diaKey = key.split('-')[0];
                         const refKey = key.split('-')[1];
                         // Busca os dados ATUALIZADOS para essa célula
                         const currentItemsInData = cardapioAtual[diaKey]?.[refKey] || [];
                         console.log(`[Delete Button] Atualizando display da célula ${key}`);
                         updateCellDisplay(cellElement, currentItemsInData); // Atualiza a lista na célula
                     });

                    // Atualiza contadores e cálculos globais UMA VEZ
                    initializeInstanceGroupCounters();
                    triggerCalculation();
                    // markGridChanged já foi chamado dentro de removeItemFromCell
                }

                // 4. LIMPA a seleção global e ATUALIZA os botões
                selectedItemsCollection = $(); // Esvazia a coleção global
                // Não precisamos remover a classe '.item-selecionado' explicitamente aqui,
                // pois o updateCellDisplay já recria os LIs da célula sem a classe.
                updateManipulationButtons(); // Atualiza o estado dos botões
                console.log("[Delete Button] Processo de deleção finalizado.");

            } else {
                 console.log("[Delete Button] Deleção cancelada pelo usuário.");
            }
        });
         $tableBody.on('click', '.item-remove-btn', function(e) {
             e.stopPropagation(); // Impede que o clique selecione o LI
             if (!dadosBaseOk) return;
             const $liToRemove = $(this).closest('li');
             if (!$liToRemove.length || $liToRemove.hasClass('placeholder')) {
                 console.warn("Botão X: Não encontrou LI para remover.");
                 return;
             }

             // REMOVA ou COMENTE a confirmação aqui se você quer que o botão Delete principal faça isso
             // const foodName = $liToRemove.find('.item-name').contents().filter(function() { return this.nodeType === 3; }).text().trim();
             // const groupNum = $liToRemove.data('instance-group');
             // if (confirm(`Remover "${foodName}" (Grupo ${groupNum})?`)) {

                 // Chama a função principal de remoção
                 if (removeItemFromCell($liToRemove)) {
                     console.log(`Item ${$liToRemove.data('placement-id')} removido via botão X.`);
                     initializeInstanceGroupCounters(); // Necessário se removeu a última instância de um grupo
                     triggerCalculation();          // Recalcula totais
                     // markGridChanged já é chamado dentro de removeItemFromCell
                 } else {
                     console.error("Botão X: Falha ao chamar removeItemFromCell ou item não removido.");
                     // Poderia adicionar um alerta aqui se quisesse feedback visual imediato da falha
                     // alert("Ocorreu um erro ao tentar remover o item.");
                 }
             // } // Fim do if(confirm(...)) comentado/removido
         });

  $('#item-copy-btn').on('click', function() {
            if (!dadosBaseOk || selectedItemsCollection.length === 0 || $(this).prop('disabled')) return;

            clearPasteTarget(); // Limpa alvo anterior ANTES de copiar

            internalClipboard = { type: 'copy', itemsData: [] }; // Reseta o clipboard
            let copiedNames = [];

            // Efeito visual e coleta de dados
            selectedItemsCollection.each(function() {
                const li = $(this);
                const itemData = {
                    foodId: li.data('food-id').toString(),
                    qty: parseInt(li.find('.item-qty-display').text().replace(/\D/g,''), 10) || 100,
                    instanceGroup: li.data('instance-group'),
                    placementId: li.data('placement-id')
                };
                internalClipboard.itemsData.push(itemData);
                copiedNames.push(alimentosIdNomeMap[itemData.foodId] || `ID ${itemData.foodId}`);

                // --- Adiciona Feedback Visual ---
                li.css('transition', 'none') // Desliga transição para flash imediato
                  .addClass('item-selecionado') // Garante que está selecionado
                  .css('background-color', 'var(--info-light)'); // Cor de "copiado"
                setTimeout(() => {
                    // Remove a cor de cópia e restaura a cor de seleção normal
                    li.css('transition', '') // Liga transição de volta
                      .css('background-color', ''); // Volta ao normal (CSS cuidará da cor .item-selecionado)
                }, 350); // Duração do flash visual
                // -------------------------------
            });

            const statusMsg = internalClipboard.itemsData.length === 1
                ? `"${copiedNames[0]}" copiado.`
                : `${internalClipboard.itemsData.length} itens copiados.`;
            showStatus('info', statusMsg, 'fa-copy'); // Mensagem de status
            updateManipulationButtons(); // Atualiza botões (habilita colar)
            console.log("Clipboard (Copy):", internalClipboard);
        });

        // ATUALIZAR ESTE LISTENER (Botão Recortar)
       $('#item-cut-btn').on('click', function() {
            if (!dadosBaseOk || selectedItemsCollection.length === 0 || $(this).prop('disabled')) return;

            internalClipboard = { type: 'cut', itemsData: [] }; // Reseta o clipboard
            let cutNames = [];
            let itemsSuccessfullyRemoved = 0;

            // 1. Coleta os dados dos itens a serem recortados
             selectedItemsCollection.each(function() {
                const li = $(this);
                const itemData = {
                    foodId: li.data('food-id').toString(),
                    qty: parseInt(li.find('.item-qty-display').text().replace(/\D/g,''), 10) || 100,
                    instanceGroup: li.data('instance-group'),
                    placementId: li.data('placement-id')
                };
                internalClipboard.itemsData.push(itemData);
                cutNames.push(alimentosIdNomeMap[itemData.foodId] || `ID ${itemData.foodId}`);
            });

            // 2. Remove os itens da grade *após* coletar os dados
            const itemsToRemove = selectedItemsCollection.toArray(); // Cria cópia para iterar
            itemsToRemove.forEach(liElement => {
                 if (removeItemFromCell($(liElement))) {
                     itemsSuccessfullyRemoved++;
                 }
            });

            if (itemsSuccessfullyRemoved > 0) {
                 const statusMsg = itemsSuccessfullyRemoved === 1
                    ? `"${cutNames[0]}" recortado.`
                    : `${itemsSuccessfullyRemoved} itens recortados.`;
                 showStatus('info', statusMsg, 'fa-cut');
                 initializeInstanceGroupCounters();
                 triggerCalculation();
                 // markGridChanged já é chamado dentro de removeItemFromCell
            } else {
                // Se nenhum item foi removido (erro?), limpa o clipboard
                internalClipboard = { type: null, itemsData: [] };
                showStatus('error', 'Erro ao recortar itens.', 'fa-times-circle');
            }

            // Limpa a seleção visual e a coleção JS
            selectedItemsCollection = $();
            updateManipulationButtons(); // Atualiza botões (desabilita cut/copy/delete, habilita paste se alvo)
            console.log("Clipboard (Cut):", internalClipboard);
        });

  $('#item-paste-btn').on('click', function() {
            if (!dadosBaseOk || internalClipboard.itemsData.length === 0 || !targetCellForPaste || targetCellForPaste.hasClass('dia-desativado') || $(this).prop('disabled')) {
                 console.warn("Colar bloqueado (botão):", {clipboard: internalClipboard.itemsData.length, target: targetCellForPaste?.length, disabled: targetCellForPaste?.hasClass('dia-desativado'), btnDisabled: $(this).prop('disabled')});
                return;
            }

            const targetCell = targetCellForPaste;
            const targetDiaKey = targetCell.data('dia');
            const targetRefKey = targetCell.closest('tr').data('refeicao-key');
            let itemsPastedCount = 0;
            let namesPasted = [];

            console.log(`[Paste Button] Colando ${internalClipboard.itemsData.length} itens em ${targetDiaKey}-${targetRefKey}`);

            internalClipboard.itemsData.forEach(itemToPaste => {
                const foodId = itemToPaste.foodId;
                const originalInstanceGroup = itemToPaste.instanceGroup;
                const originalQty = itemToPaste.qty;

                // Verifica se o foodId JÁ EXISTE na célula de destino
                const existsInTarget = checkIfFoodIdExistsInCell(foodId, targetDiaKey, targetRefKey);
                console.log(`[Paste Button] Verificando ${foodId} em ${targetDiaKey}-${targetRefKey}. Existe? ${existsInTarget}`);

                let newInstanceGroup;
                if (existsInTarget) {
                    // Se JÁ EXISTE na célula alvo, pega o PRÓXIMO número global disponível
                    newInstanceGroup = getNextInstanceGroupNumber(foodId);
                    console.log(`[Paste Button] ${foodId} já existe no alvo. Usando próximo grupo global: ${newInstanceGroup}`);
                } else {
                    // Se NÃO EXISTE na célula alvo, REUTILIZA o número do grupo original copiado
                    newInstanceGroup = originalInstanceGroup;
                    console.log(`[Paste Button] ${foodId} não existe no alvo. Reutilizando grupo original: ${newInstanceGroup}`);
                }

                // Adiciona o item à célula com o grupo determinado (novo ou original) e a quantidade original
                addItemToCell(targetCell, foodId, newInstanceGroup, originalQty);
                itemsPastedCount++;
                namesPasted.push(alimentosIdNomeMap[foodId] || `ID ${foodId}`);

                // A função addItemToCell já atualiza o foodInstanceGroupCounters se newInstanceGroup for maior
            });

            if (itemsPastedCount > 0) {
                 const firstPastedName = namesPasted[0];
                 // A mensagem de status poderia ser melhorada para indicar se manteve ou incrementou grupo, mas fica complexo.
                 const statusMsg = itemsPastedCount === 1
                    ? `"${firstPastedName}" colado.`
                    : `${itemsPastedCount} itens colados.`;
                 showStatus('success', statusMsg, 'fa-paste');

                 // Se a operação original foi 'cut', limpa o clipboard após colar.
                 if (internalClipboard.type === 'cut') {
                     internalClipboard = { type: null, itemsData: [] };
                 }

                 clearPasteTarget(); // Limpa o alvo visual
                 updateManipulationButtons(); // Atualiza os botões
                 triggerCalculation(); // Recalcula
                 markGridChanged();
                 console.log(`[Paste Button] ${itemsPastedCount} itens colados.`);
            } else {
                console.warn("[Paste Button] Nenhum item foi colado.");
            }
        });

        // --- ADICIONAR ESTA NOVA FUNÇÃO HELPER ---
        /**
         * Verifica se um item com o foodId especificado já existe
         * dentro de uma célula específica (dia/refeição).
         */
        function checkIfFoodIdExistsInCell(foodId, diaKey, refKey) {
            if (cardapioAtual[diaKey]?.[refKey] && Array.isArray(cardapioAtual[diaKey][refKey])) {
                return cardapioAtual[diaKey][refKey].some(item => item && item.foodId === foodId);
            }
            return false; // Retorna falso se a estrutura não existir ou não for um array
        }
        // Função auxiliar para pegar o maior grupo (apenas para a mensagem de status)
        function getHighestInstanceGroup(foodId) {
             return foodInstanceGroupCounters[foodId] || 1;
        }
        
        $(document).on('keydown', function(e) {
            // Ignora atalhos se o foco estiver em inputs, selects ou textareas
            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            // Ignora se alguma modal estiver aberta
            if ($('.modal-overlay:visible').length > 0) {
                return;
            }

            const isCtrl = e.ctrlKey || e.metaKey; // Considera Cmd no Mac

            // Ctrl+C (Copiar)
            if (isCtrl && e.key.toLowerCase() === 'c') {
                if (selectedItemsCollection.length > 0 && !$('#item-copy-btn').prop('disabled')) {
                    e.preventDefault(); // Previne a cópia padrão do navegador
                    $('#item-copy-btn').click();
                    console.log("Atalho: Ctrl+C");
                }
            }
            // Ctrl+X (Recortar)
            else if (isCtrl && e.key.toLowerCase() === 'x') {
                 if (selectedItemsCollection.length > 0 && !$('#item-cut-btn').prop('disabled')) {
                    e.preventDefault(); // Previne o recorte padrão do navegador
                    $('#item-cut-btn').click();
                    console.log("Atalho: Ctrl+X");
                }
            }
            // Ctrl+V (Colar)
            else if (isCtrl && e.key.toLowerCase() === 'v') {
                 if (internalClipboard.itemsData.length > 0 && targetCellForPaste && !$('#item-paste-btn').prop('disabled')) {
                    e.preventDefault(); // Previne a colagem padrão do navegador
                    $('#item-paste-btn').click();
                    console.log("Atalho: Ctrl+V");
                }
            }
            // Delete (Excluir)
            else if (e.key === 'Delete' || e.key === 'Backspace') { // Adiciona Backspace como opção
                 if (selectedItemsCollection.length > 0 && !$('#item-delete-btn').prop('disabled')) {
                    e.preventDefault(); // Previne a navegação para trás no Backspace
                    $('#item-delete-btn').click(); // Aciona a função de delete (que já tem confirmação)
                    console.log("Atalho: Delete/Backspace");
                }
            }
        });


        // Listener para remover linha (Refeição Inteira) - Nenhuma mudança necessária aqui
        $(document).on('click', '.remove-row-btn', function(){
            const row = $(this).closest('tr');
            const refKey = row.data('refeicao-key');
            const refLabel = row.find('.refeicao-label .editable-label').text().trim();
            if(confirm(`Remover a refeição "${refLabel}" e todos os seus itens?`)){
                // Remove do layout
                if (refeicoesLayout[refKey]) {
                    delete refeicoesLayout[refKey];
                }
                // Remove dos dados dos dias
                diasKeys.forEach(diaKey => {
                    if (cardapioAtual[diaKey]?.[refKey]) {
                        delete cardapioAtual[diaKey][refKey];
                    }
                });
                // Remove a linha da tabela com animação
                row.fadeOut(300, function(){
                    $(this).remove();
                    updateRemoveButtonsVisibility(); // Ajusta visibilidade dos botões de remover nas outras linhas
                    initializeInstanceGroupCounters(); // Recalcula grupos
                    triggerCalculation(); // Recalcula nutrientes
                    markGridChanged(); // Marca alteração
                });
            }
        });

        // Listeners para editar label da Refeição/Horário - Nenhuma mudança necessária aqui
        $(document).on('click', '.label-cell span.editable-label', function(){
            const s=$(this), i=s.siblings('.label-input');
            if(s.hasClass('editing')||i.is(':visible')) return;
            // Salva qualquer outro input que esteja em modo de edição na mesma linha
            s.closest('tr').find('.label-input.editing').each(function(){ saveLabelEdit($(this)); });
            // Habilita edição para o clicado
            s.addClass('editing');
            i.val(s.text().trim()).addClass('editing').show().focus().select();
        });
        $(document).on('blur', '.label-input.editing', function(){
            saveLabelEdit($(this)); // Salva ao perder o foco
        });
         $(document).on('keydown', function(e) {
            if ($(e.target).is('input, textarea, select')) return;
            if ($('.modal-overlay:visible').length > 0) return;

            const isCtrl = e.ctrlKey || e.metaKey;

            // Ctrl+C (Copiar)
            if (isCtrl && e.key.toLowerCase() === 'c') {
                if (selectedItemsCollection.length > 0 && !$('#item-copy-btn').prop('disabled')) {
                    e.preventDefault();
                    $('#item-copy-btn').click(); // Chama o listener do botão que agora tem o feedback
                    console.log("Atalho: Ctrl+C");
                }
            }
            // Ctrl+X (Recortar)
            else if (isCtrl && e.key.toLowerCase() === 'x') {
                 if (selectedItemsCollection.length > 0 && !$('#item-cut-btn').prop('disabled')) {
                    e.preventDefault();
                    $('#item-cut-btn').click();
                    console.log("Atalho: Ctrl+X");
                }
            }
            // Ctrl+V (Colar)
            else if (isCtrl && e.key.toLowerCase() === 'v') {
                 if (internalClipboard.itemsData.length > 0 && targetCellForPaste && !$('#item-paste-btn').prop('disabled')) {
                    e.preventDefault();
                    $('#item-paste-btn').click(); // Chama o listener do botão com a nova lógica
                    console.log("Atalho: Ctrl+V");
                }
            }
            // Delete (Excluir)
            else if (e.key === 'Delete' || e.key === 'Backspace') {
                 if (selectedItemsCollection.length > 0 && !$('#item-delete-btn').prop('disabled')) {
                    e.preventDefault();
                    $('#item-delete-btn').click(); // Chama o listener do botão que agora trata múltiplos
                    console.log("Atalho: Delete/Backspace");
                }
            }
        });



        // --- Inicialização ---
        function inicializarInterface() {
            console.log("Inicializando interface Montador...");
            renderCardapioGrid();
            initializeInstanceGroupCounters();
            updateManipulationButtons(false);
            limparResultados();
            updateReferenciaTable(faixaEtariaSelecionada);

            const initialLoadMessage = <?php echo json_encode($load_error_message); ?>;
             console.log("Mensagem de Load:", initialLoadMessage);

            if (initialLoadMessage && initialLoadMessage.includes('corrompido') || initialLoadMessage && initialLoadMessage.includes('inválida') || initialLoadMessage && initialLoadMessage.includes('inesperado')) {
                 showStatus('warning', initialLoadMessage);
            } else if (faixaEtariaSelecionada) {
                 let hasItems = false;
                 if (typeof cardapioAtual === 'object' && cardapioAtual !== null) { Object.values(cardapioAtual).forEach(dia => { if(typeof dia === 'object' && dia !== null) { Object.values(dia).forEach(ref => { if (Array.isArray(ref) && ref.length > 0) hasItems = true; }); } }); }
                 if (hasItems) {
                     showStatus('info', 'Cardápio carregado. Calculando...', 'fa-sync');
                     triggerCalculation();
                 } else {
                      showStatus('info', 'Cardápio pronto. Adicione itens.', 'fa-edit');
                 }
             } else if (!faixaEtariaSelecionada) {
                 showStatus('warning', 'Cardápio carregado. Selecione a faixa etária.', 'fa-users');
             }

             gridHasChanged = false;
             saveStatusSpan.text('');
             $('#save-project-btn').removeClass('unsaved saved');
             console.log("Interface Montador inicializada.");
        }


        function updateReferenciaTable(faixaKey) {
            const container = $('#referencia-pnae-container');
            const title = $('#referencia-pnae-title');
            container.empty(); // Clear previous table

            const dataRef = referenciaValoresPnae[faixaKey];

            // Helper function for formatting values (mantida)
            function formatValue(value, key = '') {
                if (value === null || typeof value === 'undefined') return '-';
                let strValue = value.toString();
                if (strValue === '-') return '-';
                if (strValue === 'a') return 'a';
                if (typeof value === 'number') {
                    if (isNaN(value)) return '-';
                    if (['fe_mg', 'vitc_mg'].includes(key) ||
                        (key.includes('_g_') && value % 1 !== 0 && value < 1000) ||
                        (key.includes('_g_') && Math.abs(value) < 1 && value !== 0) ) {
                         return value.toFixed(1).replace('.', ',');
                    } else {
                        return Math.round(value).toLocaleString('pt-BR');
                    }
                }
                return htmlspecialchars(strValue);
            }


            if (dataRef && dataRef.refs && dataRef.refs.length > 0) {
                title.text(`Valores de Referência PNAE - ${dataRef.faixa}`);
                const useCaFeVit = ['bercario', 'creche'].includes(faixaKey);

                let tableHTML = '<table class="cardapio-montagem-table" id="referencia-pnae-table" style="width: auto; margin: 0 auto; border-spacing: 0;">';

                // --- Table Header (thead) ---
                tableHTML += '<thead style="border-bottom: 2px solid #dee2e6;">';

                // Row 1: Main Group Headers (mantido com colspan=3)
                tableHTML += '<tr>';
                tableHTML += '<th rowspan="2" style="text-align: left; vertical-align: bottom; min-width: 200px; border-right: 1px solid #dee2e6; padding-bottom: 5px;">Valores de referência para:</th>';
                tableHTML += '<th rowspan="2" style="vertical-align: middle; border-right: 1px solid #dee2e6;">Nº ref.</th>';
                tableHTML += '<th rowspan="2" style="vertical-align: middle; border-right: 1px solid #dee2e6;">Energia (Kcal)</th>';
                tableHTML += '<th colspan="3" style="text-align: center; border-right: 1px solid #dee2e6;">PROTEÍNAS (g)</th>';
                tableHTML += '<th colspan="3" style="text-align: center; border-right: 1px solid #dee2e6;">LIPÍDIOS (g)</th>';
                tableHTML += '<th colspan="3" style="text-align: center; border-right: 1px solid #dee2e6;">CARBOIDRATOS (g)</th>';
                if (useCaFeVit) {
                    tableHTML += '<th rowspan="2" style="vertical-align: middle; border-right: 1px solid #dee2e6;">Ca (mg)</th>';
                    tableHTML += '<th rowspan="2" style="vertical-align: middle; border-right: 1px solid #dee2e6;">Fe (mg)</th>';
                    tableHTML += '<th rowspan="2" style="vertical-align: middle; border-right: 1px solid #dee2e6;">Vit. A (µg)</th>';
                    tableHTML += '<th rowspan="2" style="vertical-align: middle;">Vit. C (mg)</th>';
                } else {
                    tableHTML += '<th rowspan="2" style="vertical-align: middle;">Na (mg)</th>';
                }
                tableHTML += '</tr>';

                // Row 2: Sub Group Headers (% VET e coluna do 'a' VAZIA)
                tableHTML += '<tr>';
                // Sub-headers para PROTEÍNAS
                tableHTML += '<th style="text-align: center; border-right: none;">10% VET</th>';
                // A COLUNA DO MEIO (DO 'a') AGORA ESTÁ VAZIA NO HEADER
                tableHTML += '<th style="text-align: center; border-left: none; border-right: none; font-weight: normal; font-style: italic;"> </th>'; // <<-- MUDANÇA AQUI
                tableHTML += '<th style="text-align: center; border-left: none; border-right: 1px solid #dee2e6;">15% VET</th>';
                // Sub-headers para LIPÍDIOS
                tableHTML += '<th style="text-align: center; border-right: none;">15% VET</th>';
                // A COLUNA DO MEIO (DO 'a') AGORA ESTÁ VAZIA NO HEADER
                tableHTML += '<th style="text-align: center; border-left: none; border-right: none; font-weight: normal; font-style: italic;"> </th>'; // <<-- MUDANÇA AQUI
                tableHTML += '<th style="text-align: center; border-left: none; border-right: 1px solid #dee2e6;">30% VET</th>';
                // Sub-headers para CARBOIDRATOS
                tableHTML += '<th style="text-align: center; border-right: none;">55% VET</th>';
                // A COLUNA DO MEIO (DO 'a') AGORA ESTÁ VAZIA NO HEADER
                tableHTML += '<th style="text-align: center; border-left: none; border-right: none; font-weight: normal; font-style: italic;"> </th>'; // <<-- MUDANÇA AQUI
                tableHTML += '<th style="text-align: center; border-left: none; border-right: 1px solid #dee2e6;">65% VET</th>';
                tableHTML += '</tr>';

                tableHTML += '</thead>';

                // --- Table Body (tbody) ---
                // (O tbody permanece EXATAMENTE como na versão anterior, mostrando o 'a' nos dados)
                tableHTML += '<tbody>';
                dataRef.refs.forEach(refRow => {
                    tableHTML += '<tr>';
                    // Colunas 1, 2, 3
                    tableHTML += `<td style="text-align: left; border-right: 1px solid #dee2e6;">${htmlspecialchars(refRow.nivel || '-')}</td>`;
                    tableHTML += `<td style="text-align: center; border-right: 1px solid #dee2e6;">${htmlspecialchars(refRow.n_ref || '-')}</td>`;
                    tableHTML += `<td style="text-align: center; border-right: 1px solid #dee2e6;">${formatValue(refRow.valores?.energia, 'energia')}</td>`;

                    // Colunas 4, 5, 6 (Proteínas: Valor1 | 'a' | Valor2)
                    tableHTML += `<td style="text-align: center; border-right: none;">${formatValue(refRow.valores?.proteina_g_10, 'proteina_g_10')}</td>`;
                    tableHTML += `<td style="text-align: center; border-left: none; border-right: none; padding: 0 2px;">a</td>`; // Mantém 'a' nos dados
                    tableHTML += `<td style="text-align: center; border-left: none; border-right: 1px solid #dee2e6;">${formatValue(refRow.valores?.proteina_g_15, 'proteina_g_15')}</td>`;

                    // Colunas 7, 8, 9 (Lipídios: Valor1 | 'a' | Valor2)
                    tableHTML += `<td style="text-align: center; border-right: none;">${formatValue(refRow.valores?.lipidio_g_15, 'lipidio_g_15')}</td>`;
                    tableHTML += `<td style="text-align: center; border-left: none; border-right: none; padding: 0 2px;">a</td>`; // Mantém 'a' nos dados
                    tableHTML += `<td style="text-align: center; border-left: none; border-right: 1px solid #dee2e6;">${formatValue(refRow.valores?.lipidio_g_30, 'lipidio_g_30')}</td>`;

                    // Colunas 10, 11, 12 (Carboidratos: Valor1 | 'a' | Valor2)
                    tableHTML += `<td style="text-align: center; border-right: none;">${formatValue(refRow.valores?.carboidrato_g_55, 'carboidrato_g_55')}</td>`;
                    tableHTML += `<td style="text-align: center; border-left: none; border-right: none; padding: 0 2px;">a</td>`; // Mantém 'a' nos dados
                    tableHTML += `<td style="text-align: center; border-left: none; border-right: 1px solid #dee2e6;">${formatValue(refRow.valores?.carboidrato_g_65, 'carboidrato_g_65')}</td>`;

                    // Micronutrientes (condicional)
                    if (useCaFeVit) {
                        tableHTML += `<td style="text-align: center; border-right: 1px solid #dee2e6;">${formatValue(refRow.valores?.ca_mg, 'ca_mg')}</td>`;
                        tableHTML += `<td style="text-align: center; border-right: 1px solid #dee2e6;">${formatValue(refRow.valores?.fe_mg, 'fe_mg')}</td>`;
                        tableHTML += `<td style="text-align: center; border-right: 1px solid #dee2e6;">${formatValue(refRow.valores?.vita_ug, 'vita_ug')}</td>`;
                        tableHTML += `<td style="text-align: center;">${formatValue(refRow.valores?.vitc_mg, 'vitc_mg')}</td>`;
                    } else {
                        tableHTML += `<td style="text-align: center;">${formatValue(refRow.valores?.na_mg, 'na_mg')}</td>`;
                    }
                    tableHTML += '</tr>';
                });
                tableHTML += '</tbody></table>';

                container.html(tableHTML);

            } else {
                title.text(`Valores de Referência PNAE`);
                container.html(`<p style="text-align: center; color: var(--text-light); padding: 15px; background-color: #f8f9fa; border: 1px solid var(--light-border); border-radius: var(--border-radius);">
                    Selecione uma Faixa Etária acima para visualizar os valores de referência.
                </p>`);
            }
        }
        // Chama a inicialização DEPOIS que todas as funções foram definidas
        inicializarInterface();

    }); // Fim document ready
    //]]>
    </script>

</body>
</html>