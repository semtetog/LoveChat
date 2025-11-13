<?php
if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
    echo "Extensões PDO e PDO_MySQL estão HABILITADAS.";
} else {
    echo "ERRO: Extensão PDO ou PDO_MySQL NÃO está habilitada!";
    phpinfo(); // Mostra todas as configurações do PHP
}
?>