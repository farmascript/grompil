<?php
/**
 * https://dw.grompil.com/!saas/doku.php?id=src:scripts:test_db_mysql
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