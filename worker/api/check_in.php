<?php
session_start();
require_once '../../dbconnect.php';

// Ensure no HTML errors are output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set proper JSON headers
header('Content-Type: application/json');

// Check if user is logged in and is a worker
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
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
    
    // Record check-in time with current timestamp
    $stmt = $pdo->prepare("
        INSERT INTO task_check_ins (
            task_id,
            user_id,
            check_in_time,
            created_at
        ) VALUES (?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $data['task_id'],
        $_SESSION['user_id']
    ]);
    
    // Return success with formatted time
    echo json_encode([
        'success' => true,
        'message' => 'Check-in successful',
        'check_in_time' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("Check-in error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} 