<?php
session_start();
require_once 'dbconnect.php';  // Changed from 'config/database.php' to 'dbconnect.php'

// Redirect if not logged in
if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php?redirect=appointment');
    exit();
}

// Redirect if not a client
if ($_SESSION['role'] !== 'client') {
    header('Location: index.php#book-appointment');
    exit();
}

// Check for pending appointments count
$pending_count_query = "
    SELECT COUNT(*) as pending_count 
    FROM appointments 
    WHERE client_id = ? 
    AND status = 'pending'
";
$stmt = $pdo->prepare($pending_count_query);
$stmt->execute([$_SESSION['user_id']]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

if ($pending_count >= 3) {
    $_SESSION['error_message'] = "You have reached the maximum number of pending appointments (3). Please wait for your current appointments to be confirmed or cancelled before booking new ones.";
    header('Location: client/dashboard.php');
    exit;
}

// Services Data Structure
$services = [
    'Civil / Architectural' => [
        'Interior Finishes and Furnishing' => [
            'description' => 'We help design and furnish your space with modern furniture and decorative elements.',
            'icon' => 'fa-couch'
        ],
        'Fabrication of Furniture, Cabinets and Shelves' => [
            'description' => 'We build custom furniture, cabinets, and storage solutions that fit your space perfectly.',
            'icon' => 'fa-hammer'
        ],
        'Supply and install of open plan office partitions' => [
            'description' => 'We set up modern office spaces with flexible dividers to create an efficient workspace.',
            'icon' => 'fa-table-cells-large'
        ],
        'Wall Partitions, Ceilings, Floorings, Doors and Windows' => [
            'description' => 'We handle all interior work including walls, ceilings, floors, doors, and windows.',
            'icon' => 'fa-door-open'
        ],
        'Preventive maintenance and other related Civil / Architectural Works' => [
            'description' => 'We provide regular checkups and maintenance to keep your building in top shape.',
            'icon' => 'fa-screwdriver-wrench'
        ]
    ],
    'Electrical' => [
        'Installation of Electrical Facilities' => [
            'description' => 'We install all electrical systems safely and efficiently.',
            'icon' => 'fa-plug'
        ],
        'Structured Cabling Systems' => [
            'description' => 'We set up reliable network cables for internet, phones, and other communications.',
            'icon' => 'fa-network-wired'
        ],
        'Power Distribution & Monitoring' => [
            'description' => 'We help manage and track your power usage for better efficiency.',
            'icon' => 'fa-bolt'
        ],
        'Site Monitoring System' => [
            'description' => 'We install systems to help you monitor and secure your property.',
            'icon' => 'fa-tv'
        ],
        'Security CCTV & Access Control System' => [
            'description' => 'We set up security cameras and door access systems to keep your property safe.',
            'icon' => 'fa-camera'
        ],
        'Fire alarm and Detection System' => [
            'description' => 'We install fire alarms that quickly detect and warn about fire hazards.',
            'icon' => 'fa-fire'
        ],
        'Power Conditioners & Controls' => [
            'description' => 'We install equipment that keeps your power supply stable and protects your devices.',
            'icon' => 'fa-charging-station'
        ],
        'Preventive Maintenance and other related Electrical Works' => [
            'description' => 'We provide regular checkups to keep your electrical systems safe and working well.',
            'icon' => 'fa-wrench'
        ]
    ],
    'Mechanical Services and Capabilities' => [
        'Complete Refrigeration / Air Conditioning Facility' => [
            'description' => 'We install and maintain cooling systems for all types of spaces.',
            'icon' => 'fa-temperature-low'
        ],
        'Computer Grade Precision Air Conditioning' => [
            'description' => 'We provide special cooling systems for computer rooms and servers.',
            'icon' => 'fa-server'
        ],
        'Process Chillers' => [
            'description' => 'We install industrial cooling equipment for manufacturing needs.',
            'icon' => 'fa-snowflake'
        ],
        'HVAC Application' => [
            'description' => 'We handle all heating, cooling, and ventilation needs for your building.',
            'icon' => 'fa-fan'
        ],
        'Fire Suppression System' => [
            'description' => 'We install systems that quickly put out fires to protect your property.',
            'icon' => 'fa-fire-extinguisher'
        ],
        'Fire Sprinkler Systems' => [
            'description' => 'We install automatic sprinklers throughout your building for fire protection.',
            'icon' => 'fa-spray-can'
        ],
        'Generators' => [
            'description' => 'We provide backup power systems to keep you running during power outages.',
            'icon' => 'fa-power-off'
        ],
        'Preventive Maintenance and other mechanical works' => [
            'description' => 'We regularly check and maintain all mechanical equipment to prevent problems.',
            'icon' => 'fa-tools'
        ]
    ]
];

// Function to get booked time slots for a specific date
function getBookedTimeSlots($pdo, $date) {
    $stmt = $pdo->prepare("
        SELECT TIME_FORMAT(time, '%H:%i') as booked_time 
        FROM appointments 
        WHERE date = ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$date]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - EBTC</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- AOS CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js?render=6LcVU_wqAAAAANKqzxrZ-qBG1FFxOHhJd97KJSWD"></script>

    <style>
    .services-table-container {
        max-width: 100%;
        margin: 0 auto;
    }

    .category-section {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .category-header {
        background: #f8f9fa;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .category-title {
        margin: 0;
        color: #235347;
    }

    .service-row {
        transition: all 0.3s ease;
    }

    .service-row:hover {
        background-color: #f8f9fa;
    }

    .service-name {
        cursor: pointer;
        display: block;
        padding: 0.5rem 0;
    }

    .service-description {
        font-size: 0.9rem;
        padding-left: 1.8rem;
    }

    .form-check-input:checked + label {
        color: #235347;
    }

    .service-checkbox {
        transform: scale(1.2);
    }
    </style>
</head>
<body id="appointmentPage">
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/EBTC_logo.png" alt="EBTC Logo" height="120">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['role'] === 'client'): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                                <li><a class="dropdown-item" href="client/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="client/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="client/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light me-2">Sign In</a>
                        <a href="register.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Add this script right after the navbar -->
    <script>
        // Change navbar background on scroll
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').classList.add('navbar-scrolled');
            } else {
                document.querySelector('.navbar').classList.remove('navbar-scrolled');
            }
        });
    </script>

    <div class="container appointment-container" style="margin-top: 100px;">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Book an Appointment</h2>
                        
                        <form id="appointmentForm" method="POST" action="process_appointment.php">
                            <!-- Add a hidden input for the recaptcha token -->
                            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                            
                            <!-- Service Selection -->
                            <div class="mb-5">
                                <h2 class="text-center mb-4">Our Services</h2>
                                
                                <div class="services-table-container">
                                    <?php 
                                    // Define icons for each category
                                    $categoryIcons = [
                                        'Civil / Architectural' => 'fa-building',
                                        'Electrical' => 'fa-bolt',
                                        'Mechanical Services and Capabilities' => 'fa-gears'
                                    ];
                                    
                                    foreach ($services as $category => $categoryServices): 
                                    ?>
                                        <div class="category-section mb-4" data-aos="fade-up">
                                            <div class="category-header">
                                                <h4 class="category-title">
                                                    <i class="fas <?php echo $categoryIcons[$category] ?? 'fa-folder'; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($category); ?>
                                                </h4>
                                            </div>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-hover services-table">
                                                    <tbody>
                                                        <?php foreach ($categoryServices as $serviceName => $serviceData): ?>
                                                            <tr class="service-row">
                                                                <td style="width: 50px;">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input service-checkbox" 
                                                                               type="checkbox"
                                                                               name="service[]" 
                                                                               value="<?php echo htmlspecialchars($category . ': ' . $serviceName); ?>"
                                                                               id="service-<?php echo $category . '-' . $serviceName; ?>"
                                                                               data-service-name="<?php echo htmlspecialchars($serviceName); ?>">
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <label class="service-name" for="service-<?php echo $category . '-' . $serviceName; ?>">
                                                                        <i class="fas <?php echo $serviceData['icon']; ?> me-2 text-primary"></i>
                                                                        <strong><?php echo htmlspecialchars($serviceName); ?></strong>
                                                                        <p class="text-muted mb-0 mt-1 service-description">
                                                                            <?php echo htmlspecialchars($serviceData['description']); ?>
                                                                        </p>
                                                                    </label>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Selected Services Summary -->
                                    <div id="selectedServicesDisplay" class="selected-services-summary mt-4" style="display: none;">
                                        <div class="alert alert-success">
                                            <h6 class="mb-3">
                                                <i class="fas fa-check-circle me-2"></i>Selected Services
                                            </h6>
                                            <ul id="selectedServicesList" class="list-unstyled mb-0">
                                                <!-- Selected services will be listed here -->
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add this right before the Date and Time Selection section -->
                            <div class="mb-4">
                                <label class="form-label">Appointment Type</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="appointment_type" id="face-to-face" value="face-to-face" required>
                                        <label class="form-check-label" for="face-to-face">
                                            <i class="fas fa-user-group me-2"></i>Face-to-Face
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="appointment_type" id="online" value="online" required>
                                        <label class="form-check-label" for="online">
                                            <i class="fas fa-video me-2"></i>Online Meeting
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Add this div that will show only when online is selected -->
                            <div id="online-meeting-info" class="mb-4" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Meeting link will be sent to your email after confirmation.
                                </div>
                            </div>

                            <!-- Date and Time Selection -->
                            <div class="row mb-4 appointment-datetime">
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Date</label>
                                    <input type="date" class="form-control" name="date" required 
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Time</label>
                                    <select class="form-control" name="time" required>
                                        <option value="">Select a date first</option>
                                    </select>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">Book Appointment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Custom JS -->
    <script src="js/script.js"></script>

    <!-- Add this JavaScript to handle date and time restrictions -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Date restrictions
        const dateInput = document.querySelector('input[name="date"]');
        const timeSelect = document.querySelector('select[name="time"]');
        
        if (dateInput && timeSelect) {
            // Set min date to today
            dateInput.min = new Date().toISOString().split('T')[0];
            
            // Function to update available time slots
            function updateAvailableTimeSlots() {
                const selectedDate = dateInput.value;
                if (!selectedDate) return;
                
                // Clear existing options
                timeSelect.innerHTML = '<option value="">Select a time</option>';
                
                // Add time options from 8:30 AM to 4:30 PM
                for (let hour = 8; hour <= 16; hour++) {
                    let startMinute = hour === 8 ? 30 : 0;
                    let endMinute = hour === 16 ? 30 : 60;
                    
                    for (let minute = startMinute; minute < endMinute; minute += 30) {
                        const currentTime = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                        const nextTime = minute === 30 ? 
                            `${(hour + 1).toString().padStart(2, '0')}:00` : 
                            `${hour.toString().padStart(2, '0')}:30`;
                            
                        const timeDisplay = formatTimeRange(currentTime, nextTime);
                        const option = document.createElement('option');
                        option.value = currentTime;
                        option.text = timeDisplay;
                        timeSelect.appendChild(option);
                    }
                }

                // Check for booked slots after populating times
                checkBookedTimeSlots(selectedDate);
            }

            // Function to format time display
            function formatTimeRange(start, end) {
                function formatTime(time) {
                    const [hours, minutes] = time.split(':');
                    const hour = parseInt(hours);
                    const period = hour >= 12 ? 'PM' : 'AM';
                    const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                    return `${displayHour}:${minutes}${period}`;
                }
                return `${formatTime(start)} - ${formatTime(end)}`;
            }

            // Function to check booked time slots
            async function checkBookedTimeSlots(date) {
                try {
                    const response = await fetch('check_available_times.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ date: date })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.bookedTimes) {
                        // Disable booked time slots
                        Array.from(timeSelect.options).forEach(option => {
                            if (data.bookedTimes.includes(option.value)) {
                                option.disabled = true;
                                option.classList.add('text-muted');
                                // Check if "(Booked)" is not already added
                                if (!option.text.includes('(Booked)')) {
                                    option.text += ' (Booked)';
                                }
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error checking booked times:', error);
                }
            }

            // Update time slots when date changes
            dateInput.addEventListener('change', updateAvailableTimeSlots);
            
            // Add weekday validation
            dateInput.addEventListener('input', function() {
                const selected = new Date(this.value);
                const day = selected.getDay();
                
                if (day === 0 || day === 6) { // 0 is Sunday, 6 is Saturday
                    alert('Please select a date between Monday and Friday');
                    this.value = '';
                    timeSelect.innerHTML = '<option value="">Select a date first</option>';
                } else {
                    updateAvailableTimeSlots();
                }
            });
        }
    });
    </script>

    <!-- Add this JavaScript before the closing </body> tag -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('appointmentForm');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            submitButton.disabled = true;
            
            grecaptcha.ready(function() {
                grecaptcha.execute('6LcVU_wqAAAAANKqzxrZ-qBG1FFxOHhJd97KJSWD', {action: 'submit'})
                    .then(function(token) {
                        document.getElementById('recaptcha_token').value = token;
                        form.submit();
                    })
                    .catch(function(error) {
                        console.error('reCAPTCHA error:', error);
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                        alert('Error verifying reCAPTCHA. Please try again.');
                    });
            });
        });
    });
    </script>

    <!-- Add this before the closing </body> tag -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const appointmentTypeInputs = document.querySelectorAll('input[name="appointment_type"]');
        const onlineMeetingInfo = document.getElementById('online-meeting-info');

        appointmentTypeInputs.forEach(input => {
            input.addEventListener('change', function() {
                onlineMeetingInfo.style.display = this.value === 'online' ? 'block' : 'none';
            });
        });
    });
    </script>
</body>
</html> 