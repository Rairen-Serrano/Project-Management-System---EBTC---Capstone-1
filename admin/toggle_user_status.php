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

if (!isset($data['user_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Update user status
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'client'");
    $stmt->execute([
        $data['status'],
        $data['user_id']
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'User status updated successfully',
        'new_status' => $data['status']
    ]);

} catch (Exception $e) {
    error_log("Error in toggle_user_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating user status: ' . $e->getMessage()]);
} 