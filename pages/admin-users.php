<?php
// Admin Users Management
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
$pdo = getConnection();
$base_url = '../';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $user_id = $_POST['user_id'];
                    $current_status = $_POST['current_status'];
                    $new_status = $current_status === 'active' ? 'inactive' : 'active';
                    
                    // For now, we'll use a simple approach since there's no status column
                    // In a real system, you'd add a status column to users table
                    $success_message = "User status would be updated to: $new_status";
                    break;
                    
                case 'delete_user':
                    $user_id = $_POST['user_id'];
                    
                    // Don't allow deleting the current admin
                    if ($user_id == $_SESSION['user_id']) {
                        $error_message = "You cannot delete your own account.";
                        break;
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Delete related records first
                    $pdo->prepare("DELETE FROM class_bookings WHERE user_id = ?")->execute([$user_id]);
                    $pdo->prepare("DELETE FROM user_memberships WHERE user_id = ?")->execute([$user_id]);
                    $pdo->prepare("DELETE FROM trainers WHERE user_id = ?")->execute([$user_id]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                    
                    $pdo->commit();
                    $success_message = "User deleted successfully.";
                    break;
                    
                case 'create_user':
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $password = $_POST['password'];
                    $role = $_POST['role'];
                    
                    if (empty($username) || empty($email) || empty($password)) {
                        $error_message = "All fields are required.";
                        break;
                    }
                    
                    // Check if username or email already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $check_stmt->execute([$username, $email]);
                    if ($check_stmt->fetch()) {
                        $error_message = "Username or email already exists.";
                        break;
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $create_stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                    $create_stmt->execute([$username, $email, $hashed_password, $role]);
                    
                    $success_message = "User created successfully.";
                    break;
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all users with additional info
try {
    $users_query = "
        SELECT 
            u.*,
            t.name as trainer_name,
            (SELECT COUNT(*) FROM user_memberships um WHERE um.user_id = u.id) as membership_count,
            (SELECT COUNT(*) FROM class_bookings cb WHERE cb.user_id = u.id) as booking_count,
            (SELECT MAX(created_at) FROM class_bookings cb WHERE cb.user_id = u.id) as last_booking
        FROM users u
        LEFT JOIN trainers t ON u.id = t.user_id
        ORDER BY u.created_at DESC
    ";
    
    $users_stmt = $pdo->query($users_query);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
            COUNT(CASE WHEN role = 'trainer' THEN 1 END) as trainer_count,
            COUNT(CASE WHEN role = 'member' THEN 1 END) as member_count,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
        FROM users
    ";
    
    $stats_stmt = $pdo->query($stats_query);
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - FitLife Gym Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-users"></i> User Management</h1>
                <p>Manage all system users, roles, and permissions</p>
            </div>
            <div>
                <a href="admin-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button onclick="openCreateModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h2 class="stat-number"><?php echo $user_stats['total_users'] ?? 0; ?></h2>
                <p class="stat-label">Total Users</p>
            </div>
            <div class="stat-card admin">
                <h2 class="stat-number"><?php echo $user_stats['admin_count'] ?? 0; ?></h2>
                <p class="stat-label">Administrators</p>
            </div>
            <div class="stat-card trainer">
                <h2 class="stat-number"><?php echo $user_stats['trainer_count'] ?? 0; ?></h2>
                <p class="stat-label">Trainers</p>
            </div>
            <div class="stat-card member">
                <h2 class="stat-number"><?php echo $user_stats['member_count'] ?? 0; ?></h2>
                <p class="stat-label">Members</p>
            </div>
            <div class="stat-card new">
                <h2 class="stat-number"><?php echo $user_stats['new_users_30d'] ?? 0; ?></h2>
                <p class="stat-label">New (30 days)</p>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="users-section">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> All Users</h3>
                <div>
                    <input type="text" id="searchUsers" placeholder="Search users..." style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            
            <?php if (empty($users)): ?>
                <p>No users found.</p>
            <?php else: ?>
                <table class="admin-table users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>USER</th>
                            <th>ROLE</th>
                            <th>EMAIL</th>
                            <th>JOINED</th>
                            <th>ACTIVITY</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo !empty($user['profile_image']) ? $base_url . $user['profile_image'] : $base_url . 'assets/images/default-avatar.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
                                        <div class="user-details">
                                            <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                                            <?php if ($user['trainer_name']): ?>
                                                <p>Trainer: <?php echo htmlspecialchars($user['trainer_name']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <small>
                                        <?php echo $user['membership_count']; ?> memberships<br>
                                        <?php echo $user['booking_count']; ?> bookings
                                        <?php if ($user['last_booking']): ?>
                                            <br>Last: <?php echo date('M j', strtotime($user['last_booking'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-primary" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New User</h3>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="member">Member</option>
                        <option value="trainer">Trainer</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        // Modal functions
        function openCreateModal() {
            document.getElementById('createUserModal').style.display = 'block';
        }
        
        function closeCreateModal() {
            document.getElementById('createUserModal').style.display = 'none';
        }
        
        // Search functionality
        document.getElementById('searchUsers').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            }
        });
        
        // Edit user function (placeholder)
        function editUser(userId) {
            alert('Edit user functionality would be implemented here for user ID: ' + userId);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createUserModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
