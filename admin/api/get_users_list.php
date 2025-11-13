<?php
// /admin/api/get_users_list.php

// Inicia o buffer de saída para capturar qualquer saída acidental
ob_start();

// Define o tipo de conteúdo ANTES de qualquer outra saída
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo'); // Consistência de fuso horário

// Configurações de erro (não exibir na resposta, mas logar)
error_reporting(E_ALL); // Reportar todos os erros para log
ini_set('display_errors', 0); // NÃO exibir erros na saída JSON
ini_set('log_errors', 1);   // Habilitar log de erros

// --- Definição do Caminho Base do Projeto ---
$base_dir = dirname(__DIR__, 2); // Ajuste este '2' conforme sua estrutura.
                                 // Se admin/api/ está na raiz, use dirname(__DIR__, 2) para chegar na raiz.

// --- Configuração de Logs ---
$log_path = $base_dir . '/logs/api_get_users_list_errors.log';
if (!is_dir(dirname($log_path))) {
    @mkdir(dirname($log_path), 0755, true);
}
if (is_writable(dirname($log_path)) || is_writable($log_path)) {
    ini_set('error_log', $log_path);
} else {
    error_log("WARNING: [get_users_list.php] Custom log directory '{$log_path}' is not writable. PHP errors will go to the default server error log.");
}
// --- Fim Configuração de Logs ---

$response = [
    'success' => false,
    'message' => 'Erro desconhecido ao processar a solicitação da lista de usuários.',
    'users' => [],
    'pagination' => null
];

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $response['message'] = 'Acesso negado. Autenticação de administrador necessária.';
        http_response_code(403);
        error_log("[get_users_list.php] Auth_Failed: Tentativa de acesso não autorizada. Session UserID: ".($_SESSION['user_id'] ?? 'N/A').", IsAdmin: ".($_SESSION['is_admin'] ?? 'N/A'));
        throw new Exception("Auth_Failed");
    }
    $adminUserId = (int)$_SESSION['user_id'];

    $db_path_check = $base_dir . '/includes/db.php';
    if (!file_exists($db_path_check)) {
        $response['message'] = 'Erro crítico: Arquivo de configuração do banco de dados (db.php) não encontrado.';
        http_response_code(500);
        error_log("[get_users_list.php] CRITICAL: db.php not found at expected path: " . $db_path_check);
        throw new Exception("DB_Config_Missing");
    }
    require_once $db_path_check;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $response['message'] = 'Erro crítico: Falha na conexão com o banco de dados (objeto PDO não estabelecido).';
        http_response_code(500);
        error_log("[get_users_list.php] CRITICAL: PDO connection object (\$pdo) not available from db.php.");
        throw new Exception("DB_PDO_Missing");
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 200, 'min_range' => 1, 'max_range' => 500]]);
    $searchTerm = isset($_GET['search']) ? trim(filter_var($_GET['search'], FILTER_SANITIZE_SPECIAL_CHARS)) : '';
    $today = date('Y-m-d');

    error_log("[get_users_list.php] API Call - AdminID: {$adminUserId}, Page: {$page}, Limit: {$limit}, Search: '{$searchTerm}'");

    $selectFields = "u.id, u.nome, u.email, u.avatar, u.is_admin, u.is_active, u.is_approved, u.created_at, u.last_login, u.login_count, u.telefone, u.tipo_chave_pix, u.chave_pix, COALESCE(sd.saldo, 0.00) as saldo_hoje, u.total_ganho_acumulado";
    $fromBase = "FROM usuarios u";
    $joinSaldos = "LEFT JOIN saldos_diarios sd ON u.id = sd.usuario_id AND sd.data = :today_param_for_join";

    $whereClauses = [];
    $queryParams = [':today_param_for_join' => $today];

    if (!empty($searchTerm)) {
        $whereClauses[] = "(u.nome LIKE :search_term OR u.email LIKE :search_term OR u.telefone LIKE :search_term)";
        $queryParams[':search_term'] = '%' . $searchTerm . '%';
    }
    $whereSql = count($whereClauses) > 0 ? " WHERE " . implode(' AND ', $whereClauses) : "";

    $countSql = "SELECT COUNT(u.id) " . $fromBase . $whereSql;
    $stmtCount = $pdo->prepare($countSql);
    $countExecParams = $queryParams;
    unset($countExecParams[':today_param_for_join']);
    if (empty($whereSql)) { $countExecParams = []; }
    $stmtCount->execute($countExecParams);
    $totalItems = (int)$stmtCount->fetchColumn();
    $totalPages = ($limit > 0 && $totalItems > 0) ? ceil($totalItems / $limit) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $limit;

    // ###########################################################################
    // ### MUDANÇA PRINCIPAL AQUI NA CLÁUSULA ORDER BY ###
    // ###########################################################################
    $dataSql = "SELECT " . $selectFields . " " . $fromBase . " " . $joinSaldos . $whereSql . " ORDER BY u.created_at DESC LIMIT :limit_param OFFSET :offset_param";
    // Removido: u.is_approved ASC,

    $stmtData = $pdo->prepare($dataSql);
    foreach ($queryParams as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limit_param', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset_param', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $users = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    error_log("[get_users_list.php] SQL Executed. Fetched: " . count($users) . " users. Total matching filter: " . $totalItems . ". Page: {$page}/{$totalPages}. Limit: {$limit}, Offset: {$offset}");

    foreach ($users as &$userRow) {
        $userRow['is_admin']    = isset($userRow['is_admin']) ? (bool)$userRow['is_admin'] : false;
        $userRow['is_active']   = isset($userRow['is_active']) ? (bool)$userRow['is_active'] : false;
        $userRow['is_approved'] = isset($userRow['is_approved']) ? (bool)$userRow['is_approved'] : false;
        $userRow['saldo_hoje']  = isset($userRow['saldo_hoje']) ? (float)$userRow['saldo_hoje'] : 0.00;
        $userRow['total_ganho_acumulado'] = isset($userRow['total_ganho_acumulado']) ? (float)$userRow['total_ganho_acumulado'] : 0.00;
        $userRow['login_count'] = isset($userRow['login_count']) ? (int)$userRow['login_count'] : 0;
    }
    unset($userRow);

    $response['success'] = true;
    $response['message'] = 'Lista de usuários carregada com sucesso.';
    $response['users'] = $users;
    $response['pagination'] = [
        'current_page'   => $page,
        'items_per_page' => $limit,
        'total_items'    => $totalItems,
        'total_pages'    => $totalPages
    ];

} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados ao processar a solicitação de usuários.';
    if (!isset($response['debug_code'])) { $response['debug_code'] = 'PDO_ERROR'; }
    http_response_code(500);
    error_log("[get_users_list.php] PDOException: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode() . " | Query (approx): " . ($dataSql ?? $countSql ?? "N/A"));
} catch (Throwable $t) {
    $response['message'] = 'Ocorreu um erro geral no servidor ao processar a lista de usuários.';
    if (!isset($response['debug_code'])) { $response['debug_code'] = 'GENERAL_ERROR'; }
    http_response_code(500);
    error_log("[get_users_list.php] Throwable: " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine());
} finally {
    $accidentalOutput = ob_get_clean();
    if (!empty($accidentalOutput)) {
        error_log("[get_users_list.php] WARNING: Accidental output detected and cleaned before JSON response: " . substr(trim($accidentalOutput), 0, 200) . "...");
        if ($response['success'] === true && $response['message'] === 'Lista de usuários carregada com sucesso.') {
            // Keep success true
        } elseif ($response['message'] === 'Erro desconhecido ao processar a solicitação da lista de usuários.') {
            $response['message'] = 'Ocorreu um erro no servidor (output inesperado). Verifique os logs.';
            if ($response['success'] !== false) http_response_code(500);
            $response['success'] = false;
        }
    }
    echo json_encode($response);
    exit;
}
?>