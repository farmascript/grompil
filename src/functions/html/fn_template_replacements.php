<?

/**
 *
 * @file        fn_template_replacements.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 15:31:10
 * @dateLastMod Wed 2026-08-05 20:12:40
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

function fn_renderTemplate(string $file, array $arrSubst): string
{
    $html = file_get_contents($file);

    $replace = [];

    foreach ($arrSubst as $key => $value) {
        $replace["@{$key}@"] = $value;
    }

    return strtr($html, $replace);
}

function fn_checkTemplate(string $file, array $arrSubst): array
{
    $html = file_get_contents($file);

    preg_match_all('/@([A-Za-z0-9_]+)@/', $html, $matches);

    $missing = [];

    foreach ($matches[1] as $key) {

        if (!array_key_exists($key, $arrSubst)) {
			$missing[] = [
    			'file' => $file,
    			'key'  => $key
			];
        }

    }

    return array_unique($missing);
}