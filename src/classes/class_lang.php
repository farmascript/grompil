<?

/**
 *
 * @file        class_lang.php
 * @author      lm
 * @dateCreated Fri 2026-07-31 12:10:57
 * @dateLastMod Fri 2026-07-31 16:09:17
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }
class Lang
{
    private static array $replace = [];

    public static function load(array $arr): void
    {
        foreach ($arr as $key => $value) {
            self::$replace["@{$key}@"] = $value;
        }
    }

    public static function get(string $key): string
    {
        return self::$replace["@{$key}@"] ?? "@{$key}@";
    }

    public static function render(string $file): string
    {
        return strtr(file_get_contents($file), self::$replace);
    }

    public static function check(string $file): array
    {
        $html = file_get_contents($file);

        // Zoek alle @woorden@
        preg_match_all('/@([a-zA-Z0-9_]+)@/', $html, $matches);

        $missing = [];

        foreach ($matches[1] as $key) {
            if (!isset(self::$replace["@{$key}@"])) {
                $missing[] = $file . ": " . $key;
            }
        }

        return array_unique($missing);
    }
}