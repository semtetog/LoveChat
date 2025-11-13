<?php
session_start();
require 'includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$mensagem = '';
$erro = '';
$token_valido = false;
$email = '';

// Verifica se o token está na URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    error_log("Token recebido na URL: " . $token);
    
    $usuario = validarTokenReset($token);
    
    if ($usuario) {
        $token_valido = true;
        $email = $usuario['email'];
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_token'] = $token;
        error_log("Token válido para o email: " . $email);
    } else {
        $erro = "Link de recuperação inválido ou expirado";
        error_log("Token inválido ou expirado: " . $token);
    }
} else {
    $erro = "Nenhum token de recuperação fornecido.";
    error_log("Acesso sem token");
}

// Processa o formulário de redefinição
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['reset_email'], $_SESSION['reset_token'])) {
    $nova_senha = $_POST['password'];
    $confirmar_senha = $_POST['confirm_password'];
    $email = $_SESSION['reset_email'];
    $token = $_SESSION['reset_token'];
    
    if ($nova_senha !== $confirmar_senha) {
        $erro = "As senhas não coincidem.";
    } elseif (strlen($nova_senha) < 8) {
        $erro = "A senha deve ter pelo menos 8 caracteres.";
    } else {
        if (atualizarSenha($email, $nova_senha, $token)) {
            $mensagem = "Senha redefinida com sucesso!";
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_token']);
            error_log("Senha alterada para o email: " . $email);
            
            // Redireciona para login após 3 segundos
            header("Refresh: 3; url=login.php");
        } else {
            $erro = "Erro ao atualizar a senha. Tente novamente.";
            error_log("Falha ao atualizar senha para: " . $email);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Redefinir Senha - Love Chat</title>
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
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }
        
        .input-icon:hover {
            color: #fc5cac;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            text-align: left;
            color: #aaa;
        }
        
        .strength-bar {
            height: 5px;
            background: #333;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-progress {
            height: 100%;
            width: 0%;
            background: #ff3333;
            transition: all 0.3s;
        }
        
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
        
        .cta-button:disabled {
            background: #666;
            cursor: not-allowed;
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
        
        .cta-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 255, 0, 0.4);
        }
        
        .cta-button:hover:not(:disabled)::after {
            left: 100%;
        }
        
        .cta-button i {
            margin-right: 8px;
            transition: all 0.3s ease;
        }
        
        .cta-button:hover:not(:disabled) i {
            transform: translateX(3px);
        }
        
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
                <span class="headline-line">Redefinir <span class="highlight">Senha</span></span>
            </h1>
        </div>
        
        <div class="auth-container">
            <?php if(!empty($mensagem)): ?>
                <div class="mensagem">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $mensagem; ?></span>
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if(!empty($erro)): ?>
                <div class="erro-mensagem">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $erro; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($token_valido && empty($mensagem)): ?>
                <form id="resetForm" class="auth-form" method="POST" action="">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div class="form-group">
                        <input type="password" name="password" id="password" class="form-input" required placeholder="Nova senha" minlength="8">
                        <i class="fas fa-eye input-icon" id="togglePassword"></i>
                        <div class="password-strength">
                            <div>Força da senha: <span id="strength-text">fraca</span></div>
                            <div class="strength-bar">
                                <div class="strength-progress" id="strength-bar"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" required placeholder="Confirme a nova senha" minlength="8">
                        <i class="fas fa-eye input-icon" id="toggleConfirmPassword"></i>
                    </div>
                    
                    <button type="submit" class="cta-button" id="submitButton">
                        <i class="fas fa-save"></i> REDEFINIR SENHA
                    </button>
                </form>
            <?php elseif(empty($mensagem)): ?>
                <div class="auth-links">
                    <a href="forgot.php" class="auth-link">Solicitar novo link de recuperação</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Alternar visibilidade da senha
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                togglePassword.classList.toggle('fa-eye-slash');
            });
        }
        
        if (toggleConfirmPassword && confirmPassword) {
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                toggleConfirmPassword.classList.toggle('fa-eye-slash');
            });
        }
        
        // Validar força da senha
        if (password) {
            password.addEventListener('input', function() {
                const strengthBar = document.getElementById('strength-bar');
                const strengthText = document.getElementById('strength-text');
                const submitButton = document.getElementById('submitButton');
                const value = password.value;
                let strength = 0;
                
                // Verifica o comprimento
                if (value.length >= 8) strength += 1;
                if (value.length >= 12) strength += 1;
                
                // Verifica caracteres diversos
                if (/[A-Z]/.test(value)) strength += 1;
                if (/[0-9]/.test(value)) strength += 1;
                if (/[^A-Za-z0-9]/.test(value)) strength += 1;
                
                // Atualiza a barra e texto
                const width = strength * 25;
                strengthBar.style.width = width + '%';
                
                if (strength <= 1) {
                    strengthBar.style.backgroundColor = '#ff3333';
                    strengthText.textContent = 'fraca';
                } else if (strength <= 3) {
                    strengthBar.style.backgroundColor = '#ffcc00';
                    strengthText.textContent = 'média';
                } else {
                    strengthBar.style.backgroundColor = '#00cc66';
                    strengthText.textContent = 'forte';
                }
                
                // Habilita/desabilita o botão
                submitButton.disabled = value.length < 8;
            });
        }
        
        // Validação do formulário
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password');
                const confirm = document.getElementById('confirm_password');
                
                if (password.value !== confirm.value) {
                    e.preventDefault();
                    alert('As senhas não coincidem!');
                    confirm.focus();
                }
            });
        }
    });
    </script>
</body>
</html>