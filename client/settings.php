<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}

// Get user's information
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        if (!password_verify($_POST['current_password'], $user['password'])) {
            throw new Exception('Current password is incorrect');
        }

        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            throw new Exception('New passwords do not match');
        }

        if (strlen($_POST['new_password']) < 12) {
            throw new Exception('Password must be at least 12 characters long');
        }

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([
            password_hash($_POST['new_password'], PASSWORD_DEFAULT),
            $_SESSION['user_id']
        ]);

        $_SESSION['success_message'] = 'Password changed successfully!';
        header('Location: settings.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Handle PIN change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pin'])) {
    try {
        if ($_POST['current_pin'] !== $user['pin_code']) {
            throw new Exception('Current PIN is incorrect');
        }

        if ($_POST['new_pin'] !== $_POST['confirm_pin']) {
            throw new Exception('New PINs do not match');
        }

        if (!preg_match('/^[0-9]{4}$/', $_POST['new_pin'])) {
            throw new Exception('PIN must be exactly 4 digits');
        }

        $stmt = $pdo->prepare("UPDATE users SET pin_code = ? WHERE user_id = ?");
        $stmt->execute([$_POST['new_pin'], $_SESSION['user_id']]);

        $_SESSION['success_message'] = 'PIN changed successfully!';
        header('Location: settings.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | EBTC PMS</title>

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
<body id="settingsPage">
    <?php include 'client_header.php'; ?>
    
    <div class="client-dashboard-wrapper">
        <!-- Main Content -->
        <div class="client-main-content">
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

            <!-- Settings Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <!-- Change Password -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                                            <h5>Password</h5>
                                            <p class="text-muted">Change your account password</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                                <i class="fas fa-key me-2"></i>Change Password
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Change PIN -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                                            <h5>PIN Code</h5>
                                            <p class="text-muted">Change your 4-digit security PIN</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changePinModal">
                                                <i class="fas fa-calculator me-2"></i>Change PIN
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmNewPassword" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sendVerificationBtn">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Message Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div class="toast" role="alert" id="messageToast">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>

    <!-- Change PIN Modal -->
    <div class="modal fade" id="changePinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change PIN Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST" id="changePinForm">
                    <div class="modal-body">
                        <div class="mb-4 text-center">
                            <label class="form-label mb-3">Current PIN</label>
                            <div class="pin-input-group">
                                <input type="password" class="pin-input current-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input current-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input current-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input current-pin" maxlength="1" pattern="[0-9]" required>
                            </div>
                            <input type="hidden" id="current_pin" name="current_pin">
                        </div>
                        <div class="mb-4 text-center">
                            <label class="form-label mb-3">New PIN</label>
                            <div class="pin-input-group">
                                <input type="password" class="pin-input new-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input new-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input new-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input new-pin" maxlength="1" pattern="[0-9]" required>
                            </div>
                            <input type="hidden" id="new_pin" name="new_pin">
                        </div>
                        <div class="mb-4 text-center">
                            <label class="form-label mb-3">Confirm New PIN</label>
                            <div class="pin-input-group">
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="pin-input confirm-pin" maxlength="1" pattern="[0-9]" required>
                            </div>
                            <input type="hidden" id="confirm_pin" name="confirm_pin">
                        </div>
                        <input type="hidden" name="change_pin" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change PIN</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html> 