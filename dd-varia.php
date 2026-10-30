<?php
/**
 *
 * @date_cre: Fri 2026-10-30
 * @date_mut: Fri 2026-10-30
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads platform-wide hierarchical lookup management.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 28614
sequence_number: 15
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @dd_varia_title@
description: @dd_varia_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/admin/dd_varia.php';
