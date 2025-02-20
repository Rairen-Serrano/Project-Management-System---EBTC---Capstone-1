<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Manager Dashboard | EBTC PMS</title>
    
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
<body id="managerDashboardPage" data-needs-pin-setup="<?php echo empty($user['pin_code']) ? 'true' : 'false'; ?>">
    <div class="manager-dashboard-wrapper">
        <!-- Include manager header -->
        <?php include 'manager_header.php'; ?>

    <!-- Main Content -->
        <div class="manager-main-content" <?php echo !isset($_SESSION['pin_verified']) ? 'style="display: none;"' : ''; ?>>
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Projects</h6>
                                    <h2 class="mb-0">8</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-project-diagram fa-2x"></i>
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
                                    <h6 class="card-title mb-1">Team Members</h6>
                                    <h2 class="mb-0">15</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-users fa-2x"></i>
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
                                    <h6 class="card-title mb-1">Total Tasks</h6>
                                    <h2 class="mb-0">45</h2>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-tasks fa-2x"></i>
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
                                    <h6 class="card-title mb-1">Pending Tasks</h6>
                                    <h2 class="mb-0">12</h2>
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
                            <h5 class="card-title mb-0">Recent Projects</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                            <th>Project</th>
                                        <th>Team Lead</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                            <td>Website Redesign</td>
                                            <td>John Smith</td>
                                        <td>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar" role="progressbar" style="width: 75%"></div>
                                            </div>
                                        </td>
                                            <td>
                                                <span class="badge bg-success">On Track</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Mobile App Development</td>
                                            <td>Sarah Johnson</td>
                                            <td>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" role="progressbar" style="width: 45%"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">Delayed</span>
                                            </td>
                                    </tr>
                                    <tr>
                                            <td>Database Migration</td>
                                            <td>Mike Wilson</td>
                                        <td>
                                            <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" role="progressbar" style="width: 90%"></div>
                                            </div>
                                        </td>
                                            <td>
                                                <span class="badge bg-info">Completed</span>
                                            </td>
                                    </tr>
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
                                <a href="add_project.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Add New Project
                                </a>
                                <a href="add_task.php" class="btn btn-secondary">
                                    <i class="fas fa-tasks me-2"></i>Create Task
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
                    <h5 class="modal-title">Manager PIN Verification</h5>
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
</body>
</html> 