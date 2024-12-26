<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Appointment ID is required']);
    exit;
}

try {
    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT appointment_id, service, date, time, status, created_at
        FROM appointments
        WHERE appointment_id = ? AND client_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        echo json_encode(['error' => 'Appointment not found']);
        exit;
    }

    // Format the date and time
    $date = new DateTime($appointment['date']);
    $time = new DateTime($appointment['time']);
    $created = new DateTime($appointment['created_at']);
    
    // Determine status class
    $statusClass = '';
    switch($appointment['status']) {
        case 'pending':
            $statusClass = 'warning';
            break;
        case 'confirmed':
            $statusClass = 'success';
            break;
        case 'cancelled':
            $statusClass = 'danger';
            break;
        case 'completed':
            $statusClass = 'info';
            break;
    }

    // Prepare response data
    $response = [
        'date' => $date->format('M d, Y'),
        'time' => $time->format('h:i A'),
        'service' => htmlspecialchars($appointment['service']),
        'status' => ucfirst($appointment['status']),
        'statusClass' => $statusClass,
        'created_at' => $created->format('M d, Y h:i A')
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 