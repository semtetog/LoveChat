<?php
// /admin/api/update_user.php (ATUALIZADO)

ob_start();
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$base_dir = '/home/u689348922/domains/applovechat.com/public_html/'; // AJUSTE SE NECESSÁRIO
$db_path = $base_dir . 'includes/db.php';
$log_path = $base_dir . 'logs/admin_api_errors.log';
ini_set('error_log', $log_path);
ini_set('display_errors', 0); error_reporting(E_ALL);

// Função de resposta JSON
function json_response($data = null, $http_code = 200, $is_error = false, $message = null) {
    $output = ob_get_clean(); if ($output && !$is_error) { error_log("Unexpected output update_user: " . $output); http_response_code(500); echo '{"success":false,"message":"Erro interno (Output)."}'; exit(); } elseif ($output && $is_error) { $message = ($message ? $message . " | " : "") . "Output: " . substr($output, 0, 200); } http_response_code($http_code); $response = ['success' => !$is_error]; if ($message !== null) $response['message'] = $message; if (!$is_error && $data !== null) $response['data'] = $data; elseif ($is_error && $data !== null) $response['details'] = $data; $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE); if ($jsonOutput === false) { error_log("JSON Encode Error update_user: " . json_last_error_msg()); http_response_code(500); echo '{"success":false,"message":"Erro (JSON Encode)."}'; } else { echo $jsonOutput; } exit();
}

if (!file_exists($db_path)) json_response(null, 500, true, "Config DB ausente."); require_once $db_path;
if (!isset($pdo)) json_response(null, 500, true, "Falha DB.");
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) json_response(null, 403, true, "Acesso negado.");
$adminUserId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(null, 405, true, 'Método inválido.');

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) json_response(null, 400, true, 'JSON inválido.');

// Validar e sanitizar dados (incluindo telefone e PIX)
$userIdToUpdate = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$nome = trim(strip_tags($input['nome'] ?? ''));
$email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$isAdmin = filter_var($input['is_admin'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
$isActive = filter_var($input['is_active'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);

// Novos campos
$telefone = preg_replace('/[^\d+]/', '', trim($input['telefone'] ?? '')); // Limpa telefone, mantém +
$tipoChavePix = trim(strip_tags($input['tipo_chave_pix'] ?? ''));
$chavePix = trim(strip_tags($input['chave_pix'] ?? ''));

// Define como NULL se vazio após trim/strip
$telefone = $telefone ?: null;
$tipoChavePix = $tipoChavePix ?: null;
$chavePix = $chavePix ?: null;

// Validações
if (!$userIdToUpdate || $userIdToUpdate <= 0) json_response(null, 400, true, 'ID inválido.');
if (empty($nome)) json_response(null, 400, true, 'Nickname obrigatório.');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(null, 400, true, 'E-mail inválido.');
if ($isAdmin === false) json_response(null, 400, true, 'Valor "Tipo" inválido.');
if ($isActive === false) json_response(null, 400, true, 'Valor "Status" inválido.');
if ($telefone && !preg_match('/^\+\d{10,15}$/', $telefone)) json_response(null, 400, true, 'Formato de telefone inválido (use +55...).');
if (($tipoChavePix && !$chavePix) || (!$tipoChavePix && $chavePix)) json_response(null, 400, true, 'Tipo e Chave PIX devem ser preenchidos juntos ou ambos vazios.');
$allowedPixTypes = ['', 'cpf', 'cnpj', 'email', 'telefone', 'aleatoria']; // Inclui vazio
if (!in_array($tipoChavePix, $allowedPixTypes)) json_response(null, 400, true, 'Tipo de chave PIX inválido.');

// Segurança: Auto-alteração
if ($userIdToUpdate === $adminUserId) {
    if ($isActive === 0) json_response(null, 403, true, 'Você não pode desativar sua própria conta.');
    if ($isAdmin === 0) json_response(null, 403, true, 'Você não pode remover seu próprio status de admin.');
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->exec("SET NAMES 'utf8mb4'");

    // Verificar email duplicado
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id LIMIT 1");
    $stmt_check->execute([':email' => $email, ':id' => $userIdToUpdate]);
    if ($stmt_check->fetch()) json_response(null, 409, true, 'E-mail já em uso.');

    // *** ATUALIZAR USUÁRIO (COM NOVOS CAMPOS) ***
    $stmt_update = $pdo->prepare("
        UPDATE usuarios SET
            nome = :nome, email = :email, telefone = :telefone,
            tipo_chave_pix = :tipo_chave_pix, chave_pix = :chave_pix,
            is_admin = :is_admin, is_active = :is_active
        WHERE id = :id
    ");
    $params = [
        ':nome' => $nome, ':email' => $email, ':telefone' => $telefone, // Pode ser null
        ':tipo_chave_pix' => $tipoChavePix, ':chave_pix' => $chavePix, // Podem ser null
        ':is_admin' => $isAdmin, ':is_active' => $isActive, ':id' => $userIdToUpdate
    ];
    $stmt_update->execute($params);

    if ($stmt_update->rowCount() > 0) {
        error_log("Admin $adminUserId updated user #$userIdToUpdate.");
        json_response(['id' => $userIdToUpdate], 200, false, 'Usuário atualizado.');
    } else {
         $stmt_exists = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?"); $stmt_exists->execute([$userIdToUpdate]);
         if (!$stmt_exists->fetch()) json_response(null, 404, true, 'Usuário não encontrado.');
         else json_response(['id' => $userIdToUpdate], 200, false, 'Nenhuma alteração detectada.');
    }

} catch (PDOException $e) { error_log("DB Error update_user (#{$userIdToUpdate}): " . $e->getMessage()); json_response(['code' => $e->getCode()], 500, true, 'Erro DB.');
} catch (Throwable $t) { error_log("General Error update_user (#{$userIdToUpdate}): " . $t->getMessage()); json_response(null, 500, true, 'Erro servidor.'); }
?>