<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gym');
define('DB_CHARSET', 'utf8mb4');
define('DB_DSN', "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET);

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);

    return $pdo;

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
