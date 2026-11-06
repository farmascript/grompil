<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:48
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Renders safe error pages with an internal error code.
 *
 */

declare(strict_types=1);

/**
 *  Render a stable, user-facing error page without requiring the database.
 *
 * @param int $status The HTTP status code.
 * @param int $code The application error code.
 * @param string $message The user-facing error message to display.
 * @param string $title The response title.
 * @return never
 */
function grompil_show_error(int $status, int $code, string $message, string $title = 'An error occurred'): never
{
    require_once __DIR__ . '/templates.php';

    http_response_code($status);
    $language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
    if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
        $language = 'NL';
    }
    $translations = $GLOBALS['grompilTranslations'] ?? fn_readLanguageFile($language);
/**
 * https://dw.grompil.com/!saas/doku.php?id=src:includes:error_page
 */
    $title = (string) ($translations['err_' . $code . '_title'] ?? $title);
    $message = (string) ($translations['err_' . $code] ?? $message);
    $labels = [
        'NL' => ['Meldingsnummer', 'Naar manager', 'Startpagina'],
        'FR' => ['Numéro de référence', 'Accéder au gestionnaire', 'Accueil'],
        'EN' => ['Reference number', 'Go to manager', 'Home'],
        'DE' => ['Meldungsnummer', 'Zum Manager', 'Startseite'],
    ][$language];
    $templatePath = dirname(__DIR__) . '/tmpl/core/error.html';
    $template = is_file($templatePath) ? file_get_contents($templatePath) : false;
    $values = [
        'error_title' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'error_message' => htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'error_code' => (string) $code,
        'error_reference_label' => $labels[0],
        '_error_manager_label' => $labels[1],
        '_error_manager_href' => htmlspecialchars(fn_appBaseHref() . 'url-manager/index.html', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        '_error_home_href' => htmlspecialchars(fn_appBaseHref() . 'index.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'error_home_label' => $labels[2],
    ];
    if ($template === false) {
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>' . $values['error_title']
            . '</title><main><h1>' . $values['error_title'] . '</h1><p>' . $values['error_message']
            . '</p><p>Reference number ' . $code . '</p></main></html>';
    } else {
        $replacements = array_combine(
            array_map(static fn (string $key): string => '@' . $key . '@', array_keys($values)),
            array_values($values)
        );
        echo strtr($template, $replacements);
    }
    exit;
}

