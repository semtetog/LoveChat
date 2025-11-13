<?php
// db.php - Conexão com o banco de dados e funções principais

// Configurações básicas de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // Mantenha 0 em produção
ini_set('log_errors', 1);
// Garante que o diretório de logs exista ou tenta criá-lo
$log_dir = dirname(__DIR__) . '/logs'; // Assumindo que a pasta logs fica um nível acima do diretório 'includes'
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true); // O @ suprime erros se o diretório já existir ou não puder ser criado
}
ini_set('error_log', $log_dir . '/db_errors.log'); // Caminho completo para o log

// Verifique se config.php existe
$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
    // Usar error_log antes de die para garantir que o erro seja registrado
    error_log("CRITICAL ERROR: Arquivo config.php não encontrado em: " . $config_path);
    die("ERRO CRÍTICO: Arquivo de configuração (config.php) não encontrado.");
}

require_once $config_path;

// Define a constante do avatar padrão se não estiver definida
if (!defined('DEFAULT_AVATAR')) {
    // IMPORTANTE: Verifique se o nome 'default.jpg' corresponde ao arquivo real na sua pasta uploads/avatars
    define('DEFAULT_AVATAR', 'default.jpg');
}

// Função principal de conexão
function conectarDB() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lançar exceções em caso de erro
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retornar arrays associativos por padrão
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Usar prepared statements nativos
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Configurações adicionais (fuso horário brasileiro - SP e modo SQL)
        // É crucial que o fuso horário do PHP e do MySQL estejam alinhados
        try {
            $pdo->exec("SET time_zone = '-03:00';"); // Ajuste se seu servidor estiver em outro fuso
            // Modo SQL recomendado para MySQL >= 5.7 para maior consistência de dados
            $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';");
        } catch (PDOException $setupEx) {
             error_log("Aviso: Falha ao definir time_zone ou sql_mode no MySQL: " . $setupEx->getMessage());
             // Não interrompe a execução, mas registra o aviso
        }

        return $pdo;
    } catch (PDOException $e) {
        // Loga o erro detalhado
        error_log('[' . date('Y-m-d H:i:s') . '] CRITICAL DB CONNECTION ERROR: ' . $e->getMessage());
        // Interrompe a execução de forma segura para o usuário
        // Não exiba detalhes do erro para o usuário final em produção
        die("Erro crítico: Não foi possível conectar ao banco de dados. Por favor, tente novamente mais tarde ou contate o suporte.");
    }
}

// --- Conexão Global ---
// A execução só chega aqui se conectarDB() for bem-sucedida
$pdo = conectarDB();
error_log("INFO: Conexão PDO estabelecida com sucesso.");


// --- Funções de Verificação e Registro ---

/**
 * Verifica se um e-mail já existe na tabela de usuários.
 * @param string $email O e-mail a ser verificado.
 * @return bool True se o e-mail existe, false caso contrário ou em caso de erro.
 */
function emailExiste($email) {
    global $pdo;
    if (!$pdo || empty($email)) return false;

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        // fetchColumn retorna a primeira coluna da linha ou false se não houver linha
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        error_log('Erro ao verificar e-mail (' . $email . '): ' . $e->getMessage());
        return false; // Retorna false em caso de erro
    }
}

/**
 * Registra um novo usuário no banco de dados.
 * @param string $nome_completo Nome completo do usuário.
 * @param string $nickname Apelido do usuário.
 * @param string $email E-mail do usuário (deve ser único).
 * @param string $telefone Telefone do usuário.
 * @param string $senha Senha em texto plano (será hasheada).
 * @param string|null $chave_pix Chave PIX opcional.
 * @param string|null $tipo_chave_pix Tipo da chave PIX opcional.
 * @return int|string|false Retorna o ID do novo usuário em caso de sucesso,
 *                          'email_duplicado' se o email já existir,
 *                          ou false em caso de outros erros.
 */
function registrarUsuario($nome_completo, $nickname, $email, $telefone, $senha, $chave_pix = null, $tipo_chave_pix = null) {
    global $pdo;
    if (!$pdo) return false;

    // Validação básica de entrada
    if (empty($nome_completo) || empty($nickname) || empty($email) || empty($telefone) || empty($senha)) {
         error_log("Erro ao registrar: Dados obrigatórios ausentes.");
         return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         error_log("Erro ao registrar: Email inválido ({$email}).");
         return false;
    }

    // Verifica se o email já existe ANTES de tentar inserir
    if (emailExiste($email)) {
        error_log("Tentativa de registro com email duplicado: {$email}");
        return 'email_duplicado';
    }

    try {
        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
        if ($senha_hash === false) {
            throw new Exception("Falha ao gerar hash da senha.");
        }

        $sql = "INSERT INTO usuarios
                  (nome, nome_completo, email, telefone, senha, chave_pix, tipo_chave_pix, data_criacao, registration_ip, is_active)
                VALUES
                  (:nickname, :nome_completo, :email, :telefone, :senha, :chave_pix, :tipo_chave_pix, NOW(), :ip, 1)"; // Define como ativo por padrão

        $stmt = $pdo->prepare($sql);

        // Obtém o IP do usuário de forma segura
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        $result = $stmt->execute([
            ':nickname' => trim($nickname),
            ':nome_completo' => trim($nome_completo),
            ':email' => trim($email),
            ':telefone' => trim($telefone),
            ':senha' => $senha_hash,
            ':chave_pix' => $chave_pix ? trim($chave_pix) : null,
            ':tipo_chave_pix' => $tipo_chave_pix ?: null,
            ':ip' => $ip_address
        ]);

        if ($result) {
            $lastId = $pdo->lastInsertId();
            error_log("Usuário registrado com sucesso. Email: {$email}, ID: {$lastId}");
            return $lastId;
        } else {
            error_log("Falha na execução do INSERT para usuário {$email}.");
            return false;
        }

    } catch (PDOException $e) {
        error_log('Erro PDO ao registrar usuário (' . $email . '): ' . $e->getMessage() . ' | SQL Code: ' . $e->getCode());
        // O erro de duplicidade já foi tratado antes, mas podemos verificar novamente por segurança
        if ($e->getCode() == 23000) { // Código SQL para violação de constraint (geralmente UNIQUE)
             return 'email_duplicado';
        }
        return false;
    } catch (Exception $e) {
         error_log('Erro geral ao registrar usuário (' . $email . '): ' . $e->getMessage());
         return false;
    }
}


/**
 * Verifica as credenciais de login do usuário e o status da conta (ativa e aprovada).
 * @param string $email E-mail do usuário.
 * @param string $senha Senha em texto plano.
 * @return array|string|false Retorna um array com os dados do usuário se logado, ativo e aprovado,
 *                          'conta_inativa' se a conta estiver inativa,
 *                          'pendente_aprovacao' se a conta estiver ativa mas não aprovada,
 *                          ou false se o e-mail não for encontrado ou a senha estiver incorreta.
 */
function verificarLogin($email, $senha) { // Removido $pdo como parâmetro, já que é global
    global $pdo;
    if (!$pdo || empty($email) || empty($senha)) return false;

    try {
        // Seleciona colunas necessárias, incluindo is_active e is_approved
        $sql = "SELECT
                    id, nome, nome_completo, email, telefone, senha AS senha_hash, avatar, is_admin, tutorial_visto, is_active, is_approved
                 FROM usuarios
                 WHERE email = :email LIMIT 1"; // Adicionado LIMIT 1 por segurança/performance
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC

        if ($usuario) {
            // 1. Verifica a senha
            if (password_verify($senha, $usuario['senha_hash'])) { // Comparar com senha_hash
                // Senha correta, agora verifica o status da conta

                // 2. Verifica se a conta está ativa
                if ((int)$usuario['is_active'] === 0) {
                    error_log("Tentativa de login falhou para {$email}: Conta INATIVA.");
                    return ['status' => 'inactive']; // Código específico para conta inativa
                }

                // 3. Verifica se a conta está aprovada
                if ((int)$usuario['is_approved'] === 1) {
                    // Login bem-sucedido, ativo e aprovado!
                    error_log("Login bem-sucedido para {$email} (ID: {$usuario['id']}) - Ativo e Aprovado.");

                    // Atualizar último login e contador
                    try {
                        $updateStmt = $pdo->prepare("UPDATE usuarios SET last_login = NOW(), login_count = login_count + 1 WHERE id = :id");
                        $updateStmt->execute([':id' => $usuario['id']]);
                    } catch (PDOException $updateE) {
                        error_log("AVISO: Falha ao atualizar last_login/login_count para usuário ID {$usuario['id']}: " . $updateE->getMessage());
                    }

                    unset($usuario['senha_hash']); // NUNCA retornar o hash da senha
                    unset($usuario['senha']); // Remove também se o alias não foi usado na query
                    return $usuario; // Retorna os dados do usuário
                } else {
                    // Conta ativa, mas pendente de aprovação
                    error_log("Tentativa de login falhou para {$email}: Conta PENDENTE APROVAÇÃO.");
                    return ['status' => 'pending_approval']; // Código específico para pendente aprovação
                }
            } else {
                // Senha incorreta
                error_log("Tentativa de login falhou para {$email}: Senha INCORRETA.");
                return false;
            }
        } else {
            // Email não encontrado
            error_log("Tentativa de login falhou: E-mail {$email} NÃO ENCONTRADO.");
            return false;
        }
    } catch (PDOException $e) {
        error_log('Erro PDO ao verificar login para ' . $email . ': ' . $e->getMessage());
        return false; // Retorna false em caso de erro no banco
    }
}

/**
 * Busca os dados de um usuário pelo ID.
 * @param int $user_id O ID do usuário.
 * @return array|false Retorna um array associativo com os dados do usuário ou false se não encontrado/erro.
 */
function buscarUsuarioPorId($user_id) {
    global $pdo;
    if (!$pdo || empty($user_id) || !is_numeric($user_id)) {
        error_log("Erro em buscarUsuarioPorId: ID inválido ou não fornecido ({$user_id}).");
        return false;
    }

    try {
        // Seleciona todas as colunas necessárias para exibir no perfil
        $sql = "SELECT
                    id,
                    nome,           -- Nickname
                    nome_completo,
                    email,
                    telefone,       -- <<< COLUNA TELEFONE INCLUÍDA AQUI
                    avatar,
                    chave_pix,
                    tipo_chave_pix,
                    data_criacao,
                    ultima_atualizacao,
                    is_admin,       -- Para identificar administradores
                    is_active       -- Para saber o status da conta
                    -- Adicione outras colunas da tabela 'usuarios' que você precisa exibir no perfil
                FROM usuarios
                WHERE id = :user_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => (int)$user_id]); // Garante que user_id é um inteiro
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log para depuração
        if ($usuario) {
            error_log("buscarUsuarioPorId({$user_id}): Usuário encontrado. Telefone: " . ($usuario['telefone'] ?? 'NÃO PRESENTE NO RESULTADO'));
        } else {
            error_log("buscarUsuarioPorId({$user_id}): Usuário NÃO encontrado no banco de dados.");
        }

        return $usuario; // Retorna o array do usuário ou false se não encontrado

    } catch (PDOException $e) {
        error_log('Erro PDO ao buscar usuário por ID (' . $user_id . '): ' . $e->getMessage());
        return false; // Retorna false em caso de erro
    }
}

/**
 * Atualiza os dados do perfil do usuário.
 * @param int $user_id ID do usuário a ser atualizado.
 * @param string $nickname Novo nickname.
 * @param string|null $avatar Novo nome do arquivo de avatar (ou null para não alterar).
 * @param string|null $chave_pix Nova chave PIX (ou null).
 * @param string|null $tipo_chave_pix Novo tipo de chave PIX (ou null).
 * @param string|null $telefone Novo número de telefone. <<< ADICIONADO
 * @return bool True em caso de sucesso, false em caso de falha.
 */
function atualizarPerfil($user_id, $nickname, $avatar = null, $chave_pix = null, $tipo_chave_pix = null, $telefone = null) {
    global $pdo;
    if (!$pdo || empty($user_id) || !is_numeric($user_id)) {
         error_log("Erro ao atualizar perfil: ID de usuário inválido ({$user_id}).");
         return false;
    }
    if (empty($nickname)) { // Nickname é provavelmente obrigatório
         error_log("Erro ao atualizar perfil para ID {$user_id}: Nickname não pode ser vazio.");
         return false;
    }

    try {
        // Inicia a construção da query e dos parâmetros
        $setClauses = [
            "nome = :nickname",
            "telefone = :telefone", // <<< ATUALIZAÇÃO DO TELEFONE INCLUÍDA
            "chave_pix = :chave_pix",
            "tipo_chave_pix = :tipo_chave_pix",
            "ultima_atualizacao = NOW()" // Atualiza o timestamp da modificação
        ];
        $params = [
            ':nickname' => trim($nickname),
            ':telefone' => $telefone ? trim($telefone) : null, // <<< VINCULA O TELEFONE
            ':chave_pix' => $chave_pix ? trim($chave_pix) : null,
            ':tipo_chave_pix' => $tipo_chave_pix ?: null, // Permite string vazia ou null
            ':user_id' => (int)$user_id // Parâmetro para o WHERE
        ];

        // Adiciona o avatar à query SOMENTE se um novo nome de arquivo foi fornecido
        // Verifica se $avatar não é null e não é uma string vazia
        if ($avatar !== null && $avatar !== '') {
            $setClauses[] = "avatar = :avatar";
            $params[':avatar'] = $avatar; // Adiciona o parâmetro do avatar
        } else {
            // Opcional: Logar que o avatar não foi alterado
            error_log("INFO: Avatar não foi modificado na atualização do perfil para User ID {$user_id}.");
        }

        // Monta a query final
        $sql = "UPDATE usuarios SET " . implode(', ', $setClauses) . " WHERE id = :user_id";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        // Log detalhado da operação
        if ($result) {
            error_log("Perfil atualizado com sucesso para User ID {$user_id}. Avatar fornecido: " . ($avatar ? "'{$avatar}'" : 'Não') . ", Telefone: '" . ($telefone ?? 'Nulo') . "'");
        } else {
             // Tentar obter informações do erro se a execução falhar (raro com PDO::ERRMODE_EXCEPTION)
             $errorInfo = $stmt->errorInfo();
             error_log("Falha ao ATUALIZAR perfil para User ID {$user_id}. Erro PDO: " . ($errorInfo[2] ?? 'N/A'));
        }

        return $result; // Retorna true ou false

    } catch (PDOException $e) {
        error_log('Erro PDO ao atualizar perfil para User ID ' . $user_id . ': ' . $e->getMessage());
        return false; // Retorna false em caso de erro
    }
}


// --- Funções de Reset de Senha (mantidas como estavam, mas com named parameters e logs) ---

function solicitarResetSenha($email) {
    global $pdo;
    if (!$pdo || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Solicitação de reset de senha falhou: Email inválido ou vazio ({$email}).");
        return false;
    }

    try {
        // Permite reset apenas para contas ativas
        $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = :email AND is_active = 1");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            error_log("Tentativa de reset de senha para email inexistente ou inativo: {$email}");
            return false;
        }

        $token = bin2hex(random_bytes(32)); // Gera um token seguro
        $expiracao = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Expiração curta é mais segura

        $stmt = $pdo->prepare("UPDATE usuarios SET reset_token = :token, reset_expira = :expiracao WHERE id = :id");
        $updated = $stmt->execute([':token' => $token, ':expiracao' => $expiracao, ':id' => $usuario['id']]);

        if ($updated) {
             error_log("Token de reset gerado para {$email}. Token: " . substr($token, 0, 8) . "...");
             return [
                'email' => $email,
                'nome' => $usuario['nome'],
                'token' => $token,
                'expiracao' => $expiracao
            ];
        } else {
             error_log("Falha ao ATUALIZAR o token de reset para {$email}.");
             return false;
        }

    } catch (PDOException $e) {
        error_log('Erro PDO ao solicitar reset de senha para ' . $email . ': ' . $e->getMessage());
        return false;
    }
}

function validarTokenReset($token) {
    global $pdo;
     if (!$pdo || empty($token)) {
         error_log("Validação de token falhou: Token vazio.");
         return false;
     }

    try {
        // Verifica se o token existe E se ainda não expirou
        $stmt = $pdo->prepare("SELECT id, email FROM usuarios WHERE reset_token = :token AND reset_expira > NOW()");
        $stmt->execute([':token' => $token]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            error_log("Token de reset validado com sucesso para ID {$usuario['id']}. Token: " . substr($token, 0, 8));
            return $usuario; // Retorna ID e email se o token for válido
        } else {
            // Não loga o token inteiro por segurança
            error_log("Tentativa de usar token de reset inválido ou expirado: " . substr($token, 0, 8) . "...");
            return false;
        }
    } catch (PDOException $e) {
        error_log('Erro PDO ao validar token de reset (' . substr($token, 0, 8) . '...): ' . $e->getMessage());
        return false;
    }
}

function atualizarSenha($email, $novaSenha, $token) {
    global $pdo;
    if (!$pdo || empty($email) || empty($novaSenha) || empty($token)) {
        error_log("Atualização de senha falhou: Dados ausentes.");
        return false;
    }

    try {
        // 1. Valida o token novamente para garantir atomicidade
        $usuarioValido = validarTokenReset($token);
        if (!$usuarioValido || $usuarioValido['email'] !== $email) {
            error_log("Falha ao atualizar senha: Token inválido ou não corresponde ao email {$email} no momento da atualização.");
            return false;
        }

        // 2. Gera o hash da nova senha
        $hash = password_hash($novaSenha, PASSWORD_BCRYPT);
        if ($hash === false) {
             throw new Exception("Falha ao gerar hash da nova senha para {$email}.");
        }

        // 3. Atualiza a senha e limpa o token
        $sql = "UPDATE usuarios SET senha = :senha, reset_token = NULL, reset_expira = NULL WHERE id = :id AND email = :email";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':senha' => $hash,
            ':id' => $usuarioValido['id'],
            ':email' => $email
        ]);

        if($result) {
             error_log("Senha atualizada com sucesso para {$email} (ID: {$usuarioValido['id']}) usando token.");
             return true;
        } else {
             $errorInfo = $stmt->errorInfo();
             error_log("Falha na query de ATUALIZAÇÃO de senha para {$email}. Erro PDO: " . ($errorInfo[2] ?? 'N/A'));
             return false;
        }

    } catch (PDOException $e) {
        error_log('Erro PDO ao atualizar senha para ' . $email . ': ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
         error_log('Erro geral ao atualizar senha para ' . $email . ': ' . $e->getMessage());
         return false;
    }
}


// Removido: Função de envio de e-mail. É melhor mantê-la em um arquivo separado (ex: includes/mailer.php)
// function enviarEmailBoasVindas($nome, $email) { ... }

?>