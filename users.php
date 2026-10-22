<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:57
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the platform user management module.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 18473
sequence_number: 2
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @users_heading@
description: @admin_users_description@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/users/users.php';

