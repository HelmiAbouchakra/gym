<?php
// Initialize variables
$formSubmitted = false;
$errors = [];
$success = false;
$successMessage = '';
$redirectUrl = '';

// Include database connection
require_once __DIR__ . '/../includes/db_connect.php';

// Fetch trainers for selection
$trainers = [];
try {
    $trainer_stmt = $pdo->query("SELECT t.id, t.name, t.specialties FROM trainers t WHERE t.is_active = 1");
    $trainers = $trainer_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error silently
}

// Fetch membership plans from the database - only get unique plans by name
try {
    // Use DISTINCT and GROUP BY to avoid duplicates
    $stmt = $pdo->query("SELECT DISTINCT mp.* FROM membership_plans mp
                       JOIN (SELECT MIN(id) as min_id, name
                             FROM membership_plans 
                             WHERE is_active = 1
                             GROUP BY name) unique_plans
                       ON mp.id = unique_plans.min_id
                       ORDER BY mp.price ASC");
    $membershipPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle database error
    $dbError = 'Database error: ' . $e->getMessage();
    $membershipPlans = [];
}

// Process the form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_membership'])) {
    $formSubmitted = true;
    
    // Validate form data
    $required_fields = [
        'plan_type' => 'Please select a membership plan',
        'first_name' => 'First name is required',
        'last_name' => 'Last name is required',
        'email' => 'Email is required',
        'phone' => 'Phone number is required',
        'address' => 'Address is required',
        'city' => 'City is required',
        'zip' => 'ZIP code is required',
        'terms_agree' => 'You must agree to the terms and conditions'
    ];

    // Check required fields
    foreach ($required_fields as $field => $message) {
        if (empty($_POST[$field])) {
            $errors[$field] = $message;
        }
    }

    // Additional validation for email
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    // Payment validation removed as requested

    // Process add-ons
    $selected_addons = [];
    $addons_price = 0;

    $available_addons = [
        'personal_training' => [
            'name' => 'Personal Training Sessions',
            'price' => 30
        ],
        'nutrition_plan' => [
            'name' => 'Nutrition Plan',
            'price' => 25
        ],
        'guest_passes' => [
            'name' => 'Guest Passes',
            'price' => 15
        ],
        'towel_service' => [
            'name' => 'Towel Service',
            'price' => 10
        ]
    ];

    if (!empty($_POST['addons']) && is_array($_POST['addons'])) {
        foreach ($_POST['addons'] as $addon) {
            if (array_key_exists($addon, $available_addons)) {
                $selected_addons[] = $addon;
                $addons_price += $available_addons[$addon]['price'];
            }
        }
    }

    // Process the base plan
    $plan_type = $_POST['plan_type'];
    $base_price = (float) $_POST['base_price'];

    // Calculate total price
    $total_price = $base_price + $addons_price;

    // If no errors, process the form data
    if (empty($errors)) {
        try {
            // Enable PDO error mode
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Start transaction to ensure data consistency
            $pdo->beginTransaction();
            
            // Check if user is logged in, use session user_id if available
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Use the logged-in user's ID if available, otherwise create a new user
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
            } else {
                // Create a new user account for this customer
                $username = strtolower(substr($_POST['first_name'], 0, 1) . $_POST['last_name']) . rand(100, 999);
                $password = password_hash('changeme123', PASSWORD_DEFAULT); // Default password they can change later
                $email = $_POST['email'];
                
                // Check if email already exists
                $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $checkEmailStmt->execute([$email]);
                if ($checkEmailStmt->fetch()) {
                    throw new Exception('A user with this email already exists. Please log in first.');
                }
                
                // Insert new user
                $newUserStmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'member')");
                $newUserStmt->execute([$username, $password, $email]);
                $user_id = $pdo->lastInsertId();
                
                // You could also automatically log them in here
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'member';
            }
            
            // Make sure we have a user_id
            if (!$user_id) {
                throw new Exception('Unable to determine user ID for membership registration');
            }
            
            // 1. Save the membership to the database
            $stmt = $pdo->prepare("INSERT INTO user_memberships 
                (user_id, plan_id, trainer_id, start_date, end_date, status, payment_status, total_amount) 
                VALUES (?, ?, ?, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL ? DAY), 'active', 'paid', ?)");
            
            // Get plan details (need to get plan_id and duration)
            $plan_id_or_name = is_numeric($_POST['plan_type']) ? intval($_POST['plan_type']) : $_POST['plan_type'];
            $planStmt = $pdo->prepare("SELECT id, duration FROM membership_plans WHERE name = ? OR id = ? LIMIT 1");
            $planStmt->execute([$plan_id_or_name, $plan_id_or_name]);
            $planData = $planStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$planData) {
                throw new Exception('Selected plan not found');
            }
            
            $plan_id = $planData['id'];
            $duration = $planData['duration']; // Duration in months
            
            // Get trainer ID if selected
            $trainer_id = !empty($_POST['trainer_id']) ? intval($_POST['trainer_id']) : null;
            
            // Debug information
            error_log("Membership insertion - User ID: $user_id, Plan ID: $plan_id, Trainer ID: " . ($trainer_id ?? 'NULL') . ", Duration: $duration, Total Price: $total_price");
            
            // Insert the membership record
            $stmt->execute([
                $user_id,
                $plan_id,
                $trainer_id,
                $duration,
                $total_price
            ]);
            
            // Make sure the membership_addons table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS membership_addons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                membership_id INT NOT NULL,
                addon_name VARCHAR(100) NOT NULL,
                addon_price DECIMAL(10, 2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (membership_id) REFERENCES user_memberships(id) ON DELETE CASCADE
            )");
            
            // Get the last inserted membership ID
            $membership_id = $pdo->lastInsertId();
            
            // 2. Save add-ons if selected
            if (!empty($selected_addons)) {
                $addon_stmt = $pdo->prepare("INSERT INTO membership_addons 
                    (membership_id, addon_name, addon_price) VALUES (?, ?, ?)");
                
                foreach ($selected_addons as $addon) {
                    $addon_name = $available_addons[$addon]['name'];
                    $addon_price = $available_addons[$addon]['price'];
                    $addon_stmt->execute([$membership_id, $addon_name, $addon_price]);
                }
            }
            
            // 3. Save customer information
            $customer_stmt = $pdo->prepare("INSERT INTO customer_details 
                (membership_id, first_name, last_name, email, phone, address, city, state, zip) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            $customer_stmt->execute([
                $membership_id,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['city'],
                $_POST['state'] ?? '',
                $_POST['zip']
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            // Success
            $success = true;
            $successMessage = 'Your membership has been successfully registered!';
            $redirectUrl = 'thank_you.php?id=' . $membership_id;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Display detailed error message
            $errors['general'] = 'An error occurred: ' . $e->getMessage() . ' [Code: ' . $e->getCode() . ']';
            error_log('Membership registration error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            $success = false;
        }
    }
}

// Helper function to display field error message
function showError($field) {
    global $errors, $formSubmitted;
    if ($formSubmitted && isset($errors[$field])) {
        return '<div class="error-message">' . $errors[$field] . '</div>';
    }
    return '';
}

// Helper function to check if a field has an error
function hasError($field) {
    global $errors, $formSubmitted;
    return $formSubmitted && isset($errors[$field]) ? 'error' : '';
}

// Helper function to preserve form data after submission
function oldValue($field, $default = '') {
    return isset($_POST[$field]) ? htmlspecialchars($_POST[$field]) : $default;
}

// Helper function to check if checkbox/radio is selected
function isChecked($field, $value) {
    if (isset($_POST[$field])) {
        if (is_array($_POST[$field])) {
            return in_array($value, $_POST[$field]) ? 'checked' : '';
        } else {
            return $_POST[$field] == $value ? 'checked' : '';
        }
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitLife Gym Memberships</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/memberships.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php 
    // Set the base URL for the navbar component
    $base_url = '../';
    
    // Include navbar component
    include_once '../includes/components/navbar.php';
    ?>
    
    <?php if ($success): ?>
    <div class="success-message">
        <div class="container">
            <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
            <?php if (!empty($redirectUrl)): ?>
                <p>You will be redirected shortly...</p>
                <script>
                    setTimeout(function() {
                        window.location.href = "<?php echo $redirectUrl; ?>";
                    }, 3000);
                </script>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <section class="hero">
        <div class="container">
            <h2>Choose Your <span>Fitness Journey</span></h2>
            <p>Select the membership plan that fits your lifestyle and goals</p>
        </div>
    </section>
    
    <section class="membership-plans" id="membership-plans" <?php echo $formSubmitted && empty($errors) ? 'style="display:none;"' : ''; ?>>
        <div class="container">
            <?php if (isset($dbError)): ?>
                <div class="error-alert">
                    <?php echo $dbError; ?>
                </div>
            <?php endif; ?>
            
            <div class="plan-cards">
                <?php 
                $featuredPlanIndex = min(1, count($membershipPlans) - 1); // Set the second plan as featured by default (or first if only one plan)
                
                foreach ($membershipPlans as $index => $plan): 
                    // Extract features to array for display
                    $features = explode(',', $plan['features']);
                    $planClassName = strtolower(str_replace(' ', '-', $plan['name']));
                ?>
                <div class="plan-card" data-plan="<?php echo $planClassName; ?>">
                    <?php if ($index === $featuredPlanIndex && count($membershipPlans) > 1): ?>
                        <div class="featured-tag">MOST POPULAR</div>
                    <?php endif; ?>
                    
                    <div class="plan-header">
                        <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                        <div class="price">
                            <span class="currency">$</span>
                            <span class="amount"><?php echo (int)$plan['price']; ?></span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    
                    <div class="plan-features">
                        <ul>
                            <?php foreach ($features as $feature): ?>
                                <li><i class="fas fa-check"></i> <?php echo htmlspecialchars(trim($feature)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="plan-footer">
                        <button class="select-plan" data-plan="<?php echo $planClassName; ?>" data-price="<?php echo (int)$plan['price']; ?>" data-name="<?php echo htmlspecialchars($plan['name']); ?>">
                            SELECT PLAN
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($membershipPlans)): ?>
                <div class="no-plans-message">
                    <p>No membership plans are currently available. Please check back later.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <section id="customization" class="<?php echo (!$formSubmitted || !empty($errors)) ? 'hidden' : ''; ?>">
        <div class="container">
            <h2>Customize Your <span id="selected-plan-name"><?php echo oldValue('plan_display_name', 'Membership'); ?></span></h2>
            <p>Enhance your fitness experience with these add-ons:</p>
            
            <form id="membership-form" method="POST" action="">
                <input type="hidden" id="plan-type" name="plan_type" value="<?php echo oldValue('plan_type'); ?>">
                <input type="hidden" id="base-price" name="base_price" value="<?php echo oldValue('base_price'); ?>">
                <input type="hidden" id="plan-display-name" name="plan_display_name" value="<?php echo oldValue('plan_display_name'); ?>">
                
                <?php
                // Define add-ons to reduce repetition
                $addons = [
                    [
                        'id' => 'personal_training',
                        'name' => 'Personal Training Sessions',
                        'description' => 'One-on-one training with a certified fitness coach',
                        'price' => 30
                    ],
                    [
                        'id' => 'nutrition_plan',
                        'name' => 'Nutrition Plan',
                        'description' => 'Customized meal plans to support your fitness goals',
                        'price' => 25
                    ],
                    [
                        'id' => 'guest_passes',
                        'name' => 'Guest Passes',
                        'description' => 'Bring a friend (4 passes per month)',
                        'price' => 15
                    ],
                    [
                        'id' => 'towel_service',
                        'name' => 'Towel Service',
                        'description' => 'Fresh towels provided during each visit',
                        'price' => 10
                    ]
                ];
                ?>
                <div class="addons-container">
                    <?php foreach ($addons as $addon): ?>
                    <div class="addon-item">
                        <input type="checkbox" id="<?php echo $addon['id']; ?>" name="addons[]" value="<?php echo $addon['id']; ?>" <?php echo isChecked('addons', $addon['id']); ?>>
                        <label for="<?php echo $addon['id']; ?>">
                            <div class="addon-info">
                                <h4><?php echo htmlspecialchars($addon['name']); ?></h4>
                                <p><?php echo htmlspecialchars($addon['description']); ?></p>
                            </div>
                            <div class="addon-price">$<?php echo $addon['price']; ?>/month</div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="membership-summary">
                    <h3>Membership Summary</h3>
                    <div class="summary-item">
                        <span>Base Plan:</span>
                        <span id="summary-plan-name"><?php echo oldValue('plan_display_name', 'Select a plan'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Base Price:</span>
                        <span id="summary-base-price">$<?php echo oldValue('base_price', '0'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Add-ons:</span>
                        <span id="summary-addons-price">$0</span>
                    </div>
                    <div class="summary-item total">
                        <span>Total Monthly:</span>
                        <span id="summary-total-price">$<?php echo oldValue('base_price', '0'); ?></span>
                    </div>
                </div>
                
                <div class="personal-info">
                    <h3>Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group <?php echo hasError('first_name'); ?>">
                            <label for="first_name">First Name*</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo oldValue('first_name'); ?>">
                            <?php echo showError('first_name'); ?>
                        </div>
                        <div class="form-group <?php echo hasError('last_name'); ?>">
                            <label for="last_name">Last Name*</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo oldValue('last_name'); ?>">
                            <?php echo showError('last_name'); ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group <?php echo hasError('email'); ?>">
                            <label for="email">Email Address*</label>
                            <input type="email" id="email" name="email" value="<?php echo oldValue('email'); ?>">
                            <?php echo showError('email'); ?>
                        </div>
                        <div class="form-group <?php echo hasError('phone'); ?>">
                            <label for="phone">Phone Number*</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo oldValue('phone'); ?>">
                            <?php echo showError('phone'); ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group <?php echo hasError('address'); ?>">
                            <label for="address">Address*</label>
                            <input type="text" id="address" name="address" value="<?php echo oldValue('address'); ?>">
                            <?php echo showError('address'); ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group <?php echo hasError('city'); ?>">
                            <label for="city">City*</label>
                            <input type="text" id="city" name="city" value="<?php echo oldValue('city'); ?>">
                            <?php echo showError('city'); ?>
                        </div>
                        <div class="form-group <?php echo hasError('zip'); ?>">
                            <label for="zip">ZIP Code*</label>
                            <input type="text" id="zip" name="zip" value="<?php echo oldValue('zip'); ?>">
                            <?php echo showError('zip'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="trainer-selection">
                    <h3>Select Your Trainer</h3>
                    <p>Choose a trainer to work with for your fitness journey (optional)</p>
                    <div class="trainer-list">
                        <?php if (!empty($trainers)): ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="trainer_id">Trainer</label>
                                    <select id="trainer_id" name="trainer_id" class="form-control">
                                        <option value="">-- Select a trainer (optional) --</option>
                                        <?php foreach ($trainers as $trainer): ?>
                                            <option value="<?php echo $trainer['id']; ?>" <?php echo oldValue('trainer_id') == $trainer['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($trainer['name']); ?> - <?php echo htmlspecialchars($trainer['specialties']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php else: ?>
                            <p>No trainers are currently available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Information section removed as requested -->
                
                <div class="terms <?php echo hasError('terms_agree'); ?>">
                    <input type="checkbox" id="terms_agree" name="terms_agree" value="1" <?php echo isChecked('terms_agree', '1'); ?>>
                    <label for="terms_agree">I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a>*</label>
                    <?php echo showError('terms_agree'); ?>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="back-to-plans" id="back-to-plans">BACK TO PLANS</button>
                    <button type="submit" class="complete-signup" name="submit_membership">COMPLETE SIGNUP</button>
                </div>
            </form>
        </div>
    </section>
    
    <?php
    // Include footer component
    include_once '../includes/components/footer.php';
    ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Plan selection
            const planButtons = document.querySelectorAll('.select-plan');
            planButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get plan data
                    const plan = this.dataset.plan;
                    const price = this.dataset.price;
                    const name = this.dataset.name || plan;
                    
                    // Set form values
                    document.getElementById('plan-type').value = plan;
                    document.getElementById('base-price').value = price;
                    document.getElementById('plan-display-name').value = name;
                    
                    // Update display name
                    document.getElementById('selected-plan-name').textContent = name;
                    
                    // Hide plan selection and show customization form
                    document.getElementById('membership-plans').style.display = 'none';
                    document.getElementById('customization').classList.remove('hidden');
                    
                    // Initialize the price calculation
                    updateTotalPrice();
                    
                    // Scroll to customization section
                    document.getElementById('customization').scrollIntoView({ behavior: 'smooth' });
                });
            });
            
            // Back to plans button
            document.getElementById('back-to-plans').addEventListener('click', function() {
                document.getElementById('customization').classList.add('hidden');
                document.getElementById('membership-plans').style.display = 'block';
                
                window.scrollTo({
                    top: document.getElementById('membership-plans').offsetTop - 100,
                    behavior: 'smooth'
                });
            });
            
            // Toggle payment method display
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    const creditCardDetails = document.getElementById('credit-card-details');
                    if (this.value === 'credit_card') {
                        creditCardDetails.style.display = 'block';
                    } else {
                        creditCardDetails.style.display = 'none';
                    }
                });
            });
            
            // Addon selection and price calculation
            const addonCheckboxes = document.querySelectorAll('input[name="addons[]"]');
            addonCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateTotalPrice();
                });
            });
            
            function updateTotalPrice() {
                const basePrice = parseFloat(document.getElementById('base-price').value) || 0;
                let addonsPrice = 0;
                
                // Define addon prices directly to ensure they're accurate
                const addonPrices = {
                    'personal_training': 30,
                    'nutrition_plan': 25,
                    'guest_passes': 15,
                    'towel_service': 10
                };
                
                // Calculate addons price
                document.querySelectorAll('input[name="addons[]"]:checked').forEach(checkbox => {
                    addonsPrice += addonPrices[checkbox.value] || 0;
                });
                
                // Format prices with two decimal places
                const formattedAddonsPrice = addonsPrice.toFixed(2);
                const total = basePrice + addonsPrice;
                const formattedTotal = total.toFixed(2);
                
                // Update the summary display
                document.getElementById('summary-addons-price').textContent = '$' + formattedAddonsPrice;
                document.getElementById('summary-total-price').textContent = '$' + formattedTotal;
                
                // Log for debugging
                console.log('Base price:', basePrice);
                console.log('Addons price:', addonsPrice);
                console.log('Total:', total);
            }
            
            // Card number formatting
            const cardNumberInput = document.getElementById('card_number');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                    let formattedValue = '';
                    
                    for (let i = 0; i < value.length; i++) {
                        if (i > 0 && i % 4 === 0) {
                            formattedValue += ' ';
                        }
                        formattedValue += value[i];
                    }
                    
                    e.target.value = formattedValue;
                });
            }
            
            // Expiry date formatting
            const expiryInput = document.getElementById('expiry');
            if (expiryInput) {
                expiryInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                    
                    if (value.length > 2) {
                        value = value.substring(0, 2) + '/' + value.substring(2, 4);
                    }
                    
                    e.target.value = value;
                });
            }
        });
    </script>
</body>
</html>