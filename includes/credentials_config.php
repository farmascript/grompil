<?php
/**
 *
 * @date_cre: Fri 2026-10-23
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Selects the deployment-specific private credentials for database, KBO and mail.
 *
 */

declare(strict_types=1);

/**
 * Read and validate the deployment settings stored with this installation.
 *
 * @param string $projectRoot The application project root directory.
 * @return array
 */
function grompil_deployment_settings(string $projectRoot): array
{
    $settingsPath = rtrim($projectRoot, '/\\')
        . DIRECTORY_SEPARATOR . 'settings_local.php';
    if (!is_file($settingsPath)) {
        throw new RuntimeException('The local deployment settings file is missing.');
    }

    $settings = require $settingsPath;
    if (!is_array($settings)) {
        throw new RuntimeException('The local deployment settings file has an invalid format.');
    }

    return $settings;
}

/**
 * Read the validated deployment location stored with this installation.
 *
 * @param string $projectRoot The application project root directory.
 * @return string
 */
function grompil_deployment_location(string $projectRoot): string
{
    $settings = grompil_deployment_settings($projectRoot);

    $location = $settings['location'] ?? null;
    if (!is_string($location) || !in_array($location, ['local', 'test', 'production'], true)) {
        throw new RuntimeException('The local deployment location is missing or invalid.');
    }

    return $location;
}

/**
 *  Resolve the selected private credentials file above the web root.
 *
 * @param string $projectRoot The application project root directory.
 * @return string
 */
function grompil_credentials_default_path(string $projectRoot): string
{
    $location = grompil_deployment_location($projectRoot);
    $fileName = match ($location) {
        'local' => 'credentials_local.php',
        'test' => 'credentials_db_test.php',
        'production' => 'credentials_db_production.php',
    };

    $directory = rtrim($projectRoot, '/\\');
    while (dirname($directory) !== $directory) {
        if (in_array(basename($directory), ['public_html', 'grompil_test'], true)) {
            return dirname($directory) . DIRECTORY_SEPARATOR . $fileName;
        }
        $directory = dirname($directory);
    }

    return dirname($projectRoot, 3) . DIRECTORY_SEPARATOR . $fileName;
}

/**
 *  Resolve credentials from the marker, never from request headers or IP addresses.
 *
 * @return string
 */
function grompil_credentials_path(): string
{
    $projectRoot = defined('GROMPIL_ROOT') ? GROMPIL_ROOT : dirname(__DIR__);
    return grompil_credentials_default_path($projectRoot);
}

/**
 *  Read the selected credentials array for web requests and CLI commands.
 *
 * @return array
 */
function grompil_credentials_config(): array
{
    $path = grompil_credentials_path();
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('The selected private credentials file is missing or unreadable.');
    }
    $credentials = require $path;
    if (!is_array($credentials)) {
        throw new RuntimeException('The credentials file has an invalid format.');
    }
    return $credentials;
}
