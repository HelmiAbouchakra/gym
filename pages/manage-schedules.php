<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
    header('Location: ../index.php');
    exit();
}

$trainer_id = $_SESSION['user_id'];

// Get trainer info
$trainer_stmt = $pdo->prepare("SELECT * FROM trainers WHERE user_id = ?");
$trainer_stmt->execute([$trainer_id]);
$trainer = $trainer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$trainer) {
    echo "Trainer profile not found.";
    exit();
}

// Get trainer's classes
$classes_stmt = $pdo->prepare("SELECT * FROM classes WHERE trainer_id = ? ORDER BY name");
$classes_stmt->execute([$trainer['id']]);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing schedules
$schedules_stmt = $pdo->prepare("
    SELECT cs.*, c.name as class_name, c.description, c.capacity 
    FROM class_schedules cs 
    JOIN classes c ON cs.class_id = c.id 
    WHERE c.trainer_id = ? 
    ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time
");
$schedules_stmt->execute([$trainer['id']]);
$existing_schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group schedules by day
$schedules_by_day = [];
foreach ($existing_schedules as $schedule) {
    $schedules_by_day[$schedule['day_of_week']][] = $schedule;
}

$page_title = "Manage Class Schedules";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - FitLife Gym</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Site CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <!-- Bootstrap CSS (for grid system only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Include Navbar -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>

<div class="main-container">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Manage Class Schedules</h1>
                <p>Create and manage your weekly class schedules</p>
            </div>
            <a href="trainer-dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Trainer Info -->
    <div class="info-card">
        <h5>
            <i class="fas fa-user-tie"></i> Trainer: <?php echo htmlspecialchars($trainer['name']); ?>
        </h5>
        <p class="mb-0">
            <strong>Specialties:</strong> <?php echo htmlspecialchars($trainer['specialties']); ?><br>
            <strong>Experience:</strong> <?php echo isset($trainer['experience_years']) ? htmlspecialchars($trainer['experience_years']) : 'N/A'; ?> years
        </p>
    </div>

    <!-- Add New Schedule Form -->
    <div class="form-card">
        <div class="form-card-header">
            <h5><i class="fas fa-plus"></i> Add New Class Schedule</h5>
        </div>
        <div class="form-card-body">
                    <form id="scheduleForm">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="class_id" class="form-label">Class</label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Select a class...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="day_of_week" class="form-label">Day</label>
                                <select class="form-select" id="day_of_week" name="day_of_week" required>
                                    <option value="">Select day...</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-2">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Active (available for booking)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Schedule
                                </button>
                            </div>
                        </div>
                    </form>
        </div>
    </div>

    <!-- Current Schedules -->
    <div class="form-card">
        <div class="form-card-header">
            <h5><i class="fas fa-calendar-week"></i> Current Class Schedules</h5>
        </div>
        <div class="form-card-body">
                    <?php if (empty($existing_schedules)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No class schedules found. Add your first schedule above!
                        </div>
                    <?php else: ?>
                        <div class="schedule-grid">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day): 
                                if (isset($schedules_by_day[$day])): 
                            ?>
                                <div class="day-card">
                                    <div class="day-card-header">
                                        <i class="fas fa-calendar-day"></i> <?php echo $day; ?>
                                    </div>
                                    <div class="day-card-body">
                                        <?php foreach ($schedules_by_day[$day] as $schedule): ?>
                                            <div class="schedule-item">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($schedule['class_name']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock"></i> 
                                                        <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                                    </small><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-users"></i> Capacity: <?php echo $schedule['capacity']; ?>
                                                    </small><br>
                                                    <span class="badge bg-<?php echo $schedule['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </div>
                                                <div class="schedule-actions">
                                                    <button class="btn btn-outline-primary" onclick="editSchedule(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['class_name']); ?>', '<?php echo $schedule['day_of_week']; ?>', '<?php echo $schedule['start_time']; ?>', '<?php echo $schedule['end_time']; ?>', <?php echo $schedule['is_active'] ? 'true' : 'false'; ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editScheduleModalLabel">
                    <i class="fas fa-edit"></i> Edit Class Schedule
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editScheduleForm">
                    <input type="hidden" id="edit_schedule_id" name="schedule_id">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="edit_class_id" class="form-label">Class</label>
                            <select class="form-select" id="edit_class_id" name="class_id" required>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_day_of_week" class="form-label">Day</label>
                            <select class="form-select" id="edit_day_of_week" name="day_of_week" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="edit_start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active (available for booking)
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="updateSchedule()">
                    <i class="fas fa-save"></i> Update Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<div id="messageContainer"></div>

<script>
// Add new schedule
document.getElementById('scheduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../actions/manage_schedule.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            this.reset();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while adding the schedule.', 'error');
    });
});

// Edit schedule
function editSchedule(scheduleId, className, dayOfWeek, startTime, endTime, isActive) {
    // Populate the edit modal with current values
    document.getElementById('edit_schedule_id').value = scheduleId;
    document.getElementById('edit_day_of_week').value = dayOfWeek;
    document.getElementById('edit_start_time').value = startTime;
    document.getElementById('edit_end_time').value = endTime;
    document.getElementById('edit_is_active').checked = isActive;
    
    // Find and select the correct class
    const classSelect = document.getElementById('edit_class_id');
    for (let option of classSelect.options) {
        if (option.text === className) {
            option.selected = true;
            break;
        }
    }
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
    modal.show();
}

// Update schedule
function updateSchedule() {
    const formData = new FormData(document.getElementById('editScheduleForm'));
    formData.append('action', 'update');
    
    fetch('../actions/manage_schedule.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            // Hide the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editScheduleModal'));
            modal.hide();
            // Reload the page to show updated schedule
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while updating the schedule.', 'error');
    });
}

// Delete schedule
function deleteSchedule(scheduleId) {
    if (confirm('Are you sure you want to delete this schedule? This will also cancel any existing bookings.')) {
        fetch('../actions/manage_schedule.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&schedule_id=${scheduleId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred while deleting the schedule.', 'error');
        });
    }
}

// Show message function
function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    container.innerHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 1050;">
            <i class="fas ${icon}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}
</script>

<style>
/* Schedule Management Styles - Matching Site Design */
body {
    background-color: var(--light);
    padding-top: 80px;
}

.main-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    padding: 40px;
    margin: 20px auto;
    max-width: 1200px;
}

.page-header {
    border-bottom: 3px solid var(--primary);
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.page-header h1 {
    color: var(--secondary);
    font-family: 'Montserrat', sans-serif;
    font-weight: 700;
    margin-bottom: 5px;
}

.page-header p {
    color: var(--gray);
    font-size: 1.1rem;
    margin-bottom: 0;
}

.info-card {
    background: linear-gradient(135deg, var(--primary) 0%, #e64a19 100%);
    color: white;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 8px rgba(255, 87, 34, 0.3);
}

.info-card h5 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
    margin-bottom: 15px;
}

.form-card {
    background-color: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 30px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-card-header {
    background-color: var(--secondary);
    color: white;
    padding: 20px;
    border-bottom: none;
}

.form-card-header h5 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
    margin: 0;
}

.form-card-body {
    padding: 30px;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 4px;
    padding: 12px 15px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(255, 87, 34, 0.25);
}

.btn {
    padding: 12px 30px;
    font-weight: 600;
    text-transform: uppercase;
    border-radius: 4px;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
}

.btn-primary:hover {
    background-color: #e64a19;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-outline-secondary {
    background-color: transparent;
    border: 2px solid var(--gray);
    color: var(--gray);
}

.btn-outline-secondary:hover {
    background-color: var(--gray);
    color: white;
}

.btn-outline-primary {
    background-color: transparent;
    border: 2px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background-color: var(--primary);
    color: white;
}

.btn-outline-danger {
    background-color: transparent;
    border: 2px solid #dc3545;
    color: #dc3545;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    color: white;
}

.schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.day-card {
    background-color: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.day-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.day-card-header {
    background-color: var(--secondary);
    color: white;
    padding: 15px 20px;
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
}

.day-card-body {
    padding: 15px;
}

.schedule-item {
    background-color: var(--light);
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.schedule-item:hover {
    background-color: #e9ecef;
    border-color: var(--primary);
}

.schedule-item:last-child {
    margin-bottom: 0;
}

.schedule-actions {
    display: flex;
    gap: 5px;
    margin-top: 10px;
}

.schedule-actions .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    text-transform: none;
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
}

.badge.bg-success {
    background-color: var(--success) !important;
}

.badge.bg-secondary {
    background-color: var(--gray) !important;
}

.alert {
    border-radius: 6px;
    border: none;
    padding: 20px;
    margin: 20px 0;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

/* Edit Modal Styles */
.modal-content {
    border-radius: 8px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
    background-color: var(--secondary);
    color: white;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
}

.modal-header h5 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
}

.modal-body {
    padding: 30px;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    padding: 20px 30px;
}

/* Responsive */
@media (max-width: 768px) {
    .main-container {
        margin: 10px;
        padding: 20px;
    }
    
    .page-header .d-flex {
        flex-direction: column;
        gap: 15px;
    }
    
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    
    .schedule-actions {
        flex-direction: column;
    }
}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
