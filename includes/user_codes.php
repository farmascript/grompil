<?php
/**
 *
 * @date_cre: Sat 2026-11-07
 * @date_mut: Sat 2026-11-07
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Generates unique stable public codes for Grompil user records.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:user_codes
 */

declare(strict_types=1);

require_once __DIR__ . '/do_6_char_code.php';

/** Generate a six-character code from an independent random integer. */
function grompil_generate_user_code(): string
{
    $code = getalNaarCode(random_int(0, 351519999));
    if (!grompil_is_valid_user_code($code)) {
        throw new LogicException('The user code generator returned an invalid code.');
    }

    return $code;
}

/** Check the required six-character, three-letter and three-digit format. */
function grompil_is_valid_user_code(string $code): bool
{
    return preg_match('/^[A-Z0-9]{6}$/D', $code) === 1
        && preg_match_all('/[A-Z]/', $code) === 3
        && preg_match_all('/[0-9]/', $code) === 3;
}

/**
 * Run a user insert with collision-safe code generation.
 *
 * The insert callback must include the candidate in the user_code column and
 * use ON CONFLICT DO NOTHING (or ON CONFLICT (user_code) DO NOTHING), returning
 * a result with the inserted row on success.
 *
 * @param callable(string): (PgSql\Result|false) $insertAttempt
 * @param int $maximumAttempts
 */
function grompil_insert_user_with_unique_code(callable $insertAttempt, int $maximumAttempts = 5): PgSql\Result|false
{
    if ($maximumAttempts < 1 || $maximumAttempts > 20) {
        throw new InvalidArgumentException('User code insertion attempts must be between 1 and 20.');
    }

    for ($attempt = 0; $attempt < $maximumAttempts; $attempt++) {
        $candidate = grompil_generate_user_code();
        $result = $insertAttempt($candidate);
        if (!$result) {
            return false;
        }
        if (pg_num_rows($result) > 0) {
            return $result;
        }

        try {
            $collision = db_query_params(
                'users.user_code_collision_check',
                'SELECT 1 FROM users WHERE user_code = $1 LIMIT 1',
                [$candidate]
            );
        } catch (GrompilDatabaseQueryException) {
            pg_free_result($result);
            return false;
        }
        $candidateAlreadyUsed = pg_num_rows($collision) > 0;
        pg_free_result($collision);
        if (!$candidateAlreadyUsed) {
            return $result;
        }

        pg_free_result($result);
    }

    return false;
}
