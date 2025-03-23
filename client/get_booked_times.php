<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
error_log("get_booked_times.php started");

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    error_log("Unauthorized access attempt in get_booked_times.php");
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    error_log("Received data: " . print_r($data, true));

    $date = $data['date'] ?? '';
    $currentAppointmentId = $data['appointment_id'] ?? '';

    if (empty($date)) {
        throw new Exception('Date is required');
    }

    error_log("Fetching current appointment time for ID: " . $currentAppointmentId);
    
    // Fixed SQL query - removed problematic quotes around alias
    $stmt = $pdo->prepare("
        SELECT 
            date,
            TIME_FORMAT(time, '%H:%i') as time_formatted
        FROM appointments 
        WHERE appointment_id = ?
    ");
    $stmt->execute([$currentAppointmentId]);
    $currentAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentAppointment) {
        $currentAppointment['appointment_date'] = date('Y-m-d', strtotime($currentAppointment['date']));
        $currentAppointment['current_time'] = $currentAppointment['time_formatted'];
    }
    
    error_log("Current appointment details: " . print_r($currentAppointment, true));

    // Get all booked time slots for the selected date
    $stmt = $pdo->prepare("
        SELECT TIME_FORMAT(time, '%H:%i') as booked_time 
        FROM appointments 
        WHERE date = ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$date]);
    $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    error_log("Booked times: " . print_r($bookedTimes, true));

    $response = [
        'success' => true,
        'bookedTimes' => $bookedTimes,
        'currentTime' => $currentAppointment['current_time'] ?? null,
        'currentDate' => $currentAppointment['appointment_date'] ?? null
    ];
    
    error_log("Sending response: " . print_r($response, true));
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_booked_times.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 