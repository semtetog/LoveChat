<?php
// /admin/api/mark_balance_paid.php (REVISADO v2 - Garantir ob_clean)

ob_start(); // << INICIAR BUFFER O MAIS CEDO POSSÍVEL
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0); ini_set('log_errors', 1); error_reporting(E_ALL);

$base_dir = dirname(__DIR__, 2);
$log_path = $base_dir . '/logs/admin_api_errors.log';
if (!is_dir(dirname($log_path))) { @mkdir(dirname($log_path), 0755, true); }
if (is_writable(dirname($log_path))) { ini_set('error_log', $log_path); }
else { error_log("WARNING: [mark_paid] Custom log dir not writable."); ini_set('error_log', ''); }

// --- Funções Auxiliares ---
if (!function_exists('admin_api_log')) {
    function admin_api_log($message, $data = []) {
        $timestamp = date('Y-m-d H:i:s'); $adminId = $_SESSION['user_id'] ?? 'NoSession/Unknown';
        $logMessage = "[{$timestamp}] [Admin:{$adminId}] [mark_paid] {$message}";
        if (!empty($data)) { $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR); $logMessage .= ($jsonData === false) ? " - Dados: [Erro JSON Log: " . json_last_error_msg() . "]" : " - Dados: " . $jsonData; }
        @error_log($logMessage . PHP_EOL, 3, ini_get('error_log'));
    }
}
if (!function_exists('json_response')) {
    function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
        $output = ob_get_clean(); // <<< LIMPA BUFFER AQUI
        if ($output && !$is_error && trim($output) !== '') { admin_api_log("Output inesperado!", ['output' => substr(trim($output), 0, 200)]); if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); } echo json_encode(['success' => false, 'message' => 'Erro interno (Output). Ver logs.'], JSON_UNESCAPED_UNICODE); exit(); }
        elseif ($output && $is_error) { $message = ($message ? $message . " | " : "") . "Output: " . substr(trim($output), 0, 200); }
        if (!headers_sent()) { http_response_code($http_code); header('Content-Type: application/json; charset=utf-8'); }
        $response = ['success' => !$is_error]; if ($message !== null) $response['message'] = $message;
        if (!$is_error && $data !== null) { $response['data'] = $data; } elseif ($is_error && is_array($data)) { $response = array_merge($response, $data); }
        $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($jsonOutput === false) { admin_api_log("JSON Encode Error", ['error' => json_last_error_msg()]); if (!headers_sent()) { http_response_code(500); } echo '{"success":false,"message":"Erro interno (JSON Encode)."}'; }
        else { echo $jsonOutput; }
        exit();
    }
}
// --- Fim Funções ---

$targetUserId = null; $targetDate = null;
try {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { json_response(null, 403, true, 'Acesso negado.'); }
    $adminUserId = (int)$_SESSION['user_id'];

    $db_path = $base_dir . '/includes/db.php';
    if (!file_exists($db_path)) { json_response(null, 500, true, "Config DB ausente."); } require_once $db_path;
    if (!isset($pdo)) { json_response(null, 500, true, "Falha DB."); }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->exec("SET NAMES 'utf8mb4'");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(null, 405, true, 'Método inválido.'); }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) { json_response(null, 400, true, 'JSON inválido.'); }

    $targetUserId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
    $targetDate = filter_var($input['date'] ?? null, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}$/']]);
    $markAs = isset($input['paid_status']) ? (bool)$input['paid_status'] : true;

    if (!$targetUserId || $targetUserId <= 0) { json_response(null, 400, true, 'ID usuário inválido.'); }
    if (!$targetDate) { json_response(null, 400, true, 'Data inválida (AAAA-MM-DD).'); }
    admin_api_log("Iniciando", ['admin_id' => $adminUserId, 'target_user' => $targetUserId, 'date' => $targetDate, 'mark_as' => $markAs]);

    $pdo->beginTransaction();

    // Garante registro diário
    $sqlCheckOrInsert = "INSERT INTO saldos_diarios (usuario_id, data, saldo, total_comprovantes, ultima_modificacao_admin_id) VALUES (:user_id, :date, 0.00, 0.00, :admin_id) ON DUPLICATE KEY UPDATE ultima_modificacao_admin_id = VALUES(ultima_modificacao_admin_id)";
    $stmtCheck = $pdo->prepare($sqlCheckOrInsert);
    $stmtCheck->execute([':user_id' => $targetUserId, ':date' => $targetDate, ':admin_id' => $adminUserId]);
    admin_api_log("Garantindo registro diário.", ['target' => $targetUserId, 'date' => $targetDate, 'rows' => $stmtCheck->rowCount()]);

    // Atualiza status 'pago'
    $sql = "UPDATE saldos_diarios SET pago = :paid_status, ultima_modificacao_admin_id = :admin_id WHERE usuario_id = :user_id AND data = :date";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':paid_status' => $markAs ? 1 : 0, ':admin_id' => $adminUserId, ':user_id' => $targetUserId, ':date' => $targetDate]);
    $rowCount = $stmt->rowCount();

    // Log no Feed Admin (Após Commit)
    try {
        $eventData = json_encode(['target_user_id' => $userIdToReset, 'reset_date' => $today]);
        // Verifica se a codificação JSON falhou
        if ($eventData === false) {
            throw new Exception('Falha ao codificar dados do evento para o feed: ' . json_last_error_msg());
        }

        $stmt_feed = $pdo->prepare(
           "INSERT INTO admin_events_feed (event_type, user_id, event_data, related_id, status, created_at)
            VALUES ('admin_balance_reset', :admin_id, :data, :target_id, 'completed', NOW())"
        );
        $stmt_feed->execute([
            ':admin_id' => $adminUserId,      // ID do admin que fez a ação
            ':data' => $eventData,            // JSON com detalhes
            ':target_id' => $userIdToReset,   // ID do usuário afetado
        ]);
        admin_api_log("Evento 'admin_balance_reset' registrado no feed.", ['TargetUserID' => $userIdToReset, 'FeedEventID' => $pdo->lastInsertId()]);
    } catch (Exception $e_feed) {
        // Loga o erro do feed, mas não impede o sucesso da operação principal
        admin_api_log("AVISO: Falha ao registrar evento 'admin_balance_reset' no feed.", ['TargetUserID' => $userIdToReset, 'Error' => $e_feed->getMessage()]);
    }

    $pdo->commit();
    $statusText = $markAs ? "pago" : "NÃO pago";
    admin_api_log("Status pagamento atualizado para {$statusText}.", ['target' => $targetUserId, 'date' => $targetDate, 'rows' => $rowCount]);

    json_response(['user_id' => $targetUserId, 'date' => $targetDate, 'new_paid_status' => $markAs], 200, false, "Saldo do dia {$targetDate} marcado como {$statusText}.");

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    admin_api_log("Erro PDO", ['target' => $targetUserId, 'date' => $targetDate, 'error' => $e->getMessage(), 'code' => $e->getCode()]);
    json_response(null, 500, true, 'Erro no banco de dados.');
} catch (Throwable $t) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    admin_api_log("Erro Geral", ['target' => $targetUserId, 'date' => $targetDate, 'error' => $t->getMessage()]);
    json_response(null, 500, true, 'Erro inesperado no servidor.');
}
// Não deve chegar aqui, mas garante limpeza se chegar
ob_end_flush();
?>