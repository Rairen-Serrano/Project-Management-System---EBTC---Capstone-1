<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a worker
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];

    // Get Active Tasks (In Progress)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_tasks
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE ta.user_id = ? 
        AND t.status = 'in_progress'
    ");
    $stmt->execute([$user_id]);
    $active_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['active_tasks'];

    // Get Completed Tasks
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_tasks
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE ta.user_id = ? 
        AND t.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $completed_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['completed_tasks'];

    // Get Pending Tasks
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_tasks
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE ta.user_id = ? 
        AND t.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['pending_tasks'];

    // Get Total Projects (distinct projects where worker is assigned tasks)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.project_id) as total_projects
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        WHERE ta.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $total_projects = $stmt->fetch(PDO::FETCH_ASSOC)['total_projects'];

    echo json_encode([
        'success' => true,
        'data' => [
            'active_tasks' => $active_tasks,
            'completed_tasks' => $completed_tasks,
            'pending_tasks' => $pending_tasks,
            'total_projects' => $total_projects
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in dashboard_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard data'
    ]);
} 