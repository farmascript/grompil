<?php
/**
 * User-facing Grompil release notes and notification preferences.
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 72194
sequence_number: 21
public_visibility: false
group: platform
group_title: @nav_admin@
action: view
title: @release_notes_title@
description: @release_notes_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/updates/release_notes.php';
