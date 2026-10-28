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
 * @description: Tests private configuration loading without database or external connections.
 *
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/database_config.php';
require_once dirname(__DIR__) . '/includes/kbo.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

/**
 * Assert a condition in the credentials configuration check.
 *
 * @param bool $condition The condition expected to be true.
 * @param string $message The assertion message used when the condition fails.
 * @return void
 */
function credentials_test_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Test failed: ' . $message);
    }
}

$variables = ['GROMPIL_CREDENTIALS_FILE', 'GROMPIL_DB_HOST', 'GROMPIL_DB_PASSWORD'];
$previous = [];
foreach ($variables as $variable) {
    $previous[$variable] = getenv($variable);
}
$previousServer = $_SERVER;
$fixture = tempnam(sys_get_temp_dir(), 'grompil-credentials-');
if ($fixture === false) {
    throw new RuntimeException('Unable to create a temporary configuration fixture.');
}

try {
    foreach ([
        'C:/usr/Dropbox/php/grompil' => 'C:/usr/credentials.php',
        '/home/account/public_html/grompil' => '/home/account/credentials.php',
        '/home/account/public_html/apps/grompil' => '/home/account/credentials.php',
        '/home/account/public_html' => '/home/account/credentials.php',
    ] as $projectRoot => $expectedPath) {
        $actualPath = str_replace('\\', '/', grompil_credentials_default_path($projectRoot));
        credentials_test_expect($actualPath === $expectedPath, 'default path matches the installation layout');
    }
    putenv('GROMPIL_CREDENTIALS_FILE');
    credentials_test_expect(
        grompil_credentials_path() === grompil_credentials_default_path(dirname(__DIR__)),
        'no environment override uses the installation layout'
    );

    $expected = [
        'database' => ['host' => '127.0.0.1', 'port' => '5432', 'name' => 'test',
            'user' => 'test', 'password' => "fake'password\\only"],
        'kbo' => ['username' => 'test-kbo', 'password' => 'fake-kbo'],
        'mail' => ['host' => 'smtp.example.test', 'port' => 587, 'username' => 'test-mail',
            'password' => 'fake-mail', 'encryption' => 'tls', 'from_email' => 'test@example.test',
            'from_name' => 'Test', 'reply_to' => 'reply@example.test'],
    ];
    file_put_contents($fixture, '<?php return ' . var_export($expected, true) . ';');
    putenv('GROMPIL_CREDENTIALS_FILE=' . $fixture);
    putenv('GROMPIL_DB_HOST=environment.example.test');
    putenv('GROMPIL_DB_PASSWORD=fake-environment');

    foreach ([
        ['SERVER_ADDR' => '127.0.0.1', 'REMOTE_ADDR' => '127.0.0.1'],
        ['SERVER_ADDR' => '203.0.113.1', 'REMOTE_ADDR' => '198.51.100.1'],
        ['SERVER_ADDR' => '203.0.113.1', 'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'localhost', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1'],
        [], // CLI has no HTTP request or server IP address.
    ] as $server) {
        $_SERVER = $server;
        credentials_test_expect(grompil_credentials_path() === $fixture, 'explicit private path is preserved');
        credentials_test_expect(grompil_credentials_config() === $expected, 'shared credentials are independent of request IPs');
        credentials_test_expect(grompil_database_config() === $expected['database'], 'database settings override environment fallbacks');
        credentials_test_expect(grompil_kbo_config() === $expected['kbo'], 'KBO uses the shared credentials');
        credentials_test_expect(grompil_credentials_config()['mail'] === $expected['mail'], 'mail settings are preserved');
    }
    credentials_test_expect(str_contains((string) grompil_database_connection_string(), "password='fake\\'password\\\\only'"), 'database password escaping is preserved');

    file_put_contents($fixture, '<?php return "invalid";');
    try {
        grompil_credentials_config();
        throw new RuntimeException('An invalid credentials file was accepted.');
    } catch (RuntimeException $error) {
        credentials_test_expect($error->getMessage() === 'The credentials file has an invalid format.', 'invalid file is rejected');
    }

    putenv('GROMPIL_CREDENTIALS_FILE=' . $fixture . '-missing');
    credentials_test_expect(grompil_credentials_config() === [], 'missing private file has no credentials');
    credentials_test_expect(grompil_kbo_config() === [], 'missing KBO configuration remains optional');
    credentials_test_expect(grompil_database_config()['host'] === 'environment.example.test', 'database environment fallback works');
    putenv('GROMPIL_DB_PASSWORD');
    credentials_test_expect(grompil_database_connection_string() === null, 'missing database password has no connection string');
    echo "Credentials configuration checks passed; no external connections were made.\n";
} finally {
    unlink($fixture);
    foreach ($previous as $variable => $value) {
        putenv($value === false ? $variable : $variable . '=' . $value);
    }
    $_SERVER = $previousServer;
}
