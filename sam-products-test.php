<?php
/**
 * Platform-operator page for testing SAM catalog lookups.
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
