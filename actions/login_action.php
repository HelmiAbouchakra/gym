<?php
// Start session
session_start();

// Include database connection file
require_once __DIR__ . '/../includes/db_connect.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    // Validate input
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: ../pages/login.php");
        exit();
    }

    try {
        // Connect to database using the constants from db_config.php
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepare and execute query
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->execute();

        // Check if user exists
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password - try both hashed and plain text comparison
            // This handles both new users (with hashed passwords) and existing users (if they have plain text passwords)
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                // Set user session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect all users to index.php regardless of role
                header("Location: ../index.php");
                exit();
            } else {
                $_SESSION['error'] = "Invalid email or password";
                header("Location: ../pages/login.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid email or password";
            header("Location: ../pages/login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Login failed: " . $e->getMessage();
        header("Location: ../pages/login.php");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: ../pages/login.php");
    exit();
}
