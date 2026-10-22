<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:01:27
 * @date_mut: Wed 2026-10-21 16:50:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads HTML templates and fills their placeholders with page data.
 *
 */

declare(strict_types=1);

function fn_loadLanguage(): array
{
    $available = ['NL', 'FR', 'EN', 'DE'];
    $requested = strtoupper((string) ($_GET['lang'] ?? $_SESSION['language'] ?? 'NL'));
    $language = in_array($requested, $available, true) ? $requested : 'NL';
    $_SESSION['language'] = $language;
    $translations = array_replace(
        fn_readSourceLanguage($language),
        fn_readLanguageFile($language)
    );
    $companyId = (int) ($_SESSION['active_company_id'] ?? 0);
    $requestedSlug = trim((string) ($_GET['c'] ?? ''));
    if ($requestedSlug !== '') {
        $company = resolve_company_slug($requestedSlug);
        $companyId = $company === null ? 0 : (int) $company['id'];
    }
    if ($companyId > 0) {
        set_database_tenant_context($companyId, (int) ($_SESSION['user_id'] ?? 0), false);
        $companyFile = fn_companyLanguageFilePath($companyId, $language);
        if (is_file($companyFile)) {
            $arrSubst = [];
            require $companyFile;
            $translations = array_replace($translations, $arrSubst);
        }
    }
    $fallback = array_replace(
        fn_readSourceLanguage('EN'),
        fn_readLanguageFile('EN')
    );
    foreach ($fallback as $key => $value) {
        if (!isset($translations[$key]) || trim((string) $translations[$key]) === '') {
            $translations[$key] = $value;
        }
    }
    $translations['html_language'] = strtolower($language);
    return $translations;
}

function fn_appVersion(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }

    $versionFile = dirname(__DIR__) . '/VERSION';
    $candidate = is_file($versionFile) ? trim((string) @file_get_contents($versionFile)) : '';
    $version = preg_match('/^\d+\.\d+\.\d+$/D', $candidate) === 1 ? $candidate : '0.1.0';
    return $version;
}

function fn_languageFilePath(string $language): string
{
    $language = strtoupper($language);
    if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
        throw new InvalidArgumentException('Invalid language code.');
    }
    $directory = getenv('GROMPIL_LANGUAGE_DIR') ?: dirname(__DIR__) . '/src/lang';
    return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lang_' . strtolower($language) . '.php';
}

function fn_companyLanguageFilePath(int $companyId, string $language): string
{
    if ($companyId < 1 || !in_array(strtoupper($language), ['NL', 'FR', 'EN', 'DE'], true)) {
        throw new InvalidArgumentException('Invalid company or language code.');
    }
    $base = getenv('GROMPIL_LANGUAGE_DIR') ?: dirname(__DIR__) . '/src/lang';
    return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'companies'
        . DIRECTORY_SEPARATOR . $companyId . DIRECTORY_SEPARATOR . 'lang_' . strtolower($language) . '.php';
}

function fn_readLanguageFile(string $language): array
{
    $language = strtoupper($language);
    $path = fn_languageFilePath($language);
    if (!is_file($path)) {
        $path = dirname(__DIR__) . '/src/lang/lang_' . strtolower($language) . '.php';
    }
    if (!is_file($path) && $language !== 'EN') {
        $path = dirname(__DIR__) . '/src/lang/lang_en.php';
    }
    if (!is_file($path)) {
        return [];
    }
    $arrSubst = [];
    require $path;
    return $arrSubst;
}

function fn_readSourceLanguage(string $language): array
{
    $path = dirname(__DIR__) . '/src/lang/lang_' . strtolower($language) . '.php';
    if (!is_file($path)) {
        return [];
    }
    $arrSubst = [];
    require $path;
    return $arrSubst;
}

function fn_translationSeed(string $language): array
{
    $language = strtoupper($language);
    if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
        throw new InvalidArgumentException('Invalid language code.');
    }
    $dutch = fn_readSourceLanguage('NL');
    $english = fn_readSourceLanguage('EN');
    $source = fn_readSourceLanguage($language);
    $keys = array_unique(array_merge(array_keys($dutch), array_keys($english), array_keys($source)));
    $seed = [];
    foreach ($keys as $key) {
        $seed[$key] = $key === 'html_language'
            ? strtolower($language)
            : (string) ($source[$key] ?? ($language === 'EN' ? $dutch[$key] ?? '' : ''));
    }
    return $seed;
}

function fn_publishLanguageFile(string $target, string $content): bool
{
    $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
    if (@file_put_contents($temporary, $content, LOCK_EX) === false) {
        return false;
    }
    if (@rename($temporary, $target)) {
        return true;
    }
    if (DIRECTORY_SEPARATOR === '\\' && @copy($temporary, $target)) {
        @unlink($temporary);
        return true;
    }
    @unlink($temporary);
    return false;
}

function fn_exportLanguageFile(string $language): bool
{
    $result = pg_query_params(db(), 'SELECT "key", "value" FROM substitutions WHERE company_id = 0 AND code = $1 ORDER BY "key"', [$language]);
    if (!$result) {
        return false;
    }
    $translations = [];
    while ($row = pg_fetch_assoc($result)) {
        $translations[$row['key']] = $row['value'];
    }
    $target = fn_languageFilePath($language);
    $directory = dirname($target);
    if (!is_dir($directory) || !is_writable($directory)) {
        return false;
    }
    $content = "<?php\n\n\$arrSubst = " . var_export($translations, true) . ";\n";
    return fn_publishLanguageFile($target, $content);
}

function fn_exportCompanyLanguageFile(int $companyId, string $language): bool
{
    $result = pg_query_params(
        db(),
        'SELECT "key", "value" FROM substitutions WHERE company_id = $1 AND code = $2 ORDER BY "key"',
        [$companyId, strtoupper($language)]
    );
    if (!$result) {
        return false;
    }
    $translations = [];
    while ($row = pg_fetch_assoc($result)) {
        $translations[$row['key']] = $row['value'];
    }
    $target = fn_companyLanguageFilePath($companyId, $language);
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }
    if (!is_writable($directory)) {
        return false;
    }
    if ($translations === []) {
        return !is_file($target) || @unlink($target);
    }
    $content = "<?php\n\n\$arrSubst = " . var_export($translations, true) . ";\n";
    return fn_publishLanguageFile($target, $content);
}

function fn_t(string $key): string
{
    return (string) ($GLOBALS['grompilTranslations'][$key] ?? $key);
}

function fn_languageSelector(string $class = 'form-select form-select-sm w-auto'): string
{
    $language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
    $classes = trim($class . ' language-selector');
    $html = '<select id="language-select" name="lang" class="' . e($classes) . '" aria-label="Language" data-language-parameter="lang">';
    foreach (['NL', 'FR', 'EN', 'DE'] as $option) {
        $selected = $option === $language ? ' selected' : '';
        $html .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
    }
    return $html . '</select>';
}

function fn_templateTokenIsVisibleText(string $html, int $offset): bool
{
    $before = substr($html, 0, $offset);
    $lastOpen = strrpos($before, '<');
    $lastClose = strrpos($before, '>');

    if ($lastOpen !== false && ($lastClose === false || $lastOpen > $lastClose)) {
        return false;
    }

    foreach (['title', 'script', 'style', 'textarea'] as $tag) {
        $open = strripos($before, '<' . $tag);
        $close = strripos($before, '</' . $tag . '>');
        if ($open !== false && ($close === false || $open > $close)) {
            return false;
        }
    }

    return true;
}

function fn_templateRenderPath(string $path, array $values = []): string
{
    $html = file_get_contents($path);
    if ($html === false) {
        grompil_show_error(500, 72805, 'Een paginaonderdeel kon niet worden geladen. Neem contact op met de beheerder.', 'Pagina niet beschikbaar');
    }
    $values = array_merge(['_asset_base_href' => e(fn_appBaseHref())], $values);
    $translations = $GLOBALS['grompilTranslations'] ?? [];

    static $showTranslationKeyTooltips = null;
    if ($showTranslationKeyTooltips === null) {
        $user = current_user();
        $showTranslationKeyTooltips = $user !== null
            && (int) ($user['role_level'] ?? 100) < 100;
    }

    $rendered = preg_replace_callback(
        '/@([A-Za-z0-9_]+)@/',
        static function (array $match) use ($html, $translations, $values, $showTranslationKeyTooltips): string {
            $key = $match[1][0];
            $token = $match[0][0];
            $offset = $match[0][1];

            if (array_key_exists($key, $values)) {
                return str_starts_with($key, '_')
                    ? (string) $values[$key]
                    : e((string) $values[$key]);
            }

            if (!array_key_exists($key, $translations)) {
                return $token;
            }

            $translated = e((string) $translations[$key]);
            if (
                !$showTranslationKeyTooltips
                || !fn_templateTokenIsVisibleText($html, $offset)
            ) {
                return $translated;
            }

            return '<span data-bs-toggle="tooltip" data-bs-title="' . e((string) $key)
                . '" tabindex="0">' . $translated . '</span>';
        },
        $html,
        -1,
        $count,
        PREG_OFFSET_CAPTURE
    );

    return $rendered ?? $html;
}

function fn_templateRender(string $file, array $values = []): string
{
    return fn_templateRenderPath(dirname(__DIR__) . '/tmpl/default/' . basename($file), $values);
}

function fn_appBaseHref(): string
{
    $projectRoot = realpath(dirname(__DIR__));
    $scriptFile = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($projectRoot !== false && $scriptFile !== false) {
        $projectPrefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with(strtolower($scriptFile), strtolower($projectPrefix))) {
            $relativeScript = str_replace('\\', '/', substr($scriptFile, strlen($projectPrefix)));
            $suffix = '/' . $relativeScript;
            if (str_ends_with(strtolower($scriptName), strtolower($suffix))) {
                $base = substr($scriptName, 0, -strlen($suffix));
                return rtrim($base, '/') . '/';
            }
        }
    }

    $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
    if (str_ends_with($scriptDirectory, '/scripts')) {
        $scriptDirectory = substr($scriptDirectory, 0, -strlen('/scripts'));
    }
    return rtrim($scriptDirectory, '/') . '/';
}

function fn_companyStoreTemplatePath(array $company): string
{
    $base = dirname(__DIR__) . '/tmpl';
    $companyId = (int) ($company['id'] ?? 0);
    if ($companyId > 0) {
        $companyTemplate = $base . '/companies/' . $companyId . '/store.html';
        if (is_file($companyTemplate)) {
            return $companyTemplate;
        }
    }
    $theme = (string) ($company['store_theme'] ?? 'default');
    if (preg_match('/^[a-z0-9_-]{1,64}$/', $theme) === 1 && $theme !== 'default') {
        $themeTemplate = $base . '/themes/' . $theme . '/store.html';
        if (is_file($themeTemplate)) {
            return $themeTemplate;
        }
    }
    return $base . '/default/store.html';
}

function fn_companyStoreImageUrl(array $company, string $field): string
{
    $allowedFields = ['store_logo_file', 'store_hero_image_file'];
    if (!in_array($field, $allowedFields, true)) {
        return '';
    }
    $filename = (string) ($company[$field] ?? '');
    if ($filename === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,126}\\.(?:jpe?g|png|webp|gif)$/i', $filename) !== 1) {
        return '';
    }
    $companyId = (int) ($company['id'] ?? 0);
    $directory = dirname(__DIR__) . '/media/companies/' . $companyId;
    if ($companyId < 1 || !is_file($directory . '/' . $filename)) {
        return '';
    }
    return 'media/companies/' . $companyId . '/' . rawurlencode($filename);
}

function fn_companyStoreThemeOptions(string $selected = 'default'): string
{
    $themes = ['default' => fn_t('company_theme_default')];
    $directory = dirname(__DIR__) . '/tmpl/themes';
    foreach (glob($directory . '/*/store.html') ?: [] as $template) {
        $theme = basename(dirname($template));
        if (preg_match('/^[a-z0-9_-]{1,64}$/', $theme) === 1) {
            $labelKey = 'company_theme_' . $theme;
            $translatedLabel = fn_t($labelKey);
            $themes[$theme] = $translatedLabel !== $labelKey
                ? $translatedLabel
                : ucfirst(str_replace(['-', '_'], ' ', $theme));
        }
    }
    $html = '';
    foreach ($themes as $theme => $label) {
        $html .= '<option value="' . e($theme) . '"' . ($theme === $selected ? ' selected' : '') . '>'
            . e($label) . '</option>';
    }
    return $html;
}

function page_start(string $title, array $user): void
{
    $company = current_company_context($user);
    $isPlatformOperator = is_platform_operator($user);
    $GLOBALS['grompilPageFooterShowIp'] = $isPlatformOperator
        || ($company !== null && has_company_permission('company.settings.manage', $company, $user));
    $userName = trim((string) ($user['display_name'] ?? '')) ?: (string) ($user['email'] ?? '');
    $roleName = (string) ($company['company_role_name'] ?? $user['role_name'] ?? '');
    $userContextLabel = trim($userName . ($roleName !== '' ? ' · ' . $roleName : ''));
    $managerCatalogPath = __DIR__ . '/../url-manager/index.html';
    $managerCatalogVersion = is_file($managerCatalogPath)
        ? substr((string) hash_file('sha256', $managerCatalogPath), 0, 16)
        : '1';
    $companyPublic = $company !== null
        && in_array(strtolower((string) ($company['is_public'] ?? '')), ['1', 't', 'true', 'yes'], true);
    $publicSiteUrl = fn_appBaseHref();
    if ($companyPublic && !empty($company['slug'])) {
        $publicSiteUrl .= 'index.php?c=' . rawurlencode((string) $company['slug']);
    }
    $publicSiteButton = $company === null || $companyPublic
        ? '<a class="btn btn-outline-secondary btn-sm" href="' . e($publicSiteUrl)
            . '" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip"'
            . ' data-bs-placement="bottom" data-bs-title="' . e($publicSiteUrl)
            . '">Naar publiekswebsite</a>'
        : '';
    echo fn_templateRender('html_head.html', ['_page_title' => e($title . ' · Grompil'), 'html_language' => strtolower((string) ($_SESSION['language'] ?? 'NL'))]);
    echo fn_templateRender('header.html', [
        '_app_version' => e(fn_appVersion()),
        '_csrf_field_html' => csrf_field(),
        '_logout_company_field_html' => $company !== null
            ? '<input type="hidden" name="c" value="' . e((string) $company['slug']) . '">'
            : '',
        '_logout_tooltip' => e(fn_t('nav_logout') . ' · ' . $userName),
        '_language_selector_html' => fn_languageSelector(),
        'workspace_label' => fn_t('workspace_label'),
        'manager_home_label' => fn_t('manager_home'),
        '_public_site_button_html' => $publicSiteButton,
        '_manager_href' => e(fn_appBaseHref() . 'url-manager/index.html?v=' . $managerCatalogVersion),
        '_company_badge_html' => $company !== null
            ? '<span class="btn btn-success btn-sm company-context-badge" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="'
                . ($isPlatformOperator ? 'Platformbeheer voor company #' : 'Actieve company #')
                . e((string) $company['id']) . '">'
                . ($isPlatformOperator ? 'Voor: ' : '') . e((string) $company['name']) . '</span>'
            : '',
        '_user_context_html' => $userContextLabel !== ''
            ? '<span class="small text-secondary user-context-label" aria-label="Ingelogd als ' . e($userContextLabel) . '">' . e($userContextLabel) . '</span>'
            : '',
    ]);
}

function page_end(): void
{
    $ipHtml = '';
    if (!empty($GLOBALS['grompilPageFooterShowIp'])) {
        $ipAddress = grompil_client_ip_address();
        $ipHtml = '<span class="app-footer-ip">IP-adres: <code>'
            . e($ipAddress !== '' ? $ipAddress : 'onbekend')
            . '</code></span>';
    }
    echo '<footer class="app-footer"><div class="container d-flex flex-wrap justify-content-between gap-2">'
        . '<span>&copy; 1981–' . e(date('Y')) . ' Grompil</span>' . $ipHtml
        . '</div></footer>';
    echo fn_templateRender('cookie_footer.html');
    echo fn_templateRender('html_foot.html');
}

