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

if (!isset($data['task_id']) || !isset($data['check_in_time'])) {
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
    
    // Record check-in time
    $stmt = $pdo->prepare("
        INSERT INTO task_check_ins (
            task_id,
            user_id,
            check_in_time,
            created_at
        ) VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $data['task_id'],
        $_SESSION['user_id'],
        $data['check_in_time']
    ]);
    
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 