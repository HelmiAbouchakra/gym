<?php
// Start session
session_start();

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/db_config.php';

// Check if class ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid class ID']);
    exit();
}

$class_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Get trainer ID
    $trainer_stmt = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainer_stmt->execute([$user_id]);
    $trainer = $trainer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trainer) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Trainer not found']);
        exit();
    }
    
    $trainer_id = $trainer['id'];
    
    // Get class details - make sure it belongs to this trainer
    $class_stmt = $pdo->prepare("
        SELECT 
            c.*,
            (SELECT COUNT(*) FROM class_schedules cs WHERE cs.class_id = c.id) as schedule_count,
            (
                SELECT COUNT(DISTINCT cb.user_id)
                FROM class_bookings cb
                JOIN class_schedules cs ON cb.schedule_id = cs.id
                WHERE cs.class_id = c.id AND cb.status = 'confirmed'
            ) as total_students
        FROM classes c
        WHERE c.id = ? AND c.trainer_id = ?
    ");
    
    $class_stmt->execute([$class_id, $trainer_id]);
    $class = $class_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        exit();
    }
    
    // Get class schedules
    $schedule_stmt = $pdo->prepare("
        SELECT 
            cs.day_of_week,
            TIME_FORMAT(cs.start_time, '%h:%i %p') as start_time,
            TIME_FORMAT(cs.end_time, '%h:%i %p') as end_time,
            cs.room
        FROM class_schedules cs
        WHERE cs.class_id = ?
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
    ");
    
    $schedule_stmt->execute([$class_id]);
    $schedules = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'class' => [
            'id' => $class['id'],
            'name' => $class['name'],
            'description' => $class['description'],
            'duration' => $class['duration'],
            'capacity' => $class['capacity'],
            'difficulty_level' => ucfirst($class['difficulty_level']),
            'is_active' => (bool)$class['is_active'],
            'schedule_count' => $class['schedule_count'],
            'total_students' => $class['total_students']
        ],
        'schedules' => $schedules
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
