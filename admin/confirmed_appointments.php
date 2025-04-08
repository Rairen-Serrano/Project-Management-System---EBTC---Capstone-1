<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Get filter parameters
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
    AND a.status = 'confirmed'
";

$params = [];

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
    $query .= " AND (
        u.name LIKE ? OR 
        a.service LIKE ? OR 
        DATE_FORMAT(a.date, '%M %d, %Y') LIKE ? OR 
        DATE_FORMAT(a.time, '%h:%i %p') LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
$query .= " ORDER BY a.date DESC, a.time DESC";

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
    <title>Confirmed Appointments | Admin Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body id="adminConfirmedAppointmentsPage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Confirmed Appointments</h3>
                <a href="appointments.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Appointments
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Date Filter -->
                        <div class="col-md-4">
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
                        <div class="col-md-8">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                    placeholder="Search by client name, service, or date/time" 
                                    value="<?php echo htmlspecialchars($search); ?>">
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
                                    <th style="width: 15%">Client</th>
                                    <th style="width: 20%">Service</th>
                                    <th style="width: 15%" class="ps-5">Date & Time</th>
                                    <th style="width: 15%" class="text-center">Type</th>
                                    <th style="width: 15%" class="text-center">Status</th>
                                    <th style="width: 10%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No confirmed appointments found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($appointments as $appointment): 
                                        $isToday = date('Y-m-d', strtotime($appointment['date'])) === date('Y-m-d');
                                    ?>
                                    <tr class="<?php echo $isToday ? 'table-primary' : ''; ?>">
                                        <td style="min-width: 150px;">
                                            <?php echo htmlspecialchars($appointment['client_name']); ?>
                                            <?php if ($isToday): ?>
                                                <span class="badge bg-primary ms-2">Today</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 200px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($appointment['service']); ?>
                                        </td>
                                        <td style="min-width: 120px;" class="ps-5">
                                            <div class="fs-6"><?php echo date('M d, Y', strtotime($appointment['date'])); ?></div>
                                            <div class="text-muted"><?php echo date('h:i A', strtotime($appointment['time'])); ?></div>
                                            <div class="text-muted small">Created: <?php echo date('M d, Y h:i A', strtotime($appointment['created_at'])); ?></div>
                                        </td>
                                        <td style="min-width: 120px;" class="text-center">
                                            <?php if ($appointment['appointment_type'] === 'face-to-face'): ?>
                                                <span class="badge bg-primary">Face-to-Face</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Online</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 100px;" class="text-center">
                                            <span class="badge bg-success px-3 py-2">Confirmed</span>
                                        </td>
                                        <td class="actions-cell" style="min-width: 100px;">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <button type="button" class="btn btn-sm btn-info btn-action" 
                                                        onclick='viewAppointment(<?php echo json_encode($appointment); ?>)'
                                                        title="View Details"
                                                        style="width: 32px; height: 32px; padding: 0;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary btn-action" 
                                                        onclick='sendAppointmentEmail(<?php echo json_encode($appointment); ?>)'
                                                        title="Send Email"
                                                        style="width: 32px; height: 32px; padding: 0;">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </div>
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
                            <p><strong>Type:</strong> <span id="modalType"></span></p>
                            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                            <p><strong>Created:</strong> <span id="modalCreated"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="modalActions">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function viewAppointment(appointment) {
        // Fill in the modal with appointment details
        document.getElementById('modalClientName').textContent = appointment.client_name;
        document.getElementById('modalClientEmail').textContent = appointment.client_email;
        document.getElementById('modalClientPhone').textContent = appointment.client_phone;
        document.getElementById('modalService').textContent = appointment.service;
        document.getElementById('modalDate').textContent = new Date(appointment.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('modalTime').textContent = new Date('2000-01-01 ' + appointment.time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        
        // Update appointment type
        const typeText = appointment.appointment_type === 'face-to-face' ? 'Face-to-Face' : 'Online';
        document.getElementById('modalType').innerHTML = typeText;
        
        // Update status with badge
        document.getElementById('modalStatus').innerHTML = '<span class="status-badge confirmed">Confirmed</span>';
        
        document.getElementById('modalCreated').textContent = new Date(appointment.created_at).toLocaleString();

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('viewAppointmentModal'));
        modal.show();
    }

    function archiveAppointment(appointmentId) {
        if (confirm('Are you sure you want to archive this appointment?')) {
            window.location.href = `archive_appointment.php?id=${appointmentId}`;
        }
    }

    function sendAppointmentEmail(appointment) {
        if (confirm('Send appointment details to client?')) {
            // Create form data
            const formData = new FormData();
            formData.append('appointment_id', appointment.appointment_id);
            formData.append('client_email', appointment.client_email);
            formData.append('client_name', appointment.client_name);
            formData.append('service', appointment.service);
            formData.append('date', appointment.date);
            formData.append('time', appointment.time);
            formData.append('appointment_type', appointment.appointment_type);

            // Send AJAX request
            fetch('send_appointment_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Email sent successfully!');
                } else {
                    alert('Failed to send email: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the email.');
            });
        }
    }
    </script>
</body>
</html> 