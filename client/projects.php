<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}

// Get user's projects with personnel
$stmt = $pdo->prepare("
    SELECT 
        p.project_id,
        p.service,
        p.start_date,
        p.end_date,
        p.notes,
        p.status,
        p.quotation_file,
        GROUP_CONCAT(DISTINCT CONCAT(u.name, '|', u.role, '|', u.email, '|', u.phone) SEPARATOR '||') as assigned_personnel,
        (
            SELECT COUNT(*) 
            FROM tasks t 
            WHERE t.project_id = p.project_id AND t.status = 'completed'
        ) as completed_tasks,
        (
            SELECT COUNT(*) 
            FROM tasks t 
            WHERE t.project_id = p.project_id
        ) as total_tasks
    FROM projects p
    LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
    LEFT JOIN users u ON pa.user_id = u.user_id
    WHERE p.client_id = ?
    GROUP BY p.project_id
    ORDER BY p.start_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects | EBTC PMS</title>

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
        .category-section {
            position: relative;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }

        .category-section.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .category-section.active {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .task-categories .progress {
            margin-bottom: 2rem;
        }

        .accordion-item.border-primary {
            border-color: #0d6efd !important;
        }

        .accordion-button:not(.collapsed) {
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        .accordion-button .badge {
            font-size: 0.85em;
        }

        .accordion-button.bg-light:not(.collapsed) {
            background-color: #f8f9fa !important;
        }

        .accordion-button:focus {
            box-shadow: none;
        }

        .assigned-names {
            min-width: 150px;
        }

        .assigned-person {
            padding: 2px 0;
        }

        .assigned-person:not(:last-child) {
            border-bottom: 1px dashed #dee2e6;
        }
    </style>
</head>
<body id="clientProjectsPage">
    <?php include 'client_header.php'; ?>
    
    <div class="client-dashboard-wrapper">
        <!-- Main Content -->
        <div class="client-main-content">
            <!-- Mobile Toggle Button -->
            <button class="btn btn-primary d-md-none mb-3" id="clientSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Projects Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" id="projectTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="projects-tab" data-bs-toggle="tab" 
                                            data-bs-target="#projects-content" type="button" role="tab">
                                        Projects List
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="details-tab" data-bs-toggle="tab" 
                                            data-bs-target="#details-content" type="button" role="tab" disabled>
                                        Project Details
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="projectTabsContent">
                                <!-- Projects List Tab -->
                                <div class="tab-pane fade show active" id="projects-content" role="tabpanel">
                            <?php if (empty($projects)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                                    <h5>No Projects Found</h5>
                                    <p class="text-muted">You don't have any active projects at the moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Service</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($projects as $project): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-tools text-muted me-2"></i>
                                                            <?php echo htmlspecialchars($project['service']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                                                            <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-calendar-check text-muted me-2"></i>
                                                            <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClass = match($project['status']) {
                                                            'completed' => 'bg-success',
                                                            'ongoing' => 'bg-primary',
                                                            'pending' => 'bg-warning',
                                                            default => 'bg-secondary'
                                                        };
                                                        ?>
                                                        <span class="badge <?php echo $statusClass; ?>">
                                                            <?php echo ucfirst($project['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-info btn-sm view-project" data-project-id="<?php echo $project['project_id']; ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                                <!-- Project Details Tab -->
                                <div class="tab-pane fade" id="details-content" role="tabpanel">
                                    <div class="row g-4">
                                        <!-- Back Button -->
                                        <div class="col-12">
                                            <button class="btn btn-secondary mb-3" id="backToProjects">
                                                <i class="fas fa-arrow-left"></i> Back to Projects
                                            </button>
                    </div>

                                        <!-- Project Info and Team -->
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h5 class="card-title mb-0">Project Information</h5>
                </div>
                                                <div class="card-body">
                                                    <p><strong>Service:</strong> <span id="projectService"></span></p>
                                                    <p><strong>Start Date:</strong> <span id="projectStartDate"></span></p>
                                                    <p><strong>End Date:</strong> <span id="projectEndDate"></span></p>
                                                    <p><strong>Status:</strong> <span id="projectStatus"></span></p>
                                                    <p><strong>Notes:</strong></p>
                                                    <p id="projectNotes" class="text-muted"></p>
            </div>
        </div>
    </div>

                        <div class="col-md-6">
                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h5 class="card-title mb-0">Project Team</h5>
                                                </div>
                                                <div class="card-body" id="projectTeam">
                                                    <!-- Team members will be loaded here -->
                                </div>
                            </div>
                        </div>

                                        <!-- Quotation File -->
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="card-title mb-0">Quotation File</h5>
                                    </div>
                                                <div class="card-body" id="projectQuotation">
                                                    <!-- Quotation file will be loaded here -->
                                </div>
                            </div>
                        </div>

                                        <!-- Project Progress -->
                        <div class="col-12">
                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="card-title mb-0">Project Progress</h5>
                                                </div>
                                <div class="card-body">
                                                    <div class="progress mb-4" style="height: 25px;">
                                                        <div class="progress-bar" role="progressbar" id="projectProgress" 
                                                             style="width: 0%;" aria-valuenow="0" aria-valuemin="0" 
                                                             aria-valuemax="100">0%</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                                        <!-- Task Categories -->
                                        <div class="col-12">
                                            <div class="accordion" id="taskCategories">
                                                <!-- Categories will be dynamically inserted here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle view project button clicks
        document.querySelectorAll('.view-project').forEach(button => {
            button.addEventListener('click', function() {
                const projectId = this.getAttribute('data-project-id');
                document.getElementById('details-tab').disabled = false;
                document.getElementById('details-tab').click();
                fetchProjectDetails(projectId);
            });
        });

        // Handle back button
        document.getElementById('backToProjects').addEventListener('click', function() {
            document.getElementById('projects-tab').click();
        });

        function fetchProjectDetails(projectId) {
            fetch(`get_project_details.php?project_id=${projectId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text(); // Change to text() first to debug
                })
                .then(text => {
                    console.log('Raw response:', text); // Debug log
                    try {
                    return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.log('Raw text that failed to parse:', text);
                        throw new Error('Failed to parse JSON response');
                    }
                })
                .then(data => {
                    console.log('Parsed data:', data); // Debug log
                    
                    if (!data || typeof data !== 'object') {
                        throw new Error('Invalid data format received');
                    }

                    // Update project information
                    document.getElementById('projectService').textContent = data.project.service;
                    document.getElementById('projectStartDate').textContent = new Date(data.project.start_date)
                        .toLocaleDateString();
                    document.getElementById('projectEndDate').textContent = new Date(data.project.end_date)
                        .toLocaleDateString();
                    
                    const statusBadge = `<span class="badge ${getStatusClass(data.project.status)}">
                        ${data.project.status.charAt(0).toUpperCase() + data.project.status.slice(1)}
                    </span>`;
                    document.getElementById('projectStatus').innerHTML = statusBadge;
                    
                    document.getElementById('projectNotes').textContent = data.project.notes || 'No notes available';

                    // Update team members
                    updateTeamMembers(data.personnel);

                    // Update progress
                    updateProgress(data.project.completed_tasks, data.project.total_tasks);

                    // Update tasks
                    updateTasks(data.tasks);

                    // Update quotation
                    updateQuotation(data.project.quotation_file);
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert(`Error loading project details: ${error.message}`);
                });
        }

        function updateTeamMembers(personnel) {
            const teamContainer = document.getElementById('projectTeam');
            if (!personnel || personnel.length === 0) {
                teamContainer.innerHTML = '<p class="text-muted">No personnel assigned</p>';
                return;
            }

            teamContainer.innerHTML = personnel.map(person => `
        <div class="mb-3 p-2 border rounded">
            <div class="d-flex align-items-center">
                <i class="fas fa-user-circle fa-2x text-muted me-2"></i>
                <div>
                    <div class="fw-bold">${person.name} - ${person.role}</div>
                    <div class="text-muted small">
                        <i class="fas fa-phone me-1"></i>${person.phone || 'No phone number'}
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-envelope me-1"></i>${person.email}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
        }

        function updateProgress(completed, total) {
            const progress = (completed / total) * 100;
            const progressBar = document.getElementById('projectProgress');
            progressBar.style.width = `${progress}%`;
            progressBar.textContent = `${Math.round(progress)}%`;
            progressBar.setAttribute('aria-valuenow', progress);
        }

        function updateTasks(tasks) {
            console.log('Updating tasks:', tasks);

            // Get the categories container
            const categoriesContainer = document.getElementById('taskCategories');
            categoriesContainer.innerHTML = ''; // Clear existing categories

            if (!tasks || tasks.length === 0) {
                console.log('No tasks found');
                categoriesContainer.innerHTML = '<p class="text-muted text-center">No tasks found</p>';
                return;
            }

            // Find the active category (first category that's not completed)
            let activeCategory = null;
            const categories = [...new Set(tasks.map(task => task.category_id))];
            for (const catId of categories) {
                const categoryTasks = tasks.filter(t => t.category_id === catId);
                const isCompleted = categoryTasks.every(t => t.status === 'completed');
                if (!isCompleted) {
                    activeCategory = catId;
                    break;
                }
            }

            // Group tasks by category
            const tasksByCategory = {};
            tasks.forEach(task => {
                if (!tasksByCategory[task.category_id]) {
                    tasksByCategory[task.category_id] = {
                        name: task.category,
                        tasks: [],
                        status: 'pending'
                    };
                }
                tasksByCategory[task.category_id].tasks.push(task);
                
                // Calculate category status
                const allTasks = tasksByCategory[task.category_id].tasks;
                const completedTasks = allTasks.filter(t => t.status === 'completed').length;
                if (completedTasks === allTasks.length) {
                    tasksByCategory[task.category_id].status = 'completed';
                } else if (completedTasks > 0) {
                    tasksByCategory[task.category_id].status = 'in progress';
                }
            });

            // Create accordion items for each category
            Object.keys(tasksByCategory).forEach((categoryId, index) => {
                const category = tasksByCategory[categoryId];
                const isActive = categoryId === activeCategory;
                const statusClass = getCategoryStatusClass(category.status);
                
                const accordionItem = `
                    <div class="accordion-item ${isActive ? 'border-primary' : ''}">
                        <h2 class="accordion-header">
                            <button class="accordion-button ${isActive ? '' : 'collapsed'} ${isActive ? 'bg-light' : ''}" 
                                    type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#category${categoryId}">
                                <div class="d-flex align-items-center justify-content-between w-100">
                                    <div>
                                        <span class="badge bg-primary me-2">${index + 1}</span>
                                        ${category.name}
                                    </div>
                                    <div class="ms-auto">
                                        <span class="badge ${statusClass} ms-2">
                                            ${category.status.toUpperCase()}
                                        </span>
                                        ${isActive ? '<span class="badge bg-warning ms-2">CURRENT</span>' : ''}
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="category${categoryId}" 
                             class="accordion-collapse collapse ${isActive ? 'show' : ''}"
                             data-bs-parent="#taskCategories">
                            <div class="accordion-body ${isActive ? 'bg-light' : ''}">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Task</th>
                                                <th>Assigned To</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${category.tasks.map(task => {
                                                // Split assigned names into an array and create a formatted list
                                                const assignedNames = task.assigned_to ? task.assigned_to.split(',').map(name => 
                                                    `<div class="assigned-person">${name.trim()}</div>`
                                                ).join('') : '<div class="text-muted">Unassigned</div>';

                                                return `
                                <tr>
                                    <td>${task.task_name}</td>
                                                        <td class="assigned-names">
                                                            ${assignedNames}
                                                        </td>
                                    <td>${new Date(task.due_date).toLocaleDateString()}</td>
                                    <td>
                                        <span class="badge ${getStatusClass(task.status)}">
                                                                ${task.status.toUpperCase()}
                                        </span>
                                    </td>
                                </tr>
                            `;
                                            }).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                categoriesContainer.innerHTML += accordionItem;
            });
        }

        function updateQuotation(quotationFile) {
            const quotationSection = document.getElementById('projectQuotation');
            if (quotationFile) {
                quotationSection.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-pdf text-danger me-2"></i>
                        <a href="../uploads/quotations/${quotationFile}" 
                           target="_blank" class="text-decoration-none">
                            View Quotation
                        </a>
                    </div>
                `;
            } else {
                quotationSection.innerHTML = '<p class="text-muted">No quotation file available</p>';
            }
        }

        function getStatusClass(status) {
            return {
                'completed': 'bg-success',
                'ongoing': 'bg-primary',
                'pending': 'bg-warning'
            }[status.toLowerCase()] || 'bg-secondary';
        }

        // Add this new function for category status colors
        function getCategoryStatusClass(status) {
            return {
                'completed': 'bg-success',
                'in progress': 'bg-info',
                'pending': 'bg-secondary'
            }[status.toLowerCase()] || 'bg-secondary';
        }
    });
    </script>
</body>
</html> 