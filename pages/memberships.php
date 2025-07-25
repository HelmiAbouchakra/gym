<?php
// Initialize variables
$formSubmitted = false;
$errors = [];
$success = false;
$successMessage = '';
$redirectUrl = '';

// Include database connection
require_once __DIR__ . '/../includes/db_connect.php';

// Get database connection
$pdo = getConnection();
if (!$pdo) {
    die('Database connection failed');
}

// Fetch trainers for selection
$trainers = [];
try {
    $trainer_stmt = $pdo->query("SELECT t.id, t.name, t.specialties FROM trainers t WHERE t.is_active = 1");
    $trainers = $trainer_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error silently
}

// Check if logged-in user already has an active membership
$hasActiveMembership = false;
$activeMembershipMessage = '';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    try {
        $checkStmt = $pdo->prepare("SELECT um.*, mp.name as plan_name FROM user_memberships um JOIN membership_plans mp ON um.plan_id = mp.id WHERE um.user_id = ? AND um.status = 'active' LIMIT 1");
        $checkStmt->execute([$_SESSION['user_id']]);
        $activeMembership = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activeMembership) {
            $hasActiveMembership = true;
            $activeMembershipMessage = "You already have an active membership (" . htmlspecialchars($activeMembership['plan_name']) . "). You can upgrade your membership from your membership dashboard.";
        }
    } catch (PDOException $e) {
        error_log('Error checking existing membership: ' . $e->getMessage());
    }
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

// Debug: Check if form is being submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST request received');
    error_log('POST data: ' . print_r($_POST, true));
}

// Process the form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_membership'])) {
    $formSubmitted = true;
    error_log('Form submission detected - processing membership');
    
    // Check if user is already logged in and has an active membership
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id'])) {
        try {
            $checkActiveStmt = $pdo->prepare("SELECT id FROM user_memberships WHERE user_id = ? AND status = 'active' LIMIT 1");
            $checkActiveStmt->execute([$_SESSION['user_id']]);
            $existingActiveMembership = $checkActiveStmt->fetch();
            
            if ($existingActiveMembership) {
                $errors['general'] = 'You already have an active membership. Please go to your membership page to upgrade your existing plan instead of creating a new one.';
            }
        } catch (PDOException $e) {
            error_log('Error checking existing membership: ' . $e->getMessage());
        }
    }
    
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

    // Process the base plan
    $plan_type = $_POST['plan_type'];
    $base_price = (float) $_POST['base_price'];

    // Calculate total price (no addons anymore)
    $total_price = $base_price;

    // Debug: Check validation results
    error_log('Validation errors: ' . print_r($errors, true));
    
    // If no errors, process the form data
    if (empty($errors)) {
        error_log('No validation errors - proceeding with database insert');
        try {
            // Enable PDO error mode
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Start transaction to ensure data consistency
            $pdo->beginTransaction();
            
            // Check if user is logged in, use session user_id if available
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Always create a new user for membership registration
            $email = $_POST['email'];
            $username = strtolower(substr($_POST['first_name'], 0, 1) . $_POST['last_name']) . rand(100, 999);
            $password = password_hash('changeme123', PASSWORD_DEFAULT);
            
            // Check if email already exists
            $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkEmailStmt->execute([$email]);
            $existingUser = $checkEmailStmt->fetch();
            
            if ($existingUser) {
                // Use existing user
                $user_id = $existingUser['id'];
                error_log('Using existing user ID: ' . $user_id);
                
                // Check if user already has an active membership
                $activeMembershipStmt = $pdo->prepare("SELECT id FROM user_memberships WHERE user_id = ? AND status = 'active' LIMIT 1");
                $activeMembershipStmt->execute([$user_id]);
                $activeMembership = $activeMembershipStmt->fetch();
                
                if ($activeMembership) {
                    throw new Exception('You already have an active membership. Please upgrade your existing membership instead of creating a new one.');
                }
            } else {
                // Create new user
                $newUserStmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'member')");
                $newUserStmt->execute([$username, $password, $email]);
                $user_id = $pdo->lastInsertId();
                error_log('Created new user with ID: ' . $user_id);
                
                // Auto-login the new user
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
                (user_id, plan_id, trainer_id, start_date, end_date, status, payment_status) 
                VALUES (?, ?, ?, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL ? DAY), 'active', 'paid')");
            
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
                $duration
            ]);
            
            // Get the last inserted membership ID
            $membership_id = $pdo->lastInsertId();
            
            // Verify membership was created successfully
            if (!$membership_id) {
                throw new Exception('Failed to create membership record');
            }
            
            error_log("Created membership with ID: $membership_id");
            

            
            // Commit the transaction
            $pdo->commit();
            error_log('Transaction committed successfully - Membership ID: ' . $membership_id);
            
            // Success
            $success = true;
            $successMessage = 'Your membership has been successfully registered!';
            $redirectUrl = '../index.php?id=' . $membership_id;
            
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
    } else {
        error_log('Form validation failed - not processing');
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log('POST received but submit_membership not set');
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitLife Gym Memberships</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/footer.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/memberships.css?v=<?php echo time(); ?>">
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
            <h2>Join <span>FitLife Gym</span></h2>
            <p>Fill out the form below to start your fitness journey with us</p>
        </div>
    </section>
    
    <section id="membership-form-section" class="">
        <div class="container">
            
            <?php if ($hasActiveMembership): ?>
                <!-- Show message for users with active membership -->
                <div class="alert alert-info" style="text-align: center; padding: 2rem; margin-bottom: 2rem;">
                    <i class="fas fa-info-circle" style="font-size: 2rem; color: #17a2b8; margin-bottom: 1rem;"></i>
                    <h3>Active Membership Found</h3>
                    <p><?php echo $activeMembershipMessage; ?></p>
                    <div style="margin-top: 1.5rem;">
                        <a href="my-membership.php" class="btn btn-primary" style="margin-right: 1rem;">
                            <i class="fas fa-user"></i> View My Membership
                        </a>
                        <a href="upgrade-membership.php" class="btn btn-success">
                            <i class="fas fa-arrow-up"></i> Upgrade Plan
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Show registration form for users without active membership -->
                <?php if ($formSubmitted): ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h3>Success!</h3>
                            <p><?php echo htmlspecialchars($successMessage); ?></p>
                        </div>
                    <?php elseif (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <h3>Please fix the following errors:</h3>
                            <ul>
                                <?php foreach ($errors as $field => $error): ?>
                                    <li><strong><?php echo ucfirst(str_replace('_', ' ', $field)); ?>:</strong> <?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            
            <h2>Membership Registration</h2>
            <p>Complete the form below to register for your gym membership:</p>
            
            <form id="membership-form" method="POST" action="">
                <!-- Plan Selection (now visible) -->
                <div class="plan-selection-section">
                    <h3>Select Your Membership Plan</h3>
                    <div class="form-group">
                        <label for="plan_type">Membership Plan *</label>
                        <select id="plan_type" name="plan_type" class="form-control" required>
                            <option value="">-- Select a Plan --</option>
                            <?php if (!empty($membershipPlans)): ?>
                                <?php foreach ($membershipPlans as $plan): ?>
                                    <option value="<?php echo $plan['id']; ?>" 
                                            data-price="<?php echo $plan['price']; ?>"
                                            <?php echo oldValue('plan_type') == $plan['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($plan['name']); ?> - $<?php echo number_format($plan['price'], 2); ?>/month
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <input type="hidden" id="base-price" name="base_price" value="<?php echo oldValue('base_price', '29.00'); ?>">
                <input type="hidden" id="plan-display-name" name="plan_display_name" value="<?php echo oldValue('plan_display_name', 'Basic Plan'); ?>">
                
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
            <?php endif; // End of conditional block for users without active membership ?>
        </div>
    </section>
    
    <?php
    // Include footer component
    include_once '../includes/components/footer.php';
    ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Plan dropdown selection handler
            const planDropdown = document.getElementById('plan_type');
            const basePriceInput = document.getElementById('base-price');
            const planDisplayNameInput = document.getElementById('plan-display-name');
            
            if (planDropdown) {
                planDropdown.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        const planPrice = selectedOption.dataset.price;
                        const planName = selectedOption.textContent.split(' - ')[0];
                        
                        // Update hidden form fields
                        if (basePriceInput) basePriceInput.value = planPrice;
                        if (planDisplayNameInput) planDisplayNameInput.value = planName;
                        
                        // Update summary display
                        updatePlanSummary(planName, planPrice);
                        
                        // Update total price calculation
                        updateTotalPrice();
                    } else {
                        // Reset if no plan selected
                        if (basePriceInput) basePriceInput.value = '0';
                        if (planDisplayNameInput) planDisplayNameInput.value = '';
                        updatePlanSummary('Select a plan', '0');
                        updateTotalPrice();
                    }
                });
            }
            
            // Function to update plan summary display
            function updatePlanSummary(planName, planPrice) {
                const summaryPlanName = document.getElementById('summary-plan-name');
                const summaryBasePrice = document.getElementById('summary-base-price');
                
                if (summaryPlanName) summaryPlanName.textContent = planName;
                if (summaryBasePrice) summaryBasePrice.textContent = '$' + parseFloat(planPrice).toFixed(2);
            }
            
            // Plan selection buttons (if any exist)
            const planButtons = document.querySelectorAll('.select-plan');
            planButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get plan data
                    const plan = this.dataset.plan;
                    const price = this.dataset.price;
                    const name = this.dataset.name || plan;
                    
                    // Set form values
                    document.getElementById('plan_type').value = plan;
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