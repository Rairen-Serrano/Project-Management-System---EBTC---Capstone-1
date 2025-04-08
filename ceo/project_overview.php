<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a CEO
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'ceo') {
    session_unset();
    session_destroy();
    header('Location: ../admin_login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Overview | EBTC PMS</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="engineer-dashboard-wrapper">
        <?php include 'ceo_header.php'; ?>

        <div class="engineer-main-content">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-project-diagram me-2"></i>Project Overview
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-warning" data-filter="ongoing">Ongoing</button>
                        <button type="button" class="btn btn-outline-success" data-filter="completed">Completed</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Client</th>
                                    <th>Team Members</th>
                                    <th>Progress</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="projectOverviewTable">
                                <!-- Project data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        loadProjectData();

        // Filter buttons
        document.querySelectorAll('[data-filter]').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('[data-filter]').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                
                document.querySelectorAll('#projectOverviewTable tr').forEach(row => {
                    if (filter === 'all' || row.dataset.status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    });

    function loadProjectData() {
        fetch('../api/ceo/dashboard_data.php')
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data.recent_projects) {
                    updateProjectTable(result.data.recent_projects);
                }
            })
            .catch(error => {
                console.error('Error loading project data:', error);
                showError('Error loading project data. Please try refreshing the page.');
            });
    }

    function updateProjectTable(projects) {
        const projectTable = document.getElementById('projectOverviewTable');
        if (!projectTable) return;

        projectTable.innerHTML = projects.map(project => {
            const teamMembers = project.team_members ? 
                project.team_members.split('|').map(member => {
                    const [name, role] = member.split(':');
                    return `<span class="badge bg-${getRoleBadgeColor(role)}" title="${role}">${name}</span>`;
                }).join(' ') : 'No team assigned';

            const endDate = new Date(project.end_date);
            const isOverdue = endDate < new Date() && project.status !== 'completed';

            return `
                <tr data-status="${project.status}">
                    <td><strong>${project.service}</strong></td>
                    <td>${project.client_name}</td>
                    <td>${teamMembers}</td>
                    <td>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar ${project.progress >= 100 ? 'bg-success' : ''}" 
                                 role="progressbar" 
                                 style="width: ${project.progress}%" 
                                 aria-valuenow="${project.progress}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        <small class="text-muted">${Math.round(project.progress)}%</small>
                    </td>
                    <td>
                        <span class="text-${isOverdue ? 'danger' : 'muted'}">
                            ${endDate.toLocaleDateString()}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-${project.status === 'ongoing' ? 'warning' : 'success'}">
                            ${project.status}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function getRoleBadgeColor(role) {
        const colors = {
            'project_manager': 'primary',
            'engineer': 'success',
            'technician': 'info',
            'worker': 'warning'
        };
        return colors[role] || 'secondary';
    }

    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-3';
        errorDiv.textContent = message;
        document.querySelector('.card-body').prepend(errorDiv);
    }
    </script>
</body>
</html>
