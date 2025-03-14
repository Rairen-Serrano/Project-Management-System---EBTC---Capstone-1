<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a worker
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
    // Just redirect to login without clearing the session
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
    <title>Worker Dashboard | EBTC PMS</title>
    
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
<body id="workerDashboardPage" data-needs-pin-setup="<?php echo empty($user['pin_code']) ? 'true' : 'false'; ?>">
    <div class="worker-dashboard-wrapper">
        <!-- Include worker header -->
        <?php include 'worker_header.php'; ?>

        <!-- Main Content -->
        <div class="worker-main-content" <?php echo !isset($_SESSION['pin_verified']) ? 'style="display: none;"' : ''; ?>>
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4 class="welcome-message">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h4>
                            <p class="mb-0">Here's your task overview for today</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
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
                    <div class="card bg-success text-white">
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
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Projects Assigned</h6>
                                    <h2 class="mb-0" id="assignedProjects">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-project-diagram fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Tasks Table -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">My Tasks</h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary active" data-status="all">All</button>
                        <button class="btn btn-sm btn-outline-primary" data-status="pending">Pending</button>
                        <button class="btn btn-sm btn-outline-primary" data-status="completed">Completed</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Project</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch tasks assigned to the current worker
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        t.task_id,
                                        t.task_name,
                                        t.description,
                                        t.due_date,
                                        t.status,
                                        p.project_id,
                                        p.service as project_name,
                                        ta.assigned_date
                                    FROM tasks t
                                    JOIN task_assignees ta ON t.task_id = ta.task_id
                                    JOIN projects p ON t.project_id = p.project_id
                                    WHERE ta.user_id = ?
                                    ORDER BY t.due_date ASC
                                ");
                                $stmt->execute([$_SESSION['user_id']]);
                                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No tasks assigned yet.</p>
                                        </td>
                                    </tr>
                                <?php else:
                                    foreach ($tasks as $task):
                                        // Determine status class
                                        $statusClass = match($task['status']) {
                                            'completed' => 'bg-success',
                                            'pending' => 'bg-warning',
                                            default => 'bg-secondary'
                                        };

                                        // Calculate if task is overdue
                                        $dueDate = new DateTime($task['due_date']);
                                        $today = new DateTime();
                                        $isOverdue = $today > $dueDate && $task['status'] !== 'completed';
                                ?>
                                        <tr class="task-row" data-status="<?php echo strtolower($task['status']); ?>">
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)) . '...'; ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['project_name']); ?></td>
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
                                                    <?php echo ucfirst($task['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="viewTaskDetails(<?php echo $task['task_id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
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
                    <h5 class="modal-title">Worker PIN Verification</h5>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dashboard functionality
            loadWorkerDashboardData();
            
            // Show PIN modal if needed
            const needsPinSetup = document.body.dataset.needsPinSetup === 'true';
            if (needsPinSetup) {
                const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
                pinSetupModal.show();
                setupPinInputHandlers();
            } else if (!<?php echo isset($_SESSION['pin_verified']) ? 'true' : 'false'; ?>) {
                const pinVerificationModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
                pinVerificationModal.show();
                setupPinInputHandlers();
            }

            // Task filtering
            const filterButtons = document.querySelectorAll('.btn-group button');
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    filterTasks(this.dataset.status);
                });
            });

            loadDashboardStats();
            // Refresh stats every 5 minutes
            setInterval(loadDashboardStats, 300000);
        });

        function loadWorkerDashboardData() {
            // Implement dashboard data loading
            fetch('../api/worker/dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    updateDashboardStats(data);
                    updateTasksTable(data.tasks);
                })
                .catch(error => console.error('Error loading dashboard data:', error));
        }

        function filterTasks(status) {
            const rows = document.querySelectorAll('.task-row');
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Handle PIN input functionality
        function setupPinInputHandlers() {
            const pinInputs = document.querySelectorAll('.pin-input');
            
            pinInputs.forEach((input, index) => {
                // Move to next input after entering a digit
                input.addEventListener('input', function() {
                    if (this.value.length === 1) {
                        const nextInput = pinInputs[index + 1];
                        if (nextInput) nextInput.focus();
                    }
                });

                // Handle backspace
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value) {
                        const prevInput = pinInputs[index - 1];
                        if (prevInput) {
                            prevInput.focus();
                            prevInput.value = '';
                        }
                    }
                });
            });

            // Setup PIN verification handler
            const verifyPinBtn = document.getElementById('verifyPinBtn');
            if (verifyPinBtn) {
                verifyPinBtn.addEventListener('click', verifyPin);
            }

            // Setup PIN save handler
            const savePinBtn = document.getElementById('savePinBtn');
            if (savePinBtn) {
                savePinBtn.addEventListener('click', savePin);
            }
        }

        function verifyPin() {
            const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
            const pin = Array.from(pinInputs).map(input => input.value).join('');
            
            fetch('../api/auth/verify_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ pin })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    document.getElementById('pinError').style.display = 'block';
                    pinInputs.forEach(input => input.value = '');
                    pinInputs[0].focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('pinError').textContent = 'An error occurred. Please try again.';
                document.getElementById('pinError').style.display = 'block';
            });
        }

        function savePin() {
            const setupPins = Array.from(document.querySelectorAll('.setup-pin')).map(input => input.value).join('');
            const confirmPins = Array.from(document.querySelectorAll('.confirm-pin')).map(input => input.value).join('');
            
            if (setupPins !== confirmPins) {
                document.getElementById('pinSetupError').textContent = 'PINs do not match';
                document.getElementById('pinSetupError').style.display = 'block';
                return;
            }

            fetch('../api/auth/setup_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ pin: setupPins })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    document.getElementById('pinSetupError').textContent = data.message || 'Failed to set PIN';
                    document.getElementById('pinSetupError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('pinSetupError').textContent = 'An error occurred. Please try again.';
                document.getElementById('pinSetupError').style.display = 'block';
            });
        }

        function loadDashboardStats() {
            fetch('api/get_dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('pendingTasks').textContent = data.stats.pending_tasks;
                        document.getElementById('completedTasks').textContent = data.stats.completed_tasks;
                        document.getElementById('assignedProjects').textContent = data.stats.assigned_projects;
                    }
                })
                .catch(error => console.error('Error loading dashboard stats:', error));
        }
    </script>
</body>
</html> 