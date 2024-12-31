<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if PIN is provided
if (!isset($_POST['pin'])) {
    echo json_encode(['success' => false, 'message' => 'PIN is required']);
    exit;
}

try {
    // Get user's PIN
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Verify PIN
    if ($_POST['pin'] === $user['pin_code']) {
        echo json_encode(['success' => true, 'message' => 'PIN verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN']);
    }

} catch (Exception $e) {
    error_log("Error in verify_pin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error verifying PIN']);
}
?> 