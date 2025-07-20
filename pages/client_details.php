<?php
// Start session
session_start();

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
    // Redirect to login page if not logged in or not a trainer
    header("Location: login.php");
    exit();
}

// Check if client ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: clients.php");
    exit();
}

$client_id = intval($_GET['id']);

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
    
    // Verify that this client belongs to this trainer
    $verify_client_query = "
        SELECT COUNT(*) as count
        FROM class_bookings cb
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE c.trainer_id = ? AND cb.user_id = ?
    ";
    
    $verify_stmt = $pdo->prepare($verify_client_query);
    $verify_stmt->execute([$trainer_id, $client_id]);
    $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || $result['count'] == 0) {
        // This client does not belong to this trainer
        header("Location: clients.php");
        exit();
    }
    
    // Get client details
    $client_query = "
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.profile_image,
            MAX(cb.created_at) as last_booking,
            COUNT(DISTINCT cb.id) as total_bookings
        FROM users u
        JOIN class_bookings cb ON u.id = cb.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ";
    
    $client_stmt = $pdo->prepare($client_query);
    $client_stmt->execute([$client_id]);
    $client = $client_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get client's class history
    $history_query = "
        SELECT 
            c.name as class_name,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            cs.room,
            cb.booking_date,
            cb.status,
            cb.created_at
        FROM class_bookings cb
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE cb.user_id = ? AND c.trainer_id = ?
        ORDER BY cb.booking_date DESC, cs.start_time DESC
    ";
    
    $history_stmt = $pdo->prepare($history_query);
    $history_stmt->execute([$client_id, $trainer_id]);
    $booking_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get client's class preferences (classes they attend most)
    $preferences_query = "
        SELECT 
            c.name as class_name,
            COUNT(*) as attendance_count
        FROM class_bookings cb
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE cb.user_id = ? AND c.trainer_id = ?
        GROUP BY c.id
        ORDER BY attendance_count DESC
        LIMIT 3
    ";
    
    $preferences_stmt = $pdo->prepare($preferences_query);
    $preferences_stmt->execute([$client_id, $trainer_id]);
    $preferences = $preferences_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create client_notes table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS client_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            trainer_id INT NOT NULL,
            client_id INT NOT NULL,
            note_content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Get client notes
    $notes_query = "
        SELECT * FROM client_notes
        WHERE trainer_id = ? AND client_id = ?
        ORDER BY created_at DESC
    ";
    
    $notes_stmt = $pdo->prepare($notes_query);
    $notes_stmt->execute([$trainer_id, $client_id]);
    $notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle note submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $note_content = trim($_POST['note_content']);
    
    if (!empty($note_content)) {
        try {
            // Insert the note
            $insert_query = "
                INSERT INTO client_notes (trainer_id, client_id, note_content)
                VALUES (?, ?, ?)
            ";
            $insert_stmt = $pdo->prepare($insert_query);
            $insert_stmt->execute([$trainer_id, $client_id, $note_content]);
            
            // Refresh the page to show the new note
            header("Location: client_details.php?id=" . $client_id . "&note_added=1");
            exit();
        } catch (PDOException $e) {
            $note_error = "Could not add note: " . $e->getMessage();
        }
    } else {
        $note_error = "Note content cannot be empty";
    }
}

// Handle note deletion
if (isset($_GET['delete_note']) && !empty($_GET['delete_note'])) {
    $note_id = intval($_GET['delete_note']);
    
    try {
        // Verify the note belongs to this trainer before deleting
        $verify_note_query = "
            SELECT COUNT(*) as count
            FROM client_notes
            WHERE id = ? AND trainer_id = ?
        ";
        $verify_note_stmt = $pdo->prepare($verify_note_query);
        $verify_note_stmt->execute([$note_id, $trainer_id]);
        $note_result = $verify_note_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($note_result && $note_result['count'] > 0) {
            // Delete the note
            $delete_query = "DELETE FROM client_notes WHERE id = ?";
            $delete_stmt = $pdo->prepare($delete_query);
            $delete_stmt->execute([$note_id]);
            
            // Redirect back to the client details page
            header("Location: client_details.php?id=" . $client_id . "&note_deleted=1");
            exit();
        }
    } catch (PDOException $e) {
        $note_error = "Could not delete note: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Details - FitLife Gym</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <style>
        .client-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .client-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .client-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .client-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4CAF50;
        }
        
        .client-info h1 {
            margin-bottom: 5px;
            font-size: 1.8rem;
        }
        
        .client-email {
            color: #666;
            font-size: 0.9rem;
        }
        
        .client-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .client-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .client-sections {
                grid-template-columns: 1fr;
            }
        }
        
        .section-title {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .history-item {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .history-date {
            font-weight: bold;
        }
        
        .history-status {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-attended {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .status-no-show {
            background-color: #fff3e0;
            color: #e64a19;
        }
        
        .history-details {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .preference-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .attendance-count {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .note-form {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .note-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 10px;
            font-family: inherit;
        }
        
        .note-item {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: relative;
        }
        
        .note-date {
            font-size: 0.8rem;
            color: #666;
            margin-top: 10px;
            text-align: right;
        }
        
        .note-delete {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #d32f2f;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="client-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="client-header">
                <div class="client-profile">
                    <img src="<?php echo !empty($client['profile_image']) ? $base_url . $client['profile_image'] : $base_url . 'assets/images/default-avatar.png'; ?>" alt="Client" class="client-avatar">
                    <div class="client-info">
                        <h1><?php echo htmlspecialchars($client['username']); ?></h1>
                        <div class="client-email"><?php echo htmlspecialchars($client['email']); ?></div>
                    </div>
                </div>
                <a href="clients.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Clients</a>
            </div>
            
            <div class="client-stats">
                <div class="stat-card">
                    <div><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-value"><?php echo $client['total_bookings']; ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
                <div class="stat-card">
                    <div><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-value"><?php echo date('M d', strtotime($client['last_booking'])); ?></div>
                    <div class="stat-label">Last Booking</div>
                </div>
                <div class="stat-card">
                    <div><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?php echo count($booking_history); ?></div>
                    <div class="stat-label">Session History</div>
                </div>
            </div>
            
            <div class="client-sections">
                <div>
                    <h2 class="section-title">Session History</h2>
                    
                    <?php if (empty($booking_history)): ?>
                        <p>No session history available for this client.</p>
                    <?php else: ?>
                        <?php foreach ($booking_history as $booking): ?>
                            <div class="history-item">
                                <div class="history-header">
                                    <div class="history-date"><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></div>
                                    <div class="history-status status-<?php echo strtolower($booking['status']); ?>"><?php echo ucfirst($booking['status']); ?></div>
                                </div>
                                <div class="history-class"><?php echo htmlspecialchars($booking['class_name']); ?></div>
                                <div class="history-details">
                                    <div><i class="fas fa-calendar-day"></i> <?php echo $booking['day_of_week']; ?></div>
                                    <div><i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])); ?></div>
                                    <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($booking['room']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h2 class="section-title">Class Preferences</h2>
                    
                    <?php if (empty($preferences)): ?>
                        <p>No class preferences data available yet.</p>
                    <?php else: ?>
                        <?php foreach ($preferences as $preference): ?>
                            <div class="preference-item">
                                <div><?php echo htmlspecialchars($preference['class_name']); ?></div>
                                <div class="attendance-count"><?php echo $preference['attendance_count']; ?> sessions</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <h2 class="section-title" style="margin-top: 30px;">Client Notes</h2>
                    
                    <?php if (isset($note_error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $note_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['note_added'])): ?>
                        <div class="alert alert-success">
                            Note added successfully!
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['note_deleted'])): ?>
                        <div class="alert alert-success">
                            Note deleted successfully!
                        </div>
                    <?php endif; ?>
                    
                    <form class="note-form" method="post" action="client_details.php?id=<?php echo $client_id; ?>">
                        <input type="hidden" name="action" value="add_note">
                        <textarea name="note_content" class="note-textarea" placeholder="Add a note about this client..."></textarea>
                        <button type="submit" class="btn">Add Note</button>
                    </form>
                    
                    <?php if (empty($notes)): ?>
                        <p>No notes added for this client yet.</p>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="note-item">
                                <a href="client_details.php?id=<?php echo $client_id; ?>&delete_note=<?php echo $note['id']; ?>" class="note-delete" onclick="return confirm('Are you sure you want to delete this note?');">
                                    <i class="fas fa-times"></i>
                                </a>
                                <div><?php echo nl2br(htmlspecialchars($note['note_content'])); ?></div>
                                <div class="note-date"><?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
