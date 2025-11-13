<?php
date_default_timezone_set('America/Sao_Paulo');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Configurações de Erro
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$log_file_path = dirname(__DIR__, 2) . '/logs/api_download_report_errors.log';
if (!file_exists(dirname($log_file_path))) { @mkdir(dirname($log_file_path), 0755, true); }
ini_set('error_log', $log_file_path);
error_reporting(E_ALL);

// --- Segurança e Validação ---
// 1. Verifica se é Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    error_log("API Download Report: Unauthorized access attempt.");
    header('HTTP/1.1 403 Forbidden');
    die('Acesso não autorizado.');
}
// 2. Verifica se a data foi passada via GET
$report_date = $_GET['date'] ?? null;
if (!$report_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
    error_log("API Download Report: Invalid or missing date parameter. Received: ".($report_date ?? 'NULL'));
    header('HTTP/1.1 400 Bad Request');
    die('Data inválida ou não fornecida.');
}

// --- Lógica de Download ---
$reports_base_dir = dirname(__DIR__, 2) . '/logs/daily_payment_reports'; // Diretório dos relatórios
$filename = 'pagamentos_' . $report_date . '.csv';
$filepath = $reports_base_dir . '/' . $filename;

// Verifica se o arquivo existe e é legível
if (file_exists($filepath) && is_readable($filepath)) {
    error_log("API Download Report: Admin ID ".$_SESSION['user_id']." downloading report: ".$filename);

    // Define os cabeçalhos para forçar o download
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8'); // Especifica charset
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));

    // Limpa o buffer de saída antes de enviar o arquivo
    ob_clean();
    flush();

    // Lê e envia o arquivo
    readfile($filepath);
    exit;

} else {
    error_log("API Download Report: File not found or not readable for date $report_date. Path: ".$filepath.". Admin ID: ".$_SESSION['user_id']);
    header('HTTP/1.1 404 Not Found');
    // Exibe uma mensagem amigável (opcionalmente, pode ser uma página HTML)
    echo "<!DOCTYPE html><html><head><title>Erro 404</title><style>body{font-family: sans-serif; padding: 20px;}</style></head><body>";
    echo "<h1>Relatório Não Encontrado</h1>";
    echo "<p>O arquivo de relatório CSV para a data " . htmlspecialchars(date('d/m/Y', strtotime($report_date))) . " não foi encontrado ou não pôde ser lido.</p>";
    echo "<p>Possíveis causas:</p><ul><li>Nenhum pagamento foi gerado para essa data.</li><li>O script automático (cron job) pode não ter sido executado corretamente.</li><li>Problema de permissão no servidor.</li></ul>";
    echo "<p><a href='../payments.php'>Voltar para Pagamentos</a></p>";
    echo "</body></html>";
    exit;
}
?>