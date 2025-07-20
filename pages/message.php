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
if (!isset($_GET['client']) || empty($_GET['client'])) {
    header("Location: clients.php");
    exit();
}

$client_id = intval($_GET['client']);

// Include database connection
require_once __DIR__ . '/../config/db_config.php';

// Base URL for correct path resolution
$base_url = '../';

// Get trainer information
$user_id = $_SESSION['user_id'];

try {
    // Fetch trainer ID from the database
    $stmt = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $trainer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trainer) {
        // Redirect if trainer record doesn't exist
        header("Location: profile.php");
        exit();
    }
    
    $trainer_id = $trainer['id'];
    
    // Verify that this client belongs to this trainer (has taken classes with this trainer)
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
    $client_query = "SELECT id, username, email, profile_image FROM users WHERE id = ?";
    $client_stmt = $pdo->prepare($client_query);
    $client_stmt->execute([$client_id]);
    $client = $client_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        // Client not found
        header("Location: clients.php");
        exit();
    }
    
    // Create messages table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            message_content TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Get previous messages between the trainer and client
    $messages_query = "
        SELECT 
            m.*, 
            sender.username as sender_name,
            sender.profile_image as sender_image,
            recipient.username as recipient_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users recipient ON m.recipient_id = recipient.id
        WHERE (m.sender_id = ? AND m.recipient_id = ?) 
           OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
    ";
    $messages_stmt = $pdo->prepare($messages_query);
    $messages_stmt->execute([$user_id, $client_id, $client_id, $user_id]);
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process message submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $message_content = trim($_POST['message_content']);
        
        if (!empty($message_content)) {
            try {
                // Insert the message
                $insert_query = "
                    INSERT INTO messages (sender_id, recipient_id, message_content)
                    VALUES (?, ?, ?)
                ";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->execute([$user_id, $client_id, $message_content]);
                
                // Refresh the page to show the new message
                header("Location: message.php?client=" . $client_id . "&sent=1");
                exit();
            } catch (PDOException $e) {
                $message_error = "Could not send message: " . $e->getMessage();
            }
        } else {
            $message_error = "Message content cannot be empty";
        }
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
    <title>Message Client - FitLife Gym</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <style>
        .message-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .client-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .client-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .chat-container {
            background-color: #f5f5f5;
            border-radius: 10px;
            padding: 20px;
            height: 500px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-messages {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 10px;
            margin-bottom: 20px;
        }
        
        .message-bubble {
            padding: 12px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
            max-width: 70%;
            position: relative;
            word-wrap: break-word;
        }
        
        .message-outgoing {
            background-color: #4CAF50;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        
        .message-incoming {
            background-color: #fff;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 5px;
            display: block;
            text-align: right;
        }
        
        .message-form {
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex-grow: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        
        .message-input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .send-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
        
        .message-date {
            text-align: center;
            margin: 15px 0;
            font-size: 0.8rem;
            color: #666;
            position: relative;
        }
        
        .message-date::before, .message-date::after {
            content: "";
            height: 1px;
            background-color: #ddd;
            width: 45%;
            position: absolute;
            top: 50%;
        }
        
        .message-date::before {
            left: 0;
        }
        
        .message-date::after {
            right: 0;
        }
        
        .no-messages {
            text-align: center;
            color: #666;
            padding: 40px 0;
        }
    </style>
</head>
<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/../includes/components/navbar.php'; ?>
    
    <div class="message-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="message-header">
                <div class="client-profile">
                    <img src="<?php echo !empty($client['profile_image']) ? $base_url . $client['profile_image'] : $base_url . 'assets/images/default-avatar.png'; ?>" alt="Client" class="client-avatar">
                    <div>
                        <h2 class="client-name"><?php echo htmlspecialchars($client['username']); ?></h2>
                        <div class="client-email"><?php echo htmlspecialchars($client['email']); ?></div>
                    </div>
                </div>
                <a href="client_details.php?id=<?php echo $client_id; ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Client</a>
            </div>
            
            <div class="chat-container">
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <div class="no-messages">
                            <div><i class="far fa-comment-dots fa-3x"></i></div>
                            <p>No previous messages. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php 
                            $currentDate = null;
                            foreach ($messages as $msg): 
                                $messageDate = date('Y-m-d', strtotime($msg['created_at']));
                                if ($currentDate != $messageDate) {
                                    echo '<div class="message-date">' . date('F j, Y', strtotime($msg['created_at'])) . '</div>';
                                    $currentDate = $messageDate;
                                }
                        ?>
                            <div class="message-bubble <?php echo ($msg['sender_id'] == $user_id) ? 'message-outgoing' : 'message-incoming'; ?>">
                                <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
                                <span class="message-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($message_error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $message_error; ?>
                    </div>
                <?php endif; ?>
                
                <form class="message-form" method="post" action="message.php?client=<?php echo $client_id; ?>">
                    <input type="hidden" name="action" value="send_message">
                    <input type="text" name="message_content" class="message-input" placeholder="Type a message..." autocomplete="off" required>
                    <button type="submit" class="send-button">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/../includes/components/footer.php'; ?>
    
    <script>
        // Scroll to bottom of messages
        document.addEventListener('DOMContentLoaded', function() {
            var chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    </script>
</body>
</html>
