<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get the appointment data first
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$data['appointment_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Insert into archived_appointments
    $stmt = $pdo->prepare("
        INSERT INTO archived_appointments (
            appointment_id, client_id, service, date, time, 
            status, created_at, archived_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $appointment['appointment_id'],
        $appointment['client_id'],
        $appointment['service'],
        $appointment['date'],
        $appointment['time'],
        $appointment['status'],
        $appointment['created_at']
    ]);

    // Update status in appointments table
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'archived' WHERE appointment_id = ?");
    $stmt->execute([$data['appointment_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Appointment archived successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in archive_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error archiving appointment: ' . $e->getMessage()]);
}
?> 