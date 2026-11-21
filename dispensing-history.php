<?php
/**
 * Company-scoped completed dispensing history.
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
