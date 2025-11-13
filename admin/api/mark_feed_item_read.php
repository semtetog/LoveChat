<?php
// Caminho: /admin/api/mark_feed_item_read.php

// Define o fuso horário (MUITO IMPORTANTE para datas e horas consistentes)
date_default_timezone_set('America/Sao_Paulo');

// --- Segurança e Configuração Inicial ---
// Garante que a sessão PHP está iniciada (para pegar o ID do admin)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuração de Erro (Recomendado para Produção)
// Esconde erros na tela, mas registra em um arquivo de log.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Define o caminho do arquivo de log. AJUSTE SE NECESSÁRIO.
// dirname(__DIR__, 2) sobe dois níveis (de /admin/api/ para a raiz do projeto)
$log_file_path = dirname(__DIR__, 2) . '/logs/api_errors.log';
// Tenta criar o diretório de logs se ele não existir
if (!file_exists(dirname($log_file_path))) {
    @mkdir(dirname($log_file_path), 0755, true); // O @ evita erro se já existir
}
ini_set('error_log', $log_file_path);
error_reporting(E_ALL); // Loga todos os tipos de erro

// Define que a resposta será em formato JSON (o JavaScript espera isso)
header('Content-Type: application/json');

// --- Conexão com o Banco de Dados ---
// Define o caminho para o seu arquivo de conexão com o banco de dados.
// AJUSTE O CAMINHO SE O SEU ARQUIVO db.php NÃO ESTIVER EM /includes/db.php
$db_path = dirname(__DIR__, 2) . '/includes/db.php'; // Caminho: RaizDoProjeto/includes/db.php
if (!file_exists($db_path)) {
    error_log("API Mark Read Error: db.php não encontrado em " . $db_path);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor (Configuração BD).']);
    exit; // Interrompe o script
}
require_once $db_path; // Inclui o arquivo de conexão

// Verifica se a variável $pdo (conexão) foi criada corretamente no db.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("API Mark Read Error: Objeto de conexão PDO (\$pdo) não estabelecido em db.php.");
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor (Conexão BD).']);
    exit; // Interrompe o script
}

// --- Autenticação e Autorização ---
// Verifica se quem está fazendo o pedido está logado E se é um administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    error_log("API Mark Read Error: Acesso negado. Usuário não logado ou não é admin.");
    // Código 403 indica que o acesso é proibido
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit; // Interrompe o script
}
// Pega o ID do administrador que está logado (vem da sessão)
$adminUserId = (int)$_SESSION['user_id'];

// --- Processamento do Pedido (Requisição) ---
// Verifica se o pedido veio usando o método POST (o JavaScript enviará como POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Código para "Método Não Permitido"
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST.']);
    exit; // Interrompe o script
}

// Pega os dados que o JavaScript enviou no corpo do pedido.
// Espera-se que venha em formato JSON: { "event_id": 123 }
$inputData = json_decode(file_get_contents('php://input'), true);

// Valida se o 'event_id' foi recebido e se é um número inteiro maior que zero.
if (!isset($inputData['event_id']) || !filter_var($inputData['event_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    http_response_code(400); // Código para "Requisição Inválida"
    echo json_encode(['success' => false, 'message' => 'ID do evento inválido ou ausente.']);
    exit; // Interrompe o script
}
$eventId = (int)$inputData['event_id']; // Guarda o ID do evento como número inteiro

// --- Operação no Banco de Dados ---
try {
    // Configura o PDO para lançar exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prepara a instrução SQL para inserir o registro de leitura.
    // INSERT IGNORE: É a chave aqui!
    // - Se a combinação admin_id + event_id AINDA NÃO EXISTIR, ele insere normalmente.
    // - Se a combinação JÁ EXISTIR (o admin já marcou como lido antes), ele simplesmente IGNORA
    //   o comando e NÃO GERA ERRO. Isso é perfeito para o nosso caso.
    $sql = "INSERT IGNORE INTO admin_feed_read_status (admin_id, event_id) VALUES (:admin_id, :event_id)";
    $stmt = $pdo->prepare($sql);

    // Executa a instrução SQL, substituindo os :placeholders pelos valores reais.
    $stmt->execute([
        ':admin_id' => $adminUserId, // ID do admin logado
        ':event_id' => $eventId      // ID do evento recebido
    ]);

    // Verifica se a inserção realmente aconteceu (se uma nova linha foi afetada).
    // Se rowCount() for > 0, significa que era a primeira vez que marcava como lido.
    // Se for 0, significa que o IGNORE funcionou porque já estava lido.
    $wasInserted = $stmt->rowCount() > 0;

    // Envia a resposta de sucesso de volta para o JavaScript (em formato JSON).
    echo json_encode([
        'success'      => true,
        'message'      => $wasInserted ? 'Evento marcado como lido.' : 'Evento já estava marcado como lido.',
        'already_read' => !$wasInserted // Informa ao JS se já estava lido (true) ou não (false)
    ]);
    exit; // Termina o script com sucesso

} catch (PDOException $e) {
    // Se ocorrer um erro durante a operação no banco de dados
    error_log("API Mark Read DB Error: UserID {$adminUserId}, EventID {$eventId} - " . $e->getMessage());
    http_response_code(500); // Código para "Erro Interno do Servidor"
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar leitura no banco de dados.']);
    exit; // Interrompe o script
} catch (Throwable $t) {
    // Se ocorrer qualquer outro tipo de erro inesperado
    error_log("API Mark Read General Error: UserID {$adminUserId}, EventID {$eventId} - " . $t->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro inesperado no servidor.']);
    exit; // Interrompe o script
}