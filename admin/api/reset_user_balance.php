<?php
// /admin/api/reset_user_balance.php (COMPLETO vFinal)

ob_start();
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0); ini_set('log_errors', 1); error_reporting(E_ALL);

$base_dir = dirname(__DIR__, 2);
$log_path = $base_dir . '/logs/admin_api_errors.log';
if (!is_dir(dirname($log_path))) { @mkdir(dirname($log_path), 0755, true); }
if (is_writable(dirname($log_path))) { ini_set('error_log', $log_path); }
else { error_log("WARNING: [reset_balance] Custom log dir not writable."); ini_set('error_log', ''); }

// --- Funções Auxiliares Padronizadas ---
if (!function_exists('admin_api_log')) { function admin_api_log($message, $data = []) { /* ... (função log com ID [reset_balance]) ... */ } }
if (!function_exists('json_response')) { function json_response($data = null, $http_code = 200, $is_error = false, $message = null) { /* ... (função resposta com ob_get_clean) ... */ } }
// --- Fim Funções Auxiliares ---

$userIdToReset = null;
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
    $userIdToReset = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userIdToReset || $userIdToReset <= 0) { json_response(null, 400, true, 'ID de usuário inválido.'); }
    if ($userIdToReset === $adminUserId) { json_response(null, 400, true, 'Ação não permitida para sua conta.'); }
    admin_api_log("Iniciando", ['admin_id' => $adminUserId, 'target_user_id' => $userIdToReset]);

    $today = date('Y-m-d');
    $pdo->beginTransaction();
    admin_api_log("Transação iniciada", ['target' => $userIdToReset, 'date' => $today]);

    // *** Query CORRETA com ultima_modificacao_admin_id ***
    $sql = "INSERT INTO saldos_diarios (usuario_id, data, saldo, total_comprovantes, ultima_modificacao_admin_id)
            VALUES (:user_id, :today, 0.00, 0.00, :admin_id)
            ON DUPLICATE KEY UPDATE saldo = 0.00, total_comprovantes = 0.00, ultima_modificacao_admin_id = VALUES(ultima_modificacao_admin_id)";
    $params = [ ':user_id' => $userIdToReset, ':today' => $today, ':admin_id'=> $adminUserId ];

    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    admin_api_log("Query executada", ['target' => $userIdToReset, 'rows' => $stmt->rowCount()]);

    // Verificação intra-transação
    $checkStmt = $pdo->prepare("SELECT saldo FROM saldos_diarios WHERE usuario_id = :uid AND data = :dt FOR UPDATE");
    $checkStmt->execute([':uid' => $userIdToReset, ':dt' => $today]); $currentSaldo = $checkStmt->fetchColumn(); $checkStmt->closeCursor();
    if ($currentSaldo === false || (float)$currentSaldo != 0.00) {
        admin_api_log("ERRO CRÍTICO: Saldo não zerado DENTRO da transação.", ['target' => $userIdToReset, 'saldo' => $currentSaldo]);
        $pdo->rollBack(); json_response(null, 500, true, 'Erro interno: Falha ao verificar atualização.');
    }
    admin_api_log("Verificação intra-transação OK.", ['target' => $userIdToReset]);

    $pdo->commit();
    admin_api_log("Transação commitada.", ['target' => $userIdToReset]);

    // Log no Feed Admin
    try {
        $eventData = json_encode(['target_user_id' => $userIdToReset]);
        $stmt_feed = $pdo->prepare("INSERT INTO admin_events_feed (event_type, user_id, event_data, related_id, status, created_at) VALUES ('admin_balance_reset', :admin_id, :data, :target_id, 'completed', NOW())");
        $stmt_feed->execute([':admin_id' => $adminUserId, ':data' => $eventData, ':target_id' => $userIdToReset]);
    } catch (Exception $e_feed) { admin_api_log("AVISO: Falha log feed.", ['error' => $e_feed->getMessage()]); }

    json_response(['new_balance' => 0.00], 200, false, 'Saldo zerado para hoje!');

} catch (PDOException $e) {
    $inTransaction = isset($pdo) && $pdo->inTransaction(); if ($inTransaction) { try { $pdo->rollBack(); } catch (Exception $re) { admin_api_log("Erro rollback (PDO): ".$re->getMessage()); } }
    admin_api_log("Erro PDO", ['target' => $userIdToReset ?? 'N/A', 'error' => $e->getMessage(), 'code' => $e->getCode(), 'inTrans' => $inTransaction]);
    json_response(['error_code' => $e->getCode()], 500, true, 'Erro de banco de dados.');
} catch (Throwable $t) {
    $inTransaction = isset($pdo) && $pdo->inTransaction(); if ($inTransaction) { try { $pdo->rollBack(); } catch (Exception $re) { admin_api_log("Erro rollback (Thr): ".$re->getMessage()); } }
    admin_api_log("Erro Geral", ['target' => $userIdToReset ?? 'N/A', 'error' => $t->getMessage(), 'file' => $t->getFile(), 'line' => $t->getLine(), 'inTrans' => $inTransaction]);
    json_response(null, 500, true, 'Erro inesperado no servidor.');
}
?>