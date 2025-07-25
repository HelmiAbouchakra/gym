<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/db_config.php';

// Base URL for correct path resolution
$base_url = '../';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'switch_client':
                $client_id = $_POST['client_id'];
                $from_trainer_id = $_POST['from_trainer_id'];
                $to_trainer_id = $_POST['to_trainer_id'];
                
                // Update user membership to new trainer
                $stmt = $pdo->prepare("UPDATE user_memberships SET trainer_id = ? WHERE user_id = ? AND trainer_id = ? AND status = 'active'");
                $result = $stmt->execute([$to_trainer_id, $client_id, $from_trainer_id]);
                
                if ($result) {
                    // Log the activity
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'client_switch', ?)");
                    $stmt->execute([$user_id, "Client ID $client_id switched from trainer $from_trainer_id to trainer $to_trainer_id"]);
                    
                    echo json_encode(['success' => true, 'message' => 'Client switched successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to switch client']);
                }
                break;
                
            case 'update_trainer_schedule':
                $trainer_id = $_POST['trainer_id'];
                $schedule_data = json_decode($_POST['schedule_data'], true);
                
                // Delete existing schedule for this trainer
                $stmt = $pdo->prepare("DELETE FROM trainer_schedules WHERE trainer_id = ?");
                $stmt->execute([$trainer_id]);
                
                // Insert new schedule
                $stmt = $pdo->prepare("INSERT INTO trainer_schedules (trainer_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($schedule_data as $schedule) {
                    $stmt->execute([
                        $trainer_id,
                        $schedule['day'],
                        $schedule['start_time'],
                        $schedule['end_time'],
                        $schedule['is_available']
                    ]);
                }
                
                // Log the activity
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'schedule_update', ?)");
                $stmt->execute([$user_id, "Updated schedule for trainer ID $trainer_id"]);
                
                echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
                break;
                
            case 'toggle_trainer_status':
                $trainer_id = $_POST['trainer_id'];
                $is_active = $_POST['is_active'] === 'true' ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE trainers SET is_active = ? WHERE id = ?");
                $result = $stmt->execute([$is_active, $trainer_id]);
                
                if ($result) {
                    // Log the activity
                    $status = $is_active ? 'activated' : 'deactivated';
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'trainer_status', ?)");
                    $stmt->execute([$user_id, "Trainer ID $trainer_id $status"]);
                    
                    echo json_encode(['success' => true, 'message' => 'Trainer status updated']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                break;
                
            case 'get_trainer_schedule':
                $trainer_id = $_POST['trainer_id'];
                
                $stmt = $pdo->prepare("SELECT * FROM trainer_schedules WHERE trainer_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
                $stmt->execute([$trainer_id]);
                $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'schedule' => $schedule]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch all trainers with their information
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.email,
               COUNT(DISTINCT um.user_id) as client_count,
               COUNT(DISTINCT c.id) as class_count
        FROM trainers t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN user_memberships um ON t.id = um.trainer_id AND um.status = 'active'
        LEFT JOIN classes c ON t.id = c.trainer_id AND c.is_active = 1
        GROUP BY t.id
        ORDER BY t.name
    ");
    $stmt->execute();
    $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all clients with their current trainers
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, 
               um.trainer_id, t.name as trainer_name,
               mp.name as plan_name, um.start_date, um.end_date
        FROM users u
        JOIN user_memberships um ON u.id = um.user_id
        LEFT JOIN trainers t ON um.trainer_id = t.id
        LEFT JOIN membership_plans mp ON um.plan_id = mp.id
        WHERE u.role = 'member' AND um.status = 'active'
        ORDER BY u.username
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trainers - FitLife Gym Admin</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/admin-core.css">
    <link rel="stylesheet" href="../assets/css/admin-components.css">
    <link rel="stylesheet" href="../assets/css/admin-tables.css">
    <link rel="stylesheet" href="../assets/css/admin-modals.css">
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .trainer-management {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
            min-height: 100vh;
            padding-top: 100px; /* Account for navbar */
        }
        
        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .management-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            justify-content: center;
        }
        
        .tab-button {
            padding: 12px 24px;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin: 0 10px;
        }
        
        .tab-button.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        
        .tab-button:hover {
            color: #007bff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .trainer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .trainer-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            transition: transform 0.3s ease;
        }
        
        .trainer-card:hover {
            transform: translateY(-2px);
        }
        
        .trainer-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .trainer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .trainer-info h3 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .trainer-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .trainer-stats {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .trainer-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn, .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        
        .btn {
            padding: 12px 24px;
            font-size: 16px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .client-management {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .client-table th,
        .client-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .client-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .client-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-active {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: 500;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #333;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .day-schedule {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .day-header {
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .day-schedule input[type="time"] {
            width: 100%;
            padding: 5px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .day-schedule label {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .day-schedule input[type="checkbox"] {
            margin-right: 5px;
        }
        
        .modal-actions {
            margin-top: 20px;
            text-align: right;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .alert-success {
            background-color: #28a745;
        }
        
        .alert-error {
            background-color: #dc3545;
        }
        
        .alert-info {
            background-color: #007bff;
        }
        
        /* Additional essential styles */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .text-primary {
            color: #007bff !important;
        }
        
        .text-success {
            color: #28a745 !important;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .bg-light {
            background-color: #f8f9fa !important;
        }
        
        .border {
            border: 1px solid #dee2e6 !important;
        }
        
        .rounded {
            border-radius: 0.25rem !important;
        }
        
        .shadow-sm {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
        }
        
        .mb-3 {
            margin-bottom: 1rem !important;
        }
        
        .mt-3 {
            margin-top: 1rem !important;
        }
        
        .p-3 {
            padding: 1rem !important;
        }
        
        .text-center {
            text-align: center !important;
        }
        
        .d-flex {
            display: flex !important;
        }
        
        .justify-content-between {
            justify-content: space-between !important;
        }
        
        .align-items-center {
            align-items: center !important;
        }
        
        .w-100 {
            width: 100% !important;
        }
        
        /* Form styles */
        input, select, textarea {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        /* Table responsive */
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>

    <div class="trainer-management">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Manage Trainers</h1>
            <p>Manage trainer schedules, assign clients, and monitor trainer performance</p>
        </div>

        <!-- Management Tabs -->
        <div class="management-tabs">
            <button class="tab-button active" onclick="switchTab('trainers')">
                <i class="fas fa-user-tie"></i> Trainers Overview
            </button>
            <button class="tab-button" onclick="switchTab('clients')">
                <i class="fas fa-users"></i> Client Management
            </button>
            <button class="tab-button" onclick="switchTab('schedules')">
                <i class="fas fa-calendar-alt"></i> Schedule Management
            </button>
        </div>

        <!-- Trainers Overview Tab -->
        <div id="trainers-tab" class="tab-content active">
            <div class="trainer-grid">
                <?php foreach ($trainers as $trainer): ?>
                <div class="trainer-card">
                    <div class="trainer-header">
                        <div class="trainer-avatar">
                            <?php if ($trainer['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($trainer['image_url']); ?>" alt="Trainer" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="trainer-info">
                            <h3><?php echo htmlspecialchars($trainer['name']); ?></h3>
                            <p><?php echo htmlspecialchars($trainer['email']); ?></p>
                            <p class="<?php echo $trainer['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $trainer['is_active'] ? 'Active' : 'Inactive'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="trainer-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $trainer['client_count']; ?></div>
                            <div class="stat-label">Clients</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $trainer['class_count']; ?></div>
                            <div class="stat-label">Classes</div>
                        </div>
                    </div>
                    
                    <?php if ($trainer['specialties']): ?>
                    <div class="trainer-specialties" style="margin: 10px 0; font-size: 14px; color: #666;">
                        <strong>Specialties:</strong> <?php echo htmlspecialchars($trainer['specialties']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="trainer-actions">
                        <button class="btn-sm btn-primary" onclick="manageSchedule(<?php echo $trainer['id']; ?>, '<?php echo htmlspecialchars($trainer['name']); ?>')">
                            <i class="fas fa-calendar"></i> Schedule
                        </button>
                        <button class="btn-sm btn-success" onclick="viewClients(<?php echo $trainer['id']; ?>)">
                            <i class="fas fa-users"></i> Clients
                        </button>
                        <button class="btn-sm <?php echo $trainer['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                onclick="toggleTrainerStatus(<?php echo $trainer['id']; ?>, <?php echo $trainer['is_active'] ? 'false' : 'true'; ?>)">
                            <i class="fas fa-power-off"></i> <?php echo $trainer['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Client Management Tab -->
        <div id="clients-tab" class="tab-content">
            <div class="client-management">
                <h3><i class="fas fa-exchange-alt"></i> Switch Clients Between Trainers</h3>
                <table class="client-table">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Email</th>
                            <th>Current Trainer</th>
                            <th>Membership Plan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['username']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo $client['trainer_name'] ? htmlspecialchars($client['trainer_name']) : 'No Trainer'; ?></td>
                            <td><?php echo htmlspecialchars($client['plan_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($client['start_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($client['end_date'])); ?></td>
                            <td>
                                <button class="btn-sm btn-primary" onclick="switchClientTrainer(<?php echo $client['id']; ?>, <?php echo $client['trainer_id'] ?: 'null'; ?>, '<?php echo htmlspecialchars($client['username']); ?>')">
                                    <i class="fas fa-exchange-alt"></i> Switch
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Schedule Management Tab -->
        <div id="schedules-tab" class="tab-content">
            <div class="client-management">
                <h3><i class="fas fa-calendar-alt"></i> Trainer Schedules Overview</h3>
                <p>Click on a trainer's "Schedule" button in the Trainers Overview tab to manage their weekly schedule.</p>
                
                <div class="schedule-overview">
                    <?php foreach ($trainers as $trainer): ?>
                    <div style="margin: 20px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px;">
                        <h4><?php echo htmlspecialchars($trainer['name']); ?></h4>
                        <button class="btn-sm btn-primary" onclick="manageSchedule(<?php echo $trainer['id']; ?>, '<?php echo htmlspecialchars($trainer['name']); ?>')">
                            <i class="fas fa-calendar"></i> Manage Schedule
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Management Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-alt"></i> Manage Schedule - <span id="trainerName"></span></h2>
                <span class="close" onclick="closeModal('scheduleModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="schedule-grid">
                    <div class="day-schedule">
                        <div class="day-header">Monday</div>
                        <input type="time" id="mon_start" placeholder="Start Time">
                        <input type="time" id="mon_end" placeholder="End Time">
                        <label><input type="checkbox" id="mon_available"> Available</label>
                    </div>
                    <div class="day-schedule">
                        <div class="day-header">Tuesday</div>
                        <input type="time" id="tue_start" placeholder="Start Time">
                        <input type="time" id="tue_end" placeholder="End Time">
                        <label><input type="checkbox" id="tue_available"> Available</label>
                    </div>
                    <div class="day-schedule">
                        <div class="day-header">Wednesday</div>
                        <input type="time" id="wed_start" placeholder="Start Time">
                        <input type="time" id="wed_end" placeholder="End Time">
                        <label><input type="checkbox" id="wed_available"> Available</label>
                    </div>
                    <div class="day-schedule">
                        <div class="day-header">Thursday</div>
                        <input type="time" id="thu_start" placeholder="Start Time">
                        <input type="time" id="thu_end" placeholder="End Time">
                        <label><input type="checkbox" id="thu_available"> Available</label>
                    </div>
                    <div class="day-schedule">
                        <div class="day-header">Friday</div>
                        <input type="time" id="fri_start" placeholder="Start Time">
                        <input type="time" id="fri_end" placeholder="End Time">
                        <label><input type="checkbox" id="fri_available"> Available</label>
                    </div>
                    <div class="day-schedule">
                        <div class="day-header">Saturday</div>
                        <input type="time" id="sat_start" placeholder="Start Time">
                        <input type="time" id="sat_end" placeholder="End Time">
                        <label><input type="checkbox" id="sat_available"> Available</label>
                    </div>
                    <div class="day-schedule">
                        <div class="day-header">Sunday</div>
                        <input type="time" id="sun_start" placeholder="Start Time">
                        <input type="time" id="sun_end" placeholder="End Time">
                        <label><input type="checkbox" id="sun_available"> Available</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn-sm btn-success" onclick="saveSchedule()">
                        <i class="fas fa-save"></i> Save Schedule
                    </button>
                    <button class="btn-sm" onclick="closeModal('scheduleModal')" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Switch Modal -->
    <div id="switchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exchange-alt"></i> Switch Client Trainer</h2>
                <span class="close" onclick="closeModal('switchModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Switch <strong id="clientName"></strong> to a new trainer:</p>
                <select id="newTrainerSelect" style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">Select New Trainer</option>
                    <?php foreach ($trainers as $trainer): ?>
                        <?php if ($trainer['is_active']): ?>
                        <option value="<?php echo $trainer['id']; ?>"><?php echo htmlspecialchars($trainer['name']); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="modal-actions">
                    <button class="btn-sm btn-success" onclick="confirmSwitch()">
                        <i class="fas fa-check"></i> Confirm Switch
                    </button>
                    <button class="btn-sm" onclick="closeModal('switchModal')" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin-trainers.js"></script>
</body>
</html>
