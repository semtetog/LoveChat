<?php
// check_whatsapp_status.php
header('Content-Type: application/json');

// Sua lógica de verificação aqui
$response = [
    'success' => true,
    'hasWhatsApp' => false // ou true, dependendo da sua lógica
];

echo json_encode($response);