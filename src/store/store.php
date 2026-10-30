<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:17:21
 * @date_mut: Wed 2026-10-21 10:00:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the public product and company storefront.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$company = resolve_public_company();
if ($company === null) {
    if (http_response_code() >= 400) {
        grompil_show_error(404, 12544, 'Deze bedrijfswebsite is niet beschikbaar. Controleer de link of neem contact op met het bedrijf.', 'Website niet beschikbaar');
    }

    $directoryResult = pg_query(
        db(),
        'SELECT name, slug, city FROM companies WHERE is_active AND is_public ORDER BY name'
    );
    $companyCards = '';
    while ($directoryResult && $directoryCompany = pg_fetch_assoc($directoryResult)) {
        $companyCards .= fn_templateRender('store_directory_company.html', [
            'company_name' => (string) $directoryCompany['name'],
            'company_slug' => (string) $directoryCompany['slug'],
            'company_city' => (string) $directoryCompany['city'],
        ]);
    }
    echo fn_templateRender('store_directory.html', [
        'html_language' => strtolower((string) ($_SESSION['language'] ?? 'NL')),
        '_company_cards_html' => $companyCards,
        'directory_empty_message' => $companyCards === '' ? fn_t('store_directory_empty') : '',
        '_language_selector_html' => fn_languageSelector(),
        '_cookie_footer_html' => fn_templateRender('cookie_footer.html'),
    ]);
    exit;
}

$user = current_user();
$accountNavigation = '';

if ($user === null) {
    $accountNavigation = '<a class="nav-link" href="login.php">' . e(fn_t('login_title')) . '</a>'
        . '<a class="btn btn-sm btn-grompil" href="register.php?company=' . e((string) $company['slug']) . '">'
        . e(fn_t('store_create_account')) . '</a>';
} elseif (has_level(1, 100, $user) || has_level(100, 200, $user)) {
    $accountNavigation = '<a class="btn btn-sm btn-grompil" href="dashboard.php">'
        . e(fn_t('store_staff_workspace')) . '</a>';
} else {
    $accountNavigation = '<span class="small text-secondary">' . e(fn_t('store_signed_in_as')) . ' '
        . e($user['display_name']) . '</span>'
        . '<form method="post" action="logout.php" class="m-0">' . csrf_field()
        . '<button class="btn btn-sm btn-outline-secondary" type="submit">' . e(fn_t('nav_logout')) . '</button></form>';
}

$logoUrl = fn_companyStoreImageUrl($company, 'store_logo_file');
$heroUrl = fn_companyStoreImageUrl($company, 'store_hero_image_file');
$templateValues = [
    'company_name' => (string) $company['name'],
    'company_slug' => (string) $company['slug'],
    'company_logo_url' => $logoUrl,
    'company_hero_image_url' => $heroUrl,
    '_company_logo_html' => $logoUrl === '' ? '<span class="brandmark"><img src="media/gromPilLogo.png" alt="" width="32" height="32"></span>'
        : '<img class="storefront-logo" src="' . e($logoUrl) . '" alt="' . e((string) $company['name']) . '">',
    '_company_hero_image_html' => $heroUrl === '' ? '' : '<img class="storefront-hero-image" src="'
        . e($heroUrl) . '" alt="">',
    '_account_nav_html' => $accountNavigation,
    '_language_selector_html' => fn_languageSelector(),
    '_cookie_footer_html' => fn_templateRender('cookie_footer.html'),
];
echo fn_templateRenderPath(fn_companyStoreTemplatePath($company), $templateValues);

