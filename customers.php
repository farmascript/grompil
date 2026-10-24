<?php
/**
 *
 * @date_cre: Sat 2026-10-24 00:00:00
 * @date_mut: Sat 2026-10-24 00:00:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the company customer monitoring page.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 63502
sequence_number: 14
public_visibility: true
group: company
group_title: @workspace_label@
action: manage
title: @customers_heading@
description: @customers_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/companies/customers.php';
