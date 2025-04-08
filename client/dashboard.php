<?php
session_start();
require_once '../dbconnect.php';

// Debugging
error_log('Session data: ' . print_r($_SESSION, true));

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}

// Check if this is first login (needs PIN setup)
$stmt = $pdo->prepare("SELECT first_login FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['first_login'] == 1) {
    // Show PIN setup modal on page load
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
            pinSetupModal.show();
        });
    </script>";
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Items per page
$items_per_page = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Build the base query
$query = "
    SELECT appointment_id, service, date, time, status, created_at, appointment_type
    FROM appointments 
    WHERE client_id = ?
";

$params = [$_SESSION['user_id']];

// Add status filter
if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

// Add date filter
switch($date_filter) {
    case 'today':
        $query .= " AND DATE(date) = CURDATE()";
        break;
    case 'this_week':
        $query .= " AND YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'this_month':
        $query .= " AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())";
        break;
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (
        service LIKE ? OR 
        DATE_FORMAT(date, '%M %d, %Y') LIKE ? OR
        appointment_type LIKE ? OR
        status LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Get total count for pagination
$count_query = str_replace("appointment_id, service, date, time, status, created_at, appointment_type", "COUNT(*) as total", $query);
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

// Add pagination to main query
$query .= " ORDER BY created_at DESC LIMIT ?, ?";

// Execute main query
$stmt = $pdo->prepare($query);

// Bind all existing parameters
$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex, $param);
    $paramIndex++;
}

// Bind the LIMIT parameters as integers
$stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex + 1, $items_per_page, PDO::PARAM_INT);

$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for pending appointments count
$pending_count_query = "
    SELECT COUNT(*) as pending_count 
    FROM appointments 
    WHERE client_id = ? 
    AND status = 'pending'
";
$stmt = $pdo->prepare($pending_count_query);
$stmt->execute([$_SESSION['user_id']]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard | EBTC PMS</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body id="dashboardPage">
    
    <?php include 'client_header.php'; ?>
    
    <div class="client-dashboard-wrapper">
        <!-- Main Content -->
        <div class="client-main-content mt-4">
            <!-- Mobile Toggle Button -->
            <button class="btn btn-primary d-md-none mb-3" id="clientSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <!-- Status Filter -->
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <!-- Date Filter -->
                    <div class="col-md-4">
                        <label class="form-label">Date</label>
                        <select name="date" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Dates</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                placeholder="Search by service, date, type, or status" 
                                value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($search)): ?>
                <div class="mb-3">
                    <small class="text-muted">
                        Searching for: "<?php echo htmlspecialchars($search); ?>"
                        <a href="?status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>" class="text-decoration-none">
                            <i class="fas fa-times-circle"></i> Clear search
                        </a>
                    </small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Appointments Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">My Appointments</h5>
                            <?php if ($pending_count >= 3): ?>
                                <button class="btn btn-secondary" onclick="showPendingAppointmentError()">
                                    <i class="fas fa-plus me-2"></i>Book New Appointment
                                </button>
                            <?php else: ?>
                                <a href="../book_appointment.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Book New Appointment
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($appointments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No Appointments Found</h5>
                                    <p class="text-muted">You haven't booked any appointments yet.</p>
                                    <a href="../book_appointment.php" class="btn btn-primary">
                                        Book Your First Appointment
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Service</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $appointment): ?>
                                                <tr data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                                                    <td style="min-width: 150px;"><?php echo htmlspecialchars($appointment['service']); ?></td>
                                                    <td style="min-width: 120px;"><?php echo date('M d, Y', strtotime($appointment['date'])); ?></td>
                                                    <td style="min-width: 100px;"><?php echo date('h:i A', strtotime($appointment['time'])); ?></td>
                                                    <td style="min-width: 120px;">
                                                        <?php if ($appointment['appointment_type'] === 'face-to-face'): ?>
                                                            <span class="badge bg-primary">
                                                                <i class="fas fa-user-group me-1"></i> Face-to-Face
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-video me-1"></i> Online
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="min-width: 100px;">
                                                        <?php
                                                        $statusClass = '';
                                                        switch($appointment['status']) {
                                                            case 'pending':
                                                                $statusClass = 'bg-warning';
                                                                break;
                                                            case 'confirmed':
                                                                $statusClass = 'bg-success';
                                                                break;
                                                            case 'cancelled':
                                                                $statusClass = 'bg-danger';
                                                                break;
                                                            case 'completed':
                                                                $statusClass = 'bg-info';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $statusClass; ?> px-3 py-2">
                                                            <?php echo ucfirst($appointment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center actions-cell">
                                                        <?php if ($appointment['status'] === 'pending'): ?>
                                                            <div class="action-buttons-wrapper">
                                                                <button class="btn btn-secondary btn-action" 
                                                                        onclick="rescheduleAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                    <h1>Reschedule</h1>
                                                                </button>
                                                                <button class="btn btn-danger btn-action" 
                                                                        onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                    <h1>Cancel</h1>
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
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

            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page-1); ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>">
                                Previous
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page+1); ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>">
                                Next
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PIN Setup Modal -->
    <div class="modal fade" id="pinSetupModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Up Your PIN</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Please set up a 4-digit PIN code for your account security.
                    </div>
                    <form id="pinSetupForm">
                        <div class="mb-4">
                            <label class="form-label">Enter New PIN</label>
                            <div class="d-flex justify-content-center gap-2">
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm PIN</label>
                            <div class="d-flex justify-content-center gap-2">
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div id="pinSetupError" class="text-danger text-center mt-2" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="savePinBtn">Save PIN</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Verification Modal -->
    <div class="modal fade" id="pinVerificationModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enter PIN to Cancel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Please enter your 4-digit PIN code to confirm cancellation.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                    </div>
                    <input type="hidden" id="appointmentToCancel">
                    <div id="pinError" class="text-danger text-center mt-2" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmCancellation">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reschedule Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="appointmentToReschedule">
                    <div class="mb-3">
                        <label class="form-label">New Date</label>
                        <input type="date" class="form-control" id="newDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Time</label>
                        <select class="form-control" id="newTime" required>
                            <option value="">Select Time</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmReschedule">Confirm Reschedule</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Verification Modal for Reschedule -->
    <div class="modal fade" id="pinVerificationRescheduleModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enter PIN to Reschedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Please enter your 4-digit PIN code to confirm rescheduling.</p>
                    <div class="pin-input-group">
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmPinReschedule">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .pin-input-group {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin: 20px 0;
    }

    .pin-input {
        width: 50px !important;
        height: 50px;
        font-size: 24px;
        text-align: center;
        border-radius: 8px;
        border: 1px solid #ced4da;
    }

    .pin-input:focus {
        border-color: #235347;
        box-shadow: 0 0 0 0.2rem rgba(35, 83, 71, 0.25);
    }

    select option:disabled {
        color: #6c757d;
        background-color: #e9ecef;
    }
    
    select option.text-muted {
        font-style: italic;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle PIN input navigation
        function setupPinInputs(containerSelector) {
            const inputs = document.querySelectorAll(`${containerSelector} .pin-input`);
            
            inputs.forEach((input, index) => {
                input.addEventListener('keyup', function(e) {
                    // Allow only numbers
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Move to next input if value is entered
                    if (this.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    
                    // Handle backspace
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
            });
        }

        // Add PIN setup handling
        const savePinBtn = document.getElementById('savePinBtn');
        if (savePinBtn) {
            savePinBtn.addEventListener('click', function() {
                // Get all PIN inputs
                const setupPinInputs = Array.from(document.querySelectorAll('.setup-pin'));
                const confirmPinInputs = Array.from(document.querySelectorAll('.confirm-pin'));
                
                // Get PIN values
                const setupPin = setupPinInputs.map(input => input.value).join('');
                const confirmPin = confirmPinInputs.map(input => input.value).join('');
                
                // Validate PINs
                if (setupPin.length !== 4 || confirmPin.length !== 4) {
                    showPinError('Please enter all 4 digits');
                    return;
                }
                
                if (setupPin !== confirmPin) {
                    showPinError('PINs do not match');
                    return;
                }
                
                // Send PIN to server
                fetch('../api/auth/setup_pin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ pin: setupPin })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide the modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('pinSetupModal'));
                        modal.hide();
                        
                        // Show success message
                        alert('PIN setup successful!');
                        
                        // Refresh the page
                        window.location.reload();
                    } else {
                        showPinError(data.message || 'Failed to set up PIN');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showPinError('An error occurred. Please try again.');
                });
            });
        }
        
        function showPinError(message) {
            const errorDiv = document.getElementById('pinSetupError');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            
            // Clear all PIN inputs
            document.querySelectorAll('.setup-pin, .confirm-pin').forEach(input => {
                input.value = '';
            });
            // Focus on first input
            document.querySelector('.setup-pin').focus();
        }
        
        // Initialize PIN inputs for both setup and confirmation
        setupPinInputs('#pinSetupModal');

        function rescheduleAppointment(appointmentId) {
            document.getElementById('appointmentToReschedule').value = appointmentId;
            const modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
            modal.show();
        }

        document.getElementById('confirmReschedule').addEventListener('click', function() {
            const appointmentId = document.getElementById('appointmentToReschedule').value;
            const newDate = document.getElementById('newDate').value;
            const newTime = document.getElementById('newTime').value;

            // Validate fields
            if (!appointmentId || !newDate || !newTime) {
                alert('Please fill in all required fields');
                return;
            }

            // Store the values in hidden fields in the PIN modal
            document.getElementById('pinVerificationRescheduleModal').dataset.appointmentId = appointmentId;
            document.getElementById('pinVerificationRescheduleModal').dataset.newDate = newDate;
            document.getElementById('pinVerificationRescheduleModal').dataset.newTime = newTime;

            // Show PIN verification modal
            const pinModal = new bootstrap.Modal(document.getElementById('pinVerificationRescheduleModal'));
            pinModal.show();
        });

        document.getElementById('confirmPinReschedule').addEventListener('click', function() {
            const modal = document.getElementById('pinVerificationRescheduleModal');
            const pinInputs = modal.querySelectorAll('.pin-input');
            const pin = Array.from(pinInputs).map(input => input.value).join('');

            // Get stored values from the modal's dataset
            const appointmentId = modal.dataset.appointmentId;
            const newDate = modal.dataset.newDate;
            const newTime = modal.dataset.newTime;

            // Validate PIN
            if (pin.length !== 4) {
                alert('Please enter your 4-digit PIN');
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('appointment_id', appointmentId);
            formData.append('new_date', newDate);
            formData.append('new_time', newTime);
            formData.append('pin', pin);

            // Send request
            fetch('../client/reschedule_appointment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide modals
                    bootstrap.Modal.getInstance(modal).hide();
                    bootstrap.Modal.getInstance(document.getElementById('rescheduleModal')).hide();
                    
                    // Show success message and reload
                    alert('Appointment rescheduled successfully');
                    window.location.reload();
                } else {
                    alert(data.message || 'Error rescheduling appointment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing request. Please try again');
            });
        });

        function showPendingAppointmentError() {
            // Create and show the alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning alert-dismissible fade show mt-2';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                You have reached the maximum number of pending appointments (3). Please wait for your current appointments to be confirmed or cancelled before booking new ones.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert the alert after the card header
            const cardHeader = document.querySelector('.card-header');
            cardHeader.parentNode.insertBefore(alertDiv, cardHeader.nextSibling);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }, 5000);
        }

        // Make the function globally available
        window.showPendingAppointmentError = showPendingAppointmentError;
    });
    </script>
</body>
</html> 