<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:47:41
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Blocks direct HTTP access to documentation files.
 *
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

