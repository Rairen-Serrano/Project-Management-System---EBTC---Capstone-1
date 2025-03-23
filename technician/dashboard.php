<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an technician
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'technician') {
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
    <title>Technician Dashboard | EBTC PMS</title>
    
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
<body id="engineerDashboardPage" data-needs-pin-setup="<?php echo empty($user['pin_code']) ? 'true' : 'false'; ?>">
    <div class="engineer-dashboard-wrapper">
        <!-- Include Technician header -->
        <?php include 'technician_header.php'; ?>

    <!-- Main Content -->
        <div class="engineer-main-content" <?php echo !isset($_SESSION['pin_verified']) ? 'style="display: none;"' : ''; ?>>
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4 class="welcome-message">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h4>
                            <p class="mb-0">Here's your project overview for today</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card bg-success text-white stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Completed Tasks</h6>
                                    <h2 class="mb-0" id="completedTasks">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-white stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Pending Tasks</h6>
                                    <h2 class="mb-0" id="pendingTasks">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white stats-card">
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
            </div>

            <!-- Main Dashboard Content -->
            <div class="row">
                <!-- Make the main content full width -->
                <div class="col-12">
                    <!-- Latest Tasks Card -->
                    <div class="card dashboard-card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Latest Tasks</h5>
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary active" data-status="all">All</button>
                                    <button class="btn btn-sm btn-outline-primary" data-status="in_progress">In Progress</button>
                                    <button class="btn btn-sm btn-outline-primary" data-status="pending">Pending</button>
                                    <button class="btn btn-sm btn-outline-primary" data-status="completed">Completed</button>
                                </div>
                                <a href="tasks.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-list me-1"></i>View All Tasks
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive table-fixed-height">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Task Name</th>
                                            <th>Project</th>
                                            <th>Description</th>
                                            <th>Assigned Date</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Fetch tasks assigned to the current technician
                                        $stmt = $pdo->prepare("
                                            SELECT 
                                                t.task_id,
                                                t.task_name,
                                                t.description,
                                                t.due_date,
                                                t.status,
                                                p.project_id,
                                                p.service,
                                                ta.assigned_date
                                            FROM tasks t
                                            JOIN task_assignees ta ON t.task_id = ta.task_id
                                            JOIN projects p ON t.project_id = p.project_id
                                            WHERE ta.user_id = ?
                                            ORDER BY ta.assigned_date DESC
                                            LIMIT 3
                                        ");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($tasks as $task) {
                                            // Determine status class
                                            $statusClass = match($task['status']) {
                                                'completed' => 'bg-success',
                                                'in_progress' => 'bg-primary',
                                                'pending' => 'bg-warning',
                                                default => 'bg-secondary'
                                            };

                                            // Calculate days remaining
                                            $dueDate = new DateTime($task['due_date']);
                                            $today = new DateTime();
                                            $isOverdue = $today > $dueDate && $task['status'] !== 'Completed';
                                            ?>
                                            <tr class="task-row" data-status="<?php echo strtolower(str_replace(' ', '_', $task['status'])); ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)) . '...'; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="project_details.php?id=<?php echo $task['project_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($task['service']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($task['description']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></td>
                                                <td>
                                                    <div class="<?php echo $isOverdue ? 'text-danger' : ''; ?>">
                                                        <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                                        <?php if ($isOverdue): ?>
                                                            <span class="badge bg-danger">Overdue</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo $task['status']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                <?php if (empty($tasks)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No tasks assigned yet.</p>
                                    </div>
                                <?php endif; ?>
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
                    <h5 class="modal-title">Technician PIN Verification</h5>
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
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dashboard functionality
        if (typeof handleEngineerDashboardPage === 'function') {
            handleEngineerDashboardPage();
        }

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

        // Load Dashboard Data
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
        // Fetch and update dashboard data
        fetch('../api/technician/dashboard_data.php')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const data = result.data;
                    // Update statistics (removed activeTasks)
                    document.getElementById('completedTasks').textContent = data.completed_tasks;
                    document.getElementById('pendingTasks').textContent = data.pending_tasks;
                    document.getElementById('totalProjects').textContent = data.total_projects;
                } else {
                    console.error('Failed to load dashboard data:', result.message);
                }
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
            });
    }

    </script>
</body>
</html> 