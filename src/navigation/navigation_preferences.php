<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:49
 * @date_mut: Wed 2026-10-21 16:50:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages the company-specific URL manager layout.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

/**
 * Return a JSON response from company navigation preferences.
 *
 * @param int $status The HTTP status code.
 * @param array $data The response payload.
 * @return never
 */
function navigation_preferences_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$user = current_user();
if ($user === null) {
    navigation_preferences_response(401, ['authenticated' => false]);
}
$company = current_company_context($user);
if ($company === null) {
    navigation_preferences_response(409, ['error' => 'company_context_required']);
}
$canEdit = is_platform_operator($user)
    || ((int) ($company['company_role_level'] ?? 200) > 0
        && (int) ($company['company_role_level'] ?? 200) < 100
        && has_company_permission('company.settings.manage', $company, $user));

$application = trim((string) ($_GET['application'] ?? ''));
if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $application)) {
    navigation_preferences_response(400, ['error' => 'invalid_application']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = @pg_query_params(
        db(),
        'SELECT configuration, updated_at
         FROM company_navigation_preferences
         WHERE company_id = $1 AND application = $2',
        [(int) $company['id'], $application]
    );
    if (!$result) {
        navigation_preferences_response(503, ['error' => 'preferences_unavailable']);
    }
    $row = pg_fetch_assoc($result);
    navigation_preferences_response(200, [
        'authenticated' => true,
        'canEdit' => $canEdit,
        'companyName' => (string) $company['name'],
        'companyId' => (int) $company['id'],
        'csrfToken' => csrf_token(),
        'configuration' => $row ? json_decode((string) $row['configuration'], true) : null,
        'updatedAt' => $row ? (string) $row['updated_at'] : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    header('Allow: GET, PUT');
    navigation_preferences_response(405, ['error' => 'method_not_allowed']);
}
if (!$canEdit) {
    navigation_preferences_response(403, ['error' => 'admin_required']);
}

$body = file_get_contents('php://input');
if ($body === false || strlen($body) > 524288) {
    navigation_preferences_response(413, ['error' => 'payload_too_large']);
}
$payload = json_decode($body, true);
if (!is_array($payload)
    || !is_string($payload['csrfToken'] ?? null)
    || !hash_equals(csrf_token(), $payload['csrfToken'])
    || !is_array($payload['configuration'] ?? null)) {
    navigation_preferences_response(400, ['error' => 'invalid_request']);
}

$configuration = $payload['configuration'];
foreach (['favorites', 'hidden', 'groups', 'links', 'pages'] as $key) {
    if (isset($configuration[$key]) && !is_array($configuration[$key])) {
        navigation_preferences_response(400, ['error' => 'invalid_configuration']);
    }
}
foreach (['overrides', 'assignments', 'settings'] as $key) {
    if (isset($configuration[$key]) && !is_array($configuration[$key])) {
        navigation_preferences_response(400, ['error' => 'invalid_configuration']);
    }
}

$json = json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
$result = @pg_query_params(
    db(),
    'INSERT INTO company_navigation_preferences (company_id, application, configuration, updated_at)
     VALUES ($1, $2, $3::jsonb, now())
     ON CONFLICT (company_id, application) DO UPDATE
        SET configuration = EXCLUDED.configuration, updated_at = now()
     RETURNING updated_at',
    [(int) $company['id'], $application, $json]
);
if (!$result) {
    navigation_preferences_response(503, ['error' => 'preferences_unavailable']);
}
$saved = pg_fetch_assoc($result);
navigation_preferences_response(200, [
    'saved' => true,
    'updatedAt' => (string) ($saved['updated_at'] ?? ''),
]);

