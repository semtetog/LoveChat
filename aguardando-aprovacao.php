<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$message = $_SESSION['registration_pending_message'] ?? "Seu cadastro foi realizado e está aguardando aprovação. Entraremos em contato ou, se aplicável, siga as instruções de pagamento.";
unset($_SESSION['registration_pending_message']); // Limpa a mensagem da sessão

// Defina seu número de WhatsApp e a mensagem padrão aqui
$whatsapp_number = "55119XXXXXXXX"; // SUBSTITUA PELO SEU NÚMERO COMPLETO (ex: 5511998765432)
$whatsapp_message = rawurlencode("Olá! Gostaria de informações sobre a ativação da minha conta Love Chat.");
$whatsapp_link = "https://wa.me/{$whatsapp_number}?text={$whatsapp_message}";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro em Análise - Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif; color: #fff; background: #2a2a2a;
            display: flex; align-items: center; justify-content: center; min-height: 100vh;
            text-align: center; padding: 20px; margin: 0;
            position: relative; overflow: hidden;
        }
        body::before { /* Efeito de manchas rosas */
            content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 10% 20%, rgba(255,0,127,0.1) 0%, transparent 25%),
                        radial-gradient(circle at 90% 30%, rgba(252,92,172,0.1) 0%, transparent 25%),
                        radial-gradient(circle at 20% 70%, rgba(255,105,180,0.1) 0%, transparent 25%),
                        radial-gradient(circle at 80% 80%, rgba(255,20,147,0.1) 0%, transparent 25%);
            z-index: -1; pointer-events: none;
        }
        .container {
            background: rgba(40,40,40,0.85); backdrop-filter: blur(8px);
            padding: 40px; border-radius: 15px; max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4); border: 1px solid rgba(255,0,127,0.3);
        }
        .logo { width: 120px; margin-bottom: 25px; filter: drop-shadow(0 0 10px rgba(255,0,127,0.5)); }
        h1 { color: #fc5cac; font-size: 2rem; margin-bottom: 15px; }
        p { font-size: 1.1rem; line-height: 1.7; margin-bottom: 25px; color: #e0e0e0; }
        .whatsapp-button {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            background: linear-gradient(to bottom, #25D366, #128C7E); color: white;
            padding: 14px 28px; border-radius: 50px; text-decoration: none;
            font-weight: 700; font-size: 1.1rem; margin-top: 15px;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
            transition: all 0.3s ease;
        }
        .whatsapp-button:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5); }
        .whatsapp-button i { font-size: 1.3rem; }
        .login-link { margin-top: 30px; font-size: 0.95rem; }
        .login-link a { color: #00ff00; font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Logo Love Chat" class="logo">
        <h1>Cadastro em Análise!</h1>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($whatsapp_link, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="whatsapp-button">
            <i class="fab fa-whatsapp"></i> Falar no WhatsApp
        </a>
        <div class="login-link">
            <a href="login.php">Ir para a página de Login</a>
        </div>
    </div>
</body>
</html>