<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
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
    <title>Engineer Dashboard | EBTC PMS</title>
    
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
        <!-- Include engineer header -->
        <?php include 'engineer_header.php'; ?>

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
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Active Tasks</h6>
                                    <h2 class="mb-0" id="activeTasks">0</h2>
                                    <small>Tasks in progress</small>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-tasks fa-2x"></i>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
                <div class="card bg-warning text-white">
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
            <div class="col-md-3">
                <div class="card bg-info text-white">
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
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Current Tasks</h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary active" data-status="all">All</button>
                            <button class="btn btn-sm btn-outline-primary" data-status="in_progress">In Progress</button>
                            <button class="btn btn-sm btn-outline-primary" data-status="pending">Pending</button>
                            <button class="btn btn-sm btn-outline-primary" data-status="completed">Completed</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Task Name</th>
                                        <th>Project</th>
                                        <th>Assigned Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch tasks assigned to the current engineer
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
                                        ORDER BY t.due_date ASC
                                    ");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($tasks as $task) {
                                        // Determine status class
                                        $statusClass = match($task['status']) {
                                            'Completed' => 'bg-success',
                                            'In Progress' => 'bg-primary',
                                            'Pending' => 'bg-warning',
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
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="task_details.php?id=<?php echo $task['task_id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="update_task.php?id=<?php echo $task['task_id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Update Progress
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="addTaskNote(<?php echo $task['task_id']; ?>)">
                                                                <i class="fas fa-comment me-2"></i>Add Note
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
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

                <!-- Project Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Assigned Projects</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Assigned Date</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch projects assigned to the current engineer
                                    $stmt = $pdo->prepare("
                                        SELECT 
                                            p.project_id,
                                            p.service,
                                            p.start_date,
                                            p.end_date,
                                            p.status,
                                            pa.assigned_date
                                        FROM projects p
                                        JOIN project_assignees pa ON p.project_id = pa.project_id
                                        WHERE pa.user_id = ?
                                        ORDER BY p.end_date ASC
                                    ");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($projects as $project) {
                                        $projectStatusClass = match($project['status']) {
                                            'Completed' => 'bg-success',
                                            'In Progress' => 'bg-primary',
                                            'Pending' => 'bg-warning',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <tr>
                                            <td>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($project['service']); ?></h6>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($project['assigned_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($project['start_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($project['end_date'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $projectStatusClass; ?>">
                                                    <?php echo $project['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="project_details.php?id=<?php echo $project['project_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <?php if (empty($projects)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No projects assigned yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

                <!-- Right Sidebar Content -->
            <div class="col-md-4">
                    <div class="card mb-4">
                    <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="createNewTask()">
                                    <i class="fas fa-plus me-2"></i>Create New Task
                                </button>
                                <button class="btn btn-info" onclick="viewReports()">
                                    <i class="fas fa-chart-bar me-2"></i>View Reports
                                </button>
                                <button class="btn btn-success" onclick="scheduleInspection()">
                                    <i class="fas fa-clipboard-check me-2"></i>Schedule Inspection
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Upcoming Deadlines</h5>
                            <span class="badge bg-danger">Next 7 Days</span>
                        </div>
                        <div class="list-group list-group-flush" id="upcomingDeadlines">
                            <!-- Deadlines will be loaded dynamically -->
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Project Progress</h5>
                    </div>
                    <div class="card-body">
                            <div id="projectProgressList">
                                <!-- Project progress will be loaded dynamically -->
                            </div>
                            <canvas id="projectProgressChart" height="200"></canvas>
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
                    <h5 class="modal-title">Engineer PIN Verification</h5>
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
        if (typeof handleEngineerDashboardPage === 'function') {
            handleEngineerDashboardPage();
        }

        // Show PIN modal if needed
        const needsPinSetup = document.body.dataset.needsPinSetup === 'true';
        if (needsPinSetup) {
            const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
            pinSetupModal.show();
        } else if (!<?php echo isset($_SESSION['pin_verified']) ? 'true' : 'false'; ?>) {
            const pinVerificationModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
            pinVerificationModal.show();
        }

        // Handle PIN verification
        const verifyPinBtn = document.getElementById('verifyPinBtn');
        if (verifyPinBtn) {
            verifyPinBtn.addEventListener('click', function() {
                const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
                const pin = Array.from(pinInputs).map(input => input.value).join('');
                
                if (pin.length !== 4) {
                    document.getElementById('pinError').style.display = 'block';
                    return;
                }
                
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
                        // Hide the modal properly
                        const modal = bootstrap.Modal.getInstance(document.getElementById('pinVerificationModal'));
                        modal.hide();
                        
                        // Clean up modal artifacts
                        document.querySelector('.modal-backdrop').remove();
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                        document.body.style.removeProperty('overflow');
                        
                        // Show the main content
                        document.querySelector('.engineer-main-content').style.display = 'block';
                        
                        // Refresh the page to ensure everything is properly loaded
                        window.location.reload();
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
            });
        }

        // Initialize Charts
        initializeProjectProgressChart();
        initializeTimeline();
        
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
    });

    function initializeProjectProgressChart() {
        const ctx = document.getElementById('projectProgressChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Pending'],
                datasets: [{
                    data: [30, 50, 20],
                    backgroundColor: ['#28a745', '#007bff', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    function loadDashboardData() {
        // Fetch and update dashboard data
        fetch('../api/engineer/dashboard_data.php')
            .then(response => response.json())
            .then(data => {
                updateDashboardStats(data);
                updateTasksTable(data.tasks);
                updateDeadlines(data.deadlines);
                updateProjectProgress(data.projects);
            })
            .catch(error => console.error('Error loading dashboard data:', error));
    }

    function addTaskNote(taskId) {
        // Implement task note functionality
        // You can show a modal or redirect to a notes page
    }
    </script>
</body>
</html> 