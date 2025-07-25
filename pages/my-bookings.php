<?php
// My Bookings Page
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

// Initialize variables
$success_message = '';
$error_message = '';

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $booking_id = (int)$_POST['booking_id'];
    
    try {
        // Cancel the booking
        $cancel_stmt = $pdo->prepare("UPDATE class_bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'confirmed'");
        $result = $cancel_stmt->execute([$booking_id, $user_id]);
        
        if ($cancel_stmt->rowCount() > 0) {
            $success_message = "Booking cancelled successfully!";
        } else {
            $error_message = "Could not cancel booking. It may have already been cancelled.";
        }
        
    } catch (Exception $e) {
        $error_message = "Error cancelling booking: " . $e->getMessage();
    }
    
    // Refresh the page to show updated data
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

try {
    // Get user's bookings with class and schedule details
    $bookings_stmt = $pdo->prepare("
        SELECT 
            cb.id as booking_id,
            cb.booking_date,
            cb.status,
            cb.created_at,
            c.name as class_name,
            c.description as class_description,
            c.duration,
            c.capacity,
            cs.id as schedule_id,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            cs.room,
            t.name as trainer_name,
            t.specialties as trainer_specialties,
            (SELECT COUNT(*) FROM class_bookings cb2 WHERE cb2.schedule_id = cs.id AND cb2.status = 'confirmed') as current_bookings
        FROM class_bookings cb
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        JOIN trainers t ON c.trainer_id = t.id
        WHERE cb.user_id = ?
        ORDER BY 
            CASE cb.status
                WHEN 'confirmed' THEN 1
                WHEN 'cancelled' THEN 2
                ELSE 3
            END,
            CASE cs.day_of_week
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
                WHEN 'Sunday' THEN 7
            END,
            cs.start_time ASC,
            cb.created_at DESC
    ");
    $bookings_stmt->execute([$user_id]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate bookings by status
    $confirmed_bookings = [];
    $cancelled_bookings = [];
    
    foreach ($bookings as $booking) {
        if ($booking['status'] === 'confirmed') {
            $confirmed_bookings[] = $booking;
        } else {
            $cancelled_bookings[] = $booking;
        }
    }
    
} catch (Exception $e) {
    $error_message = "Error loading bookings: " . $e->getMessage();
    $confirmed_bookings = [];
    $cancelled_bookings = [];
}

// Helper function to get day color
function getDayColor($day) {
    $colors = [
        'Monday' => '#ff6b35',
        'Tuesday' => '#4ecdc4', 
        'Wednesday' => '#45b7d1',
        'Thursday' => '#96ceb4',
        'Friday' => '#feca57',
        'Saturday' => '#ff9ff3',
        'Sunday' => '#54a0ff'
    ];
    return $colors[$day] ?? '#6c757d';
}

// Helper function to format time
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'confirmed':
            return 'status-confirmed';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - FitLife Gym</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/footer.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/my-bookings.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="bookings-container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> My Bookings</h1>
            <p>Manage your class bookings and view your fitness schedule</p>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Booking Statistics -->
        <div class="booking-stats">
            <div class="stat-card">
                <div class="stat-icon confirmed">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($confirmed_bookings); ?></h3>
                    <p>Active Bookings</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon cancelled">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($cancelled_bookings); ?></h3>
                    <p>Cancelled Bookings</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($bookings); ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
        </div>
        
        <!-- Booking Tabs -->
        <div class="booking-tabs">
            <button class="tab-btn active" onclick="showTab('confirmed')">
                <i class="fas fa-calendar-check"></i> Active Bookings (<?php echo count($confirmed_bookings); ?>)
            </button>
            <button class="tab-btn" onclick="showTab('cancelled')">
                <i class="fas fa-calendar-times"></i> Cancelled Bookings (<?php echo count($cancelled_bookings); ?>)
            </button>
        </div>
        
        <!-- Active Bookings Tab -->
        <div id="confirmed-tab" class="tab-content active">
            <?php if (!empty($confirmed_bookings)): ?>
                <div class="bookings-grid">
                    <?php foreach ($confirmed_bookings as $booking): ?>
                        <div class="booking-card confirmed">
                            <div class="booking-header">
                                <div class="class-info">
                                    <h3><?php echo htmlspecialchars($booking['class_name']); ?></h3>
                                    <div class="day-badge" style="background-color: <?php echo getDayColor($booking['day_of_week']); ?>">
                                        <?php echo $booking['day_of_week']; ?>
                                    </div>
                                </div>
                                <div class="status-badge <?php echo getStatusBadgeClass($booking['status']); ?>">
                                    <i class="fas fa-check-circle"></i> Confirmed
                                </div>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo formatTime($booking['start_time']) . ' - ' . formatTime($booking['end_time']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span><?php echo $booking['duration']; ?> minutes</span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-user-tie"></i>
                                        <span><?php echo htmlspecialchars($booking['trainer_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($booking['room'] ?? 'Main Gym'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $booking['current_bookings']; ?>/<?php echo $booking['capacity']; ?> spots filled</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-plus"></i>
                                        <span>Booked on <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($booking['class_description'])): ?>
                                    <div class="class-description">
                                        <p><?php echo htmlspecialchars($booking['class_description']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="booking-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                    <button type="submit" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel this booking?')">
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                </form>
                                <a href="upcoming-classes.php" class="btn-view-classes">
                                    <i class="fas fa-calendar-alt"></i> View All Classes
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>No Active Bookings</h3>
                    <p>You don't have any confirmed class bookings at the moment.</p>
                    <a href="upcoming-classes.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Book a Class
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cancelled Bookings Tab -->
        <div id="cancelled-tab" class="tab-content">
            <?php if (!empty($cancelled_bookings)): ?>
                <div class="bookings-grid">
                    <?php foreach ($cancelled_bookings as $booking): ?>
                        <div class="booking-card cancelled">
                            <div class="booking-header">
                                <div class="class-info">
                                    <h3><?php echo htmlspecialchars($booking['class_name']); ?></h3>
                                    <div class="day-badge" style="background-color: <?php echo getDayColor($booking['day_of_week']); ?>">
                                        <?php echo $booking['day_of_week']; ?>
                                    </div>
                                </div>
                                <div class="status-badge <?php echo getStatusBadgeClass($booking['status']); ?>">
                                    <i class="fas fa-times-circle"></i> Cancelled
                                </div>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo formatTime($booking['start_time']) . ' - ' . formatTime($booking['end_time']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-user-tie"></i>
                                        <span><?php echo htmlspecialchars($booking['trainer_name']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-plus"></i>
                                        <span>Originally booked on <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3>No Cancelled Bookings</h3>
                    <p>You haven't cancelled any bookings yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // Add fade-in animation to booking cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.booking-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>
