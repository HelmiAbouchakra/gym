<?php
// My Membership Page
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
$membership = null;
$membership_plan = null;
$user_info = null;

// Handle membership actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel_membership') {
        try {
            // Update membership status to cancelled
            $cancel_stmt = $pdo->prepare("UPDATE user_memberships SET status = 'cancelled' WHERE user_id = ? AND status = 'active'");
            $result = $cancel_stmt->execute([$user_id]);
            
            if ($cancel_stmt->rowCount() > 0) {
                // Log the cancellation activity
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'membership_cancel', 'User cancelled their membership')");
                $log_stmt->execute([$user_id]);
                
                $success_message = "Your membership has been cancelled successfully. You can continue using the gym until your current billing period ends.";
            } else {
                $error_message = "Could not cancel membership. Please contact support.";
            }
            
        } catch (Exception $e) {
            $error_message = "Error cancelling membership: " . $e->getMessage();
        }
    }
    
    if ($action === 'reactivate_membership') {
        try {
            // Reactivate membership
            $reactivate_stmt = $pdo->prepare("UPDATE user_memberships SET status = 'active' WHERE user_id = ? AND status = 'cancelled'");
            $result = $reactivate_stmt->execute([$user_id]);
            
            if ($reactivate_stmt->rowCount() > 0) {
                // Log the reactivation activity
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'membership_reactivate', 'User reactivated their membership')");
                $log_stmt->execute([$user_id]);
                
                $success_message = "Your membership has been reactivated successfully!";
            } else {
                $error_message = "Could not reactivate membership. Please contact support.";
            }
            
        } catch (Exception $e) {
            $error_message = "Error reactivating membership: " . $e->getMessage();
        }
    }
    
    // Refresh the page to show updated data
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

try {
    // Get user information
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's current membership with plan details
    $membership_stmt = $pdo->prepare("
        SELECT 
            um.*,
            mp.name as plan_name,
            mp.description as plan_description,
            mp.price as plan_price,
            mp.duration,
            mp.features
        FROM user_memberships um
        JOIN membership_plans mp ON um.plan_id = mp.id
        WHERE um.user_id = ?
        ORDER BY um.created_at DESC
        LIMIT 1
    ");
    $membership_stmt->execute([$user_id]);
    $membership = $membership_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($membership) {
        $membership_plan = [
            'name' => $membership['plan_name'],
            'description' => $membership['plan_description'],
            'price' => $membership['plan_price'],
            'duration' => $membership['duration'],
            'features' => $membership['features']
        ];
    }
    
} catch (Exception $e) {
    $error_message = "Error loading membership information: " . $e->getMessage();
}

// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'status-active';
        case 'cancelled':
            return 'status-cancelled';
        case 'expired':
            return 'status-expired';
        case 'pending':
            return 'status-pending';
        default:
            return 'status-unknown';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'active':
            return 'Active';
        case 'cancelled':
            return 'Cancelled';
        case 'expired':
            return 'Expired';
        case 'pending':
            return 'Pending';
        default:
            return 'Unknown';
    }
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function calculateEndDate($start_date, $duration_days) {
    return date('F j, Y', strtotime($start_date . ' + ' . $duration_days . ' days'));
}

function getDaysRemaining($start_date, $duration_days) {
    $end_date = strtotime($start_date . ' + ' . $duration_days . ' days');
    $today = time();
    $days = ceil(($end_date - $today) / (60 * 60 * 24));
    return max(0, $days);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Membership - FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/footer.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/my-membership.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="membership-container">
        <div class="page-header">
            <h1><i class="fas fa-id-card"></i> My Membership</h1>
            <p>View and manage your gym membership details</p>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($membership): ?>
            <!-- Membership Overview -->
            <div class="membership-overview">
                <div class="membership-card">
                    <div class="membership-header">
                        <div class="member-info">
                            <div class="member-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="member-details">
                                <h2><?php echo htmlspecialchars($user_info['username'] ?? 'Member'); ?></h2>
                                <p class="member-id">Member ID: #<?php echo str_pad($user_id, 6, '0', STR_PAD_LEFT); ?></p>
                                <p class="member-since">Member since <?php echo formatDate($membership['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="membership-status">
                            <div class="status-badge <?php echo getStatusBadgeClass($membership['status']); ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo getStatusText($membership['status']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="membership-body">
                        <div class="plan-info">
                            <h3><?php echo htmlspecialchars($membership_plan['name']); ?></h3>
                            <p class="plan-description"><?php echo htmlspecialchars($membership_plan['description']); ?></p>
                            <div class="plan-price">
                                <span class="price">$<?php echo number_format($membership_plan['price'], 2); ?></span>
                                <span class="period">/month</span>
                            </div>
                        </div>
                        
                        <div class="membership-dates">
                            <div class="date-item">
                                <div class="date-label">Start Date</div>
                                <div class="date-value"><?php echo formatDate($membership['start_date']); ?></div>
                            </div>
                            <div class="date-item">
                                <div class="date-label">End Date</div>
                                <div class="date-value"><?php echo calculateEndDate($membership['start_date'], $membership_plan['duration']); ?></div>
                            </div>
                            <div class="date-item">
                                <div class="date-label">Days Remaining</div>
                                <div class="date-value days-remaining">
                                    <?php 
                                    $days_remaining = getDaysRemaining($membership['start_date'], $membership_plan['duration']);
                                    echo $days_remaining;
                                    ?>
                                    <span class="days-text">days</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($membership['status'] === 'active'): ?>
                            <div class="progress-bar">
                                <div class="progress-label">Membership Progress</div>
                                <div class="progress-track">
                                    <?php
                                    $total_days = $membership_plan['duration'];
                                    $elapsed_days = $total_days - $days_remaining;
                                    $progress_percent = min(100, max(0, ($elapsed_days / $total_days) * 100));
                                    ?>
                                    <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                                </div>
                                <div class="progress-text"><?php echo round($progress_percent); ?>% complete</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Membership Features -->
            <div class="membership-features">
                <h3><i class="fas fa-star"></i> Your Plan Features</h3>
                <div class="features-grid">
                    <?php 
                    $features = explode(',', $membership_plan['features'] ?? '');
                    foreach ($features as $feature): 
                        $feature = trim($feature);
                        if (!empty($feature)):
                    ?>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($feature); ?></span>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="payment-info">
                <h3><i class="fas fa-credit-card"></i> Payment Information</h3>
                <div class="payment-details">
                    <div class="payment-item">
                        <div class="payment-label">Payment Method</div>
                        <div class="payment-value">
                            <i class="fas fa-credit-card"></i>
                            <?php echo htmlspecialchars($membership['payment_method'] ?? 'Credit Card'); ?>
                        </div>
                    </div>
                    <div class="payment-item">
                        <div class="payment-label">Total Amount Paid</div>
                        <div class="payment-value">
                            <i class="fas fa-dollar-sign"></i>
                            $<?php echo number_format($membership['total_amount'] ?? $membership_plan['price'], 2); ?>
                        </div>
                    </div>
                    <div class="payment-item">
                        <div class="payment-label">Next Billing Date</div>
                        <div class="payment-value">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo calculateEndDate($membership['start_date'], $membership_plan['duration']); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Membership Actions -->
            <div class="membership-actions">
                <h3><i class="fas fa-cogs"></i> Manage Membership</h3>
                <div class="actions-grid">
                    <?php if ($membership['status'] === 'active'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="cancel_membership">
                            <button type="submit" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel your membership? You can continue using the gym until your current billing period ends.')">
                                <i class="fas fa-times-circle"></i>
                                Cancel Membership
                            </button>
                        </form>
                    <?php elseif ($membership['status'] === 'cancelled'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reactivate_membership">
                            <button type="submit" class="btn-reactivate" onclick="return confirm('Are you sure you want to reactivate your membership?')">
                                <i class="fas fa-check-circle"></i>
                                Reactivate Membership
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="upgrade-membership.php" class="btn-upgrade">
                        <i class="fas fa-arrow-up"></i>
                        Upgrade Plan
                    </a>
                    
                    <a href="my-bookings.php" class="btn-bookings">
                        <i class="fas fa-calendar-check"></i>
                        View My Bookings
                    </a>
                    
                    <a href="upcoming-classes.php" class="btn-classes">
                        <i class="fas fa-dumbbell"></i>
                        Browse Classes
                    </a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- No Membership -->
            <div class="no-membership">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <h3>No Active Membership</h3>
                    <p>You don't have an active membership yet. Join FitLife Gym today and start your fitness journey!</p>
                    <div class="empty-actions">
                        <a href="memberships.php" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Get Membership
                        </a>
                        <a href="../index.php" class="btn-secondary">
                            <i class="fas fa-home"></i>
                            Back to Home
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        // Add fade-in animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.membership-card, .membership-features, .payment-info, .membership-actions');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
            
            // Update progress bar animation
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                const width = progressFill.style.width;
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = width;
                }, 500);
            }
        });
        
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
    </script>
</body>
</html>
