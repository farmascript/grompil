<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:56
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the public home page and company website router.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:index
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 18477
sequence_number: 6
group: public
group_title: @marketing_nav_home@
action: view
title: @marketing_nav_home@
description: @marketing_home_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/site/index.php';

