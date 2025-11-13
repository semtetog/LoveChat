<?php
// /admin/api/get_user.php (COMPLETO vFinal - COM FALLBACK - SEM OMISSÕES)

ob_start(); // Inicia buffer de saída para evitar output inesperado
header('Content-Type: application/json; charset=utf-8'); // Define o tipo de conteúdo como JSON

// --- Configurações Essenciais ---
date_default_timezone_set('America/Sao_Paulo'); // Define o fuso horário
ini_set('display_errors', 0); // Não mostrar erros na resposta final (segurança)
ini_set('log_errors', 1);    // Habilitar log de erros em arquivo
error_reporting(E_ALL);    // Reportar todos os tipos de erro para o log

// Define o caminho do arquivo de log (ajuste se necessário)
$base_dir = dirname(__DIR__, 2); // Assume que 'api' está dentro de 'admin', que está na raiz do projeto
$log_path = $base_dir . '/logs/admin_api_errors.log';

// Tenta criar o diretório de logs se não existir
if (!is_dir(dirname($log_path))) {
    @mkdir(dirname($log_path), 0755, true);
}

// Define o arquivo de log no PHP, com fallback para o log padrão do PHP
if (is_writable(dirname($log_path))) {
    ini_set('error_log', $log_path);
} else {
    // Loga um aviso no log padrão do PHP se não conseguir escrever no customizado
    error_log("WARNING: [get_user] Custom log directory not writable: " . dirname($log_path));
    ini_set('error_log', ''); // Usa o log padrão do PHP
}
// --- Fim Configurações Essenciais ---


// --- Funções Auxiliares Padronizadas ---
// Função para logar mensagens específicas da API
if (!function_exists('admin_api_log')) {
    function admin_api_log($message, $data = []) {
        $timestamp = date('Y-m-d H:i:s');
        // Tenta pegar o ID do admin da sessão, senão usa um placeholder
        $adminId = $_SESSION['user_id'] ?? 'NoSession/Unknown';
        $logMessage = "[{$timestamp}] [Admin:{$adminId}] [get_user] {$message}";
        // Adiciona dados extras ao log se fornecidos
        if (!empty($data)) {
            // Tenta converter para JSON de forma segura
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $logMessage .= ($jsonData === false) ? " - Dados: [Erro JSON: " . json_last_error_msg() . "]" : " - Dados: " . $jsonData;
        }
        // Escreve no arquivo de log definido
        $logFilePath = ini_get('error_log');
        if (!empty($logFilePath) && is_writable(dirname($logFilePath))) {
            @error_log($logMessage . PHP_EOL, 3, $logFilePath);
        } else {
            @error_log($logMessage); // Fallback para o log padrão do PHP
        }
    }
}

// Função para enviar respostas JSON padronizadas e encerrar o script
if (!function_exists('json_response')) {
    function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
        // Limpa qualquer output acidental que possa ter ocorrido antes
        $output = ob_get_clean();
        // Se houve output inesperado e não era um erro intencional, loga e retorna erro 500
        if ($output && !$is_error && trim($output) !== '') {
            admin_api_log("Output inesperado!", ['output' => substr(trim($output), 0, 200)]);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor (Output inesperado).'], JSON_UNESCAPED_UNICODE);
            exit();
        } elseif ($output && $is_error) { // Se era um erro e houve output, anexa ao erro
            $message = ($message ? $message . " | " : "") . "Output inesperado: " . substr(trim($output), 0, 200);
        }

        // Define o código de status HTTP e o cabeçalho JSON se ainda não foram enviados
        if (!headers_sent()) {
            http_response_code($http_code);
            header('Content-Type: application/json; charset=utf-8');
        }

        // Monta a estrutura básica da resposta
        $response = ['success' => !$is_error];
        if ($message !== null) {
            $response['message'] = $message; // Adiciona mensagem se houver
        }

        // Adiciona os dados à resposta
        if (!$is_error && $data !== null) {
            $response['data'] = $data; // Adiciona 'data' se for sucesso
        } elseif ($is_error && is_array($data)) {
            // Se for erro e $data for um array, mescla com a resposta (útil para códigos de erro)
            $response = array_merge($response, $data);
        }

        // Codifica a resposta em JSON
        $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Verifica se houve erro na codificação JSON
        if ($jsonOutput === false) {
            admin_api_log("JSON Encode Error", ['error' => json_last_error_msg()]);
            // Se a codificação falhar, envia um erro genérico
            if (!headers_sent()) { http_response_code(500); }
            echo '{"success":false,"message":"Erro interno do servidor ao codificar a resposta."}';
        } else {
            echo $jsonOutput; // Envia a resposta JSON final
        }
        exit(); // Termina a execução do script
    }
}
// --- Fim Funções Auxiliares ---

$userIdToGet = null; // Inicializa para logs de erro
try {
    // --- Autenticação e Inicialização ---
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // Verifica se o usuário está logado e é admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        json_response(null, 403, true, "Acesso não autorizado."); // 403 Forbidden
    }
    $adminUserId = (int)$_SESSION['user_id']; // ID do admin logado

    // Inclui e verifica conexão com banco de dados
    $db_path = $base_dir . '/includes/db.php';
    if (!file_exists($db_path)) {
        admin_api_log("Erro Crítico: Configuração do DB ausente.", ['path' => $db_path]);
        json_response(null, 500, true, "Erro interno do servidor (DB Config).");
    }
    require_once $db_path;
    if (!isset($pdo)) {
        admin_api_log("Erro Crítico: Objeto PDO não estabelecido após incluir db.php.");
        json_response(null, 500, true, "Erro interno do servidor (DB Connection).");
    }
    // Configura PDO para lançar exceções e usar UTF-8
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
    // --- Fim Autenticação e Inicialização ---

    // --- Validação da Requisição ---
    // Garante que o método HTTP seja GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(null, 405, true, 'Método HTTP não permitido.'); // 405 Method Not Allowed
    }

    // Valida o parâmetro 'id' da URL
    $userIdToGet = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$userIdToGet || $userIdToGet <= 0) {
        json_response(null, 400, true, 'ID de usuário inválido ou ausente.'); // 400 Bad Request
    }
    // --- Fim Validação da Requisição ---

    admin_api_log("Iniciando busca de detalhes (v3 - Fallback)", ['admin_id' => $adminUserId, 'target_user_id' => $userIdToGet]);

    // --- Query SQL com Lógica de Fallback para Modelo ---
    $sql = "
        SELECT
            -- Campos principais da tabela usuarios
            u.id, u.nome, u.email, u.telefone, u.avatar, u.nome_completo,
            u.is_admin, u.is_active, u.created_at, u.last_login,
            u.registration_ip, u.login_count,
            u.modelo_ia_id, -- ID do modelo atualmente ligado ao perfil (necessário para fallback)
            u.whatsapp_solicitado, u.whatsapp_aprovado, u.whatsapp_numero,
            u.chave_pix, u.tipo_chave_pix,
            u.total_ganho_acumulado,

            -- Campo calculado: Saldo Devedor Total
            COALESCE((SELECT SUM(sd.saldo)
                      FROM saldos_diarios sd
                      WHERE sd.usuario_id = u.id AND sd.pago = FALSE AND sd.saldo > 0), 0.00) as total_saldo_devedor,

            -- Campo calculado: Posição no Rank (pode ser lento)
            (SELECT COUNT(*) + 1
              FROM usuarios other_u
              WHERE COALESCE(other_u.total_ganho_acumulado, 0.00) > COALESCE(u.total_ganho_acumulado, 0.00)
                AND other_u.is_active = TRUE AND other_u.is_admin = FALSE -- Rankeia apenas ativos/não-admins
             ) as rank_position,

            -- Campo auxiliar 1: Tenta pegar o nome da ÚLTIMA modelo selecionada do FEED de eventos
            (SELECT m_feed.nome
             FROM admin_events_feed fe
             JOIN modelos_ia m_feed ON fe.related_id = m_feed.id AND fe.event_type = 'model_selected' AND fe.related_id REGEXP '^[1-9][0-9]*$'
             WHERE fe.user_id = u.id
             ORDER BY fe.created_at DESC
             LIMIT 1) as last_selected_model_name_from_feed,

             -- Campo auxiliar 2: Pega o nome da modelo ATUALMENTE ligada ao usuário (para fallback)
             m_direct.nome as direct_model_name

        FROM usuarios u
        -- Junta com modelos_ia usando o ID do perfil do usuário para obter o nome direto (fallback)
        LEFT JOIN modelos_ia m_direct ON u.modelo_ia_id = m_direct.id
        WHERE u.id = :id -- Filtra pelo ID do usuário solicitado
        LIMIT 1"; // Garante que retorna apenas um resultado
    // --- Fim da Query SQL ---

    // Prepara e executa a query
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userIdToGet]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // Pega o resultado como array associativo

    // --- Processamento do Resultado ---
    if ($user) { // Se encontrou o usuário
        admin_api_log("Usuário encontrado (v3 - Fallback)", ['user_id' => $userIdToGet]);

        // Ajusta tipos de dados e valores padrão
        $user['id'] = (int)$user['id'];
        $user['is_admin'] = !empty($user['is_admin']);
        $user['is_active'] = !empty($user['is_active']);
        $user['whatsapp_solicitado'] = !empty($user['whatsapp_solicitado']);
        $user['whatsapp_aprovado'] = !empty($user['whatsapp_aprovado']);
        $user['avatar'] = $user['avatar'] ?? 'default.jpg';
        $user['login_count'] = (int)($user['login_count'] ?? 0);
        $user['modelo_ia_id'] = $user['modelo_ia_id'] ? (int)$user['modelo_ia_id'] : null;

        // Novos campos
        $user['total_ganho_acumulado'] = (float)($user['total_ganho_acumulado'] ?? 0.00);
        $user['total_saldo_devedor'] = (float)($user['total_saldo_devedor'] ?? 0.00);
        // Define rank_position como null se não veio ou se não há ganhos
        $user['rank_position'] = isset($user['rank_position']) && $user['total_ganho_acumulado'] > 0 ? (int)$user['rank_position'] : null;

        // ***** LÓGICA DE FALLBACK PARA O NOME DA MODELO *****
        // Usa o nome do feed se existir, senão usa o nome direto do perfil, senão usa null.
        $user['last_selected_model_name'] = $user['last_selected_model_name_from_feed'] ?? $user['direct_model_name'] ?? null;
        // Remove as colunas auxiliares da resposta final
        unset($user['last_selected_model_name_from_feed'], $user['direct_model_name']);
        // ******************************************************

        // Garante que todas as chaves esperadas existam no array final, mesmo que com valor null
        $keys_to_ensure = [
            'telefone', 'nome_completo', 'chave_pix', 'tipo_chave_pix',
            'registration_ip', 'last_login', 'whatsapp_numero',
            'last_selected_model_name' // Chave final para o nome da modelo
        ];
        foreach ($keys_to_ensure as $key) {
            if (!array_key_exists($key, $user)) {
                $user[$key] = null;
            }
        }

        // Envia a resposta de sucesso com os dados do usuário
        json_response($user, 200, false, 'Usuário encontrado.');

    } else { // Se não encontrou o usuário
        admin_api_log("Usuário não encontrado (v3 - Fallback)", ['user_id' => $userIdToGet]);
        json_response(null, 404, true, 'Usuário não encontrado.'); // 404 Not Found
    }
    // --- Fim do Processamento do Resultado ---

} catch (PDOException $e) { // Captura erros específicos do banco de dados
    admin_api_log("Erro PDO (v3 - Fallback)", ['target_user' => $userIdToGet, 'error' => $e->getMessage(), 'code' => $e->getCode()]);
    // Retorna um erro genérico para o cliente, mas loga o detalhe
    json_response(['code' => $e->getCode()], 500, true, 'Erro no banco de dados ao buscar usuário.'); // 500 Internal Server Error
} catch (Throwable $t) { // Captura outros erros inesperados (erros gerais do PHP)
     admin_api_log("Erro Geral (v3 - Fallback)", ['target_user' => $userIdToGet, 'error' => $t->getMessage(), 'file' => $t->getFile(), 'line' => $t->getLine()]);
     json_response(null, 500, true, 'Erro inesperado no servidor.'); // 500 Internal Server Error
}
?>