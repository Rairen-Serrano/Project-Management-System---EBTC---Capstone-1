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
    <title>Employee Status | EBTC PMS</title>
    
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
                        <i class="fas fa-users me-2"></i>Employee Status
                    </h5>
                    <div class="search-container">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="employeeSearch" class="form-control" placeholder="Search employees...">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 30%">Employee</th>
                                    <th style="width: 50%">Current Projects</th>
                                    <th style="width: 20%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="employeeStatusTable">
                                <!-- Employee data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        <span id="modalEmployeeName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <div class="employee-avatar mb-3">
                                <i class="fas fa-user-circle fa-4x text-primary"></i>
                            </div>
                            <h5 id="modalEmployeeNameAvatar" class="mb-1"></h5>
                            <p class="text-muted mb-0" id="modalEmployeeRoleBadge"></p>
                        </div>
                        <div class="col-md-8">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Contact Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <i class="fas fa-envelope text-primary me-2"></i>
                                                <strong>Email:</strong><br>
                                                <span id="modalEmployeeEmail"></span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <i class="fas fa-phone text-primary me-2"></i>
                                                <strong>Phone:</strong><br>
                                                <span id="modalEmployeePhone">N/A</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Project Statistics</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <div class="stat-card bg-primary bg-opacity-10 p-3 rounded">
                                                <h3 id="modalTotalProjects" class="text-primary mb-0">0</h3>
                                                <p class="text-muted mb-0">Total Projects</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="stat-card bg-warning bg-opacity-10 p-3 rounded">
                                                <h3 id="modalActiveProjects" class="text-warning mb-0">0</h3>
                                                <p class="text-muted mb-0">Active Projects</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="stat-card bg-success bg-opacity-10 p-3 rounded">
                                                <h3 id="modalCompletedProjects" class="text-success mb-0">0</h3>
                                                <p class="text-muted mb-0">Completed Projects</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Current Projects</h6>
                                    <span class="badge bg-primary" id="modalProjectCount">0 Projects</span>
                                </div>
                                <div class="card-body p-0">
                                    <div id="modalProjectList" class="list-group list-group-flush">
                                        <!-- Projects will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        loadEmployeeData();

        // Search functionality
        const searchInput = document.getElementById('employeeSearch');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            document.querySelectorAll('#employeeStatusTable tr').forEach(row => {
                const employeeName = row.querySelector('h6')?.textContent.toLowerCase() || '';
                const employeeRole = row.querySelector('.role-badge')?.textContent.toLowerCase() || '';
                const projects = row.querySelector('.projects-container')?.textContent.toLowerCase() || '';
                
                if (employeeName.includes(searchTerm) || 
                    employeeRole.includes(searchTerm) || 
                    projects.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    function loadEmployeeData() {
        fetch('../api/ceo/dashboard_data.php')
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data.employee_status) {
                    updateEmployeeStatus(result.data.employee_status);
                }
            })
            .catch(error => {
                console.error('Error loading employee data:', error);
                showError('Error loading employee data. Please try refreshing the page.');
            });
    }

    function updateEmployeeStatus(employeeStatus) {
        const statusTable = document.getElementById('employeeStatusTable');
        if (!statusTable) return;

        statusTable.innerHTML = employeeStatus.map(employee => {
            const projects = employee.project_details ? 
                employee.project_details.split('|').map(project => {
                    const [name, status] = project.split(' (');
                    const statusClass = status.includes('ongoing') ? 'warning' : 'success';
                    return `
                        <div class="project-item mb-2">
                            <div class="d-flex align-items-center">
                                <div class="project-status-indicator bg-${statusClass} me-2"></div>
                                <div class="project-info">
                                    <div class="project-name">${name}</div>
                                    <div class="project-status text-muted small">${status.replace(')', '')}</div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('') : 
                '<div class="text-muted"><i class="fas fa-info-circle me-1"></i> No active projects</div>';

            return `
                <tr data-role="${employee.role}" data-employee='${JSON.stringify(employee)}'>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="employee-avatar-small me-3">
                                <i class="fas fa-user-circle text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">${employee.name}</h6>
                                <span class="badge role-badge">${employee.role}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="projects-container">
                            ${projects}
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewEmployeeDetails(this)">
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function viewEmployeeDetails(button) {
        const row = button.closest('tr');
        const employee = JSON.parse(row.dataset.employee);
        const modal = new bootstrap.Modal(document.getElementById('employeeDetailsModal'));

        // Update modal content
        document.getElementById('modalEmployeeName').textContent = employee.name;
        document.getElementById('modalEmployeeNameAvatar').textContent = employee.name;
        document.getElementById('modalEmployeeRoleBadge').innerHTML = `
            <span class="badge role-badge">${employee.role}</span>
        `;
        document.getElementById('modalEmployeeEmail').textContent = employee.email || 'N/A';
        
        // Update project statistics
        const projectList = document.getElementById('modalProjectList');
        if (employee.project_details) {
            const projects = employee.project_details.split('|');
            const totalProjects = projects.length;
            const activeProjects = projects.filter(p => p.includes('ongoing')).length;
            const completedProjects = projects.filter(p => p.includes('completed')).length;
            
            document.getElementById('modalTotalProjects').textContent = totalProjects;
            document.getElementById('modalActiveProjects').textContent = activeProjects;
            document.getElementById('modalCompletedProjects').textContent = completedProjects;
            document.getElementById('modalProjectCount').textContent = `${totalProjects} Projects`;

            projectList.innerHTML = projects.map(project => {
                const [name, status] = project.split(' (');
                const statusClass = status.includes('ongoing') ? 'warning' : 'success';
                return `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${name}</h6>
                                <small class="text-muted">Project Details</small>
                            </div>
                            <span class="badge bg-${statusClass}">${status.replace(')', '')}</span>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            projectList.innerHTML = '<div class="list-group-item text-center py-4"><p class="text-muted mb-0">No projects assigned</p></div>';
            document.getElementById('modalTotalProjects').textContent = '0';
            document.getElementById('modalActiveProjects').textContent = '0';
            document.getElementById('modalCompletedProjects').textContent = '0';
            document.getElementById('modalProjectCount').textContent = '0 Projects';
        }

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
        color: #6c757d;
        font-weight: normal;
        padding: 0.25rem 0.5rem;
    }

    .modal-body .badge {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
    }

    .list-group-item {
        border-left: none;
        border-right: none;
    }

    .list-group-item:first-child {
        border-top: none;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    /* New styles for enhanced UI */
    .employee-avatar {
        width: 80px;
        height: 80px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        border-radius: 50%;
    }

    .employee-avatar-small {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        border-radius: 50%;
    }

    .projects-container {
        max-height: 120px;
        overflow-y: auto;
        padding-right: 10px;
    }

    .project-item {
        padding: 8px;
        border-radius: 4px;
        background-color: #f8f9fa;
        margin-bottom: 8px;
    }

    .project-status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    .project-info {
        flex: 1;
    }

    .project-name {
        font-weight: 500;
        margin-bottom: 2px;
    }

    .project-status {
        font-size: 0.8rem;
    }

    .stat-card {
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    /* Custom scrollbar for projects container */
    .projects-container::-webkit-scrollbar {
        width: 6px;
    }

    .projects-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .projects-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    .projects-container::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
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
