<?php
session_start(); // Ou inclua seu header que já faz isso

// 1. Autenticação
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar se JÁ viu o tutorial (evita loop)
require_once __DIR__ . '/includes/db.php'; // Inclua seu DB

$tutorialVisto = false; // Assume que não viu por padrão
if (!empty($_SESSION['user_id']) && isset($pdo)) { // Garante que user_id e pdo existem
    try {
        $stmtCheck = $pdo->prepare("SELECT tutorial_visto FROM usuarios WHERE id = ?");
        $stmtCheck->execute([$_SESSION['user_id']]);
        $vistoResult = $stmtCheck->fetchColumn();
        // fetchColumn retorna false se não encontrar linha ou coluna, ou NULL se o valor for NULL.
        // Precisamos tratar isso para considerar 0 como não visto.
        if ($vistoResult !== false && $vistoResult !== null) {
            $tutorialVisto = (bool)$vistoResult;
        } else {
             // Se não encontrou o usuário ou a coluna (improvável se o login funciona)
             // ou se o valor é NULL, vamos tratar como 'não visto' para segurança,
             // mas logar um aviso seria bom em um ambiente de produção.
             error_log("Aviso: Não foi possível obter 'tutorial_visto' para o usuário ID " . $_SESSION['user_id']);
             $tutorialVisto = false;
        }

    } catch (PDOException $e) {
        // Erro de banco de dados, redireciona para login ou dashboard com erro?
        // Por segurança, vamos assumir que viu e ir para o dashboard para não travar.
        error_log("Erro PDO ao verificar tutorial_visto para ID " . $_SESSION['user_id'] . ": " . $e->getMessage());
        header("Location: dashboard.php?dberror=tutcheck"); // Vai pro dashboard com um erro (opcional)
        exit();
    }
} else {
    // Se não tem user_id ou pdo, algo está muito errado, redireciona para login
    header("Location: login.php?error=session_pdo");
    exit();
}


if ($tutorialVisto) {
    header("Location: dashboard.php"); // Já viu, vai pro dashboard
    exit();
}

// Se chegou aqui, o usuário está logado e NÃO viu o tutorial ainda.
$userIdForJs = $_SESSION['user_id']; // Passa para JS
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Love Chat - Guia Rápido</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <style>
        /* === COPIE TODAS AS VARIÁVEIS CSS E ESTILOS DO TUTORIAL DO dashboard.php AQUI === */
        :root {
            --primary: #ff007f; --primary-light: rgba(255, 0, 127, 0.1); --secondary: #fc5cac;
            --dark: #1a1a1a; --darker: #121212; --darkest: #0a0a0a; --light: #e0e0e0;
            --lighter: #f5f5f5; --success: #00cc66; --warning: #ffcc00; --danger: #ff3333;
            --info: #0099ff; --gray: #2a2a2a; --gray-light: #3a3a3a; --gray-dark: #1e1e1e;
            --text-primary: rgba(255, 255, 255, 0.9); --text-secondary: rgba(255, 255, 255, 0.6);
            --primary-rgb: 255, 0, 127; --danger-rgb: 255, 51, 51; --warning-rgb: 255, 204, 0;
            --success-rgb: 0, 204, 102; --lime-green: #96ff00; --lime-green-rgb: 150, 255, 0;
            --border-color: rgba(255, 255, 255, 0.1); --border-color-light: rgba(255, 255, 255, 0.07);
            --border-color-medium: rgba(255, 255, 255, 0.15); --transition-speed-fast: 0.2s;
            --transition-speed-med: 0.4s; --transition-cubic: cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Montserrat', sans-serif; background-color: var(--darker);
            color: var(--text-primary); line-height: 1.6; display: flex;
            align-items: center; justify-content: center; min-height: 100vh; padding: 1rem;
        }

        /* Container Principal da Página (Adaptação do .tutorial-content) */
        .tutorial-container {
            background: linear-gradient(145deg, var(--darker), var(--darkest)); border-radius: 18px;
            width: 100%; max-width: 700px; /* Mantém largura máxima do modal */
            max-height: 90vh; /* Limita altura na tela */
            position: relative; display: flex; flex-direction: column;
            overflow: hidden; padding: 2rem 1.5rem; /* Padding do modal */
            border: 1px solid var(--border-color-medium);
            box-shadow: 0 15px 45px -10px rgba(0, 0, 0, 0.5);
            min-height: 0; /* Para flexbox */
        }

        /* --- INÍCIO DOS ESTILOS COPIADOS DO TUTORIAL DO dashboard.php --- */
        /* Copie EXATAMENTE todos os estilos que começam com .tutorial- ou relacionados */

        .close-tutorial-btn { /* Mantido do dashboard.php */
            position: absolute; top: 0.5rem; right: 0.5rem; background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15); color: var(--text-secondary, rgba(255, 255, 255, 0.7));
            font-size: 1.6rem; cursor: pointer; transition: all 0.3s var(--transition-cubic, cubic-bezier(0.175, 0.885, 0.32, 1.1));
            width: 2.8rem; height: 2.8rem; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; line-height: 1; z-index: 10; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        .close-tutorial-btn:hover { /* Mantido do dashboard.php */
             color: var(--primary, #ff007f); background: rgba(var(--primary-rgb, 255, 0, 127), 0.25); transform: rotate(180deg) scale(1.1); box-shadow: 0 0 15px rgba(var(--primary-rgb, 255, 0, 127), 0.5);
        }

        .tutorial-header { /* Mantido do dashboard.php */
            text-align: center; margin-bottom: 1.5rem; padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color-light, rgba(255, 255, 255, 0.15)); flex-shrink: 0;
            transition: opacity var(--transition-speed-fast, 0.2s) ease, max-height var(--transition-speed-med, 0.4s) ease, margin-bottom var(--transition-speed-med, 0.4s) ease, padding-bottom var(--transition-speed-med, 0.4s) ease, border var(--transition-speed-med, 0.4s) ease;
            opacity: 1; max-height: 250px; overflow: hidden; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        }
        .tutorial-header.hidden-header { /* Mantido do dashboard.php */
            opacity: 0; max-height: 0; margin-bottom: 0; padding-bottom: 0; border-bottom-color: transparent;
        }
        .tutorial-header i { /* Mantido do dashboard.php */
            font-size: 3rem; color: var(--primary, #ff007f); margin-bottom: 0.7rem; display: inline-block; animation: headerIconPulse 2.8s infinite ease-in-out;
        }
        .tutorial-title { /* Mantido do dashboard.php */
            font-size: 1.8rem; font-weight: 700; color: var(--lighter, #f5f5f5); margin: 0;
        }
        .tutorial-subtitle { /* Mantido do dashboard.php */
            font-size: 1.05rem; color: var(--text-secondary, rgba(255, 255, 255, 0.7)); margin-top: 0.6rem;
        }
        .tutorial-steps-container { /* Mantido do dashboard.php */
            flex: 1; position: relative; overflow-x: hidden; overflow-y: auto; min-height: 0; margin-bottom: 1.2rem;
            transition: min-height var(--transition-speed-med, 0.4s) var(--transition-cubic, cubic-bezier(0.175, 0.885, 0.32, 1.1));
            padding: 0 0.5rem 0 0; margin-right: -0.5rem; -webkit-overflow-scrolling: touch;
            scrollbar-width: thin; scrollbar-color: var(--primary, #ff007f) rgba(0, 0, 0, 0.1);
            height: 100%; box-sizing: border-box; -webkit-transform: translateZ(0); transform: translateZ(0);
        }
        .tutorial-steps-container::-webkit-scrollbar { /* Mantido do dashboard.php */
             width: 6px;
        }
        .tutorial-steps-container::-webkit-scrollbar-track { /* Mantido do dashboard.php */
            background: rgba(0, 0, 0, 0.1); border-radius: 3px;
        }
        .tutorial-steps-container::-webkit-scrollbar-thumb { /* Mantido do dashboard.php */
             background-color: var(--primary, #ff007f); border-radius: 3px;
        }
        .tutorial-steps-container::-webkit-scrollbar-thumb:hover { /* Mantido do dashboard.php */
             background-color: var(--secondary, #fc5cac);
        }
        /* Indicator scroll (opcional, pode remover se não usar a classe .is-scrollable) */
        .tutorial-steps-container.is-scrollable::after { /* Mantido do dashboard.php */
            content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; bottom: 8px; left: 50%;
            transform: translateX(-50%); font-size: 1.3rem; color: var(--primary, #ff007f); opacity: 0;
            animation: bounce-fade-indicator 2.5s infinite ease-in-out; pointer-events: none;
            text-shadow: 0 0 10px rgba(var(--primary-rgb, 255, 0, 127), 0.6); z-index: 5;
        }
        .tutorial-step { /* Mantido do dashboard.php */
            position: absolute; top: 0; left: 0; width: 100%; height: auto; padding: 1rem 0.5rem 1rem 0.5rem;
            opacity: 0; visibility: hidden; transform: translateY(20px);
            transition: opacity var(--transition-speed-med, 0.4s) var(--transition-cubic, cubic-bezier(0.175, 0.885, 0.32, 1.1)), transform var(--transition-speed-med, 0.4s) var(--transition-cubic, cubic-bezier(0.175, 0.885, 0.32, 1.1)), visibility 0s linear var(--transition-speed-med, 0.4s);
            will-change: opacity, transform;
        }
        .tutorial-step.active { /* Mantido do dashboard.php */
             opacity: 1; visibility: visible; transform: translateY(0); transition-delay: 0s; position: relative;
        }
        .tutorial-step-item { /* Mantido do dashboard.php */
            display: flex; align-items: flex-start; gap: 1.5rem; margin-bottom: 1.5rem; padding: 0;
        }
        .tutorial-step .tutorial-step-item:last-child { /* Mantido do dashboard.php */
             margin-bottom: 0.5rem;
        }
        .tutorial-step-icon-wrapper { /* Mantido do dashboard.php */
            flex-shrink: 0; width: 3.2rem; height: 3.2rem; border-radius: 14px;
            background: linear-gradient(145deg, rgba(var(--primary-rgb, 255, 0, 127), 0.18), rgba(var(--primary-rgb, 255, 0, 127), 0.3));
            display: flex; align-items: center; justify-content: center; margin-top: 0.1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.35); animation: subtleIconFloat 2.2s infinite ease-in-out alternate;
            transition: transform 0.4s ease;
        }
        .tutorial-step-item:nth-child(odd) .tutorial-step-icon-wrapper { /* Mantido do dashboard.php */
             animation-delay: 0.3s;
        }
        .tutorial-step-item:hover .tutorial-step-icon-wrapper { /* Mantido do dashboard.php */
             transform: scale(1.1) rotate(-5deg);
        }
        .tutorial-step-icon-wrapper i { /* Mantido do dashboard.php */
             font-size: 1.5rem; color: var(--primary, #ff007f); text-shadow: 0 0 12px rgba(var(--primary-rgb, 255, 0, 127), 0.6);
        }
        .tutorial-step-text { /* Mantido do dashboard.php */
             flex: 1; min-width: 0;
        }
        .tutorial-step-title { /* Mantido do dashboard.php */
            font-size: 1.25rem; font-weight: 700; color: var(--lighter, #f5f5f5); margin: 0 0 0.6rem 0; line-height: 1.4; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        }
        .tutorial-step-content { /* Mantido do dashboard.php */
            font-size: 0.98rem; color: var(--text-secondary, rgba(255, 255, 255, 0.7)); line-height: 1.7; margin: 0; word-break: break-word; hyphens: auto; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        /* Estilos para strong dentro do tutorial */
        .tutorial-step-content strong, .tutorial-option-list li strong { /* Mantido do dashboard.php */
            font-weight: 600; background-color: transparent; padding: 0; border-radius: 0; box-shadow: none; display: inline; margin: 0; transition: color 0.3s ease;
        }
        .tutorial-step-content strong:not(.highlight-alt):not(.action-required):not(.pix-cnpj):not(.option-title-strong),
        .tutorial-option-list li strong:not(.highlight-alt):not(.action-required):not(.pix-cnpj):not(.option-title-strong) { /* Mantido do dashboard.php */
            color: var(--lighter, #f5f5f5); font-weight: 700;
        }
        .tutorial-step-content strong.highlight-alt:not(.action-required):not(.pix-cnpj):not(.option-title-strong),
        .tutorial-option-list li strong.highlight-alt:not(.action-required):not(.pix-cnpj):not(.option-title-strong) { /* Mantido do dashboard.php */
            font-weight: 700; background: linear-gradient(to right, var(--primary, #ff007f), var(--secondary, #fc5cac) 120%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 8px rgba(var(--primary-rgb, 255, 0, 127), 0.35);
        }
        .tutorial-step-content strong.action-required { /* Mantido do dashboard.php */
            color: var(--warning, #ffcc00); font-weight: 700;
        }
        /* Esconder ícone nos passos 6 e 7 */
        .tutorial-step[data-step="6"] .tutorial-step-icon-wrapper,
        .tutorial-step[data-step="7"] .tutorial-step-icon-wrapper { /* Mantido do dashboard.php */
             display: none;
        }
        .tutorial-step[data-step="6"] .tutorial-step-item,
        .tutorial-step[data-step="7"] .tutorial-step-item { /* Mantido do dashboard.php */
            gap: 0;
        }
        .tutorial-step[data-step="6"] .tutorial-step-text,
        .tutorial-step[data-step="7"] .tutorial-step-text { /* Mantido do dashboard.php */
             padding-left: 0;
        }
        /* Container das opções (Passos 6 e 7) */
        .tutorial-option-container { /* Mantido do dashboard.php */
            background: rgba(255, 255, 255, 0.03); border-radius: 12px; padding: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 1rem;
        }
        .tutorial-step-option-title { /* Mantido do dashboard.php */
            font-size: 1.3rem; font-weight: 700; color: var(--text-primary, rgba(255, 255, 255, 0.9)); margin: 0 0 1rem 0; line-height: 1.4; padding-bottom: 0.6rem; border-bottom: 2px solid rgba(var(--primary-rgb, 255, 0, 127), 0.6); display: block;
        }
        .tutorial-step-option-title strong.option-title-strong { /* Mantido do dashboard.php */
            color: var(--primary, #ff007f); font-weight: 700; background: none; -webkit-background-clip: unset; background-clip: unset; -webkit-text-fill-color: unset; text-shadow: none;
        }
        .tutorial-option-list { /* Mantido do dashboard.php */
            margin-top: 1.2rem; font-size: 0.95rem; line-height: 1.75; list-style: none; padding-left: 0;
        }
        .tutorial-option-list li { /* Mantido do dashboard.php */
            padding-left: 1.8rem; position: relative; margin-bottom: 1rem; border-left: 3px solid transparent; padding-top: 0.1rem; padding-bottom: 0.1rem; transition: border-color 0.3s ease;
        }
        .tutorial-option-list li::before { /* Mantido do dashboard.php */
            content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 70%; background: linear-gradient(to bottom, rgba(var(--primary-rgb, 255, 0, 127), 0.3), rgba(var(--primary-rgb, 255, 0, 127), 0.7)); border-radius: 1.5px; transition: all 0.3s ease; opacity: 0.8;
        }
        .tutorial-option-list li:hover::before { /* Mantido do dashboard.php */
            height: 90%; background: linear-gradient(to bottom, var(--primary, #ff007f), var(--secondary, #fc5cac)); box-shadow: 0 0 8px rgba(var(--primary-rgb, 255, 0, 127), 0.4); opacity: 1;
        }
        .tutorial-option-list li.attention::before { /* Mantido do dashboard.php */
             background: linear-gradient(to bottom, rgba(var(--warning-rgb, 255, 204, 0), 0.4), rgba(var(--warning-rgb, 255, 204, 0), 0.8));
        }
        .tutorial-option-list li.attention:hover::before { /* Mantido do dashboard.php */
             background: linear-gradient(to bottom, var(--warning, #ffcc00), #ffae00); box-shadow: 0 0 8px rgba(var(--warning-rgb, 255, 204, 0), 0.5);
        }
        .pix-cnpj { /* Mantido do dashboard.php */
            font-weight: 600; color: var(--lime-green, #96ff00); background-color: rgba(var(--lime-green-rgb, 150, 255, 0), 0.15); padding: 3px 8px; border-radius: 6px; white-space: nowrap; border: 1px solid rgba(var(--lime-green-rgb, 150, 255, 0), 0.4); text-shadow: 0 0 5px rgba(var(--lime-green-rgb, 150, 255, 0), 0.2); display: inline-block; margin: 0 2px; box-shadow: none; font-family: 'Source Code Pro', 'Courier New', Courier, monospace; font-size: 0.95em; letter-spacing: 0.5px; transition: all 0.3s ease;
        }
        .pix-cnpj:hover { /* Mantido do dashboard.php */
             background-color: rgba(var(--lime-green-rgb, 150, 255, 0), 0.25); border-color: rgba(var(--lime-green-rgb, 150, 255, 0), 0.6); color: var(--lighter, #f5f5f5); box-shadow: 0 0 10px rgba(var(--lime-green-rgb, 150, 255, 0), 0.3);
        }
        .option-ref { /* Mantido do dashboard.php */
             font-size: 0.8em; color: var(--text-secondary); font-style: italic; vertical-align: super; margin: 0 2px;
        }
        .tutorial-progress { /* Mantido do dashboard.php */
            text-align: center; margin: 1rem 0; flex-shrink: 0;
        }
        .progress-dot { /* Mantido do dashboard.php */
            display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: rgba(255, 255, 255, 0.2); margin: 0 6px; cursor: pointer; transition: all 0.4s var(--transition-cubic, cubic-bezier(0.175, 0.885, 0.32, 1.275)); border: 1px solid rgba(255,255,255,0.1);
        }
        .progress-dot:hover { /* Mantido do dashboard.php */
            background-color: rgba(255, 255, 255, 0.4); transform: scale(1.2);
        }
        .progress-dot.active { /* Mantido do dashboard.php */
            background-color: var(--primary, #ff007f); transform: scale(1.4); box-shadow: 0 0 12px rgba(var(--primary-rgb, 255, 0, 127), 0.7); border-color: var(--primary, #ff007f);
        }
        .tutorial-nav { /* Mantido do dashboard.php */
            display: flex; justify-content: space-between; padding-top: 1rem; border-top: 1px solid var(--border-color-medium, rgba(255, 255, 255, 0.15)); flex-shrink: 0;
        }
        .tutorial-nav .btn { /* Mantido do dashboard.php */
            padding: 0.8rem 1.8rem; font-size: 0.95rem; min-width: 120px; text-align: center; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s var(--transition-cubic, cubic-bezier(0.175, 0.885, 0.32, 1.275)); border: none; position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; /* Added gap */
        }
        .tutorial-nav .btn i { /* Mantido do dashboard.php */
             vertical-align: middle; line-height: 1;
        }
        /* Ajuste específico para os ícones dos botões Prev/Next/Finish */
        .tutorial-nav .btn#tutorialPrev i { margin-right: 0.5rem; margin-left: -0.3rem; }
        .tutorial-nav .btn#tutorialNext i { margin-left: 0.5rem; margin-right: -0.3rem; }
        .tutorial-nav .btn#tutorialFinish i { margin-right: 0.5rem; }

        /* Estilos de botões gerais (copiados do dashboard.php) */
        .btn {
             padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer;
             transition: all 0.2s ease; border: none; display: inline-flex;
             align-items: center; justify-content: center; gap: 0.5rem;
             font-family: 'Montserrat', sans-serif;
        }
        .btn-primary { /* Mantido do dashboard.php */
             background: linear-gradient(135deg, var(--primary, #ff007f), var(--secondary, #fc5cac)); color: white; border: none; box-shadow: 0 5px 18px rgba(var(--primary-rgb, 255, 0, 127), 0.3);
        }
        .btn-primary:hover { /* Mantido do dashboard.php */
            background: linear-gradient(135deg, #e0006f, #fc5cac); transform: translateY(-2px) scale(1.03); box-shadow: 0 7px 22px rgba(var(--primary-rgb, 255, 0, 127), 0.45);
        }
        .btn-secondary { /* Mantido do dashboard.php */
             background-color: rgba(255, 255, 255, 0.1); color: var(--text-primary, rgba(255, 255, 255, 0.9)); border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .btn-secondary:hover { /* Mantido do dashboard.php */
            background-color: rgba(255, 255, 255, 0.2); transform: translateY(-2px) scale(1.03); box-shadow: 0 5px 10px rgba(0, 0, 0, 0.25);
        }
        .tutorial-nav .btn:active { /* Mantido do dashboard.php */
             transform: translateY(0px) scale(0.97); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Keyframes copiados */
        @keyframes modalScaleIn { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
        @keyframes stepFadeSlideIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes headerIconPulse { 0%, 100% { transform: scale(1); opacity: 0.9; } 50% { transform: scale(1.1); opacity: 1; } }
        @keyframes subtleIconFloat { from { transform: translateY(-1.5px) rotate(-1.5deg); } to { transform: translateY(1.5px) rotate(1.5deg); } }
        @keyframes bounce-fade-indicator { 0%, 100% { opacity: 0; transform: translateX(-50%) translateY(0); } 50% { opacity: 0.8; transform: translateX(-50%) translateY(5px); } 75% { opacity: 0.8; transform: translateX(-50%) translateY(5px); } }

        /* Estilos de Toast Notification (copiados do dashboard.php) */
        .toast-notification {
           position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
           background: var(--dark); color: white; padding: 12px 20px; border-radius: 8px;
           box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); z-index: 9999; opacity: 0;
           transition: opacity 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.1);
           display: flex; align-items: center; gap: 10px;
        }
        .toast-notification.show { opacity: 1; }
        .toast-notification.error { background: rgba(var(--danger-rgb), 0.2); border-color: rgba(var(--danger-rgb), 0.3); color: var(--danger);}
        .toast-notification.success { background: rgba(var(--success-rgb), 0.15); border-color: rgba(var(--success-rgb), 0.3); color: var(--success);}
        .toast-notification i { font-size: 1.1em; }


        /* Responsividade específica do tutorial (copiada/adaptada do dashboard.php) */
        @media (max-width: 576px) {
            .tutorial-container { padding: 1.2rem 0.8rem; max-height: 95vh; } /* Ajuste para telas menores */
            .tutorial-steps-container { padding-right: 0.3rem; margin-right: -0.3rem; }
            .close-tutorial-btn { top: 0.5rem; right: 0.5rem; width: 2.5rem; height: 2.5rem; font-size: 1.5rem; }
            .tutorial-header { margin-bottom: 1rem; padding-bottom: 1rem; } .tutorial-header i { font-size: 2.5rem; }
            .tutorial-title { font-size: 1.4rem; } .tutorial-subtitle { font-size: 0.85rem; }
            .tutorial-step-title { font-size: 1.05rem; } .tutorial-step-option-title { font-size: 1.1rem; }
            .tutorial-step-content { font-size: 0.88rem; } .tutorial-option-list { font-size: 0.85rem; }
            .tutorial-nav { flex-direction: column; gap: 0.6rem; align-items: stretch; }
            .tutorial-nav .btn { width: 100%; padding: 0.8rem; }
            .tutorial-progress { margin: 0.8rem 0; }
            .tutorial-step-item { flex-direction: column; gap: 0.8rem; align-items: center; text-align: center;}
            .tutorial-step-icon-wrapper { margin-top: 0; margin-bottom: 0.5rem;}
        }

        /* --- FIM DOS ESTILOS COPIADOS DO TUTORIAL DO dashboard.php --- */

    </style>
</head>
<body>
    <!-- O container principal da página agora é o conteúdo do tutorial -->
    <div class="tutorial-container">
        <!-- Botão Pular/Fechar -->
        <button class="close-tutorial-btn" id="tutorialSkipBtn" title="Pular Tutorial e ir para Dashboard">×</button>

        <!-- COPIE EXATAMENTE A ESTRUTURA HTML INTERNA DO TUTORIAL DO DASHBOARD AQUI -->

        <!-- HEADER - JS will hide this after step 1 -->
        <div class="tutorial-header" id="tutorialHeader">
            <i class="fas fa-hand-holding-usd"></i>
            <h2 class="tutorial-title">Seu Guia Rápido para Lucrar!</h2>
            <p class="tutorial-subtitle">Entenda os passos essenciais</p>
        </div>

        <div class="tutorial-steps-container"> <!-- Scrollable Area -->

            <!-- Step 1: WhatsApp Business -->
            <div class="tutorial-step" data-step="1">
                 <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fab fa-whatsapp"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 1: Ative seu WhatsApp</h4>
                        <p class="tutorial-step-content">
                            Primeiro, seu canal de comunicação! Clique no card <strong>WhatsApp</strong> (Dashboard) e solicite seu número comercial <strong class="highlight-alt">gratuito</strong>. Essencial para receber e falar com seus contatos.
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-check"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Aguarde a Ativação</h4>
                        <p class="tutorial-step-content">
                            Nossa equipe te contatará (pelo número do seu <strong>cadastro</strong>) para finalizar a ativação. Fique de olho!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 2: Escolher Modelo IA -->
            <div class="tutorial-step" data-step="2">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-robot"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 2: Sua Identidade Visual</h4>
                        <p class="tutorial-step-content">
                            Vá em <strong>Escolher Modelo</strong> e selecione a aparência IA. A escolha é <strong class="action-required">permanente</strong> e libera seus <strong class="highlight-alt">materiais</strong> de divulgação.
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-photo-video"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Seus Materiais</h4>
                        <p class="tutorial-step-content">
                            Após escolher, acesse <strong>Minha Modelo</strong> para baixar fotos, vídeos e áudios que te ajudarão no perfil e conversas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 3: Configurar/Verificar Perfil e PIX Pessoal -->
            <div class="tutorial-step" data-step="3">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-user-edit"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 3: Ajuste seu Perfil</h4>
                        <p class="tutorial-step-content">
                            Visite <strong>Editar Perfil</strong> para: <br>
                            • Alterar Foto e Nome/Nickname. <br>
                            • <strong class="highlight-alt">Verificar ou Cadastrar SUA Chave PIX</strong>.
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-key"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Sua Chave PIX de Recebimento</h4>
                        <p class="tutorial-step-content">
                           Garanta que <strong class="highlight-alt">SUA</strong> chave PIX está correta no perfil (se não informou no cadastro). É nela que <strong>nós pagaremos você</strong>, caso use nosso sistema de recebimento <span class="option-ref">(detalhes no Passo 6)</span>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 4: Solicitar Contatos de Clientes -->
            <div class="tutorial-step" data-step="4">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-user-plus"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 4: Solicite Contatos</h4>
                        <p class="tutorial-step-content">
                            Pronto(a) para conversar? Em <strong>Solicitar Clientes</strong>, indique quantos contatos deseja e siga as instruções. Eles chegarão no seu <strong class="highlight-alt">WhatsApp</strong>.
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-comments-dollar"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Transforme Contatos em Vendas</h4>
                        <p class="tutorial-step-content">
                            Engaje esses contatos, use seus materiais e habilidades para realizar <strong>vendas</strong> e gerar seu <strong class="highlight-alt">lucro!</strong>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 5: Introdução às Opções de Recebimento PIX -->
            <div class="tutorial-step" data-step="5">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-exchange-alt"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 5: Recebendo Pagamentos PIX</h4>
                        <p class="tutorial-step-content">
                           Ao receber PIX de um cliente, você tem <strong>duas opções</strong>. Pense no que é mais importante para você em cada venda: <strong class="highlight-alt">anonimato</strong> ou <strong >lucro total direto</strong>?
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-question-circle"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Veja as Opções</h4>
                        <p class="tutorial-step-content">
                           Os próximos passos detalham como cada opção funciona, suas <strong>vantagens</strong> e o que você <strong class="highlight-alt">precisa fazer</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 6: Detalhe Opção A (PIX da Plataforma) - Elegant Container -->
            <div class="tutorial-step" data-step="6">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-text">
                        <div class="tutorial-option-container">
                            <h6 class="tutorial-step-option-title"><strong class="option-title-strong">Opção A:</strong> Usar PIX da Plataforma (Anônimo)</h6>
                            <p class="tutorial-step-content" style="margin-bottom: 1.2rem; font-size: 0.98rem;">
                                Escolha esta opção para garantir <strong>100% de anonimato</strong>. Seus dados pessoais nunca serão vistos pelo cliente. Ideal para quem preza pela privacidade acima de tudo.
                            </p>
                            <ul class="tutorial-option-list">
                                <li><strong>Como Fazer:</strong> Informe ao cliente que o PIX para pagamento é o nosso CNPJ: <strong class="pix-cnpj">47.401.064/0001-68</strong>.</li>
                                <li><strong>Ação Essencial Pós-Pagamento:</strong> <strong class="action-required">Solicite o comprovante</strong> ao cliente e <strong class="action-required">envie-o imediatamente</strong> através da aba "Comprovantes" aqui na plataforma. <strong class="highlight-alt">Este passo é crucial!</strong></li>
                                <li><strong>Processamento e Saldo:</strong> Após recebermos seu comprovante, validamos o pagamento. Calculamos <strong class="highlight-alt">90% do valor como seu ganho</strong> e o adicionamos ao seu "Saldo de Hoje" visível no Dashboard.</li>
                                <li><strong>Taxa de Serviço:</strong> Os 10% retidos são a taxa que cobre os custos da transação segura, o <strong class="highlight-alt">serviço de intermediação</strong> e a garantia total do seu <strong>anonimato</strong>.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 7: Detalhe Opção B (Seu PIX Pessoal) - Elegant Container -->
            <div class="tutorial-step" data-step="7">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-text">
                        <div class="tutorial-option-container">
                            <h6 class="tutorial-step-option-title"><strong class="option-title-strong">Opção B:</strong> Usar SEU PIX Pessoal (100% Lucro)</h6>
                            <p class="tutorial-step-content" style="margin-bottom: 1.2rem; font-size: 0.98rem;">
                                Escolha esta opção se deseja receber o <strong>valor total da venda</strong> diretamente e está confortável com a <strong class="highlight-alt">possível exposição</strong> do seu nome.
                            </p>
                            <ul class="tutorial-option-list">
                                <li><strong>Como Fazer:</strong> Informe a <strong>sua chave PIX pessoal</strong> (CPF, celular, e-mail, ou a chave aleatória cadastrada no seu perfil) diretamente ao cliente.</li>
                                <li><strong>Recebimento:</strong> O dinheiro entra <strong>instantaneamente na sua conta</strong> bancária pessoal, sem nossa intervenção.</li>
                                <li><strong>Seu Ganho:</strong> <strong>100% do valor</strong> é seu. Não há taxas da plataforma sobre esta transação.</li>
                                <li class="attention"><strong>Ponto de Atenção CRÍTICO:</strong> Ao realizar o PIX, o cliente <strong class="action-required">verá o nome completo</strong> vinculado à sua chave PIX. Considere isso ao escolher esta opção.</li>
                                <li><strong>Comprovantes:</strong> <strong class="highlight-alt">Não</strong> é necessário enviar comprovantes para nós sobre estes recebimentos diretos.</li>
                                <li><strong>Saldo na Plataforma:</strong> Seu "Saldo de Hoje" aqui no Dashboard <strong>não</strong> será afetado por vendas recebidas via Opção B.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 8: SOLICITAR e Acompanhar Chamadas de Vídeo -->
            <div class="tutorial-step" data-step="8">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-video"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 8: Vendeu uma Chamada?</h4>
                        <p class="tutorial-step-content">
                            Excelente! Acesse <strong>Realizar Chamada</strong> para nos informar e <strong class="highlight-alt">solicitar a execução</strong>.
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-paper-plane"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Solicite a Execução</h4>
                        <p class="tutorial-step-content">
                            Preencha os dados (número, duração) e <strong class="action-required">detalhe TUDO nas observações</strong> (preferências, o que foi combinado). Isso é <strong>crucial</strong> para nós! Clicar em "Solicitar" nos avisa.
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-tasks"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Acompanhe o Status</h4>
                        <p class="tutorial-step-content">
                           Veja o andamento (<strong>Pendente</strong>, <strong class="highlight-alt">Em Andamento</strong>, <strong>Concluída</strong>) clicando no card <strong>Chamadas Agendadas</strong> no Dashboard.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 9: Comprovantes e Saldo (Relevante para Opção A do PIX) -->
            <div class="tutorial-step" data-step="9">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 9: Comprovantes <span class="option-ref">(Opção A)</span></h4>
                        <p class="tutorial-step-content">
                           Se usou <strong>nosso PIX</strong> <span class="option-ref">(Opção A)</span>, <strong class="action-required">NÃO ESQUEÇA</strong> de enviar o comprovante na aba "Comprovantes". É a <strong class="highlight-alt">única forma</strong> de seu saldo ser creditado!
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-calculator"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Seu Saldo na Plataforma</h4>
                        <p class="tutorial-step-content">
                           Cada comprovante <span class="option-ref">(Opção A)</span> atualiza seu <strong>Saldo de Hoje</strong> (Dashboard) com <strong class="highlight-alt">90% do valor</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 10: Recebendo SEU Pagamento da Plataforma -->
            <div class="tutorial-step" data-step="10">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-calendar-check"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Passo 10: Pagamento do Seu Saldo <span class="option-ref">(Opção A)</span></h4>
                        <p class="tutorial-step-content">
                            O <strong>Saldo de Hoje</strong> (acumulado via <span class="option-ref">Opção A</span>) é fechado à <strong class="highlight-alt">meia-noite (00:00)</strong>.
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-money-check-alt"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Recebimento na SUA Chave PIX</h4>
                        <p class="tutorial-step-content">
                            Pagamos o saldo do <strong>dia anterior</strong> na <strong class="highlight-alt">SUA chave PIX</strong> (do seu Perfil).
                            <br><strong>Quando?</strong> Diariamente, entre <strong class="highlight-alt">00:00 e 12:00</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 11: Finalização -->
            <div class="tutorial-step" data-step="11">
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-check-double"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Pronto para Começar!</h4>
                        <p class="tutorial-step-content">
                            Você aprendeu o essencial! Configure, escolha como receber, solicite contatos e chamadas, e comece a vender!
                        </p>
                    </div>
                </div>
                <div class="tutorial-step-item">
                    <div class="tutorial-step-icon-wrapper"><i class="fas fa-headset"></i></div>
                    <div class="tutorial-step-text">
                        <h4 class="tutorial-step-title">Dúvidas?</h4>
                        <p class="tutorial-step-content">
                            Explore a plataforma e fale com o <strong>suporte</strong> se precisar. Sucesso e <strong class="highlight-alt">boas vendas!</strong>
                        </p>
                    </div>
                </div>
            </div>

        </div> <!-- End Steps Container -->

        <!-- Progress Dots -->
        <div class="tutorial-progress" id="tutorialProgress">
            <!-- Dots added by JS -->
        </div>

        <!-- Navigation -->
        <div class="tutorial-nav">
            <button class="btn btn-secondary" id="tutorialPrev" style="visibility: hidden;"><i class="fas fa-arrow-left"></i> Anterior</button>
            <button class="btn btn-primary" id="tutorialNext">Próximo <i class="fas fa-arrow-right"></i></button>
            <!-- O botão Finish/Entendi agora chama a função de redirecionamento -->
            <button class="btn btn-primary" id="tutorialFinish" style="display: none;"><i class="fas fa-rocket"></i> Entendi!</button>
        </div>
        <!-- FIM DA ESTRUTURA HTML COPIADA -->

    </div><!-- Fim do .tutorial-container -->

    <script>
        // Funções utilitárias (escapeHtml, showToast - copiadas do dashboard.php)
        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') { // type can be 'info', 'success', 'error'
            const existingToast = document.querySelector('.toast-notification');
            if (existingToast) existingToast.remove();
            const toast = document.createElement('div');
            let iconClass = 'fas fa-info-circle';
            if (type === 'success') iconClass = 'fas fa-check-circle';
            if (type === 'error') iconClass = 'fas fa-exclamation-circle';

            toast.className = `toast-notification ${type}`; // Adiciona classe de tipo
            toast.innerHTML = `<i class="${iconClass}"></i> <span>${escapeHtml(message)}</span>`;
            document.body.appendChild(toast);
            toast.offsetHeight; // Force reflow
            setTimeout(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 5000); // Duração do toast
            }, 10);
        }

        // Lógica JavaScript do Tutorial (Copiada do dashboard.php e ADAPTADA)
        document.addEventListener('DOMContentLoaded', function() {
            // Pegar o ID do usuário passado pelo PHP
            const userId = <?= json_encode($userIdForJs ?? null) ?>;
            const API_BASE = '/api/'; // Ajuste se o diretório da API for diferente

            const tutorialContainer = document.querySelector('.tutorial-container');
            const tutorialHeader = document.getElementById('tutorialHeader');
            const tutorialSteps = tutorialContainer?.querySelectorAll('.tutorial-step');
            const tutorialProgress = document.getElementById('tutorialProgress');
            const prevButton = document.getElementById('tutorialPrev');
            const nextButton = document.getElementById('tutorialNext');
            const finishButton = document.getElementById('tutorialFinish'); // Botão "Entendi!"
            const skipButton = document.getElementById('tutorialSkipBtn'); // Botão "X" de Pular/Fechar
            let currentTutorialStep = 0;
            const totalTutorialSteps = tutorialSteps ? tutorialSteps.length : 0;

            function initTutorial() {
                if (!tutorialContainer || !tutorialSteps || totalTutorialSteps === 0) {
                    console.error("Elementos essenciais do tutorial não encontrados.");
                    // Se algo deu errado, melhor enviar para o dashboard para não travar
                    window.location.href = 'dashboard.php?error=tut_init';
                    return;
                }

                if (tutorialProgress) {
                    tutorialProgress.innerHTML = ''; // Limpa dots antigos
                    for (let i = 0; i < totalTutorialSteps; i++) {
                        const dot = document.createElement('span');
                        dot.classList.add('progress-dot');
                        dot.dataset.step = i;
                        dot.addEventListener('click', () => showTutorialStep(i));
                        tutorialProgress.appendChild(dot);
                    }
                }

                showTutorialStep(0); // Mostra o primeiro passo

                // Listeners dos botões de navegação
                if (nextButton) nextButton.addEventListener('click', () => {
                    if (currentTutorialStep < totalTutorialSteps - 1) {
                        showTutorialStep(currentTutorialStep + 1);
                    }
                });
                if (prevButton) prevButton.addEventListener('click', () => {
                    if (currentTutorialStep > 0) {
                        showTutorialStep(currentTutorialStep - 1);
                    }
                });

                // --- Ações Finais (Ir para o Dashboard) ---
                // Botão "Entendi!" (Finalizar)
                if (finishButton) finishButton.addEventListener('click', () => goToDashboard(true)); // Marca como visto e redireciona

                // Botão "X" (Pular/Fechar)
                if (skipButton) skipButton.addEventListener('click', () => goToDashboard(true)); // Marca como visto e redireciona

                // Tecla Escape também finaliza
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        goToDashboard(true); // Marca como visto e redireciona
                    }
                });
            }

            function showTutorialStep(index) {
                 if (!tutorialSteps || index < 0 || index >= totalTutorialSteps) return;

                 // Esconde/mostra o header se não for o primeiro passo
                 if (tutorialHeader) tutorialHeader.classList.toggle('hidden-header', index !== 0);

                 // Ativa o passo correto
                 tutorialSteps.forEach((step, i) => {
                     const isActive = (i === index);
                     step.classList.toggle('active', isActive);

                      // Aplica destaques alternados SOMENTE ao passo ativo
                      if (isActive) {
                           // Seleciona elementos 'strong' que não são especiais
                           const strongElements = step.querySelectorAll(
                              '.tutorial-step-content strong:not(.highlight-alt):not(.action-required):not(.pix-cnpj):not(.option-title-strong), ' +
                              '.tutorial-option-list li strong:not(.highlight-alt):not(.action-required):not(.pix-cnpj):not(.option-title-strong)'
                           );
                           strongElements.forEach((strongEl, strongIndex) => {
                               strongEl.classList.remove('highlight-alt'); // Remove da iteração anterior
                               // Adiciona a classe 'highlight-alt' aos elementos ímpares (índice 1, 3, 5...)
                               if(strongIndex % 2 !== 0) { // índice ímpar
                                  strongEl.classList.add('highlight-alt');
                               }
                           });
                      }
                 });

                 currentTutorialStep = index;
                 updateTutorialNav(); // Atualiza botões Prev/Next/Finish
                 updateTutorialProgressDots(); // Atualiza bolinhas de progresso

                 // Scroll para o topo do container de passos (útil em mobile)
                 document.querySelector('.tutorial-steps-container')?.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function updateTutorialNav() {
                 if (!prevButton || !nextButton || !finishButton) return;
                 prevButton.style.visibility = currentTutorialStep === 0 ? 'hidden' : 'visible';
                 nextButton.style.display = currentTutorialStep === totalTutorialSteps - 1 ? 'none' : 'inline-flex';
                 finishButton.style.display = currentTutorialStep === totalTutorialSteps - 1 ? 'inline-flex' : 'none';
            }

            function updateTutorialProgressDots() {
                 if (!tutorialProgress) return;
                 tutorialProgress.querySelectorAll('.progress-dot').forEach((dot, i) => {
                     dot.classList.toggle('active', i === currentTutorialStep);
                 });
            }

            // Função para marcar tutorial como visto (via API) e redirecionar
            async function goToDashboard(markAsSeen = false) {
                console.log("goToDashboard chamado. markAsSeen:", markAsSeen);

                if (markAsSeen && userId) {
                    // Desabilita botões para evitar clique duplo
                    if (finishButton) finishButton.disabled = true;
                    if (skipButton) skipButton.disabled = true;
                    showToast("Salvando e redirecionando...", 'info'); // Feedback visual

                    try {
                        console.log("Enviando requisição para marcar tutorial como visto...");
                        const response = await fetch(`${API_BASE}mark_tutorial_seen.php`, {
                             method: 'POST',
                             // Não precisa de body se o user_id for pego da sessão no backend
                             headers: { 'Content-Type': 'application/json' } // Indica que não estamos enviando dados complexos
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            console.warn("Falha ao marcar tutorial como visto no backend:", data.message || `Status ${response.status}`);
                            // Mesmo se falhar, tenta redirecionar, mas avisa
                            showToast("Erro ao salvar progresso, mas redirecionando...", 'error');
                        } else {
                            console.log("Tutorial marcado como visto com sucesso no backend.");
                            // Talvez um toast de sucesso rápido antes de redirecionar
                            // showToast("Progresso salvo!", 'success');
                        }
                    } catch (error) {
                        console.error("Erro na requisição para marcar tutorial:", error);
                        showToast("Erro de conexão ao salvar, mas redirecionando...", 'error');
                    } finally {
                        // Redireciona SEMPRE após a tentativa de marcar
                        console.log("Redirecionando para dashboard.php...");
                        // Pequeno delay para o toast ser visível (opcional)
                        setTimeout(() => {
                           window.location.href = 'dashboard.php';
                        }, 300);
                    }
                } else {
                    // Se não for para marcar (ou não tem user id), apenas redireciona
                    console.log("Redirecionando para dashboard.php (sem marcar como visto)...");
                    window.location.href = 'dashboard.php';
                }
            }

            // Inicia o tutorial assim que o DOM estiver pronto
            initTutorial();
        });
    </script>
</body>
</html>