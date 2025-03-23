<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $pin = $data['pin'] ?? '';

    if (empty($pin) || strlen($pin) !== 4 || !is_numeric($pin)) {
        echo json_encode(['success' => false, 'message' => 'Invalid PIN format']);
        exit;
    }

    // Hash the PIN
    $hashedPin = password_hash($pin, PASSWORD_DEFAULT);

    // Update user's PIN and set first_login to 0
    $stmt = $pdo->prepare("
        UPDATE users 
        SET pin_code = ?, 
            first_login = 0 
        WHERE user_id = ? 
        AND first_login = 1
    ");
    
    $result = $stmt->execute([$hashedPin, $_SESSION['user_id']]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'PIN already set']);
    }

} catch (Exception $e) {
    error_log('PIN setup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
} 