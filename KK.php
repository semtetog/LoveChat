<!DOCTYPE html>
<html lang="pt-BR" translate="no">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <meta name="google" content="notranslate">
    <link rel="shortcut icon" type="image/ico" href="favicon.ico">
    <title>Titan</title>
    <style>
        :root { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        * { box-sizing: border-box; }
        body {
            --titan-background: #101212;
            --titan-green: #17fc00;
            position: absolute; inset: 0; margin: 0; padding: 0;
            background-color: var(--titan-background);
            background-image: radial-gradient(ellipse at center, #1c1f22 0%, #101212 70%);
            overflow: hidden;
            /* Correção para impedir a seleção de texto e o menu de contexto no iOS */
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
        }
        #splash-screen {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            width: 100%; height: 100%; padding: 20px; transition: opacity 0.3s ease-in-out;
        }
        .logo-container { flex-grow: 1; display: flex; align-items: center; justify-content: center; }
        .titan-logo {
            width: 256px; height: 256px; background-image: url('assets/img/logo-quadrado-1.png');
            background-size: contain; background-repeat: no-repeat; background-position: center;
        }
        #progress-container {
            width: 100%; max-width: 450px; padding: 4px; background-color: rgba(0, 0, 0, 0.5);
            border-radius: 8px; border: 2px solid #333; box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative; margin-bottom: 50px;
        }
        #progress-bar {
            width: 0%; height: 20px; background-color: var(--titan-green);
            border-radius: 4px; transition: width 0.2s linear;
        }
        #progress-text {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            color: white; font-weight: bold; text-shadow: 1px 1px 2px black;
        }
        .titan-popup-overlay {
            position: fixed; inset: 0; background-color: rgba(0, 0, 0, 0.7);
            display: flex; align-items: center; justify-content: center; z-index: 1000;
            padding: 20px; opacity: 0; pointer-events: none; transition: opacity .2s ease-in-out;
        }
        .titan-popup-overlay--ativo { opacity: 1; pointer-events: auto; }
        .titan-popup {
            background-color: #1c1d1e; border-radius: 12px; padding: 32px;
            display: flex; flex-direction: column; gap: 24px; text-align: center;
            border: 1px solid #3d3f40; width: 100%; max-width: 400px;
            transform: scale(0.95); transition: transform .2s ease-in-out;
        }
        .titan-popup-overlay--ativo .titan-popup { transform: scale(1); }
        .titan-popup__titulo { font-size: 20px; font-weight: 700; color: #f8fbf8; }
        .titan-popup__mensagem { font-size: 16px; line-height: 1.5; color: #dcdfdc; }
        .titan-botao {
            display: inline-block; background-color: var(--titan-green); color: #101212;
            padding: 16px 24px; border: 2px solid #00d400; border-radius: 4px;
            font-weight: 700; text-transform: uppercase; user-select: none;
            text-align: center; cursor: pointer; text-decoration: none;
        }
    </style>
</head>
<body>
    <div id="splash-screen">
        <div class="logo-container">
            <div class="titan-logo"></div>
        </div>
        <div id="progress-container">
            <div id="progress-bar"></div>
            <span id="progress-text">0%</span>
        </div>
    </div>
    <div class="titan-popup-overlay" id="popup-overlay">
        <div class="titan-popup">
            <h3 class="titan-popup__titulo">Falha na Conexão</h3>
            <p class="titan-popup__mensagem">Você não está conectado à internet. Por favor, verifique sua conexão para tentar novamente.</p>
            <button class="titan-botao" id="popup-close-button">Tentar Novamente</button>
        </div>
    </div>

    <script src="capacitor.js"></script>
    <script>
        const splashScreen = document.getElementById('splash-screen');
        const popupOverlay = document.getElementById('popup-overlay');
        const retryButton = document.getElementById('popup-close-button');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');

        let currentProgress = 0;

        function animateProgress(target, duration) {
            return new Promise(resolve => {
                const start = currentProgress;
                const change = target - start;
                const increment = 20 / duration;
                let progress = 0;

                function update() {
                    progress += increment;
                    currentProgress = start + (change * Math.min(progress, 1));
                    progressBar.style.width = Math.floor(currentProgress) + '%';
                    progressText.textContent = Math.floor(currentProgress) + '%';

                    if (progress < 1) {
                        setTimeout(update, 20);
                    } else {
                        progressBar.style.width = target + '%';
                        progressText.textContent = target + '%';
                        currentProgress = target;
                        resolve();
                    }
                }
                update();
            });
        }

        async function checkNetwork() {
            try {
                if (window.Capacitor && Capacitor.isPluginAvailable('Network')) {
                    const status = await Capacitor.Plugins.Network.getStatus();
                    return status.connected;
                }
                return navigator.onLine;
            } catch (e) {
                console.error("Erro no checkNetwork:", e);
                return navigator.onLine;
            }
        }

        async function initializeApp() {
            currentProgress = 0;
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
            splashScreen.style.opacity = 1;
            popupOverlay.classList.remove('titan-popup-overlay--ativo');
            
            await animateProgress(75, 1500);

            const isOnline = await checkNetwork();

            if (isOnline) {
                await animateProgress(100, 500);
                window.location.replace('https://inteligenciatitan.com.br');
            } else {
                splashScreen.style.opacity = 0;
                setTimeout(() => popupOverlay.classList.add('titan-popup-overlay--ativo'), 300);
            }
        }
        
        retryButton.addEventListener('click', initializeApp);
        
        document.addEventListener('DOMContentLoaded', async () => {
            if (window.Capacitor && Capacitor.isNativePlatform()) {
                await setupNativeFeatures();
            }
            initializeApp();
        });

        // --- FUNÇÕES NATIVAS DO CAPACITOR (CÓDIGO COMPLETO) ---

        async function setupNativeFeatures() {
            const { StatusBar } = Capacitor.Plugins;
            try {
                await StatusBar.setOverlaysWebView({ overlay: false });
                await StatusBar.setStyle({ style: 'dark' });
                await StatusBar.setBackgroundColor({ color: '#101212' });
            } catch (e) { console.error('Falha ao configurar a status bar:', e); }

            await setupPushNotifications();
            await setupBiometriaInjetada();
        }

        async function setupPushNotifications() {
            console.log("APP: Configurando Notificações Push...");
            const { PushNotifications } = Capacitor.Plugins;
            try {
                let permStatus = await PushNotifications.checkPermissions();
                if (permStatus.receive === 'prompt') { permStatus = await PushNotifications.requestPermissions(); }
                if (permStatus.receive !== 'granted') { console.warn('Permissão de Push negada.'); return; }
                await PushNotifications.register();
                PushNotifications.addListener('registration', (token) => {
                    console.log('TOKEN DE PUSH:', token.value);
                });
                PushNotifications.addListener('registrationError', (error) => {
                    console.error('Erro no registro de Push:', error);
                });
            } catch(e) { console.error("Erro ao configurar push notifications", e); }
        }
        
        async function setupBiometriaInjetada() {
            console.log("APP: Configurando Biometria...");
            const { App, BiometricAuth, WebView, Preferences, Dialog } = Capacitor.Plugins;

            App.addListener('biometricLoginRequested', async () => {
                console.log('APP: Sinal de biometria recebido!');
                try {
                    const { value: savedUser } = await Preferences.get({ key: 'titanUser' });
                    const { value: savedPass } = await Preferences.get({ key: 'titanPass' });
                    if (!savedUser || !savedPass) {
                        await Dialog.alert({
                            title: 'Login Necessário',
                            message: 'Nenhuma credencial salva. Faça login manualmente uma vez para ativar a biometria.'
                        });
                        return;
                    }
                    await BiometricAuth.verify({ reason: 'Acesse sua conta Titan' });
                    const scriptDeLogin = `
                        document.querySelector('#username').value = '${savedUser}';
                        document.querySelector('#password').value = '${savedPass}';
                        document.querySelector('.titan-login__formulario').submit();
                    `;
                    await WebView.evaluateJavascript({ script: scriptDeLogin });
                } catch (error) { console.error('APP: Falha na biometria.', error); }
            });

            WebView.addListener('pageLoaded', () => {
                if (window.location.href.startsWith('https://inteligenciatitan.com.br')) {
                    console.log('APP: Site remoto carregado! Injetando scripts...');
                    const scriptDeInjecao = `
                        if (!document.getElementById('biometric-login-btn-app')) {
                            const biometricButton = document.createElement('button');
                            biometricButton.innerText = 'Entrar com Digital / Rosto';
                            biometricButton.id = 'biometric-login-btn-app';
                            biometricButton.className = 'titan-botao'; 
                            biometricButton.style.backgroundColor = '#007aff';
                            biometricButton.style.borderColor = '#0062cc';
                            biometricButton.style.color = 'white';
                            biometricButton.style.marginTop = '8px';
                            biometricButton.onclick = (e) => { e.preventDefault(); window.Capacitor.Plugins.App.fireAppEvent('biometricLoginRequested'); };
                            
                            const loginForm = document.querySelector('.titan-login__formulario');
                            if (loginForm) {
                                loginForm.appendChild(biometricButton);
                                console.log('SITE: Botão de biometria injetado.');
                            } else {
                                console.error('SITE: Formulário de login (.titan-login__formulario) não encontrado.');
                            }
                        }
                        
                        if (!window.loginFormListenerAttached) {
                            const form = document.querySelector('.titan-login__formulario');
                            if(form) {
                                form.addEventListener('submit', () => {
                                    const user = form.querySelector('#username').value;
                                    const pass = form.querySelector('#password').value;
                                    if (user && pass) {
                                        window.Capacitor.Plugins.App.fireAppEvent('saveCredentials', { user, pass });
                                    }
                                });
                                window.loginFormListenerAttached = true;
                            }
                        }
                    `;
                    WebView.evaluateJavascript({ script: scriptDeInjecao });
                }
            });

            App.addListener('saveCredentials', async (data) => {
                try {
                    const user = data.user;
                    const pass = data.pass;
                    const { value } = await Dialog.confirm({
                        title: 'Login Rápido',
                        message: 'Deseja salvar suas credenciais para usar o login com biometria no futuro?',
                        okButtonTitle: 'Salvar',
                        cancelButtonTitle: 'Não, obrigado'
                    });

                    if (value) {
                        await Preferences.set({ key: 'titanUser', value: user });
                        await Preferences.set({ key: 'titanPass', value: pass });
                        await Dialog.alert({
                            title: 'Salvo!',
                            message: 'Credenciais salvas. No próximo acesso, você poderá usar a biometria.'
                        });
                    }
                } catch(e) { console.error("Erro ao salvar credenciais", e); }
            });
        }
    </script>
</body>
</html>```