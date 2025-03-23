<?php
session_start();
require_once '../dbconnect.php';
require_once '../vendor/autoload.php';
require_once '../config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if project_id is provided
if (!isset($_POST['project_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

function sendCompletionEmail($to, $clientName, $projectName) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug  = 0;  // Disable debug output
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $clientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Project Completion Notification";
        
        // HTML email body
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #235347;'>Project Completion Notice</h2>
                
                <p>Dear {$clientName},</p>
                
                <p>We are pleased to inform you that your project '<strong>{$projectName}</strong>' has been successfully completed.</p>
                
                <p>You can log in to your client dashboard to view the complete project details and documentation.</p>
                
                <p>Thank you for choosing our services.</p>
                
                <br>
                <p style='margin-top: 30px;'>
                    Best regards,<br>
                    Project Management Team
                </p>
            </div>
        </body>
        </html>";

        // Plain text version
        $mail->AltBody = "
Dear {$clientName},

We are pleased to inform you that your project '{$projectName}' has been successfully completed.

You can log in to your client dashboard to view the complete project details and documentation.

Thank you for choosing our services.

Best regards,
Project Management Team";
        
        // Log email attempt
        error_log("Attempting to send completion email to: $to");
        
        $mail->send();
        error_log("Completion email sent successfully to: $to");
        return true;
        
    } catch (Exception $e) {
        error_log("Error sending completion email: " . $e->getMessage());
        return false;
    }
}

try {
    $projectId = $_POST['project_id'];

    // Check if all task categories are completed
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_categories,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_categories
        FROM task_categories 
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // If there are no categories or not all categories are completed
    if ($result['total_categories'] == 0 || $result['completed_categories'] < $result['total_categories']) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'All task categories must be completed before marking the project as complete'
        ]);
        exit;
    }

    // Get client information and project details
    $stmt = $pdo->prepare("
        SELECT 
            p.service as project_name,
            c.name as client_name,
            c.email as client_email
        FROM projects p
        JOIN users c ON p.client_id = c.user_id
        WHERE p.project_id = ?
    ");
    $stmt->execute([$projectId]);
    $projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log project info
    error_log("Project Info: " . print_r($projectInfo, true));

    // Start transaction
    $pdo->beginTransaction();

    // Update project status
    $stmt = $pdo->prepare("
        UPDATE projects 
        SET status = 'completed', 
            completed_at = NOW() 
        WHERE project_id = ?
    ");
    
    if ($stmt->execute([$projectId])) {
        // Send email notification
        $emailSent = sendCompletionEmail(
            $projectInfo['client_email'],
            $projectInfo['client_name'],
            $projectInfo['project_name']
        );

        // Insert notification in database
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                recipient_id,
                type,
                reference_id,
                title,
                message,
                created_at
            ) VALUES (
                ?, 
                (SELECT client_id FROM projects WHERE project_id = ?),
                'project_completion',
                ?,
                'Project Completed',
                ?,
                NOW()
            )
        ");

        $notificationMessage = "Your project '{$projectInfo['project_name']}' has been marked as completed.";
        $stmt->execute([
            $_SESSION['user_id'],
            $projectId,
            $projectId,
            $notificationMessage
        ]);

        $pdo->commit();
        
        // Return success even if email fails
        echo json_encode([
            'success' => true,
            'email_sent' => $emailSent,
            'message' => $emailSent ? 'Project completed and notification sent' : 'Project completed but email notification failed'
        ]);
    } else {
        throw new Exception("Failed to update project status");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in complete_project.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to complete project: ' . $e->getMessage()
    ]);
}
?> 