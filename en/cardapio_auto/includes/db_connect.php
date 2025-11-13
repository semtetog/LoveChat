<?php
// cardapio_auto/includes/db_connect.php
// Configurações de erro APENAS para este arquivo (logar erros de conexão)
error_reporting(E_ALL);
ini_set('display_errors', 0); // NUNCA mostrar erros de DB em produção
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log'); // Log na pasta raiz

$db_host = 'localhost'; // Ou 127.0.0.1 ou o host correto
$db_name = 'u689348922_database';
$db_user = 'u689348922_usuario';
$db_pass = 'Gameroficial2*';
$charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
     error_log("CRITICAL: Erro de Conexão BD (db_connect.php): " . $e->getMessage());
     // Lança a exceção para que o script que incluiu este arquivo possa tratá-la
     throw new \PDOException("Erro interno do servidor ao conectar ao banco de dados.", (int)$e->getCode(), $e);
}
?>