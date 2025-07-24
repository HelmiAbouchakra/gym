<?php
// Debug script to check database data
require_once __DIR__ . '/includes/db_connect.php';
$pdo = getConnection();

echo "<h2>Database Debug Information</h2>";

try {
    // Check users table
    $users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $users_count = $users_stmt->fetch()['count'];
    echo "<p><strong>Users:</strong> {$users_count} records</p>";
    
    if ($users_count > 0) {
        $users_sample = $pdo->query("SELECT id, username, role FROM users LIMIT 5")->fetchAll();
        echo "<ul>";
        foreach ($users_sample as $user) {
            echo "<li>ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}</li>";
        }
        echo "</ul>";
    }
    
    // Check trainers table
    $trainers_stmt = $pdo->query("SELECT COUNT(*) as count FROM trainers");
    $trainers_count = $trainers_stmt->fetch()['count'];
    echo "<p><strong>Trainers:</strong> {$trainers_count} records</p>";
    
    if ($trainers_count > 0) {
        $trainers_sample = $pdo->query("SELECT t.id, t.name, t.user_id, u.username FROM trainers t LEFT JOIN users u ON t.user_id = u.id LIMIT 5")->fetchAll();
        echo "<ul>";
        foreach ($trainers_sample as $trainer) {
            echo "<li>ID: {$trainer['id']}, Name: {$trainer['name']}, User ID: {$trainer['user_id']}, Username: {$trainer['username']}</li>";
        }
        echo "</ul>";
    }
    
    // Check classes table
    $classes_stmt = $pdo->query("SELECT COUNT(*) as count FROM classes");
    $classes_count = $classes_stmt->fetch()['count'];
    echo "<p><strong>Classes:</strong> {$classes_count} records</p>";
    
    if ($classes_count > 0) {
        $classes_sample = $pdo->query("SELECT c.id, c.name, c.trainer_id, t.name as trainer_name FROM classes c LEFT JOIN trainers t ON c.trainer_id = t.id LIMIT 5")->fetchAll();
        echo "<ul>";
        foreach ($classes_sample as $class) {
            echo "<li>ID: {$class['id']}, Name: {$class['name']}, Trainer ID: {$class['trainer_id']}, Trainer: {$class['trainer_name']}</li>";
        }
        echo "</ul>";
    }
    
    // Check class_schedules table
    $schedules_stmt = $pdo->query("SELECT COUNT(*) as count FROM class_schedules");
    $schedules_count = $schedules_stmt->fetch()['count'];
    echo "<p><strong>Class Schedules:</strong> {$schedules_count} records</p>";
    
    if ($schedules_count > 0) {
        $schedules_sample = $pdo->query("SELECT cs.id, cs.class_id, cs.day_of_week, cs.start_time, c.name as class_name FROM class_schedules cs LEFT JOIN classes c ON cs.class_id = c.id LIMIT 5")->fetchAll();
        echo "<ul>";
        foreach ($schedules_sample as $schedule) {
            echo "<li>ID: {$schedule['id']}, Class: {$schedule['class_name']}, Day: {$schedule['day_of_week']}, Time: {$schedule['start_time']}</li>";
        }
        echo "</ul>";
    }
    
    // Check class_bookings table
    $bookings_stmt = $pdo->query("SELECT COUNT(*) as count FROM class_bookings");
    $bookings_count = $bookings_stmt->fetch()['count'];
    echo "<p><strong>Class Bookings:</strong> {$bookings_count} records</p>";
    
    if ($bookings_count > 0) {
        $bookings_sample = $pdo->query("SELECT cb.id, cb.user_id, cb.schedule_id, cb.status, u.username FROM class_bookings cb LEFT JOIN users u ON cb.user_id = u.id LIMIT 5")->fetchAll();
        echo "<ul>";
        foreach ($bookings_sample as $booking) {
            echo "<li>ID: {$booking['id']}, User: {$booking['username']}, Schedule ID: {$booking['schedule_id']}, Status: {$booking['status']}</li>";
        }
        echo "</ul>";
    }
    
    // Check current session user
    session_start();
    if (isset($_SESSION['user_id'])) {
        echo "<p><strong>Current Session:</strong> User ID: {$_SESSION['user_id']}, Role: {$_SESSION['role']}</p>";
        
        // Check if current user is a trainer
        if ($_SESSION['role'] === 'trainer') {
            $trainer_check = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
            $trainer_check->execute([$_SESSION['user_id']]);
            $trainer_data = $trainer_check->fetch();
            if ($trainer_data) {
                echo "<p><strong>Trainer ID:</strong> {$trainer_data['id']}</p>";
            } else {
                echo "<p><strong>Warning:</strong> User is marked as trainer but no trainer record found!</p>";
            }
        }
    } else {
        echo "<p><strong>No active session</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
p { margin: 10px 0; }
ul { margin: 5px 0 15px 20px; }
li { margin: 3px 0; }
</style>
