<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['pin'])) {
        throw new Exception('PIN is required');
    }

    // Get user's PIN from database
    $stmt = $pdo->prepare("
        SELECT pin, pin_set 
        FROM users 
        WHERE user_id = ? 
        AND role = 'project_manager'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Check if PIN needs to be set up
    if (!$user['pin_set']) {
        $_SESSION['needs_pin_setup'] = true;
        echo json_encode([
            'success' => false,
            'error' => 'PIN setup required'
        ]);
        exit;
    }

    // Verify PIN
    if (password_verify($data['pin'], $user['pin'])) {
        $_SESSION['pin_verified'] = true;
        echo json_encode([
            'success' => true,
            'message' => 'PIN verified successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid PIN'
        ]);
    }

} catch (Exception $e) {
    error_log("PIN verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 