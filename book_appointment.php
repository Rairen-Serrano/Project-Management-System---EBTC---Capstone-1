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

    <div class="container appointment-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Book an Appointment</h2>
                        
                        <form id="appointmentForm" method="POST" action="process_appointment.php">
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
                                                                               name="services[]" 
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
                                    <input type="time" class="form-control" name="time" required>
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
        if (dateInput) {
            // Set min date to today
            dateInput.min = new Date().toISOString().split('T')[0];
            
            // Add event listener to validate weekdays
            dateInput.addEventListener('input', function() {
                const selected = new Date(this.value);
                const day = selected.getDay();
                
                if (day === 0 || day === 6) { // 0 is Sunday, 6 is Saturday
                    alert('Please select a date between Monday and Friday');
                    this.value = '';
                }
            });
        }

        // Time restrictions
        const timeInput = document.querySelector('input[name="time"]');
        if (timeInput) {
            const timeSelect = document.createElement('select');
            timeSelect.className = 'form-select';
            timeSelect.name = 'time';
            timeSelect.required = true;

            function formatTimeOption(hour, minute) {
                const period = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour > 12 ? hour - 12 : hour;
                return {
                    value: `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`,
                    text: `${displayHour}:${minute.toString().padStart(2, '0')}${period}`
                };
            }

            // Add time options from 9 AM to 3 PM with 30-minute intervals
            for (let hour = 9; hour <= 15; hour++) {
                for (let minute = 0; minute < 60; minute += 30) {
                    // Skip times after 3:00 PM
                    if (hour === 15 && minute > 0) continue;
                    
                    const time = formatTimeOption(hour, minute);
                    const nextTime = minute === 30 ? 
                        formatTimeOption(hour + 1, 0) : 
                        formatTimeOption(hour, 30);
                    
                    const option = document.createElement('option');
                    option.value = time.value;
                    option.text = `${time.text} - ${nextTime.text}`;
                    timeSelect.appendChild(option);
                }
            }

            // Replace the time input with the select
            timeInput.parentNode.replaceChild(timeSelect, timeInput);
        }
    });
    </script>
</body>
</html> 