<?php
// /admin/api/escolher_modelo.php

// --- Configuração Inicial e Logs ---
$apiLogDir = dirname(__DIR__) . '/logs'; // Ex: /caminho/para/seu/projeto/admin/logs
if (!is_dir($apiLogDir)) {
    @mkdir($apiLogDir, 0775, true); // Tenta criar o diretório se não existir
}
ini_set('log_errors', 1); // Habilita o log de erros
ini_set('error_log', $apiLogDir . '/api_escolher_modelo_errors.log'); // Define o arquivo de log
error_reporting(E_ALL); // Reporta todos os erros (para o log)
ini_set('display_errors', 0); // NÃO mostrar erros para o cliente em produção

error_log("--- API escolher_modelo.php HIT [" . date('Y-m-d H:i:s') . "] ---");

// --- Configurações de Sessão (DEVEM SER IGUAIS às do dashboard.php) ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 1 dia
        'path' => '/',
        'domain' => '', // Deixe em branco para o domínio atual ou especifique
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
error_log("Sessão iniciada/verificada. User ID: " . ($_SESSION['user_id'] ?? 'NÃO LOGADO'));

// --- Definição da Função de Resposta JSON (se não existir globalmente) ---
if (!function_exists('json_response')) {
    function json_response($data = null, $status_code = 200, $is_error = false, $message = '') {
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8'); // Adiciona charset
        $response = ['success' => !$is_error];
        if ($message) {
            $response['message'] = $message;
        }
        // Apenas adiciona 'data' se não for nulo, para evitar "data": null na resposta
        if ($data !== null || ($is_error && $data === null && $status_code !== 204)) { // 204 No Content não deve ter corpo
             $response['data'] = $data;
        }
        echo json_encode($response);
        exit;
    }
}

// --- Verificação do Método HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método HTTP inválido: " . $_SERVER['REQUEST_METHOD']);
    json_response(null, 405, true, 'Método não permitido.');
}

// --- Inclusão do Banco de Dados ---
try {
    // !! AJUSTE ESTE CAMINHO CONFORME SUA ESTRUTURA DE PASTAS !!
    // Exemplo: Se api/ está em /admin/api/ e includes/ está em /admin/includes/
    require_once __DIR__ . '/../includes/db.php';
    error_log("db.php incluído com sucesso.");
} catch (Throwable $e) { // Captura Error e Exception
    error_log("Falha crítica ao incluir db.php: " . $e->getMessage() . " na linha " . $e->getLine() . " do arquivo " . $e->getFile());
    json_response(null, 500, true, 'Erro interno do servidor (DB Setup Fail)');
}

// --- Verificação da Conexão PDO ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
     error_log("Conexão PDO falhou - \$pdo não está definido ou não é uma instância PDO após include de db.php.");
     json_response(null, 500, true, 'Erro interno do servidor (DB Connection Missing)');
}

// --- Verificação de Autenticação do Usuário ---
if (!isset($_SESSION['user_id'])) {
    error_log("Tentativa de acesso não autorizada - user_id não está na sessão.");
    json_response(null, 401, true, 'Não autorizado. Faça login novamente.');
}
$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0) {
    error_log("ID de usuário inválido na sessão: " . ($_SESSION['user_id'] ?? 'NULO'));
    json_response(null, 401, true, 'Sessão de usuário inválida.');
}

// --- Leitura e Validação do Input ---
$inputJSON = file_get_contents('php://input');
error_log("Raw input: " . $inputJSON);
$input = json_decode($inputJSON, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erro ao decodificar JSON: " . json_last_error_msg() . ". Input: " . $inputJSON);
    json_response(['debug_input' => $inputJSON], 400, true, 'Formato de requisição inválido (JSON).');
}

if (!isset($input['modelo'])) {
    error_log("Parâmetro 'modelo' ausente no input.");
    json_response(null, 400, true, 'Parâmetro "modelo" não especificado.');
}

$modeloId = filter_var($input['modelo'], FILTER_VALIDATE_INT);
if ($modeloId === false || $modeloId <= 0) {
     error_log("ID do modelo inválido fornecido: " . ($input['modelo'] ?? 'NULO'));
     json_response(null, 400, true, 'ID do modelo inválido.');
}

// --- (Opcional) Determinar Nome da Modelo para Log ---
$modeloNomeParaLog = "ID {$modeloId}";
// Se você tiver uma tabela 'modelos_ia' com 'id' e 'nome':
/*
try {
    $stmtNome = $pdo->prepare("SELECT nome FROM modelos_ia WHERE id = ?");
    $stmtNome->execute([$modeloId]);
    $modeloInfoNome = $stmtNome->fetch(PDO::FETCH_ASSOC);
    if ($modeloInfoNome && !empty($modeloInfoNome['nome'])) {
        $modeloNomeParaLog = $modeloInfoNome['nome'];
    }
} catch (PDOException $e) {
    error_log("Aviso: Não foi possível buscar nome da modelo para log. ID: {$modeloId} - Erro: " . $e->getMessage());
}
*/

// --- Log no Feed do Admin (ANTES de verificar se pode alterar) ---
try {
    $eventType = 'model_selected_attempt'; // Mudado para indicar tentativa
    $eventData = json_encode([
        'modelo_id' => $modeloId,
        'modelo_nome' => $modeloNomeParaLog
    ]);
    $stmt_feed = $pdo->prepare("
        INSERT INTO admin_events_feed (event_type, user_id, event_data, related_id, status, created_at)
        VALUES (:event_type, :user_id, :event_data, :related_id, :status, NOW())
    ");
    $stmt_feed->execute([
        ':event_type' => $eventType,
        ':user_id' => $userId,
        ':event_data' => $eventData,
        ':related_id' => $modeloId,
        ':status' => 'pending_update' // Status inicial
    ]);
    $feedEventId = $pdo->lastInsertId(); // Pega o ID do evento do feed para atualizar depois
    error_log("Evento {$eventType} registrado no feed (ID: {$feedEventId}) para usuário {$userId}, modelo {$modeloId}");
} catch (PDOException $e) {
    $feedEventId = null; // Garante que não tentaremos atualizar um evento que falhou ao ser inserido
    error_log("ADMIN FEED LOG Error ({$eventType}): " . $e->getMessage());
    // Não interrompe o script principal por falha no log do feed
}

// --- Lógica Principal de Seleção/Atualização do Modelo ---
try {
    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare("SELECT modelo_ia_id FROM usuarios WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) {
        $pdo->rollBack();
        error_log("Usuário ID {$userId} não encontrado no banco de dados.");
        if ($feedEventId) { // Atualiza status do evento no feed se ele foi criado
            $pdo->exec("UPDATE admin_events_feed SET status = 'error_user_not_found' WHERE id = {$feedEventId}");
        }
        json_response(null, 404, true, 'Usuário não encontrado.');
    }

    // REGRA DE NEGÓCIO: Usuário não pode alterar modelo uma vez escolhido.
    if ($userInfo['modelo_ia_id'] !== null && $userInfo['modelo_ia_id'] > 0) {
        $pdo->rollBack();
        error_log("Usuário {$userId} tentou alterar modelo já escolhido (ID atual: {$userInfo['modelo_ia_id']}) para modelo {$modeloId}. Ação bloqueada.");
        if ($feedEventId) {
            $pdo->exec("UPDATE admin_events_feed SET status = 'error_already_chosen' WHERE id = {$feedEventId}");
        }
        json_response(
            ['modelo_atual_id' => (int)$userInfo['modelo_ia_id']],
            409, // HTTP 409 Conflict
            false, // Não é um erro fatal do sistema, mas uma regra de negócio
            'Você já escolheu um modelo e não pode alterá-lo.'
        );
    }

    // Se chegou aqui, é a primeira escolha ou a alteração é permitida (mas a lógica acima impede alteração)
    $stmtUpdate = $pdo->prepare("UPDATE usuarios SET modelo_ia_id = ?, tutorial_visto = TRUE WHERE id = ?");
    // tutorial_visto = TRUE pode ser opcional aqui, ou definido em outro lugar.
    // Se o tutorial é mostrado após a escolha, faz sentido.
    $updateSuccess = $stmtUpdate->execute([$modeloId, $userId]);

    if (!$updateSuccess) {
        // A transação será desfeita pelo rollback no catch PDOException abaixo
        throw new PDOException("Falha ao executar o update na tabela usuarios. Erro: " . implode(";", $stmtUpdate->errorInfo()));
    }

    $pdo->commit();
    error_log("SUCESSO: Modelo ID {$modeloId} definido para usuário ID {$userId}.");

    // Atualiza a sessão PHP
    $_SESSION['modelo_escolhido'] = true; // Indica que um modelo foi escolhido
    $_SESSION['modelo_escolhido_id'] = $modeloId; // Armazena o ID do modelo escolhido

    if ($feedEventId) {
        $pdo->exec("UPDATE admin_events_feed SET status = 'success_model_updated' WHERE id = {$feedEventId}");
    }
    json_response(['modelo_id' => $modeloId], 200, false, 'Modelo selecionado com sucesso!');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro PDOException ao selecionar/atualizar modelo para usuário {$userId}, modelo {$modeloId}: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    if ($feedEventId) {
        try { $pdo->exec("UPDATE admin_events_feed SET status = 'error_db_exception' WHERE id = {$feedEventId}"); }
        catch (PDOException $ex) { error_log("Falha ao atualizar status do feed no erro: " . $ex->getMessage());}
    }
    json_response(null, 500, true, 'Erro no servidor ao processar sua escolha. Tente novamente.');
} catch (Throwable $e) { // Captura outras exceções gerais
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
     error_log("Erro Throwable geral ao selecionar/atualizar modelo: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    if ($feedEventId) {
        try { $pdo->exec("UPDATE admin_events_feed SET status = 'error_general_exception' WHERE id = {$feedEventId}"); }
        catch (PDOException $ex) { error_log("Falha ao atualizar status do feed no erro: " . $ex->getMessage());}
    }
     json_response(null, 500, true, 'Ocorreu um erro inesperado. Detalhes: ' . $e->getMessage());
}

// Certifique-se que o script sempre saia após json_response ou aqui
exit;
?>