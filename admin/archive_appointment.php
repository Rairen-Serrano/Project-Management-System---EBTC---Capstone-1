<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = 'Appointment ID is required';
    header('Location: appointments.php');
    exit;
}

try {
    // Get appointment details first to verify it's cancelled
    $stmt = $pdo->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$_GET['id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    if ($appointment['status'] !== 'cancelled') {
        throw new Exception('Only cancelled appointments can be archived');
    }

    // Update the archived status to 'Yes'
    $stmt = $pdo->prepare("UPDATE appointments SET archived = 'Yes' WHERE appointment_id = ?");
    $stmt->execute([$_GET['id']]);

    $_SESSION['success_message'] = 'Appointment has been archived successfully';
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

// Redirect back to appointments page
header('Location: appointments.php');
?> 