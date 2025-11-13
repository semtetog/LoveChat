<?php
// logout.php

// 1. INICIA A SESSÃO para acessar os dados do usuário que está saindo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. VERIFICA SE TEM ALGUÉM LOGADO e pega os dados ANTES de destruir
$userIdToLog = null;
$userNameToLog = 'Usuário Desconhecido'; // Nome padrão caso não ache na sessão
if (!empty($_SESSION['user_id'])) {
    $userIdToLog = (int)$_SESSION['user_id'];
    // Tenta pegar o nome da sessão, se não existir, usa o fallback
    if (!empty($_SESSION['user_nome'])) {
        $userNameToLog = $_SESSION['user_nome'];
    } else {
        $userNameToLog = "Usuário #" . $userIdToLog; // Usa o ID se o nome não estiver na sessão
    }

    // 3. TENTA REGISTRAR O EVENTO DE LOGOUT NO FEED DO ADMIN
    try {
        // Inclui a conexão com o banco DEPOIS de verificar se precisa logar
        require_once 'includes/db.php'; // Confirme o caminho!

        // Verifica se $pdo está disponível
        if (isset($pdo)) {
            // Define o fuso horário (consistente com outros scripts)
            date_default_timezone_set('America/Sao_Paulo'); // <-- Ajuste se necessário

            $eventType = 'user_logout'; // Tipo do evento

            // Pega IP (melhor esforço)
            $ipAddress = 'Desconhecido';
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $ipAddress = $_SERVER['HTTP_CLIENT_IP']; }
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR']; }
            elseif (!empty($_SERVER['REMOTE_ADDR'])) { $ipAddress = $_SERVER['REMOTE_ADDR']; }
            $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) ?: 'IP Inválido';

            // Dados do evento
            $eventDataArray = ['ip_address' => $ipAddress];
            $eventDataJson = json_encode($eventDataArray, JSON_UNESCAPED_UNICODE);
            if ($eventDataJson === false) {
                 // Loga erro mas não impede logout
                 error_log("Erro JSON encode [user_logout] para user $userIdToLog: " . json_last_error_msg());
                 $eventDataJson = json_encode(['error' => 'JSON encode failed', 'ip' => $ipAddress]);
            }

            // Insere no feed
            $stmt_feed = $pdo->prepare("
                INSERT INTO admin_events_feed
                (event_type, user_id, event_data, created_at)
                VALUES (:event_type, :user_id, :event_data, NOW())
            ");
            $stmt_feed->execute([
                ':event_type' => $eventType,
                ':user_id' => $userIdToLog, // Usa o ID que pegamos da sessão
                ':event_data' => $eventDataJson
            ]);
            error_log("Admin feed log created for user_logout, user ID: " . $userIdToLog);

        } else {
             error_log("ADMIN FEED LOG Error (user_logout): PDO connection not available in logout.php");
        }

    } catch (PDOException $e) {
        error_log("ADMIN FEED LOG Error (PDO - user_logout) for user $userIdToLog: " . $e->getMessage());
    } catch (Throwable $t) {
        error_log("ADMIN FEED LOG General Error (user_logout) for user $userIdToLog: " . $t->getMessage());
    }
} // Fim do if (!empty($_SESSION['user_id']))

// 4. LIMPA E DESTROI A SESSÃO (sempre faz isso, mesmo se o log falhar)
$_SESSION = array(); // Limpa todas as variáveis de sessão

// Destrói o cookie da sessão no navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão no servidor
session_destroy();

// 5. REDIRECIONA PARA LOGIN
header("Location: login.php");
exit(); // Termina o script
?>