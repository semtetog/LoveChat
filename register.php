<?php
session_start();
require 'includes/db.php'; // Conexão DB


// --- Logs de Erro ---
$log_path_register = __DIR__ . '/logs/register_errors.log';
if (!file_exists(dirname($log_path_register))) {
    @mkdir(dirname($log_path_register), 0755, true);
}
ini_set('error_log', $log_path_register);
ini_set('display_errors', 0); // Não mostrar erros em produção
error_reporting(E_ALL);

// --- Fuso Horário ---
date_default_timezone_set('America/Sao_Paulo'); // <-- Ajuste se necessário

$erro = ''; // Inicializa variável de erro

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- Coleta e Limpeza dos Dados ---
    $nome_completo = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nickname = htmlspecialchars(trim($_POST['nickname'] ?? ''), ENT_QUOTES, 'UTF-8');
    // Se nickname vazio, usa o primeiro nome como fallback
    if (empty($nickname) && !empty($nome_completo)) {
        $nickname = explode(' ', $nome_completo)[0];
    } elseif(empty($nickname)) {
        $nickname = 'Usuário'; // Fallback genérico se ambos vazios
    }

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    // Limpa telefone mantendo apenas dígitos
    $telefone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $senha = $_POST['password'] ?? '';
    $confirmacao = $_POST['password_confirm'] ?? '';
    $chave_pix = htmlspecialchars(trim($_POST['chave_pix'] ?? ''), ENT_QUOTES, 'UTF-8');
    $tipo_chave_pix = htmlspecialchars(trim($_POST['tipo_chave_pix'] ?? ''), ENT_QUOTES, 'UTF-8');
    $termos = isset($_POST['terms']); // Verifica se a checkbox foi marcada

    // --- Validações ---
    if (empty($nome_completo)) {
        $erro = "Por favor, insira seu nome completo!";
    } elseif (strlen($senha) < 8) {
        $erro = "A senha deve ter pelo menos 8 caracteres!";
    } elseif ($senha !== $confirmacao) {
        $erro = "As senhas não coincidem!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "E-mail inválido!";
    } elseif (strlen($telefone) < 10 || strlen($telefone) > 11) { // Permite 10 ou 11 dígitos
        $erro = "Por favor, insira um número de celular válido com DDD (10 ou 11 dígitos)!";
    } elseif (!$termos) { // Verifica se a variável $termos é true
        $erro = "Você deve aceitar os termos para continuar!";
    } elseif (!empty($chave_pix) && empty($tipo_chave_pix)) {
        $erro = "Selecione o tipo da chave PIX se você informou a chave!";
    } else {
        // Verifica se o e-mail já existe APENAS se não houver outros erros
        if (isset($pdo) && emailExiste($email, $pdo)) { // Passa $pdo para a função
            $erro = "Este e-mail já está cadastrado!";
        } elseif (!isset($pdo)) {
             error_log("CRITICAL REGISTER ERROR: PDO connection not available in register.php");
             $erro = "Erro interno do servidor (DB). Tente novamente mais tarde.";
        } else {
            // --- Tenta Registrar o Usuário ---
            try {
                // Chama a função registrarUsuario (deve retornar o ID do usuário ou false)
                // Certifique-se que a função aceita $pdo
                if ($userId = registrarUsuario($nome_completo, $nickname, $email, $telefone, $senha, $chave_pix, $tipo_chave_pix, $pdo)) {

                    // --- REGISTRO BEM SUCEDIDO ---

                    // 1. Configura a sessão do novo usuário
                    $_SESSION['user_id'] = $userId;
                    // As outras variáveis de sessão podem ser configuradas no login ou aqui se necessário
                    // $_SESSION['user_email'] = $email; // Evitar guardar email na sessão se não for estritamente necessário
                    $_SESSION['user_nome'] = $nickname; // Guarda o nickname
                    // $_SESSION['user_nome_completo'] = $nome_completo; // Opcional
                    // $_SESSION['user_avatar'] = DEFAULT_AVATAR; // Novo usuário começa com avatar padrão
                    // $_SESSION['is_admin'] = false; // Novo usuário nunca é admin por padrão
                    // $_SESSION['avatar_updated'] = time();
                    // $_SESSION['user_phone'] = $telefone;

                    // ***** INÍCIO: LOG DO EVENTO DE REGISTRO PARA O FEED DO ADMIN *****
                    try {
                        $eventType = 'user_registered';
                        // Dados do evento a serem salvos
                        $eventDataArray = [
                            'email' => $email,
                            'nome_completo' => $nome_completo, // Opcional
                            'telefone' => $telefone,         // Opcional
                            // 'registration_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido' // Opcional
                        ];
                        // Codifica em JSON
                        $eventDataJson = json_encode($eventDataArray, JSON_UNESCAPED_UNICODE);
                        if ($eventDataJson === false) {
                             error_log("Erro JSON encode [user_registered] para user $userId: " . json_last_error_msg());
                             $eventDataJson = json_encode(['error' => 'JSON encode failed', 'email' => $email]);
                        }

                        // Insere no feed do admin
                        $stmt_feed = $pdo->prepare("
                            INSERT INTO admin_events_feed
                            (event_type, user_id, event_data, created_at)
                            VALUES (:event_type, :user_id, :event_data, NOW())
                        ");
                        $stmt_feed->execute([
                            ':event_type' => $eventType,
                            ':user_id' => $userId, // ID do usuário que acabou de registrar
                            ':event_data' => $eventDataJson
                        ]);
                         error_log("Admin feed log created for user_registered, user ID: " . $userId);

                    } catch (PDOException $e) {
                        // Loga erro do feed, mas não impede o fluxo
                        error_log("ADMIN FEED LOG Error (PDO - user_registered) for user $userId: " . $e->getMessage());
                    } catch (Throwable $t) {
                        // Loga erro geral do feed, mas não impede o fluxo
                        error_log("ADMIN FEED LOG General Error (user_registered) for user $userId: " . $t->getMessage());
                    }
                    // ***** FIM: LOG DO EVENTO DE REGISTRO *****

                 // Redireciona para uma página de "Aguardando Aprovação"
$_SESSION['registration_pending_message'] = "Seu cadastro foi realizado com sucesso! Sua conta está pendente de ativação. Para ativá-la, realize o pagamento da taxa de adesão (se aplicável) e envie o comprovante para nosso WhatsApp. Assim que confirmado, sua conta será liberada.";
header("Location: aguardando-aprovacao.php");
exit();

                } else {
                    // Se registrarUsuario retornou false
                    $erro = "Erro ao registrar. Por favor, tente novamente.";
                    error_log("registrarUsuario function failed for email: " . $email);
                }
            } catch (PDOException $e) {
                // Erro de banco de dados durante o registro
                $erro = "Erro no sistema [DB]. Por favor, tente mais tarde.";
                // Log detalhado do erro PDO
                error_log('Erro PDO no registro (register.php): ' . $e->getMessage() . ' | SQLSTATE: ' . $e->getCode());
            } catch (Exception $e) {
                // Outros erros gerais
                 $erro = "Erro inesperado no sistema. Por favor, tente mais tarde.";
                 error_log('Erro Geral no registro (register.php): ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registro - Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            color: #fff;
            background-color: #2a2a2a;
            overflow-x: hidden;
            position: relative;
        }
        
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(255, 0, 127, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 90% 30%, rgba(252, 92, 172, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 20% 70%, rgba(255, 105, 180, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 80% 80%, rgba(255, 20, 147, 0.15) 0%, transparent 20%);
            z-index: -1;
            pointer-events: none;
        }
        
        .register-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 40px;
            width: 100%;
        }
        
        .register-logo {
            width: 140px;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 10px rgba(255,0,127,0.5));
        }
        
        .register-title {
            font-weight: 700;
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            line-height: 1.3;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.7);
        }
        
        .register-subtitle {
            font-size: 1.1rem;
            color: #e0e0e0;
            max-width: 600px;
            margin: 0 auto;
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
        
        .register-form {
            background: rgba(40, 40, 40, 0.8);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 0, 127, 0.3);
            backdrop-filter: blur(5px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #ff66b3;
        }
        
        .form-input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,0,127,0.3);
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: all 0.3s;
            caret-color: #ffffff;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #fc5cac;
            box-shadow: 0 0 10px rgba(255,0,127,0.3);
        }
        
        .form-error {
            color: #ff5555;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }
        
        .terms-group {
            margin: 30px 0;
            padding: 20px 0;
            border-top: 1px solid rgba(255,0,127,0.2);
            border-bottom: 1px solid rgba(255,0,127,0.2);
        }
        
        .terms-check {
            display: flex;
            align-items: center;
        }
        
        .terms-check input {
            margin-right: 10px;
        }
        
        .terms-text {
            color: #e0e0e0;
            font-size: 0.9rem;
        }
        
        .terms-link {
            color: #fc5cac;
            text-decoration: none;
            transition: color 0.3s;
            cursor: pointer;
        }
        
        .terms-link:hover {
            color: #ff007f;
            text-decoration: underline;
        }
        
        .register-button {
            display: block;
            width: 100%;
            background: linear-gradient(to bottom, #00cc00, #009900);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 16px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0,255,0,0.4);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }
        
        .register-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,255,0,0.6);
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(0, 255, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0); }
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #e0e0e0;
            font-size: 0.95rem;
        }
        
        .login-link a {
            color: #00ff00;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .login-link a:hover {
            color: #96ff00;
            text-decoration: underline;
        }
        
        .error-message {
            background: rgba(255, 85, 85, 0.2);
            color: #ff5555;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 85, 85, 0.5);
            display: <?php echo !empty($erro) ? 'block' : 'none'; ?>;
        }
        
        .success-message {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.5s ease;
        }

        .success-content {
            background: rgba(40, 40, 40, 0.95);
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(0, 255, 0, 0.3);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.2);
        }

        .success-icon {
            color: #00ff00;
            font-size: 50px;
            margin-bottom: 20px;
        }

        .success-message h3 {
            color: #00ff00;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .success-message p {
            color: #e0e0e0;
            margin-bottom: 25px;
        }

        .success-button {
            display: inline-block;
            background: linear-gradient(to bottom, #00cc00, #009900);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .success-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.4);
        }

        .success-redirect {
            margin-top: 20px;
            font-size: 0.9rem;
            color: #aaa;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .form-input:-webkit-autofill,
        .form-input:-webkit-autofill:hover, 
        .form-input:-webkit-autofill:focus {
            -webkit-text-fill-color: white !important;
            -webkit-box-shadow: 0 0 0px 1000px rgba(30, 30, 30, 0.7) inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
/* ============= MODAIS ULTRA CLEAN ============= */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal-container {
    background: #2a2a2a;
    border-radius: 8px;
    width: 90%;
    max-width: 700px;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border: 1px solid #3a3a3a;
}

.modal-header {
    padding: 20px;
    background: #333;
    border-bottom: 1px solid #3a3a3a;
    position: relative;
}

.modal-title {
    color: #e0e0e0;
    font-size: 1.5rem;
    font-weight: 500;
    margin: 0;
}

.close-modal {
    position: absolute;
    top: 18px;
    right: 20px;
    background: none;
    border: none;
    color: #999;
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #ff99cc; /* Rosa suave */
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: 75vh; /* Ajustado para compensar a remoção do footer */
    color: #d0d0d0;
    line-height: 1.6;
    font-size: 0.95rem;
}

.modal-section {
    margin-bottom: 25px;
}

.modal-section h3 {
    color: #e0e0e0; /* Mudado para cinza claro */
    font-size: 1.2rem;
    margin-bottom: 12px;
    font-weight: 500;
}

.modal-list {
    list-style: none;
    padding-left: 20px;
    margin: 15px 0;
}

.modal-list li {
    position: relative;
    margin-bottom: 8px;
}

.modal-list li:before {
    content: "•";
    color: #e0e0e0; /* Mudado para cinza claro */
    position: absolute;
    left: -15px;
}

/* Barra de rolagem discreta */
.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: #2a2a2a;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #4a4a4a;
}
        
        .modal-section {
            margin-bottom: 30px;
        }
        
        .modal-section h3 {
            color: #ff66b3;
            font-size: 1.4rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 0, 127, 0.3);
            position: relative;
        }
        
        .modal-section h3::after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100px;
            height: 2px;
            background: linear-gradient(to right, #ff007f, transparent);
        }
        
        .modal-section p {
            color: #e0e0e0;
            line-height: 1.7;
            margin-bottom: 15px;
        }
        
        .modal-list {
            list-style: none;
            margin: 20px 0;
            padding-left: 0;
        }
        
        .modal-list li {
            position: relative;
            padding-left: 30px;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .modal-list li::before {
            content: "•";
            color: #ff66b3;
            position: absolute;
            left: 0;
            top: 0;
            font-size: 1.8rem;
            line-height: 1;
        }
        
        .highlight-term {
            color: #ff66b3;
            font-weight: 600;
            background: rgba(255, 0, 127, 0.15);
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .modal-footer {
            padding: 20px 30px;
            background: rgba(255, 0, 127, 0.05);
            border-top: 1px solid rgba(255, 0, 127, 0.2);
            text-align: center;
        }
        
        .modal-button {
            background: linear-gradient(to bottom, #ff66b3, #ff007f);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(255, 0, 127, 0.4);
        }
        
        .modal-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 0, 127, 0.6);
            background: linear-gradient(to bottom, #ff7fbf, #ff1a8c);
        }
        
        @media (max-width: 768px) {
            .modal-container {
                width: 95%;
                max-height: 90vh;
            }
            
            .modal-header {
                padding: 15px 20px;
            }
            
            .modal-title {
                font-size: 1.5rem;
            }
            
            .modal-body {
                padding: 20px;
                max-height: 70vh;
            }
            
            .modal-section h3 {
                font-size: 1.2rem;
            }
            
            .modal-button {
                padding: 12px 30px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .modal-header {
                padding: 15px;
            }
            
            .modal-title {
                font-size: 1.3rem;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .modal-section {
                margin-bottom: 20px;
            }
            
            .modal-list li {
                padding-left: 25px;
            }
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 30px 15px;
            }
            
            .register-form {
                padding: 30px 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        @media (max-width: 480px) {
            .register-form {
                padding: 25px 15px;
            }
            
            .register-title {
                font-size: 1.5rem;
            }
            
            .register-subtitle {
                font-size: 1rem;
            }
            
            .form-input {
                padding: 12px 15px;
            }
        }
        /* Estilo para o select */
select.form-input {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ff66b3'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 20px;
    padding-right: 40px;
    cursor: pointer;
}

/* Estilo para as opções */
select.form-input option {
    background: #3a3a3a;
    color: white;
    padding: 10px;
}

/* Estilo quando o select está focado */
select.form-input:focus {
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23fc5cac'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
}
2. JavaScript para formatação automática (substitua o script existente):
    </style>
</head>
<body>
    <?php if(isset($sucesso) && $sucesso): ?>
    <div class="success-message">
        <div class="success-content">
            <i class="fas fa-check-circle success-icon"></i>
            <h3>Cadastro realizado com sucesso!</h3>
            <p>Sua conta foi criada e você já pode acessar o sistema.</p>
            <a href="login.php" class="success-button">Ir para o Login</a>
            <p class="success-redirect">Redirecionando em <span id="countdown">5</span> segundos...</p>
        </div>
    </div>

    <?php else: ?>
    <div class="register-container">
        <div class="register-header">
            <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Logo Love Chat" class="register-logo">
            <h1 class="register-title">Crie sua conta e comece a <span class="highlight">ganhar hoje</span></h1>
            <p class="register-subtitle">Preencha o formulário abaixo para começar sua jornada de sucesso com o Love Chat</p>
        </div>
        
        <form id="registerForm" class="register-form" method="POST" action="">
            <?php if(!empty($erro)): ?>
                <div class="error-message"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name" class="form-label">Nome completo</label>
                    <input type="text" id="name" name="name" class="form-input" required placeholder="Seu nome completo" 
                           autocomplete="name" spellcheck="false" autocorrect="off" autocapitalize="words">
                    <div class="form-error" id="name-error">Por favor, insira seu nome completo</div>
                </div>
                
                <div class="form-group">
                    <label for="nickname" class="form-label">Apelido/Nickname</label>
                    <input type="text" id="nickname" name="nickname" class="form-input" 
                           placeholder="Seu apelido (opcional)" autocomplete="nickname">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone" class="form-label">WhatsApp</label>
                    <input type="tel" id="phone" name="phone" class="form-input" required 
                           placeholder="(00) 91234-5678" maxlength="15" pattern="^\(\d{2}\) \d{5}-\d{4}$">
                    <div class="form-error" id="phone-error">Por favor, insira um número válido (DDD + 9 dígitos)</div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" name="email" class="form-input" required placeholder="Seu melhor e-mail">
                    <div class="form-error" id="email-error">Por favor, insira um e-mail válido</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" id="password" name="password" class="form-input" required placeholder="Mínimo 8 caracteres">
                    <div class="form-error" id="password-error">A senha deve ter pelo menos 8 caracteres</div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirme a senha</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-input" required placeholder="Digite novamente">
                    <div class="form-error" id="confirm-error">As senhas não coincidem</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="tipo_chave_pix" class="form-label">Tipo de Chave PIX</label>
                    <select id="tipo_chave_pix" name="tipo_chave_pix" class="form-input">
                        <option value="">Selecione...</option>
                        <option value="CPF">CPF</option>
                        <option value="CNPJ">CNPJ</option>
                        <option value="EMAIL">E-mail</option>
                        <option value="TELEFONE">Telefone</option>
                        <option value="ALEATORIA">Chave Aleatória</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="chave_pix" class="form-label">Chave PIX (opcional)</label>
                    <input type="text" id="chave_pix" name="chave_pix" class="form-input" 
                           placeholder="Informe sua chave PIX">
                </div>
            </div>
            
            <div class="terms-group">
                <div class="terms-check">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms" class="terms-text">Li e aceito os <a href="#" class="terms-link" id="termsLink">Termos de Uso</a> e <a href="#" class="terms-link" id="privacyLink">Política de Privacidade</a></label>
                </div>
                <div class="form-error" id="terms-error">Você deve aceitar os termos para continuar</div>
            </div>
            
            <button type="submit" class="register-button">
                <i class="fas fa-user-plus"></i> CRIAR MINHA CONTA
            </button>
            
            <div class="login-link">
                Já tem uma conta? <a href="login.php">Faça login aqui</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Modal de Termos de Uso -->
    <div id="termsModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Termos de Uso - Love Chat</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <h3>1. Funcionamento da Plataforma</h3>
                    <p>O Love Chat oferece um sistema completo para interações adultas virtuais, onde as criadoras:</p>
                    <ul class="modal-list">
                        <li>Selecionam entre <span class="highlight-term">modelos IA pré-criadas</span> com personalidades e conteúdos exclusivos</li>
                        <li>Recebem um <span class="highlight-term">WhatsApp Business dedicado</span> vinculado à modelo escolhida</li>
                        <li>Utilizam <span class="highlight-term">conteúdo multimídia pronto</span> (fotos/vídeos +18) para as interações</li>
                        <li>Contam com <span class="highlight-term">tráfego pago automático</span> direcionado para o WhatsApp</li>
                    </ul>
                </div>
                
                <div class="modal-section">
                    <h3>2. Modelos Disponíveis</h3>
                    <p>Cada modelo IA inclui:</p>
                    <ul class="modal-list">
                        <li>Perfil completo com nome, idade e personalidade definida</li>
                        <li>Galeria com mais de 500 mídias exclusivas (fotos/vídeos)</li>
                        <li>Roteiros de conversa e abordagem otimizados</li>
                        <li>Dados de desempenho histórico para referência</li>
                        <li>Atualizações mensais de conteúdo</li>
                    </ul>
                </div>
                
                <div class="modal-section">
                    <h3>3. Responsabilidades</h3>
                    <p>Ao utilizar a plataforma, você concorda em:</p>
                    <ul class="modal-list">
                        <li>Manter todas as interações dentro dos <span class="highlight-term">limites legais</span></li>
                        <li>Nunca revelar que a modelo é uma representação virtual</li>
                        <li>Seguir os <span class="highlight-term">protocolos de segurança</span> estabelecidos</li>
                        <li>Respeitar a taxa de <span class="highlight-term">30%</span> sobre as vendas realizadas</li>
                        <li>Utilizar exclusivamente os <span class="highlight-term">números WhatsApp</span> fornecidos</li>
                    </ul>
                </div>
                
                <div class="modal-section">
                    <h3>4. Remuneração</h3>
                    <p>Os ganhos seguem a estrutura:</p>
                    <ul class="modal-list">
                        <li><span class="highlight-term">70%</span> do valor bruto para a criadora</li>
                        <li>Saque mínimo de <span class="highlight-term">R$ 150,00</span></li>
                        <li>Processamento em até <span class="highlight-term">3 dias úteis</span></li>
                        <li>Relatórios detalhados de desempenho</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Política de Privacidade -->
    <div id="privacyModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Política de Privacidade - Love Chat</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <h3>1. Proteção de Dados</h3>
                    <p>Implementamos medidas rigorosas para segurança:</p>
                    <ul class="modal-list">
                        <li><span class="highlight-term">Números virtuais</span> - Todos os WhatsApp são descartáveis e não vinculados a dados pessoais</li>
                        <li><span class="highlight-term">Criptografia</span> - Todas as comunicações são criptografadas de ponta a ponta</li>
                        <li><span class="highlight-term">Dados segregados</span> - Suas informações reais nunca são vinculadas às modelos IA</li>
                        <li><span class="highlight-term">Anonimização</span> - Metadados são removidos de todas as mídias</li>
                    </ul>
                </div>
                
                <div class="modal-section">
                    <h3>2. Coleta de Informações</h3>
                    <p>Coletamos apenas o essencial:</p>
                    <ul class="modal-list">
                        <li>Dados cadastrais básicos (nome, e-mail, CPF para pagamento)</li>
                        <li>Métricas de desempenho das campanhas</li>
                        <li>Padrões de interação (apenas para melhoria do serviço)</li>
                        <li>Registros financeiros (para repasse de valores)</li>
                    </ul>
                </div>
                
                <div class="modal-section">
                    <h3>3. Compartilhamento</h3>
                    <p>Seus dados podem ser usados para:</p>
                    <ul class="modal-list">
                        <li>Processamento de pagamentos (apenas dados necessários)</li>
                        <li>Otimização de campanhas publicitárias</li>
                        <li>Geração de relatórios de desempenho</li>
                        <li>Cumprimento de obrigações legais</li>
                    </ul>
                </div>
                
                <div class="modal-section">
                    <h3>4. Segurança Operacional</h3>
                    <p>Proteções específicas do sistema:</p>
                    <ul class="modal-list">
                        <li><span class="highlight-term">Firewall dedicado</span> para proteção contra ataques</li>
                        <li><span class="highlight-term">Backups diários</span> de todas as conversas</li>
                        <li><span class="highlight-term">Monitoramento 24/7</span> de atividades suspeitas</li>
                        <li><span class="highlight-term">Números descartáveis</span> - Troca periódica dos WhatsApp Business</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Redirecionamento automático
    let seconds = 5;
    const countdownElement = document.getElementById('countdown');

    const countdown = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;
        
        if(seconds <= 0) {
            clearInterval(countdown);
            window.location.href = 'login.php';
        }
    }, 1000);
    </script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const password = document.getElementById('password');
    const confirm = document.getElementById('password_confirm');
    const terms = document.getElementById('terms');
    const phoneInput = document.getElementById('phone');
    const tipoChavePix = document.getElementById('tipo_chave_pix');
    const chavePixInput = document.getElementById('chave_pix');
    
    // Máscara do telefone (XX) 9XXXX-XXXX
    const mask = ['(', /\d/, /\d/, ')', ' ', /\d/, /\d/, /\d/, /\d/, /\d/, '-', /\d/, /\d/, /\d/, /\d/];
    const maxLength = mask.length;
    
    // Impede a entrada de caracteres não numéricos
    phoneInput.addEventListener('keypress', function(e) {
        // Permite apenas números (0-9)
        if (e.keyCode < 48 || e.keyCode > 57) {
            e.preventDefault();
        }
    });
    
    // Formatação em tempo real do telefone
    phoneInput.addEventListener('input', function(e) {
        const value = e.target.value.replace(/\D/g, '');
        let formattedValue = '';
        let valueIndex = 0;
        
        // Aplica a máscara
        for (let i = 0; i < mask.length && valueIndex < value.length; i++) {
            if (typeof mask[i] === 'string') {
                formattedValue += mask[i];
            } else {
                formattedValue += value[valueIndex++];
            }
        }
        
        e.target.value = formattedValue;
        
        // Limita ao tamanho máximo
        if (e.target.value.length > maxLength) {
            e.target.value = e.target.value.substring(0, maxLength);
        }
    });
    
    // Validação ao sair do campo do telefone
    phoneInput.addEventListener('blur', function() {
        const value = this.value.replace(/\D/g, '');
        const errorElement = document.getElementById('phone-error');
        
        if (value.length !== 11) {
            errorElement.style.display = 'block';
            errorElement.textContent = 'Por favor, insira um número válido (DDD + 9 dígitos)';
        } else {
            errorElement.style.display = 'none';
        }
    });
    
    // Impede a colagem de conteúdo inválido no telefone
    phoneInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasteData = e.clipboardData.getData('text/plain').replace(/\D/g, '');
        let formattedValue = '';
        let valueIndex = 0;
        
        for (let i = 0; i < mask.length && valueIndex < pasteData.length; i++) {
            if (typeof mask[i] === 'string') {
                formattedValue += mask[i];
            } else {
                formattedValue += pasteData[valueIndex++];
            }
        }
        
        // Insere o valor formatado
        document.execCommand('insertText', false, formattedValue);
    });

    // Formatação da chave PIX
    tipoChavePix.addEventListener('change', function() {
        formatarChavePix();
        // Limpa o campo quando muda o tipo
        chavePixInput.value = '';
        // Atualiza o placeholder
        atualizarPlaceholderPIX();
    });
    
    chavePixInput.addEventListener('input', function() {
        formatarChavePix();
    });
    
    function atualizarPlaceholderPIX() {
        const tipo = tipoChavePix.value;
        switch(tipo) {
            case 'CPF':
                chavePixInput.placeholder = '000.000.000-00';
                break;
            case 'CNPJ':
                chavePixInput.placeholder = '00.000.000/0000-00';
                break;
            case 'EMAIL':
                chavePixInput.placeholder = 'seu@email.com';
                break;
            case 'TELEFONE':
                chavePixInput.placeholder = '(00) 00000-0000';
                break;
            case 'ALEATORIA':
                chavePixInput.placeholder = '00000000-0000-0000-0000-000000000000';
                break;
            default:
                chavePixInput.placeholder = 'Informe sua chave PIX';
        }
    }
    
    function formatarChavePix() {
        const tipo = tipoChavePix.value;
        let valor = chavePixInput.value;
        
        switch(tipo) {
            case 'CPF':
                // Remove tudo que não é número
                valor = valor.replace(/\D/g, '');
                // Limita a 11 caracteres
                valor = valor.substring(0, 11);
                // Aplica a máscara
                valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                break;
                
            case 'CNPJ':
                // Remove tudo que não é número
                valor = valor.replace(/\D/g, '');
                // Limita a 14 caracteres
                valor = valor.substring(0, 14);
                // Aplica a máscara
                valor = valor.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                break;
                
            case 'TELEFONE':
                // Remove tudo que não é número
                valor = valor.replace(/\D/g, '');
                // Limita a 11 caracteres
                valor = valor.substring(0, 11);
                // Aplica a máscara
                valor = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                break;
                
            case 'EMAIL':
                // Não formata, apenas limita o tamanho
                valor = valor.substring(0, 100);
                break;
                
            case 'ALEATORIA':
                // Permite letras, números e hífens
                valor = valor.replace(/[^a-zA-Z0-9-]/g, '');
                // Limita a 36 caracteres (tamanho de um UUID)
                valor = valor.substring(0, 36);
                // Formata como UUID (opcional)
                if (valor.length > 8) {
                    valor = valor.replace(/([a-zA-Z0-9]{8})([a-zA-Z0-9]{4})([a-zA-Z0-9]{4})([a-zA-Z0-9]{4})([a-zA-Z0-9]{12})/, '$1-$2-$3-$4-$5');
                }
                break;
        }
        
        chavePixInput.value = valor;
    }
    
    // Atualiza o placeholder inicial
    atualizarPlaceholderPIX();

    // Validação em tempo real para outros campos
    form.addEventListener('input', function(e) {
        const target = e.target;
        const errorElement = document.getElementById(target.id + '-error');
        if (!errorElement) return;
        
        if(target.id === 'password') {
            if(target.value.length < 8 && target.value.length > 0) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'A senha deve ter pelo menos 8 caracteres';
            } else {
                errorElement.style.display = 'none';
            }
        }
        
        if(target.id === 'password_confirm') {
            if(target.value !== password.value && target.value.length > 0) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'As senhas não coincidem';
            } else {
                errorElement.style.display = 'none';
            }
        }
        
        if(target.id === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(!emailRegex.test(target.value) && target.value.length > 0) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'Por favor, insira um e-mail válido';
            } else {
                errorElement.style.display = 'none';
            }
        }
    });
    
    // Validação no envio
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validar nome
        if(document.getElementById('name').value.trim() === '') {
            document.getElementById('name-error').style.display = 'block';
            isValid = false;
        }
        
        // Validar senha
        if(password.value.length < 8) {
            document.getElementById('password-error').style.display = 'block';
            isValid = false;
        }
        
        // Validar confirmação
        if(password.value !== confirm.value) {
            document.getElementById('confirm-error').style.display = 'block';
            isValid = false;
        }
        
        // Validar telefone
        const phoneValue = phoneInput.value.replace(/\D/g, '');
        if(phoneValue.length !== 11) {
            document.getElementById('phone-error').style.display = 'block';
            document.getElementById('phone-error').textContent = 'Por favor, insira um número válido (DDD + 9 dígitos)';
            isValid = false;
        }
        
        // Validar chave PIX se informada
        if(chavePixInput.value.trim() !== '' && tipoChavePix.value === '') {
            document.getElementById('tipo_chave_pix').focus();
            isValid = false;
        }
        
        // Validar termos
        if(!terms.checked) {
            document.getElementById('terms-error').style.display = 'block';
            isValid = false;
        }
        
        if(!isValid) {
            e.preventDefault();
            
            // Rolagem para o primeiro erro
            const firstError = form.querySelector('.form-error[style="display: block;"]') || 
                              document.querySelector('.form-error:not([style*="none"])');
            if(firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });

    // Controle dos Modais
    const termsLink = document.getElementById('termsLink');
    const privacyLink = document.getElementById('privacyLink');
    const termsModal = document.getElementById('termsModal');
    const privacyModal = document.getElementById('privacyModal');
    const closeButtons = document.querySelectorAll('.close-modal');

    // Abrir Modal de Termos
    if(termsLink) {
        termsLink.addEventListener('click', function(e) {
            e.preventDefault();
            termsModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
    }

    // Abrir Modal de Privacidade
    if(privacyLink) {
        privacyLink.addEventListener('click', function(e) {
            e.preventDefault();
            privacyModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
    }

    // Fechar Modais
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            termsModal.style.display = 'none';
            privacyModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    });

    // Fechar ao clicar fora do modal
    window.addEventListener('click', function(e) {
        if (e.target === termsModal) {
            termsModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        if (e.target === privacyModal) {
            privacyModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Redirecionamento automático (se existir na página)
    if(document.getElementById('countdown')) {
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');

        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if(seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php';
            }
        }, 1000);
    }
});
</script>
</body>
</html>