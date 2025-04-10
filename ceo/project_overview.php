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
                    <div class="search-container">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="projectSearch" class="form-control" placeholder="Search projects...">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 25%">Project</th>
                                    <th style="width: 15%">Client</th>
                                    <th style="width: 25%">Team Members</th>
                                    <th style="width: 15%">Progress</th>
                                    <th style="width: 10%">Deadline</th>
                                    <th style="width: 10%">Status</th>
                                    <th style="width: 10%">Actions</th>
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

    <!-- Project Details Modal -->
    <div class="modal fade" id="projectDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-project-diagram me-2"></i>
                        <span id="modalProjectName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Client Information</h6>
                            <p class="mb-1" id="modalClientName"></p>
                            <p class="mb-0" id="modalClientContact"></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Project Status</h6>
                            <p class="mb-1">Status: <span id="modalProjectStatus"></span></p>
                            <p class="mb-0">Deadline: <span id="modalProjectDeadline"></span></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="text-muted mb-2">Team Members</h6>
                            <div id="modalTeamMembers" class="d-flex flex-wrap gap-2">
                                <!-- Team members will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted mb-2">Progress</h6>
                            <div class="progress mb-2" style="height: 20px;">
                                <div id="modalProgressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <p class="text-center mb-0" id="modalProgressText"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        loadProjectData();

        // Search functionality
        const searchInput = document.getElementById('projectSearch');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            document.querySelectorAll('#projectOverviewTable tr').forEach(row => {
                const projectName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                const clientName = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const teamMembers = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const progress = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                const deadline = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';
                
                if (projectName.includes(searchTerm) || 
                    clientName.includes(searchTerm) || 
                    teamMembers.includes(searchTerm) || 
                    progress.includes(searchTerm) || 
                    deadline.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
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
                    return `<span class="badge role-badge" title="${role}">${name}</span>`;
                }).join(' ') : 'No team assigned';

            const endDate = new Date(project.end_date);
            const isOverdue = endDate < new Date() && project.status !== 'completed';

            return `
                <tr data-status="${project.status}" data-project='${JSON.stringify(project)}'>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" title="${project.service}">
                            ${project.service}
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px;" title="${project.client_name}">
                            ${project.client_name}
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" title="${teamMembers}">
                            ${teamMembers}
                        </div>
                    </td>
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
                        <span class="text-${isOverdue ? 'danger' : 'muted'}" title="${endDate.toLocaleDateString()}">
                            ${endDate.toLocaleDateString()}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-${project.status === 'ongoing' ? 'warning' : 'success'}">
                            ${project.status}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewProjectDetails(this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function viewProjectDetails(button) {
        const row = button.closest('tr');
        const project = JSON.parse(row.dataset.project);
        const modal = new bootstrap.Modal(document.getElementById('projectDetailsModal'));

        // Update modal content
        document.getElementById('modalProjectName').textContent = project.service;
        document.getElementById('modalClientName').textContent = project.client_name;
        document.getElementById('modalProjectStatus').innerHTML = `
            <span class="badge bg-${project.status === 'ongoing' ? 'warning' : 'success'}">
                ${project.status}
            </span>
        `;
        document.getElementById('modalProjectDeadline').textContent = new Date(project.end_date).toLocaleDateString();
        
        // Update team members
        const teamMembersContainer = document.getElementById('modalTeamMembers');
        teamMembersContainer.innerHTML = project.team_members ? 
            project.team_members.split('|').map(member => {
                const [name, role] = member.split(':');
                return `
                    <div class="badge role-badge p-2">
                        ${name}
                        <small class="d-block">${role}</small>
                    </div>
                `;
            }).join('') : 
            '<p class="text-muted mb-0">No team assigned</p>';

        // Update progress
        const progressBar = document.getElementById('modalProgressBar');
        progressBar.style.width = `${project.progress}%`;
        progressBar.className = `progress-bar ${project.progress >= 100 ? 'bg-success' : ''}`;
        document.getElementById('modalProgressText').textContent = `${Math.round(project.progress)}% Complete`;

        modal.show();
    }

    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-3';
        errorDiv.textContent = message;
        document.querySelector('.card-body').prepend(errorDiv);
    }
    </script>

    <style>
    .text-truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .badge {
        font-size: 0.85rem;
    }

    .role-badge {
        background-color: transparent;
        border: none;
        color: #000000;
        font-weight: normal;
        padding: 0.25rem 0.5rem;
    }

    .modal-body .badge {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
    }

    .progress {
        background-color: #e9ecef;
    }

    .search-container {
        width: 300px;
    }

    .search-container .input-group {
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .search-container .input-group-text {
        border: none;
        border-right: 1px solid #e9ecef;
    }

    .search-container .form-control {
        border: none;
        padding: 0.5rem 1rem;
    }

    .search-container .form-control:focus {
        box-shadow: none;
    }
    </style>
</body>
</html>
