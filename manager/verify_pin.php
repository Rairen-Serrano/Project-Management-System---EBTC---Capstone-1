<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

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

    // First try direct comparison for non-hashed PINs
    if ($_POST['pin'] === $user['pin_code']) {
        $_SESSION['pin_verified'] = true;
        echo json_encode(['success' => true, 'message' => 'PIN verified successfully']);
    } 
    // If direct comparison fails, try password_verify for hashed PINs
    else if (password_verify($_POST['pin'], $user['pin_code'])) {
        $_SESSION['pin_verified'] = true;
        echo json_encode(['success' => true, 'message' => 'PIN verified successfully']);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN']);
    }

} catch (Exception $e) {
    error_log("Error in verify_pin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error verifying PIN']);
} 