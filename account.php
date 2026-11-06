<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the account module for its existing public URL.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:account
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 76506 
sequence_number: 9
group: platform
group_title: @nav_admin@
action: manage
title: @admin_account_titles@
description: @admin_account_description@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/account/account.php';

