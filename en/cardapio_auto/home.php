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
}           // Primeira coisa lógica

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 1); // 1 para DEV, 0 para PROD
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// --- Verificação de Autenticação (substitui auth_check.php) ---
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? null;

if (!$is_logged_in) {
    error_log("Acesso não autenticado a home.php. Redirecionando para login.");
    header('Location: login.php');
    exit;
}
// --- Fim Verificação de Autenticação ---

// Variáveis iniciais
$page_title = "Meus Cardápios";
$db_connection_error = false;
$pdo = null;
$projetos = [];
$erro_busca = null;

// Tenta conectar ao BD
try {
    require_once 'includes/db_connect.php'; // Define $pdo

    // --- Lógica para buscar projetos (APENAS se conexão OK) ---
    $sql = "SELECT id, nome_projeto, updated_at
            FROM cardapio_projetos
            WHERE usuario_id = :usuario_id
            ORDER BY updated_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $projetos = $stmt->fetchAll();
    // --- Fim busca projetos ---

} catch (\PDOException $e) {
    $db_connection_error = true;
    $erro_busca = "Erro crítico: Não foi possível conectar ao banco de dados para carregar seus projetos.";
    // O erro detalhado já foi logado por db_connect.php ou na execução da query acima
    error_log("Erro PDO em home.php ao buscar projetos para UserID $logged_user_id: " . $e->getMessage());
} catch (\Throwable $th) {
     $db_connection_error = true;
     $erro_busca = "Erro inesperado ao carregar a página.";
     error_log("Erro Throwable em home.php: " . $th->getMessage());
}

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
        /* COLOQUE AQUI TODO O CSS (Variáveis :root, Estilos Gerais, Header/Footer, Dashboard, Modais) */
        /* Cole o conteúdo que estava em css/style.css aqui */
         :root {
            --font-primary: 'Poppins', sans-serif; --font-secondary: 'Roboto', sans-serif; --font-size-base: 14px;
            --primary-color: #005A9C; --primary-dark: #003A6A; --primary-light: #4D94DB; --accent-color: #EBF4FF;
            --secondary-color: #6c757d; --secondary-light: #adb5bd; --bg-color: #F8F9FA; --card-bg: #FFFFFF;
            --text-color: #343a40; --text-light: #6c757d; --border-color: #DEE2E6; --light-border: #E9ECEF;
            --success-color: #28a745; --success-light: #e2f4e6; --success-dark: #1e7e34;
            --warning-color: #ffc107; --warning-light: #fff8e1; --warning-dark: #d39e00;
            --error-color: #dc3545;   --error-light: #f8d7da;   --error-dark: #a71d2a;
            --info-color: #17a2b8;    --info-light: #d1ecf1;    --info-dark: #117a8b; --white-color: #FFFFFF;
            --border-radius: 6px; --box-shadow: 0 2px 10px rgba(0, 90, 156, 0.08); --box-shadow-hover: 0 5px 15px rgba(0, 90, 156, 0.12);
            --transition-speed: 0.2s;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: var(--font-secondary); line-height: 1.6; margin: 0; background-color: var(--bg-color); color: var(--text-color); font-size: var(--font-size-base); display: flex; flex-direction: column; min-height: 100vh; }
        .main-content { flex-grow: 1; padding: 0px 20px 20px 20px; /* Remove padding superior que vem do header */ }

        /* --- Header --- */
        .main-header { background-color: var(--primary-dark); color: var(--white-color); padding: 10px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Removido position: sticky */ z-index: 100; }
        .header-container { max-width: 1900px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo-area a { color: var(--white-color); text-decoration: none; font-family: var(--font-primary); font-size: 1.4em; font-weight: 600; }
        .logo-area i { margin-right: 8px; color: var(--primary-light); }
        .user-nav { display: flex; align-items: center; gap: 15px; }
        .user-nav span { font-size: 0.9em; }
        .nav-button { color: var(--white-color); background-color: var(--primary-color); padding: 6px 12px; border-radius: var(--border-radius); text-decoration: none; font-size: 0.85em; transition: background-color var(--transition-speed); display: inline-flex; align-items: center; gap: 5px; }
        .nav-button:hover, .nav-button.active { background-color: var(--primary-light); }
        .nav-button.logout { background-color: var(--error-color); }
        .nav-button.logout:hover { background-color: var(--error-dark); }

        /* --- Footer --- */
        .main-footer-bottom { text-align: center; padding: 15px; margin-top: 30px; background-color: #eef2f7; color: var(--text-light); font-size: 0.85em; border-top: 1px solid var(--border-color); }

        /* --- Estilos para Dashboard (Home) --- */
        .dashboard-container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: var(--card-bg); border-radius: var(--border-radius); box-shadow: var(--box-shadow); border: 1px solid var(--light-border); }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
        .dashboard-header h1 { margin: 0; font-size: 1.7em; font-family: var(--font-primary); color: var(--primary-dark); }
        #new-project-btn { padding: 8px 18px; background-color: var(--success-color); color: var(--white-color); border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 0.9em; font-weight: 500; transition: background-color var(--transition-speed); display: inline-flex; align-items: center; gap: 8px; }
        #new-project-btn:hover { background-color: var(--success-dark); }
        .project-list { list-style: none; padding: 0; margin: 0; }
        .project-item { background-color: var(--white-color); border: 1px solid var(--light-border); border-radius: var(--border-radius); margin-bottom: 15px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; transition: box-shadow var(--transition-speed), border-color var(--transition-speed); }
        .project-item:hover { box-shadow: var(--box-shadow-hover); border-color: var(--primary-light); }
        .project-info { flex-grow: 1; margin-right: 20px; }
        .project-info h3 { margin: 0 0 5px 0; font-size: 1.2em; font-weight: 600; font-family: var(--font-primary); }
        .project-info h3 a { color: var(--primary-dark); text-decoration: none; transition: color var(--transition-speed); }
        .project-info h3 a:hover { color: var(--primary-color); }
        .project-meta { font-size: 0.8em; color: var(--text-light); }
        .project-actions button { background: none; border: none; cursor: pointer; padding: 5px 8px; font-size: 1.1em; color: var(--secondary-light); transition: color var(--transition-speed); margin-left: 5px; }
        .project-actions button:hover { color: var(--primary-dark); }
        .project-actions .delete-project-btn:hover { color: var(--error-color); }

        /* --- Estilos para Modais (Geral - Novo/Renomear Projeto) --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(52, 58, 64, 0.7); justify-content: center; align-items: center; z-index: 1000; padding: 15px; box-sizing: border-box; backdrop-filter: blur(4px); animation: fadeInModal 0.25s ease-out; }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        .modal-content { background-color: var(--card-bg); padding: 25px 30px; border-radius: var(--border-radius); box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 450px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; animation: scaleUpModal 0.25s ease-out forwards; border: 1px solid var(--light-border); }
        @keyframes scaleUpModal { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } }
        .modal-header { border-bottom: 1px solid var(--light-border); padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 1.3em; margin: 0; color: var(--primary-dark); font-weight: 600; font-family: var(--font-primary); }
        .modal-close-btn { background:none; border:none; font-size: 1.6rem; cursor:pointer; color: var(--secondary-light); padding: 0 5px; line-height: 1; transition: color var(--transition-speed); }
        .modal-close-btn:hover { color: var(--error-color); }
        .modal-body { margin-bottom: 20px; } /* Sem scroll padrão aqui */
        .modal-body label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--primary-dark); font-size: 0.9em; }
        .modal-body input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 1em; box-sizing: border-box; transition: border-color var(--transition-speed), box-shadow var(--transition-speed); }
        .modal-body input[type="text"]:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.15); outline: none; }
        .modal-footer { border-top: 1px solid var(--light-border); padding-top: 15px; text-align: right; display: flex; justify-content: flex-end; gap: 10px; }
        /* Estilos de botões dentro do modal (reutiliza .action-button) */
        .modal-button { padding: 9px 20px; font-size: 0.85em; margin-left: 0; } /* Remove margem esquerda */
        .action-button { padding: 8px 18px; background-color: var(--primary-color); color: var(--white-color); border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 0.85rem; font-weight: 500; font-family: var(--font-primary); transition: background-color var(--transition-speed), box-shadow var(--transition-speed), transform var(--transition-speed); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); display: inline-flex; align-items: center; gap: 8px; line-height: 1.5; height: 38px; text-transform: uppercase; letter-spacing: 0.5px; }
        .action-button:hover:not(:disabled) { background-color: var(--primary-dark); box-shadow: 0 4px 8px rgba(0, 90, 156, 0.1); transform: translateY(-1px); }
        .action-button.cancel { background-color: var(--secondary-color); } .action-button.cancel:hover:not(:disabled) { background-color: #5a6268; }
        .action-button.confirm { background-color: var(--success-color); } .action-button.confirm:hover:not(:disabled) { background-color: var(--success-dark); }

         /* Estilo para erro de conexão DB (caso ocorra) */
         .error-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; border: 1px solid #dc3545; max-width: 600px; margin: 50px auto; }
         .error-container h1 { color: #dc3545; margin-bottom: 15px; font-family: 'Poppins', sans-serif; font-size: 1.6em; }
         .error-container p { color: #6c757d; margin-bottom: 10px; font-size: 1em; }
         .error-container p small { font-size: 0.9em; color: #888; }

    </style>
</head>
<body>

    <!-- Header HTML -->
    <header class="main-header">
        <div class="header-container">
             <div class="logo-area">
                 <a href="home.php" title="Montador de Cardápio">
                    <i class="fas fa-utensils"></i> Montador Cardápio
                 </a>
             </div>
             <nav class="user-nav">
                 <?php if ($is_logged_in && $logged_username): ?>
                     <span>Olá, <?php echo htmlspecialchars($logged_username); ?>!</span>
                     <a href="home.php" class="nav-button active"><i class="fas fa-th-large"></i> Meus Projetos</a>
                     <a href="logout.php" class="nav-button logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
                 <?php else: ?>
                     <!-- Esta parte não deveria ser mostrada se auth_check funcionou -->
                     <a href="login.php" class="nav-button">Entrar</a>
                     <a href="register.php" class="nav-button">Registrar</a>
                 <?php endif; ?>
             </nav>
        </div>
    </header>

    <main class="main-content">
        <?php if ($db_connection_error): ?>
            <div class="error-container">
                <h1><i class="fas fa-database"></i> Erro de Conexão</h1>
                <p><?php echo htmlspecialchars($erro_busca ?: 'Não foi possível conectar ao banco de dados.'); ?></p>
                <p>Não é possível exibir seus projetos no momento.</p>
                 <p><small>(Verifique os logs do servidor para mais detalhes)</small></p>
            </div>
        <?php else: ?>
            <div class="dashboard-container">
                <div class="dashboard-header">
                    <h1>Meus Cardápios</h1>
                    <button id="new-project-btn"><i class="fas fa-plus"></i> Novo Cardápio</button>
                </div>

                <?php if (isset($erro_busca) && !$db_connection_error): ?>
                    <div class="error-message" style="background-color: var(--warning-light); color: var(--warning-dark); border-color: var(--warning-color); text-align: center;">
                        <?php echo htmlspecialchars($erro_busca); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($projetos) && !isset($erro_busca)): ?>
                    <p style="text-align: center; color: var(--text-light); margin-top: 30px;">Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!</p>
                <?php elseif (!empty($projetos)): ?>
                    <ul class="project-list">
                        <?php foreach ($projetos as $projeto): ?>
                            <li class="project-item" data-project-id="<?php echo $projeto['id']; ?>" data-project-name="<?php echo htmlspecialchars($projeto['nome_projeto']); ?>">
                                <div class="project-info">
                                    <h3>
                                        <a href="index.php?projeto_id=<?php echo $projeto['id']; ?>" title="Abrir Cardápio">
                                            <?php echo htmlspecialchars($projeto['nome_projeto']); ?>
                                        </a>
                                    </h3>
                                    <span class="project-meta">
                                        Última modificação: <?php echo date("d/m/Y H:i", strtotime($projeto['updated_at'])); ?>
                                    </span>
                                </div>
                                <div class="project-actions">
                                    <button class="rename-project-btn" title="Renomear"><i class="fas fa-pencil-alt"></i></button>
                                    <button class="delete-project-btn" title="Excluir"><i class="fas fa-trash"></i></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            </div>

            <!-- Modal para Novo Projeto -->
            <div id="new-project-modal" class="modal-overlay" style="display: none;">
              <div class="modal-content">
                <div class="modal-header">
                  <h2>Criar Novo Cardápio</h2>
                  <button type="button" class="modal-close-btn" title="Fechar">×</button>
                </div>
                <div class="modal-body">
                  <form id="new-project-form">
                      <label for="new-project-name">Nome do Cardápio:</label>
                      <input type="text" id="new-project-name" name="nome_projeto" required maxlength="100">
                  </form>
                </div>
                <div class="modal-footer">
                   <button type="button" class="action-button cancel modal-button modal-close-btn">Cancelar</button>
                   <button type="button" id="confirm-new-project-btn" class="action-button confirm modal-button">Criar</button>
                </div>
              </div>
            </div>

            <!-- Modal para Renomear Projeto -->
            <div id="rename-project-modal" class="modal-overlay" style="display: none;">
              <div class="modal-content">
                <div class="modal-header">
                  <h2>Renomear Cardápio</h2>
                  <button type="button" class="modal-close-btn" title="Fechar">×</button>
                </div>
                <div class="modal-body">
                   <form id="rename-project-form">
                        <input type="hidden" id="rename-project-id" name="projeto_id">
                        <label for="rename-project-name">Novo nome:</label>
                        <input type="text" id="rename-project-name" name="novo_nome" required maxlength="100">
                   </form>
                </div>
                <div class="modal-footer">
                   <button type="button" class="action-button cancel modal-button modal-close-btn">Cancelar</button>
                   <button type="button" id="confirm-rename-project-btn" class="action-button confirm modal-button">Salvar</button>
                </div>
              </div>
            </div>
        <?php endif; // Fim do else $db_connection_error ?>
    </main>

    <!-- Footer HTML -->
    <footer class="main-footer-bottom">
        <p>© <?php echo date("Y"); ?> Montador de Cardápio. Todos os direitos reservados.</p>
    </footer>

    <!-- Scripts JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        // COLOQUE AQUI O JAVASCRIPT específico da página home (o mesmo que estava no home.php antes)
        $(document).ready(function() {
            console.log("Dashboard (home.php) JS carregado.");
            // --- Funções Auxiliares para Modais ---
            function openModal(modalSelector) { $(modalSelector).css('display', 'flex').hide().fadeIn(200); $(modalSelector).find('input:visible:first').focus(); }
            function closeModal(modalElement) { modalElement.fadeOut(150, function() { $(this).css('display', 'none'); }); }
            $('.modal-close-btn').on('click', function() { closeModal($(this).closest('.modal-overlay')); });
            $('.modal-overlay').on('click', function(e) { if ($(e.target).is($(this))) { closeModal($(this)); } });
            $(document).on('keydown', function(e) { if (e.key === "Escape") { $('.modal-overlay:visible').each(function(){ closeModal($(this)); }); } });

            // --- Novo Projeto ---
            $('#new-project-btn').on('click', function() { $('#new-project-name').val(''); openModal('#new-project-modal'); });
            $('#confirm-new-project-btn').on('click', function() {
                const nomeProjeto = $('#new-project-name').val().trim();
                if (!nomeProjeto) { alert('Por favor, digite um nome para o cardápio.'); $('#new-project-name').focus(); return; }
                console.log("Enviando para criar projeto:", nomeProjeto);
                $.ajax({
                    url: 'project_actions.php', method: 'POST', data: { action: 'create', nome_projeto: nomeProjeto }, dataType: 'json',
                    success: function(response) {
                        console.log("Resposta do servidor (criar):", response);
                        if (response.success && response.projeto_id) { window.location.href = 'index.php?projeto_id=' + response.projeto_id; }
                        else { alert('Erro ao criar o projeto: ' + (response.message || 'Erro desconhecido.')); }
                    },
                    error: function(jqXHR, textStatus, errorThrown) { alert('Erro de comunicação ao criar o projeto.'); console.error("Erro AJAX Criar Projeto:", textStatus, errorThrown, jqXHR.responseText); }
                });
            });
            $('#new-project-name').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); $('#confirm-new-project-btn').click(); } });

            // --- Renomear Projeto ---
            $('.project-list').on('click', '.rename-project-btn', function() { const item = $(this).closest('.project-item'); $('#rename-project-id').val(item.data('project-id')); $('#rename-project-name').val(item.data('project-name')); openModal('#rename-project-modal'); });
            $('#confirm-rename-project-btn').on('click', function() {
                const projectId = $('#rename-project-id').val(); const novoNome = $('#rename-project-name').val().trim();
                if (!novoNome) { alert('O novo nome não pode estar vazio.'); $('#rename-project-name').focus(); return; }
                if (!projectId) { alert('Erro: ID do projeto não encontrado.'); closeModal($('#rename-project-modal')); return; }
                $.ajax({
                    url: 'project_actions.php', method: 'POST', data: { action: 'rename', projeto_id: projectId, novo_nome: novoNome }, dataType: 'json',
                    success: function(response) {
                        if (response.success) { const item = $('.project-item[data-project-id="' + projectId + '"]'); item.find('.project-info h3 a').text(novoNome); item.data('project-name', novoNome); closeModal($('#rename-project-modal')); }
                        else { alert('Erro ao renomear o projeto: ' + (response.message || 'Erro desconhecido.')); }
                    },
                    error: function(jqXHR, textStatus, errorThrown) { alert('Erro de comunicação ao renomear o projeto.'); console.error("Erro AJAX Renomear Projeto:", textStatus, errorThrown, jqXHR.responseText); }
                });
            });
            $('#rename-project-name').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); $('#confirm-rename-project-btn').click(); } });

             // --- Excluir Projeto ---
            $('.project-list').on('click', '.delete-project-btn', function() {
                const projectItem = $(this).closest('.project-item'); const projectId = projectItem.data('project-id'); const projectName = projectItem.data('project-name');
                if (confirm('Tem certeza que deseja excluir o cardápio "' + projectName + '"?\nEsta ação não pode ser desfeita.')) {
                    $.ajax({
                        url: 'project_actions.php', method: 'POST', data: { action: 'delete', projeto_id: projectId }, dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                const list = $('.project-list');
                                projectItem.fadeOut(400, function() {
                                     $(this).remove();
                                      // Verifica se a lista está vazia DEPOIS de remover
                                     if (list.children('.project-item').length === 0) {
                                         if ($('#no-projects-msg').length === 0) { // Evita adicionar múltiplas vezes
                                            list.after('<p id="no-projects-msg" style="text-align: center; color: var(--text-light); margin-top: 30px;">Você ainda não criou nenhum cardápio.</p>');
                                         }
                                     }
                                });
                            } else { alert('Erro ao excluir o projeto: ' + (response.message || 'Erro desconhecido.')); }
                        },
                        error: function(jqXHR, textStatus, errorThrown) { alert('Erro de comunicação ao excluir o projeto.'); console.error("Erro AJAX Excluir Projeto:", textStatus, errorThrown, jqXHR.responseText); }
                    });
                }
            });

            // Remove a mensagem de "nenhum projeto" se um item for adicionado dinamicamente (embora não seja o caso aqui, é boa prática)
             function checkEmptyList() {
                if ($('.project-list .project-item').length > 0) {
                    $('#no-projects-msg').remove();
                } else if ($('#no-projects-msg').length === 0 && !$('body').find('.error-message').length) { // Só adiciona se não houver erros
                     $('.project-list').after('<p id="no-projects-msg" style="text-align: center; color: var(--text-light); margin-top: 30px;">Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!</p>');
                }
             }
             // checkEmptyList(); // Chamada inicial não necessária pois o PHP já trata isso
        });
    </script>

</body>
</html>