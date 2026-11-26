<?php
/**
 * @date_cre: Wed 2026-11-25 20:45:47
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the sortable company customer directory.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:customers-list
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 63503
sequence_number: 15
public_visibility: true
group: company
group_title: @workspace_label@
action: manage
title: @customers_list_title@
description: @customers_list_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/companies/customers_list.php';
