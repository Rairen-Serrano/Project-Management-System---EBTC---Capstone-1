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
    <script src="../js/task_management.js"></script>

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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTaskForm">
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
                            <label class="form-label">Assign To</label>
                            <div class="d-flex align-items-start gap-2">
                                <button type="button" class="btn btn-outline-primary rounded-circle mt-1" id="showAssigneeList">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <div class="flex-grow-1">
                                    <div id="selectedAssignees" class="mb-2">
                                        <!-- Selected assignees will be shown here -->
                                    </div>
                                    <div id="assigneeListContainer" class="list-group" style="display: none;">
                                        <!-- Project personnel list will be loaded here -->
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="taskAssignees" name="assignees" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="taskDueDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="taskDescription" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveTask()">Save Task</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
    });
    </script>
</body>
</html> 