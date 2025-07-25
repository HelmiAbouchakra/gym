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
    // First, get the member's assigned trainer
    $trainer_query = "
        SELECT um.trainer_id, t.name as trainer_name, t.specialties
        FROM user_memberships um
        JOIN trainers t ON um.trainer_id = t.id
        WHERE um.user_id = ? AND um.status = 'active'
        ORDER BY um.created_at DESC
        LIMIT 1
    ";
    
    $trainer_stmt = $pdo->prepare($trainer_query);
    $trainer_stmt->execute([$user_id]);
    $assigned_trainer = $trainer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assigned_trainer) {
        $error_message = "You don't have an active membership with an assigned trainer. Please contact the gym to get assigned to a trainer.";
        $all_classes = [];
        $scheduled_classes = [];
    } else {
        // Get all classes for the assigned trainer
        $classes_stmt = $pdo->prepare("
            SELECT c.*
            FROM classes c
            WHERE c.trainer_id = ? AND c.is_active = 1
            ORDER BY c.name ASC
        ");
        $classes_stmt->execute([$assigned_trainer['trainer_id']]);
        $all_classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get scheduled sessions for these classes
        $scheduled_classes = [];
        if (!empty($all_classes)) {
            $class_ids = array_column($all_classes, 'id');
            $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';
            
            $schedules_stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    cs.id as schedule_id,
                    cs.day_of_week,
                    cs.start_time,
                    cs.end_time,
                    cs.room,
                    cs.is_active,
                    (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id AND cb.status = 'confirmed') as current_bookings,
                    (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id AND cb.user_id = ? AND cb.status = 'confirmed') as user_booked
                FROM classes c
                JOIN class_schedules cs ON c.id = cs.class_id
                WHERE c.id IN ($placeholders)
                AND cs.is_active = 1
                ORDER BY 
                    CASE cs.day_of_week
                        WHEN 'Monday' THEN 1
                        WHEN 'Tuesday' THEN 2
                        WHEN 'Wednesday' THEN 3
                        WHEN 'Thursday' THEN 4
                        WHEN 'Friday' THEN 5
                        WHEN 'Saturday' THEN 6
                        WHEN 'Sunday' THEN 7
                    END,
                    cs.start_time ASC
            ");
            $params = array_merge([$user_id], $class_ids);
            $schedules_stmt->execute($params);
            $scheduled_classes = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Initialize classes_by_day array
    $classes_by_day = [];
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['schedule_id'])) {
        $schedule_id = (int)$_POST['schedule_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'book') {
                // Check if user already has a confirmed booking
                $check_confirmed = $pdo->prepare("SELECT id FROM class_bookings WHERE user_id = ? AND schedule_id = ? AND status = 'confirmed'");
                $check_confirmed->execute([$user_id, $schedule_id]);
                
                if (!$check_confirmed->fetch()) {
                    // Check class capacity
                    $capacity_stmt = $pdo->prepare("
                        SELECT 
                            c.capacity,
                            (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = ? AND cb.status = 'confirmed') as current_bookings
                        FROM class_schedules cs
                        JOIN classes c ON cs.class_id = c.id
                        WHERE cs.id = ?
                    ");
                    $capacity_stmt->execute([$schedule_id, $schedule_id]);
                    $capacity_info = $capacity_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($capacity_info && $capacity_info['current_bookings'] < $capacity_info['capacity']) {
                        // Check if user has a cancelled booking that can be reactivated
                        $check_cancelled = $pdo->prepare("SELECT id FROM class_bookings WHERE user_id = ? AND schedule_id = ? AND status = 'cancelled' ORDER BY created_at DESC LIMIT 1");
                        $check_cancelled->execute([$user_id, $schedule_id]);
                        $cancelled_booking = $check_cancelled->fetch(PDO::FETCH_ASSOC);
                        
                        if ($cancelled_booking) {
                            // Reactivate the existing cancelled booking
                            $reactivate_stmt = $pdo->prepare("UPDATE class_bookings SET status = 'confirmed', booking_date = NOW() WHERE id = ?");
                            $reactivate_stmt->execute([$cancelled_booking['id']]);
                            $success_message = "Class booked successfully!";
                        } else {
                            // Create new booking
                            $book_stmt = $pdo->prepare("INSERT INTO class_bookings (user_id, schedule_id, booking_date, status) VALUES (?, ?, NOW(), 'confirmed')");
                            $book_stmt->execute([$user_id, $schedule_id]);
                            $success_message = "Class booked successfully!";
                        }
                    } else {
                        $error_message = "Sorry, this class is full.";
                    }
                } else {
                    $error_message = "You have already booked this class.";
                }
                
            } elseif ($action === 'cancel') {
                // Cancel the booking
                $cancel_stmt = $pdo->prepare("UPDATE class_bookings SET status = 'cancelled' WHERE user_id = ? AND schedule_id = ? AND status = 'confirmed'");
                $result = $cancel_stmt->execute([$user_id, $schedule_id]);
                
                if ($cancel_stmt->rowCount() > 0) {
                    $success_message = "Booking cancelled successfully!";
                } else {
                    $error_message = "Could not cancel booking. You may not have a confirmed booking for this class.";
                }
            }
            
        } catch (Exception $e) {
            $error_message = "Error processing request: " . $e->getMessage();
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
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/footer.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/upcoming-classes.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="classes-container">
        <h1><i class="fas fa-calendar-alt"></i> Upcoming Classes</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($assigned_trainer) && is_array($assigned_trainer)): ?>
            <div class="trainer-info-header">
                <div class="trainer-card">
                    <div class="trainer-details">
                        <h3><i class="fas fa-user-tie"></i> Your Personal Trainer</h3>
                        <h2><?php echo htmlspecialchars($assigned_trainer['trainer_name'] ?? 'Unknown Trainer'); ?></h2>
                        <?php if (!empty($assigned_trainer['specialties'])): ?>
                            <p class="specialties"><i class="fas fa-star"></i> <strong>Specialties:</strong> <?php echo htmlspecialchars($assigned_trainer['specialties']); ?></p>
                        <?php endif; ?>
                        <p class="info-text">Below are the classes and sessions available with your assigned trainer.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($scheduled_classes)): ?>
            <div class="no-classes">
                <i class="fas fa-calendar-times"></i>
                <h3>No Classes Scheduled</h3>
                <?php if (isset($assigned_trainer) && is_array($assigned_trainer)): ?>
                    <p>Your trainer <?php echo htmlspecialchars($assigned_trainer['trainer_name'] ?? 'Unknown Trainer'); ?> doesn't have any classes scheduled yet. Please contact the gym or your trainer to schedule sessions.</p>
                <?php else: ?>
                    <p>There are currently no upcoming classes scheduled. Please check back later or contact the gym for more information.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php 
            // Group classes by day of week
            $classes_by_day = [];
            foreach ($scheduled_classes as $class) {
                $classes_by_day[$class['day_of_week']][] = $class;
            }
            ?>
            <?php foreach ($classes_by_day as $day => $day_classes): ?>
                <div class="day-section">
                    <h2 class="day-header">
                        <i class="fas fa-calendar-day"></i> <?php echo $day; ?>
                        <small>(<?php echo count($day_classes); ?> classes)</small>
                    </h2>
                    
                    <?php foreach ($day_classes as $class): ?>
                        <?php
                        $capacity_percentage = $class['capacity'] > 0 ? ($class['current_bookings'] / $class['capacity']) * 100 : 0;
                        $is_full = $class['current_bookings'] >= $class['capacity'];
                        $is_booked = $class['user_booked'] > 0;
                        ?>
                        
                        <div class="class-card">
                            <div class="class-header">
                                <div>
                                    <div class="class-name"><?php echo htmlspecialchars($class['name']); ?></div>
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
                                    <i class="fas fa-clock"></i>
                                    <span><strong>Duration:</strong> <?php echo $class['duration']; ?> minutes</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span><strong>Capacity:</strong> <?php echo $class['current_bookings']; ?>/<?php echo $class['capacity']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><strong>Location:</strong> <?php echo htmlspecialchars($class['location'] ?? 'Main Gym'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-signal"></i>
                                    <span><strong>Difficulty:</strong> 
                                        <span class="difficulty-badge difficulty-<?php echo strtolower($class['difficulty_level'] ?? 'beginner'); ?>">
                                            <?php echo ucfirst($class['difficulty_level'] ?? 'Beginner'); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="capacity-info">
                                <div class="capacity-bar">
                                    <div class="capacity-fill <?php echo $capacity_percentage > 80 ? ($is_full ? 'full' : 'warning') : ''; ?>" 
                                         style="width: <?php echo $capacity_percentage; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="class-actions">
                                <?php if ($is_booked): ?>
                                    <span class="booked-badge">✓ Booked</span>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="schedule_id" value="<?php echo $class['schedule_id']; ?>">
                                        <button type="submit" class="cancel-btn">Cancel Booking</button>
                                    </form>
                                <?php else: ?>
                                    <div class="booking-info">
                                        <?php if ($is_full): ?>
                                            <span class="text-danger"><i class="fas fa-exclamation-circle"></i> Class Full</span>
                                        <?php elseif ($capacity_percentage > 80): ?>
                                            <span class="text-warning"><i class="fas fa-clock"></i> Almost Full</span>
                                        <?php else: ?>
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Available</span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="book">
                                        <input type="hidden" name="schedule_id" value="<?php echo $class['schedule_id']; ?>">
                                        <button type="submit" class="book-btn" <?php echo $is_full ? 'disabled' : ''; ?>>
                                            <i class="fas fa-plus"></i> <?php echo $is_full ? 'Class Full' : 'Book Now'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
    function bookClass(scheduleId, className, date, time) {
        if (!confirm(`Are you sure you want to book "${className}" on ${date} at ${time}?`)) {
            return;
        }
        
        const bookBtn = document.querySelector(`button[onclick*="${scheduleId}"]`);
        const originalText = bookBtn.innerHTML;
        bookBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
        bookBtn.disabled = true;
        
        fetch('../actions/book_class.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `schedule_id=${scheduleId}`
        })
        .then(response => {
            // Debug: log the raw response
            console.log('Raw response status:', response.status);
            return response.text();
        })
        .then(text => {
            // Debug: log the raw text
            console.log('Raw response text:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            if (data.success) {
                bookBtn.innerHTML = '<i class="fas fa-check"></i> Booked!';
                bookBtn.className = 'book-btn booked';
                bookBtn.disabled = true;
                
                // Show success message
                showMessage(data.message, 'success');
                
                // Update capacity display if exists
                const classCard = bookBtn.closest('.class-card');
                if (classCard) {
                    const capacityElement = classCard.querySelector('.capacity-info');
                    if (capacityElement) {
                        // Refresh the page to show updated capacity
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                }
            } else {
                bookBtn.innerHTML = originalText;
                bookBtn.disabled = false;
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Booking error:', error);
            bookBtn.innerHTML = originalText;
            bookBtn.disabled = false;
            showMessage('An error occurred while booking the class. Please try again.', 'error');
        });
    }
    
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.booking-message');
        existingMessages.forEach(msg => msg.remove());
        
        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `booking-message alert alert-${type === 'success' ? 'success' : 'danger'}`;
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(messageDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            messageDiv.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => messageDiv.remove(), 300);
        }, 5000);
    }
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .book-btn.booked {
            background: #28a745 !important;
            cursor: not-allowed;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
