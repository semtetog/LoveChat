<?php
// /admin/api/delete_user.php - MODIFICADO PARA INATIVAR

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// Caminho absoluto (AJUSTE SE NECESSÁRIO)
$base_dir = '/home/u689348922/domains/applovechat.com/public_html/';
$db_path = $base_dir . 'includes/db.php';
$log_path = $base_dir . 'logs/admin_api_errors.log';

ini_set('error_log', $log_path);

// Função de resposta JSON
if (!function_exists('json_response')) { function json_response($data = null, $http_code = 200, $is_error = false, $message = null) { http_response_code($http_code); $response = ['success' => !$is_error]; if ($message) $response['message'] = $message; if ($data !== null) $response['data'] = $data; echo json_encode($response, JSON_UNESCAPED_UNICODE); exit(); }}

// Incluir DB e verificar conexão
if (!file_exists($db_path)) json_response(null, 500, true, "Configuração interna ausente (DB).");
require_once $db_path;
if (!isset($pdo)) json_response(null, 500, true, "Falha na conexão DB.");

// Verificar autenticação e admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    json_response(null, 403, true, "Acesso negado.");
}
$adminUserId = (int)$_SESSION['user_id'];

// Usar método DELETE ainda é semanticamente ok para "remover" o acesso
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    json_response(null, 405, true, 'Método não permitido.');
}

// Obter ID da URL
$userIdToInactivate = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userIdToInactivate || $userIdToInactivate <= 0) {
    json_response(null, 400, true, 'ID de usuário inválido ou ausente.');
}

// Impedir auto-inativação
if ($userIdToInactivate === $adminUserId) {
    json_response(null, 403, true, 'Você não pode desativar sua própria conta por aqui.');
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Busca email para log ANTES de inativar
    $stmt_get = $pdo->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt_get->execute([$userIdToInactivate]);
    $userEmail = $stmt_get->fetchColumn();

    // Modifica o email para liberar (opcional, mas recomendado)
    // Adiciona um timestamp para garantir unicidade se o email for reusado
    $deletedSuffix = '_inactive_' . time();
    $newEmail = $userEmail ? substr($userEmail, 0, 255 - strlen($deletedSuffix)) . $deletedSuffix : 'deleted_' . $userIdToInactivate . $deletedSuffix;

    // **ALTERAÇÃO PRINCIPAL: UPDATE em vez de DELETE**
    $stmt_inactivate = $pdo->prepare("
        UPDATE usuarios SET
            is_active = 0,
            email = :new_email -- Modifica o email para liberar o original
            -- , deleted_at = NOW() -- Opcional: Adicionar coluna deleted_at
        WHERE id = :id AND is_active = 1 -- Só inativa se estiver ativo
    ");
    $stmt_inactivate->execute([
        ':new_email' => $newEmail,
        ':id' => $userIdToInactivate
        ]);

    if ($stmt_inactivate->rowCount() > 0) {
        error_log("Admin $adminUserId INACTIVATED user #$userIdToInactivate (Old Email: $userEmail)");

        // Opcional: Logar a inativação no feed do admin?
        // try {
        //     $eventType = 'user_inactivated';
        //     $eventData = json_encode(['reason' => 'Admin Action', 'admin_id' => $adminUserId]);
        //     $stmt_feed = $pdo->prepare("INSERT INTO admin_events_feed (event_type, user_id, event_data, created_at) VALUES (:type, :uid, :data, NOW())");
        //     $stmt_feed->execute([':type' => $eventType, ':uid' => $userIdToInactivate, ':data' => $eventData]);
        // } catch (PDOException $e) { error_log("AdminFeedLog Error(inactivate): " . $e->getMessage()); }

        json_response(['id' => $userIdToInactivate], 200, false, 'Usuário desativado com sucesso.');
    } else {
        // Verifica se o usuário existe mas já estava inativo
         $stmt_exists = $pdo->prepare("SELECT is_active FROM usuarios WHERE id = ?");
         $stmt_exists->execute([$userIdToInactivate]);
         $currentUserStatus = $stmt_exists->fetchColumn();
         if ($currentUserStatus === false) { // Não encontrado
            json_response(null, 404, true, 'Usuário não encontrado.');
         } elseif ($currentUserStatus == 0) { // Já inativo
             json_response(['id' => $userIdToInactivate], 200, false, 'Usuário já estava inativo.');
         } else { // Outro erro
              json_response(null, 500, true, 'Falha ao desativar o usuário.');
         }
    }

} catch (PDOException $e) {
    error_log("DB Error inactivating user (#{$userIdToInactivate}): " . $e->getMessage());
    json_response(['error_code' => $e->getCode()], 500, true, 'Erro no banco de dados ao desativar usuário.');
} catch (Throwable $t) {
     error_log("General Error inactivating user (#{$userIdToInactivate}): " . $t->getMessage());
     json_response(null, 500, true, 'Erro inesperado no servidor.');
}
?>