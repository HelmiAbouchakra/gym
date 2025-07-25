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
    
    // Get trainer's personal availability schedule
    $personal_schedule_query = "
        SELECT * FROM trainer_schedules 
        WHERE trainer_id = ? 
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ";
    $personal_schedule_stmt = $pdo->prepare($personal_schedule_query);
    $personal_schedule_stmt->execute([$trainer_id]);
    $trainer_personal_schedule = $personal_schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/schedule.css">
    
    <style>
        .personal-schedule-section {
            margin-bottom: 40px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        .section-title {
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title::before {
            content: "";
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .no-schedule {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-size: 16px;
        }
        
        .no-schedule i {
            font-size: 24px;
            margin-right: 10px;
            color: #17a2b8;
        }
        
        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .availability-day {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .availability-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .availability-day.available {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e9 100%);
        }
        
        .availability-day.unavailable {
            border-color: #dc3545;
            background: linear-gradient(135deg, #fff8f8 0%, #ffebee 100%);
        }
        
        .day-name {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        
        .status-indicator {
            font-weight: 500;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .status-indicator.available {
            color: #28a745;
        }
        
        .status-indicator.unavailable {
            color: #dc3545;
        }
        
        .status-indicator i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .time-range {
            font-size: 14px;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            background: rgba(255,255,255,0.7);
            color: #495057;
        }
        
        .time-range.blocked {
            color: #6c757d;
            background: rgba(108,117,125,0.1);
        }
        
        .class-schedules-section {
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .availability-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .availability-day {
                padding: 15px;
            }
            
            .section-title {
                font-size: 20px;
            }
        }
    </style>

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
            <!-- Trainer Personal Availability Schedule -->
            <div class="personal-schedule-section">
                <h2 class="section-title">My Availability Schedule</h2>
                
                <?php if (empty($trainer_personal_schedule)): ?>
                    <div class="no-schedule">
                        <p><i class="fas fa-info-circle"></i> No availability schedule has been set by the admin yet.</p>
                    </div>
                <?php else: ?>
                    <div class="availability-grid">
                        <?php foreach ($trainer_personal_schedule as $schedule): ?>
                            <div class="availability-day <?php echo $schedule['is_available'] ? 'available' : 'unavailable'; ?>">
                                <h3 class="day-name"><?php echo htmlspecialchars($schedule['day_of_week']); ?></h3>
                                <div class="availability-status">
                                    <?php if ($schedule['is_available']): ?>
                                        <div class="status-indicator available">
                                            <i class="fas fa-check-circle"></i> Available
                                        </div>
                                        <div class="time-range">
                                            <?php 
                                                echo date('g:i A', strtotime($schedule['start_time'])); 
                                                echo ' - '; 
                                                echo date('g:i A', strtotime($schedule['end_time']));
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="status-indicator unavailable">
                                            <i class="fas fa-times-circle"></i> Not Available
                                        </div>
                                        <div class="time-range blocked">
                                            <?php 
                                                echo date('g:i A', strtotime($schedule['start_time'])); 
                                                echo ' - '; 
                                                echo date('g:i A', strtotime($schedule['end_time']));
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Class Schedules Section -->
            <div class="class-schedules-section">
                <h2 class="section-title">My Class Schedules</h2>
            <?php foreach ($schedules_by_day as $day => $schedules): ?>
                <?php if (!empty($schedules)): ?>
                <div class="day-schedule">
                    <h2 class="day-title"><?php echo $day; ?></h2>
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
                </div>
                <?php endif; ?>
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