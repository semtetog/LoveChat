<?php
session_start();
require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$user_id = intval($_GET['user_id'] ?? 0);
$last_update = intval($_GET['last_update'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT nome, avatar, UNIX_TIMESTAMP(ultima_atualizacao) as last_update FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    $response = [
        'updated' => ($user['last_update'] > $last_update),
        'name' => $user['nome'],
        'avatar' => $user['avatar'] ?? 'default.jpg',
        'timestamp' => $user['last_update']
    ];
    
    if ($response['updated']) {
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['avatar_updated'] = $user['last_update'];
    }
    
    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}