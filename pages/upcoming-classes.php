<?php
// Enhanced Upcoming Classes Page
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

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$current_day = date('l'); // Full day name

try {
    // Get upcoming classes for the next 7 days
    $upcoming_query = "
        SELECT 
            c.id as class_id,
            c.name as class_name,
            c.description,
            c.duration,
            c.capacity,
            c.difficulty_level,
            cs.id as schedule_id,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            cs.room,
            t.name as trainer_name,
            t.specialties,
            (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id AND cb.status = 'confirmed') as booked_count,
            (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id AND cb.user_id = ? AND cb.status = 'confirmed') as user_booked
        FROM classes c
        JOIN class_schedules cs ON c.id = cs.class_id
        JOIN trainers t ON c.trainer_id = t.id
        WHERE c.is_active = 1 AND cs.is_active = 1
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
    ";
    
    $upcoming_stmt = $pdo->prepare($upcoming_query);
    $upcoming_stmt->execute([$user_id]);
    $upcoming_classes = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group classes by day
    $classes_by_day = [];
    foreach ($upcoming_classes as $class) {
        $classes_by_day[$class['day_of_week']][] = $class;
    }
    
    // Handle class booking
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_class'])) {
        $schedule_id = $_POST['schedule_id'];
        
        // Check if user already booked this class
        $check_stmt = $pdo->prepare("SELECT id FROM class_bookings WHERE user_id = ? AND schedule_id = ?");
        $check_stmt->execute([$user_id, $schedule_id]);
        
        if (!$check_stmt->fetch()) {
            // Book the class
            $book_stmt = $pdo->prepare("INSERT INTO class_bookings (user_id, schedule_id, booking_date, status) VALUES (?, ?, CURDATE(), 'confirmed')");
            $book_stmt->execute([$user_id, $schedule_id]);
            $success_message = "Class booked successfully!";
        } else {
            $error_message = "You have already booked this class.";
        }
        
        // Refresh the page to show updated data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
} catch (Exception $e) {
    $error_message = "Error loading classes: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Classes - FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .classes-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .day-section {
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .day-header {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .class-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .class-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        .class-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }
        .class-time {
            color: #007bff;
            font-weight: bold;
        }
        .class-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-item i {
            color: #007bff;
            width: 16px;
        }
        .capacity-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 5px;
        }
        .capacity-fill {
            background: #28a745;
            height: 100%;
            transition: width 0.3s ease;
        }
        .capacity-fill.full {
            background: #dc3545;
        }
        .book-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .book-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .booked-badge {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
        }
        .difficulty-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .difficulty-beginner { background: #d4edda; color: #155724; }
        .difficulty-intermediate { background: #fff3cd; color: #856404; }
        .difficulty-advanced { background: #f8d7da; color: #721c24; }
        .alert {
            padding: 15px;
            margin: 20px 0;
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
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="classes-container">
        <h1><i class="fas fa-calendar-alt"></i> Upcoming Classes</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($upcoming_classes)): ?>
            <div class="alert alert-error">
                <h3>No Classes Scheduled</h3>
                <p>There are currently no upcoming classes scheduled. Please check back later or contact the gym for more information.</p>
            </div>
        <?php else: ?>
            <?php foreach ($classes_by_day as $day => $day_classes): ?>
                <div class="day-section">
                    <h2 class="day-header">
                        <i class="fas fa-calendar-day"></i> <?php echo $day; ?>
                        <small>(<?php echo count($day_classes); ?> classes)</small>
                    </h2>
                    
                    <?php foreach ($day_classes as $class): ?>
                        <?php
                        $capacity_percentage = $class['capacity'] > 0 ? ($class['booked_count'] / $class['capacity']) * 100 : 0;
                        $is_full = $class['booked_count'] >= $class['capacity'];
                        $is_booked = $class['user_booked'] > 0;
                        ?>
                        
                        <div class="class-card">
                            <div class="class-header">
                                <div>
                                    <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                    <div class="class-time">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('g:i A', strtotime($class['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($is_booked): ?>
                                        <span class="booked-badge"><i class="fas fa-check"></i> Booked</span>
                                    <?php endif; ?>
                                    <span class="difficulty-badge difficulty-<?php echo $class['difficulty_level']; ?>">
                                        <?php echo ucfirst($class['difficulty_level']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($class['description']): ?>
                                <p><?php echo htmlspecialchars($class['description']); ?></p>
                            <?php endif; ?>
                            
                            <div class="class-details">
                                <div class="detail-item">
                                    <i class="fas fa-user"></i>
                                    <span>Trainer: <strong><?php echo htmlspecialchars($class['trainer_name']); ?></strong></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Duration: <strong><?php echo $class['duration']; ?> minutes</strong></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Room: <strong><?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></strong></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span>
                                        Capacity: <strong><?php echo $class['booked_count']; ?>/<?php echo $class['capacity']; ?></strong>
                                        <div class="capacity-bar">
                                            <div class="capacity-fill <?php echo $is_full ? 'full' : ''; ?>" 
                                                 style="width: <?php echo min(100, $capacity_percentage); ?>%"></div>
                                        </div>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($class['specialties']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-star"></i>
                                    <span>Specialties: <em><?php echo htmlspecialchars($class['specialties']); ?></em></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($user_role === 'member' && !$is_booked): ?>
                                <form method="POST" style="margin-top: 15px;">
                                    <input type="hidden" name="schedule_id" value="<?php echo $class['schedule_id']; ?>">
                                    <button type="submit" name="book_class" class="book-btn" <?php echo $is_full ? 'disabled' : ''; ?>>
                                        <?php if ($is_full): ?>
                                            <i class="fas fa-times"></i> Class Full
                                        <?php else: ?>
                                            <i class="fas fa-plus"></i> Book This Class
                                        <?php endif; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
