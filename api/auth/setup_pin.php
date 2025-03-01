<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['pin']) || strlen($data['pin']) !== 4 || !is_numeric($data['pin'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid PIN format. PIN must be 4 digits.']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $pin = $data['pin'];
    
    // Hash the PIN using password_hash
    $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);

    // Update user's PIN with hashed value
    $stmt = $pdo->prepare("UPDATE users SET pin_code = ? WHERE user_id = ?");
    $stmt->execute([$hashed_pin, $user_id]);

    // Set session variable to indicate PIN is verified
    $_SESSION['pin_verified'] = true;
    unset($_SESSION['needs_pin_setup']);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 