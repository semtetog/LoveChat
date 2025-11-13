<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

try {
    // Verificar autenticação
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
        throw new Exception("Acesso não autorizado");
    }

    // Consulta ao banco
    $stmt = $pdo->query("SELECT id, nome, email, is_admin, is_active, created_at FROM usuarios ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}