<?php
// Define o fuso horário (IMPORTANTE!)
date_default_timezone_set('America/Sao_Paulo');

// --- Configurações de Erro ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$log_file_path = dirname(__DIR__) . '/logs/cron_generate_payments_errors.log';
// --- V V V Diretório para os relatórios CSV V V V ---
$reports_dir = dirname(__DIR__) . '/logs/daily_payment_reports';
// --- ^ ^ ^ Diretório para os relatórios CSV ^ ^ ^ ---

// Tenta criar diretórios se não existirem
if (!file_exists(dirname($log_file_path))) { @mkdir(dirname($log_file_path), 0755, true); }
if (!file_exists($reports_dir)) { @mkdir($reports_dir, 0755, true); } // Cria pasta de relatórios

ini_set('error_log', $log_file_path);
error_reporting(E_ALL);
$script_start_time = microtime(true);
error_log("--- [" . date('Y-m-d H:i:s') . "] Cron Generate Daily Payments Started ---");

// --- Incluir Conexão DB ---
$base_dir = dirname(__DIR__) . '/';
$db_path = $base_dir . 'includes/db.php';
if (!file_exists($db_path)) { error_log("CRITICAL ERROR: db.php not found"); die("DB config not found."); }
require_once $db_path;
if (!isset($pdo) || !($pdo instanceof PDO)) { error_log("CRITICAL ERROR: PDO connection failed"); die("DB connection failed."); }
error_log("PDO connection established for Cron script.");

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // --- Lógica Principal ---
    $yesterday_date = date('Y-m-d', strtotime('-1 day'));
    error_log("Generating payments report for date: " . $yesterday_date);

    // Query para buscar usuários com saldo positivo ONTEM e com dados PIX válidos
    $sql = "
        SELECT
            u.id as usuario_id,
            u.nome as nome_usuario,
            u.tipo_chave_pix,
            u.chave_pix,
            sd.saldo as saldo_do_dia
        FROM usuarios u
        JOIN saldos_diarios sd ON u.id = sd.usuario_id
        WHERE sd.data = :yesterday_date
          AND sd.saldo > 0
          AND u.chave_pix IS NOT NULL AND u.chave_pix != ''
          AND u.tipo_chave_pix IS NOT NULL AND u.tipo_chave_pix != ''
        ORDER BY u.nome;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':yesterday_date' => $yesterday_date]);
    $users_to_pay = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inserted_count = 0;
    $skipped_count = 0;
    $file_generated = false;
    $total_amount = 0.00;
    $csv_file_path = $reports_dir . '/pagamentos_' . $yesterday_date . '.csv'; // Caminho do arquivo CSV

    if (empty($users_to_pay)) {
        error_log("No users with positive balance found for " . $yesterday_date . ". No CSV file generated.");
    } else {
        error_log("Found " . count($users_to_pay) . " users with positive balance for " . $yesterday_date);

        // --- Geração do Arquivo CSV ---
        try {
            $csv_handle = fopen($csv_file_path, 'w'); // Abre para escrita (sobrescreve se existir)
            if ($csv_handle === false) {
                throw new Exception("Failed to open CSV file for writing: " . $csv_file_path);
            }

            // Cabeçalho do CSV (use ponto e vírgula como separador)
            fputcsv($csv_handle, [
                'Data Referencia',
                'Usuario ID',
                'Nome Usuario',
                'Tipo PIX',
                'Chave PIX',
                'Saldo (R$)' // Formatar valor depois
            ], ';'); // Delimitador ;

            // Escreve os dados
            foreach ($users_to_pay as $user) {
                 $valor_formatado = number_format((float)$user['saldo_do_dia'], 2, ',', '.'); // Formata para o CSV
                fputcsv($csv_handle, [
                    $yesterday_date,
                    $user['usuario_id'],
                    $user['nome_usuario'],
                    $user['tipo_chave_pix'],
                    $user['chave_pix'], // Chave PIX já está como string
                    $valor_formatado // Usa o valor formatado
                ], ';'); // Delimitador ;
            }

            fclose($csv_handle);
            $file_generated = true;
            error_log("Successfully generated CSV report: " . $csv_file_path);

        } catch (Exception $file_e) {
            error_log("ERROR generating CSV file: " . $file_e->getMessage());
            if (isset($csv_handle) && is_resource($csv_handle)) {
                fclose($csv_handle); // Tenta fechar se abriu
            }
            // Não interrompe, tenta inserir no banco mesmo assim
        }

        // --- Inserção no Banco de Dados (pagamentos_pendentes) ---
        $insert_sql = "
            INSERT IGNORE INTO pagamentos_pendentes
                (data_referencia, usuario_id, nome_usuario, tipo_chave_pix, chave_pix, saldo_do_dia, status_pagamento)
            VALUES
                (:data_referencia, :usuario_id, :nome_usuario, :tipo_chave_pix, :chave_pix, :saldo_do_dia, 'pendente');
        ";
        $insert_stmt = $pdo->prepare($insert_sql);

        foreach ($users_to_pay as $user) {
            $params = [
                ':data_referencia' => $yesterday_date,
                ':usuario_id' => $user['usuario_id'],
                ':nome_usuario' => $user['nome_usuario'],
                ':tipo_chave_pix' => $user['tipo_chave_pix'],
                ':chave_pix' => $user['chave_pix'],
                ':saldo_do_dia' => $user['saldo_do_dia'] // Valor original não formatado para o BD
            ];

            try {
                $insert_stmt->execute($params);
                $affected_rows = $insert_stmt->rowCount();
                if ($affected_rows > 0) {
                    $inserted_count++;
                    $total_amount += (float)$user['saldo_do_dia'];
                    // error_log("Inserted payment record for user ID: " . $user['usuario_id']); // Log menos verboso
                } else {
                    $skipped_count++;
                    // error_log("Skipped existing payment record for user ID: " . $user['usuario_id']); // Log menos verboso
                }
            } catch (PDOException $insert_e) {
                error_log("ERROR inserting payment to DB for user ID " . $user['usuario_id'] . ": " . $insert_e->getMessage());
            }
        }
    } // Fim do else (!empty($users_to_pay))

    $script_end_time = microtime(true);
    $execution_time = round($script_end_time - $script_start_time, 4);
    error_log("--- Cron Generate Daily Payments Finished ---");
    error_log("Date: $yesterday_date | Users Found: " . count($users_to_pay) . " | DB Inserted: $inserted_count | DB Skipped: $skipped_count | Total Amount: R$ " . number_format($total_amount, 2, ',', '.') . " | CSV Generated: " . ($file_generated ? 'Yes' : 'No') . " | Execution Time: " . $execution_time . "s");
    echo "Script finished. Check logs for details.\n";

} catch (PDOException $e) {
    error_log("CRITICAL PDO ERROR in Cron script: " . $e->getMessage());
    echo "CRITICAL PDO ERROR. Check logs.\n";
    exit(1);
} catch (Throwable $t) {
    error_log("CRITICAL GENERAL ERROR in Cron script: " . $t->getMessage());
    echo "CRITICAL GENERAL ERROR. Check logs.\n";
    exit(1);
}

exit(0);
?>