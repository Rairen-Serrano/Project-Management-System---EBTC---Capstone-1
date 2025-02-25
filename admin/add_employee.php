<?php
session_start();
require_once '../dbconnect.php';
require '../vendor/autoload.php';
require '../config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['name', 'email', 'phone', 'role', 'password'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("$field is required");
        }
    }

    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validate phone number (numbers only)
    if (!preg_match('/^[0-9]+$/', $_POST['phone'])) {
        throw new Exception('Phone number must contain only numbers');
    }

    // Validate role
    $allowed_roles = ['project_manager', 'engineer', 'technician', 'worker'];
    if (!in_array($_POST['role'], $allowed_roles)) {
        throw new Exception('Invalid role selected');
    }

    // Validate password length
    if (strlen($_POST['password']) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }

    // Hash password
    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Insert new employee
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name,
            email,
            password,
            role,
            phone,
            status,
            date_created
        ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ");

    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['email']),
        $hashed_password,
        $_POST['role'],
        $_POST['phone']
    ]);

    $new_user_id = $pdo->lastInsertId();

    // Send welcome email with password using PHPMailer
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
        $mail->addAddress(trim($_POST['email']), trim($_POST['name']));

        $mail->isHTML(true);
        $mail->Subject = "Welcome to EBTC - Your Account Details";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='padding: 20px;'>
                    <h2 style='color: #235347;'>Welcome to EBTC!</h2>
                    <p>Dear " . htmlspecialchars($_POST['name']) . ",</p>
                    <p>Your account has been created successfully. Below are your login credentials:</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($_POST['email']) . "</p>
                    <p><strong>Password:</strong> <span style='background: #f4f4f4; padding: 10px; margin: 10px 0; display: inline-block;'>" 
                        . htmlspecialchars($_POST['password']) . "</span></p>
                    <p>For security reasons, please change your password after your first login.</p>
                    <p>Best regards,<br>EBTC Admin Team</p>
                </div>
            </div>
        ";

        $mail->send();
        echo json_encode([
            'success' => true,
            'message' => 'Employee added successfully and welcome email sent'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee added successfully, but welcome email could not be sent: ' . $mail->ErrorInfo
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 