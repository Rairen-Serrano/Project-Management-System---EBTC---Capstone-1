<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing task ID']);
    exit;
}

try {
    $task_id = $_GET['task_id'];
    
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
    
    // Get check-in time
    $stmt = $pdo->prepare("
        SELECT check_in_time 
        FROM task_check_ins 
        WHERE task_id = ? AND user_id = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    $check_in = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get uploaded pictures
    $stmt = $pdo->prepare("
        SELECT 
            file_path as url,
            uploaded_at
        FROM task_pictures 
        WHERE task_id = ?
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$task_id]);
    $pictures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format picture URLs
    foreach ($pictures as &$picture) {
        $picture['url'] = '../' . $picture['url'];
        $picture['uploaded_at'] = date('M j, Y g:i A', strtotime($picture['uploaded_at']));
    }

    // Get team members completion status
    $stmt = $pdo->prepare("
        SELECT 
            u.name,
            COALESCE(tcs.completed, FALSE) as completed,
            tcs.completed_at
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.user_id
        LEFT JOIN task_completion_status tcs ON ta.task_id = tcs.task_id AND ta.user_id = tcs.user_id
        WHERE ta.task_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$task_id]);
    $assignees_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'check_in_time' => $check_in ? $check_in['check_in_time'] : null,
        'pictures' => $pictures,
        'assignees_status' => $assignees_status
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 