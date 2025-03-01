<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];

    // Get task statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE ta.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total projects
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.project_id) as total_projects
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE ta.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $project_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'active_tasks' => $task_stats['total_tasks'] - $task_stats['completed_tasks'],
        'completed_tasks' => $task_stats['completed_tasks'],
        'pending_tasks' => $task_stats['pending_tasks'],
        'total_projects' => $project_stats['total_projects']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 