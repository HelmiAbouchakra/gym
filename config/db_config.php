<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gym_university');
define('DB_CHARSET', 'utf8mb4');
define('DB_DSN', "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET);

try {
    // Create connection without database
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    
    // Read and execute the schema file
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);
    
    return $pdo;
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>