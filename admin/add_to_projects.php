<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get appointment data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment data']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Insert into projects table
    $query = "
        INSERT INTO projects (
            appointment_id,
            client_id,
            service,
            date,
            time,
            status,
            progress,
            notes
        ) VALUES (
            :appointment_id,
            :client_id,
            :service,
            :date,
            :time,
            'in_progress',
            0,
            ''
        )
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':appointment_id' => $data['appointment_id'],
        ':client_id' => $data['client_id'],
        ':service' => $data['service'],
        ':date' => $data['date'],
        ':time' => $data['time']
    ]);

    // Update appointment status to 'in_project'
    $query = "UPDATE appointments SET status = 'in_project' WHERE appointment_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$data['appointment_id']]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Added to projects successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in add_to_projects.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error adding to projects: ' . $e->getMessage()]);
} 