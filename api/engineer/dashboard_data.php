<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an engineer
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'engineer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];

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

    // Get Total Projects (distinct projects where engineer is assigned tasks)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.project_id) as total_projects
        FROM projects p
        JOIN project_assignees pa ON p.project_id = pa.project_id
        WHERE pa.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $total_projects = $stmt->fetch(PDO::FETCH_ASSOC)['total_projects'];

    echo json_encode([
        'success' => true,
        'data' => [
            'completed_tasks' => (int)$completed_tasks,
            'pending_tasks' => (int)$pending_tasks,
            'total_projects' => (int)$total_projects
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in dashboard_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard data'
    ]);
} 