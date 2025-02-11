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
    // Hash the PIN before saving
    $hashed_pin = password_hash($_POST['pin'], PASSWORD_DEFAULT);
    
    // Update the user's PIN in the database
    $stmt = $pdo->prepare("UPDATE users SET pin_code = ? WHERE user_id = ?");
    $stmt->execute([$hashed_pin, $_SESSION['user_id']]);

    // Set the session variables
    $_SESSION['pin_verified'] = true;
    unset($_SESSION['needs_pin_setup']);

    echo json_encode(['success' => true, 'message' => 'PIN set successfully']);
} catch (Exception $e) {
    error_log("Error in setup_pin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error setting PIN']);
} 