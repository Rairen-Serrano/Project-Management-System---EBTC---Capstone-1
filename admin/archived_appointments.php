<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$query = "
    SELECT 
        a.arc_appointment_id,
        a.appointment_id,
        a.client_id,
        a.service,
        a.date,
        a.time,
        a.status,
        a.created_at,
        a.archived_at,
        u.name as client_name,
        u.email as client_email,
        u.phone as client_phone
    FROM archived_appointments a
    JOIN users u ON a.client_id = u.user_id
    WHERE 1=1
";

$params = [];

// Add status filter
if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

// Add date filter
switch($date_filter) {
    case 'today':
        $query .= " AND DATE(a.date) = CURDATE()";
        break;
    case 'this_week':
        $query .= " AND YEARWEEK(a.date, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'this_month':
        $query .= " AND MONTH(a.date) = MONTH(CURDATE()) AND YEAR(a.date) = YEAR(CURDATE())";
        break;
    case 'this_year':
        $query .= " AND YEAR(a.date) = YEAR(CURDATE())";
        break;
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR a.service LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
$query .= " ORDER BY a.archived_at DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$archived_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Appointments | Admin Dashboard</title>

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
<body id="adminArchivedAppointmentsPage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Archived Appointments</h3>
                <a href="appointments.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Appointments
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Status Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <!-- Date Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <select name="date" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Dates</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="this_year" <?php echo $date_filter === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by client name, email, or service" value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Archived Appointments Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 25%">Client</th>
                                    <th style="width: 15%">Service</th>
                                    <th style="width: 20%">Date & Time</th>
                                    <th style="width: 15%">Status</th>
                                    <th style="width: 15%">Archived</th>
                                    <th style="width: 10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($archived_appointments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No archived appointments found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($archived_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($appointment['service']); ?>
                                        </td>
                                        <td>
                                            <div class="fs-6"><?php echo date('M d, Y', strtotime($appointment['date'])); ?></div>
                                            <div class="text-muted"><?php echo date('h:i A', strtotime($appointment['time'])); ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch($appointment['status']) {
                                                case 'pending':
                                                    $statusClass = 'warning';
                                                    break;
                                                case 'confirmed':
                                                    $statusClass = 'success';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'danger';
                                                    break;
                                                case 'completed':
                                                    $statusClass = 'info';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($appointment['archived_at'])); ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" onclick='viewArchivedAppointment(<?php echo htmlspecialchars(json_encode($appointment)); ?>)'>
                                                View
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

    <!-- View Archived Appointment Modal -->
    <div class="modal fade" id="viewArchivedAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archived Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6>Client Information</h6>
                            <p><strong>Name:</strong> <span id="modalClientName"></span></p>
                            <p><strong>Email:</strong> <span id="modalClientEmail"></span></p>
                            <p><strong>Phone:</strong> <span id="modalClientPhone"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Appointment Information</h6>
                            <p><strong>Service:</strong> <span id="modalService"></span></p>
                            <p><strong>Date:</strong> <span id="modalDate"></span></p>
                            <p><strong>Time:</strong> <span id="modalTime"></span></p>
                            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                            <p><strong>Created:</strong> <span id="modalCreated"></span></p>
                            <p><strong>Archived:</strong> <span id="modalArchived"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</body>
</html> 