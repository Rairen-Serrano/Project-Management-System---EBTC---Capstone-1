<?php
session_start();
require_once '../dbconnect.php';

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
        echo json_encode(['success' => false, 'message' => 'Only pending appointments can be cancelled']);
        exit;
    }

    // Update appointment status to cancelled and update timestamp
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE appointment_id = ?");
    $stmt->execute([$_POST['appointment_id']]);

    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 