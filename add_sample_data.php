<?php
// Script to add sample data for testing class management features
require_once __DIR__ . '/includes/db_connect.php';
$pdo = getConnection();

echo "<h2>Adding Sample Data for Class Management</h2>";

try {
    $pdo->beginTransaction();
    
    // 1. First, let's check if we have any trainers
    $trainer_check = $pdo->query("SELECT COUNT(*) as count FROM trainers")->fetch()['count'];
    
    if ($trainer_check == 0) {
        echo "<p>No trainers found. Creating sample trainer...</p>";
        
        // Create a trainer user if none exists
        $trainer_user_check = $pdo->query("SELECT id FROM users WHERE role = 'trainer' LIMIT 1")->fetch();
        
        if (!$trainer_user_check) {
            // Create trainer user
            $trainer_stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $trainer_stmt->execute(['john_trainer', 'john@gym.com', password_hash('password123', PASSWORD_DEFAULT), 'trainer']);
            $trainer_user_id = $pdo->lastInsertId();
            echo "<p>Created trainer user: john_trainer (ID: $trainer_user_id)</p>";
        } else {
            $trainer_user_id = $trainer_user_check['id'];
            echo "<p>Found existing trainer user (ID: $trainer_user_id)</p>";
        }
        
        // Create trainer record
        $trainer_insert = $pdo->prepare("INSERT INTO trainers (user_id, name, specialization, experience_years, bio, hourly_rate) VALUES (?, ?, ?, ?, ?, ?)");
        $trainer_insert->execute([
            $trainer_user_id,
            'John Smith',
            'Strength Training, Cardio',
            5,
            'Experienced fitness trainer specializing in strength training and cardio workouts.',
            50.00
        ]);
        $trainer_id = $pdo->lastInsertId();
        echo "<p>Created trainer record: John Smith (ID: $trainer_id)</p>";
    } else {
        // Get existing trainer
        $trainer_data = $pdo->query("SELECT id, name FROM trainers LIMIT 1")->fetch();
        $trainer_id = $trainer_data['id'];
        echo "<p>Using existing trainer: {$trainer_data['name']} (ID: $trainer_id)</p>";
    }
    
    // 2. Create sample classes
    $classes_data = [
        ['Morning Yoga', 'beginner', 20, 'Start your day with relaxing yoga poses'],
        ['HIIT Workout', 'intermediate', 15, 'High-intensity interval training for maximum results'],
        ['Strength Training', 'advanced', 12, 'Build muscle with advanced weightlifting techniques'],
        ['Cardio Blast', 'intermediate', 25, 'High-energy cardio workout'],
        ['Pilates Core', 'beginner', 18, 'Strengthen your core with Pilates exercises']
    ];
    
    echo "<p>Creating sample classes...</p>";
    $class_ids = [];
    
    foreach ($classes_data as $class_data) {
        // Check if class already exists
        $existing_class = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
        $existing_class->execute([$class_data[0]]);
        
        if ($existing_class->fetch()) {
            echo "<p>Class '{$class_data[0]}' already exists, skipping...</p>";
            continue;
        }
        
        $class_stmt = $pdo->prepare("INSERT INTO classes (name, description, trainer_id, capacity, difficulty_level, duration, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $class_stmt->execute([
            $class_data[0],
            $class_data[3],
            $trainer_id,
            $class_data[2],
            $class_data[1],
            60
        ]);
        $class_ids[] = $pdo->lastInsertId();
        echo "<p>Created class: {$class_data[0]}</p>";
    }
    
    // 3. Create class schedules
    $schedule_data = [
        ['Monday', '09:00:00', '10:00:00', 'Room A'],
        ['Tuesday', '18:00:00', '19:00:00', 'Room B'],
        ['Wednesday', '07:00:00', '08:00:00', 'Room A'],
        ['Thursday', '19:00:00', '20:00:00', 'Room C'],
        ['Friday', '10:00:00', '11:00:00', 'Room B'],
        ['Saturday', '08:00:00', '09:00:00', 'Room A'],
        ['Sunday', '16:00:00', '17:00:00', 'Room B']
    ];
    
    echo "<p>Creating class schedules...</p>";
    $schedule_ids = [];
    
    for ($i = 0; $i < count($class_ids) && $i < count($schedule_data); $i++) {
        $schedule_stmt = $pdo->prepare("INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time, room, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $schedule_stmt->execute([
            $class_ids[$i],
            $schedule_data[$i][0],
            $schedule_data[$i][1],
            $schedule_data[$i][2],
            $schedule_data[$i][3]
        ]);
        $schedule_ids[] = $pdo->lastInsertId();
        echo "<p>Created schedule: {$classes_data[$i][0]} on {$schedule_data[$i][0]} at {$schedule_data[$i][1]}</p>";
    }
    
    // 4. Create some member users for bookings
    $members_data = [
        ['alice_member', 'alice@email.com'],
        ['bob_member', 'bob@email.com'],
        ['carol_member', 'carol@email.com'],
        ['david_member', 'david@email.com']
    ];
    
    echo "<p>Creating sample members...</p>";
    $member_ids = [];
    
    foreach ($members_data as $member_data) {
        // Check if member already exists
        $existing_member = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $existing_member->execute([$member_data[0]]);
        $existing_user = $existing_member->fetch();
        
        if ($existing_user) {
            $member_ids[] = $existing_user['id'];
            echo "<p>Member '{$member_data[0]}' already exists, using existing...</p>";
            continue;
        }
        
        $member_stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'member')");
        $member_stmt->execute([
            $member_data[0],
            $member_data[1],
            password_hash('password123', PASSWORD_DEFAULT)
        ]);
        $member_ids[] = $pdo->lastInsertId();
        echo "<p>Created member: {$member_data[0]}</p>";
    }
    
    // 5. Create sample bookings
    echo "<p>Creating sample bookings...</p>";
    $booking_statuses = ['confirmed', 'attended', 'no-show'];
    
    foreach ($schedule_ids as $schedule_id) {
        // Create 2-4 random bookings per class
        $num_bookings = rand(2, min(4, count($member_ids)));
        $selected_members = array_rand($member_ids, $num_bookings);
        
        if (!is_array($selected_members)) {
            $selected_members = [$selected_members];
        }
        
        foreach ($selected_members as $member_index) {
            $booking_stmt = $pdo->prepare("INSERT INTO class_bookings (user_id, schedule_id, booking_date, status, created_at) VALUES (?, ?, CURDATE(), ?, NOW())");
            $status = $booking_statuses[array_rand($booking_statuses)];
            $booking_stmt->execute([
                $member_ids[$member_index],
                $schedule_id,
                $status
            ]);
            echo "<p>Created booking: Member {$member_ids[$member_index]} -> Schedule $schedule_id ($status)</p>";
        }
    }
    
    $pdo->commit();
    echo "<h3 style='color: green;'>✅ Sample data created successfully!</h3>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>Trainer: John Smith</li>";
    echo "<li>Classes: " . count($class_ids) . " classes created</li>";
    echo "<li>Schedules: " . count($schedule_ids) . " schedules created</li>";
    echo "<li>Members: " . count($member_ids) . " members created</li>";
    echo "<li>Bookings: Multiple bookings with various statuses</li>";
    echo "</ul>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Login as trainer: <strong>john_trainer</strong> / <strong>password123</strong></li>";
    echo "<li>Go to trainer dashboard to see the data</li>";
    echo "<li>Test the class management pages</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 8px 0; }
ul, ol { margin: 10px 0 15px 20px; }
li { margin: 3px 0; }
</style>
