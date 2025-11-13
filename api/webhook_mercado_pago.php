<?php
// api/webhook_mercado_pago.php (vFINAL com Feed Log + Correção External Ref v3.2)

// --- Configurações de erro ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // RIGOROSAMENTE 0 em produção
ini_set('log_errors', 1);
// ATENÇÃO: Garanta que este diretório exista e tenha permissão de escrita!
ini_set('error_log', __DIR__ . '/../logs/webhook_errors.log'); // Log separado

// Responde OK imediatamente para o MP não reenviar desnecessariamente
// (Processamento pode continuar depois)
ignore_user_abort(true); // Continua executando mesmo se o MP fechar a conexão
set_time_limit(60);      // Aumenta limite de tempo para processamento
ob_start(); // Inicia buffer de saída
echo json_encode(['status' => 'received']); // Mensagem de recebido
header('Connection: close');
header('Content-Length: ' . ob_get_length());
header('Content-Type: application/json');
ob_end_flush();
ob_flush();
flush(); // Envia a resposta para o MP

// --- Log inicial ---
$requestTimestamp = date('Y-m-d H:i:s');
error_log("--- [{$requestTimestamp}] Webhook INICIADO ---");
$raw_payload = file_get_contents('php://input');
error_log("[{$requestTimestamp}] Webhook Payload Raw: " . $raw_payload);
// error_log("[{$requestTimestamp}] Webhook Headers: " . json_encode(getallheaders())); // Debug

// --- Autoload e Configuração MP (v3) ---
require __DIR__ . '/../vendor/autoload.php';
// ATENÇÃO: Garanta que este arquivo exista e inicialize a conexão PDO $pdo corretamente
require_once __DIR__ . '/../includes/db.php';

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use PDOException; // Importa PDOException

// --- Access Token (PRODUÇÃO) ---
$mercadoPagoAccessToken = "APP_USR-5764484683216319-040918-168ec8b6414d92143c6eda8a68a1d438-2121766941"; // <<=== SEU ACCESS TOKEN DE PRODUÇÃO

try {
    MercadoPagoConfig::setAccessToken($mercadoPagoAccessToken);
    error_log("[{$requestTimestamp}] [Webhook] SDK Configurado.");
} catch (Exception $e) {
    error_log("[{$requestTimestamp}] [Webhook] CRÍTICO: Erro config SDK: " . $e->getMessage());
    // Não pode mais enviar http_response_code aqui, pois a resposta já foi enviada
    exit; // Termina o script silenciosamente
}

// --- Ler Payload ---
$notification_data = json_decode($raw_payload, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($notification_data)) {
    error_log("[{$requestTimestamp}] [Webhook] Erro: Payload JSON inválido/vazio.");
    exit; // Termina silenciosamente
}

// --- Verificar Tipo/Ação e Pegar ID ---
$notification_type = $notification_data['type'] ?? null;
$notification_action = $notification_data['action'] ?? null;
$payment_id = $notification_data['data']['id'] ?? null;
error_log("[{$requestTimestamp}] [Webhook] Decodificado: Type='{$notification_type}', Action='{$notification_action}', PaymentID='{$payment_id}'");

// Processar apenas notificações de pagamento com ID válido
// Ações comuns: payment.created, payment.updated
if ($notification_type !== 'payment' || !$payment_id) {
    error_log("[{$requestTimestamp}] [Webhook] Notificação ignorada (Type: {$notification_type}).");
    exit; // Termina silenciosamente
}

// --- TODO: IMPLEMENTAR VALIDAÇÃO DE ASSINATURA (X-Signature) AQUI EM PRODUÇÃO ---
// Essencial para segurança! Consulte a documentação do MP para Webhooks v3.
// $secretKey = 'SEU_WEBHOOK_SECRET_KEY'; // Configure no painel MP
// $signatureHeader = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
// $requestIdHeader = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
// if (!MercadoPago\Utils\Webhooks::validateSignature($signatureHeader, $requestIdHeader, $raw_payload, $secretKey)) {
//     error_log("[{$requestTimestamp}] [Webhook] CRÍTICO: FALHA NA VALIDAÇÃO DA ASSINATURA! MP ID {$payment_id}");
//     exit; // NÃO PROCESSAR SE A ASSINATURA FOR INVÁLIDA
// }
// error_log("[{$requestTimestamp}] [Webhook] Assinatura validada com sucesso para MP ID {$payment_id}.");

// --- Buscar Detalhes do Pagamento na API MP ---
try {
    $client = new PaymentClient();
    error_log("[{$requestTimestamp}] [Webhook] Buscando detalhes Pagamento ID: {$payment_id}...");
    $payment = $client->get($payment_id); // Busca o pagamento completo

    if (!$payment || !isset($payment->id)) {
        throw new Exception("Pagamento ID {$payment_id} não encontrado na API MP ou resposta inválida.");
    }

    // Extrai dados relevantes da resposta da API
    $currentStatus = $payment->status ?? 'unknown';
    $externalReferenceFromAPI = $payment->external_reference ?? null; // ** PEGA EXTERNAL REFERENCE **
    $transaction_amount = $payment->transaction_amount ?? 0;
    $metadata_user_id = $payment->metadata->user_id ?? null; // Pega User ID dos metadados (se enviado na criação)
    $paymentDescription = $payment->description ?? 'Descrição não disponível';
    $paymentMethodId = $payment->payment_method_id ?? 'unknown';
    $installments = $payment->installments ?? 1;
    $statusDetail = $payment->status_detail ?? null; // Detalhe do status

    error_log("[{$requestTimestamp}] [Webhook] Pagamento ID {$payment_id} encontrado. Status MP: {$currentStatus}. RefExt: {$externalReferenceFromAPI}. User (Meta): {$metadata_user_id}.");

    // --- Verifica Conexão com Banco de Dados ---
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("Conexão PDO não disponível no webhook para Pagamento ID {$payment_id}.");
    }

    // --- Lógica de Atualização no Banco de Dados ---

    // Tenta obter o User ID. Prioriza metadados, se não, tenta extrair da External Reference (se o formato for consistente)
    $dbUserId = $metadata_user_id;
    if (!$dbUserId && $externalReferenceFromAPI && preg_match('/LVC-U(\d+)-T/', $externalReferenceFromAPI, $matches)) {
        $dbUserId = (int)$matches[1];
        error_log("[{$requestTimestamp}] [Webhook] User ID extraído da RefExt '{$externalReferenceFromAPI}': {$dbUserId} para MP ID {$payment_id}.");
    }

    // Se não conseguiu o User ID de nenhuma forma, não pode continuar
    if (!$dbUserId) {
         error_log("[{$requestTimestamp}] [Webhook] AVISO CRÍTICO: User ID não encontrado nos metadados nem na RefExt! Pagamento MP ID {$payment_id}. RefExt: {$externalReferenceFromAPI}. Não é possível processar.");
         exit; // Não pode prosseguir sem saber para qual usuário é o pagamento
    }
    // Se não conseguiu a External Reference (essencial para a query), também não pode continuar
    if (!$externalReferenceFromAPI) {
        error_log("[{$requestTimestamp}] [Webhook] AVISO CRÍTICO: External Reference ausente na resposta da API MP! Pagamento MP ID {$payment_id}. User ID: {$dbUserId}. Não é possível processar de forma segura.");
        exit;
    }

    // Busca status e ID interno atual no DB usando External Reference (mais seguro que só MP ID)
    $stmtCheck = $pdo->prepare("SELECT id, status FROM transacoes_mp WHERE external_reference = :externalRef AND usuario_id = :userId");
    $stmtCheck->execute([':externalRef' => $externalReferenceFromAPI, ':userId' => $dbUserId]);
    $transactionInfo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $currentDbStatus = $transactionInfo['status'] ?? null; // Status atual no DB (pode ser null se não existir)
    $internalTransactionId = $transactionInfo['id'] ?? null; // ID interno da transação

    // Decide se precisa atualizar (status mudou ou a transação não existe no DB ainda)
    // Evita processar o mesmo status múltiplas vezes
    $needsUpdate = ($currentDbStatus === null || $currentStatus !== $currentDbStatus);

    if ($needsUpdate) {
        error_log("[{$requestTimestamp}] [Webhook] Status diferente/Novo (MP: '{$currentStatus}', DB: '{$currentDbStatus ?? 'N/A'}'). Atualizando DB para RefExt: {$externalReferenceFromAPI}, User ID: {$dbUserId}...");

        try {
            // Usa INSERT ... ON DUPLICATE KEY UPDATE para inserir ou atualizar
            // ** GARANTE QUE external_reference ESTÁ NA QUERY **
            $stmtUpsert = $pdo->prepare(
                "INSERT INTO transacoes_mp (usuario_id, mp_payment_id, external_reference, status, valor, descricao, metodo_pagamento, parcelas, data_criacao, data_atualizacao)
                 VALUES (:userId, :mpPaymentId, :externalRef, :status, :valor, :descricao, :metodo, :parcelas, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                   mp_payment_id = VALUES(mp_payment_id), -- Atualiza MP ID caso tenha vindo nulo antes
                   status = VALUES(status),
                   valor = VALUES(valor),
                   descricao = VALUES(descricao),
                   metodo_pagamento = VALUES(metodo_pagamento),
                   parcelas = VALUES(parcelas),
                   data_atualizacao = NOW()"
            );
            $stmtUpsert->execute([
                ':userId' => $dbUserId,
                ':mpPaymentId' => $payment_id,
                ':externalRef' => $externalReferenceFromAPI, // **INCLUÍDO**
                ':status' => $currentStatus,
                ':valor' => $transaction_amount,
                ':descricao' => $paymentDescription,
                ':metodo' => $paymentMethodId,
                ':parcelas' => $installments
            ]);
            error_log("[{$requestTimestamp}] [Webhook] DB Upsert executado para RefExt: {$externalReferenceFromAPI}. Novo Status: {$currentStatus}. Linhas Afetadas: " . $stmtUpsert->rowCount());

            // --- AÇÃO SE APROVADO (e se o status anterior NÃO era approved) ---
            if ($currentStatus == 'approved' && $currentDbStatus !== 'approved') {
                error_log("[{$requestTimestamp}] [Webhook] APROVADO via Webhook: RefExt {$externalReferenceFromAPI}, MP ID {$payment_id} para User ID {$dbUserId}.");

                // --- Liberação de Leads ---
                $leads_a_liberar = 0; // Lógica de determinar leads baseada na descrição
                 if (strpos($paymentDescription, '50 Clientes') !== false) $leads_a_liberar = 50;
                 elseif (strpos($paymentDescription, '80 Clientes') !== false) $leads_a_liberar = 80;
                 elseif (strpos($paymentDescription, '135 Clientes') !== false) $leads_a_liberar = 135;
                 elseif (strpos($paymentDescription, '160 Clientes') !== false) $leads_a_liberar = 160;
                 // Adicione outros pacotes

                 if ($leads_a_liberar > 0) {
                    try {
                        $stmtLeads = $pdo->prepare("UPDATE usuarios SET leads_disponiveis = COALESCE(leads_disponiveis, 0) + :leadsToAdd WHERE id = :userId");
                        $stmtLeads->execute([':leadsToAdd' => $leads_a_liberar, ':userId' => $dbUserId]);
                        $rowsAffectedLeads = $stmtLeads->rowCount();
                        error_log("[{$requestTimestamp}] [Webhook] " . ($rowsAffectedLeads > 0 ? 'SUCESSO' : 'AVISO') . " DB: Liberação {$leads_a_liberar} leads via Webhook. User ID {$dbUserId}. RefExt: {$externalReferenceFromAPI}. Linhas: " . $rowsAffectedLeads);
                    } catch (PDOException $leadEx) {
                         error_log("[{$requestTimestamp}] [Webhook] ERRO DB ao liberar leads. User ID {$dbUserId}. RefExt: {$externalReferenceFromAPI}. Erro: " . $leadEx->getMessage());
                    }
                 } else {
                     error_log("[{$requestTimestamp}] [Webhook] AVISO Webhook: Pgto RefExt {$externalReferenceFromAPI} aprovado (User ID {$dbUserId}), mas 0 leads determinados pela desc '{$paymentDescription}'.");
                 }

                // ***** REGISTRAR EVENTO NO FEED (APROVADO VIA WEBHOOK) *****
                try {
                    $eventDataJson = json_encode([
                        'payment_id_mp' => $payment_id,
                        'external_reference' => $externalReferenceFromAPI,
                        'amount' => $transaction_amount,
                        'method' => $paymentMethodId,
                        'installments' => $installments,
                        'package_description' => $paymentDescription,
                        'status_detail' => $statusDetail,
                        'webhook_processed_at' => $requestTimestamp
                    ]);
                    $stmtFeed = $pdo->prepare("INSERT INTO admin_events_feed (user_id, event_type, event_data, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmtFeed->execute([$dbUserId, 'payment_approved_webhook', $eventDataJson, 'processed']); // Evento específico de webhook
                    error_log("[{$requestTimestamp}] [Webhook] FEED: Evento 'payment_approved_webhook' registrado. User ID {$dbUserId}. RefExt: {$externalReferenceFromAPI}.");
                } catch (PDOException $feedEx) {
                    error_log("[{$requestTimestamp}] [Webhook] ERRO FEED: Falha registrar 'payment_approved_webhook'. User ID {$dbUserId}. RefExt: {$externalReferenceFromAPI}. Erro: " . $feedEx->getMessage());
                }
                // ***** FIM REGISTRO FEED *****

            } // Fim if 'approved' e status anterior diferente

        } catch (PDOException $dbEx) {
            // Erro ao tentar executar o UPSERT no banco
            error_log("[{$requestTimestamp}] [Webhook] ERRO DB Crítico ao executar UPSERT. RefExt: {$externalReferenceFromAPI}, User ID: {$dbUserId}, Status MP: {$currentStatus}. Erro: " . $dbEx->getMessage());
            // Se deu erro aqui, a transação pode ficar inconsistente.
        }
    } else {
        // Status não mudou, não faz nada
        error_log("[{$requestTimestamp}] [Webhook] Status já está atualizado ('{$currentStatus}') no DB para RefExt {$externalReferenceFromAPI}, User ID {$dbUserId}. Nenhuma ação necessária.");
    }

    // --- Fim do Processamento ---
    error_log("[{$requestTimestamp}] --- Webhook FINALIZADO com sucesso para MP ID {$payment_id} ---");
    exit; // Termina o script

} catch (MPApiException $e) {
    $status_code = $e->getApiResponse()->getStatusCode();
    $error_data = $e->getApiResponse()->getContent();
    $error_message = $error_data['message'] ?? $e->getMessage();
    error_log("[{$requestTimestamp}] [Webhook] ERRO MPApiException ao buscar Pagamento ID {$payment_id}: HTTP {$status_code} - {$error_message}");
    // Não envia mais resposta HTTP aqui
    exit;
} catch (Throwable $e) {
    error_log("[{$requestTimestamp}] [Webhook] ERRO GERAL ao processar Pagamento ID {$payment_id}: " . get_class($e) . " - " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    // Não envia mais resposta HTTP aqui
    exit;
}

?>