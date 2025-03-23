<?php
session_start();

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

// Services Data Structure
$services = [
    'Civil / Architectural' => [
        'Interior Finishes and Furnishing',
        'Fabrication of Furniture, Cabinets and Shelves',
        'Supply and install of open plan office partitions',
        'Wall Partitions, Ceilings, Floorings, Doors and Windows',
        'Preventive maintenance and other related Civil / Architectural Works'
    ],
    'Electrical' => [
        'Installation of Electrical Facilities',
        'Structured Cabling Systems',
        'Power Distribution & Monitoring',
        'Site Monitoring System',
        'Security CCTV & Access Control System',
        'Fire alarm and Detection System',
        'Power Conditioners & Controls',
        'Preventive Maintenance and other related Electrical Works'
    ],
    'Mechanical Services and Capabilities' => [
        'Plan Design, estimate and built complete refrigeration / air conditioning facility for: food beverage industry, breweries, cold storage/ distribution centers, dairy /ice cream plants, meat process plants, sea foods/ fish process plants and other cooling applications.',
        'Computer Grade Precision Air Conditioning',
        'Process Chillers',
        'HVAC Application',
        'Fire Suppression System',
        'Fire Sprinkler Systems',
        'Generators',
        'Preventive Maintenance and other mechanical works'
    ]
];

// Function to get booked time slots for a specific date
function getBookedTimeSlots($pdo, $date) {
    $stmt = $pdo->prepare("
        SELECT TIME_FORMAT(preferred_time, '%H:%i') as booked_time 
        FROM appointments 
        WHERE preferred_date = ? 
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
                                                        <?php foreach ($categoryServices as $index => $service): ?>
                                                            <tr class="service-row">
                                                                <td style="width: 50px;">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input service-checkbox" 
                                                                               type="checkbox"
                                                                               name="service[]" 
                                                                               value="<?php echo htmlspecialchars($category . ': ' . $service); ?>"
                                                                               id="service-<?php echo $category . '-' . $index; ?>"
                                                                               data-service-name="<?php echo htmlspecialchars($service); ?>">
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <label class="service-name" for="service-<?php echo $category . '-' . $index; ?>">
                                                                        <?php echo htmlspecialchars($service); ?>
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
            
            // Add loading indicator
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            submitButton.disabled = true;
            
            grecaptcha.ready(function() {
                grecaptcha.execute('6LcVU_wqAAAAANKqzxrZ-qBG1FFxOHhJd97KJSWD', {action: 'submit'})
                .then(function(token) {
                    document.getElementById('recaptcha_token').value = token;
                    console.log('reCAPTCHA token generated:', token.substring(0, 20) + '...');
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
</body>
</html> 