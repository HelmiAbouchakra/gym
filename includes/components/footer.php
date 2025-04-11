<!-- Footer Component -->
<?php
// Get base URL for correct path resolution
$base_url = '';
?>
<footer id="contact">
    <div class="container">
        <div class="footer-content">
            <div class="footer-column">
                <h3>FitLife Gym</h3>
                <p>Your journey to a healthier, stronger you starts here. Join our fitness community today.</p>
            </div>
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo $base_url; ?>index.php#features">Features</a></li>
                    <li><a href="<?php echo $base_url; ?>index.php#membership">Memberships</a></li>
                    <li><a href="<?php echo $base_url; ?>index.php#classes">Classes</a></li>
                    <li><a href="<?php echo $base_url; ?>index.php#trainers">Trainers</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Classes</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo $base_url; ?>pages/classes.php">HIIT Training</a></li>
                    <li><a href="<?php echo $base_url; ?>pages/classes.php">Yoga Flow</a></li>
                    <li><a href="<?php echo $base_url; ?>pages/classes.php">Strength Training</a></li>
                    <li><a href="<?php echo $base_url; ?>pages/classes.php">Cardio Kickboxing</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Us</h3>
                <ul class="footer-links">
                    <li><i class="fas fa-map-marker-alt"></i> 123 Fitness Street, Gym City</li>
                    <li><i class="fas fa-phone"></i> (123) 456-7890</li>
                    <li><i class="fas fa-envelope"></i> info@fitlifegym.com</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> FitLife Gym. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- JavaScript -->
<script>
    // Sticky header
    window.addEventListener('scroll', function() {
        const header = document.querySelector('header');
        header.classList.toggle('scrolled', window.scrollY > 50);
    });

    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
</script> 