<?php
require_once 'includes/db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Não autenticado']));
}

// Aqui você implementaria a lógica para:
// 1. Verificar se já existe uma solicitação pendente
// 2. Criar uma nova solicitação no banco de dados
// 3. Opcionalmente disparar um email/notificação para a equipe

try {
    // Exemplo simplificado - você deve adaptar para sua lógica
    $stmt = $pdo->prepare("UPDATE usuarios SET whatsapp_status = 'pending' WHERE id = ? AND whatsapp_number IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $stmt->rowCount() > 0,
        'message' => $stmt->rowCount() > 0 ? 'Solicitação registrada' : 'Número já existe ou já está pendente'
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar solicitação'
    ]);
}