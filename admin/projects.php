<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Get all projects
$query = "SELECT p.*, u.name as client_name FROM projects p JOIN users u ON p.client_id = u.user_id ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects | Admin Dashboard</title>

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
<body id="adminProjectsPage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Projects</h4>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#selectAppointmentModal">
                        <i class="fas fa-plus"></i> Add Project
                    </button>
                </div>
            </div>

            <!-- Projects Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($projects) > 0): ?>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                            <td><?php echo htmlspecialchars($project['service']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($project['date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $project['status'] === 'completed' ? 'success' : 'primary'; ?>">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-secondary" onclick="viewProject(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No projects found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Select Appointment Modal -->
    <div class="modal fade" id="selectAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Confirmed Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get confirmed appointments
                                $query = "
                                    SELECT 
                                        a.*,
                                        u.name as client_name
                                    FROM appointments a
                                    JOIN users u ON a.client_id = u.user_id
                                    WHERE a.status = 'confirmed'
                                    ORDER BY a.date ASC
                                ";
                                $stmt = $pdo->prepare($query);
                                $stmt->execute();
                                $confirmed_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php if (count($confirmed_appointments) > 0): ?>
                                    <?php foreach ($confirmed_appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['service']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($appointment['date'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="addToProjects(<?php echo htmlspecialchars(json_encode($appointment)); ?>)">
                                                    Select
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No confirmed appointments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Project Modal -->
    <div class="modal fade" id="viewProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Project Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">Client:</label>
                        <span id="modalClientName"></span>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Service:</label>
                        <span id="modalService"></span>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Date:</label>
                        <span id="modalDate"></span>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Status:</label>
                        <span id="modalStatus"></span>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Notes:</label>
                        <textarea class="form-control" id="modalNotes" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Quotation File:</label>
                        <div id="quotationFileSection">
                            <div id="currentQuotationFile" class="mb-2" style="display: none;">
                                <a href="#" target="_blank" id="quotationFileLink">View Current File</a>
                                <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeQuotationFile()">Remove</button>
                            </div>
                            <div id="uploadQuotationFile">
                                <input type="file" class="form-control" id="quotationFileInput" accept=".pdf">
                                <small class="text-muted">Accepted format: PDF</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="updateProject()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 