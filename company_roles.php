<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:49
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the company role administration module.
 *
 * https://dw.grompil.com/!saas/doku.php?id=src:company_roles
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 18475
sequence_number: 4
group: company
group_title: @workspace_label@
action: manage
title: @company_roles_link@
description: @company_roles_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/companies/company_roles.php';

