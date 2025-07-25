<?php
// Membership Diagnostic Page
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
$pdo = getConnection();
$user_id = $_SESSION['user_id'];

try {
    // Get user info
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get membership info
    $membership_stmt = $pdo->prepare("
        SELECT 
            um.*,
            mp.name as plan_name,
            mp.description as plan_description,
            t.name as trainer_name,
            t.specialties as trainer_specialties
        FROM user_memberships um
        JOIN membership_plans mp ON um.plan_id = mp.id
        LEFT JOIN trainers t ON um.trainer_id = t.id
        WHERE um.user_id = ?
        ORDER BY um.created_at DESC
    ");
    $membership_stmt->execute([$user_id]);
    $memberships = $membership_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available trainers
    $trainers_stmt = $pdo->prepare("
        SELECT t.*, u.username, u.email
        FROM trainers t
        JOIN users u ON t.user_id = u.id
        WHERE t.is_active = 1
    ");
    $trainers_stmt->execute();
    $trainers = $trainers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle trainer assignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_trainer'])) {
        $membership_id = $_POST['membership_id'];
        $trainer_id = $_POST['trainer_id'];
        
        $update_stmt = $pdo->prepare("UPDATE user_memberships SET trainer_id = ? WHERE id = ? AND user_id = ?");
        $update_stmt->execute([$trainer_id, $membership_id, $user_id]);
        
        $success_message = "Trainer assigned successfully!";
        
        // Refresh data
        $membership_stmt->execute([$user_id]);
        $memberships = $membership_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Handle status activation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_membership'])) {
        $membership_id = $_POST['membership_id'];
        
        $activate_stmt = $pdo->prepare("UPDATE user_memberships SET status = 'active' WHERE id = ? AND user_id = ?");
        $activate_stmt->execute([$membership_id, $user_id]);
        
        $success_message = "Membership activated successfully!";
        
        // Refresh data
        $membership_stmt->execute([$user_id]);
        $memberships = $membership_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Status - FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .status-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-good { border-left: 5px solid #28a745; }
        .status-warning { border-left: 5px solid #ffc107; }
        .status-error { border-left: 5px solid #dc3545; }
        .membership-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 5px;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="container">
        <h1><i class="fas fa-user-check"></i> Membership Status Check</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- User Info -->
        <div class="status-card status-good">
            <h3><i class="fas fa-user"></i> User Information</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
        </div>
        
        <!-- Memberships -->
        <div class="status-card <?php echo empty($memberships) ? 'status-error' : 'status-good'; ?>">
            <h3><i class="fas fa-id-card"></i> Your Memberships</h3>
            
            <?php if (empty($memberships)): ?>
                <p class="text-danger">❌ No memberships found. Please register for a membership plan.</p>
                <a href="memberships.php" class="btn">Register for Membership</a>
            <?php else: ?>
                <?php foreach ($memberships as $membership): ?>
                    <div class="membership-item">
                        <h4><?php echo htmlspecialchars($membership['plan_name']); ?></h4>
                        <p><strong>Status:</strong> 
                            <span class="badge badge-<?php echo $membership['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($membership['status']); ?>
                            </span>
                        </p>
                        <p><strong>Period:</strong> <?php echo $membership['start_date']; ?> to <?php echo $membership['end_date']; ?></p>
                        
                        <?php if ($membership['trainer_id']): ?>
                            <p><strong>Assigned Trainer:</strong> <?php echo htmlspecialchars($membership['trainer_name']); ?></p>
                            <?php if ($membership['trainer_specialties']): ?>
                                <p><strong>Specialties:</strong> <?php echo htmlspecialchars($membership['trainer_specialties']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-warning">⚠️ No trainer assigned</p>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="membership_id" value="<?php echo $membership['id']; ?>">
                                <select name="trainer_id" required>
                                    <option value="">Select a trainer...</option>
                                    <?php foreach ($trainers as $trainer): ?>
                                        <option value="<?php echo $trainer['id']; ?>">
                                            <?php echo htmlspecialchars($trainer['name']); ?>
                                            <?php if ($trainer['specialties']): ?>
                                                - <?php echo htmlspecialchars($trainer['specialties']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="assign_trainer" class="btn btn-success">Assign Trainer</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($membership['status'] !== 'active'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="membership_id" value="<?php echo $membership['id']; ?>">
                                <button type="submit" name="activate_membership" class="btn btn-warning">Activate Membership</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Available Trainers -->
        <div class="status-card">
            <h3><i class="fas fa-users"></i> Available Trainers</h3>
            <?php if (empty($trainers)): ?>
                <p>No active trainers available.</p>
            <?php else: ?>
                <?php foreach ($trainers as $trainer): ?>
                    <div class="membership-item">
                        <h4><?php echo htmlspecialchars($trainer['name']); ?></h4>
                        <?php if ($trainer['specialties']): ?>
                            <p><strong>Specialties:</strong> <?php echo htmlspecialchars($trainer['specialties']); ?></p>
                        <?php endif; ?>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($trainer['email']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="status-card">
            <h3><i class="fas fa-tools"></i> Quick Actions</h3>
            <a href="upcoming-classes.php" class="btn">View Classes</a>
            <a href="memberships.php" class="btn">Membership Plans</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin-trainers.php" class="btn">Manage Trainers</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
