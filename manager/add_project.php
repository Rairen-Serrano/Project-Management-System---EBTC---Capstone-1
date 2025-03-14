<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if appointment ID and personnel are provided
if (!isset($_POST['appointment_id']) || !isset($_POST['personnel']) || 
    !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required information']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get appointment details
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$_POST['appointment_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Insert into projects table
    $stmt = $pdo->prepare("
        INSERT INTO projects (
            appointment_id, 
            client_id, 
            service, 
            date,
            time,
            start_date,
            end_date,
            notes,
            quotation_file,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ongoing')
    ");

    // Handle file upload if present
    $quotation_file = null;
    if (isset($_FILES['quotation_file']) && $_FILES['quotation_file']['error'] === UPLOAD_ERR_OK) {
        $file_extension = pathinfo($_FILES['quotation_file']['name'], PATHINFO_EXTENSION);
        $quotation_file = uniqid() . '.' . $file_extension;
        $upload_path = '../uploads/quotations/' . $quotation_file;
        
        if (!move_uploaded_file($_FILES['quotation_file']['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload quotation file');
        }
    }

    $stmt->execute([
        $_POST['appointment_id'],
        $appointment['client_id'],
        $appointment['service'],
        $appointment['date'],
        $appointment['time'],
        $_POST['start_date'],
        $_POST['end_date'],
        $_POST['notes'],
        $quotation_file
    ]);

    $project_id = $pdo->lastInsertId();

    // Insert into project_assignees table
    $personnel = json_decode($_POST['personnel'], true);
    $stmt = $pdo->prepare("INSERT INTO project_assignees (project_id, user_id) VALUES (?, ?)");
    
    foreach ($personnel as $user_id) {
        $stmt->execute([$project_id, $user_id]);
    }

    // Update appointment status
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'converted' WHERE appointment_id = ?");
    $stmt->execute([$_POST['appointment_id']]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Delete uploaded file if exists
    if (isset($quotation_file) && file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error creating project: ' . $e->getMessage()
    ]);
} 