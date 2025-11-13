<?php
// --- Configuração de Erros e Cabeçalhos ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 para produção/API
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

$response = [];
$alimentos_db = null;
$todos_pnae_ref = null; // Agora conterá apenas ['faixa' => 'Nome...']
$dados_ok = false;

// --- Carregamento Robusto de Dados ---
try {
    $dados_file = __DIR__ . '/dados.php';
    if (!file_exists($dados_file) || !is_readable($dados_file)) {
        throw new Exception('Erro Interno: dados.php não encontrado.');
    }
    ob_start();
    require_once $dados_file;
    $output = ob_get_clean();
    if (!empty($output)) {
        error_log("Saída inesperada durante include de dados.php (api_calculator): " . $output);
    }

    if (($dados_ok ?? false) !== true) {
         throw new Exception('Erro Interno: Falha ao carregar dados essenciais (dados.php não retornou $dados_ok=true).');
    }
    if (!isset($alimentos_db) || !is_array($alimentos_db) || empty($alimentos_db)) {
        throw new Exception('Erro Interno: $alimentos_db inválido ou vazio.');
    }
    // $todos_pnae_ref agora é simples, a verificação é menos crítica, mas ainda precisa existir
    if (!isset($todos_pnae_ref) || !is_array($todos_pnae_ref) || empty($todos_pnae_ref)) {
         throw new Exception('Erro Interno: $todos_pnae_ref (simplificado) inválido ou vazio.');
    }

} catch (Throwable $e) {
    error_log("Erro Crítico ao carregar dados.php em api_calculator.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['error' => 'Erro Crítico na Carga de Dados do Servidor (API). Verifique logs.']);
    exit;
}

// --- Leitura e Validação dos Dados da Requisição POST (Payload) ---
$payload_json = $_POST['payload'] ?? null;
if (!$payload_json) { /* ... (tratamento de erro) ... */ echo json_encode(['error'=>'Payload não recebido']); exit; }
$apiData = json_decode($payload_json, true);
if (json_last_error() !== JSON_ERROR_NONE) { /* ... (tratamento de erro) ... */ echo json_encode(['error'=>'JSON inválido']); exit; }

$cardapio_recebido = $apiData['cardapio'] ?? null;
$dias_ativos_recebidos = $apiData['dias_ativos'] ?? [];
$faixa_etaria_id = $apiData['faixa_etaria'] ?? null;
$preparacoes_defs = $apiData['meta']['preparacoes_defs'] ?? [];
$log_erros_calculo = [];

// Validações Essenciais
if (!$cardapio_recebido || !is_array($cardapio_recebido)) { /* ... (erro) ... */ }
if (!$faixa_etaria_id || !isset($todos_pnae_ref[$faixa_etaria_id])) { /* ... (erro) ... */ }
$pnae_ref_usar = $todos_pnae_ref[$faixa_etaria_id]; // Pega só o nome da faixa agora

// --- Inicializar Estruturas de Dados ---
$totais_diarios = [];
$totais_semanais = [];
$nutrientes_keys = [];

// Pega as chaves de nutrientes do primeiro alimento válido
$primeiro_alimento_valido = null; foreach ($alimentos_db as $data) { if (is_array($data) && isset($data['nome'])) { $primeiro_alimento_valido = $data; break; } }
if (!$primeiro_alimento_valido) { echo json_encode(['error' => 'Erro: Nenhuma definição de alimento encontrada.']); exit; }
foreach (array_keys($primeiro_alimento_valido) as $key) { if ($key !== 'nome' && is_numeric($primeiro_alimento_valido[$key])) { $nutrientes_keys[] = $key; } }
if (empty($nutrientes_keys)) { echo json_encode(['error' => 'Erro: Nenhuma chave de nutriente encontrada.']); exit; }

// Inicializa totais
$totais_semanais = array_fill_keys($nutrientes_keys, 0.0);
foreach ($dias_ativos_recebidos as $dia) { $totais_diarios[$dia] = array_fill_keys($nutrientes_keys, 0.0); }

// --- Processar Cardápio e Calcular Totais Diários e Semanais ---
// (A lógica interna de cálculo para alimentos base e preparações permanece a mesma da versão anterior)
foreach ($dias_ativos_recebidos as $dia) {
    if (!isset($cardapio_recebido[$dia])) continue;
    $refeicoes_do_dia = $cardapio_recebido[$dia];
    foreach ($refeicoes_do_dia as $ref_key => $itens_refeicao) {
        if (!is_array($itens_refeicao)) continue;
        foreach ($itens_refeicao as $item) {
            if (!is_array($item) || !isset($item['id'], $item['qty'])) { /* log e continue */ continue; }
            $item_id = (string)$item['id'];
            $item_qty = filter_var($item['qty'], FILTER_VALIDATE_FLOAT);
            $is_preparacao = $item['is_prep'] ?? (strpos($item_id, 'prep_') === 0);
            if ($item_qty === false || $item_qty <= 0) { /* log e continue */ continue; }

            $nutrientes_item = array_fill_keys($nutrientes_keys, 0.0);

            if (!$is_preparacao) { // Alimento Base
                $id_alimento_num = filter_var($item_id, FILTER_VALIDATE_INT);
                if ($id_alimento_num === false || !isset($alimentos_db[$id_alimento_num])) { /* log e continue */ continue; }
                $alimento_info = $alimentos_db[$id_alimento_num];
                foreach ($nutrientes_keys as $nutriente) {
                    if (isset($alimento_info[$nutriente]) && is_numeric($alimento_info[$nutriente])) {
                        $nutrientes_item[$nutriente] = (floatval($alimento_info[$nutriente]) / 100.0) * $item_qty;
                    }
                }
            } else { // Preparação
                if (!isset($preparacoes_defs[$item_id])) { /* log e continue */ continue; }
                $prep_info = $preparacoes_defs[$item_id];
                if (!isset($prep_info['ingredientes']) || empty($prep_info['ingredientes'])) { /* log e continue */ continue; }
                $nutrientes_receita_total = array_fill_keys($nutrientes_keys, 0.0);
                $peso_total_receita = 0.0;
                foreach ($prep_info['ingredientes'] as $ing) {
                    $ing_id_num = filter_var($ing['foodId'], FILTER_VALIDATE_INT);
                    $ing_qty = filter_var($ing['qty'], FILTER_VALIDATE_FLOAT);
                    if ($ing_id_num === false || !isset($alimentos_db[$ing_id_num]) || $ing_qty === false || $ing_qty <= 0) { /* log e pule ingrediente */ continue; }
                    $ing_info_db = $alimentos_db[$ing_id_num];
                    $peso_total_receita += $ing_qty;
                    foreach ($nutrientes_keys as $nutriente) {
                        if (isset($ing_info_db[$nutriente]) && is_numeric($ing_info_db[$nutriente])) {
                            $nutrientes_receita_total[$nutriente] += (floatval($ing_info_db[$nutriente]) / 100.0) * $ing_qty;
                        }
                    }
                }
                if ($peso_total_receita <= 0) { /* log e continue */ continue; }
                foreach ($nutrientes_keys as $nutriente) {
                    $nutrientes_item[$nutriente] = ($nutrientes_receita_total[$nutriente] / $peso_total_receita) * $item_qty;
                }
            } // Fim if/else is_preparacao

            // Acumula nos totais
            foreach ($nutrientes_keys as $nutriente) {
                if (isset($totais_diarios[$dia][$nutriente])) { $totais_diarios[$dia][$nutriente] += $nutrientes_item[$nutriente]; }
                if (isset($totais_semanais[$nutriente])) { $totais_semanais[$nutriente] += $nutrientes_item[$nutriente]; }
            }
        } // Fim loop itens
    } // Fim loop refeicoes
} // Fim loop dias

// --- Mapeamento Chaves Internas -> API ---
$map_keys_nutrientes = [
    'kcal' => 'kcal', 'carboidratos' => 'cho', 'proteina' => 'ptn', 'lipideos' => 'lpd',
    'calcio' => 'ca', 'ferro' => 'fe', 'retinol' => 'vita', 'vitamina_c' => 'vitc', 'sodio' => 'na'
];
$api_nutrients_keys = array_values($map_keys_nutrientes);

// --- Calcular Médias Semanais ---
$medias_semanais_api = [];
$num_dias_calc = count($dias_ativos_recebidos);
if ($num_dias_calc > 0) {
    foreach ($map_keys_nutrientes as $interno => $api_key) {
        $medias_semanais_api[$api_key] = isset($totais_semanais[$interno]) ? ($totais_semanais[$interno] / $num_dias_calc) : 0.0;
    }
} else {
    $medias_semanais_api = array_fill_keys($api_nutrients_keys, 0.0);
}

// --- Formatar Totais Diários para API ---
$totais_diarios_api = [];
$dias_semana_completa = $apiData['meta']['dias_keys'] ?? ['seg', 'ter', 'qua', 'qui', 'sex'];
foreach ($dias_semana_completa as $dia_key) {
    $totais_diarios_api[$dia_key] = array_fill_keys($api_nutrients_keys, 0.0);
    if (isset($totais_diarios[$dia_key])) {
        foreach ($map_keys_nutrientes as $interno => $api_key) {
            if (isset($totais_diarios[$dia_key][$interno])) {
                $totais_diarios_api[$dia_key][$api_key] = $totais_diarios[$dia_key][$interno];
            }
        }
    }
}

// --- Prepara Resposta Final (SEM ADEQUAÇÃO) ---
$response = [
    'daily_totals'          => $totais_diarios_api,
    'weekly_average'        => $medias_semanais_api,
    // 'adequacy_percentages'  => null, // Removido ou nulo
    'pnae_ref_selected'     => $pnae_ref_usar, // Envia o nome da faixa selecionada
    'debug_errors'          => $log_erros_calculo
];

// --- Retornar JSON ---
while (ob_get_level() > 0) { ob_end_clean(); }
$json_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR);
if (json_last_error() !== JSON_ERROR_NONE) {
     $json_error_msg = json_last_error_msg();
     error_log("JSON Encode Error (api_calculator.php): " . $json_error_msg);
     echo json_encode(['error' => 'Falha interna ao gerar resposta JSON (API): ' . htmlspecialchars($json_error_msg)]);
} else {
    echo $json_response;
}
exit;
?>