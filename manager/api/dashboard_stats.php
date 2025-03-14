<?php
session_start();
require_once '../../dbconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get active tasks count and completion rate
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as active_tasks,
            ROUND(
                (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0) / 
                COUNT(*)
            ) as completion_rate
        FROM tasks t
        WHERE t.due_date >= CURDATE()
    ");
    $stmt->execute();
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get upcoming deadlines
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as upcoming_deadlines,
            MIN(due_date) as next_deadline
        FROM tasks
        WHERE status != 'completed'
        AND due_date >= CURDATE()
    ");
    $stmt->execute();
    $deadline_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'active_tasks' => $task_stats['active_tasks'],
        'completion_rate' => $task_stats['completion_rate'],
        'upcoming_deadlines' => $deadline_stats['upcoming_deadlines'],
        'next_deadline' => $deadline_stats['next_deadline']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard statistics: ' . $e->getMessage()
    ]);
} 