<?php
// /admin/api/realtime_feed.php (ATUALIZADO)

// --- Configurações SSE e Headers ---
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Content-Encoding: none');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) ob_end_flush();

// --- Dependências e Autenticação ---
if (session_status() === PHP_SESSION_NONE) session_start();

$base_dir = '/home/u689348922/domains/applovechat.com/public_html/'; // AJUSTE SE NECESSÁRIO
$db_path = $base_dir . 'includes/db.php';
$log_path = $base_dir . 'logs/admin_api_errors.log';
ini_set('error_log', $log_path);

// Função de erro SSE
function sendSseError($message, $code = 500) {
    $errorData = json_encode(['error' => $message, 'code' => $code], JSON_UNESCAPED_UNICODE);
    echo "event: error\ndata: $errorData\n\n";
    flush();
    error_log("SSE Error Sent: " . $message);
    exit();
}

if (!file_exists($db_path)) sendSseError("Config DB ausente.", 500);
require_once $db_path;
if (!isset($pdo)) sendSseError("Falha DB.", 500);
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) sendSseError("Acesso negado.", 403);
$adminUserId = (int)$_SESSION['user_id'];
session_write_close(); // Libera sessão

set_time_limit(0); ignore_user_abort(true);

$lastEventId = isset($_GET['lastEventId']) ? (int)$_GET['lastEventId'] : 0;
error_log("SSE Feed Started for Admin ID: $adminUserId, LastEventId: $lastEventId - Filtered");

// *** TIPOS DE EVENTOS PERMITIDOS NO FEED ***
$allowedEventTypes = ['pix_payment_reported', 'call_request', 'proof_upload'];
// Cria placeholders para a cláusula IN (?, ?, ?)
$placeholders = implode(',', array_fill(0, count($allowedEventTypes), '?'));

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    while (true) {
        if (connection_aborted()) { error_log("SSE Feed Aborted by Client: Admin ID $adminUserId"); break; }

        // *** CONSULTA ATUALIZADA COM FILTRO E TELEFONE PESSOAL ***
        $sql = "
            SELECT fe.*, u.nome as user_nome, u.avatar as user_avatar, u.telefone as user_telefone_pessoal
            FROM admin_events_feed fe
            LEFT JOIN usuarios u ON fe.user_id = u.id
            WHERE fe.id > ? AND fe.event_type IN ($placeholders) -- <<< FILTRO APLICADO
            ORDER BY fe.id ASC
            LIMIT 50
        ";
        $stmt = $pdo->prepare($sql);
        // Bind do lastEventId e dos tipos de evento
        $params = array_merge([$lastEventId], $allowedEventTypes);
        $stmt->execute($params);
        $newEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($newEvents) {
            foreach ($newEvents as $event) {
                if (empty($event['user_avatar'])) $event['user_avatar'] = 'default.jpg';
                // Não precisa adicionar user_avatar_url aqui, o JS pode fazer isso
                $jsonData = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
                if ($jsonData === false) { error_log("SSE JSON Encode Error: Evt " . $event['id'] . " Err: " . json_last_error_msg()); continue; }
                echo "event: feed_update\n";
                echo "id: " . $event['id'] . "\n";
                echo "data: " . $jsonData . "\n\n";
                flush();
                $lastEventId = $event['id'];
                error_log("SSE Sent (Filtered): Evt {$event['id']} (Type: {$event['event_type']}) to Admin {$adminUserId}");
            }
        } else {
            echo ": heartbeat ". time() ."\n\n"; flush();
        }
        sleep(3); // Aumentar um pouco o intervalo pode ajudar

    } // Fim while

} catch (PDOException $e) { sendSseError("Erro DB SSE: " . $e->getMessage(), 500);
} catch (Throwable $t) { sendSseError("Erro Servidor SSE: " . $t->getMessage(), 500); }

error_log("SSE Feed Closed Gracefully: Admin ID $adminUserId");
?>