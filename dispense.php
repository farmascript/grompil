<?php
/**
 *
 * @date_cre: Thu 2026-11-12 11:07:02
 * @date_mut: Thu 2026-11-12 14:15:48
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Company-scoped pharmacy dispensing workbench entry point.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:dispense
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 74269
sequence_number: 17
public_visibility: false
group: company
group_title: @workspace_label@
action: manage
title: @dispense_title@
description: @dispense_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/pharmacy/dispensing.php';
