<?php
/**
 *
 * @date_cre: Wed 2026-10-21 17:07:00
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Rejects direct requests to internal modules without a public entrypoint.
 *
 * https://dw.grompil.com/!saas/doku.php?id=src:includes:module_guard
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    http_response_code(404);
    exit;
}
