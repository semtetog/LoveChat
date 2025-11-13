<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_admin_authenticated() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Acesso não autorizado'
        ]);
        exit();
    }
    return true;
}