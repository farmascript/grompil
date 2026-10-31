<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:48
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Routes and displays the public company website.
 *
 */

declare(strict_types=1);

require_once GROMPIL_ROOT . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['c'] ?? ''));
$company = resolve_company_slug($slug);
if ($company === null) {
    // A private company still has a valid entry point for its staff.
    // Keep its public pages hidden and send visitors to the company login.
    $privateCompany = resolve_company_slug($slug, false);
    if ($privateCompany !== null) {
        header('Location: login.php?c=' . rawurlencode($slug), true, 302);
        exit;
    }
    grompil_show_error(404, 12544, 'Deze website is niet beschikbaar. Controleer de link of neem contact op met het bedrijf.', 'Website niet beschikbaar');
}
set_database_tenant_context((int) $company['id'], (int) ($_SESSION['user_id'] ?? 0), false);
$page = (string) ($_GET['page'] ?? 'home');
$validPages = ['home', 'services', 'about_us', 'contact'];
if (!in_array($page, $validPages, true)) {
    $page = 'home';
}
$language = strtoupper((string) ($_SESSION['language'] ?? $company['default_language'] ?? 'NL'));
$language = in_array($language, ['NL', 'FR', 'EN', 'DE'], true) ? $language : 'NL';
$defaults = [
    'NL' => [
        'home' => ['Welkom bij @company@', 'Welkom op de website van @company@. Hier vind je informatie over onze diensten en contactgegevens.'],
        'services' => ['Onze diensten', 'We helpen je graag verder met onze diensten en persoonlijke ondersteuning.'],
        'about_us' => ['Over ons', 'We zijn een bedrijf met aandacht voor onze klanten en de kwaliteit van onze dienstverlening.'],
        'contact' => ['Contact', 'Neem contact met ons op via de gegevens die je van ons hebt ontvangen.'],
    ],
    'FR' => [
        'home' => ['Bienvenue chez @company@', 'Bienvenue sur le site de @company@. Vous trouverez ici des informations sur nos services et nos coordonnées.'],
        'services' => ['Nos services', 'Nous vous accompagnons avec nos services et un soutien personnalisé.'],
        'about_us' => ['À propos', 'Nous sommes une entreprise attentive à ses clients et à la qualité de ses services.'],
        'contact' => ['Contact', 'Contactez-nous en utilisant les coordonnées que nous vous avons communiquées.'],
    ],
    'EN' => [
        'home' => ['Welcome to @company@', 'Welcome to the @company@ website. Find information about our services and contact details here.'],
        'services' => ['Our services', 'We are happy to help with our services and personal support.'],
        'about_us' => ['About us', 'We are a business that cares about its customers and the quality of our services.'],
        'contact' => ['Contact', 'Please contact us using the details we have provided.'],
    ],
    'DE' => [
        'home' => ['Willkommen bei @company@', 'Willkommen auf der Website von @company@. Hier finden Sie Informationen zu unseren Dienstleistungen und Kontaktdaten.'],
        'services' => ['Unsere Dienste', 'Wir unterstützen Sie gerne mit unseren Dienstleistungen und persönlicher Beratung.'],
        'about_us' => ['Über uns', 'Wir sind ein Unternehmen, dem seine Kunden und die Qualität seiner Dienstleistungen wichtig sind.'],
        'contact' => ['Kontakt', 'Kontaktieren Sie uns über die Kontaktdaten, die wir Ihnen mitgeteilt haben.'],
    ],
];
$content = $defaults[$language][$page];
$result = pg_query_params(db(), 'SELECT title, body FROM company_pages WHERE company_id = $1 AND page_key = $2 AND language_code = $3 AND is_published', [(int) $company['id'], $page, $language]);
$custom = $result ? pg_fetch_assoc($result) : false;
if (!$custom && $language !== $company['default_language']) {
    $result = pg_query_params(db(), 'SELECT title, body FROM company_pages WHERE company_id = $1 AND page_key = $2 AND language_code = $3 AND is_published', [(int) $company['id'], $page, (string) $company['default_language']]);
    $custom = $result ? pg_fetch_assoc($result) : false;
}
$title = $custom && trim((string) $custom['title']) !== '' ? (string) $custom['title'] : str_replace('@company@', (string) $company['name'], $content[0]);
$bodyText = $custom ? (string) $custom['body'] : str_replace('@company@', (string) $company['name'], $content[1]);
$navLabels = [
    'home' => ['NL' => 'Home', 'FR' => 'Accueil', 'EN' => 'Home', 'DE' => 'Startseite'],
    'services' => ['NL' => 'Diensten', 'FR' => 'Services', 'EN' => 'Services', 'DE' => 'Dienste'],
    'about_us' => ['NL' => 'Over ons', 'FR' => 'À propos', 'EN' => 'About us', 'DE' => 'Über uns'],
    'contact' => ['NL' => 'Contact', 'FR' => 'Contact', 'EN' => 'Contact', 'DE' => 'Kontakt'],
];
$nav = '';
foreach ($validPages as $key) {
    $nav .= '<a class="nav-link" href="index.php?c=' . rawurlencode($slug) . '&amp;page=' . $key . '">' . e($navLabels[$key][$language]) . '</a>';
}
$user = current_user();
$companyContext = $user === null ? null : current_company_context($user);
$identityHtml = '';
if ($user !== null) {
    $identityRole = (string) ($companyContext['company_role_name'] ?? $user['role_name'] ?? '');
    $identityHtml = '<span class="small text-secondary company-login-status">'
        . e(fn_t('dashboard_logged_in_as')) . ' <strong>' . e((string) $user['display_name'])
        . ($identityRole !== '' ? ' · ' . e($identityRole) : '') . '</strong></span>';
}
$logoutForm = '';
if ($user !== null) {
    $logoutForm = '<form method="post" action="logout.php" class="m-0">' . csrf_field()
        . '<input type="hidden" name="c" value="' . e($slug) . '">'
        . '<button class="btn btn-sm btn-outline-secondary" type="submit">' . e(fn_t('nav_logout')) . '</button></form>';
}
if ($user === null) {
    $accountLinks = '<a class="btn btn-outline-primary" href="login.php?c=' . rawurlencode($slug) . '">'
        . e(fn_t('login_title')) . '</a> <a class="btn btn-primary" href="register.php?c=' . rawurlencode($slug) . '">'
        . e(fn_t('register_title')) . '</a>';
} elseif (is_platform_operator($user)) {
    $accountLinks = '<a class="btn btn-outline-primary" href="admin.php">' . e(fn_t('nav_admin')) . '</a>';
} elseif ($user['company_id'] !== null && $user['company_id'] !== (int) $company['id']) {
    $accountLinks = '<span class="company-access-message small text-secondary">' . e(fn_t('company_other_session')) . '</span>'
        . $logoutForm;
} elseif ($companyContext === null) {
    $accountLinks = '<span class="company-access-message small text-secondary">' . e(fn_t('company_access_pending')) . '</span>';
} elseif ((int) ($companyContext['company_role_level'] ?? 0) >= 200) {
    $accountLinks = $logoutForm;
} else {
    $accountLinks = '<a class="btn btn-outline-primary" href="dashboard.php">' . e(fn_t('workspace_label')) . '</a>';
}
$accountLinks = $identityHtml . $accountLinks;
$pageContent = fn_templateRenderPath(fn_companySitePageTemplatePath($company, $page), [
    'company_name' => (string) $company['name'],
    'page_title' => $title,
    '_page_body_html' => nl2br(e($bodyText)),
]);
echo fn_templateRenderPath(fn_companySiteLayoutTemplatePath($company), [
    'html_language' => strtolower($language),
    'page_title' => $title,
    'company_name' => (string) $company['name'],
    'company_slug' => $slug,
    '_navigation_html' => $nav,
    '_account_links_html' => $accountLinks,
    '_language_selector_html' => fn_languageSelector(),
    '_page_content_html' => $pageContent,
]);

