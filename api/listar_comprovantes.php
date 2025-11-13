<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Verifica autenticação
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, valor, arquivo, descricao, data_envio 
        FROM comprovantes 
        WHERE usuario_id = ? 
        ORDER BY data_envio DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $comprovantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'comprovantes' => array_map(function($comp) {
            return [
                'id' => $comp['id'],
                'valor' => (float)$comp['valor'],
                'arquivo' => $comp['arquivo'],
                'descricao' => $comp['descricao'],
                'data_envio' => $comp['data_envio']
            ];
        }, $comprovantes)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
}