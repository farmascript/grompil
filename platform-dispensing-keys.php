<?php
/**
 * @date_cre: Thu 2026-11-19 10:52:55
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads platform-wide dispensing function-key assignments.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:platform-dispensing-keys
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 18536
sequence_number: 19
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @platform_dispensing_shortcuts_title@
description: @platform_dispensing_shortcuts_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/admin/platform_dispensing_shortcuts.php';
