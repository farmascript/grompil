<?php
/**
 * @date_cre: Fri 2026-11-13
 * @date_mut: Fri 2026-11-13
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Platform-only test screen for the compounding raw-material catalogue.
 * https://dw.grompil.com/!saas/doku.php?id=broncode:compound-products-test
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 74270
sequence_number: 18
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @compound_products_test_title@
description: @compound_products_test_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/admin/compound_products_test.php';
