<?php
session_start();
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Configuração de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/notifications_errors.log');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Acesso não autorizado", 401);
    }

    $userId = (int)$_SESSION['user_id'];
    if ($userId <= 0) {
        throw new Exception("ID de usuário inválido", 400);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($userId);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($userId);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        handleDeleteRequest($userId);
    } else {
        throw new Exception("Método não permitido", 405);
    }
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGetRequest($userId) {
    global $pdo;
    
    if (isset($_GET['count_unread'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'count' => (int)($result['count'] ?? 0)]);
        return;
    }

    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT n.id, n.title, n.message, n.is_read, n.created_at,
               u.id as admin_id, u.nome as admin_name, u.avatar as admin_avatar
        FROM notifications n
        LEFT JOIN usuarios u ON n.admin_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => (int)$total
    ]);
}

function handlePostRequest($userId) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }

    if (isset($input['mark_as_read'])) {
        if (isset($input['notification_id'])) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$input['notification_id'], $userId]);
        } elseif (isset($input['notification_ids']) && is_array($input['notification_ids'])) {
            if (empty($input['notification_ids'])) {
                throw new Exception("Nenhuma notificação selecionada", 400);
            }
            
            $placeholders = implode(',', array_fill(0, count($input['notification_ids']), '?'));
            $params = array_merge($input['notification_ids'], [$userId]);
            
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
        }

        echo json_encode(['success' => true]);
        return;
    }

    throw new Exception("Ação não reconhecida", 400);
}

function handleDeleteRequest($userId) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }

    if (isset($input['notification_ids']) && is_array($input['notification_ids'])) {
        if (empty($input['notification_ids'])) {
            throw new Exception("Nenhuma notificação selecionada", 400);
        }
        
        $placeholders = implode(',', array_fill(0, count($input['notification_ids']), '?'));
        $params = array_merge($input['notification_ids'], [$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true, 'message' => 'Notificações removidas com sucesso']);
}