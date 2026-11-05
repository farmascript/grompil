<?php
/**
 *
 * @date_cre: Sun 2026-11-01
 * @date_mut: Thu 2026-11-05
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Builds contextual documentation links and accessible information icons.
 *
 */

declare(strict_types=1);

require_once __DIR__ . '/module_guard.php';
require_once __DIR__ . '/credentials_config.php';

/** Build a stable wiki URL; an empty configuration disables documentation links. */
function fn_dokuwikiUrl(string $level, string $pageId): string
{
    if (!in_array($level, ['users', 'dev', 'testers', 'extern'], true)
        || preg_match('/^[0-9]+$/D', $pageId) !== 1) {
        throw new InvalidArgumentException('Invalid documentation level or page ID.');
    }

    $settings = grompil_deployment_settings(GROMPIL_ROOT);
    $baseUrl = trim((string) ($settings['dokuwiki'] ?? ''));
    if ($baseUrl === '') {
        return '';
    }
    $parts = parse_url($baseUrl);
    if ($parts === false || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)
        || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])
        || isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('Invalid DokuWiki base URL.');
    }

    $language = strtolower((string) ($_SESSION['language'] ?? 'nl'));
    if (!in_array($language, ['nl', 'fr', 'en', 'de'], true)) {
        $language = 'nl';
    }
    $languagePrefix = $language . ':';

    return rtrim($baseUrl, '/') . '/doku.php?id=' . $languagePrefix . 'doc:grompil:' . $level . ':' . $pageId;
}

/** Render a keyboard-accessible information link with a localized accessible name. */
function fn_dokuwikiIcon(string $level, string $pageId): string
{
    $url = fn_dokuwikiUrl($level, $pageId);
    if ($url === '') {
        return '';
    }
    $labels = [
        'NL' => 'Meer informatie (opent in een nieuw tabblad)',
        'FR' => 'Plus d’informations (s’ouvre dans un nouvel onglet)',
        'EN' => 'More information (opens in a new tab)',
        'DE' => 'Weitere Informationen (öffnet in einem neuen Tab)',
    ];
    $label = $labels[strtoupper((string) ($_SESSION['language'] ?? 'NL'))] ?? $labels['EN'];
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<a class="dokuwiki-help d-inline-flex align-middle ms-2" href="' . $escape($url)
        . '" target="_blank" rel="noopener noreferrer" aria-label="' . $escape($label)
        . '" title="' . $escape($label) . '">'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">'
        . '<circle cx="12" cy="12" r="10"/><path d="M12 11v6"/><circle cx="12" cy="7" r="1" fill="currentColor" stroke="none"/>'
        . '</svg></a>';
}
