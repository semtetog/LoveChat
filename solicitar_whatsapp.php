<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/admin_auth.php';

header('Content-Type: application/json');

try {
    // Verifica autenticação
    if (!estaAutenticado()) {
        throw new Exception('Acesso não autorizado');
    }

    $dados = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $dados['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('ID do usuário não fornecido');
    }

    // Atualiza no banco de dados
    $stmt = $pdo->prepare("UPDATE usuarios SET whatsapp_solicitado = TRUE WHERE id = ?");
    $stmt->execute([$userId]);

    // Registra a solicitação em uma tabela de logs se necessário
    $stmt = $pdo->prepare("INSERT INTO whatsapp_solicitacoes (usuario_id, data_solicitacao) VALUES (?, NOW())");
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Solicitação registrada com sucesso'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}