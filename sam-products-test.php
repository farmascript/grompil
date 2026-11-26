<?php
/**
 * @date_cre: Fri 2026-10-30 20:39:55
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Platform-operator page for testing SAM catalog lookups.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:sam-products-test
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 74268
sequence_number: 16
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @sam_products_test_title@
description: @sam_products_test_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/admin/sam_products_test.php';
