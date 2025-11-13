<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Configurações de Erro
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$log_file_path = dirname(__DIR__, 2) . '/logs/api_update_payment_errors.log';
if (!file_exists(dirname($log_file_path))) { @mkdir(dirname($log_file_path), 0755, true); }
ini_set('error_log', $log_file_path);
error_reporting(E_ALL);

// --- Segurança e Validação ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    error_log("API Update Payment Status: Unauthorized access.");
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("API Update Payment Status: Invalid method.");
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}
if (empty($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
     error_log("API Update Payment Status: Invalid Content-Type.");
    echo json_encode(['success' => false, 'message' => 'Tipo de conteúdo inválido.']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
     error_log("API Update Payment Status: Invalid JSON.");
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$paymentId = filter_var($input['payment_id'] ?? null, FILTER_VALIDATE_INT);
$newStatus = filter_var($input['new_status'] ?? null, FILTER_SANITIZE_STRING);
$observation = filter_var($input['observation'] ?? '', FILTER_SANITIZE_STRING); // Pega a observação
$allowedStatuses = ['pendente', 'pago', 'erro'];

if (!$paymentId || !$newStatus || !in_array($newStatus, $allowedStatuses)) {
     error_log("API Update Payment Status: Invalid input data.");
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

// --- Conexão DB ---
$base_dir = dirname(__DIR__, 2) . '/';
$db_path = $base_dir . 'includes/db.php';
if (!file_exists($db_path)) { error_log("API Update Payment: DB config not found."); echo json_encode(['success' => false, 'message' => 'Erro interno.']); exit; }
require_once $db_path;
if (!isset($pdo) || !($pdo instanceof PDO)) { error_log("API Update Payment: DB connection failed."); echo json_encode(['success' => false, 'message' => 'Erro interno.']); exit; }

// --- Lógica de Atualização ---
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    $sql = "UPDATE pagamentos_pendentes SET status_pagamento = :new_status, observacao = :observation"; // Adiciona observacao
    $params = [
        ':new_status' => $newStatus,
        ':observation' => ($newStatus === 'erro' ? $observation : NULL), // Só salva obs se for erro
        ':payment_id' => $paymentId
    ];

    if ($newStatus === 'pago') {
         $sql .= ", data_pagamento = COALESCE(data_pagamento, :now)";
         $params[':now'] = date('Y-m-d H:i:s');
    } else {
         $sql .= ", data_pagamento = NULL";
    }

    $sql .= " WHERE id = :payment_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
         error_log("API Update Payment Status: ID $paymentId updated to '$newStatus'. Admin ID: ".$_SESSION['user_id'].". Obs: ".($observation ?: 'None'));
        echo json_encode(['success' => true, 'message' => 'Status do pagamento atualizado!']);
    } else {
         error_log("API Update Payment Status: No rows affected for ID $paymentId. Admin ID: ".$_SESSION['user_id']);
         echo json_encode(['success' => true, 'message' => 'Nenhuma alteração necessária.']);
    }

} catch (PDOException $e) {
    error_log("API Update Payment Status: DB Error for ID $paymentId - " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
} catch (Throwable $t) {
     error_log("API Update Payment Status: General Error for ID $paymentId - " . $t->getMessage());
     echo json_encode(['success' => false, 'message' => 'Erro interno inesperado.']);
}
?>