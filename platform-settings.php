<?php
/**
 *
 * @date_cre: Sat 2026-10-24 00:00:00
 * @date_mut: Sat 2026-10-24 00:00:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads platform-wide system parameters.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 18499
sequence_number: 13
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @platform_settings_title@
description: @platform_settings_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/admin/platform_settings.php';
