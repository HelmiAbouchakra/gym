<?php
// Include database configuration
require_once __DIR__ . '/../config/db_config.php';

// Function to get database connection
function getConnection() {
    try {
        // Create connection using config constants
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        
        // Set PDO error mode to exception
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Set default fetch mode to associative array
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Disable emulation of prepared statements
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        return $pdo;
    } catch (PDOException $e) {
        // Log error and return false
        error_log($e->getMessage());
        return false;
    }
}
?>