<?php
// Class Statistics and Analytics Page
session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['trainer', 'admin'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
$pdo = getConnection();
$base_url = '../';

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get trainer ID if user is a trainer
$trainer_id = null;
if ($user_role === 'trainer') {
    $trainer_stmt = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainer_stmt->execute([$user_id]);
    $trainer_data = $trainer_stmt->fetch();
    if ($trainer_data) {
        $trainer_id = $trainer_data['id'];
    }
}

try {
    // Overall Statistics
    $stats_query = "
        SELECT 
            COUNT(DISTINCT c.id) as total_classes,
            COUNT(DISTINCT cs.id) as total_schedules,
            COUNT(DISTINCT cb.id) as total_bookings,
            COUNT(DISTINCT CASE WHEN cb.status = 'attended' THEN cb.id END) as total_attended,
            COUNT(DISTINCT CASE WHEN cb.status = 'no-show' THEN cb.id END) as total_no_shows,
            COUNT(DISTINCT CASE WHEN cb.status = 'confirmed' THEN cb.id END) as total_confirmed,
            COUNT(DISTINCT cb.user_id) as unique_clients,
            AVG(c.capacity) as avg_capacity
        FROM classes c
        LEFT JOIN class_schedules cs ON c.id = cs.class_id
        LEFT JOIN class_bookings cb ON cs.id = cb.schedule_id
        WHERE c.is_active = 1
        " . ($trainer_id ? "AND c.trainer_id = ?" : "");
    
    $stats_stmt = $pdo->prepare($stats_query);
    if ($trainer_id) {
        $stats_stmt->execute([$trainer_id]);
    } else {
        $stats_stmt->execute();
    }
    $overall_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Class Performance
    $performance_query = "
        SELECT 
            c.id,
            c.name as class_name,
            c.capacity,
            c.difficulty_level,
            t.name as trainer_name,
            COUNT(DISTINCT cs.id) as schedule_count,
            COUNT(cb.id) as total_bookings,
            COUNT(CASE WHEN cb.status = 'attended' THEN 1 END) as attended_count,
            COUNT(CASE WHEN cb.status = 'no-show' THEN 1 END) as no_show_count,
            COUNT(CASE WHEN cb.status = 'confirmed' THEN 1 END) as confirmed_count,
            ROUND(AVG(CASE WHEN cb.status = 'attended' THEN 1 ELSE 0 END) * 100, 1) as attendance_rate,
            ROUND(COUNT(cb.id) / COUNT(DISTINCT cs.id), 1) as avg_bookings_per_session
        FROM classes c
        JOIN trainers t ON c.trainer_id = t.id
        LEFT JOIN class_schedules cs ON c.id = cs.class_id
        LEFT JOIN class_bookings cb ON cs.id = cb.schedule_id
        WHERE c.is_active = 1
        " . ($trainer_id ? "AND c.trainer_id = ?" : "") . "
        GROUP BY c.id
        ORDER BY total_bookings DESC
    ";
    
    $performance_stmt = $pdo->prepare($performance_query);
    if ($trainer_id) {
        $performance_stmt->execute([$trainer_id]);
    } else {
        $performance_stmt->execute();
    }
    $class_performance = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Weekly Schedule Analysis
    $weekly_query = "
        SELECT 
            cs.day_of_week,
            COUNT(DISTINCT cs.id) as class_count,
            COUNT(cb.id) as total_bookings,
            COUNT(CASE WHEN cb.status = 'attended' THEN 1 END) as attended_count,
            ROUND(AVG(CASE WHEN cb.status = 'attended' THEN 1 ELSE 0 END) * 100, 1) as attendance_rate
        FROM class_schedules cs
        JOIN classes c ON cs.class_id = c.id
        LEFT JOIN class_bookings cb ON cs.id = cb.schedule_id
        WHERE c.is_active = 1 AND cs.is_active = 1
        " . ($trainer_id ? "AND c.trainer_id = ?" : "") . "
        GROUP BY cs.day_of_week
        ORDER BY 
            CASE 
                WHEN cs.day_of_week = 'Monday' THEN 1
                WHEN cs.day_of_week = 'Tuesday' THEN 2
                WHEN cs.day_of_week = 'Wednesday' THEN 3
                WHEN cs.day_of_week = 'Thursday' THEN 4
                WHEN cs.day_of_week = 'Friday' THEN 5
                WHEN cs.day_of_week = 'Saturday' THEN 6
                WHEN cs.day_of_week = 'Sunday' THEN 7
            END
    ";
    
    $weekly_stmt = $pdo->prepare($weekly_query);
    if ($trainer_id) {
        $weekly_stmt->execute([$trainer_id]);
    } else {
        $weekly_stmt->execute();
    }
    $weekly_stats = $weekly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Clients
    $clients_query = "
        SELECT 
            u.id,
            u.username,
            u.email,
            COUNT(cb.id) as total_bookings,
            COUNT(CASE WHEN cb.status = 'attended' THEN 1 END) as attended_classes,
            COUNT(CASE WHEN cb.status = 'no-show' THEN 1 END) as no_shows,
            ROUND(AVG(CASE WHEN cb.status = 'attended' THEN 1 ELSE 0 END) * 100, 1) as attendance_rate,
            MAX(cb.created_at) as last_booking
        FROM users u
        JOIN class_bookings cb ON u.id = cb.user_id
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE c.is_active = 1
        " . ($trainer_id ? "AND c.trainer_id = ?" : "") . "
        GROUP BY u.id
        HAVING total_bookings > 0
        ORDER BY total_bookings DESC
        LIMIT 10
    ";
    
    $clients_stmt = $pdo->prepare($clients_query);
    if ($trainer_id) {
        $clients_stmt->execute([$trainer_id]);
    } else {
        $clients_stmt->execute();
    }
    $top_clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate rates
    $attendance_rate = $overall_stats['total_bookings'] > 0 ? 
        round(($overall_stats['total_attended'] / $overall_stats['total_bookings']) * 100, 1) : 0;
    $no_show_rate = $overall_stats['total_bookings'] > 0 ? 
        round(($overall_stats['total_no_shows'] / $overall_stats['total_bookings']) * 100, 1) : 0;
    
} catch (Exception $e) {
    $error_message = "Error loading statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Statistics - FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        .stat-card.blue { border-left-color: #007bff; }
        .stat-card.green { border-left-color: #28a745; }
        .stat-card.orange { border-left-color: #fd7e14; }
        .stat-card.red { border-left-color: #dc3545; }
        .stat-card.purple { border-left-color: #6f42c1; }
        .stat-card.teal { border-left-color: #20c997; }
        
        .stat-card i {
            font-size: 2.5em;
            margin-bottom: 15px;
        }
        .stat-card.blue i { color: #007bff; }
        .stat-card.green i { color: #28a745; }
        .stat-card.orange i { color: #fd7e14; }
        .stat-card.red i { color: #dc3545; }
        .stat-card.purple i { color: #6f42c1; }
        .stat-card.teal i { color: #20c997; }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-title {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .tables-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-title {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.2em;
            font-weight: bold;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .performance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .performance-table th,
        .performance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .performance-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .performance-table tr:hover {
            background: #f8f9fa;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .progress-excellent { background: #28a745; }
        .progress-good { background: #17a2b8; }
        .progress-fair { background: #ffc107; }
        .progress-poor { background: #dc3545; }
        
        .client-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .client-item:last-child {
            border-bottom: none;
        }
        .client-info h5 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .client-info p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        .client-stats {
            text-align: right;
            font-size: 0.9em;
        }
        .client-stats strong {
            color: #007bff;
        }
        
        @media (max-width: 768px) {
            .charts-section,
            .tables-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="stats-container">
        <h1><i class="fas fa-chart-bar"></i> Class Statistics & Analytics</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <i class="fas fa-calendar-alt"></i>
                <h2 class="stat-number"><?php echo $overall_stats['total_classes'] ?? 0; ?></h2>
                <p class="stat-label">Total Classes</p>
            </div>
            <div class="stat-card green">
                <i class="fas fa-users"></i>
                <h2 class="stat-number"><?php echo $overall_stats['total_bookings'] ?? 0; ?></h2>
                <p class="stat-label">Total Bookings</p>
            </div>
            <div class="stat-card orange">
                <i class="fas fa-check-circle"></i>
                <h2 class="stat-number"><?php echo $overall_stats['total_attended'] ?? 0; ?></h2>
                <p class="stat-label">Classes Attended</p>
            </div>
            <div class="stat-card red">
                <i class="fas fa-times-circle"></i>
                <h2 class="stat-number"><?php echo $overall_stats['total_no_shows'] ?? 0; ?></h2>
                <p class="stat-label">No Shows</p>
            </div>
            <div class="stat-card purple">
                <i class="fas fa-percentage"></i>
                <h2 class="stat-number"><?php echo $attendance_rate; ?>%</h2>
                <p class="stat-label">Attendance Rate</p>
            </div>
            <div class="stat-card teal">
                <i class="fas fa-user-friends"></i>
                <h2 class="stat-number"><?php echo $overall_stats['unique_clients'] ?? 0; ?></h2>
                <p class="stat-label">Unique Clients</p>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-container">
                <div class="chart-title">Weekly Class Distribution</div>
                <canvas id="weeklyChart" width="400" height="200"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">Attendance vs No-Shows</div>
                <canvas id="attendanceChart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div class="tables-section">
            <div class="table-container">
                <div class="table-title"><i class="fas fa-trophy"></i> Class Performance</div>
                <?php if (empty($class_performance)): ?>
                    <p>No class data available.</p>
                <?php else: ?>
                    <table class="performance-table">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Trainer</th>
                                <th>Bookings</th>
                                <th>Attendance Rate</th>
                                <th>Avg/Session</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($class_performance as $class): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                        <br><small><?php echo ucfirst($class['difficulty_level']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($class['trainer_name']); ?></td>
                                    <td>
                                        <?php echo $class['total_bookings']; ?>
                                        <div class="progress-bar">
                                            <?php 
                                            $capacity_usage = $class['capacity'] > 0 ? ($class['avg_bookings_per_session'] / $class['capacity']) * 100 : 0;
                                            $progress_class = $capacity_usage >= 80 ? 'progress-excellent' : 
                                                            ($capacity_usage >= 60 ? 'progress-good' : 
                                                            ($capacity_usage >= 40 ? 'progress-fair' : 'progress-poor'));
                                            ?>
                                            <div class="progress-fill <?php echo $progress_class; ?>" 
                                                 style="width: <?php echo min(100, $capacity_usage); ?>%"></div>
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
                                    <td><?php echo $class['avg_bookings_per_session']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="table-container">
                <div class="table-title"><i class="fas fa-star"></i> Top Clients</div>
                <?php if (empty($top_clients)): ?>
                    <p>No client data available.</p>
                <?php else: ?>
                    <?php foreach ($top_clients as $client): ?>
                        <div class="client-item">
                            <div class="client-info">
                                <h5><?php echo htmlspecialchars($client['username']); ?></h5>
                                <p><?php echo htmlspecialchars($client['email']); ?></p>
                                <p><small>Last booking: <?php echo date('M j', strtotime($client['last_booking'])); ?></small></p>
                            </div>
                            <div class="client-stats">
                                <strong><?php echo $client['total_bookings']; ?></strong> bookings<br>
                                <small><?php echo $client['attended_classes']; ?> attended</small><br>
                                <small><?php echo $client['attendance_rate']; ?>% rate</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        // Weekly Chart
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($weekly_stats, 'day_of_week')); ?>,
                datasets: [{
                    label: 'Classes',
                    data: <?php echo json_encode(array_column($weekly_stats, 'class_count')); ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.6)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }, {
                    label: 'Bookings',
                    data: <?php echo json_encode(array_column($weekly_stats, 'total_bookings')); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Attended', 'No Shows', 'Confirmed'],
                datasets: [{
                    data: [
                        <?php echo $overall_stats['total_attended'] ?? 0; ?>,
                        <?php echo $overall_stats['total_no_shows'] ?? 0; ?>,
                        <?php echo $overall_stats['total_confirmed'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(0, 123, 255, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
