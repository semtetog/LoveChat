<?php
// admin/api/clear_feed_events.php
session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// Verifica se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso não autorizado']));
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Limpar evento específico
        if (isset($data['event_id'])) {
            $eventId = (int)$data['event_id'];
            $stmt = $pdo->prepare("DELETE FROM admin_events_feed WHERE id = ?");
            $stmt->execute([$eventId]);
            
            echo json_encode(['success' => true, 'message' => 'Evento removido']);
            exit;
        }
        // Limpar todos os eventos
             // Limpar todos os eventos, EXCETO os essenciais
        else {
            // Define quais tipos de evento NÃO devem ser apagados
            $eventTypesToKeep = ['model_selected', 'whatsapp_request']; // Adicione outros se necessário

            // Cria placeholders para a cláusula NOT IN (?, ?, ...)
            $placeholders = implode(',', array_fill(0, count($eventTypesToKeep), '?'));

            // Prepara a query DELETE com a condição WHERE event_type NOT IN (...)
            $sql = "DELETE FROM admin_events_feed WHERE event_type NOT IN ({$placeholders})";
            $stmt = $pdo->prepare($sql);

            // Executa a query passando os tipos a serem mantidos como parâmetros
            $stmt->execute($eventTypesToKeep);
            $rowCount = $stmt->rowCount(); // Pega o número de linhas realmente deletadas

            echo json_encode(['success' => true, 'message' => "{$rowCount} eventos do feed foram removidos (essenciais mantidos)."]);
            exit;
        }
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}