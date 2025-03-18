<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an technician
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['task_id']) || !isset($data['picture_url'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Verify that the user is assigned to this task
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_assignees 
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$data['task_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this task']);
        exit;
    }

    // Convert the URL to a file path
    $picture_path = str_replace('../', '', $data['picture_url']);
    
    // Get the database record first
    $stmt = $pdo->prepare("
        SELECT picture_id, file_path 
        FROM task_pictures 
        WHERE task_id = ? AND file_path = ?
    ");
    $stmt->execute([$data['task_id'], $picture_path]);
    $picture = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$picture) {
        echo json_encode(['success' => false, 'message' => 'Picture not found in database']);
        exit;
    }
    
    // Delete from database first
    $stmt = $pdo->prepare("
        DELETE FROM task_pictures 
        WHERE picture_id = ?
    ");
    $stmt->execute([$picture['picture_id']]);

    // Then delete the physical file
    $file_path = '../../' . $picture['file_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 