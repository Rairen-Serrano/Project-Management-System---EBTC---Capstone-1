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
    <title>Clients | EBTC PMS</title>
    
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
                        <i class="fas fa-user-tie me-2"></i>Client Information
                    </h5>
                    <div class="search-container">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="clientSearch" class="form-control" placeholder="Search clients...">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 20%">Client Name</th>
                                    <th style="width: 20%">Email</th>
                                    <th style="width: 15%">Contact Number</th>
                                    <th style="width: 15%">Department</th>
                                    <th style="width: 15%">Projects</th>
                                    <th style="width: 10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="clientTable">
                                <!-- Client data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Details Modal -->
    <div class="modal fade" id="clientDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tie me-2"></i>
                        <span id="modalClientName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center mb-3 mb-md-0">
                            <div class="client-avatar mb-3">
                                <i class="fas fa-user-circle fa-4x text-primary"></i>
                            </div>
                            <h5 id="modalClientName2" class="mb-1"></h5>
                            <p class="text-muted mb-0" id="modalClientJobTitle"></p>
                        </div>
                        <div class="col-md-8">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Contact Information
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <p class="mb-1 text-muted small">Email</p>
                                            <p class="mb-0" id="modalClientEmail"></p>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="mb-1 text-muted small">Phone</p>
                                            <p class="mb-0" id="modalClientPhone"></p>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="mb-1 text-muted small">Department</p>
                                            <p class="mb-0" id="modalClientDepartment"></p>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="mb-1 text-muted small">Job Title</p>
                                            <p class="mb-0" id="modalClientJobTitle2"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-3">
                                        <i class="fas fa-clock me-2"></i>Account Information
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <p class="mb-1 text-muted small">Joined Date</p>
                                            <p class="mb-0" id="modalClientJoined"></p>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="mb-1 text-muted small">Last Login</p>
                                            <p class="mb-0" id="modalClientLastLogin"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0 text-primary">
                                        <i class="fas fa-project-diagram me-2"></i>Projects
                                    </h6>
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
        loadClientData();

        // Search functionality
        const searchInput = document.getElementById('clientSearch');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            document.querySelectorAll('#clientTable tr').forEach(row => {
                const clientName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                const clientEmail = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const clientPhone = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const clientDepartment = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                const clientProjects = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';
                
                if (clientName.includes(searchTerm) || 
                    clientEmail.includes(searchTerm) || 
                    clientPhone.includes(searchTerm) || 
                    clientDepartment.includes(searchTerm) || 
                    clientProjects.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    function loadClientData() {
        fetch('../api/ceo/client_data.php')
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data.clients) {
                    updateClientTable(result.data.clients);
                }
            })
            .catch(error => {
                console.error('Error loading client data:', error);
                showError('Error loading client data. Please try refreshing the page.');
            });
    }

    function updateClientTable(clients) {
        const clientTable = document.getElementById('clientTable');
        if (!clientTable) return;

        clientTable.innerHTML = clients.map(client => {
            return `
                <tr data-client='${JSON.stringify(client)}'>
                    <td>
                        <div class="d-flex align-items-center">
                            <div>
                                <h6 class="mb-0">${client.name}</h6>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" title="${client.email}">
                            ${client.email}
                        </div>
                    </td>
                    <td>${client.phone || 'N/A'}</td>
                    <td>
                        <div class="text-truncate" style="max-width: 150px;" title="${client.department || 'N/A'}">
                            ${client.department || 'N/A'}
                        </div>
                    </td>
                    <td>${client.project_count || 0}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewClientDetails(this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function viewClientDetails(button) {
        const row = button.closest('tr');
        const client = JSON.parse(row.dataset.client);
        const modal = new bootstrap.Modal(document.getElementById('clientDetailsModal'));

        // Update modal content
        document.getElementById('modalClientName').textContent = client.name;
        document.getElementById('modalClientName2').textContent = client.name;
        document.getElementById('modalClientEmail').textContent = client.email;
        document.getElementById('modalClientPhone').textContent = client.phone || 'N/A';
        document.getElementById('modalClientDepartment').textContent = client.department || 'N/A';
        document.getElementById('modalClientJobTitle').textContent = client.job_title || 'N/A';
        document.getElementById('modalClientJobTitle2').textContent = client.job_title || 'N/A';
        document.getElementById('modalClientJoined').textContent = new Date(client.date_created).toLocaleDateString();
        document.getElementById('modalClientLastLogin').textContent = 'N/A';
        
        // Update projects list
        const projectList = document.getElementById('modalProjectList');
        if (client.projects && client.projects.length > 0) {
            projectList.innerHTML = client.projects.map(project => {
                return `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${project.service}</h6>
                                <small class="text-muted">Started: ${new Date(project.start_date).toLocaleDateString()}</small>
                            </div>
                            <span class="badge bg-${project.status === 'ongoing' ? 'warning' : 'success'}">
                                ${project.status}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            projectList.innerHTML = '<div class="list-group-item"><p class="text-muted mb-0 text-center">No projects found</p></div>';
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

    .client-avatar {
        width: 100px;
        height: 100px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        border-radius: 50%;
    }

    .modal-body .card {
        border: 1px solid rgba(0,0,0,.125);
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
    }

    .modal-body .card-title {
        font-size: 1rem;
        font-weight: 600;
    }

    .modal-body .text-muted.small {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #modalProjectList .list-group-item {
        border-left: none;
        border-right: none;
        padding: 1rem;
    }

    #modalProjectList .list-group-item:first-child {
        border-top: none;
    }

    #modalProjectList .list-group-item:last-child {
        border-bottom: none;
    }

    #modalProjectList .badge {
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
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