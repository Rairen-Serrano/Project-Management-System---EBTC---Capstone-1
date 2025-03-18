<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get POST data
    $appointment_id = $_POST['appointment_id'] ?? null;
    $personnel = json_decode($_POST['personnel'] ?? '[]', true);
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $notes = $_POST['notes'] ?? '';

    // Validate required data
    if (!$appointment_id || empty($personnel) || !$start_date || !$end_date) {
        throw new Exception('Missing required data');
    }

    // Verify appointment exists and get client_id and service
    $appointment_check = $pdo->prepare("
        SELECT a.appointment_id, a.client_id, a.service
        FROM appointments a
        WHERE a.appointment_id = ?
    ");
    $appointment_check->execute([$appointment_id]);
    $appointment = $appointment_check->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Invalid appointment ID');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Insert project with client_id
    $project_stmt = $pdo->prepare("
        INSERT INTO projects (appointment_id, client_id, start_date, end_date, notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'ongoing', NOW())
    ");
    
    $project_stmt->execute([
        $appointment_id,
        $appointment['client_id'],
        $start_date,
        $end_date,
        $notes
    ]);

    // Get the newly created project ID
    $project_id = $pdo->lastInsertId();

    // Handle quotation file upload if present
    if (isset($_FILES['quotation_file']) && $_FILES['quotation_file']['error'] === UPLOAD_ERR_OK) {
        $file_info = pathinfo($_FILES['quotation_file']['name']);
        $file_extension = strtolower($file_info['extension']);
        
        // Validate file type
        if ($file_extension !== 'pdf') {
            throw new Exception('Only PDF files are allowed for quotations');
        }

        // Generate unique filename
        $new_filename = 'quotation_' . $project_id . '_' . time() . '.pdf';
        $upload_path = '../uploads/quotations/';

        // Create directory if it doesn't exist
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        // Move uploaded file
        if (move_uploaded_file($_FILES['quotation_file']['tmp_name'], $upload_path . $new_filename)) {
            // Update project with quotation file name
            $update_stmt = $pdo->prepare("UPDATE projects SET quotation_file = ? WHERE project_id = ?");
            $update_stmt->execute([$new_filename, $project_id]);
        } else {
            throw new Exception('Failed to upload quotation file');
        }
    }

    // Insert project assignees
    $assignee_stmt = $pdo->prepare("
        INSERT INTO project_assignees (project_id, user_id, assigned_date)
        VALUES (?, ?, NOW())
    ");

    foreach ($personnel as $user_id) {
        $assignee_stmt->execute([$project_id, $user_id]);
    }

    // Send notification to client
    $client_notification = $pdo->prepare("
        INSERT INTO notifications (user_id, recipient_id, type, reference_id, title, message, created_at)
        VALUES (?, ?, 'project', ?, ?, ?, NOW())
    ");

    $client_title = "New Project Created";
    $client_message = "A new project for '{$appointment['service']}' has been created and assigned to our team. You can track the project progress through your dashboard.";
    
    $client_notification->execute([
        $_SESSION['user_id'],          // project manager who created
        $appointment['client_id'],     // client to notify
        $project_id,
        $client_title,
        $client_message
    ]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Project created successfully',
        'project_id' => $project_id,
        'client_id' => $appointment['client_id']
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error creating project: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create project: ' . $e->getMessage()
    ]);
} 