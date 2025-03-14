<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is a worker
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get pending tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_tasks 
        FROM tasks t 
        JOIN task_assignees ta ON t.task_id = ta.task_id 
        WHERE ta.user_id = ? AND t.status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['pending_tasks'];

    // Get completed tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_tasks 
        FROM tasks t 
        JOIN task_assignees ta ON t.task_id = ta.task_id 
        WHERE ta.user_id = ? AND t.status = 'completed'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completed_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['completed_tasks'];

    // Get assigned projects count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.project_id) as assigned_projects
        FROM tasks t 
        JOIN task_assignees ta ON t.task_id = ta.task_id 
        WHERE ta.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $assigned_projects = $stmt->fetch(PDO::FETCH_ASSOC)['assigned_projects'];

    echo json_encode([
        'success' => true,
        'stats' => [
            'pending_tasks' => $pending_tasks,
            'completed_tasks' => $completed_tasks,
            'assigned_projects' => $assigned_projects
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 