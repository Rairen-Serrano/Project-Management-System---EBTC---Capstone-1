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
                        INSERT INTO users (
                            name, 
                            email, 
                            password, 
                            role, 
                            phone, 
                            date_created, 
                            status,
                            pin_code
                        ) 
                        VALUES (
                            ?, ?, ?, ?, ?, 
                            NOW(), 
                            'pending',
                            NULL
                        )
                    ");
                    $stmt->execute([
                        $name, 
                        $email, 
                        $hashed_password, 
                        $role, 
                        $phone
                    ]);
                    
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
                        header('Location: verification_pending.php');
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
            $error_message = implode(' • ', $errors);
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
    
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js?render=6LcVU_wqAAAAANKqzxrZ-qBG1FFxOHhJd97KJSWD"></script>

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
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                            
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

                                <div class="col-md-12 mb-4">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="password" name="password" placeholder=" " required>
                                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                        <!-- The strength indicator will be inserted here by JavaScript -->
                                    </div>
                                </div>

                                <div class="col-md-12 mb-4">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder=" " required>
                                        <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
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
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" name="agreeTerms" required>
                                <label class="form-check-label small" for="agreeTerms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and 
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Data Privacy Policy</a>
                                </label>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Add loading indicator
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
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
</body>
</html> 