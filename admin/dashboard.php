<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Check if user has a PIN code set
$stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($user['pin_code'])) {
    $_SESSION['needs_pin_setup'] = true;
} else if (!isset($_SESSION['pin_verified'])) {
    $_SESSION['needs_pin_verification'] = true;
}

// Get total users (clients)
$stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE role = 'client'");
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

// Get total employees (non-clients)
$stmt = $pdo->prepare("SELECT COUNT(*) as total_employees FROM users WHERE role != 'client'");
$stmt->execute();
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['total_employees'];

// Get total appointments
$stmt = $pdo->prepare("SELECT COUNT(*) as total_appointments FROM appointments");
$stmt->execute();
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total_appointments'];

// Get pending appointments
$stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests FROM appointments WHERE status = 'pending'");
$stmt->execute();
$pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];

// Get recent appointments (last 7 days)
$stmt = $pdo->prepare("
    SELECT a.*, u.name as client_name 
    FROM appointments a 
    JOIN users u ON a.client_id = u.user_id 
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | EBTC PMS</title>

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
<body id="adminDashboardPage" data-needs-pin-setup="<?php echo empty($user['pin_code']) ? 'true' : 'false'; ?>">
    <div class="admin-dashboard-wrapper">
        <!-- Include admin header -->
        <?php include 'admin_header.php'; ?>
        
        <!-- Main Content -->
        <div class="admin-main-content" <?php echo !isset($_SESSION['pin_verified']) ? 'style="display: none;"' : ''; ?>>
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

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Users</h6>
                                    <h2 class="mb-0"><?php echo $total_users; ?></h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Appointments</h6>
                                    <h2 class="mb-0"><?php echo $total_appointments; ?></h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Employees</h6>
                                    <h2 class="mb-0"><?php echo $total_employees; ?></h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-user-tie fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Pending Requests</h6>
                                    <h2 class="mb-0"><?php echo $pending_requests; ?></h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Appointments</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Service</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_appointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['service']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($appointment['date'])) . ' ' . date('h:i A', strtotime($appointment['time'])); ?></td>
                                                <td>
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
                                                            case 'archived':
                                                                $statusClass = 'bg-dark';
                                                                break;
                                                            default:
                                                                $statusClass = '';
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($appointment['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recent_appointments)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No recent appointments found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="add_employee_form.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Add Employee
                                </a>
                                <a href="archived_appointments.php" class="btn btn-secondary">
                                    <i class="fas fa-archive me-2"></i>View Archived Appointments
                                </a>
                                <a href="generate_report.php" class="btn btn-info">
                                    <i class="fas fa-file-alt me-2"></i>Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- PIN Verification Modal -->
    <div class="modal fade" id="pinVerificationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Admin PIN Verification</h5>
                </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Please enter your 4-digit PIN code to access the dashboard.</p>
                    <div class="pin-input-group">
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                    </div>
                    <div id="pinError" class="text-danger text-center mt-2" style="display: none;">
                        Invalid PIN. Please try again.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="verifyPinBtn">Verify PIN</button>
                    <a href="../logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Setup Modal -->
    <div class="modal fade" id="pinSetupModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Up Your PIN</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        For security purposes, you need to set up a 4-digit PIN code. This PIN will be required each time you access the dashboard.
                    </div>
                    <form id="pinSetupForm">
                        <div class="mb-4">
                            <label class="form-label">Enter New PIN</label>
                            <div class="pin-input-group">
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input setup-pin" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm PIN</label>
                            <div class="pin-input-group">
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div id="pinSetupError" class="text-danger text-center mt-2" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="savePinBtn">Save PIN</button>
                    <a href="../logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this JavaScript before the closing </body> tag -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ... existing PIN input setup code ...

        if (verifyPinBtn) {
            verifyPinBtn.addEventListener('click', function() {
                const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
                const pin = Array.from(pinInputs).map(input => input.value).join('');
                
                if (pin.length !== 4) {
                    document.getElementById('pinError').style.display = 'block';
                    return;
                }
                
                fetch('../api/auth/verify_pin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ pin })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide the modal properly
                        const modal = bootstrap.Modal.getInstance(document.getElementById('pinVerificationModal'));
                        modal.hide();
                        
                        // Clean up modal artifacts
                        document.querySelector('.modal-backdrop').remove();
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                        document.body.style.removeProperty('overflow');
                        
                        // Show the main content
                        document.querySelector('.admin-main-content').style.display = 'block';
                        
                        // Refresh the page to ensure everything is properly loaded
                        window.location.reload();
                    } else {
                        document.getElementById('pinError').textContent = data.message || 'Invalid PIN. Please try again.';
                        document.getElementById('pinError').style.display = 'block';
                        
                        if (data.lockout_duration) {
                            // Disable the verify button and show countdown
                            verifyPinBtn.disabled = true;
                            startLockoutCountdown(data.lockout_duration);
                        }
                        
                        pinInputs.forEach(input => input.value = '');
                        pinInputs[0].focus();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('pinError').textContent = 'An error occurred. Please try again.';
                    document.getElementById('pinError').style.display = 'block';
                });
            });
        }

        function startLockoutCountdown(duration) {
            const verifyBtn = document.getElementById('verifyPinBtn');
            const errorDiv = document.getElementById('pinError');
            let timeLeft = duration;

            const countdownInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                errorDiv.textContent = `Account locked. Please try again in ${minutes}:${seconds.toString().padStart(2, '0')} minutes`;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    verifyBtn.disabled = false;
                    errorDiv.textContent = 'You can now try again.';
                    setTimeout(() => {
                        errorDiv.style.display = 'none';
                    }, 3000);
                }
                timeLeft--;
            }, 1000);
        }
    });
    </script>
</body>
</html> 