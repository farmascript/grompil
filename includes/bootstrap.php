<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:34:29
 * @date_mut: Thu 2026-11-12 12:02:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Initializes sessions, database access, authentication, and access checks.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:bootstrap
 */

declare(strict_types=1);

require_once __DIR__ . '/database_config.php';
require_once __DIR__ . '/error_page.php';
require_once __DIR__ . '/contact_locations.php';

// A maintenance flag in settings_local.php blocks web requests before the app
// starts a session or attempts a database connection. CLI migration scripts
// remain available while maintenance mode is enabled.
if (PHP_SAPI !== 'cli') {
    try {
        $localSettings = grompil_deployment_settings(dirname(__DIR__));
        $maintenanceEnabled = ($localSettings['maintenance_mode'] ?? false) === true;
    } catch (Throwable $exception) {
        error_log('Could not read Grompil maintenance setting: ' . $exception->getMessage());
        $maintenanceEnabled = false;
    }

    if ($maintenanceEnabled) {
        http_response_code(503);
        header('Retry-After: 300');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow');
        $language = strtolower(substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'nl'), 0, 2));
        $maintenanceText = [
            'nl' => ['Gepland onderhoud', 'Grompil is tijdelijk niet beschikbaar wegens gepland onderhoud. Probeer het later opnieuw.'],
            'fr' => ['Maintenance planifiée', 'Grompil est temporairement indisponible en raison d’une maintenance planifiée. Veuillez réessayer plus tard.'],
            'en' => ['Scheduled maintenance', 'Grompil is temporarily unavailable due to scheduled maintenance. Please try again later.'],
            'de' => ['Geplante Wartung', 'Grompil ist wegen geplanter Wartungsarbeiten vorübergehend nicht verfügbar. Bitte versuchen Sie es später erneut.'],
        ][$language] ?? ['Gepland onderhoud', 'Grompil is tijdelijk niet beschikbaar wegens gepland onderhoud. Probeer het later opnieuw.'];
        $htmlLanguage = in_array($language, ['nl', 'fr', 'en', 'de'], true) ? $language : 'nl';
        echo '<!doctype html><html lang="' . $htmlLanguage . '"><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $maintenanceText[0]
            . ' · Grompil</title><body style="margin:0;background:#f5f7f2;color:#173d36;font:16px/1.6 system-ui,sans-serif">'
            . '<main style="max-width:640px;margin:12vh auto;padding:2rem;background:#fff;border:1px solid #dce5dd;border-radius:16px">'
            . '<strong>gromPil</strong><h1>' . $maintenanceText[0] . '</h1><p>' . $maintenanceText[1]
            . '</p></main></body></html>';
        exit;
    }
}

require_once __DIR__ . '/session.php';
grompil_start_session();

/**
 * Returns the shared PostgreSQL connection.
 *
 * @return PgSql\Connection
 */
function db(): PgSql\Connection
{
    static $connection = null;

    if ($connection instanceof PgSql\Connection) {
        return $connection;
    }

    try {
        $connectionString = grompil_database_connection_string();
    } catch (Throwable $error) {
        error_log('Grompil configuration error: ' . $error->getMessage());
        grompil_show_error(
            500,
            32719,
            'The database configuration is missing. Contact the system administrator.',
            'Service temporarily unavailable'
        );
    }
    if ($connectionString === null) {
        grompil_show_error(500, 32719, 'The database configuration is missing. Contact the system administrator.', 'Service temporarily unavailable');
    }

    $connection = @pg_connect($connectionString);

    if (!$connection) {
        grompil_show_error(500, 33826, 'The database is currently unreachable. Please try again later.', 'Service temporarily unavailable');
    }

    return $connection;
}

require_once __DIR__ . '/database_queries.php';
require_once __DIR__ . '/user_codes.php';

/** Preserve bootstrap's existing false-based query handling with central logging. */
function grompil_bootstrap_query(string $operation, string $sql, array $params = []): PgSql\Result|false
{
    try {
        return db_query_params($operation, $sql, $params);
    } catch (GrompilDatabaseQueryException) {
        return false;
    }
}

/**
 * Escapes text for safe insertion into HTML.
 *
 * @param string $value The text to escape for HTML output.
 * @return string
 */
function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Redirects to a local application page.
 *
 * @param string $path The application-relative path or filesystem path.
 * @return never
 */
function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

/**
 * Returns the currently authenticated user and their current role rights.
 *
 * @return ?array
 */
function current_user(): ?array
{
    if (!isset($_SESSION['user_code'])) {
        return null;
    }

    grompil_enforce_session_idle_timeout();
    if (!isset($_SESSION['user_code'])) {
        return null;
    }

    set_database_tenant_context(
        (int) ($_SESSION['active_company_id'] ?? 0),
        (string) $_SESSION['user_code'],
        false
    );

    $result = grompil_bootstrap_query(
        'bootstrap.current_user',
        'SELECT
            u.user_code,
            u.display_name,
            u.acronym,
            login_contact.contact_value AS email,
            u.company_id,
            u.role_id,
            u.is_active,
            u.must_change_password,
            COALESCE((to_jsonb(u)->>\'auth_version\')::bigint, 0) AS auth_version,
            u.approval_status,
            r.name AS role_name,
            r.level AS role_level,
            r.permissions,
            company_role.permissions AS company_role_permissions
         FROM bt_users AS u
         LEFT JOIN dt_user_contacts AS login_contact
           ON login_contact.user_code = u.user_code
          AND login_contact.is_login_email AND login_contact.is_active
         JOIN ft_roles AS r ON r.id = u.role_id
         LEFT JOIN ct_company_users AS company_membership
           ON company_membership.user_code = u.user_code
          AND company_membership.company_id = u.company_id
          AND company_membership.is_active
          AND company_membership.approval_status = \'approved\'
         LEFT JOIN ct_company_roles AS company_role
           ON company_role.id = company_membership.company_role_id
          AND company_role.company_id = company_membership.company_id
         WHERE u.user_code = $1',
        [(string) $_SESSION['user_code']]
    );

    $user = $result ? pg_fetch_assoc($result) : false;

    if (!$user || $user['is_active'] !== 't' || $user['approval_status'] !== 'approved'
        || (int) $user['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 0)) {
        $_SESSION = [];
        session_destroy();

        return null;
    }

    $user['user_code'] = (string) $user['user_code'];
    $user['company_id'] = $user['company_id'] === null ? null : (int) $user['company_id'];
    $user['role_id'] = (int) $user['role_id'];
    $user['role_level'] = (int) $user['role_level'];
    $user['permissions'] = json_decode($user['permissions'], true) ?: [];
    $user['company_role_permissions'] = json_decode((string) ($user['company_role_permissions'] ?? '[]'), true) ?: [];
    if (is_platform_operator($user) && ($_SESSION['platform_mfa_verified'] ?? false) !== true) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $activeCompanyId = (int) ($_SESSION['active_company_id'] ?? 0);
    if ($user['company_id'] !== null
        && grompil_company_role_requires_mfa($user['company_role_permissions'])
        && (($_SESSION['company_mfa_verified'] ?? false) !== true
            || (int) ($_SESSION['company_mfa_company_id'] ?? 0) !== $activeCompanyId
            || (string) ($_SESSION['company_mfa_user_code'] ?? '') !== (string) $user['user_code'])) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    if ($user['company_id'] !== null
        && (int) ($_SESSION['active_company_id'] ?? 0) !== $user['company_id']) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    set_database_tenant_context(
        $activeCompanyId,
        (string) $user['user_code'],
        is_platform_operator($user)
    );

    return $user;
}

/**
 *  Sets request-local PostgreSQL row-security context after authentication.
 *
 * @param int $companyId The company database ID.
 * @param string $userCode The stable user code.
 * @param bool $platformOperator Whether the user has platform-level access.
 * @return void
 */
function set_database_tenant_context(int $companyId, string $userCode, bool $platformOperator): void
{
    grompil_bootstrap_query(
        'bootstrap.set_tenant_context',
        'SELECT set_config($1, $2, false), set_config($3, $4, false), set_config($5, $6, false), set_config($7, $8, false), set_config($9, $10, false)',
        [
            'grompil.user_code', $userCode,
            'grompil.company_id', $companyId > 0 ? (string) $companyId : '',
            'grompil.platform_operator', $platformOperator ? 'true' : 'false',
            'grompil.company_login_lookup', 'false',
            'grompil.platform_login_lookup', 'false',
        ]
    );
}

/**
 * Requires an authenticated user.
 *
 * @return array
 */
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        redirect('login.php');
    }

    $activeCompanyId = (int) ($_SESSION['active_company_id'] ?? 0);
    if (is_platform_operator($user)) {
        if (!platform_management_ip_allowed(null, (string) $user['user_code'])) {
            grompil_show_error(403, 57314, 'Toegang tot het platformbeheer is vanaf dit IP-adres niet toegestaan.', 'Toegang vanaf dit IP-adres geweigerd');
        }
    } elseif ($activeCompanyId > 0) {
        $company = current_company_context($user);
        $companyRoleLevel = (int) ($company['company_role_level'] ?? 0);
        if ($companyRoleLevel > 0 && $companyRoleLevel < 200
            && !company_management_ip_allowed($activeCompanyId)) {
            grompil_show_error(403, 57314, 'Toegang tot het bedrijfsbeheer is vanaf dit IP-adres niet toegestaan. Neem contact op met de platformbeheerder.', 'Toegang vanaf dit IP-adres geweigerd');
        }
    }

    if ($user['must_change_password'] === 't'
        && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'account.php') {
        redirect('account.php?password_change_required=1#change-password');
    }

    return $user;
}

/**
 *  Returns the client IP observed by PHP, consulting forwarded headers only behind an explicitly trusted proxy.
 *
 * @return string
 */
function grompil_client_ip_address(): string
{
    $address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        return '';
    }
    $trustedProxies = array_values(array_filter(array_map(
        'trim',
        explode(',', (string) (getenv('GROMPIL_TRUSTED_PROXIES') ?: ''))
    ), static fn (string $proxy): bool => filter_var($proxy, FILTER_VALIDATE_IP) !== false));
    if (!in_array($address, $trustedProxies, true)) {
        return $address;
    }

    $forwarded = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
    if ($forwarded === [] || in_array('', $forwarded, true)) {
        return $address;
    }
    foreach ($forwarded as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            return $address;
        }
    }
    $chain = [...$forwarded, $address];
    $client = $address;
    for ($index = count($chain) - 2; $index >= 0 && in_array($client, $trustedProxies, true); $index--) {
        $client = $chain[$index];
    }
    return $client;
}

/**
 *  Checks the active company's IP allowlist for the management area. An empty list means unrestricted.
 *
 * @param int $companyId The company database ID.
 * @param ?string $address The client IP address to check.
 * @return bool
 */
function company_management_ip_allowed(int $companyId, ?string $address = null): bool
{
    $address ??= grompil_client_ip_address();
    if ($companyId < 1 || filter_var($address, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    $result = grompil_bootstrap_query(
        'bootstrap.company_management_ip_allowed',
        "SELECT count(*) AS rule_count,
                COALESCE(bool_or(network >>= $2::inet), FALSE) AS address_allowed
         FROM ft_access_ip_allowlist
         WHERE company_id = $1 AND scope = 'admin' AND is_active",
        [$companyId, $address]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row !== false
        && ((int) $row['rule_count'] === 0 || $row['address_allowed'] === 't');
}

/**
 * Checks whether a user has a named permission.
 *
 * @param string $permission The permission identifier to check.
 * @param ?array $user The authenticated user record.
 * @return bool
 */
function has_permission(string $permission, ?array $user = null): bool
{
    $user ??= current_user();

    if ($user === null) {
        return false;
    }

    if (in_array($permission, $user['permissions'] ?? [], true)) {
        return true;
    }

    // Older installations may not have the platform permission backfilled yet.
    // The global platform administrator role (currently named Beheerder) is reserved for platform accounts (company_id NULL,
    // platform role levels 1–99); recognize it so tenant RLS and the manager agree.
    return $permission === 'companies.manage'
        && ($user['company_id'] ?? null) === null
        && (int) ($user['role_level'] ?? 0) > 0
        && (int) ($user['role_level'] ?? 0) <= 100
        && strtolower(trim((string) ($user['role_name'] ?? ''))) === 'beheerder';
}

/**
 *  Platform permissions are global and do not imply membership in a company.
 *
 * @param ?array $user The authenticated user record.
 * @return bool
 */
function is_platform_operator(?array $user = null): bool
{
    $user ??= current_user();
    return $user !== null
        && ($user['company_id'] ?? null) === null
        && (int) ($user['role_level'] ?? 0) > 0
        && (int) ($user['role_level'] ?? 0) <= 100
        && has_permission('companies.manage', $user);
}

/**
 *  Returns active companies the user may administer or work in.
 *
 * @param array $user The authenticated user record.
 * @return array
 */
function available_companies(array $user): array
{
    if (is_platform_operator($user)) {
        $result = grompil_bootstrap_query('bootstrap.available_companies_platform', 'SELECT id, name, slug FROM bt_companies WHERE is_active ORDER BY name');
    } else {
        $result = grompil_bootstrap_query(
            'bootstrap.available_companies_member',
            'SELECT c.id, c.name, c.slug
             FROM bt_companies AS c
             JOIN ct_company_users AS cu ON cu.company_id = c.id
             JOIN bt_users AS u ON u.user_code = cu.user_code AND u.company_id = c.id
             JOIN ct_company_roles AS cr ON cr.id = cu.company_role_id AND cr.company_id = c.id
             WHERE cu.user_code = $1 AND cu.is_active
               AND cu.approval_status = \'approved\' AND c.is_active
               AND cr.level > 0 AND cr.level < 200
             ORDER BY c.name',
            [$user['user_code']]
        );
    }

    $companies = [];
    while ($result && $row = pg_fetch_assoc($result)) {
        $row['id'] = (int) $row['id'];
        $companies[] = $row;
    }
    return $companies;
}

/**
 * Create the default set of roles for a company.
 *
 * @param int $companyId The company database ID.
 * @return bool
 */
function seed_company_roles(int $companyId): bool
{
    $result = grompil_bootstrap_query(
        'bootstrap.seed_company_roles',
        "INSERT INTO ct_company_roles (company_id, name, description, level, permissions, is_system)
         SELECT $1, r.name, r.description, r.level,
                CASE WHEN lower(r.name) = 'beheerder'
                     THEN '[\"company.users.manage\", \"company.roles.manage\", \"company.settings.manage\", \"company.translations.manage\"]'::jsonb
                     WHEN lower(r.name) = 'apotheker'
                     THEN '[\"pharmacy.dispense\"]'::jsonb
                     ELSE '[]'::jsonb
                END,
                r.is_system
         FROM ft_roles AS r
         ON CONFLICT (company_id, name) DO NOTHING",
        [$companyId]
    );
    return (bool) $result;
}

/**
 *  Sets the active company only after rechecking the user's access.
 *
 * @param int $companyId The company database ID.
 * @param array $user The authenticated user record.
 * @return bool
 */
function set_active_company(int $companyId, array $user): bool
{
    if (is_platform_operator($user)) {
        $result = grompil_bootstrap_query(
            'bootstrap.set_active_company_platform',
            'SELECT id FROM bt_companies WHERE id = $1 AND is_active',
            [$companyId]
        );
    } else {
        $result = grompil_bootstrap_query(
            'bootstrap.set_active_company_member',
            'SELECT c.id
             FROM bt_companies AS c
             JOIN ct_company_users AS cu ON cu.company_id = c.id
             JOIN ct_company_roles AS cr ON cr.id = cu.company_role_id AND cr.company_id = c.id
             WHERE c.id = $1 AND c.is_active AND cu.user_code = $2
               AND EXISTS (SELECT 1 FROM bt_users AS u WHERE u.user_code = cu.user_code AND u.company_id = c.id)
               AND cu.is_active AND cu.approval_status = \'approved\'
               AND cr.level > 0 AND cr.level < 200',
            [$companyId, $user['user_code']]
        );
    }

    if (!$result || !pg_fetch_assoc($result)) {
        return false;
    }
    $_SESSION['active_company_id'] = $companyId;
    set_database_tenant_context($companyId, (string) $user['user_code'], is_platform_operator($user));
    return true;
}

/**
 *  Returns the active company and the user's company-specific role.
 *
 * @param ?array $user The authenticated user record.
 * @return ?array
 */
function current_company_context(?array $user = null): ?array
{
    $user ??= current_user();
    $companyId = (int) ($_SESSION['active_company_id'] ?? 0);
    if ($user === null || $companyId < 1) {
        return null;
    }
    set_database_tenant_context($companyId, (string) $user['user_code'], is_platform_operator($user));

    $result = grompil_bootstrap_query(
        'bootstrap.current_company_context',
        'SELECT c.id, c.name, c.slug, c.default_language, c.is_public,
                cu.is_active AS membership_active, cu.approval_status,
                cr.id AS company_role_id, cr.name AS company_role_name,
                cr.level AS company_role_level, cr.permissions AS company_permissions
         FROM bt_companies AS c
         LEFT JOIN ct_company_users AS cu ON cu.company_id = c.id AND cu.user_code = $2
         LEFT JOIN ct_company_roles AS cr ON cr.id = cu.company_role_id AND cr.company_id = c.id
         WHERE c.id = $1 AND c.is_active',
        [$companyId, $user['user_code']]
    );
    $company = $result ? pg_fetch_assoc($result) : false;
    if (!$company) {
        return null;
    }

    $member = $company['membership_active'] === 't'
        && $company['approval_status'] === 'approved'
        && $company['company_role_id'] !== null;
    $platformOperator = is_platform_operator($user);
    if (!$member && !$platformOperator) {
        return null;
    }

    $company['id'] = (int) $company['id'];
    $company['company_role_id'] = $company['company_role_id'] === null ? null : (int) $company['company_role_id'];
    $company['company_role_level'] = $company['company_role_level'] === null ? null : (int) $company['company_role_level'];
    $company['company_permissions'] = json_decode((string) ($company['company_permissions'] ?? '[]'), true) ?: [];
    $company['is_platform_operator'] = $platformOperator;
    return $company;
}

/**
 * Require an active company context for the current user.
 *
 * @param ?array $user The authenticated user record.
 * @return array
 */
function require_company_context(?array $user = null): array
{
    $user ??= require_login();
    $company = current_company_context($user);
    if ($company === null) {
        unset($_SESSION['active_company_id']);
        redirect('select-company.php');
    }
    return $company;
}

/**
 * Redirect an authenticated user to the appropriate destination.
 *
 * @param array $user The authenticated user record.
 * @return never
 */
function redirect_after_login(array $user): never
{
    if (($user['must_change_password'] ?? 'f') === 't') {
        redirect('account.php?password_change_required=1#change-password');
    }

    // Platform operators start in the platform context. In particular, do not
    // silently restore their only company membership after they leave it.
    if (is_platform_operator($user)) {
        unset($_SESSION['active_company_id']);
        redirect('admin.php');
    }

    if (($user['company_id'] ?? null) !== null) {
        $result = grompil_bootstrap_query('bootstrap.resolve_user_company', 'SELECT id FROM bt_companies WHERE id = $1 AND is_active', [$user['company_id']]);
        $company = $result ? pg_fetch_assoc($result) : false;
        if ($company) {
            $_SESSION['active_company_id'] = (int) $user['company_id'];
            redirect('dashboard.php');
        }
    }
    $companies = available_companies($user);
    if (count($companies) === 1 && set_active_company((int) $companies[0]['id'], $user)) {
        redirect('dashboard.php');
    }
    if (count($companies) > 1) {
        unset($_SESSION['active_company_id']);
        redirect('select-company.php');
    }
    if ((int) ($user['role_level'] ?? 200) >= 200) {
        redirect('index.php');
    }
    unset($_SESSION['active_company_id']);
    redirect('select-company.php');
}

/**
 * Check whether a user has a permission in the active company context.
 *
 * @param string $permission The permission identifier to check.
 * @param ?array $company The company record.
 * @param ?array $user The authenticated user record.
 * @return bool
 */
function has_company_permission(string $permission, ?array $company = null, ?array $user = null): bool
{
    $user ??= current_user();
    $company ??= current_company_context($user);
    return $user !== null && $company !== null
        && ($company['is_platform_operator'] || in_array($permission, $company['company_permissions'] ?? [], true));
}

/**
 *  Resolve public shops from a verified host; the slug route is for local development only.
 *
 * @return ?array
 */
function resolve_public_company(): ?array
{
    $host = strtolower((string) (parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: ''));
    if ($host !== '') {
        $domainResult = grompil_bootstrap_query(
            'bootstrap.resolve_company_domain',
            'SELECT c.id, c.name, c.slug, location.city, c.default_language,
                    c.store_theme, c.store_logo_file, c.store_hero_image_file
             FROM ct_company_domains AS d
             JOIN bt_companies AS c ON c.id = d.company_id
             LEFT JOIN LATERAL (
                SELECT city FROM dt_company_locations
                WHERE company_id = c.id AND address_type_key = \'headquarters\' AND is_active
                LIMIT 1
             ) AS location ON TRUE
             WHERE d.domain = $1 AND d.is_active AND d.is_verified
               AND c.is_active AND c.is_public',
            [$host]
        );
        $company = $domainResult ? pg_fetch_assoc($domainResult) : false;
        if ($company) {
            return $company;
        }
    }

    $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $slug = trim((string) ($_GET['company'] ?? $_GET['pharmacy'] ?? ''));
    if ($isLocalHost && $slug === '') {
        return null;
    }
    if ($isLocalHost && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $result = grompil_bootstrap_query(
            'bootstrap.resolve_local_company',
            'SELECT c.id, c.name, c.slug, location.city, c.default_language,
                    store_theme, store_logo_file, store_hero_image_file
             FROM bt_companies AS c
             LEFT JOIN LATERAL (
                SELECT city FROM dt_company_locations
                WHERE company_id = c.id AND address_type_key = \'headquarters\' AND is_active
                LIMIT 1
             ) AS location ON TRUE
             WHERE c.slug = $1 AND c.is_active AND c.is_public',
            [$slug]
        );
        $company = $result ? pg_fetch_assoc($result) : false;
        if ($company) {
            return $company;
        }
    }

    http_response_code(404);
    return null;
}

/**
 *  Resolve the canonical company entry URL /c=<slug>.
 *
 * @param string $slug The company URL slug.
 * @param bool $requirePublic Whether the company must be publicly visible.
 * @return ?array
 */
function resolve_company_slug(string $slug, bool $requirePublic = true): ?array
{
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        return null;
    }
    if (!$requirePublic) {
        grompil_bootstrap_query('bootstrap.company_login_lookup.enable', "SELECT set_config('grompil.company_login_lookup', 'true', false)");
    }
    $result = grompil_bootstrap_query(
        'bootstrap.resolve_company_slug',
        'SELECT c.id, c.name, c.slug, location.city, c.default_language, c.store_theme FROM bt_companies AS c
         LEFT JOIN LATERAL (
            SELECT city FROM dt_company_locations
            WHERE company_id = c.id AND address_type_key = \'headquarters\' AND is_active
            LIMIT 1
         ) AS location ON TRUE
         WHERE c.slug = $1 AND c.is_active AND ($2::boolean = FALSE OR c.is_public)',
        [$slug, $requirePublic ? 'true' : 'false']
    );
    $company = $result ? pg_fetch_assoc($result) : false;
    if (!$requirePublic) {
        grompil_bootstrap_query('bootstrap.company_login_lookup.disable', "SELECT set_config('grompil.company_login_lookup', 'false', false)");
    }
    return $company ?: null;
}

/**
 *  Checks a role level using SS (exact) or SL (open interval) semantics.
 *
 * @param int $lowerOrExact The exact level, or the lower bound of an open interval.
 * @param ?int $upper The optional exclusive upper bound.
 * @param ?array $user The authenticated user record.
 * @return bool
 */
function has_level(int $lowerOrExact, ?int $upper = null, ?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) {
        return false;
    }
    $level = (int) ($user['role_level'] ?? 0);
    return $upper === null
        ? $level === $lowerOrExact
        : $level > $lowerOrExact && $level < $upper;
}

/**
 *  Requires an open numeric level interval, such as the staff range.
 *
 * @param int $lowerExclusive The exclusive lower bound.
 * @param int $upperExclusive The exclusive upper bound.
 * @param ?array $user The authenticated user record.
 * @return array
 */
function require_level_range(int $lowerExclusive, int $upperExclusive, ?array $user = null): array
{
    $user ??= require_login();
    if (!has_level($lowerExclusive, $upperExclusive, $user)) {
        grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
    }
    return $user;
}

/**
 * Requires a named permission or returns an HTTP 403 response.
 *
 * @param string $permission The permission identifier to check.
 * @return array
 */
function require_permission(string $permission): array
{
    $user = require_login();

    if (!has_permission($permission, $user)) {
        grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
    }

    return $user;
}

/**
 * Creates or returns the CSRF token for the current session.
 *
 * @return string
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Returns a CSRF input for a form.
 *
 * @return string
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token())
        . '">';
}

/**
 * Verifies the token submitted by a POST request.
 *
 * @return void
 */
function verify_csrf(): void
{
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        $submittedToken === ''
        || !hash_equals(csrf_token(), $submittedToken)
    ) {
        grompil_show_error(400, 45878, 'De aanvraag kon niet worden gecontroleerd. Vernieuw de pagina en probeer opnieuw.', 'Ongeldige aanvraag');
    }
}

require_once __DIR__ . '/module_access.php';
require_once __DIR__ . '/templates.php';
$platformAuthenticatorPath = __DIR__ . '/platform_authenticator.php';
if (is_file($platformAuthenticatorPath)) {
    require_once $platformAuthenticatorPath;
}

$GLOBALS['grompilTranslations'] = fn_loadLanguage();
require_once __DIR__ . '/dd_varia.php';

