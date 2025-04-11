<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/db_config.php';

// Base URL for correct path resolution
$base_url = '../';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? 'member';

// Fetch additional user data from database
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Process form submission for profile update
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get and sanitize form data
    $new_username = trim($_POST['username']);
    $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validation
    if (empty($new_username) || empty($new_email)) {
        $error_message = "Username and email are required";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = "New passwords do not match";
    } else {
        try {
            // Verify current password if changing password
            $password_verified = false;
            if (!empty($current_password)) {
                $password_verified = password_verify($current_password, $user['password']) || $current_password === $user['password'];
                if (!$password_verified) {
                    $error_message = "Current password is incorrect";
                }
            } else {
                $password_verified = true; // Not changing password
            }
            
            if ($password_verified) {
                // Start building the update query
                $update_fields = [];
                $params = [];
                
                // Add username and email to update
                if ($new_username !== $user['username']) {
                    $update_fields[] = "username = ?";
                    $params[] = $new_username;
                }
                
                if ($new_email !== $user['email']) {
                    $update_fields[] = "email = ?";
                    $params[] = $new_email;
                }
                
                // Add password to update if provided
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_fields[] = "password = ?";
                    $params[] = $hashed_password;
                }
                
                // Handle profile image upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
                        $error_message = "Only JPG, PNG and GIF images are allowed";
                    } elseif ($_FILES['profile_image']['size'] > $max_size) {
                        $error_message = "Image size must be less than 2MB";
                    } else {
                        $upload_dir = __DIR__ . '/../uploads/profiles/';
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $filename = $user_id . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
                        $target_file = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                            $profile_image_path = 'uploads/profiles/' . $filename;
                            $update_fields[] = "profile_image = ?";
                            $params[] = $profile_image_path;
                            
                            // Update session with the new profile image path
                            $_SESSION['profile_image'] = $profile_image_path;
                            
                            // If browser caching is causing issues, we can add a timestamp to force refresh
                            $_SESSION['profile_image_updated'] = time();
                        } else {
                            $error_message = "Failed to upload image";
                        }
                    }
                }
                
                // Update user in database if there are fields to update
                if (!empty($update_fields) && empty($error_message)) {
                    $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
                    $params[] = $user_id;
                    
                    $update_stmt = $pdo->prepare($sql);
                    $result = $update_stmt->execute($params);
                    
                    if ($result) {
                        $success_message = "Profile updated successfully";
                        
                        // Update session variables
                        if ($new_username !== $user['username']) {
                            $_SESSION['username'] = $new_username;
                        }
                        
                        // Force refresh user data in session for ALL profile changes
                        try {
                            $refresh_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                            $refresh_stmt->execute([$user_id]);
                            $fresh_user_data = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($fresh_user_data) {
                                // Update profile image in session if it exists
                                if (!empty($fresh_user_data['profile_image'])) {
                                    $_SESSION['profile_image'] = $fresh_user_data['profile_image'];
                                }
                            }
                        } catch (Exception $e) {
                            // Silent fail - not critical
                        }
                        
                        // Redirect to refresh the page and show updated info
                        header("Location: profile.php?success=1");
                        exit();
                    } else {
                        $error_message = "Failed to update profile";
                    }
                } elseif (empty($update_fields) && empty($error_message)) {
                    $success_message = "No changes to update";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Set success message from query string if present
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success_message = "Profile updated successfully";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FitLife Gym</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin:0px auto 50px;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-image-container {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 30px;
            border: 3px solid var(--primary);
        }
        
        .profile-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info h1 {
            margin-bottom: 5px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
        }
        
        .role-admin {
            background-color: #dc3545;
        }
        
        .role-trainer {
            background-color: #28a745;
        }
        
        .role-member {
            background-color: var(--primary);
        }
        
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid #e1e1e1;
            margin-bottom: 20px;
        }
        
        .profile-tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            position: relative;
        }
        
        .profile-tab.active {
            color: var(--primary);
        }
        
        .profile-tab.active:after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .password-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="profile-container">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div class="profile-image-container">
                <img src="<?php echo !empty($user['profile_image']) ? $base_url . $user['profile_image'] : $base_url . 'assets/images/default-avatar.png'; ?>" alt="Profile Image">
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="role-badge role-<?php echo strtolower($user['role']); ?>">
                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                </div>
            </div>
        </div>
        
        <div class="profile-tabs">
            <div class="profile-tab active" data-tab="edit-profile">Edit Profile</div>
            <div class="profile-tab" data-tab="membership">
                <?php if ($role === 'member'): ?>
                    My Membership
                <?php elseif ($role === 'trainer'): ?>
                    Trainer Info
                <?php else: ?>
                    Account Details
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tab-content active" id="edit-profile">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="profile_image">Profile Image</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*">
                    <small>Upload a new profile image (JPG, PNG or GIF, max 2MB)</small>
                </div>
                
                <div class="password-section">
                    <h3>Change Password</h3>
                    <p>Leave blank if you don't want to change your password</p>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                </div>
                
                <button type="submit" name="update_profile" class="btn">Update Profile</button>
            </form>
        </div>
        
        <div class="tab-content" id="membership">
            <?php if ($role === 'member'): ?>
                <h2>Membership Information</h2>
                <?php
                // Fetch membership information for this user
                try {
                    $membership_stmt = $pdo->prepare("
                        SELECT um.*, mp.name as plan_name, mp.description, mp.price 
                        FROM user_memberships um
                        JOIN membership_plans mp ON um.plan_id = mp.id
                        WHERE um.user_id = ? AND um.status = 'active'
                        ORDER BY um.end_date DESC
                        LIMIT 1
                    ");
                    $membership_stmt->execute([$user_id]);
                    $membership = $membership_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($membership): ?>
                        <div class="membership-info">
                            <p><strong>Plan:</strong> <?php echo htmlspecialchars($membership['plan_name']); ?></p>
                            <p><strong>Start Date:</strong> <?php echo htmlspecialchars($membership['start_date']); ?></p>
                            <p><strong>End Date:</strong> <?php echo htmlspecialchars($membership['end_date']); ?></p>
                            <p><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($membership['status'])); ?></p>
                            <p><strong>Payment Status:</strong> <?php echo ucfirst(htmlspecialchars($membership['payment_status'])); ?></p>
                        </div>
                        <a href="memberships.php" class="btn btn-outline">View All Memberships</a>
                    <?php else: ?>
                        <p>You don't have an active membership.</p>
                        <a href="memberships.php" class="btn">Get a Membership</a>
                    <?php endif;
                } catch (PDOException $e) {
                    echo "<p>Could not retrieve membership information. Please try again later.</p>";
                }
                ?>
            <?php elseif ($role === 'trainer'): ?>
                <h2>Trainer Information</h2>
                <?php
                // Fetch trainer information
                try {
                    $trainer_stmt = $pdo->prepare("SELECT * FROM trainers WHERE user_id = ?");
                    $trainer_stmt->execute([$user_id]);
                    $trainer = $trainer_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($trainer): ?>
                        <div class="trainer-info">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($trainer['name']); ?></p>
                            <p><strong>Bio:</strong> <?php echo htmlspecialchars($trainer['bio']); ?></p>
                            <p><strong>Specialties:</strong> <?php echo htmlspecialchars($trainer['specialties']); ?></p>
                        </div>
                        <a href="trainer-dashboard.php" class="btn">Go to Trainer Dashboard</a>
                    <?php else: ?>
                        <p>Your trainer profile is not complete. Please contact an administrator.</p>
                    <?php endif;
                } catch (PDOException $e) {
                    echo "<p>Could not retrieve trainer information. Please try again later.</p>";
                }
                ?>
            <?php else: ?>
                <h2>Account Details</h2>
                <p><strong>Account Type:</strong> <?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
                <p><strong>Joined:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                
                <?php if ($role === 'admin'): ?>
                    <a href="admin/dashboard.php" class="btn">Go to Admin Dashboard</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabs = document.querySelectorAll('.profile-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and content
                    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html> 