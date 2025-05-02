<?php
// Simple utility to check uploads directory structure
// Access this file directly to see output

// Define paths to check
$paths = [
    'uploads/',
    'uploads/profiles/',
    'assets/images/'
];

echo "<h1>Directory Check</h1>";

// Check each directory
foreach ($paths as $path) {
    echo "<h2>Path: {$path}</h2>";
    
    // Check if directory exists
    if (file_exists($path)) {
        echo "<p>✅ Directory exists</p>";
        
        // Check if it's readable
        if (is_readable($path)) {
            echo "<p>✅ Directory is readable</p>";
        } else {
            echo "<p>❌ Directory is not readable</p>";
        }
        
        // Check if it's writable
        if (is_writable($path)) {
            echo "<p>✅ Directory is writable</p>";
        } else {
            echo "<p>❌ Directory is not writable</p>";
        }
        
        // List files in directory
        echo "<h3>Files in directory:</h3>";
        echo "<ul>";
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                echo "<li>{$file} (" . filesize($path . $file) . " bytes)</li>";
            }
        }
        echo "</ul>";
        
    } else {
        echo "<p>❌ Directory does not exist</p>";
    }
    
    echo "<hr>";
}

// Check default avatar
$default_avatar = 'assets/images/default-avatar.png';
echo "<h2>Default Avatar Check</h2>";
if (file_exists($default_avatar)) {
    echo "<p>✅ Default avatar exists</p>";
    echo "<p>Path: " . realpath($default_avatar) . "</p>";
    echo "<p>Size: " . filesize($default_avatar) . " bytes</p>";
    echo "<img src='/{$default_avatar}' width='100' height='100' alt='Default Avatar'>";
} else {
    echo "<p>❌ Default avatar does not exist at path: {$default_avatar}</p>";
}

// Database configuration check
echo "<h2>Database Configuration</h2>";
if (file_exists('config/db_config.php')) {
    echo "<p>✅ Database configuration file exists</p>";
    
    // Include DB config
    require_once 'config/db_config.php';
    
    try {
        // Try to connect to the database
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
            echo "<p>✅ Database constants are defined</p>";
            
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                echo "<p>✅ Successfully connected to the database</p>";
                
                // Check users table
                $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
                if ($stmt->rowCount() > 0) {
                    echo "<p>✅ Users table exists</p>";
                    
                    // Check a sample user
                    $stmt = $pdo->query("SELECT id, username, profile_image FROM users LIMIT 1");
                    if ($stmt->rowCount() > 0) {
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo "<p>Sample user: " . htmlspecialchars(json_encode($user)) . "</p>";
                    } else {
                        echo "<p>❌ No users found in database</p>";
                    }
                } else {
                    echo "<p>❌ Users table does not exist</p>";
                }
            } catch (PDOException $e) {
                echo "<p>❌ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p>❌ Database constants are not properly defined</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error loading database configuration: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>❌ Database configuration file does not exist</p>";
}

echo "<p><em>This file is for diagnostic purposes only and should be removed in production.</em></p>";
?> 