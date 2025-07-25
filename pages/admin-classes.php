<?php
// Admin Classes Management
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
$pdo = getConnection();
$base_url = '../';

// Handle class actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $class_id = $_POST['class_id'];
                    $current_status = $_POST['current_status'];
                    $new_status = $current_status ? 0 : 1;
                    
                    $update_stmt = $pdo->prepare("UPDATE classes SET is_active = ? WHERE id = ?");
                    $update_stmt->execute([$new_status, $class_id]);
                    
                    $success_message = "Class status updated successfully.";
                    break;
                    
                case 'delete_class':
                    $class_id = $_POST['class_id'];
                    
                    $pdo->beginTransaction();
                    
                    // Delete related records first
                    $pdo->prepare("DELETE FROM class_bookings WHERE schedule_id IN (SELECT id FROM class_schedules WHERE class_id = ?)")->execute([$class_id]);
                    $pdo->prepare("DELETE FROM class_schedules WHERE class_id = ?")->execute([$class_id]);
                    $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$class_id]);
                    
                    $pdo->commit();
                    $success_message = "Class deleted successfully.";
                    break;
                    
                case 'create_class':
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    $trainer_id = $_POST['trainer_id'] ?: null;
                    $capacity = (int)$_POST['capacity'];
                    $duration = (int)$_POST['duration'];
                    $difficulty_level = $_POST['difficulty_level'];
                    
                    if (empty($name) || $capacity <= 0 || $duration <= 0) {
                        $error_message = "Please fill in all required fields with valid values.";
                        break;
                    }
                    
                    $create_stmt = $pdo->prepare("INSERT INTO classes (name, description, trainer_id, capacity, duration, difficulty_level, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $create_stmt->execute([$name, $description, $trainer_id, $capacity, $duration, $difficulty_level]);
                    
                    $success_message = "Class created successfully.";
                    break;
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all classes with trainer info and statistics
try {
    $classes_query = "
        SELECT 
            c.*,
            t.name as trainer_name,
            COUNT(DISTINCT cs.id) as schedule_count,
            COUNT(cb.id) as total_bookings,
            COUNT(CASE WHEN cb.status = 'attended' THEN 1 END) as attended_count,
            COUNT(DISTINCT cb.user_id) as unique_clients
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        LEFT JOIN class_schedules cs ON c.id = cs.class_id
        LEFT JOIN class_bookings cb ON cs.id = cb.schedule_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ";
    
    $classes_stmt = $pdo->query($classes_query);
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all trainers for dropdown
    $trainers_stmt = $pdo->query("SELECT id, name FROM trainers WHERE is_active = 1 ORDER BY name");
    $trainers = $trainers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get class statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_classes,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_classes,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_classes,
            COUNT(CASE WHEN difficulty_level = 'beginner' THEN 1 END) as beginner_classes,
            COUNT(CASE WHEN difficulty_level = 'intermediate' THEN 1 END) as intermediate_classes,
            COUNT(CASE WHEN difficulty_level = 'advanced' THEN 1 END) as advanced_classes
        FROM classes
    ";
    
    $stats_stmt = $pdo->query($stats_query);
    $class_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading classes: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - FitLife Gym Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-dumbbell"></i> Class Management</h1>
                <p>Manage fitness classes and schedules</p>
            </div>
            <div>
                <a href="admin-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button onclick="openCreateModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Class
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h2 class="stat-number"><?php echo $class_stats['total_classes'] ?? 0; ?></h2>
                <p>Total Classes</p>
            </div>
            <div class="stat-card active">
                <h2 class="stat-number"><?php echo $class_stats['active_classes'] ?? 0; ?></h2>
                <p>Active Classes</p>
            </div>
            <div class="stat-card inactive">
                <h2 class="stat-number"><?php echo $class_stats['inactive_classes'] ?? 0; ?></h2>
                <p>Inactive Classes</p>
            </div>
            <div class="stat-card beginner">
                <h2 class="stat-number"><?php echo $class_stats['beginner_classes'] ?? 0; ?></h2>
                <p>Beginner</p>
            </div>
            <div class="stat-card intermediate">
                <h2 class="stat-number"><?php echo $class_stats['intermediate_classes'] ?? 0; ?></h2>
                <p>Intermediate</p>
            </div>
            <div class="stat-card advanced">
                <h2 class="stat-number"><?php echo $class_stats['advanced_classes'] ?? 0; ?></h2>
                <p>Advanced</p>
            </div>
        </div>
        
        <!-- Classes List -->
        <div class="classes-section">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> All Classes</h3>
            </div>
            
            <?php if (empty($classes)): ?>
                <p>No classes found. <a href="#" onclick="openCreateModal()">Create your first class</a>.</p>
            <?php else: ?>
                <table class="admin-table users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Trainer</th>
                            <th>Capacity</th>
                            <th>Difficulty</th>
                            <th>Status</th>
                            <th>Stats</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($class['name']); ?></strong>
                                    <?php if ($class['description']): ?>
                                        <br><small><?php echo htmlspecialchars(substr($class['description'], 0, 50)) . '...'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($class['trainer_name'] ?? 'Unassigned'); ?></td>
                                <td><?php echo $class['capacity']; ?></td>
                                <td>
                                    <span class="difficulty-badge difficulty-<?php echo $class['difficulty_level']; ?>">
                                        <?php echo ucfirst($class['difficulty_level']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $class['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $class['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $class['schedule_count']; ?> schedules<br>
                                        <?php echo $class['total_bookings']; ?> bookings
                                    </small>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $class['is_active']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i class="fas fa-<?php echo $class['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this class?')">
                                        <input type="hidden" name="action" value="delete_class">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Class Modal -->
    <div id="createModal" class="modal form-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Create New Class</h3>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="createClassForm" method="POST">
                <input type="hidden" name="action" value="create_class">
                
                <div class="form-group">
                    <label>Class Name:</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="3" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Trainer:</label>
                    <select name="trainer_id" class="form-control">
                        <option value="">Select Trainer (Optional)</option>
                        <?php foreach ($trainers as $trainer): ?>
                            <option value="<?php echo $trainer['id']; ?>"><?php echo htmlspecialchars($trainer['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Difficulty Level:</label>
                    <select name="difficulty_level" required class="form-control">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Capacity:</label>
                    <input type="number" name="capacity" min="1" max="100" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Duration (minutes):</label>
                    <input type="number" name="duration" min="15" max="180" required class="form-control">
                </div>
                
                </form>
            </div>
            <div class="modal-footer">
                <div class="button-container">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">CANCEL</button>
                    <button type="submit" form="createClassForm" class="btn btn-primary">CREATE CLASS</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('createModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
