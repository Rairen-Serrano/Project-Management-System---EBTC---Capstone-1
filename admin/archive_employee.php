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
    // First check if the user exists and is not already archived
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

    if ($user['archived'] === 'Yes') {
        throw new Exception('User is already archived');
    }

    // Update user status to archived
    $stmt = $pdo->prepare("
        UPDATE users 
        SET archived = 'Yes', 
            archived_date = NOW(),
            status = 'inactive' 
        WHERE user_id = ? 
        AND role != 'client'
    ");
    
    $stmt->execute([$_POST['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee has been archived successfully'
        ]);
    } else {
        throw new Exception('Failed to archive employee');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 