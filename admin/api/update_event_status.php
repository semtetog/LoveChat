<?php
// /admin/api/update_event_status.php (COMPLETO v7 - CORRIGIDO: Atualiza flags/datas E coluna whatsapp_status)

ob_start();
header('Content-Type: application/json; charset=utf-8');

// --- Configurações Essenciais ---
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$base_dir = dirname(__DIR__, 2);
$log_path = $base_dir . '/logs/admin_api_errors.log';
if (!is_dir(dirname($log_path))) { @mkdir(dirname($log_path), 0755, true); }
if (is_writable(dirname($log_path))) { ini_set('error_log', $log_path); }
else { error_log("WARNING: [upd_evt_stat] Custom log dir not writable."); ini_set('error_log', ''); }
// --- Fim Configurações Essenciais ---

// --- Funções Auxiliares Padronizadas ---
if (!function_exists('admin_api_log')) {
    function admin_api_log($message, $data = [], $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $adminId = $_SESSION['user_id'] ?? 'NoSession/Unknown';
        $logLevel = strtoupper($level);
        $logMessage = "[{$timestamp}] [Admin:{$adminId}] [upd_evt_stat] [{$logLevel}] {$message}";
        if (!empty($data)) {
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $logMessage .= ($jsonData === false) ? " - Dados: [Erro JSON Log: " . json_last_error_msg() . "]" : " - Dados: " . $jsonData;
        }
        $logFilePath = ini_get('error_log');
        if (!empty($logFilePath) && is_writable(dirname($logFilePath))) {
            @error_log($logMessage . PHP_EOL, 3, $logFilePath);
        } else {
            @error_log($logMessage);
        }
    }
}
if (!function_exists('json_response')) {
    function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
        $output = ob_get_clean();
        if (!$is_error && trim($output) !== '') {
             admin_api_log("Output inesperado antes do JSON!", ['output_preview' => substr(trim($output), 0, 200)], 'error');
             if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
             echo json_encode(['success' => false, 'message' => 'Erro interno (Output inesperado).'], JSON_UNESCAPED_UNICODE);
             exit();
        } elseif ($is_error && trim($output) !== '') {
             admin_api_log("Output acidental durante erro", ['output_preview' => substr(trim($output), 0, 200)], 'warning');
             $message = ($message ? $message . " | " : "") . "Output residual detectado.";
        }
        if (!headers_sent()) { http_response_code($http_code); header('Content-Type: application/json; charset=utf-8');
        } else { admin_api_log("Headers já enviados ao tentar enviar json_response.", ['http_code' => $http_code, 'message' => $message], 'warning'); }
        $response = ['success' => !$is_error];
        if ($message !== null) { $response['message'] = $message; }
        if (!$is_error && $data !== null) { $response['data'] = $data;
        } elseif ($is_error && is_array($data)) { $response = array_merge($response, $data); }
        $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_IGNORE);
        if ($jsonOutput === false) {
            admin_api_log("JSON Encode Error", ['error_msg' => json_last_error_msg(), 'data_type' => gettype($response)], 'error');
            if (!headers_sent()) { http_response_code(500); }
            echo '{"success":false,"message":"Erro interno (JSON Encode)."}';
        } else { echo $jsonOutput; }
        exit();
    }
}
// --- Fim Funções Auxiliares ---

$id_recebido_log = null; $eventType_log = null; $newStatus_log = null;

try {
    // --- Autenticação e Conexão DB ---
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        admin_api_log("Acesso não autorizado negado.", ['session_user' => $_SESSION['user_id'] ?? 'N/A', 'is_admin' => $_SESSION['is_admin'] ?? 'N/A'], 'warning');
        json_response(null, 403, true, "Acesso não autorizado.");
    }
    $adminUserId = (int)$_SESSION['user_id'];

    $db_path = $base_dir . '/includes/db.php';
    if (!file_exists($db_path)) { admin_api_log("Arquivo de configuração DB não encontrado.", ['path' => $db_path], 'error'); json_response(null, 500, true, "Erro crítico: Configuração do banco de dados ausente."); }
    require_once $db_path;
    if (!isset($pdo) || !($pdo instanceof PDO)) { admin_api_log("Objeto PDO não foi estabelecido corretamente.", [], 'error'); json_response(null, 500, true, "Erro crítico: Falha na conexão com o banco de dados."); }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->exec("SET NAMES 'utf8mb4'");
    admin_api_log("Conexão DB e autenticação OK.");
    // --- Fim Autenticação e Conexão DB ---

    // --- Validação Método e Input ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { admin_api_log("Método HTTP inválido recebido.", ['method' => $_SERVER['REQUEST_METHOD']], 'warning'); json_response(null, 405, true, 'Método não permitido. Use POST.'); }
    $inputJSON = file_get_contents('php://input'); $input = json_decode($inputJSON, true);
    if (json_last_error() !== JSON_ERROR_NONE) { admin_api_log("Erro ao decodificar JSON.", ['json_error' => json_last_error_msg(), 'raw_input' => substr($inputJSON, 0, 200)], 'error'); json_response(null, 400, true, 'JSON inválido na requisição: ' . json_last_error_msg()); }
    $id_recebido = filter_var($input['event_id'] ?? $input['user_id'] ?? null, FILTER_VALIDATE_INT);
    $newStatusEnglish = isset($input['new_status']) ? trim(strip_tags($input['new_status'])) : null;
    $eventType = isset($input['event_type']) ? trim(strip_tags($input['event_type'])) : null;
    $whatsappNumber = isset($input['whatsapp_number']) ? preg_replace('/[^\d+]/', '', $input['whatsapp_number']) : null;
    $id_recebido_log = $id_recebido; $eventType_log = $eventType; $newStatus_log = $newStatusEnglish;
    admin_api_log("Requisição recebida e parseada", ['admin_id' => $adminUserId, 'input' => $input, 'parsed_id' => $id_recebido, 'parsed_status' => $newStatusEnglish, 'parsed_type' => $eventType]);
    if ($id_recebido === false || $id_recebido <= 0 || empty($eventType) || $newStatusEnglish === null || $newStatusEnglish === '') { admin_api_log("Dados obrigatórios ausentes ou inválidos.", ['id' => $id_recebido, 'type' => $eventType, 'status' => $newStatusEnglish], 'error'); json_response(null, 400, true, 'Dados incompletos ou inválidos: ID, tipo de evento e novo status são obrigatórios.'); }
    // --- Fim Validação Método e Input ---

    // --- Mapeamento de Status e Validação Específica ---
    $statusPortugueseMap = ['pending' => 'pendente', 'in_progress' => 'em_andamento', 'completed' => 'concluida', 'cancelled' => 'cancelada', 'approved' => 'aprovado', 'rejected' => 'rejeitado', 'pending_review' => 'pendente'];
    $needsPortugueseMapping = in_array($eventType, ['call_request', 'proof_upload']);
    $primaryTableStatus = $newStatusEnglish;
    if ($needsPortugueseMapping) { if (isset($statusPortugueseMap[$newStatusEnglish])) { $primaryTableStatus = $statusPortugueseMap[$newStatusEnglish]; admin_api_log("Status mapeado p/ PT-BR ({$eventType}): '{$newStatusEnglish}' -> '{$primaryTableStatus}'", ['id' => $id_recebido]); } else { admin_api_log("ALERTA: Status '{$newStatusEnglish}' s/ mapeamento PT-BR p/ '{$eventType}'. Usando original.", ['id' => $id_recebido], 'warning'); } }
    $allowedStatusesMap = ['call_request' => ['pending', 'in_progress', 'completed', 'cancelled'], 'whatsapp_request' => ['pending', 'processing', 'aguardando_resposta', 'approved', 'rejected'], 'proof_upload' => ['pending_review', 'approved', 'rejected'], 'lead_purchase' => ['pending', 'paid', 'cancelled'], 'pix_payment_reported' => ['pending_review', 'approved', 'rejected'], 'admin_notification' => ['sent', 'read']];
    if (!isset($allowedStatusesMap[$eventType])) { admin_api_log("Tipo de evento inválido.", ['type' => $eventType], 'error'); json_response(null, 400, true, "Tipo de evento '{$eventType}' desconhecido."); }
    if (!in_array($newStatusEnglish, $allowedStatusesMap[$eventType])) { admin_api_log("Status inválido/não permitido.", ['type' => $eventType, 'status_received' => $newStatusEnglish, 'allowed' => $allowedStatusesMap[$eventType]], 'error'); json_response(['allowed_statuses' => $allowedStatusesMap[$eventType]], 400, true, "Status '{$newStatusEnglish}' inválido/não permitido para '{$eventType}'."); }
    if ($eventType === 'whatsapp_request' && $newStatusEnglish === 'approved') { if (empty($whatsappNumber)) { admin_api_log("Aprovação WA sem número.", ['id' => $id_recebido], 'error'); json_response(null, 400, true, "Número de WhatsApp obrigatório para aprovação."); } if (!preg_match('/^\+\d{10,15}$/', $whatsappNumber)) { admin_api_log("Número WA inválido.", ['id' => $id_recebido, 'number' => $whatsappNumber], 'error'); json_response(null, 400, true, "Formato do número de WhatsApp inválido (+55...)."); } }
    // --- Fim Mapeamento e Validação ---

    // --- Transação Principal ---
    $pdo->beginTransaction();
    admin_api_log("Transação iniciada p/ atualização.", ['id' => $id_recebido, 'type' => $eventType, 'status' => $newStatusEnglish]);
    $responseData = ['received_id' => $id_recebido, 'new_status' => $newStatusEnglish];

    // =============================================
    // ===== LÓGICA CONDICIONAL PRINCIPAL =========
    // =============================================
    if ($eventType === 'whatsapp_request') {
        // --- Ação é para WhatsApp: ATUALIZA TABELA USUARIOS (v4 - Usando flags/datas E coluna whatsapp_status) ---
        $userId = $id_recebido;
        admin_api_log("Tratando como atualização de WhatsApp User ID: {$userId} (v4 - flags + status col)", ['new_status' => $newStatusEnglish]);

        // Prepara parâmetros e colunas (sempre atualiza whatsapp_status)
        $params_update_user = [':user_id' => $userId, ':new_status' => $newStatusEnglish];
        $update_cols = ["whatsapp_status = :new_status"]; // *** A COLUNA DE STATUS É A BASE ***

        // Define flags/datas adicionais com base no novo status
        switch ($newStatusEnglish) {
            case 'approved':
                $update_cols[] = "whatsapp_aprovado = 1"; $update_cols[] = "whatsapp_solicitado = 1";
                $update_cols[] = "whatsapp_numero = :whatsapp_number"; $update_cols[] = "whatsapp_data_aprovacao = NOW()";
                $update_cols[] = "whatsapp_data_rejeicao = NULL"; $params_update_user[':whatsapp_number'] = $whatsappNumber;
                $responseData['whatsapp_number'] = $whatsappNumber; break;
            case 'rejected':
                $update_cols[] = "whatsapp_aprovado = 0"; $update_cols[] = "whatsapp_solicitado = 0";
                $update_cols[] = "whatsapp_numero = NULL"; $update_cols[] = "whatsapp_data_rejeicao = NOW()";
                $update_cols[] = "whatsapp_data_aprovacao = NULL"; break;
            case 'pending': case 'processing': case 'aguardando_resposta':
                 $update_cols[] = "whatsapp_aprovado = 0"; $update_cols[] = "whatsapp_solicitado = 1";
                 $update_cols[] = "whatsapp_data_solicitacao = COALESCE(whatsapp_data_solicitacao, NOW())";
                 $update_cols[] = "whatsapp_data_aprovacao = NULL"; $update_cols[] = "whatsapp_data_rejeicao = NULL";
                break;
            default: throw new Exception("Status WhatsApp ('{$newStatusEnglish}') inválido na lógica de atualização v4.");
        }

        // Constrói e executa a query SQL
        $sql_update_user = "UPDATE usuarios SET " . implode(', ', $update_cols) . " WHERE id = :user_id";
        admin_api_log("Preparando SQL WA v4", ['sql' => $sql_update_user, 'params' => array_keys($params_update_user)]);
        $stmt_update = $pdo->prepare($sql_update_user);
        $success = $stmt_update->execute($params_update_user);

        if (!$success) { $errorInfo = $stmt_update->errorInfo(); admin_api_log("Falha SQL WA v4.", ['user' => $userId, 'pdo_err' => $errorInfo], 'error'); throw new PDOException("Falha SQL WA v4 ID {$userId}. SQLSTATE[{$errorInfo[0]}]: {$errorInfo[2]}"); }
        $rowCount = $stmt_update->rowCount();
        admin_api_log("Update Usuário WA v4 OK.", ['user' => $userId, 'status' => $newStatusEnglish, 'rows' => $rowCount]);
        if ($rowCount === 0) { admin_api_log("Nenhuma linha afetada WA v4 (status já era o mesmo?).", ['user' => $userId, 'status' => $newStatusEnglish], 'warning'); }

        // Sincronização do Feed (Opcional - Mantido Comentado)
        admin_api_log("Sincronização feed WA desabilitada.", ['user' => $userId], 'info');

    } else { // Início da lógica para OUTROS tipos de evento (MANTIDA IGUAL)
        // --- Ação é para Outro Tipo de Evento: ATUALIZA TABELA admin_events_feed E OUTRAS ---
        $eventId = $id_recebido;
        admin_api_log("Tratando Evento Feed ID: {$eventId}", ['type' => $eventType, 'status' => $newStatusEnglish]);

        // 1. Atualiza status no feed (inglês)
        $stmt_feed = $pdo->prepare("UPDATE admin_events_feed SET status = :status WHERE id = :id AND event_type = :event_type");
        $stmt_feed->execute([':status' => $newStatusEnglish, ':id' => $eventId, ':event_type' => $eventType]);
        $feedRowsAffected = $stmt_feed->rowCount();
        if ($feedRowsAffected === 0) { $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM admin_events_feed WHERE id = :id AND event_type = :event_type"); $stmt_check->execute([':id' => $eventId, ':event_type' => $eventType]); if ($stmt_check->fetchColumn() == 0) { admin_api_log("Evento feed #{$eventId}/'{$eventType}' não encontrado.", [], 'error'); throw new Exception("Evento feed (#{$eventId}) não encontrado ou tipo ('{$eventType}') incompatível."); } else { admin_api_log("Nenhuma linha feed atualizada (status mesmo?).", ['evt' => $eventId, 'sts' => $newStatusEnglish], 'warning'); } }
        else { admin_api_log("Status feed atualizado.", ['evt' => $eventId, 'sts' => $newStatusEnglish]); }

        // 2. Pega UserID e RelatedID
        $stmt_details = $pdo->prepare("SELECT user_id, related_id FROM admin_events_feed WHERE id = :id LIMIT 1");
        $stmt_details->execute([':id' => $eventId]); $eventDetails = $stmt_details->fetch(PDO::FETCH_ASSOC);
        if (!$eventDetails) { admin_api_log("AVISO: Detalhes evento #{$eventId} não encontrados pós-update.", [], 'warning'); }
        $affectedUserId = $eventDetails['user_id'] ?? null; $relatedId = $eventDetails['related_id'] ?? null;
        $responseData['user_id'] = $affectedUserId;

        // 3. Lógica Adicional por tipo (Mantida)
        if ($eventType === 'call_request') {
             if ($relatedId) { $validPT = ['pendente', 'em_andamento', 'concluida', 'cancelada']; if (in_array($primaryTableStatus, $validPT)) { $st = $pdo->prepare("UPDATE chamadas SET status = :sts WHERE id = :id"); $st->execute([':sts' => $primaryTableStatus, ':id' => $relatedId]); admin_api_log("Tabela 'chamadas' atualizada.", ['call' => $relatedId, 'sts_pt' => $primaryTableStatus, 'rows' => $st->rowCount()]); } else { admin_api_log("Status PT '{$primaryTableStatus}' inválido p/ 'chamadas'. Ignorado.", ['evt' => $eventId], 'error'); } }
             else { admin_api_log("related_id (call_id) nulo p/ #{$eventId}. 'chamadas' não atualizada.", [], 'warning'); }
        } elseif ($eventType === 'proof_upload') {
            if ($relatedId && $affectedUserId) { $validPT = ['aprovado', 'rejeitado', 'pendente']; if (in_array($primaryTableStatus, $validPT)) { $stP = $pdo->prepare("UPDATE comprovantes SET status = :sts WHERE id = :id AND usuario_id = :uid"); $stP->execute([':sts' => $primaryTableStatus, ':id' => $relatedId, ':uid' => $affectedUserId]); admin_api_log("Tabela 'comprovantes' atualizada.", ['proof' => $relatedId, 'sts_pt' => $primaryTableStatus, 'rows' => $stP->rowCount()]); if ($primaryTableStatus === 'aprovado') { $stV = $pdo->prepare("SELECT valor FROM comprovantes WHERE id = :id AND usuario_id = :uid LIMIT 1"); $stV->execute([':id' => $relatedId, ':uid' => $affectedUserId]); $vB = $stV->fetchColumn(); if ($vB !== false && is_numeric($vB) && ($vB = (float)$vB) > 0) { $tax = 0.10; $vL = round($vB * (1 - $tax), 2); if ($vL > 0) { $stG = $pdo->prepare("UPDATE usuarios SET total_ganho_acumulado = COALESCE(total_ganho_acumulado, 0) + :vl WHERE id = :uid"); $stG->execute([':vl' => $vL, ':uid' => $affectedUserId]); admin_api_log("Ganhos acumulados atualizados.", ['user' => $affectedUserId, 'vl_add' => $vL, 'rows' => $stG->rowCount()]); } else { admin_api_log("Valor líquido zerado/negativo #{$relatedId}.", ['vb'=>$vB], 'warning'); } } else { admin_api_log("Valor bruto inválido #{$relatedId}.", ['vb_raw' => $vB], 'warning'); } } elseif ($primaryTableStatus === 'rejeitado') { admin_api_log("Comprovante #{$relatedId} rejeitado. Estorno NÃO implementado.", [], 'info'); } } else { admin_api_log("Status PT '{$primaryTableStatus}' inválido p/ 'comprovantes'. Ignorado.", ['evt' => $eventId], 'error'); } }
            else { admin_api_log("related_id ou user_id nulo p/ proof_upload #{$eventId}.", [], 'warning'); }
        } elseif ($eventType === 'pix_payment_reported') {
             admin_api_log("Status 'pix_payment_reported' atualizado (apenas feed).", ['evt' => $eventId]);
        }
        // ... (outros elseifs originais) ...
    }
    // =============================================
    // ===== FIM DA LÓGICA CONDICIONAL ===========
    // =============================================

    // --- Finalizar Transação ---
    $pdo->commit();
    admin_api_log("Transação commitada.", ['id' => $id_recebido, 'type' => $eventType, 'status' => $newStatusEnglish]);
    json_response($responseData, 200, false, 'Status atualizado com sucesso.');

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); admin_api_log("Rollback PDOException.", [], 'warning'); }
    admin_api_log("Erro PDO", ['id' => $id_recebido_log, 'type' => $eventType_log, 'sts' => $newStatus_log, 'err' => $e->getMessage(), 'code' => $e->getCode()], 'error');
    json_response(['error_code' => $e->getCode()], 500, true, 'Erro DB: ' . $e->getMessage());
} catch (Exception $e) {
     if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); admin_api_log("Rollback Exception.", [], 'warning'); }
     admin_api_log("Erro Exception", ['id' => $id_recebido_log, 'type' => $eventType_log, 'sts' => $newStatus_log, 'err' => $e->getMessage()], 'error');
     $httpCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
     json_response(null, $httpCode, true, $e->getMessage());
} catch (Throwable $t) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); admin_api_log("Rollback Throwable.", [], 'warning'); }
     admin_api_log("Erro Fatal/Throwable", ['id' => $id_recebido_log, 'type' => $eventType_log, 'sts' => $newStatus_log, 'err' => $t->getMessage(), 'file' => $t->getFile(), 'line' => $t->getLine()], 'error');
     json_response(null, 500, true, 'Erro interno grave. Consulte os logs.');
} finally {
    // $pdo = null;
}
?>