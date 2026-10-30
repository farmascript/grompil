<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:56
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Routes the public Grompil home page and company websites.
 *
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';

if (isset($_GET['c'])) {
    require GROMPIL_ROOT . '/src/companies/company.php';
    exit;
}

$language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
$user = current_user();
$companyContext = $user === null ? null : current_company_context($user);
$accountStatusHtml = '';
$accountActionHtml = '<a class="btn btn-outline-primary btn-sm" href="login.php">' . e(fn_t('login_title')) . '</a>';
if ($user !== null) {
    $userName = trim((string) ($user['display_name'] ?? '')) ?: (string) ($user['email'] ?? '');
    $roleName = (string) ($companyContext['company_role_name'] ?? $user['role_name'] ?? '');
    $accountStatusHtml = '<span>' . e(fn_t('dashboard_logged_in_as')) . ' <strong>' . e($userName) . '</strong>'
        . ($roleName !== '' ? ' · ' . e($roleName) : '') . '</span>';

    if ($companyContext !== null) {
        $companyLabelKey = is_platform_operator($user) ? 'platform_context_for' : 'active_company_label';
        $accountStatusHtml .= '<span>' . e(fn_t($companyLabelKey)) . ' <strong>'
            . e((string) $companyContext['name']) . '</strong></span>';
        $accountActionHtml = '<a class="btn btn-outline-primary btn-sm" href="dashboard.php">'
            . e(fn_t('workspace_label')) . '</a>';
    } elseif (is_platform_operator($user)) {
        $accountActionHtml = '<a class="btn btn-outline-primary btn-sm" href="admin.php">'
            . e(fn_t('nav_admin')) . '</a>';
    } else {
        $accountActionHtml = '<a class="btn btn-outline-primary btn-sm" href="select-company.php">'
            . e(fn_t('tenant_select_title')) . '</a>';
    }
}
$page = (string) ($_GET['page'] ?? 'home');
$pages = ['home', 'services', 'about_us', 'contact'];
if (!in_array($page, $pages, true)) {
    $page = 'home';
}
$contentKey = match ($page) {
    'home' => 'home',
    'services' => 'services',
    'about_us' => 'about',
    default => 'contact',
};
$pageTitle = fn_t('marketing_' . $contentKey . '_title');
$pageIntro = fn_t('marketing_' . $contentKey . '_intro');
$links = '';
foreach ($pages as $key) {
    $links .= '<a class="nav-link" href="index.php?page=' . $key . '">' . e(fn_t('marketing_nav_' . $key)) . '</a>';
}
$cards = '';
if ($page === 'home') {
    foreach (['security', 'speed', 'cookies'] as $feature) {
        $cards .= fn_templateRender('marketing_feature.html', [
            'feature_title' => fn_t('marketing_feature_' . $feature),
            'feature_text' => fn_t('marketing_feature_' . $feature . '_text'),
        ]);
    }
}
$languageSelector = fn_languageSelector('form-select form-select-sm w-auto');
$pageBodyValues = [
    'page_title' => $pageTitle,
    'page_intro' => $pageIntro,
    '_feature_cards_html' => $cards,
];
$body = fn_templateRender('marketing_' . $page . '.html', $pageBodyValues);
echo fn_templateRender('marketing.html', [
    'html_language' => strtolower($language),
    'marketing_title' => $pageTitle,
    '_navigation_html' => $links,
    '_language_selector_html' => $languageSelector,
    '_account_status_html' => $accountStatusHtml,
    '_account_action_html' => $accountActionHtml,
    '_page_body_html' => $body,
]);

