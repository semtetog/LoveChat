<?php
// /home/u689348922/domains/applovechat.com/public_html/scripts/reset_daily_balances.php
// Script para ser chamado pelo Cron para zerar saldos DIÁRIOS (não o de hoje)

// --- Configuração Inicial ---
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Diretório de logs (um nível acima de 'scripts')
$logDir = dirname(__DIR__) . '/logs';
$logFilePath = $logDir . '/cron_reset_balances_errors.log';

// Tenta criar diretório de logs
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

// Define o arquivo de log (usa o padrão do sistema se não conseguir escrever)
if (is_writable($logDir)) {
    ini_set('error_log', $logFilePath);
} else {
    ini_set('error_log', ''); // Usa o log padrão do PHP
    error_log("CRON RESET BALANCES: WARNING - Cannot write to custom log directory: " . $logDir);
}

$script_start_time = microtime(true);
error_log("--- [" . date('Y-m-d H:i:s') . "] CRON: reset_daily_balances.php STARTED ---");

// --- Conexão DB ---
$db_path = dirname(__DIR__) . '/includes/db.php'; // Caminho para db.php (um nível acima)
if (!file_exists($db_path)) {
    error_log("CRON RESET BALANCES: FATAL - db.php not found at: " . $db_path);
    die("DB config not found.");
}
require_once $db_path;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("CRON RESET BALANCES: FATAL - PDO connection failed after include.");
    die("DB connection failed.");
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Lança exceções
error_log("CRON RESET BALANCES: PDO Connected.");

// --- Lógica de Reset ---
try {
    // Data de ONTEM (o dia que acabou de passar e cujo saldo precisa ser 'finalizado' ou verificado)
    // Se a tabela `saldos_diarios` SÓ deve ter o dia atual, esta lógica está OK.
    // Se você quer MANTER o saldo do dia anterior e apenas garantir que não seja MAIS atualizado,
    // esta query não faz nada de útil.

    // ASSUMINDO QUE A LÓGICA ORIGINAL ERA SIMPLESMENTE APAGAR REGISTROS ANTIGOS:
    // (CUIDADO: ISSO APAGA O HISTÓRICO DE SALDOS!)

    /*
    $cleanup_date_threshold = date('Y-m-d', strtotime('-2 days')); // Apaga registros com mais de 1 dia
    error_log("CRON RESET BALANCES: Cleaning up records older than: " . $cleanup_date_threshold);

    $sql_delete = "DELETE FROM saldos_diarios WHERE data < :cleanup_date";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([':cleanup_date' => $cleanup_date_threshold]);
    $deleted_rows = $stmt_delete->rowCount();
    error_log("CRON RESET BALANCES: Deleted {$deleted_rows} old records from saldos_diarios.");
    */

    // *OU* ASSUMINDO QUE A LÓGICA CORRETA É GARANTIR QUE HOJE COMECE ZERADO (CASO ALGO TENHA SIDO INSERIDO ERRADO):
    // Esta lógica é coberta pelo script de zerar manual, mas podemos adicionar aqui como segurança.
    // No entanto, o script `generate_daily_payments.php` JÁ LÊ o saldo de ONTEM.
    // Portanto, NÃO precisamos ZERAR NADA aqui se a intenção é apenas LER o saldo de ontem.

    // **VAMOS MANTER SIMPLES:** Este script agora apenas LOGA que rodou.
    // O script `generate_daily_payments.php` fará a leitura do saldo de ontem.
    // O zeramento efetivo acontece porque novos comprovantes só atualizam CURDATE().

    error_log("CRON RESET BALANCES: Placeholder script executed successfully. No direct balance reset action taken here (handled by daily report generation and new entries).");
    echo "Placeholder reset script executed.\n";


} catch (PDOException $e) {
    error_log("CRON RESET BALANCES: DB ERROR - " . $e->getMessage());
    echo "DB Error. Check logs.\n";
    exit(1);
} catch (Throwable $t) {
    error_log("CRON RESET BALANCES: GENERAL ERROR - " . $t->getMessage());
    echo "General Error. Check logs.\n";
    exit(1);
}

$script_end_time = microtime(true);
$execution_time = round($script_end_time - $script_start_time, 4);
error_log("--- CRON: reset_daily_balances.php FINISHED in {$execution_time}s ---");
exit(0);
?>