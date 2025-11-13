<?php
// /api/realtime_updates.php (Corrigido e Otimizado para Atualização de Badge)

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@session_cache_limiter('nocache'); // Garante que o cache da sessão não interfira

define('ENVIRONMENT', 'production'); // Mude para 'development' para mais logs
define('MAIN_LOOP_SLEEP', 2); // Segundos entre checagens gerais
define('HEARTBEAT_INTERVAL', 20); // Segundos entre heartbeats (mantém conexão viva)
define('NOTIFICATION_CHECK_INTERVAL', 10); // Segundos entre checagens de *notificações* (mais frequente)
define('MAX_EXECUTION_TIME_SECONDS', 360); // Aumentado para 6 minutos

// --- Headers SSE ---
header('Content-Encoding: none');
header('Content-Type: text/event-stream; charset=utf-8'); // Adicionado charset=utf-8
header('Cache-Control: no-cache, no-store, must-revalidate, private'); // Adicionado private
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // Essencial para Nginx

// --- Controle de Origem CORS ---
$allowedOrigins = [
    'https://applovechat.com',
    'https://www.applovechat.com'
    // Adicione 'http://localhost:xxxx' ou seu ambiente de dev aqui se necessário
];
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
$requestReferer = $_SERVER['HTTP_REFERER'] ?? null;
$originAllowed = false;
$originToSend = null;

// Verifica Origin diretamente
if ($requestOrigin !== null && in_array($requestOrigin, $allowedOrigins)) {
    $originAllowed = true;
    $originToSend = $requestOrigin;
}
// Fallback para Referer (menos seguro, mas útil para acesso direto ou alguns cenários)
elseif ($requestOrigin === null && $requestReferer !== null) {
    $refererParts = parse_url($requestReferer);
    if (isset($refererParts['scheme'], $refererParts['host'])) {
        $refererOrigin = $refererParts['scheme'] . '://' . $refererParts['host'];
        // Adiciona porta se não for padrão
        if (isset($refererParts['port']) && (($refererParts['scheme'] === 'http' && $refererParts['port'] !== 80) || ($refererParts['scheme'] === 'https' && $refererParts['port'] !== 443))) {
            $refererOrigin .= ':' . $refererParts['port'];
        }
        if (in_array($refererOrigin, $allowedOrigins)) {
            $originAllowed = true;
            $originToSend = $refererOrigin; // Usar o origin derivado do referer para o header
        }
    }
}

// Tratamento OPTIONS (Pré-vôo CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if ($originAllowed && $originToSend) {
        header('Access-Control-Allow-Origin: ' . $originToSend);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, OPTIONS'); // Apenas GET é necessário para SSE
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Cache-Control, If-Modified-Since'); // Headers comuns
        header('Access-Control-Max-Age: 86400'); // Cache da resposta OPTIONS por 1 dia
        header('Vary: Origin'); // Importante para caches intermediários
        http_response_code(204); // No Content
    } else {
        error_log("SSE CORS OPTIONS Request Denied. Origin: " . ($requestOrigin ?? 'null') . ", Referer: " . ($requestReferer ?? 'null'));
        http_response_code(403); // Forbidden
    }
    exit;
}

// Verificação GET (Conexão SSE)
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if ($originAllowed && $originToSend) {
        // Define os headers CORS para a requisição GET principal
        header('Access-Control-Allow-Origin: ' . $originToSend);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    } else {
        // Se a origem não for permitida, recusa a conexão SSE
        error_log("SSE CORS GET Request Denied. Origin: " . ($requestOrigin ?? 'null') . ", Referer: " . ($requestReferer ?? 'null'));
        http_response_code(403); // Forbidden
        echo "event: error\ndata: " . json_encode(["error" => "forbidden_origin_get", "message" => "Request origin not allowed."]) . "\n\n";
        flush();
        exit;
    }
} else {
    // Outros métodos não são permitidos para SSE
    http_response_code(405); // Method Not Allowed
    exit;
}
// --- Fim CORS ---


// --- Flush inicial e controle de tempo ---
while (ob_get_level() > 0) { @ob_end_flush(); } // Limpa buffers de saída existentes
@flush(); // Envia headers imediatamente
set_time_limit(MAX_EXECUTION_TIME_SECONDS + 10); // Define limite de tempo um pouco maior que o loop
ignore_user_abort(true); // Tenta continuar rodando mesmo se o cliente desconectar (para logs finais)


// --- Sessão e Autenticação ---
if (session_status() === PHP_SESSION_NONE) {
    // Configurações de cookie de sessão (ajuste conforme necessário)
    session_set_cookie_params([
        'lifetime' => 0, // Expira com o navegador
        'path' => '/',
        'domain' => '.applovechat.com', // Domínio principal para subdomínios
        'secure' => true, // Apenas HTTPS
        'httponly' => true, // Apenas HTTP(S), não acessível por JS
        'samesite' => 'Lax' // 'Lax' ou 'Strict'. 'Lax' é geralmente bom.
    ]);
    session_start();
}

// Verifica se o user_id existe na sessão
if (empty($_SESSION['user_id'])) {
    error_log("SSE Authentication Failed: No user_id in session.");
    echo "event: error\ndata: " . json_encode(["error" => "unauthorized", "message" => "User not authenticated.", "code" => 401]) . "\n\n";
    flush();
    exit;
}
$userId = (int)$_SESSION['user_id']; // Define $userId globalmente

// Valida user_id do GET contra o da sessão (segurança extra)
$requestUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($requestUserId !== $userId) {
    error_log("SSE User ID Mismatch: Session={$userId}, GET={$requestUserId}");
    echo "event: error\ndata: " . json_encode(["error" => "user_mismatch", "message" => "User ID mismatch.", "code" => 403]) . "\n\n";
    flush();
    exit;
}

// Fecha a escrita da sessão para liberar o lock o mais rápido possível
session_write_close();
// --- Fim Sessão e Autenticação ---


// --- DB e Funções ---

/**
 * Envia um evento SSE para o cliente.
 *
 * @param string $type O nome do evento.
 * @param mixed $data Os dados a serem enviados (serão codificados em JSON).
 * @param int|null $retry O tempo em milissegundos para o cliente tentar reconectar em caso de erro (opcional).
 */
function sendSseEvent($type, $data, $retry = 5000) {
    global $userId; // Usa o ID do usuário global para logging

    // Garante que os dados sejam um array ou objeto para codificação JSON
    if (!is_array($data) && !is_object($data)) {
        $data = ['value' => $data];
    }
    // Adiciona um timestamp ao evento para depuração
    $data['sse_timestamp'] = time();

    // Codifica os dados para JSON
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

    // Verifica erro na codificação JSON
    if ($jsonData === false) {
        $jsonError = json_last_error_msg();
        error_log("SSE JSON Encode Error [User:{$userId}] [Type:{$type}]: {$jsonError} - Data: " . print_r($data, true));
        // Envia um evento de erro genérico para o cliente
        echo "event: error\ndata: " . json_encode(["error" => "json_encode_failed", "message" => "Server error encoding data.", "code" => 500]) . "\n\n";
    } else {
        // Constrói a mensagem SSE
        echo "event: " . $type . "\n";
        if ($retry !== null) {
            echo "retry: " . (int)$retry . "\n";
        }
        echo "data: " . $jsonData . "\n\n"; // Linha dupla no final é crucial!

        // Log detalhado apenas em desenvolvimento
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("SSE Sent [User:{$userId}] [Type:{$type}] Data: " . $jsonData);
        }
    }

    // Força o envio dos dados para o cliente
    if (ob_get_level() > 0) { @ob_flush(); }
    @flush();
}


// Tenta conectar ao banco de dados
try {
    // Inclui a configuração do banco de dados
    require_once __DIR__ . '/../includes/db.php';

    // Verifica se a conexão PDO foi estabelecida
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("PDO connection object not available after include.");
    }
    // Configura PDO para não lançar exceções em erros, vamos tratar manualmente
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Usar prepares nativos

} catch (Throwable $e) {
    $errorMsg = "CRITICAL SSE DB Setup Error: " . $e->getMessage();
    error_log($errorMsg);
    // Tenta enviar um erro para o cliente antes de sair
    sendSseEvent('error', ["error" => "db_setup_failed", "message" => "Database connection failed.", "code" => 500]);
    exit; // Sai se não conseguir conectar ao DB
}

// --- Variáveis de Estado ---
$lastChamadasCountersJson = null; // Armazena o JSON para comparação mais rápida
$lastWhatsappStatusJson = null;   // Armazena o JSON
$lastNotificationCount = -1;      // Inicia com -1 para forçar o envio na primeira vez
$lastHeartbeatTime = time();
$lastNotificationCheckTime = 0; // Força a checagem na primeira iteração
$dbErrorCounter = 0;
const MAX_DB_ERRORS_BEFORE_EXIT = 5; // Limite de erros de DB consecutivos

// --- Loop Principal SSE ---
error_log("SSE: Starting main loop for User ID: {$userId}");
$startTime = time();

try {
    while (true) {
        // 1. Verifica se a conexão foi abortada pelo cliente
        if (connection_aborted()) {
            error_log("SSE: Connection aborted by client for User ID: {$userId}");
            break;
        }

        // 2. Verifica o tempo máximo de execução
        if ((time() - $startTime) > MAX_EXECUTION_TIME_SECONDS) {
            error_log("SSE: Max execution time (" . MAX_EXECUTION_TIME_SECONDS . "s) reached for User ID: {$userId}");
            sendSseEvent('info', ['message' => 'Connection timed out by server, please reconnect.'], null); // Sem retry automático aqui
            break;
        }

        $currentTime = time();

        // 3. Envia Heartbeat periodicamente para manter a conexão ativa
        if ($currentTime - $lastHeartbeatTime >= HEARTBEAT_INTERVAL) {
            echo ": heartbeat\n\n"; // Comentário SSE como heartbeat
            flush();
            $lastHeartbeatTime = $currentTime;
            // Log de heartbeat apenas em desenvolvimento para não poluir logs
             if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                 error_log("SSE Heartbeat sent for User ID: {$userId}");
             }
        }

        // 4. Executa checagens no banco de dados (dentro de try/catch)
        try {
            // Tenta reestabelecer conexão se perdida (opcional, depende do driver PDO e config)
             try {
                 $pdo->query('SELECT 1');
             } catch (PDOException $e) {
                 error_log("SSE PDO connection lost for User {$userId}. Attempting reconnect... Error: " . $e->getMessage());
                 // Tentar reconectar (pode falhar dependendo da causa)
                 // require __DIR__ . '/../includes/db.php'; // Re-incluir pode não ser a melhor forma
                  throw $e; // Re-lança para o catch principal do loop DB
             }


            // --- Checagem WhatsApp Status ---
            $stmtWhatsapp = $pdo->prepare("SELECT whatsapp_solicitado, whatsapp_aprovado, whatsapp_numero FROM usuarios WHERE id = ? LIMIT 1");
            if ($stmtWhatsapp->execute([$userId])) {
                $whatsappData = $stmtWhatsapp->fetch(PDO::FETCH_ASSOC);
                if ($whatsappData) {
                    $currentWhatsappStatus = [
                        'solicitado' => (bool)$whatsappData['whatsapp_solicitado'],
                        'aprovado'   => (bool)$whatsappData['whatsapp_aprovado'],
                        'numero'     => $whatsappData['whatsapp_numero'] ?? ''
                    ];
                    $currentWhatsappStatusJson = json_encode($currentWhatsappStatus);
                    // Compara o JSON atual com o último enviado
                    if ($currentWhatsappStatusJson !== $lastWhatsappStatusJson) {
                        sendSseEvent('whatsapp_update', $currentWhatsappStatus);
                        $lastWhatsappStatusJson = $currentWhatsappStatusJson; // Atualiza o último estado enviado
                    }
                } else {
                    // Usuário não encontrado - pode indicar um problema
                     error_log("SSE Warning: User ID {$userId} not found during WhatsApp status check.");
                }
                $stmtWhatsapp->closeCursor(); // Liberar recursos
            } else {
                 // Logar erro na execução do prepare/execute
                 $errorInfo = $stmtWhatsapp->errorInfo();
                 error_log("SSE DB Error (WhatsApp Check) for User {$userId}: " . ($errorInfo[2] ?? 'Unknown error'));
                 throw new PDOException("WhatsApp check failed: " . ($errorInfo[2] ?? 'Unknown error'));
            }


            // --- Checagem Contadores de Chamadas ---
            $sqlChamadas = "SELECT
                                COUNT(*) as total,
                                COALESCE(SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END), 0) as pendentes,
                                COALESCE(SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END), 0) as em_andamento,
                                COALESCE(SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END), 0) as concluidas,
                                COALESCE(SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END), 0) as canceladas
                            FROM chamadas
                            WHERE usuario_id = ?";
            $stmtChamadas = $pdo->prepare($sqlChamadas);
             if ($stmtChamadas->execute([$userId])) {
                $currentCounters = $stmtChamadas->fetch(PDO::FETCH_ASSOC);
                if ($currentCounters) {
                    // Converte todos os valores para int explicitamente
                    $currentCounters = array_map('intval', $currentCounters);
                    $currentCountersJson = json_encode($currentCounters);
                    // Compara o JSON atual com o último enviado
                    if ($currentCountersJson !== $lastChamadasCountersJson) {
                        sendSseEvent('chamadas_update', $currentCounters);
                        $lastChamadasCountersJson = $currentCountersJson; // Atualiza o último estado enviado
                         if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                             error_log("SSE [User:{$userId}] Chamadas counters changed. Sent update.");
                         }
                    }
                }
                 $stmtChamadas->closeCursor(); // Liberar recursos
             } else {
                $errorInfo = $stmtChamadas->errorInfo();
                error_log("SSE DB Error (Chamadas Check) for User {$userId}: " . ($errorInfo[2] ?? 'Unknown error'));
                throw new PDOException("Chamadas check failed: " . ($errorInfo[2] ?? 'Unknown error'));
             }

            // --- Checagem Contagem de Notificações Não Lidas ---
            // Executa esta checagem com a frequência definida em NOTIFICATION_CHECK_INTERVAL
            if ($currentTime - $lastNotificationCheckTime >= NOTIFICATION_CHECK_INTERVAL) {
                $stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                 if ($stmtNotif->execute([$userId])) {
                    // fetchColumn retorna a contagem ou false em erro/sem linhas
                    $unreadCount = $stmtNotif->fetchColumn();
                    // Garante que é um inteiro, 0 se fetchColumn retornar false
                    $unreadCount = ($unreadCount === false) ? 0 : (int)$unreadCount;

                    // **REMOVIDO O IF** - Envia sempre a contagem atual no intervalo definido
                    // Isso garante a sincronização mesmo se eventos forem perdidos
                    sendSseEvent('notifications_update', ['unread_count' => $unreadCount]);
                    $lastNotificationCount = $unreadCount; // Atualiza o último estado conhecido *pelo servidor*

                    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                         error_log("SSE [User:{$userId}] Notifications check executed. Unread count: {$unreadCount}. Sent update.");
                     }

                    $stmtNotif->closeCursor(); // Liberar recursos
                    $lastNotificationCheckTime = $currentTime; // Atualiza o tempo da última checagem de notificação

                 } else {
                     $errorInfo = $stmtNotif->errorInfo();
                     error_log("SSE DB Error (Notifications Check) for User {$userId}: " . ($errorInfo[2] ?? 'Unknown error'));
                     // Não lança exceção aqui para não parar o loop por causa disso, mas reseta o contador de erro
                     $dbErrorCounter++;
                      if ($dbErrorCounter >= MAX_DB_ERRORS_BEFORE_EXIT) {
                           throw new RuntimeException("Too many consecutive DB errors (Notifications Check).");
                       }
                 }
            }

            // Se chegou aqui sem erros de DB, reseta o contador de erros
            $dbErrorCounter = 0;

        } catch (Throwable $e) {
            // Incrementa o contador de erros de banco de dados
            $dbErrorCounter++;
            error_log("SSE DB Error during loop [User {$userId}] (Attempt {$dbErrorCounter}/" . MAX_DB_ERRORS_BEFORE_EXIT . "): " . get_class($e) . " - " . $e->getMessage());

            // Se exceder o limite de erros consecutivos, encerra a conexão
            if ($dbErrorCounter >= MAX_DB_ERRORS_BEFORE_EXIT) {
                sendSseEvent('error', ['error' => 'db_loop_failed', 'message' => 'Server database error limit reached. Please reconnect.'], null);
                throw new RuntimeException("Too many consecutive DB errors during loop. Exiting.", 0, $e); // Re-lança para parar o script
            }

            // Espera um pouco mais antes de tentar novamente após um erro de DB
            sleep(MAIN_LOOP_SLEEP * 2); // Dobra o tempo de espera
             // Garante que a checagem de notificação não fique presa se o erro ocorrer antes dela
             if ($currentTime - $lastNotificationCheckTime >= NOTIFICATION_CHECK_INTERVAL) {
                 $lastNotificationCheckTime = $currentTime;
             }
        }

        // 5. Pausa antes da próxima iteração do loop
        sleep(MAIN_LOOP_SLEEP);

    } // Fim do while(true)

} catch (Throwable $e) {
    // Captura erros fatais que podem ocorrer fora do try/catch do DB
    error_log("SSE FATAL Error for User ID: {$userId} - " . get_class($e) . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
    // Tenta enviar uma mensagem de erro final se a conexão ainda estiver minimamente funcional
    if (!headers_sent() && !connection_aborted()) {
        sendSseEvent('error', ['error' => 'server_shutdown', 'message' => 'Internal server error caused disconnection.'], null);
    }
} finally {
    // Código que executa sempre ao final, seja por saída normal ou erro
    error_log("SSE: Connection closing for User ID: {$userId}. Reason: " . (connection_aborted() ? 'Client Aborted' : (time() - $startTime > MAX_EXECUTION_TIME_SECONDS ? 'Max Execution Time' : 'Normal/Error Exit')));
    // Fechar conexão PDO explicitamente (embora o PHP faça isso no fim do script)
    if (isset($pdo)) {
        $pdo = null;
    }
}

?>