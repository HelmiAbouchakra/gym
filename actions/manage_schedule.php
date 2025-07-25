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

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Trainers only.']);
    exit();
}

$trainer_id = $_SESSION['user_id'];

// Get trainer info
$trainer_stmt = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainer_stmt->execute([$trainer_id]);
$trainer = $trainer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$trainer) {
    echo json_encode(['success' => false, 'message' => 'Trainer profile not found.']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Handle different actions
    $action = $_POST['action'] ?? 'add';
    
    if ($action === 'update') {
        // Update schedule
        $schedule_id = $_POST['schedule_id'] ?? '';
        $class_id = $_POST['class_id'] ?? '';
        $day_of_week = $_POST['day_of_week'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate required fields
        if (empty($schedule_id) || empty($class_id) || empty($day_of_week) || empty($start_time) || empty($end_time)) {
            throw new Exception('All fields are required.');
        }
        
        // Validate time format and logic
        if (strtotime($start_time) >= strtotime($end_time)) {
            throw new Exception('End time must be after start time.');
        }
        
        // Verify the schedule belongs to this trainer
        $verify_stmt = $pdo->prepare("
            SELECT cs.id 
            FROM class_schedules cs 
            JOIN classes c ON cs.class_id = c.id 
            WHERE cs.id = ? AND c.trainer_id = ?
        ");
        $verify_stmt->execute([$schedule_id, $trainer['id']]);
        
        if (!$verify_stmt->fetch()) {
            throw new Exception('Schedule not found or access denied.');
        }
        
        // Verify the new class belongs to this trainer
        $class_stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND trainer_id = ?");
        $class_stmt->execute([$class_id, $trainer['id']]);
        
        if (!$class_stmt->fetch()) {
            throw new Exception('Class not found or access denied.');
        }
        
        // Check for time conflicts (excluding current schedule)
        $conflict_stmt = $pdo->prepare("
            SELECT cs.id 
            FROM class_schedules cs 
            JOIN classes c ON cs.class_id = c.id 
            WHERE c.trainer_id = ? 
            AND cs.day_of_week = ? 
            AND cs.is_active = 1
            AND cs.id != ?
            AND (
                (cs.start_time <= ? AND cs.end_time > ?) OR
                (cs.start_time < ? AND cs.end_time >= ?) OR
                (cs.start_time >= ? AND cs.end_time <= ?)
            )
        ");
        $conflict_stmt->execute([
            $trainer['id'], 
            $day_of_week, 
            $schedule_id,
            $start_time, $start_time,  // Check if new start time conflicts
            $end_time, $end_time,      // Check if new end time conflicts
            $start_time, $end_time     // Check if new schedule encompasses existing
        ]);
        
        if ($conflict_stmt->fetch()) {
            throw new Exception('Time conflict detected. You already have a class scheduled during this time on ' . $day_of_week . '.');
        }
        
        // Update the schedule
        $update_stmt = $pdo->prepare("
            UPDATE class_schedules 
            SET class_id = ?, day_of_week = ?, start_time = ?, end_time = ?, is_active = ?
            WHERE id = ?
        ");
        $update_stmt->execute([$class_id, $day_of_week, $start_time, $end_time, $is_active, $schedule_id]);
        
        // Log the activity
        if ($pdo->query("SHOW TABLES LIKE 'activity_logs'")->rowCount() > 0) {
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, created_at) 
                VALUES (?, 'schedule_updated', ?, NOW())
            ");
            $log_stmt->execute([
                $trainer_id, 
                "Updated class schedule for {$day_of_week} at {$start_time}-{$end_time}"
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Class schedule updated successfully!']);
        exit();
        
    } elseif ($action === 'delete') {
        // Delete schedule
        $schedule_id = $_POST['schedule_id'] ?? '';
        
        if (empty($schedule_id)) {
            throw new Exception('Schedule ID is required.');
        }
        
        // Verify the schedule belongs to this trainer
        $verify_stmt = $pdo->prepare("
            SELECT cs.id 
            FROM class_schedules cs 
            JOIN classes c ON cs.class_id = c.id 
            WHERE cs.id = ? AND c.trainer_id = ?
        ");
        $verify_stmt->execute([$schedule_id, $trainer['id']]);
        
        if (!$verify_stmt->fetch()) {
            throw new Exception('Schedule not found or access denied.');
        }
        
        // Delete the schedule
        $delete_stmt = $pdo->prepare("DELETE FROM class_schedules WHERE id = ?");
        $delete_stmt->execute([$schedule_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully!']);
        exit();
    }
    
    // Add new schedule (default action)
    $class_id = $_POST['class_id'] ?? '';
    $day_of_week = $_POST['day_of_week'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate required fields
    if (empty($class_id) || empty($day_of_week) || empty($start_time) || empty($end_time)) {
        throw new Exception('All fields are required.');
    }
    
    // Validate time format and logic
    if (strtotime($start_time) >= strtotime($end_time)) {
        throw new Exception('End time must be after start time.');
    }
    
    // Verify the class belongs to this trainer
    $class_stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND trainer_id = ?");
    $class_stmt->execute([$class_id, $trainer['id']]);
    
    if (!$class_stmt->fetch()) {
        throw new Exception('Class not found or access denied.');
    }
    
    // Check for time conflicts
    $conflict_stmt = $pdo->prepare("
        SELECT cs.id 
        FROM class_schedules cs 
        JOIN classes c ON cs.class_id = c.id 
        WHERE c.trainer_id = ? 
        AND cs.day_of_week = ? 
        AND cs.is_active = 1
        AND (
            (cs.start_time <= ? AND cs.end_time > ?) OR
            (cs.start_time < ? AND cs.end_time >= ?) OR
            (cs.start_time >= ? AND cs.end_time <= ?)
        )
    ");
    $conflict_stmt->execute([
        $trainer['id'], 
        $day_of_week, 
        $start_time, $start_time,  // Check if new start time conflicts
        $end_time, $end_time,      // Check if new end time conflicts
        $start_time, $end_time     // Check if new schedule encompasses existing
    ]);
    
    if ($conflict_stmt->fetch()) {
        throw new Exception('Time conflict detected. You already have a class scheduled during this time on ' . $day_of_week . '.');
    }
    
    // Insert the new schedule
    $insert_stmt = $pdo->prepare("
        INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $insert_stmt->execute([$class_id, $day_of_week, $start_time, $end_time, $is_active]);
    
    // Log the activity
    if ($pdo->query("SHOW TABLES LIKE 'activity_logs'")->rowCount() > 0) {
        $log_stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, created_at) 
            VALUES (?, 'schedule_created', ?, NOW())
        ");
        $log_stmt->execute([
            $trainer_id, 
            "Created class schedule for {$day_of_week} at {$start_time}-{$end_time}"
        ]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Class schedule added successfully!']);
    exit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>
