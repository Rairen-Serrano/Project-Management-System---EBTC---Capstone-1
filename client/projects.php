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
        p.quotation_file,
        GROUP_CONCAT(DISTINCT CONCAT(u.name, '|', u.role, '|', u.email) SEPARATOR '||') as assigned_personnel,
        CASE 
            WHEN p.status = 'completed' THEN 100
            WHEN p.status = 'ongoing' THEN 
                CASE 
                    WHEN CURRENT_DATE > p.end_date THEN 90
                    WHEN CURRENT_DATE < p.start_date THEN 0
                    ELSE ROUND(
                        (DATEDIFF(CURRENT_DATE, p.start_date) * 100.0) / 
                        NULLIF(DATEDIFF(p.end_date, p.start_date), 0)
                    )
                END
            ELSE 0
        END as progress
    FROM projects p
    LEFT JOIN project_personnel pp ON p.project_id = pp.project_id
    LEFT JOIN users u ON pp.user_id = u.user_id
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
                                                <th>Progress</th>
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
                                                    <td style="width: 200px;">
                                                        <?php
                                                        $progress = min(100, max(0, $project['progress']));
                                                        $progressClass = 'bg-primary';
                                                        if ($progress == 100) {
                                                            $progressClass = 'bg-success';
                                                        } elseif ($progress >= 75) {
                                                            $progressClass = 'bg-info';
                                                        } elseif ($progress >= 50) {
                                                            $progressClass = 'bg-primary';
                                                        } elseif ($progress >= 25) {
                                                            $progressClass = 'bg-warning';
                                                        } else {
                                                            $progressClass = 'bg-danger';
                                                        }
                                                        ?>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar <?php echo $progressClass; ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $progress; ?>%"
                                                                 aria-valuenow="<?php echo $progress; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted"><?php echo round($progress); ?>% Complete</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-info btn-sm" onclick='viewProjectDetails(<?php echo json_encode($project); ?>)'>
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
                                    <p><strong>Progress:</strong></p>
                                    <div class="progress mb-2" style="height: 10px;">
                                        <div class="progress-bar" id="modalProgressBar" role="progressbar"></div>
                                    </div>
                                    <p><strong>Notes:</strong></p>
                                    <p id="modalNotes" class="text-muted"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Personnel -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Assigned Personnel</h6>
                                    <div id="modalPersonnel">
                                        <!-- Personnel will be dynamically added here -->
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
    let projectDetailsModal;

    document.addEventListener('DOMContentLoaded', function() {
        projectDetailsModal = new bootstrap.Modal(document.getElementById('projectDetailsModal'));
    });

    function viewProjectDetails(project) {
        // Update project information
        document.getElementById('modalService').textContent = project.service;
        document.getElementById('modalStartDate').textContent = new Date(project.start_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        document.getElementById('modalEndDate').textContent = new Date(project.end_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        
        // Update progress bar
        const progressBar = document.getElementById('modalProgressBar');
        const progress = Math.min(100, Math.max(0, project.progress));
        progressBar.style.width = progress + '%';
        progressBar.setAttribute('aria-valuenow', progress);
        
        // Set progress bar color
        let progressClass = 'bg-primary';
        if (progress == 100) progressClass = 'bg-success';
        else if (progress >= 75) progressClass = 'bg-info';
        else if (progress >= 50) progressClass = 'bg-primary';
        else if (progress >= 25) progressClass = 'bg-warning';
        else progressClass = 'bg-danger';
        
        progressBar.className = 'progress-bar ' + progressClass;
        progressBar.textContent = Math.round(progress) + '%';

        // Update notes
        document.getElementById('modalNotes').textContent = project.notes || 'No notes available';

        // Update personnel
        const personnelContainer = document.getElementById('modalPersonnel');
        personnelContainer.innerHTML = '';
        
        if (project.assigned_personnel) {
            const personnel = project.assigned_personnel.split('||');
            personnel.forEach(person => {
                const [name, role, email] = person.split('|');
                const personDiv = document.createElement('div');
                personDiv.className = 'mb-3 p-2 border rounded';
                personDiv.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-circle fa-2x text-muted me-2"></i>
                        <div>
                            <div class="fw-bold">${name}</div>
                            <div class="text-muted small">${role}</div>
                            <div class="text-muted small">${email}</div>
                        </div>
                    </div>
                `;
                personnelContainer.appendChild(personDiv);
            });
        } else {
            personnelContainer.innerHTML = '<p class="text-muted">No personnel assigned</p>';
        }

        // Update quotation file
        const quotationContainer = document.getElementById('modalQuotation');
        if (project.quotation_file) {
            quotationContainer.innerHTML = `
                <a href="../uploads/quotations/${project.quotation_file}" class="btn btn-primary" target="_blank">
                    <i class="fas fa-file-pdf me-2"></i>View Quotation
                </a>
            `;
        } else {
            quotationContainer.innerHTML = '<p class="text-muted">No quotation file available</p>';
        }

        // Show the modal
        projectDetailsModal.show();
    }
    </script>
</body>
</html> 