<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an technician
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'technician') {
    header('Location: ../admin_login.php');
    exit;
}

// Get projects assigned to the technician
$stmt = $pdo->prepare("
    WITH ProjectStats AS (
        SELECT 
            p.project_id,
            p.service as project_name,
            p.start_date,
            p.end_date,
            p.status,
            u.name as client_name,
            (
                SELECT COUNT(*)
                FROM tasks 
                WHERE project_id = p.project_id
            ) as total_tasks,
            (
                SELECT COUNT(*)
                FROM tasks 
                WHERE project_id = p.project_id 
                AND status = 'completed'
            ) as completed_tasks,
            (
                SELECT GROUP_CONCAT(DISTINCT u.name)
                FROM task_assignees ta_inner
                JOIN tasks t_inner ON ta_inner.task_id = t_inner.task_id
                JOIN users u ON ta_inner.user_id = u.user_id
                WHERE t_inner.project_id = p.project_id
            ) as team_members_names,
            (
                SELECT COUNT(DISTINCT ta_inner.user_id)
                FROM task_assignees ta_inner
                JOIN tasks t_inner ON ta_inner.task_id = t_inner.task_id
                WHERE t_inner.project_id = p.project_id
            ) as team_members_count
        FROM projects p
        JOIN users u ON p.client_id = u.user_id
        JOIN tasks t ON t.project_id = p.project_id
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE ta.user_id = :user_id
        GROUP BY p.project_id, p.service, p.start_date, p.end_date, p.status, u.name
    )
    SELECT 
        *,
        CASE 
            WHEN total_tasks > 0 THEN ROUND((completed_tasks / total_tasks) * 100)
            ELSE 0 
        END as progress
    FROM ProjectStats
    ORDER BY 
        CASE 
            WHEN status = 'ongoing' THEN 1
            WHEN status = 'completed' THEN 2
            ELSE 3
        END,
        start_date DESC
");

$stmt->execute(['user_id' => $_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects | Technician Dashboard</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .project-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .project-card:hover {
            transform: translateY(-5px);
        }
        .progress {
            height: 8px;
        }
        .project-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        .stat-item {
            text-align: center;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .timeline-item {
            position: relative;
            padding-left: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #235347;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <div class="engineer-dashboard-wrapper">
        <?php include 'technician_header.php'; ?>
        
        <div class="engineer-main-content">
            <div class="container-fluid px-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="mb-1">My Projects</h3>
                        <p class="text-muted mb-0">Overview of all projects assigned to you</p>
                    </div>
                </div>

                <!-- Projects Grid -->
                <div class="row g-4">
                    <?php foreach ($projects as $project): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="card project-card">
                                <div class="card-body">
                                    <!-- Project Header -->
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($project['project_name']); ?></h5>
                                            <p class="text-muted mb-0">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($project['client_name']); ?>
                                            </p>
                                        </div>
                                        <span class="badge <?php echo $project['status'] === 'ongoing' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </div>

                                    <!-- Project Progress -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted">Progress</span>
                                            <span class="fw-bold"><?php echo $project['progress']; ?>%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: <?php echo $project['progress']; ?>%" 
                                                aria-valuenow="<?php echo $project['progress']; ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Project Stats -->
                                    <div class="project-stats mb-3">
                                        <div class="stat-item">
                                            <h6 class="mb-1"><?php echo $project['total_tasks']; ?></h6>
                                            <small class="text-muted">Total Tasks</small>
                                        </div>
                                        <div class="stat-item">
                                            <h6 class="mb-1"><?php echo $project['completed_tasks']; ?></h6>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                        <div class="stat-item">
                                            <h6 class="mb-1"><?php echo $project['team_members_count']; ?></h6>
                                            <small class="text-muted">Team Members</small>
                                        </div>
                                    </div>

                                    <!-- Team Members Section -->
                                    <div class="team-members mb-3">
                                        <h6 class="text-muted mb-2">Team Members</h6>
                                        <div class="team-list">
                                            <?php 
                                            $team_members = explode(',', $project['team_members_names']);
                                            foreach ($team_members as $member): ?>
                                                <div class="team-member">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <?php echo htmlspecialchars(trim($member)); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Project Timeline -->
                                    <div class="timeline-container">
                                        <div class="timeline-item mb-2">
                                            <small class="text-muted">Start Date:</small>
                                            <span class="ms-2"><?php echo date('M j, Y', strtotime($project['start_date'])); ?></span>
                                        </div>
                                        <div class="timeline-item">
                                            <small class="text-muted">End Date:</small>
                                            <span class="ms-2"><?php echo date('M j, Y', strtotime($project['end_date'])); ?></span>
                                        </div>
                                    </div>

                                    <!-- View Tasks Button -->
                                    <div class="mt-3">
                                        <a href="tasks.php?project_id=<?php echo $project['project_id']; ?>" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-tasks me-2"></i>View Tasks
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($projects)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                                    <h5>No Projects Assigned</h5>
                                    <p class="text-muted">You currently don't have any projects assigned to you.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add any JavaScript functionality here if needed
        });
    </script>
</body>
</html> 