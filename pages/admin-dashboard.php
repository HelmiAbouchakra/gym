<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../pages/login.php');
    exit();
}

$pdo = getConnection();

// Get system statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'member') as total_members,
        (SELECT COUNT(*) FROM users WHERE role = 'trainer') as total_trainers,
        (SELECT COUNT(*) FROM user_memberships WHERE status = 'active') as active_memberships,
        (SELECT COUNT(*) FROM classes WHERE is_active = 1) as active_classes,
        (SELECT COUNT(*) FROM class_bookings WHERE status = 'confirmed') as total_bookings,
        (SELECT SUM(mp.price) FROM user_memberships um JOIN membership_plans mp ON um.plan_id = mp.id WHERE um.status = 'active' AND um.payment_status = 'paid') as monthly_revenue
";

$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get recent activities
$recent_activity_query = "
    SELECT 
        'New User' as activity_type,
        CONCAT('User ', username, ' registered') as description,
        created_at as activity_time,
        'user' as icon
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
        'New Membership' as activity_type,
        CONCAT('New membership: ', mp.name) as description,
        um.created_at as activity_time,
        'id-card' as icon
    FROM user_memberships um
    JOIN membership_plans mp ON um.plan_id = mp.id
    WHERE um.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    ORDER BY activity_time DESC
    LIMIT 10
";

try {
    $recent_activities = $pdo->query($recent_activity_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error with the query, set empty activities
    $recent_activities = [];
}

// Get top membership plans
$top_plans_query = "
    SELECT 
        mp.name,
        mp.price,
        COUNT(um.id) as member_count,
        SUM(CASE WHEN um.status = 'active' THEN mp.price ELSE 0 END) as revenue
    FROM membership_plans mp
    LEFT JOIN user_memberships um ON mp.id = um.plan_id
    WHERE mp.is_active = 1
    GROUP BY mp.id, mp.name, mp.price
    ORDER BY member_count DESC
    LIMIT 5
";

$top_plans = $pdo->query($top_plans_query)->fetchAll(PDO::FETCH_ASSOC);

// Get membership status distribution
$membership_status_query = "
    SELECT 
        status,
        COUNT(*) as count
    FROM user_memberships
    GROUP BY status
";

$membership_status = $pdo->query($membership_status_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/components/navbar.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <div class="admin-nav">
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total_members']; ?></div>
                    <div class="stat-label">Members</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['active_memberships']; ?></div>
                    <div class="stat-label">Active Memberships</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">$<?php echo number_format($stats['monthly_revenue'] ?? 0, 0); ?></div>
                    <div class="stat-label">Monthly Revenue</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            </div>
            
            <div class="quick-actions-grid">
                <a href="admin-users.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="quick-action-content">
                        <h3>Manage Users</h3>
                        <p>Add, edit, and manage user accounts</p>
                    </div>
                </a>

                <a href="admin-memberships.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="quick-action-content">
                        <h3>Manage Memberships</h3>
                        <p>View and manage all user memberships</p>
                    </div>
                </a>

                <a href="admin-classes.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="quick-action-content">
                        <h3>Manage Classes</h3>
                        <p>Create and manage fitness classes</p>
                    </div>
                </a>

                <a href="class-statistics.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="quick-action-content">
                        <h3>Analytics</h3>
                        <p>View detailed statistics and reports</p>
                    </div>
                </a>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Recent Activity -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Recent Activity</h2>
                </div>
                
                <div class="activity-list">
                    <?php if (empty($recent_activities)): ?>
                        <div class="no-data">
                            <i class="fas fa-info-circle"></i>
                            <p>No recent activity to display</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-type"><?php echo htmlspecialchars($activity['activity_type']); ?></div>
                                    <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['activity_time'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Membership Plans -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-trophy"></i> Top Membership Plans</h2>
                </div>
                
                <div class="plans-list">
                    <?php if (empty($top_plans)): ?>
                        <div class="no-data">
                            <i class="fas fa-info-circle"></i>
                            <p>No membership plans found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_plans as $plan): ?>
                            <div class="plan-item">
                                <div class="plan-info">
                                    <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                                    <div class="plan-stats">
                                        <span class="plan-members">
                                            <i class="fas fa-users"></i> <?php echo $plan['member_count']; ?> members
                                        </span>
                                        <span class="plan-revenue">
                                            <i class="fas fa-dollar-sign"></i> $<?php echo number_format($plan['revenue'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="plan-price">
                                    $<?php echo number_format($plan['price'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Membership Status Chart -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-pie"></i> Membership Status Distribution</h2>
            </div>
            
            <div class="chart-container">
                <canvas id="membershipStatusChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <?php include '../includes/components/footer.php'; ?>

    <script src="../assets/js/admin.js"></script>
    <script>
        // Membership Status Chart
        const membershipStatusData = <?php echo json_encode($membership_status); ?>;
        
        if (membershipStatusData.length > 0) {
            const ctx = document.getElementById('membershipStatusChart').getContext('2d');
            
            const labels = membershipStatusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1));
            const data = membershipStatusData.map(item => item.count);
            
            const colors = {
                'Active': '#28a745',
                'Expired': '#dc3545',
                'Cancelled': '#6c757d',
                'Pending': '#ffc107'
            };
            
            const backgroundColors = labels.map(label => colors[label] || '#007bff');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
