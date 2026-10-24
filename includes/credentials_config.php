<?php
/**
 *
 * @date_cre: Fri 2026-10-23
 * @date_mut: Fri 2026-10-23
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the shared private credentials for database, KBO and mail.
 *
 */

declare(strict_types=1);

/** Keep hosting credentials above public_html and preserve the existing local path. */
function grompil_credentials_default_path(string $projectRoot): string
{
    $directory = $projectRoot;
    while (dirname($directory) !== $directory) {
        if (basename($directory) === 'public_html') {
            return dirname($directory) . DIRECTORY_SEPARATOR . 'credentials.php';
        }
        $directory = dirname($directory);
    }
    return dirname($projectRoot, 3) . DIRECTORY_SEPARATOR . 'credentials.php';
}

/** Resolve the private credentials path independently of HTTP request data. */
function grompil_credentials_path(): string
{
    return getenv('GROMPIL_CREDENTIALS_FILE')
        ?: grompil_credentials_default_path(dirname(__DIR__));
}

/** Read the same credentials format for web requests and CLI commands. */
function grompil_credentials_config(): array
{
    $path = grompil_credentials_path();
    if (!is_file($path)) {
        return [];
    }
    $credentials = require $path;
    if (!is_array($credentials)) {
        throw new RuntimeException('The credentials file has an invalid format.');
    }
    return $credentials;
}
