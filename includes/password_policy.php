<?php
/**
 * @date_cre: Thu 2026-11-26 15:19:24
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Checks password length and predictable patterns.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:password_policy
 */
declare(strict_types=1);

/**
 * Check that a password is long enough and avoids common predictable patterns.
 *
 * @param string $password The plain-text password to check.
 * @return bool
 */
function grompil_password_is_sane(string $password): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
    if ($length < 12 || $length > 4096 || strlen($password) > 4096 || str_contains($password, "\0")) {
        return false;
    }

    $characters = preg_split('//u', $password, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($characters) || count($characters) < 12) {
        return false;
    }

    $normalizedCharacters = array_map(
        static fn(string $character): string => function_exists('mb_strtolower')
            ? mb_strtolower($character, 'UTF-8')
            : strtolower($character),
        $characters
    );
    if (count(array_unique($normalizedCharacters)) < 6) {
        return false;
    }

    $frequencies = array_count_values($normalizedCharacters);
    if (max($frequencies) > intdiv(count($characters), 2)) {
        return false;
    }

    $normalized = strtolower($password);
    $commonPatterns = ['password', 'qwerty', 'letmein', 'welcome', 'iloveyou', 'admin', 'changeme', 'secret'];
    foreach ($commonPatterns as $pattern) {
        if (str_contains($normalized, $pattern)) {
            return false;
        }
    }

    $sequences = [
        'abcdefghijklmnopqrstuvwxyz',
        'zyxwvutsrqponmlkjihgfedcba',
        '0123456789',
        '9876543210',
        'qwertyuiop',
        'poiuytrewq',
        'asdfghjkl',
        'lkjhgfdsa',
        'zxcvbnm',
        'mnbvcxz',
    ];
    foreach ($sequences as $sequence) {
        for ($index = 0, $last = strlen($sequence) - 4; $index < $last; $index++) {
            if (str_contains($normalized, substr($sequence, $index, 5))) {
                return false;
            }
        }
    }

    return true;
}
