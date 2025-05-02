<?php
// Initialize variables
$formSubmitted = false;
$errors = [];
$success = false;
$successMessage = '';
$redirectUrl = '';

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
        'payment_method' => 'Payment method is required',
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

    // Validate payment information
    if (!empty($_POST['payment_method']) && $_POST['payment_method'] !== 'paypal') {
        $card_fields = [
            'card_name' => 'Card holder name is required',
            'card_number' => 'Card number is required',
            'expiry' => 'Expiry date is required',
            'cvv' => 'CVV is required'
        ];
        
        foreach ($card_fields as $field => $message) {
            if (empty($_POST[$field])) {
                $errors[$field] = $message;
            }
        }
        
        // Validate card number format
        if (!empty($_POST['card_number'])) {
            $sanitized = preg_replace('/\s+/', '', $_POST['card_number']);
            if (!preg_match('/^[0-9]{13,19}$/', $sanitized)) {
                $errors['card_number'] = 'Please enter a valid card number';
            }
        }
        
        // Validate expiry date format
        if (!empty($_POST['expiry'])) {
            if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $_POST['expiry'])) {
                $errors['expiry'] = 'Please enter a valid expiry date (MM/YY)';
            } else {
                // Check if card is expired
                list($month, $year) = explode('/', $_POST['expiry']);
                $expiry_date = \DateTime::createFromFormat('my', $month . $year);
                $current_date = new \DateTime();
                
                if ($expiry_date < $current_date) {
                    $errors['expiry'] = 'The card has expired';
                }
            }
        }
        
        // Validate CVV format
        if (!empty($_POST['cvv'])) {
            if (!preg_match('/^[0-9]{3,4}$/', $_POST['cvv'])) {
                $errors['cvv'] = 'Please enter a valid CVV';
            }
        }
    }

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
        // In a real application, you would:
        // 1. Save the membership details to a database
        // 2. Process the payment
        // 3. Create a user account if needed
        // 4. Send confirmation emails, etc.

        // For this example, we'll simulate a successful registration
        $success = true;
        $successMessage = 'Your membership has been successfully registered!';
        $redirectUrl = 'thank_you.php?id=' . rand(1000, 9999); // In a real app, use the actual membership ID
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
    <title>FitZone Gym Memberships</title>
    <link rel="stylesheet" href="../assets/css/memberships.css"
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1><i class="fas fa-dumbbell"></i> FitZone</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#" class="active">Memberships</a></li>
                    <li><a href="#">Classes</a></li>
                    <li><a href="#">Trainers</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
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
            <div class="plan-cards">
                <div class="plan-card" data-plan="basic">
                    <div class="plan-header">
                        <h3>Basic Fitness</h3>
                        <div class="price">
                            <span class="currency">$</span>
                            <span class="amount">29</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Gym access 6AM - 10PM</li>
                            <li><i class="fas fa-check"></i> Basic fitness equipment</li>
                            <li><i class="fas fa-check"></i> Locker room access</li>
                            <li><i class="fas fa-check"></i> 1 Fitness assessment</li>
                            <li class="unavailable"><i class="fas fa-times"></i> Group classes</li>
                            <li class="unavailable"><i class="fas fa-times"></i> Personal training</li>
                        </ul>
                    </div>
                    <div class="plan-footer">
                        <button class="select-plan" data-plan="basic" data-price="29">SELECT PLAN</button>
                    </div>
                </div>
                
                <div class="plan-card featured" data-plan="premium">
                    <div class="featured-tag">MOST POPULAR</div>
                    <div class="plan-header">
                        <h3>Premium Fitness</h3>
                        <div class="price">
                            <span class="currency">$</span>
                            <span class="amount">49</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> 24/7 Gym access</li>
                            <li><i class="fas fa-check"></i> All equipment access</li>
                            <li><i class="fas fa-check"></i> Locker room access</li>
                            <li><i class="fas fa-check"></i> Quarterly fitness assessment</li>
                            <li><i class="fas fa-check"></i> Unlimited group classes</li>
                            <li class="unavailable"><i class="fas fa-times"></i> Personal training</li>
                        </ul>
                    </div>
                    <div class="plan-footer">
                        <button class="select-plan" data-plan="premium" data-price="49">SELECT PLAN</button>
                    </div>
                </div>
                
                <div class="plan-card" data-plan="elite">
                    <div class="plan-header">
                        <h3>Elite Fitness</h3>
                        <div class="price">
                            <span class="currency">$</span>
                            <span class="amount">79</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> 24/7 Gym access</li>
                            <li><i class="fas fa-check"></i> All equipment access</li>
                            <li><i class="fas fa-check"></i> Premium locker access</li>
                            <li><i class="fas fa-check"></i> Monthly fitness assessment</li>
                            <li><i class="fas fa-check"></i> Unlimited group classes</li>
                            <li><i class="fas fa-check"></i> 2 PT sessions per month</li>
                        </ul>
                    </div>
                    <div class="plan-footer">
                        <button class="select-plan" data-plan="elite" data-price="79">SELECT PLAN</button>
                    </div>
                </div>
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
                
                <div class="addons-container">
                    <div class="addon-item">
                        <input type="checkbox" id="personal_training" name="addons[]" value="personal_training" <?php echo isChecked('addons', 'personal_training'); ?>>
                        <label for="personal_training">
                            <div class="addon-info">
                                <h4>Personal Training Sessions</h4>
                                <p>One-on-one training with a certified fitness coach</p>
                            </div>
                            <div class="addon-price">$30/month</div>
                        </label>
                    </div>
                    
                    <div class="addon-item">
                        <input type="checkbox" id="nutrition_plan" name="addons[]" value="nutrition_plan" <?php echo isChecked('addons', 'nutrition_plan'); ?>>
                        <label for="nutrition_plan">
                            <div class="addon-info">
                                <h4>Nutrition Plan</h4>
                                <p>Customized meal plans to support your fitness goals</p>
                            </div>
                            <div class="addon-price">$25/month</div>
                        </label>
                    </div>
                    
                    <div class="addon-item">
                        <input type="checkbox" id="guest_passes" name="addons[]" value="guest_passes" <?php echo isChecked('addons', 'guest_passes'); ?>>
                        <label for="guest_passes">
                            <div class="addon-info">
                                <h4>Guest Passes</h4>
                                <p>Bring a friend (4 passes per month)</p>
                            </div>
                            <div class="addon-price">$15/month</div>
                        </label>
                    </div>
                    
                    <div class="addon-item">
                        <input type="checkbox" id="towel_service" name="addons[]" value="towel_service" <?php echo isChecked('addons', 'towel_service'); ?>>
                        <label for="towel_service">
                            <div class="addon-info">
                                <h4>Towel Service</h4>
                                <p>Fresh towels provided during each visit</p>
                            </div>
                            <div class="addon-price">$10/month</div>
                        </label>
                    </div>
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
                
                <div class="payment-info">
                    <h3>Payment Information</h3>
                    <div class="payment-options">
                        <div class="payment-option">
                            <label>
                                <input type="radio" name="payment_method" value="credit_card" <?php echo isChecked('payment_method', 'credit_card'); ?>>
                                Credit Card
                            </label>
                        </div>
                        <div class="payment-option">
                            <label>
                                <input type="radio" name="payment_method" value="paypal" <?php echo isChecked('payment_method', 'paypal'); ?>>
                                PayPal
                            </label>
                        </div>
                    </div>
                    <?php echo showError('payment_method'); ?>
                    
                    <div id="credit-card-details" style="<?php echo oldValue('payment_method') === 'paypal' ? 'display:none;' : ''; ?>">
                        <div class="form-row">
                            <div class="form-group <?php echo hasError('card_name'); ?>">
                                <label for="card_name">Cardholder Name*</label>
                                <input type="text" id="card_name" name="card_name" value="<?php echo oldValue('card_name'); ?>">
                                <?php echo showError('card_name'); ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group <?php echo hasError('card_number'); ?>">
                                <label for="card_number">Card Number*</label>
                                <input type="text" id="card_number" name="card_number" value="<?php echo oldValue('card_number'); ?>">
                                <?php echo showError('card_number'); ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group <?php echo hasError('expiry'); ?>">
                                <label for="expiry">Expiry Date (MM/YY)*</label>
                                <input type="text" id="expiry" name="expiry" placeholder="MM/YY" value="<?php echo oldValue('expiry'); ?>">
                                <?php echo showError('expiry'); ?>
                            </div>
                            <div class="form-group <?php echo hasError('cvv'); ?>">
                                <label for="cvv">CVV*</label>
                                <input type="text" id="cvv" name="cvv" value="<?php echo oldValue('cvv'); ?>">
                                <?php echo showError('cvv'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
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
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h2><i class="fas fa-dumbbell"></i> FitZone</h2>
                    <p>Your journey to fitness excellence starts here. Join our community and transform your life.</p>
                </div>
                
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Classes</a></li>
                        <li><a href="#">Trainers</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Fitness Street, Workout City</p>
                    <p><i class="fas fa-phone"></i> (123) 456-7890</p>
                    <p><i class="fas fa-envelope"></i> info@fitzonefit.com</p>
                </div>
                
                <div class="footer-social">
                    <h3>Follow Us</h3>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 FitZone Gym. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Plan selection
            const planButtons = document.querySelectorAll('.select-plan');
            planButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const plan = this.getAttribute('data-plan');
                    const price = this.getAttribute('data-price');
                    const planName = this.closest('.plan-card').querySelector('h3').textContent;
                    
                    document.getElementById('plan-type').value = plan;
                    document.getElementById('base-price').value = price;
                    document.getElementById('plan-display-name').value = planName;
                    
                    document.getElementById('selected-plan-name').textContent = planName;
                    document.getElementById('summary-plan-name').textContent = planName;
                    document.getElementById('summary-base-price').textContent = '$' + price;
                    updateTotalPrice();
                    
                    document.getElementById('membership-plans').style.display = 'none';
                    document.getElementById('customization').classList.remove('hidden');
                    
                    // Scroll to top of customization section
                    window.scrollTo({
                        top: document.getElementById('customization').offsetTop - 100,
                        behavior: 'smooth'
                    });
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
                
                const priceMap = {
                    'personal_training': 30,
                    'nutrition_plan': 25,
                    'guest_passes': 15,
                    'towel_service': 10
                };
                
                document.querySelectorAll('input[name="addons[]"]:checked').forEach(checkbox => {
                    addonsPrice += priceMap[checkbox.value] || 0;
                });
                
                document.getElementById('summary-addons-price').textContent = '$' + addonsPrice;
                document.getElementById('summary-total-price').textContent = '$' + (basePrice + addonsPrice);
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