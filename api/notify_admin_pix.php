<?php
// Arquivo: /api/notify_admin_pix.php

// --- Configurações Iniciais e Segurança ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json'); // Sempre retorna JSON
date_default_timezone_set('America/Sao_Paulo'); // Garante fuso horário

// Log específico da API
$logFileApi = dirname(__DIR__) . '/logs/api_pix_notify.log'; // Log na pasta /logs/
ini_set('error_log', $logFileApi);
error_log("--- notify_admin_pix.php Request Received ---");

// Função básica de resposta JSON
function sendJsonResponse($success, $message, $data = null) {
    $response = ['success' => (bool)$success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit; // Termina o script após enviar a resposta
}

// 1. Verifica o método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("API Pix Notify: Método inválido - " . $_SERVER['REQUEST_METHOD']);
    sendJsonResponse(false, 'Método não permitido.');
}

// 2. Verifica se o usuário está logado na sessão
if (empty($_SESSION['user_id'])) {
    error_log("API Pix Notify: Usuário não autenticado.");
    http_response_code(401); // Unauthorized
    sendJsonResponse(false, 'Acesso não autorizado. Faça login novamente.');
}
$userId = (int)$_SESSION['user_id'];

// 3. Obtém e decodifica o corpo da requisição JSON
$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($inputData)) {
    error_log("API Pix Notify: Erro ao decodificar JSON ou dados inválidos. Input: " . file_get_contents('php://input'));
    http_response_code(400); // Bad Request
    sendJsonResponse(false, 'Dados inválidos enviados.');
}

// 4. Validação dos dados recebidos
$packageName = $inputData['packageName'] ?? null;
$leads = filter_var($inputData['leads'] ?? null, FILTER_VALIDATE_INT);
$price = filter_var($inputData['price'] ?? null, FILTER_VALIDATE_FLOAT);
$paymentMethod = $inputData['paymentMethod'] ?? null;

if (!$packageName || !$leads || $price === false || $paymentMethod !== 'pix_reported') {
    error_log("API Pix Notify: Dados obrigatórios ausentes ou inválidos. UserID: $userId | Data: " . json_encode($inputData));
    http_response_code(400);
    sendJsonResponse(false, 'Informações do pacote ou método de pagamento ausentes/inválidos.');
}

// 5. Inclui conexão com o banco de dados
$dbFile = dirname(__DIR__) . '/includes/db.php'; // Caminho relativo ao diretório da API
if (!file_exists($dbFile)) {
    error_log("API Pix Notify: ERRO CRÍTICO - db.php não encontrado em $dbFile");
    http_response_code(500); // Internal Server Error
    sendJsonResponse(false, 'Erro interno do servidor (DB Config).');
}
require_once $dbFile;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("API Pix Notify: ERRO CRÍTICO - Conexão PDO não estabelecida.");
    http_response_code(500);
    sendJsonResponse(false, 'Erro interno do servidor (DB Connection).');
}

// 6. Insere o evento no feed do admin
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $eventType = 'pix_payment_reported'; // Novo tipo de evento
    $eventData = json_encode([ // Guarda detalhes do pacote
        'packageName' => $packageName,
        'leads' => $leads,
        'price' => $price,
        'reported_at' => date('Y-m-d H:i:s') // Hora que o usuário clicou
    ]);
    $status = 'pending_confirmation'; // Status inicial, admin precisa confirmar

    $sql = "INSERT INTO admin_events_feed
                (user_id, event_type, event_data, status, created_at, is_read_by_admin)
            VALUES
                (:user_id, :event_type, :event_data, :status, NOW(), 0)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':event_type', $eventType, PDO::PARAM_STR);
    $stmt->bindParam(':event_data', $eventData, PDO::PARAM_STR);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);

    $stmt->execute();
    $newEventId = $pdo->lastInsertId(); // Pega o ID do novo evento

    error_log("API Pix Notify: Evento '$eventType' (ID: $newEventId) inserido para UserID: $userId. Pacote: $packageName");

    // 7. Envia resposta de sucesso
    sendJsonResponse(true, 'Notificação de pagamento enviada ao administrador.');

} catch (PDOException $e) {
    error_log("API Pix Notify: Erro PDO ao inserir evento. UserID: $userId | Error: " . $e->getMessage());
    http_response_code(500);
    sendJsonResponse(false, 'Erro ao registrar a notificação de pagamento.');
} catch (Throwable $t) {
    error_log("API Pix Notify: Erro geral. UserID: $userId | Error: " . $t->getMessage());
    http_response_code(500);
    sendJsonResponse(false, 'Ocorreu um erro inesperado.');
}

?>