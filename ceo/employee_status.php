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
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Current Projects</th>
                                    <th>Status</th>
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
                <tr data-role="${employee.role}">
                    <td>
                        <div class="d-flex align-items-center">
                            <div>
                                <h6 class="mb-0">${employee.name}</h6>
                                <span class="badge bg-${getRoleBadgeColor(employee.role)}">${employee.role}</span>
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
