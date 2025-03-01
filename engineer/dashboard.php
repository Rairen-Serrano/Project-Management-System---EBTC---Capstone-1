<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    header('Location: ../admin_login.php');
    exit;
}

// Check if user has a PIN code set
$stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Only show PIN setup if user doesn't have a PIN and hasn't set one in this session
if (empty($user['pin_code']) && !isset($_SESSION['pin_verified'])) {
    $_SESSION['needs_pin_setup'] = true;
}
// Only show PIN verification if user has a PIN but hasn't verified in this session
else if (!empty($user['pin_code']) && !isset($_SESSION['pin_verified'])) {
    $_SESSION['needs_pin_verification'] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Dashboard | EBTC PMS</title>
    
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
<body id="engineerDashboardPage" data-needs-pin-setup="<?php echo empty($user['pin_code']) ? 'true' : 'false'; ?>">
    <div class="engineer-dashboard-wrapper">
        <!-- Include engineer header -->
        <?php include 'engineer_header.php'; ?>

    <!-- Main Content -->
        <div class="engineer-main-content" <?php echo !isset($_SESSION['pin_verified']) ? 'style="display: none;"' : ''; ?>>
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Active Tasks</h6>
                                    <h2 class="mb-0" id="activeTasks">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-tasks fa-2x"></i>
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
                                    <h6 class="card-title mb-1">Completed Tasks</h6>
                                    <h2 class="mb-0" id="completedTasks">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-check-circle fa-2x"></i>
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
                                    <h6 class="card-title mb-1">Pending Tasks</h6>
                                    <h2 class="mb-0" id="pendingTasks">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-clock fa-2x"></i>
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
                                    <h6 class="card-title mb-1">Total Projects</h6>
                                    <h2 class="mb-0" id="totalProjects">0</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-project-diagram fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Tasks and Project Progress -->
            <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                            <h5 class="card-title mb-0">Current Tasks</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                            <th style="width: 35%">Task</th>
                                            <th style="width: 25%">Project</th>
                                            <th style="width: 20%">Due Date</th>
                                            <th style="width: 10%">Status</th>
                                            <th style="width: 10%">Actions</th>
                                    </tr>
                                </thead>
                                    <tbody id="currentTasksTable">
                                        <!-- Tasks will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

                <!-- Quick Actions and Deadlines -->
            <div class="col-md-4">
                    <div class="card mb-4">
                    <div class="card-header">
                            <h5 class="card-title mb-0">Upcoming Deadlines</h5>
                        </div>
                        <div class="list-group list-group-flush" id="upcomingDeadlines">
                            <!-- Deadlines will be loaded dynamically -->
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Project Progress</h5>
                    </div>
                    <div class="card-body">
                            <div id="projectProgressList">
                                <!-- Project progress will be loaded dynamically -->
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
                    <h5 class="modal-title">Engineer PIN Verification</h5>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dashboard functionality
        if (typeof handleEngineerDashboardPage === 'function') {
            handleEngineerDashboardPage();
        }

        // Show PIN modal if needed
        const needsPinSetup = document.body.dataset.needsPinSetup === 'true';
        if (needsPinSetup) {
            const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
            pinSetupModal.show();
        } else if (!<?php echo isset($_SESSION['pin_verified']) ? 'true' : 'false'; ?>) {
            const pinVerificationModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
            pinVerificationModal.show();
        }

        // Handle PIN verification
        const verifyPinBtn = document.getElementById('verifyPinBtn');
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
                        document.querySelector('.engineer-main-content').style.display = 'block';
                        
                        // Refresh the page to ensure everything is properly loaded
                        window.location.reload();
                    } else {
                        document.getElementById('pinError').style.display = 'block';
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
    });
    </script>
</body>
</html> 