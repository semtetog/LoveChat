<?php
// --- Configurações Iniciais ---
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Inicia a sessão se ainda não foi iniciada
}
// INCLUI db.php, que DEVE CONTER a função verificarLogin CORRIGIDA
require 'includes/db.php';

// --- Logs de Erro (Opcional, mas recomendado) ---
$log_path_login = __DIR__ . '/logs/login_errors.log'; // Log específico para login
if (!file_exists(dirname($log_path_login))) {
    // Tenta criar o diretório de logs se não existir
    @mkdir(dirname($log_path_login), 0755, true);
}
ini_set('error_log', $log_path_login);
ini_set('display_errors', 0); // Não mostrar erros em produção
error_reporting(E_ALL); // Logar todos os erros

// --- Fuso Horário ---
date_default_timezone_set('America/Sao_Paulo'); // <-- IMPORTANTE: Ajuste para seu fuso horário correto

// --- Avatar Padrão ---
// Define um avatar padrão caso não venha do banco ou da config
defined('DEFAULT_AVATAR') or define('DEFAULT_AVATAR', 'default.jpg'); // Verifique se 'default.jpg' existe em /uploads/avatars/

// --- Processamento do Formulário de Login ---
$erro = null; // Inicializa a variável para mensagens de erro ao usuário

// Verifica se o formulário foi enviado usando o método POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Verifica se os campos de e-mail e senha foram enviados e não estão vazios
    if (empty($_POST['email']) || empty($_POST['password'])) {
        $erro = "Por favor, preencha o e-mail e a senha.";
    } else {
        // Limpa e valida o e-mail
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        // Pega a senha como foi enviada (a função de verificação fará o hash/compare)
        $senha = $_POST['password'];

        // Verifica se a conexão com o banco ($pdo) está disponível (definida em db.php)
        if (!isset($pdo)) {
            error_log("CRITICAL LOGIN ERROR: PDO connection not available in login.php (expected from db.php)");
            $erro = "Erro interno do servidor. Tente novamente mais tarde.";
        }
        // Procede apenas se $pdo existe e $email/$senha são válidos
        elseif ($email && $senha) {

            // --- Tenta Verificar o Login ---
            // Chama a função verificarLogin() definida em db.php
            // Ela já usa a variável global $pdo.
            $loginResult = verificarLogin($email, $senha); // Não precisa passar $pdo aqui se a função em db.php usa 'global $pdo'

            if (is_array($loginResult) && isset($loginResult['id'])) { // LOGIN BEM SUCEDIDO E APROVADO
                $usuario = $loginResult; // Atribui os dados do usuário

                // --- LOGIN BEM SUCEDIDO ---
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_nome'] = $usuario['nome'];
                $_SESSION['user_avatar'] = $usuario['avatar'] ?? DEFAULT_AVATAR;
                $_SESSION['is_admin'] = (bool)($usuario['is_admin'] ?? false);
                $_SESSION['avatar_updated'] = time();
                $_SESSION['user_phone'] = $usuario['telefone'] ?? '';

                session_regenerate_id(true);

                // ***** INÍCIO: LOG DO EVENTO DE LOGIN PARA O FEED DO ADMIN *****
                try {
                    $eventType = 'user_login';
                    $userIdLog = $usuario['id'];
                    $userNameLog = $usuario['nome'];
                    $ipAddress = 'Desconhecido';
                    if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $ipAddress = $_SERVER['HTTP_CLIENT_IP']; }
                    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR']; }
                    elseif (!empty($_SERVER['REMOTE_ADDR'])) { $ipAddress = $_SERVER['REMOTE_ADDR']; }
                    $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) ?: 'IP Inválido';
                    $eventDataArray = ['ip_address' => $ipAddress, 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido'];
                    $eventDataJson = json_encode($eventDataArray, JSON_UNESCAPED_UNICODE);
                    if ($eventDataJson === false) {
                         error_log("Erro JSON encode [user_login] para user $userIdLog: " . json_last_error_msg());
                         $eventDataJson = json_encode(['error' => 'JSON encode failed', 'ip' => $ipAddress]);
                    }
                    $stmt_feed = $pdo->prepare("INSERT INTO admin_events_feed (event_type, user_id, event_data, created_at) VALUES (:event_type, :user_id, :event_data, NOW())");
                    $stmt_feed->execute([':event_type' => $eventType, ':user_id' => $userIdLog, ':event_data' => $eventDataJson]);
                    error_log("Admin feed log created for user_login, user ID: " . $userIdLog);
                } catch (PDOException $e) {
                    error_log("ADMIN FEED LOG Error (PDO - user_login) for user $userIdLog: " . $e->getMessage());
                } catch (Throwable $t) {
                    error_log("ADMIN FEED LOG General Error (user_login) for user $userIdLog: " . $t->getMessage());
                }
                // ***** FIM: LOG DO EVENTO DE LOGIN *****

                if ($_SESSION['is_admin']) {
                    header("Location: admin/admin_dashboard.php");
                } else {
                    $stmtCheckTutorial = $pdo->prepare("SELECT tutorial_visto FROM usuarios WHERE id = ?");
                    $stmtCheckTutorial->execute([$_SESSION['user_id']]);
                    $tutorialVisto = $stmtCheckTutorial->fetchColumn();
                    if (!$tutorialVisto) {
                        header("Location: tutorial.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                }
                exit();

            } elseif (is_array($loginResult) && isset($loginResult['status'])) {
                if ($loginResult['status'] === 'pending_approval') {
                    $whatsapp_number_contact = "5511912345678"; // SEU NÚMERO AQUI
                    $whatsapp_message_contact = rawurlencode("Olá! Minha conta Love Chat está pendente de aprovação. Gostaria de mais informações sobre o pagamento da taxa de adesão e ativação.");
                    $whatsapp_link_contact = "https://wa.me/{$whatsapp_number_contact}?text={$whatsapp_message_contact}";
                    $erro = "Sua conta ainda não foi ativada. <br>Para prosseguir, é necessário aguardar a aprovação do Login. <br><br>Clique abaixo para mais informações: <br><a href='{$whatsapp_link_contact}' target='_blank' class='btn-whatsapp-contact-login'><i class='fab fa-whatsapp'></i> Falar no WhatsApp</a>";
                    error_log("Login attempt for PENDING APPROVAL email: " . $email);
                } elseif ($loginResult['status'] === 'inactive') {
                    $erro = "Sua conta está desativada. Entre em contato com o suporte para mais informações.";
                    error_log("Login attempt for INACTIVE account email: " . $email);
                } else {
                    $erro = "E-mail ou senha incorretos.";
                    error_log("Login failed (unknown status from verificarLogin) for email: " . $email);
                }
            } else {
                $erro = "E-mail ou senha incorretos.";
                error_log("Login failed for email: " . $email);
            }
        } else {
             if (!$email && !empty($_POST['email'])) {
                 $erro = "Formato de e-mail inválido.";
             }
        }
    }
}
// --- Fim do Processamento do Formulário ---
?>
<!-- SEU HTML COMPLETO VAI AQUI, EXATAMENTE COMO ANTES -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
       <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            color: #fff;
            text-align: center;
            overflow-x: hidden;
            background: #2a2a2a;
            position: relative;
        }

        /* Manchas rosas nas laterais (original) */
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

        /* Container Principal */
        .auth-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            justify-content: center;
        }

        /* Cabeçalho */
        .header-content {
            margin-bottom: 40px;
            animation: fadeInDown 0.8s ease-out;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            width: 140px;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 10px rgba(255,0,127,0.5));
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05) rotate(-5deg);
        }

        .headline {
            font-weight: 700;
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            line-height: 1.3;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.7);
        }

        .headline-line {
            display: block;
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

        /* Container do Formulário */
        .auth-container {
            background: rgba(40, 40, 40, 0.8);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 0, 127, 0.3);
            backdrop-filter: blur(5px);
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Formulário */
        .auth-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 50px 16px 20px;
            background: rgba(30, 30, 30, 0.7);
            border: 1px solid rgba(255, 0, 127, 0.3);
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #fc5cac;
            box-shadow: 0 0 0 3px rgba(255, 0, 127, 0.2);
        }

        .input-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .input-icon:hover {
            color: #fc5cac;
            transform: translateY(-50%) scale(1.1);
        }

        /* --- INÍCIO DAS MODIFICAÇÕES PARA MENSAGEM DE ERRO E BOTÃO WHATSAPP --- */
        .erro-mensagem {
            background: rgba(255, 85, 85, 0.2);
            color: #ff5555;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 85, 85, 0.5);
            display: <?php echo isset($erro) ? 'flex' : 'none'; ?>;
            flex-direction: column; /* Para o botão ficar abaixo do texto se necessário */
            align-items: center; /* Centraliza o conteúdo (ícone, texto, botão) */
            justify-content: center;
            gap: 10px;
            animation: shake 0.5s ease;
            line-height: 1.6; /* Aumenta o espaçamento entre linhas para o texto com <br> */
            text-align: center; /* Garante que o texto dentro do span também seja centralizado */
        }

        .erro-mensagem .fa-exclamation-circle {
            margin-bottom: 8px; /* Espaço abaixo do ícone de exclamação */
            font-size: 1.2rem; /* Tamanho do ícone um pouco maior */
        }

        /* Estilo para o link DENTRO da mensagem de erro (botão WhatsApp) */
        .erro-mensagem a.btn-whatsapp-contact-login, /* Seletor mais específico */
        .erro-mensagem span a.btn-whatsapp-contact-login /* Caso esteja dentro de um span */ {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px; /* Espaço acima do botão */
            padding: 10px 22px; /* Padding do botão */
            background: #25D366; /* Cor de fundo verde WhatsApp */
            color: white !important; /* Cor do texto branca, !important para garantir */
            border-radius: 25px; /* Bordas arredondadas */
            text-decoration: none !important; /* REMOVE O SUBLINHADO */
            font-weight: bold;
            font-size: 0.95rem; /* Tamanho da fonte do botão */
            transition: background-color 0.3s ease, transform 0.2s ease;
            border: none; /* Sem borda */
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        .erro-mensagem a.btn-whatsapp-contact-login i,
        .erro-mensagem span a.btn-whatsapp-contact-login i {
            margin-right: 8px; /* Espaço entre o ícone e o texto do botão */
            font-size: 1.1rem; /* Tamanho do ícone do WhatsApp */
        }

        .erro-mensagem a.btn-whatsapp-contact-login:hover,
        .erro-mensagem span a.btn-whatsapp-contact-login:hover {
            background-color: #128C7E; /* Cor de fundo mais escura no hover */
            text-decoration: none !important; /* Garante que não apareça sublinhado no hover */
            transform: translateY(-1px); /* Leve elevação no hover */
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
        }
        /* --- FIM DAS MODIFICAÇÕES --- */

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        /* Botão */
        .cta-button {
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
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(0, 255, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .cta-button::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 255, 0, 0.4);
        }

        .cta-button:hover::after {
            left: 100%;
        }

        .cta-button i {
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .cta-button:hover i {
            transform: translateX(3px);
        }

        /* Links */
        .auth-links {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .auth-link {
            color: #e0e0e0;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            padding-bottom: 2px;
        }

        .auth-link::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background: #00ff00;
            transition: all 0.3s ease;
        }

        .auth-link:hover {
            color: #00ff00;
        }

        .auth-link:hover::after {
            width: 100%;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .auth-section {
                padding: 30px 15px;
            }

            .auth-container {
                padding: 30px 20px;
            }

            .auth-links {
                flex-direction: column;
                gap: 12px;
            }
            /* Ajuste no botão de WhatsApp para telas menores */
            .erro-mensagem a.btn-whatsapp-contact-login,
            .erro-mensagem span a.btn-whatsapp-contact-login {
                padding: 9px 18px;
                font-size: 0.9rem;
            }
            .erro-mensagem a.btn-whatsapp-contact-login i,
            .erro-mensagem span a.btn-whatsapp-contact-login i {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 25px 15px;
            }

            .logo {
                width: 120px;
            }

            .form-input {
                padding: 14px 45px 14px 15px;
            }

            .input-icon {
                right: 15px;
            }
        }
        /* Adicione isso no seu CSS */
        .form-input:-webkit-autofill,
        .form-input:-webkit-autofill:hover,
        .form-input:-webkit-autofill:focus {
            -webkit-text-fill-color: white !important;
            -webkit-box-shadow: 0 0 0px 1000px rgba(30, 30, 30, 0.7) inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body>
    <section class="auth-section">
        <div class="header-content">
            <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Logo Love Chat" class="logo">
            <h1 class="headline">
                <span class="headline-line">Acesse sua conta</span>
                <span class="headline-line">e comece a <span class="highlight">ganhar hoje</span></span>
            </h1>
        </div>

        <div class="auth-container">
            <?php if(isset($erro)): ?>
                <div class="erro-mensagem">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $erro; // $erro já contém o HTML formatado, incluindo o botão se necessário ?></span>
                </div>
            <?php endif; ?>

            <form id="loginForm" class="auth-form" method="POST" action="">
                <div class="form-group">
                    <input type="email" name="email" class="form-input" required placeholder="Seu e-mail cadastrado">
                    <i class="fas fa-envelope input-icon"></i>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="password" class="form-input" required placeholder="Sua senha">
                    <i class="fas fa-eye input-icon" id="togglePassword"></i>
                </div>

                <button type="submit" class="cta-button">
                    <i class="fas fa-sign-in-alt"></i> ENTRAR
                </button>

                <div class="auth-links">
                    <a href="register.php" class="auth-link">Criar nova conta</a>
                    <a href="forgot.php" class="auth-link">Esqueci minha senha</a>
                </div>
            </form>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                togglePassword.style.transform = 'translateY(-50%) scale(1.2)';
                setTimeout(() => {
                    togglePassword.style.transform = 'translateY(-50%) scale(1)';
                }, 200);
                if (type === 'password') {
                    togglePassword.classList.remove('fa-eye-slash');
                    togglePassword.classList.add('fa-eye');
                } else {
                    togglePassword.classList.remove('fa-eye');
                    togglePassword.classList.add('fa-eye-slash');
                }
            });
        }

        const form = document.getElementById('loginForm');
        const emailInputValidation = document.querySelector('#loginForm [name="email"]');

        if (form && emailInputValidation) {
            form.addEventListener('input', function(e) {
                const target = e.target;
                if(target.name === 'email' && target.value.length > 0) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if(!emailRegex.test(target.value)) {
                        target.style.borderColor = '#ff5555';
                    } else {
                        target.style.borderColor = 'rgba(255, 0, 127, 0.3)';
                    }
                }
            });
        }

        const emailPlaceholderInput = document.querySelector('#loginForm [name="email"]');
        if (emailPlaceholderInput) {
            const placeholderText = "Seu e-mail cadastrado";
            let i = 0;
            function typeWriter() {
                if (i < placeholderText.length && !emailPlaceholderInput.value && document.hasFocus() && emailPlaceholderInput === document.activeElement) {
                    emailPlaceholderInput.placeholder = placeholderText.substring(0, i+1);
                    i++;
                    setTimeout(typeWriter, Math.random() * 100 + 50);
                } else if (!emailPlaceholderInput.value && i >= placeholderText.length) {
                    setTimeout(() => {
                        i = 0;
                        emailPlaceholderInput.placeholder = "";
                        if (!emailPlaceholderInput.value && document.hasFocus() && emailPlaceholderInput === document.activeElement) {
                            typeWriter();
                        } else if (!emailPlaceholderInput.value) {
                             emailPlaceholderInput.placeholder = placeholderText;
                        }
                    }, 2000);
                } else if (!emailPlaceholderInput.value && emailPlaceholderInput !== document.activeElement) {
                     emailPlaceholderInput.placeholder = placeholderText;
                     i = 0;
                }
            }
            emailPlaceholderInput.addEventListener('focus', function() {
                if (!this.value) {
                    i = 0;
                    emailPlaceholderInput.placeholder = "";
                    typeWriter();
                }
            });
            emailPlaceholderInput.addEventListener('blur', function() {
                if (!this.value) {
                    i = 0;
                    this.placeholder = placeholderText;
                }
            });
        }
    });
    </script>
</body>
</html>