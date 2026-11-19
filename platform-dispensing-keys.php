<?php
/**
 * Loads platform-wide dispensing function-key assignments.
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
