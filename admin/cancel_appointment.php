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
    $pdo->beginTransaction();

    // Update appointment status
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'cancelled' 
        WHERE appointment_id = ?
    ");
    $stmt->execute([$_GET['id']]);

    // Get appointment and client details
    $stmt = $pdo->prepare("
        SELECT a.*, u.user_id, u.name as client_name 
        FROM appointments a 
        JOIN users u ON a.client_id = u.user_id 
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send notification to client
    $notification_stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id, recipient_id, type, reference_id, 
            title, message, is_read, created_at
        ) VALUES (
            ?, ?, 'appointment', ?, ?, ?, FALSE, NOW()
        )
    ");

    $title = "Appointment Cancelled";
    $message = "Your appointment for " . date('M d, Y', strtotime($appointment['date'])) . 
              " at " . date('h:i A', strtotime($appointment['time'])) . 
              " has been cancelled by the administrator.";

    $notification_stmt->execute([
        $_SESSION['user_id'],      // admin who cancelled
        $appointment['client_id'], // client to notify
        $appointment['appointment_id'],
        $title,
        $message
    ]);

    $pdo->commit();
    
    $_SESSION['success_message'] = "Appointment cancelled successfully.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = "Error cancelling appointment: " . $e->getMessage();
}

// Redirect back to appointments page
header('Location: appointments.php'); 