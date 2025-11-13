<?php
session_start();
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Configuração de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/send_notification_errors.log');

// Inclusão com caminho correto (2 níveis acima para includes)
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

// Verificação de admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso restrito a administradores']));
}

try {
    // Obter dados JSON
    $json = file_get_contents('php://input');
    if ($json === false) {
        throw new Exception("Erro ao ler dados de entrada");
    }

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }

    // Validação
    $required = ['user_id', 'title', 'message'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Campo obrigatório faltando: $field");
        }
    }

    $userId = (int)$data['user_id'];
    if ($userId <= 0) {
        throw new Exception("ID de usuário inválido");
    }

    // Sanitização
    $title = htmlspecialchars(trim($data['title']), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(trim($data['message']), ENT_QUOTES, 'UTF-8');
    $adminId = (int)$_SESSION['user_id'];

    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        throw new Exception("Usuário destino não encontrado");
    }

    // Inserir notificação
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, admin_id, title, message, is_read, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())
    ");

    $success = $stmt->execute([$userId, $adminId, $title, $message]);

    if (!$success || $stmt->rowCount() === 0) {
        throw new Exception("Falha ao inserir notificação");
    }

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Notificação enviada com sucesso',
        'notification_id' => $pdo->lastInsertId(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}