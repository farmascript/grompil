<?php
/**
 * @date_cre: Thu 2026-11-26 15:19:24
 * @date_mut: Fri 2026-11-27 22:09:13
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
 * Return all policy criteria that a password does not meet.
 *
 * @param string $password The plain-text password to check.
 * @return array<int, string> Translation keys for failed criteria.
 */
function grompil_password_diagnostics(string $password): array
{
    $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
    $diagnostics = [];
    if ($length < 12) {
        $diagnostics[] = 'password_diagnostic_minimum_length';
    }
    $characters = preg_split('//u', $password, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($characters)) {
        $diagnostics[] = 'password_diagnostic_invalid_encoding';
        $characters = str_split($password);
    }
    if ($length > 4096 || strlen($password) > 4096) {
        $diagnostics[] = 'password_diagnostic_maximum_length';
    }
    if (str_contains($password, "\0")) {
        $diagnostics[] = 'password_diagnostic_null_byte';
    }
    if ($length > 4096 || strlen($password) > 4096 || str_contains($password, "\0")) {
        return array_values(array_unique($diagnostics));
    }
    $normalizedCharacters = array_map(
        static fn(string $character): string => function_exists('mb_strtolower')
            ? mb_strtolower($character, 'UTF-8')
            : strtolower($character),
        $characters
    );
    if (count(array_unique($normalizedCharacters)) < 6) {
        $diagnostics[] = 'password_diagnostic_variety';
    }
    $frequencies = array_count_values($normalizedCharacters);
    if ($frequencies !== [] && max($frequencies) > intdiv(count($characters), 2)) {
        $diagnostics[] = 'password_diagnostic_repetition';
    }
    $normalized = strtolower($password);
    $commonPatterns = ['password', 'qwerty', 'letmein', 'welcome', 'iloveyou', 'admin', 'changeme', 'secret'];
    foreach ($commonPatterns as $pattern) {
        if (str_contains($normalized, $pattern)) {
            $diagnostics[] = 'password_diagnostic_common_pattern:' . $pattern;
            break;
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
    $hasSequence = false;
    foreach ($sequences as $sequence) {
        for ($index = 0, $last = strlen($sequence) - 4; $index < $last; $index++) {
            if (str_contains($normalized, substr($sequence, $index, 5))) {
                $hasSequence = true;
                break 2;
            }
        }
    }
    if ($hasSequence) {
        $diagnostics[] = 'password_diagnostic_sequence';
    }

    return array_values(array_unique($diagnostics));
}

/**
 * Check that a password is long enough and avoids common predictable patterns.
 *
 * @param string $password The plain-text password to check.
 * @return bool
 */
function grompil_password_is_sane(string $password): bool
{
    return grompil_password_diagnostics($password) === [];
}
