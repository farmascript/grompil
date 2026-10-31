<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:34:29
 * @date_mut: Sat 2026-10-31 21:23:17
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Initializes sessions, database access, authentication, and access checks.
 *
 */

declare(strict_types=1);

require_once __DIR__ . '/database_config.php';
require_once __DIR__ . '/error_page.php';

$https = (
    !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off'
) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$sessionLifetime = 60 * 60 * 24 * 30;

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_name('grompil_session');
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

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
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    set_database_tenant_context(
        (int) ($_SESSION['active_company_id'] ?? 0),
        (int) $_SESSION['user_id'],
        false
    );

    $result = pg_query_params(
        db(),
        'SELECT
            u.id,
            u.display_name,
            u.email,
            u.company_id,
            u.role_id,
            u.is_active,
            u.must_change_password,
            COALESCE((to_jsonb(u)->>\'auth_version\')::bigint, 0) AS auth_version,
            u.approval_status,
            r.name AS role_name,
            r.level AS role_level,
            r.permissions
         FROM users AS u
         JOIN roles AS r ON r.id = u.role_id
         WHERE u.id = $1',
        [(int) $_SESSION['user_id']]
    );

    $user = $result ? pg_fetch_assoc($result) : false;

    if (!$user || $user['is_active'] !== 't' || $user['approval_status'] !== 'approved'
        || (int) $user['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 0)) {
        $_SESSION = [];
        session_destroy();

        return null;
    }

    $user['id'] = (int) $user['id'];
    $user['company_id'] = $user['company_id'] === null ? null : (int) $user['company_id'];
    $user['role_id'] = (int) $user['role_id'];
    $user['role_level'] = (int) $user['role_level'];
    $user['permissions'] = json_decode($user['permissions'], true) ?: [];
    if (is_platform_operator($user) && ($_SESSION['platform_mfa_verified'] ?? false) !== true) {
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
        (int) ($_SESSION['active_company_id'] ?? 0),
        (int) $user['id'],
        is_platform_operator($user)
    );

    return $user;
}

/**
 *  Sets request-local PostgreSQL row-security context after authentication.
 *
 * @param int $companyId The company database ID.
 * @param int $userId The user database ID.
 * @param bool $platformOperator Whether the user has platform-level access.
 * @return void
 */
function set_database_tenant_context(int $companyId, int $userId, bool $platformOperator): void
{
    pg_query_params(
        db(),
        'SELECT set_config($1, $2, false), set_config($3, $4, false), set_config($5, $6, false), set_config($7, $8, false)',
        [
            'grompil.user_id', (string) $userId,
            'grompil.company_id', $companyId > 0 ? (string) $companyId : '',
            'grompil.platform_operator', $platformOperator ? 'true' : 'false',
            'grompil.company_login_lookup', 'false',
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
        if (!platform_management_ip_allowed(null, (int) $user['id'])) {
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
    $result = @pg_query_params(
        db(),
        "SELECT count(*) AS rule_count,
                COALESCE(bool_or(network >>= $2::inet), FALSE) AS address_allowed
         FROM access_ip_allowlist
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
        $result = pg_query(db(), 'SELECT id, name, slug FROM companies WHERE is_active ORDER BY name');
    } else {
        $result = pg_query_params(
            db(),
            'SELECT c.id, c.name, c.slug
             FROM companies AS c
             JOIN company_users AS cu ON cu.company_id = c.id
             JOIN users AS u ON u.id = cu.user_id AND u.company_id = c.id
             JOIN company_roles AS cr ON cr.id = cu.company_role_id AND cr.company_id = c.id
             WHERE cu.user_id = $1 AND cu.is_active
               AND cu.approval_status = \'approved\' AND c.is_active
               AND cr.level > 0 AND cr.level < 200
             ORDER BY c.name',
            [$user['id']]
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
    $result = pg_query_params(
        db(),
        "INSERT INTO company_roles (company_id, name, description, level, permissions, is_system)
         SELECT $1, r.name, r.description, r.level,
                CASE WHEN lower(r.name) = 'beheerder'
                     THEN '[\"company.users.manage\", \"company.roles.manage\", \"company.settings.manage\", \"company.translations.manage\"]'::jsonb
                     ELSE '[]'::jsonb
                END,
                r.is_system
         FROM roles AS r
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
        $result = pg_query_params(
            db(),
            'SELECT id FROM companies WHERE id = $1 AND is_active',
            [$companyId]
        );
    } else {
        $result = pg_query_params(
            db(),
            'SELECT c.id
             FROM companies AS c
             JOIN company_users AS cu ON cu.company_id = c.id
             JOIN company_roles AS cr ON cr.id = cu.company_role_id AND cr.company_id = c.id
             WHERE c.id = $1 AND c.is_active AND cu.user_id = $2
               AND EXISTS (SELECT 1 FROM users AS u WHERE u.id = cu.user_id AND u.company_id = c.id)
               AND cu.is_active AND cu.approval_status = \'approved\'
               AND cr.level > 0 AND cr.level < 200',
            [$companyId, $user['id']]
        );
    }

    if (!$result || !pg_fetch_assoc($result)) {
        return false;
    }
    $_SESSION['active_company_id'] = $companyId;
    set_database_tenant_context($companyId, (int) $user['id'], is_platform_operator($user));
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
    set_database_tenant_context($companyId, (int) $user['id'], is_platform_operator($user));

    $result = pg_query_params(
        db(),
        'SELECT c.id, c.name, c.slug, c.default_language, c.is_public,
                cu.is_active AS membership_active, cu.approval_status,
                cr.id AS company_role_id, cr.name AS company_role_name,
                cr.level AS company_role_level, cr.permissions AS company_permissions
         FROM companies AS c
         LEFT JOIN company_users AS cu ON cu.company_id = c.id AND cu.user_id = $2
         LEFT JOIN company_roles AS cr ON cr.id = cu.company_role_id AND cr.company_id = c.id
         WHERE c.id = $1 AND c.is_active',
        [$companyId, $user['id']]
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
        $result = pg_query_params(db(), 'SELECT id FROM companies WHERE id = $1 AND is_active', [$user['company_id']]);
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
        $domainResult = pg_query_params(
            db(),
            'SELECT c.id, c.name, c.slug, c.city, c.default_language,
                    c.store_theme, c.store_logo_file, c.store_hero_image_file
             FROM company_domains AS d
             JOIN companies AS c ON c.id = d.company_id
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
        $result = pg_query_params(
            db(),
            'SELECT id, name, slug, city, default_language,
                    store_theme, store_logo_file, store_hero_image_file
             FROM companies WHERE slug = $1 AND is_active AND is_public',
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
        pg_query(db(), "SELECT set_config('grompil.company_login_lookup', 'true', false)");
    }
    $result = pg_query_params(
        db(),
        'SELECT id, name, slug, city, default_language, store_theme FROM companies
         WHERE slug = $1 AND is_active AND ($2::boolean = FALSE OR is_public)',
        [$slug, $requirePublic ? 'true' : 'false']
    );
    $company = $result ? pg_fetch_assoc($result) : false;
    if (!$requirePublic) {
        pg_query(db(), "SELECT set_config('grompil.company_login_lookup', 'false', false)");
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

