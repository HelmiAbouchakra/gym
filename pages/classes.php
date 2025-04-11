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
    
    // Get all classes for this trainer
    $classes_query = "
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
        WHERE c.trainer_id = ?
        ORDER BY c.name
    ";
    
    $classes_stmt = $pdo->prepare($classes_query);
    $classes_stmt->execute([$trainer_id]);
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - FitLife Gym</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/classes.css">

</head>
<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="classes-container">
        <div class="classes-header">
            <h1 class="page-title">My Classes</h1>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php elseif (empty($classes)): ?>
            <div class="no-classes">
                <h3>You don't have any classes yet</h3>
                <p>Contact the administrator to have classes assigned to you.</p>
            </div>
        <?php else: ?>
            <div class="classes-grid">
                <?php foreach ($classes as $class): ?>
                    <div class="class-card">
                        <div class="class-header">
                            <h3 class="class-title"><?php echo htmlspecialchars($class['name']); ?></h3>
                        </div>
                        
                        <div class="class-body">
                            <div class="class-description">
                                <?php echo htmlspecialchars($class['description']); ?>
                            </div>
                            
                            <div class="class-stats">
                                <div class="class-stat">
                                    <div class="stat-value"><?php echo $class['schedule_count']; ?></div>
                                    <div class="stat-label">Sessions</div>
                                </div>
                                
                                <div class="class-stat">
                                    <div class="stat-value"><?php echo $class['total_students']; ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                            </div>
                            
                            <div class="class-details">
                                <div class="detail-item">
                                    <div class="detail-label">Duration:</div>
                                    <div class="detail-value"><?php echo $class['duration']; ?> minutes</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Capacity:</div>
                                    <div class="detail-value"><?php echo $class['capacity']; ?> people</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Difficulty:</div>
                                    <div class="detail-value"><?php echo ucfirst($class['difficulty_level']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Status:</div>
                                    <div class="detail-value">
                                        <?php echo $class['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="class-actions">
                                <a href="class_details.php?id=<?php echo $class['id']; ?>" class="class-btn btn-view">
                                    View Details
                                </a>
                                <a href="edit_class.php?id=<?php echo $class['id']; ?>" class="class-btn btn-edit">
                                    Edit Class
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>