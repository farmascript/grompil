<?php
/**
 *
 * @date_cre: Sat 2026-11-07
 * @date_mut: Sat 2026-11-07 14:16:49
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Assigns unique public codes to existing user accounts.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:backfill_user_codes
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('GROMPIL_ROOT', dirname(__DIR__));
require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/user_codes.php';

try {
    $usersResult = db_query_params(
        'users.user_code_backfill_list',
        "SELECT id FROM bt_users
         WHERE user_code IS NULL
            OR user_code !~ '^[A-Z0-9]{6}$'
            OR length(regexp_replace(user_code, '[A-Z]', '', 'g')) <> 3
            OR length(regexp_replace(user_code, '[0-9]', '', 'g')) <> 3
         ORDER BY id"
    );
} catch (GrompilDatabaseQueryException) {
    fwrite(STDERR, "Could not list users that need codes.\n");
    exit(1);
}

$updated = 0;
$skipped = 0;
while ($usersResult && $user = pg_fetch_assoc($usersResult)) {
    $userId = (int) $user['id'];
    $assigned = false;

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = grompil_generate_user_code();
        try {
            $updateResult = db_query_params(
                'users.user_code_backfill_update',
                "UPDATE bt_users SET user_code = $1
                 WHERE id = $2
                   AND (user_code IS NULL
                        OR user_code !~ '^[A-Z0-9]{6}$'
                        OR length(regexp_replace(user_code, '[A-Z]', '', 'g')) <> 3
                        OR length(regexp_replace(user_code, '[0-9]', '', 'g')) <> 3)",
                [$candidate, $userId]
            );
        } catch (GrompilDatabaseQueryException) {
            try {
                $collisionResult = db_query_params(
                    'users.user_code_backfill_collision_check',
                    'SELECT 1 FROM bt_users WHERE user_code = $1 LIMIT 1',
                    [$candidate]
                );
            } catch (GrompilDatabaseQueryException) {
                fwrite(STDERR, "Could not verify a code collision.\n");
                exit(1);
            }

            if (pg_num_rows($collisionResult) > 0) {
                pg_free_result($collisionResult);
                continue;
            }
            pg_free_result($collisionResult);
            fwrite(STDERR, "A user code could not be assigned due to a database error.\n");
            exit(1);
        }

        if (pg_affected_rows($updateResult) === 1) {
            $assigned = true;
            $updated++;
        } else {
            $skipped++;
            $assigned = true;
        }
        pg_free_result($updateResult);
        break;
    }

    if (!$assigned) {
        fwrite(STDERR, "Could not find an unused user code after 10 attempts.\n");
        exit(1);
    }
}

pg_free_result($usersResult);

try {
    $remainingResult = db_query_params(
        'users.user_code_backfill_remaining',
        "SELECT count(*) AS remaining FROM bt_users
         WHERE user_code IS NULL
            OR user_code !~ '^[A-Z0-9]{6}$'
            OR length(regexp_replace(user_code, '[A-Z]', '', 'g')) <> 3
            OR length(regexp_replace(user_code, '[0-9]', '', 'g')) <> 3"
    );
} catch (GrompilDatabaseQueryException) {
    fwrite(STDERR, "Could not verify that all user codes are assigned.\n");
    exit(1);
}
$remaining = $remainingResult ? (int) pg_fetch_result($remainingResult, 0, 'remaining') : -1;
if ($remaining !== 0) {
    fwrite(STDERR, "User code backfill is incomplete.\n");
    exit(1);
}

fwrite(STDOUT, sprintf("User code backfill complete. Assigned or repaired: %d; already valid concurrently: %d.\n", $updated, $skipped));
