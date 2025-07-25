<?php
// Admin Dashboard
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
$pdo = getConnection();
$base_url = '../';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Admin';

try {
    // Overall System Statistics
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'member') as total_members,
            (SELECT COUNT(*) FROM users WHERE role = 'trainer') as total_trainers,
            (SELECT COUNT(*) FROM trainers WHERE is_active = 1) as active_trainers,
            (SELECT COUNT(*) FROM classes WHERE is_active = 1) as total_classes,
            (SELECT COUNT(*) FROM class_schedules WHERE is_active = 1) as total_schedules,
            (SELECT COUNT(*) FROM class_bookings) as total_bookings,
            (SELECT COUNT(*) FROM class_bookings WHERE status = 'confirmed') as confirmed_bookings,
            (SELECT COUNT(*) FROM class_bookings WHERE status = 'attended') as attended_bookings,
            (SELECT COUNT(*) FROM class_bookings WHERE status = 'no-show') as no_show_bookings,
            (SELECT COUNT(*) FROM user_memberships WHERE status = 'active') as active_memberships,
            (SELECT COUNT(*) FROM membership_plans WHERE is_active = 1) as membership_plans,
            (SELECT SUM(price) FROM membership_plans WHERE is_active = 1) as total_plan_value
    ";
    
    $stats_stmt = $pdo->query($stats_query);
    $system_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent Activity
    $recent_activity_query = "
        SELECT 'user_registration' as type, username as title, created_at, 'New user registered' as description
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 'class_booking' as type, 
               CONCAT(u.username, ' booked ', c.name) as title, 
               cb.created_at, 
               CONCAT('Class: ', c.name, ' on ', cs.day_of_week) as description
        FROM class_bookings cb
        JOIN users u ON cb.user_id = u.id
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE cb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 'membership' as type,
               CONCAT(u.username, ' purchased ', mp.name) as title,
               um.created_at,
               CONCAT('Plan: ', mp.name, ' - $', mp.price) as description
        FROM user_memberships um
        JOIN users u ON um.user_id = u.id
        JOIN membership_plans mp ON um.plan_id = mp.id
        WHERE um.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        ORDER BY created_at DESC
        LIMIT 10
    ";
    
    $activity_stmt = $pdo->query($recent_activity_query);
    $recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Performing Classes
    $top_classes_query = "
        SELECT 
            c.id,
            c.name,
            c.capacity,
            c.difficulty_level,
            t.name as trainer_name,
            COUNT(cb.id) as total_bookings,
            COUNT(CASE WHEN cb.status = 'attended' THEN 1 END) as attended_count,
            ROUND(AVG(CASE WHEN cb.status = 'attended' THEN 1 ELSE 0 END) * 100, 1) as attendance_rate,
            COUNT(DISTINCT cb.user_id) as unique_clients
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        LEFT JOIN class_schedules cs ON c.id = cs.class_id
        LEFT JOIN class_bookings cb ON cs.id = cb.schedule_id
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY total_bookings DESC
        LIMIT 5
    ";
    
    $top_classes_stmt = $pdo->query($top_classes_query);
    $top_classes = $top_classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue Analytics (from memberships)
    $revenue_query = "
        SELECT 
            DATE_FORMAT(um.created_at, '%Y-%m') as month,
            COUNT(*) as memberships_sold,
            SUM(mp.price) as monthly_revenue
        FROM user_memberships um
        JOIN membership_plans mp ON um.plan_id = mp.id
        WHERE um.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(um.created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ";
    
    $revenue_stmt = $pdo->query($revenue_query);
    $revenue_data = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System Health Checks
    $health_checks = [
        'database' => true,
        'active_trainers' => $system_stats['active_trainers'] > 0,
        'active_classes' => $system_stats['total_classes'] > 0,
        'recent_bookings' => $system_stats['total_bookings'] > 0,
        'membership_plans' => $system_stats['membership_plans'] > 0
    ];
    
    // Calculate attendance rate
    $attendance_rate = $system_stats['total_bookings'] > 0 ? 
        round(($system_stats['attended_bookings'] / $system_stats['total_bookings']) * 100, 1) : 0;
    
    // Calculate total revenue
    $total_revenue = 0;
    foreach ($revenue_data as $month) {
        $total_revenue += $month['monthly_revenue'];
    }
    
} catch (Exception $e) {
    $error_message = "Error loading dashboard data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="admin-container">
        <!-- Admin Header -->
        <div class="admin-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Here's your gym management overview.</p>
            
            <div class="admin-nav">
                <a href="admin-users.php" class="admin-nav-btn">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="admin-trainers.php" class="admin-nav-btn">
                    <i class="fas fa-user-tie"></i> Manage Trainers
                </a>
                <a href="admin-classes.php" class="admin-nav-btn">
                    <i class="fas fa-dumbbell"></i> Manage Classes
                </a>
                <a href="admin-memberships.php" class="admin-nav-btn">
                    <i class="fas fa-credit-card"></i> Memberships
                </a>
                <a href="admin-reports.php" class="admin-nav-btn">
                    <i class="fas fa-chart-line"></i> Reports
                </a>
                <a href="admin-settings.php" class="admin-nav-btn">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h2 class="stat-number"><?php echo $system_stats['total_users'] ?? 0; ?></h2>
                <p class="stat-label">Total Users</p>
                <small><?php echo $system_stats['total_members'] ?? 0; ?> Members, <?php echo $system_stats['total_trainers'] ?? 0; ?> Trainers</small>
            </div>
            
            <div class="stat-card classes">
                <div class="stat-icon"><i class="fas fa-dumbbell"></i></div>
                <h2 class="stat-number"><?php echo $system_stats['total_classes'] ?? 0; ?></h2>
                <p class="stat-label">Active Classes</p>
                <small><?php echo $system_stats['total_schedules'] ?? 0; ?> Scheduled Sessions</small>
            </div>
            
            <div class="stat-card bookings">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <h2 class="stat-number"><?php echo $system_stats['total_bookings'] ?? 0; ?></h2>
                <p class="stat-label">Total Bookings</p>
                <small><?php echo $system_stats['confirmed_bookings'] ?? 0; ?> Confirmed</small>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                <h2 class="stat-number">$<?php echo number_format($total_revenue, 0); ?></h2>
                <p class="stat-label">Revenue (6 months)</p>
                <small><?php echo $system_stats['active_memberships'] ?? 0; ?> Active Memberships</small>
            </div>
            
            <div class="stat-card trainers">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <h2 class="stat-number"><?php echo $system_stats['active_trainers'] ?? 0; ?></h2>
                <p class="stat-label">Active Trainers</p>
                <small><?php echo $system_stats['total_trainers'] ?? 0; ?> Total Trainers</small>
            </div>
            
            <div class="stat-card attendance">
                <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                <h2 class="stat-number"><?php echo $attendance_rate; ?>%</h2>
                <p class="stat-label">Attendance Rate</p>
                <small><?php echo $system_stats['attended_bookings'] ?? 0; ?> Attended Classes</small>
            </div>
        </div>
        
        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left Column -->
            <div>
                <!-- Revenue Chart -->
                <div class="dashboard-section">
                    <h3 class="section-title"><i class="fas fa-chart-line"></i> Revenue Trends</h3>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <!-- Top Performing Classes -->
                <div class="dashboard-section" style="margin-top: 30px;">
                    <h3 class="section-title"><i class="fas fa-trophy"></i> Top Performing Classes</h3>
                    <?php if (empty($top_classes)): ?>
                        <p>No class data available.</p>
                    <?php else: ?>
                        <table class="top-classes-table">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Trainer</th>
                                    <th>Bookings</th>
                                    <th>Attendance</th>
                                    <th>Clients</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_classes as $class): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['name']); ?></strong>
                                            <br><small><?php echo ucfirst($class['difficulty_level']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['trainer_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <?php echo $class['total_bookings']; ?>
                                            <div class="progress-bar">
                                                <?php 
                                                $booking_rate = $class['capacity'] > 0 ? ($class['total_bookings'] / ($class['capacity'] * 4)) * 100 : 0; // Assuming 4 sessions per month
                                                $progress_class = $booking_rate >= 80 ? 'progress-excellent' : 
                                                                ($booking_rate >= 60 ? 'progress-good' : 
                                                                ($booking_rate >= 40 ? 'progress-fair' : 'progress-poor'));
                                                ?>
                                                <div class="progress-fill <?php echo $progress_class; ?>" 
                                                     style="width: <?php echo min(100, $booking_rate); ?>%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo $class['attendance_rate'] ?? 0; ?>%
                                            <div class="progress-bar">
                                                <?php 
                                                $rate = $class['attendance_rate'] ?? 0;
                                                $rate_class = $rate >= 80 ? 'progress-excellent' : 
                                                            ($rate >= 60 ? 'progress-good' : 
                                                            ($rate >= 40 ? 'progress-fair' : 'progress-poor'));
                                                ?>
                                                <div class="progress-fill <?php echo $rate_class; ?>" 
                                                     style="width: <?php echo $rate; ?>%"></div>
                                            </div>
                                        </td>
                                        <td><?php echo $class['unique_clients']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- System Health -->
                <div class="dashboard-section">
                    <h3 class="section-title"><i class="fas fa-heartbeat"></i> System Health</h3>
                    <div class="health-status">
                        <div class="health-item">
                            <div class="health-icon <?php echo $health_checks['database'] ? 'health-good' : 'health-error'; ?>">
                                <i class="fas fa-database"></i>
                            </div>
                            <span>Database Connection</span>
                        </div>
                        <div class="health-item">
                            <div class="health-icon <?php echo $health_checks['active_trainers'] ? 'health-good' : 'health-warning'; ?>">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <span>Active Trainers</span>
                        </div>
                        <div class="health-item">
                            <div class="health-icon <?php echo $health_checks['active_classes'] ? 'health-good' : 'health-warning'; ?>">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <span>Active Classes</span>
                        </div>
                        <div class="health-item">
                            <div class="health-icon <?php echo $health_checks['recent_bookings'] ? 'health-good' : 'health-warning'; ?>">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <span>Recent Bookings</span>
                        </div>
                        <div class="health-item">
                            <div class="health-icon <?php echo $health_checks['membership_plans'] ? 'health-good' : 'health-warning'; ?>">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <span>Membership Plans</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="dashboard-section" style="margin-top: 30px;">
                    <h3 class="section-title"><i class="fas fa-clock"></i> Recent Activity</h3>
                    <?php if (empty($recent_activities)): ?>
                        <p>No recent activity to display.</p>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($recent_activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-icon activity-<?php echo $activity['type'] === 'user_registration' ? 'user' : ($activity['type'] === 'class_booking' ? 'booking' : 'membership'); ?>">
                                        <i class="fas fa-<?php echo $activity['type'] === 'user_registration' ? 'user-plus' : ($activity['type'] === 'class_booking' ? 'calendar-check' : 'credit-card'); ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></p>
                                        <p class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    </div>
                                    <div class="activity-time">
                                        <?php 
                                        $time_diff = time() - strtotime($activity['created_at']);
                                        if ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . 'm ago';
                                        } elseif ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . 'h ago';
                                        } else {
                                            echo floor($time_diff / 86400) . 'd ago';
                                        }
                                        ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($revenue_data, 'month'))); ?>,
                datasets: [{
                    label: 'Monthly Revenue ($)',
                    data: <?php echo json_encode(array_reverse(array_column($revenue_data, 'monthly_revenue'))); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Memberships Sold',
                    data: <?php echo json_encode(array_reverse(array_column($revenue_data, 'memberships_sold'))); ?>,
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>
</html>
