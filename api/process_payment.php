<?php
// api/process_payment.php - v4.6 PayerFull + Issuer ID Test

// --- Força um log inicial ---
// Garante que os erros sejam logados desde o início
ini_set('log_errors', 1);
// Certifique-se que este caminho é gravável pelo servidor web
ini_set('error_log', __DIR__ . '/../php_process_payment_errors.log'); // Log específico
error_reporting(E_ALL); // Reporta todos os erros PHP
ini_set('display_errors', 0); // 0 em produção, 1 para depuração

// Log para verificar execução e escrita no arquivo
error_log("=== [ProcessPayment v4.6] Script INICIADO ===");

header('Content-Type: application/json');
error_log("[ProcessPayment v4.6] Header definido.");

// --- Autoload, DB, Classes MP v3 ---
try {
    // Confirme se o caminho para vendor/autoload.php está correto
    require __DIR__ . '/../vendor/autoload.php';
    error_log("[ProcessPayment v4.6] Autoload OK.");
    // Confirme se o caminho para seu arquivo de conexão DB está correto
    require_once __DIR__ . '/../includes/db.php'; // Ou o nome/caminho correto
    error_log("[ProcessPayment v4.6] DB Include OK (PDO disponível? " . (isset($pdo) && $pdo instanceof PDO ? 'Sim' : 'Não') . ")");
} catch (Throwable $incEx) {
    error_log("[ProcessPayment v4.6] ERRO CRITICO NO INCLUDE/AUTOLOAD: " . $incEx->getMessage());
    http_response_code(500);
    // Use a função de erro aqui se ela já estiver definida (ou defina antes)
    // send_json_error(500, 'Erro interno no servidor [INC].', null, $incEx->getMessage()); // Se send_json_error estiver definida
    echo json_encode(['status' => 'error', 'message' => 'Erro interno no servidor [INC].']); // Saída simples se a função não estiver disponível ainda
    exit;
}

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

// Inicia a sessão para pegar o user_id (APÓS includes e use statements)
session_start();

// --- Função Auxiliar para Resposta de Erro ---
function send_json_error($http_code, $message, $log_message = null, $details = null) {
    http_response_code($http_code);
    $response = ['status' => 'error', 'message' => $message];
    if ($details) $response['details'] = $details;
    if ($log_message === null) { $log_message = $message; }
    $log_entry = "[ProcessPayment v4.6] Erro {$http_code}: {$log_message}";
    if (is_array($details) || is_object($details)) { $log_entry .= " Details: " . json_encode($details); }
    elseif ($details) { $log_entry .= " Details: " . $details; }
    error_log($log_entry); // Escreve no log de erros do PHP configurado no início
    echo json_encode($response);
    exit;
}

// --- Autenticação ---
if (empty($_SESSION['user_id'])) {
    // Usando a função agora que ela está definida
    send_json_error(401, 'Usuário não autenticado.', "[ProcessPayment v4.6] Acesso não autenticado bloqueado.");
}
$userId = (int)$_SESSION['user_id'];
error_log("[ProcessPayment v4.6] Requisição autenticada User ID: {$userId}");

// --- Access Token (CONFIRME A CHAVE CORRETA!) ---
$mercadoPagoAccessToken = "APP_USR-5764484683216319-040918-168ec8b6414d92143c6eda8a68a1d438-2121766941"; // SEU ACCESS TOKEN

// --- Configura SDK ---
try {
    MercadoPagoConfig::setAccessToken($mercadoPagoAccessToken);
    error_log("[ProcessPayment v4.6] SDK Configurado User ID: {$userId}");
} catch (Exception $e) {
    send_json_error(500, 'Erro configuração servidor Mercado Pago [SDK].', "CRÍTICO: Erro configuração SDK MP User ID {$userId}: " . $e->getMessage());
}

// --- Recebe e Valida Payload JSON ---
$json_payload = file_get_contents('php://input');
$data = json_decode($json_payload, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    error_log("[ProcessPayment v4.6] Payload JSON inválido/vazio User ID {$userId}. Erro JSON: " . json_last_error_msg() . ". Payload: " . $json_payload);
    send_json_error(400, 'Dados da requisição inválidos ou corrompidos.');
}

$logData = $data;
if (isset($logData['token'])) $logData['token'] = '**** TOKEN OMITIDO ****';
error_log("[ProcessPayment v4.6] Payload recebido User ID {$userId}: " . json_encode($logData));

// --- Validações Essenciais ---
$transaction_amount = filter_var($data['transaction_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
$description = trim($data['description'] ?? '');
$package_id = filter_var($data['package_id'] ?? null, FILTER_VALIDATE_INT);
$payment_method_id = trim($data['payment_method_id'] ?? '');
$token = $data['token'] ?? null;
$installments = filter_var($data['installments'] ?? null, FILTER_VALIDATE_INT);
$issuer_id = $data['issuer_id'] ?? null; // Captura issuer_id
$device_id_from_frontend = $data['device_id'] ?? null;
$log_device_id_msg = $device_id_from_frontend ? $device_id_from_frontend : 'NAO RECEBIDO';
error_log("[ProcessPayment v4.6] Device ID Recebido: " . $log_device_id_msg);

if ($transaction_amount <= 0 || empty($description) || empty($payment_method_id) || $package_id === null || $package_id === false) {
    send_json_error(400, 'Dados essenciais inválidos ou ausentes.', "Dados essenciais inválidos User ID {$userId}.", ['amount' => $transaction_amount, 'desc' => $description, 'method' => $payment_method_id, 'pkg_id' => $package_id]);
}
if ($payment_method_id !== 'pix' && (!$token || !$installments)) {
     send_json_error(400, 'Dados do cartão incompletos.', "Token/Parcelas ausentes Cartão User ID {$userId}.", ['token' => !empty($token), 'installments' => !empty($installments)]);
}

error_log("[ProcessPayment v4.6] Dados básicos OK User ID {$userId}: Valor={$transaction_amount}, PkgID={$package_id}, Método={$payment_method_id}");

// --- Validação de Preço ---
$expectedPrice = null;
switch ($package_id) { /* ... seus cases ... */
    case 50: $expectedPrice = 30.00; break;
    case 80: $expectedPrice = 52.00; break;
    case 135: $expectedPrice = 80.00; break;
    case 160: $expectedPrice = 100.00; break;
}
if ($expectedPrice === null) { send_json_error(400, 'Pacote não reconhecido.', "Package ID {$package_id} inválido User ID {$userId}."); }
if (abs($transaction_amount - $expectedPrice) > 0.01) { send_json_error(400, 'Valor do pacote incorreto.', "ALERTA PREÇO: User ID {$userId}, Pkg: {$package_id}, Enviado: {$transaction_amount}, Esperado: {$expectedPrice}"); }
error_log("[ProcessPayment v4.6] Validação preço OK User ID: {$userId}.");

// --- Gera External Reference Única ---
$external_reference = 'LVC-' . $userId . '-' . $package_id . '-' . time();
error_log("[ProcessPayment v4.6] External Reference: {$external_reference}");

// --- Prepara Dados Comuns (Payer) ---
$payer_data = $data['payer'] ?? [];
$payer_email = filter_var($payer_data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$payer_first_name = trim($payer_data['first_name'] ?? '');
$payer_last_name = trim($payer_data['last_name'] ?? '');
$payer_identification_data = $payer_data['identification'] ?? [];
$payer_doc_type = trim($payer_identification_data['type'] ?? '');
$payer_doc_number = preg_replace('/\D/', '', trim($payer_identification_data['number'] ?? ''));
if (!$payer_email) { send_json_error(400, 'E-mail inválido.', "Email pagador inválido User ID {$userId}."); }
$api_payer = [ "email" => $payer_email ];
if (!empty($payer_first_name)) $api_payer['first_name'] = $payer_first_name;
if (!empty($payer_last_name)) $api_payer['last_name'] = $payer_last_name;
if (!empty($payer_doc_type) && !empty($payer_doc_number)) { $api_payer['identification'] = ["type" => $payer_doc_type, "number" => $payer_doc_number]; }
error_log("[ProcessPayment v4.6] Payer montado User ID {$userId}: " . json_encode($api_payer));

// --- Prepara Dados do Item (Usado apenas no PIX por enquanto) ---
$item = [ "id" => "LVC_PKG_" . $package_id, "title" => $description, "description" => "Pacote Leads LoveChat: " . $description, "quantity" => 1, "unit_price" => $transaction_amount, "category_id" => "services", ];
$additional_info_pix_only = [ "items" => [$item] ];

// --- Definições Comuns ---
$notification_url = "https://applovechat.com/api/webhook_mercado_pago.php"; // CONFIRME ESTA URL
$metadata = [ 'user_id' => $userId, 'package_id' => $package_id, 'origin' => 'LoveChat Checkout V4.6 PayerFull+Issuer' ];
$idempotencyKey = $external_reference;
$request_options = new RequestOptions();
$request_options->setCustomHeaders(["X-Idempotency-Key: " . $idempotencyKey]);
error_log("[ProcessPayment v4.6] Idempotency Key: " . $idempotencyKey);

$client = new PaymentClient();

// ==========================================
// === FLUXO 1: GERAÇÃO DE PAGAMENTO PIX ===
// ==========================================
if ($payment_method_id === 'pix') {
    error_log("[ProcessPayment v4.6] Iniciando GERAÇÃO PIX User ID: {$userId}. Ref: {$external_reference}");
    if (empty($api_payer['first_name']) || empty($api_payer['last_name']) || empty($api_payer['identification'])) {
        send_json_error(400, 'Dados do pagador obrigatórios para Pix.', "Dados pagador PIX ausentes User ID {$userId}.", $api_payer);
    }
    try {
        $date_of_expiration = date('Y-m-d\TH:i:s.vP', strtotime('+30 minutes'));
        $payment_data_pix = [ "transaction_amount" => $transaction_amount, "description" => $description, "payment_method_id" => "pix", "external_reference" => $external_reference, "payer" => $api_payer, "metadata" => $metadata, "date_of_expiration" => $date_of_expiration, "notification_url" => $notification_url, "additional_info" => $additional_info_pix_only ];
        error_log("[ProcessPayment v4.6] Enviando PIX MP->create() User ID {$userId}.");
        $payment = $client->create($payment_data_pix, $request_options);
        error_log("[ProcessPayment v4.6] Resposta PIX MP->create() User ID {$userId}. Status: " . ($payment->status ?? '?') . " ID: " . ($payment->id ?? '?'));

        if (isset($payment->id) && $payment->status == 'pending' && isset($payment->point_of_interaction->transaction_data)) {
            $responseStatus = $payment->status; $responseId = $payment->id;
            $qr_code_base64 = $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null; $qr_code = $payment->point_of_interaction->transaction_data->qr_code ?? null; $api_expiration = $payment->date_of_expiration ?? null;
            if (!$qr_code_base64 || !$qr_code) { error_log("[ProcessPayment v4.6] AVISO PIX: QR/CopiaCola ausente Pgto {$responseId}, User {$userId}."); }
            error_log("[ProcessPayment v4.6] Sucesso MP: Pix GERADO User ID {$userId}. ID: {$responseId}.");
             // --- DB PIX Pendente ---
             if (isset($pdo)) { try { $stmtUpsert = $pdo->prepare( /* ... Query UPSERT PIX ... */ "INSERT INTO transacoes_mp (usuario_id, mp_payment_id, external_reference, status, valor, descricao, metodo_pagamento, parcelas, data_criacao, data_atualizacao) VALUES (:user_id, :mp_id, :ext_ref, :status, :valor, :desc, :metodo, :parcelas, NOW(), NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), valor = VALUES(valor), descricao = VALUES(descricao), metodo_pagamento = VALUES(metodo_pagamento), parcelas = VALUES(parcelas), data_atualizacao = NOW()" ); $stmtUpsert->bindValue(':user_id', $userId, PDO::PARAM_INT); $stmtUpsert->bindValue(':mp_id', $responseId, PDO::PARAM_INT); $stmtUpsert->bindValue(':ext_ref', $external_reference, PDO::PARAM_STR); $stmtUpsert->bindValue(':status', $responseStatus, PDO::PARAM_STR); $stmtUpsert->bindValue(':valor', $transaction_amount, PDO::PARAM_STR); $stmtUpsert->bindValue(':desc', $description, PDO::PARAM_STR); $stmtUpsert->bindValue(':metodo', $payment_method_id, PDO::PARAM_STR); $stmtUpsert->bindValue(':parcelas', 1, PDO::PARAM_INT); $stmtUpsert->execute(); error_log("[ProcessPayment v4.6] DB: Transação Pix PENDENTE MP ID {$responseId} reg/atu User ID: {$userId}."); } catch (PDOException $dbEx) { error_log("[ProcessPayment v4.6] ERRO DB (PIX Pending): Falha reg transação {$responseId}. Erro: " . $dbEx->getMessage()); } } else { error_log("[ProcessPayment v4.6] CRÍTICO DB (PIX Pending): Conexão PDO indisponível."); }
            http_response_code(200); echo json_encode([ 'status' => $responseStatus, 'id' => $responseId, 'message' => 'Pix gerado. Aguardando pagamento.', 'pix_qr_code_base64' => $qr_code_base64, 'pix_copia_cola' => $qr_code, 'pix_expiration_date' => $api_expiration ]); exit;
        } else { $errorMessage = $payment->error->message ?? ($payment->message ?? 'Resposta inesperada API Pix.'); $errorCauses = $payment->error->causes ?? ($payment->cause ?? []); $mpStatusCode = $payment->status ?? '?'; send_json_error(400, $errorMessage, "ERRO MP PIX: Falha gerar Pix (resp inválida) User ID {$userId}. Msg: {$errorMessage}. Causes: " . json_encode($errorCauses) . ". Resp MP: " . json_encode($payment), ['causes' => $errorCauses, 'mp_status' => $mpStatusCode]); }
    } catch (MPApiException $e) { $status_code = $e->getApiResponse()->getStatusCode(); $error_data = $e->getApiResponse()->getContent(); $error_message = $error_data['message'] ?? $e->getMessage(); $error_causes = $error_data['cause'] ?? []; $mp_status = $error_data['status'] ?? $status_code; send_json_error(400, $error_message, "ERRO MPApiException PIX User ID {$userId}. HTTP: {$status_code}. Msg: {$error_message}. Causes: " . json_encode($error_causes), ['causes' => $error_causes, 'mp_status' => $mp_status]); } catch (Throwable $e) { send_json_error(500, 'Erro interno servidor Pix.', "ERRO GERAL PIX User ID {$userId}. Erro: " . get_class($e) . " - " . $e->getMessage() . " L: " . $e->getLine()); }
}

// ============================================================
// === FLUXO 2: PROCESSAMENTO CARTÃO (v4.6 PayerFull+Issuer) ===
// ============================================================
else { // Se não for PIX, assume Cartão ou Débito
    error_log("[ProcessPayment v4.6 PayerFull+Issuer] Iniciando Cartão User ID: {$userId}. Ref: {$external_reference}. Método: {$payment_method_id}");

    try {
        // *** Payload v4.6: Essencial + Payer Completo + Issuer ID (se houver) ***
        $payment_data = [
            "transaction_amount" => (float)$transaction_amount,
            "token" => $token,
            "installments" => (int)$installments,
            "payment_method_id" => $payment_method_id,
            "payer" => $api_payer, // Payer completo
            "description" => $description,
            "external_reference" => $external_reference
            // Removido: metadata, notification_url, statement_descriptor, additional_info (items)
        ];

        // *** ADICIONA issuer_id SE VEIO DO FRONTEND E NÃO ESTÁ VAZIO ***
        if (!empty($issuer_id)) {
            $payment_data["issuer_id"] = $issuer_id;
            error_log("[ProcessPayment v4.6 PayerFull+Issuer] Adicionando issuer_id: {$issuer_id}");
        } else {
             error_log("[ProcessPayment v4.6 PayerFull+Issuer] Nenhum issuer_id fornecido ou vazio.");
        }

        $logPayload = $payment_data; unset($logPayload['token']);
        error_log("[ProcessPayment v4.6 PayerFull+Issuer] Enviando Cartão MP->create() User ID {$userId}. Payload (sem token): ". json_encode($logPayload));

        // Cria o pagamento com Cartão/Débito
        $payment = $client->create($payment_data, $request_options);

        error_log("[ProcessPayment v4.6 PayerFull+Issuer] Resposta Cartão MP->create() User ID {$userId}. Status: " . ($payment->status ?? '?') . " ID: " . ($payment->id ?? '?') . " Status Detail: " . ($payment->status_detail ?? 'N/A'));

        // --- Análise Resposta MP e Ação DB ---
        if (isset($payment->id) && $payment->id) {
             $responseStatus = $payment->status ?? 'unknown'; $responseId = $payment->id; $paymentDescription = $payment->description ?? $description; $paymentMethod = $payment->payment_method_id ?? $payment_method_id; $paymentInstallments = $payment->installments ?? $installments; $statusDetail = $payment->status_detail ?? null;

             // --- Interação com Banco de Dados (Cartão) ---
             if (isset($pdo)) { try { $stmtUpsert = $pdo->prepare( /* ... Query UPSERT Cartão com status_detail ... */ "INSERT INTO transacoes_mp (usuario_id, mp_payment_id, external_reference, status, valor, descricao, metodo_pagamento, parcelas, data_criacao, data_atualizacao, status_detail) VALUES (:user_id, :mp_id, :ext_ref, :status, :valor, :desc, :metodo, :parcelas, NOW(), NOW(), :status_detail) ON DUPLICATE KEY UPDATE status = VALUES(status), valor = VALUES(valor), descricao = VALUES(descricao), metodo_pagamento = VALUES(metodo_pagamento), parcelas = VALUES(parcelas), status_detail = VALUES(status_detail), data_atualizacao = NOW()" ); $stmtUpsert->bindValue(':user_id', $userId, PDO::PARAM_INT); $stmtUpsert->bindValue(':mp_id', $responseId, PDO::PARAM_INT); $stmtUpsert->bindValue(':ext_ref', $external_reference, PDO::PARAM_STR); $stmtUpsert->bindValue(':status', $responseStatus, PDO::PARAM_STR); $stmtUpsert->bindValue(':valor', $transaction_amount, PDO::PARAM_STR); $stmtUpsert->bindValue(':desc', $paymentDescription, PDO::PARAM_STR); $stmtUpsert->bindValue(':metodo', $paymentMethod, PDO::PARAM_STR); $stmtUpsert->bindValue(':parcelas', $paymentInstallments, PDO::PARAM_INT); $stmtUpsert->bindValue(':status_detail', $statusDetail, PDO::PARAM_STR); $stmtUpsert->execute(); error_log("[ProcessPayment v4.6 PayerFull+Issuer] DB: Transação Cartão MP ID {$responseId} registrada/atualizada. Status: {$responseStatus}");

             // --- Liberação de Leads se Aprovado ---
             if ($responseStatus == 'approved') { /* ... Lógica de liberar leads como antes ... */
                error_log("[ProcessPayment v4.6 PayerFull+Issuer] APROVADO CARTÃO: Liberação leads User ID {$userId}."); $leads_a_liberar = 0; switch ($package_id) { case 50: $leads_a_liberar = 50; break; case 80: $leads_a_liberar = 80; break; case 135: $leads_a_liberar = 135; break; case 160: $leads_a_liberar = 160; break; }
                if ($leads_a_liberar > 0) { try { $stmtLeads = $pdo->prepare("UPDATE usuarios SET leads_disponiveis = COALESCE(leads_disponiveis, 0) + :leads WHERE id = :user_id"); $stmtLeads->bindValue(':leads', $leads_a_liberar, PDO::PARAM_INT); $stmtLeads->bindValue(':user_id', $userId, PDO::PARAM_INT); $stmtLeads->execute(); if ($stmtLeads->rowCount() > 0) { error_log("[ProcessPayment v4.6 PayerFull+Issuer] SUCESSO DB: {$leads_a_liberar} leads liberados."); } else { error_log("[ProcessPayment v4.6 PayerFull+Issuer] AVISO DB: Liberação leads não afetou linhas."); } } catch (PDOException $dbLeadsEx) { error_log("[ProcessPayment v4.6 PayerFull+Issuer] ERRO CRÍTICO DB Leads: Falha liberar leads. Erro: " . $dbLeadsEx->getMessage()); } } else { error_log("[ProcessPayment v4.6 PayerFull+Issuer] AVISO: Leads não determinados PkgID {$package_id}."); }
             }
             } catch (PDOException $dbEx) { error_log("[ProcessPayment v4.6 PayerFull+Issuer] ERRO DB (Cartão): Falha registrar transação. Erro: " . $dbEx->getMessage()); } } else { error_log("[ProcessPayment v4.6 PayerFull+Issuer] CRÍTICO DB (Cartão): Conexão PDO indisponível."); }
             // --- Fim Interação DB ---

             http_response_code(200); echo json_encode([ 'status' => $responseStatus, 'id' => $responseId, 'message' => 'Pagamento processado (PayerFull+Issuer Test).', 'status_detail' => $statusDetail ]); exit;
        } else { send_json_error(502, 'Falha na comunicação processador [Sem ID - PayerFull+Issuer].', "ERRO MP Cartão (PayerFull+Issuer): Resposta API sem ID válido User ID {$userId}. Resp MP: " . json_encode($payment)); }

    } catch (MPApiException $e) { /* ... Bloco catch MPApiException como antes ... */
         $status_code = $e->getApiResponse()->getStatusCode(); $error_data = $e->getApiResponse()->getContent(); $error_message = $error_data['message'] ?? $e->getMessage(); $error_causes = $error_data['cause'] ?? []; $mp_status_numeric = $error_data['status'] ?? $status_code; $final_status = ($status_code >= 400 && $status_code < 500) ? 'rejected' : 'error'; $status_detail_from_error = $error_data['cause'][0]['code'] ?? ($error_data['status_detail'] ?? null); error_log("[ProcessPayment v4.6 PayerFull+Issuer] ERRO MPApiException Cartão User ID {$userId}. HTTP: {$status_code}. Msg: {$error_message}. Causes: " . json_encode($error_causes) . ". Payload (sem token): ". json_encode($logPayload ?? 'N/A')); send_json_error(400, $error_message, null, [ 'causes' => $error_causes, 'mp_status' => $mp_status_numeric, 'status' => $final_status, 'status_detail' => $status_detail_from_error ]);
    } catch (Throwable $e) { /* ... Bloco catch Throwable como antes ... */
         error_log("[ProcessPayment v4.6 PayerFull+Issuer] ERRO GERAL Cartão User ID {$userId}. Erro: " . get_class($e) . " - " . $e->getMessage() . " L:" . $e->getLine()); send_json_error(500, 'Erro interno servidor pagamento.');
    }
}

// Fallback final
error_log("[ProcessPayment v4.6] ERRO LÓGICO INESPERADO: Fim do script. User ID {$userId}. Método: {$payment_method_id}.");
send_json_error(500, 'Ocorreu um erro inesperado.');

?>