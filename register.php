<?php
session_start();
require_once 'dbconnect.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Use Composer's autoload
require 'vendor/autoload.php';
require 'config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('CSRF token validation failed');
        }

        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $role = 'client'; // Default role

        // Validation
        $errors = [];

        // Name validation
        if (empty($name) || strlen($name) < 2 || strlen($name) > 50) {
            $errors[] = 'Name must be between 2 and 50 characters';
        }

        // Email validation
        if (!$email) {
            $errors[] = 'Please enter a valid email address';
        }

        // Phone validation
        if (!preg_match('/^[0-9]{11}$/', $phone)) {
            $errors[] = 'Phone number must be 11 digits';
        }

        // Password strength validation
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

        // Password confirmation
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }

        if (empty($errors)) {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists';
            } else {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Insert user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, password, role, phone, date_created, status) 
                        VALUES (?, ?, ?, ?, ?, NOW(), 'pending')
                    ");
                    $stmt->execute([$name, $email, $hashed_password, $role, $phone]);
                    
                    // Generate verification token
                    $verification_token = bin2hex(random_bytes(32));
                    $user_id = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO email_verifications (user_id, token, expires_at) 
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ");
                    $stmt->execute([$user_id, $verification_token]);
                    
                    $pdo->commit();
                    
                    // Send verification email
                    if (sendVerificationEmail($email, $verification_token)) {
                        $_SESSION['success_message'] = 'Registration successful! Please check your email to verify your account.';
                        header('Location: index.php');
                        exit;
                    } else {
                        throw new Exception('Failed to send verification email');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
        
        if (!empty($errors)) {
            $error_message = implode('<br>', $errors);
        }
        
    } catch(Exception $e) {
        $error_message = 'Registration error: ' . $e->getMessage();
        error_log("Registration error: " . $e->getMessage());
    }
}

function sendVerificationEmail($email, $token) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug  = 0;  // Enable verbose debug output
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // Content
        $verify_link = "http://{$_SERVER['HTTP_HOST']}/verify_email.php?token=" . $token;
        
        $mail->isHTML(true);
        $mail->Subject = "Verify your EBTC PMS account";
        
        // HTML email body
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #235347;'>Welcome to EBTC PMS!</h2>
                <p>Thank you for registering. Please verify your email address to activate your account.</p>
                
                <div style='margin: 30px 0;'>
                    <a href='{$verify_link}' 
                       style='background-color: #00A36C; 
                              color: white; 
                              padding: 12px 30px; 
                              text-decoration: none; 
                              border-radius: 5px; 
                              display: inline-block;'>
                        Verify Email Address
                    </a>
                </div>
                
                <p>Or copy and paste this link in your browser:</p>
                <p style='color: #666;'>{$verify_link}</p>
                
                <p>This link will expire in 24 hours.</p>
                
                <p style='color: #666; font-size: 0.9em; margin-top: 30px;'>
                    If you didn't create an account, please ignore this email.
                </p>
            </div>
        </body>
        </html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | EBTC PMS</title>
    
    <!--CSS link here-->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Add jQuery and script.js -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script defer src="js/script.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body id="registerPage">
    <?php include 'header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Create an Account</h2>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Full Name" required>
                                        <label for="name"><i class="fas fa-user me-2"></i>Full Name</label>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-4">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                                        <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-4">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" required>
                                        <label for="phone"><i class="fas fa-phone me-2"></i>Phone Number</label>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12 mb-4">
                                        <div class="form-floating">
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                            <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                            <!-- The strength indicator will be inserted here by JavaScript -->
                                        </div>
                                    </div>

                                    <div class="col-12 mb-4">
                                        <div class="form-floating">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                            <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                                        </div>
                                    </div>
                                </div>
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
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4">Create Account</button>
                            
                            <div class="text-center">
                                <p class="mb-0">Already have an account? <a href="index.php" class="text-decoration-none">Login here</a></p>
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