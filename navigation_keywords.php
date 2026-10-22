<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the fixed navigation label management module.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 59384
sequence_number: 10
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @translations_heading@
description: @admin_translations_description@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/navigation/navigation_keywords.php';

