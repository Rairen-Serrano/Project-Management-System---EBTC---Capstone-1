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
    LEFT JOIN project_personnel pp ON p.project_id = pp.project_id
    LEFT JOIN users up ON pp.user_id = up.user_id
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
    .timeline {
        position: relative;
        padding: 20px 0;
    }
    .timeline-item {
        position: relative;
        padding-left: 40px;
        margin-bottom: 30px;
    }
    .timeline-marker {
        position: absolute;
        left: 0;
        top: 0;
        width: 15px;
        height: 15px;
        border-radius: 50%;
    }
    .timeline-content {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
    }
    </style>
</head>
<body>
    <div class="manager-dashboard-wrapper">
        <?php include 'manager_header.php'; ?>
        
        <div class="manager-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1">Task Management</h3>
                    <p class="text-muted mb-0">
                        Project: <?php echo htmlspecialchars($project['service']); ?>
                        <span class="mx-2">|</span>
                        Client: <?php echo htmlspecialchars($project['client_name']); ?>
                    </p>
                </div>
                <a href="projects.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Projects
                </a>
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
                                <button class="btn btn-sm btn-primary" id="addCategoryBtn">
                                    <i class="fas fa-plus me-1"></i>Add Category
                                </button>

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

                <!-- Task Timeline -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-calendar-alt me-2"></i>Timeline
                            </h6>
                            <div class="timeline" id="taskTimeline">
                                <!-- Timeline will be loaded dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Task Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addCategoryForm">
                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="categoryName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="categoryDescription" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveCategory()">Save Category</button>
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
                                    <input type="date" class="form-control" id="taskDueDate" required>
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
                    <button type="button" class="btn btn-primary" onclick="saveTask()">
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded event fired');
        // Initialize variables
        const projectId = <?php echo $project_id; ?>;
        
        // Load initial data
        loadCategories();
        loadTasks();
        loadTimeline();
        updateProgress();
        
        // Set minimum date for task due date
        const taskDueDate = document.getElementById('taskDueDate');
        if (taskDueDate) {
            const today = new Date().toISOString().split('T')[0];
            taskDueDate.min = today;
        }

        // Add Category button click handler
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        console.log('Add Category button:', addCategoryBtn);
        addCategoryBtn.addEventListener('click', function() {
            console.log('Add Category button clicked');
            const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
            document.getElementById('addCategoryForm').reset();
            modal.show();
        });

        // Add Task button click handler
        const addTaskBtn = document.getElementById('addTaskBtn');
        console.log('Add Task button:', addTaskBtn);
        addTaskBtn.addEventListener('click', function() {
            console.log('Add Task button clicked');
            const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
            document.getElementById('addTaskForm').reset();
            document.getElementById('selectedAssignees').innerHTML = '';
            document.getElementById('assigneeListContainer').style.display = 'none';
            document.getElementById('taskAssignees').value = '';
            modal.show();
        });

        // Save Category button click handler
        const saveCategoryBtn = document.querySelector('#addCategoryModal .btn-primary');
        console.log('Save Category button:', saveCategoryBtn);
        saveCategoryBtn.addEventListener('click', function(e) {
            console.log('Save Category button clicked');
            e.preventDefault();
            saveCategory();
        });

        // Save Task button click handler
        const saveTaskBtn = document.querySelector('#addTaskModal .btn-primary');
        console.log('Save Task button:', saveTaskBtn);
        saveTaskBtn.addEventListener('click', function(e) {
            console.log('Save Task button clicked');
            e.preventDefault();
            saveTask();
        });

        // Show Assignee List button click handler
        const showAssigneeListBtn = document.getElementById('showAssigneeList');
        console.log('Show Assignee List button:', showAssigneeListBtn);
        showAssigneeListBtn.addEventListener('click', function() {
            console.log('Show Assignee List button clicked');
            const container = document.getElementById('assigneeListContainer');
            
            if (container.style.display === 'none') {
                // Load project personnel with current assignees filtered out
                loadProjectPersonnel(container, false);
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        });
    });

    async function loadCategories() {
        try {
            const response = await fetch(`api/tasks.php?action=categories&project_id=<?php echo $project_id; ?>`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to load categories');
            }

            const categoryList = document.getElementById('categoryList');
            const taskCategorySelect = document.getElementById('taskCategory');

            // Clear existing categories
            categoryList.innerHTML = '';
            if (taskCategorySelect) {
                taskCategorySelect.innerHTML = '<option value="">Select Category</option>';
            }

            if (!data.categories || data.categories.length === 0) {
                categoryList.innerHTML = `
                    <div class="col-12 text-center text-muted py-4">
                        <i class="fas fa-list fa-2x mb-3"></i>
                        <p class="mb-0">No categories added yet</p>
                    </div>
                `;
                return;
            }

            // Add categories to the list and dropdown
            data.categories.forEach(category => {
                // Add to category list
                const categoryCol = document.createElement('div');
                categoryCol.className = 'col-md-4 mb-3';
                categoryCol.innerHTML = `
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-2">${escapeHtml(category.category_name)}</h6>
                            <p class="card-text small text-muted mb-0">${escapeHtml(category.description || 'No description')}</p>
                        </div>
                    </div>
                `;
                categoryList.appendChild(categoryCol);

                // Add to task category dropdown
                if (taskCategorySelect) {
                    taskCategorySelect.innerHTML += `
                        <option value="${category.category_id}">${escapeHtml(category.category_name)}</option>
                    `;
                }
            });
        } catch (error) {
            console.error('Error loading categories:', error);
            document.getElementById('categoryList').innerHTML = `
                <div class="col-12 text-center text-danger py-4">
                    <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                    <p class="mb-0">Failed to load categories. Please try again.</p>
                </div>
            `;
        }
    }

    async function loadTasks() {
        try {
            const response = await fetch(`api/tasks.php?action=tasks&project_id=<?php echo $project_id; ?>`);
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
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(${task.task_id})"
                                    title="Delete Task">
                                <i class="fas fa-trash"></i>
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

    async function saveCategory() {
        console.log('saveCategory function called');
        const categoryName = document.getElementById('categoryName').value.trim();
        const categoryDescription = document.getElementById('categoryDescription').value.trim();

        if (!categoryName) {
            showAlert('error', 'Category name is required');
            return;
        }

        try {
            console.log('Sending category data:', { categoryName, categoryDescription });
            const response = await fetch(`api/tasks.php?action=category&project_id=<?php echo $project_id; ?>`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    category_name: categoryName,
                    description: categoryDescription
                })
            });

            const data = await response.json();
            console.log('Server response:', data);

            if (!response.ok) {
                throw new Error(data.error || 'Failed to add category');
            }

            // Close modal and reset form
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'));
            modal.hide();
            document.getElementById('addCategoryForm').reset();

            // Reload categories and update task category dropdown
            loadCategories();
            showAlert('success', 'Category added successfully');
        } catch (error) {
            console.error('Error adding category:', error);
            showAlert('error', 'Failed to add category');
        }
    }

    async function saveTask() {
        console.log('saveTask function called');
        const taskName = document.getElementById('taskName').value.trim();
        const categoryId = document.getElementById('taskCategory').value;
        const dueDate = document.getElementById('taskDueDate').value;
        const description = document.getElementById('taskDescription').value.trim();
        const assignees = Array.from(document.querySelectorAll('#selectedAssignees .assignee-item'))
            .map(item => item.dataset.userId);

        console.log('Task data:', {
            taskName,
            categoryId,
            dueDate,
            description,
            assignees
        });

        if (!taskName || !categoryId || !dueDate || assignees.length === 0) {
            showAlert('error', 'Please fill in all required fields and select at least one assignee');
            return;
        }

        try {
            const response = await fetch(`api/tasks.php?action=task&project_id=<?php echo $project_id; ?>`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_name: taskName,
                    category_id: categoryId,
                    due_date: dueDate,
                    description: description,
                    assignees: assignees
                })
            });

            const data = await response.json();
            console.log('Server response:', data);

            if (!response.ok) {
                throw new Error(data.error || 'Failed to add task');
            }

            // Close modal and reset form
            const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
            modal.hide();
            document.getElementById('addTaskForm').reset();
            document.getElementById('selectedAssignees').innerHTML = '';
            document.getElementById('assigneeListContainer').style.display = 'none';

            // Reload tasks and update progress
            loadTasks();
            updateProgress();
            showAlert('success', 'Task added successfully');
        } catch (error) {
            console.error('Error adding task:', error);
            showAlert('error', error.message || 'Failed to add task');
        }
    }

    function showAlert(type, message) {
        const alertContainer = document.createElement('div');
        alertContainer.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
        alertContainer.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.manager-main-content').insertAdjacentElement('afterbegin', alertContainer);

        setTimeout(() => {
            alertContainer.remove();
        }, 5000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function getStatusBadgeClass(status) {
        switch(status.toLowerCase()) {
            case 'completed':
                return 'bg-success';
            case 'in_progress':
                return 'bg-primary';
            case 'pending':
                return 'bg-warning';
            default:
                return 'bg-secondary';
        }
    }

    function showModalAssigneeList() {
        const container = document.getElementById('modalAssigneeListContainer');
        
        // Toggle display
        if (container.style.display === 'none') {
            // Load project personnel
            loadProjectPersonnel(container, true);
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    async function loadProjectPersonnel(container, isModalView = false) {
        try {
            // Get task ID if in modal view
            const taskId = isModalView ? document.querySelector('#taskDetailsModal').dataset.taskId : null;
            
            // Modify the API endpoint to include task_id if in modal view
            const apiUrl = `api/tasks.php?action=personnel&project_id=<?php echo $project_id; ?>${taskId ? '&task_id=' + taskId : ''}`;
            console.log('Loading personnel from:', apiUrl);
            
            const response = await fetch(apiUrl);
            const data = await response.json();
            console.log('Personnel data:', data);

            if (!response.ok) {
                throw new Error(data.error || 'Failed to load personnel');
            }

            const listGroup = container.querySelector('.list-group') || container;
            listGroup.innerHTML = '';

            if (!data.personnel || data.personnel.length === 0) {
                listGroup.innerHTML = `
                    <div class="list-group-item text-center text-muted">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <p class="mb-0">No personnel available</p>
                    </div>
                `;
                return;
            }

            // Get current assignees
            const currentAssignees = Array.from(document.querySelectorAll(isModalView ? '#modalTaskAssignees .badge' : '#selectedAssignees .assignee-item'))
                .map(item => item.dataset.userId);
            console.log('Current assignees:', currentAssignees);

            // Filter out already assigned personnel when not in modal view
            const availablePersonnel = isModalView ? data.personnel : 
                data.personnel.filter(person => !currentAssignees.includes(person.user_id.toString()));
            console.log('Available personnel:', availablePersonnel);

            if (availablePersonnel.length === 0) {
                listGroup.innerHTML = `
                    <div class="list-group-item text-center text-muted">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <p class="mb-0">All available personnel are already assigned</p>
                    </div>
                `;
                return;
            }

            availablePersonnel.forEach(person => {
                const isAssigned = currentAssignees.includes(person.user_id.toString());
                const item = document.createElement('a');
                item.href = '#';
                item.className = `list-group-item list-group-item-action d-flex justify-content-between align-items-center ${isAssigned ? 'active' : ''}`;
                item.dataset.userId = person.user_id;
                item.dataset.name = person.name;
                item.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-user-circle fa-lg text-secondary"></i>
                        </div>
                        <div>
                            <div class="fw-medium">${escapeHtml(person.name)}</div>
                            <small class="text-muted">${capitalizeFirst(escapeHtml(person.role))}</small>
                        </div>
                    </div>
                    <i class="fas ${isAssigned ? 'fa-check text-success' : 'fa-plus text-primary'}"></i>
                `;

                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Personnel item clicked:', person);
                    if (isModalView) {
                        toggleModalAssignee(person.user_id, person.name, this);
                    } else {
                        toggleAssignee(person.user_id, person.name, this);
                    }
                });

                listGroup.appendChild(item);
            });
        } catch (error) {
            console.error('Error loading personnel:', error);
            container.innerHTML = `
                <div class="list-group-item text-center text-danger">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <p class="mb-0">Failed to load personnel</p>
                </div>
            `;
        }
    }

    async function toggleModalAssignee(userId, name, listItem) {
        console.log('Toggling modal assignee:', { userId, name });
        const assigneesContainer = document.getElementById('modalTaskAssignees');
        const existingAssignee = assigneesContainer.querySelector(`[data-user-id="${userId}"]`);
        const taskId = document.querySelector('#taskDetailsModal').dataset.taskId;
        const projectId = <?php echo $project_id; ?>;

        try {
            if (existingAssignee) {
                console.log('Removing assignee:', userId);
                // Remove assignee
                const response = await fetch(`api/tasks.php?action=remove_assignee&project_id=${projectId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        user_id: userId
                    })
                });

                const data = await response.json();
                console.log('Remove assignee response:', data);

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to remove assignee');
                }

                existingAssignee.remove();
                listItem.classList.remove('active');
                listItem.querySelector('i:last-child').className = 'fas fa-plus text-primary';
            } else {
                console.log('Adding assignee:', userId);
                // Add assignee
                const response = await fetch(`api/tasks.php?action=add_assignee&project_id=${projectId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        user_id: userId
                    })
                });

                const data = await response.json();
                console.log('Add assignee response:', data);

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to add assignee');
                }

                const badge = document.createElement('span');
                badge.className = 'badge bg-light text-dark border';
                badge.dataset.userId = userId;
                badge.innerHTML = `
                    <i class="fas fa-user-circle me-1"></i>
                    ${escapeHtml(name)}
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" 
                            onclick="event.stopPropagation(); toggleModalAssignee('${userId}', '${name}', document.querySelector('#modalAssigneeListContainer [data-user-id=\\'${userId}\\']'))">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                assigneesContainer.appendChild(badge);
                listItem.classList.add('active');
                listItem.querySelector('i:last-child').className = 'fas fa-check text-success';
            }

            // Reload tasks to reflect changes
            await loadTasks();
            showAlert('success', existingAssignee ? 'Personnel removed successfully' : 'Personnel added successfully');
        } catch (error) {
            console.error('Error toggling assignee:', error);
            showAlert('error', error.message || 'Failed to update assignee');
        }
    }

    function viewTaskDetails(taskId) {
        try {
            // Get task details from the table row
            const taskRow = document.querySelector(`tr[data-task-id="${taskId}"]`);
            if (!taskRow) {
                throw new Error('Task not found');
            }

            // Set task ID in modal for reference
            const modal = document.getElementById('taskDetailsModal');
            modal.dataset.taskId = taskId;

            // Get task data from the row
            const taskName = taskRow.querySelector('td:first-child .fw-medium').textContent.trim();
            const description = taskRow.dataset.description;
            const category = taskRow.querySelector('td:nth-child(2)').textContent.trim();
            const dueDate = taskRow.querySelector('td:nth-child(4) span:first-child').textContent.trim();
            const status = taskRow.querySelector('td:nth-child(5) .badge').textContent.trim();
            const assignees = taskRow.querySelector('td:nth-child(3)').innerHTML;

            // Update modal content
            document.getElementById('modalTaskName').textContent = taskName;
            document.getElementById('modalTaskDescription').textContent = description || 'No description available';
            document.getElementById('modalTaskCategory').textContent = category || 'No category';
            document.getElementById('modalTaskDueDate').textContent = dueDate;
            document.getElementById('modalTaskStatus').innerHTML = `<span class="badge ${getStatusBadgeClass(status.toLowerCase())}">${status}</span>`;
            document.getElementById('modalTaskAssignees').innerHTML = assignees;

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

    async function deleteTask(taskId) {
        // Show confirmation dialog
        if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`api/tasks.php?action=task&project_id=<?php echo $project_id; ?>&id=${taskId}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to delete task');
            }

            // Reload tasks and update progress
            loadTasks();
            updateProgress();
            showAlert('success', 'Task deleted successfully');
        } catch (error) {
            console.error('Error deleting task:', error);
            showAlert('error', 'Failed to delete task');
        }
    }

    function toggleAssignee(userId, name, listItem) {
        console.log('Toggling assignee:', { userId, name });
        const selectedAssignees = document.getElementById('selectedAssignees');
        const existingAssignee = selectedAssignees.querySelector(`[data-user-id="${userId}"]`);
        
        if (existingAssignee) {
            // Remove assignee
            console.log('Removing assignee:', userId);
            existingAssignee.remove();
            listItem.classList.remove('active');
            listItem.querySelector('i:last-child').className = 'fas fa-plus text-primary';
        } else {
            // Add assignee
            console.log('Adding assignee:', userId);
            const badge = document.createElement('span');
            badge.className = 'badge bg-light text-dark border assignee-item';
            badge.dataset.userId = userId;
            badge.innerHTML = `
                <i class="fas fa-user-circle me-1"></i>
                ${escapeHtml(name)}
                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" 
                        onclick="event.stopPropagation(); toggleAssignee('${userId}', '${name}', document.querySelector('#assigneeListContainer [data-user-id=\\'${userId}\\']'))">
                    <i class="fas fa-times"></i>
                </button>
            `;
            selectedAssignees.appendChild(badge);
            listItem.classList.add('active');
            listItem.querySelector('i:last-child').className = 'fas fa-check text-success';
        }

        // Update hidden input with selected assignees
        const selectedUserIds = Array.from(selectedAssignees.querySelectorAll('.assignee-item'))
            .map(item => item.dataset.userId);
        document.getElementById('taskAssignees').value = selectedUserIds.join(',');

        // Refresh the personnel list to show/hide assigned personnel
        const container = document.getElementById('assigneeListContainer');
        loadProjectPersonnel(container, false);
    }

    async function loadTimeline() {
        try {
            const response = await fetch(`api/tasks.php?action=timeline&project_id=<?php echo $project_id; ?>`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to load timeline');
            }

            const timelineContainer = document.getElementById('taskTimeline');
            timelineContainer.innerHTML = '';

            if (!data.timeline || data.timeline.length === 0) {
                timelineContainer.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                        <p class="mb-0">No timeline events yet</p>
                    </div>
                `;
                return;
            }

            // Sort tasks by due date
            const sortedTasks = data.timeline.sort((a, b) => new Date(a.due_date) - new Date(b.due_date));

            // Add tasks to timeline
            sortedTasks.forEach(task => {
                const daysUntilDue = Math.ceil((new Date(task.due_date) - new Date()) / (1000 * 60 * 60 * 24));
                const isUrgent = daysUntilDue <= 2 && task.status !== 'completed';

                const timelineItem = document.createElement('div');
                timelineItem.className = 'timeline-item';
                timelineItem.innerHTML = `
                    <div class="timeline-marker bg-${getStatusBadgeClass(task.status).replace('bg-', '')}"></div>
                    <div class="timeline-content">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 ${isUrgent ? 'text-danger' : ''}">${escapeHtml(task.task_name)}</h6>
                            <span class="badge ${getStatusBadgeClass(task.status)}">
                                ${capitalizeFirst(task.status.replace('_', ' '))}
                            </span>
                        </div>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-layer-group me-1"></i>${escapeHtml(task.category_name || 'No Category')}
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>${formatDate(task.due_date)}
                            </small>
                            <small class="${isUrgent ? 'text-danger' : 'text-muted'}">
                                ${daysUntilDue === 0 ? 'Due today' : 
                                  daysUntilDue < 0 ? `${Math.abs(daysUntilDue)} days overdue` :
                                  `${daysUntilDue} days left`}
                            </small>
                        </div>
                    </div>
                `;
                timelineContainer.appendChild(timelineItem);
            });
        } catch (error) {
            console.error('Error loading timeline:', error);
            document.getElementById('taskTimeline').innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                    <p class="mb-0">Failed to load timeline. Please try again.</p>
                </div>
            `;
        }
    }

    async function updateProgress() {
        try {
            const response = await fetch(`api/tasks.php?action=progress&project_id=<?php echo $project_id; ?>`);
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
    </script>
</body>
</html> 