<?php
// Class Clients Management Page
session_start();

// Check if user is logged in and is a trainer or admin
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

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];
    
    $update_stmt = $pdo->prepare("UPDATE class_bookings SET status = ? WHERE id = ?");
    $update_stmt->execute([$status, $booking_id]);
    $success_message = "Attendance updated successfully!";
}

try {
    // Get all classes with their bookings
    $classes_query = "
        SELECT 
            c.id as class_id,
            c.name as class_name,
            c.capacity,
            c.difficulty_level,
            cs.id as schedule_id,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            cs.room,
            t.name as trainer_name,
            COUNT(cb.id) as total_bookings,
            SUM(CASE WHEN cb.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN cb.status = 'attended' THEN 1 ELSE 0 END) as attended_count,
            SUM(CASE WHEN cb.status = 'no-show' THEN 1 ELSE 0 END) as no_show_count
        FROM classes c
        JOIN class_schedules cs ON c.id = cs.class_id
        JOIN trainers t ON c.trainer_id = t.id
        LEFT JOIN class_bookings cb ON cs.id = cb.schedule_id
        WHERE c.is_active = 1 AND cs.is_active = 1
        " . ($trainer_id ? "AND c.trainer_id = ?" : "") . "
        GROUP BY c.id, cs.id
        ORDER BY cs.day_of_week, cs.start_time
    ";
    
    $classes_stmt = $pdo->prepare($classes_query);
    if ($trainer_id) {
        $classes_stmt->execute([$trainer_id]);
    } else {
        $classes_stmt->execute();
    }
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed bookings for selected class
    $selected_class_id = $_GET['class_id'] ?? null;
    $selected_schedule_id = $_GET['schedule_id'] ?? null;
    $class_bookings = [];
    
    if ($selected_class_id && $selected_schedule_id) {
        $bookings_query = "
            SELECT 
                cb.*,
                u.username,
                u.email,
                u.profile_image,
                c.name as class_name,
                cs.day_of_week,
                cs.start_time,
                cs.end_time,
                cs.room
            FROM class_bookings cb
            JOIN users u ON cb.user_id = u.id
            JOIN class_schedules cs ON cb.schedule_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            WHERE cs.id = ? AND c.id = ?
            ORDER BY cb.created_at ASC
        ";
        
        $bookings_stmt = $pdo->prepare($bookings_query);
        $bookings_stmt->execute([$selected_schedule_id, $selected_class_id]);
        $class_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Error loading class data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Clients - FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .clients-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .classes-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-top: 20px;
        }
        .classes-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            height: fit-content;
        }
        .class-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .class-item:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        .class-item.active {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .class-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .class-schedule {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 8px;
        }
        .class-stats {
            display: flex;
            gap: 15px;
            font-size: 0.8em;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .stat-confirmed { color: #28a745; }
        .stat-attended { color: #007bff; }
        .stat-no-show { color: #dc3545; }
        
        .clients-detail {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .clients-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .client-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .client-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .client-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .client-details h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .client-details p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-attended { background: #cce5ff; color: #004085; }
        .status-no-show { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        
        .attendance-controls {
            display: flex;
            gap: 10px;
            margin-left: 15px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .summary-card i {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .summary-card h3 {
            margin: 0;
            font-size: 1.8em;
            color: #333;
        }
        .summary-card p {
            margin: 5px 0 0 0;
            color: #666;
        }
        .card-blue i { color: #007bff; }
        .card-green i { color: #28a745; }
        .card-orange i { color: #fd7e14; }
        .card-red i { color: #dc3545; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="clients-container">
        <h1><i class="fas fa-users"></i> Class Clients Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <?php
            $total_classes = count($classes);
            $total_bookings = array_sum(array_column($classes, 'total_bookings'));
            $total_attended = array_sum(array_column($classes, 'attended_count'));
            $total_no_shows = array_sum(array_column($classes, 'no_show_count'));
            ?>
            <div class="summary-card card-blue">
                <i class="fas fa-calendar-alt"></i>
                <h3><?php echo $total_classes; ?></h3>
                <p>Total Classes</p>
            </div>
            <div class="summary-card card-green">
                <i class="fas fa-users"></i>
                <h3><?php echo $total_bookings; ?></h3>
                <p>Total Bookings</p>
            </div>
            <div class="summary-card card-orange">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $total_attended; ?></h3>
                <p>Attended</p>
            </div>
            <div class="summary-card card-red">
                <i class="fas fa-times-circle"></i>
                <h3><?php echo $total_no_shows; ?></h3>
                <p>No Shows</p>
            </div>
        </div>
        
        <div class="classes-grid">
            <!-- Classes List -->
            <div class="classes-list">
                <h3><i class="fas fa-list"></i> Classes</h3>
                
                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No classes found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($classes as $class): ?>
                        <div class="class-item <?php echo ($selected_class_id == $class['class_id'] && $selected_schedule_id == $class['schedule_id']) ? 'active' : ''; ?>"
                             onclick="window.location.href='?class_id=<?php echo $class['class_id']; ?>&schedule_id=<?php echo $class['schedule_id']; ?>'">
                            <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                            <div class="class-schedule">
                                <i class="fas fa-calendar"></i> <?php echo $class['day_of_week']; ?> 
                                <?php echo date('g:i A', strtotime($class['start_time'])); ?>
                                <br>
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($class['trainer_name']); ?>
                                <?php if ($class['room']): ?>
                                    <br><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($class['room']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="class-stats">
                                <div class="stat-item stat-confirmed">
                                    <i class="fas fa-users"></i> <?php echo $class['confirmed_bookings']; ?>/<?php echo $class['capacity']; ?>
                                </div>
                                <div class="stat-item stat-attended">
                                    <i class="fas fa-check"></i> <?php echo $class['attended_count']; ?>
                                </div>
                                <div class="stat-item stat-no-show">
                                    <i class="fas fa-times"></i> <?php echo $class['no_show_count']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Class Details -->
            <div class="clients-detail">
                <?php if (empty($class_bookings) && !$selected_class_id): ?>
                    <div class="empty-state">
                        <i class="fas fa-mouse-pointer"></i>
                        <h3>Select a Class</h3>
                        <p>Click on a class from the left to view its clients and manage attendance.</p>
                    </div>
                <?php elseif (empty($class_bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Bookings</h3>
                        <p>This class has no bookings yet.</p>
                    </div>
                <?php else: ?>
                    <div class="clients-header">
                        <h3><i class="fas fa-users"></i> Class Clients</h3>
                        <p>
                            <strong><?php echo htmlspecialchars($class_bookings[0]['class_name']); ?></strong><br>
                            <?php echo $class_bookings[0]['day_of_week']; ?> 
                            <?php echo date('g:i A', strtotime($class_bookings[0]['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($class_bookings[0]['end_time'])); ?>
                            <?php if ($class_bookings[0]['room']): ?>
                                | Room: <?php echo htmlspecialchars($class_bookings[0]['room']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php foreach ($class_bookings as $booking): ?>
                        <div class="client-card">
                            <div class="client-info">
                                <div class="client-avatar">
                                    <?php if ($booking['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($booking['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($booking['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="client-details">
                                    <h4><?php echo htmlspecialchars($booking['username']); ?></h4>
                                    <p><?php echo htmlspecialchars($booking['email']); ?></p>
                                    <p><small>Booked: <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></small></p>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                    <?php echo ucfirst(str_replace('-', ' ', $booking['status'])); ?>
                                </span>
                                
                                <?php if ($user_role === 'trainer' || $user_role === 'admin'): ?>
                                    <div class="attendance-controls">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="attended">
                                            <button type="submit" name="mark_attendance" class="btn-sm btn-success" title="Mark as Attended">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="no-show">
                                            <button type="submit" name="mark_attendance" class="btn-sm btn-danger" title="Mark as No-Show">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" name="mark_attendance" class="btn-sm btn-secondary" title="Reset to Confirmed">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
