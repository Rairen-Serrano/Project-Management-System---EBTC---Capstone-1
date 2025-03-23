<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$projectId = $data['project_id'] ?? null;
$userId = $data['user_id'] ?? null;

if (!$projectId || !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID and User ID are required']);
    exit;
}

try {
    // Check if the assignment already exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM project_assignees 
        WHERE project_id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $userId]);
    
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'User is already assigned to this project']);
        exit;
    }

    // Add the assignment
    $stmt = $pdo->prepare("
        INSERT INTO project_assignees (project_id, user_id, assigned_date) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$projectId, $userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Team member assigned successfully'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} 