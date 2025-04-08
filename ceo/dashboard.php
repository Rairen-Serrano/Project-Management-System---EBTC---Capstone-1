<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an CEO
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'ceo') {
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ../admin_login.php');
    exit;
}

// Check if user has a PIN code set
$stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Only show PIN setup if user doesn't have a PIN and hasn't set one in this session
if (empty($user['pin_code']) && !isset($_SESSION['pin_verified'])) {
    $_SESSION['needs_pin_setup'] = true;
}
// Only show PIN verification if user has a PIN but hasn't verified in this session
else if (!empty($user['pin_code']) && !isset($_SESSION['pin_verified'])) {
    $_SESSION['needs_pin_verification'] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEO Dashboard | EBTC PMS</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>

</head>
<body id="ceoDashboardPage" data-needs-pin-setup="<?php echo empty($user['pin_code']) ? 'true' : 'false'; ?>">
    <div class="engineer-dashboard-wrapper">
        <!-- Include CEO header -->
        <?php include 'ceo_header.php'; ?>

    <!-- Main Content -->
        <div class="engineer-main-content" <?php echo !isset($_SESSION['pin_verified']) ? 'style="display: none;"' : ''; ?>>
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4 class="welcome-message">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h4>
                            <p class="mb-0">Here's your company overview</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card bg-success text-white stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Projects</h6>
                                    <h2 class="mb-0" id="totalProjects">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-project-diagram fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Employees</h6>
                                    <h2 class="mb-0" id="totalEmployees">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Active Projects</h6>
                                    <h2 class="mb-0" id="activeProjects">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Clients</h6>
                                    <h2 class="mb-0" id="totalClients">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-user-tie fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Statistics Overview
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statisticsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Project Status Distribution
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="projectStatusChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activities Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Activities
                                </h5>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-primary btn-sm active" data-filter="all">All</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-filter="task_update">Tasks</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-filter="project_update">Projects</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="activitiesTable">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Activity Type</th>
                                            <th>Details</th>
                                            <th>Status</th>
                                            <th>Actor</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Activities will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Verification Modal -->
    <div class="modal fade" id="pinVerificationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">CEO PIN Verification</h5>
                </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Please enter your 4-digit PIN code to access the dashboard.</p>
                    <div class="pin-input-group">
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                    </div>
                    <div id="pinError" class="text-danger text-center mt-2" style="display: none;">
                        Invalid PIN. Please try again.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="verifyPinBtn">Verify PIN</button>
                    <a href="../logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Setup Modal -->
    <div class="modal fade" id="pinSetupModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Up Your PIN</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        For security purposes, you need to set up a 4-digit PIN code. This PIN will be required each time you access the dashboard.
                    </div>
                    <form id="pinSetupForm">
                        <div class="mb-4">
                            <label class="form-label">Enter New PIN</label>
                            <div class="pin-input-group">
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm PIN</label>
                            <div class="pin-input-group">
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div id="pinSetupError" class="text-danger text-center mt-2" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="savePinBtn">Save PIN</button>
                    <a href="../logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Update the table height to use more vertical space */
    .table-fixed-height {
        max-height: 600px; /* Increased height since we removed Projects */
        overflow-y: auto;
    }

    /* Ensure smooth scrolling */
    .table-fixed-height::-webkit-scrollbar {
        width: 8px;
    }

    .table-fixed-height::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-fixed-height::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .table-fixed-height::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Keep headers visible while scrolling */
    .table thead th {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1;
    }

    .table-sm td {
        padding: 0.5rem;
        vertical-align: middle;
    }

    .text-truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.25em 0.5em;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show PIN modal if needed
        const needsPinSetup = document.body.dataset.needsPinSetup === 'true';
        if (needsPinSetup) {
            const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
            pinSetupModal.show();
        } else if (!<?php echo isset($_SESSION['pin_verified']) ? 'true' : 'false'; ?>) {
            // Remove any existing event listeners
            const oldVerifyBtn = document.getElementById('verifyPinBtn');
            if (oldVerifyBtn) {
                const newVerifyBtn = oldVerifyBtn.cloneNode(true);
                oldVerifyBtn.parentNode.replaceChild(newVerifyBtn, oldVerifyBtn);
            }

            // Show verification modal
            const pinVerificationModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
            pinVerificationModal.show();

            // Add single event listener
            const verifyPinBtn = document.getElementById('verifyPinBtn');
            let isVerifying = false; // Flag to prevent multiple submissions

            if (verifyPinBtn) {
                verifyPinBtn.addEventListener('click', function verifyPin() {
                    // Prevent multiple submissions
                    if (isVerifying) return;
                    isVerifying = true;

                    const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
                    const pin = Array.from(pinInputs).map(input => input.value).join('');
                    const errorElement = document.getElementById('pinError');
                    
                    if (pin.length !== 4) {
                        errorElement.textContent = 'Please enter a 4-digit PIN';
                        errorElement.style.display = 'block';
                        isVerifying = false;
                        return;
                    }

                    console.log('Sending PIN verification request...'); // Debug log
                    
                    fetch('../api/auth/verify_pin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ pin }),
                        credentials: 'same-origin' // Include cookies
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Verification response:', data); // Debug log
                        
                        if (data.success) {
                            // Remove the event listener to prevent multiple calls
                            verifyPinBtn.removeEventListener('click', verifyPin);
                            
                            // Hide the modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('pinVerificationModal'));
                            modal.hide();
                            
                            // Clean up
                            if (document.querySelector('.modal-backdrop')) {
                                document.querySelector('.modal-backdrop').remove();
                            }
                            document.body.classList.remove('modal-open');
                            document.body.style.removeProperty('padding-right');
                            document.body.style.removeProperty('overflow');
                            
                            // Show main content and reload
                            document.querySelector('.engineer-main-content').style.display = 'block';
                            window.location.reload();
                        } else {
                            errorElement.textContent = data.message || 'Invalid PIN. Please try again.';
                            errorElement.style.display = 'block';
                            
                            if (data.lockout_duration) {
                                verifyPinBtn.disabled = true;
                                startLockoutCountdown(data.lockout_duration);
                            }
                            
                            pinInputs.forEach(input => input.value = '');
                            pinInputs[0].focus();
                        }
                    })
                    .catch(error => {
                        console.error('Verification error:', error); // Debug log
                        errorElement.textContent = 'An error occurred. Please try again.';
                        errorElement.style.display = 'block';
                    })
                    .finally(() => {
                        isVerifying = false;
                    });
                });
            }
        }

        function startLockoutCountdown(duration) {
            const verifyBtn = document.getElementById('verifyPinBtn');
            const errorDiv = document.getElementById('pinError');
            let timeLeft = duration;

            const countdownInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                errorDiv.textContent = `Account locked. Please try again in ${minutes}:${seconds.toString().padStart(2, '0')} minutes`;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    verifyBtn.disabled = false;
                    errorDiv.textContent = 'You can now try again.';
                    setTimeout(() => {
                        errorDiv.style.display = 'none';
                    }, 3000);
                }
                timeLeft--;
            }, 1000);
        }

        // Load Dashboard Data directly
        loadDashboardData();

        // Task filtering
        const filterButtons = document.querySelectorAll('.btn-group button');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                const status = this.dataset.status;
                const tasks = document.querySelectorAll('.task-row');
                
                tasks.forEach(task => {
                    if (status === 'all' || task.dataset.status === status) {
                        task.style.display = '';
                    } else {
                        task.style.display = 'none';
                    }
                });
            });
        });

        // Add PIN Input Handling
        function setupPinInputs(containerSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) return;

            const inputs = container.querySelectorAll('.pin-input');
            
            inputs.forEach((input, index) => {
                // Handle keyup for number input and auto-focus
                input.addEventListener('keyup', function(e) {
                    // Move to next input if value is entered
                    if (this.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    
                    // Handle backspace to previous input
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
            });
        }

        // Initialize PIN inputs for both verification and setup modals
        setupPinInputs('#pinVerificationModal .pin-input-group');
        setupPinInputs('#pinSetupModal .pin-input-group');
    });

    function loadDashboardData() {
        console.log('Loading dashboard data...');

        fetch('../api/ceo/dashboard_data.php')
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                console.log('Received data:', result);

                if (!result) {
                    console.error('Result is null or undefined');
                    return;
                }

                if (result.success === true && typeof result.data === 'object') {
                    const data = result.data;
                    
                    // Update statistics
                    updateStatistics(data);
                    
                    // Create/Update charts
                    createStatisticsChart(data);
                    createProjectStatusChart(data);
                    
                    // Update activities table
                    if (Array.isArray(data.recent_activities)) {
                        updateActivitiesTable(data.recent_activities);
                    }
                } else {
                    throw new Error(result.message || 'Invalid data format received from server');
                }
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
                showError(error.message);
            });
    }

    function updateStatistics(data) {
        const statsMapping = {
            'totalProjects': 'total_projects',
            'totalEmployees': 'total_employees',
            'activeProjects': 'active_projects',
            'totalClients': 'total_clients'
        };

        Object.entries(statsMapping).forEach(([elementId, dataKey]) => {
            const element = document.getElementById(elementId);
            if (element) {
                const value = data[dataKey] || '0';
                element.textContent = value;
            }
        });
    }

    function createStatisticsChart(data) {
        const ctx = document.getElementById('statisticsChart');
        if (!ctx) return;

        try {
            // Safely destroy existing chart
            if (window.statisticsChart && typeof window.statisticsChart.destroy === 'function') {
                window.statisticsChart.destroy();
            }

            // Clear any existing chart
            ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);

            window.statisticsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Total Projects', 'Total Employees', 'Active Projects', 'Total Clients'],
                    datasets: [{
                        data: [
                            data.total_projects || 0,
                            data.total_employees || 0,
                            data.active_projects || 0,
                            data.total_clients || 0
                        ],
                        backgroundColor: [
                            '#28a745', // success
                            '#17a2b8', // info
                            '#ffc107', // warning
                            '#dc3545'  // danger
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    animation: {
                        duration: 0 // Disable animations to prevent storage errors
                    }
                }
            });
        } catch (error) {
            console.error('Error creating statistics chart:', error);
        }
    }

    function createProjectStatusChart(data) {
        const ctx = document.getElementById('projectStatusChart');
        if (!ctx) return;

        try {
            // Calculate project status distribution
            const totalProjects = data.total_projects || 0;
            const activeProjects = data.active_projects || 0;
            const completedProjects = totalProjects - activeProjects;

            // Safely destroy existing chart
            if (window.projectStatusChart && typeof window.projectStatusChart.destroy === 'function') {
                window.projectStatusChart.destroy();
            }

            // Clear any existing chart
            ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);

            window.projectStatusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Completed'],
                    datasets: [{
                        data: [activeProjects, completedProjects],
                        backgroundColor: ['#ffc107', '#28a745']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    animation: {
                        duration: 0 // Disable animations to prevent storage errors
                    }
                }
            });
        } catch (error) {
            console.error('Error creating project status chart:', error);
        }
    }

    function updateActivitiesTable(activities) {
        const tbody = document.querySelector('#activitiesTable tbody');
        if (!tbody) return;

        tbody.innerHTML = activities.map(activity => `
            <tr data-type="${activity.activity_type}">
                <td>${activity.project_name}</td>
                <td>
                    <span class="badge bg-${activity.activity_type === 'task_update' ? 'info' : 'primary'}">
                        ${activity.activity_type === 'task_update' ? 'Task' : 'Project'}
                    </span>
                </td>
                <td>
                    ${activity.activity_type === 'task_update' ? 
                        `Task "${activity.category_name}"` : 
                        'Project Update'}
                </td>
                <td>
                    <span class="badge bg-${getStatusColor(activity.status)}">
                        ${activity.status}
                    </span>
                </td>
                <td>
                    <small>${activity.actor_name}</small>
                    <br>
                    <span class="badge bg-secondary">${activity.actor_role}</span>
                </td>
                <td>
                    <small>${new Date(activity.activity_time).toLocaleString()}</small>
                </td>
            </tr>
        `).join('');

        // Add filter functionality
        const filterButtons = document.querySelectorAll('[data-filter]');
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                const filter = button.dataset.filter;
                
                // Update active button state
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                // Filter table rows
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(row => {
                    if (filter === 'all' || row.dataset.type === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    }

    function getStatusColor(status) {
        const colors = {
            'completed': 'success',
            'ongoing': 'warning',
            'pending': 'info',
            'cancelled': 'danger'
        };
        return colors[status.toLowerCase()] || 'secondary';
    }

    function showError(message) {
        const mainContent = document.querySelector('.engineer-main-content');
        if (mainContent) {
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger mt-3';
            errorAlert.textContent = message || 'Error loading dashboard data. Please try refreshing the page.';
            mainContent.prepend(errorAlert);
        }
    }
    </script>
</body>
</html> 