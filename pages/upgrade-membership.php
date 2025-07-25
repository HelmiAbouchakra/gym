<?php
// Upgrade Membership Page
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
$pdo = getConnection();
$base_url = '../';

$user_role = $_SESSION['role'] ?? 'member';
$user_id = $_SESSION['user_id'];

// Initialize variables
$success_message = '';
$error_message = '';
$current_membership = null;
$available_plans = [];

// Get user's current membership
try {
    $current_stmt = $pdo->prepare("
        SELECT 
            um.*,
            mp.name as plan_name,
            mp.description as plan_description,
            mp.price as plan_price,
            mp.duration,
            mp.features,
            mp.id as plan_id
        FROM user_memberships um
        JOIN membership_plans mp ON um.plan_id = mp.id
        WHERE um.user_id = ? AND um.status = 'active'
        ORDER BY um.created_at DESC
        LIMIT 1
    ");
    $current_stmt->execute([$user_id]);
    $current_membership = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_membership) {
        header("Location: my-membership.php?error=no_active_membership");
        exit();
    }
    
    // Get available plans that are more expensive than current plan (upgrades only)
    $plans_stmt = $pdo->prepare("
        SELECT * FROM membership_plans 
        WHERE is_active = 1 AND price > ? AND id != ?
        ORDER BY price ASC
    ");
    $plans_stmt->execute([$current_membership['plan_price'], $current_membership['plan_id']]);
    $available_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading membership data: " . $e->getMessage();
}

// Handle upgrade request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_plan'])) {
    $new_plan_id = $_POST['new_plan_id'] ?? '';
    
    if (empty($new_plan_id)) {
        $error_message = "Please select a plan to upgrade to.";
    } else {
        try {
            // Get the new plan details
            $new_plan_stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE id = ? AND is_active = 1");
            $new_plan_stmt->execute([$new_plan_id]);
            $new_plan = $new_plan_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$new_plan) {
                $error_message = "Selected plan not found.";
            } elseif ($new_plan['price'] <= $current_membership['plan_price']) {
                $error_message = "You can only upgrade to a higher-tier plan.";
            } else {
                // Start transaction
                $pdo->beginTransaction();
                
                // Update current membership to upgraded
                $cancel_stmt = $pdo->prepare("
                    UPDATE user_memberships 
                    SET status = 'upgraded' 
                    WHERE id = ?
                ");
                $cancel_stmt->execute([$current_membership['id']]);
                
                // Create new membership with upgraded plan
                $create_stmt = $pdo->prepare("
                    INSERT INTO user_memberships 
                    (user_id, plan_id, start_date, end_date, status, payment_status)
                    VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'active', 'paid')
                ");
                $create_stmt->execute([
                    $user_id,
                    $new_plan_id,
                    $new_plan['duration']
                ]);
                
                // Log the upgrade activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description)
                    VALUES (?, 'membership_upgrade', ?)
                ");
                $log_stmt->execute([
                    $user_id,
                    "Upgraded from {$current_membership['plan_name']} to {$new_plan['name']}"
                ]);
                
                $pdo->commit();
                $success_message = "Your membership has been successfully upgraded to {$new_plan['name']}!";
                
                // Refresh current membership data
                $current_stmt->execute([$user_id]);
                $current_membership = $current_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Refresh available plans
                if ($current_membership) {
                    $plans_stmt->execute([$current_membership['plan_price'], $current_membership['plan_id']]);
                    $available_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error upgrading membership: " . $e->getMessage();
        }
    }
}

// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active': return 'status-active';
        case 'cancelled': return 'status-cancelled';
        case 'expired': return 'status-expired';
        case 'upgraded': return 'status-upgraded';
        default: return 'status-pending';
    }
}

function formatFeatures($features) {
    return explode(',', $features ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Membership - Gym Management</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/upgrade-membership.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="upgrade-container">
        <a href="my-membership.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to My Membership
        </a>
        
        <div class="upgrade-header">
            <h1><i class="fas fa-arrow-up"></i> Upgrade Your Membership</h1>
            <p>Choose a higher-tier plan to unlock more features and benefits</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Current Plan -->
        <?php if ($current_membership): ?>
        <div class="current-plan">
            <h3><i class="fas fa-id-card"></i> Your Current Plan</h3>
            <div class="plan-info">
                <div class="plan-detail">
                    <strong>Plan:</strong> <?php echo htmlspecialchars($current_membership['plan_name']); ?>
                </div>
                <div class="plan-detail">
                    <strong>Price:</strong> $<?php echo number_format($current_membership['plan_price'], 2); ?>
                </div>
                <div class="plan-detail">
                    <strong>Duration:</strong> <?php echo $current_membership['duration']; ?> days
                </div>
                <div class="plan-detail">
                    <strong>Status:</strong> 
                    <span class="status-badge <?php echo getStatusBadgeClass($current_membership['status']); ?>">
                        <?php echo ucfirst($current_membership['status']); ?>
                    </span>
                </div>
            </div>
            <div class="plan-features">
                <strong>Current Features:</strong>
                <div style="margin-top: 10px;">
                    <?php foreach (formatFeatures($current_membership['features']) as $feature): ?>
                        <?php if (trim($feature)): ?>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Upgrade Options -->
        <div class="upgrade-plans">
            <?php if (!empty($available_plans)): ?>
                <h2><i class="fas fa-star"></i> Available Upgrades</h2>
                <div class="plans-grid">
                    <?php foreach ($available_plans as $index => $plan): ?>
                        <div class="plan-card <?php echo $index === 0 ? 'recommended' : ''; ?>">
                            <div class="plan-header">
                                <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                                <div class="plan-price">$<?php echo number_format($plan['price'], 2); ?></div>
                                <div class="plan-duration"><?php echo $plan['duration']; ?> days</div>
                            </div>
                            
                            <ul class="plan-features">
                                <?php foreach (formatFeatures($plan['features']) as $feature): ?>
                                    <?php if (trim($feature)): ?>
                                        <li>
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo htmlspecialchars(trim($feature)); ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            
                            <form method="POST" style="margin-top: 20px;">
                                <input type="hidden" name="new_plan_id" value="<?php echo $plan['id']; ?>">
                                <button type="submit" name="upgrade_plan" class="upgrade-btn"
                                        onclick="return confirm('Are you sure you want to upgrade to <?php echo htmlspecialchars($plan['name']); ?>? This will replace your current membership.')">
                                    <i class="fas fa-arrow-up"></i>
                                    Upgrade to This Plan
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-upgrades">
                    <i class="fas fa-crown"></i>
                    <h3>You're Already on the Best Plan!</h3>
                    <p>You're currently on our highest-tier membership plan. There are no upgrades available at this time.</p>
                    <p>Thank you for being a premium member!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
        
        // Add animation to plan cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.plan-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
