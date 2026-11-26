<?php
/**
 * @date_cre: Sat 2026-11-21 14:45:25
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Company-scoped completed dispensing history.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:dispensing-history
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 91347
sequence_number: 20
public_visibility: false
group: company
group_title: @workspace_label@
action: manage
title: @dispensing_history_title@
description: @dispensing_history_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/pharmacy/dispensing_history.php';
