<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the PIN from the request body
$data = json_decode(file_get_contents('php://input'), true);
$pin = $data['pin'] ?? '';

if (empty($pin) || strlen($pin) !== 4 || !is_numeric($pin)) {
    echo json_encode(['success' => false, 'message' => 'Invalid PIN format']);
    exit;
}

try {
    // Hash the PIN before storing
    $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
    
    // Update the user's PIN in the database
    $stmt = $pdo->prepare("UPDATE users SET pin_code = ? WHERE user_id = ?");
    $result = $stmt->execute([$hashedPin, $_SESSION['user_id']]);

    if ($result) {
        $_SESSION['pin_verified'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save PIN']);
    }
} catch (PDOException $e) {
    error_log("PIN setup error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 