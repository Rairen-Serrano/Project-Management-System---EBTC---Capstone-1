<?php
session_start();
require_once '../dbconnect.php';
require_once '../vendor/autoload.php';
require_once '../config/mail_config.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

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

function generateProjectSummaryPDF($pdo, $projectId, $projectInfo) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('EBTC PMS');
    $pdf->SetAuthor('EBTC Project Management');
    $pdf->SetTitle('Project Completion Summary');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(15, 15, 15);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Add logo if you have one
    // $pdf->Image('../path/to/logo.png', 15, 15, 50);

    // Title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'Project Completion Summary', 0, 1, 'C');
    $pdf->Ln(10);

    // Project Details
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Project Details', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Project: ' . $projectInfo['project_name'], 0, 1, 'L');
    $pdf->Cell(0, 10, 'Client: ' . $projectInfo['client_name'], 0, 1, 'L');
    $pdf->Ln(5);

    // Get project dates
    $dateStmt = $pdo->prepare("SELECT start_date, end_date, completed_at FROM projects WHERE project_id = ?");
    $dateStmt->execute([$projectId]);
    $dates = $dateStmt->fetch(PDO::FETCH_ASSOC);
    
    $pdf->Cell(0, 10, 'Start Date: ' . date('F d, Y', strtotime($dates['start_date'])), 0, 1, 'L');
    $pdf->Cell(0, 10, 'End Date: ' . date('F d, Y', strtotime($dates['end_date'])), 0, 1, 'L');
    $pdf->Cell(0, 10, 'Completion Date: ' . date('F d, Y', strtotime($dates['completed_at'])), 0, 1, 'L');
    $pdf->Ln(10);

    // Task Categories and Progress
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Project Timeline and Tasks', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);

    $categoryStmt = $pdo->prepare("
        SELECT 
            tc.category_name,
            tc.description,
            COUNT(t.task_id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM task_categories tc
        LEFT JOIN tasks t ON tc.category_id = t.category_id
        WHERE tc.project_id = ?
        GROUP BY tc.category_id
        ORDER BY tc.category_id ASC
    ");
    $categoryStmt->execute([$projectId]);
    
    while ($category = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, $category['category_name'], 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(0, 10, $category['description'], 0, 'L');
        $pdf->Cell(0, 10, "Completed Tasks: {$category['completed_tasks']}/{$category['total_tasks']}", 0, 1, 'L');
        $pdf->Ln(5);
    }

    // Project Files Section
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Project Documentation', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);

    // Get file information
    $fileStmt = $pdo->prepare("
        SELECT quotation_file, contract_file, budget_file 
        FROM projects 
        WHERE project_id = ?
    ");
    $fileStmt->execute([$projectId]);
    $files = $fileStmt->fetch(PDO::FETCH_ASSOC);

    $pdf->Cell(0, 10, 'The following documents are attached to this project:', 0, 1, 'L');
    $pdf->Ln(5);

    if ($files['quotation_file']) {
        $pdf->Cell(0, 10, '• Quotation File: ' . $files['quotation_file'], 0, 1, 'L');
    }
    if ($files['contract_file']) {
        $pdf->Cell(0, 10, '• Contract File: ' . $files['contract_file'], 0, 1, 'L');
    }
    if ($files['budget_file']) {
        $pdf->Cell(0, 10, '• Budget/Costing File: ' . $files['budget_file'], 0, 1, 'L');
    }

    // Save PDF to temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'project_summary_');
    $pdf->Output($tempFile, 'F');
    
    return $tempFile;
}

function sendCompletionEmail($to, $clientName, $projectName, $pdfPath, $projectFiles) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug  = 0;
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
        
        // Attach the project summary PDF
        $mail->addAttachment($pdfPath, 'Project_Summary.pdf');

        // Attach project files if they exist
        if ($projectFiles['quotation_file']) {
            $mail->addAttachment('../uploads/quotations/' . $projectFiles['quotation_file']);
        }
        if ($projectFiles['contract_file']) {
            $mail->addAttachment('../uploads/contracts/' . $projectFiles['contract_file']);
        }
        if ($projectFiles['budget_file']) {
            $mail->addAttachment('../uploads/budgets/' . $projectFiles['budget_file']);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Project Completion Summary - " . $projectName;
        
        // HTML email body
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #235347;'>Project Completion Summary</h2>
                
                <p>Dear {$clientName},</p>
                
                <p>We are pleased to inform you that your project '<strong>{$projectName}</strong>' has been successfully completed.</p>
                
                <p>Attached to this email you will find:</p>
                <ul>
                    <li>Project Completion Summary (PDF)</li>
                    " . ($projectFiles['quotation_file'] ? "<li>Project Quotation</li>" : "") . "
                    " . ($projectFiles['contract_file'] ? "<li>Project Contract</li>" : "") . "
                    " . ($projectFiles['budget_file'] ? "<li>Project Budget/Costing</li>" : "") . "
                </ul>
                
                <p>You can also log in to your client dashboard to view all project details and documentation.</p>
                
                <p>Thank you for choosing our services.</p>
                
                <br>
                <p style='margin-top: 30px;'>
                    Best regards,<br>
                    Project Management Team
                </p>
            </div>
        </body>
        </html>";

        $mail->send();
        unlink($pdfPath); // Delete temporary PDF file
        return true;
        
    } catch (Exception $e) {
        error_log("Error sending completion email: " . $e->getMessage());
        if (file_exists($pdfPath)) {
            unlink($pdfPath); // Clean up temp file even if email fails
        }
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
        // Get project files
        $fileStmt = $pdo->prepare("
            SELECT quotation_file, contract_file, budget_file 
            FROM projects 
            WHERE project_id = ?
        ");
        $fileStmt->execute([$projectId]);
        $projectFiles = $fileStmt->fetch(PDO::FETCH_ASSOC);

        // Generate PDF
        $pdfPath = generateProjectSummaryPDF($pdo, $projectId, $projectInfo);

        // Send email with PDF and files
        $emailSent = sendCompletionEmail(
            $projectInfo['client_email'],
            $projectInfo['client_name'],
            $projectInfo['project_name'],
            $pdfPath,
            $projectFiles
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

        // Get assigned personnel
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM project_assignees 
            WHERE project_id = ?
        ");
        $stmt->execute([$projectId]);
        $assignees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Update active_projects count for each assignee
        $update_stmt = $pdo->prepare("
            UPDATE users 
            SET active_projects = active_projects - 1 
            WHERE user_id = ? AND active_projects > 0
        ");

        foreach ($assignees as $user_id) {
            $update_stmt->execute([$user_id]);
        }

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