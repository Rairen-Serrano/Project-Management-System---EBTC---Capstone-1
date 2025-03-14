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
        GROUP_CONCAT(DISTINCT CONCAT(u.name, '|', u.role, '|', u.email) SEPARATOR '||') as assigned_personnel
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">My Projects</h5>
                        </div>
                        <div class="card-body">
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
                    <h5 class="modal-title">Project Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <!-- Project Information -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Project Information</h6>
                                    <p><strong>Service:</strong> <span id="modalService"></span></p>
                                    <p><strong>Start Date:</strong> <span id="modalStartDate"></span></p>
                                    <p><strong>End Date:</strong> <span id="modalEndDate"></span></p>
                                    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                                    <p class="mt-3"><strong>Notes:</strong></p>
                                    <p id="modalNotes" class="text-muted"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Personnel -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Project Team</h6>
                                    <div id="modalPersonnel">
                                        <!-- Personnel will be dynamically added here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Project Tasks -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Project Tasks</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="modalTasks">
                                            <thead>
                                                <tr>
                                                    <th>Task Name</th>
                                                    <th>Assigned To</th>
                                                    <th>Due Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Tasks will be dynamically added here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quotation File -->
                        <div class="col-12" id="quotationFileSection">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Quotation File</h6>
                                    <div id="modalQuotation">
                                        <!-- Quotation file link will be added here -->
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
        const projectDetailsModal = new bootstrap.Modal(document.getElementById('projectDetailsModal'));

        // Add click event listeners to view buttons
        document.querySelectorAll('.view-project').forEach(button => {
            button.addEventListener('click', function() {
                const projectId = this.getAttribute('data-project-id');
                fetchProjectDetails(projectId);
            });
        });

        function fetchProjectDetails(projectId) {
            console.log('Fetching project:', projectId); // Debug log

            fetch(`get_project_details.php?project_id=${projectId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text); // Debug log
                    return JSON.parse(text);
                })
                .then(data => {
                    console.log('Parsed data:', data); // Debug log
                    
                    // Update modal content
                    document.getElementById('modalService').textContent = data.project.service;
                    document.getElementById('modalStartDate').textContent = new Date(data.project.start_date)
                        .toLocaleDateString();
                    document.getElementById('modalEndDate').textContent = new Date(data.project.end_date)
                        .toLocaleDateString();
                    
                    // Update status badge
                    const statusBadge = `<span class="badge ${getStatusClass(data.project.status)}">
                        ${data.project.status.charAt(0).toUpperCase() + data.project.status.slice(1)}
                    </span>`;
                    document.getElementById('modalStatus').innerHTML = statusBadge;
                    
                    // Update notes
                    document.getElementById('modalNotes').textContent = data.project.notes || 'No notes available';

                    // Update personnel list
                    let personnelHtml = '';
                    if (data.personnel && data.personnel.length > 0) {
                        data.personnel.forEach(person => {
                            personnelHtml += `
                                <div class="mb-3 p-2 border rounded">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle fa-2x text-muted me-2"></i>
                                        <div>
                                            <div class="fw-bold">${person.name}</div>
                                            <div class="text-muted small">${person.role}</div>
                                            <div class="text-muted small">${person.email}</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        personnelHtml = '<p class="text-muted">No personnel assigned</p>';
                    }
                    document.getElementById('modalPersonnel').innerHTML = personnelHtml;

                    // Update tasks table
                    let tasksHtml = '';
                    if (data.tasks && data.tasks.length > 0) {
                        data.tasks.forEach(task => {
                            tasksHtml += `
                                <tr>
                                    <td>${task.task_name}</td>
                                    <td>${task.assigned_to || 'Unassigned'}</td>
                                    <td>${new Date(task.due_date).toLocaleDateString()}</td>
                                    <td>
                                        <span class="badge ${getStatusClass(task.status)}">
                                            ${task.status}
                                        </span>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        tasksHtml = `
                            <tr>
                                <td colspan="4" class="text-center text-muted">No tasks found</td>
                            </tr>
                        `;
                    }
                    document.querySelector('#modalTasks tbody').innerHTML = tasksHtml;

                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('projectDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading project details. Please try again.');
                });
        }

        function getStatusClass(status) {
            return {
                'completed': 'bg-success',
                'ongoing': 'bg-primary',
                'pending': 'bg-warning'
            }[status.toLowerCase()] || 'bg-secondary';
        }
    });
    </script>
</body>
</html> 