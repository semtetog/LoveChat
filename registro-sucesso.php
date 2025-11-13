<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cadastro Concluído - Love Chat</title>
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
        
        .success-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            justify-content: center;
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .success-header {
            margin-bottom: 40px;
        }
        
        .success-logo {
            width: 140px;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 10px rgba(255,0,127,0.5));
        }
        
        .success-title {
            font-weight: 700;
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            line-height: 1.3;
            margin-bottom: 15px;
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
        
        .success-container {
            background: rgba(40, 40, 40, 0.8);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 0, 127, 0.3);
            backdrop-filter: blur(5px);
        }
        
        .success-icon {
            color: #00ff00;
            font-size: 60px;
            margin-bottom: 25px;
            animation: bounce 1s ease infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0) scale(1); }
            to { transform: translateY(-10px) scale(1.05); }
        }
        
        .success-message {
            font-size: 1.1rem;
            color: #e0e0e0;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .success-button {
            display: inline-block;
            background: linear-gradient(to bottom, #00cc00, #009900);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 16px 40px;
            border-radius: 50px;
            text-decoration: none;
            margin-top: 15px;
            box-shadow: 0 4px 15px rgba(0, 255, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .success-button::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.3s ease;
        }
        
        .success-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 255, 0, 0.4);
        }
        
        .success-button:hover::after {
            left: 100%;
        }
        
        .success-button i {
            margin-left: 8px;
            transition: all 0.3s ease;
        }
        
        .success-button:hover i {
            transform: translateX(5px);
        }
        
        .countdown {
            margin-top: 25px;
            font-size: 0.9rem;
            color: #aaa;
        }
        
        @media (max-width: 768px) {
            .success-section {
                padding: 30px 15px;
            }
            
            .success-container {
                padding: 30px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .success-container {
                padding: 25px 15px;
            }
            
            .success-logo {
                width: 120px;
            }
            
            .success-button {
                padding: 14px 30px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <section class="success-section">
        <div class="success-header">
            <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Logo Love Chat" class="success-logo">
            <h1 class="success-title">Cadastro <span class="highlight">Concluído!</span></h1>
        </div>
        
        <div class="success-container">
            <i class="fas fa-check-circle success-icon"></i>
            <p class="success-message">Seu cadastro foi realizado com sucesso! Agora você faz parte da comunidade Love Chat e pode começar a aproveitar todos os benefícios.</p>
            <a href="login.php" class="success-button">Ir para o Login <i class="fas fa-arrow-right"></i></a>
            <p class="countdown">Redirecionando em <span id="countdown">5</span> segundos...</p>
        </div>
    </section>

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
</body>
</html>