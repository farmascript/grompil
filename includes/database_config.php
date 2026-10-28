<?php
/**
 *
 * @date_cre: Wed 2026-10-14 22:47:17
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads PostgreSQL settings from the selected private credentials file.
 *
 */

declare(strict_types=1);

require_once __DIR__ . '/credentials_config.php';

/**
 *  Returns database settings from the credentials file selected for this installation.
 *
 * @return array
 */
function grompil_database_config(): array
{
    $config = [
        'host' => '127.0.0.1',
        'port' => '5432',
        'name' => 'grompil',
        'user' => 'grompil_app',
        'password' => '',
    ];
    $credentials = grompil_credentials_config();
    if (!is_array($credentials['database'] ?? null)) {
        throw new RuntimeException('The credentials file has an invalid format.');
    }
    foreach ($credentials['database'] as $key => $value) {
        if (array_key_exists($key, $config) && $value !== '' && $value !== null) {
            $config[$key] = (string) $value;
        }
    }
    return $config;
}

/**
 *  Returns a PostgreSQL connection string or null when no password is set.
 *
 * @return ?string
 */
function grompil_database_connection_string(): ?string
{
    $config = grompil_database_config();
    if ($config['password'] === '') {
        return null;
    }
    return sprintf(
        "host='%s' port='%s' dbname='%s' user='%s' password='%s' connect_timeout=5",
        addcslashes($config['host'], "\\'"),
        addcslashes($config['port'], "\\'"),
        addcslashes($config['name'], "\\'"),
        addcslashes($config['user'], "\\'"),
        addcslashes($config['password'], "\\'")
    );
}

