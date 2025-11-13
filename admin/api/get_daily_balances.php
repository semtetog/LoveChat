<?php
// /admin/api/get_daily_balances.php (ATUALIZADO v2 - Visão por Dia)

ob_start();
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0); ini_set('log_errors', 1); error_reporting(E_ALL);

$base_dir = dirname(__DIR__, 2);
$log_path = $base_dir . '/logs/admin_api_errors.log';
if (!is_dir(dirname($log_path))) { @mkdir(dirname($log_path), 0755, true); }
if (is_writable(dirname($log_path))) { ini_set('error_log', $log_path); }
else { error_log("WARNING: [get_daily_balances] Custom log dir not writable."); ini_set('error_log', ''); }

// --- Funções Auxiliares ---
if (!function_exists('admin_api_log')) { function admin_api_log($message, $data = []) { /* ... */ } }
if (!function_exists('json_response')) {
    function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
        $output = ob_get_clean();
        if ($output && !$is_error && trim($output) !== '') { /* ... */ }
        elseif ($output && $is_error) { $message = ($message ? $message . " | " : "") . "Output: " . substr(trim($output), 0, 200); }
        if (!headers_sent()) { http_response_code($http_code); header('Content-Type: application/json; charset=utf-8'); }
        $response = ['success' => !$is_error]; if ($message !== null) $response['message'] = $message;
        // Retorna 'balances' (um array) ou 'details' (para erro)
        if (!$is_error && $data !== null) $response['balances'] = $data;
        elseif ($is_error && $data !== null) $response['details'] = $data;
        $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($jsonOutput === false) { /* ... */ } else { echo $jsonOutput; }
        exit();
    }
}
// --- Fim Funções ---

try {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { json_response(null, 403, true, "Acesso negado."); }
    $adminUserId = (int)$_SESSION['user_id'];

    $db_path = $base_dir . '/includes/db.php';
    if (!file_exists($db_path)) { json_response(null, 500, true, "Config DB ausente."); } require_once $db_path;
    if (!isset($pdo)) { json_response(null, 500, true, "Falha DB."); }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->exec("SET NAMES 'utf8mb4'");

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { json_response(null, 405, true, 'Método inválido.'); }

    // Recebe a data desejada
    $targetDate = filter_input(INPUT_GET, 'date', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}$/']]);
    if (!$targetDate) {
        // Se nenhuma data for fornecida, pega a de ONTEM por padrão
        $targetDate = date('Y-m-d', strtotime('-1 day'));
        admin_api_log("Data não fornecida, usando ontem como padrão.", ['date' => $targetDate]);
    }
    admin_api_log("Buscando saldos para data", ['admin_id' => $adminUserId, 'date' => $targetDate]);

    // --- Query para buscar TODOS os usuários NÃO ADMIN e seus saldos/status NAQUELA data ---
    $sql = "
        SELECT
            u.id AS usuario_id,
            u.nome,
            u.avatar,
            u.chave_pix,
            u.tipo_chave_pix,
            COALESCE(sd.saldo, 0.00) AS saldo_dia, -- Saldo específico do dia
            COALESCE(sd.total_comprovantes, 0.00) AS total_comprovantes_dia,
            COALESCE(sd.pago, 0) AS pago -- Status de pagamento daquele dia
        FROM usuarios u
        LEFT JOIN saldos_diarios sd ON u.id = sd.usuario_id AND sd.data = :target_date
        WHERE u.is_admin = 0 AND u.is_active = 1 -- Busca apenas usuários ativos e não-admins
        ORDER BY u.nome ASC -- Ordena por nome
    ";
    // NOTA: LEFT JOIN garante que mesmo usuários sem saldo naquele dia apareçam (com saldo 0)

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':target_date' => $targetDate]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Converte tipos para consistência
    foreach ($balances as &$b) {
        $b['usuario_id'] = (int)$b['usuario_id'];
        $b['saldo_dia'] = (float)$b['saldo_dia'];
        $b['total_comprovantes_dia'] = (float)$b['total_comprovantes_dia'];
        $b['pago'] = (bool)$b['pago']; // Converte para true/false
        $b['avatar'] = $b['avatar'] ?? 'default.jpg'; // Garante avatar padrão
    }
    unset($b); // Desfaz referência

    admin_api_log("Saldos encontrados para data", ['date' => $targetDate, 'count' => count($balances)]);
    json_response($balances, 200, false, "Saldos de {$targetDate} carregados.");

} catch (PDOException $e) {
    admin_api_log("Erro PDO", ['date' => $targetDate ?? 'N/A', 'error' => $e->getMessage(), 'code' => $e->getCode()]);
    json_response(null, 500, true, 'Erro no banco de dados.');
} catch (Throwable $t) {
     admin_api_log("Erro Geral", ['date' => $targetDate ?? 'N/A', 'error' => $t->getMessage(), 'file' => $t->getFile(), 'line' => $t->getLine()]);
     json_response(null, 500, true, 'Erro inesperado no servidor.');
}
?>