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
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary active" data-role="all">All</button>
                        <button type="button" class="btn btn-outline-info" data-role="project_manager">PM</button>
                        <button type="button" class="btn btn-outline-success" data-role="engineer">Engineers</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 30%">Employee</th>
                                    <th style="width: 40%">Current Projects</th>
                                    <th style="width: 15%">Status</th>
                                    <th style="width: 15%">Actions</th>
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
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        <span id="modalEmployeeName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Employee Information</h6>
                            <p class="mb-1">Role: <span id="modalEmployeeRole"></span></p>
                            <p class="mb-1">Status: <span id="modalEmployeeStatus"></span></p>
                            <p class="mb-0">Email: <span id="modalEmployeeEmail"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Project Statistics</h6>
                            <p class="mb-1">Total Projects: <span id="modalTotalProjects"></span></p>
                            <p class="mb-1">Active Projects: <span id="modalActiveProjects"></span></p>
                            <p class="mb-0">Completed Projects: <span id="modalCompletedProjects"></span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted mb-2">Current Projects</h6>
                            <div id="modalProjectList" class="list-group">
                                <!-- Projects will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        loadEmployeeData();

        // Role filter buttons
        document.querySelectorAll('[data-role]').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('[data-role]').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                const role = this.dataset.role;
                
                document.querySelectorAll('#employeeStatusTable tr').forEach(row => {
                    if (role === 'all' || row.dataset.role === role) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
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
                    return `<div class="text-truncate">
                        <small class="text-muted">• ${name}</small>
                    </div>`;
                }).join('') : 
                '<small class="text-muted">No active projects</small>';

            return `
                <tr data-role="${employee.role}" data-employee='${JSON.stringify(employee)}'>
                    <td>
                        <div class="d-flex align-items-center">
                            <div>
                                <h6 class="mb-0">${employee.name}</h6>
                                <span class="badge role-badge">${employee.role}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="max-height: 60px; overflow-y: auto;">
                            ${projects}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-${employee.user_status === 'active' ? 'success' : 'secondary'}">
                            ${employee.user_status}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewEmployeeDetails(this)">
                            <i class="fas fa-eye"></i>
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
        document.getElementById('modalEmployeeRole').innerHTML = `
            <span class="badge role-badge">${employee.role}</span>
        `;
        document.getElementById('modalEmployeeStatus').innerHTML = `
            <span class="badge bg-${employee.user_status === 'active' ? 'success' : 'secondary'}">
                ${employee.user_status}
            </span>
        `;
        document.getElementById('modalEmployeeEmail').textContent = employee.email || 'N/A';
        
        // Update project statistics
        const projectList = document.getElementById('modalProjectList');
        if (employee.project_details) {
            const projects = employee.project_details.split('|');
            document.getElementById('modalTotalProjects').textContent = projects.length;
            document.getElementById('modalActiveProjects').textContent = 
                projects.filter(p => p.includes('ongoing')).length;
            document.getElementById('modalCompletedProjects').textContent = 
                projects.filter(p => p.includes('completed')).length;

            projectList.innerHTML = projects.map(project => {
                const [name, status] = project.split(' (');
                const statusClass = status.includes('ongoing') ? 'warning' : 'success';
                return `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">${name}</h6>
                            <span class="badge bg-${statusClass}">${status.replace(')', '')}</span>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            projectList.innerHTML = '<p class="text-muted mb-0">No projects assigned</p>';
            document.getElementById('modalTotalProjects').textContent = '0';
            document.getElementById('modalActiveProjects').textContent = '0';
            document.getElementById('modalCompletedProjects').textContent = '0';
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
    </style>
</body>
</html>
