<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || !isset($data['email']) || !isset($data['phone']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, status, pin_code) 
        VALUES (?, ?, ?, ?, 'client', 'active', '1234')
    ");
    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'],
        password_hash($data['password'], PASSWORD_DEFAULT)
    ]);

    echo json_encode(['success' => true, 'message' => 'User added successfully']);

} catch (Exception $e) {
    error_log("Error in add_user.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error adding user: ' . $e->getMessage()]);
} 