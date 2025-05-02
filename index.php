<?php
// Initialize session if not already started in navbar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and get role
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'member';

// Base URL for correct path resolution
$base_url = '';

require_once __DIR__ . '/config/db_config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitLife Gym - Your Path to Fitness Excellence</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <!-- Include Navbar Component -->
    <?php include_once __DIR__ . '/includes/components/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <?php if ($is_logged_in && $user_role === 'admin'): ?>
                    <h1>Admin Dashboard</h1>
                    <p>Welcome to the admin panel. Manage your gym's memberships, classes, trainers, and members from one central location.</p>
                    <div class="cta-buttons">
                        <a href="<?php echo $base_url; ?>admin/dashboard.php" class="btn">Go to Dashboard</a>
                        <a href="<?php echo $base_url; ?>admin/reports.php" class="btn btn-outline">View Reports</a>
                    </div>
                <?php elseif ($is_logged_in && $user_role === 'trainer'): ?>
                    <h1>Trainer Portal</h1>
                    <p>Welcome to your trainer portal. Manage your schedule, view your clients, and update your class information.</p>
                    <div class="cta-buttons">
                        <a href="<?php echo $base_url; ?>pages/trainer-dashboard.php" class="btn">Trainer Dashboard</a>
                        <a href="<?php echo $base_url; ?>pages/schedule.php" class="btn btn-outline">My Schedule</a>
                    </div>
                <?php elseif ($is_logged_in): ?>
                    <h1>Welcome to FitLife Gym</h1>
                    <p>Continue your fitness journey with us. Book a class, check your membership status, or explore our facilities.</p>
                    <a href="<?php echo $base_url; ?>pages/bookings.php" class="btn">Book a Class</a>
                <?php else: ?>
                    <h1>Transform Your Body, Transform Your Life</h1>
                    <p>Join FitLife Gym today and start your journey towards a healthier, stronger you. Our state-of-the-art facilities and expert trainers are here to help you achieve your fitness goals.</p>
                    <a href="<?php echo $base_url; ?>pages/register.php" class="btn">Start Your Journey</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Us</h2>
                <p>Experience the FitLife difference with our premium facilities and services</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Modern Equipment</h3>
                    <p>State-of-the-art fitness equipment designed to provide the most effective workout experience.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Expert Trainers</h3>
                    <p>Our certified personal trainers are committed to helping you achieve your fitness goals.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Diverse Classes</h3>
                    <p>From yoga to high-intensity interval training, we offer a wide range of classes for all fitness levels.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Membership Plans -->
    <section class="membership" id="membership">
        <div class="container">
            <div class="section-title">
                <h2>Membership Plans</h2>
                <p>Choose the plan that fits your fitness journey</p>
            </div>
            <div class="plan-cards">
                <div class="plan-card">
                    <h3>Basic</h3>
                    <div class="plan-price">$29<span>/month</span></div>
                    <ul class="plan-features">
                        <li>Access to gym floor</li>
                        <li>Basic equipment usage</li>
                        <li>Locker room access</li>
                        <li><del>Personal training sessions</del></li>
                        <li><del>Access to all classes</del></li>
                    </ul>
                    <a href="pages/memberships.php" class="btn btn-outline">Choose Plan</a>
                </div>
                <div class="plan-card featured">
                    <h3>Premium</h3>
                    <div class="plan-price">$59<span>/month</span></div>
                    <ul class="plan-features">
                        <li>Access to gym floor</li>
                        <li>Full equipment usage</li>
                        <li>Locker room access</li>
                        <li>2 Personal training sessions</li>
                        <li>Access to all classes</li>
                    </ul>
                    <a href="pages/memberships.php" class="btn">Choose Plan</a>
                </div>
                <div class="plan-card">
                    <h3>Elite</h3>
                    <div class="plan-price">$89<span>/month</span></div>
                    <ul class="plan-features">
                        <li>24/7 gym access</li>
                        <li>Premium equipment usage</li>
                        <li>Private locker</li>
                        <li>5 Personal training sessions</li>
                        <li>Priority class booking</li>
                    </ul>
                    <a href="pages/memberships.php" class="btn btn-outline">Choose Plan</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Classes Section -->
    <section class="classes" id="classes">
        <div class="container">
            <div class="section-title">
                <h2>Our Classes</h2>
                <p>Find the perfect workout routine with our diverse range of classes</p>
            </div>
            <div class="class-cards">
                <div class="class-card">
                    <div class="class-img" style="background-image: url('https://images.unsplash.com/photo-1518611012118-696072aa579a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="class-content">
                        <h3>HIIT Training</h3>
                        <p>High-intensity interval training designed to burn calories and improve cardiovascular health.</p>
                        <div class="class-meta">
                            <span><i class="far fa-clock"></i> 45 min</span>
                            <span><i class="fas fa-fire"></i> Advanced</span>
                        </div>
                    </div>
                </div>
                <div class="class-card">
                    <div class="class-img" style="background-image: url('https://images.unsplash.com/photo-1593810451137-9c2208093f18?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="class-content">
                        <h3>Yoga Flow</h3>
                        <p>Connect your breath with movement in this calming yet strengthening yoga practice.</p>
                        <div class="class-meta">
                            <span><i class="far fa-clock"></i> 60 min</span>
                            <span><i class="fas fa-fire"></i> Beginner</span>
                        </div>
                    </div>
                </div>
                <div class="class-card">
                    <div class="class-img" style="background-image: url('https://images.unsplash.com/photo-1548690312-e3b507d8c110?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80');"></div>
                    <div class="class-content">
                        <h3>Strength Training</h3>
                        <p>Build muscle and increase strength with our comprehensive weight training program.</p>
                        <div class="class-meta">
                            <span><i class="far fa-clock"></i> 50 min</span>
                            <span><i class="fas fa-fire"></i> Intermediate</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trainers Section -->
    <section class="trainers" id="trainers">
        <div class="container">
            <div class="section-title">
                <h2>Expert Trainers</h2>
                <p>Meet our team of certified fitness professionals</p>
            </div>
            <div class="trainer-cards">
                <div class="trainer-card">
                    <div class="trainer-img" style="background-image: url('https://images.unsplash.com/photo-1517836357463-d25dfeac3438?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="trainer-info">
                        <h3>John Doe</h3>
                        <p>Strength & Conditioning</p>
                        <div class="trainer-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
                <div class="trainer-card">
                    <div class="trainer-img" style="background-image: url('https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="trainer-info">
                        <h3>Sarah Johnson</h3>
                        <p>Yoga & Pilates</p>
                        <div class="trainer-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
                <div class="trainer-card">
                    <div class="trainer-img" style="background-image: url('https://images.unsplash.com/photo-1534367507873-d2d7e24c797f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="trainer-info">
                        <h3>Michael Chen</h3>
                        <p>HIIT & Functional Training</p>
                        <div class="trainer-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
                <div class="trainer-card">
                    <div class="trainer-img" style="background-image: url('https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="trainer-info">
                        <h3>Emily Rodriguez</h3>
                        <p>Cardio & Dance</p>
                        <div class="trainer-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta">
        <div class="container">
            <?php if ($is_logged_in && $user_role === 'admin'): ?>
                <h2>Manage Your Gym</h2>
                <p>Access all administrative tools and reports to run your gym efficiently.</p>
                <a href="<?php echo $base_url; ?>admin/dashboard.php" class="btn">Admin Dashboard</a>
            <?php elseif ($is_logged_in && $user_role === 'trainer'): ?>
                <h2>Ready for Your Next Session?</h2>
                <p>Check your schedule, prepare for your classes, and manage your clients effectively.</p>
                <a href="<?php echo $base_url; ?>pages/trainer-dashboard.php" class="btn">Trainer Dashboard</a>
            <?php elseif ($is_logged_in): ?>
                <h2>Ready to Push Your Limits?</h2>
                <p>Take your fitness to the next level with our expert trainers and specialized classes.</p>
                <a href="<?php echo $base_url; ?>pages/bookings.php" class="btn">Book a Class Now</a>
            <?php else: ?>
                <h2>Start Your Fitness Journey Today</h2>
                <p>Join FitLife Gym and transform your body and mind with our expert trainers and state-of-the-art facilities.</p>
                <a href="<?php echo $base_url; ?>pages/register.php" class="btn">Join Now</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Include Footer Component -->
    <?php include_once __DIR__ . '/includes/components/footer.php'; ?>
</body>

</html>
