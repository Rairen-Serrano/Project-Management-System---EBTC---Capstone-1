<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    header('Location: ../admin_login.php');
    exit;
}

// Get project statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_projects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_projects
    FROM projects
");
$stmt->execute();
$project_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get team member statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT pa.user_id) as total_members,
        COUNT(DISTINCT CASE WHEN u.role = 'engineer' THEN u.user_id END) as engineers,
        COUNT(DISTINCT CASE WHEN u.role = 'technician' THEN u.user_id END) as technicians,
        COUNT(DISTINCT CASE WHEN u.role = 'worker' THEN u.user_id END) as workers
    FROM project_assignees pa
    JOIN users u ON pa.user_id = u.user_id
");
$stmt->execute();
$team_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent projects
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.name as client_name,
        COUNT(DISTINCT t.task_id) as total_tasks,
        COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.task_id END) as completed_tasks,
        GROUP_CONCAT(DISTINCT CONCAT(up.name, '|', up.role) SEPARATOR '||') as team_members
    FROM projects p
    JOIN users u ON p.client_id = u.user_id
    LEFT JOIN tasks t ON p.project_id = t.project_id
    LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
    LEFT JOIN users up ON pa.user_id = up.user_id
    WHERE p.status != 'archived'
    GROUP BY p.project_id
    ORDER BY p.start_date DESC
    LIMIT 5
");
$stmt->execute();
$recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly project statistics for the graph
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(start_date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM projects
    WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(start_date, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute();
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get project creation timeline data
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as day,
        COUNT(*) as project_count
    FROM projects
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$stmt->execute();
$project_timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill in missing days with zero counts
$timeline_data = [];
$end_date = new DateTime();
$start_date = new DateTime('-30 days');

while ($start_date <= $end_date) {
    $current_date = $start_date->format('Y-m-d');
    $timeline_data[$current_date] = 0;
    $start_date->modify('+1 day');
}

foreach ($project_timeline as $data) {
    $timeline_data[$data['day']] = $data['project_count'];
}

$project_timeline = [];
foreach ($timeline_data as $day => $count) {
    $project_timeline[] = [
        'day' => $day,
        'project_count' => $count
    ];
}

// Get team workload
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        u.role,
        COUNT(DISTINCT t.task_id) as assigned_tasks,
        COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.task_id END) as completed_tasks
    FROM users u
    JOIN task_assignees ta ON u.user_id = ta.user_id
    JOIN tasks t ON ta.task_id = t.task_id
    WHERE t.due_date >= CURDATE()
    GROUP BY u.user_id
    ORDER BY assigned_tasks DESC
    LIMIT 5
");
$stmt->execute();
$team_workload = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Manager</title>
    
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

    <style>
    .stat-card {
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .project-progress {
        height: 8px;
    }
    .team-member-avatar {
        width: 40px;
        height: 40px;
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
                    <h3 class="mb-1">Dashboard</h3>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                </div>
                <div>
                    <a href="projects.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Project
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <!-- Project Statistics -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 border-primary border-opacity-25">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Projects</h6>
                                    <h2 class="mb-0 fw-bold text-primary"><?php echo $project_stats['total_projects']; ?></h2>
                                </div>
                                <div class="card-icon text-primary">
                                    <i class="fas fa-project-diagram fa-2x"></i>
                                </div>
                            </div>
                            <div class="mt-3 small">
                                <span class="text-success me-2">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo $project_stats['completed_projects']; ?> Completed
                                </span>
                                <span class="text-primary">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $project_stats['ongoing_projects']; ?> Ongoing
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Statistics -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 border-success border-opacity-25">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Team Members</h6>
                                    <h2 class="mb-0 fw-bold text-success"><?php echo $team_stats['total_members']; ?></h2>
                                </div>
                                <div class="card-icon text-success">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                            <div class="mt-3 small">
                                <span class="me-2">
                                    <i class="fas fa-user-tie"></i>
                                    <?php echo $team_stats['engineers']; ?> Engineers
                                </span>
                                <span>
                                    <i class="fas fa-user-cog"></i>
                                    <?php echo $team_stats['technicians']; ?> Technicians
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Task Statistics -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 border-warning border-opacity-25">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Active Tasks</h6>
                                    <h2 class="mb-0 fw-bold text-warning" id="activeTasks">-</h2>
                                </div>
                                <div class="card-icon text-warning">
                                    <i class="fas fa-tasks fa-2x"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="progress project-progress">
                                    <div class="progress-bar bg-warning" id="taskProgress" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deadline Statistics -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 border-info border-opacity-25">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Upcoming Deadlines</h6>
                                    <h2 class="mb-0 fw-bold text-info" id="upcomingDeadlines">-</h2>
                                </div>
                                <div class="card-icon text-info">
                                    <i class="fas fa-calendar-alt fa-2x"></i>
                                </div>
                            </div>
                            <div class="mt-3 small" id="nextDeadline">
                                Loading next deadline...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Recent Projects -->
            <div class="row g-4">
                <!-- Project Progress Chart -->
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Project Progress Overview</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="projectProgressChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Project Creation Timeline -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Project Creation Timeline</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="projectTimelineChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Projects</h5>
                                <a href="projects.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Client</th>
                                            <th>Team</th>
                                            <th>Progress</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_projects as $project): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($project['service']); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($project['start_date'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        if ($project['team_members']) {
                                                            $members = array_slice(explode('||', $project['team_members']), 0, 3);
                                                            foreach ($members as $index => $member):
                                                                list($name, $role) = explode('|', $member);
                                                        ?>
                                                            <div class="team-member-avatar <?php echo $index > 0 ? 'ms-n2' : ''; ?>" 
                                                                 data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($name); ?>">
                                                                <i class="fas fa-user-circle"></i>
                                                            </div>
                                                        <?php 
                                                            endforeach;
                                                            $total_members = count(explode('||', $project['team_members']));
                                                            if ($total_members > 3):
                                                        ?>
                                                            <div class="ms-2 small text-muted">
                                                                +<?php echo $total_members - 3; ?> more
                                                            </div>
                                                        <?php
                                                            endif;
                                                        } else {
                                                            echo '<span class="text-muted">No team assigned</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td style="width: 15%;">
                                                    <?php
                                                    $progress = $project['total_tasks'] > 0 
                                                        ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) 
                                                        : 0;
                                                    ?>
                                                    <div class="progress project-progress">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo $progress; ?>% Complete</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $project['status'] === 'ongoing' ? 'primary' : 
                                                            ($project['status'] === 'completed' ? 'success' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($project['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="task_management.php?project_id=<?php echo $project['project_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        Manage Tasks
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));

        // Initialize charts
        initializeCharts();
    });

    function initializeCharts() {
        // Project Progress Chart
        const monthlyStats = <?php echo json_encode($monthly_stats); ?>;
        const months = monthlyStats.map(stat => {
            const date = new Date(stat.month + '-01');
            return date.toLocaleDateString('default', { month: 'short', year: 'numeric' });
        });
        const totalProjects = monthlyStats.map(stat => stat.total);
        const completedProjects = monthlyStats.map(stat => stat.completed);

        new Chart(document.getElementById('projectProgressChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Total Projects',
                        data: totalProjects,
                        borderColor: '#0d6efd',
                        tension: 0.4,
                        fill: false
                    },
                    {
                        label: 'Completed Projects',
                        data: completedProjects,
                        borderColor: '#198754',
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Project Timeline Chart
        const projectTimeline = <?php echo json_encode($project_timeline); ?>;
        const timelineDays = projectTimeline.map(data => {
            const date = new Date(data.day);
            return date.toLocaleDateString('default', { month: 'short', day: 'numeric' });
        });
        const projectCounts = projectTimeline.map(data => data.project_count);

        new Chart(document.getElementById('projectTimelineChart'), {
            type: 'line',
            data: {
                labels: timelineDays,
                datasets: [{
                    label: 'New Projects',
                    data: projectCounts,
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            title: (tooltipItems) => {
                                const date = new Date(projectTimeline[tooltipItems[0].dataIndex].day);
                                return date.toLocaleDateString('default', { 
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long', 
                                    day: 'numeric'
                                });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Function to update task statistics
    async function updateTaskStats() {
        try {
            const response = await fetch('api/dashboard_stats.php');
            const data = await response.json();

            if (data.success) {
                document.getElementById('activeTasks').textContent = data.active_tasks;
                document.getElementById('taskProgress').style.width = data.completion_rate + '%';
                
                if (data.next_deadline) {
                    document.getElementById('upcomingDeadlines').textContent = data.upcoming_deadlines;
                    document.getElementById('nextDeadline').innerHTML = `
                        <i class="fas fa-clock text-info me-1"></i>
                        Next: ${new Date(data.next_deadline).toLocaleDateString()}
                    `;
                }
            }
        } catch (error) {
            console.error('Error updating task stats:', error);
        }
    }

    // Update task statistics every 5 minutes
    updateTaskStats();
    setInterval(updateTaskStats, 300000);
    </script>
</body>
</html> 