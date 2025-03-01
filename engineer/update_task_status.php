<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    header('Location: ../admin_login.php');
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tasks.php');
    exit;
}

// Get POST data
$task_id = $_POST['task_id'] ?? null;
$current_status = $_POST['current_status'] ?? null;
$new_status = $_POST['new_status'] ?? null;

if (!$task_id || (!$current_status && !$new_status)) {
    $_SESSION['error'] = 'Invalid request data';
    header('Location: tasks.php');
    exit;
}

try {
    // Verify that the user is assigned to this task
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM task_assignees 
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'You are not assigned to this task';
        header('Location: tasks.php');
        exit;
    }
    
    // If new_status is not provided, toggle between completed and pending
    if (!$new_status) {
        $new_status = $current_status === 'completed' ? 'pending' : 'completed';
    }
    
    // Update task status
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET 
            status = ?,
            updated_at = NOW()
        WHERE task_id = ?
    ");
    $stmt->execute([$new_status, $task_id]);
    
    // Log the status change
    $stmt = $pdo->prepare("
        INSERT INTO task_history (
            task_id,
            user_id,
            action,
            details,
            created_at
        ) VALUES (?, ?, 'status_update', ?, NOW())
    ");
    $stmt->execute([
        $task_id,
        $_SESSION['user_id'],
        json_encode(['old_status' => $current_status, 'new_status' => $new_status])
    ]);
    
    $_SESSION['success'] = 'Task status updated successfully';

} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to update task status';
}

// Redirect back to tasks page with filters if they exist
$status_filter = $_GET['status'] ?? 'all';
$project_filter = $_GET['project_id'] ?? 'all';
header("Location: tasks.php?status=$status_filter&project_id=$project_filter");
exit; 