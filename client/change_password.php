<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if all required fields are present
if (!isset($_POST['current_password']) || !isset($_POST['new_password']) || !isset($_POST['confirm_password'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

try {
    // Get user's information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Verify current password
    if (!password_verify($_POST['current_password'], $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    // Validate new password
    if ($_POST['new_password'] !== $_POST['confirm_password']) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        exit;
    }

    if (strlen($_POST['new_password']) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long']);
        exit;
    }

    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([
        password_hash($_POST['new_password'], PASSWORD_DEFAULT),
        $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);

} catch (Exception $e) {
    error_log("Error in change_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 