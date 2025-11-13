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
}

// Configurações de erro para ESTA PÁGINA
error_reporting(E_ALL);
// MUDE PARA 0 EM PRODUÇÃO FINAL! Deixe 1 durante o desenvolvimento.
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// Garante que o caminho do log é absoluto e correto
ini_set('error_log', __DIR__ . '/php_error.log');

// Log inicial da página para depuração
error_log("--- Acesso a login.php --- | Session ID atual: " . session_id());

// Variáveis iniciais
$errors = [];
$username_input = ''; // Input do usuário
$success_message = '';
$page_title = "Login - Montador Cardápio";
$db_connection_error = false;
$pdo = null;

// --- Verificação de Sessão Ativa (ANTES de tentar conectar ao BD) ---
// Se já estiver logado, redireciona IMEDIATAMENTE.
// Isso evita processamento desnecessário e possíveis loops.
if (isset($_SESSION['user_id'])) {
    error_log("DEBUG (login.php): Usuário já logado (ID: " . $_SESSION['user_id'] . ", Username: " . ($_SESSION['username'] ?? 'N/A') . "). Redirecionando para home.php.");
    // Garante que não haja saída antes do header
    ob_start(); // Inicia buffer de saída, se ainda não estiver ativo
    header('Location: home.php');
    ob_end_flush(); // Envia o buffer (incluindo o header)
    exit; // ESSENCIAL parar o script aqui
}

// --- Mensagem de Sucesso (Ex: vindo do registro) ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    // Limpa a mensagem para não exibir novamente
    unset($_SESSION['success_message']);
    // Recomenda-se salvar a sessão após unset se outras operações ocorrerem
    // session_write_close(); session_start(); // Ou apenas deixar para o final do script
}

// --- Conexão com Banco de Dados ---
try {
    require_once 'includes/db_connect.php'; // Define $pdo
    if (!$pdo) {
        // Se db_connect.php não definir $pdo por algum motivo interno (sem lançar exceção)
        throw new \RuntimeException("Falha ao obter objeto PDO de db_connect.php");
    }
    error_log("DEBUG (login.php): Conexão com BD estabelecida.");
} catch (\PDOException $e) {
    $db_connection_error = true;
    // Log detalhado já deve ter sido feito por db_connect.php ou pelo catch PDOException abaixo
    error_log("CRITICAL (login.php): PDOException ao conectar ao BD: " . $e->getMessage());
} catch (\Throwable $e) { // Captura outros erros/exceções do require_once
    $db_connection_error = true;
    error_log("CRITICAL (login.php): Erro geral ao incluir/conectar BD: " . $e->getMessage());
}

// --- Processamento do Formulário de Login (APENAS se conexão OK) ---
if (!$db_connection_error && $_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("--- Tentativa de Login (POST recebido) ---");

    // Limpa e obtém dados do POST
    $username_input = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    error_log("Username recebido do form: '" . $username_input . "'");
    error_log("Senha recebida (existe?): " . (!empty($password) ? 'Sim' : 'Não'));

    // Validação básica
    if (empty($username_input)) {
        $errors[] = "O nome de usuário é obrigatório.";
    }
    if (empty($password)) {
        $errors[] = "A senha é obrigatória.";
    }

    // Se não houver erros de validação, tenta autenticar
    if (empty($errors)) {
        try {
            $sql = "SELECT id, username, password_hash FROM cardapio_usuarios WHERE username = :username LIMIT 1";
            $stmt = $pdo->prepare($sql);

            if (!$stmt) {
                 throw new \RuntimeException("Falha ao preparar a consulta SQL de login.");
            }

            $stmt->bindParam(':username', $username_input, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC é comum

            if ($user) {
                error_log("Usuário encontrado no BD: ID=" . $user['id'] . ", Username=" . $user['username']);
                error_log("Verificando senha para o usuário '" . $user['username'] . "'...");

                // Verifica a senha usando password_verify
                if (password_verify($password, $user['password_hash'])) {
                    error_log("SUCESSO: password_verify() retornou true para usuário ID: " . $user['id']);

                    // --- Etapa Crítica: Gerenciamento da Sessão Pós-Login ---

                    // 1. Regenera o ID da sessão para prevenir Session Fixation.
                    //    'true' tenta deletar o arquivo da sessão antiga.
                    if (!session_regenerate_id(true)) {
                        // Logar falha é importante, mas talvez não precise parar o login
                        error_log("AVISO: session_regenerate_id(true) FALHOU durante o login para usuário ID: " . $user['id'] . ". O ID da sessão pode não ter sido regenerado.");
                        // Poderia adicionar um $errors[] aqui se for crítico
                    } else {
                         error_log("Sessão regenerada com sucesso. Novo Session ID: " . session_id());
                    }

                    // 2. Limpa COMPLETAMENTE a superglobal $_SESSION ANTES de definir os novos valores.
                    //    Isso garante que nenhum dado da sessão anônima anterior permaneça.
                    $_SESSION = array();
                    error_log("Superglobal \$_SESSION limpa.");

                    // 3. Define os dados da sessão para o usuário logado.
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username']; // Usar o do BD garante consistência de maiúsculas/minúsculas
                    // Pode adicionar mais dados se necessário (ex: nível de acesso)
                    error_log("Novos dados da sessão DEFINIDOS: user_id=" . $_SESSION['user_id'] . ", username=" . $_SESSION['username']);

                    // 4. Fecha a escrita da sessão ANTES do redirecionamento.
                    //    Isso garante que os dados sejam salvos antes que o script termine.
                    session_write_close();
                    error_log("session_write_close() chamado.");

                    // 5. Redireciona para a página principal do usuário logado.
                    //    Use ob_start/ob_end_flush para segurança extra contra saídas acidentais.
                    ob_start();
                    error_log("Redirecionando para home.php...");
                    header("Location: home.php");
                    ob_end_flush();
                    exit; // ESSENCIAL parar a execução aqui.

                } else {
                    // Senha incorreta
                    $errors[] = "Nome de usuário ou senha inválidos.";
                    error_log("ERRO LOGIN: password_verify() retornou false para username: '" . $username_input . "' (Usuário ID: " . $user['id'] . ")");
                }
            } else {
                // Usuário não encontrado
                $errors[] = "Nome de usuário ou senha inválidos."; // Mensagem genérica por segurança
                error_log("ERRO LOGIN: Usuário não encontrado no BD para username: '" . $username_input . "'");
            }

        } catch (PDOException $e) {
            $errors[] = "Erro no banco de dados ao tentar fazer login. Tente novamente mais tarde.";
            error_log("CRITICAL (Login Query PDOException): " . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A'));
        } catch (\Throwable $e) { // Captura outros erros inesperados
            $errors[] = "Ocorreu um erro inesperado durante o login. Tente novamente.";
            error_log("CRITICAL (Login Logic Error): " . $e->getMessage());
        }
    } else {
         // Log dos erros de validação
         error_log("ERRO LOGIN: Falha na validação dos campos: " . implode("; ", $errors));
    }

} // Fim do if (!$db_connection_error && POST)

// Se chegou aqui, ou não foi POST, ou houve erro de conexão, ou falha no login. Renderiza a página HTML.
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
        /* --- Variáveis Globais CSS --- */
        :root {
            --font-primary: 'Poppins', sans-serif;
            --font-secondary: 'Roboto', sans-serif;
            --font-size-base: 14px;
            --primary-color: #005A9C;
            --primary-dark: #003A6A;
            --primary-light: #4D94DB;
            --accent-color: #EBF4FF;
            --secondary-color: #6c757d;
            --secondary-light: #adb5bd;
            --bg-color: #F8F9FA;
            --card-bg: #FFFFFF;
            --text-color: #343a40;
            --text-light: #6c757d;
            --border-color: #DEE2E6;
            --light-border: #E9ECEF;
            --success-color: #28a745;
            --success-light: #e2f4e6;
            --success-dark: #1e7e34;
            --warning-color: #ffc107;
            --warning-light: #fff8e1;
            --warning-dark: #d39e00;
            --error-color: #dc3545;
            --error-light: #f8d7da;
            --error-dark: #a71d2a;
            --info-color: #17a2b8;
            --info-light: #d1ecf1;
            --info-dark: #117a8b;
            --white-color: #FFFFFF;
            --border-radius: 6px;
            --box-shadow: 0 2px 10px rgba(0, 90, 156, 0.08);
            --box-shadow-hover: 0 5px 15px rgba(0, 90, 156, 0.12);
            --transition-speed: 0.2s;
        }

        /* --- Estilos Gerais --- */
         *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: var(--font-secondary);
            line-height: 1.6;
            margin: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-size: var(--font-size-base);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100%;
            padding: 20px;
        }

        /* --- Estilos para Login/Registro --- */
        .auth-container {
            width: 100%;
            max-width: 450px;
            margin: 20px auto;
            padding: 30px 35px; /* Aumentei um pouco o padding lateral */
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-hover);
            border: 1px solid var(--light-border);
        }
        .auth-container h1 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 30px; /* Mais espaço */
            font-size: 1.8em;
            color: var(--primary-dark);
            font-family: var(--font-primary);
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px; /* Mais espaço */
            font-weight: 500;
            font-size: 0.95em; /* Ligeiramente maior */
            color: var(--primary-dark);
        }
        .auth-input {
            width: 100%;
            padding: 12px 15px; /* Mais padding interno */
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1em;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
            box-sizing: border-box;
            background-color: #fff; /* Garante fundo branco */
        }
        .auth-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.15);
            outline: none;
        }
        .auth-button {
            display: block;
            width: 100%;
            padding: 14px 20px; /* Botão mais robusto */
            background-color: var(--primary-color);
            color: var(--white-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600; /* Mais destaque */
            font-family: var(--font-primary);
            transition: background-color var(--transition-speed), transform var(--transition-speed);
            text-transform: uppercase;
            margin-top: 10px; /* Espaço acima do botão */
        }
        .auth-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px); /* Efeito sutil ao passar o mouse */
        }
         .auth-button:active {
             transform: translateY(0); /* Remove o efeito ao clicar */
         }
        .auth-link {
            text-align: center;
            margin-top: 25px; /* Mais espaço */
            font-size: 0.9em;
        }
        .auth-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600; /* Destaque link */
        }
        .auth-link a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        /* Mensagens */
        .message-box {
            padding: 12px 15px;
            border: 1px solid transparent;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            font-size: 0.9em;
            text-align: left;
        }
        .message-box p { margin: 5px 0; }
        .error-message {
            background-color: var(--error-light);
            color: var(--error-dark);
            border-color: var(--error-color);
        }
        .success-message {
            background-color: var(--success-light);
            color: var(--success-dark);
            border-color: var(--success-color);
            text-align: center; /* Mensagem de sucesso pode ser centralizada */
        }

        /* Erro de conexão DB */
         .error-container {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            border: 1px solid var(--error-color);
            max-width: 500px;
            width: 90%;
            margin: 20px auto;
         }
         .error-container h1 {
            color: var(--error-dark);
            margin-bottom: 15px;
            font-family: var(--font-primary);
            font-size: 1.5em;
            display: flex;
            align-items: center;
            justify-content: center;
         }
         .error-container h1 i {
             margin-right: 10px;
             color: var(--error-color);
             font-size: 1.2em; /* Ícone um pouco maior */
         }
         .error-container p {
            color: var(--text-light);
            margin-bottom: 10px;
            font-size: 0.95em;
         }
          .error-container p small {
             display: block; /* Quebra linha */
             margin-top: 15px; /* Espaço */
             font-size: 0.85em;
             color: var(--secondary-color);
          }
    </style>
</head>
<body>

    <?php if ($db_connection_error): ?>
        <div class="error-container">
             <h1><i class="fas fa-database fa-fw"></i>Erro Crítico de Conexão</h1>
             <p>Não foi possível estabelecer uma conexão com o banco de dados.</p>
             <p>O sistema pode estar temporariamente indisponível ou em manutenção.</p>
             <p>Por favor, tente novamente mais tarde ou contate o suporte técnico.</p>
             <p><small>(Detalhes técnicos foram registrados para análise.)</small></p>
         </div>
    <?php else: ?>
        <main class="main-content" style="width:100%; display: flex; justify-content: center; align-items: center;">
            <div class="auth-container">
                <h1><i class="fas fa-sign-in-alt" style="margin-right: 10px; color: var(--primary-light);"></i>Entrar</h1>

                 <?php if ($success_message): ?>
                    <div class="message-box success-message"><p><i class="fas fa-check-circle" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($success_message); ?></p></div>
                 <?php endif; ?>

                 <?php if (!empty($errors)): ?>
                    <div class="message-box error-message">
                        <p style="font-weight: bold; margin-bottom: 8px;"><i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i> Erro ao tentar entrar:</p>
                        <?php foreach ($errors as $error): ?>
                            <p style="margin-left: 15px;">• <?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                 <?php endif; ?>

                <form action="login.php" method="post" novalidate>
                    <div class="form-group">
                        <label for="username">Nome de Usuário</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_input); ?>" class="auth-input" required autocomplete="username" autofocus>
                        <!-- autocomplete="username" ajuda navegadores -->
                        <!-- autofocus ajuda na usabilidade -->
                    </div>
                    <div class="form-group">
                        <label for="password">Senha</label>
                        <input type="password" id="password" name="password" class="auth-input" required autocomplete="current-password">
                         <!-- autocomplete="current-password" ajuda navegadores -->
                    </div>
                    <button type="submit" class="auth-button">Entrar</button>
                </form>
                 <p class="auth-link">Ainda não tem uma conta? <a href="register.php">Registre-se aqui</a></p>
            </div>
        </main>
    <?php endif; ?>

    <!-- Scripts (se necessários) devem vir aqui -->
     <script>
         // Log JS simples para confirmar que o HTML/JS carregou
         console.log("Página de login carregada. Session ID (cliente): <?php echo session_id(); ?>");

         // Pequena melhoria de UX: focar no campo de usuário se estiver vazio,
         // ou no campo de senha se o usuário já estiver preenchido (ex: após erro)
         document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            if (usernameField && passwordField) {
                if (usernameField.value.trim() === '') {
                    //usernameField.focus(); // autofocus já faz isso
                } else {
                    passwordField.focus();
                }
            }
         });
     </script>

</body>
</html>