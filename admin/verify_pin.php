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

if (!isset($data['pin'])) {
    echo json_encode(['success' => false, 'message' => 'PIN is required']);
    exit;
}

try {
    // Get the admin's PIN from the database
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Verify PIN
    if ($data['pin'] === $user['pin_code']) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN']);
    }

} catch (Exception $e) {
    error_log("Error in verify_pin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error verifying PIN']);
} 