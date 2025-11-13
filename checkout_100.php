<?php
// Arquivo: checkout_template.php (Base v6 FINAL - Renomeie para cada pacote)

// --- [!!! ALTERAR AQUI PARA CADA PACOTE !!!] ---
$packageName = "Pacote Básico - 100 Clientes";
$leads = 100;
$price = 30.00;
$priceFormatted = "R$ 30,00";
$pixKey = "00020101021126580014br.gov.bcb.pix013673fae3c2-7c5a-457a-89af-f51a8a708ca4520400005303986540530.005802BR5913VILA FEMININA6009SAO PAULO622905251JRK4ST4D2ZVS3VBYX6Z3T15R630480F5"; // <-- SUBSTITUA SUA CHAVE PIX REAL
$qrCodeImagePath = "/img/qrcode/qr_code_pacote_50.png"; // <-- SUBSTITUA PELO CAMINHO REAL
$whatsappLink = "https://wa.me/5534998709969?text=Ol%C3%A1!%20Gostaria%20de%20pagar%20o%20Pacote%20Básico%20(R$ 30,00)%20com%20cart%C3%A3o."; // <-- SUBSTITUA SEUNUMERO
// --- [!!! FIM DAS ALTERAÇÕES OBRIGATÓRIAS !!!] ---

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'lifetime' => 86400]);
    session_start();
}
date_default_timezone_set('America/Sao_Paulo');
$logFileCheckout = __DIR__ . '/logs/checkout_page_errors.log';
if (!file_exists(dirname($logFileCheckout))) { @mkdir(dirname($logFileCheckout), 0755, true); }
ini_set('display_errors', 0); ini_set('log_errors', 1); ini_set('error_log', $logFileCheckout); error_reporting(E_ALL);
error_log("[" . date('Y-m-d H:i:s') . "] Checkout V6 ($packageName) Acessado por UserID: " . ($_SESSION['user_id'] ?? 'N/A'));

function escapeCheckout($str) { return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
    error_log("Checkout V6 ($packageName): Usuário NÃO logado. Redirecionando.");
    $_SESSION['checkout_error'] = "Faça login para continuar o pagamento.";
    header("Location: login.php?origin=checkout&package=$leads");
    exit();
}
$userId = (int)$_SESSION['user_id'];
$userNome = $_SESSION['user_nome'] ?? 'Usuário';

if (!defined('DEFAULT_AVATAR')) { define('DEFAULT_AVATAR', 'default.jpg'); }

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pagamento Seguro - <?= escapeCheckout($packageName) ?> - Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <style>
        :root {
            --primary: #ff007f; --primary-light: rgba(255, 0, 127, 0.1); --primary-dark: #d6006a;
            --secondary: #fc5cac; --dark: #1e1e1e; --darker: #121212; --darkest: #0a0a0a;
            --success: #00d16c; --success-dark: #00b359; --success-light: rgba(0, 230, 118, 0.1);
            --warning: #ffc107; --danger: #ff4d4d; --danger-light: rgba(255, 77, 77, 0.1);
            --info: #17a2b8; --gray: #2a2a2a; --gray-light: #3a3a3a; --gray-dark: #181818;
            --text-primary: rgba(255, 255, 255, 0.95); --text-secondary: rgba(255, 255, 255, 0.7); --text-tertiary: rgba(255, 255, 255, 0.5);
            --border-color: rgba(255, 255, 255, 0.15); --border-color-light: rgba(255, 255, 255, 0.1);
            --border-radius-md: 12px; --border-radius-lg: 18px;
            --shadow-color: rgba(0, 0, 0, 0.5); --shadow-color-light: rgba(0, 0, 0, 0.3);
            --primary-rgb: 255, 0, 127; --success-rgb: 0, 230, 118; --danger-rgb: 255, 77, 77;
            --whatsapp-green: #25D366; --whatsapp-green-dark: #128C7E;
            --space-sm: 8px; --space-md: 16px; --space-lg: 24px; --space-xl: 32px;
            --font-sans: 'Montserrat', sans-serif;
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            --gradient-success: linear-gradient(135deg, var(--success), var(--success-dark));
            --gradient-whatsapp: linear-gradient(135deg, var(--whatsapp-green), var(--whatsapp-green-dark));
            --gradient-whatsapp-hover: linear-gradient(135deg, var(--whatsapp-green-dark), var(--whatsapp-green));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; } html { scroll-behavior: smooth; font-size: 16px;}
        body {
            font-family: var(--font-sans); background-color: var(--darker) !important; color: var(--text-primary) !important;
            line-height: 1.6; display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 25px 15px;
            background-image: radial-gradient(circle at 5% 10%, rgba(var(--primary-rgb), 0.07) 0%, transparent 50%), radial-gradient(circle at 95% 90%, rgba(var(--primary-rgb), 0.05) 0%, transparent 40%);
            background-attachment: fixed;
        }
        .checkout-container { width: 100%; max-width: 480px; animation: fadeInPage 0.7s ease-out forwards; }
        @keyframes fadeInPage { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .checkout-card {
            background: var(--dark); border-radius: var(--border-radius-lg); padding: 2.5rem;
            box-shadow: 0 15px 40px -10px var(--shadow-color); border: 1px solid var(--border-color-light);
            position: relative; overflow: hidden; text-align: center;
        }
        .checkout-card::before { content: ''; position: absolute; top: -60%; left: -60%; width: 220%; height: 220%; background: radial-gradient(circle, rgba(var(--primary-rgb), 0.05) 0%, transparent 35%); animation: rotateGlow 18s linear infinite; pointer-events: none; z-index: 0; }
        @keyframes rotateGlow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .card-content { position: relative; z-index: 1; }
        .checkout-header { margin-bottom: 2rem; }
        .checkout-header img.logo { height: 55px; margin-bottom: var(--space-md); filter: drop-shadow(0 5px 10px rgba(0, 0, 0, 0.4)); }
        .checkout-header h1 { font-size: 1.8rem; font-weight: 800; color: var(--lighter); margin-bottom: var(--space-xs); letter-spacing: -0.5px; }
        .checkout-header p.price { font-size: 1.2rem; color: var(--text-primary); margin-bottom: 0; font-weight: 500; }
        .checkout-header p.price strong { color: var(--primary); font-weight: 700; font-size: 1.6em; display: block; margin-top: var(--space-xs);}
        .payment-option { margin-bottom: var(--space-xl); padding-bottom: var(--space-xl); border-bottom: 1px solid var(--border-color); }
        .payment-option:last-of-type { margin-bottom: var(--space-lg); border-bottom: none; padding-bottom: 0; }
        .payment-option h2 {
            font-size: 1.2rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--text-primary);
            display: flex; align-items: center; justify-content: center; gap: 0.6rem;
        }
        main#checkoutCardContent .card-content section.payment-option h2 img.pix-logo-in-title {
            height: 24px !important; width: auto !important; display: inline-block !important;
            vertical-align: -4px !important; margin: 0 6px 0 0 !important; padding: 0 !important;
            border: none !important; background: none !important; box-shadow: none !important;
            max-height: 24px !important; max-width: none !important; min-height: auto !important; min-width: auto !important;
        }
        .payment-option h2 i.fa-credit-card { color: var(--text-primary); font-size: 1.2em; margin-right: 0.2em; } /* Ícone Cartão Branco */
        .qr-code-wrapper { margin-bottom: var(--space-lg); }
        .qr-code-wrapper img { max-width: 190px; height: auto; margin: 0 auto; display: block; border: 6px solid #fff; border-radius: var(--border-radius-md); background-color: white; padding: 6px; box-shadow: 0 8px 20px var(--shadow-color-light); transition: transform 0.3s ease; }
        .qr-code-wrapper img:hover { transform: scale(1.05); }
        .qr-code-wrapper p.instruction { font-size: 0.9rem; color: var(--text-secondary); margin-top: var(--space-md); line-height: 1.5;}
        .pix-key-wrapper label { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: var(--space-sm); font-weight: 500; }
        .pix-key-input-group { display: flex; margin-bottom: var(--space-lg); }
        .pix-key-input-group input[type="text"] { flex-grow: 1; padding: 0.85rem 1.1rem; background-color: var(--gray); border: 1px solid var(--border-color); border-radius: var(--border-radius-md) 0 0 var(--border-radius-md); color: var(--text-primary); font-size: 1rem; text-align: center; overflow: hidden; text-overflow: ellipsis; border-right: none; font-family: 'Courier New', Courier, monospace; }
        .pix-key-input-group button { padding: 0.85rem 1.2rem; background: var(--primary); color: white; border: 1px solid var(--primary); border-radius: 0 var(--border-radius-md) var(--border-radius-md) 0; font-weight: 600; cursor: pointer; transition: all 0.2s ease; font-size: 0.9rem; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem; }
        .pix-key-input-group button:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .pix-key-input-group button.copied { background-color: var(--success); border-color: var(--success); }
        .pix-key-input-group button.copied:hover { background-color: var(--success-dark); }
        .btn { padding: 1rem 1.5rem; border-radius: var(--border-radius-md); font-weight: 700; cursor: pointer; transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1.1); border: none; display: flex; align-items: center; justify-content: center; gap: 0.7rem; font-size: 1rem; text-decoration: none; width: 100%; margin-top: 0.5rem; text-transform: uppercase; letter-spacing: 0.8px; box-shadow: 0 5px 15px rgba(0,0,0, 0.2); position: relative; overflow: hidden; }
        .btn i { line-height: 1; font-size: 1.1em; }
        .btn:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 8px 20px rgba(0,0,0, 0.3); }
        .btn:active { transform: translateY(0) scale(1); }
        .btn:disabled { background: var(--gray) !important; color: var(--text-tertiary) !important; cursor: not-allowed; box-shadow: none; transform: none; }
        .btn:disabled i.fa-spinner { animation: spin 1.5s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .btn-success { background: var(--gradient-success); color: white; box-shadow: 0 5px 15px rgba(var(--success-rgb), 0.3); }
        .btn-success:hover { background: linear-gradient(135deg, var(--success-dark), var(--success)); }
        .btn-whatsapp { background: var(--gradient-whatsapp); color: white; box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3); }
        .btn-whatsapp:hover { background: var(--gradient-whatsapp-hover); }
        .btn-secondary { background: var(--gray-light); color: var(--text-secondary); box-shadow: none; margin-top: var(--space-xl) !important; font-size: 0.9rem; padding: 0.8rem 1.5rem; text-transform: none; letter-spacing: 0; }
        .btn-secondary:hover { background: var(--gray); color: var(--text-primary); }
        #payment-status { margin-top: var(--space-xl); padding: 1.3rem; border-radius: var(--border-radius-md); display: none; animation: fadeInStatus 0.5s ease forwards; text-align: center; font-weight: 600; font-size: 1.05rem; border: 1px solid transparent; border-left-width: 5px; }
        @keyframes fadeInStatus { from { opacity: 0; transform: translateY(10px);} to { opacity: 1; transform: translateY(0px);} }
        #payment-status.success { background-color: var(--success-light); color: var(--success); border-color: var(--success); display: block; }
        #payment-status.error { background-color: var(--danger-light); color: var(--danger); border-color: var(--danger); display: block; }
        #payment-status i { margin-right: var(--space-sm); font-size: 1.2em; vertical-align: -2px; }
        #toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 1100; display: flex; flex-direction: column; gap: var(--space-sm); }
        .toast-notification { background: var(--dark); color: var(--text-primary); padding: 1rem 1.5rem; border-radius: var(--border-radius-md); box-shadow: 0 6px 18px rgba(0,0,0,0.4); z-index: 1; opacity: 0; transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1.1); border: 1px solid var(--border-color); display: flex; align-items: center; gap: var(--space-md); transform: translateX(110%); min-width: 300px; border-left-width: 5px; }
        .toast-notification.show { opacity: 1; transform: translateX(0); }
        .toast-notification.error { background-color: rgba(var(--danger-rgb), 0.9); border-color: var(--danger); color: white; }
        .toast-notification.success { background-color: rgba(var(--success-rgb), 0.9); border-color: var(--success); color: white; }
        .toast-notification i { font-size: 1.2em;}
        /* .or-divider { display: none; } */ /* Removido completamente */
        @media (max-width: 576px) {
            body { padding: var(--space-md); align-items: center; }
            .checkout-container { max-width: 100%; }
            .checkout-card { padding: 2rem 1rem; }
            .checkout-header h1 { font-size: 1.5rem; } .checkout-header p.price { font-size: 1.1rem; }
            .payment-option { margin-bottom: 2rem; padding-bottom: 2rem; }
            .qr-code-wrapper img { max-width: 170px; }
            .pix-key-input-group { flex-direction: column; gap: var(--space-sm); }
            .pix-key-input-group input[type="text"] { border-radius: var(--border-radius-md); border: 1px solid var(--border-color); }
            .pix-key-input-group button { border-radius: var(--border-radius-md); width: 100%; justify-content: center; padding: 0.9rem; }
            .btn { font-size: 0.95rem; }
        }
    </style>
</head>
<body>

    <div class="checkout-container">
        <main class="checkout-card" id="checkoutCardContent">
            <div class="card-content">
                <header class="checkout-header">
                    <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat Logo" class="logo">
                    <h1><?= escapeCheckout($packageName) ?></h1>
                    <p class="price">Valor Total: <strong><?= escapeCheckout($priceFormatted) ?></strong></p>
                </header>

                <section class="payment-option">
                    <h2><img src="https://img.icons8.com/color/512/pix.png" alt="Pix" class="pix-logo-in-title"> Pagar com Pix</h2>
                    <div class="qr-code-wrapper">
                        <img src="<?= escapeCheckout($qrCodeImagePath) ?>" alt="QR Code Pix para <?= escapeCheckout($packageName) ?>" onerror="this.style.display='none'; this.nextElementSibling.textContent='Erro ao carregar QR Code. Use a chave abaixo.'">
                        <p class="instruction">1. Abra o app do seu banco e escaneie o QR Code.</p>
                    </div>
                    <div class="pix-key-wrapper">
                        <label for="pixKeyInput">2. Ou copie a Chave Pix (CNPJ):</label>
                        <div class="pix-key-input-group">
                            <input type="text" id="pixKeyInput" value="<?= escapeCheckout($pixKey) ?>" readonly>
                            <button id="copyPixKeyBtn" data-pix-key="<?= escapeCheckout($pixKey) ?>">
                                <i class="fas fa-copy"></i> Copiar Chave
                            </button>
                        </div>
                    </div>
                     <button id="confirmPixPaymentBtn" class="btn btn-success">
                        <i class="fas fa-check-double"></i> Já Paguei com Pix
                    </button>
                </section>

                <section class="payment-option">
                    <h2><i class="fas fa-credit-card"></i> Pagar com Cartão</h2>
                     <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--space-md);">
                        Prefere usar cartão? Chame no WhatsApp para gerar seu link de pagamento seguro.
                    </p>
                    <a href="<?= escapeCheckout($whatsappLink) ?>" target="_blank" class="btn btn-whatsapp">
                        <i class="fab fa-whatsapp"></i> Chamar no WhatsApp
                    </a>
                </section>

                 <div id="payment-status"></div>

                 <a href="dashboard.php" class="btn btn-secondary">
                     <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                 </a>
            </div>
        </main>
    </div>

     <div id="toast-container"></div>

    <script>
        function escapeHtml(text) { if (typeof text !== 'string') return text; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if(!container) { console.error("Toast container not found!"); return; }
            const toast = document.createElement('div');
            const isError = type === 'error'; const isSuccess = type === 'success';
            toast.className = `toast-notification ${isError ? 'error' : (isSuccess ? 'success' : '')}`;
            let iconClass = 'fa-info-circle';
            if(isSuccess) iconClass = 'fa-check-circle'; if(isError) iconClass = 'fa-times-circle';
            toast.innerHTML = `<i class="fas ${iconClass}"></i> <span>${escapeHtml(message)}</span>`;
            container.prepend(toast);
            requestAnimationFrame(() => { requestAnimationFrame(() => { toast.classList.add('show'); }); });
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => { if(toast.parentNode === container) toast.remove(); }, {once: true});
                setTimeout(() => { if(toast.parentNode === container) toast.remove(); }, 500);
            }, 4000);
        }
        if (typeof escapeHtml !== 'function') { function escapeHtml(text) { if (typeof text !== 'string') return text; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; } }

        document.addEventListener('DOMContentLoaded', () => {
            const copyButton = document.getElementById('copyPixKeyBtn');
            const pixKeyInput = document.getElementById('pixKeyInput');
            const confirmButton = document.getElementById('confirmPixPaymentBtn');
            const statusDiv = document.getElementById('payment-status');
            const checkoutCardContent = document.getElementById('checkoutCardContent')?.querySelector('.card-content');
            const backButton = document.querySelector('.checkout-card .btn-secondary');

            if (copyButton) {
                copyButton.addEventListener('click', function() {
                    const pixKey = this.dataset.pixKey;
                    if (!pixKey || !navigator.clipboard) { showToast(pixKey ? 'Cópia não suportada.' : 'Chave Pix não encontrada.', 'error'); return; }
                    navigator.clipboard.writeText(pixKey).then(() => {
                        showToast('Chave Pix copiada!', 'success');
                        this.innerHTML = '<i class="fas fa-check"></i> Copiado!'; this.classList.add('copied');
                        if(pixKeyInput) pixKeyInput.select();
                        setTimeout(() => { this.innerHTML = '<i class="fas fa-copy"></i> Copiar Chave'; this.classList.remove('copied'); }, 2500);
                    }).catch(err => { showToast('Falha ao copiar.', 'error'); });
                });
            }

           if (confirmButton && statusDiv && checkoutCardContent && backButton) {
               confirmButton.addEventListener('click', async function() {
                   this.disabled = true; this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aguarde...';
                   statusDiv.style.display = 'none'; statusDiv.className = 'payment-status';

                   const paymentData = {
                       userId: <?php echo json_encode($userId); ?>,
                       packageName: <?php echo json_encode($packageName); ?>,
                       leads: <?php echo json_encode($leads); ?>,
                       price: <?php echo json_encode($price); ?>,
                       paymentMethod: 'pix_reported'
                   };

                   try {
                       const response = await fetch('/api/notify_admin_pix.php', {
                           method: 'POST',
                           headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                           body: JSON.stringify(paymentData)
                       });
                       const result = await response.json();
                       if (!response.ok || !result.success) throw new Error(result.message || 'Erro ao notificar pagamento.');

                       statusDiv.className = 'payment-status success';
                       statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Sua solicitação foi recebida! Entraremos em contato via WhatsApp em breve para liberar seus leads.';
                       statusDiv.style.display = 'block';
                        Array.from(checkoutCardContent.children).forEach(child => { if (child && child !== statusDiv && child !== backButton) {
                               child.style.transition = 'opacity 0.3s ease, height 0.3s ease, margin 0.3s ease, padding 0.3s ease';
                               child.style.opacity = '0'; child.style.height = '0'; child.style.margin = '0'; child.style.padding = '0'; child.style.overflow = 'hidden';
                        } });
                        if(backButton) { backButton.style.display = 'inline-flex'; backButton.style.marginTop = 'var(--space-lg)'; backButton.style.opacity = '1';}
                         statusDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

                   } catch (error) {
                       console.error('Erro ao confirmar Pix:', error);
                       statusDiv.className = 'payment-status error';
                       statusDiv.innerHTML = `<i class="fas fa-times-circle"></i> Erro: ${escapeHtml(error.message)}. Tente novamente ou contate o suporte.`;
                       statusDiv.style.display = 'block';
                       this.disabled = false; this.innerHTML = '<i class="fas fa-check-double"></i> Já Realizei o Pagamento Pix';
                   }
               });
           } else { console.error("Elementos PIX não encontrados:", { confirmButton: !!confirmButton, statusDiv: !!statusDiv, checkoutCardContent: !!checkoutCardContent, backButton: !!backButton }); }
        });
    </script>
</body>
</html>