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
    '_page_body_html' => $body,
]);

