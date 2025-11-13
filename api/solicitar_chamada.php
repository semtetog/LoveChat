<?php
// /api/solicitar_chamada.php

// --- Configurações Iniciais ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/api_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- Bloco de Resposta JSON ---
function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
    http_response_code($http_code);
    $response = ['success' => !$is_error];
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}
// --- Fim do Bloco ---

// --- Verificação de Autenticação ---
if (empty($_SESSION['user_id'])) {
    error_log("[solicitar_chamada] Tentativa de acesso não autorizado.");
    json_response(null, 401, true, 'Não autorizado');
}
$userId = (int)$_SESSION['user_id'];
error_log("[solicitar_chamada] Requisição recebida do usuário ID: " . $userId);

// --- Verificação da Conexão PDO ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
     error_log("[solicitar_chamada] Conexão PDO inválida ou ausente.");
     json_response(null, 500, true, 'Erro interno do servidor (DB Connection)');
}

// --- Verificação do Método HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("[solicitar_chamada] Método inválido recebido: " . $_SERVER['REQUEST_METHOD']);
    json_response(null, 405, true, 'Método não permitido');
}

// --- Obtenção e Validação dos Dados de Entrada ---
$contentType = trim($_SERVER['CONTENT_TYPE'] ?? '');
$data = [];

if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
    $input = file_get_contents('php://input');
    parse_str($input, $data);
    error_log("[solicitar_chamada] Dados recebidos via x-www-form-urlencoded para User ID: $userId");
} elseif (stripos($contentType, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[solicitar_chamada] Erro ao decodificar JSON para User ID: $userId. Erro: " . json_last_error_msg());
        json_response(null, 400, true, 'JSON inválido no corpo da requisição');
    }
    error_log("[solicitar_chamada] Dados recebidos via JSON para User ID: $userId");
} else {
    error_log("[solicitar_chamada] Content-Type não suportado recebido: '$contentType' para User ID: $userId");
    json_response(null, 415, true, 'Content-Type não suportado. Use application/json ou x-www-form-urlencoded');
}

// Validação dos campos obrigatórios
$required = ['clienteNumero', 'chamadaData', 'chamadaDuracao'];
foreach ($required as $field) {
    if (!array_key_exists($field, $data) || trim($data[$field]) === '') {
        error_log("[solicitar_chamada] Campo obrigatório ausente ou vazio: '$field' para User ID: $userId");
        json_response(['field' => $field], 400, true, "O campo '$field' é obrigatório");
    }
}

// Pegar o número ORIGINAL
$clienteNumeroOriginal = trim($data['clienteNumero']);
if (empty($clienteNumeroOriginal)) {
    error_log("[solicitar_chamada] Validação falhou - Número do cliente vazio para User ID: $userId");
    json_response(['field' => 'clienteNumero'], 400, true, 'O número do cliente é obrigatório.');
}

// Validar Data/Hora (sem checar se é passado)
$chamadaDataInput = $data['chamadaData'];
$dataHoraFormatada = null;
try {
    $dataHora = new DateTime($chamadaDataInput);
    $dataHoraFormatada = $dataHora->format('Y-m-d H:i:s');
} catch (Exception $e) {
    error_log("[solicitar_chamada] Formato de data/hora inválido recebido: '$chamadaDataInput' para User ID: $userId. Erro: " . $e->getMessage());
    json_response(['field' => 'chamadaData'], 400, true, 'Formato de data/hora inválido. Use YYYY-MM-DDTHH:MM ou similar.');
}

// Validar duração
$duracao = filter_var($data['chamadaDuracao'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 480]
]);
if ($duracao === false) {
    error_log("[solicitar_chamada] Duração inválida recebida: '" . ($data['chamadaDuracao'] ?? 'N/A') . "' para User ID: $userId");
    json_response(['field' => 'chamadaDuracao'], 400, true, 'Duração inválida. Deve ser um número entre 1 e 480 minutos.');
}

// Dados opcionais
$clienteNome = isset($data['clienteNome']) ? trim(strip_tags($data['clienteNome'])) : null;
$observacoes = isset($data['chamadaObservacao']) ? trim(strip_tags($data['chamadaObservacao'])) : null;

// --- Processamento e Inserção no Banco ---
$stmt = null;
$chamadaId = null;

try {
    $pdo->beginTransaction();

    // <<< CORRIGIDO: Removido 'created_at' da lista de colunas e NOW() dos valores >>>
    $stmt = $pdo->prepare("INSERT INTO chamadas
        (usuario_id, cliente_nome, cliente_numero, data_hora, duracao, observacoes, status)
        VALUES
        (:usuario_id, :cliente_nome, :cliente_numero, :data_hora, :duracao, :observacoes, 'pendente')");

    $success = $stmt->execute([
        ':usuario_id' => $userId,
        ':cliente_nome' => $clienteNome ?: null,
        ':cliente_numero' => $clienteNumeroOriginal, // Salva o original
        ':data_hora' => $dataHoraFormatada,
        ':duracao' => $duracao,
        ':observacoes' => $observacoes ?: null
        // <<< CORRIGIDO: Valor para created_at removido daqui >>>
    ]);

    if (!$success) {
        $errorInfo = $stmt->errorInfo();
        error_log("[solicitar_chamada] Falha ao executar INSERT para User ID: $userId. SQLSTATE: " . ($errorInfo[0] ?? 'N/A') . " Driver Code: " . ($errorInfo[1] ?? 'N/A') . " Message: " . ($errorInfo[2] ?? 'N/A'));
        throw new Exception('Falha ao inserir chamada no banco de dados.');
    }

    $chamadaId = $pdo->lastInsertId();
    if (!$chamadaId) {
        error_log("[solicitar_chamada] Falha ao obter lastInsertId após INSERT bem-sucedido para User ID: $userId.");
        throw new Exception('Falha ao obter ID da chamada inserida.');
    }

    $pdo->commit();
    error_log("[solicitar_chamada] Chamada ID: $chamadaId inserida com sucesso para User ID: $userId.");

    // --- LOG PARA O FEED DO ADMIN ---
    try {
        $eventType = 'call_request';
        $eventData = json_encode([
            'clienteNome' => $clienteNome,
            'clienteNumero' => $clienteNumeroOriginal, // Usa o original
            'chamadaData' => $dataHoraFormatada,
            'chamadaDuracao' => $duracao,
            'chamadaObservacao' => $observacoes
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $relatedId = $chamadaId;
        $initialStatus = 'pending';

        $stmt_feed = $pdo->prepare("
            INSERT INTO admin_events_feed
            (event_type, user_id, event_data, related_id, status, created_at)
            VALUES (:event_type, :user_id, :event_data, :related_id, :status, NOW())
        "); // Assume que admin_events_feed tem 'created_at'
        $stmt_feed->execute([
            ':event_type' => $eventType,
            ':user_id' => $userId,
            ':event_data' => $eventData,
            ':related_id' => $relatedId,
            ':status' => $initialStatus
        ]);
        error_log("[solicitar_chamada] Evento '{$eventType}' logado no feed para User ID: $userId, Related ID: $relatedId.");

    } catch (PDOException $e) {
        error_log("[solicitar_chamada] Erro PDO ao logar no feed ({$eventType}) para User ID: $userId: " . $e->getMessage());
    } catch (Throwable $t) {
        error_log("[solicitar_chamada] Erro Geral ao logar no feed ({$eventType}) para User ID: $userId: " . $t->getMessage());
    }
    // --- FIM DO LOG ---

    // Resposta de sucesso para o frontend
    json_response([
        'chamada_id' => $chamadaId,
        'data_hora' => $dataHora->format('d/m/Y H:i'),
        'cliente_nome' => $clienteNome,
        'cliente_numero' => $clienteNumeroOriginal, // Retorna o original
        'duracao' => $duracao
    ], 200, false, 'Chamada agendada com sucesso');

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("[solicitar_chamada] Exceção PDO ao agendar chamada para User ID: $userId: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    $errorInfo = isset($stmt) ? $stmt->errorInfo() : null;
    json_response(['error_info' => $errorInfo], 500, true, 'Erro interno do servidor ao processar o agendamento.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("[solicitar_chamada] Exceção Geral ao agendar chamada para User ID: $userId: " . $e->getMessage());
    // <<< CORRIGIDO: Não expor a mensagem de exceção genérica diretamente na resposta final por segurança >>>
    json_response(null, 500, true, 'Erro inesperado ao processar sua solicitação.');
    // json_response(null, 500, true, 'Erro ao processar solicitação: ' . $e->getMessage()); // Use esta linha apenas para depuração
}

// Código abaixo não deve ser alcançado
error_log("[solicitar_chamada] Script chegou ao final inesperadamente para User ID: $userId.");
?>