<?php
// checkout.php - v4.2 Híbrida (Fix Loading/State/Button Issues)

ini_set('display_errors', 1); error_reporting(E_ALL);
ini_set('log_errors', 1); ini_set('error_log', __DIR__ . '/php_checkout_errors.log');
session_start();

// --- Dados Pacote e Validação ---
$leads = filter_input(INPUT_GET, 'package', FILTER_VALIDATE_INT); // ID do Pacote
$price = filter_input(INPUT_GET, 'price', FILTER_VALIDATE_FLOAT);
$description = filter_input(INPUT_GET, 'description', FILTER_DEFAULT);

if ($leads === false || $price === false || $price <= 0 || empty($description)) {
    error_log("[Checkout V4.2 Custom] Erro: Parâmetros inválidos na URL (package, price, description).");
    $_SESSION['checkout_error'] = 'Informações do pacote inválidas na URL.';
    if (!headers_sent()) { header('Location: dashboard.php'); exit; }
    die('<div style="color:red; font-family: sans-serif; padding: 20px;">Erro: Informações do pacote inválidas. <a href="dashboard.php">Voltar</a>.</div>');
}

$safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
$priceFormatted = number_format($price, 2, ',', '.');
$mercadoPagoPublicKey = "APP_USR-2bba4c56-5133-4080-aabd-dee5ddaabeea"; // CONFIRME SUA PUBLIC KEY DE PRODUÇÃO OU TESTE
$userEmail = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email'], ENT_QUOTES, 'UTF-8') : '';

// --- Obter Nome/Sobrenome da Sessão (AJUSTE AS CHAVES!) ---
$userFirstName = isset($_SESSION['user_first_name']) ? htmlspecialchars($_SESSION['user_first_name'], ENT_QUOTES, 'UTF-8') : '';
$userLastName = isset($_SESSION['user_last_name']) ? htmlspecialchars($_SESSION['user_last_name'], ENT_QUOTES, 'UTF-8') : '';
if (empty($userFirstName) || empty($userLastName)) {
    error_log("[Checkout V4.2 Custom WARN] Nome/Sobrenome não encontrados na sessão (Chaves: 'user_first_name', 'user_last_name').");
}
// --- FIM Obter Nome/Sobrenome ---

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Love Chat - Finalizar Pagamento (Custom v4.2)</title>
    <!-- ***** DEVICE ID SCRIPT ***** -->
    <script src="https://www.mercadopago.com.br/v2/security.js" view="checkout"></script>
    <!-- ************************** -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
<style>
    /* --- CSS COMPLETO (Mantido como v4.1) --- */
    :root {
        --primary: #ff007f; --primary-dark: #e0006f; --primary-light: rgba(255, 0, 127, 0.08);
        --secondary: #fc5cac; --dark: #1a1a1a; --darker: #101010; --darkest: #080808;
        --light: #e8e8e8; --lighter: #f7f7f7; --success: #00d16c; --warning: #ffc107;
        --danger: #ff4d4d; --info: #17a2b8; --gray: #2c2c2c; --gray-light: #404040;
        --gray-dark: #222222; --text-primary: rgba(255, 255, 255, 0.98);
        --text-secondary: rgba(255, 255, 255, 0.65); --primary-rgb: 255, 0, 127;
        --danger-rgb: 255, 77, 77; --success-rgb: 0, 209, 108; --info-rgb: 23, 162, 184;
        --border-color: rgba(255, 255, 255, 0.12); --border-color-light: rgba(255, 255, 255, 0.08);
        --border-color-focus: rgba(var(--primary-rgb), 0.7); --shadow-color: rgba(0, 0, 0, 0.5);
        --shadow-color-light: rgba(0, 0, 0, 0.3);
        --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        --gradient-primary-hover: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        --border-radius-lg: 16px; --border-radius-md: 10px; --border-radius-sm: 6px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; } html { scroll-behavior: smooth; }
    body { font-family: 'Montserrat', sans-serif; background-color: var(--darker); color: var(--text-primary); line-height: 1.6; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 25px 15px; background-image: radial-gradient(circle at 5% 10%, rgba(var(--primary-rgb), 0.05) 0%, transparent 50%), radial-gradient(circle at 95% 90%, rgba(var(--primary-rgb), 0.04) 0%, transparent 40%); background-attachment: fixed; }
    .checkout-container { width: 100%; max-width: 580px; animation: fadeInScale 0.6s cubic-bezier(0.2, 0.8, 0.2, 1.1) forwards; }
    @keyframes fadeInScale { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .checkout-card { background: var(--dark); border-radius: var(--border-radius-lg); padding: 2.8rem; box-shadow: 0 20px 50px -10px var(--shadow-color); border: 1px solid var(--border-color-light); position: relative; overflow: hidden; }
    .checkout-card::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(var(--primary-rgb), 0.06) 0%, transparent 40%); animation: rotateGlow 15s linear infinite; pointer-events: none; z-index: 0; }
    @keyframes rotateGlow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .checkout-header { text-align: center; margin-bottom: 2rem; position: relative; z-index: 1; }
    .checkout-header h1 { font-size: 2rem; font-weight: 700; color: var(--lighter); margin-bottom: 0.5rem; background: var(--gradient-primary); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; margin-top: 0.5rem; }
    .checkout-header p { font-size: 1.05rem; color: var(--text-secondary); margin-bottom: 0; }
    .checkout-header .header-logos { display: flex; align-items: center; justify-content: center; gap: 40px; margin-bottom: 2.5rem; padding-top: 1rem; }
    .checkout-header .logo-img { height: 150px; width: auto; object-fit: contain; filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.35)); animation: float 4.5s ease-in-out infinite; }
    .checkout-header .plus-icon { font-size: 4rem; font-weight: 700; color: var(--primary); line-height: 1; text-shadow: 0 0 6px var(--primary), 0 0 15px var(--primary), 0 0 30px var(--primary), 0 0 1px var(--primary-dark); animation: float 4.5s ease-in-out infinite; animation-delay: 0.2s; }
    .checkout-header .lovechat-logo { animation-delay: 0s; }
    .checkout-header .mercadopago-logo { height: 160px; animation-delay: 0.4s; filter: none; object-fit: contain; }
    @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-7px); } 100% { transform: translateY(0px); } }
    .package-details { background-color: rgba(var(--primary-rgb), 0.04); border-radius: var(--border-radius-sm); padding: 0.8rem 1.2rem; margin-bottom: 1.5rem; border: 1px solid rgba(var(--primary-rgb), 0.12); position: relative; z-index: 1; text-align: center; }
    .package-details p { margin-bottom: 0.4rem; color: var(--text-primary); font-size: 0.9rem; line-height: 1.4; }
    .package-details p:last-child { margin-bottom: 0; }
    .package-details strong { color: var(--primary); font-weight: 600; }
    .payment-method-tabs { display: flex; border-bottom: 1px solid var(--border-color-light); margin-bottom: 2.2rem; position: relative; z-index: 1;}
    .payment-method-tab { flex: 1; padding: 0.9rem 0.5rem; text-align: center; cursor: pointer; color: var(--text-secondary); font-weight: 500; font-size: 0.95rem; border-bottom: 3px solid transparent; transition: color 0.25s ease, border-color 0.25s ease; position: relative; }
    .payment-method-tab:hover { color: var(--lighter); }
    .payment-method-tab.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
    .payment-method-tab i { margin-right: 7px; width: 18px; text-align: center; }
    .payment-method-container { display: none; opacity: 0; transition: opacity 0.4s ease-in-out, display 0s linear 0.4s; position: relative; z-index: 1; }
    .payment-method-container.active { display: block; opacity: 1; transition: opacity 0.4s ease-in-out; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem 1.1rem; margin-bottom: 1rem; }
    .form-group { margin-bottom: 0; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group.col-span-2 { grid-column: span 2; }
    .form-group.col-span-3 { grid-column: span 3; }
    label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.4rem; font-weight: 500; }
    input[type="text"], input[type="email"], input[type="tel"], select { width: 100%; padding: 0.85rem; background: var(--gray); border: 1px solid var(--border-color); border-radius: var(--border-radius-md); color: var(--text-primary); font-size: 0.95rem; font-family: 'Montserrat', sans-serif; transition: border-color 0.2s, box-shadow 0.2s; appearance: none; -webkit-appearance: none; -moz-appearance: none; }
    select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%23cccccc' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") !important; background-repeat: no-repeat !important; background-position: right 0.9rem center !important; padding-right: 3rem !important; }
    input:focus, select:focus { border-color: var(--border-color-focus) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
    .input-with-icon { position: relative; }
    .input-with-icon input { padding-right: 3rem; }
    .input-with-icon .card-brand-icon { position: absolute; right: 0.9rem; top: 50%; transform: translateY(-50%); font-size: 1.5rem; opacity: 0.6; transition: opacity 0.3s; }
    .input-with-icon .card-brand-icon.visible { opacity: 1; }
    .input-with-icon .card-brand-icon i { vertical-align: middle; }
    button[type="submit"].submit-button { width: 100%; padding: 0.9rem; background: var(--gradient-primary); color: white; border: none; border-radius: var(--border-radius-md); font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-family: 'Montserrat', sans-serif; font-size: 1rem; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.25); margin-top: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-transform: uppercase; letter-spacing: 0.8px; }
    button[type="submit"].submit-button:hover:not(:disabled) { background: var(--gradient-primary-hover); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.35); }
    button[type="submit"].submit-button:disabled { background: var(--gray-light); cursor: not-allowed; opacity: 0.6; box-shadow: none; }
    button[type="submit"].submit-button:disabled i.fa-spinner { animation: spin 1.5s linear infinite; }
    #pix-result-container { display: none; margin-top: 1.5rem; text-align: center; background-color: var(--dark); padding: 2rem 1.5rem; border-radius: var(--border-radius-md); border: 1px solid var(--border-color); }
    #pix-result-container h3 { margin-bottom: 1rem; color: var(--primary); font-weight: 600; font-size: 1.3rem;}
    #pix-result-container #pix-instructions { font-size: 0.95rem; color: var(--text-primary); margin-bottom: 1.5rem; line-height: 1.5;}
    #pix-result-container #pix-qr-code img { display: block; margin: 0 auto 1.5rem auto; max-width: 220px; border-radius: var(--border-radius-sm); background-color: white; padding: 10px; border: 1px solid var(--border-color-light); box-shadow: 0 6px 18px var(--shadow-color-light); }
    #pix-result-container .pix-code-wrapper { margin-bottom: 1.2rem; position: relative;}
    #pix-result-container .pix-code-wrapper label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.5rem; }
    #pix-result-container .pix-code-wrapper input[type="text"] { width: 100%; padding: 0.8rem; background: var(--gray-dark); border: 1px dashed var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-secondary); font-size: 0.9rem; font-family: monospace; text-align: center; word-break: break-all; margin-bottom: 1rem; }
    #pix-result-container .pix-code-wrapper button.copy-button { background: var(--gray-light); color: var(--text-primary); border: 1px solid var(--border-color); padding: 0.7rem 1.1rem; border-radius: var(--border-radius-sm); cursor: pointer; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.4rem; }
    #pix-result-container .pix-code-wrapper button.copy-button:hover:not(:disabled) { background: var(--gray); border-color: var(--text-secondary); }
    #pix-result-container .pix-code-wrapper button.copy-button:disabled { background: var(--gray-dark); cursor: not-allowed; opacity: 0.5; }
    #pix-result-container .pix-code-wrapper button.copy-button i { margin-right: 0.4rem; }
    #pix-result-container .copied-feedback { display: inline-block; margin-left: 8px; color: var(--success); font-size: 0.85rem; font-weight: 600; opacity: 0; transition: opacity 0.4s ease; }
    #pix-result-container .copied-feedback.show { opacity: 1; }
    #pix-expiration-info { font-size: 0.85rem; color: var(--warning); margin-top: 1.5rem; font-weight: 500;}
    #pix-expiration-info i { margin-right: 4px;}
    #loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(10, 10, 10, 0.9); backdrop-filter: blur(8px); display: none; flex-direction: column; justify-content: center; align-items: center; z-index: 9999; opacity: 0; visibility: hidden; transition: opacity 0.4s ease, visibility 0s linear 0.4s; }
    #loading-overlay.show { display: flex; opacity: 1; visibility: visible; transition: opacity 0.4s ease, visibility 0s linear 0s; }
    #loading-overlay .spinner-icon { font-size: 3rem; color: var(--primary); animation: spin 1.3s linear infinite; margin-bottom: 1rem; }
    #loading-overlay .spinner-text { font-size: 1rem; color: var(--text-primary); font-weight: 500; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    #payment-status { margin-top: 2rem; padding: 1.3rem; border-radius: var(--border-radius-md); text-align: center; font-weight: 500; display: none; position: relative; z-index: 1; line-height: 1.6; font-size: 0.95rem; border-left-width: 4px; border-left-style: solid; animation: fadeInStatus 0.5s ease forwards;}
    @keyframes fadeInStatus { from { opacity: 0; transform: translateY(10px);} to { opacity: 1; transform: translateY(0px);} }
    #payment-status.success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: var(--success); display: block; }
    #payment-status.error { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: var(--danger); display: block; }
    #payment-status.info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border-color: var(--info); display: block; }
    #payment-status i { margin-right: 8px; font-size: 1.05em; vertical-align: middle; }
    .checkout-footer-links { margin-top: 2.5rem; text-align: center; position: relative; z-index: 1; }
    .back-link { display: inline-block; color: var(--text-secondary); font-size: 0.85rem; text-decoration: none; transition: all 0.25s ease; padding: 0.5rem 1rem; border-radius: var(--border-radius-md); border: 1px solid transparent; }
    .back-link:hover { color: var(--primary); background-color: var(--primary-light); }
    .back-link i { margin-right: 0.5rem; }
    .secure-payment-info { margin-top: 1rem; font-size: 0.75rem; color: var(--text-secondary); opacity: 0.7; }
    .secure-payment-info i { margin-right: 0.4rem; color: var(--success); }
    @media (max-width: 600px) {
        body { padding: 25px 10px; align-items: flex-start; }
        .checkout-card { padding: 2.5rem 1.2rem; }
        .checkout-header .header-logos { gap: 25px; margin-bottom: 2rem;}
        .checkout-header .logo-img { height: 100px; }
        .checkout-header .mercadopago-logo { height: 110px; }
        .checkout-header .plus-icon { font-size: 3rem; }
        .form-grid { grid-template-columns: 1fr; }
        .form-grid.card-sensitive-grid { grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); }
    }
    @media (max-width: 450px) {
        .checkout-card { padding: 2rem 1rem; }
        .checkout-header h1 { font-size: 1.7rem; }
        .checkout-header .header-logos { gap: 20px; margin-bottom: 1.5rem; padding-top: 0.5rem;}
        .checkout-header .logo-img { height: 70px; }
        .checkout-header .mercadopago-logo { height: 75px; }
        .checkout-header .plus-icon { font-size: 2.5rem; }
        .package-details { padding: 0.8rem 1rem; font-size: 0.85rem;}
        #pix-result-container img { max-width: 180px;}
        label { font-size: 0.8rem; }
        input[type="text"], input[type="email"], input[type="tel"], select { padding: 0.75rem; font-size: 0.9rem; }
        button[type="submit"].submit-button { font-size: 0.9rem; padding: 0.8rem; }
        .form-grid.card-sensitive-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .form-grid.card-sensitive-grid .form-group:last-child { grid-column: 1 / -1; }
    }

</style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-card">
            <div id="loading-overlay"><i class="fas fa-spinner fa-spin spinner-icon"></i><span class="spinner-text">Processando...</span></div>
            <div class="checkout-header">
                <div class="header-logos">
                    <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat Logo" class="logo-img lovechat-logo">
                    <span class="plus-icon">+</span>
                    <img src="https://www.dashcontroles.com.br/wp-content/uploads/2023/08/mercado-pago-logo.png" alt="Mercado Pago Logo" class="logo-img mercadopago-logo">
                </div>
                <h1>Finalizar Compra</h1>
                <p>Escolha como pagar seu pacote.</p>
            </div>
            <div class="package-details">
                <p>Pacote: <strong><?php echo $safeDescription; ?></strong></p>
                <p>Valor: <strong>R$ <?php echo $priceFormatted; ?></strong></p>
            </div>
            <div class="payment-method-tabs">
                <div class="payment-method-tab active" data-method="card"><i class="fas fa-credit-card"></i> Cartão</div>
                <div class="payment-method-tab" data-method="pix"><i class="fa-brands fa-pix"></i> Pix</div>
            </div>

            <!-- ==== Container Pagamento Cartão (COM FORMULÁRIO CUSTOMIZADO) ==== -->
            <div id="card-method-container" class="payment-method-container active">
                <form id="custom-card-form">
                    <p style="color: var(--text-secondary); margin-bottom: 1.8rem; font-size: 0.9rem; text-align: center;">Preencha os dados do seu cartão.</p>

                    <!-- Dados do Dono do Cartão -->
                    <div class="form-grid">
                         <div class="form-group">
                            <label for="card_payerFirstName">Nome do Titular</label>
                            <input type="text" id="card_payerFirstName" data-checkout="cardholderName" required placeholder="Como no cartão" value="<?php echo $userFirstName; ?>">
                        </div>
                         <div class="form-group">
                            <label for="card_payerEmail">E-mail do Titular</label>
                            <input type="email" id="card_payerEmail" data-checkout="payerEmail" required placeholder="email@exemplo.com" value="<?php echo $userEmail; ?>">
                        </div>
                    </div>

                     <!-- Dados do Cartão -->
                     <div class="form-group full-width">
                        <label for="cardNumber">Número do Cartão</label>
                        <div class="input-with-icon">
                            <input type="tel" id="cardNumber" data-checkout="cardNumber" required placeholder="0000 0000 0000 0000" autocomplete="cc-number" inputmode="numeric">
                             <span id="card-brand-icon" class="card-brand-icon"></span> <!-- Ícone da bandeira aqui -->
                        </div>
                    </div>

                    <div class="form-grid card-sensitive-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 0.8rem;"> <!-- Grid para Validade e CVV -->
                        <div class="form-group">
                            <label for="cardExpirationMonth">Mês Exp.</label>
                            <input type="tel" id="cardExpirationMonth" data-checkout="cardExpirationMonth" required placeholder="MM" maxlength="2" inputmode="numeric">
                        </div>
                         <div class="form-group">
                            <label for="cardExpirationYear">Ano Exp.</label>
                            <input type="tel" id="cardExpirationYear" data-checkout="cardExpirationYear" required placeholder="AA" maxlength="2" inputmode="numeric">
                        </div>
                        <div class="form-group">
                            <label for="securityCode">CVV</label>
                            <input type="tel" id="securityCode" data-checkout="securityCode" required placeholder="123" maxlength="4" inputmode="numeric" autocomplete="cc-csc">
                        </div>
                    </div>

                    <!-- Documento e Parcelas (Aparecem depois de identificar bandeira) -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="card_identificationType">Tipo de Documento</label>
                            <select id="card_identificationType" data-checkout="identificationType" required disabled>
                                <option value="" disabled selected>Digite o cartão</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="card_identificationNumber">Número do Documento</label>
                            <input type="tel" id="card_identificationNumber" data-checkout="identificationNumber" required placeholder="Apenas números" disabled>
                        </div>
                    </div>
                    <div class="form-group full-width">
                         <label for="installments">Parcelas</label>
                         <select id="installments" name="installments" required disabled>
                              <option value="" disabled selected>Digite o cartão</option>
                         </select>
                         <input type="hidden" id="paymentMethodId" name="paymentMethodId">
                         <input type="hidden" id="issuerId" name="issuerId">
                    </div>

                    <button type="submit" id="card-submit-button" class="submit-button" disabled>
                        <i class="fas fa-credit-card"></i> Pagar com Cartão
                    </button>
                </form>
            </div>
            <!-- ==== FIM Container Pagamento Cartão ==== -->

            <!-- ==== Container Pagamento PIX ==== -->
            <div id="pix-method-container" class="payment-method-container">
                <form id="pix-form">
                    <p style="color: var(--text-secondary); margin-bottom: 1.8rem; font-size: 0.9rem; text-align: center;">Preencha seus dados para gerar o código Pix.</p>
                    <div class="form-grid">
                        <div class="form-group"><label for="pix_payerFirstName">Nome</label><input id="pix_payerFirstName" name="payerFirstName" type="text" required placeholder="Seu nome" value="<?php echo $userFirstName; ?>"></div>
                        <div class="form-group"><label for="pix_payerLastName">Sobrenome</label><input id="pix_payerLastName" name="payerLastName" type="text" required placeholder="Seu sobrenome" value="<?php echo $userLastName; ?>"></div>
                        <div class="form-group full-width"><label for="pix_payerEmail">E-mail</label><input id="pix_payerEmail" name="email" type="email" required placeholder="seuemail@exemplo.com" value="<?php echo $userEmail; ?>"></div>
                        <div class="form-group"><label for="pix_identificationType">Tipo de Documento</label>
                            <select id="pix_identificationType" name="identificationType" required disabled>
                                <option value="" disabled selected>Carregando...</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="pix_identificationNumber">Número do Documento</label><input id="pix_identificationNumber" name="identificationNumber" type="tel" required placeholder="Apenas números"></div>
                    </div>
                    <button type="submit" id="pix-submit-button" class="submit-button"><i class="fa-brands fa-pix"></i> Gerar Pix</button>
                </form>
                <div id="pix-result-container">
                    <h3><i class="fa-brands fa-pix"></i> Pague com Pix</h3>
                    <p id="pix-instructions">Escaneie o QR Code ou copie o código abaixo:</p>
                    <div id="pix-qr-code"><p style="color:var(--text-secondary);font-size:0.9em;">Aguardando geração...</p></div>
                    <div class="pix-code-wrapper">
                        <label for="pix-copy-code">Código Pix (Copia e Cola):</label>
                        <input type="text" id="pix-copy-code" readonly placeholder="Aguardando geração...">
                        <button type="button" class="copy-button" disabled><i class="fas fa-copy"></i> Copiar Código</button>
                        <span class="copied-feedback"></span>
                    </div>
                     <p id="pix-expiration-info" style="display: none;"><i class="fas fa-exclamation-triangle"></i> <span id="pix-expiration-text"></span></p>
                </div>
            </div>
            <!-- ==== FIM Container Pagamento PIX ==== -->

            <div id="payment-status"></div>
            <div class="checkout-footer-links">
                 <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para Dashboard</a>
                 <p class="secure-payment-info"><i class="fas fa-shield-alt"></i> Pagamento seguro processado via Mercado Pago.</p>
            </div>
        </div>
    </div>

    <!-- ***** MERCADO PAGO SDK V2 ***** -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <!-- ***************************** -->

    <script>
        // Script completo v4.4 - CORRIGE passagem de config para createSelectOptions

        // --- Constantes e Variáveis Globais ---
        const mpPublicKey = "<?php echo $mercadoPagoPublicKey; ?>";
        const purchaseAmount = <?php echo $price; ?>;
        const purchaseDescription = <?php echo json_encode($description); ?>;
        const packageId = <?php echo $leads; ?>;
        const userEmailFromSession = "<?php echo $userEmail; ?>";
        const userFirstNameFromSession = <?php echo json_encode($userFirstName); ?>;
        const userLastNameFromSession = <?php echo json_encode($userLastName); ?>;
        const processPaymentUrl = 'api/process_payment.php';
        const dashboardUrl = 'dashboard.php';

        // --- Elementos DOM ---
        const cardMethodContainer = document.getElementById('card-method-container');
        const pixMethodContainer = document.getElementById('pix-method-container');
        const tabs = document.querySelectorAll('.payment-method-tab');
        const paymentStatusContainer = document.getElementById('payment-status');
        const loadingOverlay = document.getElementById('loading-overlay');
        const loadingSpinnerText = loadingOverlay?.querySelector('.spinner-text');
        const customCardForm = document.getElementById('custom-card-form');
        const cardNumberInput = document.getElementById('cardNumber');
        const cardBrandIcon = document.getElementById('card-brand-icon');
        const cardIdentificationTypeSelect = document.getElementById('card_identificationType');
        const cardIdentificationNumberInput = document.getElementById('card_identificationNumber');
        const installmentsSelect = document.getElementById('installments');
        const cardSubmitButton = document.getElementById('card-submit-button');
        const paymentMethodIdInput = document.getElementById('paymentMethodId');
        const issuerIdInput = document.getElementById('issuerId');
        const cardPayerFirstNameInput = document.getElementById('card_payerFirstName');
        const cardPayerEmailInput = document.getElementById('card_payerEmail');
        const cardExpirationMonthInput = document.getElementById('cardExpirationMonth');
        const cardExpirationYearInput = document.getElementById('cardExpirationYear');
        const securityCodeInput = document.getElementById('securityCode');
        const pixForm = document.getElementById('pix-form');
        const pixResultContainer = document.getElementById('pix-result-container');
        const pixQrCodeContainer = document.getElementById('pix-qr-code');
        const pixCopyCodeInput = document.getElementById('pix-copy-code');
        const pixCopyButton = pixResultContainer?.querySelector('button.copy-button');
        const pixSubmitButton = document.getElementById('pix-submit-button');
        const pixInstructions = document.getElementById('pix-instructions');
        const pixExpirationInfo = document.getElementById('pix-expiration-info');
        const pixExpirationText = document.getElementById('pix-expiration-text');
        const pixIdentificationTypeSelect = document.getElementById('pix_identificationType');
        const pixPayerFirstNameInput = document.getElementById('pix_payerFirstName');
        const pixPayerLastNameInput = document.getElementById('pix_payerLastName');

        let isProcessingPayment = false;
        let isCheckingBin = false;
        let identificationTypes = [];
        let currentBin = "";
        let binCheckTimeout;

        const mp = new MercadoPago(mpPublicKey, { locale: 'pt-BR' });

        // --- Funções Auxiliares ---

        // showLoading (mantida como v4.2)
        function showLoading(show = true, text = 'Processando...', context = null) {
            if (!loadingOverlay || !loadingSpinnerText) { console.warn("showLoading: Elementos não encontrados."); return; }
            console.log(`[JS] showLoading: ${show}, Text: ${text}, Context: ${context}`);
            loadingSpinnerText.textContent = text;
            loadingOverlay.classList.toggle('show', show);
            if (context === 'cardSubmit' || context === 'pixSubmit') { tabs.forEach(tab => tab.style.pointerEvents = show ? 'none' : 'auto'); }
            if (cardSubmitButton) { if (context === 'cardBinCheck') { if (show) { cardSubmitButton.disabled = true; } } else if (context === 'cardSubmit') { cardSubmitButton.disabled = show; if (show) { cardSubmitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`; } else { if (!(paymentStatusContainer.classList.contains('success') || paymentStatusContainer.classList.contains('info'))) { cardSubmitButton.innerHTML = `<i class="fas fa-credit-card"></i> Pagar com Cartão`; } } } }
            if (pixSubmitButton) { if (context === 'pixSubmit') { pixSubmitButton.disabled = show; if (show) { pixSubmitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`; } else { if (!(paymentStatusContainer.classList.contains('success') && pixResultContainer?.style.display === 'block')) { pixSubmitButton.innerHTML = `<i class="fa-brands fa-pix"></i> Gerar Pix`; } } } }
        }

        // showStatus (mantida)
        function showStatus(message, type = 'info', sticky = false) { if (!paymentStatusContainer) { console.warn("showStatus: Container não encontrado."); return; } console.log(`[JS] showStatus: Type=${type}, Message=${message}`); paymentStatusContainer.innerHTML = ''; paymentStatusContainer.className = 'payment-status'; paymentStatusContainer.classList.add(type); let iconClass = 'fas fa-info-circle'; if (type === 'success') iconClass = 'fas fa-check-circle'; if (type === 'error') iconClass = 'fas fa-exclamation-triangle'; const iconElement = document.createElement('i'); iconElement.className = iconClass; const textElement = document.createElement('span'); textElement.innerHTML = " " + message; paymentStatusContainer.appendChild(iconElement); paymentStatusContainer.appendChild(textElement); paymentStatusContainer.style.display = 'block'; try { paymentStatusContainer.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { console.warn("Scroll falhou", e); } if (!sticky) { setTimeout(() => { if (paymentStatusContainer && paymentStatusContainer.textContent.includes(message.substring(0, 30))) { paymentStatusContainer.style.display = 'none'; paymentStatusContainer.innerHTML = ''; } }, type === 'error' ? 8000 : 5000); } }

        // copyToClipboard (mantida)
        function copyToClipboard(textToCopy, buttonElement) { if (!navigator.clipboard) { showStatus('Navegador não suporta cópia.', 'warning'); return; } navigator.clipboard.writeText(textToCopy).then(() => { console.log('[JS] Código Pix copiado!'); const originalHtml = buttonElement.innerHTML; const originalIconClass = buttonElement.querySelector('i')?.className || 'fas fa-copy'; buttonElement.innerHTML = '<i class="fas fa-check"></i> Copiado!'; buttonElement.disabled = true; let feedback = buttonElement.parentNode.querySelector('.copied-feedback'); if (feedback) { feedback.textContent = 'Copiado!'; feedback.classList.add('show'); } setTimeout(() => { buttonElement.innerHTML = `<i class="${originalIconClass}"></i> Copiar Código`; buttonElement.disabled = false; if (feedback) feedback.classList.remove('show'); }, 2500); }).catch(err => { console.error('[JS] Erro ao copiar: ', err); showStatus('Erro ao copiar.', 'error'); }); }

        // createSelectOptions (mantida como v4.3 - sem os logs detalhados extras)
        function createSelectOptions(selectElement, options, config = { label: "name", value: "id", placeholder: "Selecione...", defaultSelectedValue: null }) {
            if (!selectElement) { console.error("[JS] Select não fornecido para createSelectOptions."); return; }
            // *** Chaves são extraídas do objeto 'config' recebido ***
            const { label: labelKey, value: valueKey, placeholder, defaultSelectedValue } = config;

            // Log para confirmar as chaves que a função está usando
            console.log(`[JS createSelectOptions] Populando select: #${selectElement.id}. Usando valueKey='${valueKey}', labelKey='${labelKey}'`);

            selectElement.innerHTML = ''; // Limpa

            const placeholderOption = document.createElement('option');
            placeholderOption.value = "";
            placeholderOption.textContent = placeholder || "Selecione...";
            placeholderOption.disabled = true;
            placeholderOption.selected = true;
            selectElement.appendChild(placeholderOption);

            let hasValidOptions = false;
            const fragment = document.createDocumentFragment();

            if (Array.isArray(options) && options.length > 0) {
                options.forEach((optionData, index) => {
                    const optValue = optionData[valueKey];
                    const optLabel = optionData[labelKey];

                    // Verifica se os valores foram realmente obtidos
                    if (optValue === undefined || optLabel === undefined || optValue === null || optLabel === null) {
                        console.warn(`[JS createSelectOptions] Dado de opção inválido ou não encontrado (value='${optValue}', label='${optLabel}') para #${selectElement.id}. Pulando item ${index}. Objeto original:`, optionData);
                        return; // Pula esta iteração
                    }

                    // Se chegou aqui, os dados são válidos
                    hasValidOptions = true;
                    const optionElement = document.createElement('option');
                    optionElement.value = String(optValue);
                    optionElement.textContent = String(optLabel);

                    if (defaultSelectedValue && String(optValue) === String(defaultSelectedValue)) {
                        optionElement.selected = true;
                        placeholderOption.selected = false;
                    }
                    fragment.appendChild(optionElement);
                });
            } else {
                console.warn(`[JS createSelectOptions] Nenhuma opção válida fornecida (array vazio ou inválido) para #${selectElement.id}. Dados recebidos:`, options);
            }

            selectElement.appendChild(fragment);
            selectElement.disabled = !hasValidOptions;
            console.log(`[JS createSelectOptions] Select #${selectElement.id} ${selectElement.disabled ? 'DESABILITADO' : 'HABILITADO'}. hasValidOptions: ${hasValidOptions}`);

            selectElement.style.display = 'none';
            selectElement.offsetHeight;
            selectElement.style.display = '';
        }


        // getMercadoPagoDeviceId (mantida)
        function getMercadoPagoDeviceId() {
            const hiddenInput = document.querySelector("input[name='mercadopago_device_id']");
            if (hiddenInput && hiddenInput.value) { console.log("[JS] Device ID pego do input oculto."); return hiddenInput.value; }
            if (window.MP_DEVICE_SESSION_ID) { console.log("[JS] Device ID pego da var global."); return window.MP_DEVICE_SESSION_ID; }
            console.warn("[JS] ATENÇÃO: Não foi possível obter o Device ID."); return null;
        }

        // --- Lógica Específica do Cartão Customizado ---

        // 1. Buscar Tipos de Documento (CORRIGIDO)
        async function fetchIdentificationTypes() {
            console.log("[JS] Buscando tipos ID...");
            try {
                const fetchedTypes = await mp.getIdentificationTypes();
                console.log("[JS] Tipos ID recebidos:", fetchedTypes);
                if (!fetchedTypes || !Array.isArray(fetchedTypes) || fetchedTypes.length === 0) {
                    throw new Error("Nenhum tipo de documento retornado ou formato inválido.");
                }
                identificationTypes = fetchedTypes;

                if (pixIdentificationTypeSelect) {
                    console.log("[JS] Populando select PIX...");
                    // *** CORREÇÃO AQUI: Incluir value e label ***
                    createSelectOptions(pixIdentificationTypeSelect, identificationTypes, {
                        value: "id", label: "name", // <= EXPLICITAMENTE DEFINIDOS
                        placeholder: "Selecione...", defaultSelectedValue: 'CPF'
                    });
                } else { console.warn("[JS] Select de ID do PIX não encontrado."); }

                if (cardIdentificationTypeSelect) {
                    console.log("[JS] Populando select Cartão (inicialmente desabilitado)...");
                    // *** CORREÇÃO AQUI: Incluir value e label ***
                    createSelectOptions(cardIdentificationTypeSelect, identificationTypes, {
                         value: "id", label: "name", // <= EXPLICITAMENTE DEFINIDOS
                         placeholder: "Digite o cartão", defaultSelectedValue: 'CPF'
                    });
                    cardIdentificationTypeSelect.disabled = true; // Garante que comece desabilitado
                    if (cardIdentificationNumberInput) cardIdentificationNumberInput.disabled = true;
                } else { console.warn("[JS] Select de ID do Cartão não encontrado."); }

            } catch (error) {
                console.error('[JS] Erro CRÍTICO ao buscar tipos de ID: ', error);
                showStatus("Erro ao carregar tipos de documento. Tente recarregar.", "error", true);
                const fallbackTypes = [{id: 'CPF', name: 'CPF'}, {id: 'CNPJ', name: 'CNPJ'}];
                identificationTypes = fallbackTypes;
                if (pixIdentificationTypeSelect) {
                    console.warn("[JS] Usando fallback para popular select PIX.");
                    // *** CORREÇÃO AQUI: Incluir value e label ***
                    createSelectOptions(pixIdentificationTypeSelect, fallbackTypes, {
                        value: "id", label: "name", // <= EXPLICITAMENTE DEFINIDOS
                        placeholder: "Selecione (Fallback)", defaultSelectedValue: 'CPF'
                    });
                }
                 if (cardIdentificationTypeSelect) {
                    console.warn("[JS] Usando fallback para popular select Cartão.");
                    // *** CORREÇÃO AQUI: Incluir value e label ***
                    createSelectOptions(cardIdentificationTypeSelect, fallbackTypes, {
                         value: "id", label: "name", // <= EXPLICITAMENTE DEFINIDOS
                         placeholder: "Digite o cartão", defaultSelectedValue: 'CPF'
                     });
                    cardIdentificationTypeSelect.disabled = true;
                    if (cardIdentificationNumberInput) cardIdentificationNumberInput.disabled = true;
                }
            }
        }

         // Função para reavaliar e habilitar/desabilitar o botão de pagar cartão (mantida)
         function checkAndToggleCardSubmitButton() {
            if (!cardSubmitButton) return;
            const cardNumOk = cardNumberInput?.value?.replace(/\D/g, '').length >= 10;
            const expMonthOk = /^\d{2}$/.test(cardExpirationMonthInput?.value);
            const expYearOk = /^\d{2}$/.test(cardExpirationYearInput?.value);
            const cvvOk = /^\d{3,4}$/.test(securityCodeInput?.value);
            const idTypeOk = cardIdentificationTypeSelect?.value && !cardIdentificationTypeSelect.disabled;
            const idNumOk = cardIdentificationNumberInput?.value?.replace(/\D/g, '').length > 0 && !cardIdentificationNumberInput.disabled;
            const installmentsOk = installmentsSelect?.value && !installmentsSelect.disabled;
            const pmIdOk = paymentMethodIdInput?.value;
            const allFieldsValid = cardNumOk && expMonthOk && expYearOk && cvvOk && idTypeOk && idNumOk && installmentsOk && pmIdOk;
            if (allFieldsValid && !isProcessingPayment && !isCheckingBin) {
                console.log("[JS Check Button] Todos campos OK. Habilitando botão Pagar.");
                cardSubmitButton.disabled = false;
                if (!cardSubmitButton.innerHTML.includes('fa-spinner')) { cardSubmitButton.innerHTML = `<i class="fas fa-credit-card"></i> Pagar com Cartão`; }
            } else {
                 console.log(`[JS Check Button] Campos faltando ou processamento ativo. Botão Pagar ${cardSubmitButton.disabled ? 'permanece' : 'será'} DESABILITADO.`);
                 console.log(`Details: cardNumOk=${cardNumOk}, expMonthOk=${expMonthOk}, expYearOk=${expYearOk}, cvvOk=${cvvOk}, idTypeOk=${idTypeOk}, idNumOk=${idNumOk}, installmentsOk=${installmentsOk}, pmIdOk=${pmIdOk}, isProcessingPayment=${isProcessingPayment}, isCheckingBin=${isCheckingBin}`);
                cardSubmitButton.disabled = true;
                 if (!cardSubmitButton.innerHTML.includes('fa-spinner')) { cardSubmitButton.innerHTML = `<i class="fas fa-credit-card"></i> Pagar com Cartão`; }
            }
        }
        [cardNumberInput, cardExpirationMonthInput, cardExpirationYearInput, securityCodeInput, cardIdentificationTypeSelect, cardIdentificationNumberInput, installmentsSelect].forEach(element => {
            element?.addEventListener('change', checkAndToggleCardSubmitButton);
            element?.addEventListener('keyup', checkAndToggleCardSubmitButton);
        });


        // 2. Identificar Bandeira e Buscar Parcelas (CORRIGIDO)
        cardNumberInput?.addEventListener('input', (event) => {
            const value = event.target.value.replace(/\D/g, '');
            const bin = value.substring(0, 6);

            clearTimeout(binCheckTimeout);

            const resetCardDependentFields = (clearBin = true) => {
                console.log("[JS] Resetando campos dependentes do cartão (resetCardDependentFields)...");
                if (cardBrandIcon) cardBrandIcon.innerHTML = ''; cardBrandIcon.classList.remove('visible');
                if (installmentsSelect) {
                    createSelectOptions(installmentsSelect, [], { value: "installments", label: "recommended_message", placeholder: "Digite o cartão" }); // Explicit keys
                    installmentsSelect.disabled = true;
                }
                if (cardIdentificationTypeSelect) {
                     // *** CORREÇÃO AQUI: Incluir value e label ***
                    createSelectOptions(cardIdentificationTypeSelect, identificationTypes, {
                        value: "id", label: "name", // <= EXPLICITAMENTE DEFINIDOS
                        placeholder: "Digite o cartão", defaultSelectedValue: 'CPF'
                    });
                    cardIdentificationTypeSelect.disabled = true;
                }
                if (cardIdentificationNumberInput) {
                    cardIdentificationNumberInput.value = '';
                    cardIdentificationNumberInput.disabled = true;
                }
                if (paymentMethodIdInput) paymentMethodIdInput.value = '';
                if (issuerIdInput) issuerIdInput.value = '';
                if (clearBin) currentBin = "";
                checkAndToggleCardSubmitButton();
            };

            if (bin.length < 6) { if (currentBin !== "") resetCardDependentFields(); return; }
            if (bin === currentBin && !isCheckingBin) { console.log("[JS] BIN não mudou ou verificação em andamento."); return; }

            binCheckTimeout = setTimeout(async () => {
                if (isCheckingBin) return;
                console.log(`[JS] BIN ${bin} estável. Iniciando verificação.`);
                isCheckingBin = true; currentBin = bin;
                resetCardDependentFields(false); // <= Chama reset antes de iniciar check
                showLoading(true, 'Verificando cartão...', 'cardBinCheck');
                let success = false;

                try {
                    console.log("[JS BIN CHECK] Iniciando try...");
                    const { results } = await mp.getPaymentMethods({ bin });
                    console.log("[JS BIN CHECK] Resultado Payment Methods:", results);
                    if (!results || results.length === 0) throw new Error("Cartão não reconhecido.");
                    const paymentMethod = results[0];
                    if (paymentMethodIdInput) paymentMethodIdInput.value = paymentMethod.id;

                    if (cardBrandIcon) { /* ... lógica ícone ... */
                         let iconHtml = `<i class="fa-regular fa-credit-card"></i>`;
                         if (paymentMethod.id.includes('visa')) iconHtml = `<i class="fa-brands fa-cc-visa"></i>`;
                         else if (paymentMethod.id.includes('master')) iconHtml = `<i class="fa-brands fa-cc-mastercard"></i>`;
                         else if (paymentMethod.id.includes('amex')) iconHtml = `<i class="fa-brands fa-cc-amex"></i>`;
                         else if (paymentMethod.id.includes('elo')) iconHtml = `Elo`;
                         else if (paymentMethod.id.includes('hiper')) iconHtml = `Hiper`;
                         cardBrandIcon.innerHTML = iconHtml;
                         cardBrandIcon.classList.add('visible');
                    }

                    console.log('[JS BIN CHECK] Tentando habilitar campos de documento...');
                    if (cardIdentificationTypeSelect && Array.isArray(identificationTypes) && identificationTypes.length > 0) {
                        console.log('[JS BIN CHECK] Habilitando ID Select e Input...');
                         // *** CORREÇÃO AQUI: Incluir value e label ***
                        createSelectOptions(cardIdentificationTypeSelect, identificationTypes, {
                             value: "id", label: "name", // <= EXPLICITAMENTE DEFINIDOS
                             placeholder: "Selecione...", defaultSelectedValue: 'CPF'
                         });
                        if (cardIdentificationNumberInput) cardIdentificationNumberInput.disabled = cardIdentificationTypeSelect.disabled; // Habilita/desabilita numero junto com select
                        if (cardIdentificationTypeSelect.disabled) console.warn("[JS BIN CHECK] ID Select permaneceu desabilitado após repopular!");
                    } else { console.warn('[JS BIN CHECK] Falha ao habilitar ID - Tipos não carregados ou select não encontrado.'); }

                    console.log(`[JS BIN CHECK] Buscando parcelas...`);
                    const installmentsData = await mp.getInstallments({ amount: String(purchaseAmount), bin: bin, payment_method_id: paymentMethod.id });
                    console.log("[JS BIN CHECK] Resultado Installments:", installmentsData);
                    if (!installmentsData || installmentsData.length === 0 || !installmentsData[0].payer_costs || installmentsData[0].payer_costs.length === 0) {
                        console.warn("[JS BIN CHECK] Nenhuma opção de parcelamento. Usando 1x fallback.");
                        const fallbackInstallment = [{ installments: 1, recommended_message: `1x de R$ ${purchaseAmount.toFixed(2).replace('.', ',')}` }];
                        createSelectOptions(installmentsSelect, fallbackInstallment, { label: "recommended_message", value: "installments", placeholder: "Escolha as parcelas", defaultSelectedValue: 1 });
                    } else {
                        console.log("[JS BIN CHECK] Populando parcelas...");
                        createSelectOptions(installmentsSelect, installmentsData[0].payer_costs, { label: "recommended_message", value: "installments", placeholder: "Escolha as parcelas" });
                    }
                     if (installmentsSelect) installmentsSelect.disabled = false; // Habilita o select de parcelas

                    if (installmentsData[0]?.issuer?.id) { if (issuerIdInput) issuerIdInput.value = installmentsData[0].issuer.id; } else { if (issuerIdInput) issuerIdInput.value = ''; }
                    console.log("[JS BIN CHECK] Verificação BIN concluída com sucesso.");
                    success = true;

                } catch (error) {
                    console.error('[JS BIN CHECK] Erro no bloco try:', error);
                    showStatus(`Erro: ${error.message || 'Não foi possível verificar o cartão.'}`, 'error', true);
                    resetCardDependentFields(); success = false;
                } finally {
                    console.log("[JS BIN CHECK] Executando finally. Sucesso:", success);
                    isCheckingBin = false;
                    showLoading(false, '', 'cardBinCheck');
                    checkAndToggleCardSubmitButton(); // Reavalia botão
                    console.log("[JS BIN CHECK] Finally concluído.");
                }
            }, 300);
        });


        // 3. Lidar com o Envio do Formulário de Cartão Customizado (mantido como v4.2)
        customCardForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isProcessingPayment || isCheckingBin) { console.warn(`[JS] Submit bloqueado: ${isProcessingPayment ? 'Pagamento' : 'BIN check'} em processamento.`); return; }
            isProcessingPayment = true; showLoading(true, 'Validando dados...', 'cardSubmit'); showStatus('Validando cartão...', 'info');
            const deviceId = getMercadoPagoDeviceId(); console.log('[JS] Device ID Capturado para Cartão (Custom):', deviceId ? deviceId.substring(0, 10) + '...' : 'NÃO ENCONTRADO');
            let errorDetail = ''; let paymentSuccessOrPending = false;
            try {
                console.log("[JS CARD SUBMIT] Iniciando try...");
                const tokenData = { cardholderName: cardPayerFirstNameInput?.value, identificationType: cardIdentificationTypeSelect.value, identificationNumber: cardIdentificationNumberInput.value.replace(/\D/g, ''), };
                console.log("[JS CARD SUBMIT] Dados (não sensíveis) para createCardToken:", tokenData);
                if (!tokenData.cardholderName || !tokenData.identificationType || !tokenData.identificationNumber) throw new Error("Preencha os dados do titular (Nome, Documento).");
                if (!cardNumberInput.value || !cardExpirationMonthInput.value || !cardExpirationYearInput.value || !securityCodeInput.value) throw new Error("Preencha todos os dados do cartão (Número, Validade, CVV).");
                const tokenResponse = await mp.createCardToken(tokenData);
                console.log('%c[JS CARD SUBMIT] Card Token CRIADO!', 'color: #00d16c; font-weight: bold;', tokenResponse);
                if (!tokenResponse.id) { throw new Error("Falha ao gerar o token do cartão."); }
                showLoading(true, 'Processando pagamento...', 'cardSubmit'); showStatus('Enviando para processamento...', 'info');
                const selectedInstallment = installmentsSelect.value; if (!selectedInstallment) throw new Error("Selecione o número de parcelas.");
                const bodyData = { token: tokenResponse.id, payment_method_id: paymentMethodIdInput.value, installments: parseInt(selectedInstallment), issuer_id: issuerIdInput.value || undefined, description: purchaseDescription, package_id: packageId, transaction_amount: purchaseAmount, payer: { email: cardPayerEmailInput.value, first_name: cardPayerFirstNameInput.value, identification: { type: cardIdentificationTypeSelect.value, number: cardIdentificationNumberInput.value.replace(/\D/g, '') } }, device_id: deviceId };
                console.log('[JS CARD SUBMIT] Enviando Backend:', JSON.stringify(bodyData));
                const response = await fetch(processPaymentUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(bodyData) });
                let paymentResult; errorDetail = `Status: ${response.status}`;
                if (response.headers.get('content-type')?.includes('application/json')) { paymentResult = await response.json(); errorDetail += ` - Resp: ${JSON.stringify(paymentResult)}`; } else { const errorText = await response.text(); console.error("[JS CARD SUBMIT] Resposta não-JSON:", response.status, errorText); errorDetail = `(${response.status}). Resp: ${errorText.substring(0, 200)}...`; throw new Error(`Servidor respondeu inesperadamente.`); }
                console.log('[JS CARD SUBMIT] Resultado backend:', paymentResult);
                if (response.ok && paymentResult.status === 'approved') { paymentSuccessOrPending = true; showLoading(false, '', 'cardSubmit'); showStatus(`🎉 Pagamento Aprovado! ID: ${paymentResult.id}`, 'success', true); customCardForm.style.opacity = '0.5'; customCardForm.style.pointerEvents = 'none'; setTimeout(() => { window.location.href = `${dashboardUrl}?payment=success&id=${paymentResult.id}&method=card`; }, 3500); }
                else if (response.ok && (paymentResult.status === 'in_process' || paymentResult.status === 'pending')) { paymentSuccessOrPending = true; showLoading(false, '', 'cardSubmit'); showStatus(`⏳ Pgto em processamento (Status: ${paymentResult.status || '?'}). ID: ${paymentResult.id || 'N/A'}.`, 'info', true); customCardForm.style.opacity = '0.5'; customCardForm.style.pointerEvents = 'none'; }
                else if (response.ok && paymentResult.status === 'rejected') { const detail = paymentResult.status_detail || '?'; let msg = 'Pagamento recusado.'; if (detail === 'cc_rejected_high_risk') msg = 'Recusado por segurança.'; else if (detail === 'cc_rejected_insufficient_amount') msg = 'Saldo insuficiente.'; else if (detail.includes('bad_filled')) msg = 'Dados cartão inválidos.'; else if (detail.includes('other') || detail.includes('duplicated')) msg = 'Recusado. Tente outro cartão.'; else if (detail === 'cc_rejected_call_for_authorize') msg = 'Recusado. Ligue para o banco.'; else if (detail === 'cc_rejected_card_disabled') msg = 'Cartão desabilitado.'; if (paymentResult.message && !paymentResult.message.toLowerCase().includes('rejected')) msg = paymentResult.message; console.warn(`[JS CARD SUBMIT] Pagamento REJEITADO. ID: ${paymentResult.id || 'N/A'}, Motivo: ${detail}`); showLoading(false, '', 'cardSubmit'); showStatus(`😕 ${msg} (Detalhe: ${detail})`, 'error', true); }
                else { const errMsg = paymentResult?.message || `Falha na comunicação (${response.status})`; throw new Error(`Erro: ${errMsg}`); }
            } catch (error) {
                console.error('[JS CARD SUBMIT] Falha no bloco try:', error); showLoading(false, '', 'cardSubmit'); let detailedErrorMsg = error.message || 'Falha ao validar/processar o cartão.'; if (error.cause && Array.isArray(error.cause) && error.cause.length > 0) { const mpError = error.cause[0]; if (mpError?.description) detailedErrorMsg = mpError.description; else if (mpError?.code) { if (['E301', '316', '205', '325', '326'].includes(String(mpError.code))) detailedErrorMsg = "Verifique os dados do cartão (Número, Validade)."; else if (['E302', '224', '703'].includes(String(mpError.code))) detailedErrorMsg = "Código de segurança (CVV) inválido."; else if (['212', '213', '214', '302', '221'].includes(String(mpError.code))) detailedErrorMsg = "Verifique os dados do titular (Nome, Documento)."; else detailedErrorMsg = `Erro MP: ${mpError.code} - ${mpError.description || 'Detalhe indisponível'}`; } }
                showStatus(`😕 Ops! ${detailedErrorMsg}`, 'error', true);
            } finally {
                console.log('[JS CARD SUBMIT] Executando finally. Sucesso/Pendente:', paymentSuccessOrPending);
                isProcessingPayment = false; // Libera estado principal
                checkAndToggleCardSubmitButton(); // Reavalia botão
                console.log('[JS CARD SUBMIT] Finally concluído.');
            }
        });


        // 4. Lidar com Envio Formulário PIX Manual (mantido como v4.2)
        if (pixForm) {
            pixForm.addEventListener('submit', async (e) => {
                e.preventDefault(); if (isProcessingPayment || isCheckingBin) { console.warn(`[JS] Submit PIX bloqueado: ${isProcessingPayment ? 'Pagamento' : 'BIN check'} em processamento.`); return; }
                const deviceId = getMercadoPagoDeviceId(); console.log('[JS] Device ID Capturado para PIX:', deviceId ? deviceId.substring(0, 10) + '...' : 'NÃO ENCONTRADO');
                let errorDetail = ''; let pixGenerated = false;
                isProcessingPayment = true; showLoading(true, 'Gerando Pix...', 'pixSubmit'); showStatus('Aguarde...', 'info'); if (pixResultContainer) pixResultContainer.style.display = 'none';
                const formData = new FormData(pixForm);
                const pixData = { transaction_amount: purchaseAmount, description: purchaseDescription, payment_method_id: 'pix', package_id: packageId, payer: { first_name: formData.get('payerFirstName')?.trim(), last_name: formData.get('payerLastName')?.trim(), email: formData.get('email')?.trim(), identification: { type: formData.get('identificationType'), number: formData.get('identificationNumber')?.replace(/\D/g, '') } }, device_id: deviceId };
                console.log("[JS PIX SUBMIT] Enviando dados gerar Pix:", pixData);
                if (!pixData.payer.first_name || !pixData.payer.last_name || !pixData.payer.email || !pixData.payer.identification.type || !pixData.payer.identification.number) { showLoading(false, '', 'pixSubmit'); showStatus('Preencha todos os campos (Nome/Email/Doc).', 'error'); isProcessingPayment = false; return; }
                try {
                    console.log("[JS PIX SUBMIT] Iniciando try...");
                    const response = await fetch(processPaymentUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(pixData) });
                    let paymentResult; errorDetail = `Status: ${response.status}`;
                    if (response.headers.get('content-type')?.includes('application/json')) { paymentResult = await response.json(); errorDetail += ` - Resp: ${JSON.stringify(paymentResult)}`; } else { const errorText = await response.text(); console.error("[JS PIX SUBMIT] Resposta não-JSON:", response.status, errorText); errorDetail = `(${response.status}). Resp: ${errorText.substring(0, 200)}...`; throw new Error(`Servidor respondeu inesperadamente.`); }
                    console.log('[JS PIX SUBMIT] Resultado backend:', paymentResult);
                    if (response.ok && paymentResult.status === 'pending' && paymentResult.pix_qr_code_base64 && paymentResult.pix_copia_cola) {
                        pixGenerated = true; showLoading(false, '', 'pixSubmit'); showStatus('Pix gerado! Aguardando pagamento.', 'success', true);
                        if (pixForm) pixForm.style.display = 'none';
                        if (pixQrCodeContainer) pixQrCodeContainer.innerHTML = `<img src="data:image/png;base64,${paymentResult.pix_qr_code_base64}" alt="QR Code Pix">`;
                        if (pixCopyCodeInput) pixCopyCodeInput.value = paymentResult.pix_copia_cola;
                        if (pixCopyButton) { pixCopyButton.disabled = false; const newBtn = pixCopyButton.cloneNode(true); if(!newBtn.querySelector('i')){ const i=document.createElement('i'); i.className='fas fa-copy'; newBtn.prepend(i); } pixCopyButton.parentNode.replaceChild(newBtn, pixCopyButton); newBtn.addEventListener('click',(ev)=>{ ev.preventDefault(); if(pixCopyCodeInput) copyToClipboard(pixCopyCodeInput.value, newBtn); }); } else console.warn("Botão copiar PIX não encontrado.");
                        if (pixExpirationText && paymentResult.pix_expiration_date) { try { const expDate = new Date(paymentResult.pix_expiration_date); const fmtDate = expDate.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year:'numeric', hour: '2-digit', minute: '2-digit' }); pixExpirationText.textContent = ` Expira em: ${fmtDate}`; if(pixExpirationInfo) pixExpirationInfo.style.display = 'block'; } catch (e) { console.error("Erro formatar data Pix:", e); if(pixExpirationInfo) pixExpirationInfo.style.display = 'none';} } else if (pixExpirationInfo) pixExpirationInfo.style.display = 'none';
                        if (pixResultContainer) { pixResultContainer.style.display = 'block'; try { pixResultContainer.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch(e){} }
                    } else { const errMsg = paymentResult?.message || `Falha obter dados Pix (${paymentResult?.status || '?'})`; throw new Error(`${errMsg}`); }
                } catch (error) {
                    console.error('[JS PIX SUBMIT] Falha no bloco try:', error); showLoading(false, '', 'pixSubmit'); showStatus(`😕 Ops! ${error.message || 'Não foi possível gerar o Pix.'}`, 'error', true); pixGenerated = false;
                } finally {
                    console.log('[JS PIX SUBMIT] Executando finally. PIX Gerado:', pixGenerated);
                    isProcessingPayment = false; // Libera estado principal
                    if(pixSubmitButton){ pixSubmitButton.disabled = pixGenerated; if(!pixGenerated){ pixSubmitButton.innerHTML = `<i class="fa-brands fa-pix"></i> Gerar Pix`; } }
                    console.log('[JS PIX SUBMIT] Finally concluído.');
                }
            });
        } else console.warn("[JS] Formulário PIX não encontrado.");

        // --- Lógica das Abas (mantida) ---
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                if ((isProcessingPayment || isCheckingBin) && loadingOverlay.classList.contains('show') ) { console.warn("[JS] Troca de aba bloqueada durante processamento ativo."); return; }
                const currentActiveTab = document.querySelector('.payment-method-tab.active');
                const currentActiveContainer = document.querySelector('.payment-method-container.active');
                const method = tab.getAttribute('data-method');
                const targetContainer = document.getElementById(`${method}-method-container`);
                if (!targetContainer || tab.classList.contains('active')) return;
                console.log(`[JS] Trocando para aba: ${method}`);
                currentActiveTab?.classList.remove('active'); currentActiveContainer?.classList.remove('active'); tab.classList.add('active'); targetContainer.classList.add('active');
                if (paymentStatusContainer && !(paymentStatusContainer.classList.contains('success') && pixResultContainer?.style.display === 'block') && !( (paymentStatusContainer.classList.contains('success') || paymentStatusContainer.classList.contains('info')) && customCardForm?.style.opacity === '0.5' ) ) { paymentStatusContainer.style.display = 'none'; paymentStatusContainer.innerHTML = ''; paymentStatusContainer.className = 'payment-status'; }
                if (method === 'pix') { if (pixResultContainer && pixResultContainer.style.display === 'block') { if(pixForm) pixForm.style.display = 'none'; } else { if(pixForm) pixForm.style.display = 'block'; } }
            });
        });

        // --- Inicialização da Página ---
        document.addEventListener('DOMContentLoaded', () => {
            console.log("[JS] DOM Carregado. Iniciando setup (Custom Card Form v4.4).");
            fetchIdentificationTypes(); // Busca tipos ID
            if (userFirstNameFromSession && pixPayerFirstNameInput) pixPayerFirstNameInput.value = userFirstNameFromSession;
            if (userLastNameFromSession && pixPayerLastNameInput) pixPayerLastNameInput.value = userLastNameFromSession;
            if (userFirstNameFromSession && cardPayerFirstNameInput) cardPayerFirstNameInput.value = userFirstNameFromSession;
            if (userEmailFromSession && cardPayerEmailInput) cardPayerEmailInput.value = userEmailFromSession;
            showLoading(false); // Garante tirar overlay
            checkAndToggleCardSubmitButton(); // Verifica estado inicial botão cartão
            const activeMethod = document.querySelector('.payment-method-tab.active')?.getAttribute('data-method');
            console.log(`[JS] Aba inicial: ${activeMethod}`);
        });
    </script>

</body>
</html>