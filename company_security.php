<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:50
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the company access control module.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

/*
@NAV
id: 58731
sequence_number: 11
group: company
group_title: @workspace_label@
action: manage
title: @company_permission_settings@
@ENDNAV
*/

require GROMPIL_ROOT . '/src/companies/company_security.php';

