<?php
// cardapio_auto/login.php OU home.php OU index.php OU outro_arquivo.php
// --- Bloco Padronizado de Configuração de Sessão ---
// Certifique-se de que este bloco esteja no TOPO ABSOLUTO do arquivo, antes de QUALQUER saída.

$session_cookie_path = '/cardapio_auto/'; // Caminho CONSISTENTE para este aplicativo
$session_name = "CARDAPIOSESSID";        // Nome CONSISTENTE da sessão

// Tenta configurar os parâmetros ANTES de iniciar, se a sessão ainda não existir
if (session_status() === PHP_SESSION_NONE) {
    // Remova o @ durante o desenvolvimento para ver possíveis erros/avisos
    session_set_cookie_params([
        'lifetime' => 0, // Expira ao fechar navegador
        'path' => $session_cookie_path,
        'domain' => $_SERVER['HTTP_HOST'] ?? '', // Usa o domínio atual (geralmente seguro)
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // True se HTTPS
        'httponly' => true, // Ajuda a prevenir XSS (importante!)
        'samesite' => 'Lax' // Boa proteção CSRF padrão ('Strict' é mais seguro mas pode quebrar links externos)
    ]);
}

session_name($session_name); // Define o nome da sessão

// Inicia a sessão APENAS se ela ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
     // Remova o @ durante o desenvolvimento
     session_start();
}            // Primeira coisa lógica

// Configurações de erro para ESTA PÁGINA
error_reporting(E_ALL);
// MUDE PARA 0 EM PRODUÇÃO FINAL! Deixe 1 durante o desenvolvimento.
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Iniciando register.php ---"); // Log inicial

// Variáveis iniciais
$errors = [];
$username_input = '';
$email_input = '';
$page_title = "Registrar - Montador Cardápio";
$db_connection_error = false;
$pdo = null;

// Tenta conectar ao BD
try {
    require_once 'includes/db_connect.php'; // Define $pdo
    error_log("DEBUG Register: db_connect.php incluído com sucesso.");
} catch (\PDOException $e) {
    $db_connection_error = true;
    $errors[] = "Erro crítico [DB Connect]: Não foi possível conectar ao banco de dados.";
    error_log("CRITICAL Register: Falha ao incluir/conectar db_connect.php - " . $e->getMessage());
} catch (\Throwable $th) {
     $db_connection_error = true;
     $errors[] = "Erro crítico [Include]: Falha ao carregar dependências.";
     error_log("CRITICAL Register: Falha Throwable nos includes iniciais - " . $th->getMessage());
}

// --- Lógica de Registro (APENAS se a conexão foi OK) ---
if (!$db_connection_error) {

     // Se já estiver logado, redireciona para home
     if (isset($_SESSION['user_id'])) {
         error_log("DEBUG Register: Usuário já logado (ID: ".$_SESSION['user_id']."). Redirecionando para home.");
         header('Location: home.php');
         exit;
     }

    // LOG PARA VER O MÉTODO DA REQUISIÇÃO
    error_log("DEBUG Register: Método da Requisição = " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));

    // Processa o formulário POST
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        error_log("DEBUG Register: Recebido POST."); // Confirma entrada no bloco POST
        $username_input = trim($_POST['username'] ?? '');
        $email_input = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        error_log("DEBUG Register: Dados recebidos - User:[$username_input], Email:[$email_input]");

        // Validações
        if (empty($username_input)) $errors[] = "Nome de usuário é obrigatório.";
        if (empty($email_input)) $errors[] = "Email é obrigatório.";
        elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) $errors[] = "Formato de email inválido.";
        if (empty($password)) $errors[] = "Senha é obrigatória.";
        if ($password !== $password_confirm) $errors[] = "As senhas não coincidem.";
        if (strlen($password) < 6) $errors[] = "A senha deve ter pelo menos 6 caracteres."; // Mantido 6 por simplicidade

        if(!empty($errors)) {
            error_log("DEBUG Register: Erros de validação encontrados: " . print_r($errors, true));
        }

        // Verificar se usuário ou email já existem (APENAS se não houver erros de validação)
        if (empty($errors)) {
            error_log("DEBUG Register: Iniciando verificação de duplicidade...");
            $email_exists = false;
            $username_exists = false;
            try {
                // Verifica email
                $sql_check_email = "SELECT 1 FROM cardapio_usuarios WHERE email = :email LIMIT 1";
                $stmt_check_email = $pdo->prepare($sql_check_email);
                $stmt_check_email->bindParam(':email', $email_input, PDO::PARAM_STR);
                $stmt_check_email->execute();
                if ($stmt_check_email->fetch()) {
                    $errors[] = "Este e-mail já está cadastrado.";
                    $email_exists = true;
                    error_log("DEBUG Register: Email duplicado encontrado - " . $email_input);
                } else {
                     error_log("DEBUG Register: Email [$email_input] não encontrado (OK).");
                }

                // Verifica username (só se email não for duplicado)
                if(!$email_exists) {
                    $sql_check_user = "SELECT 1 FROM cardapio_usuarios WHERE username = :username LIMIT 1";
                    $stmt_check_user = $pdo->prepare($sql_check_user);
                    $stmt_check_user->bindParam(':username', $username_input, PDO::PARAM_STR);
                    $stmt_check_user->execute();
                    if ($stmt_check_user->fetch()) {
                        $errors[] = "Este nome de usuário já está em uso.";
                        $username_exists = true;
                        error_log("DEBUG Register: Username duplicado encontrado - " . $username_input);
                    } else {
                         error_log("DEBUG Register: Username [$username_input] não encontrado (OK).");
                    }
                }

            } catch (PDOException $e) {
                $errors[] = "Erro [DB Check]: Não foi possível verificar os dados. Tente novamente.";
                error_log("CRITICAL Register (Check User/Email): " . $e->getMessage());
            }
        }

        // Se não houver erros ATÉ AQUI, tentar inserir
        if (empty($errors)) {
            error_log("DEBUG Register: Sem erros de validação ou duplicidade. Tentando hashear senha...");
            $password_hash = password_hash($password, PASSWORD_DEFAULT); // Use PASSWORD_DEFAULT

            if ($password_hash === false) {
                 $errors[] = "Erro crítico [Hash]: Não foi possível processar a senha.";
                 error_log("CRITICAL Register: Falha no password_hash para usuario: " . $username_input);
            } else {
                error_log("DEBUG Register: Hash gerado com sucesso (" . strlen($password_hash) . " chars). Tentando inserir no BD...");
                try {
                    $sql_insert = "INSERT INTO cardapio_usuarios (username, email, password_hash) VALUES (:username, :email, :password_hash)";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->bindParam(':username', $username_input, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':email', $email_input, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);

                    if ($stmt_insert->execute()) {
                        $newUserId = $pdo->lastInsertId();
                        error_log("SUCESSO Register: Usuário registrado com ID: " . $newUserId . " para Username: " . $username_input);
                        $_SESSION['success_message'] = "Registro realizado com sucesso! Faça o login.";
                         ob_start(); // Buffer para header
                         header("Location: login.php");
                         ob_end_flush();
                        exit;
                    } else {
                        $errors[] = "Erro [DB Insert]: Falha ao registrar usuário.";
                        error_log("CRITICAL Register: Falha na execução do INSERT para " . $username_input . ". Erro PDO Info: " . print_r($stmt_insert->errorInfo(), true));
                    }
                } catch (PDOException $e) {
                     $errors[] = "Erro [DB Insert Ex]: Falha no banco de dados ao registrar.";
                     error_log("CRITICAL Register (Insert PDOException): " . $e->getMessage() . " | Code: " . $e->getCode());
                     if ($e->getCode() == 23000) {
                         $errors[] = "Erro: Nome de usuário ou e-mail já existe [DB constraint].";
                     }
                }
            }
        }
    }
} // Fim do if (!$db_connection_error)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* === COLOQUE SEU CSS COMPLETO AQUI === */
        /* Copie todo o CSS do <style> do login.php que você me enviou */
         :root { /* Suas variáveis CSS */
            --font-primary: 'Poppins', sans-serif; --font-secondary: 'Roboto', sans-serif; --font-size-base: 14px;
            --primary-color: #005A9C; --primary-dark: #003A6A; --primary-light: #4D94DB; --accent-color: #EBF4FF;
            /* ... resto das variáveis ... */
             --secondary-color: #6c757d; --secondary-light: #adb5bd; --bg-color: #F8F9FA; --card-bg: #FFFFFF; --text-color: #343a40; --text-light: #6c757d; --border-color: #DEE2E6; --light-border: #E9ECEF; --success-color: #28a745; --success-light: #e2f4e6; --success-dark: #1e7e34; --warning-color: #ffc107; --warning-light: #fff8e1; --warning-dark: #d39e00; --error-color: #dc3545;   --error-light: #f8d7da;   --error-dark: #a71d2a; --info-color: #17a2b8;    --info-light: #d1ecf1;    --info-dark: #117a8b; --white-color: #FFFFFF; --border-radius: 6px; --box-shadow: 0 2px 10px rgba(0, 90, 156, 0.08); --box-shadow-hover: 0 5px 15px rgba(0, 90, 156, 0.12); --transition-speed: 0.2s;
        }
        html, body { height: 100%; }
        body { font-family: var(--font-secondary); line-height: 1.6; margin: 0; background-color: var(--bg-color); color: var(--text-color); font-size: var(--font-size-base); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100%; padding: 20px;}
        .auth-container { width: 100%; max-width: 450px; margin: 20px auto; padding: 30px; background-color: var(--card-bg); border-radius: var(--border-radius); box-shadow: var(--box-shadow-hover); border: 1px solid var(--light-border); }
        .auth-container h1 { text-align: center; margin-top: 0; margin-bottom: 25px; font-size: 1.8em; color: var(--primary-dark); font-family: var(--font-primary); font-weight: 600; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9em; color: var(--primary-dark); }
        .auth-input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 1em; transition: border-color var(--transition-speed), box-shadow var(--transition-speed); box-sizing: border-box; }
        .auth-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.15); outline: none; }
        .auth-button { display: block; width: 100%; padding: 12px 20px; background-color: var(--primary-color); color: var(--white-color); border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 1.1em; font-weight: 500; font-family: var(--font-primary); transition: background-color var(--transition-speed); text-transform: uppercase; }
        .auth-button:hover { background-color: var(--primary-dark); }
        .auth-link { text-align: center; margin-top: 20px; font-size: 0.9em; }
        .auth-link a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .auth-link a:hover { text-decoration: underline; }
        .error-message { background-color: var(--error-light); color: var(--error-dark); padding: 12px 15px; border: 1px solid var(--error-color); border-radius: var(--border-radius); margin-bottom: 20px; font-size: 0.9em; text-align: left; }
        .error-message p { margin: 5px 0; }
        .success-message { background-color: var(--success-light); color: var(--success-dark); padding: 12px 15px; border: 1px solid var(--success-color); border-radius: var(--border-radius); margin-bottom: 20px; font-size: 0.9em; text-align: center; }
        .error-container { background-color: var(--card-bg); padding: 30px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); text-align: center; border: 1px solid var(--error-color); max-width: 500px; width: 90%; margin: 20px auto; }
        .error-container h1 { color: var(--error-dark); margin-bottom: 15px; font-family: var(--font-primary); font-size: 1.5em; display: flex; align-items: center; justify-content: center; }
        .error-container h1 i { margin-right: 10px; color: var(--error-color); }
        .error-container p { color: var(--text-light); margin-bottom: 10px; font-size: 0.95em; }
        .error-container p small { font-size: 0.85em; color: var(--secondary-color); }
    </style>
</head>
<body>

    <?php if ($db_connection_error): ?>
        <div class="error-container">
             <h1><i class="fas fa-database"></i>Erro Crítico</h1>
             <p>Não foi possível conectar ao banco de dados.</p>
             <p>O registro não pode ser concluído neste momento.</p>
             <p><small>(Detalhes técnicos foram registrados nos logs do servidor.)</small></p>
         </div>
    <?php else: ?>
        <main class="main-content" style="width:100%; display: flex; justify-content: center; align-items: center;">
            <div class="auth-container">
                <h1>Registrar Nova Conta</h1>

                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário aponta para si mesmo -->
                <form action="register.php" method="post" novalidate>
                    <div class="form-group">
                        <label for="username">Nome de Usuário:</label>
                        <input type="text" id="username" name="username" class="auth-input" value="<?php echo htmlspecialchars($username_input ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" class="auth-input" value="<?php echo htmlspecialchars($email_input ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Senha (mín. 6 caracteres):</label>
                        <input type="password" id="password" name="password" class="auth-input" required>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirmar Senha:</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="auth-input" required>
                    </div>
                    <button type="submit" class="auth-button">Registrar</button>
                </form>
                <p class="auth-link">Já tem uma conta? <a href="login.php">Faça o login</a></p>
            </div>
        </main>
    <?php endif; ?>

     <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
     <script>
         console.log("Página de registro (PHP Autocontido) carregada.");
         // Adicione aqui qualquer JS específico da página de registro, se necessário
     </script>

</body>
</html>