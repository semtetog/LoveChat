<?php
// /api/solicitar_whatsapp.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Considere restringir em produção
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Inicia a sessão e verifica autenticação
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log de erros (ajuste o caminho conforme necessário)
ini_set('error_log', dirname(__DIR__) . '/logs/api_errors.log');

// --- Bloco de Resposta JSON e Log ---
function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
    http_response_code($http_code);
    $response = ['success' => !$is_error];
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
// --- Fim do Bloco ---

if (empty($_SESSION['user_id'])) {
    json_response(null, 401, true, 'Não autorizado');
}
$userId = (int)$_SESSION['user_id']; // Pega o ID do usuário logado

require_once __DIR__ . '/../includes/db.php'; // Garanta que $pdo está disponível aqui

// Verifica conexão PDO
if (!isset($pdo)) {
     error_log("PDO connection failed in solicitar_whatsapp.php");
     json_response(null, 500, true, 'Erro interno do servidor (DB Connection)');
}

try {
    // Verifica se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(null, 405, true, "Método não permitido");
    }

    // Obtém dados do corpo da requisição
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validação básica do input (se necessário, ex: user_id no corpo)
    // $inputId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    // if ($inputId !== $userId) {
    //     json_response(null, 403, true, "ID de usuário inválido");
    // }

    // Atualiza o status no banco de dados
    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET whatsapp_solicitado = 1,
            whatsapp_data_solicitacao = NOW()
        WHERE id = ? AND whatsapp_solicitado = 0 -- Evita re-solicitar
    ");

    $success = $stmt->execute([$userId]);
    $rowCount = $stmt->rowCount();

    // Verifica se a atualização foi bem-sucedida ou se já estava solicitado
    if (!$success) {
        throw new Exception("Falha ao executar a atualização do status do WhatsApp");
    }
    if ($rowCount === 0) {
         // Verifica se já estava solicitado antes
         $checkStmt = $pdo->prepare("SELECT whatsapp_solicitado FROM usuarios WHERE id = ?");
         $checkStmt->execute([$userId]);
         $currentState = $checkStmt->fetchColumn();
         if ($currentState == 1) {
            // Já estava solicitado, considera sucesso parcial ou informa o usuário
            // json_response(['solicitado' => true, 'aprovado' => false, 'numero' => null], 200, false, 'Você já havia solicitado o WhatsApp.');
            // Por ora, vamos tratar como sucesso e logar mesmo assim se desejar, ou apenas retornar sucesso.
         } else {
              throw new Exception("Nenhuma linha afetada e status não era solicitado. Usuário pode não existir ou outra condição.");
         }
    }

    // --- INÍCIO DO BLOCO DE LOG PARA O FEED DO ADMIN ---
    try {
        $eventType = 'whatsapp_request';
        $eventData = json_encode([
            'requested_at' => date('Y-m-d H:i:s')
            // Adicione mais dados se relevante, como o número de telefone do usuário
            // 'user_phone' => $_SESSION['user_phone'] ?? null
        ]);
        $relatedId = null; // Não há ID específico relacionado aqui
        $initialStatus = 'pending'; // Status inicial da solicitação

        $stmt_feed = $pdo->prepare("
            INSERT INTO admin_events_feed
            (event_type, user_id, event_data, related_id, status, created_at)
            VALUES (:event_type, :user_id, :event_data, :related_id, :status, NOW())
        ");
        $stmt_feed->execute([
            ':event_type' => $eventType,
            ':user_id' => $userId,
            ':event_data' => $eventData,
            ':related_id' => $relatedId,
            ':status' => $initialStatus
        ]);

    } catch (PDOException $e) {
        error_log("ADMIN FEED LOG Error ({$eventType}): " . $e->getMessage());
    } catch (Throwable $t) {
        error_log("ADMIN FEED LOG General Error ({$eventType}): " . $t->getMessage());
    }
    // --- FIM DO BLOCO DE LOG PARA O FEED DO ADMIN ---


    // Registra a ação no log do usuário (opcional, já que temos o feed do admin)
    /*
    try {
        $stmt_log = $pdo->prepare("
            INSERT INTO usuarios_log (usuario_id, acao, detalhes)
            VALUES (?, 'whatsapp_solicitado', 'Solicitação de WhatsApp Business enviada')
        ");
        $stmt_log->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Failed to write to usuarios_log: " . $e->getMessage());
    }
    */

    // Retorna sucesso
    json_response([
        'solicitado' => true,
        'aprovado' => false, // Assume que não é aprovado imediatamente
        'numero' => null
    ], 200, false, 'Solicitação de WhatsApp Business enviada com sucesso!');

} catch (Exception $e) {
    // Captura exceções e retorna erro JSON
    error_log("Error in solicitar_whatsapp.php: " . $e->getMessage());
    // Tenta obter o código HTTP se já definido, senão usa 500
    $httpCode = http_response_code();
    if ($httpCode < 400) { $httpCode = 500; } // Default para erro interno
    json_response(null, $httpCode, true, $e->getMessage());
}
?>