<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an engineer   
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
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

// Get tasks data
$user_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$project_filter = isset($_GET['project_id']) ? (string)$_GET['project_id'] : 'all';

// Base query for tasks
$query = "
    SELECT 
        t.*,
        p.service as project_name,
        tc.category_name,
        u.name as client_name
    FROM tasks t
    JOIN task_assignees ta ON t.task_id = ta.task_id
    JOIN projects p ON t.project_id = p.project_id
    JOIN users u ON p.client_id = u.user_id
    LEFT JOIN task_categories tc ON t.category_id = tc.category_id
    WHERE ta.user_id = :user_id
";

// Add filters
$params = ['user_id' => $user_id];

if ($status_filter !== 'all') {
    $query .= " AND t.status = :status";
    $params['status'] = $status_filter;
}

if ($project_filter !== 'all') {
    $query .= " AND t.project_id = :project_id";
    $params['project_id'] = $project_filter;
}

// Add ordering
$query .= " ORDER BY 
    CASE 
        WHEN t.status = 'pending' THEN 1
        WHEN t.status = 'in_progress' THEN 2
        ELSE 3
    END,
    t.due_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get projects for filter
$stmt = $pdo->prepare("
    SELECT DISTINCT p.project_id, p.service as project_name
    FROM tasks t
    JOIN task_assignees ta ON t.task_id = ta.task_id
    JOIN projects p ON t.project_id = p.project_id
    WHERE ta.user_id = ?
    ORDER BY p.service ASC
");
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks | Engineer Dashboard</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body id="engineerTasksPage">
    <div class="engineer-dashboard-wrapper">
        <?php include 'engineer_header.php'; ?>
        
        <div class="engineer-main-content" <?php echo (!isset($_SESSION['pin_verified']) && !isset($_SESSION['needs_pin_setup'])) ? 'style="display: none;"' : ''; ?>>
            <div class="container-fluid px-4">
                <!-- My Tasks Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Tasks</h5>
                        <div class="d-flex gap-2">
                            <select class="form-select" id="statusFilter" name="status" style="width: auto;">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                            <select class="form-select" id="projectFilter" name="project_id" style="width: auto;">
                                <option value="all">All Projects</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo htmlspecialchars($project['project_id']); ?>" 
                                            <?php echo $project_filter === (string)$project['project_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 35%">Task</th>
                                        <th style="width: 20%">Project</th>
                                        <th style="width: 15%">Due Date</th>
                                        <th style="width: 15%">Status</th>
                                        <th style="width: 15%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-tasks fa-2x mb-3 text-muted"></i>
                                            <p class="mb-0 text-muted">No tasks found</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($tasks as $task): 
                                            $days_until_due = floor((strtotime($task['due_date']) - time()) / (60 * 60 * 24));
                                            $is_urgent = $days_until_due <= 2 && $task['status'] !== 'completed';
                                        ?>
                                        <tr data-task-id="<?php echo $task['task_id']; ?>" data-description="<?php echo htmlspecialchars($task['description']); ?>" data-category="<?php echo htmlspecialchars($task['category_name']); ?>">
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium <?php echo $is_urgent ? 'text-danger' : ''; ?>">
                                                        <?php echo htmlspecialchars($task['task_name']); ?>
                                                    </span>
                                                    <small class="text-muted text-truncate" style="max-width: 300px;" 
                                                           title="<?php echo htmlspecialchars($task['description']); ?>">
                                                        <?php echo htmlspecialchars($task['description']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span><?php echo htmlspecialchars($task['project_name']); ?></span>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($task['client_name']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span><?php echo date('M j, Y', strtotime($task['due_date'])); ?></span>
                                                    <small class="<?php echo $is_urgent ? 'text-danger' : 'text-muted'; ?>">
                                                        <?php
                                                        if ($days_until_due === 0) {
                                                            echo 'Due today';
                                                        } elseif ($days_until_due < 0) {
                                                            echo abs($days_until_due) . ' days overdue';
                                                        } else {
                                                            echo $days_until_due . ' days left';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $task['status'] === 'completed' ? 'bg-success' : 
                                                         ($task['status'] === 'in_progress' ? 'bg-primary' : 'bg-warning'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewTaskDetails(<?php echo $task['task_id']; ?>, this)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Assign New Task Section -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Assign New Task</h5>
                    </div>
                    <div class="card-body">
                        <form id="assignTaskForm" class="row g-3">
                            <!-- Project Selection -->
                            <div class="col-md-6">
                                <label for="projectSelect" class="form-label">Project</label>
                                <select class="form-select" id="projectSelect" required>
                                    <option value="">Select Project</option>
                                    <?php
                                    // Get projects where the Engineer is assigned
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT DISTINCT 
                                                p.project_id, 
                                                p.service as project_name, 
                                                u.name as client_name
                                            FROM projects p
                                            INNER JOIN project_assignees pa ON p.project_id = pa.project_id
                                            INNER JOIN users u ON p.client_id = u.user_id
                                            WHERE pa.user_id = :user_id 
                                            AND p.status != 'completed'
                                            ORDER BY p.created_at DESC
                                        ");

                                        $stmt->execute(['user_id' => $_SESSION['user_id']]);
                                        $assigned_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (empty($assigned_projects)) {
                                            echo '<option disabled>No projects assigned</option>';
                                        } else {
                                            foreach ($assigned_projects as $project) {
                                                printf(
                                                    '<option value="%s">%s (%s)</option>',
                                                    htmlspecialchars($project['project_id']),
                                                    htmlspecialchars($project['project_name']),
                                                    htmlspecialchars($project['client_name'])
                                                );
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error in engineer/tasks.php: " . $e->getMessage());
                                        echo '<option disabled>Error loading projects: ' . htmlspecialchars($e->getMessage()) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Category Selection -->
                            <div class="col-md-6">
                                <label for="categorySelect" class="form-label">Category</label>
                                <select class="form-select" id="categorySelect" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT category_id, category_name FROM task_categories");
                                    $stmt->execute();
                                    while ($category = $stmt->fetch()) {
                                        echo "<option value='" . htmlspecialchars($category['category_id']) . "'>" . 
                                             htmlspecialchars($category['category_name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Task Name -->
                            <div class="col-md-12">
                                <label for="taskName" class="form-label">Task Name</label>
                                <input type="text" class="form-control" id="taskName" required>
                            </div>

                            <!-- Task Description -->
                            <div class="col-md-12">
                                <label for="taskDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="taskDescription" rows="3"></textarea>
                            </div>

                            <!-- Due Date -->
                            <div class="col-md-6">
                                <label for="dueDate" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="dueDate" required min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <!-- Assignees -->
                            <div class="col-md-12">
                                <label class="form-label">Assignees</label>
                                <div id="assigneesList">
                                    <!-- Selected assignees will be displayed here -->
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addAssigneeBtn">
                                    <i class="fas fa-plus"></i> Add Personnel
                                </button>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Assign Task</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Add Personnel Modal -->
                <div class="modal fade" id="addPersonnelModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Personnel</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="list-group" id="personnelList">
                                    <!-- Personnel list will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskDetailsModalLabel">
                        <i class="fas fa-clipboard-list me-2"></i>
                        <span id="taskName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Alert container -->
                    <div id="alertContainer"></div>
                    
                    <!-- Task Information -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Category:</div>
                                <div class="col-md-9" id="taskCategory">N/A</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Description:</div>
                                <div class="col-md-9" id="taskDescription">No description available</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Project:</div>
                                <div class="col-md-9" id="projectName"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Client:</div>
                                <div class="col-md-9" id="clientName"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Status and Actions -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center mb-3">
                                <div class="col-md-3 text-muted">Status:</div>
                                <div class="col-md-9" id="taskStatus"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Due Date:</div>
                                <div class="col-md-9" id="taskDueDate"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Time Left:</div>
                                <div class="col-md-9" id="taskDaysLeft"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Check-in:</div>
                                <div class="col-md-9" id="checkInTime">Not checked in yet</div>
                            </div>
                            <!-- Add Team Members Completion Status -->
                            <div class="row mb-3">
                                <div class="col-md-3 text-muted">Team Members:</div>
                                <div class="col-md-9">
                                    <div id="teamMembersStatus" class="list-group list-group-flush">
                                        <!-- Team members will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Work Pictures -->
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Work Pictures</h6>
                            <div id="workPictures" class="mb-3">No pictures uploaded yet</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="file" id="pictureInput" multiple accept="image/*" style="display: none;">
                    <button type="button" class="btn btn-primary" id="checkInBtn" onclick="handleCheckIn()">
                        <i class="fas fa-clock me-2"></i>Check In
                    </button>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('pictureInput').click()">
                        <i class="fas fa-camera me-2"></i>Upload Pictures
                    </button>
                    <button type="button" class="btn btn-warning" id="completeTaskBtn" onclick="completeTask()">
                        <i class="fas fa-check-circle me-2"></i>Complete Task
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initializeTaskPage();
        initializeAssignTaskForm();
        
        // Add click handlers for view buttons
        document.querySelectorAll('.btn-outline-primary').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const taskId = this.closest('tr').dataset.taskId;
                viewTaskDetails(taskId, this);
            });
        });
    });

    function initializeTaskPage() {
        // Handle status filter change
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                window.location.href = `tasks.php?status=${this.value}&project_id=<?php echo $project_filter; ?>`;
            });
        }

        // Handle project filter change
        const projectFilter = document.getElementById('projectFilter');
        if (projectFilter) {
            projectFilter.addEventListener('change', function() {
                window.location.href = `tasks.php?status=<?php echo $status_filter; ?>&project_id=${this.value}`;
            });
        }

        // Initialize task details modal
        taskDetailsModal = new bootstrap.Modal(document.getElementById('taskDetailsModal'));

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Handle picture input change
        document.getElementById('pictureInput').addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadPictures();
            }
        });
    }

    // Move these to the global scope, before initializeAssignTaskForm
    let projectMembers = [];
    let selectedAssignees = new Set();

    // Update the updateAssigneesList function
    function updateAssigneesList() {
        const assigneesList = document.getElementById('assigneesList');
        if (selectedAssignees.size === 0) {
            assigneesList.innerHTML = '<p class="text-muted mb-0">No personnel assigned</p>';
            return;
        }

        assigneesList.innerHTML = Array.from(selectedAssignees).map(userId => {
            const member = findMemberById(userId);
            return `
                <div class="badge bg-primary me-2 mb-2 p-2">
                    ${member.name}
                    <button type="button" class="btn-close btn-close-white ms-2" 
                            data-user-id="${userId}"></button>
                </div>
            `;
        }).join('');

        // Add click event listeners to all remove buttons
        assigneesList.querySelectorAll('.btn-close').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                removeAssignee(userId);
            });
        });
    }

    function initializeAssignTaskForm() {
        const projectSelect = document.getElementById('projectSelect');
        const categorySelect = document.getElementById('categorySelect');
        const addAssigneeBtn = document.getElementById('addAssigneeBtn');
        const assignTaskForm = document.getElementById('assignTaskForm');
        
        // Initialize the personnel modal
        const personnelModal = new bootstrap.Modal(document.getElementById('addPersonnelModal'));

        // Handle project selection change
        projectSelect.addEventListener('change', function() {
            if (this.value) {
                // Enable and load categories
                categorySelect.disabled = false;
                categorySelect.innerHTML = '<option value="">Loading categories...</option>';
                
                fetch(`../api/tasks.php?action=categories&project_id=${this.value}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            categorySelect.innerHTML = '<option value="">Select Category</option>';
                            
                            // Sort categories by created_at
                            const sortedCategories = data.categories.sort((a, b) => {
                                return new Date(a.created_at) - new Date(b.created_at);
                            });

                            // Find the first incomplete category
                            const firstIncompleteIndex = sortedCategories.findIndex(cat => cat.status !== 'completed');
                            
                            sortedCategories.forEach((category, index) => {
                                const option = document.createElement('option');
                                option.value = category.category_id;
                                option.textContent = category.category_name;
                                
                                // If category is completed, disable it
                                if (category.status === 'completed') {
                                    option.disabled = true;
                                    option.classList.add('text-muted');
                                    option.textContent += ' (Completed)';
                                }
                                // If category is in progress but not the first incomplete one, disable it
                                else if (index > firstIncompleteIndex) {
                                    option.disabled = true;
                                    option.classList.add('text-muted');
                                    option.textContent += ' (Locked)';
                                }
                                
                                categorySelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => console.error('Error loading categories:', error));

                // Clear existing assignees
                selectedAssignees.clear();
                updateAssigneesList();
            } else {
                categorySelect.disabled = true;
                categorySelect.innerHTML = '<option value="">Select Project First</option>';
            }
        });

        // Handle add assignee button click
        addAssigneeBtn.addEventListener('click', function() {
            const projectId = projectSelect.value;
            if (!projectId) {
                alert('Please select a project first');
                return;
            }

            // Update the fetch URL to point to the correct endpoint
            fetch(`../api/tasks.php?action=project_members&project_id=${projectId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.personnel)) {  // Changed from data.members to data.personnel
                        // Update the global projectMembers array
                        projectMembers = data.personnel;  // Changed from data.members to data.personnel
                        const personnelList = document.getElementById('personnelList');
                        
                        if (personnelList) {
                            personnelList.innerHTML = '';

                            if (projectMembers.length === 0) {
                                personnelList.innerHTML = '<div class="list-group-item text-muted">No personnel assigned to this project</div>';
                            } else {
                                projectMembers.forEach(member => {
                                    const isSelected = selectedAssignees.has(member.user_id);
                                    const item = document.createElement('a');
                                    item.href = '#';
                                    item.className = `list-group-item list-group-item-action d-flex justify-content-between align-items-center ${isSelected ? 'active' : ''}`;
                                    item.innerHTML = `
                                        <div>
                                            <strong>${member.name}</strong>
                                            <small class="d-block text-muted">${member.role}</small>
                                        </div>
                                        <i class="fas ${isSelected ? 'fa-check-circle' : 'fa-plus-circle'}"></i>
                                    `;

                                    item.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        const userId = member.user_id;
                                        if (isSelected) {
                                            selectedAssignees.delete(userId);
                                        } else {
                                            selectedAssignees.add(userId);
                                        }
                                        updateAssigneesList();
                                        personnelModal.hide();
                                    });

                                    personnelList.appendChild(item);
                                });
                            }
                            
                            personnelModal.show();
                        }
                    } else {
                        throw new Error('Invalid data format received from server');
                    }
                })
                .catch(error => {
                    console.error('Error loading project members:', error);
                    alert('Error loading project members. Please try again.');
                });
        });

        // Handle form submission
        assignTaskForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                project_id: document.getElementById('projectSelect').value,
                category_id: document.getElementById('categorySelect').value,
                task_name: document.getElementById('taskName').value,
                description: document.getElementById('taskDescription').value,
                due_date: document.getElementById('dueDate').value,
                assignees: Array.from(selectedAssignees),
                created_by: <?php echo $_SESSION['user_id']; ?>  // Add creator ID
            };

            // Validate form data
            if (!formData.project_id) {
                alert('Please select a project');
                return;
            }
            if (!formData.category_id) {
                alert('Please select a category');
                return;
            }
            if (!formData.task_name.trim()) {
                alert('Please enter a task name');
                return;
            }
            if (!formData.due_date) {
                alert('Please select a due date');
                return;
            }
            if (formData.assignees.length === 0) {
                alert('Please assign at least one person to the task');
                return;
            }

            // Debug: Log the data being sent
            console.log('Form Data:', formData);

            // Send to assign_task.php which will handle both creation and assignment
            fetch('api/assign_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Server error occurred');
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    alert('Task assigned successfully!');
                    assignTaskForm.reset();
                    selectedAssignees.clear();
                    updateAssigneesList();
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to assign task');
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                alert('Error assigning task: ' + error.message);
            });
        });

        // Add this new code for date input validation
        const dueDateInput = document.getElementById('dueDate');
        
        // Set minimum date to today
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        dueDateInput.setAttribute('min', todayStr);
        
        // Add event listener to validate date selection
        dueDateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time part for accurate date comparison
            
            if (selectedDate < today) {
                alert('Please select a future date');
                this.value = todayStr;
            }
        });
    }

    // Update the removeAssignee function
    function removeAssignee(userId) {
        selectedAssignees.delete(userId);
        updateAssigneesList();
    }

    // Update the findMemberById function to handle cases where member is not found
    function findMemberById(userId) {
        const member = projectMembers.find(m => m.user_id === userId);
        return member || { user_id: userId, name: 'Unknown Member' };
    }

    function handleCheckIn() {
        const taskId = document.querySelector('#taskDetailsModal').dataset.taskId;
        const button = document.getElementById('checkInBtn');
        
        // Disable button to prevent double submission
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking in...';

        // Updated the fetch URL to the correct path
        fetch('../engineer/api/check_in.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                task_id: taskId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update check-in time display
                document.getElementById('checkInTime').textContent = data.check_in_time;
                // Keep button disabled after successful check-in
                button.innerHTML = '<i class="fas fa-check me-2"></i>Checked In';
                showAlert('success', 'Successfully checked in!');
            } else {
                // Re-enable button on error
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-clock me-2"></i>Check In';
                showAlert('error', data.message || 'Failed to check in');
            }
        })
        .catch(error => {
            console.error('Check-in error:', error);
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-clock me-2"></i>Check In';
            showAlert('error', 'Error checking in. Please try again.');
        });
    }

    function uploadPictures() {
        const taskId = document.querySelector('#taskDetailsModal').dataset.taskId;
        const fileInput = document.getElementById('pictureInput');
        const formData = new FormData();
        
        for (let file of fileInput.files) {
            formData.append('pictures[]', file);
        }
        formData.append('task_id', taskId);

        fetch('api/upload_pictures.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Get the current pictures container
                const picturesList = document.getElementById('workPictures');
                const currentPictures = picturesList.querySelectorAll('.picture-container');
                
                // If there are existing pictures, keep them
                if (currentPictures.length > 0 && picturesList.innerHTML !== 'No pictures uploaded yet') {
                    // Add new pictures to the existing ones
                    const newPicturesHtml = data.pictures.map(pic => `
                        <div class="picture-container">
                            <div class="picture-wrapper">
                                <img src="${pic.url}" alt="Work proof" class="img-thumbnail">
                                <button type="button" class="btn btn-danger btn-sm delete-picture" 
                                        onclick="deletePicture('${pic.url}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">${pic.uploaded_at}</small>
                        </div>
                    `).join('');
                    picturesList.insertAdjacentHTML('beforeend', newPicturesHtml);
                } else {
                    // If no existing pictures, update the entire list
                    updatePicturesList(data.pictures);
                }
                
                // Clear the file input
                fileInput.value = '';
                showAlert('success', 'Pictures uploaded successfully!');
            } else {
                showAlert('error', data.message || 'Failed to upload pictures');
            }
        })
        .catch(error => {
            showAlert('error', 'Error uploading pictures: ' + error.message);
        });
    }

    function updatePicturesList(pictures) {
        const picturesList = document.getElementById('workPictures');
        if (pictures && pictures.length > 0) {
            picturesList.innerHTML = pictures.map(pic => `
                <div class="picture-container">
                    <div class="picture-wrapper">
                        <img src="${pic.url}" alt="Work proof" class="img-thumbnail">
                        <button type="button" class="btn btn-danger btn-sm delete-picture" 
                                onclick="deletePicture('${pic.url}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <small class="text-muted d-block mt-1">${pic.uploaded_at}</small>
                </div>
            `).join('');
        } else {
            picturesList.innerHTML = 'No pictures uploaded yet';
        }
    }

    function deletePicture(pictureUrl) {
        if (!confirm('Are you sure you want to delete this picture?')) {
            return;
        }

        const taskId = document.querySelector('#taskDetailsModal').dataset.taskId;
        
        fetch('api/delete_picture.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                task_id: taskId,
                picture_url: pictureUrl
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the picture from the display
                const pictureElement = document.querySelector(`img[src="${pictureUrl}"]`).closest('.picture-container');
                if (pictureElement) {
                    pictureElement.remove();
                }
                
                // Check if there are any pictures left
                const picturesList = document.getElementById('workPictures');
                if (!picturesList.querySelector('.picture-container')) {
                    picturesList.innerHTML = 'No pictures uploaded yet';
                }
                
                showAlert('success', 'Picture deleted successfully!');
            } else {
                showAlert('error', data.message || 'Failed to delete picture');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showAlert('error', 'Error deleting picture: ' + error.message);
        });
    }

    function completeTask() {
        const taskId = document.querySelector('#taskDetailsModal').dataset.taskId;
        
        fetch('api/complete_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                task_id: taskId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the team members status display
                if (data.assignees_status) {
                    updateTeamMembersStatus(data.assignees_status);
                }
                
                // If all team members have completed, update the task status
                if (data.all_completed) {
                    document.getElementById('completeTaskBtn').disabled = true;
                    document.getElementById('taskStatus').innerHTML = '<span class="badge bg-success">Completed</span>';
                }
                
                showAlert('success', data.message);
                
                // Refresh the task list if the task is fully completed
                if (data.all_completed) {
                    location.reload();
                }
            } else {
                showAlert('error', data.message || 'Failed to complete task');
            }
        })
        .catch(error => {
            showAlert('error', 'Error completing task: ' + error.message);
        });
    }

    function updateTeamMembersStatus(assignees) {
        const container = document.getElementById('teamMembersStatus');
        container.innerHTML = '';
        
        assignees.forEach(member => {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2';
            
            const nameSpan = document.createElement('span');
            nameSpan.textContent = member.name;
            
            const statusBadge = document.createElement('span');
            statusBadge.className = `badge ${member.completed ? 'bg-success' : 'bg-warning'}`;
            statusBadge.textContent = member.completed ? 'Completed' : 'Pending';
            
            if (member.completed && member.completed_at) {
                const completedTime = new Date(member.completed_at).toLocaleString();
                statusBadge.title = `Completed at: ${completedTime}`;
            }
            
            item.appendChild(nameSpan);
            item.appendChild(statusBadge);
            container.appendChild(item);
        });
    }

    function viewTaskDetails(taskId, button) {
        // Get the task row
        const row = button.closest('tr');
        
        // Store task ID in modal
        const modal = document.getElementById('taskDetailsModal');
        modal.dataset.taskId = taskId;
        
        // Get task details from the row
        const taskName = row.querySelector('td:first-child .fw-medium').textContent.trim();
        const description = row.dataset.description;
        const projectName = row.querySelector('td:nth-child(2) span').textContent.trim();
        const clientName = row.querySelector('td:nth-child(2) small').textContent.trim();
        const category = row.dataset.category;
        const dueDate = row.querySelector('td:nth-child(3) span').textContent.trim();
        const daysLeftText = row.querySelector('td:nth-child(3) small').textContent.trim();
        const status = row.querySelector('td:nth-child(4) .badge').textContent.trim();
        
        // Update modal content
        document.getElementById('taskName').textContent = taskName;
        document.getElementById('taskDescription').textContent = description || 'No description available';
        document.getElementById('projectName').textContent = projectName;
        document.getElementById('clientName').textContent = clientName;
        document.getElementById('taskCategory').textContent = category || 'N/A';
        document.getElementById('taskStatus').innerHTML = `<span class="badge ${getStatusBadgeClass(status)}">${status}</span>`;
        document.getElementById('taskDueDate').textContent = dueDate;
        document.getElementById('taskDaysLeft').textContent = daysLeftText;

        // Update action buttons based on status
        const isCompleted = status.toLowerCase() === 'completed';
        document.getElementById('checkInBtn').disabled = isCompleted;
        document.getElementById('completeTaskBtn').disabled = isCompleted;
        
        // Reset pictures and check-in time
        document.getElementById('checkInTime').textContent = 'Not checked in yet';
        document.getElementById('workPictures').innerHTML = 'No pictures uploaded yet';
        
        // Fetch task details including team members status
        fetch(`api/get_task_details.php?task_id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.check_in_time) {
                        document.getElementById('checkInTime').textContent = data.check_in_time;
                        document.getElementById('checkInBtn').disabled = true;
                    }
                    if (data.pictures && data.pictures.length > 0) {
                        updatePicturesList(data.pictures);
                    }
                    if (data.assignees_status) {
                        updateTeamMembersStatus(data.assignees_status);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching task details:', error);
            });
        
        // Show the modal
        const modalInstance = new bootstrap.Modal(modal);
        
        // Add event listener for when modal is hidden
        modal.addEventListener('hidden.bs.modal', function () {
            // Remove any remaining backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            // Remove inline styles from body
            document.body.removeAttribute('style');
        }, { once: true }); // Use once: true to ensure the listener is removed after it's called
        
        modalInstance.show();
    }

    function getStatusBadgeClass(status) {
        switch(status.toLowerCase()) {
            case 'completed':
                return 'bg-success';
            case 'pending':
                return 'bg-warning text-dark';
            case 'in_progress':
                return 'bg-info text-dark';
            default:
                return 'bg-secondary';
        }
    }

    // Add this helper function if it doesn't exist
    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) return;

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.appendChild(alertDiv);

        // Remove the alert after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    </script>

    <style>
    /* Modal Styles */
    .modal-content {
        border-radius: 8px;
    }

    .modal-body {
        padding: 1.5rem;
    }

    /* Card Styles */
    .card {
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
    }

    .card:last-child {
        margin-bottom: 0;
    }

    /* Status Badge Styles */
    .badge {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
        border-radius: 20px;
    }

    /* Work Pictures Grid */
    #workPictures {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }

    .picture-container {
        position: relative;
        margin-bottom: 1rem;
    }

    .picture-wrapper {
        position: relative;
        display: inline-block;
    }

    .picture-wrapper img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        transition: filter 0.3s;
    }

    .picture-wrapper:hover img {
        filter: brightness(80%);
    }

    .delete-picture {
        position: absolute;
        top: 5px;
        right: 5px;
        padding: 0.25rem 0.5rem;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .picture-wrapper:hover .delete-picture {
        opacity: 1;
    }

    /* Button Styles */
    .btn {
        padding: 0.5rem 1rem;
        border-radius: 4px;
    }

    /* Alert Styles */
    #alertContainer {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060;
        max-width: 350px;
    }

    .alert {
        margin-bottom: 1rem;
        border-radius: 4px;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .row > div {
            margin-bottom: 0.5rem;
        }
        
        .modal-footer {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 1rem;
        }
        
        .modal-footer .btn {
            white-space: nowrap;
            margin-right: 0.5rem;
        }
    }

    /* Add these styles */
    #categorySelect option:disabled {
        color: #6c757d;
        font-style: italic;
        background-color: #e9ecef;
    }
    </style>
</body>
</html> 