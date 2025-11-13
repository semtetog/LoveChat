<?php
// /admin/api/approve_user.php

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
// ASSUMA que este arquivo (approve_user.php) está em 'admin/api/'
// E que as pastas 'includes/' e 'logs/' estão na RAIZ do seu projeto.
// Se sua estrutura for:
// raiz_do_projeto/
//   admin/
//     api/
//       approve_user.php  <-- ESTE ARQUIVO
//   includes/
//     db.php
//   logs/
// Então dirname(__DIR__, 2) está correto.
$base_dir = dirname(__DIR__, 2);

// --- Configuração de Logs ---
$api_log_file_path = $base_dir . '/logs/api_approve_user_errors.log'; // Log específico
if (!is_dir(dirname($api_log_file_path))) {
    @mkdir(dirname($api_log_file_path), 0755, true);
}
if (is_writable(dirname($api_log_file_path)) || (is_file($api_log_file_path) && is_writable($api_log_file_path)) || (!is_file($api_log_file_path) && is_writable(dirname($api_log_file_path))) ) {
    ini_set('error_log', $api_log_file_path);
} else {
    error_log("WARNING: [approve_user.php] Custom log directory '{$api_log_file_path}' or its parent is not writable. PHP errors will go to the default server error log.");
}
// --- Fim Configuração de Logs ---

// Resposta padrão
$response = ['success' => false, 'message' => 'Erro desconhecido ao aprovar usuário.'];
error_log("--- [approve_user.php] API Call START ---");

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Autenticação do Administrador
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $response['message'] = 'Acesso não autorizado. Você precisa ser um administrador.';
        http_response_code(403);
        error_log("[approve_user.php] Auth_Failed. AdminID: ".($_SESSION['user_id'] ?? 'N/A'));
        throw new Exception("Auth_Failed");
    }
    $admin_approver_id = (int)$_SESSION['user_id'];
    error_log("[approve_user.php] Admin Authenticated. AdminID: {$admin_approver_id}");

    // 2. Inclusão e Verificação da Conexão com o Banco de Dados
    $db_path_check = $base_dir . '/includes/db.php';
    if (!file_exists($db_path_check)) {
        $response['message'] = 'Erro crítico: Arquivo de configuração do banco de dados (db.php) não encontrado.';
        http_response_code(500);
        error_log("[approve_user.php] CRITICAL: db.php not found at expected path: " . $db_path_check);
        throw new Exception("DB_Config_Missing");
    }
    require_once $db_path_check;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $response['message'] = 'Erro crítico: Falha na conexão com o banco de dados (objeto PDO não estabelecido).';
        http_response_code(500);
        error_log("[approve_user.php] CRITICAL: PDO object not available from db.php.");
        throw new Exception("DB_PDO_Missing");
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
    error_log("[approve_user.php] DB Connection OK.");

    // 3. Verificar Método da Requisição
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Método de requisição inválido. Use POST.';
        http_response_code(405);
        error_log("[approve_user.php] Invalid HTTP Method: ".$_SERVER['REQUEST_METHOD']);
        throw new Exception("Invalid_Method");
    }

    // 4. Obter e Validar Dados de Entrada (JSON)
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Dados de entrada inválidos (JSON malformado).';
        http_response_code(400);
        error_log("[approve_user.php] Invalid JSON input. Error: " . json_last_error_msg() . " Raw input: " . $inputJSON);
        throw new Exception("Invalid_JSON");
    }
    error_log("[approve_user.php] Input received: " . print_r($input, true));

    $user_id_to_approve = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$user_id_to_approve || $user_id_to_approve <= 0) {
        $response['message'] = 'ID de usuário para aprovação é inválido ou não fornecido.';
        http_response_code(400);
        error_log("[approve_user.php] Invalid user_id in input: " . ($input['user_id'] ?? 'NULL'));
        throw new Exception("Invalid_UserID");
    }
    error_log("[approve_user.php] User ID to approve: {$user_id_to_approve}");

    // 5. Lógica Principal: Atualizar Usuário no Banco
    $pdo->beginTransaction();
    error_log("[approve_user.php] Transaction started for UserID: {$user_id_to_approve}.");

    // Verifica o status atual ANTES de tentar atualizar
    $checkStmt = $pdo->prepare("SELECT is_approved, is_active, nome FROM usuarios WHERE id = :user_id FOR UPDATE"); // FOR UPDATE para bloqueio de linha
    $checkStmt->execute([':user_id' => $user_id_to_approve]);
    $currentUserData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUserData) {
        $response['message'] = "Usuário com ID {$user_id_to_approve} não encontrado para aprovação.";
        http_response_code(404);
        error_log("[approve_user.php] UserID {$user_id_to_approve} not found in DB before update.");
        $pdo->rollBack();
        throw new Exception("User_Not_Found_For_Approval");
    }
    $userNameForLog = $currentUserData['nome'] ?? 'Desconhecido';
    error_log("[approve_user.php] UserID {$user_id_to_approve} (Nome: {$userNameForLog}) current status - is_approved: {$currentUserData['is_approved']}, is_active: {$currentUserData['is_active']}");

    if ((int)$currentUserData['is_approved'] === 1) {
        $response['success'] = true;
        $response['message'] = "Usuário ID {$user_id_to_approve} (Nome: {$userNameForLog}) já estava aprovado.";
        $response['already_approved'] = true;
        error_log("[approve_user.php] UserID {$user_id_to_approve} was already approved. Action by AdminID {$admin_approver_id}.");
        $pdo->commit(); // Commita a transação pois a verificação foi feita
    } else {
        // Se não estava aprovado, então aprova
        $sql = "UPDATE usuarios SET
                    is_approved = 1,
                    is_active = 1,       -- Garante que o usuário seja ativado ao ser aprovado
                    approved_at = NOW(),
                    admin_approver_id = :admin_id
                WHERE id = :user_id AND is_approved = 0"; // Condição extra para segurança (aprovar apenas se ainda não aprovado)

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':admin_id', $admin_approver_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id_to_approve, PDO::PARAM_INT);
        $stmt->execute();

        $rowCount = $stmt->rowCount();
        error_log("[approve_user.php] UPDATE query executed for UserID {$user_id_to_approve}. Rows affected: {$rowCount}");

        if ($rowCount > 0) {
            $response['success'] = true;
            $response['message'] = "Usuário ID {$user_id_to_approve} (Nome: {$userNameForLog}) aprovado com sucesso!";
            error_log("[approve_user.php] UserID {$user_id_to_approve} successfully approved by AdminID {$admin_approver_id}.");

            // Log no feed do admin
            try {
                $stmt_feed = $pdo->prepare("INSERT INTO admin_events_feed (event_type, user_id, related_id, event_data, created_at) VALUES (:event_type, :user_id, :related_id, :event_data, NOW())");
                $stmt_feed->execute([
                    ':event_type'       => 'user_approved',
                    ':user_id'          => $user_id_to_approve,
                    ':related_id'       => $admin_approver_id,
                    ':event_data'       => json_encode(['approved_by_admin_id' => $admin_approver_id, 'approved_user_name' => $userNameForLog])
                ]);
                error_log("[approve_user.php] Admin feed event 'user_approved' logged for UserID {$user_id_to_approve}.");
            } catch (PDOException $feed_e) {
                error_log("[approve_user.php] FAILED to log admin_events_feed for UserID {$user_id_to_approve}: " . $feed_e->getMessage());
            }
            $pdo->commit();
            error_log("[approve_user.php] Transaction committed for UserID {$user_id_to_approve}.");
        } else {
            // Se nenhuma linha foi afetada, pode ser que o usuário foi aprovado por outro admin entre o SELECT e o UPDATE,
            // ou o usuário não existe mais (apesar da checagem anterior).
            $response['message'] = 'Nenhuma alteração realizada. O usuário pode já ter sido aprovado ou não foi encontrado no momento da atualização final.';
            error_log("[approve_user.php] UNEXPECTED or CONCURRENT_UPDATE: No rows affected by UPDATE for UserID {$user_id_to_approve} by AdminID {$admin_approver_id}.");
            $pdo->rollBack(); // Reverte se nenhuma linha foi afetada inesperadamente
            // Não lançar exceção aqui, pois pode ser um caso de concorrência onde o estado já mudou.
            // A resposta já indica que nenhuma alteração foi feita.
        }
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); error_log("[approve_user.php] Transaction rolled back due to PDOException."); }
    $response['message'] = 'Erro de banco de dados ao tentar aprovar usuário.';
    if(!isset($response['debug_code'])) $response['debug_code'] = 'API_PDO_ERROR';
    http_response_code(500);
    error_log("[approve_user.php] PDOException for UserID " . ($user_id_to_approve ?? 'N/A') . ": " . $e->getMessage() . " | SQLSTATE: " . $e->getCode());
} catch (Throwable $t) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); error_log("[approve_user.php] Transaction rolled back due to Throwable."); }
    $response['message'] = 'Erro geral no servidor ao tentar aprovar usuário.';
     if(!isset($response['debug_code'])) $response['debug_code'] = 'API_GENERAL_ERROR';
    http_response_code(500);
    error_log("[approve_user.php] Throwable for UserID " . ($user_id_to_approve ?? 'N/A') . ": " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine());
} finally {
    $accidentalOutput = ob_get_clean();
    if (!empty($accidentalOutput)) {
        error_log("[approve_user.php] WARNING: Accidental output detected and cleaned before JSON response: " . substr(trim($accidentalOutput), 0, 200) . "...");
        if ($response['success'] === false && $response['message'] === 'Erro desconhecido ao aprovar usuário.') {
             $response['message'] = 'Erro no servidor (output inesperado). Verifique os logs.';
             // Garante que o código de status seja de erro se ainda não for.
             if (http_response_code() === 200 && !$response['success']) {
                 http_response_code(500);
             }
        }
    }
    // Certifique-se de que Content-Type ainda é application/json, pois o ob_clean pode ter removido.
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($response);
    exit;
}
?>