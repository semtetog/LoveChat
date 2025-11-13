<?php
// /admin/api/update_ranking.php (NOVO)

ob_start();
header('Content-Type: application/json; charset=utf-8');

// --- Error Handling and Timezone ---
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// --- Base Directory and Log Path ---
$base_dir = dirname(__DIR__, 2);
$log_path = $base_dir . '/logs/admin_api_errors.log'; // Log central

if (!is_dir(dirname($log_path))) { @mkdir(dirname($log_path), 0755, true); }
if (is_writable(dirname($log_path))) { ini_set('error_log', $log_path); }
else { error_log("WARNING: Custom log directory not writable: " . dirname($log_path)); ini_set('error_log', ''); }

// --- Helper Functions (Reutilizadas) ---
if (!function_exists('admin_api_log')) {
    function admin_api_log($message, $data = []) { /* ... (função completa) ... */ }
}
if (!function_exists('json_response')) {
    function json_response($data = null, $http_code = 200, $is_error = false, $message = null) { /* ... (função completa) ... */ }
}

// --- Main Logic ---
try {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        admin_api_log("Acesso não autorizado a update_ranking.php");
        json_response(null, 403, true, "Acesso negado.");
    }
    $adminUserId = (int)$_SESSION['user_id'];

    $db_path = $base_dir . '/includes/db.php';
    if (!file_exists($db_path)) { /* ... (erro DB config) ... */ }
    require_once $db_path;
    if (!isset($pdo) || !($pdo instanceof PDO)) { /* ... (erro PDO) ... */ }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Method Check
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(null, 405, true, 'Método inválido.'); }

    // Get JSON Input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) { json_response(null, 400, true, 'JSON inválido.'); }
    admin_api_log("Solicitação update_ranking recebida", ['admin_id' => $adminUserId, 'input' => $input]);

    // Validation
    $targetUserId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
    $newEarnings = filter_var($input['new_earnings'] ?? null, FILTER_VALIDATE_FLOAT); // Aceita float

    if (!$targetUserId || $targetUserId <= 0) { json_response(null, 400, true, 'ID de usuário inválido.'); }
    if ($newEarnings === false || $newEarnings < 0) { // filter_var retorna false se falhar a validação
        json_response(null, 400, true, 'Valor de ganhos inválido. Deve ser um número positivo.');
    }

    // --- Update Database ---
    // Atualiza a coluna total_earnings_override
    $sql = "UPDATE usuarios SET total_earnings_override = :new_earnings WHERE id = :user_id AND is_admin = 0"; // Só atualiza não-admins
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':new_earnings' => $newEarnings,
        ':user_id' => $targetUserId
    ]);
    $rowCount = $stmt->rowCount();

    if ($rowCount > 0) {
        admin_api_log("Ganhos manuais atualizados para usuário.", ['target_user_id' => $targetUserId, 'new_earnings' => $newEarnings]);
        json_response(['user_id' => $targetUserId, 'new_earnings' => $newEarnings], 200, false, 'Ganhos manuais atualizados com sucesso.');
    } else {
        // Verifica se o usuário existe e não é admin
        $stmtCheck = $pdo->prepare("SELECT 1 FROM usuarios WHERE id = :user_id AND is_admin = 0");
        $stmtCheck->execute([':user_id' => $targetUserId]);
        if ($stmtCheck->fetch()) {
            admin_api_log("Nenhuma alteração nos ganhos manuais (valor igual?).", ['target_user_id' => $targetUserId, 'new_earnings' => $newEarnings]);
            json_response(['user_id' => $targetUserId, 'new_earnings' => $newEarnings], 200, false, 'Nenhuma alteração necessária (valor pode ser o mesmo).');
        } else {
            admin_api_log("Usuário não encontrado ou é admin.", ['target_user_id' => $targetUserId]);
            json_response(null, 404, true, 'Usuário não encontrado ou é um administrador.');
        }
    }

} catch (PDOException $e) {
    admin_api_log("Erro PDO em update_ranking", ['target_user_id' => $targetUserId ?? 'N/A', 'error' => $e->getMessage(), 'code' => $e->getCode()]);
    json_response(['error_code' => $e->getCode()], 500, true, 'Erro no banco de dados.');
} catch (Throwable $t) {
     admin_api_log("Erro Geral em update_ranking", ['target_user_id' => $targetUserId ?? 'N/A', 'error' => $t->getMessage(), 'file' => $t->getFile(), 'line' => $t->getLine()]);
     json_response(null, 500, true, 'Erro inesperado no servidor.');
}
?>