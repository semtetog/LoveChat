<?php
// /admin/api/get_rankings.php (ATUALIZADO vFinal - Lógica Correta)

ob_start();
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0); ini_set('log_errors', 1); error_reporting(E_ALL);

$base_dir = dirname(__DIR__, 2);
$log_path = $base_dir . '/logs/admin_api_errors.log';
if (!is_dir(dirname($log_path))) { @mkdir(dirname($log_path), 0755, true); }
if (is_writable(dirname($log_path))) { ini_set('error_log', $log_path); }
else { error_log("WARNING: [get_rank] Custom log dir not writable."); ini_set('error_log', ''); }

// --- Funções Auxiliares Padronizadas ---
if (!function_exists('admin_api_log')) {
    function admin_api_log($message, $data = []) {
        $timestamp = date('Y-m-d H:i:s'); $adminId = $_SESSION['user_id'] ?? 'NoSession/Unknown';
        $logMessage = "[{$timestamp}] [Admin:{$adminId}] [get_rank] {$message}";
        if (!empty($data)) { $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR); $logMessage .= ($jsonData === false) ? " - Dados: [Erro JSON Log: " . json_last_error_msg() . "]" : " - Dados: " . $jsonData; }
        @error_log($logMessage . PHP_EOL, 3, ini_get('error_log'));
    }
}
if (!function_exists('json_response')) {
     function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
         $output = ob_get_clean();
         if ($output && !$is_error && trim($output) !== '') { admin_api_log("Output inesperado!", ['output' => substr(trim($output), 0, 200)]); if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); } echo json_encode(['success' => false, 'message' => 'Erro interno (Output).'], JSON_UNESCAPED_UNICODE); exit(); }
         elseif ($output && $is_error) { $message = ($message ? $message . " | " : "") . "Output: " . substr(trim($output), 0, 200); }
         if (!headers_sent()) { http_response_code($http_code); header('Content-Type: application/json; charset=utf-8'); }
         $response = ['success' => !$is_error]; if ($message !== null) $response['message'] = $message;
         if (!$is_error && $data !== null) $response['rankings'] = $data; // Chave 'rankings'
         elseif ($is_error && $data !== null) $response['details'] = $data;
         $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
         if ($jsonOutput === false) { admin_api_log("JSON Encode Error", ['error' => json_last_error_msg()]); if (!headers_sent()) { http_response_code(500); } echo '{"success":false,"message":"Erro interno (JSON Encode)."}'; }
         else { echo $jsonOutput; }
         exit();
     }
}
// --- Fim Funções Auxiliares ---

try {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { json_response(null, 403, true, "Acesso negado."); }
    $adminUserId = (int)$_SESSION['user_id'];

    $db_path = $base_dir . '/includes/db.php';
    if (!file_exists($db_path)) { json_response(null, 500, true, "Config DB ausente."); } require_once $db_path;
    if (!isset($pdo)) { json_response(null, 500, true, "Falha DB."); }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->exec("SET NAMES 'utf8mb4'");
    admin_api_log("Iniciando", ['admin_id' => $adminUserId]);

    // --- Query ATUALIZADA para buscar o Rank ---
    // 1. Junta `usuarios` com `saldos_diarios`
    // 2. Filtra por usuários ativos e não-admins
    // 3. Agrupa por usuário
    // 4. Calcula a SOMA de `saldos_diarios.saldo` para cada usuário
    // 5. Ordena pela SOMA DESCENDENTE
    // 6. Limita aos TOP (ex: 20)
    $sql = "
        SELECT
            u.id,
            u.nome,
            u.avatar,
            COALESCE(SUM(sd.saldo), 0.00) AS total_earnings -- Calcula a SOMA dos saldos diários
        FROM usuarios u
        LEFT JOIN saldos_diarios sd ON u.id = sd.usuario_id
        WHERE u.is_active = 1 AND u.is_admin = 0
        GROUP BY u.id, u.nome, u.avatar -- Agrupa para a função SUM funcionar por usuário
        ORDER BY total_earnings DESC, u.created_at ASC -- Ordena pela SOMA, desempata por criação
        LIMIT 20 -- Define quantos aparecerão no rank
    ";

    $stmt = $pdo->query($sql);
    $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Converte total_earnings para float (já deve ser float do SUM, mas garante)
    foreach ($rankings as &$rankItem) {
        $rankItem['total_earnings'] = (float)($rankItem['total_earnings'] ?? 0.00);
    }
    unset($rankItem); // Desfaz a referência

    admin_api_log("Rankings buscados pela SOMA", ['count' => count($rankings)]);
    json_response($rankings, 200, false, "Ranking carregado.");

} catch (PDOException $e) {
    admin_api_log("Erro PDO", ['error' => $e->getMessage(), 'code' => $e->getCode()]);
    json_response(['error_code' => $e->getCode()], 500, true, 'Erro no banco de dados.');
} catch (Throwable $t) {
     admin_api_log("Erro Geral", ['error' => $t->getMessage(), 'file' => $t->getFile(), 'line' => $t->getLine()]);
     json_response(null, 500, true, 'Erro inesperado no servidor.');
}
?>