<?php
/**
 * @date_cre: Sat 2026-10-31 14:32:44
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the platform administrator IP access module.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:platform-security
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

require GROMPIL_ROOT . '/src/admin/platform_security.php';
