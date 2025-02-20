<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    header('Location: ../admin_login.php');
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$query = "
    SELECT 
        p.*,
        u.name as client_name,
        u.email as client_email,
        u.phone as client_phone,
        GROUP_CONCAT(DISTINCT CONCAT(up.name, '|', up.role, '|', up.email) SEPARATOR '||') as assigned_personnel
    FROM projects p
    JOIN users u ON p.client_id = u.user_id
    LEFT JOIN project_personnel pp ON p.project_id = pp.project_id
    LEFT JOIN users up ON pp.user_id = up.user_id
";

$params = [];
$where_conditions = [];

// Add status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

// Add search filter
if (!empty($search)) {
    $where_conditions[] = "(
        u.name LIKE ? OR 
        p.service LIKE ? OR 
        DATE_FORMAT(p.date, '%M %d, %Y') LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Combine where conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Add grouping
$query .= " GROUP BY p.project_id";

// Add sorting
$query .= " ORDER BY p.date DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects | Manager Dashboard</title>

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
<body id="managerProjectsPage">
    <div class="manager-dashboard-wrapper">
        <?php include 'manager_header.php'; ?>
        
        <div class="manager-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Projects</h3>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#selectAppointmentModal">
                    <i class="fas fa-plus me-2"></i>Add Project
                </button>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Status Filter -->
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="col-md-8">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                    placeholder="Search by client name, service, or date" 
                                    value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <?php if (!empty($search)): ?>
                            <div class="form-text">
                                <small>
                                    Searching for: "<?php echo htmlspecialchars($search); ?>"
                                    <a href="?status=<?php echo $status_filter; ?>" class="text-decoration-none">
                                        <i class="fas fa-times-circle"></i> Clear search
                                    </a>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Projects Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 20%">Client</th>
                                    <th style="width: 30%">Service</th>
                                    <th style="width: 15%">Date</th>
                                    <th style="width: 15%">Status</th>
                                    <th style="width: 20%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($projects)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No projects found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($project['service']); ?>
                                        </td>
                                        <td>
                                            <div class="fs-6"><?php echo date('M d, Y', strtotime($project['date'])); ?></div>
                                            <div class="text-muted"><?php echo date('h:i A', strtotime($project['time'])); ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = $project['status'] === 'completed' ? 'bg-success' : 'bg-primary';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> px-3 py-2">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" onclick='viewProject(<?php echo json_encode($project); ?>)'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Project Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <!-- Client Information -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-user me-2"></i>Client Information
                                    </h6>
                                    <p><strong>Name:</strong> <span id="modalClientName"></span></p>
                                    <p><strong>Email:</strong> <span id="modalClientEmail"></span></p>
                                    <p><strong>Phone:</strong> <span id="modalClientPhone"></span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Project Information -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Project Information
                                    </h6>
                                    <p><strong>Service:</strong> <span id="modalService"></span></p>
                                    <p><strong>Appointment Date:</strong> <span id="modalDate"></span></p>
                                    <p><strong>Appointment Time:</strong> <span id="modalTime"></span></p>
                                    <p><strong>Start Date:</strong> <span id="modalStartDate"></span></p>
                                    <p><strong>End Date:</strong> <span id="modalEndDate"></span></p>
                                    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Project Details -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-clipboard-list me-2"></i>Additional Details
                                    </h6>
                                    <div class="mb-3">
                                        <h6 class="mb-2">Project Notes</h6>
                                        <div id="modalNotes" class="p-3 bg-light rounded"></div>
                                    </div>
                                    <div class="mb-3">
                                        <h6 class="mb-2">Quotation File</h6>
                                        <div id="modalQuotationFile"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Personnel -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fas fa-users me-2"></i>Assigned Personnel
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Role</th>
                                                    <th>Email</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modalPersonnelList">
                                            </tbody>
                                        </table>
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

    <!-- Select Appointment Modal -->
    <div class="modal fade" id="selectAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-project-diagram me-2"></i>Select Project
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 30%">Client</th>
                                    <th style="width: 55%">Service</th>
                                    <th style="width: 15%">Date</th>
                                    <th style="width: 15%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get confirmed appointments that are not yet projects
                                $query = "
                                    SELECT 
                                        a.*,
                                        u.name as client_name,
                                        DATE_FORMAT(a.date, '%M %d, %Y') as formatted_date
                                    FROM appointments a
                                    JOIN users u ON a.client_id = u.user_id
                                    LEFT JOIN projects p ON a.appointment_id = p.appointment_id
                                    WHERE a.status = 'confirmed'
                                    AND p.project_id IS NULL
                                    ORDER BY a.date ASC
                                ";
                                $stmt = $pdo->prepare($query);
                                $stmt->execute();
                                $confirmed_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php if (count($confirmed_appointments) > 0): ?>
                                    <?php foreach ($confirmed_appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user text-muted me-2"></i>
                                                    <?php echo htmlspecialchars($appointment['client_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="word-wrap: break-word; white-space: normal;">
                                                    <?php echo htmlspecialchars($appointment['service']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-muted">
                                                    <?php echo htmlspecialchars($appointment['formatted_date']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="addToProjects(<?php echo $appointment['appointment_id']; ?>, '<?php echo htmlspecialchars($appointment['formatted_date']); ?>')">
                                                    <i class="fas fa-plus me-1"></i>Add
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No confirmed appointments available
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Personnel Modal -->
    <div class="modal fade" id="assignPersonnelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>Project Creation Form
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Project Details Card -->
                    <div class="card mb-4 bg-light">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-info-circle me-2"></i>Project Details
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Service:</strong></p>
                                    <p class="text-muted" id="assignModalService"></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Client:</strong></p>
                                    <p class="text-muted" id="assignModalClient"></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-2"><strong>Appointment Date:</strong></p>
                                    <p class="text-muted" id="assignModalDate"></p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-calendar-alt me-2"></i>Start Date</label>
                                        <input type="date" class="form-control" id="projectStartDate" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-calendar-check me-2"></i>End Date</label>
                                        <input type="date" class="form-control" id="projectEndDate" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-sticky-note me-2"></i>Project Notes</label>
                                        <textarea class="form-control" id="projectNotes" rows="3" placeholder="Add project notes here..."></textarea>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-file-pdf me-2"></i>Quotation File</label>
                                        <input type="file" class="form-control" id="quotationFile" accept=".pdf">
                                        <small class="text-muted">Accepted format: PDF</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personnel Selection -->
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-user-plus me-2"></i>Select Personnel
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAllPersonnel">
                                                </div>
                                            </th>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get available personnel (excluding clients, admins, and project managers)
                                        $stmt = $pdo->prepare("
                                            SELECT user_id, name, role, email 
                                            FROM users 
                                            WHERE role NOT IN ('client', 'admin', 'project_manager')
                                            ORDER BY name ASC
                                        ");
                                        $stmt->execute();
                                        $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($personnel as $person): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input personnel-select" 
                                                               type="checkbox" 
                                                               value="<?php echo $person['user_id']; ?>"
                                                               id="person<?php echo $person['user_id']; ?>">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-hard-hat text-muted me-2"></i>
                                                        <?php echo htmlspecialchars($person['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($person['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-envelope text-muted me-2"></i>
                                                        <?php echo htmlspecialchars($person['email']); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (empty($personnel)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No personnel available for assignment.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmProjectAssignment()">
                        <i class="fas fa-check me-2"></i>Create Project & Assign Personnel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Store the current appointment data
    let currentAppointment = null;
    let assignPersonnelModal = null;
    let viewProjectModal = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        assignPersonnelModal = new bootstrap.Modal(document.getElementById('assignPersonnelModal'));
        viewProjectModal = new bootstrap.Modal(document.getElementById('viewProjectModal'));

        // Set minimum date for start date (tomorrow)
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);
        
        const startDateInput = document.getElementById('projectStartDate');
        const endDateInput = document.getElementById('projectEndDate');
        
        // Format dates to YYYY-MM-DD
        startDateInput.min = tomorrow.toISOString().split('T')[0];
        
        // Prevent selecting today's date
        startDateInput.addEventListener('input', function() {
            const selectedDate = new Date(this.value);
            if (selectedDate <= today) {
                alert('Start date must be after today');
                this.value = ''; // Clear invalid selection
                endDateInput.value = ''; // Clear end date as well
            }
        });
        
        // Update end date min when start date changes
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                const selectedDate = new Date(this.value);
                endDateInput.min = selectedDate.toISOString().split('T')[0];
                endDateInput.value = ''; // Reset end date when start date changes
            }
        });

        // Handle select all checkbox
        document.getElementById('selectAllPersonnel').addEventListener('change', function() {
            document.querySelectorAll('.personnel-select').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all state when individual checkboxes change
        document.querySelectorAll('.personnel-select').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectAllState);
        });

        // Add error handling for modal elements
        ['modalClientName', 'modalClientEmail', 'modalClientPhone', 'modalService', 
         'modalDate', 'modalTime', 'modalStatus'].forEach(id => {
            const element = document.getElementById(id);
            if (!element) {
                console.error(`Element with id "${id}" not found`);
            }
        });
    });

    function updateSelectAllState() {
        const totalCheckboxes = document.querySelectorAll('.personnel-select').length;
        const checkedCheckboxes = document.querySelectorAll('.personnel-select:checked').length;
        document.getElementById('selectAllPersonnel').checked = totalCheckboxes === checkedCheckboxes;
        document.getElementById('selectAllPersonnel').indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
    }

    function addToProjects(appointmentId, appointmentDate) {
        // Find the appointment data from the table
        const row = document.querySelector(`button[onclick*="addToProjects(${appointmentId}"]`).closest('tr');
        currentAppointment = {
            id: appointmentId,
            client: row.querySelector('.d-flex.align-items-center').textContent.trim(),
            service: row.querySelector('div[style*="word-wrap"]').textContent.trim(),
            date: appointmentDate
        };

        // Update the assign modal with appointment details
        document.getElementById('assignModalService').textContent = currentAppointment.service;
        document.getElementById('assignModalClient').textContent = currentAppointment.client;
        document.getElementById('assignModalDate').textContent = currentAppointment.date;

        // Clear any previously selected personnel
        document.querySelectorAll('.personnel-select').forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Update select all checkbox state
        document.getElementById('selectAllPersonnel').checked = false;
        document.getElementById('selectAllPersonnel').indeterminate = false;

        // Show the assign personnel modal
        assignPersonnelModal.show();
    }

    function confirmProjectAssignment() {
        if (!currentAppointment) return;

        // Get selected personnel
        const selectedPersonnel = Array.from(document.querySelectorAll('.personnel-select:checked'))
            .map(checkbox => checkbox.value);

        if (selectedPersonnel.length === 0) {
            alert('Please select at least one personnel to assign to the project.');
            return;
        }

        // Get start and end dates
        const startDate = document.getElementById('projectStartDate').value;
        const endDate = document.getElementById('projectEndDate').value;

        // Validate dates
        if (!startDate || !endDate) {
            alert('Please select both start and end dates for the project.');
            return;
        }

        if (new Date(startDate) > new Date(endDate)) {
            alert('End date cannot be earlier than start date.');
            return;
        }

        // Get project notes and quotation file
        const notes = document.getElementById('projectNotes').value;
        const quotationFile = document.getElementById('quotationFile').files[0];

        // Create FormData object
        const formData = new FormData();
        formData.append('appointment_id', currentAppointment.id);
        formData.append('personnel', JSON.stringify(selectedPersonnel));
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);
        formData.append('notes', notes);
        if (quotationFile) {
            formData.append('quotation_file', quotationFile);
        }

        // Send request to create project and assign personnel
        fetch('add_project.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                assignPersonnelModal.hide();
                alert('Project created and personnel assigned successfully!');
                location.reload();
            } else {
                alert(data.message || 'Failed to create project and assign personnel');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to create project and assign personnel');
        });
    }

    function viewProject(project) {
        try {
            console.log('View Project Data:', project);
            
            if (!project) {
                throw new Error('No project data provided');
            }

            // Fill in the modal with project details
            document.getElementById('modalClientName').textContent = project.client_name || 'N/A';
            document.getElementById('modalClientEmail').textContent = project.client_email || 'N/A';
            document.getElementById('modalClientPhone').textContent = project.client_phone || 'N/A';
            document.getElementById('modalService').textContent = project.service || 'N/A';
            
            // Format appointment date and time
            if (project.date) {
                document.getElementById('modalDate').textContent = new Date(project.date).toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            } else {
                document.getElementById('modalDate').textContent = 'N/A';
            }
            
            if (project.time) {
                document.getElementById('modalTime').textContent = new Date('2000-01-01 ' + project.time).toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit' 
                });
            } else {
                document.getElementById('modalTime').textContent = 'N/A';
            }

            // Format project dates
            if (project.start_date) {
                document.getElementById('modalStartDate').textContent = new Date(project.start_date).toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                });
            } else {
                document.getElementById('modalStartDate').textContent = 'N/A';
            }

            if (project.end_date) {
                document.getElementById('modalEndDate').textContent = new Date(project.end_date).toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                });
            } else {
                document.getElementById('modalEndDate').textContent = 'N/A';
            }
            
            // Update status with badge
            const statusClass = project.status === 'completed' ? 'bg-success' : 'bg-primary';
            document.getElementById('modalStatus').innerHTML = `
                <span class="badge ${statusClass} px-3 py-2">
                    ${(project.status || 'Unknown').charAt(0).toUpperCase() + (project.status || 'Unknown').slice(1)}
                </span>
            `;

            // Display project notes
            document.getElementById('modalNotes').textContent = project.notes || 'No notes available';

            // Display quotation file
            const quotationFileElement = document.getElementById('modalQuotationFile');
            if (project.quotation_file) {
                quotationFileElement.innerHTML = `
                    <a href="../uploads/quotations/${project.quotation_file}" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-file-pdf me-2"></i>View Quotation
                    </a>
                `;
            } else {
                quotationFileElement.innerHTML = '<p class="text-muted mb-0">No quotation file available</p>';
            }

            // Display assigned personnel
            const personnelList = document.getElementById('modalPersonnelList');
            personnelList.innerHTML = '';

            if (project.assigned_personnel) {
                const personnel = project.assigned_personnel.split('||');
                personnel.forEach(person => {
                    const [name, role, email] = person.split('|');
                    personnelList.innerHTML += `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-hard-hat text-muted me-2"></i>
                                    ${name}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary">${role}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-envelope text-muted me-2"></i>
                                    ${email}
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                personnelList.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-muted">
                            No personnel assigned to this project
                        </td>
                    </tr>
                `;
            }

            // Show the modal
            if (!viewProjectModal) {
                console.error('View project modal not initialized');
                viewProjectModal = new bootstrap.Modal(document.getElementById('viewProjectModal'));
            }
            viewProjectModal.show();
        } catch (error) {
            console.error('Error viewing project:', error);
            alert('There was an error viewing the project details. Please try again.');
        }
    }
    </script>
</body>
</html> 