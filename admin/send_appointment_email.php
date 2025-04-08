<?php
session_start();
require_once '../dbconnect.php';
require_once '../vendor/autoload.php';
require_once '../config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Get POST data
$appointment_id = $_POST['appointment_id'] ?? '';
$client_email = $_POST['client_email'] ?? '';
$client_name = $_POST['client_name'] ?? '';
$service = $_POST['service'] ?? '';
$appointment_date = $_POST['date'] ?? '';
$appointment_time = $_POST['time'] ?? '';
$appointment_type = $_POST['appointment_type'] ?? '';

// Format date and time
$formatted_date = date('F j, Y', strtotime($appointment_date));
$formatted_time = date('g:i A', strtotime($appointment_time));

// Set email subject
$subject = "Appointment Confirmation - EBTC PMS";

try {
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = SMTP_PORT;

    // Recipients
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($client_email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;

    if ($appointment_type === 'face-to-face') {
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #235347; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { text-align: center; padding: 20px; background-color: #f8f9fa; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Appointment Confirmation</h2>
                </div>
                <div class='content'>
                    <p>Dear {$client_name},</p>
                    <p>Your face-to-face appointment has been confirmed with the following details:</p>
                    <ul>
                        <li><strong>Service:</strong> {$service}</li>
                        <li><strong>Date:</strong> {$formatted_date}</li>
                        <li><strong>Time:</strong> {$formatted_time}</li>
                        <li><strong>Type:</strong> Face-to-Face</li>
                        <li><strong>Location:</strong> Block 44 Lot 44 Xyris Street, Pembo, Taguig City</li>
                    </ul>
                    <p>Please arrive 15 minutes before your scheduled appointment time.</p>
                    <p>If you need to reschedule or have any questions, please contact us immediately.</p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>EBTC Team</p>
                </div>
            </div>
        </body>
        </html>";
    } else {
        // Use the specific Google Meet link
        $meet_link = "https://meet.google.com/usy-ruxa-pwq";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #235347; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { text-align: center; padding: 20px; background-color: #f8f9fa; }
                .meet-link { 
                    display: inline-block;
                    background-color: #235347;
                    color: white !important;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Appointment Confirmation</h2>
                </div>
                <div class='content'>
                    <p>Dear {$client_name},</p>
                    <p>Your online appointment has been confirmed with the following details:</p>
                    <ul>
                        <li><strong>Service:</strong> {$service}</li>
                        <li><strong>Date:</strong> {$formatted_date}</li>
                        <li><strong>Time:</strong> {$formatted_time}</li>
                        <li><strong>Type:</strong> Online Meeting</li>
                    </ul>
                    <p><strong>Meeting Link:</strong></p>
                    <p><a href='{$meet_link}' class='meet-link'>Join Google Meet</a></p>
                    <p>Or copy this link: <a href='{$meet_link}'>{$meet_link}</a></p>
                    <p>Please join the meeting 5 minutes before your scheduled appointment time.</p>
                    <p>If you need to reschedule or have any questions, please contact us immediately.</p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>EBTC Team</p>
                </div>
            </div>
        </body>
        </html>";
    }

    $mail->Body = $message;
    $mail->send();

    // Log the email attempt
    $log_query = "INSERT INTO email_logs (appointment_id, client_email, status, sent_at) 
                  VALUES (?, ?, ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$appointment_id, $client_email, 'success']);

    echo json_encode([
        'success' => true,
        'message' => 'Email sent successfully'
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Email sending failed: {$mail->ErrorInfo}");
    
    // Log the failed attempt
    $log_query = "INSERT INTO email_logs (appointment_id, client_email, status, sent_at) 
                  VALUES (?, ?, ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$appointment_id, $client_email, 'failed']);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email: ' . $e->getMessage()
    ]);
} 