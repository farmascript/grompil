<?php
/**
 *
 * @date_cre: Wed 2026-10-21 17:00:00
 * @date_mut: Wed 2026-10-21 17:07:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Returns published general URL manager labels for browser clients.
 *
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/module_guard.php';
require_once GROMPIL_ROOT . '/includes/templates.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$labels = [];
foreach (['NL', 'FR', 'EN', 'DE'] as $language) {
    $labels[strtolower($language)] = array_filter(
        array_replace(fn_readSourceLanguage($language), fn_readLanguageFile($language)),
        static fn (string $key): bool => str_starts_with($key, 'manager_') || $key === 'nav_logout',
        ARRAY_FILTER_USE_KEY
    );
}
echo json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
