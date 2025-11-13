<?php
// Define o fuso horário padrão para São Paulo (IMPORTANTE!)
date_default_timezone_set('America/Sao_Paulo');

// --- Configurações Iniciais e Autenticação ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- Configurações de Erro (Produção) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$log_file_path = __DIR__ . '/../logs/admin_payments_page_errors.log';
if (!file_exists(dirname($log_file_path))) { @mkdir(dirname($log_file_path), 0755, true); }
ini_set('error_log', $log_file_path);
error_reporting(E_ALL);
error_log("--- Admin Payments Page Started ---");

// --- Includes e Conexão DB ---
$base_dir = dirname(__DIR__) . '/';
$db_path = $base_dir . 'includes/db.php';
$helpers_path = __DIR__ . '/admin_helpers.php'; // Caminho para helpers

if (!file_exists($db_path)) { error_log("CRITICAL ERROR: db.php not found"); die("Erro crítico: DB config."); }
if (!file_exists($helpers_path)) { error_log("CRITICAL ERROR: admin_helpers.php not found"); die("Erro crítico: Helpers."); }

require_once $db_path;
require_once $helpers_path; // Inclui os helpers

if (!isset($pdo) || !($pdo instanceof PDO)) { error_log("CRITICAL ERROR: PDO connection failed"); die("Erro crítico: DB connection."); }
error_log("PDO connection established for Payments Page.");

// --- Autenticação de Administrador ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    error_log("Admin Access Denied to Payments Page.");
    $_SESSION['error'] = 'Acesso restrito.';
    header("Location: ../login.php");
    exit();
}
$adminUserId = (int)$_SESSION['user_id'];

// --- Lógica da Página ---
$db_error = null;
$pagamentos = [];
// Pega filtros ou usa defaults (ontem, pendente)
$filter_date = $_GET['filter_date'] ?? date('Y-m-d', strtotime('-1 day'));
$filter_status = $_GET['filter_status'] ?? 'pendente';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    $sql = "SELECT * FROM pagamentos_pendentes WHERE 1=1";
    $params = [];

    if (!empty($filter_date)) {
        $sql .= " AND data_referencia = :filter_date";
        $params[':filter_date'] = $filter_date;
    }
    if (!empty($filter_status) && $filter_status != 'todos') {
        $sql .= " AND status_pagamento = :filter_status";
        $params[':filter_status'] = $filter_status;
    }

    $sql .= " ORDER BY data_referencia DESC, nome_usuario ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totais para os filtros atuais
    $totalPendente = 0; $countPendente = 0;
    $totalPago = 0; $countPago = 0;
    $totalErro = 0; $countErro = 0;
    $totalGeral = 0; $countGeral = 0;

    // Recalcula totais com base nos dados filtrados
    foreach ($pagamentos as $p) {
         $saldo = (float)$p['saldo_do_dia'];
         $totalGeral += $saldo;
         $countGeral++;
        if ($p['status_pagamento'] == 'pendente') {
             $totalPendente += $saldo; $countPendente++;
        } elseif ($p['status_pagamento'] == 'pago') {
             $totalPago += $saldo; $countPago++;
        } elseif ($p['status_pagamento'] == 'erro') {
            $totalErro += $saldo; $countErro++;
        }
    }


} catch (PDOException $e) {
    error_log("DB Query Error in Payments Page: " . $e->getMessage());
    $db_error = "Erro ao carregar lista de pagamentos.";
} catch (Throwable $t) {
    error_log("General Error in Payments Page: " . $t->getMessage());
    $db_error = "Erro interno inesperado.";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pagamentos Diários - Admin Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    
     <style>
    /* ========== VARIÁVEIS CSS - DARK THEME ========== */
    :root {
        /* Cores Base (Escuras) */
        --primary: #ff007f; /* Rosa vibrante principal */
        --primary-light: rgba(255, 0, 127, 0.1); /* Versão clara/transparente do primário */
        --primary-dark: #cc0066; /* Versão mais escura do primário */
        --secondary: #fc5cac; /* Rosa secundário */
        --secondary-light: rgba(252, 92, 172, 0.1);
        --dark: #1a1a1a; /* Cinza escuro principal */
        --darker: #121212; /* Quase preto para fundos */
        --darkest: #0a0a0a; /* Preto puro para elementos profundos */
        --light: #e0e0e0; /* Texto claro principal */
        --lighter: #f5f5f5; /* Texto/elementos mais claros */
        --gray: #2a2a2a; /* Cinza médio para cards/elementos */
        --gray-light: #3a3a3a; /* Cinza mais claro para hover/borders */
        --gray-dark: #1e1e1e; /* Cinza mais escuro para fundos sutis */

        /* Cores de Status e Feedback */
        --success: #00cc66; /* Verde para sucesso */
        --success-light: rgba(0, 204, 102, 0.1);
        --warning: #ffcc00; /* Amarelo para aviso */
        --warning-light: rgba(255, 204, 0, 0.1);
        --danger: #ff3333; /* Vermelho para erro/perigo */
        --danger-light: rgba(255, 51, 51, 0.1);
        --info: #0099ff; /* Azul para informação */
        --info-light: rgba(0, 153, 255, 0.1);
        --whatsapp-green: #25D366; /* Verde específico do WhatsApp */

        /* Cores de Texto */
        --text-primary: rgba(255, 255, 255, 0.9); /* Branco quase puro */
        --text-secondary: rgba(255, 255, 255, 0.6); /* Cinza claro para texto secundário */
        --text-tertiary: rgba(255, 255, 255, 0.4); /* Cinza mais escuro para texto menos importante */
        --text-on-primary: #ffffff; /* Texto sobre fundo primário (branco) */
        --text-on-dark-button: #ffffff; /* Texto em botões escuros */

        /* Bordas */
        --border-color: rgba(255, 255, 255, 0.1); /* Borda sutil branca/transparente */
        --border-color-light: rgba(255, 255, 255, 0.2); /* Borda um pouco mais visível */
        --border-radius-sm: 6px;
        --border-radius-md: 10px;
        --border-radius-lg: 14px;
        --border-radius-xl: 18px;

        /* Sombras (sutis para tema escuro) */
        --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.2);
        --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.3);
        --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.3);
        --shadow-xl: 0 12px 24px rgba(0, 0, 0, 0.4);

        /* Espaçamentos */
        --space-xs: 4px;
        --space-sm: 8px;
        --space-md: 16px;
        --space-lg: 24px;
        --space-xl: 32px;
        --space-2xl: 48px;

        /* Tipografia */
        --font-sans: 'Montserrat', -apple-system, BlinkMacSystemFont, sans-serif;
        --font-size-xs: 0.75rem;   /* 12px */
        --font-size-sm: 0.875rem;  /* 14px */
        --font-size-base: 1rem;    /* 16px */
        --font-size-lg: 1.125rem;  /* 18px */
        --font-size-xl: 1.25rem;   /* 20px */
        --font-size-2xl: 1.5rem;   /* 24px */
    }

    /* ========== RESET E ESTILOS BASE ========== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }

    html {
        scroll-behavior: smooth;
        font-size: 16px; /* Base para REM */
    }

    body {
        font-family: var(--font-sans);
        background-color: var(--darker);
        color: var(--text-primary);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        overflow-x: hidden; /* Prevenir scroll horizontal */
    }

    a {
        color: var(--primary); /* Link rosa por padrão */
        text-decoration: none;
        transition: color 0.2s ease;
    }
    a:hover {
        color: var(--secondary);
    }

    button, input, select, textarea {
        font-family: inherit;
        font-size: inherit;
        background-color: transparent;
        color: inherit;
        border: 1px solid var(--border-color);
    }

    img {
        max-width: 100%;
        display: block;
    }

    /* ========== LAYOUT PRINCIPAL ========== */
    .app-container {
        display: flex;
        min-height: 100vh;
        background-color: var(--dark); /* Fundo ligeiramente mais claro que body */
    }

    /* ========== SIDEBAR ========== */
    .sidebar {
        width: 260px; /* Levemente mais estreita */
        background: linear-gradient(180deg, var(--dark), var(--darkest)); /* Gradiente sutil */
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 100;
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--border-color);
        box-shadow: var(--shadow-md);
        transition: transform 0.3s ease;
    }

    .sidebar-header {
        padding: var(--space-lg) var(--space-md);
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid var(--border-color);
        height: 70px; /* Altura fixa */
    }

    .sidebar-logo {
        height: 40px;
        width: auto;
        filter: drop-shadow(0 0 5px rgba(255, 0, 127, 0.5)); /* Brilho sutil */
    }

    .user-profile {
        display: flex;
        align-items: center;
        padding: var(--space-md);
        margin: var(--space-md) var(--space-md) 0; /* Margem apenas em cima/lados */
        background-color: rgba(255, 255, 255, 0.03); /* Fundo muito sutil */
        border-radius: var(--border-radius-md);
        transition: background-color 0.2s ease;
        border: 1px solid transparent;
    }

    .user-profile:hover {
        background-color: rgba(255, 255, 255, 0.06);
        border-color: var(--primary-light);
    }

    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: var(--space-md);
        border: 2px solid var(--primary);
        box-shadow: 0 0 8px rgba(255, 0, 127, 0.4);
    }

    .user-info h3 {
        font-size: var(--font-size-sm);
        font-weight: 600;
        margin-bottom: 2px;
        color: var(--text-primary);
    }

    .user-info p {
        font-size: var(--font-size-xs);
        color: var(--text-secondary);
        font-weight: 500;
    }

    .sidebar-menu {
        padding: var(--space-sm) 0;
        flex-grow: 1;
        overflow-y: auto;
        /* Scrollbar personalizada */
        scrollbar-width: thin;
        scrollbar-color: var(--primary) rgba(255, 255, 255, 0.05);
    }
    .sidebar-menu::-webkit-scrollbar { width: 6px; }
    .sidebar-menu::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
    .sidebar-menu::-webkit-scrollbar-thumb { background-color: var(--primary); border-radius: 3px; }

    .menu-item {
        display: flex;
        align-items: center;
        padding: var(--space-sm) var(--space-lg);
        margin: var(--space-xs) var(--space-md);
        color: var(--text-secondary); /* Texto secundário por padrão */
        font-weight: 500;
        border-radius: var(--border-radius-md);
        transition: all 0.2s ease;
        border-left: 3px solid transparent; /* Borda para item ativo */
    }

    .menu-item i {
        margin-right: var(--space-md);
        font-size: 1.1rem; /* Ícone um pouco maior */
        width: 24px;
        text-align: center;
        color: var(--text-tertiary); /* Ícone mais sutil */
        transition: color 0.2s ease;
    }

    .menu-item:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--text-primary);
        border-left-color: var(--secondary); /* Borda hover sutil */
    }

    .menu-item:hover i {
        color: var(--primary);
    }

    .menu-item.active {
        background-color: var(--primary-light);
        color: var(--primary);
        font-weight: 600;
        border-left-color: var(--primary); /* Borda ativa */
    }

    .menu-item.active i {
        color: var(--primary);
    }

    .logout-btn-container {
        padding: var(--space-md);
        border-top: 1px solid var(--border-color);
        margin-top: auto; /* Empurra para baixo */
    }

    .logout-btn {
        display: flex;
        align-items: center;
        padding: var(--space-sm) var(--space-lg);
        color: var(--danger);
        font-weight: 500;
        border-radius: var(--border-radius-md);
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
    }

    .logout-btn i {
        margin-right: var(--space-md);
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
    }

    .logout-btn:hover {
        background-color: var(--danger-light);
        border-left-color: var(--danger);
    }

    /* ========== CONTEÚDO PRINCIPAL ========== */
    .main-content {
        flex: 1;
        margin-left: 260px; /* Igual à largura da sidebar */
        padding: var(--space-xl);
        transition: margin-left 0.3s ease;
    }

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-xl);
        padding-bottom: var(--space-md);
        border-bottom: 1px solid var(--border-color);
        position: sticky; /* Fixa o header ao rolar */
        top: 0;
        background-color: var(--dark); /* Fundo para cobrir conteúdo */
        z-index: 50;
        padding-top: var(--space-md); /* Espaço acima */
    }
    .menu-toggle {
         display: none; /* Oculto em desktop */
         background: none;
         border: none;
         color: var(--text-primary);
         font-size: 1.5rem;
         cursor: pointer;
         padding: 0.5rem;
    }

    .content-header h1 {
        font-size: var(--font-size-2xl);
        font-weight: 700;
        color: var(--text-primary);
    }

    .content-section {
        display: none;
        animation: fadeIn 0.4s ease-out;
    }

    .content-section.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .section-title {
        display: flex;
        align-items: center;
        font-size: var(--font-size-xl);
        margin-bottom: var(--space-md);
        color: var(--text-primary);
        font-weight: 600;
        padding-bottom: var(--space-sm);
        border-bottom: 1px solid var(--border-color-light);
    }

    .section-title i {
        margin-right: var(--space-sm);
        color: var(--primary);
        font-size: var(--font-size-lg);
    }

    .section-description {
        color: var(--text-secondary);
        margin-bottom: var(--space-xl);
        font-size: var(--font-size-sm);
        max-width: 800px; /* Limita largura */
    }

    /* ========== TABELA DE USUÁRIOS ========== */
    .table-container {
        overflow-x: auto;
        background-color: var(--gray); /* Fundo do container */
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        margin-top: var(--space-lg);
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px; /* Garante scroll se necessário */
    }

    .admin-table thead {
        background-color: var(--gray-dark); /* Cabeçalho mais escuro */
    }

    .admin-table th {
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.8px; /* Mais espaçado */
        padding: var(--space-sm) var(--space-md);
        text-align: left;
        border-bottom: 1px solid var(--border-color-light);
        white-space: nowrap;
    }

    .admin-table td {
        padding: var(--space-md); /* Mais padding */
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        font-size: var(--font-size-sm);
        color: var(--text-primary);
    }

    .admin-table tbody tr:last-child td {
        border-bottom: none;
    }

    .admin-table tbody tr {
        transition: background-color 0.2s ease;
    }

    .admin-table tbody tr:hover {
        background-color: var(--gray-light); /* Hover sutil */
    }

    .user-avatar-sm {
        width: 40px; /* Um pouco maior */
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--gray-light); /* Borda sutil */
    }

    .user-info-cell {
        display: flex;
        align-items: center;
        gap: var(--space-md); /* Mais espaço */
    }

    .user-info-cell .user-name {
        font-weight: 600; /* Nome mais destacado */
    }
    .user-info-cell .user-email-sub { /* Adicionado para email abaixo do nome */
        font-size: var(--font-size-xs);
        color: var(--text-secondary);
        display: block; /* Quebra linha */
        margin-top: 2px;
    }

    .action-buttons {
        display: flex;
        gap: var(--space-sm); /* Mais espaço */
        justify-content: flex-start;
    }

    .btn {
        padding: 6px 14px; /* Ajuste padding */
        border-radius: var(--border-radius-sm);
        font-weight: 600; /* Mais forte */
        font-size: var(--font-size-xs);
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px; /* Espaço botão/ícone */
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn i {
        font-size: 1em; /* Tamanho relativo ao botão */
    }
     /* Estilos Base Botão (Dark Theme) */
    .btn {
        background-color: var(--gray-light);
        color: var(--text-primary);
        border-color: var(--border-color);
    }
    .btn:hover {
        background-color: var(--gray);
        border-color: var(--border-color-light);
        transform: translateY(-1px); /* Efeito sutil */
    }

    /* Botões Específicos */
    .btn-edit {
        background-color: var(--info-light);
        color: var(--info);
        border-color: var(--info);
    }
    .btn-edit:hover {
        background-color: var(--info);
        color: white;
    }

    .btn-delete { /* Desativar */
        background-color: var(--danger-light);
        color: var(--danger);
        border-color: var(--danger);
    }
    .btn-delete:hover {
        background-color: var(--danger);
        color: white;
    }
     .btn-activate { /* Reativar */
        background-color: var(--success-light);
        color: var(--success);
        border-color: var(--success);
    }
    .btn-activate:hover {
        background-color: var(--success);
        color: white;
    }

    .btn-notify {
        background-color: var(--primary-light);
        color: var(--primary);
        border-color: var(--primary);
    }
    .btn-notify:hover {
        background-color: var(--primary);
        color: white;
    }
     .btn-details { /* Ver Detalhes */
        background-color: var(--secondary-light);
        color: var(--secondary);
        border-color: var(--secondary);
    }
    .btn-details:hover {
        background-color: var(--secondary);
        color: white;
    }

    /* Badges */
    .badge {
        padding: 5px 10px; /* Mais padding */
        border-radius: var(--border-radius-xl); /* Mais arredondado */
        font-size: 0.7rem; /* Menor */
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge i { font-size: 0.9em; }

    .badge-user { background-color: var(--info-light); color: var(--info); }
    .badge-admin { background-color: var(--primary-light); color: var(--primary); }
    .badge-active { background-color: var(--success-light); color: var(--success); }
    .badge-inactive { background-color: var(--gray-light); color: var(--text-secondary); }

    /* ========== FEED DE ATIVIDADES (REDESIGN) ========== */
    #realtime-feed {
        margin-top: var(--space-lg);
        display: flex;
        flex-direction: column;
        gap: var(--space-md); /* Espaço entre itens */
    }

    .feed-item {
        background: linear-gradient(145deg, var(--gray), var(--gray-dark)); /* Gradiente sutil */
        border-radius: var(--border-radius-lg);
        padding: var(--space-lg);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-md);
        display: flex;
        gap: var(--space-lg);
        align-items: flex-start; /* Alinha avatar e conteúdo no topo */
        position: relative;
        transition: all 0.3s ease;
        border-left: 4px solid transparent; /* Para estado unread */
        overflow: hidden; /* Para efeitos */
    }
    /* Efeito hover mais pronunciado */
    .feed-item:hover {
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        transform: translateY(-3px);
        border-left-color: var(--secondary); /* Muda borda no hover */
    }

    /* Estado não lido */
    .feed-item.unread {
        border-left-color: var(--primary);
        background: linear-gradient(145deg, var(--primary-light), rgba(30, 30, 30, 0.3)); /* Fundo destacado */
    }
    .feed-item.unread:hover {
        border-left-color: var(--primary-dark);
    }

    .feed-item-icon-area { /* Área dedicada para ícone e avatar */
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-sm);
        flex-shrink: 0;
    }
    .feed-item-type-icon {
        font-size: 1.5rem;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.05);
    }
    .feed-item-avatar img {
        width: 48px; /* Avatar maior */
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--gray-light);
    }
     .feed-item.unread .feed-item-avatar img {
         border-color: var(--primary); /* Destaca avatar não lido */
     }

    .feed-item-content {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        min-width: 0; /* Evita overflow */
        gap: var(--space-sm); /* Espaço interno */
    }

    .feed-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: var(--space-sm);
        flex-wrap: wrap; /* Quebra linha se necessário */
    }

    .feed-item-user {
        font-weight: 600;
        color: var(--text-primary);
        font-size: var(--font-size-base); /* Nome maior */
        line-height: 1.4;
    }

    .feed-item-time {
        font-size: var(--font-size-xs);
        color: var(--text-secondary);
        white-space: nowrap;
        background-color: rgba(255, 255, 255, 0.05);
        padding: 3px 8px;
        border-radius: var(--border-radius-xl);
    }

    .feed-item-description {
        font-size: var(--font-size-sm);
        color: var(--text-primary); /* Descrição mais clara */
        line-height: 1.5;
        margin-top: var(--space-xs);
    }

    .feed-item-description strong {
        color: var(--secondary); /* Nomes em destaque */
        font-weight: 600;
    }

    .feed-item-details {
        background-color: rgba(0, 0, 0, 0.2); /* Fundo sutil para detalhes */
        padding: var(--space-sm) var(--space-md);
        border-radius: var(--border-radius-md);
        font-size: var(--font-size-sm);
        border: 1px solid var(--border-color);
        margin-top: var(--space-sm);
    }

    .feed-item-details p {
        margin-bottom: var(--space-xs);
        line-height: 1.6;
        color: var(--text-secondary); /* Detalhes mais sutis */
    }
    .feed-item-details p:last-child {
        margin-bottom: 0;
    }

    .feed-item-details strong {
        color: var(--text-primary); /* Destaques dentro dos detalhes */
        font-weight: 600;
    }

    .feed-item-details code {
        background-color: rgba(255, 255, 255, 0.1);
        padding: 3px 8px;
        border-radius: var(--border-radius-sm);
        font-size: var(--font-size-xs);
        color: var(--primary);
        font-family: monospace;
        word-break: break-all;
    }

    .feed-item-actions {
        display: flex;
        gap: var(--space-sm); /* Mais espaço entre ações */
        align-items: center;
        flex-wrap: wrap;
        margin-top: var(--space-md);
        padding-top: var(--space-md);
        border-top: 1px solid var(--border-color);
    }

    .feed-item-status {
        margin-right: auto; /* Empurra status para esquerda */
    }

    .feed-status-select {
        background-color: var(--gray-dark); /* Fundo escuro */
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        padding: 8px 12px; /* Mais padding */
        border-radius: var(--border-radius-sm);
        font-size: var(--font-size-xs);
        cursor: pointer;
        font-family: inherit;
        transition: all 0.2s;
        height: auto; /* Altura automática */
        outline: none;
        appearance: none;
        -webkit-appearance: none;
        /* Ícone dropdown personalizado para tema escuro */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23cccccc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 12px 10px;
        padding-right: 36px; /* Espaço para ícone */
        font-weight: 500;
    }

    .feed-status-select:hover {
        border-color: var(--primary);
    }

    .feed-status-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-light); /* Outline focus */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23ff007f' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
    }

    .feed-status-select option {
        background-color: var(--dark); /* Fundo opções */
        color: var(--text-primary);
    }

    .feed-action-btn {
        background-color: var(--gray-light);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 6px 12px;
        font-size: var(--font-size-xs);
        border-radius: var(--border-radius-sm);
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-weight: 500;
    }
    .feed-action-btn i { font-size: 0.9em; }

    .feed-action-btn:hover {
        background-color: var(--gray);
        color: var(--primary);
        border-color: var(--primary);
        transform: translateY(-1px);
    }

    /* Indicador de Carregamento e Sem Itens */
    #feed-loading, #no-feed-items {
        text-align: center;
        padding: var(--space-2xl);
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
        background-color: var(--gray);
        border-radius: var(--border-radius-lg);
        margin-top: var(--space-lg);
    }

    #feed-loading i {
        margin-right: var(--space-sm);
        animation: spin 1.5s linear infinite;
        color: var(--primary); /* Cor do spinner */
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* ========== MODAIS ========== */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.8); /* Fundo mais escuro */
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        z-index: 1000;
        overflow-y: auto;
        padding: var(--space-xl);
        animation: modalFadeIn 0.3s ease-out;
        align-items: center; /* Centraliza verticalmente */
        justify-content: center; /* Centraliza horizontalmente */
    }

    .modal.show {
        display: flex; /* Usa flex para centralizar */
    }

    @keyframes modalFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: linear-gradient(145deg, var(--dark), var(--darker)); /* Gradiente modal */
        border-radius: var(--border-radius-lg);
        padding: 0;
        width: 100%; /* Ocupa largura disponível */
        max-width: 650px; /* Largura máxima */
        margin: var(--space-lg) 0; /* Margem vertical */
        box-shadow: var(--shadow-xl);
        border: 1px solid var(--border-color);
        animation: modalContentSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1); /* Animação mais suave */
        position: relative;
        overflow: hidden; /* Garante bordas arredondadas */
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 4rem); /* Limita altura */
    }

    @keyframes modalContentSlideIn {
        from { transform: translateY(20px) scale(0.98); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-md) var(--space-lg);
        border-bottom: 1px solid var(--border-color);
        background-color: var(--gray-dark); /* Fundo header */
        flex-shrink: 0; /* Não encolhe */
    }

    .modal-title {
        font-size: var(--font-size-lg);
        color: var(--text-primary);
        font-weight: 600;
    }

    .close-modal {
        background: rgba(255, 255, 255, 0.1); /* Fundo sutil botão fechar */
        border: none;
        color: var(--text-secondary);
        font-size: 1.5rem; /* Maior */
        cursor: pointer;
        transition: all 0.2s ease;
        line-height: 1;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px; /* Tamanho fixo */
        height: 36px;
        border-radius: 50%;
    }

    .close-modal:hover {
        background-color: rgba(255, 255, 255, 0.2);
        color: var(--primary);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: var(--space-lg);
        overflow-y: auto; /* Scroll apenas no body */
        flex-grow: 1; /* Ocupa espaço restante */
        /* Scrollbar personalizada para o modal */
        scrollbar-width: thin;
        scrollbar-color: var(--primary) var(--gray-dark);
    }
    .modal-body::-webkit-scrollbar { width: 6px; }
    .modal-body::-webkit-scrollbar-track { background: var(--gray-dark); }
    .modal-body::-webkit-scrollbar-thumb { background-color: var(--primary); border-radius: 3px; }


    .user-detail-modal .modal-content {
        max-width: 750px; /* Modal de detalhes maior */
    }

    .user-detail-header {
        display: flex;
        align-items: center;
        gap: var(--space-lg);
        padding: var(--space-lg);
        border-bottom: 1px solid var(--border-color);
        background-color: var(--gray); /* Fundo diferente */
        flex-wrap: wrap; /* Quebra linha se necessário */
    }

    .user-detail-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--primary);
        flex-shrink: 0;
        box-shadow: 0 0 10px rgba(255, 0, 127, 0.4);
    }
    .user-detail-info { flex-grow: 1; } /* Faz info ocupar espaço */

    .user-detail-name {
        font-size: var(--font-size-xl);
        font-weight: 700;
        margin-bottom: var(--space-xs);
        color: var(--text-primary);
    }
     .user-detail-email { /* Adicionado para email */
         color: var(--text-secondary);
         font-size: var(--font-size-sm);
         margin-bottom: var(--space-sm);
         word-break: break-all;
     }
     .user-detail-status { /* Container para badges */
         display: flex;
         gap: var(--space-sm);
         margin-top: var(--space-sm);
     }

    .user-detail-body {
        padding: var(--space-lg);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Colunas responsivas */
        gap: var(--space-lg) var(--space-xl); /* Mais gap */
    }

    .user-detail-group {
        margin-bottom: 0; /* Removido margin, controlado pelo gap do grid */
    }

    .user-detail-label {
        font-size: var(--font-size-xs);
        margin-bottom: var(--space-xs);
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 600;
    }

    .user-detail-value {
        font-size: var(--font-size-sm);
        background-color: var(--gray-dark); /* Fundo dos valores */
        padding: var(--space-sm) var(--space-md); /* Mais padding */
        border-radius: var(--border-radius-sm);
        border: 1px solid var(--border-color);
        min-height: 44px; /* Altura mínima */
        display: flex;
        align-items: center;
        word-break: break-word;
        color: var(--text-primary);
    }

    .user-detail-value:empty::before {
        content: 'Não informado'; /* Texto mais claro */
        color: var(--text-tertiary);
        font-style: italic;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: var(--space-sm);
        padding: var(--space-md) var(--space-lg);
        border-top: 1px solid var(--border-color);
        background-color: var(--gray-dark); /* Fundo footer */
        flex-shrink: 0; /* Não encolhe */
    }

    .modal-footer .btn {
        padding: 8px 18px; /* Botões footer um pouco maiores */
        font-size: var(--font-size-sm);
    }

    /* Modal de Exclusão/Desativação */
    #deleteModal .modal-content {
        max-width: 500px;
        background: linear-gradient(145deg, var(--danger), #a02020); /* Fundo vermelho */
        border-color: var(--danger);
    }
     #deleteModal .modal-header {
         background-color: rgba(0,0,0,0.2);
         border-bottom-color: rgba(255,255,255,0.2);
     }
     #deleteModal .modal-title {
         color: white;
     }
     #deleteModal .close-modal {
         color: white;
         background: rgba(0,0,0,0.3);
     }
     #deleteModal .close-modal:hover {
         background: rgba(0,0,0,0.5);
     }

    #deleteModal .modal-body {
        text-align: center;
        padding: var(--space-xl) var(--space-lg);
        color: white; /* Texto branco */
    }

    #deleteModal p {
        margin-bottom: var(--space-md);
        font-size: var(--font-size-base); /* Maior */
    }
     #deleteModal p strong {
         font-weight: 700;
     }
    #deleteModal .modal-body p:last-of-type { /* Descrição */
        font-size: var(--font-size-sm);
        color: rgba(255, 255, 255, 0.8);
    }

    #deleteModal .modal-footer {
        justify-content: center;
         background-color: rgba(0,0,0,0.2);
         border-top-color: rgba(255,255,255,0.2);
    }
     #deleteModal .modal-footer .btn-edit { /* Botão Cancelar */
         background-color: rgba(255,255,255,0.2);
         color: white;
         border-color: rgba(255,255,255,0.5);
     }
     #deleteModal .modal-footer .btn-edit:hover {
         background-color: rgba(255,255,255,0.3);
     }
     #deleteModal .modal-footer .btn-delete { /* Botão Confirmar */
         background-color: white;
         color: var(--danger);
         border-color: white;
     }
      #deleteModal .modal-footer .btn-delete:hover {
         background-color: #eee;
         border-color: #eee;
     }


    /* ========== FORMULÁRIOS ========== */
    .form-group {
        margin-bottom: var(--space-lg); /* Mais espaço */
    }

    .form-label {
        display: block;
        margin-bottom: var(--space-sm); /* Mais espaço label/input */
        font-weight: 600;
        font-size: var(--font-size-sm);
        color: var(--text-primary);
    }

    .form-control {
        width: 100%;
        padding: 12px 16px; /* Mais padding */
        background-color: var(--gray-dark); /* Fundo input */
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        color: var(--text-primary);
        font-size: var(--font-size-sm);
        font-family: inherit;
        transition: all 0.2s ease;
    }
     /* Placeholder mais sutil */
    .form-control::placeholder {
        color: var(--text-tertiary);
        opacity: 1;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-light); /* Outline focus */
        background-color: var(--gray); /* Fundo mais claro no focus */
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    select.form-control {
        appearance: none;
        -webkit-appearance: none;
        /* Ícone dropdown personalizado */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23cccccc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        background-size: 14px 10px;
        padding-right: 48px; /* Espaço para ícone */
        cursor: pointer;
    }

    select.form-control:focus {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23ff007f' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
    }
    select.form-control option {
        background-color: var(--dark);
        color: var(--text-primary);
    }


    /* ========== TOAST NOTIFICAÇÕES ========== */
    #toast-container {
        position: fixed;
        bottom: var(--space-lg);
        right: var(--space-lg);
        z-index: 1100;
        width: 360px; /* Mais largo */
        max-width: calc(100% - 40px);
        display: flex;
        flex-direction: column;
        gap: var(--space-md); /* Mais espaço */
    }

    .toast {
        background-color: var(--gray); /* Fundo toast */
        color: var(--text-primary);
        padding: var(--space-md) var(--space-lg);
        border-radius: var(--border-radius-md);
        box-shadow: var(--shadow-lg);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1); /* Animação suave */
        transform: translateX(110%);
        display: flex;
        align-items: flex-start; /* Alinha ícone no topo */
        gap: var(--space-md);
        font-size: var(--font-size-sm);
        border-left: 4px solid var(--info); /* Borda padrão info */
        border: 1px solid var(--border-color);
    }

    .toast i.fas { /* Estilo ícone */
        font-size: 1.2rem; /* Maior */
        margin-top: 2px;
        flex-shrink: 0;
    }

    .toast.show {
        opacity: 1;
        transform: translateX(0);
    }

    .toast-success { border-left-color: var(--success); border-color: var(--success); }
    .toast-success i { color: var(--success); }

    .toast-warning { border-left-color: var(--warning); border-color: var(--warning); }
    .toast-warning i { color: var(--warning); }

    .toast-error { border-left-color: var(--danger); border-color: var(--danger); }
    .toast-error i { color: var(--danger); }

    .toast-info { border-left-color: var(--info); border-color: var(--info); }
    .toast-info i { color: var(--info); }


    /* ========== RESPONSIVIDADE ========== */
    @media (max-width: 1200px) {
        .sidebar { width: 240px; }
        .main-content { margin-left: 240px; }
    }

    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px; /* Volta largura maior no mobile */
            box-shadow: var(--shadow-xl); /* Sombra mais forte quando aberto */
        }
        .sidebar.active { transform: translateX(0); }
        .main-content {
            margin-left: 0;
            padding-top: 80px; /* Espaço para header mobile */
        }
        .menu-toggle { display: block; } /* Mostra botão menu */
         .content-header {
             /* Adiciona espaço para o botão de menu */
             padding-left: 50px; /* Ajuste conforme necessário */
             position: fixed; /* Fixa o header no mobile */
             top: 0;
             left: 0;
             right: 0;
             height: 70px;
             z-index: 90; /* Abaixo da sidebar */
             box-shadow: var(--shadow-sm);
             background-color: var(--dark);
         }
         .menu-toggle { /* Posiciona o botão de menu */
             position: absolute;
             left: var(--space-md);
             top: 50%;
             transform: translateY(-50%);
         }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.6); /* Overlay mais escuro */
            z-index: 99; /* Acima do conteúdo, abaixo da sidebar */
        }
        .sidebar-overlay.active { display: block; }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: var(--space-lg);
            padding-top: 80px; /* Mantém espaço */
        }
        .content-header h1 { font-size: var(--font-size-xl); }
        .admin-table { min-width: 700px; }
        .modal-content { width: calc(100% - 40px); } /* Modal quase tela cheia */
        .user-detail-header {
            flex-direction: column;
            text-align: center;
        }
        .user-detail-avatar { margin: 0 auto var(--space-md); }
        .user-detail-body { grid-template-columns: 1fr; /* Uma coluna */ padding: var(--space-md); }
        .feed-item { gap: var(--space-md); } /* Menos gap no feed */
        .feed-item-icon-area { align-items: flex-start; } /* Alinha ícone/avatar à esquerda */
    }

    @media (max-width: 576px) {
        html { font-size: 15px; } /* Base font um pouco menor */
        .main-content {
            padding: var(--space-md);
            padding-top: 70px; /* Reduz espaço header */
        }
         .content-header {
             height: 60px;
             padding-left: 45px;
         }
         .content-header h1 { font-size: var(--font-size-lg); }
        .admin-table th, .admin-table td { padding: var(--space-sm) var(--space-md); }
        .action-buttons { flex-wrap: wrap; }
        .btn { padding: 6px 10px; }
        .user-avatar-sm { width: 36px; height: 36px; }
        .modal-header, .modal-body, .modal-footer { padding: var(--space-md); }
        .modal-title { font-size: var(--font-size-lg); }
        .modal-footer {
            flex-direction: column;
            gap: var(--space-sm);
        }
        .modal-footer .btn { width: 100%; }

        /* Feed Mobile */
        .feed-item {
             flex-direction: column; /* Empilha elementos */
             padding: var(--space-md);
             gap: var(--space-sm);
        }
         .feed-item-icon-area {
             flex-direction: row; /* Ícone e avatar lado a lado */
             width: 100%;
             align-items: center;
             margin-bottom: var(--space-sm);
         }
         .feed-item-type-icon {
             width: 24px; height: 24px; font-size: 1rem;
         }
         .feed-item-avatar img { width: 40px; height: 40px; }
        .feed-item-header {
             flex-direction: column;
             align-items: flex-start;
             gap: var(--space-xs);
        }
        .feed-item-time { margin-top: var(--space-xs); }
        .feed-item-actions {
             flex-direction: column;
             align-items: stretch; /* Botões ocupam largura total */
             gap: var(--space-xs);
        }
        .feed-item-status {
             margin-right: 0;
             margin-bottom: var(--space-xs);
        }
        .feed-status-select { width: 100%; }
        .feed-action-btn { justify-content: center; }
        #toast-container { width: calc(100% - 30px); bottom: 15px; right: 15px; }
    }
    
    /* Estilo para o botão Limpar Tudo */
#clearAllFeedBtn {
    margin-left: auto;
    padding: 8px 16px;
    font-size: var(--font-size-sm);
}

/* Estilo para o botão de remoção individual */
.feed-action-btn.btn-delete {
    background-color: var(--danger-light);
    color: var(--danger);
    border-color: var(--danger);
}
.feed-action-btn.btn-delete:hover {
    background-color: var(--danger);
    color: white;
}

/* Alinhamento dos botões de ação no feed */
.feed-item-actions {
    display: flex;
    gap: var(--space-sm);
    align-items: center;
    flex-wrap: wrap;
}

 .btn-reset-balance {
        background-color: var(--warning-light);
        color: var(--warning);
        border-color: var(--warning);
    }
    .btn-reset-balance:hover {
        background-color: var(--warning);
        color: var(--dark); /* Texto escuro para contraste */
        border-color: var(--warning);
    }
    .btn-reset-balance i.fa-times { /* Ajuste fino do ícone 'x' */
        font-size: 0.7em;
        position: relative;
        top: -0.2em;
        left: -0.3em;
        opacity: 0.8;
    }
    .btn-reset-balance:hover i.fa-times {
        opacity: 1;
    }
    </style>
    <!-- Cole o <style>...</style> completo do admin_dashboard.php aqui -->
    <style>
        /* COLE TODO O BLOCO <style> DO admin_dashboard.php AQUI */
        /* ... (muitas linhas de CSS) ... */

        /* Estilos adicionais para a página de pagamentos */
        .filter-container { /* Novo container para filtros e download */
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
            flex-wrap: wrap;
        }
        .filter-form {
            background-color: var(--gray);
            padding: var(--space-md);
            border-radius: var(--border-radius-md);
            display: flex;
            gap: var(--space-md);
            align-items: flex-end;
            flex-wrap: wrap;
            flex-grow: 1; /* Ocupa espaço disponível */
        }
        .filter-form .form-group { margin-bottom: 0; flex-grow: 1; min-width: 150px; }
        .filter-form .form-label { font-size: var(--font-size-xs); margin-bottom: var(--space-xs); color: var(--text-secondary); }
        .filter-form .form-control { height: 40px; padding: 8px 12px; font-size: var(--font-size-sm);}
        .filter-form button[type="submit"] { height: 40px; flex-shrink: 0; background-color: var(--primary); color: white; border: none;}
        .filter-form button[type="submit"]:hover { background-color: var(--primary-dark); }

        .download-button-container { flex-shrink: 0; }
        .btn-download-csv { height: 40px; background-color: var(--info); color: white; border: none;}
        .btn-download-csv:hover { background-color: var(--info-dark); } /* Defina --info-dark se necessário */
         .btn-download-csv:disabled { background-color: var(--gray-light); color: var(--text-tertiary); cursor: not-allowed; }


        .totals-info {
            background-color: var(--gray-dark);
            padding: var(--space-md);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--space-lg);
            font-size: var(--font-size-sm);
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: var(--space-md);
            border: 1px solid var(--border-color);
        }
        .totals-info div { text-align: center; }
        .totals-info .total-label { display: block; font-size: 0.8em; color: var(--text-secondary); text-transform: uppercase; }
        .totals-info .total-value { font-weight: 600; font-size: 1.1em; display: block; }
        .totals-info .count { font-size: 0.8em; color: var(--text-tertiary); display: block; }

        .totals-info .pending .total-value { color: var(--warning); }
        .totals-info .paid .total-value { color: var(--success); }
        .totals-info .error .total-value { color: var(--danger); }
        .totals-info .geral .total-value { color: var(--text-primary); }

        .payment-table td { vertical-align: middle; }
        .payment-table .pix-details { font-size: var(--font-size-xs); color: var(--text-secondary); display: block; margin-top: 3px; word-break: break-all; }
        .payment-table .badge { font-size: 0.75rem; padding: 4px 8px; }
        .payment-table .badge-pendente { background-color: var(--warning-light); color: var(--warning); border: 1px solid var(--warning); }
        .payment-table .badge-pago { background-color: var(--success-light); color: var(--success); border: 1px solid var(--success); }
        .payment-table .badge-erro { background-color: var(--danger-light); color: var(--danger); border: 1px solid var(--danger); }

        .payment-actions button { margin-right: 5px; margin-bottom: 5px; } /* Espaçamento inferior para mobile */
        .btn-sm { padding: 5px 10px; font-size: var(--font-size-xs); } /* Tamanho menor */

        .btn-mark-paid { background-color: var(--success-light); color: var(--success); border-color: var(--success); }
        .btn-mark-paid:hover { background-color: var(--success); color: white; }
        .btn-mark-error { background-color: var(--danger-light); color: var(--danger); border-color: var(--danger); }
        .btn-mark-error:hover { background-color: var(--danger); color: white; }
        .btn-mark-pending { background-color: var(--gray-light); color: var(--text-secondary); border-color: var(--gray); }
        .btn-mark-pending:hover { background-color: var(--gray); color: var(--text-primary); }

        /* Para responsividade dos filtros */
        @media (max-width: 768px) {
            .filter-container { flex-direction: column; align-items: stretch; }
            .filter-form { width: 100%; }
            .download-button-container { width: 100%; }
            .btn-download-csv { width: 100%; margin-top: var(--space-sm); }
            .totals-info { justify-content: center; }
        }

    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar (Certifique-se que o item 'Pagamentos Diários' está ativo aqui) -->
        <aside class="sidebar" id="sidebar">
            <!-- COLE A ESTRUTURA DA SIDEBAR AQUI, MARCANDO O ITEM DE PAGAMENTOS COMO 'active' -->
             <div class="sidebar-header">
                <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat Admin" class="sidebar-logo">
            </div>
             <div class="user-profile">
                 <img src="/uploads/avatars/<?php echo escapeHTML($_SESSION['user_avatar'] ?? DEFAULT_AVATAR); ?>?v=<?php echo time(); ?>" alt="Admin Avatar" class="user-avatar" id="sidebarAvatar" onerror="this.onerror=null;this.src='/uploads/avatars/<?php echo DEFAULT_AVATAR; ?>?v=<?php echo time(); ?>'">
                 <div class="user-info">
                    <h3 id="sidebarUserName"><?php echo escapeHTML($_SESSION['user_nome'] ?? 'Admin'); ?></h3>
                    <p><i class="fas fa-crown" style="color: var(--warning); margin-right: 4px;"></i> Administrador</p>
                </div>
            </div>
            <nav class="sidebar-menu" aria-label="Menu principal">
                 <a href="admin_dashboard.php" class="menu-item"> <!-- Não ativo -->
                    <i class="fas fa-stream"></i> <span>Feed Atividades</span>
                 </a>
                 <a href="admin_dashboard.php#usuarios-section" class="menu-item"> <!-- Não ativo -->
                    <i class="fas fa-users-cog"></i> <span>Gerenciar Usuários</span>
                </a>
                 <a href="payments.php" class="menu-item active"> <!-- ATIVO -->
                    <i class="fas fa-money-check-alt"></i> <span>Pagamentos Diários</span>
                </a>
                <a href="../dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> <span>Voltar ao Dashboard</span>
                </a>
                <a href="../profile.php" class="menu-item" target="_blank">
                     <i class="fas fa-user-shield"></i> <span>Meu Perfil</span>
                 </a>
             </nav>
            <div class="logout-btn-container">
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <span>Sair</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <button class="menu-toggle" id="menuToggle" aria-label="Abrir menu"><i class="fas fa-bars"></i></button>
                <h1><i class="fas fa-money-check-alt"></i> Pagamentos Diários</h1>
            </div>

            <?php if ($db_error): ?>
                <div class="message error" style="background-color: var(--danger-light); color: var(--danger); padding: var(--space-md); border-radius: var(--border-radius-md); border: 1px solid var(--danger); margin-bottom: var(--space-lg);">
                    <i class="fas fa-times-circle"></i> <?php echo escapeHTML($db_error); ?>
                </div>
            <?php endif; ?>

            <!-- Filtros e Download -->
            <div class="filter-container">
                <form method="GET" action="payments.php" class="filter-form">
                    <div class="form-group">
                        <label for="filter_date" class="form-label">Data Referência:</label>
                        <input type="date" id="filter_date" name="filter_date" class="form-control" value="<?php echo escapeHTML($filter_date); ?>">
                    </div>
                    <div class="form-group">
                        <label for="filter_status" class="form-label">Status:</label>
                        <select id="filter_status" name="filter_status" class="form-control">
                            <option value="todos" <?php echo ($filter_status == 'todos') ? 'selected' : ''; ?>>Todos</option>
                            <option value="pendente" <?php echo ($filter_status == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                            <option value="pago" <?php echo ($filter_status == 'pago') ? 'selected' : ''; ?>>Pago</option>
                            <option value="erro" <?php echo ($filter_status == 'erro') ? 'selected' : ''; ?>>Erro</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                </form>
                <div class="download-button-container">
                     <button id="downloadCsvBtn" class="btn btn-download-csv" <?php echo empty($filter_date) ? 'disabled' : ''; ?> title="<?php echo empty($filter_date) ? 'Selecione uma data para baixar' : 'Baixar relatório CSV para '.date('d/m/Y', strtotime($filter_date)); ?>">
                        <i class="fas fa-download"></i> Baixar CSV (<?php echo escapeHTML(date('d/m', strtotime($filter_date))); ?>)
                    </button>
                </div>
            </div>

            <!-- Totais Info -->
             <div class="totals-info">
                 <div class="geral">
                     <span class="total-label">Total Geral (Filtro)</span>
                     <span class="total-value">R$ <?php echo number_format($totalGeral, 2, ',', '.'); ?></span>
                     <span class="count"><?php echo $countGeral; ?> registro(s)</span>
                 </div>
                 <div class="pending">
                     <span class="total-label">Total Pendente</span>
                     <span class="total-value">R$ <?php echo number_format($totalPendente, 2, ',', '.'); ?></span>
                     <span class="count"><?php echo $countPendente; ?> registro(s)</span>
                 </div>
                 <div class="paid">
                     <span class="total-label">Total Pago</span>
                     <span class="total-value">R$ <?php echo number_format($totalPago, 2, ',', '.'); ?></span>
                     <span class="count"><?php echo $countPago; ?> registro(s)</span>
                 </div>
                 <div class="error">
                     <span class="total-label">Total c/ Erro</span>
                     <span class="total-value">R$ <?php echo number_format($totalErro, 2, ',', '.'); ?></span>
                     <span class="count"><?php echo $countErro; ?> registro(s)</span>
                 </div>
             </div>


            <!-- Tabela de Pagamentos -->
            <div class="table-container">
                <table class="admin-table payment-table">
                    <thead>
                        <tr>
                            <th>Data Ref.</th>
                            <th>Usuário</th>
                            <th>Chave PIX</th>
                            <th>Saldo</th>
                            <th>Status</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagamentos)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <i class="fas fa-info-circle fa-lg" style="margin-bottom: 10px;"></i><br>
                                    Nenhum registro de pagamento encontrado para os filtros selecionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pagamentos as $pag): ?>
                                <tr data-payment-id="<?php echo $pag['id']; ?>">
                                    <td><?php echo escapeHTML(date('d/m/Y', strtotime($pag['data_referencia']))); ?></td>
                                    <td><?php echo escapeHTML($pag['nome_usuario']); ?> <small>(ID: <?php echo $pag['usuario_id']; ?>)</small></td>
                                    <td>
                                        <strong><?php echo escapeHTML(ucfirst(str_replace('_', ' ', $pag['tipo_chave_pix'] ?? 'N/A'))); ?>:</strong>
                                        <span class="pix-details"><?php echo escapeHTML($pag['chave_pix'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td><strong>R$ <?php echo number_format((float)$pag['saldo_do_dia'], 2, ',', '.'); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo escapeHTML($pag['status_pagamento']); ?>">
                                            <i class="fas <?php echo ($pag['status_pagamento'] == 'pago' ? 'fa-check-circle' : ($pag['status_pagamento'] == 'erro' ? 'fa-exclamation-triangle' : 'fa-hourglass-half')); ?>"></i>
                                            <?php echo escapeHTML(ucfirst($pag['status_pagamento'])); ?>
                                        </span>
                                         <?php if($pag['data_pagamento']): ?>
                                             <small style="display: block; font-size: 0.7rem; color: var(--text-tertiary); margin-top: 3px;">em <?php echo date('d/m/y H:i', strtotime($pag['data_pagamento'])); ?></small>
                                         <?php endif; ?>
                                          <?php if($pag['observacao']): ?>
                                             <small style="display: block; font-size: 0.7rem; color: var(--warning); margin-top: 3px; cursor: help;" title="<?php echo escapeHTML($pag['observacao']); ?>"><i class="fas fa-comment-dots"></i> Nota</small>
                                         <?php endif; ?>
                                    </td>
                                    <td class="payment-actions" style="text-align: right;">
                                        <?php if ($pag['status_pagamento'] == 'pendente'): ?>
                                            <button class="btn btn-sm btn-mark-paid" data-action="mark-paid" data-id="<?php echo $pag['id']; ?>" title="Marcar como Pago"><i class="fas fa-check"></i> Pago</button>
                                            <button class="btn btn-sm btn-mark-error" data-action="mark-error" data-id="<?php echo $pag['id']; ?>" title="Marcar com Erro (Adicionar nota)"><i class="fas fa-times"></i> Erro</button>
                                         <?php elseif ($pag['status_pagamento'] == 'erro'): ?>
                                              <button class="btn btn-sm btn-mark-paid" data-action="mark-paid" data-id="<?php echo $pag['id']; ?>" title="Marcar como Pago"><i class="fas fa-check"></i> Pago</button>
                                             <button class="btn btn-sm btn-mark-pending" data-action="mark-pending" data-id="<?php echo $pag['id']; ?>" title="Voltar para Pendente"><i class="fas fa-undo"></i> Pendente</button>
                                         <?php elseif ($pag['status_pagamento'] == 'pago'): ?>
                                              <button class="btn btn-sm btn-mark-pending" data-action="mark-pending" data-id="<?php echo $pag['id']; ?>" title="Voltar para Pendente"><i class="fas fa-undo"></i> Pendente</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- Container para Toasts -->
    <div id="toast-container"></div>

    <!-- Script JS -->
    <script>
        // Funções utilitárias básicas (COLE AS FUNÇÕES escapeHTML e showToast aqui)
        function escapeHTML(str) {
            if (typeof str !== 'string') str = String(str ?? '');
            const p = document.createElement("p");
            p.textContent = str;
            return p.innerHTML;
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if(!container) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            let iconClass = 'fa-info-circle';
            if(type === 'success') iconClass = 'fa-check-circle';
            if(type === 'warning') iconClass = 'fa-exclamation-triangle';
            if(type === 'error') iconClass = 'fa-times-circle';
            toast.innerHTML = `<i class="fas ${iconClass}"></i> <span>${escapeHTML(message)}</span>`;
            container.prepend(toast);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => { toast.classList.add('show'); });
            });
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => { if(toast.parentNode === container) toast.remove(); }, {once: true});
                setTimeout(() => { if(toast.parentNode === container) toast.remove(); }, 500);
            }, 4000);
        }


        document.addEventListener('DOMContentLoaded', function() {
            // Lógica do Menu Mobile
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            if(menuToggle && sidebar && sidebarOverlay) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                });
                sidebarOverlay.addEventListener('click', () => {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
            }

            // Delegação para botões de ação da tabela
            const tableBody = document.querySelector('.payment-table tbody');
            if(tableBody) {
                tableBody.addEventListener('click', function(e) {
                    const button = e.target.closest('button[data-action]');
                    if (button) {
                        e.preventDefault();
                        const action = button.dataset.action;
                        const paymentId = button.dataset.id;
                        let newStatus = '';
                        let requiresNote = false;

                        switch(action) {
                            case 'mark-paid': newStatus = 'pago'; break;
                            case 'mark-error': newStatus = 'erro'; requiresNote = true; break;
                            case 'mark-pending': newStatus = 'pendente'; break;
                            default: return;
                        }

                        if(paymentId && newStatus) {
                            let observation = '';
                            if (requiresNote) {
                                observation = prompt(`Adicionar uma nota para o erro (ID Pag: ${paymentId}):`, '');
                                if (observation === null) return; // Cancelou
                            }
                            updatePaymentStatus(paymentId, newStatus, button, observation);
                        }
                    }
                });
            }

             // Botão de Download CSV
             const downloadBtn = document.getElementById('downloadCsvBtn');
             const dateFilterInput = document.getElementById('filter_date');
             if (downloadBtn && dateFilterInput) {
                 downloadBtn.addEventListener('click', () => {
                     const selectedDate = dateFilterInput.value;
                     if (selectedDate) {
                          // Abre a URL da API de download em uma nova aba (ou força download)
                          window.open(`/admin/api/download_payment_report.php?date=${selectedDate}`, '_blank');
                     } else {
                         showToast('Selecione uma data no filtro para baixar o CSV.', 'warning');
                     }
                 });

                 // Atualiza o texto e estado do botão quando a data muda
                  dateFilterInput.addEventListener('change', () => {
                      const selectedDate = dateFilterInput.value;
                      if (selectedDate) {
                          const dateParts = selectedDate.split('-'); // YYYY-MM-DD
                          const formattedDate = `${dateParts[2]}/${dateParts[1]}`; // DD/MM
                          downloadBtn.innerHTML = `<i class="fas fa-download"></i> Baixar CSV (${formattedDate})`;
                          downloadBtn.disabled = false;
                          downloadBtn.title = `Baixar relatório CSV para ${formattedDate}/${dateParts[0]}`;
                      } else {
                          downloadBtn.innerHTML = `<i class="fas fa-download"></i> Baixar CSV`;
                          downloadBtn.disabled = true;
                           downloadBtn.title = 'Selecione uma data para baixar';
                      }
                  });
             }

        }); // Fim DOMContentLoaded

        async function updatePaymentStatus(paymentId, newStatus, button, observation = '') {
             const originalHTML = button.innerHTML;
             button.disabled = true;
             button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                 const response = await fetch('/admin/api/update_payment_status.php', { // API criada no Passo 6
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-Requested-With': 'XMLHttpRequest',
                         'Accept': 'application/json'
                     },
                     credentials: 'include',
                     body: JSON.stringify({
                         payment_id: paymentId,
                         new_status: newStatus,
                         observation: observation // Envia a observação
                     })
                 });

                 const responseText = await response.text();
                 let data;
                 try { data = JSON.parse(responseText); } catch(e) { throw new Error(`Resposta inválida (Status: ${response.status})`); }

                 if (!response.ok || !data.success) { throw new Error(data.message || `Erro ${response.status}`); }

                 showToast(data.message || 'Status atualizado!', 'success');
                 window.location.reload(); // Recarrega a página para ver a mudança

             } catch (error) {
                 console.error("Erro ao atualizar status:", error);
                 showToast(`Falha: ${error.message}`, 'error');
                 button.disabled = false;
                 button.innerHTML = originalHTML;
             }
        }

    </script>
</body>
</html>