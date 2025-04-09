<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    header('Location: ../admin_login.php');
    exit;
}

// Check if project_id is provided
if (!isset($_GET['project_id'])) {
    header('Location: projects.php');
    exit;
}

$project_id = $_GET['project_id'];

// Get project details
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.name as client_name,
        u.email as client_email,
        u.phone as client_phone,
        GROUP_CONCAT(DISTINCT CONCAT(up.name, '|', up.role, '|', up.email) SEPARATOR '||') as assigned_personnel
    FROM projects p
    JOIN users u ON p.client_id = u.user_id
    LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
    LEFT JOIN users up ON pa.user_id = up.user_id
    WHERE p.project_id = ?
    GROUP BY p.project_id
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: projects.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management | Manager Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>

    <style>
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .task-header .project-info {
            flex: 1;
            min-width: 200px;
            margin-right: 1rem;
        }

        .task-header .buttons {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
    </style>

</head>
<body>
    <div class="manager-dashboard-wrapper">
        <?php include 'manager_header.php'; ?>
        
        <div class="manager-main-content">
            <!-- Page Header -->
            <div class="task-header">
                <div class="project-info">
                    <h3 class="mb-1">Task Management</h3>
                    <p class="text-muted mb-0">
                        Project: <span title="<?php echo htmlspecialchars($project['service']); ?>"><?php echo htmlspecialchars($project['service']); ?></span>
                        <br>
                        Client: <?php echo htmlspecialchars($project['client_name']); ?>
                    </p>
                </div>
                <div class="buttons">
                    <button class="btn btn-info" onclick="showArchivedTasks()">
                        <i class="fas fa-archive me-2"></i>Archived Tasks
                    </button>
                    <a href="projects.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Projects
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Project Progress -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-chart-line me-2"></i>Project Progress
                            </h6>
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar" role="progressbar" id="projectProgress" 
                                     style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    0%
                                </div>
                            </div>
                            <div class="row text-center">
                                <div class="col-4">
                                    <h5 id="totalTasks">0</h5>
                                    <small class="text-muted">Total Tasks</small>
                                </div>
                                <div class="col-4">
                                    <h5 id="completedTasks">0</h5>
                                    <small class="text-muted">Completed</small>
                                </div>
                                <div class="col-4">
                                    <h5 id="pendingTasks">0</h5>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Task Categories -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Task Categories
                                </h6>
                            </div>
                            <div class="row" id="categoryList">
                                <!-- Categories will be loaded dynamically -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Task Assignment -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-user-check me-2"></i>Task Assignment
                                </h6>
                                <button class="btn btn-sm btn-primary" id="addTaskBtn">
                                    <i class="fas fa-plus me-1"></i>Assign New Task
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table" id="taskTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 20%">Task</th>
                                            <th style="width: 15%">Category</th>
                                            <th style="width: 30%">Assigned To</th>
                                            <th style="width: 15%">Due Date</th>
                                            <th style="width: 10%">Status</th>
                                            <th style="width: 10%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Tasks will be loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-tasks me-2"></i>Assign New Task
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Client Information Card -->
                    <div class="card mb-4 bg-light">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-user me-2"></i>Client Information
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Name:</strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($project['client_name']); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Email:</strong></p>
                                    <p class="text-muted">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($project['client_email']); ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Phone:</strong></p>
                                    <p class="text-muted">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($project['client_phone']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Task Form -->
                    <form id="addTaskForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Task Name</label>
                                    <input type="text" class="form-control" id="taskName" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" id="taskCategory" required>
                                        <!-- Categories will be loaded dynamically -->
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Due Date</label>
                                    <input type="date" class="form-control" id="taskDueDate" required 
                                           min="<?php echo $project['start_date']; ?>"
                                           max="<?php echo $project['end_date']; ?>" 
                                           data-project-start="<?php echo $project['start_date']; ?>"
                                           data-project-end="<?php echo $project['end_date']; ?>">
                                    <div class="form-text text-muted">
                                        Project duration: <?php echo date('M d, Y', strtotime($project['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" id="taskDescription" rows="3" style="height: 120px;"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assign To</label>
                                    <div class="d-flex align-items-start gap-2">
                                        <button type="button" class="btn btn-outline-primary rounded-circle mt-1" id="showAssigneeList">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <div class="flex-grow-1">
                                            <div id="selectedAssignees" class="mb-2 d-flex flex-wrap gap-2">
                                                <!-- Selected assignees will be shown here -->
                                            </div>
                                            <div id="assigneeListContainer" class="list-group shadow-sm" style="display: none; max-height: 200px; overflow-y: auto;">
                                                <!-- Project personnel list will be loaded here -->
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="taskAssignees" name="assignees" required>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Task
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-tasks me-2"></i>
                        <span id="modalTaskName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Client Information Card -->
                    <div class="card mb-4 bg-light">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-user me-2"></i>Client Information
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Name:</strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($project['client_name']); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Email:</strong></p>
                                    <p class="text-muted">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($project['client_email']); ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Phone:</strong></p>
                                    <p class="text-muted">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($project['client_phone']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Task Details -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Task Details
                                    </h6>
                                    <div class="mb-3">
                                        <label class="text-muted mb-1">Category</label>
                                        <div id="modalTaskCategory" class="fw-medium"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted mb-1">Due Date</label>
                                        <div id="modalTaskDueDate" class="fw-medium"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted mb-1">Status</label>
                                        <div id="modalTaskStatus"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-align-left me-2"></i>Description
                                    </h6>
                                    <p id="modalTaskDescription" class="text-muted mb-0"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assignees Section -->
                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-users me-2"></i>Assigned Personnel
                            </h6>
                            <div id="modalTaskAssignees" class="d-flex flex-wrap gap-2 mb-3"></div>
                            <div id="modalAssigneeListContainer" class="mt-3" style="display: none;">
                                <div class="list-group shadow-sm" style="max-height: 250px; overflow-y: auto;">
                                    <!-- Project personnel list will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this modal after your other modals -->
    <div class="modal fade" id="archivedTasksModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-archive me-2"></i>Archived Tasks
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table" id="archivedTaskTable">
                            <thead>
                                <tr>
                                    <th style="width: 20%">Task</th>
                                    <th style="width: 15%">Category</th>
                                    <th style="width: 30%">Assigned To</th>
                                    <th style="width: 15%">Due Date</th>
                                    <th style="width: 10%">Status</th>
                                    <th style="width: 10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Archived tasks will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // At the top of your script, declare projectId as a global variable
    let projectId;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize projectId from PHP
        projectId = <?php echo $project_id; ?>;
        console.log('Project ID:', projectId); // Debug log

        console.log('DOMContentLoaded event fired');
        // Initialize variables
        
        // Load initial data
        loadCategories();
        loadTasks();
        updateProgress();
        
        // Set minimum date for task due date
        const taskDueDate = document.getElementById('taskDueDate');
        if (taskDueDate) {
            const today = new Date().toISOString().split('T')[0];
            taskDueDate.min = today;
            
            // Add event listener for date validation
            taskDueDate.addEventListener('change', function() {
                const projectStartDate = new Date(this.getAttribute('data-project-start'));
                const projectEndDate = new Date(this.getAttribute('data-project-end'));
                const selectedDate = new Date(this.value);
                
                if (selectedDate < projectStartDate) {
                    alert('Task due date cannot be before project start date: ' + 
                          projectStartDate.toLocaleDateString('en-US', { 
                              month: 'long', 
                              day: 'numeric', 
                              year: 'numeric' 
                          }));
                    this.value = this.getAttribute('data-project-start');
                }
                else if (selectedDate > projectEndDate) {
                    alert('Task due date cannot exceed project end date: ' + 
                          projectEndDate.toLocaleDateString('en-US', { 
                              month: 'long', 
                              day: 'numeric', 
                              year: 'numeric' 
                          }));
                    this.value = this.getAttribute('data-project-end');
                }
            });
        }

        // Add Task Modal handlers
        const addTaskModal = new bootstrap.Modal(document.getElementById('addTaskModal'));
        
        // Add Task button click handler
        document.getElementById('addTaskBtn').addEventListener('click', function() {
            console.log('Add Task button clicked');
            document.getElementById('addTaskForm').reset();
            document.getElementById('selectedAssignees').innerHTML = '';
            document.getElementById('assigneeListContainer').style.display = 'none';
            document.getElementById('taskAssignees').value = '';
            addTaskModal.show();
        });

        // Show Assignee List button click handler
        const showAssigneeListBtn = document.getElementById('showAssigneeList');
        console.log('Show Assignee List button:', showAssigneeListBtn);
        if (showAssigneeListBtn) {
            showAssigneeListBtn.addEventListener('click', function() {
                console.log('Show Assignee List button clicked');
                const container = document.getElementById('assigneeListContainer');
                
                if (container.style.display === 'none') {
                    loadProjectPersonnel(container);
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                }
            });
        }

        // Save Task button click handler
        const saveTaskButton = document.querySelector('#addTaskModal .btn-primary');
        saveTaskButton.addEventListener('click', async function() {
            await saveNewTask();
        });

        // Move the existing save task logic to a new function
        async function saveNewTask() {
            console.log('Save new task');
            const taskName = document.getElementById('taskName').value.trim();
            const categoryId = document.getElementById('taskCategory').value;
            const dueDate = document.getElementById('taskDueDate').value;
            const description = document.getElementById('taskDescription').value.trim();
            const assignees = JSON.parse(document.getElementById('taskAssignees').value || '[]');

            // Validate due date against project dates
            const projectStartDate = new Date(taskDueDate.getAttribute('data-project-start'));
            const projectEndDate = new Date(taskDueDate.getAttribute('data-project-end'));
            const selectedDueDate = new Date(dueDate);
            
            if (selectedDueDate < projectStartDate) {
                showAlert('error', 'Task due date cannot be before project start date');
                return;
            }
            else if (selectedDueDate > projectEndDate) {
                showAlert('error', 'Task due date cannot exceed project end date');
                return;
            }

            if (!taskName || !categoryId || !dueDate || assignees.length === 0) {
                showAlert('error', 'Please fill in all required fields and assign at least one person');
                return;
            }

            try {
                // First create the task
                const response = await fetch(`../api/tasks.php?action=add_task&project_id=${projectId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        task_name: taskName,
                        category_id: categoryId,
                        description: description,
                        due_date: dueDate,
                        assignees: assignees
                    })
                });

                const data = await response.json();
                console.log('Task creation response:', data);

                if (!data.success) {
                    throw new Error(data.error || 'Failed to add task');
                }

                // Then send notifications
                console.log('Sending notifications for task:', data.task_id);
                const notifyResponse = await fetch(`../api/tasks.php?action=notify_task_assignment`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: data.task_id,
                        task_name: taskName,
                        project_id: projectId,
                        assignees: assignees
                    })
                });

                const notifyData = await notifyResponse.json();
                console.log('Notification response:', notifyData);

                if (!notifyData.success) {
                    console.error('Failed to send notifications:', notifyData.error);
                }

                // Close modal and reset form
                const addTaskModal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
                addTaskModal.hide();
                document.getElementById('addTaskForm').reset();
                document.getElementById('selectedAssignees').innerHTML = '';
                document.getElementById('taskAssignees').value = '';

                // Reload tasks and update progress
                await loadTasks();
                await updateProgress();
                showAlert('success', 'Task added successfully and notifications sent');
            } catch (error) {
                console.error('Error in task creation process:', error);
                showAlert('error', error.message || 'Failed to add task');
            }
        }

        // Function to load project personnel
        async function loadProjectPersonnel(container) {
            try {
                console.log('Loading project personnel for project:', projectId);
                const response = await fetch(`../api/tasks.php?action=project_members&project_id=${projectId}`);
                const data = await response.json();
                console.log('Personnel data:', data);

                const listGroup = container.querySelector('.list-group') || container;
                listGroup.innerHTML = '';

                if (!data.personnel || data.personnel.length === 0) {
                    listGroup.innerHTML = `
                        <div class="list-group-item text-center text-muted">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <p class="mb-0">No personnel assigned to this project</p>
                        </div>
                    `;
                    return;
                }

                data.personnel.forEach(person => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.dataset.userId = person.user_id;
                    item.dataset.userName = person.name;
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-user me-2"></i>
                                ${escapeHtml(person.name)}
                            </div>
                            <small class="text-muted">${escapeHtml(person.role)}</small>
                        </div>
                    `;

                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        selectAssignee(person.user_id, person.name);
                        container.style.display = 'none';
                    });

                    listGroup.appendChild(item);
                });
            } catch (error) {
                console.error('Error loading personnel:', error);
                container.innerHTML = `
                    <div class="alert alert-danger">
                        Failed to load personnel. Please try again.
                        <small class="d-block mt-1">${error.message}</small>
                    </div>
                `;
            }
        }

        // Function to select an assignee
        function selectAssignee(userId, userName) {
            const selectedAssignees = document.getElementById('selectedAssignees');
            const existingAssignee = selectedAssignees.querySelector(`[data-user-id="${userId}"]`);
            
            if (!existingAssignee) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-primary me-2 mb-2';
                badge.dataset.userId = userId;
                badge.innerHTML = `
                    ${escapeHtml(userName)}
                    <button type="button" class="btn-close btn-close-white ms-2" 
                            onclick="removeAssignee(${userId})"></button>
                `;
                selectedAssignees.appendChild(badge);
            }
            
            updateAssigneesInput();
        }

        // Make removeAssignee function available globally
        window.removeAssignee = function(userId) {
            const assigneeTag = document.querySelector(`.badge[data-user-id="${userId}"]`);
            if (assigneeTag) {
                assigneeTag.remove();
                updateAssigneesInput();
            }
        };

        // Function to update hidden assignees input
        function updateAssigneesInput() {
            const selectedAssignees = Array.from(document.querySelectorAll('#selectedAssignees .badge'))
                .map(badge => badge.dataset.userId);
            document.getElementById('taskAssignees').value = JSON.stringify(selectedAssignees);
        }
    });

    async function loadCategories() {
        try {
            const response = await fetch(`../api/tasks.php?action=categories&project_id=${projectId}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to load categories');
            }

            const categoryList = document.getElementById('categoryList');
            const taskCategorySelect = document.getElementById('taskCategory');

            // Clear existing categories
            if (categoryList) categoryList.innerHTML = '';
            if (taskCategorySelect) {
                taskCategorySelect.innerHTML = '<option value="">Select Category</option>';
            }

            if (!data.categories || data.categories.length === 0) {
                if (categoryList) {
                    categoryList.innerHTML = `
                        <div class="col-12 text-center text-muted py-4">
                            <i class="fas fa-list fa-2x mb-3"></i>
                            <p class="mb-0">No categories found</p>
                        </div>
                    `;
                }
                return;
            }

            let previousCategoryCompleted = true; // First category is always available

            data.categories.forEach((category, index) => {
                if (categoryList) {
                    const categoryCol = document.createElement('div');
                    categoryCol.className = 'col-md-6 mb-3';
                    
                    const isLocked = !previousCategoryCompleted;
                    const statusClass = category.status === 'completed' ? 'success' : 
                                      isLocked ? 'secondary' : 'warning';
                    const statusText = category.status === 'completed' ? 'Completed' : 
                                     isLocked ? 'Locked' : 'In Progress';

                    categoryCol.innerHTML = `
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0">${escapeHtml(category.category_name)}</h6>
                                    <span class="badge bg-${statusClass}">${statusText}</span>
                                </div>
                                <p class="card-text text-muted small mb-3">
                                    ${escapeHtml(category.description || 'No description available')}
                                </p>
                                ${isLocked ? `
                                    <div class="text-muted small">
                                        <i class="fas fa-lock me-1"></i>Complete previous category to unlock
                                    </div>
                                ` : category.status !== 'completed' ? `
                                    <button class="btn btn-sm btn-success complete-category-btn" 
                                            onclick="completeCategory(${category.category_id})">
                                        <i class="fas fa-check me-1"></i>Mark as Complete
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    `;
                    categoryList.appendChild(categoryCol);
                }

                // Add to task category dropdown
                if (taskCategorySelect) {
                    const option = document.createElement('option');
                    option.value = category.category_id;
                    option.textContent = category.category_name;
                    option.disabled = !previousCategoryCompleted || category.status === 'completed';
                    taskCategorySelect.appendChild(option);
                }

                // Update for next iteration
                previousCategoryCompleted = category.status === 'completed';
            });
        } catch (error) {
            console.error('Error loading categories:', error);
            if (categoryList) {
                categoryList.innerHTML = `
                    <div class="col-12 text-center text-danger py-4">
                        <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                        <p class="mb-0">Failed to load categories. Please try again.</p>
                    </div>
                `;
            }
        }
    }

    async function loadTasks() {
        try {
            const response = await fetch(`../api/tasks.php?action=tasks&project_id=${projectId}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to load tasks');
            }

            const taskTableBody = document.querySelector('#taskTable tbody');
            taskTableBody.innerHTML = '';

            if (!data.tasks || data.tasks.length === 0) {
                taskTableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-tasks fa-2x mb-3"></i>
                            <p class="mb-0">No tasks assigned yet</p>
                        </td>
                    </tr>
                `;
                return;
            }

            data.tasks.forEach(task => {
                const daysUntilDue = Math.ceil((new Date(task.due_date) - new Date()) / (1000 * 60 * 60 * 24));
                const isUrgent = daysUntilDue <= 2 && task.status !== 'completed';

                const row = document.createElement('tr');
                row.dataset.taskId = task.task_id;
                row.dataset.description = task.description || '';
                row.dataset.category = task.category_name || '';
                row.innerHTML = `
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-medium ${isUrgent ? 'text-danger' : ''}">${escapeHtml(task.task_name)}</span>
                            <small class="text-muted text-truncate" style="max-width: 200px;" 
                                   title="${escapeHtml(task.description || '')}">
                                ${escapeHtml(task.description || 'No description')}
                            </small>
                        </div>
                    </td>
                    <td>${escapeHtml(task.category_name || '')}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            ${task.assignees.map(assignee => `
                                <span class="badge bg-light text-dark">
                                    ${escapeHtml(assignee.name)}
                                </span>
                            `).join('')}
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <span>${formatDate(task.due_date)}</span>
                            <small class="${isUrgent ? 'text-danger' : 'text-muted'}">
                                ${daysUntilDue === 0 ? 'Due today' : 
                                  daysUntilDue < 0 ? `${Math.abs(daysUntilDue)} days overdue` :
                                  `${daysUntilDue} days left`}
                            </small>
                        </div>
                    </td>
                    <td>
                        <span class="badge ${getStatusBadgeClass(task.status)}">
                            ${capitalizeFirst(task.status.replace('_', ' '))}
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewTaskDetails(${task.task_id})" 
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="archiveTask(${task.task_id})"
                                    title="Archive Task">
                                <i class="fas fa-archive"></i>
                            </button>
                        </div>
                    </td>
                `;
                taskTableBody.appendChild(row);
            });
        } catch (error) {
            console.error('Error loading tasks:', error);
            document.querySelector('#taskTable tbody').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                        <p class="mb-0">Failed to load tasks. Please try again.</p>
                    </td>
                </tr>
            `;
        }
    }

    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const container = document.querySelector('.container-fluid');
        container.insertBefore(alertDiv, container.firstChild);

        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function getStatusBadgeClass(status) {
        const classes = {
            'completed': 'bg-success',
            'in_progress': 'bg-primary',
            'pending': 'bg-warning',
            'overdue': 'bg-danger'
        };
        return classes[status] || 'bg-secondary';
    }

    async function viewTaskDetails(taskId) {
        try {
            // Get task details from the server
            const response = await fetch(`../api/tasks.php?action=get_task_details&task_id=${taskId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load task details');
            }

            const task = data.task;

            // Set task ID in modal for reference
            const modal = document.getElementById('taskDetailsModal');
            modal.dataset.taskId = taskId;

            // Update modal content
            document.getElementById('modalTaskName').textContent = task.task_name;
            document.getElementById('modalTaskDescription').textContent = task.description || 'No description available';
            document.getElementById('modalTaskCategory').textContent = task.category_name || 'No category';
            document.getElementById('modalTaskDueDate').textContent = formatDate(task.due_date);
            document.getElementById('modalTaskStatus').innerHTML = `
                <span class="badge ${getStatusBadgeClass(task.status)}">
                    ${capitalizeFirst(task.status.replace('_', ' '))}
                </span>
            `;

            // Update assignees
            const assigneesContainer = document.getElementById('modalTaskAssignees');
            assigneesContainer.innerHTML = task.assignees.map(assignee => `
                <span class="badge bg-light text-dark">
                    ${escapeHtml(assignee.name)}
                </span>
            `).join('');

            // Hide assignee list container
            document.getElementById('modalAssigneeListContainer').style.display = 'none';

            // Show the modal
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();

        } catch (error) {
            console.error('Error viewing task details:', error);
            showAlert('error', 'Failed to load task details');
        }
    }

    async function updateProgress() {
        try {
            const response = await fetch(`../api/tasks.php?action=progress&project_id=${projectId}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to load progress');
            }

            const progress = data.progress;
            
            // Update progress bar
            const progressBar = document.getElementById('projectProgress');
            progressBar.style.width = `${progress.progress_percentage}%`;
            progressBar.textContent = `${progress.progress_percentage}%`;
            
            // Update task counts
            document.getElementById('totalTasks').textContent = progress.total_tasks;
            document.getElementById('completedTasks').textContent = progress.completed_tasks;
            document.getElementById('pendingTasks').textContent = progress.pending_tasks;
        } catch (error) {
            console.error('Error updating progress:', error);
        }
    }

    // Add this function to check if previous categories are completed
    async function checkPreviousCategories(categoryId) {
        try {
            const response = await fetch(`../api/tasks.php?action=check_previous_categories&project_id=${projectId}&category_id=${categoryId}`);
            const data = await response.json();
            return data.can_proceed;
        } catch (error) {
            console.error('Error checking previous categories:', error);
            return false;
        }
    }

    // Modify the completeCategory function
    async function completeCategory(categoryId) {
        if (!confirm('Are you sure you want to mark this category as complete? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`../api/tasks.php?action=complete_category&project_id=${projectId}&category_id=${categoryId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to complete category');
            }

            // Reload categories immediately after successful completion
            await loadCategories();
            showAlert('success', 'Category marked as complete');
        } catch (error) {
            console.error('Error completing category:', error);
            showAlert('error', error.message || 'Failed to complete category');
        }
    }

    // Modify the task status update function to check category status
    async function updateTaskStatus(taskId, newStatus) {
        try {
            // First check if the task can be updated
            const checkResponse = await fetch(`../api/tasks.php?action=can_update_task&task_id=${taskId}`);
            const checkData = await checkResponse.json();

            if (!checkData.can_update) {
                showAlert('error', 'Cannot update task status. Previous category tasks must be completed first.');
                return;
            }

            // Proceed with status update if allowed
            const response = await fetch(`../api/tasks.php?action=update_task_status&task_id=${taskId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to update task status');
            }

            await loadTasks(); // Reload tasks to update UI
            showAlert('success', 'Task status updated successfully');
        } catch (error) {
            console.error('Error updating task status:', error);
            showAlert('error', 'Failed to update task status');
        }
    }

    async function archiveTask(taskId) {
        if (!confirm('Are you sure you want to archive this task? Archived tasks will no longer appear in the active task list.')) {
            return;
        }

        try {
            console.log('Archiving task:', taskId, 'from project:', projectId);
            const response = await fetch(`../api/tasks.php?action=archive_task&project_id=${projectId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId
                })
            });

            const data = await response.json();
            console.log('Archive response:', data);

            if (!data.success) {
                throw new Error(data.error || 'Failed to archive task');
            }

            // Reload tasks and update progress
            await loadTasks();
            await updateProgress();
            showAlert('success', 'Task archived successfully');
        } catch (error) {
            console.error('Error archiving task:', error);
            showAlert('error', 'Failed to archive task: ' + error.message);
        }
    }

    // Add these functions to your existing script section
    async function showArchivedTasks() {
        try {
            const response = await fetch(`../api/tasks.php?action=archived_tasks&project_id=${projectId}`);
            const data = await response.json();

            const tableBody = document.querySelector('#archivedTaskTable tbody');
            tableBody.innerHTML = '';

            if (!data.tasks || data.tasks.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-archive fa-2x mb-3"></i>
                            <p class="mb-0">No archived tasks found</p>
                        </td>
                    </tr>
                `;
            } else {
                data.tasks.forEach(task => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-medium">${escapeHtml(task.task_name)}</span>
                                <small class="text-muted text-truncate" style="max-width: 200px;">
                                    ${escapeHtml(task.description || 'No description')}
                                </small>
                            </div>
                        </td>
                        <td>${escapeHtml(task.category_name || '')}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                ${task.assignees.map(assignee => `
                                    <span class="badge bg-light text-dark">
                                        ${escapeHtml(assignee.name)}
                                    </span>
                                `).join('')}
                            </div>
                        </td>
                        <td>${formatDate(task.due_date)}</td>
                        <td>
                            <span class="badge ${getStatusBadgeClass(task.status)}">
                                ${capitalizeFirst(task.status.replace('_', ' '))}
                            </span>
                        </td>
                        <td>
                            ${task.category_status === 'completed' ? `
                                <button class="btn btn-sm btn-outline-secondary" disabled
                                        title="Cannot return task: Category is completed">
                                    <i class="fas fa-undo"></i>
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-outline-success" onclick="returnTask(${task.task_id})"
                                        title="Return Task">
                                    <i class="fas fa-undo"></i>
                                </button>
                            `}
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            }

            const modal = new bootstrap.Modal(document.getElementById('archivedTasksModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading archived tasks:', error);
            showAlert('error', 'Failed to load archived tasks');
        }
    }

    async function returnTask(taskId) {
        if (!confirm('Are you sure you want to return this task to the active task list?')) {
            return;
        }

        try {
            // First check if the task's category is completed
            const checkResponse = await fetch(`../api/tasks.php?action=check_category_status&task_id=${taskId}`);
            const checkData = await checkResponse.json();

            if (!checkData.success) {
                throw new Error(checkData.error || 'Failed to check category status');
            }

            if (checkData.is_category_completed) {
                showAlert('error', 'Cannot return task: The category is already completed');
                return;
            }

            const response = await fetch(`../api/tasks.php?action=return_task&project_id=${projectId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to return task');
            }

            // Get the modal instance and hide it
            const archivedTasksModal = bootstrap.Modal.getInstance(document.getElementById('archivedTasksModal'));
            if (archivedTasksModal) {
                archivedTasksModal.hide();
            }

            // Remove modal backdrop manually if it exists
            const modalBackdrop = document.querySelector('.modal-backdrop');
            if (modalBackdrop) {
                modalBackdrop.remove();
            }

            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';

            // Reload tasks and update progress
            await loadTasks();
            await updateProgress();
            showAlert('success', 'Task returned successfully');

            // Reload archived tasks modal after a short delay
            setTimeout(() => {
                showArchivedTasks();
            }, 300);

        } catch (error) {
            console.error('Error returning task:', error);
            showAlert('error', 'Failed to return task: ' + error.message);
        }
    }
    </script>
</body>
</html> 