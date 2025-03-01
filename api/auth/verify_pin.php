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
    echo json_encode(['error' => 'Invalid PIN format']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $pin = $data['pin'];

    // Get the hashed PIN from database
    $stmt = $pdo->prepare("SELECT pin_code FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pin, $user['pin_code'])) {
        // PIN is correct
        $_SESSION['pin_verified'] = true;
        echo json_encode(['success' => true]);
    } else {
        // PIN is incorrect
        http_response_code(401);
        echo json_encode(['error' => 'Invalid PIN']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 