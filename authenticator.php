<?php
/**
 * Loads the platform administrator authenticator challenge.
 * https://dw.grompil.com/!saas/doku.php?id=broncode:authenticator
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

require GROMPIL_ROOT . '/src/account/authenticator.php';
