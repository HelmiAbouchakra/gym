<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../pages/forgot_password.php");
        exit;
    }
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: ../pages/forgot_password.php");
        exit;
    }
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "New password must be at least 8 characters long.";
        header("Location: ../pages/forgot_password.php");
        exit;
    }
    try {
        if (!isset($_SESSION['user_email'])) {

            $_SESSION['error'] = "Please login first.";
            header("Location: ../pages/login.php");
            exit;
        }
        $email = $_SESSION['user_email'];
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($current_password, $user['password'])) {
            $_SESSION['error'] = "Current password is incorrect.";
            header("Location: ../pages/forgot_password.php");
            exit;
        }
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'password' => $hashedPassword,
            'email' => $email
        ]);
        $_SESSION['success_message'] = "Your password has been successfully changed.";
        header("Location: ../pages/profile.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "An error occurred while changing your password. Please try again later.";
        header("Location: ../pages/forgot_password.php");
        exit;
    }
} else {
  header("Location: ../pages/forgot_password.php");
    exit;
}
