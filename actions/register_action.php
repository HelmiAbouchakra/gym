<?php
// Start session
session_start();

// Include database connection - FIXED PATH to match your folder structure
require_once __DIR__ . '/../includes/db_connect.php';

// Define upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/profiles/');

// Check if form is submitted
if (isset($_POST['register'])) {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Initialize errors array
    $errors = [];

    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Username must be between 3 and 50 characters";
    }

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Process profile image if uploaded
    $profile_image_path = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Check file extension
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = "Only JPG, JPEG, PNG and GIF files are allowed";
        }

        // Create upload directory if it doesn't exist
        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0777, true);
        }

        // Generate unique filename
        $new_file_name = uniqid('profile_') . '.' . $file_ext;
        $upload_path = UPLOAD_DIR . $new_file_name;

        // Move uploaded file
        if (empty($errors)) {
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Store relative path to database
                $profile_image_path = 'uploads/profiles/' . $new_file_name;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }

    // If no errors, insert user into database
    if (empty($errors)) {
        try {
            $conn = getConnection();

            // Check if username or email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->execute([$username, $email]);

            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Username or email already exists";
            } else {
                // Hash password for encryption
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user with default role 'member'
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, profile_image) VALUES (?, ?, ?, 'member', ?)");
                $stmt->execute([$username, $hashed_password, $email, $profile_image_path]);

                // Set success message
                $_SESSION['success_message'] = "Registration successful! You can now login.";

                // Redirect to login page
                header("Location: ../pages/login.php");
                exit();
            }
        } catch (PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }

    // If there are errors, store them in session and redirect back to registration form
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = [
            'username' => $username,
            'email' => $email
        ];
        header("Location: ../pages/register.php");
        exit();
    }
}

// If not submitted via POST, redirect to registration form
header("Location: ../pages/register.php");
exit();
?>
