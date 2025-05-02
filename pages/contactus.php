<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - PowerFit Gym</title>
    <link rel="stylesheet" href="assets/css/contact_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="animate-title">CONTACT US</h1>
            <p class="animate-subtitle">We're here to power your fitness journey</p>
        </div>
    </header>
    
    <div class="container">
        <div class="contact-flex">
            <div class="contact-info">
                <div class="info-card" data-aos="fade-right">
                    <div class="icon-container">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3>Phone</h3>
                    <p>Main: (555) 123-4567</p>
                    <p>Membership: (555) 123-4568</p>
                    <p>Personal Training: (555) 123-4569</p>
                </div>
                
                <div class="info-card" data-aos="fade-right" data-aos-delay="100">
                    <div class="icon-container">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Email</h3>
                    <p>info@powerfitgym.com</p>
                    <p>membership@powerfitgym.com</p>
                    <p>training@powerfitgym.com</p>
                </div>
                
                <div class="info-card" data-aos="fade-right" data-aos-delay="200">
                    <div class="icon-container">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Location</h3>
                    <p>123 Fitness Avenue</p>
                    <p>Healthville, CA 90210</p>
                    <p>United States</p>
                </div>
            </div>
            
            <div class="contact-form" data-aos="fade-left">
                <h2>Send Us a Message</h2>
                <p>Fill out this form and we'll get back to you as soon as possible.</p>
                <form>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" placeholder="Your name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" placeholder="Your email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" placeholder="Your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <select id="subject" required>
                            <option value="" disabled selected>Select a subject</option>
                            <option value="membership">Membership Inquiry</option>
                            <option value="training">Personal Training</option>
                            <option value="classes">Group Classes</option>
                            <option value="feedback">Feedback</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" placeholder="Type your message here" required></textarea>
                    </div>
                    
                    <button type="submit" class="pulse-button">Send Message</button>
                </form>
            </div>
        </div>
        
        <div class="map-container" data-aos="zoom-in">
            <div class="map-overlay">
                <div class="map-pin">
                    <i class="fas fa-map-pin"></i>
                </div>
                <div class="map-text">
                    <h3>PowerFit Gym</h3>
                    <p>123 Fitness Avenue, Healthville</p>
                </div>
            </div>
            <img src="https://via.placeholder.com/1200x400" alt="Map location of PowerFit Gym">
        </div>
        
        <div class="schedule" data-aos="fade-up">
            <h2>Our Hours</h2>
            <div class="schedule-grid">
                <div class="day-card">
                    <h3>Monday</h3>
                    <p>5:00 AM - 11:00 PM</p>
                </div>
                <div class="day-card">
                    <h3>Tuesday</h3>
                    <p>5:00 AM - 11:00 PM</p>
                </div>
                <div class="day-card">
                    <h3>Wednesday</h3>
                    <p>5:00 AM - 11:00 PM</p>
                </div>
                <div class="day-card">
                    <h3>Thursday</h3>
                    <p>5:00 AM - 11:00 PM</p>
                </div>
                <div class="day-card">
                    <h3>Friday</h3>
                    <p>5:00 AM - 10:00 PM</p>
                </div>
                <div class="day-card">
                    <h3>Saturday</h3>
                    <p>6:00 AM - 9:00 PM</p>
                </div>
                <div class="day-card">
                    <h3>Sunday</h3>
                    <p>7:00 AM - 8:00 PM</p>
                </div>
            </div>
        </div>
        
        <div class="newsletter" data-aos="fade-up">
            <h2>Subscribe to Our Newsletter</h2>
            <p>Stay updated with our latest fitness tips, special offers, and gym news.</p>
            <form class="newsletter-form">
                <input type="email" placeholder="Your email address" required>
                <button type="submit" class="pulse-button">Subscribe</button>
            </form>
        </div>
        
        <h2 class="social-title" data-aos="fade-up">Connect With Us</h2>
        <div class="social-links" data-aos="fade-up" data-aos-delay="100">
            <a href="#" class="social-icon">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="social-icon">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="#" class="social-icon">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="#" class="social-icon">
                <i class="fab fa-youtube"></i>
            </a>
            <a href="#" class="social-icon">
                <i class="fab fa-linkedin-in"></i>
            </a>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 PowerFit Gym. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            AOS.init({
                duration: 800,
                once: false
            });
        });
    </script>
</body>
</html>