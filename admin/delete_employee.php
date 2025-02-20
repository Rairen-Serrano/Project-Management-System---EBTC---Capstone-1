<?php
session_start();
require_once '../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    // First check if the user exists and is archived
    $stmt = $pdo->prepare("
        SELECT archived 
        FROM users 
        WHERE user_id = ? 
        AND role != 'client'
    ");
    
    $stmt->execute([$_POST['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    if ($user['archived'] !== 'Yes') {
        throw new Exception('Only archived employees can be deleted');
    }

    // Delete the user from the database
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role != 'client'");
    $stmt->execute([$_POST['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee has been permanently deleted'
        ]);
    } else {
        throw new Exception('Failed to delete employee');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 