<?php
// Prevent any HTML output from error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON content type header first
header('Content-Type: application/json');

session_start();
require_once '../dbconnect.php';

// Log the request
error_log('Cancel appointment request received: ' . print_r($_POST, true));

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if appointment ID is provided
if (!isset($_POST['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit;
}

// Check for PIN
if (!isset($_POST['pin'])) {
    echo json_encode(['success' => false, 'message' => 'PIN is required']);
    exit;
}

try {
    // Verify PIN
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($_POST['pin'], $user['pin_code'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
        exit;
    }

    // Get the appointment to verify ownership and status
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND client_id = ?");
    $stmt->execute([$_POST['appointment_id'], $_SESSION['user_id']]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    if ($appointment['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending appointments can be cancelled']);
        exit;
    }

    error_log('Attempting to cancel appointment: ' . $_POST['appointment_id']);

    // Update appointment status
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE appointment_id = ?");
    $stmt->execute([$_POST['appointment_id']]);

    error_log('Appointment cancellation result: ' . ($stmt->rowCount() > 0 ? 'success' : 'failed'));

    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);

} catch (Exception $e) {
    error_log('Error in cancel_appointment.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while cancelling the appointment']);
}
?> 