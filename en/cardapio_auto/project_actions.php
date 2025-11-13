<?php
// cardapio_auto/project_actions.php
session_name("CARDAPIOSESSID");
session_start();

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // API não deve mostrar erros PHP
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Define o tipo de resposta como JSON no início
header('Content-Type: application/json; charset=utf-8');

// --- Verificação de Autenticação ---
$logged_user_id = $_SESSION['user_id'] ?? null;
if (!$logged_user_id) {
    error_log("Acesso não autenticado a project_actions.php.");
     http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Faça o login novamente.']);
    exit;
}
// --- Fim Verificação ---

// Inicializa a resposta
$response = ['success' => false, 'message' => 'Ação inválida ou não fornecida.'];
$action = $_POST['action'] ?? null;
$pdo = null;

// Log para depurar dados recebidos
error_log("project_actions.php recebido POST para User ID $logged_user_id: " . print_r($_POST, true));

// Tenta conectar ao BD
try {
    require_once 'includes/db_connect.php'; // Define $pdo

    // --- Processa a Ação ---
    if ($action === 'create') {
        $nome_projeto = trim($_POST['nome_projeto'] ?? '');
        if (empty($nome_projeto)) {
            $response['message'] = 'O nome do cardápio é obrigatório.';
        } elseif (strlen($nome_projeto) > 100) {
            $response['message'] = 'O nome do cardápio é muito longo (máx 100 caracteres).';
        } else {
            $sql = "INSERT INTO cardapio_projetos (usuario_id, nome_projeto, dados_json) VALUES (:uid, :nome, :json)";
            $stmt = $pdo->prepare($sql);
            $dados_iniciais_vazios = '{}'; // Começa vazio
            if ($stmt->execute([':uid' => $logged_user_id, ':nome' => $nome_projeto, ':json' => $dados_iniciais_vazios])) {
                $response['success'] = true;
                $response['message'] = 'Projeto criado com sucesso!';
                $response['projeto_id'] = $pdo->lastInsertId();
                error_log("Projeto criado: ID " . $response['projeto_id'] . " por User ID " . $logged_user_id);
            } else {
                $response['message'] = 'Erro ao salvar o novo projeto.';
                 error_log("Falha INSERT (Criar Projeto) User $logged_user_id. Erro PDO: " . implode(":", $stmt->errorInfo()));
            }
        }
    } elseif ($action === 'rename') {
        $projeto_id = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT);
        $novo_nome = trim($_POST['novo_nome'] ?? '');
        if (!$projeto_id) $response['message'] = 'ID do projeto inválido.';
        elseif (empty($novo_nome)) $response['message'] = 'O novo nome não pode estar vazio.';
        elseif (strlen($novo_nome) > 100) $response['message'] = 'O novo nome é muito longo.';
        else {
            $sql = "UPDATE cardapio_projetos SET nome_projeto = :nome WHERE id = :pid AND usuario_id = :uid";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':nome' => $novo_nome, ':pid' => $projeto_id, ':uid' => $logged_user_id])) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Projeto renomeado!';
                     error_log("Projeto renomeado: ID $projeto_id para '$novo_nome' por User ID $logged_user_id");
                } else {
                    $response['message'] = 'Projeto não encontrado ou permissão negada.';
                     error_log("Falha UPDATE (Renomear Projeto): rowCount 0. User $logged_user_id tentou renomear Proj $projeto_id.");
                }
            } else {
                $response['message'] = 'Erro ao atualizar o nome no banco.';
                 error_log("Falha EXECUTE (Renomear Projeto) User $logged_user_id, Proj $projeto_id. Erro PDO: " . implode(":", $stmt->errorInfo()));
            }
        }
    } elseif ($action === 'delete') {
        $projeto_id = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT);
        if (!$projeto_id) $response['message'] = 'ID do projeto inválido.';
        else {
            $sql = "DELETE FROM cardapio_projetos WHERE id = :pid AND usuario_id = :uid";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':pid' => $projeto_id, ':uid' => $logged_user_id])) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Projeto excluído!';
                     error_log("Projeto excluído: ID $projeto_id por User ID $logged_user_id");
                } else {
                    $response['message'] = 'Projeto não encontrado ou permissão negada.';
                     error_log("Falha DELETE (Excluir Projeto): rowCount 0. User $logged_user_id tentou excluir Proj $projeto_id.");
                }
            } else {
                $response['message'] = 'Erro ao excluir o projeto do banco.';
                 error_log("Falha EXECUTE (Excluir Projeto) User $logged_user_id, Proj $projeto_id. Erro PDO: " . implode(":", $stmt->errorInfo()));
            }
        }
    }
     // Se $action não for create, rename ou delete, a $response inicial 'Ação inválida' será usada.

} catch (\PDOException $e) {
    $response['success'] = false; // Garante que é falso
    $response['message'] = 'Erro interno do servidor [DB].'; // Mensagem genérica
    // O erro detalhado já foi logado por db_connect.php ou na query
    error_log("Erro PDOException GERAL em project_actions.php para User ID $logged_user_id, Ação '$action': " . $e->getMessage());
     http_response_code(500); // Internal Server Error
} catch (\Throwable $th) {
     $response['success'] = false;
     $response['message'] = 'Erro interno do servidor [General].';
     error_log("Erro Throwable GERAL em project_actions.php para User ID $logged_user_id, Ação '$action': " . $th->getMessage());
     http_response_code(500);
}

// Envia a resposta JSON final
echo json_encode($response);
exit;
?>