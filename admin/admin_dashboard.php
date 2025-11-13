<?php
// Define o fuso horário padrão para São Paulo (IMPORTANTE!)
date_default_timezone_set('America/Sao_Paulo');

// --- Configurações Iniciais e Autenticação ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Configurações de Erro (Produção) ---
ini_set('display_errors', 0); // Não mostrar erros na tela
ini_set('log_errors', 1);    // Habilitar log de erros
$log_file_path = __DIR__ . '/../logs/admin_dashboard_errors.log'; // Caminho para o arquivo de log
// Tenta criar o diretório de logs se ele não existir
if (!file_exists(dirname($log_file_path))) {
    @mkdir(dirname($log_file_path), 0755, true); // O @ suprime erros se o diretório já existir ou não puder ser criado
}
ini_set('error_log', $log_file_path); // Define o arquivo de log
error_reporting(E_ALL); // Logar todos os tipos de erros
error_log("--- Admin Dashboard Started ---"); // Marca o início da execução no log

// --- Includes e Conexão DB ---
$base_dir = dirname(__DIR__) . '/'; // Diretório pai do diretório 'admin' (onde este script está)
$db_path = $base_dir . 'includes/db.php'; // Caminho para o arquivo de conexão
// Verifica se o arquivo de conexão existe
if (!file_exists($db_path)) {
    error_log("CRITICAL ERROR: db.php not found at: " . $db_path);
    die("Erro crítico: Arquivo de configuração do banco de dados não encontrado."); // Interrompe se não encontrar
}
require_once $db_path; // Inclui o arquivo de conexão
// Verifica se a variável de conexão $pdo foi criada corretamente no arquivo db.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("CRITICAL ERROR: PDO connection object (\$pdo) not established in db.php.");
    die("Erro crítico: Falha na conexão com o banco de dados. Verifique includes/db.php."); // Interrompe se a conexão falhou
}
error_log("PDO connection established for Admin Dashboard.");

// --- Autenticação de Administrador ---
// Verifica se o usuário está logado E se ele tem permissão de administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // Registra a tentativa de acesso negado no log
    error_log("Admin Access Denied. UserID: ".($_SESSION['user_id'] ?? 'Not Set')." IsAdmin: ".($_SESSION['is_admin'] ?? 'Not Set'));
    $_SESSION['error'] = 'Acesso restrito à área administrativa.'; // Mensagem para o usuário (opcional)
    header("Location: ../login.php"); // Redireciona para a página de login principal
    exit(); // Interrompe a execução do script
}
$adminUserId = (int)$_SESSION['user_id']; // ID do administrador logado
error_log("Admin Dashboard accessed by Admin ID: " . $adminUserId);

// --- Constantes e Funções Auxiliares PHP ---

// Define um nome de arquivo de avatar padrão se não estiver definido
defined('DEFAULT_AVATAR') or define('DEFAULT_AVATAR', 'default.jpg'); // Verifique se 'default.jpg' existe em /uploads/avatars/

// Função para escapar HTML de forma segura (evitar XSS)
if (!function_exists('escapeHTML')) {
    function escapeHTML($str) {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Função para decodificar JSON de forma segura, retornando null em caso de erro ou se não for array/objeto
if (!function_exists('parseJsonSafe')) {
    function parseJsonSafe($jsonString) {
        if ($jsonString === null || $jsonString === '') return null;
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR); // Usa flags de segurança
            // Garante que o resultado seja um array (associativo ou indexado)
            if (!is_array($data)) {
                 error_log("JSON Parse Warning: Decoded data is not an array. Type: " . gettype($data) . " | Data Preview: " . substr($jsonString, 0, 100));
                 return null;
             }
            return $data;
        } catch (JsonException $e) { // Captura erros específicos de JSON
            error_log("JSON Parse Error: " . $e->getMessage() . " | Data Preview: " . substr($jsonString, 0, 100));
            return null;
        } catch (Throwable $t) { // Captura outros erros genéricos que podem ocorrer
            error_log("JSON Parse General Error: " . $t->getMessage() . " | Data Preview: " . substr($jsonString, 0, 100));
            return null;
        }
    }
}

// Função para formatar datas de forma amigável (Hoje, Ontem, DD/MM, DD/MM/YY) - VERSÃO CORRIGIDA
if (!function_exists('formatDateFullPHP')) {
    function formatDateFullPHP($dateString) {
        if (empty($dateString)) return 'N/A'; // Retorna 'N/A' se a string da data estiver vazia
        try {
            // Cria um objeto DateTimeImmutable a partir da string (imutável para segurança)
            $date = new DateTimeImmutable($dateString);
            // Define o fuso horário para consistência (o mesmo definido no início do script)
            $timezone = new DateTimeZone(date_default_timezone_get());
            $now = new DateTimeImmutable('now', $timezone); // Pega a hora atual no mesmo fuso
            $date = $date->setTimezone($timezone); // Garante que a data do evento use o mesmo fuso

            // Define o início do dia de hoje e ontem para comparação
            $today_start = $now->setTime(0, 0, 0);
            $yesterday_start = $today_start->modify('-1 day');

            // Compara APENAS a parte da data (Ano-Mês-Dia)
            if ($date->format('Y-m-d') === $today_start->format('Y-m-d')) {
                // Se for exatamente HOJE
                return 'Hoje às ' . $date->format('H:i');
            } elseif ($date->format('Y-m-d') === $yesterday_start->format('Y-m-d')) {
                // Se for exatamente ONTEM
                return 'Ontem às ' . $date->format('H:i');
            } else {
                // Se for outra data (passada OU FUTURA)
                if ($date->format('Y') === $now->format('Y')) {
                    // Se for no mesmo ano, formato DD/MM HH:MM
                    return $date->format('d/m H:i');
                } else {
                    // Se for em ano diferente, formato DD/MM/YY HH:MM
                    return $date->format('d/m/y H:i');
                }
            }
        } catch(Exception $e) {
            // Loga o erro se a formatação falhar e retorna 'Data Inválida'
            error_log("Error formatting date PHP: {$dateString} - " . $e->getMessage());
            return 'Data Inválida';
        }
    }
}

// Função para formatar tamanho de arquivo (Bytes, KB, MB...)
if (!function_exists('formatBytesPHP')) {
    function formatBytesPHP($bytes, $decimals = 1) {
        $bytes = filter_var($bytes, FILTER_VALIDATE_FLOAT); // Valida se é um número
        if ($bytes === false || $bytes <= 0) return '0 Bytes'; // Retorna 0 se inválido ou zero
        $k = 1024; // Base para cálculo
        $dm = max(0, (int)$decimals); // Garante que decimais não seja negativo
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']; // Unidades
        // Calcula o índice da unidade correta
        $i = min(count($sizes) - 1, floor(log($bytes) / log($k)));
        // Formata e retorna o valor com a unidade
        return sprintf("%.{$dm}f", $bytes / pow($k, $i)) . ' ' . $sizes[$i];
    }
}

if (!function_exists('formatWhatsappLink')) {
    function formatWhatsappLink($phone) {
        if (empty($phone)) return '#';
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 10) {
            if (strlen($digits) == 10 || strlen($digits) == 11) {
                if (substr($digits, 0, 2) !== '55') $digits = '55' . $digits;
            }
            if (strpos($digits, '+') !== 0) $digits = '+' . $digits;
            return 'https://wa.me/' . $digits;
        }
        return '#';
    }
}
// *NOVO* Função para formatar telefone
if (!function_exists('formatDisplayPhone')) {
    function formatDisplayPhone($phone) {
        if (empty($phone)) return 'N/A';
        $digits = preg_replace('/\D/', '', $phone); $len = strlen($digits);
        try {
            if ($len == 10) return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4);
            if ($len == 11) return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7, 4);
            if ($len == 12 && strpos($digits, '55') === 0) return '+55 (' . substr($digits, 2, 2) . ') ' . substr($digits, 4, 4) . '-' . substr($digits, 8, 4);
            if ($len == 13 && strpos($digits, '55') === 0) return '+55 (' . substr($digits, 2, 2) . ') ' . substr($digits, 4, 5) . '-' . substr($digits, 9, 4);
        } catch (Exception $e) {}
        return $phone;
    }
}

// Função para obter a classe do ícone Font Awesome baseado no tipo de evento
if (!function_exists('getIconClassPHP')) {
    function getIconClassPHP($eventType) {
        switch ($eventType) {
            case 'call_request': return 'fa-video';
            case 'proof_upload': return 'fa-receipt';
            case 'whatsapp_request': return 'fa-brands fa-whatsapp';
            case 'model_selected': return 'fa-robot';
            case 'profile_update': return 'fa-user-edit';
            case 'user_registered': return 'fa-user-plus';
            case 'user_login': return 'fa-sign-in-alt';
            case 'user_logout': return 'fa-sign-out-alt'; // Ícone para logout
            case 'lead_purchase': return 'fa-shopping-cart';
            case 'admin_notification': return 'fa-bell';
            case 'pix_payment_reported': return 'fa-money-check-alt'; // Ou fa-qrcode
            default: return 'fa-info-circle'; // Ícone padrão
        }
    }
}

if (!function_exists('getWhatsappStatusBadgeInfo')) {
    function getWhatsappStatusBadgeInfo($status, $statusLabels) {
        $info = [
            'class' => 'status-default', // Classe padrão
            'icon' => 'fa-question-circle', // Ícone padrão
            'label' => ucfirst(str_replace('_', ' ', $status ?: 'Desconhecido'))
        ];

        // Pega o label amigável, se existir
        if (isset($statusLabels[$status])) {
            $info['label'] = $statusLabels[$status];
        }

        // Define classe e ícone com base no status
        switch ($status) {
            case 'pending':
                $info['class'] = 'status-pending';
                $info['icon'] = 'fa-hourglass-half';
                break;
            case 'aguardando_resposta':
                $info['class'] = 'status-aguardando_resposta';
                $info['icon'] = 'fa-clock'; // Ou fa-comments
                break;
            case 'processing':
                $info['class'] = 'status-processing';
                $info['icon'] = 'fa-cogs'; // Ou fa-spinner fa-spin
                break;
            case 'approved':
                $info['class'] = 'status-approved';
                $info['icon'] = 'fa-check-circle';
                break;
            case 'rejected':
                $info['class'] = 'status-rejected';
                $info['icon'] = 'fa-times-circle';
                break;
        }
        return $info;
    }
}

// Função para obter a variável de cor CSS para o ícone baseado no tipo de evento
if (!function_exists('getIconColorVariablePHP')) {
     function getIconColorVariablePHP($eventType) {
         switch ($eventType) {
            case 'call_request': return 'var(--info)';
            case 'proof_upload': return 'var(--success)';
            case 'whatsapp_request': return 'var(--whatsapp-green)';
            case 'model_selected': return 'var(--secondary)';
            case 'profile_update': return 'var(--warning)';
            case 'user_registered': return 'var(--primary)';
            case 'user_login': return 'var(--success)';
            case 'user_logout': return 'var(--danger)'; // Vermelho para logout
            case 'lead_purchase': return 'var(--info)';
            case 'admin_notification': return 'var(--primary)';
            case 'pix_payment_reported': return 'fa-money-check-alt'; // Ou fa-qrcode
             default: return 'var(--text-secondary)'; // Cor padrão
         }
     }
}

// Mapeamento de status (*MODIFICADO* Adicionado aguardando_resposta)
$statusLabelsPHP = [
    'pending' => 'Pendente',
    'aguardando_resposta' => 'Aguard. Resposta', // <-- Adicionado
    'in_progress' => 'Em Andamento',
    'completed' => 'Concluída',
    'cancelled' => 'Cancelada',
    'processing' => 'Processando',
    'approved' => 'Aprovado',
    'rejected' => 'Rejeitado',
    'pending_review' => 'Pendente Revisão',
    'active' => 'Ativo',
    'inactive' => 'Inativo',
    'paid' => 'Pago',
    'sent' => 'Enviado',
    'read' => 'Lido',
];

// Opções de status (*MODIFICADO* Atualizado whatsapp_request)
$allowedStatusesOptionsPHP = [
    'call_request' => ['pending', 'in_progress', 'completed', 'cancelled'],
    'whatsapp_request' => ['pending', 'aguardando_resposta', 'processing', 'approved', 'rejected'], // <-- Atualizado
    'proof_upload' => ['pending_review', 'approved', 'rejected'],
    'lead_purchase' => ['pending', 'paid', 'cancelled'],
    'admin_notification' => ['sent', 'read'],
    'pix_payment_reported' => ['pending_review', 'approved', 'rejected'], // Mantido se já existia
];

// Função para gerar o HTML do dropdown de status para itens do feed
if (!function_exists('createStatusDropdownPHP')) {
    function createStatusDropdownPHP($eventId, $eventType, $currentStatus, $allowedStatusesOptions) {
        global $statusLabelsPHP; // Acessa o array global de labels
        // Pega as opções de status permitidas para este tipo de evento
        $possibleStatuses = $allowedStatusesOptions[$eventType] ?? [];
        // Se não houver status editáveis para este tipo, retorna string vazia
        if (empty($possibleStatuses)) return '';

        $optionsHTML = '';
        // Cria cada <option>
        foreach ($possibleStatuses as $status) {
            // Pega o label amigável ou usa o próprio nome do status formatado
            $label = $statusLabelsPHP[$status] ?? ucfirst(str_replace('_', ' ', $status));
            // Marca a opção atual como 'selected'
            $selected = ($status === $currentStatus) ? 'selected' : '';
            $optionsHTML .= "<option value=\"" . escapeHTML($status) . "\" $selected>" . escapeHTML($label) . "</option>";
        }

        // Retorna o HTML completo do <select>
        return "<div class=\"feed-item-status\">
            <select class=\"form-control feed-status-select\"
                    data-event-id=\"$eventId\"
                    data-event-type=\"$eventType\"
                    data-current-status=\"".escapeHTML($currentStatus ?? '')."\">
                $optionsHTML
            </select>
        </div>";
    }
}

$predefinedNotifications = [
    [
        'id' => 'proof_approved', // Um ID único para identificar este modelo
        'category' => 'Comprovante Aprovado', // O nome que aparecerá na lista para escolher
        'title' => '✅ Comprovante Aprovado!', // O título que será preenchido automaticamente
        'message' => "Olá [User Name],\n\nSeu comprovante recente foi APROVADO com sucesso.\nO valor correspondente já foi adicionado ao seu saldo.\n\nObrigado!" // A mensagem (o [User Name] será trocado depois)
    ],
    [
        'id' => 'proof_rejected_generic',
        'category' => 'Comprovante Rejeitado (Genérico)',
        'title' => '❌ Comprovante Rejeitado',
        'message' => "Olá [User Name],\n\nInfelizmente, seu comprovante recente foi REJEITADO.\n\nMotivo: [DIGITE O MOTIVO AQUI - Ex: Qualidade da imagem, dados ilegíveis, etc.].\n\nPor favor, verifique e envie novamente um comprovante válido.\n\nAtenciosamente,\nEquipe Love Chat"
    ],
    [
        'id' => 'proof_rejected_value',
        'category' => 'Comprovante Rejeitado (Valor)',
        'title' => '❌ Comprovante Rejeitado - Valor',
        'message' => "Olá [User Name],\n\nSeu comprovante recente foi REJEITADO.\n\nMotivo: O valor no comprovante ([DIGITE O VALOR DO COMPROVANTE]) não corresponde ao valor da transação/pacote selecionado.\n\nPor favor, verifique e envie o comprovante correto.\n\nAtenciosamente,\nEquipe Love Chat"
    ],
    [
        'id' => 'whatsapp_approved',
        'category' => 'WhatsApp Aprovado',
        'title' => '🟢 WhatsApp Liberado!',
        'message' => "Olá [User Name],\n\nBoas notícias! Sua solicitação para usar o número de WhatsApp no serviço foi APROVADA.\n\nNúmero: [COLE O NÚMERO APROVADO AQUI]\n\nVocê já pode configurá-lo e começar a usar.\n\nEquipe Love Chat"
    ],
    [
        'id' => 'whatsapp_rejected',
        'category' => 'WhatsApp Rejeitado',
        'title' => '🔴 Solicitação WhatsApp Rejeitada',
        'message' => "Olá [User Name],\n\nSua solicitação de número WhatsApp foi REJEITADA.\n\nMotivo: [DIGITE O MOTIVO AQUI - Ex: Número já em uso, política interna, etc.].\n\nSe tiver dúvidas, entre em contato com o suporte.\n\nAtenciosamente,\nEquipe Love Chat"
    ],
     [
        'id' => 'welcome_message',
        'category' => 'Boas Vindas',
        'title' => '👋 Bem-vindo(a) ao Love Chat!',
        'message' => "Olá [User Name],\n\nSeja muito bem-vindo(a) à nossa plataforma! 🎉\n\nExplore nossos recursos e, se precisar de ajuda, é só chamar.\n\nDivirta-se!\nEquipe Love Chat"
    ],
    [
        'id' => 'important_update',
        'category' => 'Aviso Importante',
        'title' => '📢 Aviso Importante!',
        'message' => "Olá [User Name],\n\nTemos uma atualização importante sobre [ASSUNTO DO AVISO].\n\n[DETALHES DA ATUALIZAÇÃO AQUI].\n\nLeia com atenção!\n\nAtenciosamente,\nEquipe Love Chat"
    ]
    // --- Adicione mais modelos aqui se precisar, seguindo o mesmo formato ---
];

// --- Busca de Dados Iniciais para a Página ---
$users = [];
$initial_feed_items = [];
// $pendingWhatsappRequests = []; // <<<<< VARIÁVEL ANTIGA REMOVIDA/COMENTADA
$whatsappRequestsFromUsers = []; // <<<<< NOVA VARIÁVEL PARA DADOS DO WHATSAPP
$db_error = null;
// $adminUserId já foi definido acima

try {
    // Configura PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
    error_log("Fetching initial data for admin dashboard (v2 - Users Source for WA)..."); // Mensagem de log atualizada

    // --- Query Usuários (Mantida igual) ---
    $today = date('Y-m-d');
    $stmt_users = $pdo->prepare("
        SELECT u.id, u.nome, u.email, u.avatar, u.is_admin, u.is_active, u.created_at, u.last_login, u.login_count, u.is_approved,
               u.telefone, u.tipo_chave_pix, u.chave_pix,
               u.whatsapp_numero, u.whatsapp_aprovado, u.whatsapp_solicitado, u.registration_ip,
               COALESCE(sd.saldo, 0.00) as saldo_hoje
        FROM usuarios u
        LEFT JOIN saldos_diarios sd ON u.id = sd.usuario_id AND sd.data = :today
        ORDER BY u.created_at DESC
    ");
    $stmt_users->execute([':today' => $today]);
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // --- Query Feed (Mantida igual - para o feed visual) ---
    $feed_event_types_to_show = [ 'proof_upload', 'call_request', 'pix_payment_reported', 'whatsapp_request', 'user_registered' ]; // Mantém 'whatsapp_request' aqui se quiser ver o *histórico* que sobrou ou que será criado
    $params = [':admin_id' => $adminUserId];
    $inPlaceholders = [];
    foreach ($feed_event_types_to_show as $index => $type) {
        $placeholderName = ':type' . $index;
        $inPlaceholders[] = $placeholderName;
        $params[$placeholderName] = $type;
    }
    $placeholdersString = implode(',', $inPlaceholders);
    $sql_feed = "
        SELECT
            fe.*, u.nome as user_nome, u.avatar as user_avatar, /* Removido u.whatsapp_numero daqui, buscamos direto em usuarios */
            COALESCE(m.nome, CONCAT('Modelo ID: ', fe.related_id)) as modelo_nome, m.imagem as modelo_avatar,
            (afrs.admin_id IS NOT NULL) as is_read_by_admin
        FROM admin_events_feed fe
        LEFT JOIN usuarios u ON fe.user_id = u.id
        LEFT JOIN modelos_ia m ON fe.related_id = m.id AND fe.event_type = 'model_selected' AND fe.related_id REGEXP '^[1-9][0-9]*$'
        LEFT JOIN admin_feed_read_status afrs ON fe.id = afrs.event_id AND afrs.admin_id = :admin_id
        WHERE fe.event_type IN ($placeholdersString)
        ORDER BY fe.created_at DESC
        LIMIT 200
    ";
    $stmt_feed = $pdo->prepare($sql_feed);
    $stmt_feed->execute($params);
    $initial_feed_items = $stmt_feed->fetchAll(PDO::FETCH_ASSOC);


    // <<<<< QUERY ANTIGA DO WHATSAPP REMOVIDA/COMENTADA >>>>>
    /*
    $stmt_pending_wa = $pdo->query("
        SELECT
            fe.id as event_id, fe.user_id, fe.created_at as request_time, fe.event_data, fe.status as current_status,
            u.nome as user_nome, u.avatar as user_avatar, u.telefone as user_personal_phone
        FROM admin_events_feed fe
        JOIN usuarios u ON fe.user_id = u.id
        WHERE fe.event_type = 'whatsapp_request'
        ORDER BY fe.created_at ASC
    ");
    $pendingWhatsappRequests = $stmt_pending_wa->fetchAll(PDO::FETCH_ASSOC);
    */

    // ++++++ INÍCIO DA NOVA QUERY PARA WHATSAPP (LENDO DA TABELA usuarios) ++++++
      // ++++++ INÍCIO DA QUERY WHATSAPP (v5 - LENDO COLUNA whatsapp_status - Default Otimizado) ++++++
    $stmt_wa_users = $pdo->prepare("
        SELECT
            u.id as user_id,
            u.nome as user_nome,
            u.avatar as user_avatar,
            u.telefone as user_personal_phone,
            u.whatsapp_numero as whatsapp_service_number,
            u.whatsapp_solicitado, -- Mantido para referência
            u.whatsapp_aprovado,   -- Mantido para referência
            u.whatsapp_data_solicitacao as request_time,
            -- Lê diretamente a coluna whatsapp_status ou usa fallback com CASE
            COALESCE(u.whatsapp_status,
                CASE
                    WHEN u.whatsapp_aprovado = 1 THEN 'approved' -- Fallback: NULL + Aprovado = 'approved'
                    WHEN u.whatsapp_solicitado = 1 THEN 'pending'  -- Fallback: NULL + Solicitado = 'pending'
                    ELSE 'unknown'                            -- Fallback geral
                END
            ) as current_status -- Nome final do alias
        FROM usuarios u
        WHERE
            -- Mostra todos que solicitaram E AINDA não tem status definido (pendente implícito)
            (u.whatsapp_status IS NULL AND u.whatsapp_solicitado = 1)
            OR
            -- OU mostra TODOS que JÁ TEM um status definido (incluindo 'rejected')
            (u.whatsapp_status IS NOT NULL) -- <> é o mesmo que !=
        ORDER BY
            -- Ordena pela sequência lógica dos status, tratando NULL como 'pending'
            FIELD(COALESCE(u.whatsapp_status, 'pending'), 'pending', 'aguardando_resposta', 'processing', 'approved', 'unknown'),
            u.whatsapp_data_solicitacao ASC -- Desempate pela data de solicitação
    ");
    $stmt_wa_users->execute();
    $whatsappRequestsFromUsers = $stmt_wa_users->fetchAll(PDO::FETCH_ASSOC);
    // ++++++ FIM DA NOVA QUERY PARA WHATSAPP ++++++


    // Log de sucesso (atualizado para usar a nova variável)
    error_log("Fetched initial data: Users=".count($users).", Feed=".count($initial_feed_items).", WAPending(Users)=".count($whatsappRequestsFromUsers));

} catch (PDOException $e) {
    // ... (seu catch PDO - MANTENHA IGUAL) ...
     error_log("DB Query Error in Admin Dashboard (Initial Fetch): " . $e->getMessage() . " | SQL tried: " . ($sql ?? 'N/A'));
     $db_error = "Erro ao carregar dados iniciais. Verifique os logs."; // Mensagem mais genérica
} catch (Throwable $t) {
    // ... (seu catch Throwable - MANTENHA IGUAL) ...
     error_log("General Error in Admin Dashboard (Initial Fetch): " . $t->getMessage());
     $db_error = "Erro interno inesperado ao carregar dados. Verifique os logs.";
}

// ----- IMPORTANTE: -----
// Mais abaixo no seu código HTML, quando for gerar a tabela de WhatsApp Pendente
// (dentro da div #whatsapp_pending-section), você precisa mudar o loop para usar a nova variável:
//
// Troque:
// foreach ($pendingWhatsappRequests as $request):
//
// Por:
// foreach ($whatsappRequestsFromUsers as $request):
//
// E ajuste as variáveis DENTRO do loop como mostrado na minha resposta anterior
// (ex: $currentStatusWA = $request['current_status_derived']; etc.)
// e lembre-se de ajustar a API que lida com as ações de Aprovar/Rejeitar.
// ----- FIM DA OBSERVAÇÃO IMPORTANTE -----

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - Love Chat</title>

    <!-- Fontes e Ícones -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
</head>
<body>
    <div class="app-container">
        <!-- Overlay Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="https://i.ibb.co/gbZf3dY6/love-chat.webp" alt="Love Chat Admin" class="sidebar-logo">
            </div>

            <div class="user-profile">
                 <img src="/uploads/avatars/<?php echo escapeHTML($_SESSION['user_avatar'] ?? DEFAULT_AVATAR); ?>?v=<?php echo time(); ?>"
                      alt="Admin Avatar" class="user-avatar"
                      id="sidebarAvatar"
                      onerror="this.onerror=null;this.src='/uploads/avatars/<?php echo DEFAULT_AVATAR; ?>?v=<?php echo time(); ?>'">
                 <div class="user-info">
                    <h3 id="sidebarUserName"><?php echo escapeHTML($_SESSION['user_nome'] ?? 'Admin'); ?></h3>
                    <p><i class="fas fa-crown" style="color: var(--warning); margin-right: 4px;"></i> Administrador</p>
                </div>
            </div>

            <nav class="sidebar-menu" aria-label="Menu principal">
                <a href="#" class="menu-item active" data-section="dashboard">
                    <i class="fas fa-stream"></i> <!-- Ícone alterado -->
                    <span>Feed Atividades</span>
                </a>
                <a href="#" class="menu-item" data-section="usuarios">
                    <i class="fas fa-users-cog"></i>
                    <span>Gerenciar Usuários</span>
                </a>
                
                <a href="#" class="menu-item" data-section="whatsapp_pending">
    <i class="fab fa-whatsapp" style="color: var(--whatsapp-green);"></i>
    <span>WhatsApp Business</span>
    <?php
    // ----- INÍCIO DA LÓGICA CORRETA PARA O BADGE -----
    $strictlyPendingWACount = 0; // Zera o contador

    // Verifica se a variável com os dados da lista existe e é um array
    if (isset($whatsappRequestsFromUsers) && is_array($whatsappRequestsFromUsers)) {
        // Itera sobre TODOS os usuários que solicitaram (os mesmos da lista da aba)
        foreach ($whatsappRequestsFromUsers as $req) {
            // CONTA APENAS SE: solicitado for 1 E aprovado for 0
            if (isset($req['whatsapp_solicitado']) && $req['whatsapp_solicitado'] == 1 &&
                isset($req['whatsapp_aprovado']) && $req['whatsapp_aprovado'] == 0) {
                // Se as duas condições são verdadeiras, incrementa o contador DO BADGE
                $strictlyPendingWACount++;
            }
        }
    } else {
        // Loga um erro se a variável base não existir (ajuda a debugar)
        error_log("Admin Dashboard Sidebar: Variavel \$whatsappRequestsFromUsers nao definida ou nao e um array ao calcular contagem de pendentes para o badge.");
    }
    // ----- FIM DA LÓGICA CORRETA PARA O BADGE -----
    ?>
    <?php // Este HTML usa a contagem CORRETA calculada acima ?>
    <span class="badge" id="pending-wa-count-badge" style="<?php echo $strictlyPendingWACount > 0 ? '' : 'display:none;'; ?>"><?php echo $strictlyPendingWACount; ?></span>
</a>
            
            
                  <a href="#" class="menu-item" data-section="rank">
                <i class="fas fa-trophy"></i>
                <span>Rank</span>
            </a>
            <a href="#" class="menu-item" data-section="gerenciamento_saldos">
                <i class="fas fa-wallet"></i>
                <span>Gerenciar Saldos</span>
            </a>
            
            
                
                <a href="../dashboard.php" class="menu-item">
    <i class="fas fa-tachometer-alt"></i> <!-- Ou mude para fas fa-arrow-left -->
    <span>Voltar ao Dashboard</span> <!-- Texto mais claro -->
</a>
                <a href="../profile.php" class="menu-item" target="_blank"> <!-- Abrir em nova aba -->
                    <i class="fas fa-user-shield"></i>
                    <span>Meu Perfil</span>
                </a>
                <!-- Adicionar mais links administrativos aqui -->
                <!--
                <a href="#" class="menu-item" data-section="configuracoes">
                    <i class="fas fa-cogs"></i>
                    <span>Configurações</span>
                </a>
                <a href="#" class="menu-item" data-section="relatorios">
                    <i class="fas fa-chart-line"></i>
                    <span>Relatórios</span>
                </a>
                 -->
            </nav>

            <div class="logout-btn-container">
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                 <!-- Botão de Menu Mobile -->
                <button class="menu-toggle" id="menuToggle" aria-label="Abrir menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 id="main-content-title">Feed de Atividades</h1>
                 <!-- Adicionar ações rápidas aqui se necessário -->
                 <!--
                 <div class="header-actions">
                     <button class="btn btn-primary"><i class="fas fa-plus"></i> Novo Usuário</button>
                 </div>
                 -->
            </div>

            <?php if ($db_error): ?>
                <div class="message error" style="background-color: var(--danger-light); color: var(--danger); padding: var(--space-md); border-radius: var(--border-radius-md); border: 1px solid var(--danger); margin-bottom: var(--space-lg); display: flex; align-items: center; gap: var(--space-sm);">
                    <i class="fas fa-times-circle"></i> <?php echo escapeHTML($db_error); ?>
                </div>
            <?php endif; ?>

              <div id="dashboard-section" class="content-section active">
                <h2 class="section-title"><i class="fas fa-history"></i> Histórico Recente</h2>
                <p class="section-description">Acompanhe as últimas ações e eventos dos usuários na plataforma em tempo real.</p>
                <button id="clearAllFeedBtn" class="btn btn-delete" data-action="clear-feed-all" style="float: right; margin-bottom: var(--space-md);"><i class="fas fa-trash-alt"></i> Limpar Tudo</button>
                <div style="clear: both;"></div>

                 <!-- Loading e Mensagem 'Sem Itens' movidos para fora do container do feed -->
                <div id="feed-loading" style="text-align: center; padding: var(--space-xl); color: var(--text-secondary); font-size: var(--font-size-sm); background-color: var(--gray); border-radius: var(--border-radius-lg); margin-top: var(--space-lg); display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Carregando novas atividades...
                </div>
                 <p id="no-feed-items" style="text-align: center; padding: 40px; color: var(--text-secondary); background-color: var(--gray); border-radius: var(--border-radius-lg); margin-top: var(--space-lg); <?php echo (empty($initial_feed_items) && !$db_error) ? '' : 'display: none;'; ?>">
                    <i class="fas fa-wind fa-lg" style="margin-bottom: 10px;"></i><br>
                    Nenhuma atividade encontrada para exibir.
                </p>
                
                         <div id="feed-pagination-top" class="feed-pagination" style="text-align: center; margin-top: var(--space-lg); display: none;">
                <button id="feed-prev-page-top" class="btn btn-secondary btn-sm" disabled>« Anterior</button>
                <span id="feed-page-info-top" style="margin: 0 var(--space-md); color: var(--text-secondary); font-size: var(--font-size-sm);">
                    Página <span id="feed-current-page-top">1</span> de <span id="feed-total-pages-top">1</span>
                </span>
                <button id="feed-next-page-top" class="btn btn-secondary btn-sm">Próximo »</button>
             </div>

                <!-- Container onde o JS vai renderizar os itens paginados -->
                <div id="realtime-feed" style="display: flex; flex-direction: column; gap: var(--space-md); margin-top: var(--space-lg);">
                    <!-- O loop PHP foi REMOVIDO daqui -->
                </div> <!-- Fim #realtime-feed -->

                <!-- Controles de Paginação -->
                <div id="feed-pagination" class="feed-pagination" style="text-align: center; margin-top: var(--space-lg); display: none;">
                    <button id="feed-prev-page" class="btn btn-secondary btn-sm" disabled>« Anterior</button>
                    <span id="feed-page-info" style="margin: 0 var(--space-md); color: var(--text-secondary); font-size: var(--font-size-sm);">
                        Página <span id="feed-current-page">1</span> de <span id="feed-total-pages">1</span>
                    </span>
                    <button id="feed-next-page" class="btn btn-secondary btn-sm">Próximo »</button>
                </div>

            </div> <!-- Fim #dashboard-section -->


            <!-- === Seção Usuários === -->
            <div id="usuarios-section" class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-users-cog"></i> Gerenciamento de Usuários
                </h2>
                <p class="section-description">
                    Visualize, edite detalhes, status, envie notificações ou desative/reative contas de usuários.
                </p>

                <div class="search-input-container">
                    <i class="fas fa-search search-input-icon"></i>
                    <input type="search" id="userSearchInput" class="form-control search-input" placeholder="Buscar usuário por nome...">
                </div>

                <div class="table-container">
                    <table class="admin-table" id="usersTable"> <!-- ID está correto -->
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Tipo</th>
                                <th>Status Conta</th>
                                <th>Status Aprovação</th> <!-- Nova Coluna -->
                                <th>Saldo Hoje</th>
                                <th>Registro</th>
                                <th style="text-align: right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);"> <!-- Colspan correto -->
                                        <i class="fas fa-ghost fa-2x" style="margin-bottom: 10px;"></i><br>
                                        Nenhum usuário encontrado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <!-- LINHA CORRIGIDA ABAIXO -->
                                <tr data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo escapeHTML(strtolower($user['nome'])); ?>">
                                    <td>
                                        <div class="user-info-cell">
                                            <img src="/uploads/avatars/<?php echo escapeHTML($user['avatar'] ?? DEFAULT_AVATAR); ?>?v=<?php echo time(); ?>"
                                                 alt="Avatar de <?php echo escapeHTML($user['nome']); ?>"
                                                 class="user-avatar-sm"
                                                 onerror="this.onerror=null;this.src='/uploads/avatars/<?php echo DEFAULT_AVATAR; ?>'">
                                            <div>
                                                <span class="user-name"><?php echo escapeHTML($user['nome']); ?></span>
                                                <span class="user-email-sub"><?php echo escapeHTML($user['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['is_admin'] ? 'badge-admin' : 'badge-user'; ?>">
                                            <?php if ($user['is_admin']): ?>
                                                <i class="fas fa-crown"></i> Admin
                                            <?php else: ?>
                                                 <i class="fas fa-user"></i> Usuário
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                             <?php if ($user['is_active']): ?>
                                                <i class="fas fa-check-circle"></i> Ativo
                                            <?php else: ?>
                                                 <i class="fas fa-times-circle"></i> Inativo
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td> <!-- Nova Célula Status Aprovação -->
    <?php if ($user['is_approved']): ?>
        <span class="badge badge-success"> <!-- Pode usar uma classe CSS para aprovado -->
            <i class="fas fa-user-check"></i> Aprovado
        </span>
    <?php else: ?>
        <span class="badge badge-warning"> <!-- Pode usar uma classe CSS para pendente -->
            <i class="fas fa-hourglass-half"></i> Pendente
        </span>
    <?php endif; ?>
</td>
                                    <td class="user-balance-cell" style="font-weight: 600; color: <?php echo ((float)($user['saldo_hoje'] ?? 0.00)) > 0 ? 'var(--success)' : 'inherit'; ?>">
                                        R$ <?php echo number_format((float)($user['saldo_hoje'] ?? 0.00), 2, ',', '.'); ?>
                                    </td>
                                    <td title="<?php echo escapeHTML(date('d/m/Y H:i:s', strtotime($user['created_at']))); ?>">
                                        <?php echo $user['created_at'] ? formatDateFullPHP($user['created_at']) : 'N/A'; ?>
                                    </td>
                                    <td>
  <td> <!-- CÉLULA DE AÇÕES -->
    <div class="action-buttons" style="justify-content: flex-end;">
       
        <!-- Botões Padrão -->
         <button class="btn btn-details"
                data-action="details"
                data-user-id="<?php echo $user['id']; ?>"
                title="Ver Detalhes">
            <i class="fas fa-eye"></i>
        </button>
        <button class="btn btn-edit"
                data-action="edit"
                data-user-id="<?php echo $user['id']; ?>"
                title="Editar Usuário">
            <i class="fas fa-edit"></i>
        </button>
         <button class="btn btn-notify"
                data-action="notify"
                data-user-id="<?php echo $user['id']; ?>"
                data-user-name="<?php echo escapeHTML($user['nome']); ?>"
                title="Notificar Usuário">
            <i class="fas fa-bell"></i>
        </button>
        
         <?php if ($user['id'] != $adminUserId): // --- INÍCIO: Só mostra Ações de Status/Exclusão para outros usuários --- ?>

            <?php // =================================================== ?>
            <?php // ### INÍCIO: BOTÃO APROVAR (NOVO) ### ?>
            <?php if (!$user['is_approved']): // MOSTRAR APENAS SE O USUÁRIO NÃO ESTIVER APROVADO ?>
                 <button class="btn btn-sm btn-success"
                        data-action="approve-user"
                        data-user-id="<?php echo $user['id']; ?>"
                        data-user-name="<?php echo escapeHTML($user['nome']); ?>"
                        title="Aprovar este Usuário">
                    <i class="fas fa-thumbs-up"></i> Aprovar
                </button>
            <?php endif; ?>
             <?php // ### FIM: BOTÃO APROVAR ### ?>
             <?php // =================================================== ?>

            <?php // --- Botões Ativar / Desativar --- ?>
            <?php if ($user['is_active']): ?>
                <button class="btn btn-delete"
                         data-action="deactivate"
                        data-user-id="<?php echo $user['id']; ?>"
                        data-user-name="<?php echo escapeHTML($user['nome']); ?>"
                        title="Desativar Usuário">
                    <i class="fas fa-user-slash"></i>
                </button>
            <?php else: ?>
                    <button class="btn btn-activate"
                         data-action="activate"
                         data-user-id="<?php echo $user['id']; ?>"
                         data-user-name="<?php echo escapeHTML($user['nome']); ?>"
                        title="Reativar Usuário">
                    <i class="fas fa-user-check"></i>
                </button>
            <?php endif; ?>
            
             <?php // --- Outros Botões --- ?>
             <button class="btn btn-sm btn-warning btn-reset-balance"
                 data-action="reset-balance"
                 data-user-id="<?php echo $user['id']; ?>"
                 data-user-name="<?php echo escapeHTML($user['nome']); ?>"
                 title="Zerar Saldo Hoje">
               <i class="fas fa-dollar-sign"></i><i class="fas fa-times" style="font-size:0.7em; position: relative; top: -0.1em; left: -0.2em;"></i>
            </button>
        
             <button class="btn btn-sm btn-danger"
                    data-action="delete-permanently"
                    data-user-id="<?php echo $user['id']; ?>"
                     data-user-name="<?php echo escapeHTML($user['nome']); ?>"
                    title="EXCLUIR PERMANENTEMENTE (IRREVERSÍVEL!)">
                <i class="fas fa-skull-crossbones"></i> 
            </button>
                                        
        <?php endif; // --- FIM: if ($user['id'] != $adminUserId) --- ?>
    </div>
</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div> <!-- Fim .table-container -->
            </div> <!-- Fim #usuarios-section -->

            <!-- *NOVO* === Seção WhatsApp Pendente === -->
       <div id="whatsapp_pending-section" class="content-section">
     <h2 class="section-title"><i class="fab fa-whatsapp" style="color: var(--whatsapp-green);"></i> WhatsApp Business Status</h2>
     <p class="section-description">Revise, aprove, rejeite ou marque como "Aguardando Resposta" as solicitações de número para serviço.</p>
     
      <div class="search-input-container" style="margin-bottom: var(--space-lg);">
             <i class="fas fa-search search-input-icon"></i>
             <input type="search" id="whatsappSearchInput" class="form-control search-input" placeholder="Buscar usuário por nome...">
         </div>
     
             
             <!-- *NOVO* Controles de Filtro -->
<div class="whatsapp-filter-controls">
    <button class="filter-btn active" data-status="all">
        <i class="fas fa-list"></i> Todos <span class="filter-count-badge"></span>
    </button>
    <button class="filter-btn" data-status="pending">
        <i class="fas fa-hourglass-half" style="color: var(--warning);"></i> Pendente <span class="filter-count-badge"></span>
    </button>
    <button class="filter-btn" data-status="aguardando_resposta">
        <i class="fas fa-clock" style="color: var(--info);"></i> Aguard. Resposta <span class="filter-count-badge"></span>
    </button>
    <button class="filter-btn" data-status="processing">
        <i class="fas fa-cogs" style="color: var(--secondary);"></i> Processando <span class="filter-count-badge"></span>
    </button>
    <button class="filter-btn" data-status="approved">
        <i class="fas fa-check-circle" style="color: var(--success);"></i> Aprovado <span class="filter-count-badge"></span>
    </button>
    <button class="filter-btn" data-status="rejected">
        <i class="fas fa-times-circle" style="color: var(--danger);"></i> Rejeitado <span class="filter-count-badge"></span>
    </button>
</div>
<!-- Fim Controles de Filtro -->
             
             
             <div class="whatsapp-status-legend">
    <h4><i class="fas fa-info-circle"></i> Legenda de Status:</h4>
    <ul>
        <li>
            <span class="legend-color status-pending"></span>
            <div><strong>Pendente:</strong> Ainda não entramos em contato para cadastro.</div>
        </li>
        <li>
            <span class="legend-color status-aguardando_resposta"></span>
            <div><strong>Aguard. Resposta:</strong> Entramos em contato, aguardando retorno do usuário.</div>
        </li>
        <li>
            <span class="legend-color status-processing"></span>
            <div><strong>Processando:</strong> Usuário respondeu, em processo de cadastro.</div>
        </li>
        <li>
            <span class="legend-color status-approved"></span>
            <div><strong>Aprovado:</strong> WhatsApp cadastrado e funcionando.</div>
        </li>
        <li>
            <span class="legend-color status-rejected"></span>
            <div><strong>Rejeitado:</strong> Problema interno. Contatar usuário para nova solicitação.</div>
        </li>
    </ul>
</div>
             
           <div class="table-container">
         <table class="admin-table" id="pending-whatsapp-table">
             <thead>
                 <tr>
                     <th>Usuário</th>
                     <th>Número (Solic/Aprov)</th> <!-- Nome da coluna pode mudar -->
                     <th>Status Atual</th>
                     <th>Solicitado Em</th>
                     <th style="text-align: right; min-width: 260px;">Ações</th>
                 </tr>
             </thead>
                <tbody>
                <?php // ----- Loop para exibir as solicitações de WhatsApp ----- ?>
                <?php if (empty($whatsappRequestsFromUsers)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);"><i class="fas fa-check-circle fa-lg"></i> Nenhuma solicitação encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($whatsappRequestsFromUsers as $request):
                        // Processa dados específicos vindos da tabela USUARIOS para esta linha
                        $whatsappNumberService = escapeHTML($request['whatsapp_service_number'] ?? 'N/A'); // Número de serviço solicitado/aprovado
                        $requestTimeWA = formatDateFullPHP($request['request_time']); // Data/Hora da solicitação
                        $userNameWA = escapeHTML($request['user_nome'] ?? 'Desconhecido'); // Nome do usuário
                        $userNameWALower = strtolower($userNameWA); // Nome em minúsculas para a busca (data attribute)
                        $userIdWA = (int)$request['user_id']; // ID do usuário
                        $currentStatusWA = $request['current_status'] ?? 'unknown'; // Status atual (pending, approved, etc.)
                        $whatsappLinkPersonalWA = formatWhatsappLink($request['user_personal_phone']); // Link para o WhatsApp pessoal do usuário
                        $userAvatarWA = escapeHTML($request['user_avatar'] ?? DEFAULT_AVATAR); // Avatar do usuário

                        // Gera o HTML do dropdown de status para esta linha
                        // Passa o USER ID como identificador, pois não temos mais event_id aqui
                        $dropdownHTML_WA = createStatusDropdownPHP($userIdWA, 'whatsapp_request', $currentStatusWA, $allowedStatusesOptionsPHP);

                        // Obtém informações (classe CSS, ícone, label) para o badge de status
                        $badgeInfo = getWhatsappStatusBadgeInfo($currentStatusWA, $statusLabelsPHP);
                    ?>
                    <tr data-user-id="<?php echo $userIdWA; ?>" data-user-name="<?php echo $userNameWALower; ?>"> 
                        <td>
                            <div class="user-info-cell">
                                <img src="/uploads/avatars/<?php echo $userAvatarWA; ?>?v=<?php echo time(); ?>"
                                     alt="Avatar de <?php echo $userNameWA; ?>"
                                     class="user-avatar-sm"
                                     onerror="this.onerror=null;this.src='/uploads/avatars/<?php echo DEFAULT_AVATAR; ?>';">
                                <!-- Nome do usuário (visível) -->
                                <span class="user-name"><?php echo $userNameWA; ?></span>
                            </div>
                        </td>
                        <td>
                            <!-- Número de serviço solicitado/aprovado -->
                            <strong><?php echo $whatsappNumberService; ?></strong>
                        </td>
                        <td class="status-cell">
                            <!-- Badge mostrando o status atual -->
                            <span class="status-badge-wa <?php echo escapeHTML($badgeInfo['class']); ?>" title="<?php echo escapeHTML($badgeInfo['label']); ?>">
                                <i class="fas <?php echo escapeHTML($badgeInfo['icon']); ?>"></i>
                                <?php echo escapeHTML($badgeInfo['label']); ?>
                            </span>
                            <!-- Dropdown para mudar o status -->
                            <?php echo $dropdownHTML_WA; ?>
                        </td>
                        <td title="<?php echo escapeHTML($request['request_time']); ?>">
                            <!-- Data/Hora da solicitação formatada -->
                            <?php echo $requestTimeWA ?: 'N/A'; ?>
                        </td>
                        <td>
                            <!-- Botões de Ação para esta linha -->
                            <div class="action-buttons" style="justify-content: flex-end;">
                                <!-- Botão para abrir o WhatsApp pessoal do usuário -->
                                <a href="<?php echo $whatsappLinkPersonalWA; ?>"
                                   class="btn btn-sm btn-whatsapp"
                                   target="_blank"
                                   title="Abrir WhatsApp Pessoal <?php echo escapeHTML(formatDisplayPhone($request['user_personal_phone'] ?? '')); ?>"
                                   <?php if($whatsappLinkPersonalWA === '#') echo 'style="opacity:0.5; cursor: not-allowed;"'; ?>>
                                    <i class="fab fa-whatsapp"></i> Pessoal
                                </a>
                                <!-- Botão Aprovar (usa user_id como identificador) -->
                                <button class="btn btn-sm btn-success"
                                        data-action="approve-whatsapp"
                                        data-user-id="<?php echo $userIdWA; ?>"
                                        data-current-number="<?php echo $whatsappNumberService; ?>"
                                        title="Aprovar Número de Serviço"
                                        <?php if($currentStatusWA == 'approved') echo 'disabled'; ?>>
                                    <i class="fas fa-check"></i> Aprovar
                                </button>
                                <!-- Botão Rejeitar (usa user_id como identificador) -->
                                <button class="btn btn-sm btn-danger"
                                        data-action="reject-whatsapp"
                                        data-user-id="<?php echo $userIdWA; ?>"
                                        data-user-name="<?php echo $userNameWA; ?>" {/* Adicionado nome para confirmação */}
                                        title="Rejeitar Solicitação"
                                        <?php if($currentStatusWA == 'rejected') echo 'disabled'; ?>>
                                    <i class="fas fa-times"></i> Rejeitar
                                </button>
                                <!-- Botão Ver Detalhes (leva ao modal de detalhes do usuário) -->
                                <button class="btn btn-sm btn-details"
                                        data-action="details"
                                        data-user-id="<?php echo $userIdWA; ?>"
                                        title="Ver Detalhes do Usuário">
                                    <i class="fas fa-eye"></i> Detalhes
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
         </table>
     </div>
</div> <!-- Fim #whatsapp_pending-section -->
        
        
          <!-- ========== INÍCIO: NOVA SEÇÃO RANK ========== -->
        <div id="rank-section" class="content-section">
             <h2 class="section-title"><i class="fas fa-trophy"></i> Rank de Usuários</h2>
             <p class="section-description">Classificação baseada no total de ganhos (requer API para buscar e editar dados).</p>
             <div class="table-container">
                 <table class="admin-table rank-table" id="rankTable">
                     <thead>
                         <tr>
                             <th style="width: 60px; text-align: center;">#</th>
                             <th>Usuário</th>
                             <th style="text-align: right;">Total Ganhos (R$)</th>
                             <th style="width: 100px; text-align: center;">Ações</th>
                         </tr>
                     </thead>
                     <tbody>
                         <tr id="rankLoadingMsg"><td colspan="4" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Carregando ranking...</td></tr>
                         <!-- Rank data will be loaded via JS -->
                     </tbody>
                 </table>
             </div>
        </div>
        <!-- ========== FIM: NOVA SEÇÃO RANK ========== -->

        <!-- ========== INÍCIO: NOVA SEÇÃO GERENCIAR SALDOS ========== -->
        <div id="gerenciamento_saldos-section" class="content-section">
            <h2 class="section-title"><i class="fas fa-wallet"></i> Gerenciamento de Saldos</h2>
            <p class="section-description">Visualize saldos diários, marque como pagos e navegue entre as datas.</p>

            <!-- Controles de Navegação de Data -->
            <div class="balance-date-navigation" style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1.5rem; padding: 0.75rem; background: var(--gray); border-radius: var(--border-radius-md);">
                <button id="balancePrevDayBtn" class="btn btn-secondary btn-sm" title="Dia Anterior"><i class="fas fa-chevron-left"></i></button>
                <input type="text" id="balanceDateDisplay" class="form-control flatpickr-input" placeholder="Selecione data..." style="max-width: 180px; text-align: center; font-weight: 600; background-color: var(--gray-dark);">
                <button id="balanceNextDayBtn" class="btn btn-secondary btn-sm" title="Próximo Dia" disabled><i class="fas fa-chevron-right"></i></button>
                <button id="balanceGoToYesterdayBtn" class="btn btn-info btn-sm" title="Ir para Ontem"><i class="fas fa-calendar-day"></i> Ontem</button>
                <span id="balanceDateLoading" style="display: none; margin-left: 10px;"><i class="fas fa-spinner fa-spin"></i> Carregando...</span>
            </div>

            <!-- Resumo Total a Pagar (Opcional) -->
            <div id="balanceTotalSummary" style="text-align: center; margin-bottom: 1rem; font-size: 1.1rem; display: none;">
               Total a Pagar (<span id="summaryDate"></span>): <strong style="color: var(--success);" id="summaryTotalValue">R$ 0,00</strong>
            </div>

            <div class="table-container">
                <table class="admin-table balance-table" id="balanceManagerTable">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Saldo do Dia (<span class="balance-table-date-header"></span>)</th>
                            <th>Total Comprovantes</th> <!-- Adicionei esta coluna se sua API/DB retornar -->
                            <th>Chave PIX</th>
                            <th style="text-align: center;">Pago?</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="balanceManagerTableBody">
                        <!-- Linhas carregadas via JS -->
                        <tr><td colspan="6" style="text-align: center; padding: 40px;"><i class="fas fa-calendar-alt"></i> Selecione uma data acima.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- ========== FIM: NOVA SEÇÃO GERENCIAR SALDOS ========== -->
        
        

        </main>
    </div>

    <!-- ==================== MODAIS ==================== -->

    <!-- Modal Editar Usuário -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"> <i class="fas fa-user-edit" style="margin-right: 10px;"></i> Editar Usuário</h3>
                <button type="button" class="close-modal" data-modal-id="editModal" aria-label="Fechar">×</button>
            </div>
            <form id="editForm">
                <div class="modal-body">
                    <input type="hidden" id="editUserId" name="id">

                    <div class="form-group">
                        <label class="form-label" for="editUserName">Nome (Nickname):</label>
                        <input type="text" class="form-control" id="editUserName" name="nome" required placeholder="Nome de usuário visível">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="editUserEmail">Email:</label>
                        <input type="email" class="form-control" id="editUserEmail" name="email" required placeholder="email@exemplo.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="editUserType">Tipo de Conta:</label>
                        <select class="form-control" id="editUserType" name="is_admin">
                            <option value="0">Usuário Padrão</option>
                            <option value="1">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group">
        <label class="form-label" for="editUserApprovedStatus">Status de Aprovação:</label>
         <select class="form-control" id="editUserApprovedStatus" name="is_approved">
             <option value="1">Aprovado</option>
             <option value="0">Pendente Aprovação</option>
         </select>
    </div>

                    <div class="form-group">
                        <label class="form-label" for="editUserStatus">Status da Conta:</label>
                        <select class="form-control" id="editUserStatus" name="is_active">
                            <option value="1">Ativo (Pode fazer login)</option>
                            <option value="0">Inativo (Login bloqueado)</option>
                        </select>
                    </div>
                    <!-- ========== INÍCIO: NOVOS CAMPOS EDIT MODAL ========== -->
                    <div class="form-group">
                       <label class="form-label" for="editUserTelefone">Telefone Pessoal:</label>
                       <input type="tel" class="form-control" id="editUserTelefone" name="telefone" placeholder="+55 (XX) XXXXX-XXXX">
                   </div>
                   <div class="form-group">
                       <label class="form-label" for="editUserPixType">Tipo Chave PIX:</label>
                       <select class="form-control" id="editUserPixType" name="tipo_chave_pix">
                           <option value="">-- Não Definido --</option>
                           <option value="cpf">CPF</option>
                           <option value="cnpj">CNPJ</option>
                           <option value="email">Email</option>
                           <option value="telefone">Telefone</option>
                           <option value="aleatoria">Aleatória</option>
                       </select>
                   </div>
                   <div class="form-group">
                       <label class="form-label" for="editUserPixKey">Chave PIX:</label>
                       <input type="text" class="form-control" id="editUserPixKey" name="chave_pix" placeholder="Digite a chave PIX do usuário">
                   </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-id="editModal"><i class="fas fa-times"></i> Cancelar</button>
                    <button type="submit" class="btn btn-edit"><i class="fas fa-save"></i> Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalhes do Usuário -->
    <div class="modal user-detail-modal" id="userDetailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-id-card" style="margin-right: 10px;"></i> Detalhes do Usuário</h3>
                <button type="button" class="close-modal" data-modal-id="userDetailModal" aria-label="Fechar">×</button>
            </div>
            <div id="userDetailContent" class="modal-body">
                 <!-- Conteúdo carregado via JS -->
                <p style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                    Carregando informações...
                </p>
            </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-secondary" data-modal-id="userDetailModal"><i class="fas fa-times"></i> Fechar</button>
                 <!-- Poderia adicionar um botão de editar que abre o outro modal -->
                 <!-- <button type="button" class="btn btn-edit" id="editFromDetailsBtn"><i class="fas fa-edit"></i> Editar Usuário</button> -->
            </div>
        </div>
    </div>

    <!-- Modal Enviar Notificação -->
    <div class="modal" id="notifyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-paper-plane" style="margin-right: 10px;"></i> Enviar Notificação</h3>
                <button type="button" class="close-modal" data-modal-id="notifyModal" aria-label="Fechar">×</button>
            </div>
            <form id="notifyForm">
                <div class="modal-body">
                    <input type="hidden" id="notifyUserId" name="user_id">
                    <input type="hidden" id="notifyEventId" name="event_id">
                    <!-- *NOVO* Input escondido para guardar o nome do usuário para os placeholders -->
                    <input type="hidden" id="notifyUserNameHidden">

                    <div class="form-group">
                        <label class="form-label" for="notifyUserNameDisplay">Para:</label>
                        <!-- Campo apenas para mostrar o nome, não será enviado -->
                        <input type="text" class="form-control" id="notifyUserNameDisplay" readonly style="background-color: var(--gray); cursor: not-allowed;">
                    </div>

                    <!-- *NOVO* Seleção de Tipo: Personalizado ou Pré-definido -->
                    <div class="form-group">
                        <label class="form-label" for="notifyTypeSelect">Tipo de Notificação:</label>
                        <select class="form-control" id="notifyTypeSelect">
                            <option value="custom" selected>Personalizada (Digitar)</option>
                            <option value="predefined">Usar Modelo Pré-definido</option>
                        </select>
                    </div>

                    <!-- *NOVO* Seleção do Modelo (só aparece se escolher 'Pré-definido') -->
                    <div class="form-group" id="notifyTemplateGroup" style="display: none;"> <!-- Começa escondido -->
                        <label class="form-label" for="notifyTemplateSelect">Escolha o Modelo:</label>
                        <select class="form-control" id="notifyTemplateSelect">
                            <option value="">-- Selecione um modelo --</option>
                            <!-- As opções dos modelos serão adicionadas aqui pelo JavaScript -->
                        </select>
                    </div>

                    <!-- Campos de Título e Mensagem (agora preenchidos pelos modelos também) -->
                    <div class="form-group">
                        <label class="form-label" for="notifyTitle">Título da Notificação:</label>
                        <input type="text" class="form-control" id="notifyTitle" name="title" required placeholder="Ex: Atualização Importante">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="notifyMessage">Mensagem:</label>
                        <textarea class="form-control" id="notifyMessage" name="message" required rows="5" placeholder="Digite a mensagem para o usuário..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-id="notifyModal"><i class="fas fa-times"></i> Cancelar</button>
                    <button type="submit" class="btn btn-notify"><i class="fas fa-paper-plane"></i> Enviar Notificação</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmar Desativação -->
    <div class="modal" id="deleteModal">
        <div class="modal-content"> <!-- Estilos específicos aplicados via CSS -->
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i> Confirmar Desativação</h3>
                <button type="button" class="close-modal" data-modal-id="deleteModal" aria-label="Fechar">×</button>
            </div>
            <div class="modal-body">
                 <p><i class="fas fa-user-slash fa-2x" style="margin-bottom: 15px;"></i></p>
                <p>Tem certeza que deseja <strong style="text-transform: uppercase;">DESATIVAR</strong> o usuário <strong id="deleteUserName">[Nome]</strong>?</p>
                <p>O usuário <strong>NÃO</strong> poderá mais acessar o sistema enquanto estiver inativo.</p>
                <input type="hidden" id="deleteUserId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-edit" data-modal-id="deleteModal"><i class="fas fa-times"></i> Cancelar</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-delete"><i class="fas fa-check"></i> Confirmar Desativação</button>
            </div>
        </div>
    </div>
    
     <div class="modal" id="confirmationModal">
        <div class="modal-content" style="max-width: 500px;"> <!-- Largura menor para confirmação -->
            <div class="modal-header">
                <h3 class="modal-title" id="confirmationModalTitle"><i class="fas fa-exclamation-triangle"></i> Confirmar Ação</h3>
                <button type="button" class="close-modal" data-modal-id="confirmationModal" aria-label="Fechar">×</button>
            </div>
            <div class="modal-body" id="confirmationModalBody" style="text-align: center;">
                <p>Tem certeza que deseja continuar?</p>
                <!-- Mensagem será definida pelo JS -->
            </div>
            <div class="modal-footer" style="justify-content: center;"> <!-- Botões centralizados -->
                <input type="hidden" id="confirmationUserId"> <!-- Guarda ID do usuário se necessário -->
                <input type="hidden" id="confirmationAction"> <!-- Guarda a ação a ser confirmada -->
                <button type="button" class="btn btn-secondary close-modal" data-modal-id="confirmationModal"><i class="fas fa-times"></i> Cancelar</button>
                <button type="button" id="confirmActionButton" class="btn btn-danger"><i class="fas fa-check"></i> Confirmar</button> <!-- Classe e texto serão definidos pelo JS -->
            </div>
        </div>
    </div>

    <!-- Container para Toasts -->
    <div id="toast-container"></div>
    
     <!-- ========== INÍCIO: SCRIPTS FLATICKR ========== -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/pt.js"></script>
    <!-- ========== FIM: SCRIPTS FLATICKR ========== -->


      <script>
    //<![CDATA[
    document.addEventListener('DOMContentLoaded', function() {
        // --- Seletores e Variáveis Globais ---
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');
        const menuItems = document.querySelectorAll('.sidebar-menu .menu-item[data-section]');
        const contentSections = document.querySelectorAll('.main-content .content-section');
        const mainContentTitle = document.getElementById('main-content-title');
        const usersTableBody = document.querySelector('#usersTable tbody'); // <<< ESTA É A LINHA IMPORTANTE
        const realtimeFeedContainer = document.getElementById('realtime-feed');
        const feedLoadingIndicator = document.getElementById('feed-loading');
        const noFeedItemsMessage = document.getElementById('no-feed-items');
         const pendingWACountBadge = document.getElementById('pending-wa-count-badge'); // *NOVO*
        const pendingWATableBody = document.querySelector('#pending-whatsapp-table tbody'); // *NOVO*
        const whatsappSearchInput = document.getElementById('whatsappSearchInput'); // *** NOVO Seletor ***
        const adminUserId = <?php echo json_encode($adminUserId); ?>;
         const feedPaginationControls = document.getElementById('feed-pagination'); // *NOVO*
        const feedPrevPageBtn = document.getElementById('feed-prev-page');       // *NOVO*
        const feedNextPageBtn = document.getElementById('feed-next-page');       // *NOVO*
        const feedCurrentPageSpan = document.getElementById('feed-current-page'); // *NOVO*
        const feedTotalPagesSpan = document.getElementById('feed-total-pages');   // *NOVO*
        const notifyModal = document.getElementById('notifyModal');
        const notifyTypeSelect = document.getElementById('notifyTypeSelect');
        const notifyTemplateGroup = document.getElementById('notifyTemplateGroup');
        const notifyTemplateSelect = document.getElementById('notifyTemplateSelect');
        const notifyTitleInput = document.getElementById('notifyTitle');
        const notifyMessageInput = document.getElementById('notifyMessage');
        const notifyUserNameDisplay = document.getElementById('notifyUserNameDisplay');
        const notifyUserNameHidden = document.getElementById('notifyUserNameHidden');
        
        
        
         const rankTableBody = document.querySelector('#rankTable tbody');
        const rankLoadingMsg = document.getElementById('rankLoadingMsg');
        const balanceManagerTableBody = document.getElementById('balanceManagerTableBody');
        const balanceDateDisplayInput = document.getElementById('balanceDateDisplay');
        const balancePrevDayBtn = document.getElementById('balancePrevDayBtn');
        const balanceNextDayBtn = document.getElementById('balanceNextDayBtn');
        const balanceGoToYesterdayBtn = document.getElementById('balanceGoToYesterdayBtn');
        const balanceDateLoadingSpan = document.getElementById('balanceDateLoading');
        const balanceTableDateHeader = document.querySelector('.balance-table-date-header'); // Span no header da tabela
        const balanceTotalSummaryDiv = document.getElementById('balanceTotalSummary'); // Div do resumo
        const summaryDateSpan = document.getElementById('summaryDate');         // Span para data no resumo
        const summaryTotalValueSpan = document.getElementById('summaryTotalValue'); // Span para valor no resumo
        let flatpickrInstance = null; // Variável para guardar a instância do Flatpickr
        let currentBalanceDate = ''; // Guarda a data selecionada (YYYY-MM-DD)
        
        
        
        

        let lastEventId = 0; // Último ID de evento carregado no feed
        let eventSource = null; // Objeto EventSource para SSE
        let isSSEConnected = false; // Flag de conexão SSE
        let reconnectAttempts = 0; // Tentativas de reconexão SSE
        const MAX_RECONNECT_ATTEMPTS = 5;
        const RECONNECT_DELAY_BASE = 3000; // ms
         const ITEMS_PER_PAGE = 20; // *NOVO* Quantos itens por página
        let currentPage = 1;       // *NOVO* Página atual
        let allFeedItems = [];     // *NOVO* Array para guardar TODOS os itens do feed carregados
        let filteredFeedItems = [];// *NOVO* Array para itens filtrados (se houver filtro futuro)

        // Passa dados PHP para JS de forma segura
        const statusLabels = <?php echo json_encode($statusLabelsPHP, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE); ?>; // *MODIFICADO* (JSON Flags)
        const allowedStatusesOptions = <?php echo json_encode($allowedStatusesOptionsPHP, JSON_INVALID_UTF8_IGNORE); ?>; // *MODIFICADO* (JSON Flags)
         const predefinedNotifications = <?php echo json_encode($predefinedNotifications, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE); ?>;
        let notificationSound = null; // *NOVO*
        const defaultAvatar = '<?php echo DEFAULT_AVATAR; ?>'; // Nome do avatar padrão

        // --- Funções Utilitárias JS ---
        function escapeHTML(str) {
            if (typeof str !== 'string') str = String(str ?? '');
            const p = document.createElement("p");
            p.textContent = str;
            return p.innerHTML;
        }

        function parseJsonSafe(jsonString) {
            if (jsonString === null || jsonString === '') return null;
            try {
                const data = JSON.parse(jsonString);
                return (typeof data === 'object' && data !== null) ? data : null;
            } catch (e) {
                console.warn("JSON Parse Error:", e.message, "| Data:", jsonString.substring(0, 100));
                return null;
            }
        }
        
        
        function formatCurrency(value) {
        try {
            return (parseFloat(value || 0)).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        } catch (e) {
            console.error("Currency format error:", e);
            return 'R$ Erro';
        }
    }

    function formatDateForHeader(dateStr) { // Formato DD/MM/YYYY
         try {
             if (!dateStr || !dateStr.includes('-')) return dateStr; // Retorna original se inválido
             const [year, month, day] = dateStr.split('-');
             return `${day}/${month}/${year}`;
         } catch {
             return dateStr; // Retorna original em caso de erro
         }
     }
        

        function formatDateFull(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return 'Data Inválida';

                const now = new Date();
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);

                const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());

                const timeFmt = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

                if (dateOnly.getTime() === today.getTime()) {
                    return `Hoje às ${timeFmt}`;
                } else if (dateOnly.getTime() === yesterday.getTime()) {
                    return `Ontem às ${timeFmt}`;
                } else {
                    const dateFmtOpts = { day: '2-digit', month: '2-digit' };
                    if (date.getFullYear() !== now.getFullYear()) {
                        dateFmtOpts.year = '2-digit';
                    }
                    const dateFmt = date.toLocaleDateString('pt-BR', dateFmtOpts);
                    return `${dateFmt} ${timeFmt}`;
                }
            } catch(e) {
                console.error("JS Date Format Error:", e, "| Input:", dateString);
                return 'Inválido';
            }
        }

        function formatBytes(bytes, decimals = 1) {
            bytes = parseFloat(bytes);
             if (isNaN(bytes) || bytes <= 0) return '0 Bytes';
            const k = 1024;
            const dm = Math.max(0, decimals);
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
            const i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
        }

         // Mapeamento direto das funções PHP para JS
        function getIconClass(eventType) {
             switch (eventType) {
                case 'call_request': return 'fa-video';
                case 'proof_upload': return 'fa-receipt';
                case 'whatsapp_request': return 'fa-brands fa-whatsapp';
                case 'model_selected': return 'fa-robot';
                case 'profile_update': return 'fa-user-edit';
                case 'user_registered': return 'fa-user-plus';
                case 'user_login': return 'fa-sign-in-alt';
                case 'lead_purchase': return 'fa-shopping-cart';
                case 'admin_notification': return 'fa-bell';
                case 'pix_payment_reported': return 'fa-money-check-alt'; // Ou fa-qrcode
                default: return 'fa-info-circle';
             }
         }
         function getIconColorVariable(eventType) {
             switch (eventType) {
                case 'call_request': return 'var(--info)';
                case 'proof_upload': return 'var(--success)';
                case 'whatsapp_request': return 'var(--whatsapp-green)';
                case 'model_selected': return 'var(--secondary)';
                case 'profile_update': return 'var(--warning)';
                case 'user_registered': return 'var(--primary)';
                case 'user_login': return 'var(--success)';
                case 'lead_purchase': return 'var(--info)';
                case 'admin_notification': return 'var(--primary)';
                case 'pix_payment_reported': return 'fa-money-check-alt'; // Ou fa-qrcode
                 default: return 'var(--text-secondary)';
             }
         }
         
          // *NOVO* Funções JS para formatar links e telefones (necessárias para WA Pendente)
 function formatWhatsappLinkJS(phone) {
    if (!phone) return '#';
    let digits = String(phone).replace(/\D/g, '');
    if (digits.length >= 10) {
        if ((digits.length === 10 || digits.length === 11) && !digits.startsWith('55')) {
            digits = '55' + digits;
        }
        if (!digits.startsWith('+')) {
             digits = '+' + digits;
         }
        return `https://wa.me/${digits}`;
    }
    return '#';
}
function formatDisplayPhoneJS(phone) {
    if (!phone) return 'N/A';
    const digits = String(phone).replace(/\D/g, '');
    const len = digits.length;
    try {
        if (len === 10) return `(${digits.substring(0, 2)}) ${digits.substring(2, 6)}-${digits.substring(6, 10)}`;
        if (len === 11) return `(${digits.substring(0, 2)}) ${digits.substring(2, 7)}-${digits.substring(7, 11)}`;
        if (len === 12 && digits.startsWith('55')) return `+55 (${digits.substring(2, 4)}) ${digits.substring(4, 8)}-${digits.substring(8, 12)}`;
        if (len === 13 && digits.startsWith('55')) return `+55 (${digits.substring(2, 4)}) ${digits.substring(4, 9)}-${digits.substring(9, 13)}`;
    } catch(e) {}
    return phone; // Retorna original se não formatar
}
function playNotificationSound() {
    if (!notificationSound) return;
    try {
        notificationSound.currentTime = 0;
        notificationSound.play().catch(e => console.warn("Sound playback failed:", e.message));
    } catch (e) {
        console.error("Error playing sound:", e);
    }
}
         
         


        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if(!container) {
                 console.error("Toast container not found!");
                 return;
            }

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;

            let iconClass = 'fa-info-circle';
            if(type === 'success') iconClass = 'fa-check-circle';
            if(type === 'warning') iconClass = 'fa-exclamation-triangle';
            if(type === 'error') iconClass = 'fa-times-circle'; // Ícone de erro melhor

            toast.innerHTML = `<i class="fas ${iconClass}"></i> <span>${escapeHTML(message)}</span>`; // Span para melhor controle
            container.prepend(toast); // Adiciona no início

            // Força reflow para animação
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                     toast.classList.add('show');
                });
            });

            // Auto-remove
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => {
                    // Garante que remove apenas se ainda existir
                    if(toast.parentNode === container) {
                         toast.remove();
                    }
                 }, {once: true});
                 // Fallback para remover caso transitionend não dispare
                 setTimeout(() => {
                     if(toast.parentNode === container) {
                         toast.remove();
                     }
                 }, 500); // Tempo da animação
            }, 4000); // Tempo visível
        }

         function initializeDashboard() {
            console.log("Initializing Admin Dashboard (Filtered Feed + Pagination)...");
            try { notificationSound = new Audio(notificationSoundPath); notificationSound.preload='auto';} catch(e){}

            // *NOVO* Carrega todos os itens iniciais no array global
             allFeedItems = <?php echo json_encode($initial_feed_items ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE); ?>;
             console.log(`Loaded ${allFeedItems.length} initial feed items.`);

            setupNavigation();
            setupModalClosers();
            setupFormSubmissions();
            setupActionDelegation(); // Configura listeners de clique E change
            setupPaginationListeners(); // *NOVO* Configura botões de paginação
            updatePendingWACount(); // Atualiza contagem inicial WA
            setupNotificationTemplateListeners();
    updateFilterButtonCounts(); // <-- ADICIONAR CHAMADA AQUI
    setupWhatsappSearch(); // *** NOVO: Configura busca da tabela WHATSAPP ***
    
            
            setupUserSearch();
            setupWhatsappFilters();

            // *MODIFICADO* Renderiza a primeira página do feed
             renderFeedPage(1);

            calculateInitialLastEventId(); // Calcula após renderizar a primeira página
            const initialSectionId = document.querySelector('.sidebar-menu .menu-item.active')?.dataset.section || 'dashboard';
            activateSection(initialSectionId); // Ativa a seção correta
            console.log("Admin Dashboard Initialized (Filtered Feed + Pagination).");
        }

        function calculateInitialLastEventId() {
            if(!realtimeFeedContainer) return;
            const items = realtimeFeedContainer.querySelectorAll('.feed-item[data-event-id]');
            if (items.length > 0) {
                lastEventId = Math.max(0, ...Array.from(items).map(el => parseInt(el.dataset.eventId, 10) || 0));
            } else {
                lastEventId = 0;
            }
            console.log("Initial Last Event ID set to:", lastEventId);
        }

        // --- Navegação ---
        function setupNavigation() {
            menuItems.forEach(item => {
                 // Evita adicionar múltiplos listeners
                if(item.dataset.navListenerAttached) return;
                item.dataset.navListenerAttached = 'true';

                item.addEventListener('click', function(e) {
                    const sectionId = this.dataset.section;
                    if (!sectionId) return; // Ignora se não for um item de seção
                    if(this.getAttribute('target') === '_blank') return; // Ignora links que abrem nova aba

                    e.preventDefault();
                    activateSection(sectionId);

                    // Fecha sidebar no mobile
                    if (window.innerWidth <= 992 && sidebar?.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        sidebarOverlay?.classList.remove('active');
                    }
                });
            });

            if (menuToggle) {
                 if(!menuToggle.dataset.navListenerAttached) {
                     menuToggle.dataset.navListenerAttached = 'true';
                    menuToggle.addEventListener('click', () => {
                        sidebar?.classList.add('active');
                        sidebarOverlay?.classList.add('active');
                    });
                 }
            }

            if (sidebarOverlay) {
                 if(!sidebarOverlay.dataset.navListenerAttached) {
                     sidebarOverlay.dataset.navListenerAttached = 'true';
                    sidebarOverlay.addEventListener('click', () => {
                        sidebar?.classList.remove('active');
                        sidebarOverlay?.classList.remove('active');
                    });
                 }
            }
        }

       function activateSection(sectionId) {
        console.log(`Activating section: ${sectionId}`);
        let sectionActivated = false;

        contentSections.forEach(section => {
            const isActive = section.id === `${sectionId}-section`;
            section.classList.toggle('active', isActive);
            if(isActive) sectionActivated = true;
        });

        if (!sectionActivated) {
            console.warn(`Section "${sectionId}" not found, activating 'dashboard'.`);
            sectionId = 'dashboard';
            document.getElementById('dashboard-section')?.classList.add('active');
        }

        menuItems.forEach(item => {
            item.classList.toggle('active', item.dataset.section === sectionId);
        });

        if(mainContentTitle) {
            let title = 'Admin Dashboard';
            const activeMenuItem = document.querySelector(`.menu-item[data-section="${sectionId}"] span`);
            if(activeMenuItem) { title = activeMenuItem.textContent.trim(); }
            mainContentTitle.textContent = title;
        }

        // Controle do SSE (Feed)
        if(sectionId === 'dashboard') { startRealtimeFeed(); }
        else { stopRealtimeFeed(); }

        // ========== INÍCIO: CHAMADAS PARA NOVAS SEÇÕES ==========
        if (sectionId === 'rank') {
            loadRankData(); // Carrega dados do rank ao ativar a seção
        }
        if (sectionId === 'gerenciamento_saldos') {
             // Configura o gerenciador (incluindo Flatpickr) apenas uma vez
             // ou recarrega os dados se já configurado
             setupBalanceManager(); // A função setup agora também carrega os dados iniciais
        }
        // ========== FIM: CHAMADAS PARA NOVAS SEÇÕES ==========

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

        // --- Modais ---
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if(modal) {
                 // Garante que está escondido antes de mostrar para a animação funcionar
                 modal.style.display = 'flex'; // Usar flex para centralizar
                 modal.style.opacity = '0';
                 if(modal.querySelector('.modal-content')) {
                      modal.querySelector('.modal-content').style.transform = 'translateY(20px) scale(0.98)';
                      modal.querySelector('.modal-content').style.opacity = '0';
                 }

                 // Força reflow
                void modal.offsetWidth;

                requestAnimationFrame(() => {
                     modal.classList.add('show');
                     modal.style.opacity = '1';
                     if(modal.querySelector('.modal-content')) {
                         modal.querySelector('.modal-content').style.transform = 'translateY(0) scale(1)';
                         modal.querySelector('.modal-content').style.opacity = '1';
                     }
                     document.body.style.overflow = 'hidden'; // Impede scroll do body
                    console.log(`Opened modal: ${modalId}`);
                 });

            } else {
                console.error(`Modal not found: ${modalId}`);
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if(modal?.classList.contains('show')) {
                modal.classList.remove('show');
                modal.style.opacity = '0';
                 if(modal.querySelector('.modal-content')) {
                     modal.querySelector('.modal-content').style.transform = 'translateY(20px) scale(0.98)';
                     modal.querySelector('.modal-content').style.opacity = '0';
                 }

                const transitionDuration = 400; // ms - Deve corresponder ao CSS

                // Listener para remover display:none APÓS a transição
                const onTransitionEnd = () => {
                    if(!modal.classList.contains('show')) { // Verifica se ainda está fechando
                        modal.style.display = 'none';
                        // Só restaura scroll se NENHUM outro modal estiver aberto
                        if(!document.querySelector('.modal.show')) {
                            document.body.style.overflow = 'auto';
                        }
                        console.log(`Closed modal: ${modalId}`);
                    }
                     modal.removeEventListener('transitionend', onTransitionEnd);
                };
                modal.addEventListener('transitionend', onTransitionEnd, { once: true });

                // Fallback caso transitionend não dispare
                 setTimeout(() => {
                    if(!modal.classList.contains('show')) {
                         modal.style.display = 'none';
                         if(!document.querySelector('.modal.show')) {
                             document.body.style.overflow = 'auto';
                         }
                    }
                 }, transitionDuration + 50); // Um pouco mais que a duração

            }
        }

        function setupModalClosers() {
            document.querySelectorAll('.close-modal, button[data-modal-id]').forEach(button => {
                 if(button.dataset.closerListenerAttached) return;
                 button.dataset.closerListenerAttached = 'true';
                button.addEventListener('click', function() {
                    const modalId = this.dataset.modalId || this.closest('.modal')?.id;
                    if(modalId) closeModal(modalId);
                });
            });

            // Fechar ao clicar no overlay
            document.querySelectorAll('.modal').forEach(modal => {
                 if(modal.dataset.overlayListenerAttached) return;
                 modal.dataset.overlayListenerAttached = 'true';
                modal.addEventListener('click', function(e) {
                    if (e.target === this) { // Clicou diretamente no fundo (overlay)
                        closeModal(this.id);
                    }
                });
            });

            // Fechar com ESC
             if(!document.body.dataset.escListenerAttached) {
                 document.body.dataset.escListenerAttached = 'true';
                document.addEventListener('keydown', (e) => {
                    if (e.key === "Escape") {
                        const openModal = document.querySelector('.modal.show');
                        if (openModal) {
                            closeModal(openModal.id);
                        }
                    }
                });
             }
        }
        
        
         function openConfirmationModal(title, message, confirmText, confirmClass, userId, action, extraData = {}) {
        const modal = document.getElementById('confirmationModal');
        const modalTitle = modal?.querySelector('#confirmationModalTitle');
        const modalBody = modal?.querySelector('#confirmationModalBody');
        const confirmButton = modal?.querySelector('#confirmActionButton');
        const userIdInput = modal?.querySelector('#confirmationUserId');
        const actionInput = modal?.querySelector('#confirmationAction');

        if (!modal || !modalTitle || !modalBody || !confirmButton || !userIdInput || !actionInput) {
            console.error("Confirmation modal elements missing!");
            showToast("Erro interno ao abrir confirmação.", "error");
            return;
        }

        modalTitle.innerHTML = title; // Permite HTML no título (ícones)
        modalBody.innerHTML = `<p>${message}</p>`; // Permite HTML na mensagem (negrito)

        confirmButton.textContent = confirmText;
        confirmButton.className = 'btn'; // Reseta classes do botão
        confirmButton.classList.add(confirmClass); // Adiciona a classe específica (btn-danger, btn-success, etc.)

        userIdInput.value = userId ?? ''; // Define o ID do usuário (pode ser nulo)
        actionInput.value = action; // Define a ação a ser confirmada

        // Guarda dados extras no botão (ex: para marcar pago, precisamos do user_id, date, markAs)
         confirmButton.dataset.extraData = JSON.stringify(extraData);
         confirmButton.dataset.originalHtml = confirmButton.innerHTML; // Guarda o HTML original

        openModal('confirmationModal');
    }

    async function executeConfirmedAction(action, userId, buttonElement) {
        const buttonOriginalHtml = buttonElement.dataset.originalHtml || 'Confirmar';
        let extraData = {};
        try { extraData = JSON.parse(buttonElement.dataset.extraData || '{}'); } catch (e) { console.warn("Falha ao parsear extraData do botão de confirmação"); }

        console.log(`Executando ação confirmada: ${action}`, { userId, extraData });

        try {
            let endpoint, payload, successMsg, loadingMsg, httpMethod = 'POST';

            switch (action) {
                case 'deactivate':
                    endpoint = '/admin/api/update_user.php';
                    payload = { id: userId, is_active: 0 };
                    successMsg = 'Usuário desativado com sucesso!';
                    loadingMsg = 'Desativando...';
                    break;
                case 'activate':
                    endpoint = '/admin/api/update_user.php';
                    payload = { id: userId, is_active: 1 };
                    successMsg = 'Usuário reativado com sucesso!';
                    loadingMsg = 'Reativando...';
                    break;
                    case 'approve-user':
                    endpoint = '/admin/api/approve_user.php'; // <<< VERIFIQUE SE A API EXISTE NESTE CAMINHO!
                    payload = { user_id: userId }; // A API pode precisar só do ID, ou { user_id: userId, is_approved: 1 }
                    successMsg = 'Usuário aprovado com sucesso!';
                    loadingMsg = 'Aprovando...';
                    httpMethod = 'POST';
                    break;
                case 'reset-balance':
                    endpoint = '/admin/api/reset_user_balance.php'; // API para zerar saldo
                    payload = { user_id: userId }; // API espera user_id
                    successMsg = 'Saldo de hoje zerado com sucesso!';
                    loadingMsg = 'Zerando Saldo...';
                    break;
                case 'clear-feed-all': // Ação para limpar feed
                    endpoint = '/admin/api/clear_feed_events.php';
                    payload = {}; // Sem payload específico
                    successMsg = 'Todos os eventos visíveis do feed foram removidos.';
                    loadingMsg = 'Limpando Feed...';
                    userId = null; // Não é específico de usuário
                    break;
                case 'mark-paid': // Ação para marcar/desmarcar saldo como pago
                    endpoint = '/admin/api/mark_balance_paid.php'; // API para marcar pago
                    payload = {
                        user_id: extraData.userId,
                        date: extraData.date,
                        paid_status: extraData.markAs // true para marcar pago, false para desmarcar
                    };
                    successMsg = `Saldo de ${escapeHTML(extraData.userName || 'Usuário')} (${formatDateForHeader(extraData.date)}) marcado como ${extraData.markAs ? 'PAGO' : 'NÃO PAGO'}!`;
                    loadingMsg = 'Marcando...';
                    userId = null; // Ação não é diretamente sobre o usuário ID do modal, mas sim sobre o registro de saldo
                    break;
                    case 'delete-permanently':
                         endpoint = '/admin/api/delete_user_permanently.php'; // <<< NOVA API QUE VOCÊ PRECISA CRIAR
                         payload = { user_id: userId }; // Envia o ID do usuário para a API
                         successMsg = `Usuário ${userId} e TODOS os seus dados foram EXCLUÍDOS PERMANENTEMENTE!`;
                         loadingMsg = 'EXCLUINDO TUDO...';
                         httpMethod = 'POST'; // Usar método DELETE é semanticamente correto
                         break;
                    
                default:
                    console.warn("Ação de confirmação desconhecida:", action);
                    closeModal('confirmationModal');
                    return;
            }

            // Chama a função AJAX genérica
            await submitAjax(endpoint, payload, buttonElement, loadingMsg, buttonOriginalHtml, successMsg, (responseData) => {
                closeModal('confirmationModal'); // Fecha o modal de confirmação

                // Callbacks específicos da ação
                if (action === 'deactivate' || action === 'activate' || action === 'approve-user') {
                   refreshUserTable(); // Atualiza a tabela de usuários
                }
                if (action === 'reset-balance') {
                    resetUserBalanceUIUpdate(userId); // Atualiza a UI do saldo zerado
                }
                if (action === 'clear-feed-all') {
                    if (realtimeFeedContainer) realtimeFeedContainer.innerHTML = ''; // Limpa o feed na tela
                    if (noFeedItemsMessage) noFeedItemsMessage.style.display = 'block'; // Mostra msg "sem itens"
                    allFeedItems = []; // Limpa o array de itens em memória
                    renderFeedPage(1); // Renderiza a página vazia (mostrará "sem itens")
                    lastEventId = 0; // Reseta o último ID para SSE
                }
                
                 if (action === 'mark-paid') {
                    // Atualiza a linha específica na tabela de saldos
                    updateBalanceTableRowStatus(extraData.userId, extraData.date, extraData.markAs);
                    calculateAndDisplayTotalToPay(); // Recalcula o total a pagar
                }

            }, httpMethod);

        } catch (error) {
            // submitAjax já mostra o erro, mas podemos fechar o modal aqui também
            closeModal('confirmationModal');
        }
    }
        
        

    // --- Listener Principal de Ações (Cliques e Changes) ---
        // --- Listener Principal de Ações (Cliques e Changes) ---
    function setupActionDelegation() {
        // Listener Principal de Cliques (Delegado no body)
        document.body.addEventListener('click', async (e) => {
            // --- Prioridade 1: Fechar Modais ---
            const closeModalButton = e.target.closest('.close-modal');
            if (closeModalButton) {
                e.preventDefault();
                e.stopPropagation(); // Impede que o clique vá para outros elementos (como a linha da tabela)
                const modalId = closeModalButton.dataset.modalId || closeModalButton.closest('.modal')?.id;
                if (modalId) closeModal(modalId);
                return; // Ação concluída (fechar modal)
            }
            const modalOverlay = e.target.matches('.modal.show') ? e.target : null;
            if (modalOverlay) {
                e.stopPropagation(); // Impede que o clique vá para outros elementos
                closeModal(modalOverlay.id);
                return; // Ação concluída (fechar modal)
            }

            // --- Prioridade 2: Botão de Confirmação Genérico ---
            const confirmActionButton = e.target.closest('#confirmActionButton');
            if (confirmActionButton && !confirmActionButton.disabled) {
                 console.log("[Debug] Botão Confirmar Ação (#confirmActionButton) CLICADO!");
                e.preventDefault();
                e.stopPropagation(); // Impede propagação

                const modal = confirmActionButton.closest('#confirmationModal');
                const userId = modal?.querySelector('#confirmationUserId')?.value;
                const action = modal?.querySelector('#confirmationAction')?.value;
                console.log(`[Debug] Confirmando Ação: Action=${action}, UserID=${userId || 'N/A'}`);

                // Validação Específica para Exclusão Permanente
                if (action === 'delete-permanently') {
                    console.log("[Debug] Validando nome para delete-permanently...");
                    const confirmInput = modal?.querySelector('#deleteConfirmInput');
                    const userRow = document.querySelector(`#usersTable tbody tr[data-user-id="${userId}"]`);
                    const expectedName = userRow?.dataset.userName;
                    const typedName = confirmInput?.value.trim();
                    console.log(`[Debug] Nome esperado (lowercase): "${expectedName}", Nome digitado: "${typedName}"`);
                    if (!userId || !expectedName || !confirmInput || typedName.toLowerCase() !== expectedName) {
                        const originalUserName = userRow?.querySelector('.user-name')?.textContent || 'NOME DESCONHECIDO';
                        console.warn(`[Debug] Confirmação Falhou: Nomes não batem. Esperado: "${expectedName}", Digitado: "${typedName}"`);
                        alert(`Nome de confirmação inválido! Digite "${escapeHTML(originalUserName)}" exatamente como mostrado (sem espaços extras, mas pode ser maiúscula/minúscula).`);
                        confirmInput.focus();
                        return; // Impede a exclusão
                    }
                    console.log("[Debug] Validação de nome OK.");
                }

                // Prossegue para executar a ação
                if (action) {
                    console.log("[Debug] Chamando executeConfirmedAction...");
                    await executeConfirmedAction(action, userId || null, confirmActionButton);
                    console.log("[Debug] executeConfirmedAction CONCLUÍDA.");
                } else {
                    console.warn("[Debug] Ação de confirmação não definida no modal.");
                    closeModal('confirmationModal');
                }
                return; // Ação concluída (botão de confirmação)

            } else if (confirmActionButton && confirmActionButton.disabled) {
                console.log("[Debug] Botão Confirmar Ação CLICADO, mas estava DESABILITADO.");
                return; // Ação ignorada
            }

            // --- *** Prioridade 3: Clique em Linha de Tabela *** ---
            // Verifica se o clique foi em alguma linha de tabela com data-user-id
            const clickedRow = e.target.closest('tbody tr[data-user-id]');
            if (clickedRow) {
                const tableId = clickedRow.closest('table')?.id; // Pega o ID da tabela pai
                let isInteractiveElementClick = false; // Flag para indicar clique em elemento interativo

                // Define os seletores de elementos interativos específicos para cada tabela
                if (tableId === 'pending-whatsapp-table') {
                    // Para a tabela de WA, botões, links, selects e o badge de status são interativos
                    isInteractiveElementClick = e.target.closest('button, a, select, .status-badge-wa, .feed-status-select');
                } else if (tableId === 'usersTable') {
                    // Para a tabela de usuários, botões, links e os badges de status/tipo são interativos
                    isInteractiveElementClick = e.target.closest('button, a, .badge');
                } else if (tableId === 'rankTable') {
                    // Para a tabela de rank, botões, links, o input de edição e o badge de medalha são interativos
                     isInteractiveElementClick = e.target.closest('button, a, input, .rank-badge-v2, .rank-edit-input');
                }
                // Adicione mais 'else if (tableId === ...)' aqui se tiver outras tabelas clicáveis

                // Se o clique foi na linha, mas NÃO em um dos elementos interativos definidos acima
                if (!isInteractiveElementClick) {
                    const userId = clickedRow.dataset.userId;
                    console.log(`Action: Table Row Clicked (Table: ${tableId}, non-interactive area) - UserID: ${userId}`);
                    if (userId) {
                        await openUserDetailModal(userId); // Abre o modal de detalhes
                        return; // Ação tratada, interrompe o processamento do clique aqui
                    } else {
                        console.warn(`Clique na linha da tabela ${tableId}, mas user-id não encontrado.`);
                    }
                } else {
                    // Se o clique foi em um elemento interativo, apenas registra e deixa continuar
                    console.log(`Clique em elemento interativo dentro da linha da tabela ${tableId}, deixando outros handlers gerenciarem.`);
                    // O fluxo continua para as verificações de Prioridade 4, 5, 6 etc.
                }
            }
             // --- *** FIM DA PRIORIDADE 3 *** ---


            // --- Prioridade 4: Clique no Avatar do Feed ---
             const feedAvatarContainer = e.target.closest('.feed-item-avatar');
             if (feedAvatarContainer) {
                 e.stopPropagation(); // Impedir que o clique vá para o card
                 const userId = feedAvatarContainer.dataset.userId;
                 console.log(`Action: Feed Avatar Clicked - UserID: ${userId}`);
                 if (userId && userId !== '0') {
                     await openUserDetailModal(userId);
                 } else { console.log("Avatar sem ID de usuário válido."); }
                 return; // Ação tratada
             }


            // --- Prioridade 5: Botões de Ação Principais (data-action) ---
            const actionButton = e.target.closest('button[data-action], a.btn[data-action]');
            if (actionButton && !actionButton.disabled) {
                const isNavigatingLink = (actionButton.tagName === 'A' && (actionButton.target === '_blank' || actionButton.href.startsWith('https://wa.me/')));
                if (!isNavigatingLink) {
                    e.preventDefault();
                }

                const { action, userId, userName, eventId } = actionButton.dataset;
                actionButton.dataset.originalHtml = actionButton.innerHTML;
                console.log(`Action Button Clicked: ${action}, UserID: ${userId}, EventID: ${eventId}, Name: ${userName}`);

                try {
                    // O switch case permanece o mesmo da versão anterior
                    switch(action) {
                        case 'details': if(userId) await openUserDetailModal(userId); break;
                        case 'edit': if(userId) await openEditModal(userId); break;
                        case 'notify': if(userId) openNotifyModal(userId, userName, eventId); break;
                        case 'deactivate':
                            if(userId) openConfirmationModal('<i class="fas fa-user-slash"></i> Desativar Usuário', `Tem certeza que deseja desativar <strong>${escapeHTML(userName || 'usuário')}</strong>?`, 'Confirmar Desativação', 'btn-danger', userId, action);
                            break;
                        case 'activate':
                            if(userId) openConfirmationModal('<i class="fas fa-user-check"></i> Reativar Usuário', `Tem certeza que deseja reativar <strong>${escapeHTML(userName || 'usuário')}</strong>?`, 'Confirmar Reativação', 'btn-success', userId, action);
                            break;
                        case 'reset-balance':
                             if(userId) openConfirmationModal('<i class="fas fa-undo"></i> Zerar Saldo Hoje', `Zerar saldo <strong>APENAS DE HOJE</strong> para <strong>${escapeHTML(userName||'usuário')}</strong>? <br><small>(Isso não afeta o saldo devedor total acumulado.)</small>`, 'Zerar Saldo de Hoje', 'btn-warning', userId, action);
                            break;
                        case 'edit-rank': if(userId) toggleRankEdit(userId, true); break;
                        case 'save-rank': if(userId) await saveRankEdit(userId, actionButton); break;
                        case 'cancel-rank': if(userId) toggleRankEdit(userId, false); break;
                        case 'delete-feed-item':
                            if(eventId) await clearFeedEvents(eventId, actionButton);
                            break;
                        case 'clear-feed-all':
                            openConfirmationModal('<i class="fas fa-trash"></i> Limpar Feed', 'Limpar todos os eventos visíveis do feed? Esta ação não pode ser desfeita.', 'Limpar Tudo', 'btn-danger', null, action);
                            break;
                        case 'approve-whatsapp':
                            if(eventId && userId) await handleApproveWhatsapp(actionButton);
                            else console.warn("Approve WA action missing eventId or userId");
                            break;
                        case 'reject-whatsapp':
                            if(eventId && userId) await handleRejectWhatsapp(actionButton);
                             else console.warn("Reject WA action missing eventId or userId");
                            break;
                            case 'approve-user':
                            if (userId && userName) {
                                openConfirmationModal(
                                    '<i class="fas fa-thumbs-up"></i> Aprovar Usuário', // Título
                                    `Tem certeza que deseja APROVAR o usuário <strong>${escapeHTML(userName)}</strong>? Ele poderá acessar o sistema.`, // Mensagem
                                    'Sim, Aprovar', // Texto do botão Confirmar
                                    'btn-success',  // Classe do botão Confirmar (verde)
                                    userId,         // ID do usuário
                                    action          // A ação ('approve-user')
                                );
                            }
                            break;
                        case 'delete-permanently':
                            if(userId) {
                                openConfirmationModal(
                                    '<i class="fas fa-skull-crossbones"></i><span style="color: var(--danger); font-weight: bold; margin-left: 10px;"> EXCLUIR PERMANENTEMENTE?!</span>',
                                    `<strong>ALERTA IRREVERSÍVEL!</strong><br>Excluir <strong>${escapeHTML(userName || 'usuário')} (ID: ${userId})</strong> removerá permanentemente a conta e TODOS os dados associados (comprovantes, saldos, eventos, etc.).<br><strong style='color: var(--warning);'>NÃO HÁ COMO DESFAZER.</strong><br><br>Digite o nome do usuário (<strong>${escapeHTML(userName || '???')}</strong>) para confirmar: <input type='text' id='deleteConfirmInput' class='form-control' style='margin-top: 10px; border-color: var(--danger);' placeholder='Digite o nome EXATO'>`,
                                    'EXCLUIR TUDO', 'btn-danger', userId, action
                                );
                                setTimeout(() => { // Adia a adição do listener para garantir que o modal esteja pronto
                                    const confirmInput = document.getElementById('deleteConfirmInput');
                                    const confirmBtn = document.getElementById('confirmActionButton');
                                    const userRow = document.querySelector(`#usersTable tbody tr[data-user-id="${userId}"]`);
                                    const expectedName = userRow?.dataset.userName;

                                    if (confirmInput && confirmBtn && expectedName) {
                                        confirmBtn.disabled = true;
                                        confirmInput.addEventListener('input', () => {
                                            confirmBtn.disabled = confirmInput.value.trim().toLowerCase() !== expectedName;
                                        });
                                    } else {
                                        console.error("Elementos não encontrados para habilitar confirmação de exclusão.");
                                        if(confirmBtn) confirmBtn.disabled = true;
                                    }
                                }, 200); // Pequeno delay para segurança
                            }
                            break;
                        default: console.warn("Unhandled click action:", action);
                    }
                } catch (error) {
                    console.error(`Erro ao processar ação "${actionButton?.dataset?.action}":`, error);
                }
                return; // Ação principal tratada
            }


            // --- Prioridade 6: Clique no CARD do Feed (Marcar como Lido) ---
            const feedItemElement = e.target.closest('.feed-item');
            if (feedItemElement &&
                !e.target.closest('.feed-item-avatar') &&
                !e.target.closest('button') &&
                !e.target.closest('a') &&
                !e.target.closest('select') &&
                !e.target.closest('input') &&
                !e.target.closest('label') &&
                !e.target.closest('code') &&
                !e.target.closest('.feed-item-details a') &&
                !e.target.closest('.feed-item-actions button') &&
                !e.target.closest('.feed-item-actions select'))
            {
                if (feedItemElement.classList.contains('unread')) {
                    console.log(`Action: Feed Item Card Clicked (Unread) - EventID ${feedItemElement.dataset.eventId}`);
                    markFeedItemAsRead(feedItemElement.dataset.eventId, feedItemElement);
                } else {
                    console.log(`Action: Feed Item Card Clicked (Already Read) - EventID ${feedItemElement.dataset.eventId}`);
                }
                // Não precisa de 'return' aqui
            }

        }); // Fim do listener de CLICK no body

        // --- Listener de CHANGE (para selects de status) ---
        // (O código desta seção permanece o mesmo da versão anterior)
        document.body.addEventListener('change', async (e) => {
            if (e.target.matches('.feed-status-select')) {
                const select = e.target;
                select.blur();
                const newStatus = select.value;
                const eventId = select.dataset.eventId;
                const eventType = select.dataset.eventType;
                const oldStatus = select.dataset.currentStatus;
                if (!eventId || !eventType) { console.error("Select change missing data."); select.value = oldStatus; return; }

                console.log(`Status Select Change: EvtID ${eventId}, Type ${eventType}, From ${oldStatus} To ${newStatus}`);

                if (eventType === 'whatsapp_request' && newStatus === 'approved') {
                    const container = select.closest('tr, .feed-item'); let currentWaNumber = '';
                     if(container) {
                         const approveBtn = container.querySelector(`button[data-action="approve-whatsapp"][data-event-id="${eventId}"]`);
                         if(approveBtn) currentWaNumber = approveBtn.dataset.currentNumber || '';
                         if(!currentWaNumber){ const numEl = container.querySelector('.whatsapp-number-info strong'); if(numEl) currentWaNumber = numEl.textContent.trim(); }
                     }
                     const whatsappNumberInput = prompt(`Aprovar WhatsApp (Evento #${eventId}).\nNúmero (+55XX...):`, currentWaNumber);
                     if (whatsappNumberInput === null) { select.value = oldStatus; return; }
                     const cleanNumber = whatsappNumberInput.replace(/[^\d+]/g, '');
                     if (!/^\+\d{10,15}$/.test(cleanNumber)) { alert('Número inválido!'); select.value = oldStatus; return; }
                     await updateEventStatus(eventId, eventType, newStatus, select, cleanNumber);
                } else {
                    await updateEventStatus(eventId, eventType, newStatus, select);
                }
            }
        }); // Fim do listener de CHANGE no body

        console.log("[SETUP] Action delegation (click) e CHANGE listeners REVISADOS attached.");
    } // Fim setupActionDelegation
        // --- Funções de Ação Específicas ---
        
        
        async function openUserDetailModal(userId) {
    console.log(`Iniciando openUserDetailModal para User ID: ${userId}`);
    const modalContent = document.getElementById('userDetailContent');
    if (!modalContent) {
        console.error("Erro Crítico: Elemento #userDetailContent não encontrado!");
        return;
    }

    // 1. Mostrar Loading e Abrir Modal
    modalContent.innerHTML = `<p style="text-align:center; padding:40px; color: var(--text-secondary);"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Carregando detalhes completos...</p>`;
    openModal('userDetailModal');

    // 2. Buscar Dados do Usuário (incluindo os novos campos da API)
    const user = await fetchUserData(userId); // Sua função existente, mas que agora retorna MAIS dados

    if (!user) {
        modalContent.innerHTML = `<p style="text-align:center; padding:30px; color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Falha ao carregar dados do usuário #${userId}. Verifique a API.</p>`;
        return;
    }

    // 3. Renderizar o HTML com os dados novos e antigos
    try {
        console.log("[Debug] Renderizando HTML do modal com dados completos...", user);

        // Formatações básicas
        const createdAt = user.created_at ? formatDateFull(user.created_at) : 'N/A';
        const lastLogin = user.last_login ? formatDateFull(user.last_login) : 'Nunca';
        const avatarSrc = `/uploads/avatars/${escapeHTML(user.avatar || defaultAvatar)}?v=${Date.now()}`;
        const defaultAvatarSrc = `/uploads/avatars/${defaultAvatar}?v=${Date.now()}`;

        // *** NOVOS DADOS ***
        const lastModel = escapeHTML(user.last_selected_model_name || '');
        const totalEarnings = parseFloat(user.total_earnings || 0);
        const totalEarningsFmt = formatCurrency(totalEarnings);
        const rankPosition = user.rank_position ? parseInt(user.rank_position) : null; // Pega a posição do rank
        const rankBadgeHTML = getRankBadgeHTML(rankPosition); // Gera o HTML do badge
        const totalSaldoDevedor = parseFloat(user.total_saldo_devedor || 0);
        const totalSaldoDevedorFmt = formatCurrency(totalSaldoDevedor);
        const hasSaldoDevedor = totalSaldoDevedor > 0;

        // Dados existentes
        const telefone = escapeHTML(user.telefone || '');
        const chavePix = escapeHTML(user.chave_pix || '');
        const tipoChavePix = escapeHTML(user.tipo_chave_pix ? ucfirst(user.tipo_chave_pix) : '');
        
        let pixDisplayHTML = '<span style="color:var(--text-tertiary); font-style: italic;">Não Cadastrada</span>';
        if (chavePix && tipoChavePix) {
            pixDisplayHTML = `<strong>${tipoChavePix}:</strong> ${chavePix}`;
        }
        let whatsappStatusHTML = '<span style="color:var(--text-tertiary);">Não Solicitado</span>';
        if (user.whatsapp_aprovado && user.whatsapp_numero) {
            whatsappStatusHTML = `<span style="color:var(--success);"><i class="fab fa-whatsapp"></i> Aprovado (${escapeHTML(user.whatsapp_numero)})</span>`;
        } else if (user.whatsapp_solicitado) {
            whatsappStatusHTML = '<span style="color:var(--warning);"><i class="fas fa-hourglass-half"></i> Solicitado (Aguardando Aprovação)</span>';
        }

        // *** Montagem do HTML com os novos campos ***
        const finalHTML = `
            <div class="user-detail-header">
                <img src="${avatarSrc}" alt="Avatar de ${escapeHTML(user.nome)}" class="user-detail-avatar" onerror="this.onerror=null;this.src='${defaultAvatarSrc}';">
                <div class="user-detail-info">
                    <h3 class="user-detail-name">
                        ${escapeHTML(user.nome || 'Nome não informado')}
                        ${rankBadgeHTML} <!-- BADGE DO RANK AQUI -->
                    </h3>
                    <p class="user-detail-email">${escapeHTML(user.email || 'Email não informado')}</p>
                    <div class="user-detail-status">
                        <span class="badge ${user.is_admin ? 'badge-admin' : 'badge-user'}">
                            ${user.is_admin ? '<i class="fas fa-crown"></i> Admin' : '<i class="fas fa-user"></i> Usuário'}
                        </span>
                        <span class="badge ${user.is_active ? 'badge-active' : 'badge-inactive'}">
                            ${user.is_active ? '<i class="fas fa-check-circle"></i> Ativo' : '<i class="fas fa-times-circle"></i> Inativo'}
                        </span>
                         <span class="badge ${user.is_approved ? 'badge-success' : 'badge-warning'}">
                           ${user.is_approved ? '<i class="fas fa-user-check"></i> Aprovado' : '<i class="fas fa-hourglass-half"></i> Pendente'}
                         </span>
                        <!-- Indicador de Saldo Devedor -->
                        ${hasSaldoDevedor ? `
                            <span class="badge badge-danger" title="Possui saldo devedor total de ${totalSaldoDevedorFmt}">
                                <i class="fas fa-exclamation-triangle"></i> Saldo Devedor
                            </span>
                        ` : ''}
                    </div>
                </div>
            </div>

            <div class="user-detail-body">
                <!-- Coluna 1 -->
                <div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">ID do Usuário</div>
                        <div class="user-detail-value">${user.id}</div>
                    </div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">Nickname</div>
                        <div class="user-detail-value">${escapeHTML(user.nome || '')}</div>
                    </div>
                     <div class="user-detail-group">
                        <div class="user-detail-label">Telefone Pessoal</div>
                        <div class="user-detail-value">${telefone || '<span style="color:var(--text-tertiary);">N/I</span>'}</div>
                    </div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">Data de Registro</div>
                        <div class="user-detail-value">${createdAt}</div>
                    </div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">Último Login</div>
                        <div class="user-detail-value">${lastLogin}</div>
                    </div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">Contagem de Logins</div>
                        <div class="user-detail-value">${user.login_count || '0'}</div>
                    </div>
                </div>

                <!-- Coluna 2 -->
                <div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">IP de Registro</div>
                        <div class="user-detail-value">${escapeHTML(user.registration_ip || '') || '<span style="color:var(--text-tertiary);">N/I</span>'}</div>
                    </div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">Última Modelo Escolhida</div>
                        <div class="user-detail-value">
                            ${lastModel ? `<i class="fas fa-robot" style="margin-right: 5px; color: var(--secondary);"></i><strong>${lastModel}</strong>` : '<span style="color:var(--text-tertiary);">Nenhuma</span>'}
                        </div>
                    </div>
                     <div class="user-detail-group">
                        <div class="user-detail-label">Ganhos Totais (Vida)</div>
                        <div class="user-detail-value" style="color: ${totalEarnings > 0 ? 'var(--success)' : 'inherit'}; font-weight: ${totalEarnings > 0 ? 'bold' : 'normal'};">
                            <i class="fas fa-dollar-sign" style="margin-right: 5px;"></i> ${totalEarningsFmt}
                        </div>
                    </div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">Saldo Devedor Total</div>
                        <div class="user-detail-value ${hasSaldoDevedor ? 'outstanding-balance' : ''}">
                             ${totalSaldoDevedorFmt}
                        </div>
                    </div>
                     <div class="user-detail-group">
                        <div class="user-detail-label">Status WhatsApp (Serviço)</div>
                        <div class="user-detail-value">${whatsappStatusHTML}</div>
                    </div>
                    <div class="user-detail-group">
                        <div class="user-detail-label">Chave PIX</div>
                        <div class="user-detail-value">${pixDisplayHTML}</div>
                    </div>
                </div>
            </div>
        `;
        modalContent.innerHTML = finalHTML;
        console.log("Renderização do modal concluída.");
     } catch (renderError) {
         console.error("Erro DURANTE a renderização do modal:", renderError, user);
         modalContent.innerHTML = `<p style="text-align:center; padding:30px; color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Erro ao exibir detalhes. Verifique o console.</p>`;
     }
}

        // Busca dados de um usuário específico via API
        async function fetchUserData(userId) {
             if (!userId) return null;
             console.log(`Fetching data for user ID: ${userId}`);
             try {
                // Ajuste o endpoint da API conforme necessário
                const response = await fetch(`/admin/api/get_user.php?id=${userId}`, {
                    method: 'GET', // GET é mais apropriado para buscar dados
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'include' // Inclui cookies da sessão
                });

                const responseText = await response.text(); // Pega o texto bruto para depuração
                let data;
                try {
                     // Tenta parsear como JSON
                     data = JSON.parse(responseText);
                } catch (jsonErr) {
                     // Loga o erro e o texto recebido se não for JSON válido
                     console.error("Invalid JSON received (fetchUserData):", responseText);
                     throw new Error(`Resposta inválida do servidor (Status: ${response.status})`);
                }

                // Verifica status HTTP e flag de sucesso no JSON
                if (!response.ok || !data.success) {
                    throw new Error(data.message || `Erro ao buscar dados (Status: ${response.status})`);
                }
                console.log("User data fetched:", data.data);
                return data.data; // Retorna apenas o objeto 'data' do usuário

             } catch(e) {
                console.error('Fetch User Error:', e);
                showToast(`Erro ao buscar dados do usuário #${userId}: ${e.message}`, 'error');
                return null; // Retorna null em caso de erro
             }
        }

        // Abre modal de edição preenchido com dados do usuário
        async function openEditModal(userId) {
        const user = await fetchUserData(userId); // Busca dados atualizados
        if(!user) {
             showToast(`Não foi possível carregar dados para editar o usuário #${userId}.`, 'error');
             return;
        }

        try { // Adiciona try-catch para segurança
            document.getElementById('editUserId').value = user.id || '';
            document.getElementById('editUserName').value = user.nome || '';
            document.getElementById('editUserEmail').value = user.email || '';
            document.getElementById('editUserType').value = user.is_admin ? '1' : '0';
            document.getElementById('editUserStatus').value = user.is_active ? '1' : '0';

            // --- PREENCHE NOVOS CAMPOS ---
            document.getElementById('editUserTelefone').value = user.telefone || '';
            document.getElementById('editUserPixType').value = user.tipo_chave_pix || '';
            document.getElementById('editUserPixKey').value = user.chave_pix || '';
             document.getElementById('editUserApprovedStatus').value = user.is_approved ? '1' : '0'; // <<< ADICIONAR
            // --- FIM PREENCHE NOVOS CAMPOS ---

            openModal('editModal');
        } catch (e) {
             console.error("Erro ao preencher modal de edição:", e);
             showToast("Erro ao abrir formulário de edição.", "error");
        }
    }

        // Abre modal de confirmação de desativação
        function openDeleteConfirmationModal(userId, userName) {
            if (!userId) return;
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName || `Usuário #${userId}`;
            openModal('deleteModal');
        }
         function openActivateConfirmationModal(userId, userName){
            if(!userId)return; const m=document.getElementById('deleteModal');const t=m.querySelector('#deleteModalTitle');const b=m.querySelector('#deleteModalBody');const i=m.querySelector('#deleteUserId');const a=m.querySelector('#deleteAction');const cb=m.querySelector('#confirmDeleteBtn'); if(!m||!t||!b||!i||!a||!cb)return; t.innerHTML='<i class="fas fa-user-check"></i> Confirmar Reativação';b.innerHTML=`<p>Tem certeza que deseja reativar <strong>${escapeHTML(userName||'usuário')}</strong>?</p>`;i.value=userId;a.value='activate';cb.className='btn btn-activate';cb.innerHTML='<i class="fas fa-check"></i> Confirmar Reativação'; m.querySelector('.modal-content').style.background='linear-gradient(145deg, var(--success), #008040)';m.querySelector('.modal-content').style.borderColor='var(--success)'; openModal('deleteModal');
        }

               function openNotifyModal(userId, userName, eventId = null) {
            // Verifica se os elementos necessários existem ANTES de tentar usá-los
            if (!userId || !notifyModal || !notifyTypeSelect || !notifyTemplateGroup || !notifyTemplateSelect || !notifyTitleInput || !notifyMessageInput || !notifyUserNameHidden || !notifyUserNameDisplay) {
                console.error("Error: Um ou mais elementos do modal de notificação não foram encontrados no DOM.");
                showToast("Erro interno ao abrir modal de notificação.", "error");
                return; // Interrompe a execução se algo estiver faltando
            }

            console.log(`Opening notify modal for User ID: ${userId}, Name: ${userName}`);

            // Guarda os dados nos inputs corretos
            document.getElementById('notifyUserId').value = userId;
            document.getElementById('notifyEventId').value = eventId || '';
            notifyUserNameHidden.value = userName || `Usuário #${userId}`; // Nome real no campo escondido

            // Mostra o nome no campo visível (readonly)
            notifyUserNameDisplay.value = userName || `Usuário #${userId}`;

            // --- Ações de Reset para os novos campos ---
            notifyTypeSelect.value = 'custom'; // Volta para "Personalizada" por padrão
            notifyTemplateGroup.style.display = 'none'; // Esconde o grupo de modelos
            notifyTemplateSelect.innerHTML = '<option value="">-- Selecione um modelo --</option>'; // Limpa modelos antigos

            // Preenche o dropdown de modelos com as opções do array JS 'predefinedNotifications'
            if (typeof predefinedNotifications !== 'undefined' && Array.isArray(predefinedNotifications)) {
                predefinedNotifications.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id; // Usa o 'id' do modelo como valor
                    option.textContent = template.category; // Mostra a 'category' na lista
                    notifyTemplateSelect.appendChild(option);
                });
                console.log(`Populated ${predefinedNotifications.length} templates into dropdown.`);
            } else {
                console.warn("predefinedNotifications array is missing or invalid in JS. Template dropdown will be empty.");
            }
            notifyTemplateSelect.value = ""; // Garante que o placeholder "-- Selecione --" esteja selecionado

            // Limpa campos de título e mensagem
            notifyTitleInput.value = '';
            notifyMessageInput.value = '';
            notifyTitleInput.readOnly = false; // Garante que sejam editáveis
            notifyMessageInput.readOnly = false;

            // Abre o modal (chama sua função existente)
            openModal('notifyModal');
        }
        // ===> FIM DA SUBSTITUIÇÃO de openNotifyModal <===


        // ===> ADICIONE esta função NOVA para configurar os listeners <===
        function setupNotificationTemplateListeners() {
            // Verifica novamente se os elementos existem para segurança
            if (!notifyTypeSelect || !notifyTemplateSelect || !notifyTitleInput || !notifyMessageInput || !notifyTemplateGroup || !notifyUserNameHidden) {
                 console.warn("Notify modal elements missing. Listeners cannot be attached.");
                 return; // Sai se algum elemento crucial faltar
            }

            console.log("Setting up notification template listeners...");

            // Listener para o dropdown TIPO (Personalizado/Pré-definido)
            if (!notifyTypeSelect.dataset.listenerAttached) { // Evita adicionar o listener múltiplas vezes
                notifyTypeSelect.addEventListener('change', function() {
                    console.log("Notify type changed to:", this.value);
                    if (this.value === 'predefined') {
                        // Mostra o dropdown de modelos
                        notifyTemplateGroup.style.display = 'block';
                        // Limpa título/mensagem atuais (opcional, mas bom)
                        notifyTitleInput.value = '';
                        notifyMessageInput.value = '';
                        notifyTemplateSelect.value = ""; // Seleciona o placeholder
                        // Garante que campos sejam editáveis
                        notifyTitleInput.readOnly = false;
                        notifyMessageInput.readOnly = false;
                    } else { // Se voltou para 'custom'
                        // Esconde o dropdown de modelos
                        notifyTemplateGroup.style.display = 'none';
                        // Limpa título/mensagem
                        notifyTitleInput.value = '';
                        notifyMessageInput.value = '';
                        // Garante que os campos sejam editáveis
                        notifyTitleInput.readOnly = false;
                        notifyMessageInput.readOnly = false;
                    }
                });
                notifyTypeSelect.dataset.listenerAttached = 'true'; // Marca que o listener foi adicionado
                console.log("Listener attached to notifyTypeSelect.");
            } else {
                console.log("Listener already attached to notifyTypeSelect.");
            }

            // Listener para o dropdown MODELO (quando seleciona um modelo específico)
            if (!notifyTemplateSelect.dataset.listenerAttached) { // Evita adicionar o listener múltiplas vezes
                notifyTemplateSelect.addEventListener('change', function() {
                    const selectedTemplateId = this.value;
                    console.log("Notify template selected:", selectedTemplateId);
                    // Pega o nome do usuário guardado no campo escondido
                    const userName = notifyUserNameHidden.value || 'Usuário'; // Usa 'Usuário' como fallback

                    // Encontra o modelo correspondente no array JS 'predefinedNotifications'
                    const template = predefinedNotifications.find(t => t.id === selectedTemplateId);

                    if (template) {
                        console.log("Found template:", template);
                        // Preenche título e mensagem, substituindo o placeholder [User Name]
                        let title = template.title || '';
                        let message = template.message || '';

                        // Substitui todas as ocorrências de [User Name] pelo nome real
                        // A flag 'g' garante que substitua todas as ocorrências, não só a primeira
                        title = title.replace(/\[User Name\]/g, userName);
                        message = message.replace(/\[User Name\]/g, userName);

                        // *** Adicione mais substituições aqui se precisar ***
                        // Ex: Se você precisar do ID do evento no texto:
                        // const eventId = document.getElementById('notifyEventId')?.value;
                        // if (eventId) {
                        //     message = message.replace(/\[Event ID\]/g, eventId);
                        // }

                        // Coloca o texto nos campos do formulário
                        notifyTitleInput.value = title;
                        notifyMessageInput.value = message;

                        // Deixa os campos editáveis por padrão (para você poder ajustar)
                        notifyTitleInput.readOnly = false;
                        notifyMessageInput.readOnly = false;

                    } else {
                        console.log("No template found for ID or placeholder selected:", selectedTemplateId);
                        // Se voltou para "-- Selecione --" ou não encontrou o template
                        notifyTitleInput.value = '';
                        notifyMessageInput.value = '';
                        notifyTitleInput.readOnly = false; // Garante editável
                        notifyMessageInput.readOnly = false;
                    }
                });
                notifyTemplateSelect.dataset.listenerAttached = 'true'; // Marca que o listener foi adicionado
                console.log("Listener attached to notifyTemplateSelect.");
            } else {
                 console.log("Listener already attached to notifyTemplateSelect.");
            }
        }
        
        

// *NOVO* Função para lidar com clique no botão Aprovar WA (da tabela pendente)
async function handleApproveWhatsapp(button) {
    // Pega os dados do botão clicado
    const userIdForUpdate = button.dataset.userId; // <<< Pega o user ID DO BOTÃO
    const currentNumber = button.dataset.currentNumber || '';

    // Valida se o ID foi pego corretamente
    if (!userIdForUpdate) {
        console.error("Approve WA Error: Missing data-user-id attribute on the button.");
        showToast("Erro: ID do usuário não encontrado no botão.", "error");
        return;
    }
    console.log(`[handleApproveWhatsapp] User ID from button: ${userIdForUpdate}`); // Log para confirmar

    // Pede ao admin para confirmar/inserir o número via prompt
    const whatsappNumberInput = prompt(`Aprovar WhatsApp (Usuário #${userIdForUpdate}).\nNúmero (+55XX...):`, currentNumber);
    if (whatsappNumberInput === null) return; // Usuário cancelou

    // Valida o número inserido
    const cleanNumber = whatsappNumberInput.replace(/[^\d+]/g, '');
    if (!/^\+\d{10,15}$/.test(cleanNumber)) {
        alert('Número inválido!');
        return;
    }

    // Chama a função genérica para atualizar o status no backend
    // Passa userIdForUpdate como o PRIMEIRO argumento (que updateEventStatus espera como eventId)
    await updateEventStatus(userIdForUpdate, 'whatsapp_request', 'approved', button, cleanNumber);
}
async function handleRejectWhatsapp(button) {
    const userIdForUpdate = button.dataset.userId; // <<< Pega o user ID DO BOTÃO
    const userName = button.closest('tr')?.querySelector('.user-name')?.textContent || `Usuário #${userIdForUpdate}`; // Pega nome para confirmação

    // Valida se o ID foi pego corretamente
    if (!userIdForUpdate) {
        console.error("Reject WA Error: Missing data-user-id attribute on the button.");
        showToast("Erro: ID do usuário não encontrado no botão.", "error");
        return;
    }
     console.log(`[handleRejectWhatsapp] User ID from button: ${userIdForUpdate}`); // Log para confirmar

    // Pede confirmação ao admin
    if (!confirm(`Rejeitar solicitação de WhatsApp para ${userName}?`)) return;

    // Chama a função genérica para atualizar o status no backend
    // Passa userIdForUpdate como o PRIMEIRO argumento
    await updateEventStatus(userIdForUpdate, 'whatsapp_request', 'rejected', button);
}
// *NOVO* Função para remover linha da tabela de WA pendente (com animação)
function removePendingWhatsappRow(eventId) {
     try {
         const row = pendingWATableBody?.querySelector(`tr[data-event-id="${eventId}"]`);
         if (row) {
             console.log(`Removing pending WA row ${eventId}`);
             // Adiciona animação de saída
             row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
             row.style.opacity = '0'; row.style.transform = 'translateX(-20px)';
             // Remove o elemento do HTML após a animação
             setTimeout(() => {
                 row.remove();
                 updatePendingWACount(); // Atualiza o contador no menu
                 // Se a tabela ficar vazia, mostra mensagem
                 if (pendingWATableBody && !pendingWATableBody.querySelector('tr[data-event-id]')) {
                     pendingWATableBody.innerHTML = `<tr><td colspan="5" style="/*...*/"><i class="fas fa-check-circle"></i> Nenhuma solicitação.</td></tr>`;
                 }
             }, 400);
         }
     } catch(e){ console.error("Error removing WA row:", e); }
}

// *NOVO* Função para atualizar o contador no menu da sidebar
function updatePendingWACount() {
    // Seletores (garante que existam)
    const badgeElement = document.getElementById('pending-wa-count-badge');
    const tableBody = document.querySelector('#pending-whatsapp-table tbody');

    // Sai se os elementos não forem encontrados
    if (!badgeElement || !tableBody) {
        console.warn("Elementos do badge/tabela WA não encontrados para updatePendingWACount.");
        return;
    }

    console.log("JS: Iniciando updatePendingWACount..."); // Log de início

    try {
        let strictlyPendingCountJS = 0; // Contador JS

        // Seleciona TODAS as linhas da tabela que representam um usuário (com data-user-id)
        const rows = tableBody.querySelectorAll('tr[data-user-id]');
        console.log(`JS: Encontradas ${rows.length} linhas na tabela WA.`); // Log de contagem de linhas

        rows.forEach((row, index) => {
            // Para cada linha, pega o <select> de status
            const statusSelect = row.querySelector('select.feed-status-select');

            // Pega o STATUS ATUAL definido no data attribute do select (mais confiável que o .value inicial)
            const currentStatus = statusSelect ? statusSelect.dataset.currentStatus : null;

            // Log detalhado por linha
            // console.log(`JS: Linha ${index}, User ID: ${row.dataset.userId}, Status Select encontrado: ${!!statusSelect}, Status Atual (data-current-status): ${currentStatus}`);

            // ----- CONDIÇÃO DE CONTAGEM: Exatamente igual à do PHP -----
            // Conta APENAS se o status atual for 'pending'
            // (Assumindo que o PHP está populando data-current-status corretamente
            // com 'pending' apenas para os realmente pendentes [solicitado=1, aprovado=0])
            if (currentStatus === 'pending') {
                strictlyPendingCountJS++;
                // console.log(`JS: ---> Contado como pendente! (Contagem atual: ${strictlyPendingCountJS})`);
            }
        });

        console.log(`JS: Contagem final de pendentes: ${strictlyPendingCountJS}`); // Log final

        // Atualiza o texto e a visibilidade do badge
        badgeElement.textContent = strictlyPendingCountJS > 99 ? '99+' : strictlyPendingCountJS.toString();
        badgeElement.style.display = strictlyPendingCountJS > 0 ? 'inline-flex' : 'none';

    } catch (e) {
        console.error('JS: Erro em updatePendingWACount:', e);
        // Em caso de erro, esconde o badge para evitar mostrar número errado
        badgeElement.textContent = '0';
        badgeElement.style.display = 'none';
    }
} // --- Fim da função updatePendingWACount ---
       async function updateEventStatus(eventId, eventType, newStatus, triggerElement, whatsappNumber = null) {
            //const itemElement = selectElement.closest('.feed-item'); // Linha original removida/modificada
            const containerElement = triggerElement.closest('tr, .feed-item'); // Container pai (linha da tabela OU item do feed)
            const isTableRow = containerElement?.tagName === 'TR';
            const isPendingWATable = isTableRow && containerElement?.closest('#pending-whatsapp-table') !== null;
            const feedItemElement = !isTableRow ? containerElement : document.querySelector(`.feed-item[data-event-id="${eventId}"]`); // Encontra o item no feed SE a ação veio do feed ou depois da API

            const originalStatus = triggerElement.dataset.currentStatus;
            const requestUrl = '/admin/api/update_event_status.php';

            console.log(`Attempting to update Event ${eventId} (${eventType}) from ${originalStatus} to ${newStatus}`);

            // Feedback visual de loading no elemento que disparou a ação
            triggerElement.disabled = true;
            if(containerElement) containerElement.style.opacity = '0.6'; // Diminui opacidade do container (linha ou feed item)
            const spinner = document.createElement('i');
            spinner.className = 'fas fa-spinner fa-spin feed-status-spinner'; // Classe para identificar/remover
            spinner.style.marginLeft = '5px'; spinner.style.fontSize = '0.9em';
            if(triggerElement.tagName === 'SELECT') triggerElement.parentNode.appendChild(spinner);
            else if(triggerElement.tagName === 'BUTTON') triggerElement.prepend(spinner);

            const requestBody = { event_id: eventId, event_type: eventType, new_status: newStatus };
            if (eventType === 'whatsapp_request' && newStatus === 'approved' && whatsappNumber) { requestBody.whatsapp_number = whatsappNumber; }

            try {
                const response = await fetch(requestUrl, { method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, credentials: 'include', body: JSON.stringify(requestBody) });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || `Erro ${response.status}`);

                const friendlyStatus = statusLabels[newStatus] || ucfirst(newStatus.replace(/_/g, ' '));
                showToast(`Status #${eventId} atualizado para "${friendlyStatus}".`, 'success');

                // Atualiza o estado visual do elemento gatilho (select ou botão)
                if (triggerElement.tagName === 'SELECT') {
                     triggerElement.dataset.currentStatus = newStatus; // Atualiza o data attribute no select
                }


const statusCell = triggerElement.closest('td.status-cell'); // Encontra a célula pai
if (statusCell) {
    const badgeElement = statusCell.querySelector('.status-badge-wa'); // Encontra o badge dentro da célula
    if (badgeElement) {
        // Pega as novas informações do badge (simulando a função PHP em JS)
        let badgeClass = 'status-default';
        let badgeIcon = 'fa-question-circle';
        let badgeLabel = statusLabels[newStatus] || ucfirst(newStatus.replace(/_/g, ' ')); // Usa o helper JS `ucfirst`

        switch (newStatus) {
            case 'pending': badgeClass = 'status-pending'; badgeIcon = 'fa-hourglass-half'; break;
            case 'aguardando_resposta': badgeClass = 'status-aguardando_resposta'; badgeIcon = 'fa-clock'; break;
            case 'processing': badgeClass = 'status-processing'; badgeIcon = 'fa-cogs'; break;
            case 'approved': badgeClass = 'status-approved'; badgeIcon = 'fa-check-circle'; break;
            case 'rejected': badgeClass = 'status-rejected'; badgeIcon = 'fa-times-circle'; break;
        }

        // Atualiza a classe, o ícone e o texto do badge
        badgeElement.className = `status-badge-wa ${badgeClass}`; // Define todas as classes
        badgeElement.title = badgeLabel; // Atualiza o tooltip
            // Atualiza o conteúdo interno do badge (Ícone + Espaço + Label) - CORRIGIDO
    badgeElement.innerHTML = `<i class="fas ${badgeIcon}"></i> ${badgeLabel}`;
         console.log(`Badge UI updated for event ${eventId} to ${newStatus}`);
    } else { console.warn(`Badge element not found in status cell for event ${eventId}`); }
} else { console.warn(`Status cell not found for event ${eventId}`); }
// --- FIM: Bloco para atualizar o Badge ---

                 // Lógica específica para remover da tabela de pendentes
                  if(isPendingWATable && eventType === 'whatsapp_request' && ['approved', 'rejected'].includes(newStatus)) {
            removePendingWhatsappRow(eventId); // Esta já chama updatePendingWACount
        } else if (isPendingWATable) { // Só atualiza se for da tabela WA e não removeu
             updatePendingWACount();
        }

        updateFilterButtonCounts(); // <--- ADICIONAR CHAMADA PARA RECONTAR FILTROS

                // Atualiza a UI do item correspondente no FEED (se ele existir)
                const feedItemToUpdate = document.querySelector(`.feed-item[data-event-id="${eventId}"]`); // Busca novamente, pode ter sido adicionado via SSE
                if(feedItemToUpdate) {
                    console.log(`Updating UI for feed item ${eventId}`);
                    const feedSelect = feedItemToUpdate.querySelector('.feed-status-select');
                    if(feedSelect) { feedSelect.value = newStatus; feedSelect.dataset.currentStatus = newStatus; }

                    if (eventType === 'whatsapp_request') { // Atualiza detalhes visuais de WA no feed
                        const detailsDiv = feedItemToUpdate.querySelector('.feed-item-details');
                        feedItemToUpdate.querySelectorAll('.whatsapp-number-info, .whatsapp-status-info').forEach(el => el.remove());
                        let statusInfoHTML = '';
                        const approvedNum = data.data?.whatsapp_number || whatsappNumber; // Pega da resposta ou do input
                        if (newStatus === 'approved' && approvedNum) statusInfoHTML = `<p class="whatsapp-number-info" style='color: var(--success);'><i class="fab fa-whatsapp"></i> Nº Aprovado: <strong>${escapeHTML(approvedNum)}</strong></p>`;
                        else if (newStatus === 'processing') statusInfoHTML = `<p class="whatsapp-status-info" style='color: var(--info);'><i class="fas fa-spinner fa-spin"></i> Processando...</p>`;
                        else if (newStatus === 'rejected') statusInfoHTML = `<p class="whatsapp-status-info" style='color: var(--danger);'><i class="fas fa-times-circle"></i> Rejeitado.</p>`;
                        else if (newStatus === 'aguardando_resposta') statusInfoHTML = `<p class="whatsapp-status-info" style='color: var(--info);'><i class="fas fa-clock"></i> Aguard. Resposta.</p>`;
                        else if (newStatus === 'pending') statusInfoHTML = `<p class="whatsapp-status-info" style='color: var(--warning);'><i class="fas fa-hourglass-half"></i> Pendente.</p>`;

                        if(detailsDiv && statusInfoHTML) detailsDiv.insertAdjacentHTML('afterbegin', statusInfoHTML);
                        else if (!detailsDiv && statusInfoHTML) { const newDiv=document.createElement('div'); newDiv.className='feed-item-details'; newDiv.innerHTML=statusInfoHTML; feedItemToUpdate.querySelector('.feed-item-actions')?.insertAdjacentElement('beforebegin',newDiv); }
                    }
                    // Feedback visual no feed item
                    feedItemToUpdate.style.transition='background-color 0.5s ease-out'; feedItemToUpdate.style.backgroundColor='var(--success-light)'; setTimeout(()=>{if(feedItemToUpdate)feedItemToUpdate.style.backgroundColor='';},600);
                }

            } catch (error) {
                console.error("Erro ao atualizar status:", error);
                showToast(`Falha: ${error.message}`, 'error');
                if(triggerElement.tagName === 'SELECT') triggerElement.value = originalStatus; // Reverte select
            } finally {
                triggerElement.disabled = false;
                if(containerElement) containerElement.style.opacity = '1'; // Restaura opacidade
                spinner?.remove(); // Remove o spinner do lado do select
                 if (triggerElement.tagName === 'BUTTON') { // Remove spinner do botão
                    triggerElement.querySelector('.feed-status-spinner')?.remove(); // Usa a classe correta
                 }
            }
        }
        // --- Submissão de Formulários via AJAX ---
        function setupFormSubmissions() {
            const editForm = document.getElementById('editForm');
            const notifyForm = document.getElementById('notifyForm');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

            if (editForm) {
                 if(!editForm.dataset.submitListenerAttached) {
                     editForm.dataset.submitListenerAttached = 'true';
                    editForm.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        await submitAjax(
                            '/admin/api/update_user.php', // Endpoint API
                            Object.fromEntries(new FormData(this).entries()), // Dados do form
                            this.querySelector('button[type="submit"]'), // Botão de submit
                            'Salvando...', // Texto loading
                            '<i class="fas fa-save"></i> Salvar Alterações', // Texto original
                            'Usuário atualizado com sucesso!', // Mensagem sucesso
                            () => { // Callback sucesso
                                closeModal('editModal');
                                refreshUserTable(); // Atualiza a tabela
                            }
                            // Método padrão é POST
                        );
                    });
                 }
            }

            if (notifyForm) {
                 if(!notifyForm.dataset.submitListenerAttached) {
                     notifyForm.dataset.submitListenerAttached = 'true';
                    notifyForm.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        await submitAjax(
                            '/admin/api/send_notification.php', // Endpoint API
                            Object.fromEntries(new FormData(this).entries()),
                            this.querySelector('button[type="submit"]'),
                            'Enviando...',
                            '<i class="fas fa-paper-plane"></i> Enviar Notificação',
                            'Notificação enviada com sucesso!',
                            () => {
                                closeModal('notifyModal');
                                this.reset(); // Limpa o formulário
                            }
                        );
                    });
                 }
            }

            if (confirmDeleteBtn) {
                 if(!confirmDeleteBtn.dataset.clickListenerAttached) {
                     confirmDeleteBtn.dataset.clickListenerAttached = 'true';
                    confirmDeleteBtn.addEventListener('click', async function() {
                        const userId = document.getElementById('deleteUserId').value;
                        if (!userId) return;

                        await submitAjax(
                            `/admin/api/delete_user.php?id=${userId}`, // Endpoint API (DELETE)
                            null, // Sem corpo para DELETE
                            this,
                            'Desativando...',
                            '<i class="fas fa-check"></i> Confirmar Desativação',
                            'Usuário desativado com sucesso!',
                            () => {
                                closeModal('deleteModal');
                                refreshUserTable(); // Atualiza a tabela
                            },
                            'DELETE' // Método HTTP
                        );
                    });
                 }
            }
        }

         async function refreshUserTable() {
            console.log("Refreshing user table...");
            // Não precisa redefinir 'usersTableBody' aqui se ela for global e já definida
            if (!usersTableBody) { // Usa a variável global
                console.error("CRITICAL: usersTableBody element not found in refreshUserTable.");
                // Adicione um feedback visual para o usuário, se apropriado
                const mainUsersTable = document.getElementById('usersTable');
                if (mainUsersTable) {
                    mainUsersTable.innerHTML = `<tr><td colspan="7" style="color:red; text-align:center; padding:20px;">Erro interno: Tabela de usuários não pôde ser carregada.</td></tr>`;
                }
                return;
            }

            // Mostra loading na tabela
            usersTableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px; color: var(--text-secondary);">
                        <i class="fas fa-spinner fa-spin fa-lg"></i> Atualizando lista...
                    </td>
                </tr>`;

            try {
                const response = await fetch('/admin/api/get_users_list.php', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'include'
                });

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch(e) {
                    console.error("Invalid JSON (refreshUserTable):", responseText);
                    // Logar o responseText para o log do servidor também seria útil aqui
                    // error_log_js_to_php("Invalid JSON (refreshUserTable): " + responseText); // Se você tiver tal função
                    throw new Error(`Resposta inválida do servidor ao atualizar usuários (Status ${response.status}). Verifique o console e logs da API.`);
                }

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao buscar lista de usuários da API.');
                }

                // Se a API retornar dados de paginação, você pode usá-los
                // Por enquanto, vamos assumir que ela retorna apenas 'data.users'
                renderUserTable(data.users); // Renderiza com os novos dados
                console.log("User table refreshed successfully.");
                // showToast("Lista de usuários atualizada.", "info"); // Opcional

            } catch (error) {
                console.error("Erro ao atualizar tabela de usuários:", error);
                usersTableBody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--danger); padding: 30px;">
                             <i class="fas fa-exclamation-triangle"></i> ${escapeHTML(error.message)}
                        </td>
                    </tr>`;
                showToast(`Erro ao atualizar usuários: ${error.message}`, "error");
            }
        }

        // Renderiza as linhas da tabela de usuários
 function renderUserTable(usersData) {
            // usersTableBody AQUI SE REFERE À CONSTANTE GLOBAL DEFINIDA NO TOPO DO SCRIPT
            if (!usersTableBody) {
                console.error("User table body (usersTableBody) not found for rendering.");
                return;
            }
            usersTableBody.innerHTML = ''; // Limpa conteúdo atual

            if (!usersData || usersData.length === 0) {
                usersTableBody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);"> <!-- Colspan ajustado para 7 -->
                            <i class="fas fa-ghost fa-2x" style="margin-bottom: 10px;"></i><br>
                            Nenhum usuário encontrado.
                        </td>
                    </tr>`;
                return;
            }

            usersData.forEach(user => {
                try {
                    const avatar = escapeHTML(user.avatar || defaultAvatar);
                    const nome = escapeHTML(user.nome);
                    const email = escapeHTML(user.email);
                    const isAdmin = user.is_admin == 1; // Converte para booleano
                    const isActive = user.is_active == 1; // Converte para booleano
                    const isApproved = user.is_approved == 1; // Converte para booleano (IMPORTANTE)

                    const createdAt = user.created_at ? formatDateFull(user.created_at) : 'N/A';
                    const createdAtFull = user.created_at ? new Date(user.created_at).toLocaleString('pt-BR') : '';
                    const saldoHoje = parseFloat(user.saldo_hoje || 0.00);
                    const saldoHojeFmt = formatCurrency(saldoHoje);
                    const saldoHojeColor = saldoHoje > 0 ? 'var(--success)' : 'inherit';

                    // const whatsappLink = formatWhatsappLinkJS(user.telefone); // Se você tiver essa função e quiser usar

                    const row = document.createElement('tr');
                    row.dataset.userId = user.id;
                    row.dataset.userName = nome.toLowerCase(); // Para busca
                    row.innerHTML = `
                        <td>
                            <div class="user-info-cell">
                                <img src="/uploads/avatars/${avatar}?v=${Date.now()}"
                                     alt="Avatar de ${nome}" class="user-avatar-sm"
                                     onerror="this.onerror=null;this.src='/uploads/avatars/${defaultAvatar}'">
                                <div>
                                    <span class="user-name">${nome}</span>
                                    <span class="user-email-sub">${email}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge ${isAdmin ? 'badge-admin' : 'badge-user'}">
                                 ${isAdmin ? '<i class="fas fa-crown"></i> Admin' : '<i class="fas fa-user"></i> Usuário'}
                            </span>
                        </td>
                        <td>
                            <span class="badge ${isActive ? 'badge-active' : 'badge-inactive'}">
                                 ${isActive ? '<i class="fas fa-check-circle"></i> Ativo' : '<i class="fas fa-times-circle"></i> Inativo'}
                            </span>
                        </td>
                        <td> <!-- Coluna Status Aprovação -->
                            ${isApproved ?
                                `<span class="badge badge-success"><i class="fas fa-user-check"></i> Aprovado</span>` :
                                `<span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Pendente</span>`
                            }
                        </td>
                        <td class="user-balance-cell" style="font-weight: 600; color: ${saldoHojeColor};">
                            ${saldoHojeFmt}
                        </td>
                        <td title="${createdAtFull}">${createdAt}</td>
                        <td>
                            <div class="action-buttons" style="justify-content: flex-end;">
                                <!-- Botão de Aprovar (só aparece se não aprovado e não for o próprio admin) -->
                                ${!isApproved && user.id != adminUserId ?
                                    `<button class="btn btn-sm btn-success" data-action="approve-user" data-user-id="${user.id}" data-user-name="${nome}" title="Aprovar Usuário"><i class="fas fa-thumbs-up"></i> Aprovar</button>` : ''
                                }
                                <button class="btn btn-sm btn-details" data-action="details" data-user-id="${user.id}" title="Ver Detalhes"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-edit" data-action="edit" data-user-id="${user.id}" title="Editar Usuário"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-notify" data-action="notify" data-user-id="${user.id}" data-user-name="${nome}" title="Notificar Usuário"><i class="fas fa-bell"></i></button>
                                ${user.id != adminUserId ?
                                    (isActive ?
                                        `<button class="btn btn-sm btn-delete" data-action="deactivate" data-user-id="${user.id}" data-user-name="${nome}" title="Desativar Usuário"><i class="fas fa-user-slash"></i></button>` :
                                        `<button class="btn btn-sm btn-activate" data-action="activate" data-user-id="${user.id}" data-user-name="${nome}" title="Reativar Usuário"><i class="fas fa-user-check"></i></button>`
                                    ) +
                                    `<button class="btn btn-sm btn-warning btn-reset-balance" data-action="reset-balance" data-user-id="${user.id}" data-user-name="${nome}" title="Zerar Saldo de Hoje"><i class="fas fa-dollar-sign"></i><i class="fas fa-times" style="font-size:0.7em; position: relative; top: -0.1em; left: -0.2em;"></i></button>` +
                                    `<button class="btn btn-sm btn-danger" data-action="delete-permanently" data-user-id="${user.id}" data-user-name="${nome}" title="EXCLUIR PERMANENTEMENTE (IRREVERSÍVEL!)"><i class="fas fa-skull-crossbones"></i></button>`
                                : ''}
                            </div>
                        </td>
                    `;
                    usersTableBody.appendChild(row); // Adiciona à variável global
                } catch (renderError) {
                    console.error("Erro ao renderizar linha do usuário:", renderError, user);
                    const errorRow = document.createElement('tr');
                    // Colspan ajustado para 7
                    errorRow.innerHTML = `<td colspan="7" style="color: var(--danger); font-style: italic;">Erro ao renderizar usuário ID ${user?.id || '??'}</td>`;
                    usersTableBody.appendChild(errorRow);
                }
            });
        }
      // --- Função Auxiliar para Requisições AJAX Genéricas ---
async function submitAjax(url, data, button, loadingText, originalHTML, successMessage, successCallback, method = 'POST') {
    button.disabled = true;
    button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${escapeHTML(loadingText)}`;
    try {
        const options = { method: method.toUpperCase(), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'include' };
        if (data && ['POST', 'PUT', 'PATCH'].includes(options.method)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        } else if (!data && method === 'DELETE') {
             // DELETE sem corpo
        } else if (data && method === 'GET') {
             url += '?' + new URLSearchParams(data).toString();
        }
        const response = await fetch(url, options);
        const responseText = await response.text();
        let responseData;
        try { responseData = JSON.parse(responseText); } catch (e) { throw new Error(`Resposta inválida (Status: ${response.status})`); }
        if (!response.ok || !responseData.success) throw new Error(responseData.message || `Erro ${response.status}`);
        showToast(successMessage, 'success');
        if (typeof successCallback === 'function') successCallback(responseData.data);
        return responseData; // Retorna a resposta completa
    } catch (error) {
        console.error(`AJAX Error (${url}):`, error);
        showToast(`Erro: ${error.message}`, 'error');
        throw error; // Re-throw para que a chamada possa tratar se necessário
    } finally {
        button.disabled = false;
        button.innerHTML = originalHTML;
    }
}

function setupUserSearch() {
    console.log("Setting up user search..."); // Log para depuração
    const searchInput = document.getElementById('userSearchInput');
    const tableBody = document.querySelector('#usersTable tbody'); // Seletor corrigido para pegar o tbody

    if (!searchInput || !tableBody) {
        console.warn("Search input or user table body not found for search setup.");
        return; // Sai da função se os elementos não existirem
    }

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const rows = tableBody.querySelectorAll('tr[data-user-name]'); // Pega todas as linhas com o atributo

        rows.forEach(row => {
            const userName = row.dataset.userName || ''; // Pega o nome do atributo data-user-name
            if (userName.includes(searchTerm)) {
                row.style.display = ''; // Mostra a linha se o nome contém o termo
            } else {
                row.style.display = 'none'; // Esconde a linha caso contrário
            }
        });
    });
     console.log("User search setup complete."); // Log para depuração
}

        // --- Feed Real-time (SSE) ---
        function startRealtimeFeed() {
            if (!adminUserId || isSSEConnected) {
                if(isSSEConnected) console.log("SSE: Already connected or attempting.");
                else console.log("SSE: Cannot start, adminUserId missing.");
                return;
            }

            stopRealtimeFeed(); // Garante que não haja conexões antigas

            // Inclui lastEventId para buscar apenas eventos novos na reconexão
            const url = `/admin/api/realtime_feed.php?user_id=${adminUserId}&lastEventId=${lastEventId}&t=${Date.now()}`; // Adiciona timestamp
            console.log(`SSE: Connecting to ${url}`);

            isSSEConnected = true;
            reconnectAttempts = 0; // Reseta tentativas
             if(feedLoadingIndicator) feedLoadingIndicator.style.display = 'none';
             if(noFeedItemsMessage) noFeedItemsMessage.style.display = 'none'; // Esconde msg "sem itens"

            eventSource = new EventSource(url, { withCredentials: true }); // Envia cookies

            eventSource.onopen = () => {
                console.log("SSE: Connection opened successfully.");
                  if(feedLoadingIndicator) feedLoadingIndicator.style.display = 'none';
                reconnectAttempts = 0; // Reseta tentativas no sucesso
            };
            

            eventSource.addEventListener('feed_update', (e) => {
                try {
                    const newItemData = JSON.parse(e.data);
                    // *MODIFICADO* Filtra o tipo de evento ANTES de processar
                    const allowedFeedTypes = ['proof_upload', 'call_request', 'pix_payment_reported', 'whatsapp_request', 'user_registered'];
                    if (newItemData?.id && newItemData.id > lastEventId && allowedFeedTypes.includes(newItemData.event_type)) {
                        console.log(`SSE: Processing new allowed event ID ${newItemData.id}, Type: ${newItemData.event_type}`);
                        lastEventId = newItemData.id;
                        // *NOVO* Adiciona ao INÍCIO do array de todos os itens
                        allFeedItems.unshift(newItemData);
                        // Recalcula a paginação e renderiza a página atual (geralmente a 1)
                        renderFeedPage(1); // Volta para a primeira página para ver o item novo
                        playNotificationSound();
                        if(noFeedItemsMessage) noFeedItemsMessage.style.display = 'none';
                    } else if (newItemData?.id && newItemData.id <= lastEventId) {
                        // console.log(`SSE: Skipping old/duplicate event ID ${newItemData.id}`);
                    } else if (newItemData?.event_type && !allowedFeedTypes.includes(newItemData.event_type)) {
                         console.log(`SSE: Skipping disallowed event type: ${newItemData.event_type}`);
                    }
                     else {
                         console.warn("SSE: Received invalid feed_update data:", newItemData);
                    }
                } catch(err) { console.error("SSE: Error parsing feed_update data:", err, "| Raw data:", e.data); }
            });


             // Listener genérico para mensagens sem 'event' definido (menos comum)
             eventSource.onmessage = (e) => {
                 console.log("SSE: Received generic message:", e.data);
                 // Poderia tentar parsear e processar se esperado
             };

            // Listener para erros na conexão SSE
            eventSource.onerror = (err) => {
                console.error('SSE: Connection error occurred.', err);
                 if(feedLoadingIndicator) feedLoadingIndicator.style.display = 'none';
                const currentState = eventSource.readyState;
                eventSource.close(); // Fecha a conexão atual
                eventSource = null;
                isSSEConnected = false;

                 // Ready state: 0=CONNECTING, 1=OPEN, 2=CLOSED
                 console.log(`SSE: Connection state was ${currentState}. Attempting reconnect...`);


                reconnectAttempts++;
                if(reconnectAttempts <= MAX_RECONNECT_ATTEMPTS) {
                    // Backoff exponencial com jitter
                    const delay = RECONNECT_DELAY_BASE * Math.pow(2, reconnectAttempts - 1) + Math.random() * 1000;
                     const cappedDelay = Math.min(delay, 30000); // Limita delay a 30s
                    console.log(`SSE: Reconnect attempt ${reconnectAttempts}/${MAX_RECONNECT_ATTEMPTS} in ${Math.round(cappedDelay/1000)}s...`);
                    setTimeout(startRealtimeFeed, cappedDelay);
                } else {
                    console.error("SSE: Maximum reconnect attempts reached. Stopping SSE.");
                    showToast("Conexão com as atualizações em tempo real perdida. Recarregue a página.", "error");
                }
            };
        }

        // Para a conexão SSE
        function stopRealtimeFeed() {
            if(eventSource) {
                console.log("SSE: Closing connection.");
                eventSource.close();
                eventSource = null;
                isSSEConnected = false;
                if(feedLoadingIndicator) feedLoadingIndicator.style.display = 'none';
            }
        }
        
        
        
        
        
       // *MODIFICADO* Função para renderizar UMA PÁGINA específica do feed e MANTER SCROLL (com setTimeout)
      function renderFeedPage(page) {
        const currentScrollY = window.scrollY; // Salva a posição atual do scroll

        if (!realtimeFeedContainer) return;
        currentPage = page;
        realtimeFeedContainer.innerHTML = ''; // Limpa APENAS o container dos itens

        const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
        const endIndex = startIndex + ITEMS_PER_PAGE;
        const itemsToShow = allFeedItems.slice(startIndex, endIndex);

        console.log(`Rendering page ${page}. Items to show: ${itemsToShow.length}. ScrollY was: ${currentScrollY}`);

        if (allFeedItems.length === 0) {
            if(noFeedItemsMessage) noFeedItemsMessage.style.display = 'block';
            updatePaginationControls();
        } else {
             if(noFeedItemsMessage) noFeedItemsMessage.style.display = 'none';
            if (itemsToShow.length > 0) {
                itemsToShow.forEach(item => {
                    renderFeedItem(item);
                });
            } else {
                 realtimeFeedContainer.innerHTML = '<p style="text-align:center; padding: 20px; color: var(--text-secondary);">Fim dos resultados.</p>';
            }
            updatePaginationControls();
        }

        // Dá um tempo mínimo para o navegador processar o layout antes de forçar o scroll
        setTimeout(() => {
            window.scrollTo({ top: currentScrollY, behavior: 'instant' });
            console.log(`Scroll ATTEMPTED restore to: ${currentScrollY}`); // Log para depuração
        }, 20); // Atraso minúsculo de 20 milissegundos
    }
         // *NOVO* Função para atualizar os botões e info de paginação
          function updatePaginationControls() {
        const totalItems = allFeedItems.length;
        const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);

        // Seleciona os elementos de CIMA e de BAIXO
        const currentPageSpanTop = document.getElementById('feed-current-page-top');
        const totalPagesSpanTop = document.getElementById('feed-total-pages-top');
        const prevPageBtnTop = document.getElementById('feed-prev-page-top');
        const nextPageBtnTop = document.getElementById('feed-next-page-top');
        const paginationControlsTop = document.getElementById('feed-pagination-top');

        const currentPageSpanBottom = document.getElementById('feed-current-page');
        const totalPagesSpanBottom = document.getElementById('feed-total-pages');
        const prevPageBtnBottom = document.getElementById('feed-prev-page');
        const nextPageBtnBottom = document.getElementById('feed-next-page');
        const paginationControlsBottom = document.getElementById('feed-pagination');

        // Atualiza o texto da página atual
        if (currentPageSpanTop) currentPageSpanTop.textContent = currentPage;
        if (currentPageSpanBottom) currentPageSpanBottom.textContent = currentPage;

        // Atualiza o texto do total de páginas
        if (totalPagesSpanTop) totalPagesSpanTop.textContent = totalPages;
        if (totalPagesSpanBottom) totalPagesSpanBottom.textContent = totalPages;

        // Habilita/Desabilita botão "Anterior"
        const isFirstPage = (currentPage <= 1);
        if (prevPageBtnTop) prevPageBtnTop.disabled = isFirstPage;
        if (prevPageBtnBottom) prevPageBtnBottom.disabled = isFirstPage;

        // Habilita/Desabilita botão "Próximo"
        const isLastPage = (currentPage >= totalPages);
        if (nextPageBtnTop) nextPageBtnTop.disabled = isLastPage;
        if (nextPageBtnBottom) nextPageBtnBottom.disabled = isLastPage;

        // Mostra/Esconde os controles se houver mais de uma página
        const shouldShowPagination = totalPages > 1;
        if (paginationControlsTop) {
             paginationControlsTop.style.display = shouldShowPagination ? 'flex' : 'none';
        }
        if (paginationControlsBottom) {
             paginationControlsBottom.style.display = shouldShowPagination ? 'flex' : 'none';
        }
    }

          // *MODIFICADO* Função para configurar os listeners e remover foco IMEDIATAMENTE
   function setupPaginationListeners() {
        // Botões de BAIXO (originais)
        const feedPrevPageBtnBottom = document.getElementById('feed-prev-page');
        const feedNextPageBtnBottom = document.getElementById('feed-next-page');
        // Botões de CIMA (novos)
        const feedPrevPageBtnTop = document.getElementById('feed-prev-page-top');
        const feedNextPageBtnTop = document.getElementById('feed-next-page-top');

         if(feedPrevPageBtnBottom && !feedPrevPageBtnBottom.dataset.listener) {
              feedPrevPageBtnBottom.addEventListener('click', (e) => {
                   e.currentTarget.blur(); // Tira o foco imediatamente
                   if (currentPage > 1) {
                        renderFeedPage(currentPage - 1);
                   }
              });
              feedPrevPageBtnBottom.dataset.listener = 'true';
         }
         if(feedNextPageBtnBottom && !feedNextPageBtnBottom.dataset.listener) {
              feedNextPageBtnBottom.addEventListener('click', (e) => {
                  e.currentTarget.blur(); // Tira o foco imediatamente
                  const totalPages = Math.ceil(allFeedItems.length / ITEMS_PER_PAGE);
                   if (currentPage < totalPages) {
                        renderFeedPage(currentPage + 1);
                   }
              });
               feedNextPageBtnBottom.dataset.listener = 'true';
         }

         // Adiciona listeners para os botões de CIMA
         if(feedPrevPageBtnTop && !feedPrevPageBtnTop.dataset.listener) {
              feedPrevPageBtnTop.addEventListener('click', (e) => {
                   e.currentTarget.blur(); // Tira o foco imediatamente
                   if (currentPage > 1) {
                        renderFeedPage(currentPage - 1);
                   }
              });
              feedPrevPageBtnTop.dataset.listener = 'true';
         }
         if(feedNextPageBtnTop && !feedNextPageBtnTop.dataset.listener) {
              feedNextPageBtnTop.addEventListener('click', (e) => {
                  e.currentTarget.blur(); // Tira o foco imediatamente
                  const totalPages = Math.ceil(allFeedItems.length / ITEMS_PER_PAGE);
                   if (currentPage < totalPages) {
                        renderFeedPage(currentPage + 1);
                   }
              });
               feedNextPageBtnTop.dataset.listener = 'true';
         }

         console.log("[SETUP] Pagination listeners attached for top and bottom (with immediate blur)."); // Log atualizado
    }

         // *MODIFICADO* - Renderiza UM item, mas não limpa mais o container
        function renderFeedItem(itemData, prepend = false) { // prepend não é mais usado aqui, a página controla
            if (!realtimeFeedContainer || !itemData?.id) return;
            // Não verifica mais duplicados aqui, pois a renderFeedPage limpa antes
            const itemHTML = createFeedItemHTML(itemData); if (!itemHTML) return;
            const tempDiv = document.createElement('div'); tempDiv.innerHTML = itemHTML.trim();
            const newItem = tempDiv.firstElementChild;
            if(newItem) {
                realtimeFeedContainer.appendChild(newItem); // Sempre adiciona ao final da página atual
            }
        }
        
        
        
       async function clearFeedEvents(eventId = null) {
    const isSingle = eventId !== null;
    const confirmMessage = isSingle 
        ? `Tem certeza que deseja remover este evento?` 
        : `Tem certeza que deseja remover TODOS os eventos do feed? Esta ação não pode ser desfeita.`;

    if (!confirm(confirmMessage)) return;

    try {
        const response = await fetch('/admin/api/clear_feed_events.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(isSingle ? { event_id: eventId } : {})
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Erro ao limpar eventos');
        }

        showToast(data.message, 'success');

        // Atualiza a UI - Verifica se os elementos existem antes de manipulá-los
        if (isSingle) {
            const itemElement = document.querySelector(`.feed-item[data-event-id="${eventId}"]`);
            if (itemElement) {
                itemElement.remove();
                
                // Verifica se há itens restantes
                const hasItems = document.querySelector('.feed-item') !== null;
                const noItemsMessage = document.getElementById('no-feed-items');
                if (noItemsMessage && !hasItems) {
                    noItemsMessage.style.display = 'block';
                }
            }
        } else {
            const feedContainer = document.getElementById('realtime-feed');
            if (feedContainer) {
                feedContainer.innerHTML = '';
                
                const noItemsMessage = document.getElementById('no-feed-items');
                if (noItemsMessage) {
                    noItemsMessage.style.display = 'block';
                }
            }
            lastEventId = 0; // Reseta o último ID
        }

    } catch (error) {
        console.error('Clear Feed Error:', error);
        showToast(`Erro: ${error.message}`, 'error');
    }
}



        // Cria o HTML para um item do feed (baseado na lógica PHP)
                 // Cria o HTML para um item do feed (baseado na lógica PHP)
         function createFeedItemHTML(item) {
            try { // Adiciona try...catch para robustez
                const userAvatar = escapeHTML(item.user_avatar || defaultAvatar);
                const userName = escapeHTML(item.user_nome || `Usuário #${item.user_id}`);
                // <<< ADIÇÃO: Log de Debug para verificar o ID recebido >>>
                console.log(`[createFeedItemHTML] Processing Event ID: ${item?.id}, User ID received from PHP: ${item?.user_id}`);
                const userId = parseInt(item.user_id || 0); // Tenta converter para número
                if (isNaN(userId)) { // <<< ADIÇÃO: Verificação extra de segurança >>>
                    console.warn(`[createFeedItemHTML] Invalid User ID for Event ${item?.id}:`, item?.user_id);
                    // Considera 0 se for inválido, para evitar erros no HTML, mas o clique não funcionará
                    // userId = 0; // Descomente se preferir definir como 0 em vez de NaN causar problemas
                }

                const eventTime = formatDateFull(item.created_at || '');
                const eventTimeFull = item.created_at ? new Date(item.created_at).toLocaleString('pt-BR') : '';
                // <<< MODIFICAÇÃO: Lógica correta para verificar se NÃO foi lido >>>
                // Trata is_read_by_admin como booleano ou numérico (0=lido se vier do DB como int)
                // Se for true (1) é LIDO, então isUnread deve ser FALSE.
                // Se for false (0) ou null/undefined, é NÃO LIDO, então isUnread deve ser TRUE.
                const isUnread = !(item.is_read_by_admin === true || item.is_read_by_admin === 1);

                const eventData = parseJsonSafe(item.event_data || '{}') || {}; // Garante que é objeto ou null
                const eventType = item.event_type || 'unknown';
                const currentStatus = item.status || null;

                const iconClass = getIconClass(eventType);
                const iconColorVar = getIconColorVariable(eventType);

                let description = '';
                let detailsHTML = '';
                let statusHTML = '';
                let actionsHTML = '';

                const statusesForThisType = allowedStatusesOptions[eventType] || [];

                // Lógica switch para detalhes (SEU CÓDIGO ORIGINAL MANTIDO)
                switch (eventType) {
                    case 'call_request':
                        const clienteNome = escapeHTML(eventData?.clienteNome || '');
                        const clienteNumero = escapeHTML(eventData?.clienteNumero || 'N/A');
                        const chamadaDuracao = escapeHTML(eventData?.chamadaDuracao || '?');
                        const chamadaData = eventData?.chamadaData ? formatDateFull(eventData.chamadaData) : 'N/A';
                        const chamadaObservacao = escapeHTML(eventData?.chamadaObservacao || '');
                        description = `<strong>${userName}</strong> solicitou chamada.`;
                        detailsHTML = `<p>📞 Cliente: <strong>${clienteNome || 'Sem nome'}</strong></p>`
                                     + `<p>📱 Número Cliente: <strong>${clienteNumero}</strong></p>`
                                     + `<p>⏰ Horário: <strong>${chamadaData}</strong> (${chamadaDuracao} min)</p>`
                                     + (chamadaObservacao ? `<p>📝 Obs: <i>${chamadaObservacao}</i></p>` : '');
                        statusHTML = createStatusDropdown(item.id, eventType, currentStatus, statusesForThisType);
                        break;
                    case 'proof_upload':
                        const savedFilename = escapeHTML(eventData?.saved_filename || '');
                        const originalFilename = escapeHTML(eventData?.original_filename || '');
                        const filesize = formatBytes(eventData?.filesize || 0);
                        const valorBruto = parseFloat(eventData?.valor_bruto || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                        const valorLiquido = parseFloat(eventData?.valor_liquido || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                        const descricao = escapeHTML(eventData?.descricao || '');
                        description = `<strong>${userName}</strong> enviou comprovante.`;
                        detailsHTML = `<p>📄 Arquivo: <code title="${originalFilename}">${savedFilename}</code> (${filesize})</p>`
                                     + `<p>💰 Valor: <strong>${valorBruto}</strong> (Líquido: ${valorLiquido})</p>`
                                     + (descricao ? `<p>📝 Desc: <i>${descricao}</i></p>` : '');
                        if (savedFilename) {
                            actionsHTML += `<button class="feed-action-btn" onclick="window.open('/uploads/comprovantes/${savedFilename}','_blank')"><i class="fas fa-receipt"></i> Ver Comprovante</button>`;
                        }
                        statusHTML = createStatusDropdown(item.id, eventType, currentStatus, statusesForThisType);
                        break;
                    case 'pix_payment_reported':
                        const pkgName = escapeHTML(eventData?.packageName || 'Pacote');
                        const pkgLeads = escapeHTML(eventData?.leads || '?');
                        const pkgPrice = parseFloat(eventData?.price || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL'});
                        const reportedAt = eventData?.reported_at ? formatDateFull(eventData.reported_at) : 'N/A';
                        description = `<strong>${userName}</strong> informou pagamento PIX.`;
                        detailsHTML = `<p><i class="fas fa-box-open"></i> Pacote: <strong>${pkgName} (${pkgLeads} leads)</strong></p>`
                                     + `<p>💰 Valor: <strong>${pkgPrice}</strong></p>`
                                     + `<p>🖱️ Clique Registrado: ${reportedAt}</p>`;
                        const currentStatusDisplay = statusLabels[currentStatus] || ucfirst(currentStatus?.replace(/_/g, ' ') || 'Pendente');
                        detailsHTML += `<p>📊 Status Atual: <strong>${currentStatusDisplay}</strong></p>`;
                        // Removido botão "Ver Comprovantes" daqui se ele não existe mais ou foi movido
                        // if (userId > 0) actionsHTML += `<button class="feed-action-btn" data-action="view-proofs" data-user-id="${userId}"><i class="fas fa-receipt"></i> Ver Comprovantes</button>`;
                        statusHTML = createStatusDropdown(item.id, eventType, currentStatus, statusesForThisType);
                        break;
                    case 'whatsapp_request':
                        description = `<strong>${userName}</strong> solicitou liberação de WhatsApp.`;
                        statusHTML = createStatusDropdown(item.id, eventType, currentStatus, statusesForThisType);
                        const waNum = escapeHTML(item.whatsapp_numero || eventData?.whatsapp_number || '');
                        if (currentStatus === 'approved' && waNum) { detailsHTML = `<p class="whatsapp-number-info" style='color: var(--success);'><i class="fab fa-whatsapp"></i> Nº Aprovado: <strong>${waNum}</strong></p>`; }
                        else if (currentStatus === 'processing') { detailsHTML = `<p class="whatsapp-status-info" style='color: var(--info);'><i class="fas fa-spinner fa-spin"></i> Processando...</p>`; }
                        else if (currentStatus === 'rejected') { detailsHTML = `<p class="whatsapp-status-info" style='color: var(--danger);'><i class="fas fa-times-circle"></i> Rejeitado.</p>`; }
                        else if (currentStatus === 'aguardando_resposta') { detailsHTML = `<p class="whatsapp-status-info" style='color: var(--info);'><i class="fas fa-clock"></i> Aguard. Resposta.</p>`; }
                        else { detailsHTML = `<p class="whatsapp-status-info" style='color: var(--warning);'><i class="fas fa-hourglass-half"></i> Pendente.</p>`; }
                        break;
                    case 'model_selected':
                        const modeloNome = escapeHTML(item.modelo_nome || eventData?.modelo_nome || 'N/A');
                        description = `<strong>${userName}</strong> escolheu a modelo IA.`;
                        detailsHTML = `<p><i class="fas fa-robot"></i> Modelo: <strong>${modeloNome}</strong></p>`;
                        break;
                    case 'profile_update':
                        const fieldsChanged = Array.isArray(eventData?.changes) ? eventData.changes.map(escapeHTML).join(', ') : 'Não especificado';
                        description = `<strong>${userName}</strong> atualizou o perfil.`;
                        detailsHTML = `<p><i class="fas fa-user-edit"></i> Campos: <strong>${fieldsChanged}</strong></p>`;
                        if (userId > 0) { actionsHTML += `<button class="feed-action-btn btn-details" data-action="details" data-user-id="${userId}"><i class="fas fa-user"></i> Ver Perfil</button>`; }
                        break;
                    case 'user_registered':
                        const userEmail = escapeHTML(eventData?.email || 'N/A');
                        description = `🎉 Novo usuário: <strong>${userName}</strong>.`;
                        detailsHTML = `<p><i class="fas fa-envelope"></i> Email: <strong>${userEmail}</strong></p>`;
                        if (userId > 0) { actionsHTML += `<button class="feed-action-btn btn-details" data-action="details" data-user-id="${userId}"><i class="fas fa-user"></i> Ver Perfil</button>`; }
                        break;
                    case 'user_login':
                        const ipAddress = escapeHTML(eventData?.ip_address || 'N/A');
                        description = `<strong>${userName}</strong> fez login.`;
                        detailsHTML = `<p><i class="fas fa-network-wired"></i> IP: <code>${ipAddress}</code></p>`;
                        break;
                    case 'user_logout':
                         const ipAddressLogout = escapeHTML(eventData?.ip_address || 'IP Desconhecido');
                         description = `<strong>${userName}</strong> saiu do sistema.`;
                         detailsHTML = `<p><i class="fas fa-network-wired"></i> IP: <code>${ipAddressLogout}</code></p>`;
                         if (userId > 0) { actionsHTML += `<button class="feed-action-btn btn-details" data-action="details" data-user-id="${userId}"><i class="fas fa-user"></i> Ver Perfil</button>`; }
                         break;
                    case 'lead_purchase':
                        const packageName = escapeHTML(eventData?.package_name || 'Pacote');
                        const price = parseFloat(eventData?.price || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL'});
                        description = `<strong>${userName}</strong> comprou leads.`;
                        detailsHTML = `<p><i class="fas fa-shopping-cart"></i> ${packageName}</p><p>💰 Valor: <strong>${price}</strong></p>`;
                        statusHTML = createStatusDropdown(item.id, eventType, currentStatus, statusesForThisType);
                        break;
                    default:
                        description = `Evento: <strong>${escapeHTML(ucfirst(eventType.replace(/_/g, ' ')))}</strong> por <strong>${userName}</strong>.`;
                        if (eventData && Object.keys(eventData).length > 0) { // Verifica se eventData não é vazio
                            const preview = escapeHTML(JSON.stringify(eventData)).substring(0, 100);
                            detailsHTML = `<p><i class="fas fa-code"></i> Dados: <code title='${escapeHTML(JSON.stringify(eventData))}'>${preview}...</code></p>`;
                        }
                }

                // Botão Notificar (SEU CÓDIGO ORIGINAL MANTIDO)
                if (userId > 0) { // USA userId PARSED
                    actionsHTML += `<button class="feed-action-btn btn-notify" data-action="notify" data-user-id="${userId}" data-user-name="${userName}" data-event-id="${item.id}"><i class="fas fa-bell"></i> Notificar</button>`;
                }

                // Botão Remover Item (SEU CÓDIGO ORIGINAL MANTIDO)
                actionsHTML += `<button class="feed-action-btn btn-delete" data-action="delete-feed-item" data-event-id="${item.id}" title="Remover este item"><i class="fas fa-times"></i> Remover</button>`;

                const avatarUrl = `/uploads/avatars/${userAvatar}?v=${Date.now()}`;
                const defaultAvatarUrl = `/uploads/avatars/${defaultAvatar}`;

                // Retorna o HTML montado
                // <<< MODIFICAÇÃO: Garante que data-user-id seja adicionado >>>
                return `
                    <div class="feed-item ${isUnread ? 'unread' : ''}" data-event-id="${item.id}" data-event-type="${eventType}">
                        <div class="feed-item-icon-area">
                             <span class="feed-item-type-icon" style="color: ${iconColorVar};" title="${escapeHTML(ucfirst(eventType.replace(/_/g, ' ')))}">
                                <i class="fas ${iconClass}"></i>
                             </span>
                            <!-- Container do Avatar com data-user-id e title -->
                            <div class="feed-item-avatar" data-user-id="${userId}" title="Ver detalhes de ${userName}" style="cursor: pointer;">
                                <img src="${avatarUrl}" alt="Avatar de ${userName}" onerror="this.onerror=null;this.src='${defaultAvatarUrl}';">
                            </div>
                        </div>
                        <div class="feed-item-content">
                            <div class="feed-item-header">
                                <span class="feed-item-user">${userName}</span>
                                <span class="feed-item-time" title="${eventTimeFull}">${eventTime}</span>
                            </div>
                            <div class="feed-item-description">${description}</div>
                            ${detailsHTML ? `<div class="feed-item-details">${detailsHTML}</div>` : ''}
                            ${statusHTML || actionsHTML ? `<div class="feed-item-actions">${statusHTML}${actionsHTML}</div>` : ''}
                        </div>
                    </div>`;
            } catch (e) {
                console.error(`Error creating feed item HTML for event ID ${item?.id}:`, e, item);
                return ''; // Retorna string vazia em caso de erro
            }
        }
        // Cria o HTML para o dropdown de status
        function createStatusDropdown(eventId, eventType, currentStatus, possibleStatuses) {
            if (!possibleStatuses || possibleStatuses.length === 0) return '';

            let optionsHTML = '';
            possibleStatuses.forEach(status => {
                const label = statusLabels[status] || ucfirst(status.replace(/_/g, ' '));
                optionsHTML += `<option value="${escapeHTML(status)}" ${status === currentStatus ? 'selected' : ''}>${escapeHTML(label)}</option>`;
            });

            return `
                <div class="feed-item-status">
                    <select class="form-control feed-status-select"
                            data-event-id="${eventId}"
                            data-event-type="${eventType}"
                            data-current-status="${currentStatus || ''}">
                        ${optionsHTML}
                    </select>
                </div>`;
        }

         function ucfirst(str) {
             if (!str) return '';
             return str.charAt(0).toUpperCase() + str.slice(1);
         }
         
         
         
           // ========== INÍCIO: FUNÇÕES RANK ==========
    async function loadRankData() {
        if (!rankTableBody || !rankLoadingMsg) { console.warn("Rank elements not found."); return; }
        console.log("Loading rank data...");
        rankLoadingMsg.style.display = 'table-row'; // Mostra loading
        rankTableBody.innerHTML = ''; // Limpa tabela
        try {
            const response = await fetch('/admin/api/get_rankings.php', { // Endpoint da API de Rank
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include'
            });
            if (!response.ok) throw new Error(`API Rank Error: ${response.status}`);
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to load rankings');
            renderRankTable(data.rankings); // Chama a função para renderizar
            console.log(`Rank data loaded: ${data.rankings?.length || 0} users`);
        } catch (error) {
            rankTableBody.innerHTML = `<tr><td colspan="4" style="color:var(--danger); text-align:center; padding:20px;">Erro ao carregar rank: ${escapeHTML(error.message)}</td></tr>`;
            console.error("Load rank error:", error);
            showToast("Erro ao carregar ranking.", "error");
        } finally {
            rankLoadingMsg.style.display = 'none'; // Esconde loading
        }
    }

   function renderRankTable(rankData) {
        const tableBody = rankTableBody; // Usa a variável global
        if (!tableBody) { console.error("Rank table body not found!"); return; }
        tableBody.innerHTML = ''; // Limpa antes de renderizar

        if (!rankData || rankData.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-secondary);"><i class="fas fa-list-ol fa-2x" style="margin-bottom: 10px;"></i><br>Ranking vazio.</td></tr>`;
            return;
        }

        rankData.forEach((user, index) => {
            try {
                const position = index + 1;
                let badgeClass = 'rank-badge-v2'; // Nova classe base
                let badgeIcon = '';
                let rankRowClass = ''; // Classe para a linha TR

                if (position === 1) {
                    badgeClass += ' gold';
                    badgeIcon = 'fas fa-trophy';
                    rankRowClass = 'rank-1'; // Classe para a linha #1
                } else if (position === 2) {
                    badgeClass += ' silver';
                    badgeIcon = 'fas fa-medal';
                    rankRowClass = 'rank-2'; // Classe para a linha #2
                } else if (position === 3) {
                    badgeClass += ' bronze';
                    badgeIcon = 'fas fa-award';
                    rankRowClass = 'rank-3'; // Classe para a linha #3
                }
                // Para posições > 3, não adiciona classe de cor/ícone específico

                const avatarSrc = `/uploads/avatars/${escapeHTML(user.avatar || defaultAvatar)}?v=${Date.now()}`;
                const userName = escapeHTML(user.nome);
                const totalEarnings = parseFloat(user.total_earnings || 0);
                const earningsFormatted = formatCurrency(totalEarnings);

                const row = document.createElement('tr');
                row.dataset.userId = user.id;
                if (rankRowClass) { // Adiciona a classe à linha se for Top 3
                    row.classList.add(rankRowClass);
                }

                row.innerHTML = `
                    <!-- Célula Posição/Badge com novo estilo e classe -->
                    <td class="rank-cell">
                        <span class="rank-position">${position}</span>
                        ${ position <= 3 ? `<span class="${badgeClass}" title="${badgeClass.split(' ')[1]}"><i class="${badgeIcon}"></i></span>` : '<span style="width: 36px;"></span>' /* Espaçador para alinhar */}
                    </td>
                    <!-- Célula Usuário -->
                    <td>
                        <div class="user-info-cell">
                            <img src="${avatarSrc}" alt="Avatar" class="user-avatar-sm" onerror="this.onerror=null;this.src='/uploads/avatars/${defaultAvatar}'">
                            <span class="user-name">${userName}</span>
                        </div>
                    </td>
                    <!-- Célula Ganhos -->
                    <td class="rank-earnings">
                        <span class="rank-value">${earningsFormatted}</span>
                        <input type="number" step="0.01" class="form-control rank-edit-input" style="display:none;" value="${totalEarnings.toFixed(2)}">
                    </td>
                    <!-- Célula Ações (mantida) -->
                    <td style="text-align:center;">
                         <div class="action-buttons" style="justify-content: center;"> <!-- Centraliza botões -->
                            <button class="btn btn-sm btn-edit rank-edit-btn" data-action="edit-rank" data-user-id="${user.id}" title="Editar Ganhos"><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn btn-sm btn-success rank-save-btn" data-action="save-rank" data-user-id="${user.id}" title="Salvar" style="display:none;"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-danger rank-cancel-btn" data-action="cancel-rank" data-user-id="${user.id}" title="Cancelar" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                    </td>`;
                tableBody.appendChild(row);
            } catch (e) {
                console.error("Error rendering rank row:", e, user);
                 const errorRow = document.createElement('tr');
                 errorRow.innerHTML = `<td colspan="4" style="color: var(--danger); font-style: italic;">Erro ao renderizar rank ID ${user?.id || '??'}</td>`;
                 tableBody.appendChild(errorRow);
            }
        });
    }

    function toggleRankEdit(userId, isEditing) {
        const row = rankTableBody?.querySelector(`tr[data-user-id="${userId}"]`);
        if (!row) return;
        const valueSpan = row.querySelector('.rank-value');
        const input = row.querySelector('.rank-edit-input');
        const editBtn = row.querySelector('.rank-edit-btn');
        const saveBtn = row.querySelector('.rank-save-btn');
        const cancelBtn = row.querySelector('.rank-cancel-btn');

        if (!valueSpan || !input || !editBtn || !saveBtn || !cancelBtn) return;

        valueSpan.style.display = isEditing ? 'none' : '';
        input.style.display = isEditing ? 'inline-block' : 'none';
        editBtn.style.display = isEditing ? 'none' : '';
        saveBtn.style.display = isEditing ? '' : 'none';
        cancelBtn.style.display = isEditing ? '' : 'none';

        row.classList.toggle('rank-editing', isEditing);

        if (isEditing) {
            input.focus();
            input.select();
        }
    }

    async function saveRankEdit(userId, buttonElement) {
        const row = rankTableBody?.querySelector(`tr[data-user-id="${userId}"]`);
        if (!row) return;
        const input = row.querySelector('.rank-edit-input');
        if (!input) return;

        const newEarnings = parseFloat(input.value);
        if (isNaN(newEarnings) || newEarnings < 0) {
            showToast("Valor de ganhos inválido.", "error");
            input.focus();
            return;
        }

        const originalHTML = buttonElement.dataset.originalHtml || buttonElement.innerHTML;
        try {
            await submitAjax(
                '/admin/api/update_ranking.php', // Endpoint para atualizar ranking
                { user_id: userId, new_earnings: newEarnings },
                buttonElement,
                'Salvando...',
                originalHTML,
                'Ganhos manuais atualizados!',
                () => {
                    const valueSpan = row.querySelector('.rank-value');
                    if (valueSpan) valueSpan.textContent = formatCurrency(newEarnings); // Atualiza valor na tela
                    toggleRankEdit(userId, false); // Volta para o modo visualização
                },
                'POST'
            );
        } catch (e) {
            // Erro já tratado pelo submitAjax
        }
    }
    // ========== FIM: FUNÇÕES RANK ==========

    // ========== INÍCIO: FUNÇÕES GERENCIAMENTO SALDOS ==========
    function setupBalanceManager() {
        // Verifica se os elementos essenciais existem
         if (!balanceDateDisplayInput || !balancePrevDayBtn || !balanceNextDayBtn || !balanceGoToYesterdayBtn || !balanceManagerTableBody) {
             console.error("Elementos essenciais do Gerenciador de Saldos não encontrados!");
             // Opcional: Desabilitar o item de menu ou mostrar uma mensagem
             const balanceMenuItem = document.querySelector('.menu-item[data-section="gerenciamento_saldos"]');
             if (balanceMenuItem) {
                 balanceMenuItem.style.opacity = '0.5';
                 balanceMenuItem.style.pointerEvents = 'none';
                 balanceMenuItem.title = "Erro: Componentes não encontrados.";
             }
             return; // Impede a continuação se algo estiver faltando
         }

        // Evita reinicialização
        if (flatpickrInstance) {
            console.log("Balance Manager já inicializado.");
            return;
        }

        console.log("Configurando Gerenciador de Saldos...");

        try {
             flatpickrInstance = flatpickr(balanceDateDisplayInput, {
                dateFormat: "Y-m-d", // Formato YYYY-MM-DD para consistência com backend/JS Date
                locale: "pt", // Usa localização em português (requer o script de l10n)
                defaultDate: new Date(new Date().setDate(new Date().getDate() - 1)), // Define data padrão como ONTEM
                maxDate: "today", // Não permite selecionar datas futuras
                onChange: function(selectedDates, dateStr, instance) {
                    // Chama loadBalanceForDate APENAS se a data realmente mudou
                    if (dateStr && dateStr !== currentBalanceDate) {
                         loadBalanceForDate(dateStr);
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    // Garante que a data inicial seja carregada na primeira vez
                    const initialDate = instance.formatDate(instance.selectedDates[0] || new Date(new Date().setDate(new Date().getDate() - 1)), "Y-m-d");
                     if (initialDate !== currentBalanceDate) {
                        loadBalanceForDate(initialDate);
                     }
                }
            });

            // Define a data inicial na variável global (ontem)
             const yesterday = new Date();
             yesterday.setDate(yesterday.getDate() - 1);
             currentBalanceDate = yesterday.toISOString().split('T')[0];

            // Atualiza o header da tabela com a data inicial formatada
            if (balanceTableDateHeader) {
                balanceTableDateHeader.textContent = formatDateForHeader(currentBalanceDate);
            }

            // Listeners para os botões de navegação
            balancePrevDayBtn.addEventListener('click', () => changeBalanceDate(-1));
            balanceNextDayBtn.addEventListener('click', () => changeBalanceDate(1));
            balanceGoToYesterdayBtn.addEventListener('click', () => {
                 const yesterdayStr = new Date(new Date().setDate(new Date().getDate() - 1)).toISOString().split('T')[0];
                 loadBalanceForDate(yesterdayStr);
            });

            // Listener para clique no botão "Marcar Pago/Desmarcar" (usando delegação)
            if (!balanceManagerTableBody.dataset.listenerAttached) { // Evita adicionar múltiplos listeners
                 balanceManagerTableBody.addEventListener('click', (e) => {
                    const button = e.target.closest('button[data-action="mark-paid"]');
                    if (button && !button.disabled) {
                        e.preventDefault(); // Previne qualquer ação padrão
                        e.stopPropagation(); // Impede que o clique se propague

                        const userId = button.dataset.userId;
                        const date = button.dataset.date;
                        const currentStatus = button.dataset.currentStatus === 'true'; // Status atual é pago?
                        const markAs = !currentStatus; // A nova ação é o oposto
                        const userName = button.dataset.userName || `Usuário #${userId}`;
                        const statusText = markAs ? 'PAGO' : 'NÃO PAGO'; // Texto para confirmação
                         const confirmBtnClass = markAs ? 'btn-success' : 'btn-warning'; // Classe do botão confirmar
                         const confirmTitle = `<i class="fas ${markAs ? 'fa-check-circle' : 'fa-times-circle'}"></i> Marcar Saldo ${statusText}`;
                         const confirmMessage = `Tem certeza que deseja marcar o saldo de <strong>${escapeHTML(userName)}</strong> referente a <strong>${formatDateForHeader(date)}</strong> como <strong>${statusText}</strong>?`;

                        // Abre o modal de confirmação genérico
                        openConfirmationModal(
                            confirmTitle,
                            confirmMessage,
                            `Sim, Marcar ${statusText}`, // Texto do botão confirmar
                            confirmBtnClass,
                            null, // Não passa userId aqui, pois ele vai no extraData
                            'mark-paid', // Ação a ser executada
                            { userId: userId, date: date, markAs: markAs, userName: userName } // Dados extras
                        );
                    }
                 });
                 balanceManagerTableBody.dataset.listenerAttached = 'true'; // Marca que o listener foi adicionado
             }

            // Carrega os dados da data inicial (ontem)
             loadBalanceForDate(currentBalanceDate);

        } catch (error) {
            console.error("Erro ao configurar Flatpickr ou listeners de saldo:", error);
             if (balanceManagerTableBody) {
                balanceManagerTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Erro ao inicializar gerenciador de saldos.</td></tr>`;
             }
             showToast("Erro crítico ao configurar gerenciador de saldos.", "error");
        }
    }

    function changeBalanceDate(daysToAdd) {
         if (!currentBalanceDate) {
             console.warn("changeBalanceDate: currentBalanceDate is not set.");
             return;
         }
        try {
             // Cria um objeto Date a partir da string YYYY-MM-DD, garantindo UTC para evitar problemas de fuso horário
            const dateParts = currentBalanceDate.split('-').map(Number);
             const currentDate = new Date(Date.UTC(dateParts[0], dateParts[1] - 1, dateParts[2])); // Mês é 0-indexado

             currentDate.setUTCDate(currentDate.getUTCDate() + daysToAdd); // Adiciona ou subtrai dias

             // Cria a data de hoje em UTC para comparação
            const today = new Date();
            const todayUTC = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));

            // Não permite navegar para uma data futura
             if (currentDate > todayUTC) {
                 console.log("Navegação para data futura bloqueada.");
                 showToast("Não é possível selecionar datas futuras.", "warning");
                 return;
             }

            // Formata a nova data de volta para YYYY-MM-DD
            const newDateStr = currentDate.toISOString().split('T')[0];

            loadBalanceForDate(newDateStr); // Carrega os dados para a nova data

        } catch (error) {
            console.error("Error changing balance date:", error);
            showToast("Erro ao mudar a data.", "error");
        }
    }

    async function loadBalanceForDate(dateStr) {
        // Validações iniciais
        if (!dateStr || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) { console.error("loadBalanceForDate: Data inválida ->", dateStr); return; }
        if (!balanceManagerTableBody || !balanceDateLoadingSpan || !balanceDateDisplayInput || !flatpickrInstance) { console.error("loadBalanceForDate: Elementos da UI ausentes."); return; }

        console.log(`Carregando saldos para a data: ${dateStr}`);
        currentBalanceDate = dateStr;

        // --- INÍCIO: Adiciona classe para iniciar o FADE OUT ---
        if (balanceManagerTableBody) {
            balanceManagerTableBody.classList.add('loading-content');
        }
        // --- FIM: Adiciona classe ---

        // Atualiza UI para estado de carregamento (Botões, Input, etc.)
        flatpickrInstance.setDate(dateStr, false);
        if (balanceTableDateHeader) balanceTableDateHeader.textContent = formatDateForHeader(dateStr);
        const todayStr = new Date().toISOString().split('T')[0];
        if (balanceNextDayBtn) balanceNextDayBtn.disabled = (dateStr >= todayStr);
        balanceDateLoadingSpan.style.display = 'inline';
        balancePrevDayBtn.disabled = true; balanceNextDayBtn.disabled = true; balanceGoToYesterdayBtn.disabled = true;
        if (balanceTotalSummaryDiv) balanceTotalSummaryDiv.style.display = 'none';

        // Pequeno delay para garantir que o fade out comece ANTES da busca (opcional, mas pode ajudar visualmente)
        await new Promise(resolve => setTimeout(resolve, 50)); // 50ms delay

        try {
            // Busca os dados da API
            const response = await fetch(`/admin/api/get_daily_balances.php?date=${dateStr}`, {
                 headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'include'
             });
             if (!response.ok) throw new Error(`Erro na API: ${response.status} ${response.statusText}`);
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Falha ao carregar saldos da API.');

            // Renderiza a tabela com os novos dados (AINDA com opacidade 0)
            renderBalanceManagerTable(data.balances || [], dateStr);
            calculateAndDisplayTotalToPay();

        } catch (error) {
            console.error("Erro ao carregar saldos:", error);
            // Renderiza mensagem de erro (AINDA com opacidade 0)
            if (balanceManagerTableBody) {
                balanceManagerTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Erro ao carregar: ${escapeHTML(error.message)}</td></tr>`;
            }
            showToast(`Erro ao buscar saldos para ${formatDateForHeader(dateStr)}.`, 'error');
        } finally {
            // Restaura UI (Botões, Loading Span)
            balanceDateLoadingSpan.style.display = 'none';
            balancePrevDayBtn.disabled = false;
            if (balanceNextDayBtn) balanceNextDayBtn.disabled = (dateStr >= todayStr);
            balanceGoToYesterdayBtn.disabled = false;

            // --- INÍCIO: Remove classe para iniciar o FADE IN ---
            // Usamos outro pequeno delay para garantir que o HTML foi trocado antes do fade in
            setTimeout(() => {
                 if (balanceManagerTableBody) {
                    balanceManagerTableBody.classList.remove('loading-content');
                 }
            }, 50); // 50ms delay
            // --- FIM: Remove classe ---
        }
    }
    function renderBalanceManagerTable(balances, date) {
        if (!balanceManagerTableBody) return;

        let tableHTML = '';
        let hasRowsToShow = false; // Flag para saber se algo foi renderizado

        balances.forEach(b => {
             const saldoDia = parseFloat(b.saldo_dia || 0);
             const isPaid = b.pago === true; // Verifica se está marcado como pago

            // Condição para mostrar a linha: Saldo > 0 OU já foi marcado como Pago
            if (saldoDia > 0 || isPaid) {
                hasRowsToShow = true; // Marca que temos pelo menos uma linha

                const saldoDiaFmt = formatCurrency(saldoDia);
                // Tenta pegar total_comprovantes_dia, se não existir, usa total_comprovantes
                const totalCompDia = parseFloat(b.total_comprovantes_dia ?? b.total_comprovantes ?? 0);
                const totalCompFmt = formatCurrency(totalCompDia);
                const avatarSrc = `/uploads/avatars/${escapeHTML(b.avatar || defaultAvatar)}?v=${Date.now()}`;
                const userName = escapeHTML(b.nome || `Usuário #${b.usuario_id}`);

                // Formatação da Chave PIX
                let pixDisplay = '<span style="color:var(--text-tertiary); font-style: italic;">Não Cadastrada</span>';
                if (b.chave_pix) {
                    const pixType = escapeHTML(b.tipo_chave_pix ? ucfirst(b.tipo_chave_pix) : '?');
                    const pixKey = escapeHTML(b.chave_pix);
                    pixDisplay = `<strong>${pixType}:</strong> <span class='pix-key' title="${pixKey}">${pixKey}</span>`; // Adiciona title para ver completa
                }

                // Classes CSS e textos/ícones do botão Pagar/Desmarcar
                const rowClass = (saldoDia > 0 && !isPaid) ? 'balance-unpaid' : (isPaid ? 'balance-paid' : '');
                const paidButtonText = isPaid ? 'Desmarcar' : 'Pagar';
                const paidButtonIcon = isPaid ? 'fa-times-circle' : 'fa-check-circle';
                const paidButtonClass = isPaid ? 'btn-warning' : 'btn-success'; // Laranja para desmarcar, verde para pagar
                const paidButtonTitle = isPaid ? `Marcar como NÃO Pago para ${userName} (${formatDateForHeader(date)})` : `Marcar como PAGO para ${userName} (${formatDateForHeader(date)})`;
                // Desabilita o botão de pagar/desmarcar se o saldo for 0 e ainda não estiver pago (não faz sentido pagar R$0)
                const isButtonDisabled = (saldoDia <= 0 && !isPaid);

                tableHTML += `
                    <tr data-user-id="${b.usuario_id}" data-date="${date}" class="${rowClass}">
                        <td>
                            <div class="user-info-cell">
                                <img src="${avatarSrc}" alt="Avatar" class="user-avatar-sm" onerror="this.onerror=null;this.src='/uploads/avatars/${defaultAvatar}'">
                                <span class="user-name">${userName}</span>
                            </div>
                        </td>
                        <td class="balance-value-cell" style="font-weight: ${saldoDia > 0 ? '600' : 'normal'}; color: ${saldoDia > 0 ? (isPaid ? 'var(--text-secondary)' : 'var(--success)') : 'inherit'};">
                            ${saldoDiaFmt}
                        </td>
                         <td class="balance-proof-cell">
                             ${totalCompFmt}
                        </td>
                        <td>${pixDisplay}</td>
                        <td style="text-align: center;">
                            <span class="badge ${isPaid ? 'badge-success' : 'badge-warning'}" style="cursor: help;" title="${isPaid ? 'Pagamento Registrado' : 'Pagamento Pendente'}">
                                <i class="fas ${isPaid ? 'fa-check' : 'fa-clock'}"></i> ${isPaid ? 'Sim' : 'Não'}
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn btn-sm ${paidButtonClass}"
                                    data-action="mark-paid"
                                    data-user-id="${b.usuario_id}"
                                    data-date="${date}"
                                    data-current-status="${isPaid}"
                                    data-user-name="${userName}"
                                    title="${paidButtonTitle}"
                                    ${isButtonDisabled ? 'disabled' : ''}>
                                <i class="fas ${paidButtonIcon}"></i> ${paidButtonText}
                            </button>
                        </td>
                    </tr>`;
             } // Fim if (saldoDia > 0 || isPaid)
        });

        // Se nenhuma linha foi adicionada, mostra a mensagem de vazio
        if (!hasRowsToShow) {
            // ----> LINHA CORRIGIDA <----
            balanceManagerTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);"><i class="fas fa-info-circle fa-lg" style="margin-bottom: 10px;"></i><br>Nenhum saldo encontrado para ${formatDateForHeader(date)}.</td></tr>`;
        } else {
            balanceManagerTableBody.innerHTML = tableHTML;
        }
    } // Fim da função renderBalanceManagerTable
    // Atualiza a aparência de uma linha da tabela de saldo após marcar/desmarcar
    function updateBalanceTableRowStatus(userId, date, newPaidStatus) {
         const row = balanceManagerTableBody?.querySelector(`tr[data-user-id="${userId}"][data-date="${date}"]`);
         if (!row) { console.warn(`Row not found for update: User ${userId}, Date ${date}`); return; }

         const isPaid = newPaidStatus === true;
         const paidBadge = row.querySelector('td:nth-child(5) .badge'); // Assume que é a 5ª coluna
         const paidButton = row.querySelector('td:nth-child(6) button[data-action="mark-paid"]'); // Assume que é a 6ª coluna
         const balanceCell = row.querySelector('.balance-value-cell'); // Célula do saldo
         const saldoValue = parseFloat(balanceCell?.textContent.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;

         // Atualiza o Badge (Sim/Não)
         if (paidBadge) {
             paidBadge.className = `badge ${isPaid ? 'badge-success' : 'badge-warning'}`;
             paidBadge.title = isPaid ? 'Pagamento Registrado' : 'Pagamento Pendente';
             paidBadge.innerHTML = `<i class="fas ${isPaid ? 'fa-check' : 'fa-clock'}"></i> ${isPaid ? 'Sim' : 'Não'}`;
         }

         // Atualiza o Botão (Pagar/Desmarcar)
         if (paidButton) {
             paidButton.innerHTML = `<i class="fas ${isPaid ? 'fa-times-circle' : 'fa-check-circle'}"></i> ${isPaid ? 'Desmarcar' : 'Pagar'}`;
             paidButton.className = `btn btn-sm ${isPaid ? 'btn-warning' : 'btn-success'}`;
             paidButton.title = isPaid ? `Marcar como NÃO Pago` : `Marcar como PAGO`;
             paidButton.dataset.currentStatus = isPaid.toString();
             // Reavalia se o botão deve ser desabilitado
             paidButton.disabled = (saldoValue <= 0 && !isPaid);
         }

          // Atualiza a cor do Saldo e a classe da linha
         if (balanceCell) {
             balanceCell.style.color = saldoValue > 0 ? (isPaid ? 'var(--text-secondary)' : 'var(--success)') : 'inherit';
         }
         row.classList.remove('balance-unpaid', 'balance-paid');

          // Condição para esconder a linha: Saldo é 0 E foi marcado como NÃO pago.
         if (saldoValue <= 0 && !isPaid) {
              row.style.display = 'none'; // Esconde a linha
         } else {
             row.style.display = ''; // Garante que está visível
              if (isPaid) { row.classList.add('balance-paid'); }
              else if (saldoValue > 0) { row.classList.add('balance-unpaid'); }
         }


         console.log(`UI Row Updated: User ${userId}, Date ${date}, Paid: ${isPaid}`);
     }

    // Calcula e exibe o total a pagar da data selecionada
    function calculateAndDisplayTotalToPay() {
         if (!balanceManagerTableBody || !balanceTotalSummaryDiv || !summaryDateSpan || !summaryTotalValueSpan) { console.warn("Elementos do resumo de total não encontrados."); return; }

         let totalToPay = 0;
         const rows = balanceManagerTableBody.querySelectorAll('tr[data-user-id]'); // Seleciona apenas linhas de usuário

         rows.forEach(row => {
             // Pula linhas que estão escondidas (saldo 0 e não pago)
             if (row.style.display === 'none') return;

             // Verifica se está marcado como pago pelo botão
             const isPaid = row.querySelector('button[data-action="mark-paid"]')?.dataset.currentStatus === 'true';
             // Pega o valor do saldo da célula
             const balanceCell = row.querySelector('.balance-value-cell');
             const saldo = parseFloat(balanceCell?.textContent.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;

             // Soma ao total apenas se o saldo for maior que zero E não estiver pago
             if (saldo > 0 && !isPaid) {
                 totalToPay += saldo;
             }
         });

         // Atualiza os elementos do resumo
         summaryDateSpan.textContent = formatDateForHeader(currentBalanceDate);
         summaryTotalValueSpan.textContent = formatCurrency(totalToPay);
         balanceTotalSummaryDiv.style.display = 'block'; // Mostra a div do resumo
    }

    // Atualiza a UI quando o saldo de hoje é zerado na tabela de usuários
    function resetUserBalanceUIUpdate(userId) {
        try {
            const userRowInUsersTable = document.querySelector(`#usersTable tr[data-user-id="${userId}"]`);
            const userRowInBalanceTable = document.querySelector(`#balanceManagerTableBody tr[data-user-id="${userId}"]`);
            const zeroReais = formatCurrency(0);

             const todayDateStr = new Date().toISOString().split('T')[0]; // Data de hoje YYYY-MM-DD

             // Função auxiliar para atualizar uma célula de saldo
             const updateCell = (cell) => {
                if (cell) {
                    cell.textContent = zeroReais;
                    cell.style.color = 'inherit'; // Remove cor verde
                    cell.style.fontWeight = 'normal'; // Remove negrito
                    // Efeito visual rápido
                    cell.style.transition = 'background-color 0.5s ease';
                    cell.style.backgroundColor = 'var(--warning-light)';
                    setTimeout(() => { if(cell) cell.style.backgroundColor = ''; }, 600);
                }
             };

            // Atualiza na tabela principal de usuários
            if (userRowInUsersTable) {
                updateCell(userRowInUsersTable.querySelector('.user-balance-cell'));
            }

            // Atualiza na tabela de saldos SOMENTE SE a data selecionada for HOJE
             if (userRowInBalanceTable && currentBalanceDate === todayDateStr) {
                 updateCell(userRowInBalanceTable.querySelector('.balance-value-cell'));

                 // Verifica se o botão "Marcar Pago" deve ser desabilitado (Saldo 0 e não pago)
                 const paidButton = userRowInBalanceTable.querySelector('button[data-action="mark-paid"]');
                 const isPaid = paidButton?.dataset.currentStatus === 'true';
                 if (paidButton && !isPaid) { // Se não está pago e o saldo agora é 0
                      paidButton.disabled = true;
                       // Potencialmente esconder a linha se o saldo zerou e não estava pago
                       userRowInBalanceTable.style.display = 'none';
                       calculateAndDisplayTotalToPay(); // Recalcula o total se uma linha foi escondida
                 }
             }

            showToast(`Saldo de hoje para Usuário #${userId} zerado na interface.`, 'info');

        } catch (error) {
            console.error("Erro ao atualizar UI do saldo zerado:", error);
        }
    }
    // ========== FIM: FUNÇÕES GERENCIAMENTO SALDOS ==========
    
    // --- *** NOVA FUNÇÃO: Marcar Item do Feed como Lido *** ---
async function markFeedItemAsRead(eventId, itemElement) {
    if (!eventId || !itemElement || !itemElement.classList.contains('unread')) return; // Só marca se não estiver lido

    // 1. Atualização Visual Imediata: Remove a classe que deixa rosa/destacado
    itemElement.classList.remove('unread');
    console.log(`Marcando evento ${eventId} como lido (UI atualizada). Chamando API...`);

    // 2. Chamada AJAX para o Backend
    try {
        // Certifique-se que o caminho '/admin/api/mark_feed_item_read.php' está correto!
        const response = await fetch('/admin/api/mark_feed_item_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json', // Indica que estamos enviando JSON
                'X-Requested-With': 'XMLHttpRequest', // Boa prática para APIs
                'Accept': 'application/json' // Indica que esperamos JSON de volta
            },
            credentials: 'include', // IMPORTANTE: Envia cookies da sessão (para autenticação)
            body: JSON.stringify({ event_id: parseInt(eventId) }) // Envia o ID do evento como JSON
        });

        // Tenta ler a resposta da API como JSON
        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            console.warn(`API mark_feed_item_read não retornou JSON válido para evento ${eventId}. Status: ${response.status}`);
            data = { success: false, message: `Resposta inválida do servidor (Status ${response.status})` };
        }

        // Verifica se a API respondeu com sucesso
        if (!response.ok || !data.success) {
            // Se falhou, registra um aviso no console
            console.warn(`Falha ao marcar evento ${eventId} como lido no backend:`, data.message || `Status ${response.status}`);
            // Opcional: Adicionar a classe 'unread' de volta? (Pode ser confuso)
            // itemElement.classList.add('unread');
        } else {
            // Se deu certo, registra no console
            console.log(`Evento ${eventId} marcado como lido no backend com sucesso (Já lido: ${data.already_read}).`);
        }
    } catch (error) {
        // Se ocorreu um erro de rede (não conseguiu conectar à API)
        console.error(`Erro de Rede ao marcar evento ${eventId} como lido:`, error);
        showToast(`Erro de rede ao marcar item #${eventId}.`, 'error');
        // Poderia tentar reverter a UI aqui também.
        // itemElement.classList.add('unread');
    }
}
// --- FIM da Função markFeedItemAsRead ---

        // Função MODIFICADA para chamar a lógica combinada
        function setupWhatsappFilters() {
            const filterButtons = document.querySelectorAll('#whatsapp_pending-section .filter-btn[data-status]');
            // Usa a variável global para o corpo da tabela, garantindo que ela exista
            const tableBody = pendingWATableBody;

            // Verificações iniciais (importante manter)
            if (!tableBody || filterButtons.length === 0) {
                console.warn("Elementos para filtro WhatsApp não encontrados.");
                return;
            }

            filterButtons.forEach(button => {
                // Evita adicionar o listener várias vezes (importante manter)
                if (button.dataset.listenerAttached) return;
                button.dataset.listenerAttached = 'true';

                button.addEventListener('click', function() {
                    // 1. Atualiza a aparência dos botões: marca o clicado como ativo
                    //    (Esta parte permanece igual)
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // *** 2. PONTO PRINCIPAL DA MUDANÇA: ***
                    //    Em vez de filtrar aqui mesmo, chamamos a função que
                    //    considera TANTO o filtro de status quanto a busca.
                    filterAndSearchWhatsappTable();

                    // NÃO precisamos mais da lógica antiga que escondia/mostrava
                    // linhas ou adicionava a mensagem "sem resultados" aqui,
                    // pois a 'filterAndSearchWhatsappTable' faz isso agora.
                });
            });

            // Mensagem no console indicando que a configuração (nova versão) foi feita
            console.log("Filtros WhatsApp configurados (v2 - usa lógica combinada com busca).");

            // Chama a função para atualizar os números nos badges dos botões (importante manter)
            updateFilterButtonCounts();
        }
// Função CORRIGIDA
function updateFilterButtonCounts() {
    const tableBody = document.querySelector('#pending-whatsapp-table tbody');
    const filterButtons = document.querySelectorAll('#whatsapp_pending-section .filter-btn[data-status]');

    if (!tableBody || filterButtons.length === 0) {
        // console.warn("Elementos para contagem de filtros WA não encontrados.");
        return;
    }

    const statusCounts = { all: 0, pending: 0, aguardando_resposta: 0, processing: 0, approved: 0, rejected: 0, unknown: 0 }; // Inclui unknown para segurança

    // 1. Conta os itens para cada status na tabela VISÍVEL ou INVISÍVEL
    //    *** SELETOR CORRIGIDO ABAIXO ***
    const rows = tableBody.querySelectorAll('tr[data-user-id]'); // <--- MUDANÇA AQUI

    rows.forEach(row => {
        const statusSelect = row.querySelector('.feed-status-select');
        // Lê o VALOR ATUAL do select, que reflete o estado visual
        const currentStatus = statusSelect ? statusSelect.value : 'unknown'; // Usa 'unknown' se não achar o select

        if (statusCounts.hasOwnProperty(currentStatus)) {
            statusCounts[currentStatus]++;
        } else {
             console.warn(`Status desconhecido encontrado na contagem: ${currentStatus}`);
             statusCounts.unknown++; // Conta status inesperados separadamente
        }
        statusCounts.all++; // Incrementa o total geral
    });

    // 2. Atualiza os badges nos botões
    filterButtons.forEach(button => {
        const status = button.dataset.status;
        const countBadge = button.querySelector('.filter-count-badge');
        if (countBadge && statusCounts.hasOwnProperty(status)) {
            const count = statusCounts[status];
            if (count > 0) {
                countBadge.textContent = count > 99 ? '99+' : count.toString(); // Limita a 99+ se for muito grande
                countBadge.classList.add('show'); // Mostra o badge
            } else {
                countBadge.textContent = '';
                countBadge.classList.remove('show'); // Esconde o badge
            }
        }
    });
     console.log('Filter button counts updated:', statusCounts);
}

 function getRankBadgeHTML(position) {
     if (position === null || position > 3 || position < 1) {
         return ''; // Sem badge para ranks > 3 ou inválidos
     }
     let badgeClass = 'rank-badge-v2';
     let iconClass = '';
     let title = '';
     if (position === 1) { badgeClass += ' gold'; iconClass = 'fas fa-trophy'; title = 'Ouro (Rank #1)'; }
     else if (position === 2) { badgeClass += ' silver'; iconClass = 'fas fa-medal'; title = 'Prata (Rank #2)'; }
     else if (position === 3) { badgeClass += ' bronze'; iconClass = 'fas fa-award'; title = 'Bronze (Rank #3)'; }

     return `<span class="${badgeClass}" title="${title}" style="width: 24px; height: 24px; font-size: 0.9rem; vertical-align: middle; margin-left: 8px;"><i class="${iconClass}"></i></span>`;
 }
 
 
 function setupWhatsappSearch() {
            console.log("Setting up user search for #pending-whatsapp-table...");
            const searchInput = whatsappSearchInput; // Usa o seletor global
            const tableBody = pendingWATableBody; // Usa o seletor global

            if (!searchInput || !tableBody) {
                console.warn("Search input or WhatsApp table body (#pending-whatsapp-table) not found.");
                return;
            }
             // Evita adicionar listener múltiplo
             if(searchInput.dataset.listenerAttached) return;
             searchInput.dataset.listenerAttached = 'true';

            // No evento de digitar (input)
            searchInput.addEventListener('input', function() {
                // Apenas chama a função combinada, ela pegará o termo da busca e o filtro ativo
                filterAndSearchWhatsappTable();
            });
            console.log("WhatsApp search for #pending-whatsapp-table setup complete (v2 - combined filter logic).");
        }


function filterAndSearchWhatsappTable() {
            const tableBody = pendingWATableBody;
            if (!tableBody) {
                console.warn("WhatsApp table body not found for combined filtering.");
                return;
            }

            const searchInput = whatsappSearchInput;
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

            const activeFilterButton = document.querySelector('#whatsapp_pending-section .filter-btn.active');
            const currentStatusFilter = activeFilterButton ? activeFilterButton.dataset.status : 'all';

            // Define as mensagens de "sem resultados"
            const noFilterResultsRowHTML = `<td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);"><i class="fas fa-filter fa-lg" style="margin-bottom:10px;"></i><br>Nenhuma solicitação encontrada com este status/nome.</td>`;
            const noSearchOnlyResultsRowHTML = `<td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);"><i class="fas fa-search-minus fa-lg" style="margin-bottom:10px;"></i><br>Nenhum usuário encontrado com este nome.</td>`;
            const initialEmptyMsgHTML = `<td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);"><i class="fas fa-check-circle fa-lg"></i> Nenhuma solicitação encontrada.</td>`; // Mensagem padrão inicial

            const rows = tableBody.querySelectorAll('tr[data-user-name]'); // Linhas com nome
            let visibleRowCount = 0;

            // Itera pelas linhas para mostrar/esconder
            rows.forEach(row => {
                const statusSelect = row.querySelector('.feed-status-select');
                const rowStatus = statusSelect ? statusSelect.value : 'unknown';
                const userName = row.dataset.userName || '';

                const matchesStatusFilter = (currentStatusFilter === 'all' || rowStatus === currentStatusFilter);
                const matchesSearchTerm = (searchTerm === '' || userName.includes(searchTerm));

                if (matchesStatusFilter && matchesSearchTerm) {
                    row.style.display = ''; // Mostra
                    visibleRowCount++;
                } else {
                    row.style.display = 'none'; // Esconde
                }
            });

             // Remove mensagens de "sem resultados" anteriores
             tableBody.querySelectorAll('.no-results-row-wa').forEach(el => el.remove());

             // Adiciona a mensagem apropriada se necessário
             if (visibleRowCount === 0 && rows.length > 0) { // A tabela tinha linhas, mas nada correspondeu
                 const tempRow = document.createElement('tr');
                 tempRow.className = 'no-results-row-wa'; // Classe genérica para WA
                 // Escolhe a mensagem mais apropriada
                 if (searchTerm !== '' && currentStatusFilter !== 'all') {
                      tempRow.innerHTML = noFilterResultsRowHTML; // Nenhum com status E nome
                 } else if (searchTerm !== '') {
                      tempRow.innerHTML = noSearchOnlyResultsRowHTML; // Nenhum com o nome (status era 'all')
                 } else {
                      // Se a busca está vazia, mas o filtro de status não encontrou nada
                      tempRow.innerHTML = noFilterResultsRowHTML.replace('/nome', ''); // Remove a parte do nome da msg
                 }
                  tableBody.appendChild(tempRow);
             } else if (rows.length === 0 && !tableBody.querySelector('td[colspan="5"]')) {
                 // Se a tabela está COMPLETAMENTE vazia (nem a linha inicial do PHP existe)
                  const tempRow = document.createElement('tr');
                 tempRow.className = 'no-results-row-wa';
                 tempRow.innerHTML = initialEmptyMsgHTML; // Mensagem inicial padrão
                 tableBody.appendChild(tempRow);
             }
        }

        // --- Inicializa o Dashboard ---
        initializeDashboard();

    }); // Fim DOMContentLoaded
    //]]>
    </script>
</body>
</html>