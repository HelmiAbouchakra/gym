<!-- Header/Navbar Component -->
<?php
// Check if session is not already started before starting it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'member';

// Get base URL for correct path resolution - only set if not already defined
if (!isset($base_url)) {
    $base_url = '';
}

// Default profile image
$user_image = $base_url . 'assets/images/default-avatar.png';

// If user is logged in, get profile image from session or database
if ($is_logged_in) {
    // Check if profile image path is already in session to avoid database query
    if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
        $user_image = $base_url . $_SESSION['profile_image'];
    } else {
        // Only query database if image isn't in session
        require_once __DIR__ . '/../../config/db_config.php';
        
        try {
            $user_id = $_SESSION['user_id'];
            
            // Use the PDO connection from db_config.php
            if (isset($pdo)) {
                // Prepare and execute query to get user's profile image
                $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!empty($row['profile_image'])) {
                        // Save image path to session to avoid future database queries
                        $_SESSION['profile_image'] = $row['profile_image'];
                        $user_image = $base_url . $row['profile_image'];
                    }
                }
            }
        } catch (Exception $e) {
            // In case of database error, use default image
            $user_image = $base_url . 'assets/images/default-avatar.png';
        }
    }
}

// Get the current file name to highlight active menu items
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header>
    <div class="container">
        <nav class="navbar">
            <a href="<?php echo $base_url; ?>index.php" class="logo">Fit<span>Life</span></a>
            <ul class="nav-links">
                <li><a href="<?php echo $base_url; ?>index.php#features">Features</a></li>
                <li><a href="<?php echo $base_url; ?>index.php#membership">Memberships</a></li>
                <li><a href="<?php echo $base_url; ?>index.php#classes">Classes</a></li>
                <li><a href="<?php echo $base_url; ?>index.php#trainers">Trainers</a></li>
                <li><a href="<?php echo $base_url; ?>index.php#contact">Contact</a></li>
                
                <?php if ($is_logged_in && $user_role === 'admin'): ?>
                <li><a href="<?php echo $base_url; ?>admin/dashboard.php" class="admin-link"><i class="fas fa-tachometer-alt"></i> Admin</a></li>
                <?php endif; ?>
                
                <?php if ($is_logged_in && $user_role === 'trainer'): ?>
                <li><a href="<?php echo $base_url; ?>pages/trainer-dashboard.php" class="trainer-link"><i class="fas fa-clipboard"></i> Trainer</a></li>
                <?php endif; ?>
            </ul>
            <div class="auth-buttons">
                <?php if ($is_logged_in): ?>
                    <div class="user-menu">
                        <div class="user-profile">
                            <span class="username"><?php echo htmlspecialchars($username); ?></span>
                            <?php 
                                // Add cache-busting parameter only if the file exists and isn't the default avatar
                                $image_path = str_replace($base_url, '', $user_image);
                                $cache_param = '';
                                if (file_exists($image_path) && strpos($user_image, 'default-avatar.png') === false) {
                                    $cache_param = '?v=' . filemtime($image_path);
                                }
                            ?>
                            <img src="<?php echo htmlspecialchars($user_image . $cache_param); ?>" 
                                alt="Profile" 
                                class="profile-img" 
                                onerror="this.onerror=null; this.src='<?php echo $base_url; ?>assets/images/default-avatar.png';"
                                loading="lazy">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-menu">
                            <a href="<?php echo $base_url; ?>pages/profile.php"><i class="fas fa-user"></i> My Profile</a>
                            
                            <?php if ($user_role === 'member'): ?>
                                <a href="<?php echo $base_url; ?>pages/memberships.php"><i class="fas fa-id-card"></i> My Membership</a>
                                <a href="<?php echo $base_url; ?>pages/bookings.php"><i class="fas fa-calendar-check"></i> My Bookings</a>
                            <?php endif; ?>
                            
                            <?php if ($user_role === 'trainer'): ?>
                                <a href="<?php echo $base_url; ?>pages/trainer-dashboard.php"><i class="fas fa-clipboard"></i> Trainer Dashboard</a>
                                <a href="<?php echo $base_url; ?>pages/schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a>
                                <a href="<?php echo $base_url; ?>pages/clients.php"><i class="fas fa-users"></i> My Clients</a>
                                <a href="<?php echo $base_url; ?>pages/classes.php"><i class="fas fa-dumbbell"></i> My Classes</a>
                            <?php endif; ?>
                            
                            <?php if ($user_role === 'admin'): ?>
                                <a href="<?php echo $base_url; ?>admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a>
                                <a href="<?php echo $base_url; ?>admin/users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
                                <a href="<?php echo $base_url; ?>admin/memberships.php"><i class="fas fa-tags"></i> Manage Memberships</a>
                                <a href="<?php echo $base_url; ?>admin/classes.php"><i class="fas fa-dumbbell"></i> Manage Classes</a>
                                <a href="<?php echo $base_url; ?>admin/trainers.php"><i class="fas fa-user-tie"></i> Manage Trainers</a>
                                <a href="<?php echo $base_url; ?>admin/reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                            <?php endif; ?>
                            
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo $base_url; ?>actions/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>pages/login.php" class="btn btn-outline">Login</a>
                    <a href="<?php echo $base_url; ?>pages/register.php" class="btn">Join Now</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<!-- Add JavaScript for dropdown menu toggle -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userProfile = document.querySelector('.user-profile');
        if (userProfile) {
            userProfile.addEventListener('click', function() {
                const dropdownMenu = document.querySelector('.dropdown-menu');
                dropdownMenu.classList.toggle('show');
            });

            // Close the dropdown menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.user-menu')) {
                    const dropdownMenu = document.querySelector('.dropdown-menu');
                    if (dropdownMenu && dropdownMenu.classList.contains('show')) {
                        dropdownMenu.classList.remove('show');
                    }
                }
            });
        }
    });
</script>