<?php
// ATIVE OS LOGS PARA DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

session_start();
require 'includes/db.php';
require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    $dadosReset = solicitarResetSenha($email);
    
    if ($dadosReset) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        $link = $protocol . $domain . "/reset-password?token=" . $dadosReset['token'];
        
        try {
            $mail = new PHPMailer(true);
            
            // Configurações SMTP (mantenha as suas)
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@applovechat.com';
            $mail->Password   = 'Gameroficial2*';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
        
            // Remetente e destinatário
            $mail->setFrom('contato@applovechat.com', 'Love Chat');
            $mail->addAddress($email);
            
            // Conteúdo do email - NOVO ESTILO
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Recuperação de Senha - Love Chat';
            
            $mail->Body = "
            <!DOCTYPE html>
            <html lang='pt-BR'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <meta name='color-scheme' content='dark'>
                <meta name='supported-color-schemes' content='dark only'>
                <title>Recuperação de Senha - Love Chat</title>
                <style>
                    /* Forçar tema escuro em todos os clientes de e-mail */
                    :root {
                        color-scheme: dark !important;
                        supported-color-schemes: dark !important;
                    }
                    
                    body {
                        background-color: #121212 !important;
                        color: #e0e0e0 !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    
                    /* Container principal */
                    .email-container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #1e1e1e !important;
                        border-radius: 15px;
                        overflow: hidden;
                        border: 1px solid #ff007f !important;
                    }
                    
                    /* Cabeçalho */
                    .email-header {
                        background: #1e1e1e !important;
                        padding: 40px 20px;
                        text-align: center;
                        border-bottom: 2px solid #ff007f !important;
                    }
                    
                    .email-header img {
                        max-width: 200px;
                        height: auto;
                        display: block;
                        margin: 0 auto;
                    }
                    
                    /* Conteúdo */
                    .email-content {
                        padding: 30px;
                        background-color: #1e1e1e !important;
                        color: #e0e0e0 !important;
                    }
                    
                    h1 {
                        color: #ff007f !important;
                        font-family: 'Montserrat', Arial, sans-serif;
                        font-size: 24px;
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    
                    p {
                        color: #e0e0e0 !important;
                        font-family: 'Montserrat', Arial, sans-serif;
                        text-align: center;
                        margin-bottom: 20px;
                        font-size: 16px;
                        line-height: 1.5;
                    }
                    
                    /* Botão */
                    .button-container {
                        text-align: center;
                        margin: 30px 0;
                    }
                    
                    .reset-button {
                        display: inline-block;
                        background: linear-gradient(to bottom, #00cc00, #009900) !important;
                        color: white !important;
                        padding: 14px 30px;
                        text-decoration: none;
                        border-radius: 50px;
                        font-weight: bold;
                        font-family: 'Montserrat', Arial, sans-serif;
                        font-size: 16px;
                        box-shadow: 0 4px 15px rgba(0, 255, 0, 0.3);
                    }
                    
                    /* Destaques */
                    .text-highlight {
                        color: #00ff00 !important;
                        font-weight: bold;
                    }
                    
                    .warning {
                        color: #ff5555 !important;
                        font-weight: bold;
                    }
                    
                    /* Rodapé */
                    .email-footer {
                        padding: 25px 20px;
                        text-align: center;
                        background-color: #121212 !important;
                        color: #aaa !important;
                        font-size: 12px;
                        border-top: 1px solid #333 !important;
                    }
                    
                    .email-footer a {
                        color: #00ff00 !important;
                        text-decoration: none !important;
                    }
                    
                    /* Responsivo */
                    @media screen and (max-width: 600px) {
                        .email-container {
                            border-radius: 0 !important;
                        }
                        
                        .email-header {
                            padding: 30px 15px !important;
                        }
                        
                        .email-header img {
                            max-width: 160px !important;
                        }
                        
                        .email-content {
                            padding: 25px 15px !important;
                        }
                        
                        .reset-button {
                            padding: 12px 25px !important;
                            font-size: 14px !important;
                        }
                    }
                </style>
            </head>
            <body style='margin: 0; padding: 0; background-color: #121212; color: #e0e0e0;'>
                <!--[if mso]>
                <style type='text/css'>
                    .email-container {
                        width: 600px !important;
                    }
                </style>
                <![endif]-->
                
                <div class='email-container'>
                    <div class='email-header'>
                        <img src='https://i.ibb.co/pr178Pgk/love-chat.png' alt='Love Chat' width='200' style='max-width: 200px; height: auto;'>
                    </div>
                    
                    <div class='email-content'>
                        <h1>Olá, {$dadosReset['nome']}!</h1>
                        <p>Recebemos uma solicitação para redefinir sua senha no <span class='text-highlight'>Love Chat</span>.</p>
                        <p>Clique no botão abaixo para criar uma nova senha:</p>
                        
                        <div class='button-container'>
                            <a href='{$link}' class='reset-button'>Redefinir Senha</a>
                        </div>
                        
                        <p>Se você não solicitou esta alteração, por favor ignore este e-mail.</p>
                        <p class='warning'>⚠️ O link expira em 15 minutos.</p>
                    </div>
                    
                    <div class='email-footer'>
                        <p>© ".date('Y')." Love Chat. Todos os direitos reservados.</p>
                        <p style='margin-top: 10px;'>Caso o botão não funcione, copie e cole este link no seu navegador:</p>
                        <p style='word-break: break-all;'><a href='{$link}'>{$link}</a></p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->AltBody = "Olá {$dadosReset['nome']},\n\nPara redefinir sua senha no Love Chat, acesse este link (válido por 15 minutos):\n$link\n\nSe você não solicitou isso, ignore este e-mail.";
            
            if($mail->send()) {
                $mensagem = "Um link de recuperação foi enviado para seu e-mail!";
                $_SESSION['mensagem'] = $mensagem;
                header("Location: forgot.php");
                exit();
            } else {
                $erro = "Falha ao enviar e-mail. Tente novamente.";
                $_SESSION['erro'] = $erro;
                header("Location: forgot.php");
                exit();
            }
            
        } catch (Exception $e) {
            $erro = "Erro ao enviar e-mail: " . $e->getMessage();
            $_SESSION['erro'] = $erro;
            header("Location: forgot.php");
            exit();
            error_log("Erro no forgot.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    } else {
        $erro = "E-mail não encontrado em nosso sistema.";
        $_SESSION['erro'] = $erro;
        header("Location: forgot.php");
        exit();
    }
}

// Verifica se há mensagem na sessão (do redirecionamento)
if(isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}

// Verifica se há erro na sessão (do redirecionamento)
if(isset($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Recuperar Senha - Love Chat</title>
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
        
        /* Manchas rosas nas laterais */
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
        
        /* Mensagens */
        .mensagem {
            background: rgba(0, 204, 102, 0.2);
            color: #00cc66;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid rgba(0, 204, 102, 0.5);
            display: <?php echo !empty($mensagem) ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .erro-mensagem {
            background: rgba(255, 85, 85, 0.2);
            color: #ff5555;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 85, 85, 0.5);
            display: <?php echo !empty($erro) ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
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
            top: 16px;
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
            font-size: 1.2rem;
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
            justify-content: center;
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
                top: 14px;
            }
        }
        
        /* Estilo para autofill */
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
                <span class="headline-line">Recupere sua senha</span>
                <span class="headline-line">e volte a <span class="highlight">ganhar hoje</span></span>
            </h1>
        </div>
        
        <div class="auth-container">
            <?php if(!empty($mensagem)): ?>
                <div class="mensagem">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $mensagem; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($erro)): ?>
                <div class="erro-mensagem">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $erro; ?></span>
                </div>
            <?php endif; ?>
            
            <form id="forgotForm" class="auth-form" method="POST" action="">
                <div class="form-group">
                    <input type="email" name="email" class="form-input" required placeholder="Digite seu e-mail cadastrado">
                    <i class="fas fa-envelope input-icon"></i>
                </div>
                
                <button type="submit" class="cta-button">
                    <i class="fas fa-paper-plane"></i> ENVIAR LINK
                </button>
                
                <div class="auth-links">
                    <a href="login.php" class="auth-link">Lembrou sua senha? Faça login</a>
                </div>
            </form>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validação em tempo real
        const form = document.getElementById('forgotForm');
        const email = document.querySelector('[name="email"]');
        
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
    });
    </script>
</body>
</html>