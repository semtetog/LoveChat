<?php
// includes/audit_logger.php

/**
 * Registra uma ação administrativa no log de auditoria (VERSÃO ADAPTADA).
 * Esta função deve ser chamada após uma ação administrativa ser concluída com sucesso em suas APIs.
 */
if (!function_exists('logAdminAction')) {
    /**
     * @param PDO $pdo Objeto de conexão PDO (vem do seu includes/db.php).
     * @param int $adminId ID do administrador que realizou a ação (geralmente $_SESSION['user_id']).
     * @param string $action Nome/tipo da ação (ex: 'user_update', 'status_change', 'balance_paid'). Corresponde à coluna `action` da sua tabela.
     * @param ?string $targetType Tipo do alvo/recurso afetado (ex: 'user', 'event', 'balance', 'ranking', 'feed'). Corresponde à coluna `target_type`.
     * @param ?string $targetId ID do recurso afetado (ID do usuário, ID do evento, data do saldo, etc.). Corresponde à coluna `target_id`.
     * @param ?array $details Detalhes adicionais em formato de array (ex: ['campo_alterado' => 'email', 'valor_novo' => '...', 'valor_antigo' => '...']). Será salvo como JSON na coluna `details`.
     * @return bool Retorna true se o log foi inserido com sucesso, false em caso de erro.
     */
    function logAdminAction(PDO $pdo, int $adminId, string $action, ?string $targetType = null, ?string $targetId = null, ?array $details = null): bool {
        // Pega o endereço IP do admin que fez a ação
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        // Converte o array de detalhes para JSON (ou null se não houver detalhes)
        // JSON_UNESCAPED_UNICODE preserva caracteres acentuados. JSON_PRETTY_PRINT formata para facilitar leitura no banco.
        $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null;

        // SQL que corresponde EXATAMENTE às colunas da SUA tabela admin_audit_log
        $sql = "INSERT INTO admin_audit_log
                    (admin_user_id, action, target_type, target_id, details, ip_address)
                VALUES
                    (:admin_id, :action, :target_type, :target_id, :details, :ip)";

        try {
            $stmt = $pdo->prepare($sql);
            // Associa os valores aos placeholders no SQL
            $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, PDO::PARAM_STR);       // Coluna `action`
            $stmt->bindParam(':target_type', $targetType, PDO::PARAM_STR); // Coluna `target_type`
            $stmt->bindParam(':target_id', $targetId, PDO::PARAM_STR);   // Coluna `target_id`
            $stmt->bindParam(':details', $detailsJson, PDO::PARAM_STR); // Coluna `details` (JSON)
            $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);       // Coluna `ip_address`
            // A coluna `timestamp` é preenchida automaticamente pelo MySQL (DEFAULT CURRENT_TIMESTAMP)

            // Executa a inserção
            return $stmt->execute();
        } catch (PDOException $e) {
            // Se der erro ao salvar o log, registra o erro no log de erros do PHP,
            // mas NÃO interrompe o fluxo principal da API (a ação original já aconteceu).
            error_log("Audit Log Error (Adapted): Failed to log action '$action' by admin $adminId. TargetType: $targetType, TargetId: $targetId. Error: " . $e->getMessage());
            return false; // Retorna false para indicar que o log falhou
        }
    }
}
?>