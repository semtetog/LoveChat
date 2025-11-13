<?php
// Arquivo: dashboard.php

// Configurações de erro detalhadas (manter)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Log de erros personalizado (manter)
$logFile = __DIR__ . '/dashboard_errors.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script iniciado\n", FILE_APPEND);

// Registrar todos os erros e exceções (manter)
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logFile) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] ERRO: $errstr em $errfile na linha $errline\n";
    file_put_contents($logFile, $errorMsg, FILE_APPEND);
    return false;
});

set_exception_handler(function($e) use ($logFile) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] EXCEÇÃO: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine() . "\n";
    file_put_contents($logFile, $errorMsg, FILE_APPEND);
    die("Ocorreu um erro grave. Os administradores foram notificados.");
});

// Variável para controlar exibição do tutorial - inicializa como false
$primeiroAcesso = false;



try {
    // 1. Sessão (manter)
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
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Sessão iniciada/verificada\n", FILE_APPEND);

    // 2. Autenticação (manter)
    if (empty($_SESSION['user_id'])) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Usuário não autenticado. Redirecionando para login.\n", FILE_APPEND);
        header("Location: login.php");
        exit();
    }

    // 3. Banco de dados (manter)
    $dbFile = __DIR__ . '/includes/db.php';
    if (!file_exists($dbFile)) {
        throw new Exception("Arquivo de configuração do banco de dados não encontrado em: $dbFile");
    }
    require_once $dbFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Arquivo DB incluído.\n", FILE_APPEND);

    if (!isset($pdo)) {
        throw new Exception("Objeto de conexão PDO não está disponível após incluir db.php.");
    }

    $pdo->query("SELECT 1");
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Conexão com o banco de dados testada com sucesso.\n", FILE_APPEND);

    // Inicializar variáveis (manter)
    $saldoHoje = ['saldo' => 0, 'total_comprovantes' => 0];
    $chamadasHoje = ['total' => 0, 'pendentes' => 0, 'em_andamento' => 0, 'concluidas' => 0];
    $chamadas = [];
    $modeloEscolhido = false;
    $modeloEscolhidoId = null;
    $mensagemSucesso = $_SESSION['mensagem_sucesso'] ?? '';
    $mensagemErro = $_SESSION['mensagem_erro'] ?? '';
    $whatsappSolicitado = false;
    $whatsappAprovado = false;
    $whatsappNumero = $_SESSION['user_phone'] ?? '';
    $tutorialJaVistoDB = true; // Assume que já viu

    unset($_SESSION['mensagem_sucesso']);
    unset($_SESSION['mensagem_erro']);

    // Consultar dados do banco de dados
    try {
        // --- Consulta de Usuário MODIFICADA para incluir tutorial_visto ---
        $stmtUser = $pdo->prepare("SELECT nome, email, telefone, avatar, avatar_updated_at, modelo_ia_id, whatsapp_solicitado, whatsapp_aprovado, whatsapp_numero, tutorial_visto FROM usuarios WHERE id = ?");
        $stmtUser->execute([$_SESSION['user_id']]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            // Atualiza dados da sessão se necessário (ex: nome, avatar)
            $_SESSION['user_nome'] = $userData['nome'] ?? 'Usuário';
            $_SESSION['user_email'] = $userData['email'] ?? '';
            $_SESSION['user_phone'] = $userData['telefone'] ?? '';
            $_SESSION['user_avatar'] = $userData['avatar'] ?? DEFAULT_AVATAR;
            $_SESSION['avatar_updated'] = strtotime($userData['avatar_updated_at'] ?? 'now');

            // Modelo IA
            if (!is_null($userData['modelo_ia_id']) && $userData['modelo_ia_id'] > 0) {
                $modeloEscolhido = true;
                $modeloEscolhidoId = (int)$userData['modelo_ia_id'];
            }

            // WhatsApp
            $whatsappSolicitado = (bool)$userData['whatsapp_solicitado'];
            $whatsappAprovado = (bool)$userData['whatsapp_aprovado'];
            $whatsappNumero = $userData['whatsapp_numero'] ? $userData['whatsapp_numero'] : ($_SESSION['user_phone'] ?? '');

            // --- LÓGICA DO TUTORIAL ---
            if (isset($userData['tutorial_visto'])) {
                $tutorialJaVistoDB = (bool)$userData['tutorial_visto'];
            } else {
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] AVISO: Coluna 'tutorial_visto' não encontrada nos dados do usuário ID " . $_SESSION['user_id'] . ". Assumindo que o tutorial foi visto.\n", FILE_APPEND);
                $tutorialJaVistoDB = true;
            }
            $primeiroAcesso = !$tutorialJaVistoDB;

        } else {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERRO CRÍTICO: Usuário ID " . $_SESSION['user_id'] . " não encontrado no banco de dados.\n", FILE_APPEND);
            session_unset();
            session_destroy();
            header("Location: login.php?error=user_not_found");
            exit();
        }

        // Consultar saldo (manter)
        $stmtSaldo = $pdo->prepare("SELECT saldo, total_comprovantes FROM saldos_diarios WHERE usuario_id = ? AND data = CURDATE()");
        $stmtSaldo->execute([$_SESSION['user_id']]);
        $saldoResult = $stmtSaldo->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC
        if ($saldoResult) {
            $saldoHoje = [
                'saldo' => (float)($saldoResult['saldo'] ?? 0),
                'total_comprovantes' => (float)($saldoResult['total_comprovantes'] ?? 0)
            ];
        }

        // Consultar chamadas resumo (manter)
        $stmtChamadasResumo = $pdo->prepare("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas
            FROM chamadas
            WHERE usuario_id = ?"); // REMOVED DATE FILTER: AND DATE(data_hora) = CURDATE() - Let JS/SSE handle live counts
        $stmtChamadasResumo->execute([$_SESSION['user_id']]);
        $chamadasResult = $stmtChamadasResumo->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC
        if ($chamadasResult) {
            $chamadasHoje = [
                'total' => (int)($chamadasResult['total'] ?? 0),
                'pendentes' => (int)($chamadasResult['pendentes'] ?? 0),
                'em_andamento' => (int)($chamadasResult['em_andamento'] ?? 0),
                'concluidas' => (int)($chamadasResult['concluidas'] ?? 0)
            ];
        }

        // Lista de chamadas (manter) - Load initial list for non-JS or initial view
        $stmtListaChamadas = $pdo->prepare("SELECT * FROM chamadas WHERE usuario_id = ? ORDER BY data_hora DESC LIMIT 10");
        $stmtListaChamadas->execute([$_SESSION['user_id']]);
        $chamadas = $stmtListaChamadas->fetchAll(PDO::FETCH_ASSOC); // Use FETCH_ASSOC

    } catch (PDOException $e) {
        $errorMsg = "[" . date('Y-m-d H:i:s') . "] ERRO PDO na consulta de dados: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $errorMsg, FILE_APPEND);
        $mensagemErro = "Ocorreu um erro ao carregar seus dados. Por favor, tente recarregar a página.";
        $primeiroAcesso = false;
    }

} catch (Exception $e) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] ERRO GRAVE no bloco principal: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMsg, FILE_APPEND);
    $_SESSION['mensagem_erro'] = "Ocorreu um erro inesperado no sistema. Tente novamente ou contate o suporte.";
    header("Location: login.php?error=critical");
    exit();
}

// Mapeamentos de status (manter)
$statusChamadaLabels = [
    'pending'       => 'Pendente',
    'in_progress'   => 'Em Andamento',
    'completed'     => 'Concluída',
    'cancelled'     => 'Cancelada',
    'pendente'      => 'Pendente',
    'em_andamento'  => 'Em Andamento',
    'concluida'     => 'Concluída',
    'cancelada'     => 'Cancelada',
    'desconhecido'  => 'Desconhecido'
];

$statusChamadaClasses = [
    'pending'       => 'status-pendente',
    'in_progress'   => 'status-andamento',
    'completed'     => 'status-concluida',
    'cancelled'     => 'status-cancelada',
    'pendente'      => 'status-pendente',
    'em_andamento'  => 'status-andamento',
    'concluida'     => 'status-concluida',
    'cancelada'     => 'status-cancelada',
    'desconhecido'  => 'status-desconhecido' // Fallback
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Love Chat - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <style>
        :root {
            --primary: #ff007f;
            --primary-light: rgba(255, 0, 127, 0.1);
            --secondary: #fc5cac;
            --dark: #1a1a1a;
            --darker: #121212;
            --darkest: #0a0a0a;
            --light: #e0e0e0;
            --lighter: #f5f5f5;
            --success: #00cc66;
            --warning: #ffcc00;
            --danger: #ff3333;
            --info: #0099ff;    /* Azul para em andamento */
            --gray: #2a2a2a;
            --gray-light: #3a3a3a;
            --gray-dark: #1e1e1e;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.6);
             --primary-rgb: 255, 0, 127;
             --danger-rgb: 255, 51, 51;
             --warning-rgb: 255, 204, 0;
             --success-rgb: 0, 204, 102;
             --lime-green: #96ff00;
             --lime-green-rgb: 150, 255, 0;
             --border-color: rgba(255, 255, 255, 0.1);
             --border-color-light: rgba(255, 255, 255, 0.07);
             --border-color-medium: rgba(255, 255, 255, 0.15);
             --transition-speed-fast: 0.2s;
             --transition-speed-med: 0.4s;
             --transition-cubic: cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent; /* Good for mobile taps */
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--darker);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

       /* Adicione isso na seção de estilos */
       .file-upload-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }

        .file-upload-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: var(--gray-dark);
            border: 2px dashed rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-primary);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-button:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--primary);
        }

        .file-upload-button i {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .file-name-display {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Melhorias para a seção de comprovantes */
        #comprovantes-section .form-container {
            background: var(--dark);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        #comprovantes-section .section-title {
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .highlight {
            color: transparent;
            background: linear-gradient(1deg, #96ff00, #00ff00);
            -webkit-background-clip: text;
            background-clip: text;
            text-shadow: 0 2px 5px rgba(150, 255, 0, 0.3);
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { text-shadow: 0 0 5px rgba(150, 255, 0, 0.5); }
            to { text-shadow: 0 0 15px rgba(150, 255, 0, 0.8); }
        }

        /* Layout Principal */
        .app-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: relative;
        }

        /* Header Mobile */
        .app-header {
            display: none; /* Hidden by default, shown in media query */
            padding: 1rem;
            background-color: var(--dark);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            height: 4rem; /* Fixed height */
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            display: flex; /* Ensures icon is centered */
            align-items: center;
            justify-content: center;
        }

        .app-logo {
            height: 2rem; /* Adjust as needed */
            width: auto;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

/* 1. Container Principal da Sidebar */
.sidebar {
    width: 17.5rem;
    background: linear-gradient(180deg, var(--dark), var(--darkest));
    position: fixed;
    top: 0;
    left: 0;
    /* IMPORTANTE: A altura da sidebar principal NÃO é mais 100vh direta. */
    /* Ela será controlada implicitamente pelo conteúdo */
    /* OU podemos definir um max-height para segurança */
    max-height: 100vh;
    height: 100vh; /* Vamos manter 100vh aqui para o posicionamento */
    transition: transform 0.3s ease;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    overflow: hidden; /* Impede rolagem aqui */
}

 .sidebar-header {
    padding: 1.5rem 1.25rem;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    flex-shrink: 0; /* NÃO encolhe */
    /* Vamos definir uma altura estimada ou fixa se possível.
       Se o padding for 1.5rem em cima/baixo + altura do logo + margin-bottom,
       podemos estimar. Ex: 3rem (padding) + ~2rem (logo+margin) = ~5rem.
       Use a altura real inspecionada se possível. */
    /* Exemplo: height: 5.5rem; */
    /* Se a altura for variável, esta abordagem é mais complexa */
    position: relative;
    z-index: 1;
}

.sidebar-logo {
    width: 8.75rem;
    margin-bottom: 1rem; /* Mantido do original */
}
.sidebar-scrollable-content {
    flex: 1 1 0; /* Equivalente a: flex-grow: 1, flex-shrink: 1, flex-basis: 0 */
    /* Isso força o elemento a tentar ocupar o espaço restante */
    overflow-y: auto; /* Habilita a rolagem vertical APENAS AQUI */
    -webkit-overflow-scrolling: touch; /* Rolagem suave no iOS */
    min-height: 0; /* Essencial para flexbox com overflow */
    /* Remova padding-bottom daqui se causar problemas, adicione ao último elemento se necessário */
    /* padding-bottom: 1rem; */
}




.sidebar a.user-profile {
    display: flex;
    align-items: center;
    padding: 1.25rem;
    margin-bottom: 0.5rem;
    text-decoration: none !important;
    color: inherit;
    border-radius: 8px;
    transition: background-color 0.3s ease, border-left-color 0.3s ease;
    border-left: 3px solid transparent;
    flex-shrink: 0; /* NÃO deixa o perfil encolher */
}
        /* Hover effect only on desktop */
        @media (hover: hover) and (pointer: fine) {
             .sidebar a.user-profile:hover {
                background-color: rgba(255, 255, 255, 0.05);
                border-left-color: var(--primary);
            }
        }
        /* Ensure children have no underline and correct color */
        .sidebar a.user-profile *,
        .sidebar a.user-profile .user-info h3,
        .sidebar a.user-profile .user-info p {
            text-decoration: none !important;
            color: inherit; /* Inherit color from parent link */
        }
        .sidebar a.user-profile .user-info h3 { color: var(--text-primary); }
        .sidebar a.user-profile .user-info p { color: var(--text-secondary); }


        .user-avatar {
            width: 3.125rem;
            height: 3.125rem;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 2px solid var(--primary);
            background-color: var(--dark); /* Placeholder bg */
            flex-shrink: 0;
        }

        .user-info {
            overflow: hidden; /* Prevent text overflow issues */
        }

        .user-info h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-info p {
            font-size: 0.75rem;
        }

        .sidebar-menu {
    padding: 0.5rem 0; /* Padding vertical */
    /* NÃO precisa de flex-grow aqui, o wrapper já controla o espaço */
    /* NÃO precisa de overflow aqui */
}

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            white-space: nowrap; /* Prevent text wrapping */
        }

        .menu-item i {
            margin-right: 0.75rem;
            font-size: 1.125rem;
            width: 1.5rem;
            text-align: center;
            color: var(--text-secondary);
            flex-shrink: 0;
        }

        .menu-item span {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .menu-item:hover, .menu-item.active {
            background-color: rgba(255, 255, 255, 0.05);
            border-left-color: var(--primary);
        }

        .menu-item.active {
            font-weight: 600;
        }

        .menu-item.active i {
            color: var(--primary);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            margin-top: 1.25rem; /* Pushes to bottom if menu scrolls */
            color: var(--danger);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            flex-shrink: 0; /* Prevent logout from shrinking */
             white-space: nowrap;
        }
        .logout-btn {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.25rem;
    margin-top: 1rem; /* Espaço acima do botão */
    color: var(--danger);
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
    flex-shrink: 0; /* NÃO deixa o botão encolher */
    white-space: nowrap;
    /* REMOVA margin-top: auto; */
}
        .logout-btn i {
            margin-right: 0.75rem;
            font-size: 1.125rem;
            width: 1.5rem;
            text-align: center;
        }

        .logout-btn:hover {
            background-color: rgba(var(--danger-rgb), 0.1);
        }
        
     .sidebar-scrollable-content::-webkit-scrollbar { width: 6px; }
.sidebar-scrollable-content::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.1); border-radius: 3px;}
.sidebar-scrollable-content::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
.sidebar-scrollable-content::-webkit-scrollbar-thumb:hover { background: var(--secondary); }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 17.5rem; /* Default margin for desktop */
            padding: 2rem;
            transition: margin-left 0.3s ease;
            overflow-y: auto; /* Allow content area to scroll if needed */
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--dark);
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 0.25rem 0.9375rem rgba(0, 0, 0, 0.2);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden; /* Prevent content spill */
        }

        /* --- Card Click Sensitivity Improvement --- */
        .card-saldo,
        .card-chamadas,
        .whatsapp-card {
            cursor: pointer; /* Explicitly set cursor for touch feedback */
        }
         /* Prevent hover sticking on touch */
        @media (hover: hover) and (pointer: fine) {
            .card:hover {
                transform: translateY(-0.3125rem);
                box-shadow: 0 0.5rem 1.5625rem rgba(0, 0, 0, 0.3);
            }
             .card-saldo:hover,
             .card-chamadas:hover {
                 transform: translateY(-3px);
                 box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            }
        }
        /* --- End Card Click Improvement --- */


        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .card-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .card-icon.primary { background: var(--primary-light); color: var(--primary); }
        .card-icon.success { background: rgba(var(--success-rgb), 0.1); color: var(--success); }
        .card-icon.warning { background: rgba(var(--warning-rgb), 0.1); color: var(--warning); }
        .card-icon.info { background: rgba(0, 153, 255, 0.1); color: var(--info); }

        .card-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
            word-break: break-word; /* Prevent long numbers/text overflow */
        }

        .card-footer {
            font-size: 0.75rem;
            color: var(--text-secondary);
            word-break: break-word;
        }

        /* Sections */
        .section {
            background: var(--dark);
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.25rem 0.9375rem rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .section-title {
            display: flex;
            align-items: center;
            font-size: 1.125rem;
            margin-bottom: 1.25rem;
            color: var(--text-primary);
        }

        .section-title i {
            margin-right: 0.625rem;
            color: var(--primary);
        }

        /* Overlay for menu mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none; /* Hidden by default */
            opacity: 0; /* Start transparent */
            transition: opacity 0.3s ease; /* Fade transition */
        }
        .sidebar-overlay.active {
             display: block;
             opacity: 1;
        }


        /* Responsividade */
        @media (max-width: 992px) {
            .app-header {
                display: block; /* Show mobile header */
            }

            .main-content {
                margin-left: 0; /* Remove sidebar margin */
                padding-top: 5rem; /* Add padding for fixed header (4rem + 1rem buffer) */
            }

            .sidebar {
                transform: translateX(-100%); /* Hide sidebar off-screen */
                /* width: 17.5rem; */ /* Already set */
            }

            .sidebar.active {
                transform: translateX(0); /* Slide in */
            }
            /* .sidebar-overlay.active is handled above */
            .desktop-only { display: none; } /* Hide desktop notification bell */
            
             /* --- ADD THIS RULE --- */
    .notification-bell.desktop-only {
        display: none; /* Hide the notification bell meant only for desktop */
    }

        }
        
        
        
        @media (max-width: 992px) {
    /* ... (outros estilos mobile que já existem aqui) ... */

    .sidebar .sidebar-header .sidebar-logo {
        width: 6rem; /* <<< Diminui a largura (ajuste o valor como preferir) */
        /* ou use max-width: 6rem; */
        margin-bottom: -1rem; /* <<< Diminui a margem inferior */
    }

     /* Opcional: Ajustar o padding do header se necessário */
     .sidebar .sidebar-header {
         padding-top: 1.25rem; */
         padding-bottom: 1.25rem; */
         /* Talvez não precise mudar se a logo ainda estiver lá, só menor */
    }


    /* ... (continuação dos outros estilos mobile) ... */
}



        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr 1fr; /* 2 columns */
            }
             /* Make file upload button smaller */
             .file-upload-button {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
             /* Reduce form padding */
            #comprovantes-section .form-container { padding: 1rem; }

            .saldo-tooltip-content { padding: 1.5rem; }
            .saldo-tooltip-header { flex-direction: column; text-align: center; gap: 0.8rem; }
            .saldo-tooltip-header i { margin-bottom: 0.5rem; }

            /* --- WhatsApp Modal Height Fix --- */
            .whatsapp-modal-content {
                padding: 1.5rem;
                max-height: 85vh; /* Limit height */
                overflow-y: auto; /* Enable scroll if needed */
            }
            .whatsapp-modal-header { flex-direction: column; text-align: center; gap: 0.8rem; }
            .whatsapp-modal-header i { margin-bottom: 0.5rem; }
            /* --- End WhatsApp Modal Fix --- */

             .models-grid { grid-template-columns: 1fr 1fr; }
             .models-header h2 { font-size: 1.6rem; }
             .model-selection-guide { flex-direction: column; text-align: center; }

            .packages-container { grid-template-columns: 1fr; }
            .package-card { padding: 1.5rem; }
            .package-card.recommended::after { font-size: 0.6rem; padding: 0.3rem 1.2rem; top: -10px; }
            .package-leads { font-size: 1.6rem; }
            .package-price { font-size: 1.4rem; }

            .form-actions { flex-direction: column; }
            .btn { width: 100%; padding: 0.75rem; }

            .modal-content { max-height: 90vh; border-radius: 12px; }
            .modal-header { padding: 1rem; }
            .modal-title { font-size: 1.2rem; }
            .notification-item { padding: 1rem; }
            .notification-avatar { width: 38px; height: 38px; }
            .notification-message { padding-left: 0; margin-top: 0.75rem; }
            .notifications-actions { padding: 1rem; }

            /* Responsive Table Adjustments (Copied from original) */
            /* ... (Keep the entire responsive table CSS block here) ... */
             /* ============================================ */
            /* == Layout Responsivo da Tabela de Chamadas == */
            /* ============================================ */
             .chamadas-details #chamadasHeader {
                display: flex; flex-direction: column; align-items: center;
                gap: 0.8rem; margin-bottom: 1.5rem;
            }
             .chamadas-details #chamadasHeader h3 {
                width: 100%; text-align: center; margin-bottom: 0; font-size: 1.15rem;
            }
             .chamadas-details #chamadasHeader div[style*="gap: 0.5rem"] {
                width: 100%; justify-content: center; gap: 1rem;
            }
             .chamadas-details #chamadasHeader .btn-sm {
                padding: 0.6rem 1.2rem; font-size: 0.85rem;
            }
             .chamadas-details .chamadas-table { border: 0; margin-top: 0; }
             .chamadas-details .chamadas-table thead { display: none; }
             .chamadas-details .chamadas-table tr {
                display: flex; flex-direction: column; margin-bottom: 1.25rem;
                border: 1px solid rgba(255, 255, 255, 0.07); border-radius: 10px;
                padding: 0; background: linear-gradient(145deg, var(--dark), var(--gray-dark));
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2); overflow: hidden;
            }
             .chamadas-details .chamadas-table td {
                display: flex; justify-content: space-between; align-items: center;
                text-align: right; font-size: 0.9rem; padding: 0.8rem 1.25rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05); min-height: 2.5em;
                flex-wrap: wrap; gap: 0.5rem;
            }
             .chamadas-details .chamadas-table tr td:last-of-type { border-bottom: 0; }
             .chamadas-details .chamadas-table tr td[data-label="Duração"] {
                 border-bottom: 1px solid rgba(255, 255, 255, 0.05);
             }
             .chamadas-details .chamadas-table td::before {
                content: attr(data-label); font-weight: 600; color: var(--text-secondary);
                font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;
                margin-right: 1rem; text-align: left; flex-shrink: 0;
            }
             .chamadas-details .chamadas-table td[data-label="Status"] {
                order: -1; background-color: rgba(255, 255, 255, 0.04);
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
             .chamadas-details .chamadas-table td[data-label="Status"]::before {
                 font-size: 0.8rem; color: var(--text-primary);
            }
             .chamadas-details .chamadas-table td[data-label="Status"] .status-badge { margin-left: auto; }
             .chamadas-details .chamadas-table td[data-label="Cliente"] {
                 font-weight: 500; color: var(--text-primary); text-decoration: none;
             }
             .chamadas-details .chamadas-table td[data-label="Cliente"] a {
                color: inherit !important; text-decoration: none !important;
            }
             .chamadas-details .chamadas-table td[data-label="Duração"]::after {
                 content: ' min'; font-size: 0.85em; color: var(--text-secondary); margin-left: 0.2rem;
             }
        }


        @media (max-width: 576px) {
            .main-content {
                padding: 1.25rem 1rem;
                padding-top: 4.5rem; /* Adjust for smaller header/spacing */
            }
            .dashboard-cards {
                grid-template-columns: 1fr; /* 1 column */
            }
            .content-header h1 { font-size: 1.25rem; }
            .card { padding: 1rem; }
            .section { padding: 1rem; }
            .confirm-content { padding: 1.5rem; }
            .confirm-actions { flex-direction: column; }
            .confirm-btn { width: 100%; }

             .modal-header { padding: 0.75rem 1rem; }
             .modal-title { font-size: 1.1rem; }
             .notification-item { padding: 0.9rem; }
             .notification-title { font-size: 0.9rem; }
             .notification-message { font-size: 0.8rem; }
             .empty-notifications { padding: 2rem 1rem; }
             .empty-notifications i { font-size: 2.5rem; }

             .models-grid { grid-template-columns: 1fr; }
             .models-header h2 { font-size: 1.5rem; }

        
        }

        /* Efeitos de transição */
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(0.625rem); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scrollbar personalizada */
        ::-webkit-scrollbar { width: 0.375rem; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 0.1875rem; }

        /* Modal de Saldo */
        /* ... (Keep Saldo Modal CSS as is) ... */
         .saldo-tooltip-modal {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            z-index: 1000; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease; padding: 1rem;
        }
        .saldo-tooltip-modal.active { opacity: 1; visibility: visible; }
        .saldo-tooltip-content {
            background: linear-gradient(145deg, var(--dark), var(--darker)); border-radius: 16px;
            width: 100%; max-width: 480px; padding: 2rem; border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); position: relative;
            animation: modalFadeIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }
        .saldo-tooltip-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .saldo-tooltip-header i { font-size: 1.8rem; color: var(--primary); }
        .saldo-tooltip-header h4 { font-size: 1.3rem; color: var(--text-primary); margin: 0; }
        .saldo-info-item { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.2rem; }
        .saldo-info-icon {
            width: 3rem; height: 3rem; border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 1.2rem;
        }
        .saldo-info-text { flex: 1; }
        .saldo-info-text strong { display: block; color: var(--text-primary); font-size: 1rem; margin-bottom: 0.2rem; }
        .saldo-info-text span { color: var(--text-secondary); font-size: 0.85rem; }
        .saldo-tooltip-note {
            background: rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 1rem;
            margin-top: 1.5rem; display: flex; gap: 0.8rem;
        }
        .saldo-tooltip-note i { color: var(--primary); font-size: 1.2rem; }
        .saldo-tooltip-note p { color: var(--text-secondary); font-size: 0.85rem; margin: 0; line-height: 1.5; }
        .close-modal { /* Applied to multiple modals */
            position: absolute; top: 1rem; right: 1rem; background: none; border: none;
            color: var(--text-secondary); font-size: 1.5rem; cursor: pointer; transition: all 0.2s ease;
            width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; z-index: 10; /* Ensure above content */
        }
        .close-modal:hover { color: var(--primary); background: rgba(255, 255, 255, 0.1); }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }


        /* Formulário Geral */
        .form-container { background: var(--gray-dark); border-radius: 8px; padding: 1.5rem; margin-top: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-primary); font-size: 0.875rem; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 0.75rem; background: var(--gray); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px; color: var(--text-primary); font-size: 0.875rem; transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary); outline: none; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }

        /* Botões */
        .btn {
            padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer;
            transition: all 0.2s ease; border: none; display: inline-flex; /* Use inline-flex */
            align-items: center; justify-content: center; gap: 0.5rem; /* Gap between icon/text */
            font-family: 'Montserrat', sans-serif; /* Ensure font */
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #e0006f; }
        .btn-secondary { background: var(--gray); color: var(--text-primary); }
        .btn-secondary:hover { background: var(--gray-light); }
        .btn i { line-height: 1; /* Better icon vertical alignment */ }

        /* Pacotes de Leads */
        /* ... (Keep Packages CSS as is) ... */
        .packages-container {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem; margin-top: 1.5rem;
        }
        .package-card {
            background: linear-gradient(145deg, var(--gray-dark), var(--darker)); border-radius: 16px;
            padding: 1.75rem; border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); display: flex; flex-direction: column;
            height: 100%; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); position: relative; overflow: hidden;
        }
        .package-card:hover {
            transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border-color: rgba(var(--primary-rgb), 0.3);
        }
        .package-card.recommended {
            position: relative; border: 2px solid var(--primary); background: var(--dark);
            box-shadow: 0 8px 25px rgba(var(--primary-rgb), 0.3); transform: translateY(-3px);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); overflow: visible;
            z-index: 1; border-radius: 16px;
        }
        .package-card.recommended::after {
            content: 'MAIS RECOMENDADO'; position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;
            font-family: 'Montserrat', sans-serif; font-size: 0.7rem; font-weight: 800; padding: 0.4rem 1.5rem;
            border-radius: 20px; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
            text-transform: uppercase; letter-spacing: 1px; z-index: 2; white-space: nowrap;
        }
        .package-card.recommended::before {
            content: ''; position: absolute; inset: -5px;
            background: radial-gradient(circle at 20% 30%, rgba(255, 60, 140, 0.4) 0%, transparent 50%), radial-gradient(circle at 80% 70%, rgba(255, 100, 180, 0.3) 0%, transparent 50%);
            z-index: -1; animation: fluid-motion 15s linear infinite; filter: blur(15px); opacity: 0.9; border-radius: 18px;
        }
        @keyframes fluid-motion { 0% { background-position: 0% 0%, 100% 100%; } 50% { background-position: 100% 100%, 0% 0%; } 100% { background-position: 0% 0%, 100% 100%; } }
        .package-card.recommended:hover { transform: translateY(-6px); box-shadow: 0 12px 35px rgba(var(--primary-rgb), 0.5); }
        .package-card.recommended:hover::before { filter: blur(20px); opacity: 1; }
        .package-card.recommended .package-badge {
            background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;
            font-weight: 800; padding: 0.3rem 0.8rem; box-shadow: 0 2px 10px rgba(var(--primary-rgb), 0.3); border-radius: 6px;
        }
        .package-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; position: relative; z-index: 2; }
        .package-title { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0; letter-spacing: 0.5px; }
        .package-badge {
            background: rgba(255, 255, 255, 0.1); color: var(--primary); font-size: 0.65rem; font-weight: 600;
            padding: 0.25rem 0.75rem; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .package-content { flex: 1; margin-bottom: 1.5rem; }
        .package-leads {
            font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent; text-shadow: 0 2px 10px rgba(var(--primary-rgb), 0.15);
        }
        .package-price {
            font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1.5rem;
            display: flex; align-items: flex-start; line-height: 1;
        }
        .price-currency { font-size: 0.9em; margin-right: 2px; color: var(--text-secondary); align-self: flex-start; }
        .price-value { font-size: 1.8em; line-height: 1; }
        .price-decimal { font-size: 0.9em; align-self: flex-end; margin-bottom: 0.1em; color: var(--text-secondary); }
        .package-features { margin: 1.5rem 0 0; padding: 0; }
        .feature-item { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; }
        .feature-item i { color: var(--primary); font-size: 0.9rem; margin-top: 0.15rem; flex-shrink: 0; }
        .package-btn {
            width: 100%; padding: 0.85rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3); margin-top: auto;
        }
        .package-btn:hover {
            background: linear-gradient(135deg, #e0006f, #fc5cac); transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.4);
        }
        .package-btn:active { transform: translateY(0); }
        .package-card.recommended .package-btn { background: linear-gradient(135deg, var(--primary), var(--secondary)); }
        .package-card.recommended .package-btn:hover { background: linear-gradient(135deg, #e0006f, #fc5cac); }
        .package-card.premium { border: 1px solid rgba(255, 215, 0, 0.3); box-shadow: 0 4px 25px rgba(255, 215, 0, 0.2); }
        .package-card.premium .package-badge { background: linear-gradient(45deg, #FFD700, #FFA500); color: #000; }
        .package-card.premium .package-btn { background: linear-gradient(45deg, #FFD700, #FFA500); box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3); color: #000; }
        .package-card.premium .package-btn:hover { background: linear-gradient(45deg, #FFC000, #FF8C00); }
        .package-card.premium .feature-item i { color: #FFD700; }
        .package-card.premium .package-leads { background: linear-gradient(45deg, #FFD700, #FFA500); -webkit-background-clip: text; background-clip: text; }


        /* Modelos IA */
        /* ... (Keep Models CSS as is) ... */
         .models-selection-container { max-width: 1000px; margin: 0 auto; padding: 0 1rem; }
        .models-header { text-align: center; margin-bottom: 2rem; }
        .models-header h2 {
            font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;
            background: linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .models-header p { color: var(--text-secondary); font-size: 1rem; }
        .models-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .model-card {
            background: linear-gradient(145deg, var(--gray-dark), var(--darker)); border-radius: 12px; overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex; flex-direction: column;
        }
        .model-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); border-color: var(--primary); }
        .model-media { position: relative; padding-top: 100%; overflow: hidden; }
        .model-avatar { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .model-card:hover .model-avatar { transform: scale(1.05); }
        .model-info { padding: 1.5rem; text-align: center; flex: 1; display: flex; flex-direction: column; }
        .model-name {
            font-size: 1.3rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-primary);
            background: linear-gradient(to right, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .model-actions { margin-top: auto; }
        .model-btn {
            font-family: 'Montserrat', sans-serif; width: 100%; padding: 0.75rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border: none; border-radius: 8px;
            font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        }
        .model-btn:hover {
            background: linear-gradient(135deg, #e0006f, #fc5cac); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
        }
        .model-card.selected { border: 2px solid var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2); }
        .model-card.selected .model-btn { background: var(--success); }
        .model-card.selected .model-btn:hover { background: #00b359; }
        .model-selection-guide {
            background: rgba(var(--primary-rgb), 0.1); border-radius: 12px; padding: 1.5rem; margin-top: 2rem;
            display: flex; align-items: center; gap: 1rem; border: 1px dashed var(--primary);
        }
        .guide-icon {
            background: rgba(var(--primary-rgb), 0.2); width: 3rem; height: 3rem; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 1.5rem; color: var(--primary); flex-shrink: 0;
        }
        .guide-content h4 { font-size: 1.1rem; color: var(--text-primary); margin-bottom: 0.5rem; }
        .guide-content p { font-size: 0.9rem; color: var(--text-secondary); margin: 0; }


        /* Modal de confirmação */
        /* ... (Keep Confirmation Modal CSS as is) ... */
        .confirm-modal {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center;
            justify-content: center; opacity: 0; transition: opacity 0.3s ease; padding: 1rem;
        }
        .confirm-modal.show { display: flex; opacity: 1; }
        .confirm-content {
            background: linear-gradient(145deg, var(--dark), var(--darker)); border-radius: 16px; width: 100%; max-width: 500px;
            padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.1);
            transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            animation: modalFadeIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }
        .confirm-modal.show .confirm-content { transform: translateY(0); }
        .confirm-title {
            font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--text-primary); text-align: center;
            background: linear-gradient(to right, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent; text-shadow: 0 2px 10px rgba(var(--primary-rgb), 0.15);
        }
        .confirm-message { margin-bottom: 2rem; color: var(--text-secondary); line-height: 1.6; text-align: center; font-size: 1rem; }
        .confirm-actions { display: flex; gap: 1rem; margin-top: 2rem; }
        .confirm-btn {
            flex: 1; padding: 0.85rem; border-radius: 8px; font-weight: 600; cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border: none;
        }
        .confirm-btn.confirm {
            background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;
            box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
        }
        .confirm-btn.confirm:hover {
            background: linear-gradient(135deg, #e0006f, #fc5cac); transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.4);
        }
        .confirm-btn.cancel {
            background: rgba(255, 255, 255, 0.05); color: var(--text-primary); border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .confirm-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); transform: translateY(-2px);
        }


        /* Tabela de chamadas */
        .chamadas-table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        .chamadas-table th, .chamadas-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .chamadas-table th { font-size: 0.875rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; }
        .chamadas-table td { font-size: 0.875rem; color: var(--text-primary); }

        .status-badge {
             display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem;
             font-weight: 600; text-transform: capitalize; border: 1px solid transparent;
         }
         .status-pendente { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3); }
         .status-andamento { background-color: rgba(0, 153, 255, 0.1); color: var(--info); border-color: rgba(0, 153, 255, 0.3); }
         .status-concluida { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3); }
         .status-cancelada { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3); }
         .status-desconhecido { background-color: var(--gray-light); color: var(--text-secondary); border-color: var(--border-color); }

        /* Detalhes das chamadas */
        .chamadas-details { display: none; margin-top: 1.5rem; background: var(--gray-dark); border-radius: 8px; padding: 1.5rem; }
        .chamadas-details.show { display: block; }
        .chamadas-details h3 { font-size: 1.125rem; margin-bottom: 1rem; color: var(--text-primary); }

         /* Chamadas Details Header Buttons (Specific Styling) */
         .chamadas-details #chamadasHeader .btn-sm {
             padding: 0.4rem 0.8rem; font-size: 0.8rem; line-height: 1.2; display: inline-flex;
             align-items: center; justify-content: center; gap: 0.4rem; border: none; border-radius: 6px;
             cursor: pointer; transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
         }
         .chamadas-details #chamadasHeader .btn-sm i { margin: 0; font-size: 0.9em; }
         .chamadas-details #chamadasHeader .btn-sm:hover { transform: translateY(-1px); }
         .chamadas-details #chamadasHeader .btn-sm:focus { outline: none; }
         .chamadas-details #chamadasHeader .btn-secondary { background-color: var(--gray); color: var(--text-primary); }
         .chamadas-details #chamadasHeader .btn-secondary:hover { background-color: var(--gray-light); }
         /* Apply secondary style to clear button */
        .chamadas-details #chamadasHeader #limparChamadasBtn { background-color: var(--gray); color: var(--text-primary); }
        .chamadas-details #chamadasHeader #limparChamadasBtn:hover { background-color: var(--gray-light); color: var(--text-primary); }

        /* Preview Container */
        .preview-container {
            background: var(--gray-dark); border-radius: 8px; padding: 1.25rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .preview-content { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
        .preview-image { max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); }
        .preview-file-icon { font-size: 4rem; color: var(--primary); }
        .preview-file-info { text-align: center; color: var(--text-secondary); }

         /* Card Updating Animation */
        .card.updating { animation: pulseUpdate 1s ease; position: relative; }
        .card.updating::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.1); border-radius: inherit; pointer-events: none;
        }
        @keyframes pulseUpdate { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }

        /* WhatsApp Card Styles */
        .whatsapp-card {
            /* cursor: pointer; /* Already added globally */
            position: relative; overflow: hidden; border: 1px solid rgba(37, 211, 102, 0.3);
            animation: pulseWhatsApp 2s infinite;
        }
        .whatsapp-card .card-icon { background: rgba(37, 211, 102, 0.1); color: #25D366; }
        .whatsapp-card .card-value { color: #25D366; }
        .whatsapp-card .card-footer { color: rgba(37, 211, 102, 0.8); }
        @keyframes pulseWhatsApp { 0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); } 100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); } }

        /* WhatsApp Modal */
        .whatsapp-modal {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.85);  backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
            z-index: 1000; align-items: center; justify-content: center; opacity: 0;
            transition: opacity 0.3s ease; padding: 1rem;
        }
        .whatsapp-modal.active { display: flex; opacity: 1; }
        .whatsapp-modal-content {
            background: rgba(18, 140, 126, 0.15); border-radius: 16px; width: 100%; max-width: 500px;
            padding: 2rem; border: 1px solid rgba(37, 211, 102, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1);
             position: relative; /* Needed for absolute positioned close button */
             /* max-height and overflow added in media query */
        }
        .whatsapp-modal.active .whatsapp-modal-content { transform: translateY(0); }
        .whatsapp-modal-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .whatsapp-modal-header i { font-size: 2rem; color: #25D366; }
        .whatsapp-modal-header h4 {
            font-size: 1.5rem; color: white; margin: 0; background: linear-gradient(to right, #25D366, #128C7E);
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .whatsapp-info-item { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.2rem; }
     .whatsapp-info-icon {
    min-width: 3rem; /* Adiciona min-width para evitar compressão */
    width: 3rem; 
    height: 3rem; 
    min-height: 3rem; /* Adiciona min-height para evitar compressão */
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    font-size: 1.2rem; 
    background: rgba(37, 211, 102, 0.1); 
    color: #25D366;
    flex-shrink: 0; /* Impede que o ícone encolha em containers flex */
    box-sizing: border-box; /* Garante que padding não afete as dimensões */
}
        .whatsapp-info-text strong { display: block; color: white; font-size: 1rem; margin-bottom: 0.2rem; }
        .whatsapp-info-text span { color: rgba(255, 255, 255, 0.7); font-size: 0.85rem; }
        .whatsapp-modal-note {
            background: rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 1rem; margin: 1.5rem 0;
            display: flex; gap: 0.8rem; border-left: 3px solid #25D366;
        }
        .whatsapp-modal-note i { color: #25D366; font-size: 1.2rem; }
        .whatsapp-modal-note p { color: rgba(255, 255, 255, 0.8); font-size: 0.85rem; margin: 0; line-height: 1.5; }
        .whatsapp-confirm-btn {
            width: 100%; padding: 1rem; background: linear-gradient(135deg, #25D366, #128C7E); color: white;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            font-size: 1rem; margin-top: 1rem;
        }
        .whatsapp-confirm-btn:hover { background: linear-gradient(135deg, #20bd5a, #0e7a68); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3); }
        
        @media (max-width: 480px) {
    .whatsapp-info-icon {
        width: 3rem !important;
        height: 3rem !important;
        border-radius: 50% !important;
    }
}


         /* Alert Messages */
         .alert-message {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
            border: 1px solid transparent;
        }
        .alert-message i { font-size: 1.2em; }
        .alert-success {
            background-color: rgba(var(--success-rgb), 0.1);
            color: var(--success);
            border-color: rgba(var(--success-rgb), 0.3);
        }
         .alert-error, .alert-danger { /* Combine error/danger */
            background-color: rgba(var(--danger-rgb), 0.1);
            color: var(--danger);
            border-color: rgba(var(--danger-rgb), 0.3);
        }
        .alert-warning {
            background-color: rgba(var(--warning-rgb), 0.1);
            color: var(--warning);
            border-color: rgba(var(--warning-rgb), 0.3);
        }
        .alert-info {
            background-color: rgba(0, 153, 255, 0.1);
            color: var(--info);
            border-color: rgba(0, 153, 255, 0.3);
        }


    </style>
    
    <style>
/* Notification Modal - COMPLETE STYLES */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s ease;
    overflow-y: auto;
    padding: 20px;
    box-sizing: border-box;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
    opacity: 1;
}

.modal-content {
    background: linear-gradient(145deg, var(--dark), var(--darkest));
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    max-height: 85vh;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    transform: translateY(0);
    transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1);
    opacity: 0;
    border: 1px solid rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.modal.show .modal-content {
    opacity: 1;
    transform: translateY(0);
}

.modal-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(to right, var(--dark), var(--gray-dark));
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.modal-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    letter-spacing: 0.5px;
    background: linear-gradient(to right, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 2px 10px rgba(255, 0, 127, 0.15);
}

.close-modal {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: var(--text-secondary);
    font-size: 1.4rem;
    cursor: pointer;
    transition: all 0.25s ease;
    line-height: 1;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    margin-left: auto;
}

.close-modal:hover {
    color: var(--text-primary);
    background: rgba(255, 0, 127, 0.2);
    transform: rotate(90deg);
}

.notifications-list {
    flex: 1;
    overflow-y: auto;
    padding: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--primary) rgba(255, 255, 255, 0.05);
}

/* Notification Item */
.notification-item {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    animation: fadeIn 0.4s ease forwards;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3px;
    height: 100%;
    background: var(--gray);
    transition: all 0.3s ease;
}

.notification-item.unread {
    background: linear-gradient(90deg, rgba(255, 0, 127, 0.03), transparent);
}

.notification-item.unread::before {
    background: linear-gradient(to bottom, var(--primary), var(--secondary));
}

.notification-item:hover {
    background: rgba(255, 255, 255, 0.03);
}

.notification-item.reading {
    animation: readingAnimation 0.5s ease forwards;
}

@keyframes readingAnimation {
    0% { background: linear-gradient(90deg, rgba(255, 0, 127, 0.1), transparent); }
    100% { background: transparent; }
}

.notification-header {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    gap: 12px;
}

.notification-avatar-container {
    position: relative;
    flex-shrink: 0;
}

.notification-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary);
    background-color: var(--dark);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.notification-item.unread .notification-avatar {
    box-shadow: 0 0 0 3px rgba(255, 0, 127, 0.3);
}

.notification-title-wrapper {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
    line-height: 1.4;
    margin-bottom: 0.25rem;
    word-break: break-word;
    hyphens: auto;
}

.notification-admin {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.notification-admin i {
    color: var(--primary);
    font-size: 0.7rem;
}

.notification-time {
    font-size: 0.72rem;
    color: var(--text-secondary);
    background: rgba(255, 255, 255, 0.05);
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: 500;
    display: inline-block;
    white-space: nowrap;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    transition: all 0.3s ease;
}

.notification-item.unread .notification-time {
    background: rgba(255, 0, 127, 0.1);
    color: var(--primary);
}

.notification-message {
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.6;
    margin-top: 0.5rem;
    padding-left: 54px;
    word-break: break-word;
    hyphens: auto;
}

.notification-message a {
    color: var(--primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.notification-message a:hover {
    text-decoration: underline;
}

/* Notification Actions */
.notifications-actions {
    padding: 1rem 1.5rem;
    background: linear-gradient(to right, var(--dark), var(--gray-dark));
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 0.75rem;
    position: sticky;
    bottom: 0;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.notifications-actions button {
    flex: 1;
    padding: 0.75rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.notifications-actions button.delete-all {
    background: linear-gradient(135deg, var(--gray), var(--gray-dark));
    color: var(--text-primary);
    border-radius: 8px;
    padding: 0.8rem;
    font-weight: 600;
    font-size: 0.9rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
}

.notifications-actions button.delete-all:hover {
    background: linear-gradient(135deg, var(--gray-dark), var(--gray));
    transform: translateY(-2px);
    border-color: rgba(255, 0, 127, 0.3);
}

.notifications-actions button.delete-all i {
    font-size: 0.9rem;
}

/* Empty State */
.empty-notifications {
    padding: 3rem 1.5rem;
    text-align: center;
    color: var(--text-secondary);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-notifications i {
    font-size: 3.5rem;
    color: var(--gray-light);
    margin-bottom: 1.5rem;
    opacity: 0.3;
}

.empty-notifications p {
    font-size: 0.95rem;
    margin: 0;
    max-width: 280px;
    line-height: 1.6;
}

.empty-notifications .btn-retry {
    margin-top: 1.5rem;
    padding: 0.5rem 1.25rem;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 6px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.empty-notifications .btn-retry:hover {
    background: rgba(255, 255, 255, 0.2);
}

.empty-notifications.error i {
    color: var(--danger);
    opacity: 0.5;
}

/* Loading State */
.loading-notifications {
    padding: 2rem;
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.9rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.loading-notifications:after {
    content: "";
    width: 24px;
    height: 24px;
    border: 3px solid rgba(255, 255, 255, 0.1);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Scrollbar Styles */
.notifications-list::-webkit-scrollbar {
    width: 6px;
}

.notifications-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02);
    border-radius: 3px;
}

.notifications-list::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.notifications-list::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Animation Enhancements */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.notification-item:nth-child(1) { animation-delay: 0.05s; }
.notification-item:nth-child(2) { animation-delay: 0.1s; }
.notification-item:nth-child(3) { animation-delay: 0.15s; }
.notification-item:nth-child(4) { animation-delay: 0.2s; }
.notification-item:nth-child(5) { animation-delay: 0.25s; }

/* Responsive Design */
@media (max-width: 768px) {
    .modal {
        padding: 10px; /* Mantém o padding externo */
    }

    .modal-content {
        /* max-height: 90vh;  <-- Comente ou remova esta linha se existir */
        max-height: 80vh; /* <<< ALTURA MÁXIMA REDUZIDA AQUI (era 85vh ou 90vh) */
        border-radius: 12px;
        width: calc(100% - 20px); /* Garante que use a largura disponível menos o padding do .modal */
        max-width: 500px; /* Pode reduzir um pouco se quiser, mas max-height é mais efetivo */
    }

    .modal-header {
        padding: 1rem; /* Mantido */
        flex-direction: row;
        align-items: center;
    }

    .modal-title {
        font-size: 1.2rem; /* Mantido */
        flex: 1;
    }

    .close-modal {
        position: static; /* Mantido */
        margin-left: auto;
    }

    .notification-item {
        padding: 0.9rem 1rem; /* <<< Reduzido levemente o padding horizontal */
    }

    .notification-avatar {
        width: 38px; /* Mantido */
        height: 38px;
    }

    .notification-message {
        padding-left: 0; /* Mantido */
        margin-top: 0.75rem;
    }

    .notifications-actions {
        padding: 1rem; /* Mantido */
    }
}

@media (max-width: 480px) {
    .modal-header {
        padding: 0.75rem 1rem; /* Mantido */
    }

    .modal-title {
        font-size: 1.1rem; /* Mantido */
    }

    .notification-item {
        padding: 0.8rem 0.9rem; /* <<< Reduzido mais um pouco para telas bem pequenas */
    }

    .notification-title {
        font-size: 0.9rem; /* Mantido */
    }

    .notification-message {
        font-size: 0.8rem; /* Mantido */
    }

    .empty-notifications {
        padding: 2rem 1rem; /* Mantido */
    }

    .empty-notifications i {
        font-size: 2.5rem; /* Mantido */
    }

     /* Opcional: Pode definir uma altura máxima ainda menor para telas muito pequenas */
    .modal-content {
        max-height: 78vh; /* Exemplo: um pouco menor ainda */
    }
}
/* Notification Bell Styles - Modernizado */
.notification-bell {
    position: relative;
    cursor: pointer;
    font-size: 1.4rem;
    color: var(--text-primary);
    padding: 0.65rem;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border-radius: 50%;
    width: 3rem;
    height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transform-origin: center center;
}

.notification-bell:hover {
    background-color: var(--primary-light);
    color: var(--primary);
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(255, 0, 127, 0.2);
}

.notification-bell.has-unread {
    animation: gentlePulse 2s infinite;
}

.notification-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background-color: var(--danger);
    color: white;
    border-radius: 50%;
    width: 1.4rem;
    height: 1.4rem;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    transform: translate(25%, -25%);
    border: 2px solid var(--dark);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: all 0.2s ease;
    z-index: 1;
}

.notification-bell:hover .notification-badge {
    transform: translate(25%, -25%) scale(1.05);
}

/* Bell Animations */
@keyframes gentleShake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(5deg); }
    75% { transform: rotate(-5deg); }
}

@keyframes gentlePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.notification-bell.shake {
    animation: gentleShake 0.5s ease-in-out;
}

.notification-bell.pulse {
    animation: gentlePulse 2s infinite;
}

.desktop-only {
    display: none;
}

@media (min-width: 993px) {
    .desktop-only {
        display: flex;
    }
}

  .notification-time.unread {
            background: rgba(255, 0, 127, 0.1) !important;
            color: var(--primary) !important;
            transition: none !important;
        }
        
        .notification-item.unread {
            animation: none !important;
        }
        
        .notification-item {
            transition: background-color 0.3s ease;
        }
        
        .toast-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toast-notification.show {
            opacity: 1;
        }
        
        .toast-notification.error {
            background: rgba(255, 51, 51, 0.2);
            border-color: rgba(255, 51, 51, 0.3);
        }
        .notification-item.read {
    animation: none !important;
    transition: none !important;
}

.notification-item .notification-time {
    color: var(--text-secondary) !important;
    background: rgba(255, 255, 255, 0.05) !important;
    transition: none !important;
}

.notification-item.unread .notification-time {
    color: var(--primary) !important;
    background: rgba(255, 0, 127, 0.1) !important;
}

.notification-item.reading .notification-time {
    color: var(--text-secondary) !important;
    background: rgba(255, 255, 255, 0.05) !important;
}


/* Define a animação do sino tocando */
@keyframes bellRingPulse {
    0% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
    25% {
        /* Pulo maior e rotação inicial */
        transform: scale(1.3) rotate(-10deg);
        opacity: 0.9;
    }
    50% {
        /* Volta um pouco e rotaciona para o outro lado */
        transform: scale(0.9) rotate(10deg);
        opacity: 1;
    }
    75% {
        /* Quase de volta ao normal, pequena rotação */
        transform: scale(1.1) rotate(-5deg);
        opacity: 1;
    }
    100% {
        /* Estado final normal */
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

/* Classe para ativar a animação no sino */
.notification-bell.animate-ring {
    /* Aplica a animação definida acima */
    animation-name: bellRingPulse;
    /* Duração da animação - ajuste se quiser mais rápido/lento */
    animation-duration: 0.7s; /* Era 0.6s, aumentei um pouco para suavidade */
    /* Curva de aceleração para suavidade */
    animation-timing-function: cubic-bezier(0.25, 0.1, 0.25, 1); /* Curva suave */
    /* Quantas vezes a animação roda (só uma vez por toque) */
    animation-iteration-count: 1;
    /* Define o ponto de rotação/escala para a base do sino */
    transform-origin: bottom center;
}

/* (Opcional) Estilo para o badge durante a animação, se necessário */

.notification-bell.animate-ring .notification-badge {
    transform: translate(25%, -25%) scale(1.1); // Exemplo: fazer o badge pular um pouco também
}
/* ========================================================== */
/* === PIX CARD - ALINHAMENTO FORÇADO E GLOW AMARELO === */
/* ========================================================== */

#pixCard {
    cursor: pointer;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Ícone Amarelo/Laranja (mantido) */
.card-icon.warning {
    background: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

/* --- Valor (Chave Pix) - Alinhamento SUPER Forçado --- */
#pixCard .card-value.pix-key-value {
    /* 1. Garante que ocupa a largura e respeita o padding pai */
    width: 100%;                /* Ocupa toda a largura */
    box-sizing: border-box;     /* Padding dentro da largura */
    display: block !important;  /* Força comportamento de bloco */

    /* 2. Define o Padding Esquerdo (COPIE DO HEADER/OUTRO VALOR) */
    /* Se o .card tem padding: 1.25rem, então o padding aqui deve ser 0 ou pequeno */
    /* Se o .card-header tem padding: 1.25rem, o valor aqui deve alinhar */
    padding-left: 0rem !important; /* <<< COMECE COM 0, ou o padding interno desejado */
    padding-right: 1rem !important; /* Espaço à direita */
    padding-top: 0.5rem !important; /* Espaçamento vertical */
    padding-bottom: 0.5rem !important; /* Espaçamento vertical */
    margin: 0 !important;        /* Remove margens */

    /* 3. Alinhamento do Texto e Estilo */
    text-align: left !important; /* Força alinhamento */
    word-break: break-all;
    font-size: 1.1rem;
    font-weight: 600;
    line-height: 1.4;
    color: var(--text-primary) !important;
    -webkit-text-fill-color: var(--text-primary);
    text-decoration: none !important;
    pointer-events: none;
    flex-grow: 1; /* Ocupa espaço flexível */
}

/* Anti-link iOS (mantido) */
#pixCard .card-value.pix-key-value a {
     color: inherit !important;
     text-decoration: none !important;
     cursor: inherit;
     pointer-events: none;
}

/* --- Footer - Alinhamento SUPER Forçado --- */
#pixCard .card-footer {
    /* 1. Garante largura e box-sizing */
    width: 100%;
    box-sizing: border-box;
    display: block !important;

    /* 2. Define o Padding Esquerdo (MESMO DO VALOR ACIMA) */
    padding-left: 0rem !important; /* <<< COMECE COM 0, ou o padding interno desejado */
    padding-right: 1rem !important; /* Espaço à direita */
    padding-top: 0.5rem !important; /* Espaçamento vertical */
    padding-bottom: 0.75rem !important; /* Espaçamento vertical */
    margin: 0 !important;        /* Remove margens */

    /* 3. Alinhamento do Texto e Estilo */
    text-align: left !important; /* Força alinhamento */
    font-size: 0.8rem;
    color: var(--text-secondary);
    flex-shrink: 0;
    margin-top: auto;
}

/* === ANIMAÇÃO AMARELA COM GLOW === */
#pixCard.copied-success {
    animation: smoothYellowGlow 0.7s ease-out;
}

@keyframes smoothYellowGlow {
    0% {
        border-color: rgba(255, 255, 255, 0.05);
        /* Sombra externa normal, sombra interna amarela invisível */
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2),
                    inset 0 0 0px 0px rgba(var(--warning-rgb), 0);
        transform: scale(1);
    }
    50% {
        border-color: rgba(var(--warning-rgb), 0.7); /* Borda fica amarela */
        /* Sombra externa levemente aumentada + Glow (sombra interna amarela) */
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.25),
                    inset 0 0 12px 3px rgba(var(--warning-rgb), 0.3); /* Ajuste o 'spread' (3px) e a opacidade (0.3) do glow */
        transform: scale(1.015); /* Pequeno pulso */
    }
    100% {
        border-color: rgba(255, 255, 255, 0.05);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2),
                    inset 0 0 0px 0px rgba(var(--warning-rgb), 0);
        transform: scale(1);
    }
}

/* <<< SUBSTITUA A REGRA .notification-bell > i.fas.fa-bell EXISTENTE POR ESTA >>> */
.notification-bell > i.fas.fa-bell {
    position: absolute; /* Posicionamento absoluto */
    top: 50%;           /* Começa no meio verticalmente */
    left: 50%;          /* Começa no meio horizontalmente */
    transform: translate(-50%, -50%); /* Puxa de volta metade do seu tamanho */
    /* Reseta outros estilos que podem interferir */
    margin: 0;
    padding: 0;
    line-height: 1;     /* Importante para cálculo da altura do transform */
    display: block;     /* Necessário para transform funcionar corretamente */
    width: auto;        /* Tamanho intrínseco do ícone */
    height: auto;       /* Tamanho intrínseco do ícone */
}
/* ================================================================== */
/* === ESTILOS MINHA MODELO - v5 PREMIUM GALERIA === */
/* ================================================================== */

/* Reset da Section Pai */
#minha-modelo-section {
    padding: 0 !important;
    background: var(--darker) !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 2rem;
}

/* --- Hero Section v5 --- */
.modelo-hero-v5 {
    position: relative;
    padding: 2.5rem 1.5rem;
    text-align: center;
    overflow: hidden;
    /* Cor tema da modelo aplicada via style inline */
    --hero-gradient-start: rgba(var(--modelo-theme-rgb, 255, 0, 127), 0.25);
    --hero-gradient-mid: rgba(var(--modelo-theme-rgb, 255, 0, 127), 0.05);
    --hero-gradient-end: rgba(var(--darker-rgb, 18, 18, 18), 0);
}

.modelo-hero-v5-bg { /* Fundo gradiente e efeito sutil */
    position: absolute;
    inset: 0;
    background: linear-gradient(170deg, var(--hero-gradient-start) 0%, var(--hero-gradient-mid) 50%, var(--hero-gradient-end) 100%), var(--dark);
    z-index: 0;
    /* Efeito de brilho sutil (opcional) */
   /*  &::after {
        content: '';
        position: absolute;
        inset: -50%;
        background: radial-gradient(circle, rgba(var(--modelo-theme-rgb), 0.1) 0%, transparent 50%);
        animation: rotateGlowSlow 25s linear infinite;
        opacity: 0.5;
    }
    @keyframes rotateGlowSlow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } */
}

.modelo-hero-v5-content {
    position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center;
}

.modelo-hero-v5-avatar-wrapper {
    position: relative; margin-bottom: 1rem;
}

.modelo-hero-v5-avatar {
    width: 110px; height: 110px; border-radius: 50%; object-fit: cover; display: block;
    border: 4px solid var(--darker); /* Borda escura para destacar do fundo */
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

/* Bolinha de status verde */
.modelo-hero-v5-status-dot {
    position: absolute;
    bottom: 5px; right: 5px;
    width: 18px; height: 18px;
    background-color: var(--success); /* Verde sucesso */
    border-radius: 50%;
    border: 3px solid var(--darker); /* Contorno com fundo */
    box-shadow: 0 0 8px rgba(var(--success-rgb), 0.5);
}

.modelo-hero-v5-name {
    font-size: 1.8rem; font-weight: 700; color: var(--text-primary);
    margin: 0 0 0.3rem 0; letter-spacing: 0.5px;
    /* Cor tema aplicada sutilmente */
    /* text-shadow: 0 0 20px rgba(var(--modelo-theme-rgb), 0.3); */
}

.modelo-hero-v5-desc {
    font-size: 0.95rem; color: var(--text-secondary); max-width: 480px; line-height: 1.6; opacity: 0.9;
}

/* --- Área de Conteúdo e Tabs v5 --- */
.modelo-content-area-v5 {
    padding: 1.5rem; /* Padding interno para o conteúdo abaixo do hero */
    background: var(--darker);
}

.materials-tabs-v5 {
    display: flex;
    gap: 0.5rem; /* Espaço entre tabs */
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color-light);
    overflow-x: auto; /* Permite scroll horizontal em telas pequenas */
    padding-bottom: 1px; /* Evita que o indicador cubra a borda */
     /* Estilo da barra de rolagem horizontal */
     &::-webkit-scrollbar { height: 3px; }
     &::-webkit-scrollbar-track { background: transparent; }
     &::-webkit-scrollbar-thumb { background: var(--gray); border-radius: 3px; }
}

.tab-btn-v5 {
    appearance: none; border: none; background: none; cursor: pointer;
    font-family: inherit; font-size: 0.95rem; font-weight: 500;
    color: var(--text-secondary); /* Cor inativa */
    padding: 0.75rem 1rem; /* Padding interno */
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    white-space: nowrap; /* Impede quebra de linha */
    transition: color 0.25s ease;
}
.tab-btn-v5 i {
    font-size: 1.1em; /* Ícone um pouco maior */
    opacity: 0.7; /* Ícone inativo mais sutil */
    transition: color 0.25s ease, opacity 0.25s ease;
}
.tab-btn-v5 span { /* Apenas o texto */ }

/* Indicador da tab ativa */
.tab-btn-v5 .tab-indicator {
    position: absolute;
    bottom: -1px; /* Alinha com a borda inferior */
    left: 0; right: 0;
    height: 3px;
    background-color: var(--primary); /* Cor do indicador */
    border-radius: 3px 3px 0 0;
    transform: scaleX(0); /* Começa escondido */
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: center;
}

.tab-btn-v5:hover {
    color: var(--text-primary);
}
.tab-btn-v5:hover i {
    opacity: 1;
}

.tab-btn-v5.active {
    color: var(--text-primary); /* Cor ativa */
    font-weight: 600;
}
.tab-btn-v5.active i {
    color: var(--primary); /* Cor do ícone ativo */
    opacity: 1;
}
.tab-btn-v5.active .tab-indicator {
    transform: scaleX(1); /* Mostra indicador */
}

/* --- Área de Exibição Dinâmica v5 --- */
.materials-display-area-v5 {
    min-height: 200px; /* Altura mínima enquanto carrega */
    position: relative;
}

.material-loading-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
    font-size: 1rem;
    gap: 0.75rem;
    position: absolute;
    inset: 0;
    background: var(--darker); /* Cobre conteúdo antigo */
    opacity: 1;
    transition: opacity 0.3s ease;
}
.material-loading-placeholder.hidden {
    opacity: 0;
    pointer-events: none;
}

/* --- Estilos Específicos por Tipo de Material --- */

/* Grid de Fotos */
.material-photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
    animation: fadeInItems 0.5s ease-out forwards;
}
.photo-thumbnail {
    aspect-ratio: 1 / 1;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    border: 1px solid var(--border-color-light);
}
.photo-thumbnail img { display: block; width: 100%; height: 100%; object-fit: cover; }
.photo-thumbnail::after { /* Overlay sutil no hover */
    content: '\f06e'; /* Ícone de olho FontAwesome */
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute; inset: 0; background: rgba(0,0,0,0.5);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; opacity: 0; transition: opacity 0.25s ease;
}
.photo-thumbnail:hover { transform: scale(1.04); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
.photo-thumbnail:hover::after { opacity: 1; }

/* Lista de Vídeos, Áudios, Roteiros */
.material-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
     animation: fadeInItems 0.5s ease-out forwards;
}
.material-list-item {
    background: var(--dark);
    border-radius: 8px;
    padding: 1rem 1.25rem;
    border: 1px solid var(--border-color-light);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
.material-list-item:hover {
    background-color: rgba(255,255,255,0.03);
    border-color: var(--border-color);
}

.material-item-icon {
    flex-shrink: 0;
    font-size: 1.5rem;
    width: 30px; text-align: center;
    opacity: 0.8;
}
.material-item-icon.videos { color: var(--info); }
.material-item-icon.audios { color: var(--lime-green); }
.material-item-icon.scripts { color: var(--warning); }

.material-item-info {
    flex-grow: 1;
    min-width: 0; /* Para text-overflow funcionar */
}
.material-item-name {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.1rem;
}
.material-item-url { /* Opcional: mostrar URL sutilmente */
    font-size: 0.75rem;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: 0.7;
}

/* Player de Áudio Inline */
.material-audio-player {
    height: 35px; /* Altura do player */
    max-width: 250px; /* Largura máxima */
    border-radius: 20px;
    filter: invert(0.9) sepia(0.3) saturate(0.8) hue-rotate(280deg); /* Tenta estilizar para combinar */
    opacity: 0.8;
    transition: opacity 0.2s ease;
}
.material-list-item:hover .material-audio-player {
    opacity: 1;
}

.material-item-actions {
    flex-shrink: 0;
    margin-left: 1rem;
}

/* Botões de Ação (Genérico para lista) */
.btn-material-action {
    appearance: none; border: none; background: none; cursor: pointer;
    font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 0.8rem; /* Menor padding */
    border-radius: 6px;
    font-size: 0.8rem; font-weight: 500;
    transition: all 0.2s ease;
    background: var(--gray);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
}
.btn-material-action i { font-size: 0.9em; }
.btn-material-action:hover {
    background: var(--gray-light);
    color: var(--text-primary);
    border-color: var(--border-color-medium);
}

/* Texto indicando nenhuma material */
.material-empty-message {
    text-align: center;
    color: var(--text-secondary);
    padding: 3rem 1rem;
    font-style: italic;
     animation: fadeInItems 0.5s ease-out forwards;
}

@keyframes fadeInItems {
     from { opacity: 0; transform: translateY(5px); }
     to { opacity: 1; transform: translateY(0); }
}

/* --- Lightbox v5 --- */
.photo-lightbox-v5 {
    position: fixed; inset: 0; background-color: rgba(10, 10, 10, 0.92);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    z-index: 2000; display: none; /* Controlado por JS */ align-items: center; justify-content: center;
    flex-direction: column; padding: 1rem; opacity: 0; transition: opacity 0.3s ease;
}
.photo-lightbox-v5.show { opacity: 1; }

.photo-lightbox-v5-close {
    position: absolute; top: 1.25rem; right: 1.25rem; background: rgba(255,255,255,0.1); border: none;
    color: white; font-size: 1.6rem; line-height: 1; width: 2.8rem; height: 2.8rem; border-radius: 50%;
    cursor: pointer; z-index: 2015; transition: all 0.2s ease;
}
.photo-lightbox-v5-close:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }

.photo-lightbox-v5-nav {
    position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.1); border: none; color: white; font-size: 1.6rem; width: 3.2rem; height: 3.2rem;
    border-radius: 50%; cursor: pointer; z-index: 2014; transition: all 0.2s ease;
    display: flex; align-items: center; justify-content: center; opacity: 0.7;
}
.photo-lightbox-v5-nav.prev { left: 1.25rem; }
.photo-lightbox-v5-nav.next { right: 1.25rem; }
.photo-lightbox-v5-nav:hover { background: rgba(255,255,255,0.15); opacity: 1; transform: translateY(-50%) scale(1.05); }
.photo-lightbox-v5-nav.hidden { display: none; opacity: 0; pointer-events: none; }

.photo-lightbox-v5-image-wrapper {
    max-width: 90vw; max-height: 70vh; /* Menor para dar espaço */ margin-bottom: 1.5rem; /* Mais espaço */
    display: flex; justify-content: center; align-items: center;
}
.photo-lightbox-v5-image-wrapper img {
    display: block; max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain;
    border-radius: 6px; box-shadow: 0 8px 30px rgba(0,0,0,0.5);
    animation: lightboxFadeIn 0.4s ease forwards;
}
@keyframes lightboxFadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

.photo-lightbox-v5-actions { display: flex; align-items: center; gap: 1rem; color: rgba(255,255,255,0.9); }
.photo-lightbox-v5-counter { font-size: 0.9rem; background: rgba(0,0,0,0.4); padding: 0.3rem 0.7rem; border-radius: 6px; }
.btn-lightbox-v5-download { /* Botão de download no lightbox */
    appearance: none; border: none; background: none; cursor: pointer; font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
    transition: all 0.25s ease; background: var(--primary); color: white;
}
.btn-lightbox-v5-download:hover { background: #e0006f; box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.3); }
.btn-lightbox-v5-download i { margin-right: 0.4rem;}

/* Ajustes Mobile Finais */
@media (max-width: 768px) {
     .modelo-hero-v5 { padding: 2rem 1rem; }
     .modelo-hero-v5-avatar { width: 90px; height: 90px; }
     .modelo-hero-v5-status-dot { width: 15px; height: 15px; bottom: 3px; right: 3px; border-width: 2px; }
     .modelo-hero-v5-name { font-size: 1.6rem; }
     .modelo-content-area-v5 { padding: 1.5rem 1rem; }
     .materials-tabs-v5 { gap: 0.2rem; }
     .tab-btn-v5 { padding: 0.6rem 0.8rem; font-size: 0.9rem; }
     .materials-grid-v4, .material-list { gap: 0.8rem; } /* Menor gap nos itens */
     .material-list-item { flex-direction: column; align-items: flex-start; padding: 0.8rem; }
     .material-item-actions { margin-left: 0; margin-top: 0.8rem; width: 100%; }
     .btn-material-action { width: 100%; justify-content: center; }
     .photo-lightbox-v5-nav { width: 2.8rem; height: 2.8rem; font-size: 1.3rem; opacity: 0.8; }
     .photo-lightbox-v5-close { width: 2.5rem; height: 2.5rem; font-size: 1.4rem; top: 1rem; right: 1rem;}
     .photo-lightbox-v5-actions { flex-direction: column; gap: 0.8rem; }
}

/* Visibilidade Mobile/Desktop (Mantido) */
.mobile-only { display: none !important; }
.desktop-only { display: inline-flex !important; } /* ou flex dependendo do botão */
@media (max-width: 768px) {
    .mobile-only { display: inline-flex !important; }
    .desktop-only { display: none !important; }
}


/* ========================================================== */
/* === ESTILOS PARA GRID DE VÍDEOS (Minha Modelo) === */
/* ========================================================== */

.material-video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); /* Colunas um pouco maiores que fotos */
    gap: 1rem;
    animation: fadeInItems 0.5s ease-out forwards; /* Reutiliza animação */
}

/* ========================================================== */
/* === ESTILOS MINHA MODELO - THUMBNAILS DE VÍDEO & CORREÇÕES === */
/* ========================================================== */

/* --- Estilização do Thumbnail na Grade de Vídeos --- */
.video-thumbnail {
    aspect-ratio: 16 / 9; /* Proporção comum de vídeo */
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    position: relative; /* Para posicionar overlay */
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    border: 1px solid var(--border-color-light);
    background-color: var(--gray-dark); /* Fundo escuro para fallback */
    display: flex; /* Para alinhar nome e overlay */
    flex-direction: column;
    justify-content: flex-end; /* Nome fica na parte inferior */
    text-decoration: none; /* Remove sublinhado se for link */
    color: inherit; /* Herda cor do texto */
}

/* Estilo da IMAGEM do Thumbnail */
.video-thumbnail-img {
    position: absolute; /* Cobre todo o card */
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover; /* Cobre o espaço mantendo proporção */
    z-index: 1; /* Fica abaixo do overlay e nome */
    transition: transform 0.3s ease;
}

/* Ícone de Fallback (se a imagem falhar ou não existir) */
.video-thumbnail.img-error .video-thumbnail-icon,
.video-thumbnail:not(:has(.video-thumbnail-img)) .video-thumbnail-icon {
    /* Mostra o ícone se a classe img-error estiver presente OU se não houver tag img */
    display: block;
    text-align: center;
    font-size: 2.5rem; /* Tamanho do ícone */
    color: var(--info); /* Cor azul (info) */
    opacity: 0.4;
    position: absolute; /* Centraliza no card */
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
}
/* Esconde o ícone genérico se a imagem existir (mesmo que esteja carregando) */
.video-thumbnail:has(.video-thumbnail-img) .video-thumbnail-icon {
    display: none;
}


/* Overlay de Play (aparece no hover) */
.video-play-overlay {
    position: absolute;
    inset: 0; /* Cobre todo o card */
    background-color: rgba(0, 0, 0, 0.4); /* Fundo escuro semi-transparente */
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem; /* Tamanho do ícone de play */
    color: rgba(255, 255, 255, 0.8);
    opacity: 0; /* Escondido por padrão */
    transition: opacity 0.25s ease;
    z-index: 3; /* Fica acima da imagem e do nome */
    pointer-events: none; /* Não interfere no clique do card */
}

/* Efeito Hover no Card de Vídeo */
.video-thumbnail:hover {
    transform: scale(1.04);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    border-color: var(--info); /* Cor azul para vídeos */
}
.video-thumbnail:hover .video-thumbnail-img {
    transform: scale(1.05); /* Leve zoom na imagem */
}
.video-thumbnail:hover .video-play-overlay {
    opacity: 1; /* Mostra o overlay de play */
}
.video-thumbnail:hover .video-thumbnail-name {
    color: var(--text-primary); /* Destaca o nome */
     background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%); /* Melhora legibilidade */
}


/* Nome do vídeo na parte inferior */
.video-thumbnail-name {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0.5rem 0.8rem; /* Espaçamento interno */
    position: relative; /* Para garantir que fique acima da imagem */
    z-index: 4; /* Acima de tudo, exceto overlay no hover */
    background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 100%); /* Gradiente para legibilidade */
    margin-top: auto; /* Empurra para baixo */
    transition: color 0.25s ease, background 0.25s ease;
}

/* --- Correção: Remover display:none/inline-flex das classes de visibilidade --- */
/* Estas classes agora só controlam a visibilidade, não o tipo de display */
/* Garanta que elas existam ou ajuste conforme sua implementação original */
.mobile-only { display: none !important; }
.desktop-only { display: block !important; } /* Use 'block' ou 'inline-block' conforme necessário */

@media (max-width: 768px) {
    .mobile-only { display: block !important; } /* Use 'block' ou 'inline-block' */
    .desktop-only { display: none !important; }

    /* Ajuste para o botão de download na lista em mobile */
    .material-list-item .material-item-actions .btn-material-action {
        width: 100%; /* Faz o botão ocupar a largura toda */
        justify-content: center; /* Centraliza o ícone e texto */
        padding: 0.7rem 1rem; /* Padding ajustado */
        display: inline-flex; /* Garante que o ícone e texto fiquem alinhados */
        align-items: center;
        gap: 0.5rem;
    }
}

/* ========================================================== */
/* === ESTILOS PARA LIGHTBOX DE VÍDEO === */
/* ========================================================== */

.video-lightbox-v5 {
    position: fixed; inset: 0; background-color: rgba(10, 10, 10, 0.92);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    z-index: 2001; /* Um pouco acima do lightbox de fotos, se necessário */
    display: none; /* Controlado por JS */
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.video-lightbox-v5.show {
    display: flex; /* Mudar para flex quando ativo */
    opacity: 1;
}

/* Reutiliza botão de fechar */
.video-lightbox-v5 .photo-lightbox-v5-close { }

/* Player de vídeo wrapper */
.video-lightbox-v5-player-wrapper {
    width: 90%; /* Ajuste a largura máxima */
    max-width: 800px; /* Limite máximo */
    max-height: 80vh;
    margin-bottom: 1.5rem;
    background-color: #000; /* Fundo preto para o vídeo */
    border-radius: 6px;
    overflow: hidden; /* Garante que o vídeo fique dentro das bordas */
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
    position: relative; /* Para posicionamento interno se necessário */
}

.video-lightbox-v5-player-wrapper video {
    display: block;
    width: 100%;
    height: auto; /* Mantém proporção */
    max-height: 80vh; /* Limita altura */
    border-radius: 6px; /* Aplica borda ao vídeo */
}

/* Reutiliza ações do lightbox de fotos */
.video-lightbox-v5 .photo-lightbox-v5-actions { }
.video-lightbox-v5 .btn-lightbox-v5-download { } /* Reutiliza botão download */

/* Remove o contador (não faz sentido para um vídeo único) */
.video-lightbox-v5 .photo-lightbox-v5-counter {
    display: none;
}



/* ========================================================== */
/* === INÍCIO: ESTILOS ISOLADOS PARA #aulas-section === */
/* ========================================================== */

/* Estilo base para o contêiner da seção de aulas */
#aulas-section {
    font-family: 'Montserrat', sans-serif;
    color: #fff;
    text-align: center;
    overflow-x: hidden;
    /* background: #2a2a2a; /* Já está no style inline do div#aulas-section */
    /* position: relative;  /* Já está no style inline do div#aulas-section */
    min-height: 100vh;
}

/* Efeito de gradiente para o fundo da seção de aulas */
#aulas-section::before {
    content: "";
    position: absolute; /* Mudado de fixed para absolute */
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 10% 20%, rgba(255, 0, 127, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 90% 30%, rgba(252, 92, 172, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 20% 70%, rgba(255, 105, 180, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 80% 80%, rgba(255, 20, 147, 0.15) 0%, transparent 20%);
    z-index: -1;
    pointer-events: none;
}

#aulas-section .lessons-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    display: flex;
    flex-direction: column;
    align-items: center; /* Centraliza o conteúdo do header */
}

#aulas-section .header-content {
    margin-bottom: 50px;
    animation: aulasFadeInDown 0.8s ease-out; /* Animação prefixada */
    width: 100%;
    display: flex; /* Para centralizar logo e headline */
    flex-direction: column; /* Logo acima do headline */
    align-items: center; /* Centraliza horizontalmente */
}

@keyframes aulasFadeInDown { /* Keyframe prefixado */
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

#aulas-section .logo {
    width: 140px;
    margin-bottom: 20px;
    filter: drop-shadow(0 0 10px rgba(255,0,127,0.5));
    transition: all 0.3s ease;
}

#aulas-section .logo:hover {
    transform: scale(1.05) rotate(-5deg);
}

#aulas-section .headline {
    font-weight: 700;
    font-size: clamp(1.8rem, 5vw, 2.5rem);
    line-height: 1.3;
    margin-bottom: 10px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.7);
    text-align: center; /* Garante que o texto dentro do headline esteja centralizado */
}

#aulas-section .headline-line {
    display: block;
}

#aulas-section .highlight {
    color: transparent;
    background: linear-gradient(1deg, #96ff00, #00ff00);
    -webkit-background-clip: text;
    background-clip: text;
    text-shadow: 0 2px 5px rgba(150, 255, 0, 0.3);
    animation: aulasGlow 2s ease-in-out infinite alternate; /* Animação prefixada */
}

@keyframes aulasGlow { /* Keyframe prefixado */
    from { text-shadow: 0 0 5px rgba(150, 255, 0, 0.5); }
    to { text-shadow: 0 0 15px rgba(150, 255, 0, 0.8); }
}

#aulas-section .lessons-container {
    width: 100%;
    max-width: 900px;
    display: flex;
    flex-direction: column;
    gap: 40px;
}

#aulas-section .lesson-item {
    background: rgba(40, 40, 40, 0.8);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 0, 127, 0.3);
    backdrop-filter: blur(5px);
    animation: aulasFadeInUp 0.8s ease-out; /* Animação prefixada */
    text-align: left;
}

@keyframes aulasFadeInUp { /* Keyframe prefixado */
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

#aulas-section .lesson-item h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 20px;
    border-left: 4px solid #fc5cac;
    padding-left: 15px;
}

#aulas-section .lesson-video {
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 10px;
    margin-bottom: 20px;
    background-color: #1a1a1a;
    overflow: hidden;
    position: relative;
}

#aulas-section .lesson-video video {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 10px;
}

#aulas-section .lesson-video video[poster] {
    background-size: cover;
    background-position: center;
    background-color: #1a1a1a;
}

#aulas-section .lesson-description {
    font-size: 1rem;
    line-height: 1.6;
    color: #e0e0e0;
    margin-bottom: 25px;
}

#aulas-section .lesson-materials {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

#aulas-section .lesson-materials h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #eee;
    margin-bottom: 15px;
}

#aulas-section .download-link {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(to bottom, #00cc00, #009900);
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
    padding: 12px 25px;
    border-radius: 50px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 255, 0, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    margin-right: 15px;
    margin-bottom: 10px;
}

#aulas-section .download-link::after {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: all 0.3s ease;
}

#aulas-section .download-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 255, 0, 0.3);
}

#aulas-section .download-link:hover::after {
    left: 100%;
}

#aulas-section .download-link i {
    margin-right: 10px;
    font-size: 1rem;
}

#aulas-section .footer {
    margin-top: 60px;
    padding: 20px;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
}

/* --- Responsividade para #aulas-section --- */
@media (max-width: 768px) {
    #aulas-section .lessons-section { padding: 30px 15px; }
    #aulas-section .lesson-item { padding: 25px 20px; }
    #aulas-section .lesson-item h2 { font-size: 1.6rem; }
}

@media (max-width: 480px) {
    #aulas-section .header-content { margin-bottom: 30px; }
    #aulas-section .logo { width: 100px; }
    #aulas-section .headline { font-size: 1.6rem; }
    #aulas-section .lesson-item { padding: 20px 15px; }
    #aulas-section .lesson-item h2 { font-size: 1.4rem; padding-left: 10px; }
    #aulas-section .lesson-description { font-size: 0.9rem; }
    #aulas-section .download-link { padding: 10px 20px; font-size: 0.9rem; }
    #aulas-section .download-link i { margin-right: 8px; }
}

#aulas-section .whatsapp-note {
    background: rgba(30, 30, 30, 0.7);
    border: 1px solid rgba(37, 211, 102, 0.3);
    border-radius: 10px;
    padding: 15px;
    margin: 20px 0;
    backdrop-filter: blur(5px);
}

#aulas-section .whatsapp-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

#aulas-section .whatsapp-content .fa-robot {
    font-size: 1.8rem;
    color: #25D366;
    flex-shrink: 0;
}

#aulas-section .whatsapp-text {
    flex-grow: 1;
    text-align: left;
}

#aulas-section .whatsapp-text strong {
    color: #25D366;
    display: block;
    margin-bottom: 5px;
    font-size: 0.95rem;
}

#aulas-section .whatsapp-text p {
    color: #e0e0e0;
    font-size: 0.85rem;
    margin: 0;
}

#aulas-section .whatsapp-button {
    background: linear-gradient(to bottom, #25D366, #128C7E);
    color: white !important;
    padding: 8px 15px;
    border-radius: 50px;
    font-size: 0.85rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(37, 211, 102, 0.2);
}

#aulas-section .whatsapp-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
}

@media (max-width: 480px) {
    #aulas-section .whatsapp-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    #aulas-section .whatsapp-button {
        align-self: stretch;
        justify-content: center;
    }
}

#aulas-section .app-cta {
    background: rgba(255, 215, 0, 0.1);
    border: 1px solid rgba(255, 215, 0, 0.3);
    border-radius: 12px;
    padding: 20px;
    margin: 25px 0;
    display: flex;
    gap: 20px;
    align-items: center;
    backdrop-filter: blur(5px);
    transition: all 0.3s ease;
}

#aulas-section .app-cta:hover {
    background: rgba(255, 215, 0, 0.15);
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.1);
}

#aulas-section .app-icon {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

#aulas-section .app-icon i {
    color: #2a2a2a;
    font-size: 1.5rem;
}

#aulas-section .app-content { /* Este .app-content é filho de .app-cta, então o escopo já ajuda */
    flex-grow: 1;
}

#aulas-section .app-content h4 {
    color: #FFD700;
    margin-bottom: 5px;
    font-size: 1.1rem;
}

#aulas-section .app-content p {
    color: #e0e0e0;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

#aulas-section .cta-button {
    background: linear-gradient(to right, #FFD700, #FF8C00);
    color: #2a2a2a !important;
    padding: 10px 20px;
    border-radius: 50px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
}

#aulas-section .cta-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
}

@media (max-width: 480px) {
    #aulas-section .app-cta {
        flex-direction: column;
        text-align: center;
    }
    #aulas-section .app-content {
        text-align: center;
    }
}

#aulas-section .lesson-materials h3 i.fa-youtube {
    color: #ff0000;
    font-size: 1.2em;
    margin-right: 8px;
}

#aulas-section .video-links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

#aulas-section .video-link-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(50, 50, 50, 0.6);
    border-radius: 10px;
    padding: 15px 20px;
    text-decoration: none;
    color: #e0e0e0;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(3px);
}

#aulas-section .video-link-card:hover {
    background: rgba(60, 60, 60, 0.8);
    border-color: #fc5cac;
    transform: translateY(-4px);
    box-shadow: 0 8px 15px rgba(255, 0, 127, 0.15);
}

#aulas-section .video-link-icon {
    background: linear-gradient(135deg, var(--primary, #ff007f), var(--secondary, #fc5cac));
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 3px 8px rgba(255, 0, 127, 0.3);
}

#aulas-section .video-link-icon i {
    color: white;
    font-size: 1.2rem;
}

#aulas-section .video-link-text {
    flex-grow: 1;
    line-height: 1.4;
}

#aulas-section .video-link-text span {
    display: block;
    font-weight: 600;
    font-size: 0.95rem;
    color: #fff;
    margin-bottom: 3px;
}

#aulas-section .video-link-text small {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

#aulas-section .video-link-arrow {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
    transition: transform 0.3s ease;
    margin-left: auto;
    padding-left: 10px;
}

#aulas-section .video-link-card:hover .video-link-arrow {
    transform: translateX(5px) scale(1.1);
    color: var(--primary);
}

/* --- Responsividade para Links de Vídeo em #aulas-section --- */
@media (max-width: 480px) {
    #aulas-section .video-links-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    #aulas-section .video-link-card {
        padding: 12px 15px;
        gap: 12px;
    }
    #aulas-section .video-link-icon {
        width: 35px;
        height: 35px;
    }
    #aulas-section .video-link-icon i {
        font-size: 1rem;
    }
    #aulas-section .video-link-text span {
        font-size: 0.9rem;
    }
    #aulas-section .video-link-text small {
        font-size: 0.75rem;
    }
    #aulas-section .video-link-arrow {
        font-size: 0.8rem;
    }
}

/* O reset global '*' já deve existir no CSS principal do seu dashboard.
   Se não existir, você pode descomentar a linha abaixo, mas é melhor ter um reset global. */
/* #aulas-section * { margin: 0; padding: 0; box-sizing: border-box; } */

/* ========================================================== */
/* === FIM: ESTILOS ISOLADOS PARA #aulas-section === */
/* ========================================================== */

</style>


</head>
<body>
    <div class="app-container">
        <!-- Header Mobile -->
           <header class="app-header">
            <div class="header-content">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat" class="app-logo">
                <div class="header-actions">
                      
                    <div class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" style="display: none;"></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Notifications Modal -->
        <div class="modal" id="notificationsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Notificações</h3>
                    <button class="close-modal">&times;</button> <!-- Uses generic close style -->
                </div>
                <div class="notifications-list" id="notificationsList">
                    <!-- Notifications loaded via JS -->
                     <div class="loading-notifications">Carregando...</div>
                </div>
                <div class="notifications-actions">
                    <button class="delete-all"><i class="fas fa-trash-alt"></i> Limpar todas</button>
                </div>
            </div>
        </div>

        <!-- Modal de Confirmação -->
        <div class="confirm-modal" id="confirmModal">
            <div class="confirm-content">
                <h3 class="confirm-title" id="confirmTitle">Confirmação</h3>
                <p class="confirm-message" id="confirmMessage">Tem certeza?</p>
                <div class="confirm-actions">
                    <button class="confirm-btn cancel" id="confirmCancel">Cancelar</button>
                    <button class="confirm-btn confirm" id="confirmAccept">Confirmar</button>
                </div>
            </div>
        </div>

      
        <!-- Overlay para menu mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

 <aside class="sidebar" id="sidebar">
     <div class="sidebar-header">
         <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat" class="sidebar-logo">
     </div>

     <!-- ▼▼▼ ADICIONE ESTE WRAPPER NOVAMENTE ▼▼▼ -->
     <div class="sidebar-scrollable-content">

         <a href="profile.php" class="user-profile">
             <img src="/uploads/avatars/<?php echo htmlspecialchars($_SESSION['user_avatar'] ?? DEFAULT_AVATAR); ?>?v=<?php echo $_SESSION['avatar_updated'] ?? time(); ?>"
                  alt="User"
                  class="user-avatar"
                  id="dashboardAvatar"
                  onerror="this.onerror=null;this.src='/uploads/avatars/<?php echo DEFAULT_AVATAR; ?>';">
             <div class="user-info">
                 <h3 id="userNameDisplay"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário'); ?></h3>
                 <p>Chatter VIP</p>
             </div>
         </a>

         <nav class="sidebar-menu">
             <a href="#" class="menu-item active" data-section="dashboard">
                 <i class="fas fa-home"></i>
                 <span>Dashboard</span>
             </a>
              <a href="#" class="menu-item" data-section="comprar-leads">
                 <i class="fas fa-users"></i>
                 <span>Solicitar Clientes</span>
             </a>
             <a href="#" class="menu-item" data-section="solicitar-chamada">
                 <i class="fas fa-video"></i>
                 <span>Realizar Chamada</span>
             </a>
             <a href="#" class="menu-item" data-section="comprovantes">
                 <i class="fas fa-file-upload"></i>
                 <span>Comprovantes</span>
             </a>
             <?php if (!$modeloEscolhido): ?>
                 <a href="#" class="menu-item" data-section="escolher-modelo">
                     <i class="fas fa-robot"></i>
                     <span>Escolher Modelo</span>
                 </a>
             <?php else: ?>
                 <a href="#" class="menu-item" data-section="minha-modelo">
                     <i class="fas fa-user-astronaut"></i>
                     <span>Minha Modelo</span>
                 </a>
             <?php endif; ?>
             <a href="#" class="menu-item" data-section="aulas">
    <i class="fas fa-book-open"></i> <!-- Ou outro ícone: fas fa-chalkboard-teacher, fas fa-graduation-cap -->
    <span>Área de Membros</span>
</a>

             <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) : ?>
                 <a href="admin/admin_dashboard.php" class="menu-item">
                     <i class="fas fa-user-shield"></i>
                     <span>Painel Admin</span>
                 </a>
             <?php endif; ?>

             <a href="profile.php" class="menu-item">
                 <i class="fas fa-user-edit"></i>
                 <span>Editar Perfil</span>
             </a>
             <a href="https://wa.me/5534998709969" target="_blank" rel="noopener noreferrer" class="menu-item">
                 <i class="fas fa-headset"></i>
                 <span>Suporte</span>
             </a>
         </nav>

          <a href="#" class="logout-btn" id="logout-btn">
              <i class="fas fa-sign-out-alt"></i>
              <span>Sair</span>
          </a>

     </div>
     <!-- ▲▲▲ FIM DO WRAPPER ▲▲▲ -->
 </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Olá, <span class="highlight"><?php echo explode(' ', $_SESSION['user_nome'])[0]; ?></span>!</h1>
                <div class="notification-bell desktop-only">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" style="display: none;"></span>
                </div>
            </div>

            <!-- Mensagens de sucesso/erro -->
            <?php if (!empty($mensagemSucesso)): ?>
                <div class="alert-message alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensagemSucesso); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($mensagemErro)): ?>
                <div class="alert-message alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($mensagemErro); ?>
                </div>
            <?php endif; ?>

            <!-- Seções do Dashboard -->
            <div id="dashboard-section" class="section fade-in">
                <h2 class="section-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Visão Geral
                </h2>

                <!-- Dashboard Cards -->
                <div class="dashboard-cards">

                    <!-- WhatsApp Card -->
                    <div class="card whatsapp-card" id="whatsappCard" style="<?= $whatsappSolicitado ? 'animation: none;' : '' ?>">
                        <div class="card-header">
                            <span class="card-title">WhatsApp Business</span>
                            <div class="card-icon success"> <i class="fab fa-whatsapp"></i> </div>
                        </div>
                        <div class="card-value" id="whatsappStatus">
                            <!-- Status loaded by JS --> Loading...
                        </div>
                        <div class="card-footer" id="whatsappFooter">
                             <!-- Footer loaded by JS --> ...
                        </div>
                    </div>
                    <!-- WhatsApp Modal -->
                    <div class="whatsapp-modal" id="whatsappModal">
                        <div class="whatsapp-modal-content">
                            <button class="close-modal">&times;</button>
                            <div class="whatsapp-modal-header"> <i class="fab fa-whatsapp"></i> <h4>Seu Número WhatsApp Business</h4> </div>
                            <div class="whatsapp-modal-body">
                                <div class="whatsapp-info-item">
                                    <div class="whatsapp-info-icon"> <i class="fas fa-check-circle"></i> </div>
                                    <div class="whatsapp-info-text"> <strong>Número pronto para cadastro</strong> <span>Seu número já está reservado</span> </div>
                                </div>
                                <div class="whatsapp-info-item">
                                    <div class="whatsapp-info-icon"> <i class="fas fa-phone-alt"></i> </div>
                                    <div class="whatsapp-info-text"> <strong>Contato pelo número cadastrado</strong> <span>Em menos de 24 horas entraremos em contato.</span> </div>
                                </div>
                                
                                <div class="whatsapp-modal-note"> <i class="fas fa-info-circle"></i> <p>Serviço gratuito essencial para receber clientes com profissionalismo.</p> </div>
                                <button class="btn whatsapp-confirm-btn"> <i class="fab fa-whatsapp"></i> Confirmar Solicitação </button>
                            </div>
                        </div>
                    </div>


                    <!-- Saldo Card -->
                    <div class="card card-saldo" id="saldoCard">
                        <div class="card-header">
                            <span class="card-title">Saldo de Hoje</span>
                            <div class="card-icon primary"> <i class="fas fa-wallet"></i> </div>
                        </div>
                        <div class="card-value">R$ <?= number_format($saldoHoje['saldo'], 2, ',', '.') ?></div>
                        <div class="card-footer">Total comprovantes: R$ <?= number_format($saldoHoje['total_comprovantes'], 2, ',', '.') ?></div>
                    </div>
                    <!-- Saldo Modal -->
                    <div class="saldo-tooltip-modal" id="saldoTooltipModal">
                        <div class="saldo-tooltip-content">
                            <button class="close-modal">&times;</button>
                            <div class="saldo-tooltip-header"> <i class="fas fa-wallet"></i> <h4>Como funciona seu saldo</h4> </div>
                            <div class="saldo-tooltip-body">
                                <div class="saldo-info-item">
                                    <div class="saldo-info-icon" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);"> <i class="fas fa-percentage"></i> </div>
                                    <div class="saldo-info-text"> <strong>10% retido</strong> <span>Taxa da plataforma</span> </div>
                                </div>
                                <div class="saldo-info-item">
                                    <div class="saldo-info-icon" style="background: rgba(var(--success-rgb), 0.1); color: var(--success);"> <i class="fas fa-hand-holding-usd"></i> </div>
                                    <div class="saldo-info-text"> <strong>90% disponível</strong> <span>Para saque</span> </div>
                                </div>
                                <div class="saldo-info-item">
                                    <div class="saldo-info-icon" style="background: rgba(0, 153, 255, 0.1); color: var(--info);"> <i class="fas fa-clock"></i> </div>
                                    <div class="saldo-info-text"> <strong>Atualização diária</strong> <span>Zerado às 00:00</span> </div>
                                </div>
                                <div class="saldo-tooltip-note"> <i class="fas fa-info-circle"></i> <p>O valor é enviado automaticamente para sua conta PIX cadastrada.</p> </div>
                            </div>
                        </div>
                    </div>
                    
                                    <!-- ================== CLICK-TO-COPY PIX CARD ================== -->
         <!-- ================== ULTRA-CONSISTENT PIX CARD ================== -->
                <div class="card" id="pixCard" data-pix-key="47401064000168" style="cursor: pointer;" title="Clique para copiar a Chave Pix">
                    <!-- Header IDÊNTICO aos outros cards -->
                    <div class="card-header">
                        <span class="card-title">Chave Pix (CNPJ)</span>
                         <div class="card-icon warning"> <!-- Use a mesma classe de cor (primary, info, success) dos outros -->
                            <i class="fas fa-copy"></i> <!-- Ícone universal de cópia -->
                        </div>
                    </div>

                    <!-- Valor (Chave Pix) IDÊNTICO em estrutura aos outros -->
                    <div class="card-value pix-key-value" style="word-break: break-all; text-align: center;">
                        47401064000168
                    </div>

                    <!-- Footer IDÊNTICO em estrutura, só muda o texto -->
                    <div class="card-footer">
                        Clique no card para copiar
                    </div>
                </div>
                <!-- ================== END CLICK-TO-COPY PIX CARD ================== -->

                    <!-- Chamadas Card -->
                    <div class="card card-chamadas" id="cardChamadas">
                        <div class="card-header">
                            <span class="card-title">Chamadas Agendadas</span>
                            <div class="card-icon info"> <i class="fas fa-video"></i> </div>
                        </div>
                        <div class="card-value" id="chamadasTotalCount">
                             <?= $chamadasHoje['total'] ?>
                        </div>
                        <div class="card-footer">
                           Clique para ver o status
                        </div>
                    </div>
                </div>
                
                

                <!-- Detalhes das chamadas (initially hidden) -->
                <div class="chamadas-details" id="chamadasDetails">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;" id="chamadasHeader">
                      <h3>Minhas Chamadas</h3>
                      <div style="display: flex; gap: 0.5rem;">
                         <button class="btn btn-secondary btn-sm" id="refreshChamadasBtn" title="Atualizar Lista">
                              <i class="fas fa-sync-alt"></i>
                              <span style="margin-left: 0.4rem;">Atualizar</span>
                          </button>
                          <button class="btn btn-secondary btn-sm" id="limparChamadasBtn" title="Limpar Histórico"> <!-- Changed to btn-secondary -->
                              <i class="fas fa-trash-alt"></i> Limpar
                          </button>
                      </div>
                  </div>
                  <div id="chamadasContentArea">
                      <!-- Content loaded via JS or initial PHP -->
                      <?php if (count($chamadas) > 0): ?>
                          <div style="overflow-x:auto;" id="chamadasTableContainer">
                              <table class="chamadas-table">
                                  <thead>
                                      <tr><th>Cliente</th><th>Data/Hora Agendada</th><th>Duração (min)</th><th>Status</th></tr>
                                  </thead>
                                  <tbody>
                                      <?php foreach ($chamadas as $chamada): ?>
                                          <?php
                                              $statusKey = strtolower($chamada['status'] ?? 'desconhecido');
                                              $statusLabel = $statusChamadaLabels[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey));
                                              $statusClass = $statusChamadaClasses[$statusKey] ?? 'status-desconhecido';
                                              $clienteDisplay = htmlspecialchars($chamada['cliente_nome'] ?: ($chamada['cliente_numero'] ?? 'N/A'));
                                              $dataHoraDisplay = isset($chamada['data_hora']) ? date('d/m/Y H:i', strtotime($chamada['data_hora'])) : 'N/A';
                                              $duracaoDisplay = htmlspecialchars($chamada['duracao'] ?? '-');
                                          ?>
                                          <tr>
                                              <td data-label="Cliente"><?= $clienteDisplay ?></td>
                                              <td data-label="Data/Hora"><?= $dataHoraDisplay ?></td>
                                              <td data-label="Duração"><?= $duracaoDisplay ?></td> <!-- Removed 'min' here, added via CSS -->
                                              <td data-label="Status"><span class="status-badge <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                                          </tr>
                                      <?php endforeach; ?>
                                  </tbody>
                              </table>
                          </div>
                      <?php else: ?>
                          <p style="color: var(--text-secondary); text-align: center; margin-top: 1rem;" id="noChamadasMessage">
                              Nenhuma chamada encontrada no histórico recente.
                          </p>
                      <?php endif; ?>
                  </div>
                </div>
            </div>
            
            
            <!-- Aulas Mentoria Section -->
<div id="aulas-section" class="section fade-in" style="display: none; padding: 0; background: #2a2a2a; position: relative; min-height: 100vh;">
    <!-- O CONTEÚDO HTML DA PÁGINA DE AULAS SERÁ COLADO AQUI -->
    
     <section class="lessons-section">
        <header class="header-content">
            <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Logo Love Chat" class="logo">
            <h1 class="headline">
                <span class="headline-line">Área de Membros</span>
                <span class="headline-line">Aulas Completas <span class="highlight">Love Chat</span></span>
            </h1>
        </header>

        <main class="lessons-container">

            <!-- Aula: Introdução -->
            <article class="lesson-item">
                <h2>Introdução</h2>
                <div class="lesson-video">
            
                    <video controls controlsList="nodownload" poster="img/THUMB.png">
                        <source src="videos/introducao.mp4" type="video/mp4">
                        Seu navegador não suporta o elemento de vídeo.
                    </video>
                </div>
                <p class="lesson-description">
                    Bem-vindo(a) à Mentoria Love Chat! Neste vídeo inicial, vamos apresentar a estrutura completa do curso, nossos objetivos e como você pode extrair o máximo valor de cada aula. Prepare-se para transformar sua operação e seus resultados!
                </p>
                
            </article>

            <!-- Aula 1 -->
            <article class="lesson-item">
                <h2>Aula 1: Sistema Organizacional</h2>
                <div class="lesson-video">
              
                    <video controls controlsList="nodownload" poster="img/THUMB.png">
                        <source src="videos/aula1.mp4" type="video/mp4">
                        Seu navegador não suporta o elemento de vídeo.
                    </video>
                </div>
                <p class="lesson-description">
                    Organização é a chave do sucesso! Aprenda a implementar um sistema organizacional eficiente com tabelas práticas para gerenciar sua operação e contabilidade. Tenha controle total sobre seus números e otimize sua gestão.
                </p>
                <div class="lesson-materials">
                    <h3><i class="fas fa-folder-open"></i> Materiais de Apoio</h3>
                    <a href="materials/tabelas_organizacionais.zip" class="download-link" download>
                        <i class="fas fa-table"></i> Baixar Modelos de Tabelas
                    </a>
                </div>
            </article>

             <!-- Aula 2 -->
            <article class="lesson-item">
                <h2>Aula 2: Modelo IA</h2>
                <div class="lesson-video">
            
                    <video controls controlsList="nodownload" poster="img/THUMB.png">
                        <source src="videos/aula2.mp4" type="video/mp4">
                        Seu navegador não suporta o elemento de vídeo.
                    </video>
                </div>
                <p class="lesson-description">
                    Descubra o passo a passo para criar seu próprio Modelo de Inteligência Artificial (IA) do zero! Revelamos segredos e dicas práticas para desenvolver sua IA personalizada com o menor custo possível, maximizando seu investimento.
                </p>
                <div class="lesson-materials">
                    <h3><i class="fas fa-folder-open"></i> Materiais de Apoio</h3>
                    <a href="materials/conteudo_ia.zip" class="download-link" download>
                        <i class="fas fa-robot"></i> Baixar Banco de Dados Modelos IA
                    </a>
                </div>
            </article>

            <!-- Aula 3 -->
    <article class="lesson-item">
    <h2>Aula 3: Chips, Whatsapp Business e Contingência</h2>
    <div class="lesson-video">
        <video controls controlsList="nodownload" poster="img/THUMB.png">
            <source src="videos/aula3.mp4" type="video/mp4">
            Seu navegador não suporta o elemento de vídeo.
        </video>
    </div>
    <p class="lesson-description">
        Domine a gestão de múltiplos Chips (SIM cards) e contas de WhatsApp Business. Aprenda estratégias de contingência essenciais para lidar com bloqueios e garantir que sua comunicação com clientes nunca pare.
    </p>
    <div class="lesson-materials">
        <h3><i class="fas fa-folder-open"></i> Materiais de Apoio</h3>
        <div class="material-links">
            <!-- Link para download do PDF -->
            <a href="materials/chips_contingencia.pdf" class="download-link" download>
                <i class="fas fa-shield-alt"></i> Baixar Guia Whatsapp Business (PDF)
            </a>
            <!-- Link para tutorial do BlueStacks -->
            <a href="https://www.youtube.com/watch?v=rTqf4PkRtpA" class="download-link" target="_blank">
                <i class="fas fa-download"></i> Baixar e instalar o BlueStacks (Vídeo)
            </a>
            <!-- Link para ativação de ambiente virtual -->
            <a href="https://www.youtube.com/watch?v=uH5lNe3LKik" class="download-link" target="_blank">
                <i class="fas fa-cog"></i> Ativar ambiente virtual (Vídeo)
            </a>
        </div>
    </div>
</article>

            <!-- Aula 4 -->
            <article class="lesson-item">
                <h2>Aula 4: Facebook ADS</h2>
                <div class="lesson-video">
                
                    <video controls controlsList="nodownload" poster="img/THUMB.png">
                        <source src="videos/aula4.mp4" type="video/mp4">
                        Seu navegador não suporta o elemento de vídeo.
                    </video>
                </div>
                <p class="lesson-description">
                    Mergulhe fundo no Facebook Ads! Uma aula completa e detalhada revelando as estratégias e os criativos exatos que usamos em nossas campanhas de alta performance. Aprenda a atrair clientes qualificados de forma eficaz.
                </p>
                                <div class="lesson-materials">
                    <h3><i class="fab fa-youtube"></i> Vídeos de Apoio - Facebook ADS</h3>
                    <div class="video-links-grid">
                        <!-- Link Vídeo 1 -->
                        <a href="https://www.youtube.com/watch?v=jenaiq4mXlw" target="_blank" rel="noopener noreferrer" class="video-link-card">
                            <div class="video-link-icon">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="video-link-text">
                                <span>(Atualizado) Como criar um BM Gerenciador de Negócios no Facebook</span>
                                <small>Clique para assistir no YouTube</small>
                            </div>
                            <div class="video-link-arrow">
                                <i class="fas fa-external-link-alt"></i>
                            </div>
                        </a>

                        <!-- Link Vídeo 2 -->
                        <a href="https://www.youtube.com/watch?v=YhHv8WVhHIM" target="_blank" rel="noopener noreferrer" class="video-link-card">
                            <div class="video-link-icon">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="video-link-text">
                                <span>Como CRIAR UMA PÁGINA no FACEBOOK para ANÚNCIOS (GUIA DEFINITIVO)</span>
                                <small>Clique para assistir no YouTube</small>
                            </div>
                             <div class="video-link-arrow">
                                <i class="fas fa-external-link-alt"></i>
                            </div>
                        </a>

                        <!-- Link Vídeo 3 -->
                        <a href="https://www.youtube.com/watch?v=pKYcPATxwok" target="_blank" rel="noopener noreferrer" class="video-link-card">
                            <div class="video-link-icon">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="video-link-text">
                                <span>ATUALIZAÇÃO 2024: NOVA FORMA DE CRIAR O PIXEL DO FACEBOOK ADS | META ADS</span>
                                <small>Clique para assistir no YouTube</small>
                            </div>
                             <div class="video-link-arrow">
                                <i class="fas fa-external-link-alt"></i>
                            </div>
                        </a>

                        <!-- Link Vídeo 4 -->
                        <a href="https://www.youtube.com/watch?v=pUU7aH3A90w" target="_blank" rel="noopener noreferrer" class="video-link-card">
                             <div class="video-link-icon">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="video-link-text">
                                <span>O que é CBO e ABO? Como usar? Quando usar? Aprenda na prática! | Facebook ads</span>
                                <small>Clique para assistir no YouTube</small>
                            </div>
                            <div class="video-link-arrow">
                                <i class="fas fa-external-link-alt"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </article>

            <!-- Aula 5 -->
           <article class="lesson-item">
    <h2>Aula 5: Estratégias de Conversão</h2>
    <div class="lesson-video">
        <video controls controlsList="nodownload" poster="img/THUMB.png">
            <source src="videos/aula5.mp4" type="video/mp4">
            Seu navegador não suporta o elemento de vídeo.
        </video>
    </div>
    <p class="lesson-description">
        Transforme conversas em conversões! Domine as estratégias de venda e persuasão mais eficazes para o nicho hot. Aprenda a abordar clientes, quebrar objeções e fechar vendas de forma consistente.
    </p>
    
    <div class="whatsapp-note">
        <div class="whatsapp-content">
            <i class="fas fa-robot"></i>
            <div class="whatsapp-text">
                <strong>Quer ativar seu bot?</strong>
                <p>Ative nosso Bot exclusivo da Love Chat para WhatsApp Business!</p>
            </div>
            <a href="https://wa.me/+5534998709969" target="_blank" class="whatsapp-button">
                <i class="fab fa-whatsapp"></i> Falar com equipe
            </a>
        </div>
    </div>

    <div class="lesson-materials">
        <h3><i class="fas fa-folder-open"></i> Materiais de Apoio</h3>
        <a href="materials/conversao.zip" class="download-link" download>
            <i class="fas fa-comments-dollar"></i> Baixar Estratégias de Conversão
        </a>
    </div>
</article>
            <!-- Vídeo: Videochamada com IA -->
            <article class="lesson-item">
                <h2>Guia de Videochamada com IA</h2>
                <div class="lesson-video">
          
                    <video controls controlsList="nodownload" poster="img/THUMB.png">
                        <source src="videos/videochamada_ia.mp4" type="video/mp4">
                        Seu navegador não suporta o elemento de vídeo.
                    </video>
                </div>
                 <p class="lesson-description">
                   Aprenda a realizar videochamadas para o nicho hot no Whatsapp utilizando Inteligência Artificial.
                </p>
                <div class="lesson-materials">
                    <h3><i class="fas fa-folder-open"></i> Materiais de Apoio</h3>
                    <a href="materials/ManyCam.rar" class="download-link" download>
                        <i class="fas fa-headset"></i> Baixar ManyCam
                    </a>
                </div>
            </article>

            <!-- Aula 6 -->
    <article class="lesson-item">
    <h2>Aula 6: Escala Máxima!</h2>
    <div class="lesson-video">
        <video controls controlsList="nodownload" poster="img/THUMB.png">
            <source src="videos/aula6.mp4" type="video/mp4">
            Seu navegador não suporta o elemento de vídeo.
        </video>
    </div>
    <p class="lesson-description">
        Conheça o futuro da sua operação: o White Label Love Chat! Demonstramos nosso aplicativo exclusivo, seu funcionamento e como você pode usá-lo para escalar seus negócios. Uma oportunidade única para potencializar seus ganhos!
    </p>
    

</article>

        </main>

        <footer class="footer">
            © <span id="currentYear"></span> Love Chat Mentoria. Todos os direitos reservados.
        </footer>

    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página de aulas carregada!');

            // Adiciona o ano atual no rodapé
            document.getElementById('currentYear').textContent = new Date().getFullYear();

             // Adiciona 'noopener noreferrer' para segurança nos links de download
            const downloadLinks = document.querySelectorAll('.download-link');
            downloadLinks.forEach(link => {
                link.setAttribute('rel', 'noopener noreferrer');
            });
        });
    </script>
    
</div>
            
            

           <!-- Solicitar Chamada Section -->
            <div id="solicitar-chamada-section" class="section fade-in" style="display: none;">
                 <h2 class="section-title"> <i class="fas fa-video"></i> Solicitar Chamada de Vídeo </h2>
                 <div class="form-container">
                    <form id="chamadaForm">
                        <div class="form-group">
                            <label for="clienteNome">Nome do Cliente (Opcional)</label>
                            <input type="text" id="clienteNome" name="clienteNome" placeholder="Nome (se souber)">
                        </div>
                        <div class="form-group">
                             <label for="clienteNumero">Número do Cliente (WhatsApp)</label>
                             <input type="tel" id="clienteNumero" name="clienteNumero" placeholder="Qualquer formato, ex: +55 11 98765-4321" required> <!-- <<< CHANGED Placeholder -->
                        </div>
                        <div class="form-group">
                            <label for="chamadaData">Data e Horário</label>
                            <!-- <<< ADDED Wrapper para input e botão 'Agora' >>> -->
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="datetime-local" id="chamadaData" name="chamadaData" required style="flex-grow: 1;">
                                <button type="button" id="setNowBtn" class="btn btn-secondary btn-sm" title="Definir para Agora"> <!-- <<< ADDED Button -->
                                    <i class="fas fa-clock"></i> Agora
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="chamadaDuracao">Duração (minutos)</label>
                            <select id="chamadaDuracao" name="chamadaDuracao" required>
                                <option value="1">1 minuto</option> <option value="5">5 minutos</option>
                                <option value="10">10 minutos</option> <option value="15">15 minutos</option>
                                <option value="20">20 minutos</option> <option value="25">25 minutos</option>
                                <option value="30">30 minutos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="chamadaObservacao">Observações (Importante)</label>
                            <textarea id="chamadaObservacao" name="chamadaObservacao" placeholder="Detalhes da chamada, preferências, o que foi combinado..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancelarChamada"><i class="fas fa-times"></i> Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Solicitar Chamada</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Comprar Leads Section -->
            <div id="comprar-leads-section" class="section fade-in" style="display: none;">
                <h2 class="section-title"> <i class="fas fa-users"></i> Solicitar Clientes </h2>
                <p>Escolha quantos clientes você quer atender hoje.</p>
                <div class="packages-container">
                    <?php
                        // Example structure - Replace with dynamic data if needed
                        $packages = [
                            ['leads' => 100, 'price' => 30.00, 'title' => 'Básico', 'badge' => 'Iniciante', 'profit' => '200-300'],
                            ['leads' => 174, 'price' => 52.00, 'title' => 'Intermediário', 'badge' => 'Popular', 'profit' => '250-350'],
                            ['leads' => 266, 'price' => 80.00, 'title' => 'Avançado', 'badge' => 'Top', 'profit' => '300-400', 'recommended' => true],
                            ['leads' => 333, 'price' => 100.00, 'title' => 'Premium', 'badge' => 'VIP', 'profit' => '450-500', 'premium' => true],
                        ];
                        foreach ($packages as $pkg):
                            $leads = $pkg['leads'];
                            $price = $pkg['price'];
                            $price_int = floor($price);
                            $price_dec = sprintf('%02d', ($price - $price_int) * 100);
                            $description = "Pacote " . $pkg['title'] . " - " . $leads . " Clientes";
                    ?>
                    <div class="package-card <?= $pkg['recommended'] ?? false ? 'recommended' : '' ?> <?= $pkg['premium'] ?? false ? 'premium' : '' ?>">
                        <div class="package-header">
                            <h3 class="package-title"><?= htmlspecialchars($pkg['title']) ?></h3>
                            <div class="package-badge"><?= htmlspecialchars($pkg['badge']) ?></div>
                        </div>
                        <div class="package-content">
                            <div class="package-leads"><?= $leads ?> Clientes</div>
                            <div class="package-price">
                                <span class="price-currency">R$</span>
                                <span class="price-value"><?= $price_int ?></span>
                                <span class="price-decimal">,<?= $price_dec ?></span>
                            </div>
                            <div class="package-features">
                                <div class="feature-item"> <i class="fas fa-check-circle"></i> <span>Lucro estimado: R$<?= $pkg['profit'] ?></span> </div>
                                <div class="feature-item"> <i class="fas fa-check-circle"></i> <span>Suporte 24/7</span> </div>
                            </div>
                        </div>
                        <button class="package-btn"
                                data-package="<?= $leads ?>"
                                data-price="<?= number_format($price, 2, '.', '') ?>"  // <<< MUDE PARA ESTA LINHA
                                data-description="<?= htmlspecialchars($description) ?>">
                            <i class="fas fa-shopping-cart"></i> Comprar Agora
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Comprovantes Section -->
            <div id="comprovantes-section" class="section fade-in" style="display: none;">
                <h2 class="section-title"> <i class="fas fa-file-upload"></i> Enviar Comprovantes </h2>
                <div class="form-container">
                    <form id="comprovanteForm" enctype="multipart/form-data">
                       <div class="form-group">
                            <label for="comprovanteFile">Arquivo do Comprovante</label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="comprovanteFile" name="comprovante" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                                <label for="comprovanteFile" class="file-upload-button">
                                    <i class="fas fa-cloud-upload-alt"></i> <span>Selecionar Arquivo</span>
                                </label>
                                <span class="file-name-display" id="fileNameDisplay">Nenhum arquivo selecionado</span>
                            </div>
                            <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.5rem;">
                                Formatos: JPG, PNG, PDF, WEBP (Max 5MB)
                            </p>
                            <div class="preview-container" id="previewContainer" style="display: none; margin-top: 1rem;">
                                <h4 style="margin-bottom: 0.5rem; color: var(--text-primary); font-size: 0.9rem;">Pré-visualização</h4>
                                <div class="preview-content" id="previewContent"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="comprovanteDescricao">Descrição (Opcional)</label>
                            <input type="text" id="comprovanteDescricao" name="descricao" placeholder="Ex: Venda Produto X para Cliente Y">
                        </div>
                       <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancelarComprovante"> <i class="fas fa-times"></i> Cancelar </button>
                            <button type="submit" class="btn btn-primary"> <i class="fas fa-paper-plane"></i> Enviar </button>
                        </div>
                    </form>
                    <!-- Add area for recent uploads if needed -->
                </div>
            </div>

            <!-- Escolher Modelo Section -->
            <?php if (!$modeloEscolhido): ?>
            <div id="escolher-modelo-section" class="section fade-in" style="display: none;">
                 <div class="models-selection-container">
                    <div class="models-header"> <h2>Escolha sua Modelo IA</h2> </div>
                    <div class="models-grid">
                        <!-- Modelo 1 -->
                        <div class="model-card" data-modelo="1">
                            <div class="model-media"> <img src="/OLIVIA.jpg" alt="Olivia" class="model-avatar"> </div>
                            <div class="model-info"> <h3 class="model-name">Olivia</h3> <div class="model-actions"> <button class="model-btn"><i class="fas fa-check-circle"></i> Selecionar</button> </div> </div>
                        </div>
                        <!-- Modelo 2 -->
                        <div class="model-card" data-modelo="2">
                            <div class="model-media"> <img src="/SOPHIAEE.jpg" alt="Sophia" class="model-avatar"> </div>
                            <div class="model-info"> <h3 class="model-name">Sophia</h3> <div class="model-actions"> <button class="model-btn"><i class="fas fa-check-circle"></i> Selecionar</button> </div> </div>
                        </div>
                        <!-- Modelo 3 -->
                        <div class="model-card" data-modelo="3">
                             <div class="model-media"> <img src="/MANUE.jpg" alt="Manuela" class="model-avatar"> </div>
                             <div class="model-info"> <h3 class="model-name">Manuela</h3> <div class="model-actions"> <button class="model-btn"><i class="fas fa-check-circle"></i> Selecionar</button> </div> </div>
                        </div>
                         <!-- Modelo 4 -->
                        <div class="model-card" data-modelo="4">
                            <div class="model-media"> <img src="/CAMILAE.jpg" alt="Camila" class="model-avatar"> </div>
                            <div class="model-info"> <h3 class="model-name">Camila</h3> <div class="model-actions"> <button class="model-btn"><i class="fas fa-check-circle"></i> Selecionar</button> </div> </div>
                        </div>
                    </div>
                    <div class="model-selection-guide">
                        <div class="guide-icon"> <i class="fas fa-info-circle"></i> </div>
                        <div class="guide-content"> <h4>Escolha Permanente</h4> <p>Selecione com atenção! A escolha não poderá ser alterada.</p> </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

          <!-- ================== SEÇÃO MINHA MODELO - ESTILO PREMIUM GALERIA v5 ================== -->
<div id="minha-modelo-section" class="section fade-in" style="display: none; padding: 0; background: var(--darker); border: none; box-shadow: none; border-radius: 12px; overflow: hidden;">

 <?php if ($modeloEscolhido && isset($modeloEscolhidoId)): ?>
      <?php
// --- LÓGICA PHP PARA DEFINIR AVATAR, MATERIAIS E CORES ---
// Mapeamento dos dados da modelo baseados no ID escolhido
$modeloInfo = match ($modeloEscolhidoId) {
    1 => ['img' => 'OLIVIA.jpg', 'nome' => 'Olivia', 'desc' => 'Sua Modelo I.A', 'color' => 'var(--primary)', 'color_rgb' => 'var(--primary-rgb)'],
    2 => ['img' => 'SOPHIAEE.jpg', 'nome' => 'Sophia', 'desc' => 'Sua Modelo I.A', 'color' => 'var(--info)', 'color_rgb' => 'var(--info-rgb)'],
    3 => ['img' => 'MANUE.jpg', 'nome' => 'Manuela', 'desc' => 'Sua Modelo I.A', 'color' => 'var(--lime-green)', 'color_rgb' => 'var(--lime-green-rgb)'],
    4 => ['img' => 'CAMILAE.jpg', 'nome' => 'Camila', 'desc' => 'Sua Modelo I.A', 'color' => 'var(--secondary)', 'color_rgb' => '252, 92, 172'],
    // Caso default: Usa o nome de arquivo definido em DEFAULT_AVATAR
    default => ['img' => defined('DEFAULT_AVATAR') ? DEFAULT_AVATAR : 'default.jpg', 'nome' => 'Modelo Padrão', 'desc' => 'Sua assistente virtual', 'color' => 'var(--text-secondary)', 'color_rgb' => '180, 180, 180'],
};

// --- LÓGICA CORRIGIDA PARA O AVATAR PRINCIPAL DA MODELO ---

// Nome do arquivo da imagem principal da modelo (ex: 'OLIVIA.jpg')
$avatarFileName = $modeloInfo['img'];

// 1. Define o caminho WEB padrão como sendo o da raiz do site
// Exemplo: /OLIVIA.jpg
$webAvatarPath = '/' . $avatarFileName;

// 2. Define o caminho no SERVIDOR para verificar a existência do arquivo (na raiz do projeto)
// __DIR__ é a pasta onde o dashboard.php está. Assume que as imagens estão lá.
$potentialAvatarPathOnServer = __DIR__ . '/' . $avatarFileName;

// 3. Log para depuração (verifique dashboard_errors.log)
error_log("DEBUG [Minha Modelo Avatar Check] - Verificando Server Path (RAIZ): " . $potentialAvatarPathOnServer);
error_log("DEBUG [Minha Modelo Avatar Check] - Web Path Tentativo (RAIZ): " . $webAvatarPath);

// 4. Verifica se o arquivo específico da modelo existe na RAIZ do servidor
//    OU se o nome do arquivo é igual ao default (para o caso default do match)
if (!file_exists($potentialAvatarPathOnServer) || empty($avatarFileName) || (defined('DEFAULT_AVATAR') && $avatarFileName === DEFAULT_AVATAR)) {
    // Se NÃO encontrou a imagem específica na RAIZ, ou se é o caso default...
    error_log("DEBUG [Minha Modelo Avatar Check] - Arquivo da modelo na RAIZ NÃO encontrado ou default (" . $potentialAvatarPathOnServer . "). Usando DEFAULT de /uploads/avatars/.");
    // ...então define o caminho WEB para o avatar PADRÃO, que está DENTRO de /uploads/avatars/
    $webAvatarPath = '/uploads/avatars/' . (defined('DEFAULT_AVATAR') ? DEFAULT_AVATAR : 'default.jpg');
    // E reseta as cores para o padrão
    $modeloColor = 'var(--text-secondary)';
    $modeloColorRGB = '180, 180, 180';
} else {
    // Se ENCONTROU a imagem específica na RAIZ, o $webAvatarPath já está correto ('/OLIVIA.jpg', etc.)
    error_log("DEBUG [Minha Modelo Avatar Check] - Arquivo da modelo na RAIZ ENCONTRADO: " . $potentialAvatarPathOnServer . ". Usando Web Path: " . $webAvatarPath);
    // Mantém as cores da modelo específica
    $modeloColor = $modeloInfo['color'];
    $modeloColorRGB = $modeloInfo['color_rgb'];
}
// A variável $webAvatarPath agora contém a URL correta para a tag <img>

// --- FIM DA LÓGICA CORRIGIDA PARA O AVATAR PRINCIPAL ---


// --- Função para buscar os materiais da GALERIA (Fotos, Vídeos, etc.) ---
// Esta função busca dentro de 'uploads/avatars/model_materials/'
function getMaterialFiles($modelId, $type, $baseDir, $baseWeb) {
    $list = [];
    // Caminho relativo para os materiais da galeria
    $subPath = 'uploads/avatars/model_materials/' . $type . '/' . $modelId . '/';
    $serverPath = rtrim($baseDir, '/') . '/' . trim($subPath, '/'); // Caminho completo no servidor
    $webPath = '/' . trim($subPath, '/'); // Caminho base web para esta subpasta

    if (is_dir($serverPath)) {
        $allowedExtensionsConfig = match($type) {
            'photos' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'videos' => ['mp4', 'mov', 'webm'], // Adicione outras extensões de vídeo se necessário
            'audios' => ['mp3', 'wav', 'ogg'],
            'scripts' => ['pdf', 'doc', 'docx', 'txt'],
            default => []
        };

        if (!empty($allowedExtensionsConfig)) {
            $files = scandir($serverPath);
            if ($files) {
                foreach ($files as $file) {
                    $filePath = $serverPath . '/' . $file;
                    if ($file !== '.' && $file !== '..' && is_file($filePath)) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                        if (in_array($ext, $allowedExtensionsConfig, true)) {
                            // Monta a URL completa para o arquivo da galeria
                            $fileUrl = rtrim($baseWeb, '/') . $webPath . '/' . rawurlencode($file);

                            // Cria o array $listItem inicial (APENAS com url e name)
                            $listItem = [
                                'url' => $fileUrl,
                                'name' => pathinfo($file, PATHINFO_FILENAME)
                            ];

                            // ===========================================================
                            // === ↓↓↓ LÓGICA PARA ADICIONAR THUMBNAIL (se vídeo) ↓↓↓ ===
                            // ===========================================================
                            if ($type === 'videos') {
                                // --- Configure sua convenção de miniaturas ---
                                $thumbnailSubDir = 'video_thumbnails'; // Subpasta para thumbs DENTRO da pasta de videos do modelo
                                $thumbnailExtension = '.jpg';          // Extensão esperada das thumbs (ex: .jpg)

                                // 1. Determinar nome/caminho da miniatura
                                $videoFilenameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
                                $thumbnailFilename = $videoFilenameWithoutExt . $thumbnailExtension;
                                $thumbnailServerPath = $serverPath . $thumbnailSubDir . '/' . $thumbnailFilename; // Caminho Físico
                                $thumbnailWebPath = $webPath . $thumbnailSubDir . '/' . rawurlencode($thumbnailFilename); // Caminho Web Relativo

                                // 2. Verificar se a miniatura existe no servidor
                                if (file_exists($thumbnailServerPath)) {
                                    // 3. Se existe, ADICIONA a chave 'thumbnail_url' ao $listItem
                                    $listItem['thumbnail_url'] = rtrim($baseWeb, '/') . $thumbnailWebPath; // URL Web Completa
                                     // error_log("DEBUG [getMaterialFiles] - Thumb encontrada para $file: " . $listItem['thumbnail_url']);
                                } else {
                                    // 4. Se NÃO existe, ADICIONA a chave 'thumbnail_url' como null
                                    $listItem['thumbnail_url'] = null;
                                     // error_log("DEBUG [getMaterialFiles] - Thumb NÃO encontrada para $file em $thumbnailServerPath");
                                }
                            } else {
                                // Para outros tipos, garante que a chave exista como null (se o JS precisar)
                                $listItem['thumbnail_url'] = null;
                            }
                            // ===========================================================
                            // === ↑↑↑ FIM DA LÓGICA DA THUMBNAIL ↑↑↑ ===
                            // ===========================================================

                            // >>>>> ADICIONA O $listItem COMPLETO À LISTA $list AQUI <<<<<
                            $list[] = $listItem;

                            // error_log("DEBUG [getMaterialFiles] - ADDED Galeria: $file (Completo: " . json_encode($listItem) . ")");

                        } // Fim if (in_array...)
                    } // Fim if (is_file...)
                } // Fim foreach
            } // Fim if ($files)
        } // Fim if (!empty($allowedExtensionsConfig))
    } // Fim if (is_dir...)
    return $list;
}

// --- Busca os materiais da GALERIA ---
$baseDir = __DIR__; // Diretório do dashboard.php
$baseWeb = '';     // Assume que o site roda na raiz do domínio

// error_log("DEBUG [Principal - Galeria] - Base Dir: $baseDir");
// error_log("DEBUG [Principal - Galeria] - Base Web: $baseWeb");

// Chama a função para buscar os itens da GALERIA
$photoList = getMaterialFiles($modeloEscolhidoId, 'photos', $baseDir, $baseWeb);
$videoList = getMaterialFiles($modeloEscolhidoId, 'videos', $baseDir, $baseWeb);
$audioList = getMaterialFiles($modeloEscolhidoId, 'audios', $baseDir, $baseWeb);
$scriptList = getMaterialFiles($modeloEscolhidoId, 'scripts', $baseDir, $baseWeb);

// --- FIM DA LÓGICA PHP ---
?>
        <!-- O HTML abaixo usa a variável $webAvatarPath corrigida para a imagem principal -->
        <!-- e as listas $photoList, etc., para o JSON dos materiais da galeria -->

        <!-- Hero Section v5 - Estilo Premium -->
        <div class="modelo-hero-v5" style="--modelo-theme-color: <?= $modeloColor ?>; --modelo-theme-rgb: <?= $modeloColorRGB ?>;">
            <div class="modelo-hero-v5-bg"></div>
            <div class="modelo-hero-v5-content">
                 <div class="modelo-hero-v5-avatar-wrapper">
                     <img src="<?= htmlspecialchars($webAvatarPath) ?>?v=<?= time() /* Cache busting */ ?>"
                          alt="<?= htmlspecialchars($modeloInfo['nome']) ?>"
                          class="modelo-hero-v5-avatar"
                          onerror="this.onerror=null; this.src='/uploads/avatars/<?= defined('DEFAULT_AVATAR') ? DEFAULT_AVATAR : 'default.jpg' ?>'; console.error('Erro ao carregar avatar principal: <?= htmlspecialchars($webAvatarPath) ?>');">
                     <div class="modelo-hero-v5-status-dot"></div> <!-- Indicador online/ativo -->
                 </div>
                 <h2 class="modelo-hero-v5-name"><?= htmlspecialchars($modeloInfo['nome']) ?></h2>
                 <p class="modelo-hero-v5-desc"><?= htmlspecialchars($modeloInfo['desc']) ?></p>
            </div>
        </div>

        <!-- Área de Conteúdo e Navegação de Materiais -->
        <div class="modelo-content-area-v5">
            <!-- Abas de Navegação -->
            <div class="materials-tabs-v5">
                <button class="tab-btn-v5 active" data-tab="photos">
                    <i class="fas fa-images"></i><span>Fotos</span>
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-btn-v5" data-tab="videos">
                    <i class="fas fa-video"></i><span>Vídeos</span>
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-btn-v5" data-tab="audios">
                    <i class="fas fa-file-audio"></i><span>Áudios</span>
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-btn-v5" data-tab="scripts">
                    <i class="fas fa-comment-dots"></i><span>Roteiros</span>
                     <span class="tab-indicator"></span>
                </button>
            </div>

            <!-- Área de Exibição Dinâmica -->
            <div class="materials-display-area-v5" id="materialsDisplayArea">
                <!-- Conteúdo será carregado aqui via JS -->
                <div class="material-loading-placeholder">
                     <i class="fas fa-spinner fa-spin"></i> Carregando...
                </div>
            </div>
        </div>

        <!-- Dados dos materiais para JS (JSON embutido) -->
        <script type="application/json" id="materialsData">
            <?= json_encode([
                'photos' => $photoList,
                'videos' => $videoList,
                'audios' => $audioList,
                'scripts' => $scriptList
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT /* Pretty print opcional para debug */); ?>
        </script>

    <?php else: ?>
        <!-- Mensagem exibida se nenhuma modelo foi escolhida -->
         <p style="color: var(--text-secondary); text-align: center; padding: 3rem 1rem;">Nenhuma modelo selecionada ainda. <a href="#" data-section="escolher-modelo" class="link-escolher-modelo" style="color: var(--primary); text-decoration: underline;">Escolha sua modelo aqui</a>.</p>
    <?php endif; ?>
</div>

<!-- Lightbox para Fotos (FORA da seção principal, no final do body talvez) -->
<div class="photo-lightbox-v5" id="photoLightboxV5" style="display: none;">
    <button class="photo-lightbox-v5-close">×</button>
    <button class="photo-lightbox-v5-nav prev"><i class="fas fa-chevron-left"></i></button>
    <button class="photo-lightbox-v5-nav next"><i class="fas fa-chevron-right"></i></button>
    <div class="photo-lightbox-v5-image-wrapper">
        <img src="" alt="Foto Ampliada" id="lightboxImageV5">
    </div>
    <div class="photo-lightbox-v5-actions">
        <span class="photo-lightbox-v5-counter" id="lightboxCounterV5">1 / 1</span>
        <a href="#" download="foto_modelo.jpg" class="btn-lightbox-v5-download" id="lightboxDownloadBtnV5">
            <i class="fas fa-download"></i> Baixar Foto
        </a>
    </div>
</div>

<div class="video-lightbox-v5" id="videoLightboxV5" style="display: none;">
    <!-- Reutiliza o botão de fechar do lightbox de fotos -->
    <button class="photo-lightbox-v5-close">×</button>

    <div class="video-lightbox-v5-player-wrapper">
        <video controls preload="metadata" id="lightboxVideoV5" playsinline>
            <!-- O atributo src será definido via JS -->
            <source src="" type="video/mp4"> <!-- Defina o type correto se souber -->
            Seu navegador não suporta a tag de vídeo.
        </video>
    </div>

    <!-- Reutiliza as ações do lightbox de fotos (sem o contador) -->
    <div class="photo-lightbox-v5-actions">
        <!-- Contador removido ou escondido via CSS -->
        <a href="#" download="video_modelo.mp4" class="btn-lightbox-v5-download" id="lightboxVideoDownloadBtnV5">
            <i class="fas fa-download"></i> Baixar Vídeo
        </a>
    </div>
</div>

<!-- ================== FIM DA SEÇÃO MINHA MODELO v5 ================== -->

        </main>
    </div>

    <audio id="notificationSound" src="/sounds/notification.mp3" preload="auto" style="display: none;"></audio>
    
    
    <script>

        // Função Utilitária para escapar HTML
    function escapeHtml(text) {
         if (typeof text !== 'string') return text;
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
     }
    // Função Utilitária para formatar datas
    function formatDate(dateString) {
         const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Data inválida';
        return date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
     }
    // Função Utilitária para primeira letra maiúscula
    function ucFirst(str) {
         if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
     }
    // Função para exibir Toast Notifications
    function showToast(message, isError = false) {
         const existingToast = document.querySelector('.toast-notification');
        if (existingToast) existingToast.remove();
        const toast = document.createElement('div');
        toast.className = `toast-notification ${isError ? 'error' : ''}`;
        toast.innerHTML = `<i class="fas fa-${isError ? 'exclamation-circle' : 'check-circle'}"></i> <span>${escapeHtml(message)}</span>`;
        document.body.appendChild(toast);
        toast.offsetHeight; // Force reflow
        setTimeout(() => {
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }, 10);
     }
    // Função para exibir Modal de Confirmação
    function showConfirmModal(title, message, onConfirm, onCancel = null) {
         const modal = document.getElementById('confirmModal');
        if (!modal) return;
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMessage').innerHTML = message; // Use innerHTML for potential strong tags
        modal.classList.add('show');
        const cancelBtn = document.getElementById('confirmCancel');
        const acceptBtn = document.getElementById('confirmAccept');
        // Clone and replace to remove previous listeners
        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        const newAcceptBtn = acceptBtn.cloneNode(true);
        acceptBtn.parentNode.replaceChild(newAcceptBtn, acceptBtn);
        // Add new listeners
        newCancelBtn.onclick = () => { modal.classList.remove('show'); if (typeof onCancel === 'function') onCancel(); };
        newAcceptBtn.onclick = () => { modal.classList.remove('show'); if (typeof onConfirm === 'function') onConfirm(); };
     }

    document.addEventListener('DOMContentLoaded', function() {
        const userId = <?= json_encode($_SESSION['user_id'] ?? null) ?>;
        const API_BASE = '/api/'; // <<< Certifique-se que está correto
        const statusChamadaLabelsJS = <?= json_encode($statusChamadaLabels ?? []) ?>;
        const statusChamadaClassesJS = <?= json_encode($statusChamadaClasses ?? []) ?>;

        // --- Seletores Globais ---
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const menuItems = document.querySelectorAll('.menu-item[data-section]');
        const sections = {
            'dashboard': document.getElementById('dashboard-section'),
            'solicitar-chamada': document.getElementById('solicitar-chamada-section'),
            'comprar-leads': document.getElementById('comprar-leads-section'),
            'comprovantes': document.getElementById('comprovantes-section'),
            'escolher-modelo': document.getElementById('escolher-modelo-section'),
            'minha-modelo': document.getElementById('minha-modelo-section'),
            'aulas': document.getElementById('aulas-section')
        };
        // <<< MOVED >>> Seletores do formulário de chamada movidos para antes do IF
        const chamadaForm = document.getElementById('chamadaForm');
        const dataInput = document.getElementById('chamadaData');
        const setNowBtn = document.getElementById('setNowBtn');
        const numeroInput = document.getElementById('clienteNumero');
        // <<< END MOVED >>>
        const comprovanteForm = document.getElementById('comprovanteForm');
        const packageButtons = document.querySelectorAll('.package-btn[data-package]');
        const modelCards = document.querySelectorAll('.model-card');
        const cardChamadas = document.getElementById('cardChamadas');
        const chamadasDetails = document.getElementById('chamadasDetails');
        const logoutBtn = document.getElementById('logout-btn');
        const greetingHeader = document.querySelector('.content-header h1');
        const tutorialModal = document.getElementById('tutorialModal'); // <<< Assumindo que o ID do modal do tutorial é este

        // --- Menu Mobile ---
        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            const closeSidebar = () => {
                 sidebar.classList.remove('active');
                 sidebarOverlay.classList.remove('active');
                 document.body.style.overflow = '';
            }
            sidebarOverlay.addEventListener('click', closeSidebar);
            menuItems.forEach(item => item.addEventListener('click', closeSidebar));
            document.querySelector('.sidebar a.user-profile')?.addEventListener('click', closeSidebar);
            logoutBtn?.addEventListener('click', closeSidebar);
        }

        // --- Navegação entre Seções ---
        menuItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = item.getAttribute('data-section');
                Object.values(sections).forEach(sec => { if (sec) sec.style.display = 'none'; });
                if (sections[sectionId]) sections[sectionId].style.display = 'block';
                if (greetingHeader) greetingHeader.style.display = (sectionId === 'dashboard') ? 'flex' : 'none';
                menuItems.forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                // Close sidebar handled by closeSidebar listener attached above
            });
        });

        // --- Tooltip de Saldo ---
        const saldoCard = document.getElementById('saldoCard');
        const saldoTooltipModal = document.getElementById('saldoTooltipModal');
        const closeSaldoTooltip = saldoTooltipModal?.querySelector('.close-modal');
        if (saldoCard && saldoTooltipModal && closeSaldoTooltip) {
            const closeSaldoAction = () => {
                 saldoTooltipModal.classList.remove('active');
                 document.body.style.overflow = '';
            };
            saldoCard.addEventListener('click', (e) => { e.stopPropagation(); saldoTooltipModal.classList.add('active'); document.body.style.overflow = 'hidden'; });
            closeSaldoTooltip.addEventListener('click', (e) => { e.stopPropagation(); closeSaldoAction(); });
            saldoTooltipModal.addEventListener('click', (e) => { if (e.target === saldoTooltipModal) closeSaldoAction(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && saldoTooltipModal.classList.contains('active')) closeSaldoAction(); });
        }

        // ==============================================================
        // === INÍCIO: Lógica Completa para Solicitar Chamada (v2) ===
        // ==============================================================
        console.log("JS: Configurando Formulário Solicitar Chamada...");

        if (chamadaForm && dataInput && setNowBtn && numeroInput) {

            // --- Lógica do Botão "Agora" ---
            setNowBtn.addEventListener('click', () => {
                const now = new Date();
                // Formata para YYYY-MM-DDTHH:MM (padrão para datetime-local)
                const year = now.getFullYear();
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                const day = now.getDate().toString().padStart(2, '0');
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;

                dataInput.value = formattedDateTime;
                console.log("Data/Hora definida para Agora:", formattedDateTime);
            });

            // --- Lógica do Submit do Formulário ---
            chamadaForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                console.log("JS: Submit do formulário de chamada iniciado.");

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Solicitando...';

                // --- Validações ---
                // 1. Número do Cliente (apenas verifica se não está vazio)
                if (!numeroInput.value.trim()) {
                    showToast('O número do cliente é obrigatório.', true);
                    numeroInput.focus();
                    submitBtn.disabled = false; submitBtn.innerHTML = originalBtnText;
                    console.log("JS: Validação falhou - Número vazio.");
                    return;
                }
                const numero = numeroInput.value.trim(); // Pega o valor bruto (sem limpar caracteres não numéricos)

                // 2. Data/Hora (apenas verifica se foi preenchido)
                const dataValue = dataInput.value;
                if (!dataValue) {
                    showToast('Selecione a data e hora da chamada.', true);
                    dataInput.focus();
                    submitBtn.disabled = false; submitBtn.innerHTML = originalBtnText;
                    console.log("JS: Validação falhou - Data vazia.");
                    return;
                }

                // --- Coleta outros dados ---
                const duracao = document.getElementById('chamadaDuracao').value;
                const nome = document.getElementById('clienteNome').value;
                const observacao = document.getElementById('chamadaObservacao').value;

                console.log("JS: Dados para envio:", { nome, numero, dataValue, duracao, observacao });

                // --- Envio para API ---
                try {
                    const response = await fetch(`${API_BASE}solicitar_chamada.php`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            clienteNome: nome,
                            clienteNumero: numero, // Envia o número como foi digitado
                            chamadaData: dataValue,
                            chamadaDuracao: duracao,
                            chamadaObservacao: observacao
                        })
                    });

                    // Processa a resposta JSON (mesmo que seja erro)
                    let dataResponse;
                    try {
                        dataResponse = await response.json();
                    } catch (jsonError) {
                        console.error("JS: Erro ao parsear JSON da resposta:", jsonError);
                        const responseText = await response.text();
                        throw new Error(`Resposta inválida do servidor: ${responseText || response.statusText}`);
                    }


                    if (!response.ok || !dataResponse.success) {
                        console.error("JS: Erro da API:", dataResponse);
                        throw new Error(dataResponse.message || `Erro ${response.status} ao agendar chamada.`);
                    }

                    // --- Sucesso ---
                    console.log("JS: Chamada agendada com sucesso:", dataResponse);
                    showToast('Chamada agendada com sucesso!');
                    this.reset(); // Limpa o formulário

                    // Tenta atualizar UI relacionada a chamadas (se as funções existirem)
                    if (typeof fetchChamadasCountAndUpdateUI === 'function') {
                        fetchChamadasCountAndUpdateUI();
                    }
                    // Verifique se chamadasDetails existe antes de acessar classList
                    if (typeof loadChamadasList === 'function' && chamadasDetails && chamadasDetails.classList.contains('show')) {
                        loadChamadasList();
                    }

                } catch (error) {
                    console.error('JS: Erro no fetch ao solicitar chamada:', error);
                    showToast(error.message || 'Erro de conexão ao agendar. Tente novamente.', true);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }); // Fim do event listener 'submit'

            // --- Lógica do Botão Cancelar ---
            const cancelarBtn = document.getElementById('cancelarChamada');
            if (cancelarBtn) {
                 cancelarBtn.addEventListener('click', () => {
                     chamadaForm.reset();
                     showToast('Formulário limpo.');
                     console.log("JS: Formulário de chamada cancelado/limpo.");
                 });
            }

        } else {
            console.warn("JS: Formulário 'Solicitar Chamada' (chamadaForm) ou seus elementos essenciais (dataInput, setNowBtn, numeroInput) não encontrados no DOM. A funcionalidade não estará ativa.");
        }
        // ==============================================================
        // === FIM: Lógica Completa para Solicitar Chamada (v2) ========
        // ==============================================================


        // --- LÓGICA DE COMPRA DE LEADS (sem alterações) ---
        document.querySelector('.packages-container')?.addEventListener('click', function(e) {
            const button = e.target.closest('.package-btn[data-package]');
            if (!button) return;

            const leads = button.dataset.package;
            const price = button.dataset.price;
            const description = button.dataset.description || `Pacote ${leads} Leads`;
            const priceFormatted = parseFloat(price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

            console.log("[Checkout Link] Botão Clicado:", { leads, price, description });
            const checkoutFileName = `checkout_${leads}.php`;
            const checkoutUrl = `${checkoutFileName}?package=${leads}&price=${price}&description=${encodeURIComponent(description)}`;

            showConfirmModal(
                `Confirmar Compra - ${escapeHtml(description)}`,
                `Deseja comprar <strong>${escapeHtml(description)}</strong> por <strong>${priceFormatted}</strong>?<br>Você será redirecionado para a página de pagamento.`,
                () => {
                    console.log("[Checkout Link] Redirecionando para:", checkoutUrl);
                    window.location.href = checkoutUrl;
                }
            );
        });
        // --- FIM DA LÓGICA DE COMPRA DE LEADS ---

        // --- Formulário: Enviar Comprovante (sem alterações) ---
        if (comprovanteForm) {
            const comprovanteFileInput = document.getElementById('comprovanteFile');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const previewContainer = document.getElementById('previewContainer');
            const previewContent = document.getElementById('previewContent');

            if (comprovanteFileInput && fileNameDisplay && previewContainer && previewContent) {
                comprovanteFileInput.addEventListener('change', function(e) {
                    previewContainer.style.display = 'none';
                    previewContent.innerHTML = '';
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        fileNameDisplay.textContent = file.name;
                        fileNameDisplay.style.color = 'var(--text-primary)';
                        previewContainer.style.display = 'block';

                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (ev) => {
                                const img = document.createElement('img');
                                img.src = ev.target.result;
                                img.classList.add('preview-image');
                                previewContent.appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        } else {
                             const fileIcon = document.createElement('i');
                            fileIcon.className = 'fas fa-file preview-file-icon';
                            if (file.type === 'application/pdf') fileIcon.classList.add('fa-file-pdf');
                            previewContent.appendChild(fileIcon);
                            const fileInfo = document.createElement('div');
                            fileInfo.classList.add('preview-file-info');
                            fileInfo.innerHTML = `(${escapeHtml(file.type)})<br><small>Preview não disponível</small>`;
                            previewContent.appendChild(fileInfo);
                        }
                    } else {
                        fileNameDisplay.textContent = 'Nenhum arquivo selecionado';
                        fileNameDisplay.style.color = 'var(--text-secondary)';
                    }
                });
            }

            comprovanteForm.addEventListener('submit', async function(e) {
                 e.preventDefault();
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                const formData = new FormData(this);
                if (!comprovanteFileInput?.files?.[0]) {
                    showToast('Selecione um arquivo de comprovante.', true);
                    submitBtn.disabled = false; submitBtn.innerHTML = originalBtnText; return;
                }

                try {
                    const response = await fetch(`${API_BASE}enviar_comprovante.php`, { method: 'POST', body: formData });
                    const data = await response.json();
                    if (!response.ok || !data.status) throw new Error(data.message || `Erro ${response.status}.`);

                    const saldoElement = document.querySelector('.card-saldo .card-value');
                    const totalElement = document.querySelector('.card-saldo .card-footer');
                    if (saldoElement && totalElement && data.data && typeof data.data.novo_saldo !== 'undefined' && typeof data.data.novo_total_comprovantes !== 'undefined') {
                        saldoElement.textContent = 'R$ ' + data.data.novo_saldo.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        totalElement.textContent = 'Total comprovantes: R$ ' + data.data.novo_total_comprovantes.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        document.getElementById('saldoCard')?.classList.add('updating');
                        setTimeout(() => document.getElementById('saldoCard')?.classList.remove('updating'), 1000);
                     } else {
                        console.warn("Não foi possível atualizar a UI do saldo. Dados ausentes.");
                        showToast('Comprovante enviado! Atualize a página para ver o saldo atualizado.', false);
                    }
                    showToast(data.message || 'Comprovante enviado com sucesso!');
                    this.reset();
                    if (fileNameDisplay) fileNameDisplay.textContent = 'Nenhum arquivo selecionado';
                    if (previewContainer) previewContainer.style.display = 'none';
                    if (previewContent) previewContent.innerHTML = '';

                } catch (error) {
                    console.error('Erro ao enviar comprovante:', error);
                    showToast(error.message || 'Erro de conexão ao enviar. Tente novamente.', true);
                } finally {
                    submitBtn.disabled = false; submitBtn.innerHTML = originalBtnText;
                }
            });
             document.getElementById('cancelarComprovante')?.addEventListener('click', () => {
                 comprovanteForm.reset();
                 if (fileNameDisplay) fileNameDisplay.textContent = 'Nenhum arquivo selecionado';
                 if (previewContainer) previewContainer.style.display = 'none';
                 if (previewContent) previewContent.innerHTML = '';
                 showToast('Formulário limpo.');
             });
        }


        // --- Escolher Modelo IA (sem alterações) ---
         modelCards.forEach(card => {
            card.addEventListener('click', function() {
                const modeloId = this.getAttribute('data-modelo');
                const modeloNome = this.querySelector('.model-name')?.textContent || `Modelo ${modeloId}`;
                showConfirmModal(
                    `Escolher ${modeloNome}?`,
                    `Confirma a seleção do modelo <strong>${escapeHtml(modeloNome)}</strong>?<br><strong style="color: var(--danger);">Esta ação é IRREVERSÍVEL!</strong>`,
                    async () => {
                        try {
                            showToast('Salvando sua escolha...', false);
                            const response = await fetch(`${API_BASE}escolher_modelo.php`, {
                                method: 'POST', headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ modelo: modeloId })
                            });
                            const data = await response.json();
                            if (!response.ok || !data.success) throw new Error(data.message || `Erro ${response.status} ao salvar.`);
                            showToast('Modelo selecionado! Recarregando...', false);
                            setTimeout(() => window.location.reload(), 2000);
                        } catch (error) {
                            console.error('Erro ao escolher modelo:', error);
                            showToast(error.message || 'Erro ao salvar. Tente novamente.', true);
                        }
                    }
                );
            });
        });



        // --- Configuração de Chamadas (Display, etc. - sem alterações na lógica interna) ---
         function setupChamadasInteraction() {
            if (!cardChamadas || !chamadasDetails) return;
            cardChamadas.addEventListener('click', function() {
                const isShowing = chamadasDetails.classList.toggle('show');
                if (isShowing) loadChamadasList(); // Load list when opening
            });
             chamadasDetails.addEventListener('click', function(e) {
                 const refreshBtn = e.target.closest('#refreshChamadasBtn');
                 const clearBtn = e.target.closest('#limparChamadasBtn');
                 if (refreshBtn) { e.preventDefault(); refreshChamadasHandler(refreshBtn); }
                 else if (clearBtn) { e.preventDefault(); handleLimparChamadas(); }
             });
        }
         function updateChamadasCountersUI(countersData) {
            const countElement = document.getElementById('chamadasTotalCount');
            if (countElement) countElement.textContent = countersData?.total ?? 0;
         }
         async function fetchChamadasCountAndUpdateUI() {
            if (!userId) return;
            try {
                const response = await fetch(`${API_BASE}get_chamadas.php?count=true&user_id=${userId}&t=${Date.now()}`);
                if (!response.ok) throw new Error(`Erro ${response.status} ao buscar contadores.`);
                const data = await response.json();
                if (data.success && data.counters) {
                    updateChamadasCountersUI(data.counters);
                    localStorage.setItem('lastChamadasCount', JSON.stringify(data.counters));
                } else { throw new Error(data.message || 'Resposta inválida da API de contadores.'); }
            } catch (error) {
                console.error("Erro ao buscar contadores:", error);
                const cached = localStorage.getItem('lastChamadasCount');
                if (cached) updateChamadasCountersUI(JSON.parse(cached));
            }
        }
         async function loadChamadasList() {
            const contentArea = document.getElementById('chamadasContentArea');
            if (!contentArea || !userId) return Promise.reject("UI ou UserID ausente.");
            showLoadingOverlay(contentArea);
            try {
                const response = await fetch(`${API_BASE}get_chamadas.php?list=true&user_id=${userId}&t=${Date.now()}`);
                 if (!response.ok) throw new Error(`Falha ao buscar lista: ${response.status}`);
                const data = await response.json();
                if (data.success && Array.isArray(data.chamadas)) renderChamadasListContent(data.chamadas);
                else throw new Error(data.message || 'Resposta inválida da lista.');
                return Promise.resolve();
            } catch (error) {
                console.error('Erro no loadChamadasList:', error);
                renderChamadasListContent(null, error.message);
                return Promise.reject(error);
            } finally { hideLoadingOverlay(contentArea); }
        }
         function renderChamadasListContent(chamadas, errorMessage = null) {
            const contentArea = document.getElementById('chamadasContentArea');
            if (!contentArea) return;
            let contentHTML = '';
            if (errorMessage) {
                contentHTML = `<div class="alert-message alert-error" style="margin-top: 1rem;"><i class="fas fa-exclamation-triangle"></i> ${escapeHtml(errorMessage)} <button class="btn btn-secondary btn-sm btn-retry" style="margin-left: 1rem;" onclick="loadChamadasList()">Tentar Novamente</button></div>`;
            } else if (!chamadas || chamadas.length === 0) {
                contentHTML = `<p style="color: var(--text-secondary); text-align: center; margin-top: 1rem;" id="noChamadasMessage">Nenhuma chamada encontrada.</p>`;
            } else {
                let tableRows = '';
                chamadas.forEach(c => {
                    const statusKey = (c.status || 'desconhecido').toLowerCase();
                    tableRows += `
                        <tr>
                            <td data-label="Cliente">${escapeHtml(c.cliente_nome || c.cliente_numero || 'N/A')}</td>
                            <td data-label="Data/Hora">${formatDate(c.data_hora)}</td>
                            <td data-label="Duração">${escapeHtml(c.duracao || '-')}</td>
                            <td data-label="Status"><span class="status-badge ${escapeHtml(statusChamadaClassesJS[statusKey] || 'status-desconhecido')}">${escapeHtml(statusChamadaLabelsJS[statusKey] || ucFirst(statusKey))}</span></td>
                        </tr>`;
                });
                contentHTML = `<div style="overflow-x:auto;" id="chamadasTableContainer"><table class="chamadas-table"><thead><tr><th>Cliente</th><th>Data/Hora</th><th>Duração</th><th>Status</th></tr></thead><tbody>${tableRows}</tbody></table></div>`;
            }
            contentArea.innerHTML = contentHTML;
        }
        async function refreshChamadasHandler(buttonElement) {
            if (!buttonElement) return;
            const originalButtonHTML = buttonElement.innerHTML;
            buttonElement.disabled = true; buttonElement.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
            try { await loadChamadasList(); }
            catch (err) { showToast('Erro ao atualizar a lista de chamadas.', true); }
            finally {
                const currentRefreshBtn = document.getElementById('refreshChamadasBtn');
                 if (currentRefreshBtn) {
                     currentRefreshBtn.disabled = false;
                     currentRefreshBtn.innerHTML = originalButtonHTML;
                 }
            }
        }
         async function handleLimparChamadas() {
             if (!userId) return;
             showConfirmModal('Limpar Histórico', 'Limpar chamadas concluídas/canceladas? (Pendente/Em Andamento NÃO serão afetadas). Ação irreversível.', async () => {
                try {
                    const response = await fetch(`${API_BASE}limpar_chamadas.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: userId }) });
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || `Erro ${response.status}`);
                    showToast('Histórico limpo com sucesso!');
                    fetchChamadasCountAndUpdateUI();
                    loadChamadasList();
                } catch (error) {
                    console.error('Erro ao limpar chamadas:', error);
                    showToast(error.message || 'Erro ao limpar histórico.', true);
                }
             });
        }
         function showLoadingOverlay(container) {
             if (!container) return;
             container.style.position = container.style.position || 'relative';
             container.style.minHeight = '100px';
             let overlay = container.querySelector('.loading-overlay');
             if (!overlay) {
                 overlay = document.createElement('div');
                 overlay.className = 'loading-overlay';
                 Object.assign(overlay.style, { position: 'absolute', inset: '0', background: 'rgba(18, 18, 18, 0.7)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: '10', borderRadius: 'inherit', opacity: '0', transition: 'opacity 0.2s ease-in-out' });
                 overlay.innerHTML = '<i class="fas fa-spinner fa-spin fa-2x" style="color: var(--text-primary);"></i>';
                 container.appendChild(overlay);
             }
             overlay.offsetHeight; overlay.style.opacity = '1';
         }
        function hideLoadingOverlay(container) {
            const overlay = container?.querySelector('.loading-overlay');
            if (overlay) {
                overlay.style.opacity = '0';
                setTimeout(() => {
                     overlay.remove();
                     if(container) {
                         container.style.position = ''; container.style.minHeight = '';
                     }
                 }, 200);
            }
        }

        // --- Logout (sem alterações) ---
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                showConfirmModal('Confirmar Saída', 'Deseja realmente sair?', () => { window.location.href = 'logout.php'; });
            });
        }

        // --- WhatsApp Logic (sem alterações) ---
         const whatsappCard = document.getElementById('whatsappCard');
         const whatsappModal = document.getElementById('whatsappModal');
         const whatsappStatusEl = document.getElementById('whatsappStatus');
         const whatsappFooterEl = document.getElementById('whatsappFooter');
         const whatsappConfirmBtn = whatsappModal?.querySelector('.whatsapp-confirm-btn');
         const closeWhatsAppModalBtn = whatsappModal?.querySelector('.close-modal');

         const whatsappState = {
            solicitado: <?= $whatsappSolicitado ? 'true' : 'false' ?>,
            aprovado: <?= $whatsappAprovado ? 'true' : 'false' ?>,
            numero: '<?= addslashes($whatsappNumero) ?>'
         };

         function updateWhatsAppUI() {
            if (!whatsappCard || !whatsappStatusEl || !whatsappFooterEl) return;
            whatsappCard.style.cursor = 'default';
            whatsappCard.style.animation = 'none'; // Stop animation by default

            if (whatsappState.aprovado) {
                whatsappStatusEl.textContent = `Ativo: ${whatsappState.numero || ''}`;
                whatsappFooterEl.textContent = 'Pronto para uso!';
            } else if (whatsappState.solicitado) {
                whatsappStatusEl.textContent = 'Em Processamento';
                whatsappFooterEl.textContent = 'Aguardando ativação';
            } else {
                whatsappStatusEl.textContent = 'Ativar Número';
                whatsappFooterEl.textContent = 'Clique para solicitar grátis';
                whatsappCard.style.cursor = 'pointer';
                whatsappCard.style.animation = 'pulseWhatsApp 2s infinite'; // Start animation only if not requested/approved
            }
        }
         function openWhatsAppModal() { if (whatsappModal) { whatsappModal.classList.add('active'); document.body.style.overflow = 'hidden'; }}
         function closeWhatsAppModal() { if (whatsappModal) { whatsappModal.classList.remove('active'); document.body.style.overflow = ''; }}

         if (whatsappModal && closeWhatsAppModalBtn && whatsappConfirmBtn) {
            closeWhatsAppModalBtn.onclick = closeWhatsAppModal;
            whatsappModal.onclick = (e) => { if (e.target === whatsappModal) closeWhatsAppModal(); };
            whatsappConfirmBtn.onclick = async function() {
                const originalText = this.innerHTML; this.disabled = true; this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                try {
                    const response = await fetch(`${API_BASE}solicitar_whatsapp.php`, {
                         method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                         body: JSON.stringify({ user_id: userId })
                    });
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) { throw new Error(`Resposta inválida: ${await response.text()}`); }
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || 'Erro ao processar');
                    whatsappState.solicitado = true;
                    updateWhatsAppUI(); // Update state and UI
                    showToast('Solicitação enviada!');
                    closeWhatsAppModal();
                } catch (error) {
                    console.error('Erro ao solicitar WhatsApp:', error);
                    showToast(error.message || 'Erro. Tente novamente.', true);
                } finally { this.disabled = false; this.innerHTML = originalText; }
            };
         }

        // --- Server-Sent Events (SSE) (sem alterações) ---
        let eventSource = null; let sseRetryAttempts = 0; const MAX_SSE_RETRIES = 5;
        function setupSSEConnection() {
            if (eventSource) { eventSource.close(); console.log("SSE: Old connection closed."); }
            if (!userId) { console.log("SSE: No User ID, skipping connection."); return; }
            const sseUrl = `${API_BASE}realtime_updates.php?user_id=${userId}&t=${Date.now()}`;
            console.log("SSE: Connecting to:", sseUrl);
            eventSource = new EventSource(sseUrl, { withCredentials: true });
            eventSource.onopen = () => { console.log("SSE: Connected."); sseRetryAttempts = 0; };
            eventSource.addEventListener('chamadas_update', (event) => {
                 try {
                     const data = JSON.parse(event.data); console.log("SSE: chamadas_update", data);
                     updateChamadasCountersUI(data);
                     if (chamadasDetails?.classList.contains('show')) loadChamadasList();
                 } catch (e) { console.error("SSE chamadas_update error:", e, event.data); }
            });
            eventSource.addEventListener('whatsapp_update', (event) => {
                try {
                    const data = JSON.parse(event.data); console.log("SSE: whatsapp_update", data);
                    Object.assign(whatsappState, data); // Update state object
                    updateWhatsAppUI(); // Update UI based on new state
                } catch (e) { console.error("SSE whatsapp_update error:", e, event.data); }
            });
               eventSource.addEventListener('notifications_update', (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        console.log("SSE Recebido: notifications_update", data);
                        if (typeof data.unread_count !== 'undefined') {
                            const newCount = parseInt(data.unread_count, 10);
                            if (!isNaN(newCount)) {
                                const countIncreased = newCount > currentUnreadCount;
                                updateNotificationBadge(newCount); // Atualiza badge
                                if (countIncreased && notificationSound && audioUnlocked) {
                                    console.log("Tocando som e animando sino.");
                                    notificationSound.currentTime = 0;
                                    notificationSound.play().catch(e => console.warn("Falha ao tocar som:", e));
                                    const animationDuration = 700;
                                    const bells = document.querySelectorAll('.notification-bell');
                                    bells.forEach(bell => {
                                        bell.classList.remove('animate-ring');
                                        void bell.offsetWidth;
                                        bell.classList.add('animate-ring');
                                        setTimeout(() => { bell.classList.remove('animate-ring'); }, animationDuration);
                                    });
                                } else if (countIncreased && !audioUnlocked) {
                                    console.warn("Notificação nova, áudio bloqueado.");
                                }
                            } else { console.error("SSE count inválido:", data.unread_count); }
                        } else { console.warn("SSE sem unread_count:", data); }
                    } catch (e) { console.error("SSE Erro processar notifications_update:", e, event.data); }
                });
            eventSource.addEventListener('heartbeat', (event) => { /* console.log('SSE Heartbeat'); */ });
            eventSource.onerror = (err) => {
                 console.error('SSE: Connection error:', err); eventSource.close();
                 if (sseRetryAttempts < MAX_SSE_RETRIES) {
                    const delay = Math.min(3000 * Math.pow(2, sseRetryAttempts), 30000);
                    console.log(`SSE: Retrying in ${delay/1000}s (${sseRetryAttempts+1}/${MAX_SSE_RETRIES})`);
                    setTimeout(() => { sseRetryAttempts++; setupSSEConnection(); }, delay);
                 } else { console.error('SSE: Max retries reached.'); showToast('Erro na conexão real-time. Atualize a página.', true); }
            };
        }

        // --- Notificações (sem alterações) ---
        const NOTIFICATIONS_ENDPOINT = API_BASE + 'notifications.php';
        const notificationBells = document.querySelectorAll('.notification-bell');
        const notificationsModal = document.getElementById('notificationsModal');
        const notificationsModalContent = notificationsModal?.querySelector('.modal-content');
        const notificationsList = document.getElementById('notificationsList');
        const closeNotificationsButton = notificationsModal?.querySelector('.close-modal');
        const deleteAllNotificationsButton = notificationsModal?.querySelector('.delete-all');
        const notificationSound = document.getElementById('notificationSound');
        let currentUnreadCount = 0; let isNotificationsModalOpen = false; let lastNotificationIdReceived = 0; let audioUnlocked = false; let isMarkingRead = false;

         function unlockAudioContext() {
            if (audioUnlocked || !notificationSound) return;
            console.log("Attempting to unlock audio context...");
            const vol = notificationSound.volume; notificationSound.volume = 0;
            notificationSound.play().then(() => {
                notificationSound.pause(); notificationSound.currentTime = 0; notificationSound.volume = vol; audioUnlocked = true; console.log("Audio context UNLOCKED.");
                ['click', 'touchstart', 'keydown'].forEach(evt => document.body.removeEventListener(evt, unlockAudioContext, true));
            }).catch(error => { console.warn("Audio unlock failed (requires user interaction):", error); notificationSound.volume = vol; });
        }
        ['click', 'touchstart', 'keydown'].forEach(evt => document.body.addEventListener(evt, unlockAudioContext, { once: true, capture: true }));

         function processNewNotificationSSE(notificationData) { // Chamada pelo listener SSE
             if (!notificationData?.id || (notificationData.id <= lastNotificationIdReceived && lastNotificationIdReceived > 0)) return;
             lastNotificationIdReceived = notificationData.id;
             const previousCount = currentUnreadCount;
             const newCount = typeof notificationData.unread_count !== 'undefined' ? notificationData.unread_count : currentUnreadCount + 1;
             updateNotificationBadge(newCount);
             if (currentUnreadCount > previousCount && notificationSound && audioUnlocked) {
                 console.log("Playing notification sound."); notificationSound.currentTime = 0; notificationSound.play().catch(e => console.warn("Sound play failed:", e));
             } else if (currentUnreadCount > previousCount && !audioUnlocked) { console.warn("New notification, but audio locked."); }
             if (!isNotificationsModalOpen) showNotificationAlert(notificationData); else loadNotifications();
         }

         function updateNotificationBadge(count) {
             count = Math.max(0, parseInt(count) || 0);
             if (count === currentUnreadCount && document.querySelector('.notification-badge')?.style.display !== 'none') return;
             console.log(`Updating badge to ${count}`); currentUnreadCount = count;
             document.querySelectorAll('.notification-badge').forEach(badge => {
                 if (!badge) return; badge.textContent = count > 99 ? '99+' : count.toString(); badge.style.display = count > 0 ? 'flex' : 'none';
                 badge.classList.add('badge-update'); setTimeout(() => badge.classList.remove('badge-update'), 300);
             });
             document.querySelectorAll('.notification-bell').forEach(bell => bell?.classList.toggle('has-unread', count > 0));
         }
         function openNotificationsModal() {
            if (!notificationsModal) return; isNotificationsModalOpen = true; notificationsModal.style.display = 'flex';
            setTimeout(() => { notificationsModal.style.opacity = '1'; if (notificationsModalContent) { notificationsModalContent.style.opacity = '1'; notificationsModalContent.style.transform = 'translateY(0)'; } }, 10);
            loadNotifications();
         }
         function closeNotificationsModal() {
            if (!notificationsModal) return; isNotificationsModalOpen = false; notificationsModal.style.opacity = '0'; if (notificationsModalContent) { notificationsModalContent.style.opacity = '0'; notificationsModalContent.style.transform = 'translateY(20px)'; }
            setTimeout(() => { notificationsModal.style.display = 'none'; }, 300);
         }
         async function loadNotifications() {
             if (!notificationsList || !userId) return;
             notificationsList.innerHTML = `<div class="loading-notifications">Carregando...</div>`;
             try {
                const response = await fetch(`${NOTIFICATIONS_ENDPOINT}?user_id=${userId}&limit=50&t=${Date.now()}`);
                if (!response.ok) throw new Error(`Erro ${response.status}`);
                const data = await response.json();
                if (data.success && data.notifications) {
                     renderNotificationsList(data.notifications);
                     const loadedUnreadCount = data.notifications.filter(n => !n.is_read).length;
                     updateNotificationBadge(loadedUnreadCount);
                     if (data.notifications.length > 0) lastNotificationIdReceived = Math.max(lastNotificationIdReceived, ...data.notifications.map(n => n.id || 0));
                } else { throw new Error(data.message || 'Falha ao obter notifics.'); }
             } catch (error) { console.error('Erro ao carregar notifics:', error); notificationsList.innerHTML = `<div class="empty-notifications error">Erro ao carregar. Tente novamente.</div>`; }
        }
         function renderNotificationsList(notifications) {
             if (!notificationsList) return;
             if (!notifications || notifications.length === 0) { notificationsList.innerHTML = `<div class="empty-notifications"><i class="fas fa-bell-slash"></i><p>Nenhuma notificação.</p></div>`; return; }
             notificationsList.innerHTML = notifications.map(n => `
                <div class="notification-item ${n.is_read ? 'read' : 'unread'}" data-id="${n.id}">
                    <div class="notification-header">
                        <div class="notification-avatar-container"> <img src="/uploads/avatars/${escapeHtml(n.admin_avatar || 'default.jpg')}" class="notification-avatar" onerror="this.onerror=null; this.src='/uploads/avatars/default.jpg';"> </div>
                        <div class="notification-title-wrapper">
                            <div class="notification-title">${escapeHtml(n.title)}</div>
                            ${n.admin_name ? `<div class="notification-admin"><i class="fas fa-user-shield"></i> <span>${escapeHtml(n.admin_name)}</span></div>` : ''}
                        </div>
                        <div class="notification-time ${n.is_read ? '' : 'unread'}">${formatDate(n.created_at)}</div>
                    </div>
                    <div class="notification-message">${escapeHtml(n.message)}</div>
                </div>`).join('');
             notificationsList.querySelectorAll('.notification-item.unread').forEach(item => {
                 item.addEventListener('click', () => markNotificationAsRead(item.dataset.id, item));
             });
         }
        async function markNotificationAsRead(notificationId, element) {
             if (!userId || isMarkingRead || !element || !element.classList.contains('unread')) return;
             isMarkingRead = true;
             element.classList.remove('unread'); element.classList.add('read', 'reading'); // Add 'reading' for animation
             try {
                 const response = await fetch(NOTIFICATIONS_ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mark_as_read: true, notification_ids: [notificationId], user_id: userId }) });
                 if (!response.ok) throw new Error('Falha ao marcar como lida.');
                 const data = await response.json(); // Assume API returns new count
                 if (data.success && typeof data.new_unread_count !== 'undefined') {
                    updateNotificationBadge(data.new_unread_count);
                 } else {
                     updateNotificationBadge(currentUnreadCount - 1); // Fallback: decrement client-side
                 }
                 setTimeout(() => element.classList.remove('reading'), 500); // Remove animation class
             } catch (error) {
                 console.error("Erro markRead:", error); showToast('Erro ao marcar notificação.', true);
                 element.classList.add('unread'); element.classList.remove('read', 'reading');
             } finally { isMarkingRead = false; }
         }
        async function clearAllNotifications() {
             if (!userId || !deleteAllNotificationsButton) return;
             showConfirmModal('Limpar Notificações', 'Remover TODAS as notificações? (Irreversível)', async () => {
                 const btn = deleteAllNotificationsButton; const originalHTML = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Limpando...';
                 try {
                     const response = await fetch(NOTIFICATIONS_ENDPOINT, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: userId }) });
                     const data = await response.json(); if (!response.ok || !data.success) throw new Error(data.message || `Erro ${response.status}`);
                     if (notificationsList) notificationsList.innerHTML = `<div class="empty-notifications"><i class="fas fa-check-circle" style="color:var(--success);"></i><p>Notificações limpas.</p></div>`;
                     updateNotificationBadge(0); showToast('Notificações limpas.');
                 } catch (error) { console.error('Erro ao limpar:', error); showToast(error.message || 'Erro ao limpar.', true); }
                 finally { btn.disabled = false; btn.innerHTML = originalHTML; }
             });
        }
         if (notificationsModal) {
             notificationBells.forEach(bell => bell.addEventListener('click', openNotificationsModal));
             if (closeNotificationsButton) closeNotificationsButton.addEventListener('click', closeNotificationsModal);
             if (deleteAllNotificationsButton) deleteAllNotificationsButton.addEventListener('click', clearAllNotifications);
             notificationsModal.addEventListener('click', (e) => { if (e.target === notificationsModal) closeNotificationsModal(); });
         }
         async function fetchInitialNotificationData() {
             if (!userId) { setupSSEConnection(); return; } // Connect SSE even if no user ID for potential later use? Or just return.
             console.log("Fetching initial notification data...");
             try {
                 const response = await fetch(`${NOTIFICATIONS_ENDPOINT}?count_unread=true&get_last_id=true&user_id=${userId}&t=${Date.now()}`);
                 if (!response.ok) throw new Error(`HTTP ${response.status}`);
                 const data = await response.json();
                 if (data.success) {
                     console.log("Initial data received:", data);
                     updateNotificationBadge(data.count || 0);
                     lastNotificationIdReceived = data.last_id || 0;
                 } else { console.warn("API error fetching initial data:", data.message); updateNotificationBadge(0); }
             } catch (error) { console.error('Error fetching initial notification data:', error); updateNotificationBadge(0); }
             finally { setupSSEConnection(); } // Connect SSE AFTER getting initial state
         }
         function addDynamicNotificationAlertStyles() {
             if (document.getElementById('notification-alert-styles')) return; const style = document.createElement('style'); style.id = 'notification-alert-styles'; style.textContent = ` .notification-alert { position: fixed; bottom: 20px; right: 20px; background: linear-gradient(145deg, var(--dark), var(--darkest)); border: 1px solid rgba(255, 255, 255, 0.1); border-left: 4px solid var(--primary); border-radius: 10px; padding: 1rem; width: 340px; max-width: calc(100% - 40px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4); transform: translateX(120%); opacity: 0; transition: transform .5s cubic-bezier(.2, .8, .2, 1), opacity .4s ease-out; z-index: 10000; cursor: pointer; overflow: hidden; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); } .notification-alert.show { transform: translateX(0); opacity: 1; } .notification-alert-content { display: flex; gap: 15px; align-items: flex-start; } .notification-alert-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); background-color: var(--dark); flex-shrink: 0; } .notification-alert-body { flex: 1; min-width: 0; } .notification-alert-title { font-weight: 600; color: var(--text-primary); font-size: 1rem; line-height: 1.4; margin-bottom: .3rem; word-break: break-word; } .notification-alert-message { color: var(--text-secondary); font-size: .9rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; margin-bottom: .5rem; } .notification-alert-time { font-size: .75rem; color: var(--text-secondary); background: rgba(255, 255, 255, 0.05); padding: 3px 10px; border-radius: 12px; font-weight: 500; display: inline-block; } .notification-alert-close { position: absolute; top: 8px; right: 8px; background: none; border: none; color: var(--text-secondary); font-size: 1.5rem; line-height: 1; padding: 5px; cursor: pointer; transition: all .2s ease; opacity: .7; } .notification-alert:hover .notification-alert-close { opacity: 1; } .notification-alert-close:hover { color: var(--primary); transform: scale(1.1); } `; document.head.appendChild(style);
         }
         function showNotificationAlert(notification) {
              const existingAlert = document.querySelector('.notification-alert'); if (existingAlert) closeNotificationAlert(existingAlert); const alertDiv = document.createElement('div'); alertDiv.className = 'notification-alert'; alertDiv.dataset.id = notification.id; const avatarFilename = notification.admin_avatar?.trim() || 'default.jpg'; const avatarUrl = `/uploads/avatars/${escapeHtml(avatarFilename)}?v=${Date.now()}`; alertDiv.innerHTML = ` <div class="notification-alert-content"> <img src="${avatarUrl}" class="notification-alert-avatar" onerror="this.onerror=null; this.src='/uploads/avatars/default.jpg';"> <div class="notification-alert-body"> <div class="notification-alert-title">${escapeHtml(notification.title)}</div> <div class="notification-alert-message">${escapeHtml(notification.message)}</div> <div class="notification-alert-time">${formatDate(notification.created_at)}</div> </div> </div> <button class="notification-alert-close" title="Fechar">×</button> `; document.body.appendChild(alertDiv); alertDiv.offsetHeight; setTimeout(() => alertDiv.classList.add('show'), 10); const closeButton = alertDiv.querySelector('.notification-alert-close'); closeButton.addEventListener('click', (e) => { e.stopPropagation(); closeNotificationAlert(alertDiv); }); alertDiv.addEventListener('click', () => { if (typeof openNotificationsModal === 'function') openNotificationsModal(); closeNotificationAlert(alertDiv); }); setTimeout(() => { if (document.body.contains(alertDiv)) closeNotificationAlert(alertDiv); }, 8000);
         }
         function closeNotificationAlert(alertElement) {
              if (!alertElement) return; alertElement.classList.remove('show'); alertElement.style.transform = 'translateX(120%)'; alertElement.style.opacity = '0'; setTimeout(() => { if (document.body.contains(alertElement)) alertElement.remove(); }, 500);
         }

         // --- Lógica do PIX Card (sem alterações) ---
          const pixCard = document.getElementById('pixCard');
            if (pixCard) {
                pixCard.addEventListener('click', function() {
                    const cardElement = this;
                    const pixKey = cardElement.dataset.pixKey;
                    if (!pixKey) { console.error("Chave Pix não encontrada."); showToast("Erro: Chave Pix não configurada.", true); return; }
                    if (!navigator.clipboard) { showToast("Cópia não suportada.", true); return; }
                    navigator.clipboard.writeText(pixKey).then(() => {
                        console.log('Chave Pix copiada:', pixKey); showToast('Chave Pix copiada!', false);
                        cardElement.classList.add('copied-success');
                        setTimeout(() => { cardElement.classList.remove('copied-success'); }, 700);
                    }).catch(err => { console.error('Erro ao copiar Chave Pix:', err); showToast('Falha ao copiar.', true); });
                });
            } else { console.warn("Elemento #pixCard não encontrado."); }


        // --- Delegação de Evento WhatsApp Card (sem alterações) ---
        const dashboardCardsContainer = document.querySelector('.dashboard-cards');
        if (dashboardCardsContainer) {
            dashboardCardsContainer.addEventListener('click', function(event) {
                const clickedCard = event.target.closest('#whatsappCard');
                if (clickedCard) {
                    if (!whatsappState.aprovado && !whatsappState.solicitado) {
                        openWhatsAppModal();
                    } else { console.log("WhatsApp já solicitado/aprovado."); }
                }
            });
        } else { console.error("Container .dashboard-cards não encontrado."); }

        // --- Inicialização Geral ---
        setupChamadasInteraction();
        updateWhatsAppUI(); // Chamada inicial
        fetchChamadasCountAndUpdateUI();
        fetchInitialNotificationData(); // <<< Isso agora também chama setupSSEConnection() no final
        addDynamicNotificationAlertStyles();

        // --- Lógica do Tutorial (verificar se existe) ---
        // if (tutorialModal && typeof initTutorial === 'function' && tutorialModal.classList.contains('show')) {
        //    console.log("DOMContentLoaded - Inicializando Tutorial...");
        //    initTutorial();
        // } else {
        //     console.log("DOMContentLoaded - Tutorial não está ativo ou função initTutorial não definida.");
        // }

        console.log("DOMContentLoaded - Fim da inicialização principal.");

    }); // --- Fim do DOMContentLoaded ---
    </script>
<script>
// ==================================================================
// === LÓGICA ISOLADA: MINHA MODELO - v5 COMPLETA (FOTOS E VÍDEOS) ===
// ==================================================================
console.log("--- EXECUTANDO SCRIPT ISOLADO MINHA MODELO (v5 - Completo com Vídeos) ---");

(function() {

    // --- Funções Utilitárias ---
    function escapeHtml(text) {
         if (typeof text !== 'string') return text;
         const div = document.createElement('div');
         div.textContent = text;
         return div.innerHTML;
    }

    function showToast(message, isError = false) {
         console.log(`TOAST (${isError ? 'Erro' : 'Sucesso'}): ${message}`);
         const existingToast = document.querySelector('.toast-notification');
         if (existingToast) existingToast.remove();
         const toast = document.createElement('div');
         toast.className = `toast-notification ${isError ? 'error' : ''}`;
         toast.innerHTML = `<i class="fas fa-${isError ? 'exclamation-circle' : 'check-circle'}"></i> <span>${escapeHtml(message)}</span>`;
         document.body.appendChild(toast);
         toast.offsetHeight; // Force reflow
         setTimeout(() => {
             toast.classList.add('show');
             setTimeout(() => {
                 toast.classList.remove('show');
                 setTimeout(() => toast.remove(), 300);
             }, 5000);
         }, 10);
     }
    // --- Fim Funções Utilitárias ---

    let isMinhaModeloInitialized = false;

    // <<< DECLARAÇÕES NO ESCOPO DA IIFE >>>
    let photoLightbox = null;
    let videoLightbox = null;
    let lightboxImage = null;
    let lightboxCounter = null;
    let photoLightboxDownloadBtn = null;
    let photoLightboxCloseBtn = null;
    let photoLightboxPrevBtn = null;
    let photoLightboxNextBtn = null;
    let lightboxVideoPlayer = null;
    let videoLightboxDownloadBtn = null;
    let videoLightboxCloseBtn = null; // Se os botões de fechar usarem a mesma classe, este pode ser o mesmo que photoLightboxCloseBtn após a query no videoLightbox.

    function setupMinhaModeloV5() {
        if (isMinhaModeloInitialized) {
            console.log("MINHA_MODELO_V5: Setup já inicializado, saindo.");
            return;
        }
        console.log("MINHA_MODELO_V5: >>> Iniciando setup <<<");

        const minhaModeloSection = document.getElementById('minha-modelo-section');
        if (!minhaModeloSection) {
            console.error("MINHA_MODELO_V5: ERRO - Seção #minha-modelo-section não encontrada!");
            return;
        }

        // Seletores principais (escopo local da função, pois só são usados aqui)
        const tabsContainer = minhaModeloSection.querySelector('.materials-tabs-v5');
        const displayArea = minhaModeloSection.querySelector('#materialsDisplayArea');
        const loadingPlaceholder = displayArea?.querySelector('.material-loading-placeholder');
        const materialsDataElement = minhaModeloSection.querySelector('#materialsData');

        // <<< ATRIBUIÇÕES ÀS VARIÁVEIS DO ESCOPO DA IIFE (SEM const/let AQUI) >>>
        photoLightbox = document.getElementById('photoLightboxV5');
        if (photoLightbox) {
            lightboxImage = photoLightbox.querySelector('#lightboxImageV5');
            lightboxCounter = photoLightbox.querySelector('#lightboxCounterV5');
            photoLightboxDownloadBtn = photoLightbox.querySelector('#lightboxDownloadBtnV5');
            photoLightboxCloseBtn = photoLightbox.querySelector('.photo-lightbox-v5-close');
            photoLightboxPrevBtn = photoLightbox.querySelector('.photo-lightbox-v5-nav.prev');
            photoLightboxNextBtn = photoLightbox.querySelector('.photo-lightbox-v5-nav.next');
        }

        videoLightbox = document.getElementById('videoLightboxV5');
        if (videoLightbox) {
            lightboxVideoPlayer = videoLightbox.querySelector('#lightboxVideoV5');
            videoLightboxDownloadBtn = videoLightbox.querySelector('#lightboxVideoDownloadBtnV5');
            // Reutiliza a variável videoLightboxCloseBtn para o botão de fechar do videoLightbox.
            // Se a classe '.photo-lightbox-v5-close' é usada em ambos, esta linha vai pegar o botão do videoLightbox.
            videoLightboxCloseBtn = videoLightbox.querySelector('.photo-lightbox-v5-close');
        }

        console.log("MINHA_MODELO_V5: Elementos encontrados:", {
            tabs: !!tabsContainer,
            display: !!displayArea,
            loader: !!loadingPlaceholder,
            dataEl: !!materialsDataElement,
            photoLightbox: !!photoLightbox,
            videoLightbox: !!videoLightbox,
            // Log para elementos internos dos lightboxes (opcional, mas útil para debug)
            lightboxImage: !!lightboxImage,
            lightboxVideoPlayer: !!lightboxVideoPlayer
        });

        // Verifica se os elementos base da seção foram encontrados
        if (!tabsContainer || !displayArea || !materialsDataElement) {
             console.error("MINHA_MODELO_V5: ERRO CRÍTICO - Elementos base da seção (tabs, displayArea, materialsDataElement) não encontrados. Verifique o HTML.");
             if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
             return; // Aborta se os elementos base não existirem
        }
        // A verificação de elementos internos dos lightboxes pode ser feita nas funções openPhotoLightbox/openVideoLightbox


        let materials = { photos: [], videos: [], audios: [], scripts: [] };
        let currentPhotoLightboxList = [];
        let currentPhotoLightboxIndex = 0;

        // 1. Parse Material Data
        console.log("MINHA_MODELO_V5: Tentando parsear JSON de materiais...");
        try {
            const rawJson = materialsDataElement.textContent || '';
            if (!rawJson.trim() || rawJson.trim() === '{}' || rawJson.trim() === '[]' || rawJson.trim().toLowerCase() === 'null') {
                 console.warn("MINHA_MODELO_V5: JSON de materiais vazio, ausente ou inválido. Abortando setup precoce.");
                 if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
                 isMinhaModeloInitialized = false; // Permite que tente novamente após reload
                 return;
            }
            const parsedData = JSON.parse(rawJson);
            materials.photos = Array.isArray(parsedData.photos) ? parsedData.photos.filter(item => item?.url) : [];
            materials.videos = Array.isArray(parsedData.videos) ? parsedData.videos.filter(item => item?.url && item.name) : []; // Adicionado item.name para vídeos
            materials.audios = Array.isArray(parsedData.audios) ? parsedData.audios.filter(item => item?.url && item.name) : [];
            materials.scripts = Array.isArray(parsedData.scripts) ? parsedData.scripts.filter(item => item?.url && item.name) : [];

            console.log(`MINHA_MODELO_V5: Materiais parseados - Fotos: ${materials.photos.length}, Vídeos: ${materials.videos.length}, Áudios: ${materials.audios.length}, Scripts: ${materials.scripts.length}`);
        } catch (e) {
            console.error("MINHA_MODELO_V5: ERRO CRÍTICO AO PARSEAR JSON:", e, "Conteúdo do JSON:", materialsDataElement.textContent);
            if (displayArea) displayArea.innerHTML = '<p class="material-empty-message" style="color: var(--danger);">Erro ao carregar dados dos materiais. A página será recarregada.</p>';
            if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
            setTimeout(() => window.location.reload(), 2500);
            return;
        }

        // 2. Lógica de Troca de Abas
        console.log("MINHA_MODELO_V5: Adicionando listener de clique nas Abas.");
        tabsContainer.removeEventListener('click', handleTabClick); // Evita múltiplos listeners
        tabsContainer.addEventListener('click', handleTabClick);

        function handleTabClick(e) {
            const button = e.target.closest('.tab-btn-v5');
            if (!button || button.classList.contains('active')) return;
            console.log("MINHA_MODELO_V5: Troca de Aba ->", button.dataset.tab);
            tabsContainer.querySelectorAll('.tab-btn-v5').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            renderMaterials(button.dataset.tab);
        }

        // 3. Função para Renderizar Materiais
        function renderMaterials(type) {
            console.log(`MINHA_MODELO_V5: ---- Renderizando Materiais (Tipo: ${type}) ----`);
            if (!displayArea) {
                console.error("MINHA_MODELO_V5: displayArea nulo em renderMaterials");
                return;
            }
            if (loadingPlaceholder) loadingPlaceholder.classList.remove('hidden');

            // Limpa a área de exibição, exceto o placeholder
            // Garante que displayArea.children exista (se displayArea for válido)
            if (displayArea.children) {
                Array.from(displayArea.children).forEach(child => {
                    if (child !== loadingPlaceholder) displayArea.removeChild(child);
                });
            }


            let contentHtml = '';
            const dataList = materials[type] || [];
            console.log(`MINHA_MODELO_V5: Dados para renderizar ${type}:`, dataList.length > 0 ? dataList : 'Nenhum');

            if (dataList.length === 0) {
                contentHtml = `<p class="material-empty-message">Nenhum material "${type}" encontrado.</p>`;
            } else {
                if (type === 'photos') {
                    contentHtml = '<div class="material-photo-grid">';
                    dataList.forEach((item, index) => {
                        const safeUrl = escapeHtml(item.url);
                        const photoName = escapeHtml(item.name || `foto_${index + 1}`);
                        contentHtml += `<div class="photo-thumbnail" data-index="${index}" title="Ver ${photoName}">
                                            <img src="${safeUrl}" alt="${photoName}" loading="lazy" onerror="this.parentElement.classList.add('img-error'); this.style.display='none'; console.error('Erro ao carregar imagem: ${safeUrl}');">
                                        </div>`;
                    });
                    contentHtml += '</div>';
                } else if (type === 'videos') {
                    contentHtml = '<div class="material-video-grid">';
                    dataList.forEach((item, index) => {
                        const videoUrl = escapeHtml(item.url);
                        const videoName = escapeHtml(item.name || `video_${index + 1}`);
                        const thumbnailUrl = escapeHtml(item.thumbnail_url || '');

                        contentHtml += `<div class="video-thumbnail" data-video-url="${videoUrl}" data-video-name="${videoName}" title="Ver vídeo: ${videoName}">`;
                        if (thumbnailUrl) {
                            contentHtml += `<img src="${thumbnailUrl}" class="video-thumbnail-img" alt="Preview de ${videoName}" loading="lazy" onerror="this.parentElement.classList.add('img-error'); this.style.display='none'; console.error('Erro ao carregar thumbnail de vídeo: ${thumbnailUrl}');">`;
                        } else {
                            contentHtml += `<i class="video-thumbnail-icon fas fa-film"></i>`;
                        }
                        contentHtml += `<div class="video-play-overlay"><i class="fas fa-play"></i></div>`;
                        // O nome do vídeo é intencionalmente omitido do display aqui, mas usado no title e data-video-name
                        contentHtml += `</div>`;
                    });
                    contentHtml += '</div>';
                } else { // audios, scripts
                    contentHtml = '<div class="material-list">';
                    dataList.forEach(item => {
                        const iconClass = type === 'audios' ? 'fas fa-file-audio' : (type === 'scripts' ? 'fas fa-comment-dots' : 'fas fa-file');
                        const fileUrl = escapeHtml(item.url);
                        const originalFileName = item.name || fileUrl.substring(fileUrl.lastIndexOf('/') + 1);
                        // Tenta obter nome sem extensão para display, mas mantém original para download
                        let displayName = originalFileName;
                        const lastDotIndex = originalFileName.lastIndexOf('.');
                        if (lastDotIndex > 0 && lastDotIndex < originalFileName.length -1) { // Garante que não é arquivo oculto ou sem nome
                            displayName = originalFileName.substring(0, lastDotIndex);
                        }
                        displayName = escapeHtml(displayName);
                        const downloadFileName = escapeHtml(originalFileName);

                        contentHtml += `
                            <div class="material-list-item">
                                <div class="material-item-icon ${type}"><i class="${iconClass}"></i></div>
                                <div class="material-item-info">
                                    <p class="material-item-name" title="${downloadFileName}">${displayName}</p>
                                    ${type === 'audios' ? `<audio controls class="material-audio-player" src="${fileUrl}" preload="metadata"></audio>` : ''}
                                </div>
                                <div class="material-item-actions">
                                    <a href="${fileUrl}" download="${downloadFileName}" class="btn-material-action" title="Baixar ${downloadFileName}">
                                        <i class="fas fa-download"></i>
                                        <span class="btn-text-desktop desktop-only"> Baixar</span>
                                        <span class="btn-text-mobile mobile-only"> DL</span>
                                    </a>
                                </div>
                            </div>`;
                    });
                    contentHtml += '</div>';
                }
            }

            if (contentHtml) {
                console.log(`MINHA_MODELO_V5: HTML final gerado para ${type}. Inserindo...`);
                if (loadingPlaceholder) {
                    // Remove conteúdo antigo antes de inserir novo, exceto o loader
                    if (displayArea.children) {
                         Array.from(displayArea.children).forEach(child => {
                             if (child !== loadingPlaceholder) displayArea.removeChild(child);
                         });
                    }
                    loadingPlaceholder.insertAdjacentHTML('beforebegin', contentHtml);
                } else {
                    displayArea.innerHTML = contentHtml;
                }
            } else {
                 console.error(`MINHA_MODELO_V5: ERRO - contentHtml está vazio para o tipo ${type}.`);
                 if (!loadingPlaceholder && displayArea) {
                     displayArea.innerHTML = '<p class="material-empty-message">Erro ao gerar conteúdo.</p>';
                 }
            }

            if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
            console.log(`MINHA_MODELO_V5: Renderização para ${type} concluída.`);
        }

        // 4. Listener de Delegação ÚNICO
        console.log("MINHA_MODELO_V5: Adicionando listener GERAL de clique na displayArea.");
        if (displayArea) { // Adiciona verificação se displayArea existe
            displayArea.removeEventListener('click', handleDisplayAreaClick);
            displayArea.addEventListener('click', handleDisplayAreaClick);
        }


        function handleDisplayAreaClick(e) {
            console.log("MINHA_MODELO_V5: Clique detectado na displayArea. Target:", e.target);

            const photoThumb = e.target.closest('.photo-thumbnail');
            if (photoThumb) {
                e.preventDefault();
                const indexStr = photoThumb.dataset.index;
                const index = parseInt(indexStr || '-1', 10);
                if (!isNaN(index) && index >= 0 && materials.photos && materials.photos.length > index) {
                    console.log("MINHA_MODELO_V5: Abrindo lightbox FOTO (delegado), índice:", index);
                    openPhotoLightbox(materials.photos, index);
                } else {
                    console.warn("MINHA_MODELO_V5: Índice/dados inválidos para lightbox FOTO.", {indexStr, index, photosLength: materials.photos?.length});
                    showToast("Erro ao abrir foto.", true);
                }
                return;
            }

            const videoThumb = e.target.closest('.video-thumbnail');
            if (videoThumb) {
                e.preventDefault();
                const videoUrl = videoThumb.dataset.videoUrl;
                const videoName = videoThumb.dataset.videoName || 'video';
                if (videoUrl) {
                    console.log("MINHA_MODELO_V5: Abrindo lightbox VÍDEO (delegado), URL:", videoUrl);
                    openVideoLightbox(videoUrl, videoName);
                } else {
                    console.warn("MINHA_MODELO_V5: URL do vídeo não encontrada no data attribute.");
                    showToast("Erro ao abrir vídeo.", true);
                }
                return;
            }

            // O botão de copiar link foi removido da renderização, mas a lógica pode ficar para referência
            // const copyButton = e.target.closest('.btn-copy-link');
            // if (copyButton) { /* ... */ }

            console.log("MINHA_MODELO_V5: Clique na displayArea não correspondeu a um elemento esperado (foto ou vídeo).");
        }

        // 4.1. Função para Copiar Link (mantida para referência, embora o botão tenha sido removido)
        // function handleCopyLinkClick(buttonElement) { /* ... seu código aqui ... */ }


        // 5. Lógica Completa do Photo Lightbox
        function openPhotoLightbox(photoList, index) {
            if (!photoLightbox || !lightboxImage || !lightboxCounter || !photoLightboxDownloadBtn) {
                 console.error("MINHA_MODELO_V5: Elementos essenciais do Photo Lightbox não encontrados para ABRIR.");
                 return;
            }
            if (!Array.isArray(photoList) || photoList.length === 0) {
                 console.warn("MINHA_MODELO_V5: Lista de fotos vazia ou inválida para Photo Lightbox.");
                 return;
            }

            currentPhotoLightboxList = photoList;
            currentPhotoLightboxIndex = Math.max(0, Math.min(index, currentPhotoLightboxList.length - 1));
            console.log(`MINHA_MODELO_V5: Abrindo Lightbox FOTO - Índice ${currentPhotoLightboxIndex}/${currentPhotoLightboxList.length - 1}`);
            updatePhotoLightboxContent();
            updatePhotoLightboxNav();
            photoLightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            photoLightbox.offsetHeight; // Force reflow
            photoLightbox.style.opacity = '1';
            photoLightbox.classList.add('show');
        }

        function closePhotoLightbox() {
            if (!photoLightbox) {
                 console.error("MINHA_MODELO_V5: Photo Lightbox não encontrado para FECHAR.");
                 return;
            }
            console.log("MINHA_MODELO_V5: Fechando Lightbox FOTO");
            photoLightbox.style.opacity = '0';
            photoLightbox.classList.remove('show');
            document.body.style.overflow = '';
            setTimeout(() => {
                photoLightbox.style.display = 'none';
                if (lightboxImage) lightboxImage.src = ''; // Limpa a imagem para liberar memória
            }, 300); // Duração da transição CSS
        }

        function updatePhotoLightboxContent() {
            if (!lightboxImage || !lightboxCounter || !photoLightboxDownloadBtn || currentPhotoLightboxList.length === 0) {
                 console.error("MINHA_MODELO_V5: Elementos do Photo Lightbox ou lista de fotos ausentes para ATUALIZAR conteúdo.");
                 return;
            }
            const currentPhoto = currentPhotoLightboxList[currentPhotoLightboxIndex];
            if (!currentPhoto || !currentPhoto.url) {
                console.error("MINHA_MODELO_V5: Dados da foto atual inválidos (lightbox).");
                if (lightboxImage) lightboxImage.src = ''; // Limpa se houver erro
                return;
            }

            const imageUrl = escapeHtml(currentPhoto.url);
            const imageName = escapeHtml(currentPhoto.name || `foto_${currentPhotoLightboxIndex + 1}`);
            // Tenta pegar a extensão da URL, senão usa 'jpg' como padrão
            const urlParts = imageUrl.split('.');
            const imageExtension = urlParts.length > 1 ? urlParts.pop().toLowerCase() : 'jpg';

            lightboxImage.src = imageUrl;
            lightboxImage.alt = `Foto ${imageName} (${currentPhotoLightboxIndex + 1} de ${currentPhotoLightboxList.length})`;
            lightboxCounter.textContent = `${currentPhotoLightboxIndex + 1} / ${currentPhotoLightboxList.length}`;
            photoLightboxDownloadBtn.href = imageUrl;
            photoLightboxDownloadBtn.download = `${imageName}.${imageExtension}`; // Nome do arquivo para download
        }

        function updatePhotoLightboxNav() {
            if (!photoLightboxPrevBtn || !photoLightboxNextBtn) {
                console.warn("MINHA_MODELO_V5: Botões de navegação do Photo Lightbox não encontrados.");
                return;
            }
            if (currentPhotoLightboxList.length <= 1) {
                photoLightboxPrevBtn.classList.add('hidden');
                photoLightboxNextBtn.classList.add('hidden');
                return;
            }
            photoLightboxPrevBtn.classList.toggle('hidden', currentPhotoLightboxIndex === 0);
            photoLightboxNextBtn.classList.toggle('hidden', currentPhotoLightboxIndex >= currentPhotoLightboxList.length - 1);
        }

        function showPrevPhoto() {
            if (currentPhotoLightboxIndex > 0) {
                currentPhotoLightboxIndex--;
                updatePhotoLightboxContent();
                updatePhotoLightboxNav();
            }
        }

        function showNextPhoto() {
            if (currentPhotoLightboxIndex < currentPhotoLightboxList.length - 1) {
                currentPhotoLightboxIndex++;
                updatePhotoLightboxContent();
                updatePhotoLightboxNav();
            }
        }

        // 5.1 Lógica do Video Lightbox
        function openVideoLightbox(videoUrl, videoName = 'video') {
            if (!videoLightbox || !lightboxVideoPlayer || !videoLightboxDownloadBtn || !videoUrl) {
                 console.error("MINHA_MODELO_V5: Elementos essenciais do Video Lightbox ou URL não encontrados para ABRIR.");
                 return;
            }
            console.log(`MINHA_MODELO_V5: Abrindo Lightbox VÍDEO - URL: ${videoUrl}`);

            lightboxVideoPlayer.src = videoUrl; // Define o src principal

            // Tenta definir o tipo da source tag para melhor compatibilidade
            const sourceElement = lightboxVideoPlayer.querySelector('source');
            const extension = videoUrl.split('.').pop()?.toLowerCase();
            if (extension) {
                const videoTypeMap = { mp4: 'video/mp4', webm: 'video/webm', mov: 'video/quicktime', ogv: 'video/ogg' };
                const videoType = videoTypeMap[extension];
                if (videoType) {
                    if (sourceElement) {
                        sourceElement.src = videoUrl;
                        sourceElement.type = videoType;
                    } else {
                        // Se não houver tag <source>, cria uma (embora o src direto no <video> deva funcionar)
                        const newSource = document.createElement('source');
                        newSource.src = videoUrl;
                        newSource.type = videoType;
                        lightboxVideoPlayer.appendChild(newSource);
                    }
                } else if (sourceElement) { // Se extensão desconhecida e source existe, limpa o tipo
                    sourceElement.removeAttribute('type');
                    sourceElement.src = videoUrl;
                }
            } else if (sourceElement) { // Se não há extensão e source existe
                sourceElement.removeAttribute('type');
                sourceElement.src = videoUrl;
            }

            lightboxVideoPlayer.load(); // Recarrega o player com o novo src/source

            const downloadFileName = escapeHtml(videoName) + '.' + (extension || 'mp4');
            videoLightboxDownloadBtn.href = videoUrl;
            videoLightboxDownloadBtn.download = downloadFileName;

            videoLightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            videoLightbox.offsetHeight; // Force reflow
            videoLightbox.style.opacity = '1';
            videoLightbox.classList.add('show');
        }

        function closeVideoLightbox() {
            if (!videoLightbox || !lightboxVideoPlayer) {
                console.error("MINHA_MODELO_V5: Video Lightbox ou Player não encontrado para FECHAR.");
                return;
            }
            console.log("MINHA_MODELO_V5: Fechando Lightbox VÍDEO");
            videoLightbox.style.opacity = '0';
            videoLightbox.classList.remove('show');
            document.body.style.overflow = '';

            lightboxVideoPlayer.pause();
            lightboxVideoPlayer.removeAttribute('src'); // Remove src para parar download/streaming
            const sourceElement = lightboxVideoPlayer.querySelector('source');
            if (sourceElement) sourceElement.removeAttribute('src');
            lightboxVideoPlayer.load(); // Importante para aplicar a limpeza

            setTimeout(() => {
                videoLightbox.style.display = 'none';
            }, 300); // Duração da transição CSS
        }

        // 6. Adiciona Listeners dos Lightboxes (uma vez por lightbox)
        if (photoLightbox && !photoLightbox.dataset.listenersAddedV5) {
            console.log("MINHA_MODELO_V5: Adicionando Listeners do Lightbox de FOTOS.");
            if (photoLightboxCloseBtn) photoLightboxCloseBtn.addEventListener('click', closePhotoLightbox);
            if (photoLightboxPrevBtn) photoLightboxPrevBtn.addEventListener('click', showPrevPhoto);
            if (photoLightboxNextBtn) photoLightboxNextBtn.addEventListener('click', showNextPhoto);
            photoLightbox.addEventListener('click', (e) => {
                if (e.target === photoLightbox) closePhotoLightbox();
            });
            photoLightbox.dataset.listenersAddedV5 = 'true';
        } else if (!photoLightbox) {
            console.warn("MINHA_MODELO_V5: Photo Lightbox não encontrado, listeners não adicionados.");
        }


        if (videoLightbox && !videoLightbox.dataset.listenersAddedV5) {
            console.log("MINHA_MODELO_V5: Adicionando Listeners do Lightbox de VÍDEOS.");
            if (videoLightboxCloseBtn) videoLightboxCloseBtn.addEventListener('click', closeVideoLightbox);
            videoLightbox.addEventListener('click', (e) => {
                if (e.target === videoLightbox) closeVideoLightbox();
            });
            videoLightbox.dataset.listenersAddedV5 = 'true';
        } else if (!videoLightbox) {
            console.warn("MINHA_MODELO_V5: Video Lightbox não encontrado, listeners não adicionados.");
        }

        // 7. Gatilho para Renderização Inicial
        const initialActiveTabBtn = tabsContainer?.querySelector('.tab-btn-v5.active'); // Adiciona ? para segurança
        const initialTabType = initialActiveTabBtn ? initialActiveTabBtn.dataset.tab : 'photos';
        console.log("MINHA_MODELO_V5: Agendando renderização inicial para aba:", initialTabType);
        setTimeout(() => {
            console.log("MINHA_MODELO_V5: Executando renderMaterials inicial via setTimeout");
            if (displayArea && materialsDataElement) { // Verifica se os elementos necessários para renderizar existem
                 renderMaterials(initialTabType);
            } else {
                console.warn("MINHA_MODELO_V5: Não foi possível executar renderMaterials inicial, displayArea ou materialsDataElement ausentes.");
            }
        }, 150);

        isMinhaModeloInitialized = true;
        console.log("MINHA_MODELO_V5: >>> setupMinhaModeloV5 CONCLUÍDO <<<");

    } // --- Fim da função setupMinhaModeloV5 ---


    // --- Listener Global de Teclado ---
    if (!document.body.dataset.globalKeyListenerAddedMinhaModeloV5) {
         document.addEventListener('keydown', (e) => {
             // photoLightbox e videoLightbox são acessados do escopo da IIFE
             const photoLbVisible = photoLightbox && window.getComputedStyle(photoLightbox).display !== 'none';
             const videoLbVisible = videoLightbox && window.getComputedStyle(videoLightbox).display !== 'none';

             if (e.key === 'Escape') {
                if (photoLbVisible) {
                     console.log("MINHA_MODELO_V5: Tecla ESC - Fechando lightbox FOTO.");
                     closePhotoLightbox();
                } else if (videoLbVisible) {
                     console.log("MINHA_MODELO_V5: Tecla ESC - Fechando lightbox VÍDEO.");
                     closeVideoLightbox();
                }
             } else if (photoLbVisible) { // Setas só funcionam no lightbox de fotos
                 // Adiciona verificação se os botões de navegação existem e estão visíveis
                 if (e.key === 'ArrowLeft' && photoLightboxPrevBtn && !photoLightboxPrevBtn.classList.contains('hidden')) {
                     console.log("MINHA_MODELO_V5: Tecla <- pressionada (Foto).");
                     showPrevPhoto();
                 } else if (e.key === 'ArrowRight' && photoLightboxNextBtn && !photoLightboxNextBtn.classList.contains('hidden')) {
                     console.log("MINHA_MODELO_V5: Tecla -> pressionada (Foto).");
                     showNextPhoto();
                 }
             }
         });
         document.body.dataset.globalKeyListenerAddedMinhaModeloV5 = 'true';
         console.log("MINHA_MODELO_V5: Listener Global de Teclado (ESC, Setas) adicionado/atualizado.");
    }

    // --- Gatilho de Inicialização Principal ---
    const minhaModeloSectionObserver = document.getElementById('minha-modelo-section');
    if (minhaModeloSectionObserver) {
        const style = window.getComputedStyle(minhaModeloSectionObserver);
         if (style.display !== 'none' && !isMinhaModeloInitialized) {
             console.log("MINHA_MODELO_V5: Seção visível na carga inicial, chamando setup...");
             setupMinhaModeloV5();
        } else if (style.display === 'none') {
             console.log("MINHA_MODELO_V5: Seção não visível na carga inicial.");
        } else if (isMinhaModeloInitialized) {
             console.log("MINHA_MODELO_V5: Seção visível mas setup já inicializado.");
        }
    } else {
         console.log("MINHA_MODELO_V5: Seção #minha-modelo-section não encontrada na carga inicial (para observer).");
    }

    const modeloMenuItem = document.querySelector('.menu-item[data-section="minha-modelo"]');
    if (modeloMenuItem) {
         modeloMenuItem.addEventListener('click', () => {
             console.log("MINHA_MODELO_V5: Menu 'minha-modelo' clicado, tentando setup (se não inicializado)...");
             // Força a re-inicialização ou uma verificação mais robusta aqui se necessário.
             // Por ora, apenas chama setupMinhaModeloV5 que já tem a guarda isMinhaModeloInitialized.
             setupMinhaModeloV5();
         });
     } else {
          console.warn("MINHA_MODELO_V5: Item de menu [data-section=\"minha-modelo\"] não encontrado.");
     }

})(); // Fim da IIFE

console.log("--- FIM SCRIPT ISOLADO MINHA MODELO ---");
</script>
</body>
</html>