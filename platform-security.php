<?php
/**
 * Loads the platform administrator IP access module.
 * https://dw.grompil.com/!saas/doku.php?id=broncode:platform-security
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

require GROMPIL_ROOT . '/src/admin/platform_security.php';
