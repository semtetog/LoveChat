<?php
// /api/limpar_chamadas.php

// --- Configurações Iniciais ---
header('Content-Type: application/json; charset=utf-8');
// Defina headers CORS se necessário (igual aos outros endpoints da API)
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*')); // RESTRINJA EM PRODUÇÃO!
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Permite POST e OPTIONS (para pre-flight)
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
header('Access-Control-Allow-Credentials: true');

// Trata requisição OPTIONS (pre-flight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Iniciar Sessão ANTES de qualquer output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Inclusão e Verificação de Dependências ---
require_once __DIR__ . '/../includes/db.php'; // Inclui $pdo
// Incluir arquivo de log (se usar a função log_detalhes)
$logFile = dirname(__DIR__) . '/logs/api_errors.log'; // Ou um log específico
ini_set('log_errors', 1);
ini_set('error_log', $logFile);
error_reporting(E_ALL); // Loga tudo

// --- Função de Resposta JSON (Defina ou inclua) ---
if (!function_exists('json_response')) {
    function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
        http_response_code($http_code);
        $response = ['success' => !$is_error]; // Usa 'success' para consistência
        if ($message) $response['message'] = $message;
        if ($data !== null) $response['data'] = $data;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
// --- Função de Log (Opcional, mas recomendada) ---
if (!function_exists('log_detalhes')) {
     function log_detalhes($mensagem, $dados = []) {
         // ... (implementação da função de log, como nos outros arquivos) ...
          $logFile = ini_get('error_log');
          $timestamp = date('Y-m-d H:i:s');
          $userId = $_SESSION['user_id'] ?? 'Anon';
          $logMessage = "[{$timestamp}] [User:{$userId}] {$mensagem}";
          if (!empty($dados)) { $logMessage .= " - Dados: " . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE); }
          @error_log($logMessage . PHP_EOL, 3, $logFile);
     }
}


// --- Verificação de Segurança e Método ---

// 1. Autenticação: Garante que o usuário está logado
if (empty($_SESSION['user_id'])) {
    log_detalhes("Limpar Chamadas: Acesso não autorizado.");
    json_response(null, 401, true, 'Não autorizado.');
}
$userId = (int)$_SESSION['user_id'];

// 2. Método HTTP: Garante que é um POST (ações destrutivas devem usar POST ou DELETE)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(null, 405, true, 'Método não permitido.');
}

// 3. Conexão DB: Verifica se $pdo está disponível
if (!isset($pdo) || !($pdo instanceof PDO)) {
     log_detalhes("Limpar Chamadas: Falha crítica - Conexão PDO indisponível.");
     json_response(null, 500, true, 'Erro interno do servidor (DB).');
}

// --- Lógica Principal: Excluir Chamadas ---
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Define quais status serão excluídos
    // IMPORTANTE: Verifique se os valores correspondem EXATAMENTE aos usados no seu banco!
    $statusParaLimpar = ['completed', 'cancelled', 'concluida', 'cancelada'];

    // Prepara a query DELETE
    // Usar IN() é eficiente para múltiplos status
    // WHERE garante que só o usuário logado possa limpar suas próprias chamadas
    $stmt = $pdo->prepare("
        DELETE FROM chamadas
        WHERE usuario_id = :user_id
        AND status IN (:status1, :status2, :status3, :status4) -- Adapte o número de placeholders se tiver mais/menos status
    ");

    // Executa a query com os parâmetros
    // NOTA: PDO não suporta diretamente passar um array para IN().
    //       Vamos bindar cada status individualmente para segurança.
    //       Se tiver muitos status, gerar placeholders dinamicamente seria melhor.
    $success = $stmt->execute([
        ':user_id' => $userId,
        ':status1' => 'completed',    // Ou 'concluida'
        ':status2' => 'cancelled',    // Ou 'cancelada'
        ':status3' => 'concluida',    // Redundante, mas seguro se BD usa ambos
        ':status4' => 'cancelada'     // Redundante, mas seguro
    ]);

    // Verifica quantas linhas foram afetadas (quantas chamadas foram excluídas)
    $rowCount = $stmt->rowCount();

    log_detalhes("Limpar Chamadas: Executado com sucesso.", ['usuario_id' => $userId, 'chamadas_removidas' => $rowCount]);

    // Retorna sucesso com a contagem de chamadas removidas (opcional)
    json_response(['chamadas_removidas' => $rowCount], 200, false, "Histórico de chamadas concluídas/canceladas limpo.");

} catch (PDOException $e) {
    // Erro no banco de dados
    log_detalhes("Limpar Chamadas: Erro PDO.", ['usuario_id' => $userId, 'erro' => $e->getMessage(), 'code' => $e->getCode()]);
    json_response(['error_code' => $e->getCode()], 500, true, 'Erro no banco de dados ao limpar histórico.');
} catch (Exception $e) {
    // Outros erros inesperados
    log_detalhes("Limpar Chamadas: Erro Geral.", ['usuario_id' => $userId, 'erro' => $e->getMessage()]);
    json_response(null, 500, true, 'Erro inesperado no servidor ao limpar histórico.');
}
?>