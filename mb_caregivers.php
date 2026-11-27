<?php
/**
 * @date_cre: Fri 2026-11-27 11:02:00
 * @date_mut: Fri 2026-11-27
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Opens the company caregiver registry.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:mb_caregivers
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 63504
sequence_number: 17
public_visibility: true
group: company
group_title: @workspace_label@
action: manage
title: @caregivers_title@
description: @caregivers_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/companies/caregivers.php';
