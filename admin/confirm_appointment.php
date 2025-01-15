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
    // Update appointment status to confirmed
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ?");
    $result = $stmt->execute([$data['appointment_id']]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Appointment confirmed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to confirm appointment']);
    }

} catch (Exception $e) {
    error_log("Error in confirm_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error confirming appointment: ' . $e->getMessage()]);
}
?> 