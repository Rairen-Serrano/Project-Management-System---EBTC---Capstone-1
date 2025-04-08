<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$appointmentId = $_POST['appointment_id'] ?? '';
$newDate = $_POST['new_date'] ?? '';
$newTime = $_POST['new_time'] ?? '';
$pin = $_POST['pin'] ?? '';

// Check if all required fields are present
if (empty($appointmentId) || empty($newDate) || empty($newTime) || empty($pin)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Verify PIN
$stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!password_verify($pin, $user['pin_code'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
    exit;
}

try {
    // Get the appointment to verify ownership and status
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND client_id = ?");
    $stmt->execute([$appointmentId, $_SESSION['user_id']]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    if ($appointment['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending appointments can be rescheduled']);
        exit;
    }

    // Check if the time slot is already booked by another appointment
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE date = ? 
        AND time = ? 
        AND appointment_id != ? 
        AND status != 'cancelled'
        AND client_id != ?
    ");
    $stmt->execute([$newDate, $newTime, $appointmentId, $_SESSION['user_id']]);
    $exists = $stmt->fetchColumn();

    if ($exists > 0) {
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
        exit;
    }

    // Validate new date and time
    $newDateTime = new DateTime($newDate . ' ' . $newTime);
    $now = new DateTime();

    if ($newDateTime <= $now) {
        echo json_encode(['success' => false, 'message' => 'Please select a future date and time']);
        exit;
    }

    // Update appointment
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET date = ?, 
            time = ?, 
            updated_at = NOW() 
        WHERE appointment_id = ?
    ");
    $stmt->execute([$newDate, $newTime, $appointmentId]);

    echo json_encode([
        'success' => true, 
        'message' => 'Appointment rescheduled successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in reschedule_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error rescheduling appointment']);
}
?> 