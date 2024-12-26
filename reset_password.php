<?php
session_start();
require_once 'dbconnect.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

$token = $_GET['token'] ?? '';
$valid_token = false;
$token_expired = false;

if ($token) {
    try {
        // Check if token exists and is valid
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.user_id 
            WHERE pr.token = ? 
            AND CONVERT_TZ(pr.expires_at, '+00:00', '+08:00') > NOW()
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        // Add debugging
        if (!$reset) {
            error_log('Token not found or expired. Token: ' . $token);
            // Check if token exists but expired
            $stmt = $pdo->prepare("
                SELECT pr.*, u.email, pr.expires_at
                FROM password_resets pr 
                JOIN users u ON pr.user_id = u.user_id 
                WHERE pr.token = ?
            ");
            $stmt->execute([$token]);
            $expired_reset = $stmt->fetch();
            if ($expired_reset) {
                error_log('Token found but expired. Expires at: ' . $expired_reset['expires_at']);
            } else {
                error_log('Token not found in database.');
            }
        }

        if ($reset) {
            $valid_token = true;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                $errors = [];
                
                // Password validation
                if (strlen($password) < 12) {
                    $errors[] = 'Password must be at least 12 characters long';
                }
                if (!preg_match('/[A-Z]/', $password)) {
                    $errors[] = 'Password must contain at least one uppercase letter';
                }
                if (!preg_match('/[a-z]/', $password)) {
                    $errors[] = 'Password must contain at least one lowercase letter';
                }
                if (!preg_match('/[0-9]/', $password)) {
                    $errors[] = 'Password must contain at least one number';
                }
                if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
                    $errors[] = 'Password must contain at least one special character';
                }
                if ($password !== $confirm_password) {
                    $errors[] = 'Passwords do not match';
                }

                if (empty($errors)) {
                    $pdo->beginTransaction();
                    
                    try {
                        // Update password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashed_password, $reset['user_id']]);
                        
                        // Delete all reset tokens for this user
                        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                        $stmt->execute([$reset['user_id']]);
                        
                        $pdo->commit();
                        
                        $_SESSION['success_message'] = 'Your password has been reset successfully. You can now login with your new password.';
                        header('Location: login.php');
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
            }
        } else {
            $token_expired = true;
        }
    } catch (PDOException $e) {
        $error_message = 'An error occurred. Please try again later.';
        error_log('Reset password error: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | EBTC PMS</title>
    
    <!--CSS link here-->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <?php if ($token_expired): ?>
                            <div class="text-center">
                                <i class="fas fa-times-circle text-danger fa-3x mb-3"></i>
                                <h2 class="mb-4">Link Expired</h2>
                                <p class="mb-4">This password reset link has expired or is invalid.</p>
                                <a href="forgot_password.php" class="btn btn-primary">Request New Reset Link</a>
                            </div>
                        <?php elseif ($valid_token): ?>
                            <h2 class="text-center mb-4">Reset Password</h2>
                            
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="form-floating mb-4">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required>
                                    <label for="password"><i class="fas fa-lock me-2"></i>New Password</label>
                                </div>
                                
                                <div class="form-floating mb-4">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                    <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                                </div>

                                <div class="mb-4">
                                    <div class="password-requirements small text-muted">
                                        <p class="mb-2">Password must contain:</p>
                                        <ul class="ps-3">
                                            <li>At least 12 characters</li>
                                            <li>At least one uppercase letter</li>
                                            <li>At least one lowercase letter</li>
                                            <li>At least one number</li>
                                            <li>At least one special character</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 py-3">Reset Password</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <i class="fas fa-exclamation-circle text-danger fa-3x mb-3"></i>
                                <h2 class="mb-4">Invalid Link</h2>
                                <p class="mb-4">This password reset link is invalid.</p>
                                <a href="forgot_password.php" class="btn btn-primary">Request New Reset Link</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script>
        // Add password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            const passwordFields = document.querySelectorAll('input[type="password"]');
            passwordFields.forEach(field => {
                // Create and add toggle button
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'btn btn-link password-toggle';
                toggleBtn.innerHTML = '<i class="fas fa-eye" style="color: #000000;"></i>';
                field.parentElement.appendChild(toggleBtn);

                // Add click event
                toggleBtn.addEventListener('click', function() {
                    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
                    field.setAttribute('type', type);
                    this.innerHTML = type === 'password' 
                        ? '<i class="fas fa-eye" style="color: #000000;"></i>' 
                        : '<i class="fas fa-eye-slash" style="color: #000000;"></i>';
                });
            });
        });
    </script>
</body>
</html> 