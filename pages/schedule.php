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

// Get trainer information
$user_id = $_SESSION['user_id'];

// Fetch trainer ID from the database
try {
    $stmt = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $trainer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trainer) {
        // Redirect if trainer record doesn't exist
        header("Location: profile.php");
        exit();
    }
    
    $trainer_id = $trainer['id'];
    
    // Get all class schedules for this trainer
    $schedule_query = "
        SELECT 
            cs.id AS schedule_id,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            cs.room,
            c.id AS class_id,
            c.name AS class_name,
            c.description,
            c.capacity,
            c.difficulty_level,
            (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id AND cb.status = 'confirmed') as booked_count
        FROM class_schedules cs
        JOIN classes c ON cs.class_id = c.id
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
    ";
    
    $schedule_stmt = $pdo->prepare($schedule_query);
    $schedule_stmt->execute([$trainer_id]);
    $all_schedules = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize schedules by day of week
    $schedules_by_day = [
        'Monday' => [],
        'Tuesday' => [],
        'Wednesday' => [],
        'Thursday' => [],
        'Friday' => [],
        'Saturday' => [],
        'Sunday' => []
    ];
    
    foreach ($all_schedules as $schedule) {
        $schedules_by_day[$schedule['day_of_week']][] = $schedule;
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - FitLife Gym</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/schedule.css">

</head>
<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="schedule-container">
        <h1 class="page-title">My Schedule</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <?php foreach ($schedules_by_day as $day => $schedules): ?>
                <div class="day-schedule">
                    <h2 class="day-title"><?php echo $day; ?></h2>
                    
                    <?php if (empty($schedules)): ?>
                        <p class="schedule-empty">No classes scheduled for this day.</p>
                    <?php else: ?>
                        <?php foreach ($schedules as $schedule): ?>
                            <div class="class-card">
                                <div class="class-time">
                                    <div class="time-display">
                                        <?php echo date('g:i A', strtotime($schedule['start_time'])); ?>
                                    </div>
                                    <div>to</div>
                                    <div class="time-display">
                                        <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                    </div>
                                </div>
                                
                                <div class="class-details">
                                    <h3 class="class-name"><?php echo htmlspecialchars($schedule['class_name']); ?></h3>
                                    
                                    <div class="class-meta">
                                        <div class="class-meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($schedule['room']); ?>
                                        </div>
                                        
                                        <div class="class-meta-item">
                                            <i class="fas fa-signal"></i>
                                            <?php echo ucfirst(htmlspecialchars($schedule['difficulty_level'])); ?>
                                        </div>
                                        
                                        <div class="class-meta-item">
                                            <i class="fas fa-users"></i>
                                            <?php echo $schedule['booked_count']; ?> / <?php echo $schedule['capacity']; ?> booked
                                        </div>
                                    </div>
                                    
                                    <div class="capacity-bar">
                                        <?php 
                                            $fill_percentage = ($schedule['booked_count'] / $schedule['capacity']) * 100;
                                            echo '<div class="capacity-fill" style="width: ' . $fill_percentage . '%"></div>';
                                        ?>
                                    </div>
                                    
                                    <div class="class-actions">
                                        <button class="view-attendees" data-schedule="<?php echo $schedule['schedule_id']; ?>">
                                            <i class="fas fa-user-friends"></i> View Attendees
                                        </button>
                                        
                                        <div id="attendees-<?php echo $schedule['schedule_id']; ?>" class="attendees-list">
                                            <h4>Class Attendees</h4>
                                            <div class="attendee-container">
                                                <!-- Attendees will be loaded here via AJAX -->
                                                <div class="loading">Loading attendees...</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle attendees list
            const viewAttendeesBtns = document.querySelectorAll('.view-attendees');
            
            viewAttendeesBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const scheduleId = this.getAttribute('data-schedule');
                    const attendeesList = document.getElementById('attendees-' + scheduleId);
                    
                    if (attendeesList.style.display === 'block') {
                        attendeesList.style.display = 'none';
                        this.innerHTML = '<i class="fas fa-user-friends"></i> View Attendees';
                    } else {
                        // Close any other open lists
                        document.querySelectorAll('.attendees-list').forEach(list => {
                            list.style.display = 'none';
                        });
                        
                        document.querySelectorAll('.view-attendees').forEach(button => {
                            button.innerHTML = '<i class="fas fa-user-friends"></i> View Attendees';
                        });
                        
                        // Open this list
                        attendeesList.style.display = 'block';
                        this.innerHTML = '<i class="fas fa-times"></i> Hide Attendees';
                        
                        // Load attendees via AJAX
                        const attendeeContainer = attendeesList.querySelector('.attendee-container');
                        attendeeContainer.innerHTML = '<div class="loading">Loading attendees...</div>';
                        
                        // Make AJAX request to get attendees
                        fetch('../actions/get_attendees.php?schedule_id=' + scheduleId)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    if (data.attendees.length > 0) {
                                        let html = '';
                                        
                                        data.attendees.forEach(attendee => {
                                            html += `
                                                <div class="attendee">
                                                    <strong>${attendee.username}</strong> - ${attendee.email}
                                                    <span class="status status-${attendee.status}">${attendee.status}</span>
                                                </div>
                                            `;
                                        });
                                        
                                        attendeeContainer.innerHTML = html;
                                    } else {
                                        attendeeContainer.innerHTML = '<p>No attendees for this class yet.</p>';
                                    }
                                } else {
                                    attendeeContainer.innerHTML = '<p>Error loading attendees: ' + data.message + '</p>';
                                }
                            })
                            .catch(error => {
                                attendeeContainer.innerHTML = '<p>Error loading attendees. Please try again.</p>';
                                console.error('Error:', error);
                            });
                    }
                });
            });
        });
    </script>
</body>
</html>