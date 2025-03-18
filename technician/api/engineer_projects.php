<?php
session_start();
require_once '../../dbconnect.php';

// Check if user is logged in and is an technician
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'technician') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];

    // Get projects and their progress
    $stmt = $pdo->prepare("
        WITH ProjectTasks AS (
            SELECT 
                p.project_id,
                p.project_name,
                p.service,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
            FROM tasks t
            JOIN task_assignees ta ON t.task_id = ta.task_id
            JOIN projects p ON t.project_id = p.project_id
            WHERE ta.user_id = ?
            GROUP BY p.project_id, p.project_name, p.service
        )
        SELECT 
            project_id,
            project_name,
            service,
            ROUND((completed_tasks / total_tasks) * 100) as progress
        FROM ProjectTasks
        ORDER BY progress DESC
    ");
    $stmt->execute([$user_id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['projects' => $projects]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 