<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../pages/login.php');
    exit();
}

$pdo = getConnection();

// Handle membership status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $membership_id = $_POST['membership_id'];
        $new_status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE user_memberships SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $membership_id]);
            $success_message = "Membership status updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating membership status: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_payment'])) {
        $membership_id = $_POST['membership_id'];
        $payment_status = $_POST['payment_status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE user_memberships SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $membership_id]);
            $success_message = "Payment status updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating payment status: " . $e->getMessage();
        }
    }
}

// Get membership statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_memberships,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_memberships,
        COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_memberships,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_memberships,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_memberships,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments,
        SUM(CASE WHEN status = 'active' AND payment_status = 'paid' THEN mp.price ELSE 0 END) as total_revenue
    FROM user_memberships um
    JOIN membership_plans mp ON um.plan_id = mp.id
";

$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get all memberships with user and plan details
$memberships_query = "
    SELECT 
        um.id,
        um.status,
        um.payment_status,
        um.start_date,
        um.end_date,
        um.created_at,
        u.id as user_id,
        u.username,
        u.email,
        mp.id as plan_id,
        mp.name as plan_name,
        mp.description as plan_description,
        mp.duration,
        mp.price,
        mp.features,
        t.name as trainer_name,
        DATEDIFF(um.end_date, CURDATE()) as days_remaining,
        CASE 
            WHEN um.end_date < CURDATE() AND um.status = 'active' THEN 'expired'
            ELSE um.status 
        END as actual_status
    FROM user_memberships um
    JOIN users u ON um.user_id = u.id
    JOIN membership_plans mp ON um.plan_id = mp.id
    LEFT JOIN trainers t ON um.trainer_id = t.id
    ORDER BY um.created_at DESC
";

$memberships = $pdo->query($memberships_query)->fetchAll(PDO::FETCH_ASSOC);

// Get membership plans for filtering
$plans = $pdo->query("SELECT id, name FROM membership_plans WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Membership Management | FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/components/navbar.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-id-card"></i> Membership Management</h1>
            <div class="admin-nav">
                <a href="admin-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total_memberships']; ?></div>
                    <div class="stat-label">Total Memberships</div>
                </div>
            </div>

            <div class="stat-card active">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['active_memberships']; ?></div>
                    <div class="stat-label">Active Memberships</div>
                </div>
            </div>

            <div class="stat-card expired">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['expired_memberships']; ?></div>
                    <div class="stat-label">Expired Memberships</div>
                </div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="stat-label">Active Revenue</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="admin-filters">
            <div class="filter-group">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterMemberships()">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="pending">Pending</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="planFilter">Filter by Plan:</label>
                <select id="planFilter" onchange="filterMemberships()">
                    <option value="">All Plans</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo htmlspecialchars($plan['name']); ?>">
                            <?php echo htmlspecialchars($plan['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="searchMemberships">Search:</label>
                <input type="text" id="searchMemberships" placeholder="Search by username or email..." onkeyup="filterMemberships()">
            </div>
        </div>

        <!-- Memberships Table -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> All Memberships</h2>
                <div class="section-actions">
                    <span class="record-count"><?php echo count($memberships); ?> memberships found</span>
                </div>
            </div>

            <div class="table-container">
                <table class="admin-table" id="membershipsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Plan</th>
                            <th>Features</th>
                            <th>Trainer</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Days Left</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memberships as $membership): ?>
                            <tr data-status="<?php echo $membership['actual_status']; ?>" 
                                data-plan="<?php echo htmlspecialchars($membership['plan_name']); ?>"
                                data-search="<?php echo strtolower($membership['username'] . ' ' . $membership['email']); ?>">
                                <td><?php echo $membership['id']; ?></td>
                                
                                <td>
                                    <div class="member-info">
                                        <strong><?php echo htmlspecialchars($membership['username']); ?></strong>
                                        <small><?php echo htmlspecialchars($membership['email']); ?></small>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="plan-info">
                                        <strong><?php echo htmlspecialchars($membership['plan_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($membership['plan_description']); ?></small>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="features-list">
                                        <?php 
                                        $features = explode(',', $membership['features']);
                                        foreach ($features as $feature): 
                                            $feature = trim($feature);
                                            if (!empty($feature)):
                                        ?>
                                            <span class="feature-tag">
                                                <i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?>
                                            </span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <?php if ($membership['trainer_name']): ?>
                                        <span class="trainer-badge">
                                            <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($membership['trainer_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-trainer">No trainer assigned</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div class="duration-info">
                                        <strong><?php echo $membership['duration']; ?> days</strong>
                                        <small>
                                            <?php echo date('M j, Y', strtotime($membership['start_date'])); ?> - 
                                            <?php echo date('M j, Y', strtotime($membership['end_date'])); ?>
                                        </small>
                                    </div>
                                </td>
                                
                                <td>
                                    <span class="price-tag">$<?php echo number_format($membership['price'], 2); ?></span>
                                </td>
                                
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="membership_id" value="<?php echo $membership['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="status-select status-<?php echo $membership['actual_status']; ?>">
                                            <option value="active" <?php echo $membership['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="expired" <?php echo $membership['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                            <option value="cancelled" <?php echo $membership['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="pending" <?php echo $membership['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="membership_id" value="<?php echo $membership['id']; ?>">
                                        <select name="payment_status" onchange="this.form.submit()" class="payment-select payment-<?php echo $membership['payment_status']; ?>">
                                            <option value="paid" <?php echo $membership['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="pending" <?php echo $membership['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="failed" <?php echo $membership['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        </select>
                                        <input type="hidden" name="update_payment" value="1">
                                    </form>
                                </td>
                                
                                <td>
                                    <?php if ($membership['actual_status'] === 'active'): ?>
                                        <?php if ($membership['days_remaining'] > 0): ?>
                                            <span class="days-remaining positive"><?php echo $membership['days_remaining']; ?> days</span>
                                        <?php elseif ($membership['days_remaining'] === 0): ?>
                                            <span class="days-remaining warning">Expires today</span>
                                        <?php else: ?>
                                            <span class="days-remaining expired">Expired <?php echo abs($membership['days_remaining']); ?> days ago</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="days-remaining inactive">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewMembershipDetails(<?php echo $membership['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="mailto:<?php echo $membership['email']; ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Membership Details Modal -->
    <div id="membershipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-id-card"></i> Membership Details</h3>
                <span class="close" onclick="closeMembershipModal()">&times;</span>
            </div>
            <div class="modal-body" id="membershipDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>

    <?php include '../includes/components/footer.php'; ?>

    <script src="../assets/js/admin.js"></script>
    <script>
        function filterMemberships() {
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const planFilter = document.getElementById('planFilter').value.toLowerCase();
            const searchTerm = document.getElementById('searchMemberships').value.toLowerCase();
            const table = document.getElementById('membershipsTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const status = row.getAttribute('data-status').toLowerCase();
                const plan = row.getAttribute('data-plan').toLowerCase();
                const searchData = row.getAttribute('data-search');
                
                const statusMatch = !statusFilter || status.includes(statusFilter);
                const planMatch = !planFilter || plan.includes(planFilter);
                const searchMatch = !searchTerm || searchData.includes(searchTerm);
                
                row.style.display = statusMatch && planMatch && searchMatch ? '' : 'none';
            }
            
            // Update record count
            const visibleRows = Array.from(rows).slice(1).filter(row => row.style.display !== 'none');
            document.querySelector('.record-count').textContent = `${visibleRows.length} memberships found`;
        }

        function viewMembershipDetails(membershipId) {
            // This would typically fetch details via AJAX
            // For now, we'll show a placeholder
            document.getElementById('membershipDetails').innerHTML = `
                <p>Loading membership details for ID: ${membershipId}...</p>
                <p>This feature can be expanded to show detailed membership history, payment records, etc.</p>
            `;
            document.getElementById('membershipModal').style.display = 'block';
        }

        function closeMembershipModal() {
            document.getElementById('membershipModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('membershipModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Auto-refresh page every 5 minutes to update days remaining
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
