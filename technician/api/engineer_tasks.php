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

    // Get tasks assigned to the technician
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            p.service,
            p.project_name,
            tc.category_name
        FROM tasks t
        JOIN task_assignees ta ON t.task_id = ta.task_id
        JOIN projects p ON t.project_id = p.project_id
        LEFT JOIN task_categories tc ON t.category_id = tc.category_id
        WHERE ta.user_id = ?
        ORDER BY 
            CASE 
                WHEN t.status = 'pending' THEN 0
                ELSE 1
            END,
            t.due_date ASC
    ");
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['tasks' => $tasks]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 