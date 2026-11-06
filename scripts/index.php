<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:47:41
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Blocks direct HTTP access to administration scripts.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:index
 */

/**
 *
 * @file        index.php
 * @author      lm
 * @dateCreated Wed 2026-10-14 21:47:31
 * @dateLastMod Wed 2026-10-14 21:47:31
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
 **/

declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php';
}

