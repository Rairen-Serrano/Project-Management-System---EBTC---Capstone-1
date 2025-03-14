<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if project_id is provided
if (!isset($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

try {
    // Update project status to completed
    $stmt = $pdo->prepare("UPDATE projects SET status = 'completed' WHERE project_id = ?");
    $result = $stmt->execute([$_POST['project_id']]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update project status']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 