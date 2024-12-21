<?php
session_start();
require_once 'dbconnect.php';
require 'vendor/autoload.php';
require 'config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set timezone
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        if ($user = $stmt->fetch()) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Delete any existing reset tokens for this user
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                
                // Insert new reset token
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at) 
                    VALUES (?, ?, CONVERT_TZ(?, '+00:00', '+08:00'))
                ");
                $stmt->execute([$user['user_id'], $token, $expires]);
                
                // Add debugging
                error_log('Password reset token created: ' . $token . ' for user_id: ' . $user['user_id'] . ' expires at: ' . $expires);
                
                $pdo->commit();
                
                // Send reset email
                $mail = new PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = SMTP_PORT;

                // Recipients
                $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                $mail->addAddress($email, $user['name']);

                // Content
                $reset_link = "http://{$_SERVER['HTTP_HOST']}/reset_password.php?token=" . $token;
                
                $mail->isHTML(true);
                $mail->Subject = "Reset Your EBTC PMS Password";
                
                // HTML email body
                $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #235347;'>Password Reset Request</h2>
                        <p>Hello {$user['name']},</p>
                        <p>We received a request to reset your password. Click the button below to create a new password:</p>
                        
                        <div style='margin: 30px 0;'>
                            <a href='{$reset_link}' 
                               style='background-color: #00A36C; 
                                      color: white; 
                                      padding: 12px 30px; 
                                      text-decoration: none; 
                                      border-radius: 5px; 
                                      display: inline-block;'>
                                Reset Password
                            </a>
                        </div>
                        
                        <p>Or copy and paste this link in your browser:</p>
                        <p style='color: #666;'>{$reset_link}</p>
                        
                        <p>This link will expire in 1 hour.</p>
                        
                        <p style='color: #666; font-size: 0.9em; margin-top: 30px;'>
                            If you didn't request a password reset, please ignore this email.
                        </p>
                    </div>
                </body>
                </html>";

                $mail->send();
                $success_message = 'Password reset instructions have been sent to your email.';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // Show same message even if email not found (security best practice)
            $success_message = 'If your email is registered, you will receive password reset instructions.';
        }
    } catch(Exception $e) {
        $error_message = 'An error occurred. Please try again later.';
        error_log('Password reset error: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <!--CSS link here-->
    <link rel="stylesheet" href="css/style.css">

    <!--Javascript link here-->
    <script defer src="js/script.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="js/jquery-3.7.1.js" defer></script>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!--Font Awesome Link-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <title>Forgot Password | EBTC PMS</title>
    
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-key fa-3x text-primary mb-3"></i>
                            <h2 class="mb-2">Forgot Password</h2>
                            <p class="text-muted">Enter your email address to reset your password</p>
                        </div>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="form-floating mb-4">
                                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                                <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4">Reset Password</button>
                            
                            <div class="text-center">
                                <a href="index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 