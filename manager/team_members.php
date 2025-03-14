<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    header('Location: ../admin_login.php');
    exit;
}

// Get all projects with their personnel
$stmt = $pdo->prepare("
    SELECT 
        p.project_id,
        p.service as project_name,
        p.status as project_status,
        p.start_date,
        p.end_date,
        u.name as client_name,
        GROUP_CONCAT(
            DISTINCT CONCAT(up.user_id, '|', up.name, '|', up.role, '|', up.email)
            ORDER BY 
                CASE up.role 
                    WHEN 'engineer' THEN 1
                    WHEN 'technician' THEN 2
                    WHEN 'worker' THEN 3
                    ELSE 4
                END,
                up.name
            SEPARATOR '||'
        ) as team_members
    FROM projects p
    JOIN users u ON p.client_id = u.user_id
    LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
    LEFT JOIN users up ON pa.user_id = up.user_id
    GROUP BY p.project_id
    ORDER BY p.start_date DESC
");
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Members | Manager Dashboard</title>

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
    .team-member-card {
        transition: transform 0.2s;
    }
    .team-member-card:hover {
        transform: translateY(-5px);
    }
    .project-section {
        border-left: 4px solid;
        margin-bottom: 2rem;
        padding-left: 1rem;
    }
    .project-ongoing { border-color: #0d6efd; }
    .project-completed { border-color: #198754; }
    .project-pending { border-color: #ffc107; }
    .role-badge {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
    }
    .member-avatar {
        width: 48px;
        height: 48px;
        background-color: #e9ecef;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
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
                    <h3 class="mb-1">Team Members</h3>
                    <p class="text-muted mb-0">View and manage project team members</p>
                </div>
            </div>

            <!-- Team Overview -->
            <div class="row g-4">
                <?php foreach ($projects as $project): ?>
                    <div class="col-12">
                        <div class="project-section project-<?php echo strtolower($project['project_status']); ?>">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <?php echo htmlspecialchars($project['project_name']); ?>
                                            <span class="badge bg-<?php 
                                                echo $project['project_status'] === 'ongoing' ? 'primary' : 
                                                    ($project['project_status'] === 'completed' ? 'success' : 'warning'); 
                                            ?> ms-2">
                                                <?php echo ucfirst($project['project_status']); ?>
                                            </span>
                                        </h5>
                                        <div>
                                            <button class="btn btn-sm btn-primary" onclick="addTeamMember(<?php echo $project['project_id']; ?>)">
                                                <i class="fas fa-plus me-1"></i>Add Member
                                            </button>
                                        </div>
                                    </div>
                                    <div class="text-muted mt-2">
                                        <small>
                                            <i class="fas fa-user me-1"></i>Client: <?php echo htmlspecialchars($project['client_name']); ?>
                                        </small>
                                        <span class="mx-2">|</span>
                                        <small>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php 
                                                echo date('M d, Y', strtotime($project['start_date'])) . ' - ' . 
                                                     date('M d, Y', strtotime($project['end_date'])); 
                                            ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php 
                                        if ($project['team_members']): 
                                            $members = array_filter(explode('||', $project['team_members']));
                                            foreach ($members as $member):
                                                list($user_id, $name, $role, $email) = explode('|', $member);
                                        ?>
                                            <div class="col-md-4">
                                                <div class="card team-member-card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-center">
                                                            <div class="member-avatar me-3">
                                                                <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($name); ?></h6>
                                                                <span class="badge bg-secondary role-badge">
                                                                    <?php echo ucfirst($role); ?>
                                                                </span>
                                                            </div>
                                                            <div class="dropdown">
                                                                <button class="btn btn-link text-dark" type="button" data-bs-toggle="dropdown">
                                                                    <i class="fas fa-ellipsis-v"></i>
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <a class="dropdown-item" href="mailto:<?php echo $email; ?>">
                                                                            <i class="fas fa-envelope me-2"></i>Contact
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="#" onclick="viewMemberTasks(<?php echo $user_id; ?>, <?php echo $project['project_id']; ?>)">
                                                                            <i class="fas fa-tasks me-2"></i>View Tasks
                                                                        </a>
                                                                    </li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger" href="#" 
                                                                           onclick="removeMember(<?php echo $user_id; ?>, <?php echo $project['project_id']; ?>, '<?php echo htmlspecialchars($name); ?>')">
                                                                            <i class="fas fa-user-minus me-2"></i>Remove
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <small class="text-muted">
                                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($email); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php 
                                            endforeach;
                                        else: 
                                        ?>
                                            <div class="col-12">
                                                <div class="text-center text-muted py-4">
                                                    <i class="fas fa-users fa-3x mb-3"></i>
                                                    <p class="mb-0">No team members assigned to this project yet</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Member Tasks Modal -->
    <div class="modal fade" id="memberTasksModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tasks me-2"></i>Member Tasks
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="memberTasksList">
                        <!-- Tasks will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function addTeamMember(projectId) {
        // Redirect to the project details page with the personnel section focused
        window.location.href = `projects.php?action=view&id=${projectId}&section=personnel`;
    }

    async function viewMemberTasks(userId, projectId) {
        try {
            const response = await fetch(`api/tasks.php?action=member_tasks&project_id=${projectId}&user_id=${userId}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to load tasks');
            }

            const tasksList = document.getElementById('memberTasksList');
            tasksList.innerHTML = '';

            if (!data.tasks || data.tasks.length === 0) {
                tasksList.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-tasks fa-3x mb-3"></i>
                        <p class="mb-0">No tasks assigned to this team member</p>
                    </div>
                `;
                return;
            }

            // Create task list
            const taskTable = document.createElement('table');
            taskTable.className = 'table table-hover align-middle';
            taskTable.innerHTML = `
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Category</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.tasks.map(task => `
                        <tr>
                            <td>
                                <div class="fw-medium">${task.task_name}</div>
                                <small class="text-muted">${task.description || 'No description'}</small>
                            </td>
                            <td>${task.category_name || 'Uncategorized'}</td>
                            <td>${new Date(task.due_date).toLocaleDateString()}</td>
                            <td>
                                <span class="badge bg-${
                                    task.status === 'completed' ? 'success' :
                                    task.status === 'in_progress' ? 'primary' : 'warning'
                                }">
                                    ${task.status.replace('_', ' ')}
                                </span>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            `;
            tasksList.appendChild(taskTable);

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('memberTasksModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading member tasks:', error);
            alert('Failed to load member tasks. Please try again.');
        }
    }

    function removeMember(userId, projectId, memberName) {
        if (!confirm(`Are you sure you want to remove ${memberName} from this project?`)) {
            return;
        }

        fetch('api/personnel.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                project_id: projectId,
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                throw new Error(data.message || 'Failed to remove team member');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message);
        });
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
    </script>
</body>
</html> 