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

if (empty($pin)) {
    echo json_encode(['success' => false, 'message' => 'PIN is required']);
    exit;
}

try {
    // Get the stored PIN for the current user
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Verify the PIN
    if (password_verify($pin, $user['pin_code'])) {
        $_SESSION['pin_verified'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
    }
} catch (PDOException $e) {
    error_log("PIN verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 