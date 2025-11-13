<?php
// =========================================================================
// API: /admin/api/delete_user_permanently.php
// OBJETIVO: Excluir permanentemente um usuário e TODOS os seus dados
//           relacionados em múltiplas tabelas.
//
// !!! AVISO EXTREMO: ESTA AÇÃO É IRREVERSÍVEL E DESTRUTIVA !!!
// Uma vez executada, os dados do usuário e seus registros associados
// (comprovantes, saldos, mensagens, eventos, etc.) SÃO PERDIDOS PARA SEMPRE.
// Use com MÁXIMA CAUTELA e apenas se tiver ABSOLUTA CERTEZA.
// Considere usar a DESATIVAÇÃO (soft delete) como alternativa mais segura.
// =========================================================================

// --- Setup Inicial Essencial ---
date_default_timezone_set('America/Sao_Paulo'); // Define o fuso horário
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Inicia a sessão se ainda não iniciada
}

// --- Configuração de Log de Erros Específico para esta API ---
ini_set('display_errors', 0); // Não mostrar erros na tela em produção
ini_set('log_errors', 1);    // Habilitar log de erros
// Define um caminho para o arquivo de log (fora do diretório web publicamente acessível)
$log_file_path = dirname(__DIR__, 2) . '/logs/api_delete_user_errors.log';
// Tenta criar o diretório de logs se ele não existir
if (!file_exists(dirname($log_file_path))) {
    @mkdir(dirname($log_file_path), 0755, true); // O @ suprime erros se já existir
}
ini_set('error_log', $log_file_path); // Define o arquivo de log
error_reporting(E_ALL); // Logar todos os tipos de erro

// --- Resposta Padrão (JSON) ---
header('Content-Type: application/json');

// --- Verificação do Método HTTP (Aceita DELETE ou POST) ---
// Usar POST é mais compatível se houver restrições com corpo em DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Use DELETE ou POST.']);
    exit;
}

// --- Autenticação e Autorização de Administrador ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403); // Forbidden
    error_log("API Delete User Permanently: Access denied. User not logged in or not admin.");
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Requer login de administrador.']);
    exit;
}
$adminUserId = (int)$_SESSION['user_id']; // ID do admin que está executando a ação

// --- Conexão com Banco de Dados ---
// Ajuste o 'dirname(__DIR__, 2)' se a estrutura de pastas for diferente
require_once dirname(__DIR__, 2) . '/includes/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("API Delete User Permanently: CRITICAL - Failed to establish PDO connection.");
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erro Crítico: Falha na conexão com o banco de dados.']);
    exit;
}

// --- Obter e Validar ID do Usuário a ser Excluído ---
$input = json_decode(file_get_contents('php://input'), true); // Lê o corpo JSON da requisição
$userIdToDelete = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT); // Pega 'user_id' do JSON

if (!$userIdToDelete || $userIdToDelete <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'ID de usuário inválido ou ausente no corpo da requisição.']);
    exit;
}

// --- Verificação de Segurança Crucial: Impedir Auto-Exclusão ---
if ($userIdToDelete === $adminUserId) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Operação inválida: Um administrador não pode excluir permanentemente a própria conta.']);
    exit;
}

// --- Processo de Exclusão Permanente (Dentro de uma Transação) ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Garante que erros PDO lancem exceções
$foreignKeysCheckWasEnabled = true; // Flag para controlar reativação das FKs

try {
    // Inicia a transação: todas as exclusões ou nenhuma
    $pdo->beginTransaction();
    error_log("[DELETE PERMANENT] Admin ID {$adminUserId} initiated permanent deletion for User ID {$userIdToDelete}.");

    // 1. Desabilitar Temporariamente a Verificação de Chaves Estrangeiras
    //    Isso permite deletar o usuário mesmo que outras tabelas o referenciem.
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
    $foreignKeysCheckWasEnabled = false; // Marca que foi desativado
    error_log("[DELETE PERMANENT] FOREIGN_KEY_CHECKS=0 set for User ID {$userIdToDelete}.");

    // 2. Listar TODAS as tabelas que dependem do ID do usuário
    //    Formato: 'nome_tabela' => 'nome_coluna_que_guarda_o_id_do_usuario'
    //    Se a coluna for exatamente 'usuario_id', pode omitir o nome da coluna.
    //    !! REVISE E COMPLETE ESTA LISTA CUIDADOSAMENTE COM SUAS TABELAS !!
    $dependentTables = [
        'comprovantes'            => 'usuario_id',
        'saldos_diarios'          => 'usuario_id',
        'admin_events_feed'       => 'user_id', // Coluna específica
        'avaliacoes'              => 'usuario_id', // Verificar nome da coluna
        'chamadas'                => 'usuario_id', // Verificar nome da coluna
        'comprovantes_pix'        => 'usuario_id', // Verificar nome da coluna
        'conversas'               => 'usuario_id', // ATENÇÃO: Se tiver usuario1_id e usuario2_id, tratar separadamente
        'notificacoes'            => 'usuario_id', // Verificar nome da coluna
        // 'notifications',         // Existe? Qual a coluna? Se sim, adicione.
        'pagamentos_pendentes'    => 'usuario_id', // Verificar nome da coluna
        'transacoes'              => 'usuario_id', // Verificar nome da coluna
        'transacoes_mp'           => 'usuario_id', // Verificar nome da coluna
        'transacoes_pix'          => 'user_id', // Verificar nome da coluna
        'user_activities'         => 'user_id',    // Coluna específica
        'usuarios_log'            => 'usuario_id', // Verificar nome da coluna
        'whatsapp_requests'       => 'user_id', // Verificar nome da coluna
        // Adicione aqui CADA tabela que tenha uma coluna referenciando `usuarios`.`id`
    ];

    // 3. Executar DELETE para cada tabela dependente listada
    foreach ($dependentTables as $tableName => $columnName) {
        // Se a chave for numérica, o valor é o nome da tabela e a coluna é 'usuario_id'
        if (is_int($tableName)) {
             $tableName = $columnName;
             $columnName = 'usuario_id'; // Assume 'usuario_id' como padrão se não especificado
        }

        error_log("[DELETE PERMANENT] Preparing to delete from `{$tableName}` where `{$columnName}` = {$userIdToDelete}...");
        try {
            $stmt = $pdo->prepare("DELETE FROM `{$tableName}` WHERE `{$columnName}` = ?");
            $stmt->execute([$userIdToDelete]);
            error_log("[DELETE PERMANENT] Deleted {$stmt->rowCount()} rows from `{$tableName}`.");
        } catch (PDOException $tableError) {
            // Loga se uma tabela específica não existir, mas continua (pois desativamos FK checks)
            // O erro 1146 é "Table doesn't exist"
            if ($tableError->getCode() == '42S02' || strpos($tableError->getMessage(), '1146') !== false) {
                error_log("[DELETE PERMANENT] Warning: Table `{$tableName}` not found or error during delete, skipping: " . $tableError->getMessage());
            } else {
                throw $tableError; // Relança outros erros de banco de dados
            }
        }
    }

    // 4. Tratamento Especial: Tabela 'mensagens' (deletar onde é remetente OU destinatário)
    //    (Ajuste os nomes das colunas se forem diferentes)
    try {
        error_log("[DELETE PERMANENT] Preparing to delete from `mensagens` where remetente_id = {$userIdToDelete} OR destinatario_id = {$userIdToDelete}...");
        $stmt = $pdo->prepare("DELETE FROM `mensagens` WHERE `remetente_id` = ? OR `destinatario_id` = ?");
        $stmt->execute([$userIdToDelete, $userIdToDelete]);
        error_log("[DELETE PERMANENT] Deleted {$stmt->rowCount()} rows from `mensagens`.");
    } catch (PDOException $msgTableError) {
         if ($msgTableError->getCode() == '42S02' || strpos($msgTableError->getMessage(), '1146') !== false) {
            error_log("[DELETE PERMANENT] Warning: Table `mensagens` not found or error during delete, skipping: " . $msgTableError->getMessage());
         } else {
            throw $msgTableError;
         }
    }

    // 5. Finalmente, deletar o usuário da tabela principal `usuarios`
    error_log("[DELETE PERMANENT] Preparing to delete from `usuarios` where `id` = {$userIdToDelete}...");
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$userIdToDelete]);
    $deletedUserCount = $stmt->rowCount(); // Verifica se o usuário foi realmente deletado
    error_log("[DELETE PERMANENT] Deleted {$deletedUserCount} rows from `usuarios`.");

    // 6. Reabilitar Imediatamente a Verificação de Chaves Estrangeiras (CRUCIAL!)
    //    Fazemos isso ANTES do commit para garantir a integridade o mais rápido possível.
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    $foreignKeysCheckWasEnabled = true; // Marca que foi reativado
    error_log("[DELETE PERMANENT] FOREIGN_KEY_CHECKS=1 restored for User ID {$userIdToDelete}.");

    // 7. Confirmar a Transação (tornar todas as exclusões permanentes)
    $pdo->commit();
    error_log("[DELETE PERMANENT] Transaction committed successfully for User ID {$userIdToDelete}.");

    // 8. Preparar Resposta JSON de Sucesso
    if ($deletedUserCount > 0) {
         echo json_encode([
            'success' => true,
            'message' => "Usuário ID {$userIdToDelete} e todos os seus dados relacionados foram EXCLUÍDOS PERMANENTEMENTE."
        ]);
    } else {
         // O usuário principal não foi encontrado, embora outros dados possam ter sido deletados
         http_response_code(404); // Not Found
         echo json_encode([
            'success' => false,
            'message' => "Usuário ID {$userIdToDelete} não encontrado na tabela principal 'usuarios'. Dados dependentes podem ter sido removidos."
        ]);
         error_log("[DELETE PERMANENT] User ID {$userIdToDelete} not found in 'usuarios' table during final delete attempt.");
    }

} catch (Exception $e) {
    // 9. Tratamento de Erro: Desfazer a Transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
         error_log("[DELETE PERMANENT] Transaction rolled back for User ID {$userIdToDelete} due to error: " . $e->getMessage());
    } else {
         error_log("[DELETE PERMANENT] Error occurred for User ID {$userIdToDelete} outside active transaction: " . $e->getMessage());
    }

    // Retornar erro genérico para o cliente
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor ao tentar excluir o usuário. Verifique os logs.'
    ]);
    // Log detalhado do erro no servidor
    error_log("[DELETE PERMANENT] FAILED for User ID {$userIdToDelete}: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());

} finally {
    // 10. Garantir que as Chaves Estrangeiras sejam SEMPRE Reativadas
    //     Este bloco executa mesmo se ocorrer um erro no try ou catch.
    if (!$foreignKeysCheckWasEnabled) { // Se a flag indica que foram desativadas
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
            error_log("[DELETE PERMANENT] FOREIGN_KEY_CHECKS=1 successfully restored in finally block for User ID {$userIdToDelete}.");
        } catch (Exception $fkError) {
            // Erro crítico se não conseguir reativar as chaves
            error_log("[DELETE PERMANENT] CRITICAL FAILURE: Could not re-enable FOREIGN_KEY_CHECKS in finally block: " . $fkError->getMessage());
            // Talvez enviar um alerta para o admin aqui
        }
    }
}

exit; // Finaliza o script PHP
?>