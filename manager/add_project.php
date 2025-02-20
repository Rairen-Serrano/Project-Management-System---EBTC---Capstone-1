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
    $pdo->beginTransaction();

    // Validate dates
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if (!strtotime($start_date) || !strtotime($end_date)) {
        throw new Exception('Invalid date format');
    }

    if (strtotime($start_date) > strtotime($end_date)) {
        throw new Exception('End date cannot be earlier than start date');
    }

    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT * FROM appointments 
        WHERE appointment_id = ? 
        AND status = 'confirmed'
    ");
    $stmt->execute([$_POST['appointment_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found or not confirmed');
    }

    // Check if project already exists for this appointment
    $stmt = $pdo->prepare("SELECT project_id FROM projects WHERE appointment_id = ?");
    $stmt->execute([$_POST['appointment_id']]);
    if ($stmt->fetch()) {
        throw new Exception('A project already exists for this appointment');
    }

    // Insert new project
    $stmt = $pdo->prepare("
        INSERT INTO projects (
            client_id, 
            appointment_id,
            service,
            date,
            time,
            start_date,
            end_date,
            status,
            notes,
            quotation_file,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ongoing', ?, ?, NOW())
    ");

    // Handle quotation file upload if provided
    $quotation_filename = null;
    if (isset($_FILES['quotation_file']) && $_FILES['quotation_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['quotation_file'];
        
        // Validate file type
        $allowed_types = ['application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Only PDF files are allowed');
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/quotations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $quotation_filename = 'quotation_' . time() . '_' . $file['name'];
        $filepath = $upload_dir . $quotation_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Error uploading quotation file');
        }
    }

    $stmt->execute([
        $appointment['client_id'],
        $appointment['appointment_id'],
        $appointment['service'],
        $appointment['date'],
        $appointment['time'],
        $start_date,
        $end_date,
        isset($_POST['notes']) ? $_POST['notes'] : '',
        $quotation_filename
    ]);

    $project_id = $pdo->lastInsertId();

    // Decode and validate personnel array
    $personnel = json_decode($_POST['personnel'], true);
    if (!is_array($personnel) || empty($personnel)) {
        throw new Exception('Invalid personnel data');
    }

    // Insert project assignments
    $stmt = $pdo->prepare("
        INSERT INTO project_personnel (
            project_id,
            user_id,
            assigned_at
        ) VALUES (?, ?, NOW())
    ");

    foreach ($personnel as $user_id) {
        $stmt->execute([$project_id, $user_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Project created and personnel assigned successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in add_project.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 