<?php
// Suppress any PHP warnings that might break JSON response
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to book classes.']);
    exit();
}

// Check if user is a member
if ($_SESSION['role'] !== 'member') {
    echo json_encode(['success' => false, 'message' => 'Only members can book classes.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$pdo = getConnection();
$user_id = $_SESSION['user_id'];
$schedule_id = $_POST['schedule_id'] ?? null;

if (!$schedule_id) {
    echo json_encode(['success' => false, 'message' => 'Schedule ID is required.']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get class schedule details with capacity check
    $schedule_stmt = $pdo->prepare("
        SELECT 
            cs.*,
            c.name as class_name,
            c.capacity,
            c.trainer_id,
            (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id AND cb.status = 'confirmed') as current_bookings
        FROM class_schedules cs
        JOIN classes c ON cs.class_id = c.id
        WHERE cs.id = ? AND cs.is_active = 1
    ");
    $schedule_stmt->execute([$schedule_id]);
    $schedule = $schedule_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$schedule) {
        throw new Exception('Class schedule not found or not available.');
    }
    
    // Check if class is full
    if ($schedule['current_bookings'] >= $schedule['capacity']) {
        throw new Exception('This class is fully booked.');
    }
    
    // Check if user has an active membership with this trainer
    $membership_stmt = $pdo->prepare("
        SELECT um.* 
        FROM user_memberships um 
        WHERE um.user_id = ? 
        AND um.trainer_id = ? 
        AND um.status = 'active' 
        AND um.end_date >= CURDATE()
        LIMIT 1
    ");
    $membership_stmt->execute([$user_id, $schedule['trainer_id']]);
    $membership = $membership_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$membership) {
        throw new Exception('You must have an active membership with this trainer to book their classes.');
    }
    
    // Check if user has already booked this class
    $existing_booking_stmt = $pdo->prepare("
        SELECT id FROM class_bookings 
        WHERE user_id = ? AND schedule_id = ? AND status IN ('confirmed', 'pending')
    ");
    $existing_booking_stmt->execute([$user_id, $schedule_id]);
    $existing_booking = $existing_booking_stmt->fetch();
    
    if ($existing_booking) {
        throw new Exception('You have already booked this class.');
    }
    
    // Create the booking
    $booking_stmt = $pdo->prepare("
        INSERT INTO class_bookings (user_id, schedule_id, status, booking_date, created_at) 
        VALUES (?, ?, 'confirmed', CURDATE(), NOW())
    ");
    $booking_stmt->execute([$user_id, $schedule_id]);
    
    // Log the activity
    if ($pdo->query("SHOW TABLES LIKE 'activity_logs'")->rowCount() > 0) {
        $log_stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, created_at) 
            VALUES (?, 'class_booking', ?, NOW())
        ");
        $log_stmt->execute([
            $user_id, 
            "Booked class: {$schedule['class_name']} on {$schedule['day_of_week']} at {$schedule['start_time']}"
        ]);
    }
    
    $pdo->commit();
    
    // Send clean JSON response
    echo json_encode([
        'success' => true, 
        'message' => 'Class booked successfully!'
    ]);
    exit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
