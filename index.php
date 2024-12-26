<?php
session_start();
require_once 'dbconnect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equalizer Builders Technologies Corporation</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- AOS CSS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-4 fw-bold mb-4">Building Tomorrow's Infrastructure Today</h1>
                    <p class="lead mb-4">Equalizer Builders Technologies Corporation delivers excellence in civil/architectural, and electro-mechanical services.</p>
                    <a href="<?php echo isset($_SESSION['logged_in']) ? '#book-appointment' : 'login.php?redirect=appointment'; ?>" 
                       class="btn btn-primary btn-lg">Book Appointment Now</a>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="images/landing-picture.jpg" alt="Engineering Excellence" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5" data-aos="fade-up">Our Services</h2>
            <div class="row g-4">
                <!-- Civil/Architectural -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card service-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-building service-icon mb-3"></i>
                            <h3 class="card-title">Civil/Architectural</h3>
                            <p class="card-text">
                            Specializing in civil and architectural works, including interior finishes, custom furniture, office 
                            partitions, and preventive maintenance.</p>
                            <ul class="list-unstyled">
                                <li>Interior Finishes and Furnishing</li>
                                <li>Install of Open Plan Office Partitions</li>
                                <li>Preventive Maintenance</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Electrical -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card service-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-bolt service-icon mb-3"></i>
                            <h3 class="card-title">Electrical</h3>
                            <p class="card-text">
                            Electrical services covering installations, power systems, cabling, site monitoring, security, fire alarms, and preventive maintenance.</p>
                            <ul class="list-unstyled">
                                <li>Structured Cabling System</li>
                                <li>Security CCTV & Access Control System</li>
                                <li>Power Conditioners & Controls</li> 
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Mechanical -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card service-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-cogs service-icon mb-3"></i>
                            <h3 class="card-title">Mechanical</h3>
                            <p class="card-text">
                            Comprehensive mechanical services, including refrigeration and air conditioning systems, HVAC, fire suppression, generators, and preventive maintenance for various industries.</p>
                            <ul class="list-unstyled">
                                <li>Plan Design, Estimate, and Built Complete Refrigeration/Air Conditioning System</li>
                                <li>HVAC Application</li>
                                <li>Fire Sprinkler System</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Add this after your services section -->
    <section id="book-appointment" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5" data-aos="fade-up">Book an Appointment</h2>
            
            <?php if (!isset($_SESSION['logged_in'])): ?>
                <div class="text-center">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <h4 class="mb-4">Login Required</h4>
                            <p class="mb-4">Please login to your account to book an appointment.</p>
                            <a href="login.php?redirect=appointment" class="btn btn-primary">Login Now</a>
                            <p class="mt-3">Don't have an account? <a href="register.php">Create one</a></p>
                        </div>
                    </div>
                </div>
            <?php elseif ($_SESSION['role'] !== 'client'): ?>
                <div class="text-center">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <h4 class="mb-4">Client Access Only</h4>
                            <p>Only client accounts can book appointments.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <h4 class="mb-4">Ready to Book?</h4>
                            <p class="mb-4">Click below to start booking your appointment.</p>
                            <a href="book_appointment.php" class="btn btn-primary btn-lg">Book Appointment Now</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h2 class="mb-4">About EBTC</h2>
                    <p style="text-align: justify; hyphens: auto;" class="lead">
                        <strong>EQUALIZER Builders & Technologies Corporation</strong> is a design and construction group 
                        involved in civil / architectural and electro-mechanical services. The group is a spin-off from an
                        installation/installer and sales group involved in mechanical services, Equalizer Temperature & System 
                        Services and formerly from one of the prestigious Company in the installation of split type, package type, 
                        centralizes Air-conditioning and precision Air-conditioning units including of installations of Air-Duct 
                        System.
                    </p>
                    <p> “We design, build, and manage facilities that improve everydaylife”</p>
                    <div class="row g-4 mt-4">
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                <span>Years of Experience</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                <span>Certified Engineers</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                <span>Projects Completed</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                <span>Satisfied Clients</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="images/landing-picture1.jpg" alt="About EBTC" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5" data-aos="fade-up">Contact Us</h2>
            <div class="row g-4">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="card">
                        <div class="card-body">
                            <form>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" rows="5" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title mb-4">Get in Touch</h3>
                            <div class="d-flex mb-3">
                                <i class="fas fa-map-marker-alt text-primary me-3"></i>
                                <p class="mb-0">Block 44 Lot 44 Xyris Street, Pembo, Taguig City</p>
                            </div>
                            <div class="d-flex mb-3">
                                <i class="fas fa-phone text-primary me-3"></i>
                                <p class="mb-0">0949-439-4587 | 0968-861-0062</p>
                            </div>
                            <div class="d-flex mb-3">
                                <i class="fas fa-envelope text-primary me-3"></i>
                                <p class="mb-0">vojetss@yahoo.com</p>
                            </div>
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3223.3718150730974!2d121.06125809999999!3d14.5413186!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c90049f04a93%3A0x65c05ce9b9149410!2sEqualizer%20Builders%20and%20Technologies%20Corp.!5e1!3m2!1sen!2sph!4v1735195662249!5m2!1sen!2sph" width="500" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS JS for animations -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true
        });

        // Change navbar background on scroll
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').classList.add('navbar-scrolled');
            } else {
                document.querySelector('.navbar').classList.remove('navbar-scrolled');
            }
        });
    </script>
</body>
</html> 