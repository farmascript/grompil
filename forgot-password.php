<?php
/**
 *
 * @date_cre: Wed 2026-10-21 17:30:00
 * @date_mut: Wed 2026-10-21 18:39:38
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the password reset request module.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}
require GROMPIL_ROOT . '/src/account/forgot_password.php';
