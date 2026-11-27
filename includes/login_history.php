<?php
/**
 * @date_cre: Fri 2026-11-27 15:31:44
 * @date_mut: Fri 2026-11-27
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Records company-scoped successful sign-ins and explicit sign-outs.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:login_history
 */
declare(strict_types=1);

/**
 * Record a successful login or explicit logout event without interrupting authentication.
 *
 * @param string $eventKey Event key, either login or logout.
 * @param ?int $companyId Company identifier, or null for platform-only access.
 * @param string $userCode User identifier.
 * @param bool $platformOperator Whether the event was recorded in platform-operator context.
 *
 * @return void
 */
function grompil_login_history_record(string $eventKey, ?int $companyId, string $userCode, bool $platformOperator = false): void
{
    if (!in_array($eventKey, ['login', 'logout'], true) || $userCode === '') return;
    $companyKey = $companyId === null ? 'platform' : (string) $companyId;
    $sessions = $_SESSION['login_history_sessions'] ?? [];
    if (!is_array($sessions)) $sessions = [];
    $auditSessionId = (string) ($sessions[$companyKey] ?? '');
    try {
        if ($eventKey === 'login' || !preg_match('/^[a-f0-9-]{36}$/iD', $auditSessionId)) {
            $uuidHex = bin2hex(random_bytes(16));
            $uuidHex[12] = '4';
            $uuidHex[16] = dechex((hexdec($uuidHex[16]) & 0x3) | 0x8);
            $auditSessionId = sprintf(
                '%s-%s-%s-%s-%s',
                substr($uuidHex, 0, 8),
                substr($uuidHex, 8, 4),
                substr($uuidHex, 12, 4),
                substr($uuidHex, 16, 4),
                substr($uuidHex, 20, 12)
            );
            $sessions[$companyKey] = $auditSessionId;
        }
        set_database_tenant_context($companyId ?? 0, $userCode, $platformOperator || $companyId === null);
        $ipAddress = grompil_client_ip_address();
        if ($ipAddress === '') $ipAddress = 'unknown';
        db_query_params(
            'login_history.record_event',
            'INSERT INTO st_login_history (company_id, user_code, session_audit_id, event_key, ip_address)
             VALUES ($1, $2, $3::UUID, $4, $5)',
            [$companyId, $userCode, $auditSessionId, $eventKey, $ipAddress]
        );
        $_SESSION['login_history_sessions'] = $sessions;
    } catch (Throwable $error) {
        error_log('Login history event could not be recorded: ' . get_class($error));
    }
}
