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
    
    // Get all clients for this trainer
    $clients_query = "
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.profile_image,
            MAX(cb.created_at) as last_booking,
            COUNT(DISTINCT cb.id) as total_bookings,
            COUNT(DISTINCT cs.class_id) as unique_classes
        FROM users u
        JOIN class_bookings cb ON u.id = cb.user_id
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        WHERE c.trainer_id = ?
        GROUP BY u.id
        ORDER BY last_booking DESC
    ";
    
    $clients_stmt = $pdo->prepare($clients_query);
    $clients_stmt->execute([$trainer_id]);
    $clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Clients - FitLife Gym</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/clients.css">
</head>
<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="clients-container">
        <div class="clients-header">
            <h1 class="page-title">My Clients</h1>
            
            <div class="search-box">
                <span class="search-icon"><i class="fas fa-search"></i></span>
                <input type="text" id="clientSearch" placeholder="Search clients..." onkeyup="searchClients()">
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php elseif (empty($clients)): ?>
            <div class="clients-table-container">
                <div class="no-clients">
                    <h3>You don't have any clients yet</h3>
                    <p>When members book your classes, they'll appear here.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="clients-table-container">
                <table class="clients-table" id="clientsTable">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Last Booking</th>
                            <th>Total Sessions</th>
                            <th>Classes Taken</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <div class="client-info">
                                        <img src="<?php echo !empty($client['profile_image']) ? $base_url . $client['profile_image'] : $base_url . 'assets/images/default-avatar.png'; ?>" alt="Client" class="client-avatar">
                                        <div>
                                            <div class="client-name"><?php echo htmlspecialchars($client['username']); ?></div>
                                            <div class="client-email"><?php echo htmlspecialchars($client['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($client['last_booking'])); ?>
                                </td>
                                <td>
                                    <span class="booking-count"><?php echo $client['total_bookings']; ?></span> sessions
                                </td>
                                <td>
                                    <?php echo $client['unique_classes']; ?> different classes
                                </td>
                                <td>
                                    <div class="client-actions">
                                        <a href="client_details.php?id=<?php echo $client['id']; ?>" class="action-btn btn-view">
                                            <i class="fas fa-user"></i> View Details
                                        </a>
                                        <a href="message.php?client=<?php echo $client['id']; ?>" class="action-btn btn-message">
                                            <i class="fas fa-envelope"></i> Message
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        function searchClients() {
            // Get input value and convert to lowercase
            var input = document.getElementById("clientSearch");
            var filter = input.value.toLowerCase();
            
            // Get table and rows
            var table = document.getElementById("clientsTable");
            var rows = table.getElementsByTagName("tr");
            
            // Loop through rows and hide those that don't match the search
            for (var i = 1; i < rows.length; i++) { // Start at 1 to skip header row
                var nameCell = rows[i].getElementsByTagName("td")[0];
                
                if (nameCell) {
                    // Get the text content of the name cell
                    var nameText = nameCell.textContent || nameCell.innerText;
                    
                    // If the name contains the search term, show the row, otherwise hide it
                    if (nameText.toLowerCase().indexOf(filter) > -1) {
                        rows[i].style.display = "";
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>