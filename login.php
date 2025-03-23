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

// Debugging
error_log('Login process started');

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
            
            // Set essential session variables
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
            
            // Redirect based on role
            if ($user['role'] === 'client') {
                header('Location: client/dashboard.php');
            } else {
                header('Location: admin_login.php');
            }
            exit;
        } else {
            $error_message = 'Invalid or expired verification code';
        }
    } catch(PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $error_message = 'Login error occurred';
    }
}

// Add reCAPTCHA verification at the top where other POST processing happens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_code'])) {
    try {
        // Verify reCAPTCHA first
        $recaptcha_token = $_POST['recaptcha_token'] ?? '';
        if (empty($recaptcha_token)) {
            throw new Exception('reCAPTCHA verification failed');
        }

        // Verify the token with Google
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_secret = '6LcVU_wqAAAAAKp4DJS_cEa4RecUQ8M4ECERbXPy';
        
        $recaptcha_response = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_token);
        $recaptcha_data = json_decode($recaptcha_response);

        // Check if verification was successful and score is acceptable
        if (!$recaptcha_data->success || $recaptcha_data->score < 0.5) {
            throw new Exception('reCAPTCHA verification failed. Please try again.');
        }

        // Handle initial login attempt
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Check if user exists and their email is verified
        $stmt = $pdo->prepare("
            SELECT user_id, name, email, password, role, status
            FROM users 
            WHERE email = ? AND role = 'client'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if email is verified (status is active)
            if ($user['status'] === 'pending') {
                $error_message = 'Please verify your email address before logging in. 
                                Check your email for the verification link or 
                                <a href="resend_verification.php?email=' . urlencode($email) . '">click here</a> 
                                to resend the verification email.';
            } else {
                $_SESSION['temp_user_id'] = $user['user_id'];
                $_SESSION['verification_required'] = true;
                
                // Generate and send verification code
                $verification_code = generateAndSaveVerificationCode($pdo, $user['user_id']);
                sendVerificationEmail($user, $verification_code);
                
                $success_message = 'Verification code sent to your email.';
            }
        } else {
            $error_message = 'Invalid email or password';
        }
    } catch(PDOException $e) {
        error_log("Login attempt error: " . $e->getMessage());
        $error_message = 'Login error occurred';
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
    
    <!-- Add reCAPTCHA script -->
    <script src="https://www.google.com/recaptcha/api.js?render=6LcVU_wqAAAAANKqzxrZ-qBG1FFxOHhJd97KJSWD"></script>
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

                            <!-- Modified login form with reCAPTCHA -->
                            <form method="POST" action="" id="loginForm">
                                <!-- Add hidden input for recaptcha token -->
                                <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                                
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

    <!-- Add reCAPTCHA handling script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        
        if (form) { // Only add listener if login form exists (not verification form)
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Add loading indicator
                const submitButton = form.querySelector('button[type="submit"]');
                const originalText = submitButton.innerHTML;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
                submitButton.disabled = true;
                
                grecaptcha.ready(function() {
                    grecaptcha.execute('6LcVU_wqAAAAANKqzxrZ-qBG1FFxOHhJd97KJSWD', {action: 'submit'})
                    .then(function(token) {
                        document.getElementById('recaptcha_token').value = token;
                        form.submit();
                    })
                    .catch(function(error) {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                        alert('Error verifying request. Please try again.');
                    });
                });
            });
        }
    });
    </script>

    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Data Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Republic Act 10173 – Data Privacy Act of 2012</h6>
                    <p>This privacy policy is in compliance with Republic Act No. 10173, also known as the Data Privacy Act of 2012, which protects individuals' personal information in information and communications systems.</p>

                    <h6>Collection of Personal Information</h6>
                    <p>We collect personal information that you voluntarily provide when using our services, including but not limited to:</p>
                    <ul>
                        <li>Name</li>
                        <li>Email address</li>
                        <li>Phone number</li>
                        <li>Other information necessary for service delivery</li>
                    </ul>

                    <h6>Use of Personal Information</h6>
                    <p>Your personal information will be used for:</p>
                    <ul>
                        <li>Providing and improving our services</li>
                        <li>Communication regarding your appointments and inquiries</li>
                        <li>Account management and verification</li>
                        <li>Compliance with legal obligations</li>
                    </ul>

                    <h6>Protection of Personal Information</h6>
                    <p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

                    <h6>Your Rights Under the Data Privacy Act</h6>
                    <p>You have the right to:</p>
                    <ul>
                        <li>Be informed about the collection and use of your personal data</li>
                        <li>Access your personal information</li>
                        <li>Object to the processing of your personal data</li>
                        <li>Rectify inaccurate or incorrect personal data</li>
                        <li>Remove or withdraw your personal information</li>
                        <li>Be indemnified for damages due to inaccurate information</li>
                        <li>Data portability</li>
                    </ul>

                    <h6>Contact Information</h6>
                    <p>For any concerns regarding your personal data, please contact our Data Protection Officer at [contact information].</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By accessing and using this service, you accept and agree to be bound by the terms and conditions of this agreement.</p>

                    <h6>2. User Account Responsibilities</h6>
                    <ul>
                        <li>You are responsible for maintaining the confidentiality of your account credentials</li>
                        <li>You agree to provide accurate and complete information</li>
                        <li>You must notify us immediately of any unauthorized use of your account</li>
                    </ul>

                    <h6>3. Service Usage</h6>
                    <ul>
                        <li>Services must be used for lawful purposes only</li>
                        <li>Appointments must be canceled at least 24 hours in advance</li>
                        <li>Users must provide accurate information for appointments</li>
                    </ul>

                    <h6>4. Intellectual Property</h6>
                    <p>All content and materials available through our service are protected by intellectual property rights.</p>

                    <h6>5. Limitation of Liability</h6>
                    <p>We shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of our services.</p>

                    <h6>6. Modifications to Service</h6>
                    <p>We reserve the right to modify or discontinue the service at any time without notice.</p>

                    <h6>7. Governing Law</h6>
                    <p>These terms shall be governed by and construed in accordance with the laws of the Philippines.</p>

                    <h6>8. Contact Information</h6>
                    <p>For any questions regarding these terms, please contact us at [contact information].</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this to your existing script section -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const agreeTerms = document.getElementById('agreeTerms');
                if (!agreeTerms.checked) {
                    e.preventDefault();
                    alert('Please agree to the Terms and Conditions and Data Privacy Policy to continue.');
                    return false;
                }
                // ... rest of your existing login form submission code ...
            });
        }
    });
    </script>
</body>
</html>