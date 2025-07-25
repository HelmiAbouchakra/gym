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
    
    // Get members who selected this trainer in their memberships
    $members_stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.profile_image, 
               um.start_date, um.end_date, um.status,
               mp.name as plan_name, mp.price
        FROM user_memberships um
        JOIN users u ON um.user_id = u.id
        JOIN membership_plans mp ON um.plan_id = mp.id
        WHERE um.trainer_id = ? AND um.status = 'active'
        ORDER BY um.created_at DESC
    ");
    $members_stmt->execute([$trainer_id]);
    $assigned_members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total assigned members (through memberships)
    $assigned_members_count = count($assigned_members);
    
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
    <link rel="stylesheet" href="../assets/css/trainer.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <style>
        .member-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fff;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .member-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .member-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .member-details {
            font-size: 0.85rem;
            color: #666;
            margin-top: 2px;
        }
        
        .member-dates {
            font-size: 0.8rem;
            color: #888;
        }
        
        .member-status {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-expired {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .status-cancelled {
            background-color: #fafafa;
            color: #616161;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #ff8f00;
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
            <div class="dashboard-nav-grid">
                <a href="manage-schedules.php" class="nav-card primary">
                    <div class="nav-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Manage Class Schedules</h4>
                        <p>Create and edit your weekly class schedules</p>
                    </div>
                </a>
                
                <a href="upcoming-classes.php" class="nav-card secondary">
                    <div class="nav-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Upcoming Classes</h4>
                        <p>View and manage your scheduled classes</p>
                    </div>
                </a>
                
                <a href="class-clients.php" class="nav-card tertiary">
                    <div class="nav-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Class Clients</h4>
                        <p>Manage your class participants</p>
                    </div>
                </a>
                
                <a href="schedule.php" class="nav-card quaternary">
                    <div class="nav-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="nav-content">
                        <h4>View My Schedule</h4>
                        <p>See your personal training schedule</p>
                    </div>
                </a>
                
                <a href="class-statistics.php" class="nav-card accent">
                    <div class="nav-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Statistics</h4>
                        <p>View class and performance analytics</p>
                    </div>
                </a>
                
                <a href="<?php echo $base_url; ?>pages/profile.php" class="nav-card outline">
                    <div class="nav-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Edit Profile</h4>
                        <p>Update your trainer information</p>
                    </div>
                </a>
            </div>
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
                <p class="stat-label">Class Clients</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <h2 class="stat-value"><?php echo $assigned_members_count; ?></h2>
                <p class="stat-label">Membership Clients</p>
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
                    <h3 class="section-title">Members With Your Membership Plans</h3>
                    
                    <?php if (empty($assigned_members)): ?>
                        <p>No members have selected you as their trainer yet.</p>
                    <?php else: ?>
                        <ul class="member-list">
                            <?php foreach ($assigned_members as $member): ?>
                                <li class="member-item">
                                    <div class="member-info">
                                        <img src="<?php echo !empty($member['profile_image']) ? $base_url . $member['profile_image'] : $base_url . 'assets/images/default-avatar.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($member['username']); ?>" class="member-avatar">
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['username']); ?></strong>
                                            <div class="member-details">
                                                <span><?php echo htmlspecialchars($member['plan_name']); ?> - $<?php echo htmlspecialchars($member['price']); ?>/month</span><br>
                                                <span class="member-dates">From: <?php echo htmlspecialchars($member['start_date']); ?> To: <?php echo htmlspecialchars($member['end_date']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="member-status status-<?php echo strtolower($member['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($member['status'])); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
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
    
    <style>
    /* Premium Navigation Cards Styling */
    .dashboard-nav-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 40px 0;
        padding: 0;
        justify-content: center;
    }
    
    .nav-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 25px 20px;
        border-radius: 20px;
        text-decoration: none;
        color: white;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        position: relative;
        overflow: hidden;
        min-height: 160px;
        width: 180px;
        flex-shrink: 0;
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
    }
    
    .nav-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.05) 50%, rgba(0, 0, 0, 0.05) 100%);
        z-index: 1;
        transition: opacity 0.3s ease;
    }
    
    .nav-card::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
        transform: translateX(-100%) translateY(-100%) rotate(45deg);
        transition: transform 0.6s ease;
        z-index: 3;
    }
    
    .nav-card:hover::after {
        transform: translateX(100%) translateY(100%) rotate(45deg);
    }
    
    .nav-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        text-decoration: none;
        color: white;
    }
    
    .nav-card:hover::before {
        opacity: 0.8;
    }
    
    /* Premium Color Variations with Enhanced Gradients */
    .nav-card.primary {
        background: linear-gradient(135deg, #ff5722 0%, #ff7043 25%, #ff8a65 50%, #e64a19 100%);
        box-shadow: 0 10px 30px rgba(255, 87, 34, 0.3);
    }
    
    .nav-card.primary:hover {
        box-shadow: 0 20px 40px rgba(255, 87, 34, 0.4);
    }
    
    .nav-card.secondary {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 25%, #3c5a78 50%, #1a252f 100%);
        box-shadow: 0 10px 30px rgba(44, 62, 80, 0.3);
    }
    
    .nav-card.secondary:hover {
        box-shadow: 0 20px 40px rgba(44, 62, 80, 0.4);
    }
    
    .nav-card.tertiary {
        background: linear-gradient(135deg, #28a745 0%, #34ce57 25%, #4ade80 50%, #20c997 100%);
        box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
    }
    
    .nav-card.tertiary:hover {
        box-shadow: 0 20px 40px rgba(40, 167, 69, 0.4);
    }
    
    .nav-card.quaternary {
        background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 25%, #a78bfa 50%, #5a2d91 100%);
        box-shadow: 0 10px 30px rgba(111, 66, 193, 0.3);
    }
    
    .nav-card.quaternary:hover {
        box-shadow: 0 20px 40px rgba(111, 66, 193, 0.4);
    }
    
    .nav-card.accent {
        background: linear-gradient(135deg, #fd7e14 0%, #ff9500 25%, #ffab40 50%, #e55100 100%);
        box-shadow: 0 10px 30px rgba(253, 126, 20, 0.3);
    }
    
    .nav-card.accent:hover {
        box-shadow: 0 20px 40px rgba(253, 126, 20, 0.4);
    }
    
    .nav-card.outline {
        background: linear-gradient(135deg, #6c757d 0%, #868e96 25%, #adb5bd 50%, #495057 100%);
        box-shadow: 0 10px 30px rgba(108, 117, 125, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    .nav-card.outline:hover {
        box-shadow: 0 20px 40px rgba(108, 117, 125, 0.4);
    }
    
    .nav-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.95;
        z-index: 4;
        position: relative;
        transition: all 0.4s ease;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    
    .nav-content {
        z-index: 4;
        position: relative;
        text-align: center;
    }
    
    .nav-content h4 {
        font-family: 'Montserrat', sans-serif;
        font-weight: 700;
        font-size: 1.3rem;
        margin: 0 0 10px 0;
        color: white;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        letter-spacing: -0.5px;
        transition: all 0.3s ease;
    }
    
    .nav-content p {
        font-size: 0.95rem;
        margin: 0;
        opacity: 0.95;
        line-height: 1.5;
        color: rgba(255, 255, 255, 0.95);
        text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        font-weight: 400;
        transition: all 0.3s ease;
    }
    
    /* Enhanced Hover Effects */
    .nav-card:hover .nav-icon {
        transform: scale(1.15) rotate(5deg);
        opacity: 1;
        text-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }
    
    .nav-card:hover .nav-content h4 {
        color: white;
        transform: translateY(-3px);
        text-shadow: 0 3px 15px rgba(0, 0, 0, 0.4);
    }
    
    .nav-card:hover .nav-content p {
        opacity: 1;
        transform: translateY(-3px);
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .dashboard-nav-grid {
            justify-content: center;
        }
        
        .nav-card {
            width: 160px;
            min-height: 140px;
            padding: 20px 15px;
        }
        
        .nav-icon {
            font-size: 2.5rem;
        }
        
        .nav-content h4 {
            font-size: 1.1rem;
        }
        
        .nav-content p {
            font-size: 0.85rem;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-nav-grid {
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin: 25px 0;
        }
        
        .nav-card {
            width: 280px;
            min-height: 120px;
            padding: 20px;
            border-radius: 16px;
        }
        
        .nav-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }
        
        .nav-content h4 {
            font-size: 1.2rem;
        }
        
        .nav-content p {
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 480px) {
        .nav-card {
            width: 250px;
            min-height: 140px;
            padding: 25px 15px;
        }
        
        .nav-icon {
            font-size: 2.8rem;
            margin-bottom: 15px;
        }
        
        .nav-content h4 {
            font-size: 1.15rem;
            margin-bottom: 8px;
        }
        
        .nav-content p {
            font-size: 0.85rem;
        }
    }
    
    /* Enhanced Animation for page load */
    .nav-card {
        opacity: 0;
        transform: translateY(50px) scale(0.9);
        animation: premiumSlideIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    
    .nav-card:nth-child(1) { animation-delay: 0.1s; }
    .nav-card:nth-child(2) { animation-delay: 0.2s; }
    .nav-card:nth-child(3) { animation-delay: 0.3s; }
    .nav-card:nth-child(4) { animation-delay: 0.4s; }
    .nav-card:nth-child(5) { animation-delay: 0.5s; }
    .nav-card:nth-child(6) { animation-delay: 0.6s; }
    
    @keyframes premiumSlideIn {
        0% {
            opacity: 0;
            transform: translateY(50px) scale(0.9);
        }
        60% {
            opacity: 0.8;
            transform: translateY(-10px) scale(1.02);
        }
        100% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    /* Additional Premium Effects */
    .nav-card:active {
        transform: translateY(-5px) scale(0.98);
        transition: all 0.1s ease;
    }
    
    /* Subtle pulse animation for primary card */
    .nav-card.primary {
        animation: premiumSlideIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards,
                   subtlePulse 3s ease-in-out infinite 2s;
    }
    
    @keyframes subtlePulse {
        0%, 100% {
            box-shadow: 0 10px 30px rgba(255, 87, 34, 0.3);
        }
        50% {
            box-shadow: 0 15px 35px rgba(255, 87, 34, 0.4);
        }
    }
    </style>
</body>
</html>