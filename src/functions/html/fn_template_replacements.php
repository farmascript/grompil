<?

/**
 *
 * @file        fn_template_replacements.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 15:31:10
 * @dateLastMod Wed 2026-08-05 16:03:18
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

function fn_renderTemplate(string $file, array $arrLang): string
{
    $html = file_get_contents($file);

    $replace = [];

    foreach ($arrLang as $key => $value) {
        $replace["@{$key}@"] = $value;
    }

    return strtr($html, $replace);
}

function fn_checkTemplate(string $file, array $arrLang): array
{
    $html = file_get_contents($file);

    preg_match_all('/@([A-Za-z0-9_]+)@/', $html, $matches);

    $missing = [];

    foreach ($matches[1] as $key) {

        if (!array_key_exists($key, $arrLang)) {
			$missing[] = [
    			'file' => $file,
    			'key'  => $key
			];
        }

    }

    return array_unique($missing);
}