<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:45:46
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the translation management module.
 *
 * https://dw.grompil.com/!saas/doku.php?id=src:translations
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 16721 
sequence_number: 8
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @nav_translations@
description: @admin_translations@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/translations/translations.php';

