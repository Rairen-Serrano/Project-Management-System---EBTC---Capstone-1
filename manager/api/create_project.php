<?php
session_start();
require_once '../../dbconnect.php';

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, get the client_id and service from the appointment
    $stmt = $pdo->prepare("SELECT client_id, service FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$_POST['appointment_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Format dates to ensure proper MySQL date format
    $start_date = date('Y-m-d', strtotime($_POST['start_date']));
    $end_date = date('Y-m-d', strtotime($_POST['end_date']));

    // Validate dates
    if (!$start_date || $start_date === '1970-01-01' || $start_date === '-0001-11-30') {
        throw new Exception('Invalid start date format');
    }
    if (!$end_date || $end_date === '1970-01-01' || $end_date === '-0001-11-30') {
        throw new Exception('Invalid end date format');
    }

    // Create project with client_id and service
    $stmt = $pdo->prepare("
        INSERT INTO projects (
            appointment_id,
            client_id, 
            service,
            start_date, 
            end_date, 
            notes, 
            quotation_file,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ongoing', NOW())
    ");

    $stmt->execute([
        $_POST['appointment_id'],
        $appointment['client_id'],
        $appointment['service'],    // Add service from appointment
        $start_date,
        $end_date,
        $_POST['notes'],
        isset($_FILES['quotation_file']) ? $_FILES['quotation_file']['name'] : null
    ]);

    $project_id = $pdo->lastInsertId();

    // Handle file upload if exists
    if (isset($_FILES['quotation_file']) && $_FILES['quotation_file']['error'] == 0) {
        $upload_dir = '../../uploads/quotations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        move_uploaded_file($_FILES['quotation_file']['tmp_name'], $upload_dir . $_FILES['quotation_file']['name']);
    }

    // Assign personnel to project
    if (isset($_POST['personnel'])) {
        $personnel = json_decode($_POST['personnel']);
        $assign_stmt = $pdo->prepare("
            INSERT INTO project_assignees (project_id, user_id, assigned_date)
            VALUES (?, ?, NOW())
        ");

        foreach ($personnel as $user_id) {
            $assign_stmt->execute([$project_id, $user_id]);
        }
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Project created successfully',
        'project_id' => $project_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in create_project.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating project: ' . $e->getMessage()
    ]);
}
?> 