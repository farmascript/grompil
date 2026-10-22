<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages the platform-wide URL manager layout.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function platform_navigation_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$user = current_user();
$isPlatformOperator = $user !== null && is_platform_operator($user);
$application = trim((string) ($_GET['application'] ?? ''));
if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $application)) {
    platform_navigation_response(400, ['error' => 'invalid_application']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = @pg_query_params(
        db(),
        'SELECT configuration, updated_at FROM platform_navigation_preferences WHERE application = $1',
        [$application]
    );
    if (!$result) {
        platform_navigation_response(503, ['error' => 'preferences_unavailable']);
    }
    $row = pg_fetch_assoc($result);
    $company = $user !== null ? current_company_context($user) : null;
    $userName = $user === null
        ? ''
        : (trim((string) ($user['display_name'] ?? '')) ?: (string) ($user['email'] ?? ''));
    $userRole = $user === null
        ? ''
        : ($isPlatformOperator
            ? 'Platformbeheerder'
            : (string) ($company['company_role_name'] ?? $user['role_name'] ?? ''));
    platform_navigation_response(200, [
        'isPlatformOperator' => $isPlatformOperator,
        'phpVersion' => $user !== null ? PHP_VERSION : null,
        'userName' => $userName,
        'userRole' => $userRole,
        'companyName' => (string) ($company['name'] ?? ''),
        'csrfToken' => $isPlatformOperator ? csrf_token() : '',
        'configuration' => $row ? json_decode((string) $row['configuration'], true) : null,
        'updatedAt' => $row ? (string) $row['updated_at'] : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    header('Allow: GET, PUT');
    platform_navigation_response(405, ['error' => 'method_not_allowed']);
}
if (!$isPlatformOperator) {
    platform_navigation_response(403, ['error' => 'platform_admin_required']);
}

$body = file_get_contents('php://input');
$payload = $body === false ? null : json_decode($body, true);
if ($body === false || strlen($body) > 524288) {
    platform_navigation_response(413, ['error' => 'payload_too_large']);
}
if (!is_array($payload)
    || !is_string($payload['csrfToken'] ?? null)
    || !hash_equals(csrf_token(), $payload['csrfToken'])
    || !is_array($payload['configuration'] ?? null)
    || !is_array($payload['configuration']['groups'] ?? null)
    || !is_array($payload['configuration']['assignments'] ?? null)
    || (isset($payload['configuration']['groupColors']) && !is_array($payload['configuration']['groupColors']))
    || (isset($payload['configuration']['groupTitles']) && !is_array($payload['configuration']['groupTitles']))
    || (isset($payload['configuration']['groupOrder']) && !is_array($payload['configuration']['groupOrder']))) {
    platform_navigation_response(400, ['error' => 'invalid_configuration']);
}

$configuration = $payload['configuration'];
if (count($configuration['groups']) > 200 || count($configuration['assignments']) > 10000
    || count($configuration['groupColors'] ?? []) > 200
    || count($configuration['groupTitles'] ?? []) > 200
    || count($configuration['groupOrder'] ?? []) > 400) {
    platform_navigation_response(413, ['error' => 'configuration_too_large']);
}
foreach ($configuration['groups'] as $group) {
    if (!is_array($group)
        || !is_string($group['id'] ?? null)
        || !preg_match('/^platform:[a-zA-Z0-9-]{1,80}$/', $group['id'])
        || !is_string($group['title'] ?? null)
        || trim($group['title']) === ''
        || strlen($group['title']) > 180
        || (isset($group['titles']) && !is_array($group['titles']))) {
        platform_navigation_response(400, ['error' => 'invalid_group']);
    }
    foreach ($group['titles'] ?? [] as $language => $translatedTitle) {
        if (!is_string($language) || !preg_match('/^[a-z]{2,8}(?:-[a-z0-9]{2,8})?$/i', $language)
            || !is_string($translatedTitle) || trim($translatedTitle) === '' || strlen($translatedTitle) > 180) {
            platform_navigation_response(400, ['error' => 'invalid_group_title']);
        }
    }
}
foreach ($configuration['assignments'] as $item => $groupId) {
    if (!is_string($item) || !preg_match('/^[a-z0-9._-]{1,80}:\d{1,12}$/', $item)
        || !is_string($groupId) || (!preg_match('/^catalog:[a-zA-Z0-9._-]{1,80}$/', $groupId)
            && !preg_match('/^platform:[a-zA-Z0-9-]{1,80}$/', $groupId))) {
        platform_navigation_response(400, ['error' => 'invalid_assignment']);
    }
}
foreach ($configuration['groupColors'] ?? [] as $groupId => $color) {
    if (!is_string($groupId) || !preg_match('/^catalog:[a-zA-Z0-9._-]{1,80}$/', $groupId)
        || !is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        platform_navigation_response(400, ['error' => 'invalid_group_color']);
    }
}
foreach ($configuration['groupTitles'] ?? [] as $groupId => $title) {
    if (!is_string($groupId) || !preg_match('/^catalog:[a-zA-Z0-9._-]{1,80}$/', $groupId)
        || !is_array($title) || $title === []) {
        platform_navigation_response(400, ['error' => 'invalid_group_title']);
    }
    foreach ($title as $language => $translatedTitle) {
        if (!is_string($language) || !preg_match('/^[a-z]{2,8}(?:-[a-z0-9]{2,8})?$/i', $language)
            || !is_string($translatedTitle) || trim($translatedTitle) === '' || strlen($translatedTitle) > 180) {
            platform_navigation_response(400, ['error' => 'invalid_group_title']);
        }
    }
}
$groupOrderSeen = [];
foreach ($configuration['groupOrder'] ?? [] as $groupId) {
    if ($groupId === null) {
        continue;
    }
    if (!is_string($groupId)
        || (!preg_match('/^catalog:[a-zA-Z0-9._-]{1,80}$/', $groupId)
            && !preg_match('/^platform:[a-zA-Z0-9-]{1,80}$/', $groupId))
        || isset($groupOrderSeen[$groupId])) {
        platform_navigation_response(400, ['error' => 'invalid_group_order']);
    }
    $groupOrderSeen[$groupId] = true;
}

$json = json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
$result = @pg_query_params(
    db(),
    'INSERT INTO platform_navigation_preferences (application, configuration, updated_at)
     VALUES ($1, $2::jsonb, now())
     ON CONFLICT (application) DO UPDATE
        SET configuration = EXCLUDED.configuration, updated_at = now()
     RETURNING updated_at',
    [$application, $json]
);
if (!$result) {
    platform_navigation_response(503, ['error' => 'preferences_unavailable']);
}
$saved = pg_fetch_assoc($result);
platform_navigation_response(200, ['saved' => true, 'updatedAt' => (string) ($saved['updated_at'] ?? '')]);

