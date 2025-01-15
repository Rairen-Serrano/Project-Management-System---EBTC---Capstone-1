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

if (!isset($data['user_id']) || !isset($data['name']) || !isset($data['email']) || !isset($data['phone'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Check if email already exists for another user
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? AND role = 'client'");
    $stmt->execute([$data['email'], $data['user_id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    // Update user
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ? AND role = 'client'");
    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'],
        $data['user_id']
    ]);

    echo json_encode(['success' => true, 'message' => 'User updated successfully']);

} catch (Exception $e) {
    error_log("Error in update_user.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
} 