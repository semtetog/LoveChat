<?php
// Arquivo: api/mark_tutorial_seen.php

// Configurações de logging
$apiLogFile = __DIR__ . '/../logs/mark_tutorial_errors.log';
@mkdir(dirname($apiLogFile), 0755, true); // Garante que o diretório existe
file_put_contents($apiLogFile, "[" . date('Y-m-d H:i:s') . "] Endpoint acessado. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido') . "\n", FILE_APPEND);

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents($apiLogFile, "[" . date('Y-m-d H:i:s') . "] ERRO: Método não permitido\n", FILE_APPEND);
    header('Content-Type: application/json', true, 405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Configuração de sessão segura
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Incluir conexão com o banco
require_once __DIR__ . '/../includes/db.php';

// Headers de resposta
header('Content-Type: application/json');

// Verificar autenticação
if (empty($_SESSION['user_id'])) {
    file_put_contents($apiLogFile, "[" . date('Y-m-d H:i:s') . "] ERRO: Usuário não autenticado\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Verificar se o tutorial já estava marcado antes
    $checkStmt = $pdo->prepare("SELECT tutorial_visto FROM usuarios WHERE id = ?");
    $checkStmt->execute([$userId]);
    $alreadySeen = (bool)$checkStmt->fetchColumn();
    
    // Atualizar status
    $updateStmt = $pdo->prepare("UPDATE usuarios SET tutorial_visto = 1, data_ultimo_login = NOW() WHERE id = ?");
    $updateSuccess = $updateStmt->execute([$userId]);
    $rowsAffected = $updateStmt->rowCount();

    if ($updateSuccess) {
        $response = [
            'success' => true,
            'already_seen' => $alreadySeen,
            'message' => $alreadySeen ? 'Status confirmado' : 'Tutorial marcado como visto'
        ];
        
        file_put_contents($apiLogFile, "[" . date('Y-m-d H:i:s') . "] SUCESSO: User $userId - " . ($alreadySeen ? 'Já visto' : 'Marcado como visto') . "\n", FILE_APPEND);
        
        echo json_encode($response);
    } else {
        throw new Exception("Falha na execução do UPDATE");
    }

} catch (PDOException $e) {
    file_put_contents($apiLogFile, "[" . date('Y-m-d H:i:s') . "] ERRO PDO: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
} catch (Exception $e) {
    file_put_contents($apiLogFile, "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
}