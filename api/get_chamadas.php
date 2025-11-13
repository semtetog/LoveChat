<?php
session_start();
require_once __DIR__ . '/../includes/db.php'; // Garanta que db.php define $pdo

header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0'); // Desativa cache

// Define a constante ENVIRONMENT se não existir (ajuste conforme seu ambiente)
if (!defined('ENVIRONMENT')) {
    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
        define('ENVIRONMENT', 'development');
    } else {
        define('ENVIRONMENT', 'production'); // Padrão seguro
    }
}

// Verificação robusta de autenticação
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode([
        'success' => false,
        'message' => 'Não autorizado - sessão inválida'
    ]));
}

$userId = (int)$_SESSION['user_id'];

// Verifica se $pdo foi definido em db.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("CRITICAL Error [get_chamadas]: PDO connection object not found.");
    http_response_code(500);
    exit(json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor (DB Connection).'
    ]));
}


try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Modo de contagem rápida
    if (isset($_GET['count']) && $_GET['count'] == 'true') {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                COALESCE(SUM(status = 'pendente'), 0) as pendentes,
                COALESCE(SUM(status = 'em_andamento'), 0) as em_andamento,
                COALESCE(SUM(status = 'concluida'), 0) as concluidas
                -- , COALESCE(SUM(duracao), 0) as total_minutos -- Descomente se precisar
            FROM chamadas
            WHERE usuario_id = ? AND (
                DATE(data_hora) = CURDATE()
                OR data_hora >= NOW() - INTERVAL 1 DAY
            )
        ");

        $stmt->execute([$userId]);
        $counters = $stmt->fetch(PDO::FETCH_ASSOC);
        $counters = array_map('intval', $counters);

        echo json_encode([
            'success' => true,
            'counters' => $counters,
            'metadata' => [
                'timestamp' => time(),
                'user_id' => $userId
            ]
        ], JSON_NUMERIC_CHECK);
        exit;
    }

    // Modo de listagem detalhada
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;

    // *** CORREÇÃO APLICADA AQUI: REMOVIDO O COMENTÁRIO PHP DA QUERY ***
    $stmt = $pdo->prepare("
        SELECT
            id,
            cliente_nome,
            cliente_numero,
            DATE_FORMAT(data_hora, '%Y-%m-%dT%H:%i:%s') as data_hora, /* Formato ISO para JS */
            duracao,
            status,
            observacoes,
            TIMESTAMPDIFF(MINUTE, NOW(), data_hora) as minutos_restantes
            /* , DATE_FORMAT(data_criacao, '%Y-%m-%d %H:%i:%s') as data_criacao -- Exemplo SQL comment */
        FROM chamadas
        WHERE usuario_id = ?
        ORDER BY
            CASE status
                WHEN 'em_andamento' THEN 1
                WHEN 'pendente' THEN 2
                WHEN 'concluida' THEN 3
                WHEN 'cancelada' THEN 4
                ELSE 5
            END,
            data_hora DESC
        LIMIT ?
    ");

    $stmt->execute([$userId, $limit]);
    $chamadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($chamadas as &$chamada) {
        $chamada['id'] = (int)$chamada['id'];
        $chamada['duracao'] = (int)$chamada['duracao'];
        $chamada['minutos_restantes'] = isset($chamada['minutos_restantes']) ? (int)$chamada['minutos_restantes'] : null;
    }
    unset($chamada);

    echo json_encode([
        'success' => true,
        'chamadas' => $chamadas,
        'metadata' => [
            'count' => count($chamadas),
            'limit' => $limit,
            'timestamp' => time(),
            'user_id' => $userId
        ]
    ], JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    error_log("Database Error [get_chamadas]: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor de banco de dados ao buscar chamadas.',
        'error_code' => 'DB_ERROR',
        'error_details' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : 'Detalhes ocultos em produção.'
    ]);
    exit;
} catch (Exception $e) {
    error_log("General Error [get_chamadas]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro inesperado no servidor ao buscar chamadas.',
        'error_code' => 'SERVER_ERROR',
         'error_details' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : 'Detalhes ocultos em produção.'
    ]);
    exit;
}
?>