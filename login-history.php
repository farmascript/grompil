<?php
/**
 * @date_cre: Fri 2026-11-27 15:33:57
 * @date_mut: Fri 2026-11-27
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the company login and logout history.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:login-history
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 91348
sequence_number: 21
public_visibility: true
group: company
group_title: @workspace_label@
action: manage
title: @login_history_title@
description: @login_history_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/companies/login_history.php';
