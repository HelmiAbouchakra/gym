<?php
// Start session
session_start();

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
    // Redirect to login page if not logged in or not a trainer
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

// Fetch trainer information from database
try {
    $stmt = $pdo->prepare("SELECT * FROM trainers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $trainer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trainer) {
        // If trainer record doesn't exist, create basic record
        $stmt = $pdo->prepare("INSERT INTO trainers (user_id, name, bio, specialties) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $user_id, 
            $username, 
            'Professional fitness trainer', 
            'General fitness'
        ]);
        
        // Fetch the newly created record
        $stmt = $pdo->prepare("SELECT * FROM trainers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $trainer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $trainer_id = $trainer['id'];
    
    // Get upcoming classes
    $today = date('Y-m-d');
    $time_now = date('H:i:s');
    
    $class_stmt = $pdo->prepare("
        SELECT c.*, cs.day_of_week, cs.start_time, cs.end_time, cs.room,
               (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id AND cb.status = 'confirmed') as booked_count
        FROM classes c
        JOIN class_schedules cs ON c.id = cs.class_id
        WHERE c.trainer_id = ?
        ORDER BY 
            CASE 
                WHEN cs.day_of_week = 'Monday' THEN 1
                WHEN cs.day_of_week = 'Tuesday' THEN 2
                WHEN cs.day_of_week = 'Wednesday' THEN 3
                WHEN cs.day_of_week = 'Thursday' THEN 4
                WHEN cs.day_of_week = 'Friday' THEN 5
                WHEN cs.day_of_week = 'Saturday' THEN 6
                WHEN cs.day_of_week = 'Sunday' THEN 7
            END,
            cs.start_time
        LIMIT 5
    ");
    $class_stmt->execute([$trainer_id]);
    $upcoming_classes = $class_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get client count
    $client_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as client_count
        FROM class_bookings cb
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE c.trainer_id = ?
    ");
    $client_stmt->execute([$trainer_id]);
    $client_result = $client_stmt->fetch(PDO::FETCH_ASSOC);
    $client_count = $client_result ? $client_result['client_count'] : 0;
    
    // Get total classes count
    $class_count_stmt = $pdo->prepare("
        SELECT COUNT(*) as class_count
        FROM classes
        WHERE trainer_id = ?
    ");
    $class_count_stmt->execute([$trainer_id]);
    $class_count_result = $class_count_stmt->fetch(PDO::FETCH_ASSOC);
    $class_count = $class_count_result ? $class_count_result['class_count'] : 0;
    
    // Get recent activity (latest bookings)
    $activity_stmt = $pdo->prepare("
        SELECT cb.*, u.username, c.name as class_name, cs.day_of_week, cs.start_time
        FROM class_bookings cb
        JOIN users u ON cb.user_id = u.id
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE c.trainer_id = ?
        ORDER BY cb.created_at DESC
        LIMIT 5
    ");
    $activity_stmt->execute([$trainer_id]);
    $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer Dashboard - FitLife Gym</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 30px auto 50px;
            padding: 0 15px;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .dashboard-title {
            margin: 0;
        }
        
        .dashboard-welcome {
            color: #666;
            margin-top: 5px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--secondary);
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .dashboard-section {
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .section-title {
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.2rem;
        }
        
        .class-card {
            display: flex;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            margin-bottom: 15px;
            transition: background-color 0.3s ease;
        }
        
        .class-card:hover {
            background-color: #eff6ff;
        }
        
        .class-info {
            flex: 1;
        }
        
        .class-title {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
        }
        
        .class-details {
            display: flex;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .class-details > div {
            margin-right: 15px;
        }
        
        .class-capacity {
            display: flex;
            align-items: center;
        }
        
        .class-capacity-bar {
            height: 6px;
            width: 100px;
            background-color: #e9ecef;
            border-radius: 3px;
            margin-left: 8px;
            overflow: hidden;
        }
        
        .capacity-fill {
            height: 100%;
            background-color: var(--primary);
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            color: #888;
            font-size: 0.85rem;
        }
        
        .activity-icon {
            margin-right: 8px;
            color: var(--primary);
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .action-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border-radius: 8px;
            background-color: #f8f9fa;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
        }
        
        .action-button:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 992px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1 class="dashboard-title">Trainer Dashboard</h1>
                <p class="dashboard-welcome">Welcome back, <?php echo htmlspecialchars($trainer['name']); ?>!</p>
            </div>
            <a href="<?php echo $base_url; ?>pages/profile.php" class="btn btn-outline">Edit Profile</a>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <h2 class="stat-value"><?php echo $class_count; ?></h2>
                <p class="stat-label">Total Classes</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h2 class="stat-value"><?php echo $client_count; ?></h2>
                <p class="stat-label">Active Clients</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h2 class="stat-value"><?php echo count($upcoming_classes); ?></h2>
                <p class="stat-label">Upcoming Classes</p>
            </div>
        </div>
        
        <div class="dashboard-sections">
            <div>
                <div class="dashboard-section">
                    <h3 class="section-title">Upcoming Classes</h3>
                    
                    <?php if (empty($upcoming_classes)): ?>
                        <p>No upcoming classes scheduled.</p>
                    <?php else: ?>
                        <?php foreach ($upcoming_classes as $class): ?>
                            <div class="class-card">
                                <div class="class-info">
                                    <h4 class="class-title"><?php echo htmlspecialchars($class['name']); ?></h4>
                                    <div class="class-details">
                                        <div>
                                            <i class="fas fa-calendar-day"></i> 
                                            <?php echo htmlspecialchars($class['day_of_week']); ?>
                                        </div>
                                        <div>
                                            <i class="far fa-clock"></i> 
                                            <?php 
                                                echo date('g:i A', strtotime($class['start_time'])); 
                                                echo ' - '; 
                                                echo date('g:i A', strtotime($class['end_time']));
                                            ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($class['room']); ?>
                                        </div>
                                    </div>
                                    <div class="class-capacity">
                                        <span>
                                            <?php 
                                                echo htmlspecialchars($class['booked_count']); 
                                                echo '/';
                                                echo htmlspecialchars($class['capacity']); 
                                            ?> booked
                                        </span>
                                        <div class="class-capacity-bar">
                                            <?php 
                                                $fill_percentage = ($class['booked_count'] / $class['capacity']) * 100;
                                                echo '<div class="capacity-fill" style="width: ' . $fill_percentage . '%"></div>';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="<?php echo $base_url; ?>pages/schedule.php" class="btn btn-outline">View Full Schedule</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <div class="dashboard-section">
                    <h3 class="section-title">Quick Actions</h3>
                    <div class="action-buttons">
                        <a href="<?php echo $base_url; ?>pages/schedule.php" class="action-button">
                            <div class="action-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <span>My Schedule</span>
                        </a>
                        <a href="<?php echo $base_url; ?>pages/classes.php" class="action-button">
                            <div class="action-icon">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <span>My Classes</span>
                        </a>
                        <a href="<?php echo $base_url; ?>pages/clients.php" class="action-button">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span>My Clients</span>
                        </a>
                        <a href="<?php echo $base_url; ?>pages/profile.php" class="action-button">
                            <div class="action-icon">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <span>Edit Profile</span>
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-section">
                    <h3 class="section-title">Recent Activity</h3>
                    
                    <?php if (empty($recent_activity)): ?>
                        <p>No recent activity to display.</p>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($recent_activity as $activity): ?>
                                <li class="activity-item">
                                    <div>
                                        <i class="fas fa-user-check activity-icon"></i>
                                        <strong><?php echo htmlspecialchars($activity['username']); ?></strong> 
                                        booked your <strong><?php echo htmlspecialchars($activity['class_name']); ?></strong> class
                                        for <?php echo htmlspecialchars($activity['day_of_week']); ?> at
                                        <?php echo date('g:i A', strtotime($activity['start_time'])); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php 
                                            $booking_date = strtotime($activity['created_at']);
                                            $time_diff = time() - $booking_date;
                                            
                                            if ($time_diff < 60) {
                                                echo 'Just now';
                                            } elseif ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' minutes ago';
                                            } elseif ($time_diff < 86400) {
                                                echo floor($time_diff / 3600) . ' hours ago';
                                            } else {
                                                echo date('M j, Y', $booking_date);
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
    
    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>