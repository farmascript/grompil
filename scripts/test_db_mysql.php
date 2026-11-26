<?php
/**
 * @date_cre: Thu 2026-10-29 15:12:14
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages test db mysql.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:test_db_mysql
 */
try {
$dsn = 'mysql:host=127.0.0.1;port=5432;dbname=test_gro1_test;charset=utf8mb4';
$pdo = new PDO($dsn, 'test_gro1_grompil_app', 'Apo?Grompil!2600', [
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
echo 'Connected successfully to MySQL!';
} catch (PDOException $e) {
echo 'Connection failed: ' . $e->getMessage();
}