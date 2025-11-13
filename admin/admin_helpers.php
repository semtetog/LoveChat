<?php
// Define o fuso horário padrão para São Paulo (IMPORTANTE!)
// Faça isso no script principal que inclui este helper
// date_default_timezone_set('America/Sao_Paulo');

// Define um nome de arquivo de avatar padrão se não estiver definido
defined('DEFAULT_AVATAR') or define('DEFAULT_AVATAR', 'default.jpg');

// Mapeamento de nomes amigáveis para status
$statusLabelsPHP = [
    'pending' => 'Pendente',
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
    // Status da tabela pagamentos_pendentes
    'pendente' => 'Pendente',
    'pago' => 'Pago',
    'erro' => 'Erro',
];

// Opções de status permitidas para cada tipo de evento (para dropdowns do feed)
$allowedStatusesOptionsPHP = [
    'call_request' => ['pending', 'in_progress', 'completed', 'cancelled'],
    'whatsapp_request' => ['pending', 'processing', 'approved', 'rejected'],
    'proof_upload' => ['pending_review', 'approved', 'rejected'],
    'lead_purchase' => ['pending', 'paid', 'cancelled'],
    'admin_notification' => ['sent', 'read'],
];


// --- Funções Auxiliares ---

if (!function_exists('escapeHTML')) {
    function escapeHTML($str) {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('parseJsonSafe')) {
     function parseJsonSafe($jsonString) {
         if ($jsonString === null || $jsonString === '') return null;
         try {
             $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
             if (!is_array($data)) {
                  error_log("JSON Parse Warning: Decoded data is not an array. Type: " . gettype($data) . " | Data Preview: " . substr($jsonString, 0, 100));
                  return null;
              }
             return $data;
         } catch (JsonException $e) {
             error_log("JSON Parse Error: " . $e->getMessage() . " | Data Preview: " . substr($jsonString, 0, 100));
             return null;
         } catch (Throwable $t) {
             error_log("JSON Parse General Error: " . $t->getMessage() . " | Data Preview: " . substr($jsonString, 0, 100));
             return null;
         }
     }
}

if (!function_exists('formatDateFullPHP')) {
    function formatDateFullPHP($dateString) {
        if (empty($dateString)) return 'N/A';
        try {
            $date = new DateTimeImmutable($dateString);
            $timezone = new DateTimeZone(date_default_timezone_get()); // Usa o timezone padrão definido
            $now = new DateTimeImmutable('now', $timezone);
            $date = $date->setTimezone($timezone);

            $today_start = $now->setTime(0, 0, 0);
            $yesterday_start = $today_start->modify('-1 day');

            if ($date->format('Y-m-d') === $today_start->format('Y-m-d')) {
                return 'Hoje às ' . $date->format('H:i');
            } elseif ($date->format('Y-m-d') === $yesterday_start->format('Y-m-d')) {
                return 'Ontem às ' . $date->format('H:i');
            } else {
                if ($date->format('Y') === $now->format('Y')) {
                    return $date->format('d/m H:i');
                } else {
                    return $date->format('d/m/y H:i');
                }
            }
        } catch(Exception $e) {
            error_log("Error formatting date PHP: {$dateString} - " . $e->getMessage());
            return 'Data Inválida';
        }
    }
}

if (!function_exists('formatBytesPHP')) {
    function formatBytesPHP($bytes, $decimals = 1) {
        $bytes = filter_var($bytes, FILTER_VALIDATE_FLOAT);
        if ($bytes === false || $bytes <= 0) return '0 Bytes';
        $k = 1024;
        $dm = max(0, (int)$decimals);
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $i = min(count($sizes) - 1, floor(log($bytes) / log($k)));
        return sprintf("%.{$dm}f", $bytes / pow($k, $i)) . ' ' . $sizes[$i];
    }
}

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
            case 'user_logout': return 'fa-sign-out-alt';
            case 'lead_purchase': return 'fa-shopping-cart';
            case 'admin_notification': return 'fa-bell';
            case 'pix_payment_reported': return 'fa-money-check-alt';
            default: return 'fa-info-circle';
        }
    }
}

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
            case 'user_logout': return 'var(--danger)';
            case 'lead_purchase': return 'var(--info)';
            case 'admin_notification': return 'var(--primary)';
            case 'pix_payment_reported': return 'fa-money-check-alt'; // Note: This might be a class, not a color var. Let's keep it simple or define a color.
             default: return 'var(--text-secondary)';
         }
     }
}

if (!function_exists('createStatusDropdownPHP')) {
    function createStatusDropdownPHP($eventId, $eventType, $currentStatus, $allowedStatusesOptions) {
        global $statusLabelsPHP;
        $possibleStatuses = $allowedStatusesOptions[$eventType] ?? [];
        if (empty($possibleStatuses)) return '';

        $optionsHTML = '';
        foreach ($possibleStatuses as $status) {
            $label = $statusLabelsPHP[$status] ?? ucfirst(str_replace('_', ' ', $status));
            $selected = ($status === $currentStatus) ? 'selected' : '';
            $optionsHTML .= "<option value=\"" . escapeHTML($status) . "\" $selected>" . escapeHTML($label) . "</option>";
        }

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

?>