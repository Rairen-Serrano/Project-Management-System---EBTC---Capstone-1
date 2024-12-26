<?php
session_start();
require_once 'dbconnect.php';
require 'vendor/autoload.php';
require 'config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set timezone for Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Add these functions at the top after the imports
function generateAndSaveVerificationCode($pdo, $user_id) {
    $verification_code = sprintf('%06d', mt_rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Delete any existing codes
    $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Insert new code
    $stmt = $pdo->prepare("
        INSERT INTO verification_codes (user_id, code, expires_at) 
        VALUES (?, ?, CONVERT_TZ(?, '+00:00', '+08:00'))
    ");
    $stmt->execute([$user_id, $verification_code, $expires_at]);
    
    return $verification_code;
}

function sendVerificationEmail($user, $verification_code) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($user['email'], $user['name']);

        $mail->isHTML(true);
        $mail->Subject = "Your EBTC Login Verification Code";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #235347;'>Login Verification</h2>
                <p>Hello {$user['name']},</p>
                <p>Your verification code is: <strong style='font-size: 24px;'>{$verification_code}</strong></p>
                <p>This code will expire in 15 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

// Handle verification code submission
if (isset($_POST['verify_code'])) {
    $code = $_POST['verification_code'];
    $user_id = $_SESSION['temp_user_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM verification_codes 
            WHERE user_id = ? 
            AND code = ? 
            AND CONVERT_TZ(expires_at, '+00:00', '+08:00') > NOW()
        ");
        $stmt->execute([$user_id, $code]);
        
        if ($verification = $stmt->fetch()) {
            // Code is valid - complete login
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            // Delete used verification code
            $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Clear temporary session data
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['verification_required']);
            
            header('Location: client/dashboard.php');
            exit;
        } else {
            $error_message = 'Invalid or expired verification code';
        }
    } catch(PDOException $e) {
        $error_message = 'Verification error: ' . $e->getMessage();
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_code'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] !== 'client') {
                $error_message = 'Please use the admin login page';
            } else {
                try {
                    $verification_code = generateAndSaveVerificationCode($pdo, $user['user_id']);
                    sendVerificationEmail($user, $verification_code);
                    
                    // Store user_id temporarily and set verification flag
                    $_SESSION['temp_user_id'] = $user['user_id'];
                    $_SESSION['verification_required'] = true;
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            }
        } else {
            $error_message = 'Invalid email or password';
        }
    } catch(PDOException $e) {
        $error_message = 'Login error: ' . $e->getMessage();
    }
}

// Handle resend verification code
if (isset($_POST['resend_code'])) {
    try {
        $user_id = $_SESSION['temp_user_id'];
        
        // Get user details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $verification_code = generateAndSaveVerificationCode($pdo, $user_id);
            sendVerificationEmail($user, $verification_code);
            $success_message = "A new verification code has been sent to your email.";
        }
    } catch(Exception $e) {
        $error_message = 'Error resending code: ' . $e->getMessage();
    }
}

// Handle return to login
if (isset($_POST['return_login'])) {
    // Clear verification session data
    unset($_SESSION['temp_user_id']);
    unset($_SESSION['verification_required']);
    // Redirect to prevent form resubmission
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

    <title>Landing Page | Login Page</title>
    
</head>
<body id="loginPage">
    <?php include 'header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <?php if (isset($_SESSION['verification_required'])): ?>
                            <!-- Verification Code Form -->
                            <h2 class="text-center mb-4">Verify Your Login</h2>
                            <p class="text-center mb-4">Please enter the verification code sent to your email.</p>
                            
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
                                    <input type="text" class="form-control" id="verification_code" 
                                           name="verification_code" placeholder="Verification Code" 
                                           required pattern="[0-9]{6}" maxlength="6">
                                    <label for="verification_code">
                                        <i class="fas fa-key me-2"></i>Verification Code
                                    </label>
                                </div>
                                
                                <input type="hidden" name="verify_code" value="1">
                                <button type="submit" class="btn btn-primary w-100 py-3 mb-3">Verify</button>
                            </form>

                            <!-- Additional buttons -->
                            <div class="d-flex justify-content-between gap-3">
                                <form method="POST" action="" class="w-100">
                                    <input type="hidden" name="resend_code" value="1">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-redo me-2"></i>Resend Code
                                    </button>
                                </form>
                                
                                <form method="POST" action="" class="w-100">
                                    <input type="hidden" name="return_login" value="1">
                                    <button type="submit" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-arrow-left me-2"></i>Return to Login
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Login Form -->
                            <h2 class="text-center mb-4">Welcome Back!</h2>
                            
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Your existing login form -->
                            <form method="POST" action="">
                                <div class="form-floating mb-4">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                                    <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                </div>
                                
                                <div class="form-floating mb-4">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                </div>

                                <div class="d-flex justify-content-end mb-4">
                                    <a href="forgot_password.php" class="text-decoration-none small">Forgot Password?</a>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 py-3 mb-4">Login</button>

                                <div class="text-center">
                                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-decoration-none">Create one</a></p>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>