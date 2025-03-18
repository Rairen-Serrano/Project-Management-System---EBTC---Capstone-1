<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'project_manager') {
        throw new Exception('Unauthorized access');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['pin']) || strlen($data['pin']) !== 4 || !is_numeric($data['pin'])) {
        throw new Exception('Invalid PIN format');
    }

    // Hash the PIN before storing
    $hashed_pin = password_hash($data['pin'], PASSWORD_DEFAULT);

    // Update user's PIN
    $stmt = $pdo->prepare("
        UPDATE users 
        SET pin = ?, pin_set = TRUE 
        WHERE user_id = ? 
        AND role = 'project_manager'
    ");
    
    if ($stmt->execute([$hashed_pin, $_SESSION['user_id']])) {
        $_SESSION['needs_pin_setup'] = false;
        $_SESSION['pin_verified'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => 'PIN set successfully'
        ]);
    } else {
        throw new Exception('Failed to set PIN');
    }

} catch (Exception $e) {
    error_log("PIN setup error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 