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
        a.*, 
        u.name as client_name,
        u.email as client_email,
        u.phone as client_phone
    FROM appointments a
    JOIN users u ON a.client_id = u.user_id
    WHERE a.archived = 'No'
    AND a.status IN ('pending', 'cancelled')
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
    case 'tomorrow':
        $query .= " AND DATE(a.date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $query .= " AND YEARWEEK(a.date, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'next_week':
        $query .= " AND YEARWEEK(a.date, 1) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 1 WEEK), 1)";
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
$query .= " ORDER BY a.date ASC, a.time ASC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | Admin Dashboard</title>

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
<body id="adminAppointmentsPage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Appointments</h3>
                <a href="archived_appointments.php" class="btn btn-secondary">
                    <i class="fas fa-archive me-2"></i>View Archive
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
                                <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="next_week" <?php echo $date_filter === 'next_week' ? 'selected' : ''; ?>>Next Week</option>
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

            <!-- Appointments Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 25%">Client</th>
                                    <th style="width: 50%">Service</th>
                                    <th style="width: 15%">Date & Time</th>
                                    <th style="width: 10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">No appointments found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($appointment['service']); ?></td>
                                        <td>
                                            <div class="fs-6"><?php echo date('M d, Y', strtotime($appointment['date'])); ?></div>
                                            <div class="text-muted"><?php echo date('h:i A', strtotime($appointment['time'])); ?></div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" onclick='viewAppointment(<?php echo json_encode($appointment); ?>)'>
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

    <!-- View Appointment Modal -->
    <div class="modal fade" id="viewAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
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
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="modalActions">
                    <!-- Actions will be dynamically added here -->
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .pin-input {
        width: 50px !important;
        height: 50px;
        font-size: 24px;
        padding: 0;
    }

    .status-badge {
        font-size: 1rem;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        display: inline-block;
    }

    .status-badge.pending {
        background-color: #ffc107;
        color: #000;
    }

    .status-badge.cancelled {
        background-color: #dc3545;
        color: #fff;
    }
    </style>

    <script>
    function viewAppointment(appointment) {
        // Fill in the modal with appointment details
        document.getElementById('modalClientName').textContent = appointment.client_name;
        document.getElementById('modalClientEmail').textContent = appointment.client_email;
        document.getElementById('modalClientPhone').textContent = appointment.client_phone;
        document.getElementById('modalService').textContent = appointment.service;
        document.getElementById('modalDate').textContent = new Date(appointment.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('modalTime').textContent = new Date('2000-01-01 ' + appointment.time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        
        // Update status with badge
        const statusText = appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
        const statusClass = appointment.status === 'pending' ? 'pending' : 'cancelled';
        document.getElementById('modalStatus').innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;
        
        document.getElementById('modalCreated').textContent = new Date(appointment.created_at).toLocaleString();

        // Clear existing action buttons except Close
        const modalActions = document.getElementById('modalActions');
        modalActions.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';

        // Add appropriate action buttons based on status
        if (appointment.status === 'pending') {
            // For pending appointments: Confirm, Cancel
            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'btn btn-success me-2';
            confirmBtn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm';
            confirmBtn.onclick = () => confirmAppointment(appointment.appointment_id);
            
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'btn btn-danger me-2';
            cancelBtn.innerHTML = '<i class="fas fa-times me-2"></i>Cancel';
            cancelBtn.onclick = () => cancelAppointment(appointment.appointment_id);

            modalActions.insertBefore(cancelBtn, modalActions.firstChild);
            modalActions.insertBefore(confirmBtn, modalActions.firstChild);
        } else if (appointment.status === 'cancelled') {
            // For cancelled appointments: Archive
            const archiveBtn = document.createElement('button');
            archiveBtn.className = 'btn btn-secondary me-2';
            archiveBtn.innerHTML = '<i class="fas fa-archive me-2"></i>Archive';
            archiveBtn.onclick = () => archiveAppointment(appointment.appointment_id);

            modalActions.insertBefore(archiveBtn, modalActions.firstChild);
        }

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('viewAppointmentModal'));
        modal.show();
    }

    function confirmAppointment(appointmentId) {
        if (confirm('Are you sure you want to confirm this appointment?')) {
            window.location.href = `confirm_appointment.php?id=${appointmentId}`;
        }
    }

    function cancelAppointment(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            window.location.href = `cancel_appointment.php?id=${appointmentId}`;
        }
    }

    function archiveAppointment(appointmentId) {
        if (confirm('Are you sure you want to archive this appointment?')) {
            window.location.href = `archive_appointment.php?id=${appointmentId}`;
        }
    }
    </script>

</body>
</html> 