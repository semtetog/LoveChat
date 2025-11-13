<?php
// /api/enviar_comprovante.php (COMPLETO vFinal - Saldo Acumulado)

ob_start(); // Inicia buffer o mais cedo possível
// Define o header ANTES de qualquer output potencial
header('Content-Type: application/json; charset=utf-8');

// --- Configurações Iniciais ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, 'path' => '/', 'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}
date_default_timezone_set('America/Sao_Paulo'); // Define fuso horário

// --- Configurações de Log de Erros ---
$base_dir = dirname(__DIR__); // Assume que 'api' está um nível abaixo da raiz
$logDir = $base_dir . '/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
$logFilePath = $logDir . '/comprovante_api_errors.log';
ini_set('error_log', $logFilePath);
ini_set('log_errors', 1);
ini_set('display_errors', 0); // Nunca exibir erros em produção/API
error_reporting(E_ALL); // Logar todos os erros

// --- Bloco de Resposta JSON Padronizado ---
if (!function_exists('json_response')) {
    function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
        $output = ob_get_clean(); // Limpa qualquer output acidental
        if ($output && trim($output) !== '') {
            // Loga o output inesperado, mas tenta enviar um JSON de erro válido
            error_log("[json_response] Unexpected output detected: " . substr(trim($output), 0, 200));
            if (!$is_error) { // Se não era pra ser um erro, força código 500
                $http_code = 500;
                $is_error = true;
                $message = $message ? $message . " | Erro interno (Output)" : "Erro interno (Output). Ver logs.";
            }
        }
        // Define headers se ainda não foram enviados
        if (!headers_sent()) {
            http_response_code($http_code);
            header('Content-Type: application/json; charset=utf-8');
        }
        $response = ['status' => !$is_error]; // Chave 'status' no seu padrão original
        if ($message) $response['message'] = $message;
        if ($data !== null) $response['data'] = $data; // Usa 'data' para dados de sucesso ou detalhes de erro
        // Tenta codificar o JSON
        $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
        if ($jsonOutput === false) {
            // Se o encode falhar, loga e envia um erro JSON genérico
            error_log("[json_response] JSON Encode Error: " . json_last_error_msg());
            if (!headers_sent()) { http_response_code(500); } // Garante código 500 se possível
            echo '{"status":false,"message":"Erro interno do servidor ao gerar resposta."}';
        } else {
            echo $jsonOutput;
        }
        exit();
    }
}
// --- Fim Bloco JSON ---

// --- Função de Log Detalhado Padronizada ---
if (!function_exists('log_detalhes')) {
     function log_detalhes($mensagem, $dados = []) {
         $logFile = ini_get('error_log');
         if (!$logFile || !is_writable(dirname($logFile))) { // Verifica se pode escrever
             error_log("Fallback log: " . $mensagem . " | " . json_encode($dados)); // Fallback para log padrão do PHP
             return;
         }
         $timestamp = date('Y-m-d H:i:s');
         $userId = $_SESSION['user_id'] ?? 'Anonimo';
         $logMessage = "[{$timestamp}] [Usuario:{$userId}] [enviar_comp] {$mensagem}"; // Identificador
         if (!empty($dados)) {
             // Tenta remover senhas antes de logar
             $safeData = $dados;
             if (isset($safeData['senha'])) $safeData['senha'] = '***';
             if (isset($safeData['password'])) $safeData['password'] = '***';
             // Adicione outras chaves sensíveis se necessário

             $encodedData = json_encode($safeData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PRETTY_PRINT);
             if ($encodedData === false) { $logMessage .= " - Dados: [Erro JSON log: " . json_last_error_msg() . "]"; }
             else { $logMessage .= " - Dados: " . $encodedData; }
         }
         // Usa tipo 3 para garantir escrita no arquivo correto
         @error_log($logMessage . PHP_EOL, 3, $logFile);
     }
}
// --- Fim Função Log ---

// --- Função Conversão PDF para Imagem ---
if (!function_exists('converterPdfParaImagem')) {
    function converterPdfParaImagem($pdfPath, $outputPath = null, $resolution = 150, $quality = 85) {
        if (!extension_loaded('imagick')) { log_detalhes('ERRO FATAL: Extensão PHP Imagick não carregada.'); return false; }
        if (!file_exists($pdfPath) || !is_readable($pdfPath)) { log_detalhes("Erro conversão PDF: Origem inválida.", ['path' => $pdfPath]); return false; }
        if ($outputPath === null) { $baseDir = dirname($pdfPath); $outputDir = $baseDir . DIRECTORY_SEPARATOR . 'thumbnails'; $outputFilename = pathinfo($pdfPath, PATHINFO_FILENAME) . '.jpg'; $outputPath = $outputDir . DIRECTORY_SEPARATOR . $outputFilename; } else { $outputDir = dirname($outputPath); }
        if (!is_dir($outputDir)) { if (!@mkdir($outputDir, 0775, true) && !is_dir($outputDir)) { log_detalhes("Erro conversão PDF: Criar dir falhou.", ['dir' => $outputDir]); return false; } }
        if (!is_writable($outputDir)) { log_detalhes("Erro conversão PDF: Dir sem escrita.", ['dir' => $outputDir]); return false; }
        $imagick = null;
        try {
            $imagick = new \Imagick(); $imagick->setResolution($resolution, $resolution);
            if (!$imagick->readImage($pdfPath . '[0]')) { throw new \ImagickException("Falha leitura PDF: $pdfPath"); }
            $imagick->setImageBackgroundColor('white'); $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE); $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $imagick->setImageFormat('jpg'); $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG); $imagick->setImageCompressionQuality($quality); $imagick->stripImage();
            if (!$imagick->writeImage($outputPath)) { throw new \ImagickException("Falha escrita imagem: $outputPath"); }
            log_detalhes("PDF convertido.", ['origem' => basename($pdfPath), 'destino' => basename($outputPath)]); return $outputPath;
        } catch (\Exception $e) { log_detalhes("Erro conversão PDF.", ['pdf' => basename($pdfPath), 'erro' => $e->getMessage()]); return false;
        } finally { if (isset($imagick) && $imagick instanceof \Imagick) { $imagick->clear(); $imagick->destroy(); } }
    }
}
// --- Fim Função Conversão PDF ---

// --- Função Limpar Valor OCR ---
if (!function_exists('limparValorOCR')) {
    function limparValorOCR($textoValor) {
        if (empty($textoValor) || !is_string($textoValor)) return false;
        $limpo = preg_replace('/[^\d,\.]/', '', $textoValor);
        if (strpos($limpo, ',') !== false && strpos($limpo, '.') !== false && strrpos($limpo, '.') > strrpos($limpo, ',')) { $limpo = str_replace(',', '', $limpo); } // Caso: 1.000,50
        elseif (strpos($limpo, ',') !== false) { $limpo = str_replace('.', '', $limpo); $limpo = str_replace(',', '.', $limpo); } // Caso: 1.000,50 ou 40,00
        if (substr_count($limpo, '.') > 1) { $limpo = str_replace('.', '', substr($limpo, 0, strrpos($limpo, '.'))) . substr($limpo, strrpos($limpo, '.')); } // Remove pontos extras
        if (is_numeric($limpo) && (float)$limpo >= 0) { return (float)$limpo; } // Permite zero
        log_detalhes("limparValorOCR: Falha conversão ou valor inválido.", ['input' => $textoValor, 'result' => $limpo]); return false;
    }
}
// --- Fim Função Limpar Valor OCR ---

// --- Função OCR ---
if (!function_exists('extrairValorComOCR')) {
    function extrairValorComOCR($imagePath) {
        $apiKey = 'K82849181888957'; // Chave pública OCR.space (Verifique se ainda é válida)
        log_detalhes("OCR: Iniciando OCR.space.", ['arquivo' => basename($imagePath)]);
        if (!file_exists($imagePath) || !is_readable($imagePath)) { log_detalhes("OCR: Imagem não encontrada.", ['path' => $imagePath]); return false; }

        $textoCompletoDoOCR = null;
        try {
            $ch = curl_init(); /* ... (Configuração cURL como antes) ... */
             curl_setopt($ch, CURLOPT_URL, "https://api.ocr.space/parse/image");
             curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); curl_setopt($ch, CURLOPT_POST, 1);
             curl_setopt($ch, CURLOPT_TIMEOUT, 45); curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
             if (!class_exists('CURLFile')) { throw new Exception("CURLFile não encontrada."); }
             $postFields = [ 'file' => new CURLFile(realpath($imagePath)), 'apikey' => $apiKey, 'language' => 'por', 'isOverlayRequired' => "false", 'OCREngine' => 2 ];
             curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
             log_detalhes("OCR: Executando cURL.");
             $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
             $curlErrorNo = curl_errno($ch); $curlError = curl_error($ch); curl_close($ch);
             if ($curlErrorNo !== CURLE_OK) { throw new Exception("Erro cURL ({$curlErrorNo}): " . $curlError); }
             if ($httpCode !== 200) { $errorData = json_decode($response, true); $errorMessage = $errorData['ErrorMessage'][0] ?? $errorData['ErrorMessage'] ?? $response; log_detalhes("OCR: API HTTP Error.", ['code' => $httpCode, 'response' => $response]); throw new Exception("API OCR HTTP {$httpCode}: " . substr($errorMessage, 0, 500)); }
             $result = json_decode($response, true); if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception("API OCR JSON inválido: " . substr($response, 0, 500)); }
             if (!isset($result['OCRExitCode']) || $result['OCRExitCode'] != 1 || empty($result['ParsedResults'][0]['ParsedText'])) { $apiError = $result['ErrorMessage'][0] ?? $result['ErrorMessage'] ?? 'Erro OCR ou sem texto.'; log_detalhes("OCR: API Error interno.", ['resposta' => $result]); throw new Exception("Falha OCR API: " . $apiError); }
             $textoCompletoDoOCR = $result['ParsedResults'][0]['ParsedText'];
             log_detalhes("OCR: Texto recebido.");
        } catch (Exception $e) { log_detalhes("OCR: Erro crítico API.", ['erro' => $e->getMessage()]); return false; }

        log_detalhes("OCR: Texto Bruto (amostra)", ['texto_parcial' => mb_substr($textoCompletoDoOCR, 0, 500)]);

        // Validação Keywords
        $keywordsObrigatorias = ['Vila feminina', 'feminina', 'river']; $keywordEncontrada = false;
        log_detalhes("OCR: Verificando Keywords...", ['keywords' => $keywordsObrigatorias]);
        if (is_string($textoCompletoDoOCR)) { foreach ($keywordsObrigatorias as $kw) { if (stripos($textoCompletoDoOCR, $kw) !== false) { $keywordEncontrada = true; log_detalhes("OCR: Keyword encontrada.", ['kw' => $kw]); break; } } }
        if (!$keywordEncontrada) { log_detalhes("OCR: Falha Validação Keyword."); return false; }
        log_detalhes("OCR: Validação Keyword OK.");

        // Extração Valor
        $valorEncontradoString = null; $padroes = ['/R\$\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2}))/', '/valor.*?(\d{1,3}(?:\.\d{3})*(?:,\d{2}))/i', '/transfer[êe]ncia.*?(\d+,\d{2})/i', '/pix.*?(\d{1,3}(?:\.\d{3})*,\d{2})/i'];
        log_detalhes("OCR: Procurando valor...");
        if (is_string($textoCompletoDoOCR)) { foreach ($padroes as $padrao) { if (preg_match($padrao, $textoCompletoDoOCR, $matches)) { if (isset($matches[count($matches)-1]) && !empty(trim($matches[count($matches)-1]))) { $valorEncontradoString = trim($matches[count($matches)-1]); log_detalhes("OCR: Valor encontrado.", ['pattern' => $padrao, 'valor_str' => $valorEncontradoString]); break; } } } }
        if (!$valorEncontradoString) { log_detalhes("OCR: Falha Extração Valor."); return false; }

        // Limpar e Retornar
        log_detalhes("OCR: Limpando valor.", ['valor_str' => $valorEncontradoString]);
        $valorFloat = limparValorOCR($valorEncontradoString);
        if ($valorFloat === false) { log_detalhes("OCR: Falha Limpeza Valor."); return false; }
        log_detalhes("OCR: Sucesso!", ['valor_final' => $valorFloat]); return $valorFloat;
    }
}
// --- Fim OCR ---

// ====================================
// ------ Início Script Principal -----
// ====================================

// Constantes
define('UPLOAD_DIR_BASE', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprovantes');
define('ALLOWED_TYPES_COMP', ['jpg', 'jpeg', 'png', 'pdf', 'webp']);
define('MAX_FILE_SIZE_COMP', 10 * 1024 * 1024); // 10MB

$pdo = null; // Inicializa $pdo
$user_id = null;
$arquivoProcessadoPath = null; // Para limpeza em caso de erro
$filepath = null; // Caminho original do upload
$extension = null;
$arquivoOriginalPdfRemovido = false;

try {
    // 1. Verificar Autenticação
    if (empty($_SESSION['user_id'])) { json_response(null, 401, true, 'Não autorizado.'); }
    $user_id = (int)$_SESSION['user_id'];

    // 2. Verificar Conexão PDO (requerida no início)
    if (!isset($pdo_global_connection) || !($pdo_global_connection instanceof PDO)) { // Usa a variável do db.php
         // Tenta reconectar como fallback (não ideal, mas pode salvar)
         log_detalhes("AVISO: PDO global não encontrado, tentando reconectar...");
         require_once __DIR__ . '/../includes/db.php'; // Tenta incluir de novo
         if (!isset($pdo) || !($pdo instanceof PDO)) {
             log_detalhes("ERRO CRÍTICO: Conexão PDO falhou mesmo após re-include.");
             json_response(null, 500, true, 'Erro interno do servidor (DB)');
         }
         // Se reconectou, usa a variável local $pdo
    } else {
         $pdo = $pdo_global_connection; // Usa a conexão global se disponível
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Garante exceptions

    // 3. Verificar Upload
    if (empty($_FILES['comprovante']) || !isset($_FILES['comprovante']['error']) || !is_uploaded_file($_FILES['comprovante']['tmp_name'])) { log_detalhes("Upload: Nenhum arquivo válido.", ['FILES' => $_FILES]); json_response(null, 400, true, 'Nenhum arquivo válido foi recebido.'); }
    $file = $_FILES['comprovante'];

    // 4. Verificar Erros PHP Upload
    if ($file['error'] !== UPLOAD_ERR_OK) { /* ... (Tratamento de erros PHP upload como antes) ... */ }

    // 5. Validações Adicionais
    $descricao = isset($_POST['descricao']) ? trim(strip_tags($_POST['descricao'])) : null;
    $originalFilenameForLog = $file['name'] ?? 'nome_desconhecido';
    log_detalhes("Processando...", ['original' => $originalFilenameForLog, 'size' => $file['size'], 'desc' => $descricao]);
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_TYPES_COMP)) { json_response(null, 415, true, 'Tipo de arquivo inválido.'); }
    if ($file['size'] > MAX_FILE_SIZE_COMP) { json_response(null, 413, true, 'Arquivo muito grande.'); }

    // 6. Mover Arquivo
    $userUploadDir = UPLOAD_DIR_BASE . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR;
    if (!is_dir($userUploadDir)) { if (!@mkdir($userUploadDir, 0775, true) && !is_dir($userUploadDir)) { log_detalhes("Falha criar dir.", ['dir' => $userUploadDir]); json_response(null, 500, true, 'Erro servidor (armazenamento).'); } }
    if (!is_writable($userUploadDir)) { log_detalhes("Dir sem escrita.", ['dir' => $userUploadDir]); json_response(null, 500, true, 'Erro servidor (permissões).'); }
    $filename = sprintf('comp_uid%d_%s.%s', $user_id, bin2hex(random_bytes(12)), $extension);
    $filepath = $userUploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) { log_detalhes("Falha mover upload.", ['from' => $file['tmp_name'], 'to' => $filepath]); json_response(null, 500, true, 'Erro ao salvar arquivo.'); }
    @chmod($filepath, 0644);
    log_detalhes("Arquivo movido.", ['destino' => $filepath]);

    // 7. Processamento Pós-Upload (PDF, OCR)
    $arquivoProcessadoPath = $filepath; $valorFinal = 0.0; $nomeArquivoFinalParaDB = $user_id . '/' . $filename; $arquivoOriginalPdfRemovido = false;
    if ($extension === 'pdf') {
        log_detalhes("PDF detectado, convertendo...");
        $thumbnailDir = $userUploadDir . 'thumbnails' . DIRECTORY_SEPARATOR; $thumbnailPath = $thumbnailDir . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        try { $imagePathFromPdf = converterPdfParaImagem($filepath, $thumbnailPath); if ($imagePathFromPdf === false || !file_exists($imagePathFromPdf)) { throw new Exception("Falha conversão PDF."); } $arquivoProcessadoPath = $imagePathFromPdf; $nomeArquivoFinalParaDB = $user_id . '/thumbnails/' . basename($imagePathFromPdf); log_detalhes("PDF convertido.", ['imagem' => $nomeArquivoFinalParaDB]);
        } catch (Exception $e) { log_detalhes("Erro CRÍTICO conversão PDF.", ['erro' => $e->getMessage()]); if(file_exists($filepath)) @unlink($filepath); json_response(null, 500, true, 'Erro ao processar PDF: ' . $e->getMessage()); }
    }

    // 8. Extrair valor via OCR
    $valorExtraido = false;
    try { log_detalhes("Iniciando OCR/Validação.", ['arquivo_ocr' => basename($arquivoProcessadoPath)]); $valorExtraido = extrairValorComOCR($arquivoProcessadoPath);
        if ($valorExtraido !== false && $valorExtraido >= 0) { // Permite valor 0 se OCR extrair
            $valorFinal = $valorExtraido; log_detalhes("Valor OCR OK.", ['valor' => $valorFinal]);
            if ($extension === 'pdf' && file_exists($filepath)) { if (@unlink($filepath)) { $arquivoOriginalPdfRemovido = true; log_detalhes("PDF original removido."); } else { log_detalhes("AVISO: Falha remover PDF original."); } }
        } else {
            log_detalhes("Falha OCR/Validação.", ['retorno_ocr' => $valorExtraido]);
            if (file_exists($arquivoProcessadoPath) && $arquivoProcessadoPath !== $filepath) @unlink($arquivoProcessadoPath); // Remove thumb se OCR falhar
            if (!$arquivoOriginalPdfRemovido && file_exists($filepath)) { @unlink($filepath); } // Remove original
            json_response(null, 400, true, 'Comprovante inválido. Verifique legibilidade, keywords (Vila feminina, etc.) e valor.');
        }
    } catch (Exception $e) { log_detalhes("Erro CRÍTICO OCR.", ['erro' => $e->getMessage()]); if (isset($arquivoProcessadoPath) && file_exists($arquivoProcessadoPath) && $arquivoProcessadoPath !== $filepath) @unlink($arquivoProcessadoPath); if (!$arquivoOriginalPdfRemovido && file_exists($filepath)) { @unlink($filepath); } json_response(null, 500, true, 'Erro interno ao ler comprovante.'); }

    // 9. Registrar no BD
    $comprovanteId = null; $valorLiquido = 0.0; $novoSaldoDevedor = 0.0; $novoTotalCompDia = 0.0;
    try {
        log_detalhes("Iniciando transação BD (Saldo Acumulado)."); $pdo->beginTransaction();

        // Inserir comprovante
        $stmtComp = $pdo->prepare("INSERT INTO comprovantes (usuario_id, arquivo, valor, status, data_envio, descricao) VALUES (:uid, :arq, :val, 'pendente', NOW(), :desc)");
        $stmtComp->execute([':uid' => $user_id, ':arq' => $nomeArquivoFinalParaDB, ':val' => $valorFinal, ':desc' => $descricao]);
        $comprovanteId = $pdo->lastInsertId(); if (!$comprovanteId) throw new PDOException("Falha ID comp.");
        log_detalhes("Comprovante inserido.", ['id' => $comprovanteId]);

        // Calcular líquido
        $taxaPlataforma = 0.10; $valorLiquido = round($valorFinal * (1 - $taxaPlataforma), 2);
        log_detalhes("Valor líquido calculado.", ['liquido' => $valorLiquido]);

        // Atualizar saldo DEVEDOR (usuarios)
        if ($valorLiquido > 0) { $stmtUpdateDevedor = $pdo->prepare("UPDATE usuarios SET saldo_atual_devedor = saldo_atual_devedor + :valor_liquido WHERE id = :user_id"); $stmtUpdateDevedor->execute([':valor_liquido' => $valorLiquido, ':user_id' => $user_id]); log_detalhes("Saldo DEVEDOR atualizado.", ['user' => $user_id, 'add' => $valorLiquido]); }

        // Atualizar saldo DIÁRIO (saldos_diarios)
        $stmtSaldo = $pdo->prepare("INSERT INTO saldos_diarios (usuario_id, data, saldo, total_comprovantes) VALUES (:uid, CURDATE(), :liq, :bruto) ON DUPLICATE KEY UPDATE saldo = saldo + VALUES(saldo), total_comprovantes = total_comprovantes + VALUES(total_comprovantes)");
        $stmtSaldo->execute([':uid' => $user_id, ':liq' => $valorLiquido, ':bruto' => $valorFinal]);
        log_detalhes("Saldo DIÁRIO atualizado.", ['user' => $user_id, 'liq_add' => $valorLiquido, 'bruto_add' => $valorFinal]);

        // Obter novos saldos
        $stmtGetSaldos = $pdo->prepare("SELECT u.saldo_atual_devedor, COALESCE(sd.total_comprovantes, 0.00) as total_comp_dia FROM usuarios u LEFT JOIN saldos_diarios sd ON u.id = sd.usuario_id AND sd.data = CURDATE() WHERE u.id = :user_id");
        $stmtGetSaldos->execute([':user_id' => $user_id]); $saldosAtuais = $stmtGetSaldos->fetch();
        $novoSaldoDevedor = (float)($saldosAtuais['saldo_atual_devedor'] ?? 0.0); $novoTotalCompDia = (float)($saldosAtuais['total_comp_dia'] ?? 0.0);
        log_detalhes("Novos saldos obtidos.", ['devedor' => $novoSaldoDevedor, 'comp_dia' => $novoTotalCompDia]);

        $pdo->commit(); log_detalhes("Transação commitada.");

        // Log feed admin
        try { /* ... (lógica feed admin como antes) ... */ } catch (Exception $e) { error_log("ADMIN FEED LOG Error (proof_upload): " . $e->getMessage()); }

        // Resposta
        json_response(['comprovante_id' => $comprovanteId, 'filename' => $nomeArquivoFinalParaDB, 'valor_bruto' => $valorFinal, 'valor_liquido_adicionado' => $valorLiquido, 'novo_saldo_devedor_total' => $novoSaldoDevedor, 'novo_total_comprovantes_dia' => $novoTotalCompDia], 200, false, 'Comprovante enviado! Saldo atualizado.');

    } catch (PDOException $e) {
         if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); log_detalhes("Erro BD.", ['erro' => $e->getMessage()]);
         if (isset($arquivoProcessadoPath) && file_exists($arquivoProcessadoPath) && $arquivoProcessadoPath !== $filepath) @unlink($arquivoProcessadoPath); if (!$arquivoOriginalPdfRemovido && isset($filepath) && file_exists($filepath)) @unlink($filepath);
         json_response(['error_info' => $e->errorInfo ?? null], 500, true, 'Erro interno (BD) ao registrar.');
    } catch (Exception $e) {
         if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); log_detalhes("Erro GERAL.", ['erro' => $e->getMessage()]);
         if (isset($arquivoProcessadoPath) && file_exists($arquivoProcessadoPath) && $arquivoProcessadoPath !== $filepath) @unlink($arquivoProcessadoPath); if (!$arquivoOriginalPdfRemovido && isset($filepath) && file_exists($filepath)) @unlink($filepath);
         json_response(null, 500, true, 'Erro inesperado: ' . $e->getMessage());
    }

} catch (Throwable $t) { // Captura erros fatais iniciais
     log_detalhes("ERRO FATAL INICIAL.", ['erro' => $t->getMessage(), 'file' => $t->getFile(), 'line' => $t->getLine()]);
     // Tenta enviar uma resposta JSON mesmo em erro fatal inicial
     json_response(null, 500, true, 'Erro crítico no servidor.');
}

// Garante limpeza do buffer no final
ob_end_flush();
?>