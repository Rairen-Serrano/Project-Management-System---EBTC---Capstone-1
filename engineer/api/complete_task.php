<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing task ID']);
    exit;
}

try {
    $task_id = $data['task_id'];
    
    // Verify that the user is assigned to this task
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_assignees 
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this task']);
        exit;
    }
    
    // Check if task has check-in record
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_check_ins 
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You must check in before completing the task']);
        exit;
    }
    
    // Check if task has at least one picture
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_pictures 
        WHERE task_id = ?
    ");
    $stmt->execute([$task_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You must upload at least one picture before completing the task']);
        exit;
    }
    
    // Update task status
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET 
            status = 'completed',
            completed_at = NOW(),
            completed_by = ?,
            updated_at = NOW()
        WHERE task_id = ?
    ");
    
    $stmt->execute([$_SESSION['user_id'], $task_id]);
    
    // Log the completion
    $stmt = $pdo->prepare("
        INSERT INTO task_history (
            task_id,
            user_id,
            action,
            details,
            created_at
        ) VALUES (?, ?, 'completed', ?, NOW())
    ");
    
    $stmt->execute([
        $task_id,
        $_SESSION['user_id'],
        json_encode(['status' => 'completed'])
    ]);
    
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 