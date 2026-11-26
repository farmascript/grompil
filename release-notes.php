<?php
/**
 * @date_cre: Wed 2026-11-25
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Routes authenticated users to the release notes and notification preferences module.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:release-notes
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 72194
sequence_number: 21
public_visibility: false
group: platform
group_title: @nav_admin@
action: view
title: @release_notes_title@
description: @release_notes_intro@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/updates/release_notes.php';
