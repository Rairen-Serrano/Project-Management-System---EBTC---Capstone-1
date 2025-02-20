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
    // Update user status to active and remove archived status
    $stmt = $pdo->prepare("
        UPDATE users 
        SET archived = 'No', 
            archived_date = NULL,
            status = 'active' 
        WHERE user_id = ? 
        AND role != 'client'
    ");
    
    $stmt->execute([$_POST['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee has been restored successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found or cannot be restored'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error restoring employee: ' . $e->getMessage()
    ]);
} 