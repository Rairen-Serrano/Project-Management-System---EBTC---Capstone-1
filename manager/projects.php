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
        a.service as service,
        a.date as appointment_date,
        a.time as appointment_time,
        GROUP_CONCAT(DISTINCT CONCAT(up.name, '|', up.role, '|', up.email) SEPARATOR '||') as assigned_personnel
    FROM projects p
    JOIN users u ON p.client_id = u.user_id
    JOIN appointments a ON p.appointment_id = a.appointment_id
    LEFT JOIN project_assignees pa ON p.project_id = pa.project_id
    LEFT JOIN users up ON pa.user_id = up.user_id
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

    <style>
        .personnel-select:disabled + label {
            cursor: not-allowed;
        }
        
        .text-muted.bg-light td {
            opacity: 0.7;
        }
        
        .personnel-availability {
            font-size: 0.8rem;
            margin-top: 2px;
        }
    </style>
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
                                    <th style="width: 20%" class="text-start">Client</th>
                                    <th style="width: 35%" class="text-start">Service</th>
                                    <th style="width: 25%" class="text-start">Date</th>
                                    <th style="width: 10%" class="text-center">Status</th>
                                    <th style="width: 10%" class="text-center">Actions</th>
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
                                        <td class="text-start">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-circle text-muted me-2"></i>
                                                <?php echo htmlspecialchars($project['client_name']); ?>
                                            </div>
                                        </td>
                                        <td class="text-start">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-briefcase text-muted me-2"></i>
                                                <?php 
                                                $stmt = $pdo->prepare("
                                                    SELECT service 
                                                    FROM appointments 
                                                    WHERE appointment_id = ?
                                                ");
                                                $stmt->execute([$project['appointment_id']]);
                                                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                                                echo htmlspecialchars($appointment['service'] ?? 'N/A'); 
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-start">
                                            <div class="small">
                                                <?php 
                                                if ($project['start_date'] && $project['end_date']) {
                                                    echo '<div><i class="fas fa-calendar-alt text-muted me-2"></i><strong>Start:</strong> ' . 
                                                         date('M d, Y', strtotime($project['start_date'])) . '</div>';
                                                    echo '<div><i class="fas fa-calendar-check text-muted me-2"></i><strong>End:</strong> ' . 
                                                         date('M d, Y', strtotime($project['end_date'])) . '</div>';
                                                } else {
                                                    echo 'Not set';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $statusClass = $project['status'] === 'completed' ? 'bg-success' : 'bg-primary';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-info" 
                                                    onclick='viewProject(<?php echo json_encode($project); ?>)'>
                                                <i class="fas fa-eye"></i>
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
                                        <h6 class="mb-2">Project Files</h6>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div id="modalQuotationFile"></div>
                                            </div>
                                            <div class="col-md-4">
                                                <div id="modalContractFile"></div>
                                            </div>
                                            <div class="col-md-4">
                                                <div id="modalBudgetFile"></div>
                                            </div>
                                        </div>
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
                    <button type="button" class="btn btn-success me-2" id="completeProjectBtn" onclick="completeProject()">
                        <i class="fas fa-check-circle me-2"></i>Mark as Complete
                    </button>
                    <button type="button" class="btn btn-success me-2" onclick="redirectToTaskManagement()">
                        <i class="fas fa-tasks me-2"></i>Task Management
                    </button>
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
                                        <small class="text-muted">Accepted format: PDF only</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-file-pdf me-2"></i>Contract File</label>
                                        <input type="file" class="form-control" id="contractFile" accept=".pdf">
                                        <small class="text-muted">Accepted format: PDF only</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-file-pdf me-2"></i>Budget/Costing File</label>
                                        <input type="file" class="form-control" id="budgetFile" accept=".pdf">
                                        <small class="text-muted">Accepted format: PDF only</small>
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
                                            <th>Projects</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get available personnel (excluding clients, admins, and project managers)
                                        $stmt = $pdo->prepare("
                                            SELECT 
                                                u.user_id, 
                                                u.name, 
                                                u.role, 
                                                u.email, 
                                                u.active_projects,
                                                CASE 
                                                    WHEN u.role = 'engineer' THEN 3
                                                    WHEN u.role = 'technician' THEN 2
                                                    WHEN u.role = 'worker' THEN 1
                                                    ELSE 0
                                                END as max_projects
                                            FROM users u
                                            WHERE u.role NOT IN ('client', 'admin', 'project_manager', 'ceo')
                                            ORDER BY u.name ASC
                                        ");
                                        $stmt->execute();
                                        $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($personnel as $person): 
                                            $available = $person['active_projects'] < $person['max_projects'];
                                            $availableText = $available ? "Available" : "Not Available";
                                        ?>
                                            <tr class="<?php echo $available ? '' : 'text-muted bg-light'; ?>">
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input personnel-select" 
                                                               type="checkbox" 
                                                               value="<?php echo $person['user_id']; ?>"
                                                               id="person<?php echo $person['user_id']; ?>"
                                                               <?php echo $available ? '' : 'disabled'; ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-hard-hat text-muted me-2"></i>
                                                        <?php echo htmlspecialchars($person['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="badge bg-secondary">
                                                            <?php echo htmlspecialchars($person['role']); ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-<?php echo $available ? 'success' : 'danger'; ?>">
                                                            <?php echo $availableText; ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-envelope text-muted me-2"></i>
                                                        <?php echo htmlspecialchars($person['email']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-info">
                                                            <?php echo $person['active_projects']; ?> / <?php echo $person['max_projects']; ?> projects
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary view-personnel-projects" 
                                                            data-personnel-id="<?php echo $person['user_id']; ?>"
                                                            data-personnel-name="<?php echo htmlspecialchars($person['name']); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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

    <!-- Add Personnel Projects Modal -->
    <div class="modal fade" id="personnelProjectsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-project-diagram me-2"></i>
                        <span id="personnelProjectsTitle">Personnel Projects</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project Name</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                </tr>
                            </thead>
                            <tbody id="personnelProjectsList">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Store the current appointment data
    let currentAppointment = null;
    let assignPersonnelModal = null;
    let viewProjectModal = null;
    let currentProjectId = null; // Add this to store current project ID

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        assignPersonnelModal = new bootstrap.Modal(document.getElementById('assignPersonnelModal'));
        viewProjectModal = new bootstrap.Modal(document.getElementById('viewProjectModal'));

        // Set minimum date for start date
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);
        
        const startDateInput = document.getElementById('projectStartDate');
        const endDateInput = document.getElementById('projectEndDate');
        
        // Prevent selecting date before appointment date
        startDateInput.addEventListener('input', function() {
            const selectedDate = new Date(this.value);
            const appointmentDate = new Date(currentAppointment.date);
            
            // Reset time part for accurate date comparison
            selectedDate.setHours(0, 0, 0, 0);
            appointmentDate.setHours(0, 0, 0, 0);
            
            if (selectedDate < appointmentDate) {
                alert('Start date cannot be before the appointment date: ' + currentAppointment.date);
                this.value = ''; // Clear invalid selection
                endDateInput.value = ''; // Clear end date as well
                return;
            }
            
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
            document.querySelectorAll('.personnel-select:not([disabled])').forEach(checkbox => {
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

        // Add file type validation
        document.getElementById('quotationFile').addEventListener('change', function() {
            validateFileType(this, 'Quotation file');
        });

        document.getElementById('contractFile').addEventListener('change', function() {
            validateFileType(this, 'Contract file');
        });

        document.getElementById('budgetFile').addEventListener('change', function() {
            validateFileType(this, 'Budget file');
        });

        // Initialize personnel projects modal
        const personnelProjectsModal = new bootstrap.Modal(document.getElementById('personnelProjectsModal'));

        // Add click handlers for view projects buttons
        document.querySelectorAll('.view-personnel-projects').forEach(button => {
            button.addEventListener('click', async function() {
                const personnelId = this.dataset.personnelId;
                const personnelName = this.dataset.personnelName;
                
                // Update modal title
                document.getElementById('personnelProjectsTitle').textContent = `${personnelName}'s Projects`;
                
                try {
                    // Fetch personnel's projects
                    const response = await fetch(`./api/get_personnel_projects.php?personnel_id=${personnelId}`);
                    const responseText = await response.text();
                    console.log('Raw response:', responseText); // Debug log
                    
                    const data = JSON.parse(responseText);
                    console.log('Parsed data:', data); // Debug log
                    
                    const projectsList = document.getElementById('personnelProjectsList');
                    projectsList.innerHTML = '';
                    
                    if (data.success && data.projects && data.projects.length > 0) {
                        data.projects.forEach(project => {
                            console.log('Processing project:', project); // Debug log
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${project.project_name || 'N/A'}</td>
                                <td>${project.service || 'N/A'}</td>
                                <td><span class="badge bg-${project.status === 'completed' ? 'success' : 'primary'}">${project.status || 'N/A'}</span></td>
                                <td>${project.start_date ? new Date(project.start_date).toLocaleDateString() : 'N/A'}</td>
                                <td>${project.end_date ? new Date(project.end_date).toLocaleDateString() : 'N/A'}</td>
                            `;
                            projectsList.appendChild(row);
                        });
                    } else {
                        console.log('No projects found or invalid data structure:', data); // Debug log
                        projectsList.innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <i class="fas fa-info-circle me-2"></i>No active projects found
                                </td>
                            </tr>
                        `;
                    }
                    
                    personnelProjectsModal.show();
                } catch (error) {
                    console.error('Error fetching personnel projects:', error);
                    alert('Failed to load personnel projects');
                }
            });
        });
    });

    function updateSelectAllState() {
        const availableCheckboxes = document.querySelectorAll('.personnel-select:not([disabled])');
        const checkedAvailableCheckboxes = document.querySelectorAll('.personnel-select:not([disabled]):checked');
        const selectAllCheckbox = document.getElementById('selectAllPersonnel');
        
        if (availableCheckboxes.length === 0) {
            selectAllCheckbox.disabled = true;
            selectAllCheckbox.checked = false;
        } else {
            selectAllCheckbox.disabled = false;
            selectAllCheckbox.checked = availableCheckboxes.length === checkedAvailableCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedAvailableCheckboxes.length > 0 && 
                checkedAvailableCheckboxes.length < availableCheckboxes.length;
        }
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

    // Update this function in projects.php
    async function checkPersonnelAvailability(selectedPersonnel) {
        try {
            console.log('Sending personnel data:', selectedPersonnel); // Debug log
            
            const response = await fetch('./api/check_personnel_availability.php', {  // Updated path with ./
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    personnel: selectedPersonnel
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text(); // Get raw response text
            console.log('Raw response:', responseText); // Debug log
            
            try {
                const data = JSON.parse(responseText);
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data.available_personnel;
            } catch (parseError) {
                throw new Error(`Invalid JSON response: ${responseText}`);
            }
        } catch (error) {
            console.error('Full error:', error); // Debug log
            throw new Error(`Failed to check personnel availability: ${error.message}`);
        }
    }

    // Modify the existing confirmProjectAssignment function
    async function confirmProjectAssignment() {
        if (!currentAppointment) return;

        // Get selected personnel
        const selectedPersonnel = Array.from(document.querySelectorAll('.personnel-select:checked'))
            .map(checkbox => ({
                id: checkbox.value,
                role: checkbox.closest('tr').querySelector('.badge').textContent.trim()
            }));

        if (selectedPersonnel.length === 0) {
            alert('Please select at least one personnel to assign to the project.');
            return;
        }

        // Check personnel availability
        try {
            const availablePersonnel = await checkPersonnelAvailability(selectedPersonnel);
            
            const unavailablePersonnel = selectedPersonnel.filter(person => 
                !availablePersonnel.includes(parseInt(person.id))
            );

            if (unavailablePersonnel.length > 0) {
                const unavailableNames = unavailablePersonnel.map(person => 
                    document.querySelector(`#person${person.id}`).closest('tr').querySelector('.d-flex').textContent.trim()
                );
                
                alert(`The following personnel have reached their project limit:\n${unavailableNames.join('\n')}`);
                return;
            }

            // Get start and end dates
            const startDate = new Date(document.getElementById('projectStartDate').value);
            const endDate = new Date(document.getElementById('projectEndDate').value);

            // Validate dates
            if (!startDate || !endDate) {
                alert('Please select both start and end dates for the project.');
                return;
            }

            if (startDate > endDate) {
                alert('End date cannot be earlier than start date.');
                return;
            }

            // Calculate the duration in days
            const durationInDays = (endDate - startDate) / (1000 * 60 * 60 * 24);

            // Determine task categories based on project duration
            let taskCategories;
            if (durationInDays <= 2) {
                // Short-term project
                taskCategories = [
                    {
                        name: 'Resource Preparation',
                        description: 'Preparation of resources and materials for the project.'
                    },
                    {
                        name: 'Execution and Wrap-up',
                        description: 'Execution of the project tasks and final wrap-up.'
                    }
                ];
            } else {
                // Regular project
                taskCategories = [
                    {
                        name: 'Project Planning and Design',
                        description: 'Initial phase focusing on project scope definition, timeline planning, and technical design specifications.'
                    },
                    {
                        name: 'Materials Selection and Procurement',
                        description: 'Selection and acquisition of necessary materials, equipment, and resources for the project.'
                    },
                    {
                        name: 'Project Execution',
                        description: 'Implementation phase where the main project work is carried out according to specifications.'
                    },
                    {
                        name: 'Maintenance and Optimization',
                        description: 'Final phase ensuring project sustainability, performance optimization, and maintenance planning.'
                    }
                ];
            }

            // Get project notes and files
            const notes = document.getElementById('projectNotes').value;
            const quotationFile = document.getElementById('quotationFile').files[0];
            const contractFile = document.getElementById('contractFile').files[0];
            const budgetFile = document.getElementById('budgetFile').files[0];

            // Validate file types
            if (quotationFile && !validateFileType(document.getElementById('quotationFile'), 'Quotation file')) return;
            if (contractFile && !validateFileType(document.getElementById('contractFile'), 'Contract file')) return;
            if (budgetFile && !validateFileType(document.getElementById('budgetFile'), 'Budget file')) return;

            // Create FormData object
            const formData = new FormData();
            formData.append('appointment_id', currentAppointment.id);
            formData.append('personnel', JSON.stringify(selectedPersonnel));
            formData.append('start_date', startDate.toISOString().split('T')[0]);
            formData.append('end_date', endDate.toISOString().split('T')[0]);
            formData.append('notes', notes);

            // Add the new files
            if (quotationFile) {
                formData.append('quotation_file', quotationFile);
            }
            if (contractFile) {
                formData.append('contract_file', contractFile);
            }
            if (budgetFile) {
                formData.append('budget_file', budgetFile);
            }

            // Send request to create project and assign personnel
            const response = await fetch('./api/create_project.php', {
                method: 'POST',
                body: formData
            });
            
            const responseText = await response.text();
            console.log('Create project response:', responseText); // Debug log

            let projectData;
            try {
                projectData = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse response:', responseText);
                throw new Error('Invalid server response');
            }

            if (!projectData.success) {
                throw new Error(projectData.message || 'Project creation failed');
            }

            // Create task categories
            await fetch('./api/create_task_categories.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    project_id: projectData.project_id,
                    categories: taskCategories
                })
            });

            // Send notifications
            const notificationData = new FormData();
            notificationData.append('project_id', projectData.project_id.toString());
            notificationData.append('personnel', JSON.stringify(selectedPersonnel));
            notificationData.append('service', currentAppointment.service);

            await fetch('send_project_notifications.php', {
                method: 'POST',
                body: notificationData
            });

            assignPersonnelModal.hide();
            alert('Project created successfully with task categories!');
            location.reload();
        } catch (error) {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        }
    }

    function viewProject(project) {
        // Update modal content
        document.getElementById('modalClientName').textContent = project.client_name;
        document.getElementById('modalClientEmail').textContent = project.client_email;
        document.getElementById('modalClientPhone').textContent = project.client_phone;
        document.getElementById('modalService').textContent = project.service;
        
        // Format appointment date and time
        const appointmentDate = project.appointment_date ? new Date(project.appointment_date) : null;
        document.getElementById('modalDate').textContent = appointmentDate ? 
            appointmentDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'Not set';
        
        document.getElementById('modalTime').textContent = project.appointment_time || 'Not set';
        
        // Format project dates
        const startDate = project.start_date ? new Date(project.start_date) : null;
        const endDate = project.end_date ? new Date(project.end_date) : null;
        
        document.getElementById('modalStartDate').textContent = startDate ? 
            startDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'Not set';
        document.getElementById('modalEndDate').textContent = endDate ? 
            endDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'Not set';

        // Update status with proper styling
        const statusElement = document.getElementById('modalStatus');
        statusElement.textContent = project.status.charAt(0).toUpperCase() + project.status.slice(1);
        statusElement.className = `badge ${project.status === 'completed' ? 'bg-success' : 'bg-primary'} px-3 py-2`;

        // Update notes
        document.getElementById('modalNotes').textContent = project.notes || 'No notes available';

        // Update quotation file
        const quotationFileElement = document.getElementById('modalQuotationFile');
        if (project.quotation_file) {
            quotationFileElement.innerHTML = `
                <a href="../uploads/quotations/${project.quotation_file}" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-pdf me-2"></i>View Quotation
                </a>
            `;
        } else {
            quotationFileElement.innerHTML = '<p class="text-muted mb-0">No quotation file uploaded</p>';
        }

        // Update contract file
        const contractFileElement = document.getElementById('modalContractFile');
        if (project.contract_file) {
            contractFileElement.innerHTML = `
                <a href="../uploads/contracts/${project.contract_file}" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-contract me-2"></i>View Contract
                </a>
            `;
        } else {
            contractFileElement.innerHTML = '<p class="text-muted mb-0">No contract file uploaded</p>';
        }

        // Update budget file
        const budgetFileElement = document.getElementById('modalBudgetFile');
        if (project.budget_file) {
            budgetFileElement.innerHTML = `
                <a href="../uploads/budgets/${project.budget_file}" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-invoice-dollar me-2"></i>View Budget
                </a>
            `;
        } else {
            budgetFileElement.innerHTML = '<p class="text-muted mb-0">No budget file uploaded</p>';
        }

        // Update personnel list
        const personnelList = document.getElementById('modalPersonnelList');
        personnelList.innerHTML = '';
        
        if (project.assigned_personnel) {
            const personnel = project.assigned_personnel.split('||');
            personnel.forEach(person => {
                const [name, role, email] = person.split('|');
                personnelList.innerHTML += `
                    <tr>
                        <td>${name}</td>
                        <td><span class="badge bg-secondary">${role}</span></td>
                        <td>${email}</td>
                    </tr>
                `;
            });
        }

        // Store current project ID
        currentProjectId = project.project_id;

        // Check task categories status
        fetch(`api/get_task_categories_status.php?project_id=${currentProjectId}`)
            .then(response => response.json())
            .then(data => {
                const completeProjectBtn = document.getElementById('completeProjectBtn');
                if (completeProjectBtn) {
                    if (project.status === 'completed') {
                        completeProjectBtn.style.display = 'none';
                    } else {
                        completeProjectBtn.style.display = 'block';
                        if (data.hasInProgressCategories) {
                            completeProjectBtn.disabled = true;
                            completeProjectBtn.title = 'Cannot complete project while task categories are in progress';
                        } else {
                            completeProjectBtn.disabled = false;
                            completeProjectBtn.title = 'Mark project as complete';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking task categories status:', error);
            });

        // Show the modal
        const viewProjectModal = new bootstrap.Modal(document.getElementById('viewProjectModal'));
        viewProjectModal.show();
    }

    function redirectToTaskManagement() {
        if (!currentProjectId) {
            alert('No project selected');
            return;
        }
        
        // Get the current project status from the modal
        const statusElement = document.getElementById('modalStatus');
        const currentStatus = statusElement.textContent.trim().toLowerCase();
        
        if (currentStatus === 'completed') {
            alert('Task management is not available for completed projects.');
            return;
        }
        
        // Redirect to the task management page with the project ID
        window.location.href = `task_management.php?project_id=${currentProjectId}`;
    }

    function completeProject() {
        if (!currentProjectId) {
            alert('No project selected');
            return;
        }

        if (!confirm('Are you sure you want to mark this project as complete?')) {
            return;
        }

        // Create FormData object
        const formData = new FormData();
        formData.append('project_id', currentProjectId);

        // Send request to update project status
        fetch('complete_project.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Project marked as complete successfully!');
                location.reload();
            } else {
                alert(data.message || 'Failed to complete project');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to complete project');
        });
    }

    // Add this function to validate file types
    function validateFileType(fileInput, fileType) {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            if (file.type !== 'application/pdf') {
                alert(`${fileType} must be a PDF file`);
                fileInput.value = ''; // Clear the file input
                return false;
            }
        }
        return true;
    }
    </script>
</body>
</html> 