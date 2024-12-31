<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if all required fields are present
if (!isset($_POST['appointment_id']) || !isset($_POST['new_date']) || !isset($_POST['new_time'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Get the appointment to verify ownership and status
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND client_id = ?");
    $stmt->execute([$_POST['appointment_id'], $_SESSION['user_id']]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    if ($appointment['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending appointments can be rescheduled']);
        exit;
    }

    // Validate new date and time
    $newDateTime = new DateTime($_POST['new_date'] . ' ' . $_POST['new_time']);
    $now = new DateTime();

    if ($newDateTime <= $now) {
        echo json_encode(['success' => false, 'message' => 'Please select a future date and time']);
        exit;
    }

    // Update appointment date and time
    $stmt = $pdo->prepare("UPDATE appointments SET date = ?, time = ?, updated_at = NOW() WHERE appointment_id = ?");
    $stmt->execute([
        $_POST['new_date'],
        $_POST['new_time'],
        $_POST['appointment_id']
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'Appointment rescheduled successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in reschedule_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error rescheduling appointment']);
}
?> 