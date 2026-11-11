<?php
/**
 *
 * @date_cre: Wed 2026-10-21 17:00:00
 * @date_mut: Wed 2026-11-11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Returns published general URL manager labels for browser clients.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:navigation:navigation_labels
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/module_guard.php';
require_once GROMPIL_ROOT . '/includes/templates.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$labels = [];
foreach (['NL', 'FR', 'EN', 'DE'] as $language) {
    $languageCode = strtolower($language);
    $labels[$languageCode] = array_filter(
        array_replace(fn_readSourceLanguage($language), fn_readLanguageFile($language)),
        static fn (string $key): bool => str_starts_with($key, 'manager_') || $key === 'nav_logout',
        ARRAY_FILTER_USE_KEY
    );
    $labels[$languageCode]['manager_add_page_help_url'] = fn_dokuwikiUrl('users', '384727', $languageCode);
}
echo json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
