<?php
session_start();
require_once 'dbconnect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $date = $data['date'] ?? '';

    if (empty($date)) {
        throw new Exception('Date is required');
    }

    // Get all booked time slots for the selected date
    $stmt = $pdo->prepare("
        SELECT TIME_FORMAT(time, '%H:%i') as booked_time 
        FROM appointments 
        WHERE date = ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$date]);
    $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Return the array of booked times
    echo json_encode([
        'success' => true,
        'bookedTimes' => $bookedTimes
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 